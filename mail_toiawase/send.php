<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$to           = trim($_POST['to']            ?? '');
$replySubject = trim($_POST['reply_subject'] ?? '');
$replyBody    = trim($_POST['reply_body']    ?? '');

if (!$to || !$replySubject || !$replyBody) {
    die('必要な情報が不足しています。<a href="index.php">一覧に戻る</a>');
}

// To: からアドレスとお名前を抽出
if (preg_match('/<(.+?)>/', $to, $m)) {
    $toAddr = trim($m[1]);
    $toName = trim(preg_replace('/<.+?>/', '', $to), ' "\'');
} else {
    $toAddr = trim($to);
    $toName = '';
}

try {
    $mail = new PHPMailer(true);
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->Port       = 587;
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = 'tls';
    $mail->Username   = GMAIL_USER;
    $mail->Password   = GMAIL_PASS;
    $mail->setFrom(GMAIL_FROM, GMAIL_NAME);
    $mail->addAddress($toAddr, $toName);
    $mail->Subject = $replySubject;
    $mail->Body    = $replyBody;
    $mail->send();

    header('Location: index.php?sent=1');
    exit;
} catch (Exception $e) {
    die('送信エラー: ' . htmlspecialchars($e->getMessage()) . ' <a href="javascript:history.back()">戻る</a>');
}
