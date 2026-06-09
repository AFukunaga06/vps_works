<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$lc_user    = lc_require_login();
$page_title = '活動記録';
$page_nav   = 'activities';

$activities = get_activities([], 100);
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
          <span class="badge" style="background:#e8f0fe;color:#1a3a5c"><?= h(ACTIVITY_TYPE_MAP[$a['activity_type']] ?? '') ?></span>
          <?php if ($a['duration_min']): ?><span class="badge bg-light text-muted"><?= $a['duration_min'] ?>分</span><?php endif; ?>
          <a href="<?= LC_BASE_URL ?>/client_view.php?id=<?= $a['client_id'] ?>" class="text-decoration-none text-muted small"><?= h($a['client_name']) ?></a>
          <?php if ($a['case_id']): ?>
          <span class="text-muted">›</span>
          <a href="<?= LC_BASE_URL ?>/case_view.php?id=<?= $a['case_id'] ?>" class="text-decoration-none small"><?= h($a['case_name']) ?></a>
          <?php endif; ?>
        </div>
        <small class="text-muted"><?= fmt_datetime($a['activity_at']) ?> | <?= h($a['user_name']) ?></small>
      </div>
      <?php if ($a['title']): ?><div class="fw-bold small"><?= h($a['title']) ?></div><?php endif; ?>
      <?php if ($a['content']): ?><div class="small text-muted mt-1" style="white-space:pre-wrap"><?= h(mb_strimwidth($a['content'], 0, 200, '…')) ?></div><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
