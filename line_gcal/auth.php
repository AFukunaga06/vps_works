<?php
require_once __DIR__ . '/config.php';
require_once GCAL_VENDOR;

$creds = json_decode(file_get_contents(GCAL_CREDENTIALS), true);

$client = new Google\Client();
$client->setClientId($creds['client_id']);
$client->setClientSecret($creds['client_secret']);
$client->setRedirectUri('https://sakuhinnsyuu01.afuku5906.com/line_gcal/auth.php');
$client->addScope(Google\Service\Calendar::CALENDAR_EVENTS);
$client->setAccessType('offline');
$client->setPrompt('consent');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (!isset($token['error'])) {
        file_put_contents(GCAL_TOKEN, json_encode($token));
        echo '<p style="font-size:1.2rem;color:green;font-family:sans-serif">✅ Google Calendar認証完了！LINEからカレンダー登録が使えます。</p>';
    } else {
        echo '<p style="color:red">❌ エラー: ' . htmlspecialchars($token['error_description'] ?? $token['error']) . '</p>';
    }
    exit;
}

$auth_url = $client->createAuthUrl();
header('Location: ' . $auth_url);
exit;
