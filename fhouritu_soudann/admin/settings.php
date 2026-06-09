<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_email = trim($_POST['2fa_email'] ?? '');
    if ($new_email === '') {
        $error = 'メールアドレスを入力してください。';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'メールアドレスの形式が正しくありません。';
    } else {
        set_setting('2fa_email', $new_email);
        $success = '設定を保存しました。';
    }
}

$current_email = get_setting('2fa_email', '');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> - システム設定</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
body { background: #f0f4f1; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.navbar-brand { font-weight: bold; color: #3a7d5c !important; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
  <div class="container">
    <a class="navbar-brand" href="<?= BASE_URL ?>/admin/index.php"><?= SITE_NAME ?></a>
    <div class="ms-auto d-flex gap-2">
      <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-sm btn-outline-secondary">← 管理画面へ戻る</a>
      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-outline-danger">ログアウト</a>
    </div>
  </div>
</nav>

<div class="container" style="max-width:600px;">
  <h2 class="h5 mb-4">システム設定</h2>

  <?php if ($success): ?>
  <div class="alert alert-success py-2"><?= h($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-danger py-2"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header fw-bold">２段階認証コード送信先メール</div>
    <div class="card-body">
      <p class="text-muted small mb-3">
        ログイン時の認証コードをこのメールアドレスに送信します。<br>
        変更後は次回ログインから新しいアドレスに送られます。
      </p>
      <form method="post">
        <div class="mb-3">
          <label for="2fa_email" class="form-label">メールアドレス</label>
          <input type="email" class="form-control" id="2fa_email" name="2fa_email"
                 value="<?= h($current_email) ?>" required>
        </div>
        <div class="d-grid">
          <button type="submit" class="btn text-white" style="background:#3a7d5c">保存する</button>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>
