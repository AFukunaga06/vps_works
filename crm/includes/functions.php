<?php
require_once __DIR__ . '/db.php';

if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

function crm_status_badge(string $s): string {
    $cls = match($s) { 'active' => 'success', 'pending' => 'warning', 'inactive' => 'secondary', default => 'light' };
    $map = STATUS_MAP;
    return '<span class="badge bg-'.$cls.'">' . h($map[$s] ?? $s) . '</span>';
}

// ===== 顧客 =====

function get_customers(array $f = [], int $page = 1, int $per = 25): array {
    $db = get_db();
    $where = ['1=1']; $params = [];

    if (!empty($f['q'])) {
        $q = '%'.$f['q'].'%';
        $where[] = '(c.name LIKE ? OR c.kana LIKE ? OR c.email LIKE ? OR c.tel LIKE ?)';
        array_push($params, $q, $q, $q, $q);
    }
    if (!empty($f['status'])) { $where[] = 'c.member_status=?'; $params[] = $f['status']; }
    if (!empty($f['tag'])) {
        $where[] = 'EXISTS(SELECT 1 FROM crm_customer_tags t WHERE t.customer_id=c.id AND t.tag=?)';
        $params[] = $f['tag'];
    }

    $w = implode(' AND ', $where);
    $cnt = $db->prepare("SELECT COUNT(*) FROM crm_customers c WHERE $w");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $offset = ($page - 1) * $per;
    $stmt = $db->prepare(
        "SELECT c.*,
            (SELECT MAX(consulted_at) FROM crm_consultations WHERE customer_id=c.id) AS last_consulted
         FROM crm_customers c WHERE $w ORDER BY c.kana, c.id LIMIT $per OFFSET $offset"
    );
    $stmt->execute($params);

    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => (int)ceil($total / $per)];
}

