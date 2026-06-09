<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$token   = trim($_GET['token'] ?? '');
$error   = '';
$success = false;

// トークンの有効性チェック
if ($token === '' || !verify_reset_token($token)) {
    $token_invalid = true;
} else {
    $token_invalid = false;
}

if (!$token_invalid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password  = $_POST['new_password']     ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 6) {
        $error = 'パスワードは6文字以上で入力してください。';
    } elseif ($new_password !== $confirm_password) {
        $error = 'パスワードが一致しません。';
    } else {
        if (consume_reset_token_and_update_password($token, $new_password)) {
            $success = true;
        } else {
            $error = 'パスワードの更新に失敗しました。もう一度お試しください。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> - パスワードリセット</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
body { background: #f0f4f1; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.box { max-width: 400px; margin: 80px auto; }
.brand { color: #3a7d5c; font-weight: bold; }
</style>
</head>
<body>
<div class="box">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h1 class="h5 text-center mb-1 brand"><?= SITE_NAME ?></h1>
      <p class="text-center text-muted small mb-4">パスワードリセット</p>

      <?php if ($token_invalid): ?>
        <div class="alert alert-danger py-2 small">
          このリセット用URLは無効か有効期限切れです。<br>
          再度「パスワードを忘れた」からお試しください。
        </div>
        <div class="d-grid mt-3">
          <a href="<?= BASE_URL ?>/forgot_password.php" class="btn text-white" style="background:#3a7d5c">
            パスワードをお忘れの方はこちら
          </a>
        </div>

      <?php elseif ($success): ?>
        <div class="alert alert-success py-2 small">
          パスワードを変更しました。新しいパスワードでログインしてください。
        </div>
        <div class="d-grid mt-3">
          <a href="<?= BASE_URL ?>/login.php" class="btn text-white" style="background:#3a7d5c">
            ログインページへ
          </a>
        </div>

      <?php else: ?>
        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="mb-3">
            <label for="new_password" class="form-label">新しいパスワード</label>
            <input type="password" class="form-control" id="new_password" name="new_password"
                   required minlength="6" autofocus placeholder="6文字以上">
          </div>
          <div class="mb-4">
            <label for="confirm_password" class="form-label">新しいパスワード（確認）</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                   required minlength="6" placeholder="もう一度入力">
          </div>
          <div class="d-grid">
            <button type="submit" class="btn text-white" style="background:#3a7d5c">
              パスワードを変更する
            </button>
          </div>
        </form>

      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
