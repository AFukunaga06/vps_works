<?php
session_start();
require_once __DIR__ . '/config.php';

// PHPMailerはファイル先頭でuse宣言が必要
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

function redirect_error(string $msg): void {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => $msg];
    header('Location: index.php');
    exit;
}

function redirect_success(): void {
    $_SESSION['flash'] = ['type' => 'success',
        'msg' => '✅ お問い合わせを受け付けました。確認メールをお送りしましたのでご確認ください。'];
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

// ===== CSRF検証 =====
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    redirect_error('不正なリクエストです。もう一度お試しください。');
}
unset($_SESSION['csrf_token']);

// ===== Honeypot検証 =====
if (!empty($_POST['website'])) {
    redirect_success(); // Bot: 静かに成功ページへ
}

// ===== 入力取得 =====
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$phone   = trim($_POST['phone']   ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// ===== バリデーション =====
if ($name === '' || mb_strlen($name) > 100)
    redirect_error('お名前を正しく入力してください。');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200)
    redirect_error('メールアドレスを正しく入力してください。');
if ($subject === '')
    redirect_error('お問い合わせ種別を選択してください。');
if ($message === '' || mb_strlen($message) > 3000)
    redirect_error('お問い合わせ内容を入力してください（3000文字以内）。');

// ===== reCAPTCHA v3 =====
if (RECAPTCHA_ENABLED && RECAPTCHA_SECRET !== '') {
    $token = $_POST['g_recaptcha_response'] ?? '';
    if ($token === '') redirect_error('reCAPTCHA検証に失敗しました。');
    $res = @json_decode(@file_get_contents(
        "https://www.google.com/recaptcha/api/siteverify?secret=" . urlencode(RECAPTCHA_SECRET)
        . "&response=" . urlencode($token)
    ), true);
    if (empty($res['success']) || ($res['score'] ?? 0) < RECAPTCHA_THRESHOLD) {
        redirect_error('スパムの疑いがあるため送信をブロックしました。');
    }
}

// ===== IP取得 =====
$ip = '';
foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) { $ip = trim(explode(',', $_SERVER[$k])[0]); break; }
}
$ip_hash = hash('sha256', $ip . 'cf_salt_2026');

// ===== DB接続 =====
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    redirect_error('サーバーエラーが発生しました。しばらく後でお試しください。');
}

// ===== レート制限 =====
$limit_since = date('Y-m-d H:i:s', strtotime('-' . RATE_LIMIT_MINUTES . ' minutes'));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE ip_hash = ? AND created_at > ?");
$stmt->execute([$ip_hash, $limit_since]);
if ((int)$stmt->fetchColumn() >= RATE_LIMIT_COUNT) {
    redirect_error('送信回数の上限に達しました。しばらく後でお試しください。');
}

// ===== DBに保存 =====
$pdo->prepare(
    "INSERT INTO inquiries (name, email, phone, subject, message, ip_address, user_agent)
     VALUES (?,?,?,?,?,?,?)"
)->execute([$name, $email, $phone, $subject, $message, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '']);

$pdo->prepare("INSERT INTO rate_limits (ip_hash) VALUES (?)")->execute([$ip_hash]);

// ===== PHPMailerでメール送信 =====
function make_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    if (SMTP_USER !== '') {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
    }
    $mail->CharSet = 'UTF-8';
    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    return $mail;
}

if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    try {
        // 管理者宛
        $mail = make_mailer();
        $mail->addAddress(ADMIN_EMAIL);
        $mail->addReplyTo($email, $name);
        $mail->Subject = '[お問い合わせ] ' . $subject . ' - ' . SITE_NAME;
        $mail->Body    = implode("\n", [
            "新しいお問い合わせが届きました。",
            str_repeat('─', 25),
            "お名前  : {$name}",
            "メール  : {$email}",
            "電話    : " . ($phone ?: 'なし'),
            "種別    : {$subject}",
            str_repeat('─', 25),
            "内容:",
            $message,
            str_repeat('─', 25),
            "IP: {$ip} / 日時: " . date('Y-m-d H:i:s'),
        ]);
        $mail->send();

        // 自動返信
        $mail2 = make_mailer();
        $mail2->addAddress($email, $name);
        $mail2->Subject = 'お問い合わせを受け付けました | ' . SITE_NAME;
        $mail2->Body    = implode("\n", [
            "{$name} 様",
            "",
            "このたびはお問い合わせいただきありがとうございます。",
            "以下の内容でお問い合わせを受け付けました。",
            "通常2〜3営業日以内にご返信いたします。",
            "",
            str_repeat('─', 25),
            "種別: {$subject}",
            str_repeat('─', 25),
            $message,
            str_repeat('─', 25),
            "",
            SITE_NAME,
            FROM_EMAIL,
        ]);
        $mail2->send();
    } catch (MailException $e) {
        error_log('PHPMailer error: ' . $e->getMessage());
    }
}

redirect_success();
