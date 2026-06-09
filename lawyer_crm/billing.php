<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$lc_user    = lc_require_login();
$page_title = '請求管理';
$page_nav   = 'billing';

$show_unpaid = (int)($_GET['unpaid'] ?? 1);

$db  = get_db();
$tid = lc_current_tenant_id();

// 入金ステータス更新（先に処理）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'mark_paid') {
    $bid = (int)$_POST['bid'];
    $db->prepare("UPDATE lc_billing SET is_paid=1, paid_date=CURDATE() WHERE id=? AND tenant_id=?")
       ->execute([$bid, $tid]);
    header('Location: ' . LC_BASE_URL . '/billing.php?unpaid=' . $show_unpaid); exit;
}

$where_paid = $show_unpaid ? 'AND b.is_paid=0' : '';
$rows_stmt = $db->prepare(
    "SELECT b.*, cs.case_name, cs.case_number, cl.name AS client_name
     FROM lc_billing b
     JOIN lc_cases cs ON cs.id=b.case_id
     JOIN lc_clients cl ON cl.id=cs.client_id
     WHERE b.tenant_id=? $where_paid
     ORDER BY b.billed_date DESC, b.id DESC
     LIMIT 200"
);
$rows_stmt->execute([$tid]);
$rows = $rows_stmt->fetchAll();

$sum_stmt = $db->prepare(
    "SELECT SUM(amount) AS total, SUM(CASE WHEN is_paid=1 THEN amount ELSE 0 END) AS paid
     FROM lc_billing WHERE tenant_id=?"
);
$sum_stmt->execute([$tid]);
$summary = $sum_stmt->fetch() ?: ['total' => 0, 'paid' => 0];
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<!-- 集計カード -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="page-card text-center py-3">
      <div class="h5 fw-bold"><?= fmt_money((int)($summary['total'] ?? 0)) ?></div>
      <div class="small text-muted">請求合計</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="page-card text-center py-3">
      <div class="h5 fw-bold text-success"><?= fmt_money((int)($summary['paid'] ?? 0)) ?></div>
      <div class="small text-muted">入金済</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="page-card text-center py-3">
      <div class="h5 fw-bold text-danger"><?= fmt_money((int)($summary['total'] ?? 0) - (int)($summary['paid'] ?? 0)) ?></div>
      <div class="small text-muted">未入金</div>
    </div>
  </div>
</div>

<div class="page-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-muted small"><?= count($rows) ?> 件</div>
    <div class="d-flex gap-2">
      <?php if ($show_unpaid): ?>
      <a href="?unpaid=0" class="btn btn-sm btn-outline-secondary">全件表示</a>
      <?php else: ?>
      <a href="?unpaid=1" class="btn btn-sm btn-outline-secondary">未入金のみ</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>依頼者</th>
          <th>案件</th>
          <th>種別</th>
          <th>項目</th>
          <th>金額</th>
          <th>時間</th>
          <th>請求日</th>
          <th>入金</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">請求記録がありません。</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $b): ?>
        <tr>
          <td><?= h($b['client_name']) ?></td>
          <td><a href="<?= LC_BASE_URL ?>/case_view.php?id=<?= $b['case_id'] ?>#billing"><?= h($b['case_name']) ?></a></td>
          <td><?= h(BILLING_TYPE_MAP[$b['billing_type']] ?? '') ?></td>
          <td><?= h($b['title']) ?></td>
          <td class="fw-bold"><?= fmt_money((int)$b['amount']) ?></td>
          <td><?= $b['hours'] ? $b['hours'] . 'h' : '' ?></td>
          <td><?= fmt_date($b['billed_date']) ?></td>
          <td><?= billing_paid_badge((int)$b['is_paid']) ?>
            <?php if ($b['paid_date']): ?><br><small class="text-muted"><?= fmt_date($b['paid_date']) ?></small><?php endif; ?>
          </td>
          <td>
            <?php if (!$b['is_paid']): ?>
            <form method="post" class="d-inline" onsubmit="return confirm('入金済みにしますか？')">
              <input type="hidden" name="_action" value="mark_paid">
              <input type="hidden" name="bid" value="<?= $b['id'] ?>">
              <button class="btn btn-xs btn-sm btn-success py-0 px-2">入金</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
