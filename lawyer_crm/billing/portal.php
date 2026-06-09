<?php
/**
 * テナント向け課金ポータル
 *  - 現在のプラン / ステータス / トライアル残日数
 *  - プラン変更（Stripe Checkout / PayPay）
 *  - Stripe Customer Portal へのリンク（解約・カード変更等）
 *  - 請求履歴
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/_lib.php';

$lc_user   = lc_require_login();
$tenant    = lc_current_tenant();
$page_title = '課金・プラン管理';
$page_nav   = 'billing';

$specs = lc_plan_specs();
$current_plan = $tenant['plan'] ?? 'solo';
$cycle = $tenant['billing_cycle'] ?? 'monthly';
$status = $tenant['status'] ?? 'trial';

// トライアル残日数
$trial_days_left = null;
if (!empty($tenant['trial_ends_at'])) {
    $trial_days_left = (int)((strtotime($tenant['trial_ends_at']) - time()) / 86400);
}

// 請求履歴
$invoices = get_db()->prepare("SELECT * FROM lc_tenant_invoices WHERE tenant_id=? ORDER BY id DESC LIMIT 20");
$invoices->execute([$tenant['id']]);
$invoices = $invoices->fetchAll();

// ヘルパー: ステータスバッジ
function status_badge(string $s): string {
    $map = [
        'trial' => ['secondary','トライアル中'],
        'active' => ['success','契約中'],
        'past_due' => ['warning','支払遅延'],
        'suspended' => ['danger','停止中'],
        'canceled' => ['dark','解約済'],
    ];
    [$cls, $label] = $map[$s] ?? ['secondary', $s];
    return "<span class='badge bg-{$cls}'>" . htmlspecialchars($label) . "</span>";
}

require __DIR__ . '/../includes/_header.php';

$config_issues = billing_check_config();
?>

<?php if ($config_issues && $lc_user['role'] === 'admin'): ?>
<div class="alert alert-warning small">
  <i class="bi bi-exclamation-triangle me-1"></i>
  決済設定が一部未完了です: <?= htmlspecialchars(implode(' / ', $config_issues)) ?>
</div>
<?php endif; ?>

<!-- 現在のプラン -->
<div class="page-card mb-3">
  <h2 class="h6 fw-bold mb-3"><i class="bi bi-shield-check me-1 text-primary"></i>現在のプラン</h2>
  <div class="row g-3">
    <div class="col-md-3">
      <small class="text-muted d-block">事務所名</small>
      <div class="fw-bold"><?= htmlspecialchars($tenant['name']) ?></div>
    </div>
    <div class="col-md-2">
      <small class="text-muted d-block">プラン</small>
      <div class="fw-bold text-primary"><?= htmlspecialchars($specs[$current_plan]['label']) ?></div>
    </div>
    <div class="col-md-2">
      <small class="text-muted d-block">支払サイクル</small>
      <div><?= $cycle === 'yearly' ? '年払い' : '月払い' ?></div>
    </div>
    <div class="col-md-2">
      <small class="text-muted d-block">ステータス</small>
      <div><?= status_badge($status) ?></div>
    </div>
    <div class="col-md-3">
      <small class="text-muted d-block">トライアル</small>
      <div>
        <?php if ($trial_days_left !== null && $status === 'trial'): ?>
          <?= $trial_days_left >= 0 ? "残り {$trial_days_left} 日" : "<span class='text-danger'>期限切れ</span>" ?>
        <?php else: ?>
          ―
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($tenant['subscription_id'])): ?>
  <div class="mt-3">
    <form method="post" action="customer_portal.php" class="d-inline">
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-credit-card me-1"></i>カード情報・解約管理</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<!-- プラン変更 -->
<?php if ($lc_user['role'] === 'admin'): ?>
<div class="page-card mb-3">
  <h2 class="h6 fw-bold mb-3"><i class="bi bi-arrow-repeat me-1 text-primary"></i>プラン変更・契約</h2>

  <div class="btn-group mb-3" role="group">
    <input type="radio" class="btn-check" name="plan_cycle" id="cyc_m" value="monthly" <?= $cycle==='monthly'?'checked':'' ?>>
    <label class="btn btn-outline-primary btn-sm" for="cyc_m">月払い</label>
    <input type="radio" class="btn-check" name="plan_cycle" id="cyc_y" value="yearly" <?= $cycle==='yearly'?'checked':'' ?>>
    <label class="btn btn-outline-primary btn-sm" for="cyc_y">年払い（2か月分お得）</label>
  </div>

  <div class="row g-3">
    <?php foreach (['solo','standard','pro'] as $p):
      $sp = $specs[$p];
      $active = $p === $current_plan;
    ?>
    <div class="col-md-4">
      <div class="border rounded p-3 h-100 <?= $active ? 'border-primary bg-light' : '' ?>">
        <div class="d-flex justify-content-between">
          <h3 class="h6 fw-bold mb-1"><?= htmlspecialchars($sp['label']) ?></h3>
          <?php if ($active): ?><span class="badge bg-primary">現在</span><?php endif; ?>
        </div>
        <div class="fs-4 fw-bold text-primary plan-price" data-monthly="<?= $sp['price_monthly'] ?>" data-yearly="<?= $sp['price_yearly'] ?>">
          <?= number_format($sp['price_monthly']) ?>円<small class="text-muted fs-6">/月</small>
        </div>
        <ul class="small text-muted mb-3">
          <li>ユーザー <?= $sp['max_users'] ?>名</li>
          <li>依頼者 <?= $sp['max_clients'] !== null ? $sp['max_clients'].'件' : '無制限' ?></li>
          <li>案件 <?= $sp['max_cases'] !== null ? $sp['max_cases'].'件' : '無制限' ?></li>
          <li>ストレージ <?= number_format($sp['storage_mb']/1000, 1) ?>GB</li>
        </ul>
        <div class="d-grid gap-2">
          <a class="btn btn-sm btn-primary plan-link"
             data-plan="<?= $p ?>"
             href="checkout.php?method=stripe&plan=<?= $p ?>&cycle=<?= $cycle ?>">
            <i class="bi bi-credit-card-2-front me-1"></i>クレジットカードで申込
          </a>
          <a class="btn btn-sm btn-outline-danger plan-link"
             data-plan="<?= $p ?>"
             href="checkout.php?method=paypay&plan=<?= $p ?>&cycle=<?= $cycle ?>"
             style="color:#FF0033;border-color:#FF0033;">
            <i class="bi bi-phone me-1"></i>PayPayで申込
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="small text-muted mt-3 mb-0">
    ※ プラン変更は次回請求から反映されます。アップグレードは即時有効、ダウングレードは現在の請求期間終了後に反映。
  </p>
</div>
<?php endif; ?>

<!-- 請求履歴 -->
<div class="page-card">
  <h2 class="h6 fw-bold mb-3"><i class="bi bi-receipt me-1 text-primary"></i>請求履歴</h2>
  <?php if (empty($invoices)): ?>
    <p class="text-muted small mb-0">まだ請求履歴はありません。</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead class="table-light">
          <tr><th>請求番号</th><th>期間</th><th>プラン</th><th class="text-end">金額</th><th>支払</th><th>状態</th></tr>
        </thead>
        <tbody>
        <?php foreach ($invoices as $inv): ?>
          <tr>
            <td><code class="small"><?= htmlspecialchars($inv['invoice_no']) ?></code></td>
            <td class="small"><?= htmlspecialchars($inv['period_start']) ?> 〜 <?= htmlspecialchars($inv['period_end']) ?></td>
            <td><?= htmlspecialchars($specs[$inv['plan']]['label'] ?? $inv['plan']) ?> / <?= $inv['billing_cycle']==='yearly'?'年':'月' ?></td>
            <td class="text-end">¥<?= number_format((int)$inv['amount']) ?></td>
            <td><?= htmlspecialchars(strtoupper($inv['payment_method'])) ?></td>
            <td>
              <?php
              $sm = ['draft'=>'下書','open'=>'未払','paid'=>'支払済','void'=>'無効','uncollectible'=>'回収不能'];
              $sc = ['draft'=>'secondary','open'=>'warning','paid'=>'success','void'=>'dark','uncollectible'=>'danger'];
              echo "<span class='badge bg-".($sc[$inv['status']]??'secondary')."'>".($sm[$inv['status']]??$inv['status'])."</span>";
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
// 月/年切替で価格・リンクを更新
const radios = document.querySelectorAll('input[name="plan_cycle"]');
const prices = document.querySelectorAll('.plan-price');
const links  = document.querySelectorAll('.plan-link');
function applyCycle(cycle) {
  prices.forEach(el => {
    const v = cycle === 'yearly' ? el.dataset.yearly : el.dataset.monthly;
    el.innerHTML = Number(v).toLocaleString() + '円<small class="text-muted fs-6">/' + (cycle==='yearly'?'年':'月') + '</small>';
  });
  links.forEach(a => {
    a.href = a.href.replace(/cycle=[a-z]+/, 'cycle=' + cycle);
  });
}
radios.forEach(r => r.addEventListener('change', () => applyCycle(r.value)));
</script>

<?php require __DIR__ . '/../includes/_footer.php'; ?>
