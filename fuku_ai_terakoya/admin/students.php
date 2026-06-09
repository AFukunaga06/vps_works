<?php
require_once __DIR__ . '/_layout.php';
$user = require_admin();

$q = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT s.*, (SELECT COUNT(*) FROM reservations r WHERE r.student_id = s.id) AS res_count
        FROM students s";
$args = [];
if ($q !== '') { $sql .= ' WHERE s.name LIKE ? OR s.email LIKE ?'; $args[]="%{$q}%"; $args[]="%{$q}%"; }
$sql .= ' ORDER BY s.id DESC LIMIT 300';

$stmt = db()->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

admin_header('受講者一覧', $user);
?>
<h2 class="mb-3">受講者一覧</h2>
<form class="row g-2 mb-3" method="get">
  <div class="col-auto"><input class="form-control form-control-sm" name="q" value="<?= h($q) ?>" placeholder="名前/メール検索"></div>
  <div class="col-auto"><button class="btn btn-sm btn-primary">検索</button></div>
</form>
<div class="bg-white p-2 rounded shadow-sm">
<table class="table table-sm compact mb-0">
  <thead><tr><th>#</th><th>名前</th><th>メール</th><th>電話</th><th>予約数</th><th>登録日</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)$r['id'] ?></td>
      <td><?= h($r['name']) ?></td>
      <td><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a></td>
      <td><?= h($r['phone']) ?></td>
      <td><?= (int)$r['res_count'] ?></td>
      <td class="text-muted small"><?= h($r['created_at']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <tr><td colspan="6" class="text-center text-muted py-4">受講者がいません</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<?php admin_footer();
