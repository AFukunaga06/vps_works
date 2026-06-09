<?php
require_once 'config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $db = get_db();
            $st = $db->prepare('SELECT id, name, password_hash, role FROM users WHERE username = ? LIMIT 1');
            $st->execute([$username]);
            $user = $st->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'データベース接続エラー: ' . h($e->getMessage());
        }
        if (!$error) $error = 'ユーザー名またはパスワードが正しくありません。';
    } else {
        $error = 'ユーザー名とパスワードを入力してください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ログイン | <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { background: #1a2744; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .login-card {
      width: 100%; max-width: 420px;
      background: #fff; border-radius: 1rem;
      padding: 2.5rem 2rem; box-shadow: 0 8px 32px rgba(0,0,0,.25);
    }
    .login-logo { color: #1a2744; }
  </style>
</head>
<body>
<div class="login-card">
  <div class="text-center mb-4 login-logo">
    <i class="bi bi-briefcase-fill" style="font-size:2.5rem"></i>
    <h4 class="fw-bold mt-2 mb-0">弁護士事務所CRM</h4>
    <p class="text-muted small">Lawyer Management System</p>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= $error ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <div class="mb-3">
      <label class="form-label fw-semibold">ユーザー名</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-person"></i></span>
        <input type="text" name="username" class="form-control" placeholder="username"
               value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
      </div>
    </div>
    <div class="mb-4">
      <label class="form-label fw-semibold">パスワード</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input type="password" name="password" class="form-control" placeholder="password" required>
      </div>
    </div>
    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
      <i class="bi bi-box-arrow-in-right me-1"></i>ログイン
    </button>
  </form>

  <div class="mt-4 p-3 bg-light rounded small text-muted">
    <strong>デモ用ログイン情報</strong><br>
    ユーザー名: <code>admin</code>　パスワード: <code>admin123</code><br>
    <span class="text-danger">※ 初回は <code>setup.php</code> を実行してください</span>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
