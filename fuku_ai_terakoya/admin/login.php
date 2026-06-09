<?php
require_once __DIR__ . '/../lib/auth.php';

if (!empty($_SESSION['admin'])) {
    header('Location: ' . APP_ROOT_URL . '/admin/index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim((string)($_POST['username'] ?? ''));
    $p = (string)($_POST['password'] ?? '');
    if (admin_login($u, $p)) {
        session_regenerate_id(true);
        header('Location: ' . APP_ROOT_URL . '/admin/index.php');
        exit;
    }
    $error = 'ユーザー名またはパスワードが違います';
}
$csrf = csrf_token();
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ログイン - <?= h(APP_NAME) ?> 管理</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
  body{background:#eef1f5;display:flex;align-items:center;min-height:100vh}
  .card{max-width:380px;margin:0 auto;padding:24px;border:0;box-shadow:0 8px 24px rgba(0,0,0,.08)}
  h3{color:#4a7c59;font-weight:700}
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <div class="text-center mb-3">
      <i class="bi"></i>
      <h3 class="mt-2"><?= h(APP_NAME) ?></h3>
      <div class="text-muted small">管理画面ログイン</div>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <div class="mb-3">
        <label class="form-label">ユーザー名</label>
        <input class="form-control" name="username" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">パスワード</label>
        <input class="form-control" type="password" name="password" required>
      </div>
      <button class="btn btn-success w-100" type="submit">ログイン</button>
    </form>
  </div>
</div>
</body>
</html>
