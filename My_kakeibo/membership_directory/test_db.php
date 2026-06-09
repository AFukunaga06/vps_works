<?php
require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM members");
    $row = $stmt->fetch();

    echo "✅ DB接続成功<br>";
    echo "members件数: " . $row['cnt'];

} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}
