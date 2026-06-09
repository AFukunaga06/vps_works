<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['logged_in'])) {
    header('Location: admin.php');
    exit;
}
if (!empty($_SESSION['user_id'])) {
    header('Location: mypage.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    // 管理者ログイン
    if ($user === ADMIN_USER && password_verify($pass, ADMIN_PASS)) {
        $_SESSION['logged_in'] = true;
        header('Location: admin.php');
        exit;
    }

    // 一般ユーザーログイン（ニックネーム＋パスワード）
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM personal_info WHERE nickname = ? AND nickname != '' AND password = ?");
    $stmt->execute([$user, $pass]);
    $row = $stmt->fetch();
    if ($row) {
        $_SESSION['user_id'] = $row['id'];
        header('Location: mypage.php');
        exit;
    }

    $error = 'ニックネームまたはパスワードが違います。';
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
            <label>ニックネーム（ログインID）</label>
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
    <div style="text-align:center; margin-top:16px;">
        <a href="signup.php" class="btn btn-success" style="width:100%; display:block; margin-bottom:10px;">新規登録はこちら</a>
        <span style="font-size:13px; color:#666;">IDパスワードを忘れた方は教会システム管理者にお尋ねください</span>
    </div>
</div>
</body>
</html>
