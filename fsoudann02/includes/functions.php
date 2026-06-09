<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

/**
 * 営業日かどうか（月〜土、日曜休み）
 */
function is_business_day(string $date): bool {
    $w = (int)date('w', strtotime($date));
    return $w >= 1 && $w <= 6;
}

/**
 * 休業日設定に含まれるか
 */
function is_blocked(string $date): bool {
    $db = get_db();
    $stmt = $db->prepare("SELECT id FROM blocked_dates WHERE blocked_date = ?");
    $stmt->execute([$date]);
    return (bool)$stmt->fetch();
}

/**
 * 個別にブロックされている時間枠を取得
 */
function get_blocked_slots(string $date): array {
    $db = get_db();
    $stmt = $db->prepare(
        "SELECT TIME_FORMAT(start_time, '%H:%i') AS t FROM blocked_slots WHERE blocked_date = ?"
    );
    $stmt->execute([$date]);
    return array_column($stmt->fetchAll(), 't');
}

/**
 * 予約済み時間枠を取得（キャンセル除く）
 */
function get_booked_slots(string $date, string $type): array {
    $db = get_db();
    $stmt = $db->prepare(
        "SELECT TIME_FORMAT(start_time, '%H:%i') AS t
         FROM reservations
         WHERE reserve_date = ? AND consultation_type = ? AND status != 'cancelled'"
    );
    $stmt->execute([$date, $type]);
    return array_column($stmt->fetchAll(), 't');
}

/**
 * 空き枠を取得（予約済み＋弁護士スケジュールブロック済みを除外）
 */
function get_available_slots(string $date, string $type): array {
    $booked  = get_booked_slots($date, $type);
    $blocked = get_blocked_slots($date);
    $unavail = array_unique(array_merge($booked, $blocked));
    return array_values(array_filter(TIME_SLOTS, fn($s) => !in_array($s, $unavail)));
}

/**
 * 空き枠数を取得（カレンダー表示用）
 */
function get_available_count(string $date, string $type): int {
    return count(get_available_slots($date, $type));
}

/**
 * 日付が予約可能か（過去・休業・日曜を除く）
 */
function is_bookable_date(string $date): bool {
    $today    = date('Y-m-d');
    $deadline = date('Y-m-d', strtotime('+14 days'));
    return $date > $today && $date <= $deadline && is_business_day($date) && !is_blocked($date);
}

/**
 * ステータスの日本語表示
 */
function status_label(string $status): string {
    return match($status) {
        'pending'         => '申込済み',
        'pending_payment' => '入金待ち',
        'confirmed'       => '予約確定',
        'cancelled'       => 'キャンセル',
        'completed'       => '完了',
        default           => $status,
    };
}

function status_badge(string $status): string {
    $class = match($status) {
        'pending'         => 'warning',
        'pending_payment' => 'info',
        'confirmed'       => 'success',
        'cancelled'       => 'secondary',
        'completed'       => 'primary',
        default           => 'light',
    };
    return '<span class="badge bg-' . $class . '">' . status_label($status) . '</span>';
}

/**
 * XSSエスケープ
 */
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
