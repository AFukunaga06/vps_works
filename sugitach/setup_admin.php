<?php
// setup_admin.php
// ★ 実行後は必ず削除してください

require_once __DIR__ . "/config/database.php"; // database.php の場所に合わせて変更

$username = "sugita234";
$plainPassword = "REDACTED_FOR_PUBLIC"; // ← ここをあなたの好きな強いパスワードに変更

$hash = password_hash($plainPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO users (username, password_hash, role)
    VALUES (?, ?, 'admin')
    ON DUPLICATE KEY UPDATE
        password_hash = VALUES(password_hash),
        role = 'admin'
");

$stmt->bind_param("ss", $username, $hash);
$stmt->execute();

echo "管理者 admin 作成 / 更新 完了しました。<br>";
echo "このファイルはすぐ削除してください。";
