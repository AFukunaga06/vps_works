<?php
require_once dirname(__DIR__).'/auth.php';
require_login();
if (!can_edit_persons()) require_role(['admin','pastor','secretary']);

$id = (int)($_GET['id'] ?? 0);
$person = null;
if ($id) {
    $person = $pdo->prepare("SELECT * FROM persons WHERE id=?");
    $person->execute([$id]);
    $person = $person->fetch();
    if (!$person) { header('Location: '.BASE_URL.'/persons/'); exit; }
}

$users = $pdo->query("SELECT id,display_name,role FROM users WHERE is_active=1 ORDER BY display_name")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $last = trim($_POST['last_name'] ?? '');
    $first = trim($_POST['first_name'] ?? '');
    if (!$last) $errors[] = '姓は必須です。';
    if (!$first) $errors[] = '名は必須です。';

    if (empty($errors)) {
        $fields = [
            'status'=>$_POST['status']??'inquiry',
            'last_name'=>$last,'first_name'=>$first,
            'last_name_kana'=>$_POST['last_name_kana']??null,
            'first_name_kana'=>$_POST['first_name_kana']??null,
            'gender'=>$_POST['gender']??null,
            'birth_date'=>($_POST['birth_date']??'')?:null,
            'phone'=>$_POST['phone']??null,
            'email'=>$_POST['email']??null,
            'address'=>$_POST['address']??null,
            'first_visit_date'=>($_POST['first_visit_date']??'')?:null,
            'assigned_user_id'=>($_POST['assigned_user_id']??'')?:null,
            'consent_personal_info'=>isset($_POST['consent_personal_info'])?1:0,
            'photo_permission'=>isset($_POST['photo_permission'])?1:0,
            'directory_permission'=>isset($_POST['directory_permission'])?1:0,
            'alert_flag'=>isset($_POST['alert_flag'])?1:0,
            'alert_note'=>$_POST['alert_note']??null,
        ];
        if (can_view_confidential()) {
            $fields['pastoral_memo'] = $_POST['pastoral_memo']??null;
            $fields['prayer_requests'] = $_POST['prayer_requests']??null;
            $fields['care_notes'] = $_POST['care_notes']??null;
        }

        if ($id) {
            $set = implode(',', array_map(fn($k)=>"$k=?", array_keys($fields)));
            $vals = array_values($fields);
            $vals[] = $id;
            $pdo->prepare("UPDATE persons SET $set WHERE id=?")->execute($vals);
            log_activity($pdo,'person_update','person',$id,h($last.$first).'を更新');
            flash('更新しました。');
        } else {
            $fields['created_by'] = $_SESSION['user_id'];
            $cols = implode(',', array_keys($fields));
            $phs = implode(',', array_fill(0, count($fields), '?'));
            $pdo->prepare("INSERT INTO persons ($cols) VALUES ($phs)")->execute(array_values($fields));
            $new_id = $pdo->lastInsertId();
            log_activity($pdo,'person_create','person',$new_id,h($last.$first).'を登録');
            flash('登録しました。');
            header('Location: '.BASE_URL.'/persons/view.php?id='.$new_id);
            exit;
        }
        header('Location: '.BASE_URL.'/persons/view.php?id='.$id);
        exit;
    }
}

$p = $person ?? [];
include dirname(__DIR__).'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-person-<?= $id?'pencil':'plus' ?>"></i> <?= $id?'人物編集':'新規登録' ?></h4>
  <?php if($id): ?><a href="<?= BASE_URL ?>/persons/view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">詳細に戻る</a><?php endif; ?>
</div>

<?php if($errors): ?>
<div class="alert alert-danger"><?= implode('<br>',array_map('h',$errors)) ?></div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

<div class="row g-3">
<div class="col-md-8">

