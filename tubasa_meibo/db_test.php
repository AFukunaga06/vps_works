<?php
session_start();
if (empty($_SESSION['login_ok'])) { header("Location: login.php"); exit; }

require_once __DIR__ . "/config/database.php";

try {
    $v = $pdo->query("SELECT VERSION() AS v")->fetch();
    echo "DB OK / MySQL version: " . htmlspecialchars($v["v"] ?? "?", ENT_QUOTES, "UTF-8");
} catch (Throwable $e) {
    http_response_code(500);
    echo "DB NG";
}
