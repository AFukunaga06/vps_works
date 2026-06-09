<?php
// 管理画面の共通ヘッダ — require_admin() 済みである前提
$me = current_admin();
$current = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title><?= h($page_title ?? '予約管理') ?>｜予約システム</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">
<header class="admin-header">
    <div class="admin-brand">予約システム <span class="muted">/ 管理</span></div>
    <nav class="admin-nav">
        <a href="index.php"          class="<?= $current === 'index.php'          ? 'on' : '' ?>">予約一覧</a>
        <a href="reservation_new.php" class="<?= $current === 'reservation_new.php' ? 'on' : '' ?>">新規予約</a>
        <a href="slots.php"          class="<?= $current === 'slots.php'          ? 'on' : '' ?>">営業設定</a>
        <a href="exceptions.php" class="<?= $current === 'exceptions.php' ? 'on' : '' ?>">例外日</a>
    </nav>
    <div class="admin-user">
        <?= h($me['name'] ?: $me['email']) ?>
        <a class="btn-link" href="logout.php">ログアウト</a>
    </div>
</header>
<main class="admin-main">
