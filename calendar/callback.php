<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(REDIRECT_URI);

if (!isset($_GET['code'])) {
    header('Location: auth.php');
    exit;
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    die('認証エラー: ' . htmlspecialchars($token['error_description'] ?? $token['error']));
}

file_put_contents(TOKEN_FILE, json_encode($token));

header('Location: index.php');
exit;
