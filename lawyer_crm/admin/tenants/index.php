<?php
/**
 * 運営管理: テナント一覧
 * - 全テナント横断で表示
 * - プラン別/ステータス別の集計
 * - クイックアクション（停止/解除）
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/tenant.php';

$lc_user = lc_require_super_admin();
$page_title = 'テナント管理（運営）';
$page_nav   = 'superadmin_tenants';

$db = get_db();
$f_q      = trim($_GET['q'] ?? '');
$f_status = $_GET['status'] ?? '';
$f_plan   = $_GET['plan'] ?? '';

// アクション処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    $tid    = (int)($_POST['tenant_id'] ?? 0);
    if ($tid > 0) {
        switch ($action) {
            case 'suspend':
                $db->prepare("UPDATE lc_tenants SET status='suspended' WHERE id=?")->execute([$tid]);
                break;
            case 'reactivate':
                $db->prepare("UPDATE lc_tenants SET status='active' WHERE id=?")->execute([$tid]);
                break;
            case 'extend_trial':
                $db->prepare("UPDATE lc_tenants SET trial_ends_at=DATE_ADD(IFNULL(trial_ends_at,NOW()), INTERVAL 14 DAY), status='trial' WHERE id=?")
                    ->execute([$tid]);
                break;
        }
    }
    header('Location: index.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '')); exit;
}

// 集計
$stats_rows = $db->query("
    SELECT plan, status, COUNT(*) AS cnt
    FROM lc_tenants GROUP BY plan, status
")->fetchAll();

$total_count = (int)$db->query("SELECT COUNT(*) FROM lc_tenants")->fetchColumn();
$by_plan = ['solo'=>0, 'standard'=>0, 'pro'=>0, 'enterprise'=>0];
$by_status = ['trial'=>0, 'active'=>0, 'past_due'=>0, 'suspended'=>0, 'canceled'=>0];
foreach ($stats_rows as $r) {
    $by_plan[$r['plan']] = ($by_plan[$r['plan']] ?? 0) + (int)$r['cnt'];
    $by_status[$r['status']] = ($by_status[$r['status']] ?? 0) + (int)$r['cnt'];
}

// MRR概算（active テナントのみ）
$mrr_rows = $db->query("
    SELECT plan, billing_cycle, COUNT(*) AS cnt
    FROM lc_tenants WHERE status='active'
    GROUP BY plan, billing_cycle
")->fetchAll();
$specs = lc_plan_specs();
$mrr = 0;
foreach ($mrr_rows as $r) {
    $sp = $specs[$r['plan']] ?? null;
    if (!$sp) continue;
    $price = $r['billing_cycle'] === 'yearly' ? ($sp['price_yearly'] ? (int)($sp['price_yearly']/12) : 0) : (int)$sp['price_monthly'];
    $mrr += $price * (int)$r['cnt'];
}

// 一覧取得
$where = ['1=1']; $params = [];
if ($f_q !== '') {
    $q = '%' . $f_q . '%';
    $where[] = '(t.name LIKE ? OR t.contact_email LIKE ? OR t.contact_name LIKE ? OR t.slug LIKE ?)';
    array_push($params, $q, $q, $q, $q);
}
if ($f_status) { $where[] = 't.status=?'; $params[] = $f_status; }
if ($f_plan)   { $where[] = 't.plan=?';   $params[] = $f_plan; }
$w = implode(' AND ', $where);

$stmt = $db->prepare(
    "SELECT t.*,
        (SELECT COUNT(*) FROM lc_users    u WHERE u.tenant_id=t.id AND u.is_active=1) AS users_cnt,
        (SELECT COUNT(*) FROM lc_clients  c WHERE c.tenant_id=t.id) AS clients_cnt,
        (SELECT COUNT(*) FROM lc_cases    cs WHERE cs.tenant_id=t.id) AS cases_cnt,
        (SELECT COALESCE(SUM(amount),0) FROM lc_tenant_invoices i WHERE i.tenant_id=t.id AND i.status='paid') AS revenue
     FROM lc_tenants t
     WHERE $w
     ORDER BY t.created_at DESC LIMIT 200"
);
$stmt->execute($params);
$tenants = $stmt->fetchAll();

function status_badge2(string $s): string {
    $map = [
        'trial' => ['secondary','トライアル'],
        'active' => ['success','契約中'],
        'past_due' => ['warning','支払遅延'],
        'suspended' => ['danger','停止'],
        'canceled' => ['dark','解約済'],
    ];
    [$cls, $label] = $map[$s] ?? ['secondary', $s];
    return "<span class='badge bg-{$cls}'>" . htmlspecialchars($label) . "</span>";
}

require __DIR__ . '/../../includes/_header.php';
?>

<!-- KPI -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="page-card text-center py-3">
      <div class="fs-3 fw-bold text-primary"><?= $total_count ?></div>
      <small class="text-muted">総テナント数</small>
    </div>
  </div>
  <div class="col-md-3">
    <div class="page-card text-center py-3">
      <div class="fs-3 fw-bold text-success"><?= $by_status['active'] ?? 0 ?></div>
      <small class="text-muted">契約中</small>
    </div>
  </div>
  <div class="col-md-3">
    <div class="page-card text-center py-3">
      <div class="fs-3 fw-bold text-secondary"><?= $by_status['trial'] ?? 0 ?></div>
      <small class="text-muted">トライアル中</small>
    </div>
  </div>
  <div class="col-md-3">
    <div class="page-card text-center py-3">
      <div class="fs-3 fw-bold" style="color:#c8a94a;">¥<?= number_format($mrr) ?></div>
      <small class="text-muted">概算MRR</small>
    </div>
  </div>
</div>

<!-- プラン別 -->
<div class="page-card mb-3">
  <h2 class="h6 fw-bold mb-3"><i class="bi bi-pie-chart me-1"></i>プラン別内訳</h2>
  <div class="row text-center">
    <?php foreach (['solo','standard','pro','enterprise'] as $p):
      $sp = $specs[$p];
    ?>
    <div class="col-md-3">
      <div class="border rounded p-2">
        <div class="text-muted small"><?= $sp['label'] ?></div>
        <div class="fs-4 fw-bold"><?= $by_plan[$p] ?? 0 ?></div>
        <small class="text-muted">
          <?= $sp['price_monthly'] ? '¥'.number_format($sp['price_monthly']).'/月' : '個別見積' ?>
        </small>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- フィルタ -->
<div class="page-card">
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="事務所名・メール・slug" value="<?= h($f_q) ?>">
    </div>
    <div class="col-auto">
      <select name="status" class="form-select form-select-sm">
        <option value="">ステータス（全て）</option>
        <?php foreach (['trial'=>'トライアル','active'=>'契約中','past_due'=>'支払遅延','suspended'=>'停止','canceled'=>'解約済'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $f_status===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <select name="plan" class="form-select form-select-sm">
        <option value="">プラン（全て）</option>
        <?php foreach (['solo','standard','pro','enterprise'] as $k): ?>
        <option value="<?= $k ?>" <?= $f_plan===$k?'selected':'' ?>><?= $specs[$k]['label'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-primary">検索</button>
      <a href="index.php" class="btn btn-sm btn-outline-secondary ms-1">リセット</a>
    </div>
  </form>

  <div class="text-muted small mb-2"><?= count($tenants) ?> 件表示</div>

  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>事務所名</th>
          <th>代表/メール</th>
          <th>プラン</th>
          <th>ステータス</th>
          <th class="text-end">U / C / Ca</th>
          <th class="text-end">累計売上</th>
          <th>登録日</th>
          <th>トライアル</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($tenants)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">該当するテナントがありません。</td></tr>
      <?php else: foreach ($tenants as $t):
        $trial_left = null;
        if ($t['trial_ends_at']) $trial_left = (int)((strtotime($t['trial_ends_at']) - time()) / 86400);
      ?>
        <tr>
          <td>
            <a href="view.php?id=<?= $t['id'] ?>" class="fw-bold text-decoration-none"><?= h($t['name']) ?></a>
            <div class="small text-muted"><code><?= h($t['slug']) ?></code></div>
          </td>
          <td>
            <?= h($t['contact_name'] ?: '—') ?>
            <div class="small text-muted"><?= h($t['contact_email']) ?></div>
          </td>
          <td>
            <span class="badge bg-info"><?= h($specs[$t['plan']]['label'] ?? $t['plan']) ?></span><br>
            <small class="text-muted"><?= $t['billing_cycle']==='yearly'?'年払い':'月払い' ?></small>
          </td>
          <td><?= status_badge2($t['status']) ?></td>
          <td class="text-end small">
            <?= $t['users_cnt'] ?> / <?= $t['clients_cnt'] ?> / <?= $t['cases_cnt'] ?>
          </td>
          <td class="text-end fw-bold">¥<?= number_format((int)$t['revenue']) ?></td>
          <td class="small"><?= fmt_date($t['created_at']) ?></td>
          <td class="small">
            <?php if ($trial_left !== null && in_array($t['status'], ['trial','past_due'], true)): ?>
              <?= $trial_left >= 0 ? "残{$trial_left}日" : "<span class='text-danger'>切れ</span>" ?>
            <?php else: ?>
              ―
            <?php endif; ?>
          </td>
          <td class="small">
            <a href="view.php?id=<?= $t['id'] ?>" class="btn btn-xs btn-sm btn-outline-secondary py-0 px-2">詳細</a>
            <?php if ($t['status'] === 'suspended'): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('停止解除しますか？')">
                <input type="hidden" name="_action" value="reactivate">
                <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                <button class="btn btn-xs btn-sm btn-outline-success py-0 px-2">解除</button>
              </form>
            <?php elseif (!in_array($t['status'], ['canceled'], true)): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('このテナントを停止しますか？')">
                <input type="hidden" name="_action" value="suspend">
                <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                <button class="btn btn-xs btn-sm btn-outline-danger py-0 px-2">停止</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <p class="small text-muted mt-2 mb-0">U=ユーザー / C=依頼者 / Ca=案件</p>
</div>

<?php require __DIR__ . '/../../includes/_footer.php'; ?>
