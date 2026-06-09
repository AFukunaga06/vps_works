<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tenant.php';

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
    $m = CASE_STATUS_MAP;
    return '<span class="badge bg-'.$cls.'">' . h($m[$s] ?? $s) . '</span>';
}

function client_status_badge(string $s): string {
    $cls = match($s) { 'active' => 'success', 'pending' => 'warning', 'closed' => 'secondary', default => 'light' };
    $m = CLIENT_STATUS_MAP;
    return '<span class="badge bg-'.$cls.'">' . h($m[$s] ?? $s) . '</span>';
}

function billing_paid_badge(int $paid): string {
    return $paid
        ? '<span class="badge bg-success">入金済</span>'
        : '<span class="badge bg-danger">未入金</span>';
}

/**
 * 現在テナントID（必須）。未ログイン状態で呼ばれることはないが安全策。
 */
function _tid(): int {
    $tid = lc_current_tenant_id();
    if (!$tid) { http_response_code(403); exit('テナント未指定'); }
    return $tid;
}

// ===== ユーザー =====

function get_all_users(): array {
    $stmt = get_db()->prepare("SELECT * FROM lc_users WHERE tenant_id=? AND is_active=1 ORDER BY role,name");
    $stmt->execute([_tid()]);
    return $stmt->fetchAll();
}

function get_user(int $id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM lc_users WHERE id=? AND tenant_id=?");
    $stmt->execute([$id, _tid()]);
    return $stmt->fetch() ?: null;
}

function get_lawyers(): array {
    $stmt = get_db()->prepare("SELECT * FROM lc_users WHERE tenant_id=? AND role IN ('admin','lawyer') AND is_active=1 ORDER BY name");
    $stmt->execute([_tid()]);
    return $stmt->fetchAll();
}

// ===== 依頼者 =====

