<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$staff      = crm_require_admin();
$page_title = 'スタッフ管理';
$all        = get_all_staff();

include __DIR__ . '/../includes/_header.php';
?>

<div class="d-flex justify-content-end mb-3">
  <a href="staff_edit.php" class="btn btn-sm text-white" style="background:#3a7d5c">＋ スタッフ追加</a>
</div>

<table class="table table-hover table-sm bg-white shadow-sm rounded">
  <thead class="table-light">
    <tr><th>氏名</th><th>メール</th><th>権限</th><th>状態</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($all as $s): ?>
  <tr>
    <td><?= h($s['name']) ?></td>
    <td class="small"><?= h($s['email']) ?></td>
    <td><?= $s['role'] === 'admin' ? '<span class="badge bg-danger">管理者</span>' : '<span class="badge bg-secondary">スタッフ</span>' ?></td>
    <td><?= $s['is_active'] ? '<span class="badge bg-success">有効</span>' : '<span class="badge bg-secondary">無効</span>' ?></td>
    <td><a href="staff_edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary py-0">編集</a></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php include __DIR__ . '/../includes/_footer.php'; ?>
