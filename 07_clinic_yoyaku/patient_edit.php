<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { header('Location: patients.php'); exit; }

$errors = [];
$data   = $p;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'セッションエラーが発生しました。';
    } else {
        $fields = ['last_name','last_name_kana',
                   'birth_date','gender','phone','email','postal_code','address',
                   'blood_type','allergies','medical_history','note','insurance_no'];
        foreach ($fields as $f) {
            $data[$f] = trim($_POST[$f] ?? '');
        }
        if ($data['last_name'] === '') $errors[] = '患者名は必須です。';
        if ($data['phone'] === '')      $errors[] = '電話番号は必須です。';

        if (empty($errors)) {
            $upd = $pdo->prepare(
                'UPDATE patients SET
                   last_name=?, first_name='', last_name_kana=?, first_name_kana='',
                   birth_date=?, gender=?, phone=?, email=?, postal_code=?, address=?,
                   blood_type=?, allergies=?, medical_history=?, note=?, insurance_no=?
                 WHERE id=?'
            );
            $upd->execute([
                $data['last_name'],
                $data['last_name_kana'],
                $data['birth_date'] ?: null,
                $data['gender'], $data['phone'], $data['email'],
                $data['postal_code'], $data['address'], $data['blood_type'],
                $data['allergies'], $data['medical_history'], $data['note'],
                $data['insurance_no'], $id,
            ]);
            header('Location: patient_view.php?id=' . $id . '&msg=updated');
            exit;
        }
    }
}

pageHead('患者情報編集');
navbar();
?>
<div class="container">
  <h5 class="mb-3"><i class="bi bi-pencil"></i> 患者情報編集 — <?= h($p['last_name'] . '　' . $p['first_name']) ?></h5>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">姓名 <span class="text-danger">*</span></label>
            <input type="text" name="last_name" class="form-control" value="<?= h($data['last_name']) ?>" required placeholder="例：山田 太郎">
          </div>
          <div class="col-md-6">
            <label class="form-label">セイメイ</label>
            <input type="text" name="last_name_kana" class="form-control" value="<?= h($data['last_name_kana']) ?>" placeholder="例：ヤマダ タロウ">
          </div>
          <div class="col-md-3">
            <label class="form-label">生年月日</label>
            <input type="date" id="birth_date" name="birth_date" class="form-control" value="<?= h($data['birth_date']) ?>">
            <span id="age_display" class="text-muted small"></span>
          </div>
          <div class="col-md-2">
            <label class="form-label">性別</label>
            <select name="gender" class="form-select">
              <option value="male"   <?= $data['gender'] === 'male'   ? 'selected' : '' ?>>男性</option>
              <option value="female" <?= $data['gender'] === 'female' ? 'selected' : '' ?>>女性</option>
              <option value="other"  <?= $data['gender'] === 'other'  ? 'selected' : '' ?>>その他</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">血液型</label>
            <select name="blood_type" class="form-select">
              <?php foreach (['A','B','O','AB','unknown'] as $bt): ?>
                <option <?= $data['blood_type'] === $bt ? 'selected' : '' ?>><?= $bt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label">電話番号 <span class="text-danger">*</span></label>
            <input type="tel" name="phone" class="form-control" value="<?= h($data['phone']) ?>" required>
          </div>
          <div class="col-md-5">
            <label class="form-label">メール</label>
            <input type="email" name="email" class="form-control" value="<?= h($data['email']) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">郵便番号</label>
            <input type="text" name="postal_code" class="form-control" value="<?= h($data['postal_code']) ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label">住所</label>
            <input type="text" name="address" class="form-control" value="<?= h($data['address']) ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label">保険証番号</label>
            <input type="text" name="insurance_no" class="form-control" value="<?= h($data['insurance_no']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">アレルギー</label>
            <input type="text" name="allergies" class="form-control" value="<?= h($data['allergies']) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">既往歴</label>
            <textarea name="medical_history" class="form-control" rows="2"><?= h($data['medical_history']) ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">備考</label>
            <textarea name="note" class="form-control" rows="2"><?= h($data['note']) ?></textarea>
          </div>
        </div>
        <div class="mt-3">
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> 更新する</button>
          <a href="patient_view.php?id=<?= $id ?>" class="btn btn-outline-secondary ms-2">キャンセル</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php pageFooter(); ?>
