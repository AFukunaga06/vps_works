<?php
require_once __DIR__ . '/auth_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => true,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}
if (empty($_SESSION[AUTH_SESSION_NAME])) {
    header('Location: login.php');
    exit;
}
