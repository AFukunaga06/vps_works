<?php
session_start();
require_once __DIR__ . '/config.php';

// すでにログイン済みならダッシュボードへ
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    if (password_verify($pass, ADMIN_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'パスワードが正しくありません。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ログイン - <?= SITE_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    body { background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .login-card { width: 100%; max-width: 360px; }
    .login-title { font-weight: 700; font-size: 1.3rem; }
  </style>
</head>
<body>
<div class="login-card">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <div class="text-center mb-4">
        <i class="bi bi-book-half fs-1 text-dark"></i>
        <p class="login-title mt-2"><?= SITE_NAME ?></p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label">管理者パスワード</label>
          <input type="password" name="password" class="form-control" autofocus required>
        </div>
        <button type="submit" class="btn btn-dark w-100">ログイン</button>
      </form>
    </div>
  </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</body>
</html>
