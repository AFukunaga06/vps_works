<?php
require_once __DIR__ . '/db.php';

// ===== ヘルパ =====================================================

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): bool {
    $t = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $t);
}

// 公開ページのテナント解決 — ?t=N または DEFAULT_TENANT_ID
function resolve_public_tenant_id(): int {
    $t = isset($_GET['t']) ? (int)$_GET['t'] : DEFAULT_TENANT_ID;
    return $t > 0 ? $t : DEFAULT_TENANT_ID;
}

// ===== 設定取得 ===================================================

function get_settings(int $tenant_id): array {
    $stmt = db()->prepare('SELECT * FROM lc_settings_reserve WHERE tenant_id = ?');
    $stmt->execute([$tenant_id]);
    $row = $stmt->fetch();
    if (!$row) {
        // 該当テナント用がまだない場合はデフォルト値を返す
        return [
            'id' => null,
            'tenant_id' => $tenant_id,
            'business_name' => '法律事務所',
            'slot_minutes' => 30,
            'open_time' => '10:00:00',
            'close_time' => '18:00:00',
            'open_days' => '1,2,3,4,5',
            'buffer_minutes' => 0,
            'advance_days' => 30,
            'cancel_deadline_hours' => 24,
            'contact_email' => '',
            'contact_tel' => '',
            'consultation_types' => '一般相談,離婚,相続,刑事,労働,債務整理,その他',
            'notice_message' => '',
        ];
    }
    return $row;
}

function get_exception(int $tenant_id, string $date): ?array {
    $stmt = db()->prepare(
        'SELECT * FROM lc_appointment_slots WHERE tenant_id = ? AND exception_date = ?'
    );
    $stmt->execute([$tenant_id, $date]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_exceptions_in_range(int $tenant_id, string $from, string $to): array {
    $stmt = db()->prepare(
        'SELECT * FROM lc_appointment_slots
         WHERE tenant_id = ? AND exception_date BETWEEN ? AND ?
         ORDER BY exception_date'
    );
    $stmt->execute([$tenant_id, $from, $to]);
    $rows = $stmt->fetchAll();
    $byDate = [];
    foreach ($rows as $r) {
        $byDate[$r['exception_date']] = $r;
    }
    return $byDate;
}

// ===== スロット計算 ===============================================

/**
 * 指定日における [HH:MM-HH:MM, ...] のスロット一覧を返す。
 * 営業日 / 例外日 / 過去 / advance_days を加味する。
 *
 * @return array{slots: array<int, array{start:string,end:string,booked:bool}>, status: string}
 *  status: 'closed'|'past'|'out_of_range'|'available'
 */
function compute_day_slots(array $settings, int $tenant_id, string $date): array {
    $today = date('Y-m-d');
    $maxDate = date('Y-m-d', strtotime("+{$settings['advance_days']} days"));

    if ($date < $today) {
        return ['slots' => [], 'status' => 'past'];
    }
    if ($date > $maxDate) {
        return ['slots' => [], 'status' => 'out_of_range'];
    }

    // 例外日チェック
    $exc = get_exception($tenant_id, $date);

    $isOpen = false;
    $openT = $settings['open_time'];
    $closeT = $settings['close_time'];

    if ($exc) {
        if ($exc['type'] === 'closed') {
            return ['slots' => [], 'status' => 'closed'];
        }
        // 'open' 例外 — 上書き可能
        $isOpen = true;
        if (!empty($exc['open_time'])) $openT = $exc['open_time'];
        if (!empty($exc['close_time'])) $closeT = $exc['close_time'];
    } else {
        $dow = (int)date('w', strtotime($date));        // 0=日 ... 6=土
        $openDays = array_map('intval', array_filter(explode(',', $settings['open_days']), 'strlen'));
        if (in_array($dow, $openDays, true)) {
            $isOpen = true;
        }
    }

    if (!$isOpen) {
        return ['slots' => [], 'status' => 'closed'];
    }

    // 当該日の既存予約 (active = pending or confirmed)
    $stmt = db()->prepare(
        "SELECT start_time, end_time FROM lc_appointments
          WHERE tenant_id = ? AND reservation_date = ?
            AND status IN ('pending','confirmed')"
    );
    $stmt->execute([$tenant_id, $date]);
    $booked = $stmt->fetchAll();

    $slotMin = max(5, (int)$settings['slot_minutes']);
    $buffer  = max(0, (int)$settings['buffer_minutes']);

    $slots = [];
    $cursor = strtotime("$date {$openT}");
    $endTs  = strtotime("$date {$closeT}");

    while ($cursor + $slotMin * 60 <= $endTs) {
        $s = date('H:i:s', $cursor);
        $e = date('H:i:s', $cursor + $slotMin * 60);

        // ブッキング判定 — バッファ込み
        $isBooked = false;
        foreach ($booked as $b) {
            $bs = strtotime("$date {$b['start_time']}") - $buffer * 60;
            $be = strtotime("$date {$b['end_time']}")   + $buffer * 60;
            if ($cursor < $be && ($cursor + $slotMin * 60) > $bs) {
                $isBooked = true;
                break;
            }
        }

        // 当日の場合は現在時刻より前のスロットは出さない
        if ($date === $today && $cursor < time()) {
            $isBooked = true; // 表示はするが選択不可とする扱い
        }

        $slots[] = ['start' => $s, 'end' => $e, 'booked' => $isBooked];
        $cursor += $slotMin * 60;
    }

    return ['slots' => $slots, 'status' => 'available'];
}

// 指定月の各日について 'available' | 'full' | 'closed' | 'past' | 'out_of_range' を返す
function compute_month_summary(array $settings, int $tenant_id, int $year, int $month): array {
    $first = sprintf('%04d-%02d-01', $year, $month);
    $last  = date('Y-m-t', strtotime($first));
    $result = [];
    $cur = $first;
    while ($cur <= $last) {
        $info = compute_day_slots($settings, $tenant_id, $cur);
        if ($info['status'] !== 'available') {
            $result[$cur] = $info['status'];
        } else {
            $hasFree = false;
            foreach ($info['slots'] as $s) {
                if (!$s['booked']) { $hasFree = true; break; }
            }
            $result[$cur] = $hasFree ? 'available' : 'full';
        }
        $cur = date('Y-m-d', strtotime($cur . ' +1 day'));
    }
    return $result;
}

// ===== ラベル化 ===================================================

function status_label(string $s): string {
    return [
        'pending'   => '仮予約',
        'confirmed' => '確定',
        'canceled'  => 'キャンセル',
        'noshow'    => '不来訪',
    ][$s] ?? $s;
}

function dow_label(int $d): string {
    return ['日','月','火','水','木','金','土'][$d] ?? '';
}
