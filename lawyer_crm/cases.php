<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$lc_user    = lc_require_login();
$page_title = '案件一覧';
$page_nav   = 'cases';

$f    = [
    'q'         => trim($_GET['q'] ?? ''),
    'status'    => $_GET['status'] ?? 'active',
    'case_type' => $_GET['case_type'] ?? '',
    'lawyer_id' => (int)($_GET['lawyer_id'] ?? 0),
];
$page    = max(1, (int)($_GET['page'] ?? 1));
$data    = get_cases($f, $page);
$lawyers = get_lawyers();

$page_actions = '<a href="' . LC_BASE_URL . '/case_edit.php" class="btn btn-sm text-white" style="background:#1a3a5c"><i class="bi bi-plus-lg me-1"></i>新規案件</a>';
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<div class="page-card">
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="案件名・番号・依頼者名" value="<?= h($f['q']) ?>">
    </div>
    <div class="col-auto">
      <select name="status" class="form-select form-select-sm">
        <option value="">ステータス（全て）</option>
        <?php foreach (CASE_STATUS_MAP as $k => $v): ?>
        <option value="<?= $k ?>" <?= $f['status']===$k?'selected':'' ?>><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <select name="case_type" class="form-select form-select-sm">
        <option value="">種別（全て）</option>
        <?php foreach (lc_case_type_map() as $k => $v): ?>
        <option value="<?= $k ?>" <?= $f['case_type']===$k?'selected':'' ?>><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <select name="lawyer_id" class="form-select form-select-sm">
        <option value="">担当弁護士（全て）</option>
        <?php foreach ($lawyers as $l): ?>
        <option value="<?= $l['id'] ?>" <?= $f['lawyer_id']===$l['id']?'selected':'' ?>><?= h($l['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-primary">検索</button>
      <a href="<?= LC_BASE_URL ?>/cases.php" class="btn btn-sm btn-outline-secondary ms-1">リセット</a>
    </div>
  </form>

  <div class="text-muted small mb-2">全 <?= $data['total'] ?> 件</div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>案件番号</th>
          <th>案件名</th>
          <th>依頼者</th>
          <th>種別</th>
          <th>担当弁護士</th>
          <th>期日</th>
          <th>ステータス</th>
          <th>開始日</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($data['rows'])): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">案件が見つかりません。</td></tr>
      <?php else: ?>
        <?php foreach ($data['rows'] as $cs): ?>
        <tr>
          <td class="text-muted small"><?= h($cs['case_number']) ?></td>
          <td><a href="<?= LC_BASE_URL ?>/case_view.php?id=<?= $cs['id'] ?>" class="fw-bold text-decoration-none"><?= h($cs['case_name']) ?></a></td>
          <td><a href="<?= LC_BASE_URL ?>/client_view.php?id=<?= $cs['client_id'] ?>"><?= h($cs['client_name']) ?></a></td>
          <td><span class="badge badge-case-type"><?= h(lc_case_type_map()[$cs['case_type']] ?? '') ?></span></td>
          <td><?= h($cs['lawyer_name'] ?? '未割当') ?></td>
          <td>
            <?php if ($cs['open_deadlines'] > 0): ?>
            <span class="badge bg-warning text-dark"><?= $cs['open_deadlines'] ?>件</span>
            <?php else: ?>
            <span class="text-muted">-</span>
            <?php endif; ?>
          </td>
          <td><?= case_status_badge($cs['status']) ?></td>
          <td><?= fmt_date($cs['opened_date']) ?></td>
          <td>
            <a href="<?= LC_BASE_URL ?>/case_view.php?id=<?= $cs['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">詳細</a>
            <a href="<?= LC_BASE_URL ?>/case_edit.php?id=<?= $cs['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">編集</a>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($data['pages'] > 1): ?>
  <nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
      <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
      <li class="page-item <?= $i===$page?'active':'' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge($f, ['page' => $i])) ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
