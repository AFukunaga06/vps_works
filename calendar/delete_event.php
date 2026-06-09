<?php
require_once __DIR__ . '/gcal_helper.php';

$client  = get_google_client();
$service = new Google\Service\Calendar($client);

$id   = $_GET['id'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');

if ($id) {
    $service->events->delete(CALENDAR_ID, $id);
}

header('Location: index.php?date=' . urlencode($date));
exit;
