<?php
require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/mailer.php";
require_once __DIR__ . "/../lib/square.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

const PRICE_SESSION = 1500;

function api_bad(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(["ok" => false, "error" => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") api_bad("POST only", 405);

$raw = file_get_contents("php://input");
$data = [];
if ($raw) {
    $j = json_decode($raw, true);
    if (is_array($j)) $data = $j;
}
if (!$data) $data = $_POST ?? [];

$date  = trim((string)($data["date"]  ?? ""));
$time  = trim((string)($data["time"]  ?? ""));
$type  = trim((string)($data["type"]  ?? "orientation"));
$name  = trim((string)($data["name"]  ?? ""));
$email = trim((string)($data["email"] ?? ""));
$goal  = trim((string)($data["goal"]  ?? ""));

if ($date === "" || $time === "" || $name === "" || $email === "") api_bad("missing fields");
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) api_bad("invalid email");
if (!in_array($type, ["orientation","session"], true)) $type = "orientation";
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) api_bad("invalid date format (YYYY-MM-DD)");
if (mb_strlen($name) > 100 || mb_strlen($email) > 255 || mb_strlen($time) > 20 || mb_strlen($goal) > 4000) {
    api_bad("field too long");
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $studentId = (int)($stmt->fetchColumn() ?: 0);

    if ($studentId === 0) {
        $ins = $pdo->prepare("INSERT INTO students (name, email) VALUES (?, ?)");
        $ins->execute([$name, $email]);
        $studentId = (int)$pdo->lastInsertId();
    } else {
        $pdo->prepare("UPDATE students SET name = ? WHERE id = ?")->execute([$name, $studentId]);
    }

    $ins = $pdo->prepare(
        "INSERT INTO reservations
          (student_id, type, reserve_date, reserve_time, name, email, goal, status, source_ip, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    // session は支払い完了まで pending、orientation は即 pending（管理者確定待ち）
    $resStatus = "pending";
    $ins->execute([
        $studentId, $type, $date, $time, $name, $email, $goal, $resStatus,
        $_SERVER["REMOTE_ADDR"] ?? null,
        substr((string)($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 255),
    ]);
    $reservationId = (int)$pdo->lastInsertId();

    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    api_bad("db error: " . $e->getMessage(), 500);
}

$typeLabel = ($type === "session") ? "本講座" : "オリエンテーション";

// ===== 本講座: Square Payment Link を発行して URL を返す =====
if ($type === "session") {
    if (!square_is_configured()) {
        api_bad("決済の準備が整っていません（管理者連絡）", 500);
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $insP = $pdo->prepare(
            "INSERT INTO payments
              (student_id, reservation_id, amount, currency, status,
               square_environment, customer_name, customer_email, source_ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insP->execute([
            $studentId, $reservationId, PRICE_SESSION, "JPY", "pending",
            SQUARE_ENV, $name, $email,
            $_SERVER["REMOTE_ADDR"] ?? null,
            substr((string)($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 255),
        ]);
        $paymentId = (int)$pdo->lastInsertId();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        api_bad("payment record error: " . $e->getMessage(), 500);
    }

    $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
    $host   = $_SERVER["HTTP_HOST"] ?? "localhost";
    $redirect_url = $scheme . "://" . $host . APP_ROOT_URL . "/payments/reservation_complete.php?p=" . $paymentId;

    $resp = square_create_payment_link([
        "name"         => "本講座（" . $date . " " . $time . "）- " . APP_NAME,
        "amount"       => PRICE_SESSION,
        "reference_id" => "RES_" . $reservationId,
        "description"  => sprintf("本講座 %s %s（%s様）", $date, $time, $name),
        "redirect_url" => $redirect_url,
        "buyer_email"  => $email,
    ]);

    $pdo->prepare("UPDATE payments SET raw_response = ? WHERE id = ?")
        ->execute([substr((string)($resp["raw"] ?? ""), 0, 65000), $paymentId]);

    if (!$resp["ok"] || empty($resp["body"]["payment_link"])) {
        $err = $resp["error"] ?? "Square API error";
        $pdo->prepare("UPDATE payments SET status = ?, admin_note = ? WHERE id = ?")
            ->execute(["failed", "payment_link発行失敗: " . $err, $paymentId]);
        api_bad("決済リンクの発行に失敗しました: " . $err, 500);
    }

    $link = $resp["body"]["payment_link"];
    $pdo->prepare(
        "UPDATE payments
           SET square_payment_link_id = ?, square_order_id = ?, square_checkout_url = ?
         WHERE id = ?"
    )->execute([
        $link["id"]       ?? null,
        $link["order_id"] ?? null,
        $link["url"]      ?? null,
        $paymentId,
    ]);

    if (empty($link["url"])) {
        api_bad("決済URLが取得できませんでした", 500);
    }

    echo json_encode([
        "ok"          => true,
        "id"          => $reservationId,
        "type"        => "session",
        "payment_url" => $link["url"],
        "payment_id"  => $paymentId,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== オリエンテーション: 即時メール送信 =====
$subjectAdmin = "【予約】{$typeLabel} {$date} {$time} {$name}様";
$bodyAdmin =
    "予約が入りました。\n\n" .
    "【ID】#{$reservationId}\n" .
    "【種別】{$typeLabel}\n" .
    "【日時】{$date} {$time}\n" .
    "【お名前】{$name}\n" .
    "【メール】{$email}\n" .
    "【目的】{$goal}\n\n" .
    "----\nフクのAI寺子屋 / api/reserve.php\n";

$subjectUser = "【受付】予約を受け付けました（{$typeLabel} {$date} {$time}）";
$bodyUser =
    "{$name} 様\n\n" .
    "ご予約ありがとうございます。下記内容で受け付けました。\n\n" .
    "【種別】{$typeLabel}\n" .
    "【日時】{$date} {$time}\n" .
    "【目的】{$goal}\n\n" .
    "確定後、Zoomのご案内をお送りします。\n\nフクのAI寺子屋\n";

$r1 = send_and_log(ADMIN_MAIL, $subjectAdmin, $bodyAdmin, $email, $name, "reservation", $reservationId);
$r2 = send_and_log($email,     $subjectUser,  $bodyUser,  null,   null,  "reservation", $reservationId);

echo json_encode([
    "ok"   => true,
    "id"   => $reservationId,
    "type" => "orientation",
    "mail" => ["admin" => $r1["ok"], "user" => $r2["ok"]],
], JSON_UNESCAPED_UNICODE);
