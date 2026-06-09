<?php
/**
 * 決済共通ヘルパー（cURLベース、composer不要）
 */
require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/../includes/db.php';

/**
 * 共通HTTPリクエスト
 */
function billing_http_request(string $method, string $url, array $opts = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => $opts['headers'] ?? [],
    ]);
    if (!empty($opts['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['status' => $code, 'body' => $body, 'error' => $err];
}

// ============================================================
// Stripe
// ============================================================

/**
 * Stripe Checkout セッション作成
 *
 * @param array $tenant tenant行
 * @param string $plan  solo|standard|pro
 * @param string $cycle monthly|yearly
 * @return array ['url'=>checkout_url, 'session_id'=>...] or ['error'=>...]
 */
function stripe_create_checkout_session(array $tenant, string $plan, string $cycle): array {
    if (!STRIPE_SECRET_KEY) return ['error' => 'Stripe未設定です。管理者にお問合せください。'];
    $price_id = STRIPE_PRICE_IDS[$cycle][$plan] ?? '';
    if (!$price_id) return ['error' => "プラン {$plan}/{$cycle} の Price ID が未設定です。"];

    $params = http_build_query([
        'mode'                  => 'subscription',
        'line_items[0][price]'  => $price_id,
        'line_items[0][quantity]' => 1,
        'customer_email'        => $tenant['contact_email'],
        'client_reference_id'   => 'tenant_' . $tenant['id'],
        'metadata[tenant_id]'   => $tenant['id'],
        'metadata[plan]'        => $plan,
        'metadata[cycle]'       => $cycle,
        'subscription_data[trial_period_days]' => $tenant['status'] === 'trial' ? 14 : 0,
        'subscription_data[metadata][tenant_id]' => $tenant['id'],
        'success_url'           => LC_PUBLIC_URL . '/billing/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'            => LC_PUBLIC_URL . '/billing/cancel.php',
        'allow_promotion_codes' => 'true',
        'locale'                => 'ja',
    ]);

    $res = billing_http_request('POST', 'https://api.stripe.com/v1/checkout/sessions', [
        'headers' => [
            'Authorization: Bearer ' . STRIPE_SECRET_KEY,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        'body' => $params,
    ]);

    if ($res['status'] !== 200) {
        error_log('[stripe] checkout failed: ' . $res['body']);
        return ['error' => 'Stripe決済の開始に失敗しました。'];
    }
    $data = json_decode($res['body'], true);
    return ['url' => $data['url'] ?? '', 'session_id' => $data['id'] ?? ''];
}

/**
 * Stripe Customer Portal セッション作成（解約・カード変更等）
 */
function stripe_create_portal_session(array $tenant): array {
    if (!STRIPE_SECRET_KEY) return ['error' => 'Stripe未設定です。'];
    if (empty($tenant['subscription_id'])) return ['error' => 'まだサブスクリプションが開始されていません。'];

    // tenant.subscription_id は sub_xxx を保持。Customer ID は subscription から取得が必要
    $sub_res = billing_http_request('GET',
        'https://api.stripe.com/v1/subscriptions/' . urlencode($tenant['subscription_id']),
        ['headers' => ['Authorization: Bearer ' . STRIPE_SECRET_KEY]]
    );
    if ($sub_res['status'] !== 200) return ['error' => 'サブスクリプション情報の取得に失敗しました。'];
    $sub = json_decode($sub_res['body'], true);
    $customer_id = $sub['customer'] ?? '';
    if (!$customer_id) return ['error' => '顧客IDが見つかりません。'];

    $params = http_build_query([
        'customer'   => $customer_id,
        'return_url' => LC_PUBLIC_URL . '/billing/portal.php',
    ]);
    $res = billing_http_request('POST', 'https://api.stripe.com/v1/billing_portal/sessions', [
        'headers' => [
            'Authorization: Bearer ' . STRIPE_SECRET_KEY,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        'body' => $params,
    ]);
    if ($res['status'] !== 200) return ['error' => 'カスタマーポータルの作成に失敗しました。'];
    $data = json_decode($res['body'], true);
    return ['url' => $data['url'] ?? ''];
}

/**
 * Stripe Webhook 署名検証
 */
function stripe_verify_webhook(string $payload, string $sig_header, string $secret, int $tolerance = 300): bool {
    if (!preg_match('/t=(\d+),v1=([0-9a-f]+)/', $sig_header, $m)) return false;
    [$_, $t, $v1] = $m;
    $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
    if (!hash_equals($expected, $v1)) return false;
    if (abs(time() - (int)$t) > $tolerance) return false;
    return true;
}

// ============================================================
// PayPay
// ============================================================

/**
 * PayPay HMAC 認証ヘッダ生成
 * https://developer.paypay.ne.jp/products/docs/webpayment/reference#authentication
 */
function paypay_auth_header(string $method, string $path, string $body): string {
    $contentType = $body === '' ? 'empty' : 'application/json';
    $epoch = time();
    $nonce = bin2hex(random_bytes(8));

    $hashed = 'REDACTED_FOR_PUBLIC';
    if ($body !== '') {
        $md5 = base64_encode(md5($contentType . $body, true));
        $hashed = $md5;
    }

    $signatureRaw = implode("\n", [
        $path,
        strtoupper($method),
        $nonce,
        $epoch,
        $contentType,
        $hashed,
    ]);
    $signature = base64_encode(hash_hmac('sha256', $signatureRaw, PAYPAY_API_SECRET, true));

    $authHeader = sprintf(
        'hmac OPA-Auth:%s:%s:%s:%d:%s',
        PAYPAY_API_KEY, $signature, $nonce, $epoch, $hashed
    );
    return $authHeader;
}

/**
 * PayPay QRコード決済を作成（一回払い／追加課金用途）
 */
function paypay_create_qr(array $payload): array {
    if (!PAYPAY_API_KEY) return ['error' => 'PayPay未設定です。'];
    $path = '/v2/codes';
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $auth = paypay_auth_header('POST', $path, $body);
    $res = billing_http_request('POST', PAYPAY_API_BASE . $path, [
        'headers' => [
            'Content-Type: application/json',
            'X-ASSUME-MERCHANT: ' . PAYPAY_MERCHANT_ID,
            'Authorization: ' . $auth,
        ],
        'body' => $body,
    ]);
    if ($res['status'] >= 300) {
        error_log('[paypay] create_qr failed: ' . $res['body']);
        return ['error' => 'PayPay決済の開始に失敗しました。', 'detail' => $res['body']];
    }
    $data = json_decode($res['body'], true);
    return ['data' => $data['data'] ?? []];
}

/**
 * PayPay QRコードの決済状態を取得
 */
function paypay_get_payment(string $merchant_payment_id): array {
    if (!PAYPAY_API_KEY) return ['error' => 'PayPay未設定です。'];
    $path = '/v2/codes/payments/' . rawurlencode($merchant_payment_id);
    $auth = paypay_auth_header('GET', $path, '');
    $res = billing_http_request('GET', PAYPAY_API_BASE . $path, [
        'headers' => [
            'X-ASSUME-MERCHANT: ' . PAYPAY_MERCHANT_ID,
            'Authorization: ' . $auth,
        ],
    ]);
    if ($res['status'] >= 300) return ['error' => 'PayPay状態取得失敗', 'detail' => $res['body']];
    return ['data' => json_decode($res['body'], true)['data'] ?? []];
}

// ============================================================
// 共通ユーティリティ
// ============================================================

/**
 * テナントのプラン変更 + 請求履歴記録
 */
function billing_record_invoice(int $tenant_id, string $plan, string $cycle, int $amount,
                                string $period_start, string $period_end,
                                string $payment_method, string $external_id,
                                string $status = 'paid'): int {
    $stmt = get_db()->prepare("INSERT INTO lc_tenant_invoices
        (tenant_id, invoice_no, plan, billing_cycle, amount, period_start, period_end,
         status, paid_at, payment_method, external_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $invoice_no = 'INV-' . date('Ymd') . '-' . str_pad((string)$tenant_id, 4, '0', STR_PAD_LEFT) . '-' . substr(uniqid(), -4);
    $stmt->execute([
        $tenant_id, $invoice_no, $plan, $cycle, $amount, $period_start, $period_end,
        $status, $status === 'paid' ? date('Y-m-d H:i:s') : null,
        $payment_method, $external_id,
    ]);
    return (int)get_db()->lastInsertId();
}

/**
 * テナントのステータスとプランを更新（決済成功時に呼ぶ）
 */
function billing_activate_tenant(int $tenant_id, string $plan, string $cycle, string $subscription_id): void {
    require_once __DIR__ . '/../includes/tenant.php';
    $specs = lc_plan_specs();
    $spec  = $specs[$plan] ?? null;
    if (!$spec) return;

    $stmt = get_db()->prepare("UPDATE lc_tenants SET
        status='active', plan=?, billing_cycle=?, subscription_id=?,
        max_users=?, max_clients=?, max_cases=?, storage_quota_mb=?,
        trial_ends_at=NULL, canceled_at=NULL
        WHERE id=?");
    $stmt->execute([
        $plan, $cycle, $subscription_id,
        $spec['max_users'], $spec['max_clients'], $spec['max_cases'], $spec['storage_mb'],
        $tenant_id,
    ]);
}
