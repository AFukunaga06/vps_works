<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

function admin_header(string $title, ?array $user = null): void {
    $user = $user ?? ($_SESSION['admin'] ?? null);
    ?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> - <?= h(APP_NAME) ?> 管理</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  body{background:#f5f6f8}
  .navbar-brand{font-weight:700;color:#4a7c59 !important}
  .nav-link.active{font-weight:600;color:#4a7c59 !important}
  .badge-status-pending{background:#f0ad4e}
  .badge-status-confirmed{background:#5bc0de}
  .badge-status-done{background:#5cb85c}
  .badge-status-cancelled{background:#999}
  .badge-status-new{background:#0d6efd}
  .badge-status-reviewing{background:#f0ad4e}
  .badge-status-accepted{background:#5cb85c}
  .badge-status-rejected{background:#dc3545}
  .badge-status-sent{background:#5cb85c}
  .badge-status-failed{background:#dc3545}
  table.compact td, table.compact th{font-size:.92em;vertical-align:middle}
  .container-narrow{max-width:1100px}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm mb-4">
  <div class="container container-narrow">
    <a class="navbar-brand" href="<?= h(APP_ROOT_URL) ?>/admin/"><i class="bi bi-mortarboard-fill"></i> <?= h(APP_NAME) ?> 管理</a>
    <?php if ($user): ?>
    <ul class="navbar-nav me-auto">
      <li class="nav-item"><a class="nav-link" href="<?= h(APP_ROOT_URL) ?>/admin/index.php">ダッシュボード</a></li>
      <li class="nav-item"><a class="nav-link" href="<?= h(APP_ROOT_URL) ?>/admin/reservations.php">予約</a></li>
      <li class="nav-item"><a class="nav-link" href="<?= h(APP_ROOT_URL) ?>/admin/instructor_applications.php">講師応募</a></li>
      <li class="nav-item"><a class="nav-link" href="<?= h(APP_ROOT_URL) ?>/admin/students.php">受講者</a></li>
      <li class="nav-item"><a class="nav-link" href="<?= h(APP_ROOT_URL) ?>/admin/payments.php">決済</a></li>
      <li class="nav-item"><a class="nav-link" href="<?= h(APP_ROOT_URL) ?>/admin/mail_logs.php">メールログ</a></li>
    </ul>
    <span class="text-muted small me-3"><i class="bi bi-person-circle"></i> <?= h($user['name']) ?></span>
    <a class="btn btn-outline-secondary btn-sm" href="<?= h(APP_ROOT_URL) ?>/admin/logout.php">ログアウト</a>
    <?php endif; ?>
  </div>
</nav>
<main class="container container-narrow pb-5">
<?php
    foreach (flash_take() as $f) {
        $cls = ($f['type'] === 'error') ? 'danger' : (($f['type'] === 'success') ? 'success' : 'info');
        echo '<div class="alert alert-' . h($cls) . '">' . h($f['msg']) . '</div>';
    }
}

function admin_footer(): void {
    ?>
</main>
<footer class="container container-narrow text-muted small pb-4">
  &copy; <?= date('Y') ?> <?= h(APP_NAME) ?>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}

function badge_status(string $type, string $status): string {
    $label = $status;
    $map = [
        'reservation' => ['pending'=>'未確定','confirmed'=>'確定','done'=>'完了','cancelled'=>'キャンセル'],
        'application' => ['new'=>'新規','reviewing'=>'検討中','accepted'=>'採用','rejected'=>'不採用'],
        'mail'        => ['sent'=>'送信','failed'=>'失敗'],
    ];
    if (isset($map[$type][$status])) $label = $map[$type][$status];
    return '<span class="badge badge-status-' . h($status) . '">' . h($label) . '</span>';
}
