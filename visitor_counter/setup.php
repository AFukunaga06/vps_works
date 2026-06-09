<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>visitor_counter セットアップ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width:700px">
<h2 class="mb-4">訪問者カウンター セットアップ</h2>
<?php
$log = [];
$ok = true;

try {
    $root_pdo = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $log[] = ['success', 'MySQL root接続 OK'];

    $root_pdo->exec("CREATE DATABASE IF NOT EXISTS visitor_counter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $log[] = ['success', 'DB visitor_counter 作成/確認 OK'];

    $root_pdo->exec("CREATE USER IF NOT EXISTS 'vc_user'@'localhost' IDENTIFIED BY 'REDACTED_FOR_PUBLIC'");
    $root_pdo->exec("GRANT ALL PRIVILEGES ON visitor_counter.* TO 'vc_user'@'localhost'");
    $root_pdo->exec("FLUSH PRIVILEGES");
    $log[] = ['success', 'DBユーザー vc_user 作成/確認 OK'];

    $pdo = new PDO("mysql:host=localhost;dbname=visitor_counter;charset=utf8mb4", 'vc_user', 'REDACTED_FOR_PUBLIC', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS page_views (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL DEFAULT '',
        url VARCHAR(500) NOT NULL DEFAULT '',
        page_title VARCHAR(300) NOT NULL DEFAULT '',
        ip_hash VARCHAR(64) NOT NULL DEFAULT '',
        user_agent TEXT,
        referrer VARCHAR(500) NOT NULL DEFAULT '',
        country_code CHAR(2) NOT NULL DEFAULT '',
        country_name VARCHAR(100) NOT NULL DEFAULT '',
        is_bot TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at),
        INDEX idx_is_bot (is_bot),
        INDEX idx_url (url(100))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $log[] = ['success', 'テーブル page_views 作成/確認 OK'];

    $pdo->exec("CREATE TABLE IF NOT EXISTS ip_country_cache (
        ip_hash VARCHAR(64) PRIMARY KEY,
        country_code CHAR(2) NOT NULL DEFAULT '',
        country_name VARCHAR(100) NOT NULL DEFAULT '',
        cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $log[] = ['success', 'テーブル ip_country_cache 作成/確認 OK'];

} catch (Exception $e) {
    $log[] = ['danger', 'エラー: ' . htmlspecialchars($e->getMessage())];
    $ok = false;
}

foreach ($log as [$cls, $msg]) {
    echo "<div class='alert alert-{$cls} py-2'>{$msg}</div>";
}
if ($ok): ?>
<div class="alert alert-success fw-bold">✅ セットアップ完了！</div>

<div class="card mt-4">
  <div class="card-header fw-bold">埋め込みスニペット（他サイトに貼るだけ）</div>
  <div class="card-body">
    <p class="small text-muted">計測したいページの &lt;/body&gt; 直前に貼り付けてください。</p>
    <pre class="bg-dark text-light p-3 rounded small"><?= htmlspecialchars('<script>
(function(){
  fetch("https://sakuhinnsyuu01.afuku5906.com/visitor_counter/tracker.php", {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body: new URLSearchParams({
      url: location.href,
      title: document.title,
      referrer: document.referrer
    })
  });
})();
</script>') ?></pre>
  </div>
</div>
<div class="mt-3">
  <a href="admin.php" class="btn btn-primary">管理画面へ</a>
</div>
<?php endif; ?>
</div>
</body>
</html>
