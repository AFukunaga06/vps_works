<?php
require_once __DIR__ . '/config.php';

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
