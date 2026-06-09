<?php
require_once 'config.php';
require_login();
$page_title = '案件管理';

$db        = get_db();
$status    = $_GET['status'] ?? '';
$type      = $_GET['type'] ?? '';
$client_id = (int)($_GET['client_id'] ?? 0);
$q         = trim($_GET['q'] ?? '');

$where  = [];
$params = [];
if ($status) { $where[] = 'c.status = ?';      $params[] = $status; }
if ($type)   { $where[] = 'c.case_type = ?';   $params[] = $type; }
if ($client_id) { $where[] = 'c.client_id = ?'; $params[] = $client_id; }
if ($q) {
    $where[] = '(c.title LIKE ? OR cl.name LIKE ? OR c.case_number LIKE ?)';
    $like = '%'.$q.'%';
    array_push($params, $like, $like, $like);
}

$sql = "SELECT c.*, cl.name AS client_name,
               (SELECT COUNT(*) FROM deadlines d WHERE d.case_id=c.id AND d.is_completed=0) AS open_dl
        FROM cases c JOIN clients cl ON c.client_id=cl.id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY c.updated_at DESC';

$st = $db->prepare($sql);
$st->execute($params);
$cases = $st->fetchAll();

require 'includes/header.php';
?>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-sm-auto">
        <label class="form-label small fw-semibold mb-1">ステータス</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">すべて</option>
          <?php foreach (CASE_STATUSES as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-auto">
        <label class="form-label small fw-semibold mb-1">案件種別</label>
        <select name="type" class="form-select form-select-sm">
          <option value="">すべて</option>
          <?php foreach (CASE_TYPES as $t): ?>
          <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm">
        <label class="form-label small fw-semibold mb-1">キーワード</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="案件名・依頼人・案件番号"
               value="<?= h($q) ?>">
      </div>
      <div class="col-sm-auto">
        <button class="btn btn-sm btn-primary">絞り込み</button>
        <a href="cases.php" class="btn btn-sm btn-outline-secondary ms-1">クリア</a>
      </div>
    </form>
  </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-3">
  <span class="text-muted small">
    <?= count($cases) ?>件の案件
    <?php if ($client_id): ?>
      <?php
      $cn = $db->prepare('SELECT name FROM clients WHERE id=?');
      $cn->execute([$client_id]);
      $cn_row = $cn->fetch();
      ?>
      <span class="badge bg-info ms-1"><?= h($cn_row['name'] ?? '') ?> の案件</span>
    <?php endif; ?>
  </span>
  <a href="case_form.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i>新規案件登録
  </a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>案件番号</th>
          <th>状態</th>
          <th>案件名</th>
          <th>種別</th>
          <th>依頼人</th>
          <th>担当</th>
          <th>着手日</th>
          <th>期日</th>
          <th>着手金</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($cases): ?>
        <?php foreach ($cases as $c):
          $sc = STATUS_COLORS[$c['status']] ?? 'secondary';
        ?>
        <tr>
          <td class="small text-muted"><?= h($c['case_number'] ?? '—') ?></td>
          <td><span class="badge bg-<?= $sc ?>"><?= h($c['status']) ?></span></td>
          <td>
            <a href="case_detail.php?id=<?= $c['id'] ?>" class="fw-semibold text-decoration-none">
              <?= h(mb_strimwidth($c['title'], 0, 30, '…')) ?>
            </a>
          </td>
          <td>
            <span class="badge bg-light text-dark border">
              <i class="bi <?= TYPE_ICONS[$c['case_type']] ?? 'bi-folder' ?> me-1"></i><?= h($c['case_type']) ?>
            </span>
          </td>
          <td><a href="client_detail.php?id=<?= $c['client_id'] ?>" class="text-decoration-none small"><?= h($c['client_name']) ?></a></td>
          <td class="small"><?= h($c['assigned_lawyer'] ?? '—') ?></td>
          <td class="small text-muted"><?= format_date($c['start_date']) ?></td>
          <td>
            <?php if ($c['open_dl'] > 0): ?>
            <a href="case_detail.php?id=<?= $c['id'] ?>#deadlines" class="badge bg-danger text-decoration-none"><?= $c['open_dl'] ?>件</a>
            <?php else: ?>
            <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
          <td class="small"><?= format_money((int)$c['retainer_fee']) ?></td>
          <td>
            <a href="case_detail.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">詳細</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <tr><td colspan="10" class="text-center text-muted py-5">案件が見つかりませんでした</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
