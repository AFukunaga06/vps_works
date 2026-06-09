<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$lc_user    = lc_require_login();
$page_title = '期日管理';
$page_nav   = 'deadlines';

$show_done = (int)($_GET['done'] ?? 0);
$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d', strtotime('+30 days'));

$deadlines = get_deadlines(['is_done' => $show_done ? null : 0, 'from' => $show_done ? null : $from, 'to' => $show_done ? null : $to]);
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<div class="page-card">
  <form method="get" class="row g-2 mb-3 align-items-end">
    <?php if (!$show_done): ?>
    <div class="col-auto">
      <label class="form-label small mb-1">期間（開始）</label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= h($from) ?>">
    </div>
    <div class="col-auto">
      <label class="form-label small mb-1">期間（終了）</label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?= h($to) ?>">
    </div>
    <div class="col-auto pt-3">
      <button class="btn btn-sm btn-primary">絞り込み</button>
    </div>
    <?php endif; ?>
    <div class="col-auto pt-3">
      <?php if ($show_done): ?>
      <a href="<?= LC_BASE_URL ?>/deadlines.php" class="btn btn-sm btn-outline-secondary">未完了のみ表示</a>
      <?php else: ?>
      <a href="<?= LC_BASE_URL ?>/deadlines.php?done=1" class="btn btn-sm btn-outline-secondary">完了済みを含む</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="text-muted small mb-2">
    <?= $show_done ? '全件' : h($from) . ' 〜 ' . h($to) . ' の未完了期日' ?> ／ <?= count($deadlines) ?> 件
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>状態</th>
          <th>日時</th>
          <th>種別</th>
          <th>タイトル</th>
          <th>案件</th>
          <th>依頼者</th>
          <th>メモ</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($deadlines)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">該当する期日がありません。</td></tr>
      <?php else: ?>
        <?php foreach ($deadlines as $dl):
          $dt = strtotime($dl['deadline_date']);
          $days_left = (int)(($dt - time()) / 86400);
          $is_past = $dt < time() && !$dl['is_done'];
        ?>
        <tr class="<?= $dl['is_done'] ? 'text-muted' : ($is_past ? 'table-danger' : ($days_left <= 3 ? 'table-warning' : '')) ?>">
          <td>
            <?php if ($dl['is_done']): ?>
            <span class="badge bg-success">完了</span>
            <?php elseif ($is_past): ?>
            <span class="badge bg-danger">超過</span>
            <?php elseif ($days_left === 0): ?>
            <span class="badge bg-warning text-dark">今日</span>
            <?php elseif ($days_left <= 3): ?>
            <span class="badge" style="background:#fd7e14;color:#fff"><?= $days_left ?>日後</span>
            <?php else: ?>
            <span class="badge bg-light text-dark"><?= $days_left ?>日後</span>
            <?php endif; ?>
          </td>
          <td><?= date('Y/m/d H:i', $dt) ?></td>
          <td><?= h(DEADLINE_TYPE_MAP[$dl['deadline_type']] ?? '') ?></td>
          <td><?= $dl['is_done'] ? '<s>' . h($dl['title']) . '</s>' : h($dl['title']) ?></td>
          <td><a href="<?= LC_BASE_URL ?>/case_view.php?id=<?= $dl['case_id'] ?>"><?= h($dl['case_name']) ?></a></td>
          <td><?= h($dl['client_name']) ?></td>
          <td class="text-muted small"><?= h($dl['memo']) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
