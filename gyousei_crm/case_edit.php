<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$gc_user = gc_require_login();

$id        = (int)($_GET['id'] ?? 0);
$client_id = (int)($_GET['client_id'] ?? 0);
$cs        = $id ? get_case($id) : null;
if ($id && !$cs) { header('Location: '.GC_BASE_URL.'/cases.php'); exit; }

$page_title = $id ? '案件編集' : '案件新規登録';
$page_nav   = 'cases';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = $_POST;
    if (empty($d['case_name'])) $errors[] = '案件名は必須です。';
    if (empty($d['client_id'])) $errors[] = '依頼人は必須です。';
    if (empty($errors)) {
        $new_id = save_case($d, $id ?: null);
        header('Location: '.GC_BASE_URL.'/case_view.php?id='.$new_id.'&saved=1'); exit;
    }
    $cs = array_merge($cs ?? [], $d);
}

if (!$id && $client_id) {
    $cs = $cs ?? [];
    $cs['client_id'] = $client_id;
}

$users   = get_gyosei_users();
$clients = get_clients([], 1, 200)['rows'];
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<div class="page-card" style="max-width:800px">
  <?php if ($id): ?>
  <div class="mb-3">
    <a href="<?= GC_BASE_URL ?>/case_view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">
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
      <div class="col-md-8">
        <label class="form-label fw-bold">案件名 <span class="text-danger">*</span></label>
        <input type="text" name="case_name" class="form-control" required value="<?= h($cs['case_name'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">案件番号</label>
        <input type="text" name="case_number" class="form-control" placeholder="GC-2026-000" value="<?= h($cs['case_number'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold">依頼人 <span class="text-danger">*</span></label>
        <select name="client_id" class="form-select" required>
          <option value="">選択してください</option>
          <?php foreach ($clients as $cl): ?>
          <option value="<?= $cl['id'] ?>" <?= ($cs['client_id']??'')==$cl['id']?'selected':'' ?>>
            <?= h($cl['company_name'] ?: $cl['name']) ?>（<?= h($cl['name']) ?>）
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold">許認可種別</label>
        <select name="case_type" class="form-select">
          <?php foreach (CASE_TYPE_MAP as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($cs['case_type']??'other_permit')===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">進捗段階</label>
        <select name="progress_stage" class="form-select">
          <?php foreach (PROGRESS_STAGE_MAP as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($cs['progress_stage']??'accepted')===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">ステータス</label>
        <select name="status" class="form-select">
          <?php foreach (CASE_STATUS_MAP as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($cs['status']??'active')===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">担当行政書士</label>
        <select name="assigned_user_id" class="form-select">
          <option value="">未割当</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= ($cs['assigned_user_id']??'')==$u['id']?'selected':'' ?>><?= h($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">受任日</label>
        <input type="date" name="opened_date" class="form-control" value="<?= h($cs['opened_date'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">許可期限</label>
        <input type="date" name="permit_expiry_date" class="form-control" value="<?= h($cs['permit_expiry_date'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold">終結日</label>
        <input type="date" name="closed_date" class="form-control" value="<?= h($cs['closed_date'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold">申請先官庁</label>
        <input type="text" name="government_office" class="form-control" placeholder="例：神奈川県土木事務所" value="<?= h($cs['government_office'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold">受付番号・申請番号</label>
        <input type="text" name="application_number" class="form-control" value="<?= h($cs['application_number'] ?? '') ?>">
      </div>
      <div class="col-12">
        <label class="form-label fw-bold">備考</label>
        <textarea name="memo" class="form-control" rows="3"><?= h($cs['memo'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button type="submit" class="btn btn-gc px-4"><i class="bi bi-check-lg me-1"></i>保存</button>
      <a href="<?= $id ? GC_BASE_URL.'/case_view.php?id='.$id : GC_BASE_URL.'/cases.php' ?>" class="btn btn-outline-secondary">キャンセル</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
