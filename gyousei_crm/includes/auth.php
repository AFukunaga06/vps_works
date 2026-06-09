<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function gc_session(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function gc_current_user(): ?array {
    gc_session();
    return $_SESSION[GC_SESSION_KEY] ?? null;
}

function gc_require_login(): array {
    $u = gc_current_user();
    if (!$u) {
        header('Location: ' . GC_BASE_URL . '/login.php');
        exit;
    }
    return $u;
}

function gc_require_admin(): array {
    $u = gc_require_login();
    if ($u['role'] !== 'admin') {
        header('Location: ' . GC_BASE_URL . '/index.php');
        exit;
    }
    return $u;
}

function gc_login(string $email, string $pass): bool {
    $stmt = get_db()->prepare("SELECT * FROM gc_users WHERE email=? AND is_active=1");
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($pass, $u['password_hash'])) return false;
    gc_session();
    $_SESSION[GC_SESSION_KEY] = ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']];
    return true;
}

function gc_logout(): void {
    gc_session();
    unset($_SESSION[GC_SESSION_KEY]);
    session_destroy();
}
