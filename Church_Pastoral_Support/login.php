<?php
require_once __DIR__.'/auth.php';
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? AND is_active=1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['display_name'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: '.BASE_URL.'/');
            exit;
        }
    }
    $error = 'ユーザー名またはパスワードが正しくありません。';
}
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ログイン - <?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#1e3a5f;display:flex;align-items:center;justify-content:center;min-height:100vh}
.login-box{background:#fff;border-radius:12px;padding:40px;width:380px;box-shadow:0 8px 32px rgba(0,0,0,0.3)}
.login-title{text-align:center;color:#1e3a5f;font-size:16px;font-weight:bold;margin-bottom:4px}
.login-sub{text-align:center;color:#888;font-size:12px;margin-bottom:24px}
</style>
</head>
<body>
<div class="login-box">
  <div class="login-title"><i class="bi bi-buildings"></i> <?= h(APP_NAME) ?></div>
  <div class="login-sub">ログインしてください</div>
  <?php if($error): ?>
  <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <div class="mb-3">
      <label class="form-label small fw-bold">ユーザー名</label>
      <input type="text" name="username" class="form-control" autofocus required>
    </div>
    <div class="mb-3">
      <label class="form-label small fw-bold">パスワード</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button class="btn btn-primary w-100 mt-1" type="submit">ログイン</button>
  </form>
  <div class="text-center mt-3 small text-muted">初回: admin / REDACTED_FOR_PUBLIC</div>
</div>
</body>
</html>
