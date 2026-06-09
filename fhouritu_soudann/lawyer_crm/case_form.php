<?php
require_once 'config.php';
require_login();

$db        = get_db();
$id        = (int)($_GET['id'] ?? 0);
$client_id = (int)($_GET['client_id'] ?? 0);

$case = [];
if ($id) {
    $st = $db->prepare('SELECT * FROM cases WHERE id=?');
    $st->execute([$id]);
    $case = $st->fetch();
    if (!$case) { header('Location: cases.php'); exit; }
    $page_title = '案件編集';
} else {
    $page_title = '案件新規登録';
}

// 依頼人リスト
$clients_list = $db->query('SELECT id, name FROM clients ORDER BY id DESC')->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'client_id'       => (int)$_POST['client_id'],
        'case_number'     => trim($_POST['case_number'] ?? ''),
        'title'           => trim($_POST['title'] ?? ''),
        'case_type'       => $_POST['case_type'] ?? '',
        'description'     => trim($_POST['description'] ?? ''),
        'start_date'      => $_POST['start_date'] ?? '',
        'end_date'        => $_POST['end_date'] ?? '',
        'status'          => $_POST['status'] ?? '受任中',
        'assigned_lawyer' => trim($_POST['assigned_lawyer'] ?? ''),
        'court_name'      => trim($_POST['court_name'] ?? ''),
        'retainer_fee'    => (int)str_replace(',', '', $_POST['retainer_fee'] ?? '0'),
        'success_fee'     => (int)str_replace(',', '', $_POST['success_fee'] ?? '0'),
    ];

    if (!$data['client_id']) $errors[] = '依頼人を選択してください。';
    if (!$data['title'])     $errors[] = '案件名は必須です。';
    if (!$data['case_type']) $errors[] = '案件種別を選択してください。';

    if (!$errors) {
        if ($id) {
            $st = $db->prepare(
                'UPDATE cases SET client_id=?,case_number=?,title=?,case_type=?,description=?,
                 start_date=?,end_date=?,status=?,assigned_lawyer=?,court_name=?,retainer_fee=?,success_fee=?
                 WHERE id=?'
            );
            $st->execute([...array_values($data), $id]);
            header('Location: case_detail.php?id=' . $id . '&saved=1');
        } else {
            $st = $db->prepare(
                'INSERT INTO cases (client_id,case_number,title,case_type,description,start_date,end_date,
                 status,assigned_lawyer,court_name,retainer_fee,success_fee) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $st->execute(array_values($data));
            header('Location: case_detail.php?id=' . $db->lastInsertId() . '&saved=1');
        }
        exit;
    }
    $case = $data;
}

if (!$id && $client_id) $case['client_id'] = $client_id;

require 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="cases.php">案件管理</a></li>
    <?php if ($id): ?><li class="breadcrumb-item"><a href="case_detail.php?id=<?= $id ?>"><?= h($case['title'] ?? '') ?></a></li><?php endif; ?>
    <li class="breadcrumb-item active"><?= $id ? '編集' : '新規登録' ?></li>
  </ol>
</nav>

<?php if ($errors): ?>
<div class="alert alert-danger">
  <?php foreach ($errors as $e): ?><div><i class="bi bi-exclamation-circle me-1"></i><?= h($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:800px">
  <div class="card-header bg-white py-3 fw-semibold">
    <i class="bi bi-folder-plus text-primary me-2"></i><?= h($page_title) ?>
  </div>
  <div class="card-body">
    <form method="post">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">依頼人 <span class="text-danger">*</span></label>
          <select name="client_id" class="form-select" required>
            <option value="">選択してください</option>
            <?php foreach ($clients_list as $cl): ?>
            <option value="<?= $cl['id'] ?>" <?= (int)($case['client_id'] ?? 0) === (int)$cl['id'] ? 'selected' : '' ?>>
              <?= h($cl['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">案件番号</label>
          <input type="text" name="case_number" class="form-control" placeholder="LC-2026-001"
                 value="<?= h($case['case_number'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">案件名 <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="例：山田太郎 離婚調停・審判申立事件"
                 value="<?= h($case['title'] ?? '') ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">案件種別 <span class="text-danger">*</span></label>
          <select name="case_type" class="form-select" required>
            <option value="">選択してください</option>
            <?php foreach (CASE_TYPES as $t): ?>
            <option value="<?= $t ?>" <?= ($case['case_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">ステータス</label>
          <select name="status" class="form-select">
            <?php foreach (CASE_STATUSES as $s): ?>
            <option value="<?= $s ?>" <?= ($case['status'] ?? '受任中') === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">担当弁護士</label>
          <input type="text" name="assigned_lawyer" class="form-control" placeholder="田中 弁護士"
                 value="<?= h($case['assigned_lawyer'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">着手日</label>
          <input type="date" name="start_date" class="form-control"
                 value="<?= h($case['start_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">終了日</label>
          <input type="date" name="end_date" class="form-control"
                 value="<?= h($case['end_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">担当裁判所</label>
          <input type="text" name="court_name" class="form-control" placeholder="東京地方裁判所"
                 value="<?= h($case['court_name'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">着手金（円）</label>
          <div class="input-group">
            <span class="input-group-text">¥</span>
            <input type="number" name="retainer_fee" class="form-control" min="0" step="1000"
                   value="<?= (int)($case['retainer_fee'] ?? 0) ?>">
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">成功報酬（円）</label>
          <div class="input-group">
            <span class="input-group-text">¥</span>
            <input type="number" name="success_fee" class="form-control" min="0" step="1000"
                   value="<?= (int)($case['success_fee'] ?? 0) ?>">
          </div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">案件概要・メモ</label>
          <textarea name="description" class="form-control" rows="4"><?= h($case['description'] ?? '') ?></textarea>
        </div>
      </div>
      <hr class="my-4">
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-check-circle me-1"></i><?= $id ? '更新する' : '登録する' ?>
        </button>
        <a href="<?= $id ? 'case_detail.php?id='.$id : 'cases.php' ?>" class="btn btn-outline-secondary">キャンセル</a>
      </div>
    </form>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
