<?php
require_once __DIR__ . '/_layout.php';
$user = require_admin();

$pdo = db();
$status_filter = (string)($_GET['status'] ?? '');
$where = '';
$params = [];
if (in_array($status_filter, ['pending','completed','failed','refunded','cancelled'], true)) {
    $where = 'WHERE p.status = ?';
    $params[] = $status_filter;
}

$sql = "SELECT p.*, pl.name AS plan_name, pl.type AS plan_type
          FROM payments p
          LEFT JOIN plans pl ON pl.id = p.plan_id
          $where
         ORDER BY p.id DESC
         LIMIT 200";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// 集計
$summary = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status='failed'    THEN 1 ELSE 0 END) AS failed,
        SUM(CASE WHEN status='completed' THEN amount ELSE 0 END) AS total_amount
       FROM payments"
)->fetch();

$subs = $pdo->query(
    "SELECT s.*, st.name AS student_name, st.email AS student_email, pl.name AS plan_name
       FROM subscriptions s
       JOIN students st ON st.id = s.student_id
       JOIN plans    pl ON pl.id = s.plan_id
      ORDER BY s.id DESC LIMIT 50"
)->fetchAll();

admin_header('決済管理', $user);
?>
<h2 class="mb-4">決済管理</h2>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="card p-3"><div class="text-muted small">完了</div><div class="display-6 text-success"><?= (int)$summary['completed'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3"><div class="text-muted small">保留</div><div class="display-6 text-warning"><?= (int)$summary['pending'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3"><div class="text-muted small">失敗</div><div class="display-6 text-danger"><?= (int)$summary['failed'] ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3"><div class="text-muted small">入金合計</div><div class="display-6 text-success">¥<?= number_format((int)$summary['total_amount']) ?></div></div></div>
</div>

<div class="mb-3">
  <a href="payments.php" class="btn btn-sm <?= $status_filter==='' ? 'btn-secondary' : 'btn-outline-secondary' ?>">すべて</a>
  <a href="payments.php?status=completed" class="btn btn-sm <?= $status_filter==='completed' ? 'btn-success' : 'btn-outline-success' ?>">完了</a>
  <a href="payments.php?status=pending"   class="btn btn-sm <?= $status_filter==='pending' ? 'btn-warning' : 'btn-outline-warning' ?>">保留</a>
  <a href="payments.php?status=failed"    class="btn btn-sm <?= $status_filter==='failed' ? 'btn-danger' : 'btn-outline-danger' ?>">失敗</a>
  <span class="text-muted small ms-2">環境: <?= h(SQUARE_ENV) ?></span>
</div>

<h5>決済履歴</h5>
<div class="table-responsive">
<table class="table table-sm table-hover compact bg-white">
  <thead>
    <tr>
      <th>#</th><th>受付日時</th><th>プラン</th><th>金額</th>
      <th>受講者</th><th>状態</th><th>環境</th><th>SqPay ID</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)$r['id'] ?></td>
      <td><?= h(substr((string)$r['created_at'], 0, 16)) ?></td>
      <td><?= h($r['plan_name']) ?></td>
      <td class="text-end">¥<?= number_format((int)$r['amount']) ?></td>
      <td>
        <?= h($r['customer_name']) ?><br>
        <small class="text-muted"><?= h($r['customer_email']) ?></small>
      </td>
      <td>
        <span class="badge bg-<?= $r['status']==='completed'?'success':($r['status']==='pending'?'warning':($r['status']==='failed'?'danger':'secondary')) ?>">
          <?= h($r['status']) ?>
        </span>
      </td>
      <td><small><?= h($r['square_environment']) ?></small></td>
      <td><small class="text-muted"><?= h(substr((string)$r['square_payment_id'], 0, 16)) ?></small></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <tr><td colspan="8" class="text-center text-muted py-4">該当なし</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

<h5 class="mt-5">月謝サブスクリプション</h5>
<div class="table-responsive">
<table class="table table-sm compact bg-white">
  <thead>
    <tr><th>#</th><th>受講者</th><th>プラン</th><th>状態</th><th>開始日</th><th>次回請求</th></tr>
  </thead>
  <tbody>
  <?php foreach ($subs as $s): ?>
    <tr>
      <td>#<?= (int)$s['id'] ?></td>
      <td><?= h($s['student_name']) ?><br><small class="text-muted"><?= h($s['student_email']) ?></small></td>
      <td><?= h($s['plan_name']) ?></td>
      <td><span class="badge bg-<?= $s['status']==='active'?'success':($s['status']==='paused'?'warning':'secondary') ?>"><?= h($s['status']) ?></span></td>
      <td><?= h($s['start_date']) ?></td>
      <td><?= h((string)$s['next_billing_date']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$subs): ?>
    <tr><td colspan="6" class="text-center text-muted py-4">該当なし</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

<div class="alert alert-light small text-muted mt-4">
  <strong>運用メモ</strong><br>
  ・決済リンク発行から完了までは Square Webhook で同期されます（payments/webhook.php）。<br>
  ・受講者がリダイレクト戻りでcomplete.phpに着いたタイミングでも軽い同期処理が走ります。<br>
  ・月謝の毎月課金は半自動方式です。月初に「次回請求」が来た受講者へ決済リンクをメール送付してください。
</div>

<?php admin_footer();
