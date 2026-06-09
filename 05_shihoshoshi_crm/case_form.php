<?php
require_once 'config.php';
require_login();

$db        = get_db();
$id        = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$case      = $id ? $db->query("SELECT * FROM cases WHERE id = $id")->fetch() : null;
$clients   = $db->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'client_id'      => (int)($_POST['client_id'] ?? 0),
        'case_number'    => trim($_POST['case_number'] ?? ''),
        'title'          => trim($_POST['title'] ?? ''),
        'case_type'      => $_POST['case_type'] ?? '',
        'description'    => trim($_POST['description'] ?? ''),
        'start_date'     => $_POST['start_date'] ?: null,
        'end_date'       => $_POST['end_date'] ?: null,
        'status'         => $_POST['status'] ?? '受任中',
        'assigned_staff' => trim($_POST['assigned_staff'] ?? ''),
        'registry_office'=> trim($_POST['registry_office'] ?? ''),
    ];

    if ($data['title'] === '' || $data['client_id'] === 0) {
        $error = '依頼人と案件名は必須です。';
    } else {
        if ($id) {
            $db->prepare("
                UPDATE cases SET client_id=?, case_number=?, title=?, case_type=?, description=?,
                start_date=?, end_date=?, status=?, assigned_staff=?, registry_office=? WHERE id=?
            ")->execute([...$data, $id]);
        } else {
            $db->prepare("
                INSERT INTO cases (client_id,case_number,title,case_type,description,
                  start_date,end_date,status,assigned_staff,registry_office)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ")->execute($data);
            $id = $db->lastInsertId();
        }
        header("Location: case_detail.php?id=$id");
        exit;
    }
}

$page_title = $case ? '案件編集' : '新規案件登録';
require 'includes/header.php';
?>

<div class="mb-3">
  <a href="<?= $case ? "case_detail.php?id=$id" : 'cases.php' ?>" class="text-muted small">
    <i class="bi bi-arrow-left me-1"></i>戻る
  </a>
</div>

<div class="card" style="max-width:750px">
  <div class="card-header bg-white py-2 fw-semibold"><?= $page_title ?></div>
  <div class="card-body">
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="row g-3">
        <div class="col-sm-6">
          <label class="form-label fw-semibold">依頼人 <span class="text-danger">*</span></label>
          <select name="client_id" class="form-select" required>
            <option value="">選択してください</option>
            <?php foreach ($clients as $cl): ?>
            <option value="<?= $cl['id'] ?>"
              <?= (int)($case['client_id'] ?? $client_id) === $cl['id'] ? 'selected' : '' ?>>
              <?= h($cl['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-6">
          <label class="form-label fw-semibold">案件番号</label>
          <input type="text" name="case_number" class="form-control" placeholder="SH-2025-001" value="<?= h($case['case_number'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">案件名 <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" required value="<?= h($case['title'] ?? '') ?>">
        </div>
        <div class="col-sm-6">
          <label class="form-label fw-semibold">案件種別</label>
          <select name="case_type" class="form-select">
            <?php foreach (CASE_TYPES as $t): ?>
            <option <?= ($case['case_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-6">
          <label class="form-label fw-semibold">状態</label>
          <select name="status" class="form-select">
            <?php foreach (CASE_STATUSES as $s): ?>
            <option <?= ($case['status'] ?? '受任中') === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-6">
          <label class="form-label fw-semibold">着手日</label>
          <input type="date" name="start_date" class="form-control" value="<?= h($case['start_date'] ?? '') ?>">
        </div>
        <div class="col-sm-6">
          <label class="form-label fw-semibold">完了日</label>
          <input type="date" name="end_date" class="form-control" value="<?= h($case['end_date'] ?? '') ?>">
        </div>
        <div class="col-sm-6">
          <label class="form-label fw-semibold">担当者</label>
          <input type="text" name="assigned_staff" class="form-control" value="<?= h($case['assigned_staff'] ?? '') ?>">
        </div>
        <div class="col-sm-6">
          <label class="form-label fw-semibold">管轄法務局・裁判所</label>
          <input type="text" name="registry_office" class="form-control" value="<?= h($case['registry_office'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">備考</label>
          <textarea name="description" class="form-control" rows="3"><?= h($case['description'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>保存</button>
        <a href="<?= $case ? "case_detail.php?id=$id" : 'cases.php' ?>" class="btn btn-outline-secondary">キャンセル</a>
      </div>
    </form>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
