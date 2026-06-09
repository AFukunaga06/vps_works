<?php
require_once 'config.php';
$pdo = getPdo();

$today     = date('Y-m-d');
$alertLimit = date('Y-m-d', strtotime('+'.ALERT_DAYS.' days'));

$filterStatus = $_GET['status'] ?? '';
$params = [];
$where  = ['1=1'];
if ($filterStatus !== '') {
    $where[] = 'd.status = :status';
    $params[':status'] = $filterStatus;
}
$whereStr = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT d.*, c.company_name, c.corp_type
    FROM tax_deadlines d
    JOIN clients c ON c.id = d.client_id
    WHERE {$whereStr}
    ORDER BY d.deadline ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = '申告期限管理';
include 'layout/header.php';
?>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-auto"><label class="form-label mb-0">状態</label>
        <select name="status" class="form-select">
          <option value="">すべて</option>
          <?php foreach (['pending'=>'未着手','in_progress'=>'進行中','filed'=>'申告済','overdue'=>'期限超過'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= $filterStatus===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto"><button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>絞込</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-dark">
        <tr><th>顧問先</th><th>種別</th><th>年度</th><th>期限</th><th>残日数</th><th>申告日</th><th>状態</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
            $diff = (int)((strtotime($row['deadline']) - strtotime($today)) / 86400);
            $rc = '';
            if ($row['status']==='overdue' || ($diff<0 && $row['status']!=='filed')) $rc='table-danger';
            elseif ($diff<=14 && !in_array($row['status'],['filed'])) $rc='table-warning';
        ?>
        <tr class="<?= $rc ?>">
          <td><a href="client_detail.php?id=<?= h((string)$row['client_id']) ?>"><?= h($row['company_name']) ?></a></td>
          <td><?= h($row['tax_type']) ?></td>
          <td><?= h($row['fiscal_year']) ?></td>
          <td><?= h($row['deadline']) ?></td>
          <td>
            <?php if ($row['status']==='filed'): ?>
              <span class="text-muted">─</span>
            <?php elseif ($diff < 0): ?>
              <span class="text-danger fw-bold">超過 <?= abs($diff) ?>日</span>
            <?php else: ?>
              <span class="<?= $diff<=14 ? 'text-danger fw-bold' : '' ?>"><?= $diff ?>日</span>
            <?php endif; ?>
          </td>
          <td><?= h((string)($row['filed_at'] ?? '─')) ?></td>
          <td><?= statusBadge($row['status']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">該当なし</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'layout/footer.php'; ?>
