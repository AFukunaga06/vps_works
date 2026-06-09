<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$staff      = crm_require_login();
$page_title = '顧客一覧';

$filters = [
    'q'      => trim($_GET['q'] ?? ''),
    'status' => $_GET['status'] ?? '',
    'tag'    => $_GET['tag'] ?? '',
];
$page   = max(1, (int)($_GET['page'] ?? 1));
$result = get_customers($filters, $page);
$tags   = get_all_tags();

include __DIR__ . '/includes/_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-muted small">全<?= $result['total'] ?>件</div>
  <a href="customer_edit.php" class="btn btn-sm text-white" style="background:#3a7d5c">＋ 新規登録</a>
</div>

<!-- 検索フォーム -->
<form method="get" class="row g-2 mb-3">
  <div class="col-md-4">
    <input type="text" name="q" class="form-control form-control-sm" placeholder="氏名・ふりがな・メール・電話"
           value="<?= h($filters['q']) ?>">
  </div>
  <div class="col-md-2">
    <select name="status" class="form-select form-select-sm">
      <option value="">すべてのステータス</option>
      <?php foreach (STATUS_MAP as $v => $l): ?>
      <option value="<?= h($v) ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= h($l) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select name="tag" class="form-select form-select-sm">
      <option value="">すべてのタグ</option>
      <?php foreach ($tags as $t): ?>
      <option value="<?= h($t) ?>" <?= $filters['tag'] === $t ? 'selected' : '' ?>><?= h($t) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <button class="btn btn-sm btn-outline-secondary">検索</button>
    <a href="index.php" class="btn btn-sm btn-link">リセット</a>
  </div>
</form>

<!-- 顧客テーブル -->
<div class="table-responsive">
<table class="table table-hover table-sm bg-white shadow-sm rounded">
  <thead class="table-light">
    <tr>
      <th>初回相談日時</th>
      <th>氏名</th>
      <th>ふりがな</th>
      <th>メール</th>
      <th>電話</th>
      <th>ステータス</th>
      <th>相談内容</th>
      <th>最終相談</th>
      <th>タグ</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php if (empty($result['rows'])): ?>
    <tr><td colspan="8" class="text-center text-muted py-4">該当する顧客がいません。</td></tr>
  <?php endif; ?>
  <?php foreach ($result['rows'] as $c): ?>
    <?php $ctags = get_customer_tags((int)$c['id']); ?>
    <tr>
      <td class="small text-muted">
        <?php
          $first = get_db()->prepare("SELECT consulted_at FROM crm_consultations WHERE customer_id=? AND is_first=1 ORDER BY consulted_at ASC LIMIT 1");
          $first->execute([$c['id']]);
          $fd = $first->fetchColumn();
          echo $fd ? date('Y/m/d H:i', strtotime($fd)) : '—';
        ?>
      </td>
      <td><a href="customer.php?id=<?= $c['id'] ?>"><?= h($c['name']) ?></a></td>
      <td class="text-muted small"><?= h($c['kana']) ?></td>
      <td class="small"><?= h($c['email']) ?></td>
      <td class="small"><?= h($c['tel']) ?></td>
      <td><?= crm_status_badge($c['member_status']) ?></td>
      <td class="small text-muted">
        <?php
          $last = get_db()->prepare("SELECT content FROM crm_consultations WHERE customer_id=? ORDER BY consulted_at DESC LIMIT 1");
          $last->execute([$c['id']]);
          $lc = $last->fetchColumn();
          echo $lc ? h(mb_strimwidth($lc, 0, 30, '…')) : '—';
        ?>
      </td>
      <td class="small text-muted">
        <?= $c['last_consulted'] ? date('Y/m/d', strtotime($c['last_consulted'])) : '未記録' ?>
      </td>
      <td>
        <?php foreach ($ctags as $t): ?>
        <span class="badge bg-light text-dark border"><?= h($t) ?></span>
        <?php endforeach; ?>
      </td>
      <td>
        <a href="customer.php?id=<?= $c['id'] ?>" class="btn btn-xs btn-outline-secondary btn-sm py-0">詳細</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- ページネーション -->
<?php if ($result['pages'] > 1): ?>
<nav><ul class="pagination pagination-sm justify-content-center">
  <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
  <li class="page-item <?= $i === $page ? 'active' : '' ?>">
    <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a>
  </li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php include __DIR__ . '/includes/_footer.php'; ?>
