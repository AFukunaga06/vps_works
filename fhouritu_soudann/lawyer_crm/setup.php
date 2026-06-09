<?php
// 初期セットアップスクリプト（管理者アカウント作成）
// 本番環境では実行後に削除してください
require_once 'config.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name || !$username || !$password) {
        $message = '全ての項目を入力してください。';
    } elseif ($password !== $confirm) {
        $message = 'パスワードが一致しません。';
    } elseif (strlen($password) < 6) {
        $message = 'パスワードは6文字以上で入力してください。';
    } else {
        try {
            $db   = get_db();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st   = $db->prepare(
                'INSERT INTO users (username, password_hash, name, role)
                 VALUES (?, ?, ?, "admin")
                 ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), name=VALUES(name)'
            );
            $st->execute([$username, $hash, $name]);
            $success = true;
            $message = '管理者アカウントを作成しました。login.php からログインしてください。';
        } catch (PDOException $e) {
            $message = 'エラー: ' . h($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>初期セットアップ | <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { background: #1a2744; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  </style>
</head>
<body>
<div class="card" style="width:440px;border-radius:1rem;padding:2rem;">
  <div class="text-center mb-3">
    <i class="bi bi-gear-fill text-primary" style="font-size:2rem"></i>
    <h5 class="fw-bold mt-2">初期セットアップ</h5>
    <p class="text-muted small">管理者アカウントを作成します</p>
  </div>

  <?php if ($message): ?>
  <div class="alert alert-<?= $success ? 'success' : 'danger' ?> py-2 small"><?= h($message) ?></div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="post">
    <div class="mb-3">
      <label class="form-label fw-semibold">氏名</label>
      <input type="text" name="name" class="form-control" placeholder="例：田中 一郎" value="<?= h($_POST['name'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">ユーザー名</label>
      <input type="text" name="username" class="form-control" placeholder="例：admin" value="<?= h($_POST['username'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">パスワード</label>
      <input type="password" name="password" class="form-control" placeholder="6文字以上">
    </div>
    <div class="mb-4">
      <label class="form-label fw-semibold">パスワード（確認）</label>
      <input type="password" name="confirm" class="form-control">
    </div>
    <button type="submit" class="btn btn-primary w-100">アカウントを作成</button>
  </form>
  <?php else: ?>
  <div class="text-center mt-2">
    <a href="login.php" class="btn btn-primary">ログイン画面へ</a>
  </div>
  <div class="alert alert-warning small mt-3">
    <i class="bi bi-exclamation-triangle-fill me-1"></i>
    セキュリティのため、本番環境では setup.php を削除してください。
  </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
