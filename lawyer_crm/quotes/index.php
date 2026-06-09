<?php
/**
 * 見積一覧
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quote_calc.php';

$lc_user    = lc_require_login();
$tid        = _tid();
$page_title = '見積算出';
$page_nav   = 'quotes';

$f = [
    'client_id' => (int)($_GET['client_id'] ?? 0),
    'status'    => $_GET['status']           ?? '',
    'q'         => trim($_GET['q']           ?? ''),
];

$STATUS_MAP = [
    'draft'    => '下書き',
    'sent'     => '送付済',
    'accepted' => '承諾',
    'rejected' => '辞退',
    'canceled' => 'キャンセル',
];

$sql = "SELECT q.*, cl.name AS client_name
          FROM lc_quotes q
          LEFT JOIN lc_clients cl ON cl.id = q.client_id
         WHERE q.tenant_id = ?";
$params = [$tid];
if ($f['client_id']) { $sql .= " AND q.client_id = ?"; $params[] = $f['client_id']; }
if ($f['status'] !== '' && isset($STATUS_MAP[$f['status']])) {
    $sql .= " AND q.status = ?";
    $params[] = $f['status'];
}
if ($f['q'] !== '') {
    $sql .= " AND (q.title LIKE ? OR q.quote_number LIKE ?)";
    $like = '%' . $f['q'] . '%';
    $params[] = $like; $params[] = $like;
}
$sql .= " ORDER BY q.created_at DESC";

$stmt = get_db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$clients = get_db()->prepare("SELECT id, name FROM lc_clients WHERE tenant_id=? ORDER BY name");
$clients->execute([$tid]);
$client_list = $clients->fetchAll();

$page_actions =
    '<a href="' . LC_BASE_URL . '/quotes/calc.php" class="btn btn-sm text-white" '
    . 'style="background:#1a3a5c"><i class="bi bi-plus-lg me-1"></i>新規見積</a>';
?>
<?php require __DIR__ . '/../includes/_header.php'; ?>

<div class="page-card">
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
      <input type="text" name="q" class="form-control form-control-sm"
             placeholder="件名・見積番号" value="<?= h($f['q']) ?>">
    </div>
    <div class="col-md-3">
      <select name="client_id" class="form-select form-select-sm">
        <option value="">依頼者（全て）</option>
        <?php foreach ($client_list as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $f['client_id']===(int)$c['id']?'selected':'' ?>>
            <?= h($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="status" class="form-select form-select-sm">
        <option value="">ステータス（全て）</option>
        <?php foreach ($STATUS_MAP as $k => $v): ?>
          <option value="<?= $k ?>" <?= $f['status']===$k?'selected':'' ?>><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-primary">検索</button>
      <a href="<?= LC_BASE_URL ?>/quotes/index.php"
         class="btn btn-sm btn-outline-secondary ms-1">リセット</a>
    </div>
  </form>

  <div class="text-muted small mb-2">全 <?= count($rows) ?> 件</div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>見積番号</th>
          <th>件名</th>
          <th>依頼者</th>
          <th>案件種別</th>
          <th class="text-end">総額(税込)</th>
          <th>ステータス</th>
          <th>作成日</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">見積はまだありません。</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td>
            <a href="<?= LC_BASE_URL ?>/quotes/view.php?id=<?= (int)$r['id'] ?>"
               class="fw-bold text-decoration-none">
              <?= h($r['quote_number'] ?: lc_quote_number((int)$r['id'], $r['created_at'])) ?>
            </a>
          </td>
          <td><?= h($r['title'] ?: '(無題)') ?></td>
          <td><?= h($r['client_name'] ?? '—') ?></td>
          <td><?= h($r['case_type'] ?? '') ?></td>
          <td class="text-end fw-bold"><?= fmt_money((int)$r['total_yen']) ?></td>
          <td><?= lc_quote_status_badge($r['status']) ?></td>
          <td><?= h(fmt_date($r['created_at'])) ?></td>
          <td class="text-nowrap">
            <a href="<?= LC_BASE_URL ?>/quotes/view.php?id=<?= (int)$r['id'] ?>"
               class="btn btn-sm btn-outline-primary py-0 px-2">表示</a>
            <a href="<?= LC_BASE_URL ?>/quotes/calc.php?id=<?= (int)$r['id'] ?>"
               class="btn btn-sm btn-outline-secondary py-0 px-2">編集</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/_footer.php'; ?>
