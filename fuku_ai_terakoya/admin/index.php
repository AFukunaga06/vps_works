<?php
require_once __DIR__ . '/_layout.php';
$user = require_admin();

$pdo = db();
$stats = [
    'res_pending'   => (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE status='pending'")->fetchColumn(),
    'res_confirmed' => (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE status='confirmed'")->fetchColumn(),
    'res_done'      => (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE status='done'")->fetchColumn(),
    'apps_new'      => (int)$pdo->query("SELECT COUNT(*) FROM instructor_applications WHERE status='new'")->fetchColumn(),
    'students'      => (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'mail_failed'   => (int)$pdo->query("SELECT COUNT(*) FROM mail_logs WHERE status='failed'")->fetchColumn(),
];

$recent_res = $pdo->query(
    "SELECT id, reserve_date, reserve_time, type, name, status, created_at
     FROM reservations ORDER BY id DESC LIMIT 10"
)->fetchAll();

$recent_apps = $pdo->query(
    "SELECT id, name, email, status, created_at
     FROM instructor_applications ORDER BY id DESC LIMIT 10"
)->fetchAll();

admin_header('ダッシュボード', $user);
?>
<h2 class="mb-4">ダッシュボード</h2>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="card p-3 h-100"><div class="text-muted small">未確定予約</div><div class="display-6 text-warning"><?= $stats['res_pending'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 h-100"><div class="text-muted small">確定予約</div><div class="display-6 text-info"><?= $stats['res_confirmed'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 h-100"><div class="text-muted small">新規 講師応募</div><div class="display-6 text-primary"><?= $stats['apps_new'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 h-100"><div class="text-muted small">受講者数</div><div class="display-6 text-success"><?= $stats['students'] ?></div></div></div>
</div>
<?php if ($stats['mail_failed'] > 0): ?>
  <div class="alert alert-warning">メール送信失敗が <?= $stats['mail_failed'] ?> 件あります。<a href="mail_logs.php?status=failed">確認</a></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-md-6">
    <h5>直近の予約</h5>
    <table class="table table-sm compact bg-white">
      <thead><tr><th>#</th><th>日時</th><th>種別</th><th>名前</th><th>状態</th></tr></thead>
      <tbody>
      <?php foreach ($recent_res as $r): ?>
        <tr>
          <td><a href="reservation_edit.php?id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
          <td><?= h($r['reserve_date']) ?> <?= h($r['reserve_time']) ?></td>
          <td><?= h($r['type']==='session' ? '本講座' : 'オリエン') ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= badge_status('reservation', $r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="col-md-6">
    <h5>直近の講師応募</h5>
    <table class="table table-sm compact bg-white">
      <thead><tr><th>#</th><th>名前</th><th>メール</th><th>状態</th></tr></thead>
      <tbody>
      <?php foreach ($recent_apps as $a): ?>
        <tr>
          <td><a href="instructor_application_edit.php?id=<?= (int)$a['id'] ?>">#<?= (int)$a['id'] ?></a></td>
          <td><?= h($a['name']) ?></td>
          <td><?= h($a['email']) ?></td>
          <td><?= badge_status('application', $a['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php admin_footer();
