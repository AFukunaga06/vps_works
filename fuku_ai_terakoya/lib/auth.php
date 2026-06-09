<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

function admin_login(string $username, string $password): bool {
    $stmt = db()->prepare("SELECT * FROM admins WHERE username = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    if (!$u) return false;
    if (!password_verify($password, $u['password_hash'])) return false;

    $_SESSION['admin'] = [
        'id'       => (int)$u['id'],
        'username' => $u['username'],
        'name'     => $u['name'],
        'email'    => $u['email'],
    ];
    db()->prepare("UPDATE admins SET last_login_at = NOW() WHERE id = ?")->execute([$u['id']]);
    return true;
}

function admin_logout(): void {
    unset($_SESSION['admin']);
    session_regenerate_id(true);
}

function require_admin(): array {
    if (empty($_SESSION['admin'])) {
        header('Location: ' . APP_ROOT_URL . '/admin/login.php');
        exit;
    }
    return $_SESSION['admin'];
}
