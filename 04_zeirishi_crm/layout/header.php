<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle ?? 'CRM') ?> - 税理士事務所 顧問先管理</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background: #f4f6fb; }
.sidebar { min-height: 100vh; background: #1a2744; }
.sidebar .nav-link { color: rgba(255,255,255,.75); border-radius: .375rem; }
.sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.15); }
.sidebar .brand { color: #fff; font-weight: 700; font-size: 1.1rem; }
.main-content { min-height: 100vh; }
</style>
</head>
<body>
<div class="container-fluid p-0">
<div class="row g-0">
  <!-- Sidebar -->
  <nav class="col-md-2 sidebar d-none d-md-block py-3 px-2">
    <div class="brand mb-4 ps-2"><i class="bi bi-briefcase-fill me-2"></i>ZeiriCRM</div>
    <ul class="nav flex-column gap-1">
      <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='index.php'?'active':'' ?>" href="index.php"><i class="bi bi-speedometer2 me-2"></i>ダッシュボード</a></li>
      <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='clients.php'?'active':'' ?>" href="clients.php"><i class="bi bi-building me-2"></i>顧問先台帳</a></li>
      <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='deadlines.php'?'active':'' ?>" href="deadlines.php"><i class="bi bi-calendar-event me-2"></i>申告期限</a></li>
      <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='tasks.php'?'active':'' ?>" href="tasks.php"><i class="bi bi-list-task me-2"></i>依頼タスク</a></li>
      <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='fees.php'?'active':'' ?>" href="fees.php"><i class="bi bi-cash-coin me-2"></i>月次報酬</a></li>
    </ul>
  </nav>

  <!-- Mobile topbar -->
  <nav class="navbar navbar-dark d-md-none w-100" style="background:#1a2744">
    <div class="container-fluid">
      <span class="navbar-brand fw-bold"><i class="bi bi-briefcase-fill me-2"></i>ZeiriCRM</span>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mobileNav">
        <ul class="navbar-nav">
          <li><a class="nav-link text-white" href="index.php">ダッシュボード</a></li>
          <li><a class="nav-link text-white" href="clients.php">顧問先台帳</a></li>
          <li><a class="nav-link text-white" href="deadlines.php">申告期限</a></li>
          <li><a class="nav-link text-white" href="tasks.php">依頼タスク</a></li>
          <li><a class="nav-link text-white" href="fees.php">月次報酬</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Main -->
  <main class="col-md-10 main-content p-4">
    <h4 class="mb-4 fw-bold text-secondary"><?= h($pageTitle ?? '') ?></h4>
