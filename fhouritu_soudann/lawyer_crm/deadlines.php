<?php
require_once 'config.php';
require_login();
$page_title = '期日リマインド';

$db     = get_db();
$filter = $_GET['filter'] ?? 'upcoming';
$type   = $_GET['type'] ?? '';

$where  = ['d.is_completed = 0'];
$params = [];

if ($filter === 'today') {
    $where[] = 'd.deadline_date = CURDATE()';
} elseif ($filter === 'week') {
    $where[] = 'd.deadline_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)';
} elseif ($filter === 'month') {
    $where[] = 'd.deadline_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
} elseif ($filter === 'overdue') {
    $where[] = 'd.deadline_date < CURDATE()';
} elseif ($filter === 'all') {
    $where = [];
}

if ($type) { $where[] = 'd.deadline_type = ?'; $params[] = $type; }

$sql = "SELECT d.*, c.title AS case_title, c.case_type, c.id AS case_id,
               cl.name AS client_name, c.assigned_lawyer
        FROM deadlines d
        JOIN cases c ON d.case_id = c.id
        JOIN clients cl ON c.client_id = cl.id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY d.is_completed ASC, d.deadline_date ASC';

$st = $db->prepare($sql);
$st->execute($params);
$deadlines = $st->fetchAll();

// counts
$counts = $db->query(
    "SELECT
       SUM(deadline_date = CURDATE() AND is_completed=0) AS today,
       SUM(deadline_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 6 DAY) AND is_completed=0) AS week,
       SUM(deadline_date < CURDATE() AND is_completed=0) AS overdue
     FROM deadlines"
)->fetch();

require 'includes/header.php';
?>

<!-- Filters -->
<div class="d-flex align-items-center gap-2 flex-wrap mb-3">
  <?php
  $filters = [
    ['today',    '今日',           $counts['today']],
    ['week',     '今週',           $counts['week']],
    ['month',    '今後30日',        null],
    ['upcoming', '未完了すべて',    null],
    ['overdue',  '期限超過',       $counts['overdue']],
    ['all',      '完了含む全件',    null],
  ];
  foreach ($filters as [$key, $label, $cnt]):
    $active = $filter === $key ? 'btn-primary' : 'btn-outline-secondary';
  ?>
  <a href="?filter=<?= $key ?><?= $type ? '&type='.$type : '' ?>" class="btn btn-sm <?= $active ?>">
    <?= $label ?>
    <?php if ($cnt): ?><span class="badge bg-<?= $key === 'overdue' ? 'danger' : 'light text-dark' ?> ms-1"><?= $cnt ?></span><?php endif; ?>
  </a>
  <?php endforeach; ?>

  <div class="ms-auto">
    <select name="type" class="form-select form-select-sm" onchange="location='?filter=<?= $filter ?>&type='+this.value">
      <option value="">すべての種別</option>
      <?php foreach (DEADLINE_TYPES as $t): ?>
      <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<?php if ($counts['overdue'] > 0 && $filter !== 'overdue'): ?>
<div class="alert alert-danger py-2">
  <i class="bi bi-exclamation-triangle-fill me-1"></i>
  <strong><?= $counts['overdue'] ?>件</strong>の期日が超過しています。
  <a href="?filter=overdue" class="alert-link">確認する</a>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
    <span class="fw-semibold">
      <?= array_column($filters, 2, 0)[$filter][1] ?? $filter ?>
      <span class="badge bg-secondary ms-1"><?= count($deadlines) ?>件</span>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>状態</th>
          <th>期日</th>
          <th>種別</th>
          <th>内容</th>
          <th>案件</th>
          <th>依頼人</th>
          <th>担当</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($deadlines): ?>
        <?php foreach ($deadlines as $dl):
          if ($dl['is_completed']) {
            $diff_label = '完了';
            $badge = 'success';
          } else {
            $diff = days_until($dl['deadline_date']);
            if ($diff < 0)      { $diff_label = abs($diff).'日超過'; $badge = 'danger'; }
            elseif ($diff === 0){ $diff_label = '今日';              $badge = 'danger'; }
            elseif ($diff <= 3) { $diff_label = $diff.'日後';        $badge = 'warning'; }
            elseif ($diff <= 14){ $diff_label = $diff.'日後';        $badge = 'info'; }
            else                { $diff_label = $diff.'日後';        $badge = 'secondary'; }
          }
        ?>
        <tr class="<?= !$dl['is_completed'] && days_until($dl['deadline_date']) < 0 ? 'table-danger' : '' ?>">
          <td><span class="badge bg-<?= $badge ?>"><?= $diff_label ?></span></td>
          <td class="small"><?= format_date($dl['deadline_date']) ?></td>
          <td><span class="badge bg-light text-dark border"><?= h($dl['deadline_type']) ?></span></td>
          <td>
            <div class="fw-semibold small"><?= h($dl['title']) ?></div>
            <?php if ($dl['description']): ?>
            <div class="text-muted" style="font-size:.75rem"><?= h(mb_strimwidth($dl['description'],0,40,'…')) ?></div>
            <?php endif; ?>
          </td>
          <td><a href="case_detail.php?id=<?= $dl['case_id'] ?>#deadlines" class="text-decoration-none small"><?= h(mb_strimwidth($dl['case_title'],0,22,'…')) ?></a></td>
          <td class="small"><?= h($dl['client_name']) ?></td>
          <td class="small text-muted"><?= h($dl['assigned_lawyer'] ?? '—') ?></td>
          <td>
            <?php if (!$dl['is_completed']): ?>
            <form method="post" action="case_detail.php?id=<?= $dl['case_id'] ?>">
              <input type="hidden" name="action" value="complete_deadline">
              <input type="hidden" name="dl_id" value="<?= $dl['id'] ?>">
              <input type="hidden" name="redirect_anchor" value="">
              <button class="btn btn-sm btn-outline-success" title="完了">
                <i class="bi bi-check2"></i>
              </button>
            </form>
            <?php else: ?>
            <i class="bi bi-check-circle-fill text-success"></i>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <tr><td colspan="8" class="text-center text-muted py-5">
          <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>該当する期日はありません
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
