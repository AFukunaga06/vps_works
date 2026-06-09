<?php
require_once __DIR__ . '/db.php';

function crm_session(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function crm_current_staff(): ?array {
    crm_session();
    return $_SESSION[CRM_SESSION_KEY] ?? null;
}

function crm_require_login(): array {
    $s = crm_current_staff();
    if (!$s) { header('Location: ' . CRM_BASE_URL . '/login.php'); exit; }
    return $s;
}

function crm_require_admin(): array {
    $s = crm_require_login();
    if ($s['role'] !== 'admin') { http_response_code(403); exit('権限がありません。'); }
    return $s;
}

function crm_login(string $username, string $pass): bool {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM crm_staff WHERE username=? AND is_active=1");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if ($row && password_verify($pass, $row['password_hash'])) {
        crm_session();
        $_SESSION[CRM_SESSION_KEY] = ['id' => $row['id'], 'name' => $row['name'], 'role' => $row['role']];
        return true;
    }
    return false;
}

function crm_logout(): void {
    crm_session();
    unset($_SESSION[CRM_SESSION_KEY]);
}