<div class="card mb-3">
  <div class="card-header fw-bold"><i class="bi bi-person"></i> 基本情報</div>
  <div class="card-body">
    <div class="row g-2 mb-2">
      <div class="col">
        <label class="form-label small fw-bold">姓 <span class="text-danger">*</span></label>
        <input type="text" name="last_name" class="form-control form-control-sm" value="<?= h($p['last_name']??'') ?>" required>
      </div>
      <div class="col">
        <label class="form-label small fw-bold">名 <span class="text-danger">*</span></label>
        <input type="text" name="first_name" class="form-control form-control-sm" value="<?= h($p['first_name']??'') ?>" required>
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col">
        <label class="form-label small">姓（カナ）</label>
        <input type="text" name="last_name_kana" class="form-control form-control-sm" value="<?= h($p['last_name_kana']??'') ?>">
      </div>
      <div class="col">
        <label class="form-label small">名（カナ）</label>
        <input type="text" name="first_name_kana" class="form-control form-control-sm" value="<?= h($p['first_name_kana']??'') ?>">
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-md-4">
        <label class="form-label small fw-bold">ステータス</label>
        <select name="status" class="form-select form-select-sm">
          <?php foreach(['inquiry'=>'問い合わせ者','first_visit'=>'初来会者','regular'=>'継続来会者','seeker'=>'求道者','baptism_prep'=>'洗礼準備中','member'=>'会員','inactive'=>'休会中','ended'=>'終了'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($p['status']??'inquiry')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small">性別</label>
        <select name="gender" class="form-select form-select-sm">
          <option value="">選択</option>
          <option value="male" <?= ($p['gender']??'')==='male'?'selected':'' ?>>男性</option>
          <option value="female" <?= ($p['gender']??'')==='female'?'selected':'' ?>>女性</option>
          <option value="other" <?= ($p['gender']??'')==='other'?'selected':'' ?>>その他</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small">生年月日</label>
        <input type="date" name="birth_date" class="form-control form-control-sm" value="<?= h($p['birth_date']??'') ?>">
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col">
        <label class="form-label small">電話番号</label>
        <input type="tel" name="phone" class="form-control form-control-sm" value="<?= h($p['phone']??'') ?>">
      </div>
      <div class="col">
        <label class="form-label small">メールアドレス</label>
        <input type="email" name="email" class="form-control form-control-sm" value="<?= h($p['email']??'') ?>">
      </div>
    </div>
    <div class="mb-2">
      <label class="form-label small">住所</label>
      <input type="text" name="address" class="form-control form-control-sm" value="<?= h($p['address']??'') ?>">
    </div>
    <div class="row g-2">
      <div class="col">
        <label class="form-label small">初来会日</label>
        <input type="date" name="first_visit_date" class="form-control form-control-sm" value="<?= h($p['first_visit_date']??'') ?>">
      </div>
      <div class="col">
        <label class="form-label small">担当者</label>
        <select name="assigned_user_id" class="form-select form-select-sm">
          <option value="">未割当</option>
          <?php foreach($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= ($p['assigned_user_id']??'')==$u['id']?'selected':'' ?>><?= h($u['display_name']) ?> (<?= role_label($u['role']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
</div>

<?php if(can_view_confidential()): ?>
<div class="card mb-3 border-danger">
  <div class="card-header fw-bold text-danger"><i class="bi bi-lock"></i> 牧会情報（管理者・牧師のみ）</div>
  <div class="card-body">
    <div class="mb-2">
      <label class="form-label small fw-bold">牧会メモ</label>
      <textarea name="pastoral_memo" class="form-control form-control-sm" rows="3"><?= h($p['pastoral_memo']??'') ?></textarea>
    </div>
    <div class="mb-2">
      <label class="form-label small fw-bold">祈りの課題</label>
      <textarea name="prayer_requests" class="form-control form-control-sm" rows="3"><?= h($p['prayer_requests']??'') ?></textarea>
    </div>
    <div class="mb-2">
      <label class="form-label small fw-bold">配慮事項</label>
      <textarea name="care_notes" class="form-control form-control-sm" rows="2"><?= h($p['care_notes']??'') ?></textarea>
    </div>
  </div>
</div>
<?php endif; ?>

</div><!-- col-md-8 -->
<div class="col-md-4">

<div class="card mb-3">
  <div class="card-header fw-bold"><i class="bi bi-shield-check"></i> 個人情報・権限</div>
  <div class="card-body">
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" name="consent_personal_info" id="consent" <?= ($p['consent_personal_info']??0)?'checked':'' ?>>
      <label class="form-check-label small" for="consent">個人情報提供同意</label>
    </div>
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" name="photo_permission" id="photo" <?= ($p['photo_permission']??0)?'checked':'' ?>>
      <label class="form-check-label small" for="photo">写真掲載可</label>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="directory_permission" id="dir" <?= ($p['directory_permission']??0)?'checked':'' ?>>
      <label class="form-check-label small" for="dir">名簿掲載可</label>
    </div>
    <hr>
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" name="alert_flag" id="alert" <?= ($p['alert_flag']??0)?'checked':'' ?>>
      <label class="form-check-label small text-danger fw-bold" for="alert"><i class="bi bi-exclamation-circle"></i> 要注意フラグ</label>
    </div>
    <div>
      <label class="form-label small">フラグメモ</label>
      <textarea name="alert_note" class="form-control form-control-sm" rows="2"><?= h($p['alert_note']??'') ?></textarea>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <button type="submit" class="btn btn-primary w-100 mb-2"><?= $id?'更新する':'登録する' ?></button>
    <a href="<?= BASE_URL ?>/persons/<?= $id?'view.php?id='.$id:'' ?>" class="btn btn-outline-secondary w-100 btn-sm">キャンセル</a>
    <?php if($id && is_admin()): ?>
    <hr>
    <a href="<?= BASE_URL ?>/persons/action.php?action=delete&id=<?= $id ?>"
       class="btn btn-outline-danger w-100 btn-sm"
       onclick="return confirm('本当に削除しますか？')">削除する</a>
    <?php endif; ?>
  </div>
</div>

</div><!-- col-md-4 -->
</div><!-- row -->
</form>

<?php include dirname(__DIR__).'/footer.php'; ?>
