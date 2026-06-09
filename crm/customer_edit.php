<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$staff = crm_require_login();

$id = (int)($_GET['id'] ?? 0);
$c  = $id ? get_customer($id) : null;
if ($id && !$c) { header('Location: ' . CRM_BASE_URL . '/index.php'); exit; }

$page_title = $c ? '顧客編集' : '顧客新規登録';
$errors = [];
$old    = $c ?? ['name'=>'','kana'=>'','email'=>'','tel'=>'','address'=>'',
                  'birth_date'=>'','member_status'=>'pending','member_since'=>'','memo'=>''];
$old_tags = $id ? implode(', ', get_customer_tags($id)) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'name'          => trim($_POST['name'] ?? ''),
        'kana'          => trim($_POST['kana'] ?? ''),
        'email'         => trim($_POST['email'] ?? ''),
        'tel'           => trim($_POST['tel'] ?? ''),
        'address'       => trim($_POST['address'] ?? ''),
        'birth_date'    => trim($_POST['birth_date'] ?? ''),
        'member_status' => $_POST['member_status'] ?? 'pending',
        'member_since'  => trim($_POST['member_since'] ?? ''),
        'memo'          => trim($_POST['memo'] ?? ''),
    ];
    $old_tags = trim($_POST['tags'] ?? '');

    if (!$old['name']) $errors['name'] = '氏名は必須です。';
    if ($old['email'] && !filter_var($old['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = '正しいメールアドレスを入力してください。';
    if (!in_array($old['member_status'], array_keys(STATUS_MAP)))
        $errors['member_status'] = '会員ステータスを選択してください。';

    if (empty($errors)) {
        $cid = save_customer($old, $id ?: null);
        $tags = array_filter(array_map('trim', explode(',', $old_tags)));
        save_customer_tags($cid, $tags);
        header('Location: ' . CRM_BASE_URL . '/customer.php?id=' . $cid);
        exit;
    }
}

include __DIR__ . '/includes/_header.php';
?>

<div style="max-width:700px">
  <?php if ($errors): ?>
  <div class="alert alert-danger">入力内容を確認してください。</div>
  <?php endif; ?>

  <form method="post">
    <div class="card shadow-sm mb-4">
      <div class="card-header"><strong>基本情報</strong></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-bold">氏名 <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                   value="<?= h($old['name']) ?>" placeholder="例：福田 花子">
            <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= h($errors['name']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">ふりがな</label>
            <input type="text" name="kana" class="form-control" value="<?= h($old['kana']) ?>" placeholder="ふくだ はなこ">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">メールアドレス</label>
            <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                   value="<?= h($old['email']) ?>">
            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= h($errors['email']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">電話番号</label>
            <input type="tel" name="tel" class="form-control" value="<?= h($old['tel']) ?>" placeholder="090-0000-0000">
          </div>
          <div class="col-12">
            <label class="form-label">住所</label>
            <input type="text" name="address" class="form-control" value="<?= h($old['address']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">生年月日</label>
            <input type="date" name="birth_date" class="form-control" value="<?= h($old['birth_date'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header"><strong>会員情報</strong></div>
      <div class="card-body row g-3">
        <div class="col-md-6">
          <label class="form-label fw-bold">ステータス</label>
          <select name="member_status" class="form-select">
            <?php foreach (STATUS_MAP as $v => $l): ?>
            <option value="<?= h($v) ?>" <?= ($old['member_status'] ?? '') === $v ? 'selected' : '' ?>><?= h($l) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">会員登録日</label>
          <input type="date" name="member_since" class="form-control" value="<?= h($old['member_since'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">タグ <span class="text-muted small">（カンマ区切りで複数入力）</span></label>
          <input type="text" name="tags" class="form-control" value="<?= h($old_tags) ?>"
                 placeholder="例：要フォロー, 法律案件, 継続中">
        </div>
        <div class="col-12">
          <label class="form-label">内部メモ</label>
          <textarea name="memo" class="form-control" rows="3"><?= h($old['memo']) ?></textarea>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn text-white" style="background:#3a7d5c">
        <?= $c ? '更新する' : '登録する' ?>
      </button>
      <a href="<?= $c ? CRM_BASE_URL . '/customer.php?id=' . $id : CRM_BASE_URL . '/index.php' ?>"
         class="btn btn-outline-secondary">キャンセル</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/includes/_footer.php'; ?>
