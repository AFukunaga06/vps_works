<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= CRM_SITE_NAME ?><?= isset($page_title) ? ' - ' . h($page_title) : '' ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
:root { --crm-green: #3a7d5c; }
body { background: #f4f6f4; font-family: 'Hiragino Sans','Meiryo',sans-serif; }
.navbar { background: var(--crm-green) !important; }
.navbar-brand, .nav-link { color: #fff !important; }
.nav-link:hover { opacity: .8; }
.nav-link.active { font-weight: bold; border-bottom: 2px solid #fff; }
.page-header { background: #fff; border-bottom: 1px solid #dee2e6; padding: 1rem 0; margin-bottom: 1.5rem; }
.page-header h1 { font-size: 1.25rem; margin: 0; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-md">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= CRM_BASE_URL ?>/index.php"><?= CRM_SITE_NAME ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span style="color:#fff">&#9776;</span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= CRM_BASE_URL ?>/index.php">顧客一覧</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= CRM_BASE_URL ?>/export.php">CSV出力</a></li>
        <?php if (($staff['role'] ?? '') === 'admin'): ?>
        <li class="nav-item"><a class="nav-link" href="<?= CRM_BASE_URL ?>/admin/index.php">スタッフ管理</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item">
          <span class="nav-link text-white-50 small"><?= h($staff['name'] ?? '') ?></span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= CRM_BASE_URL ?>/logout.php">ログアウト</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<div class="page-header">
  <div class="container">
    <h1><?= h($page_title ?? '') ?></h1>
  </div>
</div>
<div class="container pb-5">
