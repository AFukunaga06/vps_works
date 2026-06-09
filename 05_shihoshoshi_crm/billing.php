<?php
require_once 'config.php';
require_login();

$db     = get_db();
$filter = $_GET['filter'] ?? 'unpaid';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_paid') {
    $db->prepare("UPDATE billing SET is_paid = NOT is_paid, payment_date = IF(is_paid=0, CURDATE(), NULL) WHERE id=?")
       ->execute([(int)$_POST['billing_id']]);
    header('Location: billing.php?filter=' . urlencode($filter));
    exit;
}

$where = $filter === 'unpaid' ? 'WHERE b.is_paid = 0' : ($filter === 'paid' ? 'WHERE b.is_paid = 1' : '');
$billings = $db->query("
  SELECT b.*, c.title AS case_title, c.case_number, c.id AS case_id, cl.name AS client_name
  FROM billing b
  JOIN cases c ON c.id = b.case_id
  JOIN clients cl ON cl.id = c.client_id
  $where
  ORDER BY b.billing_date DESC, b.id DESC
")->fetchAll();

// 集計
$summary = $db->query("
  SELECT
    COALESCE(SUM(amount), 0) AS total,
    COALESCE(SUM(CASE WHEN is_paid=1 THEN amount END), 0) AS paid,
    COALESCE(SUM(CASE WHEN is_paid=0 THEN amount END), 0) AS unpaid,
    COALESCE(SUM(CASE WHEN billing_type='司法書士報酬' THEN amount END), 0) AS fee,
    COALESCE(SUM(CASE WHEN billing_type='登録免許税' THEN amount END), 0) AS tax
  FROM billing
")->fetch();

$page_title = '報酬管理';
require 'includes/header.php';
?>

<!-- 集計カード -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card text-center py-3">
      <div class="small text-muted">合計</div>
      <div class="fw-bold fs-5">¥<?= number_format($summary['total']) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card text-center py-3 border-success">
      <div class="small text-muted">収納済み</div>
      <div class="fw-bold fs-5 text-success">¥<?= number_format($summary['paid']) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card text-center py-3 border-danger">
      <div class="small text-muted">未収</div>
      <div class="fw-bold fs-5 text-danger">¥<?= number_format($summary['unpaid']) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card text-center py-3">
      <div class="small text-muted">司法書士報酬 / 登録免許税</div>
      <div class="small fw-bold">
        <span class="text-primary">¥<?= number_format($summary['fee']) ?></span>
        &nbsp;/&nbsp;
        <span class="text-warning">¥<?= number_format($summary['tax']) ?></span>
      </div>
    </div>
  </div>
</div>

<!-- フィルター -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div class="btn-group btn-group-sm">
    <a href="?filter=unpaid" class="btn btn-<?= $filter === 'unpaid' ? 'primary' : 'outline-secondary' ?>">未収</a>
    <a href="?filter=paid" class="btn btn-<?= $filter === 'paid' ? 'primary' : 'outline-secondary' ?>">収納済</a>
    <a href="?filter=all" class="btn btn-<?= $filter === 'all' ? 'primary' : 'outline-secondary' ?>">すべて</a>
  </div>
  <span class="text-muted small"><?= count($billings) ?> 件</span>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>案件</th><th>依頼人</th><th>種別</th><th class="text-end">金額</th>
          <th>請求日</th><th>入金日</th><th>状態</th><th>メモ</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($billings as $b): ?>
        <tr>
          <td>
            <a href="case_detail.php?id=<?= $b['case_id'] ?>" class="text-decoration-none small fw-semibold">
              <?= h(mb_strimwidth($b['case_title'], 0, 20, '…')) ?>
            </a>
            <div class="text-muted" style="font-size:.75rem"><?= h($b['case_number'] ?? '') ?></div>
          </td>
          <td class="small"><?= h($b['client_name']) ?></td>
          <td><span class="badge bg-light text-dark"><?= h($b['billing_type']) ?></span></td>
          <td class="text-end fw-semibold">¥<?= number_format($b['amount']) ?></td>
          <td><small><?= format_date($b['billing_date']) ?></small></td>
          <td><small><?= $b['payment_date'] ? format_date($b['payment_date']) : '—' ?></small></td>
          <td>
            <form method="post">
              <input type="hidden" name="action" value="toggle_paid">
              <input type="hidden" name="billing_id" value="<?= $b['id'] ?>">
              <button type="submit" class="btn btn-sm <?= $b['is_paid'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                <?= $b['is_paid'] ? '<i class="bi bi-check-circle me-1"></i>収納済' : '未収納' ?>
              </button>
            </form>
          </td>
          <td><small class="text-muted"><?= h($b['notes'] ?? '') ?></small></td>
          <td><a href="case_detail.php?id=<?= $b['case_id'] ?>" class="btn btn-sm btn-outline-primary">案件へ</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($billings)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">データがありません</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