function get_customer(int $id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM crm_customers WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function save_customer(array $d, ?int $id = null): int {
    $db = get_db();
    $fields = ['name','kana','email','tel','address','birth_date','member_status','member_since','memo'];
    $vals = array_map(fn($k) => ($d[$k] ?? '') ?: null, $fields);
    // birth_date, member_since が空なら NULL
    $vals[5] = $d['birth_date'] ?: null;
    $vals[7] = $d['member_since'] ?: null;

    if ($id) {
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE crm_customers SET $set WHERE id=?")->execute([...$vals, $id]);
        return $id;
    } else {
        $cols = implode(',', $fields);
        $ph   = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO crm_customers ($cols) VALUES ($ph)")->execute($vals);
        return (int)$db->lastInsertId();
    }
}

function get_customer_tags(int $cid): array {
    $stmt = get_db()->prepare("SELECT tag FROM crm_customer_tags WHERE customer_id=? ORDER BY tag");
    $stmt->execute([$cid]);
    return array_column($stmt->fetchAll(), 'tag');
}

function save_customer_tags(int $cid, array $tags): void {
    $db = get_db();
    $db->prepare("DELETE FROM crm_customer_tags WHERE customer_id=?")->execute([$cid]);
    $stmt = $db->prepare("INSERT IGNORE INTO crm_customer_tags (customer_id,tag) VALUES (?,?)");
    foreach ($tags as $t) { $t = trim($t); if ($t !== '') $stmt->execute([$cid, $t]); }
}

function get_all_tags(): array {
    $rows = get_db()->query("SELECT DISTINCT tag FROM crm_customer_tags ORDER BY tag")->fetchAll();
    return array_column($rows, 'tag');
}

// フクの相談窓口の予約から顧客・相談履歴を自動登録
function upsert_customer_from_reservation(array $r): int {
    $db = get_db();

    // 顧客の登録または既存IDの取得
    $customer_id = null;
    if ($r['email']) {
        $stmt = $db->prepare("SELECT id FROM crm_customers WHERE email=?");
        $stmt->execute([$r['email']]);
        $customer_id = $stmt->fetchColumn() ?: null;
    }
    if (!$customer_id) {
        $db->prepare(
            "INSERT INTO crm_customers (name,kana,email,tel,member_status,member_since,source,memo)
             VALUES (?,?,?,?,'pending',CURDATE(),'fuku_soudan','')"
        )->execute([$r['name'], $r['kana'], $r['email'], $r['tel']]);
        $customer_id = (int)$db->lastInsertId();
    }

    // 同じreserve_idで既に相談記録がある場合は重複登録しない
    if (!empty($r['reserve_id'])) {
        $chk = $db->prepare("SELECT id FROM crm_consultations WHERE reserve_id=?");
        $chk->execute([$r['reserve_id']]);
        if ($chk->fetchColumn()) return $customer_id;
    }

    // 担当スタッフ：最初の管理者を使用
    $staff_id = $db->query("SELECT id FROM crm_staff WHERE is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
    if (!$staff_id) return $customer_id;

    // 相談件数から初回判定
    $cnt = $db->prepare("SELECT COUNT(*) FROM crm_consultations WHERE customer_id=?");
    $cnt->execute([$customer_id]);
    $is_first = ((int)$cnt->fetchColumn() === 0) ? 1 : 0;

    // 相談日時（予約日 + 開始時刻）
    $consulted_at = $r['date'] . ' ' . $r['start_time'] . ':00';

    $db->prepare(
        "INSERT INTO crm_consultations
         (customer_id, staff_id, consulted_at, consultation_type, method,
          is_first, content, result, next_action, reserve_id)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $customer_id,
        $staff_id,
        $consulted_at,
        $r['type'] ?? '',
        $r['consult_method'] ?? '',
        $is_first,
        $r['content'] ?? '',
        '',
        '',
        $r['reserve_id'] ?? null,
    ]);

    return $customer_id;
}

// ===== 相談履歴 =====

function get_consultations(int $cid): array {
    $stmt = get_db()->prepare(
        "SELECT cn.*, s.name AS staff_name
         FROM crm_consultations cn LEFT JOIN crm_staff s ON s.id=cn.staff_id
         WHERE cn.customer_id=? ORDER BY cn.consulted_at DESC"
    );
    $stmt->execute([$cid]);
    return $stmt->fetchAll();
}

function get_consultation(int $id): ?array {
    $stmt = get_db()->prepare(
        "SELECT cn.*, s.name AS staff_name
         FROM crm_consultations cn LEFT JOIN crm_staff s ON s.id=cn.staff_id
         WHERE cn.id=?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_consultation_count(int $cid): int {
    $stmt = get_db()->prepare("SELECT COUNT(*) FROM crm_consultations WHERE customer_id=?");
    $stmt->execute([$cid]);
    return (int)$stmt->fetchColumn();
}

function save_consultation(array $d, ?int $id = null): int {
    $db  = get_db();
    $isf = (int)(bool)($d['is_first'] ?? 0);

    $common = [
        'customer_id'       => (int)$d['customer_id'],
        'staff_id'          => (int)$d['staff_id'],
        'consulted_at'      => $d['consulted_at'],
        'consultation_type' => $d['consultation_type'] ?? '',
        'method'            => $d['method'] ?? '',
        'is_first'          => $isf,
        'content'           => $d['content'] ?? '',
        'result'            => $d['result'] ?? '',
        'next_action'       => $d['next_action'] ?? '',
        // 初回専用
        'first_background'       => $isf ? ($d['first_background'] ?? null) : null,
        'first_living_situation' => $isf ? ($d['first_living_situation'] ?? null) : null,
        'first_urgency'          => $isf ? ($d['first_urgency'] ?? null) : null,
        // 2回目以降専用
        'repeat_progress'        => !$isf ? ($d['repeat_progress'] ?? null) : null,
        'repeat_payment_status'  => !$isf ? ($d['repeat_payment_status'] ?? null) : null,
        'repeat_materials'       => !$isf ? ($d['repeat_materials'] ?? null) : null,
        'reserve_id'             => $d['reserve_id'] ? (int)$d['reserve_id'] : null,
    ];

    if ($id) {
        $set = implode(',', array_map(fn($k) => "$k=?", array_keys($common)));
        $db->prepare("UPDATE crm_consultations SET $set WHERE id=?")->execute([...array_values($common), $id]);
        return $id;
    } else {
        $cols = implode(',', array_keys($common));
        $ph   = implode(',', array_fill(0, count($common), '?'));
        $db->prepare("INSERT INTO crm_consultations ($cols) VALUES ($ph)")->execute(array_values($common));
        return (int)$db->lastInsertId();
    }
}

// ===== スタッフ =====

function get_all_staff(): array {
    return get_db()->query("SELECT * FROM crm_staff ORDER BY name")->fetchAll();
}

function get_staff(int $id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM crm_staff WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// ===== CSV =====

function export_customers_csv(): void {
    $rows = get_db()->query(
        "SELECT c.id, c.name, c.kana, c.email, c.tel, c.address, c.birth_date,
                c.member_status, c.member_since, c.source, c.memo, c.created_at,
                GROUP_CONCAT(t.tag ORDER BY t.tag SEPARATOR '/') AS tags
         FROM crm_customers c
         LEFT JOIN crm_customer_tags t ON t.customer_id=c.id
         GROUP BY c.id ORDER BY c.kana"
    )->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="customers_' . date('Ymd') . '.csv"');
    $fp = fopen('php://output', 'w');
    fprintf($fp, "\xEF\xBB\xBF"); // BOM
    fputcsv($fp, ['ID','氏名','ふりがな','メール','電話','住所','生年月日','会員ステータス','会員登録日','登録元','タグ','メモ','登録日時']);
    foreach ($rows as $r) {
        $sm = STATUS_MAP;
        fputcsv($fp, [
            $r['id'], $r['name'], $r['kana'], $r['email'], $r['tel'], $r['address'],
            $r['birth_date'], $sm[$r['member_status']] ?? $r['member_status'],
            $r['member_since'], $r['source'] === 'fuku_soudan' ? 'フクの相談窓口' : '手動',
            $r['tags'], $r['memo'], $r['created_at'],
        ]);
    }
    fclose($fp);
}

function export_consultations_csv(): void {
    $rows = get_db()->query(
        "SELECT cn.id, c.name, c.kana, cn.consulted_at, cn.consultation_type, cn.method,
                cn.is_first, s.name AS staff_name,
                cn.content, cn.result, cn.next_action,
                cn.first_background, cn.first_living_situation, cn.first_urgency,
                cn.repeat_progress, cn.repeat_payment_status, cn.repeat_materials
         FROM crm_consultations cn
         JOIN crm_customers c ON c.id=cn.customer_id
         LEFT JOIN crm_staff s ON s.id=cn.staff_id
         ORDER BY cn.consulted_at DESC"
    )->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="consultations_' . date('Ymd') . '.csv"');
    $fp = fopen('php://output', 'w');
    fprintf($fp, "\xEF\xBB\xBF");
    fputcsv($fp, [
        'ID','顧客氏名','ふりがな','相談日時','種別','方法','初回','担当者',
        '相談内容','対応結果','次回対応',
        '【初回】経緯','【初回】生活状況','【初回】緊急度',
        '【継続】前回からの変化','【継続】入金状況','【継続】書類等',
    ]);
    $um = URGENCY_MAP;
    foreach ($rows as $r) {
        fputcsv($fp, [
            $r['id'], $r['name'], $r['kana'],
            $r['consulted_at'], $r['consultation_type'], $r['method'],
            $r['is_first'] ? '初回' : '2回目以降', $r['staff_name'],
            $r['content'], $r['result'], $r['next_action'],
            $r['first_background'], $r['first_living_situation'],
            $um[$r['first_urgency'] ?? ''] ?? '',
            $r['repeat_progress'], $r['repeat_payment_status'], $r['repeat_materials'],
        ]);
    }
    fclose($fp);
}
