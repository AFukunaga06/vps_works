<?php
require_once __DIR__ . '/_layout.php';
$user = require_admin();

$status = $_GET['status'] ?? '';
$where = []; $args = [];
if (in_array($status, ['sent','failed'], true)) { $where[]='status=?'; $args[]=$status; }
$sql = 'SELECT * FROM mail_logs';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

admin_header('メールログ', $user);
?>
<h2 class="mb-3">メールログ</h2>
<form class="row g-2 mb-3" method="get">
  <div class="col-auto">
    <select name="status" class="form-select form-select-sm">
      <option value="">全て</option>
      <option value="sent" <?= $status==='sent'?'selected':'' ?>>送信</option>
      <option value="failed" <?= $status==='failed'?'selected':'' ?>>失敗</option>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-sm btn-primary">絞り込み</button></div>
</form>
<div class="bg-white p-2 rounded shadow-sm">
<table class="table table-sm compact mb-0">
  <thead><tr><th>#</th><th>日時</th><th>関連</th><th>宛先</th><th>件名</th><th>状態</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)$r['id'] ?></td>
      <td class="text-muted small"><?= h($r['created_at']) ?></td>
      <td class="text-muted small"><?= h($r['related_type']) ?><?= $r['related_id']?' #'.(int)$r['related_id']:'' ?></td>
      <td><?= h($r['to_email']) ?></td>
      <td><?= h($r['subject']) ?></td>
      <td><?= badge_status('mail', $r['status']) ?></td>
      <td>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#log-<?= (int)$r['id'] ?>">詳細</button>
      </td>
    </tr>
    <tr class="collapse" id="log-<?= (int)$r['id'] ?>">
      <td colspan="7" class="bg-light">
        <?php if ($r['error_message']): ?>
          <div class="text-danger small mb-2"><strong>error:</strong> <?= h($r['error_message']) ?></div>
        <?php endif; ?>
        <pre class="mb-0 small" style="white-space:pre-wrap"><?= h($r['body']) ?></pre>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <tr><td colspan="7" class="text-center text-muted py-4">ログがありません</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<?php admin_footer();
