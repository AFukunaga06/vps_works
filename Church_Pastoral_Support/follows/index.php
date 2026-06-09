<?php
require_once dirname(__DIR__).'/auth.php';
require_login();

$person_id_filter = (int)($_GET['person_id'] ?? 0);
$persons = $pdo->query("SELECT id,last_name,first_name FROM persons WHERE status NOT IN ('ended') ORDER BY last_name")->fetchAll();
$users = $pdo->query("SELECT id,display_name FROM users WHERE is_active=1")->fetchAll();

$where = $person_id_filter ? "WHERE f.person_id=$person_id_filter" : "WHERE 1=1";
if (!can_view_confidential()) $where .= " AND f.private_flag=0";
$follows = $pdo->query("SELECT f.*, p.last_name, p.first_name, u.display_name as recorder FROM follow_histories f JOIN persons p ON f.person_id=p.id LEFT JOIN users u ON f.recorded_by=u.id $where ORDER BY f.follow_date DESC LIMIT 50")->fetchAll();

include dirname(__DIR__).'/header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h4 class="mb-0"><i class="bi bi-telephone-forward"></i> フォロー履歴</h4>
  <a href="<?= BASE_URL ?>/follows/add.php<?= $person_id_filter?'?person_id='.$person_id_filter:'' ?>" class="btn btn-primary btn-sm">追加</a>
</div>

<?php if($person_id_filter): ?>
<?php $fp=$pdo->prepare("SELECT last_name,first_name FROM persons WHERE id=?");$fp->execute([$person_id_filter]);$fp=$fp->fetch(); ?>
<p class="text-muted small">対象: <strong><?= h($fp['last_name'].$fp['first_name']) ?></strong> <a href="<?= BASE_URL ?>/follows/" class="ms-2">全件表示</a></p>
<?php endif; ?>

<div class="card">
<div class="card-body p-0">
<table class="table table-hover mb-0">
  <thead><tr><th>日付</th><th>対象者</th><th>種別</th><th>内容</th><th>次回</th><th>記録者</th></tr></thead>
  <tbody>
  <?php if(empty($follows)): ?><tr><td colspan="6" class="text-center text-muted py-4">記録なし</td></tr><?php endif; ?>
  <?php foreach($follows as $f): ?>
  <tr>
    <td class="small"><?= h($f['follow_date']) ?></td>
    <td><a href="<?= BASE_URL ?>/persons/view.php?id=<?= $f['person_id'] ?>" class="small"><?= h($f['last_name'].$f['first_name']) ?></a></td>
    <td class="small"><?= follow_type_label($f['follow_type']) ?><?= $f['private_flag']?' <i class="bi bi-lock-fill text-danger small" title="非公開"></i>':'' ?></td>
    <td class="small"><?= h(mb_strimwidth($f['content']??'',0,50,'…')) ?></td>
    <td class="small"><?= $f['next_plan_date']?h($f['next_plan_date']):'-' ?></td>
    <td class="small"><?= h($f['recorder']??'-') ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>
<?php include dirname(__DIR__).'/footer.php'; ?>
