<?php
// 初回セットアップ専用 - 完了後は削除してください
$dsn = 'mysql:host=localhost;dbname=fuku_soudan;charset=utf8mb4';
$pdo = new PDO($dsn, 'fuku_user', 'REDACTED_FOR_PUBLIC', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$sql = file_get_contents(__DIR__ . '/setup.sql');
// USE文を除いて実行
$statements = array_filter(array_map('trim', explode(';', $sql)));
foreach ($statements as $s) {
    if ($s && !str_starts_with(strtoupper($s), 'USE')) {
        $pdo->exec($s);
    }
}
echo "テーブル作成完了<br>";

// 初期管理者アカウント
$name  = '管理者';
$email = 'afky5906@gmail.com';
$pass  = 'REDACTED_FOR_PUBLIC'; // ログイン後に変更してください
$hash  = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT IGNORE INTO crm_staff (name, email, password_hash, role) VALUES (?,?,?,'admin')");
$stmt->execute([$name, $email, $hash]);
echo "管理者アカウント作成完了<br>";
echo "メール: {$email}<br>";
echo "初期パスワード: {$pass}<br>";
echo "<strong>ログイン後すぐにパスワードを変更してください。</strong><br>";
echo "<a href='login.php'>ログイン画面へ</a>";
