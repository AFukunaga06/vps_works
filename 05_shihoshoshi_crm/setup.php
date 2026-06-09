<?php
/**
 * DBセットアップ用スクリプト（初回のみ使用）
 * 使用後は削除またはアクセス制限すること
 */
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $seed   = file_get_contents(__DIR__ . '/seed.sql');

    foreach (array_filter(array_map('trim', explode(';', $schema))) as $q) {
        if ($q !== '') $pdo->exec($q);
    }
    foreach (array_filter(array_map('trim', explode(';', $seed))) as $q) {
        if ($q !== '') $pdo->exec($q);
    }

    echo '<p style="color:green;font-family:sans-serif">✅ セットアップ完了！<a href="login.php">ログイン画面へ</a></p>';
} catch (Exception $e) {
    echo '<p style="color:red;font-family:sans-serif">❌ エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
