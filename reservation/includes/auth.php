<?php
// 管理画面の認証 — 既存 lc_users を流用
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function start_session_if_needed(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(RESV_SESSION_NAME);
        session_start();
    }
}

function admin_login(string $login_id, string $password): bool {
    start_session_if_needed();
    // lawyer_crm の lc_users は (tenant_id, login_id) でユニーク。
    // 同一 login_id が複数テナントに存在し得るため、候補を全部試して
    // password_verify が通った最初のものでログイン。
    $stmt = db()->prepare(
        'SELECT u.id, u.tenant_id, u.login_id, u.email, u.password_hash, u.name,
                u.is_active, u.is_super_admin, t.status AS tenant_status
           FROM lc_users u
           JOIN lc_tenants t ON t.id = u.tenant_id
          WHERE u.login_id = ? AND u.is_active = 1
          ORDER BY u.id ASC'
    );
    $stmt->execute([$login_id]);
    foreach ($stmt->fetchAll() as $user) {
        if (in_array($user['tenant_status'], ['suspended','canceled'], true)) continue;
        if (!password_verify($password, $user['password_hash'])) continue;

        $_SESSION['resv_admin'] = [
            'id'             => (int)$user['id'],
            'tenant_id'      => (int)$user['tenant_id'],
            'login_id'       => $user['login_id'],
            'email'          => $user['email'],
            'name'           => $user['name'],
            'is_super_admin' => (int)($user['is_super_admin'] ?? 0),
            'logged_in_at'   => time(),
        ];
        return true;
    }
    return false;
}

function admin_logout(): void {
    start_session_if_needed();
    unset($_SESSION['resv_admin']);
    session_destroy();
}

function current_admin(): ?array {
    start_session_if_needed();
    return $_SESSION['resv_admin'] ?? null;
}

function require_admin(): array {
    $a = current_admin();
    if (!$a) {
        header('Location: login.php');
        exit;
    }
    return $a;
}

// 管理画面で使うテナントID（管理者本人の tenant_id を優先）
function admin_tenant_id(): int {
    $a = current_admin();
    return $a ? (int)$a['tenant_id'] : DEFAULT_TENANT_ID;
}
