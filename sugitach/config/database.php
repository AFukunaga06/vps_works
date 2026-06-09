<?php
/**
 * データベース接続設定
 * ※ パスワードはご自身で設定したものに書き換えてください
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'church_attendance');
define('DB_USER', 'church_user');
define('DB_PASS', 'REDACTED_FOR_PUBLIC'); // ← 必ず書き換えてください

// PDO接続
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // 本番ではDBエラーの詳細を表示しない
            error_log('DB接続エラー: ' . $e->getMessage());
            die('データベース接続に失敗しました。管理者に連絡してください。');
        }
    }
    return $pdo;
}
