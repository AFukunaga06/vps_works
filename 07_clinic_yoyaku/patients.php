<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();

$pdo = getPDO();
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE last_name LIKE ? OR last_name_kana LIKE ?
                 OR patient_no LIKE ? OR phone LIKE ?';
    $like = "%{$search}%";
    $params = array_fill(0, 6, $like);
}

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM patients $where")->execute($params) ?
    $pdo->prepare("SELECT COUNT(*) FROM patients $where") : 0;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM patients $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = (int)ceil($total / $limit);

$stmt = $pdo->prepare(
    "SELECT * FROM patients $where ORDER BY last_name_kana LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$patients = $stmt->fetchAll();

pageHead('患者台帳');
navbar();
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-people"></i> 患者台帳</h5>
    <a href="patient_new.php" class="btn btn-primary btn-sm">
      <i class="bi bi-person-plus"></i> 新規登録
    </a>
  </div>

  <form class="row g-2 mb-3" method="get">
    <div class="col-sm-8 col-md-5">
      <input type="text" name="q" class="form-control form-control-sm"
        placeholder="氏名・カナ・患者番号・電話で検索"
        value="<?= h($search) ?>">
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-sm btn-secondary">
        <i class="bi bi-search"></i> 検索
      </button>
      <?php if ($search): ?>
        <a href="patients.php" class="btn btn-sm btn-outline-secondary">クリア</a>
      <?php endif; ?>
    </div>
  </form>

  <p class="text-muted small">全 <?= $total ?> 件 / <?= $page ?> / <?= max(1,$pages) ?> ページ</p>

  <div class="table-responsive">
    <table class="table table-hover table-sm">
      <thead class="table-light">
        <tr>
          <th>患者番号</th><th>氏名（カナ）</th><th>生年月日</th><th>年齢</th>
          <th>性別</th><th>電話</th><th>血液型</th><th>アレルギー</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($patients as $p): ?>
        <tr>
          <td><?= h($p['patient_no']) ?></td>
          <td>
            <a href="patient_view.php?id=<?= (int)$p['id'] ?>">
              <?= h($p['last_name'] . '　' . $p['first_name']) ?>
            </a><br>
            <small class="text-muted"><?= h($p['last_name_kana'] . '　' . $p['first_name_kana']) ?></small>
          </td>
          <td><?= h(formatDate($p['birth_date'])) ?></td>
          <td><?= $p['birth_date'] ? age($p['birth_date']) : '' ?>歳</td>
          <td><?= h(genderLabel($p['gender'])) ?></td>
          <td><?= h($p['phone']) ?></td>
          <td><?= h($p['blood_type']) ?></td>
          <td class="text-truncate" style="max-width:120px"><?= h($p['allergies']) ?></td>
          <td>
            <a href="patient_view.php?id=<?= (int)$p['id'] ?>"
               class="btn btn-xs btn-outline-primary btn-sm">詳細</a>
            <a href="patient_edit.php?id=<?= (int)$p['id'] ?>"
               class="btn btn-xs btn-outline-secondary btn-sm">編集</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($patients)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">該当する患者が見つかりません</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <nav>
    <ul class="pagination pagination-sm">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="?q=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>
<?php pageFooter(); ?>
