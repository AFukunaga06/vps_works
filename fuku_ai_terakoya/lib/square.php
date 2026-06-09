<?php
require_once __DIR__ . '/../config.php';

/**
 * Square REST API ラッパー（PHP SDK不使用、curl直叩き）
 * - SQUARE_ENV / SQUARE_API_BASE / SQUARE_ACCESS_TOKEN / SQUARE_LOCATION_ID を参照
 * - Sandbox / Production は config.php の SQUARE_ENV で切替
 */

function square_api_request(string $method, string $path, ?array $body = null): array {
    $url = SQUARE_API_BASE . $path;
    $headers = [
        'Square-Version: 2025-04-16',
        'Authorization: Bearer ' . SQUARE_ACCESS_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    $payload = $body !== null
        ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;

    if (function_exists('curl_init')) {
        return _square_request_curl($method, $url, $headers, $payload);
    }
    return _square_request_stream($method, $url, $headers, $payload);
}

function _square_request_curl(string $method, string $url, array $headers, ?string $payload): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return ['ok' => false, 'http_code' => 0, 'error' => 'curl: ' . $err, 'body' => null, 'raw' => null];
    }
    $decoded = json_decode($resp, true);
    $ok = ($httpCode >= 200 && $httpCode < 300);
    return [
        'ok' => $ok, 'http_code' => $httpCode,
        'body' => is_array($decoded) ? $decoded : null,
        'raw' => $resp,
        'error' => $ok ? null : square_extract_error($decoded),
    ];
}

function _square_request_stream(string $method, string $url, array $headers, ?string $payload): array {
    $ctx = stream_context_create([
        'http' => [
            'method'        => strtoupper($method),
            'header'        => implode("\r\n", $headers),
            'content'       => $payload ?? '',
            'ignore_errors' => true,
            'timeout'       => 30,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    $httpCode = 0;
    if (!empty($http_response_header[0])) {
        if (preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) $httpCode = (int)$m[1];
    }
    if ($resp === false) {
        $err = error_get_last()['message'] ?? 'stream error';
        return ['ok' => false, 'http_code' => $httpCode, 'error' => 'stream: ' . $err, 'body' => null, 'raw' => null];
    }
    $decoded = json_decode($resp, true);
    $ok = ($httpCode >= 200 && $httpCode < 300);
    return [
        'ok' => $ok, 'http_code' => $httpCode,
        'body' => is_array($decoded) ? $decoded : null,
        'raw' => $resp,
        'error' => $ok ? null : square_extract_error($decoded),
    ];
}

function square_extract_error($decoded): string {
    if (is_array($decoded) && !empty($decoded['errors'])) {
        $msgs = [];
        foreach ($decoded['errors'] as $e) {
            $msgs[] = ($e['code'] ?? '?') . ': ' . ($e['detail'] ?? '');
        }
        return implode(' / ', $msgs);
    }
    return 'unknown error';
}

/**
 * Payment Link（Square Checkout）作成
 * @param array $opts
 *   - name: 商品名
 *   - amount: 円（整数）
 *   - reference_id: payments.id を渡す（webhook で照合）
 *   - description: 任意
 *   - redirect_url: 決済完了後の戻り先URL
 *   - buyer_email: 任意
 */
function square_create_payment_link(array $opts): array {
    $payload = [
        'idempotency_key' => bin2hex(random_bytes(16)),
        'quick_pay' => [
            'name'        => $opts['name'],
            'price_money' => [
                'amount'   => (int)$opts['amount'],
                'currency' => 'JPY',
            ],
            'location_id' => SQUARE_LOCATION_ID,
        ],
        'checkout_options' => [
            'redirect_url'                  => $opts['redirect_url'] ?? null,
            'ask_for_shipping_address'      => false,
            'allow_tipping'                 => false,
            'enable_coupon'                 => false,
            'enable_loyalty'                => false,
            'merchant_support_email'        => FROM_EMAIL,
        ],
    ];
    if (!empty($opts['description'])) {
        $payload['description'] = $opts['description'];
    }
    if (!empty($opts['reference_id'])) {
        $payload['payment_note'] = (string)$opts['reference_id'];
    }
    if (!empty($opts['buyer_email'])) {
        $payload['pre_populated_data'] = ['buyer_email' => $opts['buyer_email']];
    }
    return square_api_request('POST', '/v2/online-checkout/payment-links', $payload);
}

function square_get_payment(string $paymentId): array {
    return square_api_request('GET', '/v2/payments/' . rawurlencode($paymentId));
}

function square_get_order(string $orderId): array {
    return square_api_request('GET', '/v2/orders/' . rawurlencode($orderId));
}

/**
 * Webhook 署名検証
 * Squareの仕様: HMAC-SHA256( signatureKey, notificationUrl + requestBody ) を Base64
 * 比較対象は X-Square-HmacSha256-Signature ヘッダ
 */
function square_verify_webhook_signature(string $notificationUrl, string $body, string $signatureHeader): bool {
    $key = SQUARE_WEBHOOK_SIGNATURE_KEY;
    if ($key === '' || $signatureHeader === '') return false;
    $hash     = hash_hmac('sha256', $notificationUrl . $body, $key, true);
    $expected = base64_encode($hash);
    return hash_equals($expected, $signatureHeader);
}

function square_is_configured(): bool {
    return SQUARE_ACCESS_TOKEN !== '' && SQUARE_LOCATION_ID !== '';
}
