<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/crm_functions.php';
require_admin();

$nav        = 'clients';
$page_title = '依頼者一覧';
$page_btn   = '<a href="'.CRM_URL.'/client_edit.php" class="btn btn-sm text-white" style="background:var(--g)"><i class="bi bi-plus-lg me-1"></i>新規登録</a>';

$f    = ['q' => trim($_GET['q'] ?? ''), 'status' => $_GET['status'] ?? ''];
$page = max(1, (int)($_GET['page'] ?? 1));
$data = crm_get_clients($f, $page);
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<div class="page-card">
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-4"><input type="text" name="q" class="form-control form-control-sm" placeholder="氏名・ふりがな・電話・メール" value="<?= crm_h($f['q']) ?>"></div>
    <div class="col-auto">
      <select name="status" class="form-select form-select-sm">
        <option value="">ステータス（全て）</option>
        <?php foreach (CLIENT_STATUS_MAP as $k => $v): ?>
        <option value="<?= $k ?>" <?= $f['status']===$k?'selected':'' ?>><?= crm_h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-primary">検索</button>
      <a href="<?= CRM_URL ?>/clients.php" class="btn btn-sm btn-outline-secondary ms-1">リセット</a>
    </div>
  </form>
  <div class="text-muted small mb-2">全 <?= $data['total'] ?> 件</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>氏名</th><th>ふりがな</th><th>電話</th><th>メール</th><th>案件数</th><th>状態</th><th></th></tr></thead>
      <tbody>
      <?php if (empty($data['rows'])): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">依頼者が見つかりません。</td></tr>
      <?php else: foreach ($data['rows'] as $c): ?>
        <tr>
          <td><a href="<?= CRM_URL ?>/client_view.php?id=<?= $c['id'] ?>" class="fw-bold text-decoration-none"><?= crm_h($c['name']) ?></a></td>
          <td><?= crm_h($c['kana']) ?></td>
          <td><?= crm_h($c['tel']) ?></td>
          <td><?= crm_h($c['email']) ?></td>
          <td><span class="badge bg-secondary"><?= $c['case_count'] ?></span></td>
          <td><?= crm_client_badge($c['status']) ?></td>
          <td>
            <a href="<?= CRM_URL ?>/client_view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">詳細</a>
            <a href="<?= CRM_URL ?>/client_edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">編集</a>
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
