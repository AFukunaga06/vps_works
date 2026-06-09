<?php
require_once dirname(__DIR__).'/auth.php';
require_login();

// Handle quick status update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status'])) {
    verify_csrf();
    $pdo->prepare("UPDATE inquiries SET status=?,response=? WHERE id=?")
        ->execute([$_POST['status'],$_POST['response']??null,$_POST['inq_id']]);
    flash('更新しました。');
    header('Location: '.BASE_URL.'/inquiries/');
    exit;
}

$status_filter = $_GET['status'] ?? 'pending';
$where = $status_filter ? "WHERE i.status=?" : "WHERE 1=1";
$params = $status_filter ? [$status_filter] : [];

$inquiries = $pdo->prepare("SELECT i.*, p.last_name, p.first_name, u.display_name as assigned_name
    FROM inquiries i
    LEFT JOIN persons p ON i.person_id=p.id
    LEFT JOIN users u ON i.assigned_user_id=u.id
    $where ORDER BY i.inquiry_date DESC LIMIT 100");
$inquiries->execute($params);
$inquiries = $inquiries->fetchAll();

include dirname(__DIR__).'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-chat-dots"></i> 問い合わせ管理</h4>
  <a href="<?= BASE_URL ?>/inquiries/form.php" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> 新規登録</a>
</div>

<div class="mb-3">
  <?php foreach([''=>'全件','pending'=>'未対応','in_progress'=>'対応中','resolved'=>'解決済'] as $v=>$l): ?>
  <a href="?status=<?= $v ?>" class="btn btn-sm <?= $status_filter===$v?'btn-primary':'btn-outline-secondary' ?> me-1"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>日付</th><th>種別</th><th>問い合わせ者</th><th>内容</th><th>状況</th><th>担当</th><th></th></tr></thead>
      <tbody>
      <?php if(empty($inquiries)): ?>
      <tr><td colspan="7" class="text-center text-muted py-4">該当なし</td></tr>
      <?php endif; ?>
      <?php foreach($inquiries as $i): ?>
      <tr>
        <td class="small"><?= h($i['inquiry_date']) ?></td>
        <td class="small"><?= inquiry_type_label($i['inquiry_type']) ?></td>
        <td class="small">
          <?php if($i['person_id']): ?>
          <a href="<?= BASE_URL ?>/persons/view.php?id=<?= $i['person_id'] ?>"><?= h($i['last_name'].$i['first_name']) ?></a>
          <?php else: ?>
          <?= h($i['inquirer_name']??'不明') ?>
          <?php endif; ?>
        </td>
        <td class="small"><?= h(mb_strimwidth($i['content']??'',0,40,'…')) ?></td>
        <td>
          <span class="badge <?= ['pending'=>'bg-warning text-dark','in_progress'=>'bg-primary','resolved'=>'bg-success'][$i['status']]??'bg-secondary' ?>">
            <?= ['pending'=>'未対応','in_progress'=>'対応中','resolved'=>'解決済'][$i['status']]??$i['status'] ?>
          </span>
        </td>
        <td class="small"><?= h($i['assigned_name']??'-') ?></td>
        <td>
          <a href="<?= BASE_URL ?>/inquiries/form.php?id=<?= $i['id'] ?>" class="btn btn-outline-secondary btn-sm py-0"><i class="bi bi-pencil"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include dirname(__DIR__).'/footer.php'; ?>
