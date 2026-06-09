<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'ユーザーIDまたはパスワードが違います。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ログイン</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-box">
    <h1>ログイン</h1>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>ユーザーID</label>
            <input type="text" name="username" required autofocus>
        </div>
        <div class="form-group">
            <label>パスワード</label>
            <input type="password" name="password" required>
        </div>
        <div style="text-align:center; margin-top:24px;">
            <button type="submit" class="btn btn-primary" style="width:100%;">ログイン</button>
        </div>
    </form>
</div>
</body>
</html>
