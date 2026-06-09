<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$lc_user   = lc_require_login();
$page_title = 'ダッシュボード';
$page_nav   = 'dashboard';

$stats     = get_dashboard_stats();
$deadlines = get_upcoming_deadlines(14);
$recent_activities = get_activities([], 10);
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="page-card text-center py-3">
      <div class="fs-2 fw-bold text-primary"><?= $stats['active_clients'] ?></div>
      <div class="small text-muted"><i class="bi bi-people me-1"></i>対応中依頼者</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="page-card text-center py-3">
      <div class="fs-2 fw-bold text-success"><?= $stats['active_cases'] ?></div>
      <div class="small text-muted"><i class="bi bi-folder2-open me-1"></i>進行中案件</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="page-card text-center py-3">
      <div class="fs-2 fw-bold <?= $stats['upcoming_deadlines'] > 0 ? 'text-danger' : 'text-secondary' ?>"><?= $stats['upcoming_deadlines'] ?></div>
      <div class="small text-muted"><i class="bi bi-calendar-event me-1"></i>7日以内の期日</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="page-card text-center py-3">
      <div class="fs-2 fw-bold text-warning"><?= fmt_money((int)$stats['unpaid_billing']) ?></div>
      <div class="small text-muted"><i class="bi bi-currency-yen me-1"></i>未入金合計</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- 直近の期日 -->
  <div class="col-md-7">
    <div class="page-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0 fw-bold"><i class="bi bi-calendar-event text-danger me-2"></i>直近14日の期日</h2>
        <a href="<?= LC_BASE_URL ?>/deadlines.php" class="btn btn-sm btn-outline-secondary">全件表示</a>
      </div>
      <?php if (empty($deadlines)): ?>
      <p class="text-muted small mb-0">直近14日に予定されている期日はありません。</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr><th>日時</th><th>種別</th><th>案件</th><th>依頼者</th><th>内容</th></tr>
          </thead>
          <tbody>
          <?php foreach ($deadlines as $dl):
            $dt = strtotime($dl['deadline_date']);
            $days_left = (int)(($dt - time()) / 86400);
            $cls = $days_left < 0 ? 'deadline-overdue' : ($days_left <= 3 ? 'deadline-soon' : '');
          ?>
            <tr>
              <td class="<?= $cls ?>">
                <?= date('m/d H:i', $dt) ?>
                <?php if ($days_left < 0): ?><span class="badge bg-danger ms-1">超過</span>
                <?php elseif ($days_left === 0): ?><span class="badge bg-warning text-dark ms-1">今日</span>
                <?php elseif ($days_left <= 3): ?><span class="badge bg-orange ms-1" style="background:#fd7e14"><?= $days_left ?>日後</span>
                <?php endif; ?>
              </td>
              <td><?= h(DEADLINE_TYPE_MAP[$dl['deadline_type']] ?? '') ?></td>
              <td><a href="<?= LC_BASE_URL ?>/case_view.php?id=<?= $dl['case_id'] ?>"><?= h($dl['case_name']) ?></a></td>
              <td><?= h($dl['client_name']) ?></td>
              <td><?= h(mb_strimwidth($dl['title'], 0, 20, '…')) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 最近の活動記録 -->
  <div class="col-md-5">
    <div class="page-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0 fw-bold"><i class="bi bi-journal-text text-primary me-2"></i>最近の活動</h2>
        <a href="<?= LC_BASE_URL ?>/activities.php" class="btn btn-sm btn-outline-secondary">全件表示</a>
      </div>
      <?php if (empty($recent_activities)): ?>
      <p class="text-muted small mb-0">活動記録がありません。</p>
      <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($recent_activities as $a): ?>
        <li class="list-group-item px-0 py-2">
          <div class="d-flex justify-content-between">
            <span class="badge" style="background:#e8f0fe;color:#1a3a5c"><?= h(ACTIVITY_TYPE_MAP[$a['activity_type']] ?? '') ?></span>
            <small class="text-muted"><?= fmt_datetime($a['activity_at']) ?></small>
          </div>
          <div class="mt-1">
            <a href="<?= $a['case_id'] ? LC_BASE_URL . '/case_view.php?id=' . $a['case_id'] : '#' ?>">
              <?= h($a['case_name'] ?? $a['client_name']) ?>
            </a>
            <?php if ($a['title']): ?>
            <span class="text-muted ms-1">— <?= h(mb_strimwidth($a['title'], 0, 25, '…')) ?></span>
            <?php endif; ?>
          </div>
          <small class="text-muted"><?= h($a['user_name']) ?></small>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
