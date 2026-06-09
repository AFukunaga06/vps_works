<?php
/**
 * テナント解決・スコープ制御ヘルパー
 *
 * 利用者ログイン時に lc_users.tenant_id をセッションに保存し、
 * 全クエリは lc_current_tenant_id() で取得した tenant_id でフィルタする。
 *
 * 使用例:
 *   $tid = lc_current_tenant_id();
 *   $stmt = get_db()->prepare("SELECT * FROM lc_clients WHERE tenant_id=? AND id=?");
 *   $stmt->execute([$tid, $id]);
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * 現在ログイン中ユーザーの tenant_id を返す。
 * 未ログインなら null。
 */
function lc_current_tenant_id(): ?int {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $u = $_SESSION[LC_SESSION_KEY] ?? null;
    return $u['tenant_id'] ?? null;
}

/**
 * 現在テナント情報をDBから取得（プラン・利用枠・ステータス含む）。
 * 1リクエスト内でキャッシュ。
 */
function lc_current_tenant(): ?array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $tid = lc_current_tenant_id();
    if (!$tid) return null;
    $stmt = get_db()->prepare("SELECT * FROM lc_tenants WHERE id=?");
    $stmt->execute([$tid]);
    $cache = $stmt->fetch() ?: null;
    return $cache;
}

/**
 * テナントがアクティブ（trial / active）であることを保証。
 * suspended / canceled の場合は停止画面へリダイレクト。
 */
function lc_require_active_tenant(): array {
    $t = lc_current_tenant();
    if (!$t) {
        header('Location: ' . LC_BASE_URL . '/login.php');
        exit;
    }
    // trial 期限切れチェック
    if ($t['status'] === 'trial' && !empty($t['trial_ends_at']) && strtotime($t['trial_ends_at']) < time()) {
        // trial 切れ → past_due に自動更新
        get_db()->prepare("UPDATE lc_tenants SET status='past_due' WHERE id=?")->execute([$t['id']]);
        $t['status'] = 'past_due';
    }
    if (in_array($t['status'], ['suspended', 'canceled'], true)) {
        header('Location: ' . LC_BASE_URL . '/lp/suspended.html');
        exit;
    }
    return $t;
}

/**
 * 利用枠チェック：現在のテナントが指定リソースを追加可能か判定。
 *  $resource: 'users' | 'clients' | 'cases'
 * 戻り値: ['ok'=>bool, 'limit'=>?int, 'used'=>int, 'plan'=>string]
 */
function lc_check_quota(string $resource): array {
    $t = lc_current_tenant();
    if (!$t) return ['ok' => false, 'limit' => 0, 'used' => 0, 'plan' => ''];

    $tid   = (int)$t['id'];
    $limit = null;
    $used  = 0;

    switch ($resource) {
        case 'users':
            $limit = (int)$t['max_users'];
            $used  = (int)get_db()->query("SELECT COUNT(*) FROM lc_users WHERE tenant_id={$tid} AND is_active=1")->fetchColumn();
            break;
        case 'clients':
            $limit = $t['max_clients'] !== null ? (int)$t['max_clients'] : null;
            $used  = (int)get_db()->query("SELECT COUNT(*) FROM lc_clients WHERE tenant_id={$tid}")->fetchColumn();
            break;
        case 'cases':
            $limit = $t['max_cases'] !== null ? (int)$t['max_cases'] : null;
            $used  = (int)get_db()->query("SELECT COUNT(*) FROM lc_cases WHERE tenant_id={$tid}")->fetchColumn();
            break;
    }

    $ok = ($limit === null) || ($used < $limit);
    return ['ok' => $ok, 'limit' => $limit, 'used' => $used, 'plan' => $t['plan']];
}

/**
 * プラン別の機能フラグ（feature flag）チェック。
 *   例: lc_feature_enabled('gcal') → Standard以上ならtrue
 */
function lc_feature_enabled(string $feature): bool {
    $t = lc_current_tenant();
    if (!$t) return false;

    // tenant.feature_flags JSON が優先（個別オーバーライド）
    if (!empty($t['feature_flags'])) {
        $flags = is_array($t['feature_flags']) ? $t['feature_flags'] : json_decode($t['feature_flags'], true);
        if (is_array($flags) && array_key_exists($feature, $flags)) return (bool)$flags[$feature];
    }

    // プラン別デフォルト
    $matrix = [
        'solo'       => ['gcal'=>false, 'audit'=>false, 'api'=>false, 'ip_restrict'=>false, 'role_based'=>false],
        'standard'   => ['gcal'=>true,  'audit'=>true,  'api'=>false, 'ip_restrict'=>false, 'role_based'=>true ],
        'pro'        => ['gcal'=>true,  'audit'=>true,  'api'=>true,  'ip_restrict'=>true,  'role_based'=>true ],
        'enterprise' => ['gcal'=>true,  'audit'=>true,  'api'=>true,  'ip_restrict'=>true,  'role_based'=>true ],
    ];
    $plan = $t['plan'] ?? 'solo';
    return $matrix[$plan][$feature] ?? false;
}

/**
 * 監査ログ記録（Standard以上で有効）。
 */
function lc_audit_log(string $action, string $target_type = '', ?int $target_id = null, $detail = null): void {
    if (!lc_feature_enabled('audit')) return;
    $tid = lc_current_tenant_id();
    if (!$tid) return;
    $u = $_SESSION[LC_SESSION_KEY] ?? null;
    try {
        $stmt = get_db()->prepare("INSERT INTO lc_audit_logs
            (tenant_id, user_id, action, target_type, target_id, detail, ip, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $tid, $u['id'] ?? null, $action, $target_type, $target_id,
            $detail !== null ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
        ]);
    } catch (Throwable $e) {
        error_log('[audit_log] ' . $e->getMessage());
    }
}

/**
 * プラン情報マスタ（料金・上限値）— signup.php / 管理画面で利用。
 */
function lc_plan_specs(): array {
    return [
        'solo' => [
            'label'   => 'Solo',
            'price_monthly' => 3300,
            'price_yearly'  => 33000,
            'max_users'   => 1,
            'max_clients' => 50,
            'max_cases'   => 50,
            'storage_mb'  => 5000,
        ],
        'standard' => [
            'label'   => 'Standard',
            'price_monthly' => 9800,
            'price_yearly'  => 98000,
            'max_users'   => 3,
            'max_clients' => null,
            'max_cases'   => null,
            'storage_mb'  => 50000,
        ],
        'pro' => [
            'label'   => 'Pro',
            'price_monthly' => 19800,
            'price_yearly'  => 198000,
            'max_users'   => 10,
            'max_clients' => null,
            'max_cases'   => null,
            'storage_mb'  => 500000,
        ],
        'enterprise' => [
            'label'   => 'Enterprise',
            'price_monthly' => null,
            'price_yearly'  => null,
            'max_users'   => 999,
            'max_clients' => null,
            'max_cases'   => null,
            'storage_mb'  => 5000000,
        ],
    ];
}
