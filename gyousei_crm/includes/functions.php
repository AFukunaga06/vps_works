<?php
require_once __DIR__ . '/db.php';

if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

function fmt_money(int $v): string {
    return '¥' . number_format($v);
}

function fmt_date(?string $d): string {
    if (!$d) return '';
    return date('Y/m/d', strtotime($d));
}

function fmt_datetime(?string $d): string {
    if (!$d) return '';
    return date('Y/m/d H:i', strtotime($d));
}

function case_status_badge(string $s): string {
    $cls = match($s) { 'active' => 'success', 'pending' => 'warning', 'suspended' => 'secondary', 'closed' => 'dark', default => 'light' };
    return '<span class="badge bg-'.$cls.'">'.h(CASE_STATUS_MAP[$s] ?? $s).'</span>';
}

function progress_stage_badge(string $s): string {
    $cls = match($s) {
        'approved','completed' => 'success',
        'submitted','reviewing' => 'info',
        'correction' => 'danger',
        'withdrawn' => 'dark',
        'inquiry','accepted' => 'secondary',
        default => 'warning',
    };
    return '<span class="badge bg-'.$cls.' text-'.($cls==='warning'?'dark':'white').'">'.h(PROGRESS_STAGE_MAP[$s] ?? $s).'</span>';
}

function client_status_badge(string $s): string {
    $cls = match($s) { 'active' => 'success', 'pending' => 'warning', 'closed' => 'secondary', default => 'light' };
    return '<span class="badge bg-'.$cls.'">'.h(CLIENT_STATUS_MAP[$s] ?? $s).'</span>';
}

function billing_paid_badge(int $paid): string {
    return $paid
        ? '<span class="badge bg-success">入金済</span>'
        : '<span class="badge bg-danger">未入金</span>';
}

// ===== ユーザー =====

function get_all_users(): array {
    return get_db()->query("SELECT * FROM gc_users WHERE is_active=1 ORDER BY role,name")->fetchAll();
}

