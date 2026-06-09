<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
lc_session();
if (lc_current_user()) { header('Location: ' . LC_BASE_URL . '/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id'] ?? '');
    $pass     = $_POST['password'] ?? '';
    if (lc_login($login_id, $pass)) {
        header('Location: ' . LC_BASE_URL . '/index.php'); exit;
    }
    $error = 'ログインIDまたはパスワードが違います。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= LC_SITE_NAME ?> - ログイン</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background: #1a3a5c; font-family: 'Hiragino Sans','Meiryo',sans-serif; }
.login-box { max-width: 420px; margin: 80px auto; }
.login-header { background: #1a3a5c; color: #c8a94a; padding: 2rem 1.5rem 1.5rem; border-radius: .5rem .5rem 0 0; text-align: center; border: 1px solid #c8a94a; border-bottom: none; }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-header">
    <i class="bi bi-briefcase-fill fs-1 d-block mb-2"></i>
    <h1 class="h4 mb-0"><?= LC_SITE_NAME ?></h1>
    <p class="small mb-0 opacity-75 mt-1">依頼者・案件管理システム</p>
  </div>
  <div class="card border-0 rounded-top-0 shadow">
    <div class="card-body p-4">
      <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">ログインID</label>
          <input type="text" name="login_id" class="form-control" required autofocus autocomplete="username"
                 value="<?= h($_POST['login_id'] ?? '') ?>">
        </div>
        <div class="mb-4">
          <label class="form-label">パスワード</label>
          <input type="password" name="password" class="form-control" required autocomplete="current-password">
        </div>
        <button class="btn w-100 text-white fw-bold" style="background:#1a3a5c">ログイン</button>
      </form>
    </div>
  </div>
  <p class="text-center text-white-50 small mt-3">初期アカウント: admin / admin123</p>
</div>
</body>
</html>
