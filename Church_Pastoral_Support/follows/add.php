<?php
require_once dirname(__DIR__).'/auth.php';
require_login();

$person_id = (int)($_GET['person_id'] ?? 0);
$persons = $pdo->query("SELECT id,last_name,first_name FROM persons WHERE status NOT IN ('ended') ORDER BY last_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $pid = (int)$_POST['person_id'];
    if ($pid) {
        $pdo->prepare("INSERT INTO follow_histories (person_id,follow_type,follow_date,content,next_plan,next_plan_date,private_flag,recorded_by) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$pid,$_POST['follow_type']??'other',$_POST['follow_date']??date('Y-m-d'),$_POST['content']??null,$_POST['next_plan']??null,($_POST['next_plan_date']??'')?:null,isset($_POST['private_flag'])?1:0,$_SESSION['user_id']]);
        flash('記録しました。');
        header('Location: '.BASE_URL.'/persons/view.php?id='.$pid);
        exit;
    }
}

include dirname(__DIR__).'/header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h4><i class="bi bi-telephone-forward"></i> フォロー記録を追加</h4>
  <a href="<?= BASE_URL ?>/follows/" class="btn btn-outline-secondary btn-sm">一覧</a>
</div>
<div class="card" style="max-width:600px">
<div class="card-body">
<form method="post">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
<div class="mb-2">
  <label class="form-label small fw-bold">対象者</label>
  <select name="person_id" class="form-select form-select-sm" required>
    <option value="">選択</option>
    <?php foreach($persons as $p): ?>
    <option value="<?= $p['id'] ?>" <?= $person_id===$p['id']?'selected':'' ?>><?= h($p['last_name'].$p['first_name']) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div class="row g-2 mb-2">
  <div class="col">
    <label class="form-label small fw-bold">種別</label>
    <select name="follow_type" class="form-select form-select-sm">
      <?php foreach(['phone'=>'電話','meeting'=>'面談','visit'=>'訪問','email'=>'メール','letter'=>'手紙','other'=>'その他'] as $v=>$l): ?>
      <option value="<?= $v ?>"><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col">
    <label class="form-label small fw-bold">日付</label>
    <input type="date" name="follow_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
  </div>
</div>
<div class="mb-2">
  <label class="form-label small fw-bold">内容</label>
  <textarea name="content" class="form-control form-control-sm" rows="4"></textarea>
</div>
<div class="mb-2">
  <label class="form-label small">次回フォロー予定</label>
  <input type="text" name="next_plan" class="form-control form-control-sm" placeholder="例：次の礼拝で声かけ">
</div>
<div class="mb-3">
  <label class="form-label small">次回予定日</label>
  <input type="date" name="next_plan_date" class="form-control form-control-sm">
</div>
<?php if(can_view_confidential()): ?>
<div class="form-check mb-3">
  <input class="form-check-input" type="checkbox" name="private_flag" id="priv">
  <label class="form-check-label small" for="priv"><i class="bi bi-lock"></i> 非公開（管理者・牧師のみ閲覧）</label>
</div>
<?php endif; ?>
<button type="submit" class="btn btn-primary">記録する</button>
<a href="<?= BASE_URL ?>/follows/" class="btn btn-outline-secondary ms-2">キャンセル</a>
</form>
</div>
</div>
<?php include dirname(__DIR__).'/footer.php'; ?>
