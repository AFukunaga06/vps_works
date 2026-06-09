<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/crm_functions.php';
require_admin();

$id     = (int)($_GET['id'] ?? 0);
$client = $id ? crm_get_client($id) : null;
if ($id && !$client) { header('Location: '.CRM_URL.'/clients.php'); exit; }

$nav        = 'clients';
$page_title = $client ? '依頼者編集' : '依頼者新規登録';
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = $_POST;
    if (empty(trim($d['name'] ?? ''))) $errors[] = '氏名は必須です。';
    if (empty($errors)) {
        $nid = crm_save_client($d, $id ?: null);
        header('Location: '.CRM_URL.'/client_view.php?id='.$nid.'&saved=1'); exit;
    }
} else {
    $d = $client ?: ['status' => 'active', 'gender' => ''];
}
?>
<?php require __DIR__ . '/includes/_header.php'; ?>
<div class="page-card" style="max-width:700px">
  <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= crm_h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">氏名 <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= crm_h($d['name'] ?? '') ?>" required></div>
      <div class="col-md-6"><label class="form-label">ふりがな</label>
        <input type="text" name="kana" class="form-control" value="<?= crm_h($d['kana'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label">電話番号</label>
        <input type="tel" name="tel" class="form-control" value="<?= crm_h($d['tel'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label">電話番号2</label>
        <input type="tel" name="tel2" class="form-control" value="<?= crm_h($d['tel2'] ?? '') ?>"></div>
      <div class="col-md-8"><label class="form-label">メールアドレス</label>
        <input type="email" name="email" class="form-control" value="<?= crm_h($d['email'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">性別</label>
        <select name="gender" class="form-select">
          <option value="">未選択</option>
          <option value="male"   <?= ($d['gender']??'')==='male'?'selected':'' ?>>男性</option>
          <option value="female" <?= ($d['gender']??'')==='female'?'selected':'' ?>>女性</option>
          <option value="other"  <?= ($d['gender']??'')==='other'?'selected':'' ?>>その他</option>
        </select></div>
      <div class="col-md-6"><label class="form-label">生年月日</label>
        <input type="date" name="birth_date" class="form-control" value="<?= crm_h($d['birth_date'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label">職業</label>
        <input type="text" name="occupation" class="form-control" value="<?= crm_h($d['occupation'] ?? '') ?>"></div>
      <div class="col-12"><label class="form-label">住所</label>
        <input type="text" name="address" class="form-control" value="<?= crm_h($d['address'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label">ステータス</label>
        <select name="status" class="form-select">
          <?php foreach (CLIENT_STATUS_MAP as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($d['status']??'')===$k?'selected':'' ?>><?= crm_h($v) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-12"><label class="form-label">メモ</label>
        <textarea name="memo" class="form-control" rows="3"><?= crm_h($d['memo'] ?? '') ?></textarea></div>
    </div>
    <div class="d-flex gap-2 mt-4">
      <button class="btn text-white" style="background:var(--g)">保存</button>
      <a href="<?= $client ? CRM_URL.'/client_view.php?id='.$id : CRM_URL.'/clients.php' ?>" class="btn btn-outline-secondary">キャンセル</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/_footer.php'; ?>
