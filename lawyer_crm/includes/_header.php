<?php
// 業種別ブランド情報
$_lc_v = function_exists('lc_current_vertical') ? lc_current_vertical() : ['brand' => LC_SITE_NAME, 'sub' => '', 'icon' => 'briefcase-fill'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($_lc_v['brand']) ?><?= isset($page_title) ? ' - ' . h($page_title) : '' ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root { --lc-navy: #1a3a5c; --lc-gold: #c8a94a; }
body { background: #f2f4f7; font-family: 'Hiragino Sans','Meiryo',sans-serif; font-size: .9rem; }
.navbar { background: var(--lc-navy) !important; }
.navbar-brand { color: var(--lc-gold) !important; font-weight: 700; letter-spacing: .03em; }
.nav-link { color: rgba(255,255,255,.85) !important; }
.nav-link:hover, .nav-link.active { color: #fff !important; }
.nav-link.active { border-bottom: 2px solid var(--lc-gold); }
.sidebar { background: #fff; border-right: 1px solid #dee2e6; min-height: calc(100vh - 56px); }
.sidebar .nav-link { color: #343a40; padding: .45rem 1rem; border-radius: .35rem; }
.sidebar .nav-link:hover { background: #f0f4ff; }
.sidebar .nav-link.active { background: var(--lc-navy); color: #fff !important; }
.sidebar .nav-link .bi { width: 1.2em; }
.page-card { background: #fff; border-radius: .5rem; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 1.5rem; margin-bottom: 1.5rem; }
.badge-case-type { background: #e8f0fe; color: #1a3a5c; }
th { white-space: nowrap; }
.deadline-overdue { color: #dc3545; font-weight: 600; }
.deadline-soon { color: #fd7e14; font-weight: 600; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-md">
  <div class="container-fluid px-3">
    <a class="navbar-brand" href="<?= LC_BASE_URL ?>/index.php">
      <i class="bi bi-<?= h($_lc_v['icon']) ?> me-1"></i><?= h($_lc_v['brand']) ?>
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <i class="bi bi-list text-white fs-5"></i>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <span class="nav-link text-white-50 small">
            <i class="bi bi-person-circle me-1"></i><?= h($lc_user['name'] ?? '') ?>
            <span class="badge bg-secondary ms-1"><?= h(ROLE_MAP[$lc_user['role'] ?? ''] ?? '') ?></span>
          </span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= LC_BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-1"></i>ログアウト</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container-fluid">
  <div class="row">
    <!-- サイドバー -->
    <div class="col-md-2 sidebar py-3 d-none d-md-block">
      <nav class="nav flex-column gap-1">
        <a class="nav-link <?= ($page_nav??'')==='dashboard'?'active':'' ?>" href="<?= LC_BASE_URL ?>/index.php">
          <i class="bi bi-speedometer2"></i> ダッシュボード
        </a>
        <hr class="my-1">
        <div class="text-muted small px-2 mb-1">依頼者・案件</div>
        <a class="nav-link <?= ($page_nav??'')==='clients'?'active':'' ?>" href="<?= LC_BASE_URL ?>/clients.php">
          <i class="bi bi-people"></i> 依頼者一覧
        </a>
        <a class="nav-link <?= ($page_nav??'')==='cases'?'active':'' ?>" href="<?= LC_BASE_URL ?>/cases.php">
          <i class="bi bi-folder2-open"></i> 案件一覧
        </a>
        <hr class="my-1">
        <div class="text-muted small px-2 mb-1">業務管理</div>
        <a class="nav-link <?= ($page_nav??'')==='deadlines'?'active':'' ?>" href="<?= LC_BASE_URL ?>/deadlines.php">
          <i class="bi bi-calendar-event"></i> 期日管理
        </a>
        <a class="nav-link <?= ($page_nav??'')==='activities'?'active':'' ?>" href="<?= LC_BASE_URL ?>/activities.php">
          <i class="bi bi-journal-text"></i> 活動記録
        </a>
        <a class="nav-link <?= ($page_nav??'')==='billing'?'active':'' ?>" href="<?= LC_BASE_URL ?>/billing.php">
          <i class="bi bi-currency-yen"></i> 請求管理
        </a>
        <a class="nav-link <?= ($page_nav??'')==='contracts'?'active':'' ?>" href="<?= LC_BASE_URL ?>/contracts/list.php">
          <i class="bi bi-file-earmark-text"></i> 契約書レビュー
        </a>
        <a class="nav-link <?= ($page_nav??'')==='quotes'?'active':'' ?>" href="<?= LC_BASE_URL ?>/quotes/index.php">
          <i class="bi bi-calculator"></i> 見積算出
        </a>
        <a class="nav-link" href="/reservation/admin/index.php" target="_blank" rel="noopener">
          <i class="bi bi-calendar-check"></i> 予約管理 <i class="bi bi-box-arrow-up-right small text-muted"></i>
        </a>
        <?php if (($lc_user['role'] ?? '') === 'admin'): ?>
        <hr class="my-1">
        <a class="nav-link <?= ($page_nav??'')==='admin'?'active':'' ?>" href="<?= LC_BASE_URL ?>/admin/index.php">
          <i class="bi bi-gear"></i> スタッフ管理
        </a>
        <a class="nav-link <?= ($page_nav??'')==='billing'?'active':'' ?>" href="<?= LC_BASE_URL ?>/billing/portal.php">
          <i class="bi bi-credit-card-2-front"></i> 課金・プラン
        </a>
        <?php endif; ?>
        <?php if (!empty($lc_user['is_super_admin'])): ?>
        <hr class="my-1">
        <div class="text-muted small px-2 mb-1">運営管理</div>
        <a class="nav-link <?= ($page_nav??'')==='superadmin_tenants'?'active':'' ?>" href="<?= LC_BASE_URL ?>/admin/tenants/index.php">
          <i class="bi bi-building"></i> テナント管理
        </a>
        <a class="nav-link <?= ($page_nav??'')==='superadmin_audit'?'active':'' ?>" href="<?= LC_BASE_URL ?>/admin/tenants/audit.php">
          <i class="bi bi-clipboard-data"></i> 監査ログ
        </a>
        <?php endif; ?>
      </nav>
    </div>

    <!-- メインコンテンツ -->
    <div class="col-md-10 py-3 px-4">
      <?php if (isset($page_title)): ?>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0 fw-bold"><?= h($page_title) ?></h1>
        <?php if (isset($page_actions)) echo $page_actions; ?>
      </div>
      <?php endif; ?>
