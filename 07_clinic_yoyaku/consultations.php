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

$where  = [];
$params = [];
if ($search !== '') {
    $like = "%{$search}%";
    $where[]  = '(p.last_name LIKE ? OR c.assessment LIKE ?)';
    $params = array_merge($params, [$like, $like]);
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM consultations c
     JOIN patients p ON p.id = c.patient_id
     JOIN doctors  d ON d.id = c.doctor_id
     JOIN appointments a ON a.id = c.appointment_id
     $whereStr"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = (int)ceil($total / $limit);

$stmt = $pdo->prepare(
    "SELECT c.*, a.appt_date,
            CONCAT(p.last_name," ",p.first_name) AS patient_name, p.id AS patient_id,
            d.name AS doctor_name
     FROM consultations c
     JOIN patients p ON p.id = c.patient_id
     JOIN doctors  d ON d.id = c.doctor_id
     JOIN appointments a ON a.id = c.appointment_id
     $whereStr
     ORDER BY a.appt_date DESC
     LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$list = $stmt->fetchAll();

pageHead('診察履歴');
navbar();
?>
<div class="container-fluid">
  <h5 class="mb-3"><i class="bi bi-clipboard2-pulse"></i> 診察履歴</h5>

  <form class="row g-2 mb-3" method="get">
    <div class="col-sm-7 col-md-4">
      <input type="text" name="q" class="form-control form-control-sm"
        placeholder="患者名・診断名で検索" value="<?= h($search) ?>">
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-sm btn-secondary">
        <i class="bi bi-search"></i> 検索
      </button>
      <?php if ($search): ?>
        <a href="consultations.php" class="btn btn-sm btn-outline-secondary">クリア</a>
      <?php endif; ?>
    </div>
  </form>

  <p class="text-muted small">全 <?= $total ?> 件</p>

  <div class="table-responsive">
    <table class="table table-hover table-sm">
      <thead class="table-light">
        <tr>
          <th>診察日</th><th>患者名</th><th>担当医</th><th>診断</th>
          <th>体温</th><th>血圧</th><th>処方</th><th>次回</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $c): ?>
        <tr>
          <td><?= h(formatDate($c['appt_date'])) ?></td>
          <td>
            <a href="patient_view.php?id=<?= (int)$c['patient_id'] ?>">
              <?= h($c['patient_name']) ?>
            </a>
          </td>
          <td><?= h($c['doctor_name']) ?></td>
          <td class="text-truncate" style="max-width:140px"><?= h($c['assessment']) ?></td>
          <td><?= $c['temperature'] ? h($c['temperature']) . '℃' : '' ?></td>
          <td class="text-nowrap">
            <?= $c['bp_sys'] ? h($c['bp_sys']) . '/' . h($c['bp_dia']) : '' ?>
          </td>
          <td class="text-truncate" style="max-width:120px"><?= h($c['prescription']) ?></td>
          <td>
            <?php if ($c['next_visit_days']): ?>
              <span class="badge bg-info text-dark"><?= h($c['next_visit_days']) ?>日後</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="appointment_view.php?id=<?= (int)$c['appointment_id'] ?>"
               class="btn btn-sm btn-outline-secondary">詳細</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($list)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">該当する診察記録が見つかりません</td></tr>
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
