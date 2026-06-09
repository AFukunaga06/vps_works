<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
gc_session();
if (gc_current_user()) { header('Location: ' . GC_BASE_URL . '/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if (gc_login($email, $pass)) {
        header('Location: ' . GC_BASE_URL . '/index.php'); exit;
    }
    $error = 'メールアドレスまたはパスワードが違います。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= GC_SITE_NAME ?> - ログイン</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background: #1b5e35; font-family: 'Hiragino Sans','Meiryo',sans-serif; }
.login-box { max-width: 420px; margin: 80px auto; }
.login-header { background: #1b5e35; color: #fff; padding: 2rem 1.5rem 1.5rem; border-radius: .5rem .5rem 0 0; text-align: center; border: 1px solid rgba(255,255,255,.3); border-bottom: none; }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-header">
    <i class="bi bi-building fs-1 d-block mb-2"></i>
    <h1 class="h4 mb-0"><?= GC_SITE_NAME ?></h1>
    <p class="small mb-0 opacity-75 mt-1">行政書士向け案件管理システム</p>
  </div>
  <div class="card border-0 rounded-top-0 shadow">
    <div class="card-body p-4">
      <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">メールアドレス</label>
          <input type="email" name="email" class="form-control" required autofocus autocomplete="email"
                 value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <div class="mb-4">
          <label class="form-label">パスワード</label>
          <input type="password" name="password" class="form-control" required autocomplete="current-password">
        </div>
        <button class="btn w-100 text-white fw-bold" style="background:#1b5e35">ログイン</button>
      </form>
    </div>
  </div>
  <p class="text-center text-white-50 small mt-3">初期アカウント: admin@example.com / admin123</p>
</div>
</body>
</html>
