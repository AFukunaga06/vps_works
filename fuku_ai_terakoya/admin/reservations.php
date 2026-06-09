<?php
require_once __DIR__ . '/_layout.php';
$user = require_admin();

$status = $_GET['status'] ?? '';
$q      = trim((string)($_GET['q'] ?? ''));

$where = [];
$args  = [];
if (in_array($status, ['pending','confirmed','done','cancelled'], true)) {
    $where[] = 'status = ?';
    $args[]  = $status;
}
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ?)';
    $args[]  = "%{$q}%";
    $args[]  = "%{$q}%";
}
$sql = 'SELECT * FROM reservations';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY reserve_date DESC, reserve_time DESC, id DESC LIMIT 300';

$stmt = db()->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

admin_header('予約一覧', $user);
?>
<h2 class="mb-3">予約一覧</h2>

<form class="row g-2 mb-3" method="get">
  <div class="col-auto">
    <select name="status" class="form-select form-select-sm">
      <option value="">全ての状態</option>
      <?php foreach (['pending'=>'未確定','confirmed'=>'確定','done'=>'完了','cancelled'=>'キャンセル'] as $k=>$v): ?>
        <option value="<?= h($k) ?>" <?= $status===$k?'selected':'' ?>><?= h($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <input class="form-control form-control-sm" name="q" value="<?= h($q) ?>" placeholder="名前/メールで検索">
  </div>
  <div class="col-auto"><button class="btn btn-sm btn-primary">絞り込み</button></div>
</form>

<div class="bg-white p-2 rounded shadow-sm">
<table class="table table-sm compact mb-0">
  <thead>
    <tr><th>#</th><th>日付</th><th>時間</th><th>種別</th><th>名前</th><th>メール</th><th>状態</th><th>作成</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)$r['id'] ?></td>
      <td><?= h($r['reserve_date']) ?></td>
      <td><?= h($r['reserve_time']) ?></td>
      <td><?= h($r['type']==='session' ? '本講座' : 'オリエン') ?></td>
      <td><?= h($r['name']) ?></td>
      <td><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a></td>
      <td><?= badge_status('reservation', $r['status']) ?></td>
      <td class="text-muted small"><?= h($r['created_at']) ?></td>
      <td><a class="btn btn-outline-secondary btn-sm" href="reservation_edit.php?id=<?= (int)$r['id'] ?>">編集</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <tr><td colspan="9" class="text-center text-muted py-4">該当データがありません</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<?php admin_footer();
