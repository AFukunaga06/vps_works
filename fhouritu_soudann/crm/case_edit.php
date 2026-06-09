<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/crm_functions.php';
require_admin();

$id       = (int)($_GET['id'] ?? 0);
$case_obj = $id ? crm_get_case($id) : null;
if ($id && !$case_obj) { header('Location: '.CRM_URL.'/cases.php'); exit; }

$nav        = 'cases';
$page_title = $case_obj ? '案件編集' : '案件新規登録';
$preset_cid = (int)($_GET['client_id'] ?? 0);
$errors     = [];

// 全依頼者（選択用）
$all_clients = crm_get_clients([], 1, 999)['rows'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = $_POST;
    if (empty(trim($d['case_name'] ?? ''))) $errors[] = '案件名は必須です。';
    if (empty($d['client_id'])) $errors[] = '依頼者は必須です。';
    if (empty($errors)) {
        $nid = crm_save_case($d, $id ?: null);
        // 期日も同時登録
        if (!$id && !empty($d['dl_title']) && !empty($d['dl_date']) && !empty($d['dl_time'])) {
            $dl = [
                'case_id'       => $nid,
                'title'         => $d['dl_title'],
                'deadline_date' => $d['dl_date'] . ' ' . $d['dl_time'] . ':00',
                'deadline_type' => $d['dl_type'] ?? 'other',
                'memo'          => $d['dl_memo'] ?? '',
                'is_done'       => 0,
            ];
            crm_save_deadline($dl);
        }
        header('Location: '.CRM_URL.'/case_view.php?id='.$nid.'&saved=1'); exit;
    }
} else {
    $d = $case_obj ?: ['status' => 'active', 'case_type' => 'other', 'client_id' => $preset_cid, 'case_number' => crm_generate_case_number()];
}
?>
<?php require __DIR__ . '/includes/_header.php'; ?>
<div class="page-card" style="max-width:760px">
  <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= crm_h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">依頼者 <span class="text-danger">*</span></label>
        <select name="client_id" class="form-select" required>
          <option value="">選択してください</option>
          <?php foreach ($all_clients as $cl): ?>
          <option value="<?= $cl['id'] ?>" <?= ($d['client_id']??'')==$cl['id']?'selected':'' ?>>
            <?= crm_h($cl['name']) ?><?= $cl['kana'] ? '（'.crm_h($cl['kana']).'）' : '' ?>
          </option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-6"><label class="form-label">案件番号</label>
        <div class="input-group">
          <input type="text" name="case_number" class="form-control" placeholder="例: 2026-001" value="<?= crm_h($d['case_number'] ?? '') ?>">
          <?php if (!$id): ?><span class="input-group-text text-muted small">自動採番</span><?php endif; ?>
        </div></div>
      <div class="col-12"><label class="form-label">案件名 <span class="text-danger">*</span></label>
        <input type="text" name="case_name" class="form-control" value="<?= crm_h($d['case_name'] ?? '') ?>" required></div>
      <div class="col-md-4"><label class="form-label">種別</label>
        <select name="case_type" class="form-select">
          <?php foreach (CASE_TYPE_MAP as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($d['case_type']??'')===$k?'selected':'' ?>><?= crm_h($v) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-4"><label class="form-label">状態</label>
        <select name="status" class="form-select">
          <?php foreach (CASE_STATUS_MAP as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($d['status']??'')===$k?'selected':'' ?>><?= crm_h($v) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-4"><label class="form-label">相手方</label>
        <input type="text" name="opponent" class="form-control" value="<?= crm_h($d['opponent'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">開始日</label>
        <input type="date" name="opened_date" class="form-control" value="<?= crm_h($d['opened_date'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">終結日</label>
        <input type="date" name="closed_date" class="form-control" value="<?= crm_h($d['closed_date'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">裁判所</label>
        <input type="text" name="court_name" class="form-control" value="<?= crm_h($d['court_name'] ?? '') ?>"></div>
      <div class="col-12"><label class="form-label">メモ</label>
        <textarea name="memo" class="form-control" rows="4"><?= crm_h($d['memo'] ?? '') ?></textarea></div>

      <?php if (!$id): ?>
      <div class="col-12">
        <hr class="my-1">
        <div class="fw-bold small mb-2 text-muted"><i class="bi bi-calendar-event me-1"></i>最初の期日（任意）</div>
        <div class="row g-2">
          <div class="col-md-4"><label class="form-label small">内容</label>
            <input type="text" name="dl_title" class="form-control form-control-sm" placeholder="例: 第1回相談"></div>
          <div class="col-md-3"><label class="form-label small">日付</label>
            <input type="date" name="dl_date" class="form-control form-control-sm"></div>
          <div class="col-md-2"><label class="form-label small">時間</label>
            <input type="time" name="dl_time" class="form-control form-control-sm"></div>
          <div class="col-md-3"><label class="form-label small">種別</label>
            <select name="dl_type" class="form-select form-select-sm">
              <?php foreach (DEADLINE_TYPE_MAP as $k => $v): ?><option value="<?= $k ?>"><?= crm_h($v) ?></option><?php endforeach; ?>
            </select></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <div class="d-flex gap-2 mt-4">
      <button class="btn text-white" style="background:var(--g)">保存</button>
      <a href="<?= $case_obj ? CRM_URL.'/case_view.php?id='.$id : CRM_URL.'/cases.php' ?>" class="btn btn-outline-secondary">キャンセル</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/_footer.php'; ?>
