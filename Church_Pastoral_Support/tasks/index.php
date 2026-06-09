<?php
require_once dirname(__DIR__).'/auth.php';
require_login();

// Handle status update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['complete_task'])) {
    verify_csrf();
    $pdo->prepare("UPDATE tasks SET status='completed' WHERE id=?")->execute([$_POST['task_id']]);
    flash('完了にしました。');
    header('Location: '.BASE_URL.'/tasks/');
    exit;
}

$status_filter = $_GET['status'] ?? 'active';
$today = date('Y-m-d');

if ($status_filter === 'active') {
    $where = "WHERE t.status IN ('pending','in_progress')";
} elseif ($status_filter === 'completed') {
    $where = "WHERE t.status='completed'";
} else {
    $where = "WHERE 1=1";
}

// Officers/reception see only their tasks
if (in_array($_SESSION['user_role']??'', ['officer','reception'])) {
    $where .= " AND t.assigned_user_id=".(int)$_SESSION['user_id'];
}

$tasks = $pdo->query("SELECT t.*, p.last_name, p.first_name, u.display_name as assigned_name FROM tasks t LEFT JOIN persons p ON t.person_id=p.id LEFT JOIN users u ON t.assigned_user_id=u.id $where ORDER BY t.due_date ASC, t.created_at DESC LIMIT 100")->fetchAll();

include dirname(__DIR__).'/header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h4 class="mb-0"><i class="bi bi-check2-square"></i> タスク管理</h4>
  <a href="<?= BASE_URL ?>/tasks/form.php" class="btn btn-primary btn-sm">追加</a>
</div>

<div class="mb-3">
  <a href="?status=active" class="btn btn-sm <?= $status_filter==='active'?'btn-primary':'btn-outline-secondary' ?> me-1">未完了</a>
  <a href="?status=completed" class="btn btn-sm <?= $status_filter==='completed'?'btn-primary':'btn-outline-secondary' ?> me-1">完了済</a>
  <a href="?status=all" class="btn btn-sm <?= $status_filter==='all'?'btn-primary':'btn-outline-secondary' ?>">全件</a>
</div>

<div class="card">
<div class="card-body p-0">
<table class="table table-hover mb-0">
  <thead><tr><th>期限</th><th>タイトル</th><th>対象者</th><th>担当者</th><th>状況</th><th></th></tr></thead>
  <tbody>
  <?php if(empty($tasks)): ?><tr><td colspan="6" class="text-center text-muted py-4">タスクなし</td></tr><?php endif; ?>
  <?php foreach($tasks as $t): ?>
  <tr>
    <td class="small <?= ($t['due_date']&&$t['due_date']<$today&&$t['status']!=='completed')?'text-danger fw-bold':'' ?>"><?= h($t['due_date']??'-') ?></td>
    <td><a href="<?= BASE_URL ?>/tasks/form.php?id=<?= $t['id'] ?>" class="small"><?= h($t['title']) ?></a>
      <?php if($t['description']): ?><div class="text-muted" style="font-size:11px"><?= h(mb_strimwidth($t['description'],0,40,'…')) ?></div><?php endif; ?>
    </td>
    <td class="small"><?= $t['person_id']?'<a href="'.BASE_URL.'/persons/view.php?id='.$t['person_id'].'">'.h($t['last_name'].$t['first_name']).'</a>':'-' ?></td>
    <td class="small"><?= h($t['assigned_name']??'-') ?></td>
    <td>
      <span class="badge <?= ['pending'=>'bg-warning text-dark','in_progress'=>'bg-primary','completed'=>'bg-success','cancelled'=>'bg-secondary'][$t['status']]??'bg-secondary' ?>">
        <?= ['pending'=>'未着手','in_progress'=>'対応中','completed'=>'完了','cancelled'=>'中止'][$t['status']]??$t['status'] ?>
      </span>
    </td>
    <td>
      <?php if($t['status']!=='completed'&&$t['status']!=='cancelled'): ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
        <button name="complete_task" class="btn btn-outline-success btn-sm py-0" title="完了" onclick="return confirm('完了にしますか？')"><i class="bi bi-check2"></i></button>
      </form>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/tasks/form.php?id=<?= $t['id'] ?>" class="btn btn-outline-secondary btn-sm py-0"><i class="bi bi-pencil"></i></a>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>
<?php include dirname(__DIR__).'/footer.php'; ?>
