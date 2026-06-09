<?php
/**
 * フクのAI寺子屋 講師応募フォーム 送信ハンドラ
 *  - fuku_ai_terakoya_bosyuu_01.html の応募フォームから AJAX(POST) で呼ばれる
 *  - 既存 contact_form の SMTP 設定（Gmail）と PHPMailer をそのまま流用
 *  - 受信先: REDACTED_EMAIL（指定）＋ 応募者への自動返信
 */
header('Content-Type: application/json; charset=utf-8');

require_once '/var/www/html/contact_form/vendor/autoload.php'; // PHPMailer ライブラリ

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

const RECIPIENT = 'REDACTED_EMAIL'; // 受信先（指定）

// ===== Gmail SMTP 設定 =====
// 認証情報の出所: /var/www/html/fuku_ai_terakoya/config.php の GMAIL_PASS（現役・認証OK確認済み）
// ※Gmailアプリパスワードを再発行したら、ここも合わせて更新すること
const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_USER = 'REDACTED_EMAIL';
const SMTP_PASS = 'znawtdbppvorvahm';
const FROM_EMAIL = 'REDACTED_EMAIL';

function out(bool $ok, string $error = ''): void {
    echo json_encode(['ok' => $ok, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    out(false, '不正なリクエストです。');
}

// ===== Honeypot（botは静かに成功扱い）=====
if (!empty($_POST['website'])) {
    out(true);
}

// ===== 入力取得 =====
$name    = trim($_POST['name']         ?? '');
$email   = trim($_POST['email']        ?? '');
$phone   = trim($_POST['phone']        ?? '');
$pcExp   = trim($_POST['pc_exp']       ?? '');
$avail   = trim($_POST['availability'] ?? '');
$message = trim($_POST['message']      ?? '');
$nda     = !empty($_POST['nda']);

// ===== バリデーション =====
if ($name === '' || mb_strlen($name) > 100) {
    out(false, 'お名前を正しく入力してください。');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200) {
    out(false, 'メールアドレスを正しく入力してください。');
}
if ($phone === '' || mb_strlen($phone) > 50) {
    out(false, '電話番号を入力してください。');
}
if ($pcExp === '' || mb_strlen($pcExp) > 300) {
    out(false, 'パソコンの実務経験を入力してください。');
}
if ($message === '' || mb_strlen($message) > 3000) {
    out(false, '自己PR・志望動機を入力してください（3000文字以内）。');
}
if (!$nda) {
    out(false, '守秘義務契約（NDA）への同意が必要です。');
}

// ===== 送信元IP =====
$ip = '';
foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) { $ip = trim(explode(',', $_SERVER[$k])[0]); break; }
}

// ===== 簡易レート制限（同一IPから30分に3件まで／失敗しても送信は止めない）=====
$ipHash = hash('sha256', $ip . 'terakoya_apply_salt_2026');
$rlFile = sys_get_temp_dir() . '/terakoya_apply_rl.json';
$now    = time();
$store  = is_file($rlFile) ? (json_decode(@file_get_contents($rlFile), true) ?: []) : [];
$times  = array_values(array_filter($store[$ipHash] ?? [], fn($t) => $t > $now - 1800));
if (count($times) >= 3) {
    out(false, '送信回数の上限に達しました。しばらくしてからお試しください。');
}
$times[]        = $now;
$store[$ipHash] = $times;
// 古いIPエントリを掃除
foreach ($store as $k => $ts) {
    $store[$k] = array_values(array_filter($ts, fn($t) => $t > $now - 1800));
    if (empty($store[$k])) unset($store[$k]);
}
@file_put_contents($rlFile, json_encode($store), LOCK_EX);

// ===== メール送信（PHPMailer / Gmail SMTP）=====
function make_mailer(): PHPMailer {
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = SMTP_HOST;
    $m->SMTPAuth   = true;
    $m->Username   = SMTP_USER;
    $m->Password   = SMTP_PASS;
    $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $m->Port       = SMTP_PORT;
    $m->CharSet    = 'UTF-8';
    $m->setFrom(FROM_EMAIL, 'フクのAI寺子屋 講師応募フォーム');
    return $m;
}

$sep = str_repeat('─', 25);

try {
    // --- 管理者（主催）宛 ---
    $mail = make_mailer();
    $mail->addAddress(RECIPIENT);
    $mail->addReplyTo($email, $name);
    $mail->Subject = '[講師応募] ' . $name . ' 様 - フクのAI寺子屋';
    $mail->Body    = implode("\n", [
        'フクのAI寺子屋 講師募集ページから応募がありました。',
        $sep,
        "お名前        : {$name}",
        "メール        : {$email}",
        '電話          : ' . ($phone !== '' ? $phone : 'なし'),
        'PC実務経験    : ' . $pcExp,
        '対応可能時間  : ' . ($avail !== '' ? $avail : '未記入'),
        'NDA同意       : ' . ($nda ? '同意あり' : 'なし'),
        $sep,
        '自己PR・志望動機:',
        $message,
        $sep,
        "IP: {$ip} / 日時: " . date('Y-m-d H:i:s'),
    ]);
    $mail->send();

    // --- 応募者への自動返信 ---
    $reply = make_mailer();
    $reply->addAddress($email, $name);
    $reply->Subject = '【フクのAI寺子屋】講師応募を受け付けました';
    $reply->Body    = implode("\n", [
        "{$name} 様",
        '',
        'このたびはフクのAI寺子屋 講師募集にご応募いただきありがとうございます。',
        '以下の内容で応募を受け付けました。内容を確認のうえ、追ってご連絡いたします。',
        '',
        $sep,
        'PC実務経験    : ' . $pcExp,
        '対応可能時間  : ' . ($avail !== '' ? $avail : '未記入'),
        $sep,
        '自己PR・志望動機:',
        $message,
        $sep,
        '',
        'フクのAI寺子屋（主催：福永 篤）',
        'メール：' . RECIPIENT,
        '電話：080-4788-2900',
    ]);
    $reply->send();
} catch (MailException $e) {
    error_log('terakoya apply mail error: ' . $e->getMessage());
    out(false, 'メール送信に失敗しました。お手数ですが REDACTED_EMAIL まで直接ご連絡ください。');
}

out(true);
