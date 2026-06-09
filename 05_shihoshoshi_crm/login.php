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
    if ($username !== '' && $password !== '') {
        $stmt = get_db()->prepare('SELECT id, name, password_hash, role FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: index.php');
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
  <title>ログイン | 司法書士事務所CRM</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { background: #eaf0f8; min-height: 100vh; display: flex; align-items: center; justify-content: center;
      font-family: 'Hiragino Kaku Gothic ProN', 'Yu Gothic', sans-serif; }
    .login-card { width: 100%; max-width: 400px; border-radius: .75rem; border: none;
      box-shadow: 0 4px 24px rgba(0,0,0,.1); }
    .login-header { background: #1b3a5c; color: #fff; border-radius: .75rem .75rem 0 0; padding: 2rem; text-align: center; }
    .demo-box { background: #f0f7ff; border: 1px solid #b8d9f8; border-radius: .5rem; padding: .85rem 1rem; margin-top: 1.25rem; }
    .demo-box .demo-title { font-size: .75rem; font-weight: 700; color: #1b6bbf; letter-spacing: .04em; margin-bottom: .5rem; }
    .demo-box table { width: 100%; font-size: .9rem; }
    .demo-box td:first-child { color: #555; width: 6rem; }
    .demo-box td:last-child { font-weight: 700; color: #1b3a5c; font-family: monospace; font-size: 1rem; }
    .demo-box .fill-btn { font-size: .75rem; float: right; }
  </style>
</head>
<body>
<div class="login-card card">
  <div class="login-header">
    <i class="bi bi-journal-bookmark-fill fs-1 mb-2 d-block"></i>
    <h1 class="h5 mb-0">司法書士事務所CRM</h1>
    <div class="small opacity-75 mt-1">Judicial Scrivener Management</div>
  </div>
  <div class="card-body p-4">
    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post" id="loginForm">
      <div class="mb-3">
        <label class="form-label fw-semibold">ユーザー名</label>
        <input type="text" name="username" id="username" class="form-control" autofocus required>
      </div>
      <div class="mb-4">
        <label class="form-label fw-semibold">パスワード</label>
        <input type="password" name="password" id="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-box-arrow-in-right me-1"></i>ログイン
      </button>
    </form>

    <div class="demo-box">
      <div class="demo-title">
        <i class="bi bi-info-circle me-1"></i>初回ログイン情報
        <button type="button" class="btn btn-sm btn-outline-primary fill-btn" onclick="fillDemo()">自動入力</button>
        <a href="reset_initial.php" class="btn btn-sm btn-outline-danger fill-btn me-1" style="float:right;margin-right:.25rem"><i class="bi bi-arrow-counterclockwise"></i> リセット</a>
      </div>
      <table>
        <tr>
          <td>ユーザー名</td>
          <td>admin</td>
        </tr>
        <tr>
          <td>パスワード</td>
          <td>password123</td>
        </tr>
      </table>
      <div style="font-size:.78rem;color:#555;margin-top:.6rem;border-top:1px solid #c8dff5;padding-top:.55rem;"><i class="bi bi-arrow-right-circle me-1 text-primary"></i>初回ログイン後は<strong>アカウント設定</strong>から自分のユーザー名・パスワードに変更し、次回からはそちらでログインしてください。</div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function fillDemo() {
  document.getElementById('username').value = 'admin';
  document.getElementById('password').value = 'password123';
}
</script>
</body>
</html>
