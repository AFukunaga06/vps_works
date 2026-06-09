<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/crm_functions.php';
require_admin();

$nav        = 'billing';
$page_title = '請求管理';
$show_unpaid = (int)($_GET['unpaid'] ?? 1);

// 入金更新
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_a']??'')==='mark_paid') {
    get_db()->prepare("UPDATE crm_billing SET is_paid=1, paid_date=CURDATE() WHERE id=?")->execute([(int)$_POST['bid']]);
    header('Location: '.CRM_URL.'/billing.php?unpaid='.$show_unpaid); exit;
}

$db = get_db();
$where = $show_unpaid ? 'WHERE b.is_paid=0' : '';
$rows = $db->query(
    "SELECT b.*, cs.case_name, cl.name AS client_name
     FROM crm_billing b JOIN crm_cases cs ON cs.id=b.case_id JOIN crm_clients cl ON cl.id=cs.client_id
     $where ORDER BY b.billed_date DESC LIMIT 200"
)->fetchAll();
$sum = $db->query(
    "SELECT COALESCE(SUM(amount),0) AS total, COALESCE(SUM(CASE WHEN is_paid=1 THEN amount ELSE 0 END),0) AS paid FROM crm_billing"
)->fetch();
?>
<?php require __DIR__ . '/includes/_header.php'; ?>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="page-card text-center py-3">
    <div class="h5 fw-bold"><?= crm_fmt_money((int)$sum['total']) ?></div>
    <div class="small text-muted">請求合計</div>
  </div></div>
  <div class="col-md-4"><div class="page-card text-center py-3">
    <div class="h5 fw-bold text-success"><?= crm_fmt_money((int)$sum['paid']) ?></div>
    <div class="small text-muted">入金済</div>
  </div></div>
  <div class="col-md-4"><div class="page-card text-center py-3">
    <div class="h5 fw-bold text-danger"><?= crm_fmt_money((int)$sum['total'] - (int)$sum['paid']) ?></div>
    <div class="small text-muted">未入金</div>
  </div></div>
</div>
<div class="page-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-muted small"><?= count($rows) ?> 件</div>
    <?php if ($show_unpaid): ?>
    <a href="?unpaid=0" class="btn btn-sm btn-outline-secondary">全件表示</a>
    <?php else: ?>
    <a href="?unpaid=1" class="btn btn-sm btn-outline-secondary">未入金のみ</a>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>依頼者</th><th>案件</th><th>種別</th><th>項目</th><th>金額</th><th>請求日</th><th>入金状態</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">請求記録がありません。</td></tr>
      <?php else: foreach ($rows as $b): ?>
        <tr>
          <td><?= crm_h($b['client_name']) ?></td>
          <td><a href="<?= CRM_URL ?>/case_view.php?id=<?= $b['case_id'] ?>#billing"><?= crm_h($b['case_name']) ?></a></td>
          <td><?= crm_h(BILLING_TYPE_MAP[$b['billing_type']] ?? '') ?></td>
          <td><?= crm_h($b['title']) ?></td>
          <td class="fw-bold"><?= crm_fmt_money((int)$b['amount']) ?></td>
          <td><?= crm_fmt_date($b['billed_date']) ?></td>
          <td>
            <?php if ($b['is_paid']): ?>
            <span class="badge bg-success">入金済</span> <small class="text-muted"><?= crm_fmt_date($b['paid_date']) ?></small>
            <?php else: ?>
            <span class="badge bg-danger">未入金</span>
            <form method="post" class="d-inline ms-1" onsubmit="return confirm('入金済みにしますか？')">
              <input type="hidden" name="_a" value="mark_paid">
              <input type="hidden" name="bid" value="<?= $b['id'] ?>">
              <button class="btn btn-sm btn-success py-0 px-2">入金確認</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/_footer.php'; ?>
