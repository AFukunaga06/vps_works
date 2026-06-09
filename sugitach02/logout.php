<?php
session_start();

// active_session を削除
require_once __DIR__ . '/config.php';
$pdo->exec("DELETE FROM active_session");

// セッション変数を全て破棄
$_SESSION = [];

// セッション破棄
session_destroy();

// ログイン画面へ
header("Location: login.php");
exit;
