<?php
session_start();

/* ★ ログイン情報（固定） */
$VALID_USER = 'sugita';
$VALID_PASS_HASH = 'REDACTED_FOR_PUBLIC';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user = $_POST['userId'] ?? '';
    $pass = $_POST['password'] ?? '';

    /* 認証チェック */
        if ($user === $VALID_USER && password_verify($pass, $VALID_PASS_HASH)) {
        

        $_SESSION['login_ok'] = true;
        header("Location: ch_summary_0207_02.php");

        /* ← ここで目的ページへ飛ばす */
        header("Location: ch_summary_0223_06.php");
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
    font-family:"Yu Gothic","Hiragino Kaku Gothic ProN",sans-serif;
}

.login-card{
    width:360px;
    padding:40px;
    background:#ffffff;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,0.1);
    text-align:center;
}

.login-card h2{
    margin-bottom:25px;
    color:#2f5d3a;
}

input{
    width:100%;
    padding:12px;
    margin-bottom:15px;
    border:1px solid #cfded4;
    border-radius:6px;
    font-size:15px;
}

input:focus{
    outline:none;
    border-color:#6bbf73;
    box-shadow:0 0 4px rgba(107,191,115,0.4);
}

button{
    width:100%;
    padding:12px;
    background:#6bbf73;
    border:none;
    color:white;
    font-size:16px;
    border-radius:6px;
    cursor:pointer;
}

button:hover{
    background:#57a85f;
}

.error{
    color:#d33;
    margin-bottom:10px;
    font-size:14px;
}
</style>
</head>

<body>

<div class="login-card">
<h2>ログイン</h2>

<?php if(!empty($error)): ?>
<div class="error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" action="login.php">
<input type="text" name="userId" placeholder="ユーザーID" required>
<input type="password" name="password" placeholder="パスワード" required>
<button type="submit">ログイン</button>
</form>

</div>

</body>
</html>
