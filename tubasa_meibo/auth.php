<?php
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

if (empty($_SESSION['login_ok'])) {
    header("Location: login.php");
    exit;
}
