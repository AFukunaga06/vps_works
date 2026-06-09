<?php

function csrf_generate(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_time']  = time();
    return $token;
}

function csrf_verify(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_time'])) {
        return false;
    }
    if (time() - $_SESSION['csrf_time'] > 1800) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string
{
    $token = csrf_generate();
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
