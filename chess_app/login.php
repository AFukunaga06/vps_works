<?php
require __DIR__ . '/config.php';
start_session();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id, username, password_hash, display_name FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        $_SESSION['user_id']      = (int)$u['id'];
        $_SESSION['username']     = $u['username'];
        $_SESSION['display_name'] = $u['display_name'] ?: $u['username'];
        header('Location: index.php');
        exit;
    }
    $error = 'ユーザー名またはパスワードが違います';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ログイン - チェスvsClaude</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #1a1a2e; color: #eee; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
.card { background: #16213e; padding: 32px; border-radius: 10px; width: 100%; max-width: 380px; }
h1 { color: #e0c870; font-size: 1.5em; margin-bottom: 4px; text-align: center; }
.sub { color: #888; font-size: 0.85em; text-align: center; margin-bottom: 24px; }
label { display: block; font-size: 0.85em; color: #aaa; margin-bottom: 6px; margin-top: 14px; }
input { width: 100%; padding: 10px 12px; background: #1a1a2e; border: 1px solid #2a3a5a; color: #eee; border-radius: 6px; font-size: 0.95em; }
input:focus { outline: none; border-color: #e0c870; }
.btn { width: 100%; padding: 12px; background: #e0c870; color: #1a1a2e; border: none; border-radius: 6px; font-size: 0.95em; font-weight: bold; cursor: pointer; margin-top: 20px; }
.btn:hover { background: #f0d880; }
.error { background: #3a1a1a; border-left: 3px solid #ef5350; color: #ff9090; padding: 10px 12px; border-radius: 4px; font-size: 0.85em; margin-bottom: 12px; }
.link { text-align: center; margin-top: 16px; font-size: 0.85em; color: #888; }
.link a { color: #4fc3f7; text-decoration: none; }
</style>
</head>
<body>
<div class="card">
  <h1>♟ ログイン</h1>
  <p class="sub">チェスvsClaude</p>
  <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <label>ユーザー名</label>
    <input type="text" name="username" required value="<?= e($_POST['username'] ?? '') ?>">
    <label>パスワード</label>
    <input type="password" name="password" required>
    <button type="submit" class="btn">ログイン</button>
  </form>
  <div class="link">アカウントが無い方は <a href="register.php">新規登録</a></div>
</div>
</body>
</html>
