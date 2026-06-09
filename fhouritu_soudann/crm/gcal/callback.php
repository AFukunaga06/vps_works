<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_admin();

$credentials_file = defined('GCAL_CREDENTIALS_FILE') ? GCAL_CREDENTIALS_FILE : __DIR__ . '/credentials.json';

if (!file_exists($credentials_file)) {
    die('認証情報が見つかりません。先にSETUP画面で設定してください。');
}

$creds = json_decode(file_get_contents($credentials_file), true);

$client = new Google\Client();
$client->setClientId($creds['client_id']);
$client->setClientSecret($creds['client_secret']);
$client->setRedirectUri(BASE_URL . '/crm/gcal/callback.php');
$client->addScope(Google\Service\Calendar::CALENDAR_EVENTS);
$client->setAccessType('offline');

if (isset($_GET['error'])) {
    $err = htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8');
    die("認証エラー: {$err}");
}

if (!isset($_GET['code'])) {
    die('認証コードが見つかりません。');
}

try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        die('トークン取得エラー: ' . htmlspecialchars($token['error_description'] ?? $token['error'], ENT_QUOTES, 'UTF-8'));
    }

    file_put_contents(__DIR__ . '/token.json', json_encode($token));
    header('Location: ' . BASE_URL . '/crm/gcal/setup.php?connected=1');
    exit;
} catch (Exception $e) {
    die('エラーが発生しました: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
