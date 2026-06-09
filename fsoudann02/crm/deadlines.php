<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/crm_functions.php';
require_admin();

$nav        = 'deadlines';
$page_title = '期日管理';
$show_all   = (int)($_GET['all'] ?? 0);
$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d', strtotime('+30 days'));

$f = $show_all ? ['is_done' => null] : ['is_done' => 0, 'from' => $from, 'to' => $to];
$deadlines = crm_get_deadlines($f);
?>
<?php require __DIR__ . '/includes/_header.php'; ?>
<div class="page-card">
  <form method="get" class="row g-2 mb-3 align-items-end">
    <?php if (!$show_all): ?>
    <div class="col-auto"><label class="form-label small mb-1">期間（開始）</label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= crm_h($from) ?>"></div>
    <div class="col-auto"><label class="form-label small mb-1">期間（終了）</label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?= crm_h($to) ?>"></div>
    <div class="col-auto pt-3"><button class="btn btn-sm btn-primary">絞り込み</button></div>
    <?php endif; ?>
    <div class="col-auto pt-3">
      <?php if ($show_all): ?>
      <a href="<?= CRM_URL ?>/deadlines.php" class="btn btn-sm btn-outline-secondary">未完了のみ</a>
      <?php else: ?>
      <a href="<?= CRM_URL ?>/deadlines.php?all=1" class="btn btn-sm btn-outline-secondary">全件表示</a>
      <?php endif; ?>
    </div>
  </form>
  <div class="text-muted small mb-2"><?= $show_all ? '全件' : crm_h($from).' 〜 '.crm_h($to).' の未完了' ?> ／ <?= count($deadlines) ?> 件</div>
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>状態</th><th>日時</th><th>種別</th><th>内容</th><th>案件</th><th>依頼者</th></tr></thead>
    <tbody>
    <?php if (empty($deadlines)): ?>
      <tr><td colspan="6" class="text-center text-muted py-4">該当する期日がありません。</td></tr>
    <?php else: foreach ($deadlines as $dl):
      $dt = strtotime($dl['deadline_date']); $days = (int)(($dt - time()) / 86400);
      $is_past = $dt < time() && !$dl['is_done'];
    ?>
      <tr class="<?= $dl['is_done'] ? 'text-muted' : ($is_past ? 'table-danger' : ($days<=3 ? 'table-warning' : '')) ?>">
        <td>
          <?php if ($dl['is_done']): ?><span class="badge bg-success">完了</span>
          <?php elseif ($is_past): ?><span class="badge bg-danger">超過</span>
          <?php elseif ($days===0): ?><span class="badge bg-warning text-dark">今日</span>
          <?php elseif ($days<=3): ?><span class="badge" style="background:#fd7e14;color:#fff"><?= $days ?>日後</span>
          <?php else: ?><span class="badge bg-light text-dark"><?= $days ?>日後</span>
          <?php endif; ?>
        </td>
        <td><?= date('Y/m/d H:i', $dt) ?></td>
        <td><?= crm_h(DEADLINE_TYPE_MAP[$dl['deadline_type']] ?? '') ?></td>
        <td><?= $dl['is_done'] ? '<s>'.crm_h($dl['title']).'</s>' : crm_h($dl['title']) ?></td>
        <td><a href="<?= CRM_URL ?>/case_view.php?id=<?= $dl['case_id'] ?>"><?= crm_h($dl['case_name']) ?></a></td>
        <td><?= crm_h($dl['client_name']) ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/_footer.php'; ?>
