<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ログインページを開いたら既存セッションをリセット
session_unset();
session_destroy();
session_start();
session_regenerate_id(true);

$error      = '';
$form_email = get_setting('2fa_email', '');
$client_ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id    = trim($_POST['user_id']    ?? '');
    $password   = trim($_POST['password']   ?? '');
    $form_email = trim($_POST['auth_email'] ?? '');

    // IPロックチェック
    $ip_lock = get_lockout_seconds($client_ip, 'ip');
    if ($ip_lock > 0) {
        $error = 'このIPアドレスからのアクセスはロックされています。10分後に再試行してください。';
    } elseif ($user_id !== '' && get_lockout_seconds($user_id, 'user') > 0) {
        // ユーザーIDロックチェック
        $error = 'このアカウントはロックされています。10分後に再試行してください。';
    } elseif ($form_email === '' || !filter_var($form_email, FILTER_VALIDATE_EMAIL)) {
        $error = '認証コード送信先のメールアドレスを正しく入力してください。';
    } else {
        $user = verify_admin_credentials($user_id, $password);
        if ($user !== false) {
            clear_login_attempts($user_id, $client_ip);
            set_setting('2fa_email', $form_email);
            $code = generate_2fa_code($user_id);
            $sent = send_2fa_code_mail($form_email, $code);
            if ($sent) {
                header('Location: ' . BASE_URL . '/verify_2fa.php');
            } else {
                $error = 'メール送信に失敗しました。メールアドレスをご確認ください。';
            }
            exit;
        } else {
            record_login_failure($user_id, $client_ip);
            // 失敗記録後にロック判定
            if (get_lockout_seconds($user_id, 'user') > 0 || get_lockout_seconds($client_ip, 'ip') > 0) {
                $error = 'ログインに5回失敗しました。10分後に再試行してください。';
            } else {
                $db   = get_db();
                $stmt = $db->prepare(
                    "SELECT COUNT(*) FROM login_attempts
                     WHERE identifier = ? AND type = 'user'
                     AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
                );
                $stmt->execute([$user_id, LOGIN_LOCK_MINUTES]);
                $fails = (int)$stmt->fetchColumn();
                $left  = LOGIN_MAX_ATTEMPTS - $fails;
                if ($left === 1) {
                    $error = 'IDまたはパスワードが正しくありません。あと1回失敗するとロックされます。';
                } else {
                    $error = 'IDまたはパスワードが正しくありません。';
                }
            }
        }
    }
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

      <form method="post" autocomplete="off" id="loginForm">
        <!-- ブラウザの自動入力を吸収するダミーフィールド -->
        <input type="text"     style="display:none" tabindex="-1">
        <input type="password" style="display:none" tabindex="-1">
        <div class="mb-3">
          <label for="user_id" class="form-label">ユーザーID</label>
          <input type="text" class="form-control" id="user_id" name="user_id"
                 autocomplete="off" required>
        </div>
        <div class="mb-3">
          <label for="password" class="form-label">パスワード</label>
          <input type="password" class="form-control" id="password" name="password"
                 autocomplete="off" required>
        </div>
        <div class="mb-4">
          <label for="auth_email" class="form-label">認証コード送信先メールアドレス</label>
          <input type="email" class="form-control" id="auth_email" name="auth_email"
                 value="<?= htmlspecialchars($form_email) ?>"
                 autocomplete="email" required placeholder="例）xxx@gmail.com">
          <div class="form-text">入力したアドレスに6桁のコードを送ります。</div>
        </div>
        <div class="d-grid">
          <button type="submit" class="btn text-white" style="background:#3a7d5c">次へ（認証コード送信）</button>
        </div>
        <div class="text-center mt-3">
          <a href="<?= BASE_URL ?>/forgot_password.php" class="text-muted small">パスワードをお忘れの方はこちら</a>
        </div>
      </form>
    </div>
  </div>
  <p class="text-center mt-3">
    <a href="<?= BASE_URL ?>/reserve/index.php" class="text-muted small">← 予約ページへ戻る</a>
  </p>
</div>
<script>
// ブラウザの自動入力をページ読み込み後にクリア
window.addEventListener('load', function () {
    var uid = document.getElementById('user_id');
    var pw  = document.getElementById('password');
    uid.value = '';
    pw.value  = '';
    // 少し遅らせて再クリア（Windows Hello対策）
    setTimeout(function () {
        uid.value = '';
        pw.value  = '';
        uid.focus();
    }, 200);
});
</script>
</body>
</html>
