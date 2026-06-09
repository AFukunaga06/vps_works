<?php
/**
 * PayPay 決済完了後のリダイレクト先。
 * mpid=merchantPaymentId をクエリで受け取り、APIで状態確認 → DB反映。
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/_lib.php';

$user   = lc_require_login();
$tenant = lc_current_tenant();
$mpid   = $_GET['mpid'] ?? '';

$result = ['ok' => false, 'msg' => ''];

if ($mpid) {
    // DB上の請求レコード取得
    $stmt = get_db()->prepare("SELECT * FROM lc_tenant_invoices WHERE external_id=? AND tenant_id=?");
    $stmt->execute([$mpid, $tenant['id']]);
    $inv = $stmt->fetch();

    $r = paypay_get_payment($mpid);
    $status = $r['data']['status'] ?? '';

    if ($status === 'COMPLETED' && $inv) {
        // 支払完了
        get_db()->prepare("UPDATE lc_tenant_invoices SET status='paid', paid_at=NOW() WHERE id=?")
            ->execute([$inv['id']]);
        // テナントをactiveに
        billing_activate_tenant((int)$tenant['id'], $inv['plan'], $inv['billing_cycle'], '');
        $result = ['ok' => true, 'msg' => 'PayPayでの決済が完了しました。'];
    } elseif ($status === 'CANCELED' || $status === 'FAILED' || $status === 'EXPIRED') {
        if ($inv) {
            get_db()->prepare("UPDATE lc_tenant_invoices SET status='void' WHERE id=?")
                ->execute([$inv['id']]);
        }
        $result = ['ok' => false, 'msg' => '決済がキャンセルまたは失敗しました。'];
    } else {
        $result = ['ok' => false, 'msg' => '決済はまだ完了していません。少し時間をおいてご確認ください。'];
    }
}

function he2(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ja"><head>
<meta charset="UTF-8">
<title>PayPay決済結果 - LegalDesk</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head><body class="bg-light">
<div class="container py-5" style="max-width:560px;">
  <div class="card border-0 shadow-sm">
    <div class="card-body text-center p-5">
      <?php if ($result['ok']): ?>
        <i class="bi bi-check-circle-fill text-success" style="font-size:4rem"></i>
        <h1 class="h4 fw-bold mt-3">決済完了</h1>
      <?php else: ?>
        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:4rem"></i>
        <h1 class="h4 fw-bold mt-3">決済結果</h1>
      <?php endif; ?>
      <p class="text-muted"><?= he2($result['msg']) ?></p>
      <a class="btn btn-primary" href="<?= LC_BASE_URL ?>/index.php">ダッシュボードに戻る</a>
    </div>
  </div>
</div>
</body></html>
