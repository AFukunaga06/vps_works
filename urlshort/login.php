<?php
require_once __DIR__ . '/auth_config.php';
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        $err = 'セッションエラー。再度お試しください';
    } else {
        $u = trim($_POST['user'] ?? '');
        $p = (string)($_POST['pass'] ?? '');
        if ($u === AUTH_USER && password_verify($p, AUTH_PASS_HASH)) {
            session_regenerate_id(true);
            $_SESSION[AUTH_SESSION_NAME] = ['user'=>$u, 'login_at'=>time()];
            header('Location: index.php');
            exit;
        }
        // 軽いブルートフォース対策（応答遅延）
        usleep(800000);
        $err = 'ログインに失敗しました';
    }
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ログイン - URL短縮ツール</title>
<link rel="stylesheet" href="style.css">
<style>
.login-wrap{max-width:380px;margin:80px auto;padding:0 16px}
.login-card{background:#fff;padding:30px 28px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.login-card h1{margin:0 0 6px;font-size:1.3rem;color:#11998e}
.login-card .lead{color:#888;font-size:.85rem;margin-bottom:20px}
.login-card label{display:block;font-size:.85rem;color:#555;margin-bottom:5px;margin-top:12px}
.login-card input{width:100%;padding:10px 12px;border:1px solid #d0d7de;border-radius:6px;font-size:.95rem}
.login-card input:focus{border-color:#11998e;outline:none}
.login-card button{width:100%;margin-top:20px;background:#11998e;color:#fff;border:0;padding:11px;border-radius:6px;font-weight:600;cursor:pointer;font-size:.95rem}
.login-card button:hover{background:#0d7d75}
.login-err{background:#f8d7da;color:#841a23;padding:8px 12px;border-radius:5px;font-size:.85rem;margin-bottom:12px}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <h1>🔗 管理画面ログイン</h1>
    <div class="lead">URL短縮ツールの管理にはログインが必要です</div>
    <?php if ($err): ?><div class="login-err"><?=htmlspecialchars($err, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8')?>">
      <label>ユーザー名</label>
      <input type="text" name="user" required autofocus autocomplete="username">
      <label>パスワード</label>
      <input type="password" name="pass" required autocomplete="current-password">
      <button type="submit">ログイン</button>
    </form>
  </div>
</div>
</body></html>