function get_clients(array $f = [], int $page = 1, int $per = 25): array {
    $db = get_db();
    $tid = _tid();
    $where = ['c.tenant_id=?']; $params = [$tid];

    if (!empty($f['q'])) {
        $q = '%' . $f['q'] . '%';
        $where[] = '(c.name LIKE ? OR c.kana LIKE ? OR c.email LIKE ? OR c.tel LIKE ?)';
        array_push($params, $q, $q, $q, $q);
    }
    if (!empty($f['status'])) { $where[] = 'c.status=?'; $params[] = $f['status']; }
    if (!empty($f['lawyer_id'])) { $where[] = 'c.assigned_lawyer_id=?'; $params[] = $f['lawyer_id']; }

    $w = implode(' AND ', $where);
    $cnt = $db->prepare("SELECT COUNT(*) FROM lc_clients c WHERE $w");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $offset = ($page - 1) * $per;
    $stmt = $db->prepare(
        "SELECT c.*, u.name AS lawyer_name,
            (SELECT COUNT(*) FROM lc_cases WHERE client_id=c.id AND tenant_id=c.tenant_id) AS case_count
         FROM lc_clients c
         LEFT JOIN lc_users u ON u.id=c.assigned_lawyer_id AND u.tenant_id=c.tenant_id
         WHERE $w ORDER BY c.kana, c.id LIMIT $per OFFSET $offset"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => (int)ceil($total / $per)];
}

function get_client(int $id): ?array {
    $stmt = get_db()->prepare(
        "SELECT c.*, u.name AS lawyer_name
         FROM lc_clients c LEFT JOIN lc_users u ON u.id=c.assigned_lawyer_id AND u.tenant_id=c.tenant_id
         WHERE c.id=? AND c.tenant_id=?"
    );
    $stmt->execute([$id, _tid()]);
    return $stmt->fetch() ?: null;
}

function save_client(array $d, ?int $id = null): int {
    $db = get_db();
    $tid = _tid();
    $fields = ['name','kana','email','tel','tel2','address','birth_date','gender','occupation','assigned_lawyer_id','status','memo'];
    $vals = [];
    foreach ($fields as $k) {
        $v = $d[$k] ?? '';
        $vals[] = ($v === '') ? null : $v;
    }
    if ($id) {
        // 既存所有確認
        $own = $db->prepare("SELECT id FROM lc_clients WHERE id=? AND tenant_id=?");
        $own->execute([$id, $tid]);
        if (!$own->fetchColumn()) { http_response_code(403); exit('権限がありません'); }
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE lc_clients SET $set WHERE id=? AND tenant_id=?")->execute([...$vals, $id, $tid]);
        return $id;
    } else {
        $cols = implode(',', $fields) . ',tenant_id';
        $ph   = implode(',', array_fill(0, count($fields), '?')) . ',?';
        $db->prepare("INSERT INTO lc_clients ($cols) VALUES ($ph)")->execute([...$vals, $tid]);
        return (int)$db->lastInsertId();
    }
}

// ===== 案件 =====

function get_cases(array $f = [], int $page = 1, int $per = 25): array {
    $db = get_db();
    $tid = _tid();
    $where = ['cs.tenant_id=?']; $params = [$tid];

    if (!empty($f['q'])) {
        $q = '%' . $f['q'] . '%';
        $where[] = '(cs.case_name LIKE ? OR cs.case_number LIKE ? OR cl.name LIKE ?)';
        array_push($params, $q, $q, $q);
    }
    if (!empty($f['status']))    { $where[] = 'cs.status=?';      $params[] = $f['status']; }
    if (!empty($f['case_type'])) { $where[] = 'cs.case_type=?';   $params[] = $f['case_type']; }
    if (!empty($f['lawyer_id'])) { $where[] = 'cs.assigned_lawyer_id=?'; $params[] = $f['lawyer_id']; }
    if (isset($f['client_id']) && $f['client_id'] > 0) { $where[] = 'cs.client_id=?'; $params[] = $f['client_id']; }

    $w = implode(' AND ', $where);
    $cnt = $db->prepare("SELECT COUNT(*) FROM lc_cases cs JOIN lc_clients cl ON cl.id=cs.client_id WHERE $w");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $offset = ($page - 1) * $per;
    $stmt = $db->prepare(
        "SELECT cs.*, cl.name AS client_name, u.name AS lawyer_name,
            (SELECT COUNT(*) FROM lc_deadlines WHERE case_id=cs.id AND is_done=0 AND deadline_date >= NOW()) AS open_deadlines
         FROM lc_cases cs
         JOIN lc_clients cl ON cl.id=cs.client_id
         LEFT JOIN lc_users u ON u.id=cs.assigned_lawyer_id
         WHERE $w ORDER BY cs.status='active' DESC, cs.opened_date DESC LIMIT $per OFFSET $offset"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => (int)ceil($total / $per)];
}

function get_case(int $id): ?array {
    $stmt = get_db()->prepare(
        "SELECT cs.*, cl.name AS client_name, u.name AS lawyer_name
         FROM lc_cases cs
         JOIN lc_clients cl ON cl.id=cs.client_id
         LEFT JOIN lc_users u ON u.id=cs.assigned_lawyer_id
         WHERE cs.id=? AND cs.tenant_id=?"
    );
    $stmt->execute([$id, _tid()]);
    return $stmt->fetch() ?: null;
}

function save_case(array $d, ?int $id = null): int {
    $db = get_db();
    $tid = _tid();
    $fields = ['client_id','case_number','case_name','case_type','status','assigned_lawyer_id','opened_date','closed_date','court_name','opponent','memo'];
    $vals = [];
    foreach ($fields as $k) {
        $v = $d[$k] ?? '';
        $vals[] = ($v === '') ? null : $v;
    }
    // client_id がテナントに属するか確認
    $cv = $db->prepare("SELECT id FROM lc_clients WHERE id=? AND tenant_id=?");
    $cv->execute([$d['client_id'] ?? 0, $tid]);
    if (!$cv->fetchColumn()) { http_response_code(403); exit('依頼者が見つかりません'); }

    if ($id) {
        $own = $db->prepare("SELECT id FROM lc_cases WHERE id=? AND tenant_id=?");
        $own->execute([$id, $tid]);
        if (!$own->fetchColumn()) { http_response_code(403); exit('権限がありません'); }
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE lc_cases SET $set WHERE id=? AND tenant_id=?")->execute([...$vals, $id, $tid]);
        return $id;
    } else {
        $cols = implode(',', $fields) . ',tenant_id';
        $ph   = implode(',', array_fill(0, count($fields), '?')) . ',?';
        $db->prepare("INSERT INTO lc_cases ($cols) VALUES ($ph)")->execute([...$vals, $tid]);
        return (int)$db->lastInsertId();
    }
}

// ===== 期日 =====

function get_deadlines(array $f = []): array {
    $db = get_db();
    $tid = _tid();
    $where = ['d.tenant_id=?']; $params = [$tid];

    if (isset($f['case_id']) && $f['case_id'] > 0) { $where[] = 'd.case_id=?'; $params[] = $f['case_id']; }
    if (isset($f['is_done'])) { $where[] = 'd.is_done=?'; $params[] = (int)$f['is_done']; }
    if (!empty($f['from'])) { $where[] = 'd.deadline_date >= ?'; $params[] = $f['from']; }
    if (!empty($f['to']))   { $where[] = 'd.deadline_date <= ?'; $params[] = $f['to']; }

    $w = implode(' AND ', $where);
    $stmt = $db->prepare(
        "SELECT d.*, cs.case_name, cs.case_number, cl.name AS client_name
         FROM lc_deadlines d
         JOIN lc_cases cs ON cs.id=d.case_id
         JOIN lc_clients cl ON cl.id=cs.client_id
         WHERE $w ORDER BY d.deadline_date ASC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_deadline(int $id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM lc_deadlines WHERE id=? AND tenant_id=?");
    $stmt->execute([$id, _tid()]);
    return $stmt->fetch() ?: null;
}

function save_deadline(array $d, ?int $id = null): int {
    $db = get_db();
    $tid = _tid();
    $fields = ['case_id','title','deadline_date','deadline_type','is_done','memo'];
    $vals = array_map(fn($k) => $d[$k] ?? '', $fields);
    $vals[4] = (int)($d['is_done'] ?? 0);

    // case_idがテナントに属するか確認
    $cv = $db->prepare("SELECT id FROM lc_cases WHERE id=? AND tenant_id=?");
    $cv->execute([$d['case_id'] ?? 0, $tid]);
    if (!$cv->fetchColumn()) { http_response_code(403); exit('案件が見つかりません'); }

    if ($id) {
        $own = $db->prepare("SELECT id FROM lc_deadlines WHERE id=? AND tenant_id=?");
        $own->execute([$id, $tid]);
        if (!$own->fetchColumn()) { http_response_code(403); exit('権限がありません'); }
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE lc_deadlines SET $set WHERE id=? AND tenant_id=?")->execute([...$vals, $id, $tid]);
        return $id;
    } else {
        $cols = implode(',', $fields) . ',tenant_id';
        $ph   = implode(',', array_fill(0, count($fields), '?')) . ',?';
        $db->prepare("INSERT INTO lc_deadlines ($cols) VALUES ($ph)")->execute([...$vals, $tid]);
        return (int)$db->lastInsertId();
    }
}

// ===== 活動記録 =====

function get_activities(array $f = [], int $limit = 50): array {
    $db = get_db();
    $tid = _tid();
    $where = ['a.tenant_id=?']; $params = [$tid];

    if (isset($f['case_id'])   && $f['case_id'] > 0)   { $where[] = 'a.case_id=?';   $params[] = $f['case_id']; }
    if (isset($f['client_id']) && $f['client_id'] > 0) { $where[] = 'a.client_id=?'; $params[] = $f['client_id']; }

    $w = implode(' AND ', $where);
    $stmt = $db->prepare(
        "SELECT a.*, u.name AS user_name, cs.case_name, cl.name AS client_name
         FROM lc_activities a
         LEFT JOIN lc_users u ON u.id=a.user_id
         LEFT JOIN lc_cases cs ON cs.id=a.case_id
         JOIN lc_clients cl ON cl.id=a.client_id
         WHERE $w ORDER BY a.activity_at DESC LIMIT $limit"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function save_activity(array $d, ?int $id = null): int {
    $db = get_db();
    $tid = _tid();
    $fields = ['case_id','client_id','user_id','activity_type','activity_at','duration_min','title','content','result'];
    $vals = [];
    foreach ($fields as $k) {
        $v = $d[$k] ?? '';
        $vals[] = ($v === '') ? null : $v;
    }
    // client_idがテナントに属するか確認
    $cv = $db->prepare("SELECT id FROM lc_clients WHERE id=? AND tenant_id=?");
    $cv->execute([$d['client_id'] ?? 0, $tid]);
    if (!$cv->fetchColumn()) { http_response_code(403); exit('依頼者が見つかりません'); }

    if ($id) {
        $own = $db->prepare("SELECT id FROM lc_activities WHERE id=? AND tenant_id=?");
        $own->execute([$id, $tid]);
        if (!$own->fetchColumn()) { http_response_code(403); exit('権限がありません'); }
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE lc_activities SET $set WHERE id=? AND tenant_id=?")->execute([...$vals, $id, $tid]);
        return $id;
    } else {
        $cols = implode(',', $fields) . ',tenant_id';
        $ph   = implode(',', array_fill(0, count($fields), '?')) . ',?';
        $db->prepare("INSERT INTO lc_activities ($cols) VALUES ($ph)")->execute([...$vals, $tid]);
        return (int)$db->lastInsertId();
    }
}

// ===== 請求 =====

function get_billings(int $case_id): array {
    $stmt = get_db()->prepare("SELECT b.* FROM lc_billing b
                               JOIN lc_cases cs ON cs.id=b.case_id
                               WHERE b.case_id=? AND cs.tenant_id=?
                               ORDER BY b.billed_date DESC, b.id DESC");
    $stmt->execute([$case_id, _tid()]);
    return $stmt->fetchAll();
}

function get_billing_summary(int $case_id): array {
    $stmt = get_db()->prepare(
        "SELECT SUM(b.amount) AS total, SUM(CASE WHEN b.is_paid=1 THEN b.amount ELSE 0 END) AS paid
         FROM lc_billing b JOIN lc_cases cs ON cs.id=b.case_id
         WHERE b.case_id=? AND cs.tenant_id=?"
    );
    $stmt->execute([$case_id, _tid()]);
    return $stmt->fetch() ?: ['total' => 0, 'paid' => 0];
}

function save_billing(array $d, ?int $id = null): int {
    $db = get_db();
    $tid = _tid();
    $fields = ['case_id','billing_type','title','amount','hours','billed_date','paid_date','is_paid','memo'];
    $vals = [];
    foreach ($fields as $k) {
        $v = $d[$k] ?? '';
        $vals[] = ($v === '') ? null : $v;
    }
    $vals[7] = (int)($d['is_paid'] ?? 0);

    $cv = $db->prepare("SELECT id FROM lc_cases WHERE id=? AND tenant_id=?");
    $cv->execute([$d['case_id'] ?? 0, $tid]);
    if (!$cv->fetchColumn()) { http_response_code(403); exit('案件が見つかりません'); }

    if ($id) {
        $own = $db->prepare("SELECT id FROM lc_billing WHERE id=? AND tenant_id=?");
        $own->execute([$id, $tid]);
        if (!$own->fetchColumn()) { http_response_code(403); exit('権限がありません'); }
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE lc_billing SET $set WHERE id=? AND tenant_id=?")->execute([...$vals, $id, $tid]);
        return $id;
    } else {
        $cols = implode(',', $fields) . ',tenant_id';
        $ph   = implode(',', array_fill(0, count($fields), '?')) . ',?';
        $db->prepare("INSERT INTO lc_billing ($cols) VALUES ($ph)")->execute([...$vals, $tid]);
        return (int)$db->lastInsertId();
    }
}

// ===== 書類 =====

function get_documents(int $case_id): array {
    $stmt = get_db()->prepare(
        "SELECT d.*, u.name AS uploader_name
         FROM lc_documents d
         JOIN lc_cases cs ON cs.id=d.case_id
         LEFT JOIN lc_users u ON u.id=d.uploaded_by
         WHERE d.case_id=? AND cs.tenant_id=? ORDER BY d.created_at DESC"
    );
    $stmt->execute([$case_id, _tid()]);
    return $stmt->fetchAll();
}

function upload_document(int $case_id, array $file, int $user_id, string $memo = ''): string {
    $tid = _tid();
    if ($file['error'] !== UPLOAD_ERR_OK) return 'アップロードエラーが発生しました。';
    if ($file['size'] > 20 * 1024 * 1024) return 'ファイルサイズは20MB以内にしてください。';

    // case_idのテナント所有確認
    $cv = get_db()->prepare("SELECT id FROM lc_cases WHERE id=? AND tenant_id=?");
    $cv->execute([$case_id, $tid]);
    if (!$cv->fetchColumn()) return '案件が見つかりません。';

    $allowed = ['application/pdf','image/jpeg','image/png','image/gif',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain'];
    if (!in_array($file['type'], $allowed)) return '対応していないファイル形式です。';

    // ストレージ容量チェック（テナント単位）
    $tenant = lc_current_tenant();
    if ($tenant) {
        $used_mb = (int)($tenant['storage_used_mb'] ?? 0);
        $quota_mb = (int)($tenant['storage_quota_mb'] ?? 5000);
        $new_mb = (int)ceil($file['size'] / 1024 / 1024);
        if ($used_mb + $new_mb > $quota_mb) {
            return 'ストレージ容量を超過します。プランをアップグレードするか不要なファイルを削除してください。';
        }
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $stored = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = LC_UPLOAD_DIR . $stored;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return 'ファイルの保存に失敗しました。';

    get_db()->prepare(
        "INSERT INTO lc_documents (tenant_id, case_id, original_name, stored_name, file_size, mime_type, uploaded_by, memo)
         VALUES (?,?,?,?,?,?,?,?)"
    )->execute([$tid, $case_id, $file['name'], $stored, $file['size'], $file['type'], $user_id, $memo]);

    // ストレージ使用量更新
    get_db()->prepare("UPDATE lc_tenants SET storage_used_mb = storage_used_mb + ? WHERE id=?")
        ->execute([(int)ceil($file['size'] / 1024 / 1024), $tid]);

    return '';
}

// ===== ダッシュボード集計 =====

function get_dashboard_stats(): array {
    $db = get_db();
    $tid = _tid();
    $stmt = function(string $sql) use ($db, $tid) {
        $s = $db->prepare($sql);
        $s->execute([$tid]);
        return (int)$s->fetchColumn();
    };
    return [
        'active_clients' => $stmt("SELECT COUNT(*) FROM lc_clients WHERE tenant_id=? AND status='active'"),
        'active_cases'   => $stmt("SELECT COUNT(*) FROM lc_cases WHERE tenant_id=? AND status='active'"),
        'upcoming_deadlines' => $stmt("SELECT COUNT(*) FROM lc_deadlines WHERE tenant_id=? AND is_done=0
                                       AND deadline_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)"),
        'unpaid_billing' => $stmt("SELECT COALESCE(SUM(amount),0) FROM lc_billing WHERE tenant_id=? AND is_paid=0"),
    ];
}

function get_upcoming_deadlines(int $days = 14): array {
    $stmt = get_db()->prepare(
        "SELECT d.*, cs.case_name, cs.case_number, cl.name AS client_name
         FROM lc_deadlines d
         JOIN lc_cases cs ON cs.id=d.case_id
         JOIN lc_clients cl ON cl.id=cs.client_id
         WHERE d.tenant_id=? AND d.is_done=0
           AND d.deadline_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
         ORDER BY d.deadline_date ASC LIMIT 20"
    );
    $stmt->execute([_tid(), $days]);
    return $stmt->fetchAll();
}
