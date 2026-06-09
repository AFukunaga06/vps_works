<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/crm_functions.php';
require_admin();

$nav        = 'activities';
$page_title = '活動記録';
$activities = crm_get_activities([], 100);
?>
<?php require __DIR__ . '/includes/_header.php'; ?>
<div class="page-card">
  <div class="text-muted small mb-3"><?= count($activities) ?> 件（最新100件）</div>
  <?php if (empty($activities)): ?>
  <p class="text-muted">活動記録がありません。</p>
  <?php else: ?>
  <ul class="list-group list-group-flush">
    <?php foreach ($activities as $a): ?>
    <li class="list-group-item px-0 py-3">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <div class="d-flex align-items-center gap-2">
          <span class="badge badge-type"><?= crm_h(ACTIVITY_TYPE_MAP[$a['activity_type']] ?? '') ?></span>
          <?php if ($a['duration_min']): ?><span class="badge bg-light text-muted"><?= $a['duration_min'] ?>分</span><?php endif; ?>
          <a href="<?= CRM_URL ?>/client_view.php?id=<?= $a['client_id'] ?>" class="text-decoration-none text-muted small"><?= crm_h($a['client_name']) ?></a>
          <?php if ($a['case_id']): ?><span class="text-muted">›</span>
          <a href="<?= CRM_URL ?>/case_view.php?id=<?= $a['case_id'] ?>" class="text-decoration-none small"><?= crm_h($a['case_name']) ?></a>
          <?php endif; ?>
        </div>
        <small class="text-muted"><?= crm_fmt_dt($a['activity_at']) ?></small>
      </div>
      <?php if ($a['title']): ?><div class="fw-bold small"><?= crm_h($a['title']) ?></div><?php endif; ?>
      <?php if ($a['content']): ?><div class="small text-muted mt-1"><?= crm_h(mb_strimwidth($a['content'], 0, 200, '…')) ?></div><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/_footer.php'; ?>
