<?php
session_start();

/* ★ ログイン情報（固定） */
$VALID_USER = 'admin';
$VALID_PASS_HASH = 'REDACTED_FOR_PUBLIC';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user = $_POST['userId'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === $VALID_USER && password_verify($pass, $VALID_PASS_HASH)) {

        $_SESSION['login_ok'] = true;
        header("Location: church_attendance/ch_summary_0207_02.php");
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
<title>出欠管理ログイン</title>

<style>
body{
    margin:0;
    height:100vh;
    background:#eaf4ec; /* 薄い緑 */
    display:flex;
    justify-content:center;
    align-items:center;
    font-family: "Hiragino Kaku Gothic ProN","Yu Gothic",sans-serif;
}

.login-box{
    background:#ffffff;
    padding:40px;
    border-radius:20px;
    box-shadow:0 8px 25px rgba(0,0,0,0.1);
    width:320px;
}

h2{
    text-align:center;
    margin-bottom:30px;
    color:#1f7a3b;
}

input{
    width:100%;
    padding:10px;
    margin-bottom:15px;
    border-radius:8px;
    border:1px solid #ccc;
    font-size:14px;
}

button{
    width:100%;
    padding:10px;
    border:none;
    border-radius:8px;
    background:#1f7a3b;
    color:white;
    font-size:14px;
    cursor:pointer;
}

button:hover{
    background:#16652f;
}

.error{
    color:red;
    text-align:center;
    margin-bottom:10px;
}
</style>
</head>

<body>

<div class="login-box">
    <h2>ログイン</h2>

    <?php if($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="userId" placeholder="ユーザーID" required>
        <input type="password" name="password" placeholder="パスワード" required>
        <button type="submit">ログイン</button>
    </form>
</div>

</body>
</html>
