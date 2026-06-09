<?php
require_once dirname(__DIR__).'/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$person_id = (int)($_GET['person_id'] ?? 0);
$task = null;
if ($id) {
    $task = $pdo->prepare("SELECT * FROM tasks WHERE id=?");
    $task->execute([$id]);
    $task = $task->fetch();
}

$persons = $pdo->query("SELECT id,last_name,first_name FROM persons WHERE status NOT IN ('ended') ORDER BY last_name")->fetchAll();
$users = $pdo->query("SELECT id,display_name FROM users WHERE is_active=1 ORDER BY display_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $fields = [
        'title'=>trim($_POST['title']??''),
        'description'=>$_POST['description']??null,
        'due_date'=>($_POST['due_date']??'')?:null,
        'status'=>$_POST['status']??'pending',
        'person_id'=>($_POST['person_id']??'')?:null,
        'assigned_user_id'=>($_POST['assigned_user_id']??'')?:null,
    ];
    if (empty($fields['title'])) { flash('タイトルは必須です。','error'); }
    else {
        if ($id) {
            $set = implode(',', array_map(fn($k)=>"$k=?", array_keys($fields)));
            $vals = array_values($fields); $vals[] = $id;
            $pdo->prepare("UPDATE tasks SET $set WHERE id=?")->execute($vals);
            flash('更新しました。');
        } else {
            $fields['created_by'] = $_SESSION['user_id'];
            $cols = implode(',', array_keys($fields));
            $phs = implode(',', array_fill(0,count($fields),'?'));
            $pdo->prepare("INSERT INTO tasks ($cols) VALUES ($phs)")->execute(array_values($fields));
            flash('登録しました。');
        }
        $redirect = $fields['person_id'] ? BASE_URL.'/persons/view.php?id='.$fields['person_id'] : BASE_URL.'/tasks/';
        header('Location: '.$redirect);
        exit;
    }
}

$t = $task ?? ['person_id'=>$person_id,'assigned_user_id'=>$_SESSION['user_id']];
include dirname(__DIR__).'/header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h4><i class="bi bi-check2-square"></i> タスク<?= $id?'編集':'追加' ?></h4>
  <a href="<?= BASE_URL ?>/tasks/" class="btn btn-outline-secondary btn-sm">一覧</a>
</div>
<div class="card" style="max-width:600px">
<div class="card-body">
<form method="post">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
<div class="mb-2">
  <label class="form-label small fw-bold">タイトル <span class="text-danger">*</span></label>
  <input type="text" name="title" class="form-control form-control-sm" value="<?= h($t['title']??'') ?>" required>
</div>
<div class="mb-2">
  <label class="form-label small">詳細</label>
  <textarea name="description" class="form-control form-control-sm" rows="3"><?= h($t['description']??'') ?></textarea>
</div>
<div class="row g-2 mb-2">
  <div class="col">
    <label class="form-label small fw-bold">期限日</label>
    <input type="date" name="due_date" class="form-control form-control-sm" value="<?= h($t['due_date']??'') ?>">
  </div>
  <div class="col">
    <label class="form-label small fw-bold">状況</label>
    <select name="status" class="form-select form-select-sm">
      <option value="pending" <?= ($t['status']??'')==='pending'?'selected':'' ?>>未着手</option>
      <option value="in_progress" <?= ($t['status']??'')==='in_progress'?'selected':'' ?>>対応中</option>
      <option value="completed" <?= ($t['status']??'')==='completed'?'selected':'' ?>>完了</option>
      <option value="cancelled" <?= ($t['status']??'')==='cancelled'?'selected':'' ?>>中止</option>
    </select>
  </div>
</div>
<div class="row g-2 mb-3">
  <div class="col">
    <label class="form-label small">対象者</label>
    <select name="person_id" class="form-select form-select-sm">
      <option value="">なし</option>
      <?php foreach($persons as $p): ?>
      <option value="<?= $p['id'] ?>" <?= ($t['person_id']??'')==$p['id']?'selected':'' ?>><?= h($p['last_name'].$p['first_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col">
    <label class="form-label small">担当者</label>
    <select name="assigned_user_id" class="form-select form-select-sm">
      <option value="">未割当</option>
      <?php foreach($users as $u): ?>
      <option value="<?= $u['id'] ?>" <?= ($t['assigned_user_id']??'')==$u['id']?'selected':'' ?>><?= h($u['display_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>
<button type="submit" class="btn btn-primary"><?= $id?'更新':'登録' ?></button>
<a href="<?= BASE_URL ?>/tasks/" class="btn btn-outline-secondary ms-2">キャンセル</a>
</form>
</div>
</div>
<?php include dirname(__DIR__).'/footer.php'; ?>
