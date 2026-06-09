<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$gc_user = gc_require_login();

$id = (int)($_GET['id'] ?? 0);
$cl = $id ? get_client($id) : null;
if ($id && !$cl) { header('Location: '.GC_BASE_URL.'/clients.php'); exit; }

$page_title = $id ? '依頼人編集' : '依頼人新規登録';
$page_nav   = 'clients';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = $_POST;
    if (empty($d['name'])) $errors[] = '氏名は必須です。';
    if (empty($errors)) {
        $new_id = save_client($d, $id ?: null);
        header('Location: '.GC_BASE_URL.'/client_view.php?id='.$new_id.'&saved=1'); exit;
    }
    $cl = array_merge($cl ?? [], $d);
}

$users = get_all_users();
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<div class="page-card" style="max-width:760px">
  <?php if ($id): ?>
  <div class="mb-3">
    <a href="<?= GC_BASE_URL ?>/client_view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>詳細に戻る
    </a>
  </div>
  <?php endif; ?>

  <?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="post">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label fw-bold">氏名 <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="<?= h($cl['name'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold">氏名（読み）</label>
        <input type="text" name="kana" class="form-control" value="<?= h($cl['kana'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold">会社名</label>
        <input type="text" name="company_name" class="form-control" value="<?= h($cl['company_name'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold">会社名（読み）</label>
        <input type="text" name="company_kana" class="form-control" value="<?= h($cl['company_kana'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold">電話番号</label>
        <input type="tel" name="tel" class="form-control" value="<?= h($cl['tel'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold">電話番号2</label>
        <input type="tel" name="tel2" class="form-control" value="<?= h($cl['tel2'] ?? '') ?>">
      </div>
      <div class="col-md-8">
        <label class="form-label fw-bold">メールアドレス</label>
        <input type="email" name="email" class="form-control" value="<?= h($cl['email'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">区分</label>
        <select name="gender" class="form-select">
          <option value="">選択</option>
          <option value="male"      <?= ($cl['gender']??'')==='male'?'selected':'' ?>>男性（個人）</option>
          <option value="female"    <?= ($cl['gender']??'')==='female'?'selected':'' ?>>女性（個人）</option>
          <option value="corporate" <?= ($cl['gender']??'')==='corporate'?'selected':'' ?>>法人</option>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label fw-bold">住所</label>
        <input type="text" name="address" class="form-control" value="<?= h($cl['address'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">生年月日</label>
        <input type="date" name="birth_date" class="form-control" value="<?= h($cl['birth_date'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">担当者</label>
        <select name="assigned_user_id" class="form-select">
          <option value="">未割当</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= ($cl['assigned_user_id']??'')==$u['id']?'selected':'' ?>><?= h($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">ステータス</label>
        <select name="status" class="form-select">
          <?php foreach (CLIENT_STATUS_MAP as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($cl['status']??'active')===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label fw-bold">備考</label>
        <textarea name="memo" class="form-control" rows="3"><?= h($cl['memo'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button type="submit" class="btn btn-gc px-4"><i class="bi bi-check-lg me-1"></i>保存</button>
      <a href="<?= $id ? GC_BASE_URL.'/client_view.php?id='.$id : GC_BASE_URL.'/clients.php' ?>" class="btn btn-outline-secondary">キャンセル</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
