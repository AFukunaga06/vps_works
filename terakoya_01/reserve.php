<?php
header("Content-Type: application/json; charset=UTF-8");
require __DIR__ . "/config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/PHPMailer/src/Exception.php";
require __DIR__ . "/PHPMailer/src/PHPMailer.php";
require __DIR__ . "/PHPMailer/src/SMTP.php";

function bad($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(["ok" => false, "error" => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    bad("POST only", 405);
}

$raw = file_get_contents("php://input");
if (!$raw) bad("empty body");

$data = json_decode($raw, true);
if (!is_array($data)) bad("invalid json");

$date  = trim($data["date"]  ?? "");
$time  = trim($data["time"]  ?? "");
$type  = trim($data["type"]  ?? "");
$name  = trim($data["name"]  ?? "");
$email = trim($data["email"] ?? "");
$goal  = trim($data["goal"]  ?? "");

if ($date === "" || $time === "" || $type === "" || $name === "" || $email === "") {
    bad("missing fields");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    bad("invalid email");
}

$typeLabel = ($type === "session") ? "本講座" : "オリエンテーション";

$subjectAdmin = "【予約】{$typeLabel} {$date} {$time} {$name}様";
$bodyAdmin =
"予約が入りました。\n\n".
"【種別】{$typeLabel}\n".
"【日時】{$date} {$time}\n".
"【お名前】{$name}\n".
"【メール】{$email}\n".
"【目的】{$goal}\n\n".
"----\n".
"送信元：reserve.php\n";

$subjectUser = "【受付】予約を受け付けました（{$typeLabel} {$date} {$time}）";
$bodyUser =
"{$name} 様\n\n".
"ご予約ありがとうございます。下記内容で受け付けました。\n\n".
"【種別】{$typeLabel}\n".
"【日時】{$date} {$time}\n".
"【目的】{$goal}\n\n".
"確定後、Zoomのご案内をお送りします。\n\n".
"フクのオンライン寺子屋\n";

function createMailer() {
    global $GMAIL_USER, $GMAIL_PASS;

    $mail = new PHPMailer(true);
    $mail->CharSet  = "UTF-8";
    $mail->Encoding = "base64";
    $mail->isSMTP();
    $mail->Host       = "smtp.gmail.com";
    $mail->Port       = 587;
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Username   = $GMAIL_USER;
    $mail->Password   = $GMAIL_PASS;
    $mail->setFrom($GMAIL_USER, "フクのオンライン寺子屋");

    return $mail;
}

try {
    $mail = createMailer();
    $mail->addAddress($ADMIN_MAIL);
    $mail->addReplyTo($email, $name);
    $mail->Subject = $subjectAdmin;
    $mail->Body    = $bodyAdmin;
    $mail->send();

    $mail2 = createMailer();
    $mail2->addAddress($email);
    $mail2->Subject = $subjectUser;
    $mail2->Body    = $bodyUser;
    $mail2->send();

    echo json_encode(["ok" => true], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    bad("send failed: " . ($mail->ErrorInfo ?? "unknown"), 500);
}
