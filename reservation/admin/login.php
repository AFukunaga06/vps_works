<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

start_session_if_needed();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = 'セッションが無効です。再度お試しください。';
    } else {
        $login_id = trim($_POST['login_id'] ?? '');
        $pw       = $_POST['password'] ?? '';
        if (admin_login($login_id, $pw)) {
            redirect('index.php');
        }
        $error = 'ログインIDまたはパスワードが正しくありません。';
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>管理者ログイン｜予約システム</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">
<div class="login-box">
    <h1>予約システム<br><small>管理者ログイン</small></h1>
    <?php if ($error): ?>
        <p class="err"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <label>ログインID
            <input type="text" name="login_id" required autofocus autocomplete="username"
                   value="<?= h($_POST['login_id'] ?? '') ?>">
        </label>
        <label>パスワード
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn-primary">ログイン</button>
    </form>
    <p class="note">初期アカウント: <code>admin</code> / <code>admin123</code>（弁護士CRMの <code>lc_users</code> 共用）</p>
</div>
</body>
</html>
