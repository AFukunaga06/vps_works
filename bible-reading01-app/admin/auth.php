<?php
/**
 * 聖書通読表 管理画面 - 認証・セキュリティ共通
 */

session_start();
require_once __DIR__ . '/config.php';

function requireLogin(): void {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function csrfInput(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(generateCsrfToken()) . '">';
}

/**
 * 共通ヘッダー HTML 出力
 */
function renderHeader(string $title, string $activeMenu = ''): void {
    $siteName = SITE_NAME;
    $menus = [
        ['href' => 'index.php',      'label' => 'ダッシュボード', 'key' => 'dashboard'],
        ['href' => 'members.php',    'label' => 'メンバー管理',   'key' => 'members'],
        ['href' => 'records.php',    'label' => '通読記録',       'key' => 'records'],
    ];
    echo <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title} - {$siteName}</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background: #f4f6f9; }
    .navbar-brand { font-weight: 700; }
    .nav-link.active { font-weight: 600; }
    .status-0 { color: #6c757d; }
    .status-1 { color: #fd7e14; }
    .status-2 { color: #198754; }
    .card { border: none; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
    .table th { background: #f8f9fa; font-weight: 600; }
    .page-header { padding: 1.25rem 0 1rem; border-bottom: 1px solid #dee2e6; margin-bottom: 1.5rem; }
    .btn-sm { font-size: .8rem; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php"><i class="bi bi-book-half me-1"></i>{$siteName}</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navmenu">
      <ul class="navbar-nav me-auto">
HTML;
    foreach ($menus as $m) {
        $active = ($m['key'] === $activeMenu) ? ' active' : '';
        echo "        <li class=\"nav-item\"><a class=\"nav-link{$active}\" href=\"{$m['href']}\">{$m['label']}</a></li>\n";
    }
    echo <<<HTML
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> ログアウト</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container-lg py-4">
HTML;
}

function renderFooter(): void {
    echo <<<HTML
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
}
