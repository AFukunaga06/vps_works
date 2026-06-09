<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function lc_session(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function lc_current_user(): ?array {
    lc_session();
    return $_SESSION[LC_SESSION_KEY] ?? null;
}

function lc_require_login(): array {
    $u = lc_current_user();
    if (!$u) {
        header('Location: ' . LC_BASE_URL . '/login.php');
        exit;
    }
    return $u;
}

function lc_require_admin(): array {
    $u = lc_require_login();
    if ($u['role'] !== 'admin') {
        header('Location: ' . LC_BASE_URL . '/index.php');
        exit;
    }
    return $u;
}

function lc_require_super_admin(): array {
    $u = lc_require_login();
    if (empty($u['is_super_admin'])) {
        http_response_code(403);
        exit('運営管理者のみアクセス可能です。');
    }
    return $u;
}

function lc_login(string $login_id, string $pass): bool {
    // マルチテナント環境では (tenant_id, login_id) で一意。
    // ログインは login_id + パスワードでテナントを横断検索し、合致したユーザーのテナントに入る。
    $stmt = get_db()->prepare("SELECT u.*, t.status AS tenant_status
                               FROM lc_users u
                               JOIN lc_tenants t ON t.id = u.tenant_id
                               WHERE u.login_id=? AND u.is_active=1
                               ORDER BY u.id ASC");
    $stmt->execute([$login_id]);
    $candidates = $stmt->fetchAll();
    foreach ($candidates as $u) {
        if (password_verify($pass, $u['password_hash'])) {
            if (in_array($u['tenant_status'], ['suspended','canceled'], true)) return false;
            lc_session();
            $_SESSION[LC_SESSION_KEY] = [
                'id'             => $u['id'],
                'tenant_id'      => (int)$u['tenant_id'],
                'login_id'       => $u['login_id'],
                'name'           => $u['name'],
                'email'          => $u['email'],
                'role'           => $u['role'],
                'is_super_admin' => (int)($u['is_super_admin'] ?? 0),
            ];
            return true;
        }
    }
    return false;
}

function lc_logout(): void {
    lc_session();
    unset($_SESSION[LC_SESSION_KEY]);
    session_destroy();
}
