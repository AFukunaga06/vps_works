<?php
/**
 * Stripe Checkout 成功時のリダイレクト先。
 * Webhook で正式に有効化されるが、ここで session 検証して即座にUI反映する。
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/_lib.php';

$user   = lc_require_login();
$tenant = lc_current_tenant();

$session_id = $_GET['session_id'] ?? '';
$activated = false;

if ($session_id && STRIPE_SECRET_KEY) {
    $res = billing_http_request('GET',
        'https://api.stripe.com/v1/checkout/sessions/' . urlencode($session_id) . '?expand[]=subscription',
        ['headers' => ['Authorization: Bearer ' . STRIPE_SECRET_KEY]]
    );
    if ($res['status'] === 200) {
        $s = json_decode($res['body'], true);
        if (($s['payment_status'] ?? '') === 'paid' || ($s['status'] ?? '') === 'complete') {
            $plan  = $s['metadata']['plan']  ?? $tenant['plan'];
            $cycle = $s['metadata']['cycle'] ?? $tenant['billing_cycle'];
            $sub_id = $s['subscription']['id'] ?? ($s['subscription'] ?? '');
            billing_activate_tenant((int)$tenant['id'], $plan, $cycle, (string)$sub_id);
            $activated = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja"><head>
<meta charset="UTF-8">
<title>決済完了 - LegalDesk</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head><body class="bg-light">
<div class="container py-5" style="max-width:560px;">
  <div class="card border-0 shadow-sm">
    <div class="card-body text-center p-5">
      <i class="bi bi-check-circle-fill text-success" style="font-size:4rem"></i>
      <h1 class="h4 fw-bold mt-3">ご契約ありがとうございます</h1>
      <p class="text-muted">
        <?= $activated
              ? '決済が完了しました。すべての機能をご利用いただけます。'
              : '決済処理を受け付けました。反映までしばらくお待ちください。' ?>
      </p>
      <a class="btn btn-primary" href="<?= LC_BASE_URL ?>/index.php">ダッシュボードに戻る</a>
    </div>
  </div>
</div>
</body></html>
