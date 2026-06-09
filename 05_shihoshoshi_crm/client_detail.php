<?php
require_once 'config.php';
require_login();

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cl = $db->query("SELECT * FROM clients WHERE id = $id")->fetch();
if (!$cl) { header('Location: clients.php'); exit; }

$cases = $db->query("
  SELECT c.*, COALESCE(SUM(b.amount), 0) AS total_billing
  FROM cases c
  LEFT JOIN billing b ON b.case_id = c.id
  WHERE c.client_id = $id
  GROUP BY c.id
  ORDER BY c.start_date DESC
")->fetchAll();

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_client') {
    $db->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
    header('Location: clients.php');
    exit;
}

$page_title = h($cl['name']);
require 'includes/header.php';
?>

<div class="mb-3 d-flex align-items-center justify-content-between">
  <a href="clients.php" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>依頼人台帳</a>
  <div class="d-flex gap-2">
    <a href="client_form.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>編集</a>
    <a href="case_form.php?client_id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>案件追加</a>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body">
        <h5 class="fw-bold mb-1"><?= h($cl['name']) ?></h5>
        <div class="text-muted small mb-3"><?= h($cl['name_kana'] ?? '') ?></div>
        <dl class="row small mb-0">
          <dt class="col-4 text-muted">性別</dt><dd class="col-8"><?= h($cl['gender'] ?? '—') ?></dd>
          <dt class="col-4 text-muted">生年月日</dt><dd class="col-8"><?= format_date($cl['birth_date'] ?? null) ?></dd>
          <dt class="col-4 text-muted">電話</dt><dd class="col-8"><?= h($cl['phone'] ?? '—') ?></dd>
          <dt class="col-4 text-muted">メール</dt><dd class="col-8"><?= h($cl['email'] ?? '—') ?></dd>
          <dt class="col-4 text-muted">郵便番号</dt><dd class="col-8"><?= h($cl['postal_code'] ?? '—') ?></dd>
          <dt class="col-4 text-muted">住所</dt><dd class="col-8"><?= h($cl['address'] ?? '—') ?></dd>
        </dl>
        <?php if ($cl['notes']): ?>
        <hr class="my-2">
        <div class="small text-muted"><?= nl2br(h($cl['notes'])) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="mt-3">
      <form method="post" onsubmit="return confirm('この依頼人と関連する全データを削除します。よろしいですか？')">
        <input type="hidden" name="action" value="delete_client">
        <button class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-trash me-1"></i>依頼人を削除</button>
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header bg-white py-2 fw-semibold">
        <i class="bi bi-folder2-open text-primary me-2"></i>案件一覧（<?= count($cases) ?>件）
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>案件番号</th><th>案件名</th><th>種別</th><th>状態</th><th>着手日</th><th>報酬計</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cases as $c): ?>
            <tr>
              <td><small class="text-muted"><?= h($c['case_number'] ?? '—') ?></small></td>
              <td class="fw-semibold"><a href="case_detail.php?id=<?= $c['id'] ?>" class="text-decoration-none"><?= h($c['title']) ?></a></td>
              <td><small><?= h($c['case_type']) ?></small></td>
              <td><span class="badge bg-<?= STATUS_COLORS[$c['status']] ?>"><?= h($c['status']) ?></span></td>
              <td><small><?= format_date($c['start_date']) ?></small></td>
              <td><small>¥<?= number_format($c['total_billing']) ?></small></td>
              <td><a href="case_detail.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">詳細</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($cases)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">案件はありません</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
