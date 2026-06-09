<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';

sessionStart();
if (currentUser()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if ($u === '' || $p === '') {
        $error = 'ユーザー名とパスワードを入力してください。';
    } elseif (!login($u, $p)) {
        $error = 'ユーザー名またはパスワードが正しくありません。';
    } else {
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ログイン | <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<div class="container">
  <div class="row justify-content-center mt-5">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h4 class="card-title text-center mb-4 text-primary">
            <i class="bi bi-hospital fs-3"></i><br>クリニック予約管理
          </h4>
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
          <?php endif; ?>
          <form method="post">
            <div class="mb-3">
              <label class="form-label">ユーザー名</label>
              <input type="text" name="username" class="form-control"
                value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label">パスワード</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-box-arrow-in-right"></i> ログイン
            </button>
          </form>
          <hr>
          <p class="text-muted small text-center mb-0">
            初回ログイン情報：admin / password123
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
