<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

// WPに同梱されているPHPMailerを共有利用（独立コピー不要）
$_PHPMAILER_BASE = '/var/www/html/wp/wp-includes/PHPMailer';
require_once $_PHPMAILER_BASE . '/Exception.php';
require_once $_PHPMAILER_BASE . '/PHPMailer.php';
require_once $_PHPMAILER_BASE . '/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function build_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->Port       = SMTP_PORT;
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Username   = GMAIL_USER;
    $mail->Password   = GMAIL_PASS;
    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    return $mail;
}

/**
 * メール送信し mail_logs に記録する
 * @return array{ok:bool, error?:string}
 */
function send_and_log(string $toEmail, string $subject, string $body, ?string $replyToEmail = null, ?string $replyToName = null, ?string $relatedType = null, ?int $relatedId = null): array {
    try {
        $mail = build_mailer();
        $mail->addAddress($toEmail);
        if ($replyToEmail) {
            $mail->addReplyTo($replyToEmail, $replyToName ?? '');
        }
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();

        $stmt = db()->prepare(
            "INSERT INTO mail_logs (related_type, related_id, to_email, subject, body, status)
             VALUES (?, ?, ?, ?, ?, 'sent')"
        );
        $stmt->execute([$relatedType, $relatedId, $toEmail, $subject, $body]);
        return ['ok' => true];
    } catch (Throwable $e) {
        $err = method_exists($e, 'getMessage') ? $e->getMessage() : 'unknown';
        try {
            $stmt = db()->prepare(
                "INSERT INTO mail_logs (related_type, related_id, to_email, subject, body, status, error_message)
                 VALUES (?, ?, ?, ?, ?, 'failed', ?)"
            );
            $stmt->execute([$relatedType, $relatedId, $toEmail, $subject, $body, $err]);
        } catch (Throwable $_) {
            // ログ失敗は無視
        }
        return ['ok' => false, 'error' => $err];
    }
}
