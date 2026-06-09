<?php
require_once 'config.php';
require_login();

$db     = get_db();
$status = $_GET['status'] ?? '';
$type   = $_GET['type'] ?? '';
$q      = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($status !== '') { $where[] = 'c.status = ?'; $params[] = $status; }
if ($type   !== '') { $where[] = 'c.case_type = ?'; $params[] = $type; }
if ($q      !== '') {
    $where[] = '(c.title LIKE ? OR c.case_number LIKE ? OR cl.name LIKE ?)';
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%"]);
}

$sql = "SELECT c.*, cl.name AS client_name
        FROM cases c JOIN clients cl ON cl.id = c.client_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY c.updated_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$cases = $stmt->fetchAll();

$page_title = '案件管理';
require 'includes/header.php';
?>

<!-- フィルター -->
<form class="card card-body mb-3 py-2" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-1">キーワード</label>
      <input type="search" name="q" value="<?= h($q) ?>" class="form-control form-control-sm" placeholder="案件名・番号・依頼人">
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">状態</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">すべて</option>
        <?php foreach (CASE_STATUSES as $s): ?>
        <option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label small mb-1">案件種別</label>
      <select name="type" class="form-select form-select-sm">
        <option value="">すべて</option>
        <?php foreach (CASE_TYPES as $t): ?>
        <option <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>絞込</button>
      <a href="cases.php" class="btn btn-outline-secondary btn-sm">リセット</a>
    </div>
  </div>
</form>

<div class="d-flex align-items-center justify-content-between mb-2">
  <div class="text-muted small"><?= count($cases) ?> 件</div>
  <a href="case_form.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規案件</a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>案件番号</th><th>案件名</th><th>依頼人</th><th>種別</th>
          <th>状態</th><th>担当</th><th>着手日</th><th>法務局</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cases as $c): ?>
        <tr>
          <td><small class="text-muted"><?= h($c['case_number'] ?? '—') ?></small></td>
          <td class="fw-semibold">
            <i class="bi <?= TYPE_ICONS[$c['case_type']] ?? 'bi-folder' ?> me-1 text-primary"></i>
            <a href="case_detail.php?id=<?= $c['id'] ?>" class="text-decoration-none"><?= h($c['title']) ?></a>
          </td>
          <td><a href="client_detail.php?id=<?= $c['client_id'] ?>" class="text-decoration-none text-muted"><?= h($c['client_name']) ?></a></td>
          <td><small><?= h($c['case_type']) ?></small></td>
          <td><span class="badge bg-<?= STATUS_COLORS[$c['status']] ?>"><?= h($c['status']) ?></span></td>
          <td><small><?= h($c['assigned_staff'] ?? '—') ?></small></td>
          <td><small><?= format_date($c['start_date']) ?></small></td>
          <td><small><?= h($c['registry_office'] ?? '—') ?></small></td>
          <td>
            <div class="d-flex gap-1">
              <a href="case_detail.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">詳細</a>
              <a href="case_form.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">編集</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($cases)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">案件が見つかりません</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
