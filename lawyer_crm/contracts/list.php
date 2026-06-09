<?php
/**
 * 契約書一覧
 *
 *  - 自テナントの契約書のみ表示（テナントスコープ）
 *  - 絞り込み: 依頼者・ステータス
 *  - 新しい順
 *  - 各行に「アップロード済 / 分析中 / 分析完了 / 確認済み / エラー」のバッジ
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$lc_user    = lc_require_login();
$tid        = _tid();
$page_title = '契約書レビュー';
$page_nav   = 'contracts';

// 削除処理（POST・テナントスコープ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = (int)($_POST['id'] ?? 0);
    if ($del_id > 0) {
        $pdo = get_db();
        // テナント確認込みでファイルパス取得
        $q = $pdo->prepare("SELECT file_path FROM lc_contracts WHERE id=? AND tenant_id=?");
        $q->execute([$del_id, $tid]);
        $fp = $q->fetchColumn();
        if ($fp !== false) {
            // 契約書本体を削除（条項は ON DELETE CASCADE で自動削除）
            $pdo->prepare("DELETE FROM lc_contracts WHERE id=? AND tenant_id=?")
                ->execute([$del_id, $tid]);
            // アップロードファイルを削除（uploads 配下のみ・パストラバーサル防止）
            $base = realpath(__DIR__ . '/uploads');
            $real = $fp ? realpath($fp) : false;
            if ($real && $base && strpos($real, $base) === 0 && is_file($real)) {
                @unlink($real);
            }
            header('Location: ' . LC_BASE_URL . '/contracts/list.php?deleted=1');
            exit;
        }
    }
    header('Location: ' . LC_BASE_URL . '/contracts/list.php');
    exit;
}

$f = [
    'client_id' => (int)($_GET['client_id'] ?? 0),
    'status'    => $_GET['status']           ?? '',
];

$STATUS_MAP = [
    'uploaded'  => 'アップロード済',
    'analyzing' => '分析中',
    'done'      => '分析完了',
    'reviewed'  => '確認済み',
    'error'     => 'エラー',
];

$sql = "SELECT c.id, c.file_name, c.contract_type, c.party_side, c.status,
               c.total_risk_high, c.total_risk_medium, c.total_risk_low,
               c.uploaded_at, c.analyzed_at,
               cl.name AS client_name
          FROM lc_contracts c
          LEFT JOIN lc_clients cl ON cl.id = c.client_id
         WHERE c.tenant_id = ?";
$params = [$tid];
if ($f['client_id']) { $sql .= " AND c.client_id = ?"; $params[] = $f['client_id']; }
if ($f['status'] !== '' && isset($STATUS_MAP[$f['status']])) {
    $sql .= " AND c.status = ?";
    $params[] = $f['status'];
}
$sql .= " ORDER BY c.uploaded_at DESC";

$stmt = get_db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// 依頼者ドロップダウン
$clients = get_db()->prepare(
    "SELECT id, name FROM lc_clients WHERE tenant_id=? ORDER BY name"
);
$clients->execute([$tid]);
$client_list = $clients->fetchAll();

function _ctr_list_status_badge(string $s): string {
    $cls = [
        'uploaded'  => 'secondary',
        'analyzing' => 'info',
        'done'      => 'primary',
        'reviewed'  => 'success',
        'error'     => 'danger',
    ][$s] ?? 'secondary';
    $labels = [
        'uploaded'  => 'アップロード済',
        'analyzing' => '分析中',
        'done'      => '分析完了',
        'reviewed'  => '確認済み',
        'error'     => 'エラー',
    ];
    return '<span class="badge bg-' . $cls . '">' . h($labels[$s] ?? $s) . '</span>';
}

$page_actions =
    '<a href="' . LC_BASE_URL . '/contracts/upload.php" class="btn btn-sm text-white" '
    . 'style="background:#1a3a5c"><i class="bi bi-upload me-1"></i>契約書をアップロード</a>';
?>
<?php require __DIR__ . '/../includes/_header.php'; ?>

<div class="page-card">
  <?php if (!empty($_GET['deleted'])): ?>
    <div class="alert alert-success py-2 small mb-3">契約書を削除しました。</div>
  <?php endif; ?>
  <div class="mb-3">
    <a href="<?= LC_BASE_URL ?>/contracts/upload.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>アップロード画面に戻る
    </a>
  </div>
  <div class="alert alert-warning small mb-3">
    <i class="bi bi-shield-exclamation me-1"></i>
    各契約書のレビュー結果はAIによる一次分析です。最終判断は弁護士が行ってください。
  </div>

  <form method="get" class="row g-2 mb-3">
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
      <a href="<?= LC_BASE_URL ?>/contracts/list.php"
         class="btn btn-sm btn-outline-secondary ms-1">リセット</a>
    </div>
  </form>

  <div class="text-muted small mb-2">全 <?= count($rows) ?> 件</div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>ファイル</th>
          <th>依頼者</th>
          <th>類型</th>
          <th>立場</th>
          <th>リスク（高／中／低）</th>
          <th>ステータス</th>
          <th>アップロード</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">契約書はまだありません。</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td>
            <a href="<?= LC_BASE_URL ?>/contracts/view.php?id=<?= (int)$r['id'] ?>"
               class="fw-bold text-decoration-none">
              <?= h($r['file_name']) ?>
            </a>
          </td>
          <td><?= h($r['client_name'] ?? '—') ?></td>
          <td><?= h($r['contract_type'] ?? '') ?></td>
          <td><?= h($r['party_side']) ?></td>
          <td>
            <?php if (in_array($r['status'], ['done', 'reviewed'], true)): ?>
              <span class="badge bg-danger"><?= (int)$r['total_risk_high']   ?></span>
              <span class="badge bg-warning text-dark"><?= (int)$r['total_risk_medium'] ?></span>
              <span class="badge bg-success"><?= (int)$r['total_risk_low']    ?></span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td><?= _ctr_list_status_badge($r['status']) ?></td>
          <td><?= h(fmt_datetime($r['uploaded_at'])) ?></td>
          <td>
            <div class="d-flex gap-1 justify-content-end">
              <a href="<?= LC_BASE_URL ?>/contracts/view.php?id=<?= (int)$r['id'] ?>"
                 class="btn btn-sm btn-outline-primary py-0 px-2">詳細</a>
              <form method="post" class="d-inline m-0"
                    onsubmit="return confirm('削除して宜しいですか');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                  <i class="bi bi-trash"></i> 削除
                </button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/_footer.php'; ?>
