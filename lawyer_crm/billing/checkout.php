<?php
/**
 * 決済開始エンドポイント
 *  - method=stripe → Stripe Checkout（月額サブスク）
 *  - method=paypay → PayPay QR決済（1回分の前払い）
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/_lib.php';

$user   = lc_require_login();
$tenant = lc_current_tenant();
if (!$tenant) { header('Location: ' . LC_BASE_URL . '/login.php'); exit; }
if ($user['role'] !== 'admin') {
    http_response_code(403); echo '管理者のみ操作可能です。'; exit;
}

$method = $_GET['method'] ?? 'stripe';
$plan   = $_GET['plan']   ?? $tenant['plan'];
$cycle  = $_GET['cycle']  ?? $tenant['billing_cycle'];

$specs = lc_plan_specs();
if (!isset($specs[$plan]) || !in_array($cycle, ['monthly','yearly'], true)) {
    http_response_code(400); echo 'プラン指定が不正です。'; exit;
}

if ($method === 'stripe') {
    $r = stripe_create_checkout_session($tenant, $plan, $cycle);
    if (!empty($r['error'])) { echo h_safe($r['error']); exit; }
    header('Location: ' . $r['url']); exit;
}

if ($method === 'paypay') {
    $amount = $cycle === 'yearly' ? $specs[$plan]['price_yearly'] : $specs[$plan]['price_monthly'];
    if (!$amount) { http_response_code(400); echo 'このプランはPayPayでは購入できません。'; exit; }

    $merchant_payment_id = 'lc_' . $tenant['id'] . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
    $payload = [
        'merchantPaymentId' => $merchant_payment_id,
        'amount'            => ['amount' => (int)$amount, 'currency' => 'JPY'],
        'codeType'          => 'ORDER_QR',
        'orderDescription'  => "LegalDesk {$specs[$plan]['label']} ($cycle)",
        'isAuthorization'   => false,
        'redirectUrl'       => LC_PUBLIC_URL . '/billing/paypay_return.php?mpid=' . urlencode($merchant_payment_id),
        'redirectType'      => 'WEB_LINK',
        'userAgent'         => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];
    $r = paypay_create_qr($payload);
    if (!empty($r['error'])) { echo h_safe($r['error']) . '<br><pre>' . h_safe($r['detail'] ?? '') . '</pre>'; exit; }

    // 請求レコード（draft）を先に作成
    billing_record_invoice(
        (int)$tenant['id'], $plan, $cycle, (int)$amount,
        date('Y-m-d'), date('Y-m-d', strtotime($cycle === 'yearly' ? '+1 year' : '+1 month')),
        'paypay', $merchant_payment_id, 'open'
    );

    $url = $r['data']['url'] ?? '';
    if (!$url) { echo 'PayPay決済URLの取得に失敗しました。'; exit; }
    header('Location: ' . $url); exit;
}

http_response_code(400); echo '決済方法の指定が不正です。';

function h_safe(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
