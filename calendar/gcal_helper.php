<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

function get_google_client(): Google\Client {
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(REDIRECT_URI);
    $client->addScope(Google\Service\Calendar::CALENDAR);
    $client->setAccessType('offline');

    if (!file_exists(TOKEN_FILE)) {
        header('Location: auth.php');
        exit;
    }

    $token = json_decode(file_get_contents(TOKEN_FILE), true);
    $client->setAccessToken($token);

    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents(TOKEN_FILE, json_encode($client->getAccessToken()));
        } else {
            header('Location: auth.php');
            exit;
        }
    }

    return $client;
}
