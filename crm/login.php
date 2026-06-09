<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
crm_session();
if (crm_current_staff()) { header('Location: ' . CRM_BASE_URL . '/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $pass     = $_POST['password'] ?? '';
    if (crm_login($username, $pass)) {
        header('Location: ' . CRM_BASE_URL . '/index.php'); exit;
    }
    $error = 'ユーザーIDまたはパスワードが違います。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= CRM_SITE_NAME ?> - ログイン</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
body { background: #f4f6f4; font-family: 'Hiragino Sans','Meiryo',sans-serif; }
.login-box { max-width: 400px; margin: 80px auto; }
.login-header { background: #3a7d5c; color: #fff; padding: 1.5rem; border-radius: .5rem .5rem 0 0; text-align: center; }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-header">
    <h1 class="h5 mb-0"><?= CRM_SITE_NAME ?></h1>
  </div>
  <div class="card border-top-0 rounded-top-0 shadow-sm">
    <div class="card-body p-4">
      <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">ユーザーID</label>
          <input type="text" name="username" class="form-control" required autofocus autocomplete="username">
        </div>
        <div class="mb-4">
          <label class="form-label">パスワード</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn w-100 text-white" style="background:#3a7d5c">ログイン</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
