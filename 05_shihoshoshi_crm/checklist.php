<?php
require_once 'config.php';
require_login();

$db     = get_db();
$filter = $_GET['filter'] ?? 'pending';
$q      = trim($_GET['q'] ?? '');

// POST: チェック切替
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle') {
        $doc = $db->query("SELECT * FROM doc_checklist WHERE id=" . (int)$_POST['doc_id'])->fetch();
        $received = $doc['is_received'] ? 0 : 1;
        $db->prepare("UPDATE doc_checklist SET is_received=?, received_at=? WHERE id=?")
           ->execute([$received, $received ? date('Y-m-d') : null, (int)$_POST['doc_id']]);
    }
    header('Location: checklist.php?filter=' . urlencode($filter) . '&q=' . urlencode($q));
    exit;
}

// 案件ごとにグルーピング
$caseWhere = $filter === 'pending' ? 'AND d.is_received = 0'
           : ($filter === 'done'   ? 'AND d.is_received = 1' : '');
$qWhere    = $q ? " AND (c.title LIKE ? OR cl.name LIKE ?)" : '';

$sql = "SELECT c.id AS case_id, c.title AS case_title, c.case_number, c.status,
               cl.name AS client_name,
               COUNT(d.id) AS total_docs,
               SUM(d.is_received) AS received_docs
        FROM cases c
        JOIN clients cl ON cl.id = c.client_id
        JOIN doc_checklist d ON d.case_id = c.id
        WHERE 1=1 $qWhere
        GROUP BY c.id
        HAVING 1=1 " . ($filter === 'pending' ? " AND received_docs < total_docs"
                       : ($filter === 'done'   ? " AND received_docs = total_docs" : ""))
     . " ORDER BY received_docs/total_docs ASC, c.updated_at DESC";

$params = $q ? ["%$q%", "%$q%"] : [];
$stmt = $db->prepare($sql);
$stmt->execute($params);
$case_groups = $stmt->fetchAll();

$page_title = '書類チェックリスト';
require 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div class="btn-group btn-group-sm">
    <a href="?filter=pending&q=<?= urlencode($q) ?>" class="btn btn-<?= $filter === 'pending' ? 'primary' : 'outline-secondary' ?>">未収取あり</a>
    <a href="?filter=done&q=<?= urlencode($q) ?>" class="btn btn-<?= $filter === 'done' ? 'primary' : 'outline-secondary' ?>">収取完了</a>
    <a href="?filter=all&q=<?= urlencode($q) ?>" class="btn btn-<?= $filter === 'all' ? 'primary' : 'outline-secondary' ?>">すべて</a>
  </div>
  <form class="d-flex gap-2" method="get">
    <input type="hidden" name="filter" value="<?= h($filter) ?>">
    <input type="search" name="q" value="<?= h($q) ?>" class="form-control form-control-sm" placeholder="案件名・依頼人" style="width:200px">
    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
  </form>
</div>

<?php if (empty($case_groups)): ?>
<div class="text-center text-muted py-5"><i class="bi bi-check2-all fs-1 d-block mb-2"></i>該当する案件がありません</div>
<?php endif; ?>

<?php foreach ($case_groups as $cg):
  $docs = $db->query("SELECT * FROM doc_checklist WHERE case_id={$cg['case_id']} ORDER BY sort_order, id")->fetchAll();
  $pct  = $cg['total_docs'] > 0 ? round($cg['received_docs'] / $cg['total_docs'] * 100) : 0;
?>
<div class="card mb-3">
  <div class="card-header bg-white py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <a href="case_detail.php?id=<?= $cg['case_id'] ?>" class="fw-semibold text-decoration-none">
        <?= h($cg['case_title']) ?>
      </a>
      <span class="text-muted small ms-2"><?= h($cg['case_number'] ?? '') ?></span>
      <span class="badge bg-light text-dark ms-1"><?= h($cg['client_name']) ?></span>
      <span class="badge bg-<?= STATUS_COLORS[$cg['status']] ?> ms-1"><?= h($cg['status']) ?></span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <div class="progress" style="width:80px;height:8px">
        <div class="progress-bar bg-<?= $pct === 100 ? 'success' : 'primary' ?>" style="width:<?= $pct ?>%"></div>
      </div>
      <span class="small fw-semibold <?= $pct === 100 ? 'text-success' : 'text-primary' ?>">
        <?= $cg['received_docs'] ?>/<?= $cg['total_docs'] ?>
      </span>
    </div>
  </div>
  <div class="card-body py-2 px-3">
    <div class="row g-1">
      <?php foreach ($docs as $doc): ?>
      <div class="col-md-6">
        <div class="checklist-item <?= $doc['is_received'] ? 'received' : '' ?>">
          <form method="post" class="d-flex align-items-center gap-2 w-100">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
            <button type="submit" class="btn btn-sm p-0 border-0">
              <i class="bi <?= $doc['is_received'] ? 'bi-check-square-fill text-success' : 'bi-square text-muted' ?> fs-5"></i>
            </button>
            <span class="small flex-1"><?= h($doc['doc_name']) ?></span>
            <?php if ($doc['is_received'] && $doc['received_at']): ?>
            <small class="text-muted ms-auto text-nowrap"><?= format_date($doc['received_at']) ?></small>
            <?php endif; ?>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php require 'includes/footer.php'; ?>
