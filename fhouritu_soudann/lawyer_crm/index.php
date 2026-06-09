<?php
require_once 'config.php';
require_login();
$page_title = 'ダッシュボード';

$db = get_db();

// --- Stats ---
$total_clients = $db->query('SELECT COUNT(*) FROM clients')->fetchColumn();
$active_cases  = $db->query("SELECT COUNT(*) FROM cases WHERE status='受任中'")->fetchColumn();

$today = date('Y-m-d');
$week_end = date('Y-m-d', strtotime('+6 days'));

$this_week_deadlines = $db->prepare(
    "SELECT COUNT(*) FROM deadlines WHERE is_completed=0 AND deadline_date BETWEEN ? AND ?"
);
$this_week_deadlines->execute([$today, $week_end]);
$deadline_count = $this_week_deadlines->fetchColumn();

$unpaid = $db->query("SELECT COALESCE(SUM(amount),0) FROM billing WHERE is_paid=0")->fetchColumn();

// --- 今週の期日 ---
$st = $db->prepare(
    "SELECT d.*, c.title AS case_title, c.case_type, cl.name AS client_name, c.id AS case_id
     FROM deadlines d
     JOIN cases c ON d.case_id = c.id
     JOIN clients cl ON c.client_id = cl.id
     WHERE d.is_completed = 0 AND d.deadline_date BETWEEN ? AND ?
     ORDER BY d.deadline_date ASC
     LIMIT 15"
);
$st->execute([$today, $week_end]);
$week_deadlines = $st->fetchAll();

// --- 近い期日（今後30日）---
$near = $db->prepare(
    "SELECT d.*, c.title AS case_title, c.case_type, cl.name AS client_name, c.id AS case_id
     FROM deadlines d
     JOIN cases c ON d.case_id = c.id
     JOIN clients cl ON c.client_id = cl.id
     WHERE d.is_completed = 0 AND d.deadline_date > ?
     ORDER BY d.deadline_date ASC
     LIMIT 8"
);
$near->execute([$week_end]);
$near_deadlines = $near->fetchAll();

// --- 進行中案件 ---
$active = $db->query(
    "SELECT c.*, cl.name AS client_name,
            (SELECT COUNT(*) FROM deadlines d WHERE d.case_id=c.id AND d.is_completed=0 AND d.deadline_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)) AS near_dl
     FROM cases c JOIN clients cl ON c.client_id=cl.id
     WHERE c.status='受任中'
     ORDER BY c.updated_at DESC
     LIMIT 10"
)->fetchAll();

require 'includes/header.php';
?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 p-3" style="background:#e8f0fe">
          <i class="bi bi-people-fill fs-3 text-primary"></i>
        </div>
        <div>
          <div class="text-muted small">依頼人総数</div>
          <div class="fs-2 fw-bold"><?= $total_clients ?><small class="fs-6 text-muted ms-1">名</small></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 p-3" style="background:#e6f9f0">
          <i class="bi bi-folder2-open fs-3 text-success"></i>
        </div>
        <div>
          <div class="text-muted small">受任中案件</div>
          <div class="fs-2 fw-bold"><?= $active_cases ?><small class="fs-6 text-muted ms-1">件</small></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 p-3" style="background:#fff3e0">
          <i class="bi bi-calendar-event-fill fs-3 text-warning"></i>
        </div>
        <div>
          <div class="text-muted small">今週の期日</div>
          <div class="fs-2 fw-bold <?= $deadline_count > 0 ? 'text-warning' : '' ?>"><?= $deadline_count ?><small class="fs-6 text-muted ms-1">件</small></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 p-3" style="background:#fce4ec">
          <i class="bi bi-currency-yen fs-3 text-danger"></i>
        </div>
        <div>
          <div class="text-muted small">未収金合計</div>
          <div class="fs-4 fw-bold text-danger"><?= format_money((int)$unpaid) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- 今週の期日 -->
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-week text-warning me-2"></i>今週の期日</h6>
        <a href="deadlines.php" class="btn btn-sm btn-outline-secondary">すべて表示</a>
      </div>
      <div class="card-body p-0">
        <?php if ($week_deadlines): ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>日付</th>
                <th>種別</th>
                <th>内容</th>
                <th>案件</th>
                <th>依頼人</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($week_deadlines as $dl):
                $diff = days_until($dl['deadline_date']);
                $badge = $diff === 0 ? 'danger' : ($diff <= 2 ? 'warning' : 'info');
              ?>
              <tr>
                <td>
                  <span class="badge bg-<?= $badge ?> me-1"><?= $diff === 0 ? '今日' : ($diff === 1 ? '明日' : $diff.'日後') ?></span>
                  <small class="text-muted"><?= date('m/d', strtotime($dl['deadline_date'])) ?></small>
                </td>
                <td><span class="badge bg-light text-dark border"><?= h($dl['deadline_type']) ?></span></td>
                <td><?= h(mb_strimwidth($dl['title'], 0, 25, '…')) ?></td>
                <td><a href="case_detail.php?id=<?= $dl['case_id'] ?>" class="text-decoration-none small"><?= h(mb_strimwidth($dl['case_title'], 0, 18, '…')) ?></a></td>
                <td class="small"><?= h($dl['client_name']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="text-center text-muted py-5">
          <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>今週の期日はありません
        </div>
        <?php endif; ?>
      </div>
      <?php if ($near_deadlines): ?>
      <div class="card-footer bg-white border-top pt-3">
        <div class="small fw-semibold text-muted mb-2">来週以降の近い期日</div>
        <?php foreach ($near_deadlines as $dl):
          $diff = days_until($dl['deadline_date']);
        ?>
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge bg-secondary"><?= $diff ?>日後</span>
          <span class="small"><?= date('m/d', strtotime($dl['deadline_date'])) ?> <?= h($dl['deadline_type']) ?> — <a href="case_detail.php?id=<?= $dl['case_id'] ?>" class="text-decoration-none"><?= h(mb_strimwidth($dl['case_title'], 0, 20, '…')) ?></a></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 進行中案件 -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-folder2-open text-primary me-2"></i>受任中案件</h6>
        <a href="cases.php" class="btn btn-sm btn-outline-secondary">すべて表示</a>
      </div>
      <div class="card-body p-0">
        <div class="list-group list-group-flush">
          <?php foreach ($active as $case): ?>
          <a href="case_detail.php?id=<?= $case['id'] ?>" class="list-group-item list-group-item-action py-3">
            <div class="d-flex justify-content-between align-items-start">
              <div class="flex-grow-1 me-2">
                <div class="small fw-semibold text-truncate" style="max-width:180px"><?= h($case['title']) ?></div>
                <div class="text-muted" style="font-size:.78rem"><?= h($case['client_name']) ?> <span class="badge bg-light text-dark border ms-1"><?= h($case['case_type']) ?></span></div>
              </div>
              <?php if ($case['near_dl'] > 0): ?>
              <span class="badge bg-danger rounded-pill"><?= $case['near_dl'] ?>件</span>
              <?php endif; ?>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-footer text-center bg-white">
        <a href="cases.php?status=受任中" class="btn btn-sm btn-primary">
          <i class="bi bi-plus-circle me-1"></i>新規案件登録
        </a>
      </div>
    </div>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
