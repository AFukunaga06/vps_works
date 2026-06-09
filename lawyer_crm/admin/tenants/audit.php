<?php
/**
 * 運営管理: 全テナント横断の監査ログビューア
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/tenant.php';

$lc_user = lc_require_super_admin();
$page_title = '監査ログ（全テナント）';
$page_nav   = 'superadmin_audit';

$db   = get_db();
$f_tenant = (int)($_GET['tenant_id'] ?? 0);
$f_action = $_GET['action'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 50;
$off  = ($page - 1) * $per;

$where = ['1=1']; $params = [];
if ($f_tenant) { $where[] = 'a.tenant_id=?'; $params[] = $f_tenant; }
if ($f_action) { $where[] = 'a.action=?';    $params[] = $f_action; }
$w = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM lc_audit_logs a WHERE $w");
$total->execute($params);
$total = (int)$total->fetchColumn();

$stmt = $db->prepare(
    "SELECT a.*, t.name AS tenant_name, u.name AS user_name, u.email AS user_email
     FROM lc_audit_logs a
     LEFT JOIN lc_tenants t ON t.id=a.tenant_id
     LEFT JOIN lc_users u ON u.id=a.user_id
     WHERE $w
     ORDER BY a.id DESC LIMIT $per OFFSET $off"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// テナント一覧（フィルタ用）
$tenants = $db->query("SELECT id, name FROM lc_tenants ORDER BY name")->fetchAll();
$actions = $db->query("SELECT DISTINCT action FROM lc_audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/../../includes/_header.php';
?>

<div class="page-card">
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
      <select name="tenant_id" class="form-select form-select-sm">
        <option value="">テナント（全て）</option>
        <?php foreach ($tenants as $t): ?>
        <option value="<?= $t['id'] ?>" <?= $f_tenant===(int)$t['id']?'selected':'' ?>><?= h($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <select name="action" class="form-select form-select-sm">
        <option value="">アクション（全て）</option>
        <?php foreach ($actions as $a): ?>
        <option value="<?= h($a) ?>" <?= $f_action===$a?'selected':'' ?>><?= h($a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-primary">絞り込み</button>
      <a href="audit.php" class="btn btn-sm btn-outline-secondary ms-1">リセット</a>
    </div>
    <div class="col text-md-end small text-muted">全 <?= number_format($total) ?> 件</div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead class="table-light">
        <tr>
          <th>日時</th>
          <th>テナント</th>
          <th>ユーザー</th>
          <th>アクション</th>
          <th>対象</th>
          <th>詳細</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">監査ログがありません。<br><small>監査ログは Standard 以上のプランで自動記録されます。</small></td></tr>
      <?php else: foreach ($logs as $l): ?>
        <tr>
          <td class="small"><?= fmt_datetime($l['created_at']) ?></td>
          <td><a href="view.php?id=<?= $l['tenant_id'] ?>" class="small"><?= h($l['tenant_name']) ?></a></td>
          <td class="small"><?= h($l['user_name'] ?? '—') ?></td>
          <td><span class="badge bg-info"><?= h($l['action']) ?></span></td>
          <td class="small">
            <?= h($l['target_type']) ?>
            <?= $l['target_id'] ? '#' . $l['target_id'] : '' ?>
          </td>
          <td class="small text-muted" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= h($l['detail'] ?? '') ?>
          </td>
          <td class="small"><code><?= h($l['ip']) ?></code></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php $pages = (int)ceil($total / $per); if ($pages > 1): ?>
  <nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
      <?php for ($i = max(1, $page-3); $i <= min($pages, $page+3); $i++): ?>
      <li class="page-item <?= $i===$page?'active':'' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge(['tenant_id'=>$f_tenant,'action'=>$f_action,'page'=>$i])) ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/_footer.php'; ?>
