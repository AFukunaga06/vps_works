<?php
session_start();

/* ★ ログイン情報（固定） */
$VALID_USER = 'tubasa';
$VALID_PASS_HASH = 'REDACTED_FOR_PUBLIC';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user = $_POST['userId'] ?? '';
    $pass = $_POST['password'] ?? '';

    /* 認証チェック */
    if ($user === $VALID_USER && password_verify($pass, $VALID_PASS_HASH)) {

        $_SESSION['login_ok'] = true;

        /* ← ここで目的ページへ飛ばす */
        header("Location: tubasa_syusseki_meibo_0213_01.php");
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
/* ここから下のCSSはそのままでOK */
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
