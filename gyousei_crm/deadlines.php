<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$gc_user    = gc_require_login();
$page_title = '期日管理';
$page_nav   = 'deadlines';

$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d', strtotime('+30 days'));
$show = $_GET['show'] ?? 'pending';

$f = ['from' => $from, 'to' => $to];
if ($show === 'pending') $f['is_done'] = 0;
elseif ($show === 'done') $f['is_done'] = 1;

$deadlines = get_deadlines($f);
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<div class="page-card">
  <form method="get" class="row g-2 mb-3 align-items-end">
    <div class="col-auto">
      <label class="form-label small fw-bold mb-1">開始日</label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= h($from) ?>">
    </div>
    <div class="col-auto">
      <label class="form-label small fw-bold mb-1">終了日</label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?= h($to) ?>">
    </div>
    <div class="col-auto">
      <label class="form-label small fw-bold mb-1">表示</label>
      <select name="show" class="form-select form-select-sm">
        <option value="pending" <?= $show==='pending'?'selected':'' ?>>未完了</option>
        <option value="done"    <?= $show==='done'?'selected':'' ?>>完了済み</option>
        <option value="all"     <?= $show==='all'?'selected':'' ?>>全て</option>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-primary">絞り込み</button>
      <a href="<?= GC_BASE_URL ?>/deadlines.php" class="btn btn-sm btn-outline-secondary ms-1">リセット</a>
    </div>
  </form>

  <div class="text-muted small mb-2">全 <?= count($deadlines) ?> 件</div>

  <?php if (empty($deadlines)): ?>
    <p class="text-muted text-center py-4">条件に該当する期日がありません。</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>期日</th><th>タイトル</th><th>種別</th><th>案件名</th><th>依頼人</th><th>状態</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($deadlines as $dl):
        $dt = strtotime($dl['deadline_date']); $diff = (int)(($dt - time()) / 86400);
        $row_cls = $dl['is_done'] ? 'text-muted' : ($diff < 0 ? 'table-danger' : ($diff <= 3 ? 'table-warning' : ''));
      ?>
      <tr class="<?= $row_cls ?>">
        <td class="small fw-bold <?= !$dl['is_done'] && $diff < 0 ? 'deadline-overdue' : (!$dl['is_done'] && $diff <= 3 ? 'deadline-soon' : '') ?>">
          <?= fmt_datetime($dl['deadline_date']) ?>
          <?php if (!$dl['is_done']): ?>
            <?php if ($diff < 0): ?><span class="badge bg-danger ms-1">超過</span>
            <?php elseif ($diff === 0): ?><span class="badge bg-danger ms-1">本日</span>
            <?php elseif ($diff <= 3): ?><span class="badge bg-warning text-dark ms-1">あと<?= $diff ?>日</span>
            <?php endif; ?>
          <?php endif; ?>
        </td>
        <td><?= h($dl['title']) ?></td>
        <td><span class="badge bg-secondary"><?= h(DEADLINE_TYPE_MAP[$dl['deadline_type']] ?? '') ?></span></td>
        <td><a href="<?= GC_BASE_URL ?>/case_view.php?id=<?= $dl['case_id'] ?>&tab=deadlines" class="text-decoration-none"><?= h($dl['case_name']) ?></a></td>
        <td><?= h($dl['company_name'] ?: $dl['client_name']) ?></td>
        <td><?= $dl['is_done'] ? '<span class="badge bg-success">完了</span>' : '<span class="badge bg-warning text-dark">未完了</span>' ?></td>
        <td>
          <a href="<?= GC_BASE_URL ?>/case_view.php?id=<?= $dl['case_id'] ?>&tab=deadlines" class="btn btn-sm btn-outline-secondary py-0 px-2">詳細</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
