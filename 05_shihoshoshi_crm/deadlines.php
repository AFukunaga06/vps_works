<?php
require_once 'config.php';
require_login();

$db     = get_db();
$filter = $_GET['filter'] ?? 'upcoming';

if ($filter === 'all') {
    $sql = "SELECT d.*, c.title AS case_title, c.case_number, c.id AS case_id
            FROM deadlines d JOIN cases c ON c.id = d.case_id
            WHERE d.is_completed = 0
            ORDER BY d.deadline_date ASC";
} elseif ($filter === 'done') {
    $sql = "SELECT d.*, c.title AS case_title, c.case_number, c.id AS case_id
            FROM deadlines d JOIN cases c ON c.id = d.case_id
            WHERE d.is_completed = 1
            ORDER BY d.completed_at DESC LIMIT 50";
} else {
    $sql = "SELECT d.*, c.title AS case_title, c.case_number, c.id AS case_id
            FROM deadlines d JOIN cases c ON c.id = d.case_id
            WHERE d.is_completed = 0 AND d.deadline_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY d.deadline_date ASC";
}
$deadlines = $db->query($sql)->fetchAll();

$page_title = '期日管理';
require 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div class="btn-group btn-group-sm">
    <a href="?filter=upcoming" class="btn btn-<?= $filter === 'upcoming' ? 'primary' : 'outline-secondary' ?>">直近30日</a>
    <a href="?filter=all" class="btn btn-<?= $filter === 'all' ? 'primary' : 'outline-secondary' ?>">未完了すべて</a>
    <a href="?filter=done" class="btn btn-<?= $filter === 'done' ? 'primary' : 'outline-secondary' ?>">完了済</a>
  </div>
  <span class="text-muted small"><?= count($deadlines) ?> 件</span>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>期日</th><th>残日数</th><th>タイトル</th><th>種別</th><th>案件</th><th>状態</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($deadlines as $d):
          $days = days_until($d['deadline_date']);
          $rowClass = '';
          if (!$d['is_completed']) {
              if ($days < 0) $rowClass = 'table-danger';
              elseif ($days <= 3) $rowClass = 'table-warning';
          }
        ?>
        <tr class="<?= $rowClass ?>">
          <td class="fw-semibold"><?= format_date($d['deadline_date']) ?></td>
          <td>
            <?php if ($d['is_completed']): ?>
            <span class="badge bg-success">完了</span>
            <?php elseif ($days < 0): ?>
            <span class="badge bg-danger"><?= abs($days) ?>日超過</span>
            <?php elseif ($days === 0): ?>
            <span class="badge bg-warning text-dark">本日</span>
            <?php else: ?>
            <span class="badge bg-primary"><?= $days ?>日後</span>
            <?php endif; ?>
          </td>
          <td class="fw-semibold"><?= h($d['title']) ?></td>
          <td><span class="badge bg-light text-dark"><?= h($d['deadline_type']) ?></span></td>
          <td>
            <a href="case_detail.php?id=<?= $d['case_id'] ?>" class="text-decoration-none small">
              <span class="text-muted"><?= h($d['case_number'] ?? '') ?></span>
              <?= h(mb_strimwidth($d['case_title'], 0, 20, '…')) ?>
            </a>
          </td>
          <td>
            <?php if ($d['is_completed']): ?>
            <span class="text-muted small"><?= format_date($d['completed_at']) ?>完了</span>
            <?php else: ?>
            <form method="post" action="case_detail.php?id=<?= $d['case_id'] ?>">
              <input type="hidden" name="action" value="complete_deadline">
              <input type="hidden" name="deadline_id" value="<?= $d['id'] ?>">
              <button class="btn btn-sm btn-outline-success py-0"><i class="bi bi-check-lg"></i> 完了</button>
            </form>
            <?php endif; ?>
          </td>
          <td>
            <a href="case_detail.php?id=<?= $d['case_id'] ?>" class="btn btn-sm btn-outline-primary">案件へ</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($deadlines)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">期日がありません</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
