<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/mailer.php';
require_once __DIR__ . '/lib/rate_limiter.php';
require_once __DIR__ . '/lib/db.php';

// POSTかつ確認ステップを経ていること
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['confirm_step'])) {
    header('Location: index.php');
    exit;
}

// CSRFチェック
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['form_errors'] = ['_global' => '不正なリクエストです。最初からやり直してください。'];
    header('Location: index.php');
    exit;
}

// 送信回数制限
if (!rate_limit_check()) {
    $_SESSION['form_errors'] = ['_global' => 'しばらく時間をおいてから再度お試しください。'];
    header('Location: index.php');
    exit;
}

// reCAPTCHA検証
if (RECAPTCHA_ENABLED && RECAPTCHA_SECRET !== '') {
    $token    = $_POST['recaptcha_token'] ?? '';
    $response = file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify?secret='
        . urlencode(RECAPTCHA_SECRET) . '&response=' . urlencode($token)
    );
    $result = json_decode($response, true);
    if (!$result['success'] || ($result['score'] ?? 0) < 0.5) {
        $_SESSION['form_errors'] = ['_global' => 'スパム判定されました。お手数ですが再度お試しください。'];
        header('Location: index.php');
        exit;
    }
}

$data   = $_SESSION['form_data'] ?? [];
$fields = FORM_FIELDS;

// メール送信（管理者）
$admin_body = build_admin_mail($data, $fields);
$admin_ok   = send_mail(ADMIN_EMAIL, MAIL_SUBJECT_ADMIN, $admin_body);

// メール送信（自動返信）
$user_email = $data['email'] ?? '';
$user_ok    = true;
if ($user_email !== '') {
    $user_body = build_user_mail($data, $fields);
    $user_ok   = send_mail($user_email, MAIL_SUBJECT_USER, $user_body);
}

if (!$admin_ok) {
    error_log('Admin mail send failed: ' . ADMIN_EMAIL);
}

// DBログ保存
db_save_log($data, $fields);

// セッションクリア
unset($_SESSION['form_data'], $_SESSION['confirm_step']);

// 完了ページへ
header('Location: complete.php');
exit;
