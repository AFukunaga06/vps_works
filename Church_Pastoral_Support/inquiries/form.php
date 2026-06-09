<?php
require_once dirname(__DIR__).'/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$inq = null;
if ($id) {
    $inq = $pdo->prepare("SELECT * FROM inquiries WHERE id=?");
    $inq->execute([$id]);
    $inq = $inq->fetch();
}

$persons = $pdo->query("SELECT id,last_name,first_name FROM persons WHERE status NOT IN ('ended') ORDER BY last_name,first_name")->fetchAll();
$users = $pdo->query("SELECT id,display_name FROM users WHERE is_active=1")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    if (isset($_POST['delete']) && $id) {
        $pdo->prepare("DELETE FROM inquiries WHERE id=?")->execute([$id]);
        flash('削除しました。');
        header('Location: '.BASE_URL.'/inquiries/');
        exit;
    }
    $fields = [
        'person_id'=>($_POST['person_id']??'')?:null,
        'inquirer_name'=>$_POST['inquirer_name']??null,
        'inquirer_kana'=>$_POST['inquirer_kana']??null,
        'inquirer_email'=>$_POST['inquirer_email']??null,
        'inquirer_tel'=>$_POST['inquirer_tel']??null,
        'preferred_time'=>$_POST['preferred_time']??null,
        'inquiry_type'=>$_POST['inquiry_type']??'other',
        'inquiry_date'=>$_POST['inquiry_date']??date('Y-m-d'),
        'content'=>$_POST['content']??null,
        'response'=>$_POST['response']??null,
        'status'=>$_POST['status']??'pending',
        'assigned_user_id'=>($_POST['assigned_user_id']??'')?:null,
    ];
    if ($id) {
        $set = implode(',', array_map(fn($k)=>"$k=?", array_keys($fields)));
        $vals = array_values($fields); $vals[] = $id;
        $pdo->prepare("UPDATE inquiries SET $set WHERE id=?")->execute($vals);
        flash('更新しました。');
    } else {
        $fields['created_by'] = $_SESSION['user_id'];
        $cols = implode(',', array_keys($fields));
        $phs = implode(',', array_fill(0,count($fields),'?'));
        $pdo->prepare("INSERT INTO inquiries ($cols) VALUES ($phs)")->execute(array_values($fields));
        flash('登録しました。');
    }
    header('Location: '.BASE_URL.'/inquiries/');
    exit;
}

$q = $inq ?? [];
include dirname(__DIR__).'/header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h4 class="mb-0"><i class="bi bi-chat-dots"></i> 問い合わせ<?= $id?'編集':'登録' ?></h4>
  <a href="<?= BASE_URL ?>/inquiries/" class="btn btn-outline-secondary btn-sm">一覧</a>
</div>
<div class="card" style="max-width:700px">
<div class="card-body">
<form method="post">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
<div class="row g-2 mb-2">
  <div class="col-md-6">
    <label class="form-label small fw-bold">対象者（登録済みの場合）</label>
    <select name="person_id" class="form-select form-select-sm">
      <option value="">選択してください</option>
      <?php foreach($persons as $p): ?>
      <option value="<?= $p['id'] ?>" <?= ($q['person_id']??'')==$p['id']?'selected':'' ?>><?= h($p['last_name'].$p['first_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label small">氏名（未登録の場合）</label>
    <input type="text" name="inquirer_name" class="form-control form-control-sm" value="<?= h($q['inquirer_name']??'') ?>" placeholder="例：田中 太郎">
  </div>
</div>
<div class="row g-2 mb-2">
  <div class="col-md-6 offset-md-6">
    <label class="form-label small">ふりがな</label>
    <input type="text" name="inquirer_kana" class="form-control form-control-sm" value="<?= h($q['inquirer_kana']??'') ?>" placeholder="例：たなか たろう">
  </div>
</div>
<div class="row g-2 mb-2">
  <div class="col-md-6">
    <label class="form-label small">メールアドレス</label>
    <input type="email" name="inquirer_email" class="form-control form-control-sm" value="<?= h($q['inquirer_email']??'') ?>" placeholder="例：example@mail.com">
  </div>
  <div class="col-md-3">
    <label class="form-label small">電話番号</label>
    <input type="text" name="inquirer_tel" class="form-control form-control-sm" value="<?= h($q['inquirer_tel']??'') ?>" placeholder="例：080-1234-5678">
  </div>
  <div class="col-md-3">
    <label class="form-label small">連絡希望時間帯</label>
    <select name="preferred_time" class="form-select form-select-sm">
      <option value="">-</option>
      <?php foreach(['午前中'=>'午前中 9:30〜12:00','午後'=>'午後 13:00〜17:00','夜'=>'夜 18:00〜20:00'] as $v=>$l): ?>
      <option value="<?= $v ?>" <?= ($q['preferred_time']??'')===$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>
<div class="row g-2 mb-2">
  <div class="col-md-4">
    <label class="form-label small fw-bold">種別</label>
    <select name="inquiry_type" class="form-select form-select-sm">
      <?php foreach(['phone'=>'電話','email'=>'メール','form'=>'フォーム','walk_in'=>'来訪','other'=>'その他'] as $v=>$l): ?>
      <option value="<?= $v ?>" <?= ($q['inquiry_type']??'other')===$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label small fw-bold">日付</label>
    <input type="date" name="inquiry_date" class="form-control form-control-sm" value="<?= h($q['inquiry_date']??date('Y-m-d')) ?>" required>
  </div>
  <div class="col-md-4">
    <label class="form-label small fw-bold">状況</label>
    <select name="status" class="form-select form-select-sm">
      <option value="pending" <?= ($q['status']??'')==='pending'?'selected':'' ?>>未対応</option>
      <option value="in_progress" <?= ($q['status']??'')==='in_progress'?'selected':'' ?>>対応中</option>
      <option value="resolved" <?= ($q['status']??'')==='resolved'?'selected':'' ?>>解決済</option>
    </select>
  </div>
</div>
<div class="mb-2">
  <label class="form-label small fw-bold">問い合わせ内容</label>
  <textarea name="content" class="form-control form-control-sm" rows="4"><?= h($q['content']??'') ?></textarea>
</div>
<div class="mb-2">
  <label class="form-label small">対応内容</label>
  <textarea name="response" class="form-control form-control-sm" rows="3"><?= h($q['response']??'') ?></textarea>
</div>
<div class="mb-3">
  <label class="form-label small">担当者</label>
  <select name="assigned_user_id" class="form-select form-select-sm">
    <option value="">未割当</option>
    <?php foreach($users as $u): ?>
    <option value="<?= $u['id'] ?>" <?= ($q['assigned_user_id']??'')==$u['id']?'selected':'' ?>><?= h($u['display_name']) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<button type="submit" class="btn btn-primary"><?= $id?'更新':'登録' ?></button>
<a href="<?= BASE_URL ?>/inquiries/" class="btn btn-outline-secondary ms-2">キャンセル</a>
<?php if ($id): ?>
<button type="submit" name="delete" class="btn btn-danger ms-2"
  onclick="return confirm('この問い合わせを削除しますか？')">削除</button>
<?php endif; ?>
</form>
</div>
</div>
<?php include dirname(__DIR__).'/footer.php'; ?>
