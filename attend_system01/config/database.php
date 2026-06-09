<?php
// config/database.php
declare(strict_types=1);

/**
 * DB接続設定
 * ※ MySQL側で作成したDB/ユーザーと必ず一致させてください
 */
$DB_HOST = "127.0.0.1";
$DB_NAME = "tubasa_meibo";
$DB_USER = "tubasa_user";
$DB_PASS = "REDACTED_FOR_PUBLIC";

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // 例外で受ける
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // 連想配列で取得
    PDO::ATTR_EMULATE_PREPARES   => false,                  // ネイティブprepare
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) {
    // 本番では詳細を画面に出さない（漏洩防止）
    http_response_code(500);
    header("Content-Type: text/plain; charset=utf-8");
    echo "DB connection failed";
    exit;
}

