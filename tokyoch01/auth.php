<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}
if (empty($_SESSION['login_ok'])) {
    header('Location: login.php');
    exit;
}

// active_session の最終活動時刻を更新
require_once __DIR__ . '/config.php';
$pdo->prepare("UPDATE active_session SET last_activity = ? WHERE session_id = ?")
    ->execute([time(), session_id()]);
