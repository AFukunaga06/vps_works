<?php
/**
 * 運営管理: テナント詳細
 *  - 基本情報・プラン編集
 *  - ユーザー一覧（このテナント所属）
 *  - 課金履歴
 *  - 利用状況
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/tenant.php';

$lc_user = lc_require_super_admin();
$page_nav = 'superadmin_tenants';

$db   = get_db();
$id   = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM lc_tenants WHERE id=?");
$stmt->execute([$id]);
$t = $stmt->fetch();
if (!$t) { http_response_code(404); exit('テナントが見つかりません。'); }

$page_title = 'テナント: ' . $t['name'];

// 編集処理
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    if ($action === 'update') {
        $specs = lc_plan_specs();
        $plan  = $_POST['plan'] ?? $t['plan'];
        $cycle = $_POST['billing_cycle'] ?? $t['billing_cycle'];
        $status = $_POST['status'] ?? $t['status'];
        $sp = $specs[$plan] ?? null;
        if ($sp) {
            $db->prepare("UPDATE lc_tenants SET
                name=?, contact_name=?, contact_email=?, contact_tel=?,
                plan=?, billing_cycle=?, status=?,
                max_users=?, max_clients=?, max_cases=?, storage_quota_mb=?,
                trial_ends_at=?, memo=?
                WHERE id=?")->execute([
                trim($_POST['name'] ?? ''),
                trim($_POST['contact_name'] ?? ''),
                trim($_POST['contact_email'] ?? ''),
                trim($_POST['contact_tel'] ?? ''),
                $plan, $cycle, $status,
                (int)($_POST['max_users'] ?? $sp['max_users']),
                $_POST['max_clients'] === '' ? null : (int)$_POST['max_clients'],
                $_POST['max_cases'] === '' ? null : (int)$_POST['max_cases'],
                (int)($_POST['storage_quota_mb'] ?? $sp['storage_mb']),
                $_POST['trial_ends_at'] ?: null,
                $_POST['memo'] ?? '',
                $id,
            ]);
            $msg = '保存しました。';
            // 再読込
            $stmt->execute([$id]); $t = $stmt->fetch();
        }
    } elseif ($action === 'reset_password') {
        $uid = (int)$_POST['uid'];
        $newpw = bin2hex(random_bytes(6));
        $db->prepare("UPDATE lc_users SET password_hash=? WHERE id=? AND tenant_id=?")
            ->execute([password_hash($newpw, PASSWORD_DEFAULT), $uid, $id]);
        $msg = "パスワードをリセットしました（新パスワード: <code>$newpw</code> ）";
    }
}

// ユーザー
$users = $db->prepare("SELECT * FROM lc_users WHERE tenant_id=? ORDER BY role,name");
$users->execute([$id]); $users = $users->fetchAll();

// 請求履歴
$invoices = $db->prepare("SELECT * FROM lc_tenant_invoices WHERE tenant_id=? ORDER BY id DESC LIMIT 50");
$invoices->execute([$id]); $invoices = $invoices->fetchAll();

// 利用状況
$usage = [
    'users'    => count(array_filter($users, fn($u) => $u['is_active'])),
    'clients'  => (int)$db->query("SELECT COUNT(*) FROM lc_clients WHERE tenant_id={$id}")->fetchColumn(),
    'cases'    => (int)$db->query("SELECT COUNT(*) FROM lc_cases WHERE tenant_id={$id}")->fetchColumn(),
    'documents'=> (int)$db->query("SELECT COUNT(*) FROM lc_documents WHERE tenant_id={$id}")->fetchColumn(),
];

$specs = lc_plan_specs();

require __DIR__ . '/../../includes/_header.php';
?>

<a href="index.php" class="small text-muted mb-2 d-inline-block">&larr; テナント一覧に戻る</a>

<?php if ($msg): ?>
<div class="alert alert-success small"><?= $msg ?></div>
<?php endif; ?>

<div class="row g-3">
  <!-- 編集フォーム -->
  <div class="col-lg-8">
    <div class="page-card">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-pencil-square me-1"></i>基本情報</h2>
      <form method="post">
        <input type="hidden" name="_action" value="update">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small">事務所名</label>
            <input type="text" name="name" class="form-control form-control-sm" value="<?= h($t['name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small">slug（URL識別子）</label>
            <input type="text" class="form-control form-control-sm" value="<?= h($t['slug']) ?>" disabled>
          </div>
          <div class="col-md-4">
            <label class="form-label small">代表者氏名</label>
            <input type="text" name="contact_name" class="form-control form-control-sm" value="<?= h($t['contact_name']) ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label small">代表メール</label>
            <input type="email" name="contact_email" class="form-control form-control-sm" value="<?= h($t['contact_email']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label small">代表電話</label>
            <input type="tel" name="contact_tel" class="form-control form-control-sm" value="<?= h($t['contact_tel']) ?>">
          </div>

          <hr class="my-2">

          <div class="col-md-4">
            <label class="form-label small">プラン</label>
            <select name="plan" class="form-select form-select-sm">
              <?php foreach ($specs as $k => $sp): ?>
              <option value="<?= $k ?>" <?= $t['plan']===$k?'selected':'' ?>><?= h($sp['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">支払サイクル</label>
            <select name="billing_cycle" class="form-select form-select-sm">
              <option value="monthly" <?= $t['billing_cycle']==='monthly'?'selected':'' ?>>月払い</option>
              <option value="yearly"  <?= $t['billing_cycle']==='yearly'?'selected':'' ?>>年払い</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">ステータス</label>
            <select name="status" class="form-select form-select-sm">
              <?php foreach (['trial'=>'トライアル','active'=>'契約中','past_due'=>'支払遅延','suspended'=>'停止','canceled'=>'解約済'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $t['status']===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label small">ユーザー上限</label>
            <input type="number" name="max_users" class="form-control form-control-sm" value="<?= (int)$t['max_users'] ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label small">依頼者上限（空=無制限）</label>
            <input type="number" name="max_clients" class="form-control form-control-sm" value="<?= $t['max_clients'] !== null ? (int)$t['max_clients'] : '' ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label small">案件上限（空=無制限）</label>
            <input type="number" name="max_cases" class="form-control form-control-sm" value="<?= $t['max_cases'] !== null ? (int)$t['max_cases'] : '' ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label small">ストレージ(MB)</label>
            <input type="number" name="storage_quota_mb" class="form-control form-control-sm" value="<?= (int)$t['storage_quota_mb'] ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label small">トライアル終了日時</label>
            <input type="datetime-local" name="trial_ends_at" class="form-control form-control-sm"
                   value="<?= $t['trial_ends_at'] ? date('Y-m-d\TH:i', strtotime($t['trial_ends_at'])) : '' ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small">SubscriptionID（参照のみ）</label>
            <input type="text" class="form-control form-control-sm" value="<?= h($t['subscription_id']) ?>" disabled>
          </div>

          <div class="col-12">
            <label class="form-label small">運営メモ</label>
            <textarea name="memo" class="form-control form-control-sm" rows="2"><?= h($t['memo'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="mt-3">
          <button class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>保存</button>
        </div>
      </form>
    </div>

    <!-- ユーザー -->
    <div class="page-card mt-3">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-people me-1"></i>所属ユーザー（<?= count($users) ?>）</h2>
      <table class="table table-sm">
        <thead class="table-light"><tr><th>氏名</th><th>メール</th><th>ロール</th><th>状態</th><th>登録日</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= h($u['name']) ?>
            <?php if ($u['is_super_admin']): ?><span class="badge bg-warning text-dark">運営</span><?php endif; ?>
          </td>
          <td><small><?= h($u['email']) ?></small></td>
          <td><span class="badge bg-secondary"><?= h(ROLE_MAP[$u['role']] ?? $u['role']) ?></span></td>
          <td><?= $u['is_active'] ? '<span class="badge bg-success">有効</span>' : '<span class="badge bg-secondary">無効</span>' ?></td>
          <td class="small"><?= fmt_date($u['created_at']) ?></td>
          <td>
            <form method="post" class="d-inline" onsubmit="return confirm('パスワードをリセットしますか？')">
              <input type="hidden" name="_action" value="reset_password">
              <input type="hidden" name="uid" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-sm btn-outline-warning py-0 px-2">PWリセット</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 請求履歴 -->
    <div class="page-card mt-3">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-receipt me-1"></i>請求履歴（<?= count($invoices) ?>）</h2>
      <?php if (empty($invoices)): ?>
        <p class="text-muted small mb-0">請求履歴はありません。</p>
      <?php else: ?>
        <table class="table table-sm">
          <thead class="table-light"><tr><th>請求番号</th><th>期間</th><th>プラン</th><th class="text-end">金額</th><th>支払</th><th>状態</th><th>登録</th></tr></thead>
          <tbody>
          <?php foreach ($invoices as $inv): ?>
          <tr>
            <td><code class="small"><?= h($inv['invoice_no']) ?></code></td>
            <td class="small"><?= h($inv['period_start']) ?>〜<?= h($inv['period_end']) ?></td>
            <td><?= h($specs[$inv['plan']]['label'] ?? $inv['plan']) ?>/<?= $inv['billing_cycle']==='yearly'?'年':'月' ?></td>
            <td class="text-end">¥<?= number_format((int)$inv['amount']) ?></td>
            <td class="small"><?= h(strtoupper($inv['payment_method'])) ?></td>
            <td><span class="badge bg-<?= ['paid'=>'success','open'=>'warning','draft'=>'secondary','void'=>'dark','uncollectible'=>'danger'][$inv['status']] ?? 'secondary' ?>"><?= h($inv['status']) ?></span></td>
            <td class="small"><?= fmt_date($inv['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- サイドカード -->
  <div class="col-lg-4">
    <div class="page-card">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-bar-chart me-1"></i>利用状況</h2>
      <table class="table table-sm mb-0">
        <tr><th>ユーザー</th><td class="text-end"><?= $usage['users'] ?> / <?= (int)$t['max_users'] ?></td></tr>
        <tr><th>依頼者</th><td class="text-end"><?= $usage['clients'] ?> / <?= $t['max_clients'] !== null ? (int)$t['max_clients'] : '∞' ?></td></tr>
        <tr><th>案件</th><td class="text-end"><?= $usage['cases'] ?> / <?= $t['max_cases'] !== null ? (int)$t['max_cases'] : '∞' ?></td></tr>
        <tr><th>書類</th><td class="text-end"><?= $usage['documents'] ?> 件</td></tr>
        <tr><th>ストレージ</th><td class="text-end"><?= number_format((int)$t['storage_used_mb']) ?> / <?= number_format((int)$t['storage_quota_mb']) ?> MB</td></tr>
      </table>
    </div>

    <div class="page-card mt-3">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-clock-history me-1"></i>履歴</h2>
      <dl class="small mb-0">
        <dt>登録日</dt><dd><?= fmt_datetime($t['created_at']) ?></dd>
        <dt>最終更新</dt><dd><?= fmt_datetime($t['updated_at']) ?></dd>
        <?php if ($t['canceled_at']): ?>
        <dt>解約日</dt><dd><?= fmt_datetime($t['canceled_at']) ?></dd>
        <?php endif; ?>
        <dt>トライアル終了</dt><dd><?= $t['trial_ends_at'] ? fmt_datetime($t['trial_ends_at']) : '―' ?></dd>
      </dl>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/_footer.php'; ?>
