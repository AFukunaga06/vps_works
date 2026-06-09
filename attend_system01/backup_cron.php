<?php
// 自動バックアップスクリプト（cron用）
define('DB_HOST', 'localhost');
define('DB_NAME', 'tubasa_meibo');
define('DB_USER', 'tubasa_user');
define('DB_PASS', 'REDACTED_FOR_PUBLIC');
define('DB_CHARSET', 'utf8mb4');
define('BACKUP_DIR', __DIR__ . '/sql/backups');
define('BACKUP_MAX', 30);

if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0750, true);

$pdo = new PDO(
    'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$filename = 'backup_' . date('Ymd_His') . '.sql';
$filepath = BACKUP_DIR . '/' . $filename;

$sql  = "-- つばさ名簿 自動バックアップ\n";
$sql .= "-- 作成日時: " . date('Y-m-d H:i:s') . "\n";
$sql .= "SET NAMES utf8mb4;\nSET foreign_key_checks=0;\n\n";

foreach (['members', 'attendance', 'confirmed_dates'] as $tbl) {
    $r = $pdo->query("SHOW CREATE TABLE `$tbl`")->fetch();
    $sql .= "DROP TABLE IF EXISTS `$tbl`;\n" . $r['Create Table'] . ";\n\n";
    $rows = $pdo->query("SELECT * FROM `$tbl`")->fetchAll(PDO::FETCH_NUM);
    if ($rows) {
        $cols = $pdo->query("SHOW COLUMNS FROM `$tbl`")->fetchAll(PDO::FETCH_COLUMN);
        $colList = implode(',', array_map(fn($c) => "`$c`", $cols));
        $sql .= "LOCK TABLES `$tbl` WRITE;\n";
        foreach (array_chunk($rows, 100) as $chunk) {
            $vals = array_map(function($row) use ($pdo) {
                return '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row)) . ')';
            }, $chunk);
            $sql .= "INSERT INTO `$tbl` ($colList) VALUES\n" . implode(",\n", $vals) . ";\n";
        }
        $sql .= "UNLOCK TABLES;\n\n";
    }
}
$sql .= "SET foreign_key_checks=1;\n";
file_put_contents($filepath, $sql);

// 古いバックアップ削除
$files = glob(BACKUP_DIR . '/backup_*.sql') ?: [];
usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
foreach (array_slice($files, BACKUP_MAX) as $old) unlink($old);

echo date('Y-m-d H:i:s') . " バックアップ完了: $filename\n";
