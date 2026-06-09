<?php
// =====================================================================
// 予約通知メール送信モジュール（PHPMailer + SMTP）
// SMTP認証情報は includes/mail_config.php（サーバー側のみ・git管理外）に定義。
//   define('RESV_SMTP_HOST', 'smtp.gmail.com');
//   define('RESV_SMTP_USER', 'REDACTED_EMAIL');
//   define('RESV_SMTP_PASS', 'REDACTED_FOR_PUBLIC');
//   define('RESV_SMTP_PORT', 587);
//   define('RESV_FROM_EMAIL', 'REDACTED_EMAIL');
//   define('RESV_ADMIN_EMAIL', 'REDACTED_EMAIL');
// 設定ファイル・PHPMailer のいずれかが無い場合は送信せず false を返す（予約処理は止めない）。
// =====================================================================

if (is_file(__DIR__ . '/mail_config.php')) {
    require_once __DIR__ . '/mail_config.php';
}

function resv_mail_enabled(): bool {
    return defined('RESV_SMTP_USER') && RESV_SMTP_USER !== ''
        && is_file(__DIR__ . '/../vendor/autoload.php');
}

/**
 * メール送信。失敗しても例外は投げず false を返す（予約完了処理を妨げないため）。
 */
function resv_send_mail(string $to, string $toName, string $subject, string $body,
                        string $fromName, ?string $replyTo = null, ?string $replyToName = null): bool {
    if (!resv_mail_enabled()) {
        error_log('reservation mailer: disabled (config or PHPMailer missing)');
        return false;
    }
    require_once __DIR__ . '/../vendor/autoload.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = RESV_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = RESV_SMTP_USER;
        $mail->Password   = RESV_SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = defined('RESV_SMTP_PORT') ? RESV_SMTP_PORT : 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(RESV_FROM_EMAIL, $fromName);
        $mail->addAddress($to, $toName);
        if ($replyTo) $mail->addReplyTo($replyTo, $replyToName ?? '');

        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('reservation mailer error: ' . $e->getMessage());
        return false;
    }
}
