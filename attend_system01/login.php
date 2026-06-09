<?php
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

if (!empty($_SESSION['login_ok'])) {
    header("Location: index.php");
    exit;
}

$VALID_USER      = 'tokyo';
$VALID_PASS_HASH = 'REDACTED_FOR_PUBLIC';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['userId']   ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === $VALID_USER && password_verify($pass, $VALID_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['login_ok'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "IDまたはパスワードが違います";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ABC○○商事㈱名簿管理 - ログイン</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    height: 100vh;
    background: #f0f4f8;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    font-family: 'Helvetica Neue', Arial, sans-serif;
}
.login-header {
    background: #2c5f8a;
    color: #fff;
    width: 100%;
    padding: 12px 20px;
    font-size: 1.1rem;
    font-weight: bold;
    position: fixed;
    top: 0;
    left: 0;
}
.login-box {
    background: #fff;
    padding: 40px 36px;
    border-radius: 10px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    width: 320px;
}
h2 {
    text-align: center;
    margin-bottom: 28px;
    color: #2c5f8a;
    font-size: 1.2rem;
}
label {
    display: block;
    font-size: .85rem;
    color: #555;
    margin-bottom: 4px;
}
input[type=text],
input[type=password] {
    width: 100%;
    padding: 9px 12px;
    margin-bottom: 16px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: .95rem;
}
input:focus {
    outline: none;
    border-color: #2c5f8a;
    box-shadow: 0 0 0 2px rgba(44,95,138,0.15);
}
button {
    width: 100%;
    padding: 10px;
    border: none;
    border-radius: 6px;
    background: #2c5f8a;
    color: #fff;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 4px;
}
button:hover { background: #1a4a70; }
.error {
    color: #c62828;
    background: #ffebee;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: .88rem;
    text-align: center;
    margin-bottom: 16px;
}
</style>
</head>
<body>
<div class="login-header">ABC○○商事㈱名簿管理</div>
<div class="login-box">
    <h2>ログイン</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <label>ユーザーID</label>
        <input type="text" name="userId" placeholder="ユーザーID" required autofocus>
        <label>パスワード</label>
        <input type="password" name="password" placeholder="パスワード" required>
        <button type="submit">ログイン</button>
    </form>
</div>
</body>
</html>
