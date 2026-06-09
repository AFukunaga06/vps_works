<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ロック設定
define('LOGIN_MAX_ATTEMPTS', 5);   // 最大失敗回数
define('LOGIN_LOCK_MINUTES', 10);  // ロック時間（分）

function require_admin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION[ADMIN_SESSION_KEY])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function is_admin(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION[ADMIN_SESSION_KEY]);
}

/**
 * IDとパスワードを検証し、一致すればユーザー情報を返す。失敗時はfalse。
 * パスワードはDBのハッシュ値と照合する。DBになければconfigの値をフォールバック。
 */
function verify_admin_credentials(string $user_id, string $password): array|false {
    $users = ADMIN_USERS;
    if (!isset($users[$user_id])) return false;

    $db   = get_db();
    $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = 'admin_password'");
    $stmt->execute();
    $hash = $stmt->fetchColumn();

    if ($hash && password_verify($password, $hash)) {
        return $users[$user_id];
    }
    // DBにハッシュがなければconfigのパスワードで照合（移行期フォールバック）
    if (!$hash && $users[$user_id]['password'] === $password) {
        return $users[$user_id];
    }
    return false;
}

/**
 * 6桁のランダムコードを生成してセッションに保存する
 */
function generate_2fa_code(string $user_id): string {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['2fa_code']    = $code;
    $_SESSION['2fa_user_id'] = $user_id;
    $_SESSION['2fa_expires'] = time() + TWO_FA_EXPIRE;
    return $code;
}

/**
 * 入力されたコードをセッションのコードと照合する
 */
function verify_2fa_code(string $input): bool {
    if (empty($_SESSION['2fa_code']) || empty($_SESSION['2fa_expires'])) {
        return false;
    }
    if (time() > $_SESSION['2fa_expires']) {
        unset($_SESSION['2fa_code'], $_SESSION['2fa_user_id'], $_SESSION['2fa_expires']);
        return false;
    }
    return hash_equals($_SESSION['2fa_code'], $input);
}

/**
 * 2FAを完了してログインセッションを確立する
 */
function complete_2fa_login(): void {
    session_regenerate_id(true);
    $_SESSION[ADMIN_SESSION_KEY] = true;
    unset($_SESSION['2fa_code'], $_SESSION['2fa_user_id'], $_SESSION['2fa_expires']);
}

/**
 * ロック中かどうかを確認する（ユーザーID or IPアドレス）
 * ロック中なら1を返す。問題なければ0を返す。
 * ウィンドウ計算はMySQLのNOW()で統一（タイムゾーンずれ対策）
 */
function get_lockout_seconds(string $identifier, string $type): int {
    $db   = get_db();
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE identifier = ? AND type = ?
         AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    $stmt->execute([$identifier, $type, LOGIN_LOCK_MINUTES]);
    $count = (int)$stmt->fetchColumn();

    return $count >= LOGIN_MAX_ATTEMPTS ? 1 : 0;
}

/**
 * ログイン失敗を記録する
 */
function record_login_failure(string $user_id, string $ip): void {
    $db = get_db();
    $stmt = $db->prepare(
        "INSERT INTO login_attempts (identifier, type) VALUES (?, 'user'), (?, 'ip')"
    );
    $stmt->execute([$user_id, $ip]);
}

/**
 * ログイン成功時に失敗記録をリセットする
 */
function clear_login_attempts(string $user_id, string $ip): void {
    $db = get_db();
    $db->prepare(
        "DELETE FROM login_attempts WHERE (identifier = ? AND type = 'user')
         OR (identifier = ? AND type = 'ip')"
    )->execute([$user_id, $ip]);
}

/**
 * 秒数を「X分Y秒」形式に変換
 */
function format_lockout_time(int $seconds): string {
    $m = (int)floor($seconds / 60);
    $s = $seconds % 60;
    if ($m > 0 && $s > 0) return "{$m}分{$s}秒";
    if ($m > 0)            return "{$m}分";
    return "{$s}秒";
}

/**
 * パスワードリセット用トークンを生成してDBに保存する（有効期限30分）
 */
function generate_password_reset_token(): string {
    $token = bin2hex(random_bytes(32)); // 64文字
    $db    = get_db();
    $db->prepare(
        "INSERT INTO password_resets (token, expires_at) VALUES (?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))"
    )->execute([$token]);
    return $token;
}

/**
 * トークンを検証する。有効なら true、無効・期限切れ・使用済みなら false。
 */
function verify_reset_token(string $token): bool {
    $db   = get_db();
    $stmt = $db->prepare(
        "SELECT id FROM password_resets
         WHERE token = ? AND used = 0 AND expires_at > NOW()"
    );
    $stmt->execute([$token]);
    return (bool)$stmt->fetch();
}

/**
 * トークンを使用済みにしてパスワードを更新する
 */
function consume_reset_token_and_update_password(string $token, string $new_password): bool {
    if (!verify_reset_token($token)) return false;
    $db   = get_db();
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    // パスワード更新
    $db->prepare(
        "INSERT INTO settings (`key`, `value`) VALUES ('admin_password', ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    )->execute([$hash]);
    // トークンを使用済みに
    $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$token]);
    return true;
}
