<?php
require_once 'config.php';

$done  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes') {
    $hash = password_hash('password123', PASSWORD_BCRYPT);
    $stmt = get_db()->prepare("UPDATE users SET username = 'admin', password_hash = ? WHERE id = 1");
    $stmt->execute([$hash]);
    $done = true;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>初期パスワードリセット | 司法書士事務所CRM</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { background: #eaf0f8; min-height: 100vh; display: flex; align-items: center; justify-content: center;
      font-family: 'Hiragino Kaku Gothic ProN', 'Yu Gothic', sans-serif; }
    .reset-card { width: 100%; max-width: 420px; border-radius: .75rem; border: none;
      box-shadow: 0 4px 24px rgba(0,0,0,.1); }
    .reset-header { background: #7b2020; color: #fff; border-radius: .75rem .75rem 0 0; padding: 1.5rem 2rem; text-align: center; }
  </style>
</head>
<body>
<div class="reset-card card">
  <div class="reset-header">
    <i class="bi bi-arrow-counterclockwise fs-1 mb-2 d-block"></i>
    <h1 class="h5 mb-0">初期パスワードリセット</h1>
    <div class="small opacity-75 mt-1">司法書士事務所CRM</div>
  </div>
  <div class="card-body p-4">
    <?php if ($done): ?>
      <div class="alert alert-success">
        <i class="bi bi-check-circle me-1"></i>リセットが完了しました。
      </div>
      <div class="border rounded p-3 mb-4 bg-light">
        <div class="row g-2 text-center">
          <div class="col-6">
            <div class="text-muted small">ユーザー名</div>
            <div class="fw-bold font-monospace fs-5">admin</div>
          </div>
          <div class="col-6">
            <div class="text-muted small">パスワード</div>
            <div class="fw-bold font-monospace fs-5">password123</div>
          </div>
        </div>
      </div>
      <a href="login.php" class="btn btn-primary w-100">
        <i class="bi bi-box-arrow-in-right me-1"></i>ログイン画面へ
      </a>
    <?php else: ?>
      <p class="text-muted small mb-3">
        管理者アカウントのユーザー名・パスワードを初期値に戻します。
      </p>
      <div class="border rounded p-3 mb-4 bg-light">
        <div class="row g-2 text-center">
          <div class="col-6">
            <div class="text-muted small">ユーザー名</div>
            <div class="fw-bold font-monospace fs-5">admin</div>
          </div>
          <div class="col-6">
            <div class="text-muted small">パスワード</div>
            <div class="fw-bold font-monospace fs-5">password123</div>
          </div>
        </div>
      </div>
      <div class="alert alert-warning py-2 small">
        <i class="bi bi-exclamation-triangle me-1"></i>現在のパスワードは上書きされます。
      </div>
      <form method="post">
        <input type="hidden" name="confirm" value="yes">
        <button type="submit" class="btn btn-danger w-100 mb-2">
          <i class="bi bi-arrow-counterclockwise me-1"></i>初期値にリセットする
        </button>
      </form>
      <a href="login.php" class="btn btn-outline-secondary w-100">
        <i class="bi bi-x me-1"></i>キャンセル
      </a>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
