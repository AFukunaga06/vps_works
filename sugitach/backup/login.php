<?php
session_start();

/* ログイン情報 */
$VALID_USER = 'sugita';
$VALID_PASS_HASH = 'REDACTED_FOR_PUBLIC';   // password_hashで作ったもの

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user = $_POST['userId'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === $VALID_USER && password_verify($pass, $VALID_PASS_HASH)) {

        session_regenerate_id(true);   // セキュリティ強化
        $_SESSION['login_ok'] = true;

        header("Location: main.php");  // ←ここ重要
        exit;

    } else {
        $error = "IDまたはパスワードが違います";
    }
}
?>