<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= GC_SITE_NAME ?><?= isset($page_title) ? ' - ' . h($page_title) : '' ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root { --gc-green: #1b5e35; --gc-light: #e8f5e9; --gc-gold: #b8860b; }
body { background: #f2f4f2; font-family: 'Hiragino Sans','Meiryo',sans-serif; font-size: .9rem; }
.navbar { background: var(--gc-green) !important; }
.navbar-brand { color: #fff !important; font-weight: 700; letter-spacing: .03em; }
.nav-link { color: rgba(255,255,255,.85) !important; }
.nav-link:hover, .nav-link.active { color: #fff !important; }
.nav-link.active { border-bottom: 2px solid #ffd700; }
.sidebar { background: #fff; border-right: 1px solid #dee2e6; min-height: calc(100vh - 56px); }
.sidebar .nav-link { color: #343a40; padding: .45rem 1rem; border-radius: .35rem; }
.sidebar .nav-link:hover { background: #e8f5e9; }
.sidebar .nav-link.active { background: var(--gc-green); color: #fff !important; }
.sidebar .nav-link .bi { width: 1.2em; }
.page-card { background: #fff; border-radius: .5rem; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 1.5rem; margin-bottom: 1.5rem; }
.badge-case-type { background: #e8f5e9; color: #1b5e35; }
th { white-space: nowrap; }
.deadline-overdue { color: #dc3545; font-weight: 600; }
.deadline-soon { color: #fd7e14; font-weight: 600; }
.btn-gc { background: var(--gc-green); color: #fff; }
.btn-gc:hover { background: #145228; color: #fff; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-md">
  <div class="container-fluid px-3">
    <a class="navbar-brand" href="<?= GC_BASE_URL ?>/index.php">
      <i class="bi bi-building me-1"></i><?= GC_SITE_NAME ?>
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <i class="bi bi-list text-white fs-5"></i>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <span class="nav-link text-white-50 small">
            <i class="bi bi-person-circle me-1"></i><?= h($gc_user['name'] ?? '') ?>
            <span class="badge bg-secondary ms-1"><?= h(ROLE_MAP[$gc_user['role'] ?? ''] ?? '') ?></span>
          </span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= GC_BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-1"></i>ログアウト</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container-fluid">
  <div class="row">
    <div class="col-md-2 sidebar py-3 d-none d-md-block">
      <nav class="nav flex-column gap-1">
        <a class="nav-link <?= ($page_nav??'')==='dashboard'?'active':'' ?>" href="<?= GC_BASE_URL ?>/index.php">
          <i class="bi bi-speedometer2"></i> ダッシュボード
        </a>
        <hr class="my-1">
        <div class="text-muted small px-2 mb-1">依頼人・案件</div>
        <a class="nav-link <?= ($page_nav??'')==='clients'?'active':'' ?>" href="<?= GC_BASE_URL ?>/clients.php">
          <i class="bi bi-people"></i> 依頼人台帳
        </a>
        <a class="nav-link <?= ($page_nav??'')==='cases'?'active':'' ?>" href="<?= GC_BASE_URL ?>/cases.php">
          <i class="bi bi-folder2-open"></i> 案件一覧
        </a>
        <hr class="my-1">
        <div class="text-muted small px-2 mb-1">業務管理</div>
        <a class="nav-link <?= ($page_nav??'')==='deadlines'?'active':'' ?>" href="<?= GC_BASE_URL ?>/deadlines.php">
          <i class="bi bi-calendar-event"></i> 期日管理
        </a>
        <a class="nav-link <?= ($page_nav??'')==='billing'?'active':'' ?>" href="<?= GC_BASE_URL ?>/billing.php">
          <i class="bi bi-currency-yen"></i> 報酬管理
        </a>
        <?php if (($gc_user['role'] ?? '') === 'admin'): ?>
        <hr class="my-1">
        <a class="nav-link <?= ($page_nav??'')==='admin'?'active':'' ?>" href="<?= GC_BASE_URL ?>/admin/index.php">
          <i class="bi bi-gear"></i> スタッフ管理
        </a>
        <?php endif; ?>
      </nav>
    </div>

    <div class="col-md-10 py-3 px-4">
      <?php if (isset($page_title)): ?>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0 fw-bold"><?= h($page_title) ?></h1>
        <?php if (isset($page_actions)) echo $page_actions; ?>
      </div>
      <?php endif; ?>
