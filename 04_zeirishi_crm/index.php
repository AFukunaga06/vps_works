<?php
require_once 'config.php';
$pdo = getPdo();

// ダッシュボード集計
$today = date('Y-m-d');
$alertLimit = date('Y-m-d', strtotime("+".ALERT_DAYS." days"));

// 期限近接・超過アラート（未申告）
$stmtAlert = $pdo->prepare("
    SELECT d.*, c.company_name
    FROM tax_deadlines d
    JOIN clients c ON c.id = d.client_id
    WHERE d.status IN ('pending','in_progress')
      AND d.deadline <= :limit
    ORDER BY d.deadline ASC
");
$stmtAlert->execute([':limit' => $alertLimit]);
$alerts = $stmtAlert->fetchAll();

// タスク期限近接（未完了）
$stmtTaskAlert = $pdo->prepare("
    SELECT t.*, c.company_name
    FROM tasks t
    JOIN clients c ON c.id = t.client_id
    WHERE t.status IN ('todo','in_progress')
      AND t.due_date IS NOT NULL
      AND t.due_date <= :limit
    ORDER BY t.due_date ASC
");
$stmtTaskAlert->execute([':limit' => $alertLimit]);
$taskAlerts = $stmtTaskAlert->fetchAll();

// 顧問先数
$clientCount = $pdo->query("SELECT COUNT(*) FROM clients WHERE status='active'")->fetchColumn();

// 今月未入金件数
$ym = date('Y-m');
$unpaidCount = $pdo->prepare("SELECT COUNT(*) FROM monthly_fees WHERE `year_month`=:ym AND paid_at IS NULL");
$unpaidCount->execute([':ym' => $ym]);
$unpaidCount = $unpaidCount->fetchColumn();

// 進行中タスク数
$inProgressTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status='in_progress'")->fetchColumn();

// 期限超過申告
$overdueCount = $pdo->query("SELECT COUNT(*) FROM tax_deadlines WHERE status='overdue'")->fetchColumn();

$pageTitle = 'ダッシュボード';
include 'layout/header.php';
?>

<div class="row g-4 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card text-white bg-primary h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-building fs-2"></i>
        <div>
          <div class="small opacity-75">顧問先数（稼働）</div>
          <div class="fs-3 fw-bold"><?= h((string)$clientCount) ?> 件</div>
        </div>
      </div>
      <a href="clients.php" class="card-footer text-white text-decoration-none small">詳細を見る &rsaquo;</a>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card text-white bg-danger h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-exclamation-triangle fs-2"></i>
        <div>
          <div class="small opacity-75">申告期限超過</div>
          <div class="fs-3 fw-bold"><?= h((string)$overdueCount) ?> 件</div>
        </div>
      </div>
      <a href="deadlines.php" class="card-footer text-white text-decoration-none small">詳細を見る &rsaquo;</a>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card text-white bg-warning h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-cash-coin fs-2"></i>
        <div>
          <div class="small opacity-75">今月未入金</div>
          <div class="fs-3 fw-bold"><?= h((string)$unpaidCount) ?> 件</div>
        </div>
      </div>
      <a href="fees.php" class="card-footer text-white text-decoration-none small">詳細を見る &rsaquo;</a>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card text-white bg-success h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-list-task fs-2"></i>
        <div>
          <div class="small opacity-75">進行中タスク</div>
          <div class="fs-3 fw-bold"><?= h((string)$inProgressTasks) ?> 件</div>
        </div>
      </div>
      <a href="tasks.php" class="card-footer text-white text-decoration-none small">詳細を見る &rsaquo;</a>
    </div>
  </div>
</div>

<?php if ($alerts): ?>
<div class="card mb-4 border-danger">
  <div class="card-header bg-danger text-white fw-bold">
    <i class="bi bi-alarm me-1"></i> 申告期限アラート（<?= ALERT_DAYS ?>日以内・超過）
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light">
        <tr><th>顧問先</th><th>種別</th><th>年度</th><th>期限</th><th>残日数</th><th>状態</th></tr>
      </thead>
      <tbody>
        <?php foreach ($alerts as $row):
            $diff = (int)((strtotime($row['deadline']) - strtotime($today)) / 86400);
            $rowClass = $diff < 0 ? 'table-danger' : ($diff <= 7 ? 'table-warning' : '');
        ?>
        <tr class="<?= $rowClass ?>">
          <td><a href="client_detail.php?id=<?= h((string)$row['client_id']) ?>"><?= h($row['company_name']) ?></a></td>
          <td><?= h($row['tax_type']) ?></td>
          <td><?= h($row['fiscal_year']) ?></td>
          <td><?= h($row['deadline']) ?></td>
          <td>
            <?php if ($diff < 0): ?>
              <span class="text-danger fw-bold">超過 <?= abs($diff) ?>日</span>
            <?php else: ?>
              <span class="<?= $diff <= 7 ? 'text-danger fw-bold' : '' ?>"><?= $diff ?>日</span>
            <?php endif; ?>
          </td>
          <td><?= statusBadge($row['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($taskAlerts): ?>
<div class="card mb-4 border-warning">
  <div class="card-header bg-warning fw-bold">
    <i class="bi bi-clock-history me-1"></i> タスク期限アラート（<?= ALERT_DAYS ?>日以内）
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light">
        <tr><th>顧問先</th><th>タスク</th><th>期限</th><th>優先度</th><th>担当</th><th>状態</th></tr>
      </thead>
      <tbody>
        <?php foreach ($taskAlerts as $row):
            $diff = (int)((strtotime($row['due_date']) - strtotime($today)) / 86400);
            $rowClass = $diff < 0 ? 'table-danger' : ($diff <= 7 ? 'table-warning' : '');
        ?>
        <tr class="<?= $rowClass ?>">
          <td><a href="client_detail.php?id=<?= h((string)$row['client_id']) ?>"><?= h($row['company_name']) ?></a></td>
          <td><?= h($row['title']) ?></td>
          <td><?= h($row['due_date']) ?></td>
          <td><?= priorityBadge($row['priority']) ?></td>
          <td><?= h((string)($row['assignee'] ?? '')) ?></td>
          <td><?= statusBadge($row['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include 'layout/footer.php'; ?>
