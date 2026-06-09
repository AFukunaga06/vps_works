<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/square.php';

// Square Webhook 受信エンドポイント
// 設定方法: Developer Dashboard → Webhooks → Add Subscription
//   通知URL: https://<host>/fuku_ai_terakoya/payments/webhook.php
//   購読イベント: payment.created / payment.updated（最低限）

header('Content-Type: application/json; charset=UTF-8');

$rawBody = file_get_contents('php://input') ?: '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
// Apache以外の環境向け: $_SERVER から拾う
$signatureHeader = '';
foreach (['HTTP_X_SQUARE_HMACSHA256_SIGNATURE', 'HTTP_X_SQUARE_SIGNATURE'] as $k) {
    if (!empty($_SERVER[$k])) { $signatureHeader = $_SERVER[$k]; break; }
}
if ($signatureHeader === '' && is_array($headers)) {
    foreach ($headers as $hk => $hv) {
        if (strcasecmp($hk, 'X-Square-HmacSha256-Signature') === 0) { $signatureHeader = $hv; break; }
        if (strcasecmp($hk, 'X-Square-Signature') === 0)            { $signatureHeader = $hv; break; }
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$notificationUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

$signatureValid = square_verify_webhook_signature($notificationUrl, $rawBody, $signatureHeader);

$pdo = db();

$payload = json_decode($rawBody, true);
$eventId   = is_array($payload) ? ($payload['event_id']   ?? null) : null;
$eventType = is_array($payload) ? ($payload['type']       ?? null) : null;

// ログ事前挿入（重複event_idはINSERT IGNOREで弾く）
$ins = $pdo->prepare(
    "INSERT IGNORE INTO payment_webhook_logs
       (event_id, event_type, signature_valid, raw_body, raw_headers, source_ip)
     VALUES (?, ?, ?, ?, ?, ?)"
);
$ins->execute([
    $eventId, $eventType, $signatureValid ? 1 : 0,
    substr($rawBody, 0, 65000),
    substr(json_encode($headers, JSON_UNESCAPED_UNICODE), 0, 4000),
    $_SERVER['REMOTE_ADDR'] ?? null,
]);
$logId = (int)$pdo->lastInsertId();

if (!$signatureValid) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'invalid signature']);
    exit;
}

// 重複イベントは即200返す（処理済として）
if ($logId === 0) {
    echo json_encode(['ok' => true, 'duplicate' => true]);
    exit;
}

try {
    if (in_array($eventType, ['payment.created', 'payment.updated'], true)) {
        $sqPayment = $payload['data']['object']['payment'] ?? null;
        if (is_array($sqPayment)) {
            $sqPaymentId = $sqPayment['id']        ?? null;
            $sqOrderId   = $sqPayment['order_id']  ?? null;
            $sqStatus    = $sqPayment['status']    ?? '';
            $note        = $sqPayment['note']      ?? '';

            // 自分側のpayments.idは「PAYMENT_<id>」形式でnoteに乗せている
            $localPaymentId = 0;
            if (preg_match('/PAYMENT_(\d+)/', (string)$note, $m)) {
                $localPaymentId = (int)$m[1];
            }
            // noteで取れなければ order_id で逆引き
            if ($localPaymentId === 0 && $sqOrderId) {
                $st = $pdo->prepare("SELECT id FROM payments WHERE square_order_id = ? LIMIT 1");
                $st->execute([$sqOrderId]);
                $localPaymentId = (int)($st->fetchColumn() ?: 0);
            }

            $newStatus = match (strtoupper((string)$sqStatus)) {
                'COMPLETED', 'APPROVED' => 'completed',
                'CANCELED', 'CANCELLED' => 'cancelled',
                'FAILED'                => 'failed',
                default                 => 'pending',
            };

            if ($localPaymentId > 0) {
                $pdo->prepare(
                    "UPDATE payments
                       SET status            = ?,
                           square_payment_id = ?,
                           square_order_id   = COALESCE(?, square_order_id),
                           paid_at           = CASE WHEN ?='completed' AND paid_at IS NULL THEN NOW() ELSE paid_at END
                     WHERE id = ?"
                )->execute([$newStatus, $sqPaymentId, $sqOrderId, $newStatus, $localPaymentId]);

                $pdo->prepare("UPDATE payment_webhook_logs SET payment_id = ?, processed = 1 WHERE id = ?")
                    ->execute([$localPaymentId, $logId]);
            } else {
                $pdo->prepare("UPDATE payment_webhook_logs SET error_message = ?, processed = 1 WHERE id = ?")
                    ->execute(['no matching local payment', $logId]);
            }
        }
    } else {
        // 未対応イベントは記録だけして200返す
        $pdo->prepare("UPDATE payment_webhook_logs SET processed = 1, error_message = ? WHERE id = ?")
            ->execute(['ignored event type', $logId]);
    }
} catch (Throwable $e) {
    $pdo->prepare("UPDATE payment_webhook_logs SET error_message = ? WHERE id = ?")
        ->execute([substr($e->getMessage(), 0, 1000), $logId]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'internal']);
    exit;
}

echo json_encode(['ok' => true]);
