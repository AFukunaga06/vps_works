<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/square.php';
require_once __DIR__ . '/../lib/mailer.php';

$paymentId = (int)($_GET['p'] ?? 0);
// Square から渡される transactionId / orderId（CheckoutAPI/Payment Linkの返り値）
$squareTransactionId = (string)($_GET['transactionId'] ?? '');
$squareOrderId       = (string)($_GET['orderId']       ?? '');

$pdo = db();
$payment = null;
if ($paymentId > 0) {
    $st = $pdo->prepare(
        "SELECT p.*, pl.name AS plan_name, pl.type AS plan_type, pl.monthly_quota
           FROM payments p
           LEFT JOIN plans pl ON pl.id = p.plan_id
          WHERE p.id = ?"
    );
    $st->execute([$paymentId]);
    $payment = $st->fetch();
}

if (!$payment) {
    http_response_code(404);
    echo '決済情報が見つかりません';
    exit;
}

// URLパラメータに orderId が無ければ DBに保存済みの order_id を使う
if ($squareOrderId === '' && !empty($payment['square_order_id'])) {
    $squareOrderId = $payment['square_order_id'];
}

// Webhookが先に到達していなくてもUIで反映できるよう、ここでも軽く照会する
$updated = false;
if ($payment['status'] === 'pending' && $squareOrderId !== '') {
    $orderRes = square_get_order($squareOrderId);
    if ($orderRes['ok'] && !empty($orderRes['body']['order'])) {
        $order = $orderRes['body']['order'];
        $tenders = $order['tenders'] ?? [];
        $sqPaymentId = !empty($tenders[0]['payment_id']) ? $tenders[0]['payment_id'] : null;
        $orderState  = $order['state'] ?? '';

        if ($sqPaymentId) {
            $payRes = square_get_payment($sqPaymentId);
            if ($payRes['ok'] && !empty($payRes['body']['payment'])) {
                $sqPay = $payRes['body']['payment'];
                $sqStatus = $sqPay['status'] ?? '';
                $newStatus = match (strtoupper($sqStatus)) {
                    'COMPLETED', 'APPROVED' => 'completed',
                    'CANCELED', 'CANCELLED' => 'cancelled',
                    'FAILED'                => 'failed',
                    default                 => 'pending',
                };
                if ($newStatus !== 'pending') {
                    $pdo->prepare(
                        "UPDATE payments
                           SET status = ?, square_payment_id = ?, square_order_id = ?,
                               paid_at = COALESCE(paid_at, NOW())
                         WHERE id = ?"
                    )->execute([$newStatus, $sqPaymentId, $squareOrderId, $paymentId]);
                    $payment['status']            = $newStatus;
                    $payment['square_payment_id'] = $sqPaymentId;
                    $updated = ($newStatus === 'completed');
                }
            }
        }
    }
}

// 月謝(subscription)プランで初回決済が完了した場合、subscriptions に紐付け
// Webhook先行で既にcompletedになっていてもこの処理が走るよう冪等化（subscription_id空チェックが二重防止）
if ($payment['status'] === 'completed' && $payment['plan_type'] === 'subscription' && empty($payment['subscription_id'])) {
    // 既存のactive subがあれば再利用、無ければ新規作成
    $st = $pdo->prepare(
        "SELECT id FROM subscriptions
          WHERE student_id = ? AND plan_id = ? AND status = 'active' LIMIT 1"
    );
    $st->execute([(int)$payment['student_id'], (int)$payment['plan_id']]);
    $subId = (int)($st->fetchColumn() ?: 0);
    if ($subId === 0) {
        $today = date('Y-m-d');
        $next  = date('Y-m-d', strtotime('+1 month', strtotime($today)));
        $ins = $pdo->prepare(
            "INSERT INTO subscriptions (student_id, plan_id, status, start_date, next_billing_date)
             VALUES (?, ?, 'active', ?, ?)"
        );
        $ins->execute([(int)$payment['student_id'], (int)$payment['plan_id'], $today, $next]);
        $subId = (int)$pdo->lastInsertId();
    }
    $pdo->prepare("UPDATE payments SET subscription_id = ? WHERE id = ?")->execute([$subId, $paymentId]);
    $payment['subscription_id'] = $subId;
}

