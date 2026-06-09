<?php
require_once __DIR__ . '/helpers.php';

function pageHead(string $title = ''): void {
    $appName = APP_NAME;
    $fullTitle = $title ? h($title) . ' | ' . h($appName) : h($appName);
    echo <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$fullTitle}</title>
<link rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
HTML;
}

function navbar(): void {
    $user = currentUser();
    $name = $user ? h($user['name']) : '';
    $role = $user ? h($user['role']) : '';
    echo <<<HTML
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="index.php">
      <i class="bi bi-hospital"></i> クリニック予約管理
    </a>
    <button class="navbar-toggler" type="button"
      data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link" href="index.php"><i class="bi bi-house"></i> ダッシュボード</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="appointments.php"><i class="bi bi-calendar3"></i> 予約一覧</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="appointment_new.php"><i class="bi bi-calendar-plus"></i> 新規予約</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="patients.php"><i class="bi bi-people"></i> 患者台帳</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="consultations.php"><i class="bi bi-clipboard2-pulse"></i> 診察履歴</a>
        </li>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> {$name}
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text text-muted small">役職: {$role}</span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> ログアウト</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
HTML;
}

function pageFooter(): void {
    echo <<<HTML
<footer class="mt-5 py-3 text-center text-muted small border-top">
  &copy; 2026 クリニック予約管理システム
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
HTML;
}
