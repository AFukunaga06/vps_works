<?php
require_once __DIR__ . '/_layout.php';
$user = require_admin();

$status = $_GET['status'] ?? '';
$q      = trim((string)($_GET['q'] ?? ''));

$where = []; $args = [];
if (in_array($status, ['new','reviewing','accepted','rejected'], true)) { $where[]='status = ?'; $args[]=$status; }
if ($q !== '') { $where[]='(name LIKE ? OR email LIKE ?)'; $args[]="%{$q}%"; $args[]="%{$q}%"; }
$sql = 'SELECT * FROM instructor_applications';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id DESC LIMIT 300';

$stmt = db()->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

admin_header('講師応募一覧', $user);
?>
<h2 class="mb-3">講師応募一覧</h2>

<form class="row g-2 mb-3" method="get">
  <div class="col-auto">
    <select name="status" class="form-select form-select-sm">
      <option value="">全ての状態</option>
      <?php foreach (['new'=>'新規','reviewing'=>'検討中','accepted'=>'採用','rejected'=>'不採用'] as $k=>$v): ?>
        <option value="<?= h($k) ?>" <?= $status===$k?'selected':'' ?>><?= h($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><input class="form-control form-control-sm" name="q" value="<?= h($q) ?>" placeholder="名前/メール検索"></div>
  <div class="col-auto"><button class="btn btn-sm btn-primary">絞り込み</button></div>
</form>

<div class="bg-white p-2 rounded shadow-sm">
<table class="table table-sm compact mb-0">
  <thead><tr><th>#</th><th>名前</th><th>メール</th><th>電話</th><th>対応曜日</th><th>状態</th><th>応募日</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)$r['id'] ?></td>
      <td><?= h($r['name']) ?></td>
      <td><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a></td>
      <td><?= h($r['phone']) ?></td>
      <td><?= h($r['available_days']) ?></td>
      <td><?= badge_status('application', $r['status']) ?></td>
      <td class="text-muted small"><?= h($r['created_at']) ?></td>
      <td><a class="btn btn-outline-secondary btn-sm" href="instructor_application_edit.php?id=<?= (int)$r['id'] ?>">編集</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <tr><td colspan="8" class="text-center text-muted py-4">該当データがありません</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<?php admin_footer();
