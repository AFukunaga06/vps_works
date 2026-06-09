<?php
session_start();
if (empty($_SESSION['login_ok'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>つばさ名簿</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    min-height: 100vh;
    background: #f0f4f8;
    display: flex;
    flex-direction: column;
    align-items: center;
    font-family: 'Helvetica Neue', Arial, sans-serif;
}
.header {
    background: #2c5f8a;
    color: #fff;
    width: 100%;
    padding: 12px 20px;
    font-size: 1.1rem;
    font-weight: bold;
    position: fixed;
    top: 0;
    left: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header a.logout {
    color: #fff;
    font-size: .85rem;
    text-decoration: none;
    border: 1px solid rgba(255,255,255,0.6);
    padding: 4px 12px;
    border-radius: 4px;
}
.header a.logout:hover { background: rgba(255,255,255,0.15); }
.main {
    margin-top: 80px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    width: 320px;
}
h2 {
    text-align: center;
    color: #2c5f8a;
    font-size: 1.1rem;
    margin-bottom: 8px;
}
.btn {
    display: block;
    width: 100%;
    padding: 18px;
    background: #2c5f8a;
    color: #fff;
    text-align: center;
    text-decoration: none;
    border-radius: 10px;
    font-size: 1.1rem;
    font-weight: bold;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    transition: background .2s;
}
.btn:hover { background: #1a4a70; }
.btn.green { background: #2e7d52; }
.btn.green:hover { background: #1b5e38; }
.btn.orange { background: #e65100; }
.btn.orange:hover { background: #bf360c; }
</style>
</head>
<body>
<div class="header">
    つばさ名簿
    <a href="logout.php" class="logout">ログアウト</a>
</div>
<div class="main">
    <h2>メニュー</h2>
    <a href="index_01h.php" class="btn">平日</a>
    <a href="index_01.php" class="btn green">土曜開所</a>
    <a href="report.php" class="btn orange">レポート（日計・月計・年計）</a>
</div>
</body>
</html>
