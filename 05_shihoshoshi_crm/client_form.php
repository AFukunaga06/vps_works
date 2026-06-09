<?php
require_once 'config.php';
require_login();

$db  = get_db();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cl  = $id ? $db->query("SELECT * FROM clients WHERE id = $id")->fetch() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'        => trim($_POST['name'] ?? ''),
        'name_kana'   => trim($_POST['name_kana'] ?? ''),
        'gender'      => $_POST['gender'] ?? null,
        'birth_date'  => $_POST['birth_date'] ?: null,
        'phone'       => trim($_POST['phone'] ?? ''),
        'email'       => trim($_POST['email'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'address'     => trim($_POST['address'] ?? ''),
        'notes'       => trim($_POST['notes'] ?? ''),
    ];

    if ($data['name'] === '') {
        $error = '氏名は必須です。';
    } else {
        if ($id) {
            $db->prepare("
                UPDATE clients SET name=?, name_kana=?, gender=?, birth_date=?, phone=?, email=?,
                postal_code=?, address=?, notes=? WHERE id=?
            ")->execute([...$data, $id]);
        } else {
            $db->prepare("
                INSERT INTO clients (name,name_kana,gender,birth_date,phone,email,postal_code,address,notes)
                VALUES (?,?,?,?,?,?,?,?,?)
            ")->execute($data);
            $id = $db->lastInsertId();
        }
        header("Location: client_detail.php?id=$id");
        exit;
    }
}

$page_title = $cl ? '依頼人編集' : '依頼人新規登録';
require 'includes/header.php';
?>

<div class="mb-3">
  <a href="<?= $cl ? "client_detail.php?id=$id" : 'clients.php' ?>" class="text-muted small">
    <i class="bi bi-arrow-left me-1"></i>戻る
  </a>
</div>

<div class="card" style="max-width:700px">
  <div class="card-header bg-white py-2 fw-semibold"><?= $page_title ?></div>
  <div class="card-body">
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="row g-3">
        <div class="col-sm-6">
          <label class="form-label fw-semibold">氏名 <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required value="<?= h($cl['name'] ?? '') ?>">
        </div>
        <div class="col-sm-6">
          <label class="form-label fw-semibold">かな</label>
          <input type="text" name="name_kana" class="form-control" value="<?= h($cl['name_kana'] ?? '') ?>">
        </div>
        <div class="col-sm-4">
          <label class="form-label fw-semibold">性別</label>
          <select name="gender" class="form-select">
            <option value="">未設定</option>
            <?php foreach (['男','女','法人','その他'] as $g): ?>
            <option <?= ($cl['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-4">
          <label class="form-label fw-semibold">生年月日</label>
          <input type="date" name="birth_date" class="form-control" value="<?= h($cl['birth_date'] ?? '') ?>">
        </div>
        <div class="col-sm-4">
          <label class="form-label fw-semibold">電話</label>
          <input type="tel" name="phone" class="form-control" value="<?= h($cl['phone'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">メールアドレス</label>
          <input type="email" name="email" class="form-control" value="<?= h($cl['email'] ?? '') ?>">
        </div>
        <div class="col-sm-4">
          <label class="form-label fw-semibold">郵便番号</label>
          <input type="text" name="postal_code" class="form-control" placeholder="000-0000" value="<?= h($cl['postal_code'] ?? '') ?>">
        </div>
        <div class="col-sm-8">
          <label class="form-label fw-semibold">住所</label>
          <input type="text" name="address" class="form-control" value="<?= h($cl['address'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">メモ</label>
          <textarea name="notes" class="form-control" rows="3"><?= h($cl['notes'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>保存</button>
        <a href="<?= $cl ? "client_detail.php?id=$id" : 'clients.php' ?>" class="btn btn-outline-secondary">キャンセル</a>
      </div>
    </form>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
