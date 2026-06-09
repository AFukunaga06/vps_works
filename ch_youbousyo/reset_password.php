<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';

if ($token === '') {
    header('Location: login.php');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT id FROM personal_info WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);
$row  = $stmt->fetch();

if (!$row) {
    $error = 'このリンクは無効または期限切れです。再度お試しください。';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = trim($_POST['password'] ?? '');
    $pass2 = trim($_POST['password2'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9]{4,}$/', $pass1)) {
        $error = 'パスワードは数字・英数で4文字以上で入力してください。';
    } elseif ($pass1 !== $pass2) {
        $error = 'パスワードが一致しません。';
    } else {
        $db->prepare("UPDATE personal_info SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?")
           ->execute([$pass1, $row['id']]);
        $success = 'パスワードを再設定しました。ログイン画面からログインしてください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>パスワード再設定</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-box">
    <h1>パスワード再設定</h1>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <div style="text-align:center; margin-top:16px;">
        <a href="forgot_password.php" style="font-size:13px; color:#3498db;">再度リセットを申請する</a>
    </div>
    <?php elseif ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <div style="text-align:center; margin-top:16px;">
        <a href="login.php" class="btn btn-primary" style="width:100%; display:block;">ログイン画面へ</a>
    </div>
    <?php else: ?>
    <form method="post">
        <div class="form-group">
            <label>新しいパスワード（数字・英数4文字以上）</label>
            <input type="password" name="password" required autofocus>
        </div>
        <div class="form-group">
            <label>新しいパスワード（確認）</label>
            <input type="password" name="password2" required>
        </div>
        <div style="text-align:center; margin-top:24px;">
            <button type="submit" class="btn btn-primary" style="width:100%;">再設定する</button>
        </div>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
