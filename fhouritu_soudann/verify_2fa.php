<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ログイン済みなら管理画面へ
if (is_admin()) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

// 2FAセッションがなければログインページへ
if (empty($_SESSION['2fa_code'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = trim($_POST['code'] ?? '');
    if (verify_2fa_code($input)) {
        complete_2fa_login();
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    } else {
        // 有効期限切れかコード不一致
        if (empty($_SESSION['2fa_code'])) {
            $error = '認証コードの有効期限が切れました。再度ログインしてください。';
        } else {
            $error = '認証コードが正しくありません。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> - 認証コード確認</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
body { background: #f0f4f1; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.login-box { max-width: 400px; margin: 80px auto; }
.brand { color: #3a7d5c; font-weight: bold; }
.code-input { font-size: 1.5rem; letter-spacing: 0.3em; text-align: center; }
</style>
</head>
<body>
<div class="login-box">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h1 class="h5 text-center mb-1 brand"><?= SITE_NAME ?></h1>
      <p class="text-center text-muted small mb-1">２段階認証</p>
      <p class="text-center text-muted small mb-4">
        登録メールアドレスに6桁の認証コードを送信しました。<br>
        <span class="text-danger small">有効期限：5分</span>
      </p>

      <?php if ($error): ?>
      <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (empty($_SESSION['2fa_code'])): ?>
        <div class="d-grid">
          <a href="<?= BASE_URL ?>/login.php" class="btn text-white" style="background:#3a7d5c">ログインページへ戻る</a>
        </div>
      <?php else: ?>
      <form method="post">
        <div class="mb-4">
          <label for="code" class="form-label text-center d-block">認証コード（6桁）</label>
          <input type="text" class="form-control code-input" id="code" name="code"
                 maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                 autocomplete="one-time-code" required autofocus
                 placeholder="000000">
        </div>
        <div class="d-grid mb-3">
          <button type="submit" class="btn text-white" style="background:#3a7d5c">確認してログイン</button>
        </div>
      </form>
      <p class="text-center">
        <a href="<?= BASE_URL ?>/login.php" class="text-muted small">← ログインページへ戻る</a>
      </p>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
