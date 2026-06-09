<?php
require_once 'config.php';
require_login();

$db = get_db();
$q  = trim($_GET['q'] ?? '');

$sql = "SELECT c.*, COUNT(ca.id) AS case_count
        FROM clients c
        LEFT JOIN cases ca ON ca.client_id = c.id";
$params = [];
if ($q !== '') {
    $sql .= " WHERE c.name LIKE ? OR c.name_kana LIKE ? OR c.phone LIKE ? OR c.email LIKE ?";
    $params = ["%$q%", "%$q%", "%$q%", "%$q%"];
}
$sql .= " GROUP BY c.id ORDER BY c.updated_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$page_title = '依頼人台帳';
require 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div class="text-muted small"><?= count($clients) ?> 件</div>
  <div class="d-flex gap-2">
    <form class="d-flex gap-2" method="get">
      <input type="search" name="q" value="<?= h($q) ?>" class="form-control form-control-sm" placeholder="氏名・電話・メール検索" style="width:220px">
      <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
    </form>
    <a href="client_form.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規登録</a>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>氏名</th>
          <th>かな</th>
          <th>性別</th>
          <th>電話</th>
          <th>メール</th>
          <th>住所</th>
          <th class="text-center">案件数</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $cl): ?>
        <tr>
          <td class="fw-semibold"><?= h($cl['name']) ?></td>
          <td class="text-muted small"><?= h($cl['name_kana'] ?? '') ?></td>
          <td><span class="badge bg-light text-dark"><?= h($cl['gender'] ?? '') ?></span></td>
          <td><?= h($cl['phone'] ?? '') ?></td>
          <td><small><?= h($cl['email'] ?? '') ?></small></td>
          <td><small><?= h(mb_strimwidth($cl['address'] ?? '', 0, 22, '…')) ?></small></td>
          <td class="text-center">
            <span class="badge bg-primary"><?= $cl['case_count'] ?></span>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="client_detail.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-primary">詳細</a>
              <a href="client_form.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-secondary">編集</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($clients)): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">依頼人が見つかりません</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
