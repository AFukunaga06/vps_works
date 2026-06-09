<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$gc_user    = gc_require_login();
$page_title = '依頼人台帳';
$page_nav   = 'clients';

$f    = ['q' => trim($_GET['q'] ?? ''), 'status' => $_GET['status'] ?? 'active'];
$page = max(1, (int)($_GET['page'] ?? 1));
$data = get_clients($f, $page);

$page_actions = '<a href="'.GC_BASE_URL.'/client_edit.php" class="btn btn-sm btn-gc"><i class="bi bi-plus-lg me-1"></i>新規依頼人</a>';
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<div class="page-card">
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="氏名・会社名・電話番号・メール" value="<?= h($f['q']) ?>">
    </div>
    <div class="col-auto">
      <select name="status" class="form-select form-select-sm">
        <option value="">ステータス（全て）</option>
        <?php foreach (CLIENT_STATUS_MAP as $k => $v): ?>
        <option value="<?= $k ?>" <?= $f['status']===$k?'selected':'' ?>><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-primary">検索</button>
      <a href="<?= GC_BASE_URL ?>/clients.php" class="btn btn-sm btn-outline-secondary ms-1">リセット</a>
    </div>
  </form>

  <div class="text-muted small mb-2">全 <?= $data['total'] ?> 名</div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>氏名 / 会社名</th>
          <th>電話番号</th>
          <th>メール</th>
          <th>案件数</th>
          <th>ステータス</th>
          <th>登録日</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($data['rows'])): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">依頼人が見つかりません。</td></tr>
      <?php else: ?>
        <?php foreach ($data['rows'] as $cl): ?>
        <tr>
          <td>
            <a href="<?= GC_BASE_URL ?>/client_view.php?id=<?= $cl['id'] ?>" class="fw-bold text-decoration-none"><?= h($cl['name']) ?></a>
            <?php if ($cl['company_name']): ?>
            <div class="text-muted small"><?= h($cl['company_name']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= h($cl['tel']) ?></td>
          <td><?= h($cl['email']) ?></td>
          <td><span class="badge bg-secondary"><?= $cl['case_count'] ?>件</span></td>
          <td><?= client_status_badge($cl['status']) ?></td>
          <td class="text-muted small"><?= fmt_date($cl['created_at']) ?></td>
          <td>
            <a href="<?= GC_BASE_URL ?>/client_view.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">詳細</a>
            <a href="<?= GC_BASE_URL ?>/client_edit.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">編集</a>
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
