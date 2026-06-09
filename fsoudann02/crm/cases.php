<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/crm_functions.php';
require_admin();

$nav        = 'cases';
$page_title = '案件一覧';
$page_btn   = '<a href="'.CRM_URL.'/case_edit.php" class="btn btn-sm text-white" style="background:var(--g)"><i class="bi bi-plus-lg me-1"></i>新規案件</a>';

$f    = ['q' => trim($_GET['q'] ?? ''), 'status' => $_GET['status'] ?? 'active', 'case_type' => $_GET['case_type'] ?? ''];
$page = max(1, (int)($_GET['page'] ?? 1));
$data = crm_get_cases($f, $page);
?>
<?php require __DIR__ . '/includes/_header.php'; ?>
<div class="page-card">
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-3"><input type="text" name="q" class="form-control form-control-sm" placeholder="案件名・番号・依頼者" value="<?= crm_h($f['q']) ?>"></div>
    <div class="col-auto">
      <select name="status" class="form-select form-select-sm">
        <option value="">ステータス（全て）</option>
        <?php foreach (CASE_STATUS_MAP as $k => $v): ?>
        <option value="<?= $k ?>" <?= $f['status']===$k?'selected':'' ?>><?= crm_h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <select name="case_type" class="form-select form-select-sm">
        <option value="">種別（全て）</option>
        <?php foreach (CASE_TYPE_MAP as $k => $v): ?>
        <option value="<?= $k ?>" <?= $f['case_type']===$k?'selected':'' ?>><?= crm_h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-primary">検索</button>
      <a href="<?= CRM_URL ?>/cases.php" class="btn btn-sm btn-outline-secondary ms-1">リセット</a>
    </div>
  </form>
  <div class="text-muted small mb-2">全 <?= $data['total'] ?> 件</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>案件番号</th><th>案件名</th><th>依頼者</th><th>種別</th><th>期日</th><th>状態</th><th>開始日</th><th></th></tr></thead>
      <tbody>
      <?php if (empty($data['rows'])): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">案件が見つかりません。</td></tr>
      <?php else: foreach ($data['rows'] as $cs): ?>
        <tr>
          <td class="text-muted small"><?= crm_h($cs['case_number']) ?></td>
          <td><a href="<?= CRM_URL ?>/case_view.php?id=<?= $cs['id'] ?>" class="fw-bold text-decoration-none"><?= crm_h($cs['case_name']) ?></a></td>
          <td><a href="<?= CRM_URL ?>/client_view.php?id=<?= $cs['client_id'] ?>"><?= crm_h($cs['client_name']) ?></a></td>
          <td><span class="badge badge-type"><?= crm_h(CASE_TYPE_MAP[$cs['case_type']] ?? '') ?></span></td>
          <td><?= $cs['open_deadlines'] > 0 ? '<span class="badge bg-warning text-dark">'.$cs['open_deadlines'].'件</span>' : '<span class="text-muted">-</span>' ?></td>
          <td><?= crm_case_badge($cs['status']) ?></td>
          <td><?= crm_fmt_date($cs['opened_date']) ?></td>
          <td>
            <a href="<?= CRM_URL ?>/case_view.php?id=<?= $cs['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">詳細</a>
            <a href="<?= CRM_URL ?>/case_edit.php?id=<?= $cs['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">編集</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($data['pages'] > 1): ?>
  <nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-0">
    <?php for ($i=1; $i<=$data['pages']; $i++): ?>
    <li class="page-item <?= $i===$page?'active':'' ?>">
      <a class="page-link" href="?<?= http_build_query(array_merge($f,['page'=>$i])) ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
  </ul></nav>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/_footer.php'; ?>
