<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $today = date('Y-m-d');
    $today_pv = (int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE DATE(created_at)='{$today}' AND is_bot=0")->fetchColumn();
    $total_pv = (int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE is_bot=0")->fetchColumn();
    echo json_encode(['today' => $today_pv, 'total' => $total_pv]);
} catch (Exception $e) {
    echo json_encode(['today' => 0, 'total' => 0]);
}
