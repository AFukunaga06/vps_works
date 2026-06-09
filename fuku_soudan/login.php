<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (is_admin()) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $db   = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION[ADMIN_SESSION_KEY] = $user['id'];
            $_SESSION['admin_name']      = $user['display_name'];
            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        }
    }
    $error = 'ユーザー名またはパスワードが正しくありません。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> - 管理者ログイン</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
body { background: #f0f4f1; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.login-box { max-width: 400px; margin: 80px auto; }
.brand { color: #3a7d5c; font-weight: bold; }
</style>
</head>
<body>
<div class="login-box">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h1 class="h5 text-center mb-1 brand"><?= SITE_NAME ?></h1>
      <p class="text-center text-muted small mb-4">管理者ログイン</p>

      <?php if ($error): ?>
      <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label for="username" class="form-label">ユーザー名</label>
          <input type="text" class="form-control" id="username" name="username"
                 autocomplete="username" required>
        </div>
        <div class="mb-4">
          <label for="password" class="form-label">パスワード</label>
          <input type="password" class="form-control" id="password" name="password"
                 autocomplete="current-password" required>
        </div>
        <div class="d-grid">
          <button type="submit" class="btn text-white" style="background:#3a7d5c">ログイン</button>
        </div>
      </form>
    </div>
  </div>
  <p class="text-center mt-3">
    <a href="<?= BASE_URL ?>/reserve/index.php" class="text-muted small">← 予約ページへ戻る</a>
  </p>
</div>
</body>
</html>
