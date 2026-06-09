<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// ===== Gmail SMTP 設定 =====
define('MAIL_FROM',     'afky5906@gmail.com');
define('MAIL_FROM_NAME','ABC○○法律事務所相談窓口');
define('MAIL_TO',       'afky5906@gmail.com');
define('GMAIL_APP_PASS', 'REDACTED_FOR_PUBLIC');

/**
 * 予約完了メールを管理者に送信
 */
function send_reserve_mail(array $data, int $reserve_id): bool {
    $mail = new PHPMailer(true);
    try {
        // SMTPサーバー設定
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_FROM;
        $mail->Password   = GMAIL_APP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // 送信元・宛先
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress(MAIL_TO);

        // 件名
        $is_first  = (bool)$data['is_first'];
        $type_label = $data['type'];
        $mail->Subject = '【予約申込】' . $type_label . ' - ' . $data['name'] . ' 様'
                       . ($is_first ? '（初回無料）' : '（入金待ち）');

        // 本文
        $date_label = date('Y年n月j日（', strtotime($data['date']))
            . ['日','月','火','水','木','金','土'][(int)date('w', strtotime($data['date']))] . '）';
        $end_time   = date('H:i', strtotime($data['start_time']) + 2700);
        $price        = $is_first ? '初回無料' : (($data['type'] === '一般相談') ? '1,200円（税込）' : '2,000円（税込）');
        $id_str       = '#' . str_pad($reserve_id, 5, '0', STR_PAD_LEFT);
        $first_label  = $is_first ? '初回（無料）' : '2回目以降';

        $body = <<<TEXT
ABC○○法律事務所相談窓口に予約申込がありました。

━━━━━━━━━━━━━━━━━━━━
 予約番号: {$id_str}
━━━━━━━━━━━━━━━━━━━━

【相談種別】{$type_label}
【日時】    {$date_label} {$data['start_time']}〜{$end_time}
【相談方法】{$data['consult_method']}
【相談回数】{$first_label}
【料金】    {$price}

【お名前】  {$data['name']} 様
【ふりがな】{$data['kana']}
【メール】  {$data['email']}
【電話】    {$data['tel']}

【相談内容】
{$data['content']}

TEXT;
        if (!empty($data['note'])) {
            $body .= "【備考】\n{$data['note']}\n";
        }
        $body .= "\n━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "管理画面: http://162.43.14.130/fuku_soudan/admin/index.php\n";

        $mail->Body = $body;
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('PHPMailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * 申込者へ自動返信メールを送信
 */
function send_auto_reply_mail(array $data, int $reserve_id): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_FROM;
        $mail->Password   = GMAIL_APP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($data['email'], $data['name']);

        $mail->Subject = '【自動返信】お申し込みありがとうございます';

        $name       = $data['name'];
        $id_str     = '#' . str_pad($reserve_id, 5, '0', STR_PAD_LEFT);
        $date_label = date('Y年n月j日（', strtotime($data['date']))
            . ['日','月','火','水','木','金','土'][(int)date('w', strtotime($data['date']))] . '）';
        $end_time   = date('H:i', strtotime($data['start_time']) + 2700);

        $body = <<<TEXT
{$name} 様

この度は、ABC○○法律事務所相談窓口へのお申し込み・ご予約をいただき、誠にありがとうございます。

本メールは自動送信にてお届けしております。
担当者よりご確認のうえ、当日または翌営業日中にあらためてご連絡申し上げます。
しばらくお待ちいただけますと幸いです。

━━━━━━━━━━━━━━━━━━━━
 ご予約内容（予約番号：{$id_str}）
━━━━━━━━━━━━━━━━━━━━
【相談種別】{$data['type']}
【日時】    {$date_label} {$data['start_time']}〜{$end_time}
【相談方法】{$data['consult_method']}

【ご相談内容の概要】
{$data['content']}
━━━━━━━━━━━━━━━━━━━━

なお、お急ぎの場合やご不明な点がございましたら、
下記までお気軽にお問い合わせください。

─────────────────────
ABC○○法律事務所相談窓口
TEL：080-4788-2900
MAIL：afky5906@gmail.com
営業時間：月〜土 10:00〜18:00
─────────────────────

引き続き、どうぞよろしくお願いいたします。
TEXT;

        $mail->Body = $body;
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('AutoReply mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * 予約確定メールを相談者に送信
 */
function send_confirmed_mail(array $r): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_FROM;
        $mail->Password   = GMAIL_APP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($r['email'], $r['name']);

        $mail->Subject = '【予約確定】' . $r['name'] . '様のご予約が確定しました';

        $name       = $r['name'];
        $id_str     = '#' . str_pad($r['id'], 5, '0', STR_PAD_LEFT);
        $date_label = date('Y年n月j日（', strtotime($r['reserve_date']))
            . ['日','月','火','水','木','金','土'][(int)date('w', strtotime($r['reserve_date']))] . '）';
        $start_time = date('H:i', strtotime($r['start_time']));
        $end_time   = date('H:i', strtotime($r['start_time']) + 2700);

        $body = <<<TEXT
{$name} 様

この度は、ABC○○法律事務所相談窓口をご利用いただきありがとうございます。

{$name}様のご予約が確定しました。

━━━━━━━━━━━━━━━━━━━━
 ご予約内容（予約番号：{$id_str}）
━━━━━━━━━━━━━━━━━━━━
【日時】    {$date_label} {$start_time}〜{$end_time}
【相談方法】{$r['consult_method']}
━━━━━━━━━━━━━━━━━━━━

当日はどうぞよろしくお願いいたします。
ご不明な点がございましたら、お気軽にお問い合わせください。

─────────────────────
ABC○○法律事務所相談窓口
TEL：080-4788-2900
MAIL：afky5906@gmail.com
営業時間：月〜土 10:00〜18:00
─────────────────────
TEXT;

        $mail->Body = $body;
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Confirmed mail error: ' . $mail->ErrorInfo);
        return false;
    }
}