// 入金通知メール（status が完了したら1回だけ送る）
// Webhook先行で既にcompletedになっていてもこの処理が走るよう冪等化（admin_noteの[mailed]が二重送信防止）
if ($payment['status'] === 'completed' && strpos((string)$payment['admin_note'], '[mailed]') === false) {
    $subjectAdmin = sprintf('【入金】#%d %s %s様 %s円',
        $paymentId, $payment['plan_name'], $payment['customer_name'], number_format((int)$payment['amount']));
    $bodyAdmin =
        "決済が完了しました。\n\n" .
        "【ID】#{$paymentId}\n" .
        "【プラン】{$payment['plan_name']}\n" .
        "【金額】" . number_format((int)$payment['amount']) . "円\n" .
        "【お名前】{$payment['customer_name']}\n" .
        "【メール】{$payment['customer_email']}\n" .
        "【Square Payment ID】" . ($payment['square_payment_id'] ?? '-') . "\n" .
        "【環境】" . SQUARE_ENV . "\n";

    $subjectUser = sprintf('【受領】%s のお支払いを承りました', $payment['plan_name']);
    $bodyUser =
        "{$payment['customer_name']} 様\n\n" .
        "下記内容でお支払いを受領いたしました。ありがとうございます。\n\n" .
        "【プラン】{$payment['plan_name']}\n" .
        "【金額】" . number_format((int)$payment['amount']) . "円\n" .
        "【決済日】" . date('Y-m-d H:i') . "\n\n" .
        "領収書はSquareより別途送信されます。\n\n" . APP_NAME . "\n";

    send_and_log(ADMIN_MAIL,                 $subjectAdmin, $bodyAdmin, $payment['customer_email'], $payment['customer_name'], 'payment', $paymentId);
    send_and_log($payment['customer_email'], $subjectUser,  $bodyUser,  null, null, 'payment', $paymentId);

    $pdo->prepare("UPDATE payments SET admin_note = CONCAT(COALESCE(admin_note,''), '[mailed]') WHERE id = ?")
        ->execute([$paymentId]);
}

$status = $payment['status'];
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>お支払い結果 - <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
  body{background:#faf8f5;color:#333;font-family:"Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif}
  h1{color:#4a7c59}
  .check{font-size:4em;color:#4a7c59;text-align:center;margin:24px 0}
  .pending{font-size:4em;color:#d4a574;text-align:center;margin:24px 0}
  .summary{background:#fff;border:1px solid #e0d8c8;border-radius:8px;padding:24px}
</style>
</head>
<body>
<main class="container" style="max-width:640px;padding:32px 16px">
<?php if ($status === 'completed'): ?>
  <div class="check">✓</div>
  <h1 class="text-center">お支払いありがとうございました</h1>
  <p class="text-center text-muted">決済が完了しました。受領メールをお送りしましたのでご確認ください。</p>
<?php elseif ($status === 'pending'): ?>
  <div class="pending">⏳</div>
  <h1 class="text-center">処理中です</h1>
  <p class="text-center text-muted">Squareでの決済確認中です。少し時間をおいてからこのページをリロードするか、メールをお待ちください。</p>
<?php else: ?>
  <h1 class="text-center text-danger">決済が完了しませんでした</h1>
  <p class="text-center">状態: <?= h($status) ?> 。再度お試しいただくか、管理者(<?= h(ADMIN_MAIL) ?>)までご連絡ください。</p>
<?php endif; ?>

<div class="summary mt-4">
  <table class="table table-sm mb-0">
    <tr><th class="w-25">受付番号</th><td>#<?= (int)$payment['id'] ?></td></tr>
    <tr><th>プラン</th><td><?= h($payment['plan_name']) ?></td></tr>
    <tr><th>金額</th><td><?= number_format((int)$payment['amount']) ?> 円</td></tr>
    <tr><th>お名前</th><td><?= h($payment['customer_name']) ?></td></tr>
    <tr><th>メール</th><td><?= h($payment['customer_email']) ?></td></tr>
    <tr><th>状態</th><td><?= h($status) ?></td></tr>
  </table>
</div>

<div class="text-center mt-4">
  <a href="<?= h(APP_ROOT_URL) ?>/" class="btn btn-outline-secondary">トップへ戻る</a>
</div>
</main>
</body>
</html>
