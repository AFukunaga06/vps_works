<?php
require_once __DIR__ . '/config.php';

function sessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
        session_start();
    }
}

function requireLogin(): void {
    sessionStart();
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function currentUser(): ?array {
    sessionStart();
    return isset($_SESSION['user_id']) ? [
        'id'   => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'role' => $_SESSION['user_role'],
    ] : null;
}

function isAdmin(): bool {
    $u = currentUser();
    return $u && $u['role'] === 'admin';
}

function login(string $username, string $password): bool {
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row  = $stmt->fetch();
    if (!$row) {
        // デモ用：ダミーハッシュのためpassword_verifyが必ず失敗するので平文比較も許可
        return false;
    }
    // seed.sqlのハッシュはダミーなのでデモ環境では平文 "password" を受け付ける
    if (password_verify($password, $row['password'])) {
        sessionStart();
        session_regenerate_id(true);
        $_SESSION['user_id']   = $row['id'];
        $_SESSION['user_name'] = $row['name'];
        $_SESSION['user_role'] = $row['role'];
        return true;
    }
    return false;
}

function logout(): void {
    sessionStart();
    session_destroy();
    header('Location: login.php');
    exit;
}
