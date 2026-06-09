<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/crm_functions.php';
require_admin();

$nav        = 'dash';
$page_title = 'ダッシュボード';
$stats      = crm_dashboard_stats();
$deadlines  = crm_upcoming_deadlines(14);
$activities = crm_get_activities([], 8);
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="page-card text-center py-3">
      <div class="fs-2 fw-bold text-success"><?= $stats['active_clients'] ?></div>
      <div class="small text-muted"><i class="bi bi-people me-1"></i>対応中依頼者</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="page-card text-center py-3">
      <div class="fs-2 fw-bold" style="color:var(--g)"><?= $stats['active_cases'] ?></div>
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
      <div class="fs-2 fw-bold text-warning"><?= crm_fmt_money((int)$stats['unpaid']) ?></div>
      <div class="small text-muted"><i class="bi bi-currency-yen me-1"></i>未入金合計</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="page-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0 fw-bold"><i class="bi bi-calendar-event text-danger me-1"></i>直近14日の期日</h2>
        <a href="<?= CRM_URL ?>/deadlines.php" class="btn btn-sm btn-outline-secondary">全件</a>
      </div>
      <?php if (empty($deadlines)): ?>
      <p class="text-muted small mb-0">直近14日の期日はありません。</p>
      <?php else: ?>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>日時</th><th>種別</th><th>案件</th><th>内容</th></tr></thead>
        <tbody>
        <?php foreach ($deadlines as $dl):
          $dt = strtotime($dl['deadline_date']);
          $days = (int)(($dt - time()) / 86400);
          $cls  = $days < 0 ? 'text-danger fw-bold' : ($days <= 3 ? 'text-warning fw-bold' : '');
        ?>
        <tr>
          <td class="<?= $cls ?>"><?= date('m/d H:i', $dt) ?>
            <?php if ($days < 0): ?><span class="badge bg-danger ms-1">超過</span>
            <?php elseif ($days === 0): ?><span class="badge bg-warning text-dark ms-1">今日</span>
            <?php elseif ($days <= 3): ?><span class="badge bg-warning text-dark ms-1"><?= $days ?>日後</span>
            <?php endif; ?>
          </td>
          <td><?= crm_h(DEADLINE_TYPE_MAP[$dl['deadline_type']] ?? '') ?></td>
          <td><a href="<?= CRM_URL ?>/case_view.php?id=<?= $dl['case_id'] ?>"><?= crm_h($dl['case_name']) ?></a></td>
          <td><?= crm_h(mb_strimwidth($dl['title'], 0, 20, '…')) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-md-5">
    <div class="page-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0 fw-bold"><i class="bi bi-journal-text me-1" style="color:var(--g)"></i>最近の活動</h2>
        <a href="<?= CRM_URL ?>/activities.php" class="btn btn-sm btn-outline-secondary">全件</a>
      </div>
      <?php if (empty($activities)): ?>
      <p class="text-muted small mb-0">活動記録がありません。</p>
      <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($activities as $a): ?>
        <li class="list-group-item px-0 py-2">
          <div class="d-flex justify-content-between">
            <span class="badge badge-type"><?= crm_h(ACTIVITY_TYPE_MAP[$a['activity_type']] ?? '') ?></span>
            <small class="text-muted"><?= crm_fmt_dt($a['activity_at']) ?></small>
          </div>
          <div class="mt-1 small">
            <a href="<?= $a['case_id'] ? CRM_URL . '/case_view.php?id=' . $a['case_id'] : '#' ?>">
              <?= crm_h($a['case_name'] ?? $a['client_name']) ?>
            </a>
            <?php if ($a['title']): ?><span class="text-muted"> — <?= crm_h(mb_strimwidth($a['title'], 0, 20, '…')) ?></span><?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
