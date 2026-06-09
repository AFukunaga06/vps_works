<?php
require_once dirname(__DIR__).'/auth.php';
require_login();

$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 30;
$offset = ($page-1)*$per_page;

$where = ['1=1'];
$params = [];
if ($status_filter) { $where[] = 'p.status=?'; $params[] = $status_filter; }
if ($search) {
    $where[] = '(p.last_name LIKE ? OR p.first_name LIKE ? OR p.last_name_kana LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)';
    $s = "%$search%";
    array_push($params,$s,$s,$s,$s,$s);
}
// Officers see only their assigned persons
if (($_SESSION['user_role']??'') === 'officer') {
    $where[] = 'p.assigned_user_id=?';
    $params[] = $_SESSION['user_id'];
}

$w = implode(' AND ', $where);
$total = $pdo->prepare("SELECT COUNT(*) FROM persons p WHERE $w");
$total->execute($params);
$total = $total->fetchColumn();
$pages = ceil($total/$per_page);

$stmt = $pdo->prepare("SELECT p.*, u.display_name as assigned_name FROM persons p LEFT JOIN users u ON p.assigned_user_id=u.id WHERE $w ORDER BY p.updated_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$persons = $stmt->fetchAll();

include dirname(__DIR__).'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-people"></i> 人物一覧 <small class="text-muted fs-6">(<?= $total ?>件)</small></h4>
  <?php if(can_edit_persons()): ?>
  <a href="<?= BASE_URL ?>/persons/form.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> 新規登録</a>
  <?php endif; ?>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-auto">
    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">全ステータス</option>
      <?php foreach(['inquiry'=>'問い合わせ者','first_visit'=>'初来会者','regular'=>'継続来会者','seeker'=>'求道者','baptism_prep'=>'洗礼準備中','member'=>'会員','inactive'=>'休会中','ended'=>'終了'] as $v=>$l): ?>
      <option value="<?= $v ?>" <?= $status_filter===$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <input type="text" name="q" class="form-control form-control-sm" placeholder="氏名・電話・メール検索" value="<?= h($search) ?>">
  </div>
  <div class="col-auto"><button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button></div>
  <?php if($search||$status_filter): ?>
  <div class="col-auto"><a href="<?= BASE_URL ?>/persons/" class="btn btn-outline-danger btn-sm">クリア</a></div>
  <?php endif; ?>
</form>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr>
        <th>氏名</th><th>ステータス</th><th>来会日</th><th>最終出席</th>
        <th>電話</th><th>担当者</th><th>フラグ</th><th></th>
      </tr></thead>
      <tbody>
      <?php if(empty($persons)): ?>
      <tr><td colspan="8" class="text-center text-muted py-4">該当する人物がいません</td></tr>
      <?php endif; ?>
      <?php foreach($persons as $p): ?>
      <tr>
        <td>
          <a href="<?= BASE_URL ?>/persons/view.php?id=<?= $p['id'] ?>">
            <?= h($p['last_name'].$p['first_name']) ?>
          </a>
          <?php if($p['last_name_kana']): ?>
          <div class="text-muted" style="font-size:11px"><?= h($p['last_name_kana'].$p['first_name_kana']) ?></div>
          <?php endif; ?>
        </td>
        <td><?= status_badge($p['status']) ?></td>
        <td class="small"><?= h($p['first_visit_date']) ?></td>
        <td class="small <?= ($p['last_attend_date'] && $p['last_attend_date'] < date('Y-m-d',strtotime('-2 months'))) ? 'text-danger' : '' ?>">
          <?= h($p['last_attend_date'] ?? '-') ?>
        </td>
        <td class="small"><?= h($p['phone']) ?></td>
        <td class="small"><?= h($p['assigned_name'] ?? '-') ?></td>
        <td><?php if($p['alert_flag']): ?><span class="text-danger" title="<?= h($p['alert_note']) ?>"><i class="bi bi-exclamation-circle-fill"></i></span><?php endif; ?></td>
        <td>
          <a href="<?= BASE_URL ?>/persons/view.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm py-0"><i class="bi bi-eye"></i></a>
          <?php if(can_edit_persons()): ?>
          <a href="<?= BASE_URL ?>/persons/form.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary btn-sm py-0"><i class="bi bi-pencil"></i></a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if($pages>1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm">
  <?php for($i=1;$i<=$pages;$i++): ?>
  <li class="page-item <?= $i===$page?'active':'' ?>">
    <a class="page-link" href="?page=<?= $i ?>&status=<?= h($status_filter) ?>&q=<?= h($search) ?>"><?= $i ?></a>
  </li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php include dirname(__DIR__).'/footer.php'; ?>
