<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> CRM<?= isset($page_title) ? ' - ' . crm_h($page_title) : '' ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root { --g: #3a7d5c; --gd: #2c5f44; }
body { background: #f4f6f4; font-family: 'Hiragino Sans','Meiryo',sans-serif; font-size: .9rem; }
.sidebar { background: #fff; border-right: 1px solid #dee2e6; min-height: calc(100vh - 52px); }
.sidebar .nav-link { color: #343a40; padding: .45rem 1rem; border-radius: .35rem; }
.sidebar .nav-link:hover { background: #e8f5ee; }
.sidebar .nav-link.active { background: var(--g); color: #fff !important; }
.sidebar .nav-link .bi { width: 1.2em; }
.sidebar hr { margin: .35rem 0; }
.topbar { background: var(--g); }
.topbar a, .topbar span { color: rgba(255,255,255,.9) !important; }
.topbar .navbar-brand { font-weight: 700; color: #fff !important; }
.page-card { background: #fff; border-radius: .5rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 1.25rem; margin-bottom: 1.25rem; }
th { white-space: nowrap; }
.badge-type { background: #e8f5ee; color: #2c5f44; }
/* モバイル対応 */
.mobile-nav { display:none; position:fixed; bottom:0; left:0; right:0; background:#fff; border-top:1px solid #dee2e6; z-index:1000; padding:.4rem 0; }
.mobile-nav a { color:#555; font-size:.65rem; text-align:center; flex:1; padding:.3rem .2rem; text-decoration:none; display:flex; flex-direction:column; align-items:center; }
.mobile-nav a.active { color:var(--g); }
.mobile-nav .bi { font-size:1.2rem; }
@media (max-width:767px) {
  .mobile-nav { display:flex; }
  body { padding-bottom:70px; }
  .page-card { padding:.85rem; }
  .table-responsive { overflow-x:auto; }
}
</style>
</head>
<body>
<nav class="navbar topbar navbar-expand-md py-1 px-3">
  <a class="navbar-brand fs-6" href="<?= CRM_URL ?>/index.php">
    <i class="bi bi-briefcase me-1"></i><?= SITE_NAME ?> <small class="opacity-75">CRM</small>
  </a>
  <div class="ms-auto d-flex align-items-center gap-3">
    <a href="<?= BASE_URL ?>/admin/index.php" class="small"><i class="bi bi-calendar3 me-1"></i>予約管理</a>
    <a href="<?= BASE_URL ?>/login.php" class="small"><i class="bi bi-box-arrow-right me-1"></i>ログアウト</a>
  </div>
</nav>

<div class="container-fluid">
  <div class="row">
    <div class="col-md-2 sidebar py-3 d-none d-md-block">
      <nav class="nav flex-column gap-1 px-1">
        <a class="nav-link <?= ($nav??'')==='dash'?'active':'' ?>" href="<?= CRM_URL ?>/index.php">
          <i class="bi bi-speedometer2"></i> ダッシュボード
        </a>
        <hr>
        <small class="text-muted px-2">依頼者・案件</small>
        <a class="nav-link <?= ($nav??'')==='clients'?'active':'' ?>" href="<?= CRM_URL ?>/clients.php">
          <i class="bi bi-people"></i> 依頼者一覧
        </a>
        <a class="nav-link <?= ($nav??'')==='cases'?'active':'' ?>" href="<?= CRM_URL ?>/cases.php">
          <i class="bi bi-folder2-open"></i> 案件一覧
        </a>
        <hr>
        <small class="text-muted px-2">業務管理</small>
        <a class="nav-link <?= ($nav??'')==='deadlines'?'active':'' ?>" href="<?= CRM_URL ?>/deadlines.php">
          <i class="bi bi-calendar-event"></i> 期日管理
        </a>
        <a class="nav-link <?= ($nav??'')==='activities'?'active':'' ?>" href="<?= CRM_URL ?>/activities.php">
          <i class="bi bi-journal-text"></i> 活動記録
        </a>
        <a class="nav-link <?= ($nav??'')==='billing'?'active':'' ?>" href="<?= CRM_URL ?>/billing.php">
          <i class="bi bi-currency-yen"></i> 請求管理
        </a>
        <hr>
        <a class="nav-link <?= ($nav??'')==='gcal'?'active':'' ?>" href="<?= CRM_URL ?>/gcal/setup.php">
          <i class="bi bi-calendar-check"></i> Googleカレンダー
          <?php if (function_exists('gcal_is_configured') && gcal_is_configured()): ?>
          <span class="badge bg-success ms-1" style="font-size:.65rem">連携中</span>
          <?php endif; ?>
        </a>
      </nav>
    </div>
    <div class="col-md-10 py-3 px-3">
      <?php if (isset($page_title)): ?>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0 fw-bold"><?= crm_h($page_title) ?></h1>
        <?php if (isset($page_btn)) echo $page_btn; ?>
      </div>
      <?php endif; ?>
<!-- モバイル用ボトムナビ -->
<nav class="mobile-nav d-md-none">
  <a href="<?= CRM_URL ?>/index.php" class="<?= ($nav??'')==='dash'?'active':'' ?>"><i class="bi bi-speedometer2"></i>ダッシュ</a>
  <a href="<?= CRM_URL ?>/clients.php" class="<?= ($nav??'')==='clients'?'active':'' ?>"><i class="bi bi-people"></i>依頼者</a>
  <a href="<?= CRM_URL ?>/cases.php" class="<?= ($nav??'')==='cases'?'active':'' ?>"><i class="bi bi-folder2-open"></i>案件</a>
  <a href="<?= CRM_URL ?>/deadlines.php" class="<?= ($nav??'')==='deadlines'?'active':'' ?>"><i class="bi bi-calendar-event"></i>期日</a>
  <a href="<?= CRM_URL ?>/billing.php" class="<?= ($nav??'')==='billing'?'active':'' ?>"><i class="bi bi-currency-yen"></i>請求</a>
</nav>
