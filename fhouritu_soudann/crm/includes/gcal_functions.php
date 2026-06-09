<?php
/**
 * Google Calendar 連携機能
 */

// Google API Client autoload（crm/includes/ から ../../vendor/autoload.php）
if (!class_exists('Google\Client') && file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

define('GCAL_CREDENTIALS_FILE', __DIR__ . '/../gcal/credentials.json');
define('GCAL_TOKEN_FILE',       __DIR__ . '/../gcal/token.json');
define('GCAL_REDIRECT_URI',     BASE_URL . '/crm/gcal/callback.php');

function gcal_is_configured(): bool {
    return file_exists(GCAL_CREDENTIALS_FILE) && file_exists(GCAL_TOKEN_FILE);
}

function gcal_get_client(): ?Google\Client {
    if (!file_exists(GCAL_CREDENTIALS_FILE)) return null;

    $creds = json_decode(file_get_contents(GCAL_CREDENTIALS_FILE), true);
    if (empty($creds['client_id']) || empty($creds['client_secret'])) return null;

    $client = new Google\Client();
    $client->setClientId($creds['client_id']);
    $client->setClientSecret($creds['client_secret']);
    $client->setRedirectUri(GCAL_REDIRECT_URI);
    $client->addScope(Google\Service\Calendar::CALENDAR_EVENTS);
    $client->setAccessType('offline');
    $client->setPrompt('consent');

    if (file_exists(GCAL_TOKEN_FILE)) {
        $token = json_decode(file_get_contents(GCAL_TOKEN_FILE), true);
        $client->setAccessToken($token);

        // トークン期限切れなら更新
        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                file_put_contents(GCAL_TOKEN_FILE, json_encode($client->getAccessToken()));
            } else {
                return null; // 再認証が必要
            }
        }
    }

    return $client;
}

function gcal_get_service(): ?Google\Service\Calendar {
    $client = gcal_get_client();
    if (!$client || !file_exists(GCAL_TOKEN_FILE)) return null;
    try {
        return new Google\Service\Calendar($client);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * 期日をGoogleカレンダーに作成 → event_id を返す
 */
function gcal_create_event(array $deadline, string $case_name, string $client_name): ?string {
    $service = gcal_get_service();
    if (!$service) return null;

    try {
        $event = gcal_build_event($deadline, $case_name, $client_name);
        $result = $service->events->insert('primary', $event);
        return $result->getId();
    } catch (Exception $e) {
        error_log('gcal_create_event error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Googleカレンダーのイベントを更新
 */
function gcal_update_event(string $event_id, array $deadline, string $case_name, string $client_name): bool {
    $service = gcal_get_service();
    if (!$service) return false;

    try {
        $event = gcal_build_event($deadline, $case_name, $client_name);
        $service->events->update('primary', $event_id, $event);
        return true;
    } catch (Exception $e) {
        error_log('gcal_update_event error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Googleカレンダーのイベントを削除
 */
function gcal_delete_event(string $event_id): bool {
    $service = gcal_get_service();
    if (!$service) return false;

    try {
        $service->events->delete('primary', $event_id);
        return true;
    } catch (Exception $e) {
        error_log('gcal_delete_event error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Google Calendar Event オブジェクトを生成
 */
function gcal_build_event(array $deadline, string $case_name, string $client_name): Google\Service\Calendar\Event {
    $dt       = new DateTime($deadline['deadline_date'], new DateTimeZone('Asia/Tokyo'));
    $dt_end   = (clone $dt)->modify('+1 hour');
    $type_map = DEADLINE_TYPE_MAP;
    $type_label = $type_map[$deadline['deadline_type']] ?? '';

    $event = new Google\Service\Calendar\Event([
        'summary'     => "【{$type_label}】{$deadline['title']}",
        'description' => "案件: {$case_name}\n依頼者: {$client_name}" . ($deadline['memo'] ? "\nメモ: {$deadline['memo']}" : ''),
        'start'       => ['dateTime' => $dt->format(DateTime::RFC3339), 'timeZone' => 'Asia/Tokyo'],
        'end'         => ['dateTime' => $dt_end->format(DateTime::RFC3339), 'timeZone' => 'Asia/Tokyo'],
        'reminders'   => [
            'useDefault' => false,
            'overrides'  => [
                ['method' => 'email',  'minutes' => 24 * 60],
                ['method' => 'popup',  'minutes' => 60],
            ],
        ],
        'colorId' => '11', // Tomato red
    ]);

    return $event;
}

/**
 * 指定時間帯に既存の予定があるか確認
 */
function gcal_check_conflict(string $datetime_str): array {
    $service = gcal_get_service();
    if (!$service) return [];

    try {
        $dt_start = new DateTime($datetime_str, new DateTimeZone('Asia/Tokyo'));
        $dt_end   = (clone $dt_start)->modify('+1 hour');

        $events = $service->events->listEvents('primary', [
            'timeMin'      => $dt_start->format(DateTime::RFC3339),
            'timeMax'      => $dt_end->format(DateTime::RFC3339),
            'singleEvents' => true,
            'orderBy'      => 'startTime',
        ]);

        $conflicts = [];
        foreach ($events->getItems() as $event) {
            $conflicts[] = $event->getSummary();
        }
        return $conflicts;
    } catch (Exception $e) {
        error_log('gcal_check_conflict error: ' . $e->getMessage());
        return [];
    }
}
