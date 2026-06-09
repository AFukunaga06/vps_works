<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$lc_user = lc_require_login();

$id       = (int)($_GET['id'] ?? 0);
$case_obj = $id ? get_case($id) : null;
if ($id && !$case_obj) { header('Location: ' . LC_BASE_URL . '/cases.php'); exit; }

$page_title = $case_obj ? '案件編集' : '案件新規登録';
$page_nav   = 'cases';

$lawyers = get_lawyers();
$all_clients = get_clients([], 1, 9999)['rows'];
$errors  = [];

// 依頼者が指定されている場合（client_viewからの遷移）
$preset_client_id = (int)($_GET['client_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = $_POST;
    if (empty(trim($d['case_name'] ?? ''))) $errors[] = '案件名は必須です。';
    if (empty($d['client_id'])) $errors[] = '依頼者は必須です。';

    if (empty($errors)) {
        $new_id = save_case($d, $id ?: null);
        header('Location: ' . LC_BASE_URL . '/case_view.php?id=' . $new_id . '&saved=1'); exit;
    }
} else {
    $d = $case_obj ?: ['status' => 'active', 'case_type' => 'other', 'client_id' => $preset_client_id];
}
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<div class="page-card" style="max-width:760px">
  <?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">依頼者 <span class="text-danger">*</span></label>
        <select name="client_id" class="form-select" required>
          <option value="">選択してください</option>
          <?php foreach ($all_clients as $cl): ?>
          <option value="<?= $cl['id'] ?>" <?= ($d['client_id']??'')==$cl['id']?'selected':'' ?>>
            <?= h($cl['name']) ?><?= $cl['kana'] ? '（' . h($cl['kana']) . '）' : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">案件番号</label>
        <input type="text" name="case_number" class="form-control" placeholder="例: 2024-001" value="<?= h($d['case_number'] ?? '') ?>">
      </div>
      <div class="col-12">
        <label class="form-label">案件名 <span class="text-danger">*</span></label>
        <input type="text" name="case_name" class="form-control" value="<?= h($d['case_name'] ?? '') ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">種別</label>
        <select name="case_type" class="form-select">
          <?php foreach (lc_case_type_map() as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($d['case_type']??'')===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">ステータス</label>
        <select name="status" class="form-select">
          <?php foreach (CASE_STATUS_MAP as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($d['status']??'')===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">担当弁護士</label>
        <select name="assigned_lawyer_id" class="form-select">
          <option value="">未割当</option>
          <?php foreach ($lawyers as $l): ?>
          <option value="<?= $l['id'] ?>" <?= ($d['assigned_lawyer_id']??'')==$l['id']?'selected':'' ?>><?= h($l['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">開始日</label>
        <input type="date" name="opened_date" class="form-control" value="<?= h($d['opened_date'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">終結日</label>
        <input type="date" name="closed_date" class="form-control" value="<?= h($d['closed_date'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">裁判所名</label>
        <input type="text" name="court_name" class="form-control" value="<?= h($d['court_name'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">相手方</label>
        <input type="text" name="opponent" class="form-control" value="<?= h($d['opponent'] ?? '') ?>">
      </div>
      <div class="col-12">
        <label class="form-label">メモ</label>
        <textarea name="memo" class="form-control" rows="4"><?= h($d['memo'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="d-flex gap-2 mt-4">
      <button class="btn text-white" style="background:#1a3a5c">保存</button>
      <a href="<?= $case_obj ? LC_BASE_URL . '/case_view.php?id=' . $id : LC_BASE_URL . '/cases.php' ?>" class="btn btn-outline-secondary">キャンセル</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
