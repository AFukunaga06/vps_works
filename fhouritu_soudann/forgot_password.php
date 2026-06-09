<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$message = '';
$is_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $send_email = get_setting('2fa_email', '');
    if ($send_email !== '') {
        $token = generate_password_reset_token();
        $sent  = send_password_reset_mail($send_email, $token);
        if ($sent) {
            $is_sent = true;
            // メールアドレスの一部を隠して表示（例: af***@gmail.com）
            $parts   = explode('@', $send_email);
            $masked  = substr($parts[0], 0, 2) . '***@' . $parts[1];
            $message = $masked . ' にリセット用URLを送信しました。';
        } else {
            $message = 'メール送信に失敗しました。管理者にお問い合わせください。';
        }
    } else {
        $message = '送信先メールアドレスが設定されていません。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> - パスワードをお忘れの方</title>
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
      <p class="text-center text-muted small mb-4">パスワードをお忘れの方</p>

      <?php if ($is_sent): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($message) ?></div>
        <p class="small text-muted text-center">URLの有効期限は<strong>30分</strong>です。<br>メールをご確認ください。</p>
        <div class="d-grid mt-3">
          <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-secondary btn-sm">← ログインページへ戻る</a>
        </div>
      <?php else: ?>
        <?php if ($message): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <p class="small text-muted mb-4">
          登録済みのメールアドレスにパスワードリセット用のURLを送信します。
        </p>
        <form method="post">
          <div class="d-grid">
            <button type="submit" class="btn text-white" style="background:#3a7d5c">
              リセット用URLを送信する
            </button>
          </div>
        </form>
        <p class="text-center mt-3">
          <a href="<?= BASE_URL ?>/login.php" class="text-muted small">← ログインページへ戻る</a>
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
