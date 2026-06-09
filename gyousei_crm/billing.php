<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$gc_user    = gc_require_login();
$page_title = '報酬管理';
$page_nav   = 'billing';

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? 0);
$show  = $_GET['show'] ?? 'unpaid';

$db = get_db();
$where = ['1=1']; $params = [];
if ($year)  { $where[] = 'YEAR(b.billed_date)=?';  $params[] = $year; }
if ($month) { $where[] = 'MONTH(b.billed_date)=?'; $params[] = $month; }
if ($show === 'unpaid') { $where[] = 'b.is_paid=0'; }
elseif ($show === 'paid') { $where[] = 'b.is_paid=1'; }
$w = implode(' AND ', $where);

$rows = $db->prepare(
    "SELECT b.*, cs.case_name, cs.case_number, cl.name AS client_name, cl.company_name
     FROM gc_billing b
     JOIN gc_cases cs ON cs.id=b.case_id
     JOIN gc_clients cl ON cl.id=cs.client_id
     WHERE $w ORDER BY b.is_paid ASC, b.billed_date DESC"
);
$rows->execute($params);
$rows = $rows->fetchAll();

$total_all  = array_sum(array_column($rows, 'amount'));
$total_paid = array_sum(array_column(array_filter($rows, fn($r) => $r['is_paid']), 'amount'));
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<div class="row g-3 mb-3">
  <div class="col-auto"><div class="border rounded px-3 py-2 text-center small"><div class="text-muted">合計</div><div class="fw-bold"><?= fmt_money($total_all) ?></div></div></div>
  <div class="col-auto"><div class="border rounded px-3 py-2 text-center small"><div class="text-muted">入金済み</div><div class="fw-bold text-success"><?= fmt_money($total_paid) ?></div></div></div>
  <div class="col-auto"><div class="border rounded px-3 py-2 text-center small"><div class="text-muted">未入金</div><div class="fw-bold text-danger"><?= fmt_money($total_all - $total_paid) ?></div></div></div>
</div>

<div class="page-card">
  <form method="get" class="row g-2 mb-3 align-items-end">
    <div class="col-auto">
      <label class="form-label small fw-bold mb-1">年</label>
      <select name="year" class="form-select form-select-sm">
        <?php for ($y = date('Y'); $y >= date('Y')-3; $y--): ?>
        <option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?>年</option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label small fw-bold mb-1">月</label>
      <select name="month" class="form-select form-select-sm">
        <option value="0" <?= !$month?'selected':'' ?>>全月</option>
        <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $month===$m?'selected':'' ?>><?= $m ?>月</option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label small fw-bold mb-1">状態</label>
      <select name="show" class="form-select form-select-sm">
        <option value="unpaid" <?= $show==='unpaid'?'selected':'' ?>>未入金のみ</option>
        <option value="paid"   <?= $show==='paid'?'selected':'' ?>>入金済みのみ</option>
        <option value="all"    <?= $show==='all'?'selected':'' ?>>全て</option>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-primary">絞り込み</button>
    </div>
  </form>

  <div class="text-muted small mb-2">全 <?= count($rows) ?> 件</div>

  <?php if (empty($rows)): ?>
    <p class="text-muted text-center py-4">条件に該当する報酬がありません。</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>依頼人</th><th>案件名</th><th>種別</th><th>内容</th><th>金額</th><th>請求日</th><th>入金日</th><th>状態</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $b): ?>
      <tr class="<?= $b['is_paid'] ? '' : 'table-warning bg-opacity-25' ?>">
        <td class="small"><?= h($b['company_name'] ?: $b['client_name']) ?></td>
        <td><a href="<?= GC_BASE_URL ?>/case_view.php?id=<?= $b['case_id'] ?>&tab=billing" class="text-decoration-none small"><?= h($b['case_name']) ?></a></td>
        <td><span class="badge bg-secondary"><?= h(BILLING_TYPE_MAP[$b['billing_type']] ?? '') ?></span></td>
        <td><?= h($b['title']) ?></td>
        <td class="fw-bold"><?= fmt_money((int)$b['amount']) ?></td>
        <td class="small"><?= fmt_date($b['billed_date']) ?></td>
        <td class="small"><?= fmt_date($b['paid_date']) ?></td>
        <td><?= billing_paid_badge($b['is_paid']) ?></td>
        <td>
          <?php if (!$b['is_paid']): ?>
          <form method="post" action="<?= GC_BASE_URL ?>/case_view.php?id=<?= $b['case_id'] ?>" class="d-inline">
            <input type="hidden" name="_action" value="paid_billing">
            <input type="hidden" name="_tab" value="">
            <input type="hidden" name="billing_id" value="<?= $b['id'] ?>">
            <button class="btn btn-sm btn-success py-0 px-2">入金</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