function get_user(int $id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM gc_users WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_gyosei_users(): array {
    return get_db()->query("SELECT * FROM gc_users WHERE role IN ('admin','gyosei') AND is_active=1 ORDER BY name")->fetchAll();
}

// ===== 依頼人 =====

function get_clients(array $f = [], int $page = 1, int $per = 25): array {
    $db = get_db();
    $where = ['1=1']; $params = [];

    if (!empty($f['q'])) {
        $q = '%' . $f['q'] . '%';
        $where[] = '(c.name LIKE ? OR c.kana LIKE ? OR c.company_name LIKE ? OR c.email LIKE ? OR c.tel LIKE ?)';
        array_push($params, $q, $q, $q, $q, $q);
    }
    if (!empty($f['status'])) { $where[] = 'c.status=?'; $params[] = $f['status']; }

    $w = implode(' AND ', $where);
    $cnt = $db->prepare("SELECT COUNT(*) FROM gc_clients c WHERE $w");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $offset = ($page - 1) * $per;
    $stmt = $db->prepare(
        "SELECT c.*, u.name AS user_name,
            (SELECT COUNT(*) FROM gc_cases WHERE client_id=c.id) AS case_count
         FROM gc_clients c
         LEFT JOIN gc_users u ON u.id=c.assigned_user_id
         WHERE $w ORDER BY c.kana, c.id LIMIT $per OFFSET $offset"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => (int)ceil($total / $per)];
}

function get_client(int $id): ?array {
    $stmt = get_db()->prepare(
        "SELECT c.*, u.name AS user_name
         FROM gc_clients c LEFT JOIN gc_users u ON u.id=c.assigned_user_id
         WHERE c.id=?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function save_client(array $d, ?int $id = null): int {
    $db = get_db();
    $fields = ['name','kana','company_name','company_kana','email','tel','tel2','address','birth_date','gender','assigned_user_id','status','memo'];
    $vals = [];
    foreach ($fields as $k) {
        $v = $d[$k] ?? '';
        $vals[] = ($v === '') ? null : $v;
    }
    if ($id) {
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE gc_clients SET $set WHERE id=?")->execute([...$vals, $id]);
        return $id;
    } else {
        $cols = implode(',', $fields);
        $ph   = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO gc_clients ($cols) VALUES ($ph)")->execute($vals);
        return (int)$db->lastInsertId();
    }
}

// ===== 案件 =====

function get_cases(array $f = [], int $page = 1, int $per = 25): array {
    $db = get_db();
    $where = ['1=1']; $params = [];

    if (!empty($f['q'])) {
        $q = '%' . $f['q'] . '%';
        $where[] = '(cs.case_name LIKE ? OR cs.case_number LIKE ? OR cl.name LIKE ? OR cl.company_name LIKE ?)';
        array_push($params, $q, $q, $q, $q);
    }
    if (!empty($f['status']))    { $where[] = 'cs.status=?';     $params[] = $f['status']; }
    if (!empty($f['case_type'])) { $where[] = 'cs.case_type=?';  $params[] = $f['case_type']; }
    if (!empty($f['stage']))     { $where[] = 'cs.progress_stage=?'; $params[] = $f['stage']; }
    if (isset($f['client_id']) && $f['client_id'] > 0) { $where[] = 'cs.client_id=?'; $params[] = $f['client_id']; }

    $w = implode(' AND ', $where);
    $cnt = $db->prepare("SELECT COUNT(*) FROM gc_cases cs JOIN gc_clients cl ON cl.id=cs.client_id WHERE $w");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $offset = ($page - 1) * $per;
    $stmt = $db->prepare(
        "SELECT cs.*, cl.name AS client_name, cl.company_name, u.name AS user_name,
            (SELECT COUNT(*) FROM gc_deadlines WHERE case_id=cs.id AND is_done=0 AND deadline_date >= NOW()) AS open_deadlines,
            (SELECT COUNT(*) FROM gc_doc_checklist WHERE case_id=cs.id) AS doc_total,
            (SELECT COUNT(*) FROM gc_doc_checklist WHERE case_id=cs.id AND is_obtained=1) AS doc_obtained
         FROM gc_cases cs
         JOIN gc_clients cl ON cl.id=cs.client_id
         LEFT JOIN gc_users u ON u.id=cs.assigned_user_id
         WHERE $w ORDER BY cs.status='active' DESC, cs.opened_date DESC LIMIT $per OFFSET $offset"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => (int)ceil($total / $per)];
}

function get_case(int $id): ?array {
    $stmt = get_db()->prepare(
        "SELECT cs.*, cl.name AS client_name, cl.company_name, u.name AS user_name
         FROM gc_cases cs
         JOIN gc_clients cl ON cl.id=cs.client_id
         LEFT JOIN gc_users u ON u.id=cs.assigned_user_id
         WHERE cs.id=?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function save_case(array $d, ?int $id = null): int {
    $db = get_db();
    $fields = ['client_id','case_number','case_name','case_type','status','progress_stage','assigned_user_id','opened_date','permit_expiry_date','closed_date','government_office','application_number','memo'];
    $vals = [];
    foreach ($fields as $k) {
        $v = $d[$k] ?? '';
        $vals[] = ($v === '') ? null : $v;
    }
    if ($id) {
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE gc_cases SET $set WHERE id=?")->execute([...$vals, $id]);
        return $id;
    } else {
        $cols = implode(',', $fields);
        $ph   = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO gc_cases ($cols) VALUES ($ph)")->execute($vals);
        return (int)$db->lastInsertId();
    }
}

// ===== 期日 =====

function get_deadlines(array $f = []): array {
    $db = get_db();
    $where = ['1=1']; $params = [];

    if (isset($f['case_id']) && $f['case_id'] > 0) { $where[] = 'd.case_id=?'; $params[] = $f['case_id']; }
    if (isset($f['is_done'])) { $where[] = 'd.is_done=?'; $params[] = (int)$f['is_done']; }
    if (!empty($f['from'])) { $where[] = 'd.deadline_date >= ?'; $params[] = $f['from']; }
    if (!empty($f['to']))   { $where[] = 'd.deadline_date <= ?'; $params[] = $f['to']; }

    $w = implode(' AND ', $where);
    $stmt = $db->prepare(
        "SELECT d.*, cs.case_name, cs.case_number, cl.name AS client_name, cl.company_name
         FROM gc_deadlines d
         JOIN gc_cases cs ON cs.id=d.case_id
         JOIN gc_clients cl ON cl.id=cs.client_id
         WHERE $w ORDER BY d.deadline_date ASC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function save_deadline(array $d, ?int $id = null): int {
    $db = get_db();
    $fields = ['case_id','title','deadline_date','deadline_type','is_done','memo'];
    $vals = array_map(fn($k) => $d[$k] ?? '', $fields);
    $vals[4] = (int)($d['is_done'] ?? 0);
    if ($id) {
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE gc_deadlines SET $set WHERE id=?")->execute([...$vals, $id]);
        return $id;
    } else {
        $cols = implode(',', $fields);
        $ph   = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO gc_deadlines ($cols) VALUES ($ph)")->execute($vals);
        return (int)$db->lastInsertId();
    }
}

// ===== 必要書類チェックリスト =====

function get_checklist(int $case_id): array {
    $stmt = get_db()->prepare("SELECT * FROM gc_doc_checklist WHERE case_id=? ORDER BY sort_order, id");
    $stmt->execute([$case_id]);
    return $stmt->fetchAll();
}

function save_checklist_item(array $d, ?int $id = null): int {
    $db = get_db();
    if ($id) {
        $db->prepare("UPDATE gc_doc_checklist SET doc_name=?,is_obtained=?,note=?,sort_order=? WHERE id=?")
           ->execute([$d['doc_name'], (int)($d['is_obtained'] ?? 0), $d['note'] ?? '', $d['sort_order'] ?? 0, $id]);
        return $id;
    } else {
        $db->prepare("INSERT INTO gc_doc_checklist (case_id,doc_name,is_obtained,note,sort_order) VALUES (?,?,?,?,?)")
           ->execute([$d['case_id'], $d['doc_name'], (int)($d['is_obtained'] ?? 0), $d['note'] ?? '', $d['sort_order'] ?? 0]);
        return (int)$db->lastInsertId();
    }
}

// ===== 進捗記録 =====

function get_progress_list(int $case_id): array {
    $stmt = get_db()->prepare(
        "SELECT p.*, u.name AS user_name
         FROM gc_progress p LEFT JOIN gc_users u ON u.id=p.user_id
         WHERE p.case_id=? ORDER BY p.recorded_at DESC"
    );
    $stmt->execute([$case_id]);
    return $stmt->fetchAll();
}

function save_progress(array $d, ?int $id = null): int {
    $db = get_db();
    if ($id) {
        $db->prepare("UPDATE gc_progress SET progress_type=?,content=?,recorded_at=? WHERE id=?")
           ->execute([$d['progress_type'], $d['content'], $d['recorded_at'], $id]);
        return $id;
    } else {
        $db->prepare("INSERT INTO gc_progress (case_id,user_id,progress_type,content,recorded_at) VALUES (?,?,?,?,?)")
           ->execute([$d['case_id'], $d['user_id'], $d['progress_type'], $d['content'], $d['recorded_at']]);
        return (int)$db->lastInsertId();
    }
}

// ===== 報酬管理 =====

function get_billings(int $case_id): array {
    $stmt = get_db()->prepare("SELECT * FROM gc_billing WHERE case_id=? ORDER BY billed_date DESC, id DESC");
    $stmt->execute([$case_id]);
    return $stmt->fetchAll();
}

function get_billing_summary(int $case_id): array {
    $stmt = get_db()->prepare(
        "SELECT COALESCE(SUM(amount),0) AS total, COALESCE(SUM(CASE WHEN is_paid=1 THEN amount ELSE 0 END),0) AS paid
         FROM gc_billing WHERE case_id=?"
    );
    $stmt->execute([$case_id]);
    return $stmt->fetch();
}

function save_billing(array $d, ?int $id = null): int {
    $db = get_db();
    $fields = ['case_id','billing_type','title','amount','billed_date','paid_date','is_paid','memo'];
    $vals = [];
    foreach ($fields as $k) {
        $v = $d[$k] ?? '';
        $vals[] = ($v === '') ? null : $v;
    }
    $vals[6] = (int)($d['is_paid'] ?? 0);
    if ($id) {
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE gc_billing SET $set WHERE id=?")->execute([...$vals, $id]);
        return $id;
    } else {
        $cols = implode(',', $fields);
        $ph   = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO gc_billing ($cols) VALUES ($ph)")->execute($vals);
        return (int)$db->lastInsertId();
    }
}

// ===== ダッシュボード集計 =====

function get_dashboard_stats(): array {
    $db = get_db();
    return [
        'active_clients'     => (int)$db->query("SELECT COUNT(*) FROM gc_clients WHERE status='active'")->fetchColumn(),
        'active_cases'       => (int)$db->query("SELECT COUNT(*) FROM gc_cases WHERE status='active'")->fetchColumn(),
        'upcoming_deadlines' => (int)$db->query(
            "SELECT COUNT(*) FROM gc_deadlines WHERE is_done=0 AND deadline_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 14 DAY)"
        )->fetchColumn(),
        'unpaid_billing' => (int)$db->query("SELECT COALESCE(SUM(amount),0) FROM gc_billing WHERE is_paid=0")->fetchColumn(),
        'correction_cases'   => (int)$db->query("SELECT COUNT(*) FROM gc_cases WHERE progress_stage='correction'")->fetchColumn(),
    ];
}

function get_upcoming_deadlines(int $days = 14): array {
    $stmt = get_db()->prepare(
        "SELECT d.*, cs.case_name, cs.case_number, cl.name AS client_name, cl.company_name
         FROM gc_deadlines d
         JOIN gc_cases cs ON cs.id=d.case_id
         JOIN gc_clients cl ON cl.id=cs.client_id
         WHERE d.is_done=0 AND d.deadline_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
         ORDER BY d.deadline_date ASC LIMIT 20"
    );
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

function get_recent_progress(int $limit = 10): array {
    $stmt = get_db()->prepare(
        "SELECT p.*, cs.case_name, cl.name AS client_name, cl.company_name, u.name AS user_name
         FROM gc_progress p
         JOIN gc_cases cs ON cs.id=p.case_id
         JOIN gc_clients cl ON cl.id=cs.client_id
         LEFT JOIN gc_users u ON u.id=p.user_id
         ORDER BY p.recorded_at DESC LIMIT ?"
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}
