<?php
require_once __DIR__ . '/config.php';

date_default_timezone_set(TIMEZONE);

// ===== LINE署名検証 =====
$body = file_get_contents('php://input');
$sig  = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
$hash = base64_encode(hash_hmac('sha256', $body, LINE_CHANNEL_SECRET, true));
if ($sig !== $hash) {
    http_response_code(400);
    exit('Invalid signature');
}

$data   = json_decode($body, true);
$events = $data['events'] ?? [];

foreach ($events as $event) {
    if ($event['type'] !== 'message') continue;
    if ($event['message']['type'] !== 'text') continue;

    $text    = trim($event['message']['text']);
    $reply_token = $event['replyToken'];

    $result = parse_and_create_event($text);
    reply_line($reply_token, $result);
}

// ===== メッセージ解析＆カレンダー登録 =====
function parse_and_create_event(string $text): string {
    // 形式1: 5/10 14:00-15:00 タイトル
    // 形式2: 5/10 14:00 タイトル
    // 形式3: 5/10 終日 タイトル
    // 年省略時は今年 or 来年

    $year = (int)date('Y');

    // 終日パターン: 5/10 終日 タイトル
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\s+終日\s+(.+)$/u', $text, $m)) {
        [$_, $mon, $day, $title] = $m;
        $date = sprintf('%04d-%02d-%02d', $year, $mon, $day);
        if (strtotime($date) < strtotime('today')) $year++;
        $date = sprintf('%04d-%02d-%02d', $year, $mon, $day);
        return create_allday_event($title, $date);
    }

    // 年付き終日: 2026/5/10 終日 タイトル
    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})\s+終日\s+(.+)$/u', $text, $m)) {
        [$_, $yr, $mon, $day, $title] = $m;
        $date = sprintf('%04d-%02d-%02d', $yr, $mon, $day);
        return create_allday_event($title, $date);
    }

    // 時間範囲パターン: 5/10 14:00-15:00 タイトル
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})\s+(.+)$/u', $text, $m)) {
        [$_, $mon, $day, $sh, $sm, $eh, $em, $title] = $m;
        $date  = sprintf('%04d-%02d-%02d', $year, $mon, $day);
        if (strtotime($date) < strtotime('today')) $year++;
        $date  = sprintf('%04d-%02d-%02d', $year, $mon, $day);
        $start = "{$date}T{$sh}:{$sm}:00";
        $end   = "{$date}T{$eh}:{$em}:00";
        return create_event($title, $start, $end);
    }

    // 年付き時間範囲: 2026/5/10 14:00-15:00 タイトル
    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})\s+(.+)$/u', $text, $m)) {
        [$_, $yr, $mon, $day, $sh, $sm, $eh, $em, $title] = $m;
        $date  = sprintf('%04d-%02d-%02d', $yr, $mon, $day);
        $start = "{$date}T{$sh}:{$sm}:00";
        $end   = "{$date}T{$eh}:{$em}:00";
        return create_event($title, $start, $end);
    }

    // 開始時刻のみ: 5/10 14:00 タイトル（1時間のイベント）
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{2})\s+(.+)$/u', $text, $m)) {
        [$_, $mon, $day, $sh, $sm, $title] = $m;
        $date  = sprintf('%04d-%02d-%02d', $year, $mon, $day);
        if (strtotime($date) < strtotime('today')) $year++;
        $date  = sprintf('%04d-%02d-%02d', $year, $mon, $day);
        $start = "{$date}T{$sh}:{$sm}:00";
        $end   = date('Y-m-d\TH:i:s', strtotime($start) + 3600);
        return create_event($title, $start, $end);
    }

    // 年付き開始時刻のみ: 2026/5/10 14:00 タイトル
    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{2})\s+(.+)$/u', $text, $m)) {
        [$_, $yr, $mon, $day, $sh, $sm, $title] = $m;
        $date  = sprintf('%04d-%02d-%02d', $yr, $mon, $day);
        $start = "{$date}T{$sh}:{$sm}:00";
        $end   = date('Y-m-d\TH:i:s', strtotime($start) + 3600);
        return create_event($title, $start, $end);
    }

    return "❌ 形式が違います。以下の形式で送ってください。\n\n"
         . "📅 5/10 14:00 打ち合わせ\n"
         . "📅 5/10 14:00-15:00 会議\n"
         . "📅 5/10 終日 健康診断\n"
         . "📅 2026/5/10 14:00 打ち合わせ";
}

function get_gcal_service(): ?Google\Service\Calendar {
    if (!file_exists(GCAL_VENDOR)) return null;
    require_once GCAL_VENDOR;

    $creds = json_decode(file_get_contents(GCAL_CREDENTIALS), true);
    $token = json_decode(file_get_contents(GCAL_TOKEN), true);

    $client = new Google\Client();
    $client->setClientId($creds['client_id']);
    $client->setClientSecret($creds['client_secret']);
    $client->setAccessToken($token);

    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents(GCAL_TOKEN, json_encode($client->getAccessToken()));
    }

    return new Google\Service\Calendar($client);
}

function create_event(string $title, string $start, string $end): string {
    try {
        $service = get_gcal_service();
        if (!$service) return '❌ Google Calendar接続エラー';

        $event = new Google\Service\Calendar\Event([
            'summary' => $title,
            'start'   => ['dateTime' => $start, 'timeZone' => TIMEZONE],
            'end'     => ['dateTime' => $end,   'timeZone' => TIMEZONE],
        ]);
        $service->events->insert(GCAL_CALENDAR_ID, $event);

        $date_str = date('m月d日', strtotime($start));
        $time_str = date('H:i', strtotime($start)) . '〜' . date('H:i', strtotime($end));
        return "✅ カレンダーに登録しました！\n📅 {$date_str} {$time_str}\n📝 {$title}";
    } catch (Exception $e) {
        error_log('GCal error: ' . $e->getMessage());
        return '❌ 登録に失敗しました: ' . $e->getMessage();
    }
}

function create_allday_event(string $title, string $date): string {
    try {
        $service = get_gcal_service();
        if (!$service) return '❌ Google Calendar接続エラー';

        $next = date('Y-m-d', strtotime($date . ' +1 day'));
        $event = new Google\Service\Calendar\Event([
            'summary' => $title,
            'start'   => ['date' => $date],
            'end'     => ['date' => $next],
        ]);
        $service->events->insert(GCAL_CALENDAR_ID, $event);

        $date_str = date('m月d日', strtotime($date));
        return "✅ カレンダーに登録しました！\n📅 {$date_str}（終日）\n📝 {$title}";
    } catch (Exception $e) {
        error_log('GCal error: ' . $e->getMessage());
        return '❌ 登録に失敗しました: ' . $e->getMessage();
    }
}

// ===== LINE返信 =====
function reply_line(string $reply_token, string $message): void {
    $payload = json_encode([
        'replyToken' => $reply_token,
        'messages'   => [['type' => 'text', 'text' => $message]],
    ]);
    $ch = curl_init('https://api.line.me/v2/bot/message/reply');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN,
        ],
        CURLOPT_POSTFIELDS     => $payload,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

http_response_code(200);
echo 'OK';
