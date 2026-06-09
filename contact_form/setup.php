<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>contact_form セットアップ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width:700px">
<h2 class="mb-4">お問い合わせフォーム セットアップ</h2>
<?php
$log = [];
$ok  = true;

try {
    $root = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $log[] = ['success', 'MySQL root接続 OK'];

    $root->exec("CREATE DATABASE IF NOT EXISTS contact_form CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $log[] = ['success', 'DB contact_form 作成/確認 OK'];

    $root->exec("CREATE USER IF NOT EXISTS 'cf_user'@'localhost' IDENTIFIED BY 'REDACTED_FOR_PUBLIC'");
    $root->exec("GRANT ALL PRIVILEGES ON contact_form.* TO 'cf_user'@'localhost'");
    $root->exec("FLUSH PRIVILEGES");
    $log[] = ['success', 'DBユーザー cf_user 作成/確認 OK'];

    $pdo = new PDO("mysql:host=localhost;dbname=contact_form;charset=utf8mb4", 'cf_user', 'REDACTED_FOR_PUBLIC', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS inquiries (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL DEFAULT '',
        email VARCHAR(200) NOT NULL DEFAULT '',
        phone VARCHAR(30) NOT NULL DEFAULT '',
        subject VARCHAR(200) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        ip_address VARCHAR(45) NOT NULL DEFAULT '',
        user_agent TEXT,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at),
        INDEX idx_is_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $log[] = ['success', 'テーブル inquiries 作成/確認 OK'];

    // Rate limit table
    $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip_hash VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip (ip_hash),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $log[] = ['success', 'テーブル rate_limits 作成/確認 OK'];

    // CSRF table
    $pdo->exec("CREATE TABLE IF NOT EXISTS csrf_tokens (
        token VARCHAR(64) PRIMARY KEY,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $log[] = ['success', 'テーブル csrf_tokens 作成/確認 OK'];

    // Install PHPMailer via Composer if needed
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        chdir(__DIR__);
        $result = shell_exec('composer require phpmailer/phpmailer 2>&1');
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            $log[] = ['success', 'PHPMailer インストール OK'];
        } else {
            $log[] = ['warning', 'PHPMailer インストール失敗。手動で composer require phpmailer/phpmailer を実行してください。<br><small>' . nl2br(htmlspecialchars($result)) . '</small>'];
        }
    } else {
        $log[] = ['success', 'PHPMailer 確認 OK（インストール済み）'];
    }

} catch (Exception $e) {
    $log[] = ['danger', 'エラー: ' . htmlspecialchars($e->getMessage())];
    $ok = false;
}

foreach ($log as [$cls, $msg]) {
    echo "<div class='alert alert-{$cls} py-2'>{$msg}</div>";
}
if ($ok): ?>
<div class="alert alert-success fw-bold">✅ セットアップ完了！</div>
<div class="mt-3 d-flex gap-2">
  <a href="index.php" class="btn btn-primary">フォームを見る</a>
  <a href="admin.php" class="btn btn-secondary">管理画面へ</a>
</div>
<?php endif; ?>
</div>
</body>
</html>
