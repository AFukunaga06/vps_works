<?php
$flash = get_flash();
$current_page = basename(dirname($_SERVER['PHP_SELF'])).'/'.basename($_SERVER['PHP_SELF']);
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#f0f4f8}
.sidebar{width:230px;min-height:100vh;background:#1e3a5f;flex-shrink:0}
.sidebar .brand{background:#162d4a;padding:16px;color:#fff;font-size:13px;font-weight:bold;line-height:1.5}
.sidebar .nav-link{color:#cce0f5;font-size:13px;padding:9px 16px;border-radius:0;border-bottom:1px solid #254d7a}
.sidebar .nav-link:hover,.sidebar .nav-link.active{background:#254d7a;color:#fff}
.sidebar .nav-section{color:#7aadd4;font-size:10px;text-transform:uppercase;padding:10px 16px 4px;letter-spacing:1px}
.main-content{flex:1;padding:20px;overflow-x:auto}
.stat-card{border-top:3px solid #2c5f8a}
.badge.bg-purple{background:#7b2d8b!important}
.table th{font-size:12px;background:#f0f4f8}
</style>
</head>
<body>
<div class="d-flex">
<div class="sidebar">
  <div class="brand"><i class="bi bi-church"></i> <?= h(APP_NAME) ?></div>
  <nav>
    <div class="nav-section">メイン</div>
    <a href="<?= BASE_URL ?>/" class="nav-link <?= (basename($_SERVER['PHP_SELF'])=='index.php'&&dirname($_SERVER['PHP_SELF'])==dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF'])) ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> ダッシュボード</a>
    <div class="nav-section">人物管理</div>
    <a href="<?= BASE_URL ?>/persons/" class="nav-link"><i class="bi bi-people"></i> 人物一覧</a>
    <a href="<?= BASE_URL ?>/persons/form.php" class="nav-link"><i class="bi bi-person-plus"></i> 新規登録</a>
    <div class="nav-section">活動管理</div>
    <a href="<?= BASE_URL ?>/inquiries/" class="nav-link"><i class="bi bi-chat-dots"></i> 問い合わせ</a>
    <a href="<?= BASE_URL ?>/contact/index.php" class="nav-link" target="_blank"><i class="bi bi-envelope-plus"></i> お問い合わせフォーム</a>
    <a href="<?= BASE_URL ?>/attendance/" class="nav-link"><i class="bi bi-calendar-check"></i> 出席記録</a>
    <a href="<?= BASE_URL ?>/follows/" class="nav-link"><i class="bi bi-telephone-forward"></i> フォロー履歴</a>
    <a href="<?= BASE_URL ?>/tasks/" class="nav-link"><i class="bi bi-check2-square"></i> タスク</a>
    <div class="nav-section">グループ・報告</div>
    <a href="<?= BASE_URL ?>/groups/" class="nav-link"><i class="bi bi-diagram-3"></i> グループ</a>
    <a href="<?= BASE_URL ?>/reports/" class="nav-link"><i class="bi bi-bar-chart"></i> 集計・CSV</a>
    <?php if(is_admin()): ?>
    <div class="nav-section">管理</div>
    <a href="<?= BASE_URL ?>/users.php" class="nav-link"><i class="bi bi-shield-lock"></i> ユーザー管理</a>
    <?php endif; ?>
    <div class="nav-section">外部リンク</div>
    <a href="https://sugitach02.afuku5906.com/login.php" class="nav-link" target="_blank"><i class="bi bi-box-arrow-up-right"></i> 出席管理システム</a>
  </nav>
  <div style="padding:12px 16px;color:#7aadd4;font-size:12px;margin-top:auto">
    <i class="bi bi-person-circle"></i> <?= h($_SESSION['user_name']??'') ?>
    (<?= role_label($_SESSION['user_role']??'') ?>)<br>
    <a href="<?= BASE_URL ?>/logout.php" style="color:#7aadd4;font-size:11px"><i class="bi bi-box-arrow-right"></i> ログアウト</a>
  </div>
</div>
<div class="main-content">
<?php if($flash): ?>
<div class="alert alert-<?= $flash['type']==='success'?'success':($flash['type']==='error'?'danger':'warning') ?> alert-dismissible fade show" role="alert">
  <?= h($flash['msg']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
