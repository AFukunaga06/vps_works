<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
verifyCsrf($body['csrf'] ?? '');

$date   = $body['date']   ?? '';
$events = $body['events'] ?? [];

if (!$date) {
    http_response_code(400);
    echo json_encode(['error' => '日付が指定されていません']);
    exit;
}

// 日付を日本語形式に
$dt       = DateTime::createFromFormat('Y-m-d', $date);
$dateJa   = $dt ? $dt->format('Y年n月j日') : $date;

// メール本文作成
$evLines = '';
if (empty($events)) {
    $evLines = "（この日の予定はありません）\n";
} else {
    foreach ($events as $ev) {
        $time  = $ev['time'] ?? '';
        $title = $ev['title'] ?? '';
        $memo  = $ev['memo']  ?? '';
        $evLines .= ($time ? $time . ' ' : '') . $title;
        if ($memo) $evLines .= "\n　└ " . $memo;
        $evLines .= "\n";
    }
}

$subject = "【" . APP_NAME . "】{$dateJa} の予定";
$textBody = <<<TEXT
{$dateJa} の予定をお知らせします。

─────────────────────
{$evLines}─────────────────────

このメールは My Calendar より送信されました。
TEXT;

// PHPMailer を使ってSMTP送信
// composer で入れている場合: require_once __DIR__ . '/vendor/autoload.php';
// VPSに直接 PHPMailer がある場合はパスを調整
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    // PHPMailerが入っていない場合は素のSMTPソケットで送信（簡易版）
    $result = sendWithSocket($subject, $textBody);
} else {
    require_once $autoload;
    $result = sendWithPhpMailer($subject, $textBody);
}

echo json_encode($result);
exit;

// ─── PHPMailer版 ─────────────────────────────────────────
function sendWithPhpMailer(string $subject, string $body): array {
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
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress(SMTP_FROM);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return ['ok' => true, 'message' => 'メールを送信しました'];
    } catch (Exception $e) {
        return ['error' => 'メール送信失敗: ' . $mail->ErrorInfo];
    }
}

// ─── ソケット版（PHPMailerなし時のフォールバック） ─────────
function sendWithSocket(string $subject, string $body): array {
    // PHPMailer推奨。未インストールならインストールを促す
    return ['error' => 'PHPMailerが必要です。次のコマンドで導入してください: cd /var/www/html/calendar02 && composer require phpmailer/phpmailer'];
}
