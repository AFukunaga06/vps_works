<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/gcal_helper.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $client  = get_google_client();
    $service = new Google\Service\Calendar($client);

    // GET: 月の予定一覧
    if ($method === 'GET' && $action === 'list') {
        $year  = (int)($_GET['year']  ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('n'));
        $lastDay = date('t', mktime(0, 0, 0, $month, 1, $year));
        $from  = sprintf('%04d-%02d-01T00:00:00+09:00', $year, $month);
        $to    = sprintf('%04d-%02d-%02dT23:59:59+09:00', $year, $month, $lastDay);

        $events = $service->events->listEvents(CALENDAR_ID, [
            'timeMin'      => $from,
            'timeMax'      => $to,
            'orderBy'      => 'startTime',
            'singleEvents' => true,
            'timeZone'     => TIMEZONE,
        ]);

        $result = [];
        foreach ($events->getItems() as $ev) {
            $start = $ev->getStart();
            if ($start->dateTime) {
                $date = substr($start->dateTime, 0, 10);
                $time = substr($start->dateTime, 11, 5);
            } else {
                $date = $start->date;
                $time = '';
            }
            $result[$date][] = [
                'id'    => $ev->getId(),
                'time'  => $time,
                'title' => $ev->getSummary() ?? '（タイトルなし）',
                'memo'  => $ev->getDescription() ?? '',
            ];
        }
        echo json_encode(['ok' => true, 'events' => $result]);
        exit;
    }

    // POST: 予定追加
    if ($method === 'POST' && $action === 'add') {
        $body = json_decode(file_get_contents('php://input'), true);
        verifyCsrf($body['csrf'] ?? '');

        $date  = $body['date']  ?? '';
        $time  = $body['time']  ?? '';
        $title = trim($body['title'] ?? '');
        $memo  = trim($body['memo']  ?? '');

        if (!$date || !$title) {
            http_response_code(400);
            echo json_encode(['error' => '日付とタイトルは必須です']);
            exit;
        }

        $ev = new Google\Service\Calendar\Event();
        $ev->setSummary($title);
        if ($memo) $ev->setDescription($memo);

        if ($time) {
            $startDt = new Google\Service\Calendar\EventDateTime();
            $startDt->setDateTime($date . 'T' . $time . ':00+09:00');
            $startDt->setTimeZone(TIMEZONE);
            $endDt = new Google\Service\Calendar\EventDateTime();
            $endTime = date('H:i', strtotime($time) + 3600);
            $endDt->setDateTime($date . 'T' . $endTime . ':00+09:00');
            $endDt->setTimeZone(TIMEZONE);
        } else {
            $startDt = new Google\Service\Calendar\EventDateTime();
            $startDt->setDate($date);
            $endDt = new Google\Service\Calendar\EventDateTime();
            $endDt->setDate($date);
        }
        $ev->setStart($startDt);
        $ev->setEnd($endDt);

        $created = $service->events->insert(CALENDAR_ID, $ev);
        echo json_encode(['ok' => true, 'id' => $created->getId()]);
        exit;
    }

    // POST: 予定削除
    if ($method === 'POST' && $action === 'delete') {
        $body = json_decode(file_get_contents('php://input'), true);
        verifyCsrf($body['csrf'] ?? '');

        $id = $body['id'] ?? '';
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID不正']); exit; }

        $service->events->delete(CALENDAR_ID, $id);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => '不正なリクエスト']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'サーバーエラー: ' . $e->getMessage()]);
}
