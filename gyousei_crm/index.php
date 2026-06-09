<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$gc_user    = gc_require_login();
$page_title = 'ダッシュボード';
$page_nav   = 'dashboard';

$stats      = get_dashboard_stats();
$deadlines  = get_upcoming_deadlines(14);
$recent     = get_recent_progress(8);
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<!-- 集計カード -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center py-3">
        <div class="text-muted small mb-1">進行中の依頼人</div>
        <div class="fw-bold" style="font-size:2rem;color:#1b5e35"><?= $stats['active_clients'] ?></div>
        <div class="text-muted small">名</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center py-3">
        <div class="text-muted small mb-1">進行中の案件</div>
        <div class="fw-bold" style="font-size:2rem;color:#1b5e35"><?= $stats['active_cases'] ?></div>
        <div class="text-muted small">件</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center py-3">
        <div class="text-muted small mb-1">14日以内の期日</div>
        <div class="fw-bold <?= $stats['upcoming_deadlines'] > 0 ? 'text-danger' : '' ?>" style="font-size:2rem"><?= $stats['upcoming_deadlines'] ?></div>
        <div class="text-muted small">件</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center py-3">
        <div class="text-muted small mb-1">未入金報酬</div>
        <div class="fw-bold text-danger" style="font-size:1.4rem;line-height:2.5rem"><?= fmt_money($stats['unpaid_billing']) ?></div>
      </div>
    </div>
  </div>
</div>

<?php if ($stats['correction_cases'] > 0): ?>
<div class="alert alert-danger d-flex align-items-center mb-4">
  <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
  <div>補正対応中の案件が <strong><?= $stats['correction_cases'] ?> 件</strong> あります。
    <a href="<?= GC_BASE_URL ?>/cases.php?stage=correction" class="alert-link ms-2">確認する</a>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- 直近期日 -->
  <div class="col-md-6">
    <div class="page-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0 fw-bold"><i class="bi bi-calendar-event me-1 text-danger"></i>直近14日の期日</h2>
        <a href="<?= GC_BASE_URL ?>/deadlines.php" class="btn btn-sm btn-outline-secondary py-0">全て見る</a>
      </div>
      <?php if (empty($deadlines)): ?>
        <p class="text-muted small mb-0">期限が近い期日はありません。</p>
      <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($deadlines as $dl):
            $dt = strtotime($dl['deadline_date']);
            $now = time();
            $diff = (int)(($dt - $now) / 86400);
            $cls = $diff < 0 ? 'deadline-overdue' : ($diff <= 3 ? 'deadline-soon' : '');
        ?>
        <div class="list-group-item px-0 py-2 border-0 border-bottom">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="<?= $cls ?> small fw-bold"><?= fmt_date($dl['deadline_date']) ?>
                <?php if ($diff < 0): ?><span class="badge bg-danger ms-1">超過</span>
                <?php elseif ($diff <= 3): ?><span class="badge bg-warning text-dark ms-1">あと<?= $diff ?>日</span>
                <?php else: ?><span class="text-muted ms-1">あと<?= $diff ?>日</span>
                <?php endif; ?>
              </div>
              <div><?= h($dl['title']) ?></div>
              <div class="text-muted small"><?= h($dl['company_name'] ?: $dl['client_name']) ?> / <?= h($dl['case_name']) ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 最近の進捗 -->
  <div class="col-md-6">
    <div class="page-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0 fw-bold"><i class="bi bi-journal-text me-1" style="color:#1b5e35"></i>最近の進捗</h2>
        <a href="<?= GC_BASE_URL ?>/cases.php" class="btn btn-sm btn-outline-secondary py-0">案件一覧</a>
      </div>
      <?php if (empty($recent)): ?>
        <p class="text-muted small mb-0">進捗記録がありません。</p>
      <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($recent as $p): ?>
        <div class="list-group-item px-0 py-2 border-0 border-bottom">
          <div class="d-flex justify-content-between">
            <div>
              <span class="badge" style="background:#e8f5e9;color:#1b5e35"><?= h(PROGRESS_TYPE_MAP[$p['progress_type']] ?? '') ?></span>
              <a href="<?= GC_BASE_URL ?>/case_view.php?id=<?= $p['case_id'] ?>" class="ms-1 text-decoration-none small fw-bold"><?= h($p['case_name']) ?></a>
              <div class="text-muted small mt-1"><?= h(mb_strimwidth($p['content'], 0, 60, '…')) ?></div>
            </div>
            <div class="text-muted small text-nowrap ms-2"><?= fmt_date($p['recorded_at']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
