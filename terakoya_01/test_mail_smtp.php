<?php
require __DIR__ . "/config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/PHPMailer/src/Exception.php";
require __DIR__ . "/PHPMailer/src/PHPMailer.php";
require __DIR__ . "/PHPMailer/src/SMTP.php";

$mail = new PHPMailer(true);
$mail->CharSet  = "UTF-8";
$mail->Encoding = "base64";
$mail->isHTML(false);

try {
    $mail->isSMTP();
    $mail->Host       = "smtp.gmail.com";
    $mail->Port       = 587;
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Username   = $GMAIL_USER;
    $mail->Password   = $GMAIL_PASS;

    $mail->setFrom($GMAIL_USER, "terakoya_01");
    $mail->addAddress($ADMIN_MAIL);

    $mail->Subject = "[テスト] Gmail SMTP 送信";
    $mail->Body    = "Gmail SMTP (アプリパスワード) 経由で送信されたテストメールです。\n送信日時: " . date("Y-m-d H:i:s");

    $mail->send();
    echo "SUCCESS: メール送信成功しました\n";
} catch (Exception $e) {
    echo "ERROR: {$mail->ErrorInfo}\n";
}
