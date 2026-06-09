<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page_title ?? APP_NAME) ?> | <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root { --sidebar-w: 240px; }
    body { background: #f0f2f5; font-family: 'Hiragino Kaku Gothic ProN', 'Yu Gothic', sans-serif; }

    /* Sidebar */
    .sidebar {
      position: fixed; top: 0; left: 0; bottom: 0;
      width: var(--sidebar-w); background: #1a2744;
      display: flex; flex-direction: column; z-index: 200;
    }
    .sidebar-brand {
      padding: 1.1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,.1);
      color: #fff; text-decoration: none; display: flex; align-items: center; gap: .5rem;
    }
    .sidebar-brand:hover { color: #adc6ff; }
    .sidebar-brand .brand-title { font-weight: 700; font-size: 1rem; line-height: 1.2; }
    .sidebar-brand .brand-sub  { font-size: .7rem; color: rgba(255,255,255,.5); }
    .sidebar nav { flex: 1; padding: .5rem 0; overflow-y: auto; }
    .sidebar .nav-link {
      color: rgba(255,255,255,.72); padding: .6rem 1.25rem; border-radius: .4rem;
      margin: 2px 8px; display: flex; align-items: center; gap: .55rem; font-size: .9rem;
      transition: background .15s, color .15s;
    }
    .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,.12); }
    .sidebar .nav-link.active { color: #fff; background: rgba(108,168,255,.25); }
    .sidebar .nav-link i { font-size: 1rem; flex-shrink: 0; }
    .sidebar-footer {
      padding: .85rem 1rem; border-top: 1px solid rgba(255,255,255,.1);
    }

    /* Main layout */
    .main-wrap { margin-left: var(--sidebar-w); min-height: 100vh; display: flex; flex-direction: column; }
    .topbar {
      background: #fff; border-bottom: 1px solid #e0e4ea;
      padding: .75rem 1.75rem; display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 100;
    }
    .topbar-title { font-weight: 600; font-size: 1.05rem; color: #1a2744; margin: 0; }
    .page-content { padding: 1.75rem; flex: 1; }

    /* Cards */
    .stat-card { border: none; border-radius: .75rem; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
    .card { border-radius: .6rem; border: 1px solid #e4e8ef; box-shadow: 0 1px 3px rgba(0,0,0,.05); }

    /* Timeline */
    .timeline { position: relative; padding-left: 2rem; }
    .timeline::before {
      content: ''; position: absolute; left: .65rem; top: 0; bottom: 0;
      width: 2px; background: #dee2e6;
    }
    .timeline-item { position: relative; margin-bottom: 1.25rem; }
    .timeline-dot {
      position: absolute; left: -1.65rem; top: .25rem;
      width: 14px; height: 14px; border-radius: 50%;
      background: #0d6efd; border: 2px solid #fff; box-shadow: 0 0 0 2px #0d6efd;
    }

    /* Badge helpers */
    .badge { font-weight: 500; }

    /* Tables */
    .table th { background: #f8f9fa; font-weight: 600; font-size: .85rem; color: #495057; }
    .table td { vertical-align: middle; }
    .table-hover tbody tr:hover { background: #f0f5ff; }
  </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <a href="index.php" class="sidebar-brand">
    <i class="bi bi-briefcase-fill fs-4"></i>
    <div>
      <div class="brand-title">弁護士事務所CRM</div>
      <div class="brand-sub">Lawyer Management</div>
    </div>
  </a>
  <nav>
    <?php
    $cur = basename($_SERVER['PHP_SELF']);
    $nav = [
      'index.php'    => ['bi-speedometer2',      'ダッシュボード'],
      'clients.php'  => ['bi-people-fill',        '依頼人台帳'],
      'cases.php'    => ['bi-folder2-open',       '案件管理'],
      'deadlines.php'=> ['bi-calendar-event-fill','期日リマインド'],
      'billing.php'  => ['bi-currency-yen',       '着手金・報酬管理'],
      'documents.php'=> ['bi-file-earmark-text',  '書類管理'],
    ];
    foreach ($nav as $file => [$icon, $label]):
      $active = ($cur === $file || ($file !== 'index.php' && str_starts_with($cur, str_replace('.php','',$file)))) ? 'active' : '';
    ?>
    <a href="<?= $file ?>" class="nav-link <?= $active ?>">
      <i class="bi <?= $icon ?>"></i><?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="text-white-50 small mb-2">
      <i class="bi bi-person-circle me-1"></i><?= h($_SESSION['user_name'] ?? '') ?>
    </div>
    <a href="logout.php" class="btn btn-outline-light btn-sm w-100">
      <i class="bi bi-box-arrow-right me-1"></i>ログアウト
    </a>
  </div>
</div>

<!-- Main -->
<div class="main-wrap">
  <div class="topbar">
    <h1 class="topbar-title">
      <?= h($page_title ?? APP_NAME) ?>
    </h1>
    <span class="text-muted small">
      <i class="bi bi-calendar3 me-1"></i><?= date('Y年m月d日') ?>（<?= ['日','月','火','水','木','金','土'][date('w')] ?>）
    </span>
  </div>
  <div class="page-content">
