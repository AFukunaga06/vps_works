<?php
require_once 'config.php';
require_login();
$page_title = '依頼人台帳';

$db = get_db();
$q  = trim($_GET['q'] ?? '');

if ($q) {
    $st = $db->prepare(
        "SELECT c.*, (SELECT COUNT(*) FROM cases WHERE client_id=c.id) AS case_count
         FROM clients c
         WHERE c.name LIKE ? OR c.name_kana LIKE ? OR c.phone LIKE ? OR c.email LIKE ?
         ORDER BY c.id DESC"
    );
    $like = '%' . $q . '%';
    $st->execute([$like, $like, $like, $like]);
} else {
    $st = $db->query(
        "SELECT c.*, (SELECT COUNT(*) FROM cases WHERE client_id=c.id) AS case_count
         FROM clients c ORDER BY c.id DESC"
    );
}
$clients = $st->fetchAll();

require 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <form class="d-flex gap-2" method="get" style="max-width:400px;width:100%">
    <div class="input-group">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input type="text" name="q" class="form-control" placeholder="氏名・かな・電話・メールで検索"
             value="<?= h($q) ?>">
    </div>
    <button class="btn btn-outline-secondary" type="submit">検索</button>
    <?php if ($q): ?><a href="clients.php" class="btn btn-outline-danger">クリア</a><?php endif; ?>
  </form>
  <a href="client_form.php" class="btn btn-primary">
    <i class="bi bi-person-plus-fill me-1"></i>新規依頼人登録
  </a>
</div>

<div class="card">
  <div class="card-header bg-white py-3">
    <span class="fw-semibold">依頼人一覧</span>
    <span class="badge bg-secondary ms-2"><?= count($clients) ?>件</span>
    <?php if ($q): ?>
    <span class="badge bg-info ms-1">「<?= h($q) ?>」で絞り込み中</span>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>ID</th>
          <th>氏名</th>
          <th>ふりがな</th>
          <th>性別</th>
          <th>電話番号</th>
          <th>メールアドレス</th>
          <th>案件数</th>
          <th>登録日</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($clients): ?>
        <?php foreach ($clients as $c): ?>
        <tr>
          <td class="text-muted small"><?= $c['id'] ?></td>
          <td><a href="client_detail.php?id=<?= $c['id'] ?>" class="fw-semibold text-decoration-none"><?= h($c['name']) ?></a></td>
          <td class="text-muted small"><?= h($c['name_kana'] ?? '—') ?></td>
          <td><?= h($c['gender'] ?? '—') ?></td>
          <td class="small"><?= h($c['phone'] ?? '—') ?></td>
          <td class="small"><?= h($c['email'] ?? '—') ?></td>
          <td>
            <?php if ($c['case_count'] > 0): ?>
            <a href="cases.php?client_id=<?= $c['id'] ?>" class="badge bg-primary text-decoration-none"><?= $c['case_count'] ?>件</a>
            <?php else: ?>
            <span class="text-muted">0件</span>
            <?php endif; ?>
          </td>
          <td class="small text-muted"><?= date('Y/m/d', strtotime($c['created_at'])) ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="client_detail.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="詳細"><i class="bi bi-eye"></i></a>
              <a href="client_form.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" title="編集"><i class="bi bi-pencil"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <tr><td colspan="9" class="text-center text-muted py-5">依頼人が登録されていません</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
