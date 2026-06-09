<?php

function send_mail(string $to, string $subject, string $body): bool
{
    // PHPMailerが使えるか確認
    $phpmailer_path = __DIR__ . '/../vendor/autoload.php';
    if (SMTP_USER !== '' && file_exists($phpmailer_path)) {
        return _send_via_phpmailer($to, $subject, $body);
    }
    return _send_via_mail($to, $subject, $body);
}

function _send_via_phpmailer(string $to, string $subject, string $body): bool
{
    require_once __DIR__ . '/../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('PHPMailer error: ' . $e->getMessage());
        return false;
    }
}

function _send_via_mail(string $to, string $subject, string $body): bool
{
    // メールヘッダインジェクション対策
    $to      = _sanitize_header($to);
    $subject = mb_encode_mimeheader($subject, 'UTF-8', 'B');
    $from    = _sanitize_header(FROM_EMAIL);
    $name    = mb_encode_mimeheader(FROM_NAME, 'UTF-8', 'B');

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "From: {$name} <{$from}>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($to, $subject, base64_encode($body), $headers);
}

function _sanitize_header(string $value): string
{
    return preg_replace('/[\r\n\t]/', '', $value);
}

function build_admin_mail(array $data, array $fields): string
{
    $lines   = [];
    $lines[] = SITE_NAME . ' のお問い合わせフォームから送信がありました。';
    $lines[] = '';
    $lines[] = '送信日時：' . date('Y-m-d H:i:s');
    $lines[] = '送信元IP：' . ($_SERVER['REMOTE_ADDR'] ?? '不明');
    $lines[] = str_repeat('-', 40);

    foreach ($fields as $field) {
        $name  = $field['name'];
        $label = $field['label'];
        $val   = $data[$name] ?? '';
        if (is_array($val)) {
            $val = implode(', ', $val);
        }
        $lines[] = $label . '：' . $val;
    }

    $lines[] = str_repeat('-', 40);
    return implode("\n", $lines);
}

function build_user_mail(array $data, array $fields): string
{
    $name    = $data['name'] ?? 'お客様';
    $lines   = [];
    $lines[] = $name . ' 様';
    $lines[] = '';
    $lines[] = 'お問い合わせいただきありがとうございます。';
    $lines[] = '以下の内容で受け付けました。';
    $lines[] = '担当者より折り返しご連絡いたします。';
    $lines[] = '';
    $lines[] = str_repeat('-', 40);

    foreach ($fields as $field) {
        $n   = $field['name'];
        $lbl = $field['label'];
        $val = $data[$n] ?? '';
        if (is_array($val)) {
            $val = implode(', ', $val);
        }
        $lines[] = $lbl . '：' . $val;
    }

    $lines[] = str_repeat('-', 40);
    $lines[] = '';
    $lines[] = '※このメールは自動返信です。返信はできません。';
    $lines[] = SITE_NAME;
    return implode("\n", $lines);
}
