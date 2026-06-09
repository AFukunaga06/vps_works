<?php
require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/square.php";
require_once __DIR__ . "/../lib/mailer.php";

$paymentId = (int)($_GET["p"] ?? 0);
$squareOrderId = (string)($_GET["orderId"] ?? "");

$pdo = db();
$payment = null;
if ($paymentId > 0) {
    $st = $pdo->prepare(
        "SELECT p.*, r.type AS res_type, r.reserve_date, r.reserve_time, r.goal,
                r.id AS reservation_id_real
           FROM payments p
           LEFT JOIN reservations r ON r.id = p.reservation_id
          WHERE p.id = ? AND p.reservation_id IS NOT NULL"
    );
    $st->execute([$paymentId]);
    $payment = $st->fetch();
}

if (!$payment) {
    http_response_code(404);
    echo "Payment not found";
    exit;
}

$updated = false;

if ($payment["status"] === "pending") {
    if ($squareOrderId === "") {
        $squareOrderId = (string)($payment["square_order_id"] ?? "");
    }
    if ($squareOrderId !== "") {
        $orderRes = square_get_order($squareOrderId);
        if ($orderRes["ok"] && !empty($orderRes["body"]["order"])) {
            $order = $orderRes["body"]["order"];
            $tenders = $order["tenders"] ?? [];
            $sqPaymentId = !empty($tenders[0]["payment_id"]) ? $tenders[0]["payment_id"] : null;

            if ($sqPaymentId) {
                $payRes = square_get_payment($sqPaymentId);
                if ($payRes["ok"] && !empty($payRes["body"]["payment"])) {
                    $sqPay = $payRes["body"]["payment"];
                    $sqStatus = $sqPay["status"] ?? "";
                    $newStatus = match (strtoupper($sqStatus)) {
                        "COMPLETED", "APPROVED" => "completed",
                        "CANCELED", "CANCELLED" => "cancelled",
                        "FAILED"                => "failed",
                        default                 => "pending",
                    };
                    if ($newStatus !== "pending") {
                        $pdo->prepare(
                            "UPDATE payments
                               SET status = ?, square_payment_id = ?, square_order_id = ?,
                                   paid_at = COALESCE(paid_at, NOW())
                             WHERE id = ?"
                        )->execute([$newStatus, $sqPaymentId, $squareOrderId, $paymentId]);
                        $payment["status"] = $newStatus;
                        $payment["square_payment_id"] = $sqPaymentId;
                        $updated = ($newStatus === "completed");
                    }
                }
            }
        }
    }
}

// 決済完了 → 予約を confirmed に更新 + メール送信（admin_note の[mailed]で2重送信防止）
// このスクリプトでの同期更新でも、Webhook先行で既にcompletedになっていた場合でも、両方で同じ処理が走るよう冪等化
if ($payment["status"] === "completed" && $payment["reservation_id"] && strpos((string)$payment["admin_note"], "[mailed]") === false) {
    $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?")
        ->execute(["confirmed", (int)$payment["reservation_id"]]);

    $typeLabel = ($payment["res_type"] === "session") ? "本講座" : "オリエンテーション";
    $date = (string)$payment["reserve_date"];
    $time = (string)$payment["reserve_time"];
    $name = (string)$payment["customer_name"];
    $email = (string)$payment["customer_email"];
    $goal  = (string)$payment["goal"];
    $amount = (int)$payment["amount"];

    $subjectAdmin = sprintf("【入金＆予約確定】#%d %s %s %s様 %s円",
        (int)$payment["reservation_id"], $typeLabel, $date . " " . $time, $name, number_format($amount));
    $bodyAdmin =
        "決済が完了し予約が確定しました。\n\n" .
        "【予約ID】#" . (int)$payment["reservation_id"] . "\n" .
        "【決済ID】#" . (int)$paymentId . "\n" .
        "【種別】{$typeLabel}\n" .
        "【日時】{$date} {$time}\n" .
        "【金額】" . number_format($amount) . "円\n" .
        "【お名前】{$name}\n" .
        "【メール】{$email}\n" .
        "【目的】{$goal}\n" .
        "【Square Payment ID】" . ($payment["square_payment_id"] ?? "-") . "\n" .
        "【環境】" . SQUARE_ENV . "\n";

    $subjectUser = sprintf("【予約確定】%s %s %s（お支払い受領）", $typeLabel, $date, $time);
    $bodyUser =
        "{$name} 様\n\n" .
        "お支払いを受領し、ご予約が確定いたしました。\n\n" .
        "【種別】{$typeLabel}\n" .
        "【日時】{$date} {$time}\n" .
        "【金額】" . number_format($amount) . "円\n" .
        "【目的】{$goal}\n\n" .
        "Zoomのご案内は別途お送りします。\n" .
        "領収書はSquareより別途送信されます。\n\n" . APP_NAME . "\n";

    send_and_log(ADMIN_MAIL, $subjectAdmin, $bodyAdmin, $email, $name, "reservation", (int)$payment["reservation_id"]);
    send_and_log($email,     $subjectUser,  $bodyUser,  null,   null,  "reservation", (int)$payment["reservation_id"]);

    $pdo->prepare("UPDATE payments SET admin_note = CONCAT(COALESCE(admin_note,\"\"), \"[mailed]\") WHERE id = ?")
        ->execute([$paymentId]);
}

$status = $payment["status"];
$typeLabel = ($payment["res_type"] === "session") ? "本講座" : "オリエンテーション";
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>予約・お支払い結果 - <?= h(APP_NAME) ?></title>
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
<?php if ($status === "completed"): ?>
  <div class="check">✓</div>
  <h1 class="text-center">ご予約・お支払いありがとうございました</h1>
  <p class="text-center text-muted">予約が確定しました。確認メールをお送りしましたのでご確認ください。</p>
<?php elseif ($status === "pending"): ?>
  <div class="pending">⏳</div>
  <h1 class="text-center">処理中です</h1>
  <p class="text-center text-muted">Squareでの決済確認中です。少し時間をおいてからこのページをリロードするか、メールをお待ちください。</p>
<?php else: ?>
  <h1 class="text-center text-danger">決済が完了しませんでした</h1>
  <p class="text-center">状態: <?= h($status) ?> 。予約は保留状態です。再度お試しいただくか、管理者(<?= h(ADMIN_MAIL) ?>)までご連絡ください。</p>
<?php endif; ?>

<div class="summary mt-4">
  <table class="table table-sm mb-0">
    <tr><th class="w-25">予約番号</th><td>#<?= (int)$payment["reservation_id"] ?></td></tr>
    <tr><th>種別</th><td><?= h($typeLabel) ?></td></tr>
    <tr><th>日時</th><td><?= h((string)$payment["reserve_date"]) ?> <?= h((string)$payment["reserve_time"]) ?></td></tr>
    <tr><th>金額</th><td><?= number_format((int)$payment["amount"]) ?> 円</td></tr>
    <tr><th>お名前</th><td><?= h($payment["customer_name"]) ?></td></tr>
    <tr><th>メール</th><td><?= h($payment["customer_email"]) ?></td></tr>
    <tr><th>状態</th><td><?= h($status) ?></td></tr>
  </table>
</div>

<div class="text-center mt-4">
  <a href="/terakoya_01/" class="btn btn-outline-secondary">予約ページへ戻る</a>
</div>
</main>
</body>
</html>
