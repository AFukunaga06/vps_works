<?php
require_once 'config.php';
require_login();
$page_title = '着手金・報酬管理';

$db     = get_db();
$filter = $_GET['filter'] ?? 'all';
$type   = $_GET['type'] ?? '';

$where  = [];
$params = [];
if ($filter === 'unpaid') { $where[] = 'b.is_paid = 0'; }
if ($filter === 'paid')   { $where[] = 'b.is_paid = 1'; }
if ($type)                { $where[] = 'b.billing_type = ?'; $params[] = $type; }

$sql = "SELECT b.*, c.title AS case_title, c.case_type, c.id AS case_id,
               cl.name AS client_name, c.status AS case_status
        FROM billing b
        JOIN cases c ON b.case_id = c.id
        JOIN clients cl ON c.client_id = cl.id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY b.is_paid ASC, b.billing_date DESC';

$st = $db->prepare($sql);
$st->execute($params);
$bills = $st->fetchAll();

// Summary
$summary = $db->query(
    "SELECT
       COALESCE(SUM(amount), 0) AS total,
       COALESCE(SUM(IF(is_paid=1, amount, 0)), 0) AS paid,
       COALESCE(SUM(IF(is_paid=0, amount, 0)), 0) AS unpaid,
       COUNT(*) AS cnt,
       SUM(is_paid=0) AS unpaid_cnt
     FROM billing"
)->fetch();

require 'includes/header.php';
?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 bg-primary bg-opacity-10">
          <i class="bi bi-currency-yen fs-3 text-primary"></i>
        </div>
        <div>
          <div class="text-muted small">請求総額</div>
          <div class="fw-bold fs-5"><?= format_money((int)$summary['total']) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 bg-success bg-opacity-10">
          <i class="bi bi-check-circle-fill fs-3 text-success"></i>
        </div>
        <div>
          <div class="text-muted small">入金済み</div>
          <div class="fw-bold fs-5 text-success"><?= format_money((int)$summary['paid']) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 bg-danger bg-opacity-10">
          <i class="bi bi-exclamation-circle-fill fs-3 text-danger"></i>
        </div>
        <div>
          <div class="text-muted small">未収金（<?= $summary['unpaid_cnt'] ?>件）</div>
          <div class="fw-bold fs-5 text-danger"><?= format_money((int)$summary['unpaid']) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="d-flex align-items-center gap-2 flex-wrap mb-3">
  <?php foreach ([['all','すべて'],['unpaid','未収のみ'],['paid','入金済みのみ']] as [$k,$l]): ?>
  <a href="?filter=<?= $k ?><?= $type?'&type='.$type:'' ?>"
     class="btn btn-sm <?= $filter===$k?'btn-primary':'btn-outline-secondary' ?>"><?= $l ?></a>
  <?php endforeach; ?>
  <select class="form-select form-select-sm ms-2" style="width:auto"
          onchange="location='?filter=<?= $filter ?>&type='+this.value">
    <option value="">すべての種別</option>
    <?php foreach (BILLING_TYPES as $t): ?>
    <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= $t ?></option>
    <?php endforeach; ?>
  </select>
</div>

<div class="card">
  <div class="card-header bg-white py-3">
    <span class="fw-semibold">請求・入金一覧 <span class="badge bg-secondary ms-1"><?= count($bills) ?>件</span></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>状態</th>
          <th>種別</th>
          <th>金額</th>
          <th>請求日</th>
          <th>入金日</th>
          <th>案件</th>
          <th>依頼人</th>
          <th>メモ</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($bills): ?>
        <?php foreach ($bills as $b): ?>
        <tr>
          <td>
            <span class="badge bg-<?= $b['is_paid'] ? 'success' : 'warning text-dark' ?>">
              <?= $b['is_paid'] ? '入金済' : '未収' ?>
            </span>
          </td>
          <td><span class="badge bg-light text-dark border"><?= h($b['billing_type']) ?></span></td>
          <td class="fw-semibold"><?= format_money((int)$b['amount']) ?></td>
          <td class="small text-muted"><?= format_date($b['billing_date']) ?></td>
          <td class="small text-muted"><?= $b['payment_date'] ? format_date($b['payment_date']) : '—' ?></td>
          <td>
            <a href="case_detail.php?id=<?= $b['case_id'] ?>#billing" class="text-decoration-none small">
              <?= h(mb_strimwidth($b['case_title'],0,22,'…')) ?>
            </a>
            <div>
              <span class="badge bg-<?= STATUS_COLORS[$b['case_status']] ?? 'secondary' ?>" style="font-size:.65rem">
                <?= h($b['case_status']) ?>
              </span>
            </div>
          </td>
          <td class="small"><?= h($b['client_name']) ?></td>
          <td class="small text-muted"><?= h(mb_strimwidth($b['notes']??'',0,20,'…')) ?></td>
          <td>
            <?php if (!$b['is_paid']): ?>
            <form method="post" action="case_detail.php?id=<?= $b['case_id'] ?>">
              <input type="hidden" name="action" value="paid_billing">
              <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
              <input type="hidden" name="redirect_anchor" value="">
              <button class="btn btn-sm btn-outline-success" title="入金確認">
                <i class="bi bi-check2-circle"></i>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <tr><td colspan="9" class="text-center text-muted py-5">
          <i class="bi bi-cash-stack fs-1 d-block mb-2"></i>データがありません
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
