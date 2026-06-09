<?php
/**
 * Stripe Webhook受信エンドポイント。
 *
 * Stripe Dashboard > Developers > Webhooks に以下URLを登録:
 *   POST  {LC_PUBLIC_URL}/billing/stripe_webhook.php
 * 受信イベント:
 *   - checkout.session.completed     (初回支払い完了)
 *   - invoice.paid                   (継続支払い)
 *   - customer.subscription.deleted  (解約)
 *   - customer.subscription.updated  (プラン変更等)
 */
require_once __DIR__ . '/_lib.php';

$payload    = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!STRIPE_WEBHOOK_SECRET || !stripe_verify_webhook($payload, $sig_header, STRIPE_WEBHOOK_SECRET)) {
    http_response_code(400);
    error_log('[stripe_webhook] signature verification failed');
    exit('signature failed');
}

$event = json_decode($payload, true);
$type  = $event['type'] ?? '';
$obj   = $event['data']['object'] ?? [];

try {
    switch ($type) {
        case 'checkout.session.completed':
            $tenant_id = (int)preg_replace('/\D/', '', $obj['client_reference_id'] ?? '0');
            $plan      = $obj['metadata']['plan']  ?? '';
            $cycle     = $obj['metadata']['cycle'] ?? 'monthly';
            $sub_id    = $obj['subscription']      ?? '';
            if ($tenant_id && $plan) {
                billing_activate_tenant($tenant_id, $plan, $cycle, (string)$sub_id);
                error_log("[stripe_webhook] activated tenant={$tenant_id} plan={$plan} sub={$sub_id}");
            }
            break;

        case 'invoice.paid':
            // 月額/年額の自動継続支払
            $sub_id = $obj['subscription'] ?? '';
            if ($sub_id) {
                $stmt = get_db()->prepare("SELECT id, plan, billing_cycle FROM lc_tenants WHERE subscription_id=?");
                $stmt->execute([$sub_id]);
                $t = $stmt->fetch();
                if ($t) {
                    $period_start = date('Y-m-d', $obj['period_start'] ?? time());
                    $period_end   = date('Y-m-d', $obj['period_end']   ?? time());
                    billing_record_invoice(
                        (int)$t['id'], $t['plan'], $t['billing_cycle'],
                        (int)(($obj['amount_paid'] ?? 0)),
                        $period_start, $period_end,
                        'stripe', (string)($obj['id'] ?? ''), 'paid'
                    );
                }
            }
            break;

        case 'invoice.payment_failed':
            $sub_id = $obj['subscription'] ?? '';
            if ($sub_id) {
                get_db()->prepare("UPDATE lc_tenants SET status='past_due' WHERE subscription_id=?")
                    ->execute([$sub_id]);
            }
            break;

        case 'customer.subscription.deleted':
            $sub_id = $obj['id'] ?? '';
            if ($sub_id) {
                get_db()->prepare("UPDATE lc_tenants SET status='canceled', canceled_at=NOW() WHERE subscription_id=?")
                    ->execute([$sub_id]);
            }
            break;

        case 'customer.subscription.updated':
            $sub_id = $obj['id'] ?? '';
            $status = $obj['status'] ?? '';
            $map = ['active'=>'active','trialing'=>'trial','past_due'=>'past_due','canceled'=>'canceled','unpaid'=>'past_due'];
            if ($sub_id && isset($map[$status])) {
                get_db()->prepare("UPDATE lc_tenants SET status=? WHERE subscription_id=?")
                    ->execute([$map[$status], $sub_id]);
            }
            break;
    }
} catch (Throwable $e) {
    error_log('[stripe_webhook] error: ' . $e->getMessage());
    http_response_code(500);
    exit('error');
}

http_response_code(200);
echo 'ok';
