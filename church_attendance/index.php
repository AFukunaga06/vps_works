sudo tee /var/www/html/church_attendance/index.php >/dev/null <<'PHP'
<?php
session_start();
if (empty($_SESSION["login"])) {
  header("Location: login.php");
  exit;
}
?>
<!doctype html>
<html lang="ja">
<head><meta charset="utf-8"><title>トップ</title></head>
<body>
  <h2>トップ画面（仮）</h2>
  <p>ログイン中：<?php echo htmlspecialchars($_SESSION["user"] ?? "", ENT_QUOTES, "UTF-8"); ?></p>

  <p><a href="logout.php">ログアウト</a></p>
  <hr>
  <p>ここに出欠簿を置きます。</p>
</body>
</html>
PHP
