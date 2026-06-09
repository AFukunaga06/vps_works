<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/config.php';

define('CRM_URL',        BASE_URL . '/crm');
define('CRM_UPLOAD_DIR', __DIR__ . '/../uploads/');

define('CASE_TYPE_MAP', [
    'divorce'     => '離婚',
    'inheritance' => '相続',
    'criminal'    => '刑事',
    'civil'       => '民事',
    'labor'       => '労働',
    'real_estate' => '不動産',
    'corporate'   => '企業法務',
    'debt'        => '債務整理',
    'other'       => 'その他',
]);
define('CASE_STATUS_MAP', [
    'active'    => '進行中',
    'pending'   => '準備中',
    'suspended' => '中断',
    'closed'    => '終結',
]);
define('CLIENT_STATUS_MAP', [
    'active'  => '対応中',
    'pending' => '保留',
    'closed'  => '終結',
]);
define('DEADLINE_TYPE_MAP', [
    'court'    => '裁判期日',
    'document' => '書類提出',
    'meeting'  => '打合せ',
    'payment'  => '支払期限',
    'other'    => 'その他',
]);
define('ACTIVITY_TYPE_MAP', [
    'phone'    => '電話',
    'meeting'  => '面談',
    'email'    => 'メール',
    'document' => '書類作成',
    'court'    => '出廷',
    'other'    => 'その他',
]);
define('BILLING_TYPE_MAP', [
    'retainer' => '着手金',
    'success'  => '報酬金',
    'hourly'   => '時間報酬',
    'expense'  => '実費',
    'other'    => 'その他',
]);

// ===== ユーティリティ =====

function crm_h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function crm_fmt_money(int $v): string {
    return '¥' . number_format($v);
}
function crm_fmt_date(?string $d): string {
    return $d ? date('Y/m/d', strtotime($d)) : '';
}
function crm_fmt_dt(?string $d): string {
    return $d ? date('Y/m/d H:i', strtotime($d)) : '';
}
function crm_case_badge(string $s): string {
    $cls = match($s) { 'active' => 'success', 'pending' => 'warning', 'suspended' => 'secondary', 'closed' => 'dark', default => 'light' };
    $m = CASE_STATUS_MAP;
    return '<span class="badge bg-'.$cls.'">' . crm_h($m[$s] ?? $s) . '</span>';
}
function crm_client_badge(string $s): string {
    $cls = match($s) { 'active' => 'success', 'pending' => 'warning', 'closed' => 'secondary', default => 'light' };
    $m = CLIENT_STATUS_MAP;
    return '<span class="badge bg-'.$cls.'">' . crm_h($m[$s] ?? $s) . '</span>';
}

// ===== 依頼者 =====

function crm_get_clients(array $f = [], int $page = 1, int $per = 25): array {
    $db = get_db();
    $where = ['1=1']; $params = [];
    if (!empty($f['q'])) {
        $q = '%'.$f['q'].'%';
        $where[] = '(name LIKE ? OR kana LIKE ? OR email LIKE ? OR tel LIKE ?)';
        array_push($params, $q, $q, $q, $q);
    }
    if (!empty($f['status'])) { $where[] = 'status=?'; $params[] = $f['status']; }
    $w = implode(' AND ', $where);
    $total = (int)$db->prepare("SELECT COUNT(*) FROM crm_clients WHERE $w")->execute($params) ? 0 : 0;
    $cnt = $db->prepare("SELECT COUNT(*) FROM crm_clients WHERE $w"); $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();
    $offset = ($page - 1) * $per;
    $stmt = $db->prepare(
        "SELECT c.*, (SELECT COUNT(*) FROM crm_cases WHERE client_id=c.id) AS case_count
         FROM crm_clients c WHERE $w ORDER BY c.kana, c.id LIMIT $per OFFSET $offset"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => (int)ceil($total / $per)];
}

function crm_get_client(int $id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM crm_clients WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function crm_save_client(array $d, ?int $id = null): int {
    $db = get_db();
    $fields = ['name','kana','email','tel','tel2','address','birth_date','gender','occupation','status','memo'];
    $nullable = ["birth_date", "memo"];
    $vals = array_map(fn($k) => (($d[$k] ?? "") === "") ? (in_array($k, $nullable) ? null : "") : $d[$k], $fields);
    if ($id) {
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE crm_clients SET $set WHERE id=?")->execute([...$vals, $id]);
        return $id;
    } else {
        $cols = implode(',', $fields);
        $ph   = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO crm_clients ($cols) VALUES ($ph)")->execute($vals);
        return (int)$db->lastInsertId();
    }
}

// ===== 案件 =====

function crm_get_cases(array $f = [], int $page = 1, int $per = 25): array {
    $db = get_db();
    $where = ['1=1']; $params = [];
    if (!empty($f['q'])) {
        $q = '%'.$f['q'].'%';
        $where[] = '(cs.case_name LIKE ? OR cs.case_number LIKE ? OR cl.name LIKE ?)';
        array_push($params, $q, $q, $q);
    }
    if (!empty($f['status']))    { $where[] = 'cs.status=?';    $params[] = $f['status']; }
    if (!empty($f['case_type'])) { $where[] = 'cs.case_type=?'; $params[] = $f['case_type']; }
    if (!empty($f['client_id'])) { $where[] = 'cs.client_id=?'; $params[] = $f['client_id']; }
    $w = implode(' AND ', $where);
    $cnt = $db->prepare("SELECT COUNT(*) FROM crm_cases cs JOIN crm_clients cl ON cl.id=cs.client_id WHERE $w");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();
    $offset = ($page - 1) * $per;
    $stmt = $db->prepare(
        "SELECT cs.*, cl.name AS client_name,
            (SELECT COUNT(*) FROM crm_deadlines WHERE case_id=cs.id AND is_done=0 AND deadline_date >= NOW()) AS open_deadlines
         FROM crm_cases cs JOIN crm_clients cl ON cl.id=cs.client_id
         WHERE $w ORDER BY (cs.status='active') DESC, cs.opened_date DESC LIMIT $per OFFSET $offset"
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => (int)ceil($total / $per)];
}

function crm_get_case(int $id): ?array {
    $stmt = get_db()->prepare(
        "SELECT cs.*, cl.name AS client_name
         FROM crm_cases cs JOIN crm_clients cl ON cl.id=cs.client_id WHERE cs.id=?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function crm_generate_case_number(): string {
    $db   = get_db();
    $year = date('Y');
    $row  = $db->query(
        "SELECT case_number FROM crm_cases WHERE case_number REGEXP '^{$year}-[0-9]+$' ORDER BY CAST(SUBSTRING_INDEX(case_number,'-',-1) AS UNSIGNED) DESC LIMIT 1"
    )->fetch();
    if ($row) {
        $seq = (int)explode('-', $row['case_number'])[1] + 1;
    } else {
        $seq = 1;
    }
    return $year . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

function crm_save_case(array $d, ?int $id = null): int {
    $db = get_db();
    $fields = ['client_id','case_number','case_name','case_type','status','opened_date','closed_date','court_name','opponent','memo'];
    $nullable = ["opened_date", "closed_date", "memo"];
    $vals = array_map(fn($k) => (($d[$k] ?? "") === "") ? (in_array($k, $nullable) ? null : "") : $d[$k], $fields);
    if ($id) {
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE crm_cases SET $set WHERE id=?")->execute([...$vals, $id]);
        return $id;
    } else {
        $cols = implode(',', $fields);
        $ph   = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO crm_cases ($cols) VALUES ($ph)")->execute($vals);
        return (int)$db->lastInsertId();
    }
}

// ===== 期日 =====

function crm_get_deadlines(array $f = []): array {
    $db = get_db(); $where = ['1=1']; $params = [];
    if (!empty($f['case_id'])) { $where[] = 'd.case_id=?'; $params[] = $f['case_id']; }
    if (isset($f['is_done']))  { $where[] = 'd.is_done=?';  $params[] = (int)$f['is_done']; }
    if (!empty($f['from']))    { $where[] = 'd.deadline_date >= ?'; $params[] = $f['from']; }
    if (!empty($f['to']))      { $where[] = 'd.deadline_date <= ?'; $params[] = $f['to']; }
    $w = implode(' AND ', $where);
    $stmt = $db->prepare(
        "SELECT d.*, cs.case_name, cl.name AS client_name
         FROM crm_deadlines d JOIN crm_cases cs ON cs.id=d.case_id
         JOIN crm_clients cl ON cl.id=cs.client_id
         WHERE $w ORDER BY d.deadline_date ASC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function crm_get_deadline(int $id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM crm_deadlines WHERE id=?");
    $stmt->execute([$id]); return $stmt->fetch() ?: null;
}

function crm_save_deadline(array $d, ?int $id = null): int {
    $db = get_db();
    $fields  = ['case_id','title','deadline_date','deadline_type','is_done','memo'];
    $vals    = array_map(fn($k) => $d[$k] ?? '', $fields);
    $vals[4] = (int)($d['is_done'] ?? 0);
    $is_done = $vals[4];

    if ($id) {
        $existing      = crm_get_deadline($id);
        $gcal_event_id = $existing['gcal_event_id'] ?? null;

        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE crm_deadlines SET $set WHERE id=?")->execute([...$vals, $id]);

        if (_gcal_enabled()) {
            if ($is_done && $gcal_event_id) {
                gcal_delete_event($gcal_event_id);
                $db->prepare("UPDATE crm_deadlines SET gcal_event_id=NULL WHERE id=?")->execute([$id]);
            } elseif (!$is_done) {
                $info = _crm_case_info((int)$d['case_id']);
                if ($info) {
                    $dl = array_combine($fields, $vals);
                    if ($gcal_event_id) {
                        gcal_update_event($gcal_event_id, $dl, $info['case_name'], $info['client_name']);
                    } else {
                        $eid = gcal_create_event($dl, $info['case_name'], $info['client_name']);
                        if ($eid) $db->prepare("UPDATE crm_deadlines SET gcal_event_id=? WHERE id=?")->execute([$eid, $id]);
                    }
                }
            }
        }
        return $id;
    } else {
        $cols = implode(',', $fields);
        $ph   = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO crm_deadlines ($cols) VALUES ($ph)")->execute($vals);
        $new_id = (int)$db->lastInsertId();

        if (!$is_done && _gcal_enabled()) {
            $info = _crm_case_info((int)$d['case_id']);
            if ($info) {
                $dl  = array_combine($fields, $vals);
                $eid = gcal_create_event($dl, $info['case_name'], $info['client_name']);
                if ($eid) $db->prepare("UPDATE crm_deadlines SET gcal_event_id=? WHERE id=?")->execute([$eid, $new_id]);
            }
        }
        return $new_id;
    }
}

function _gcal_enabled(): bool {
    return function_exists('gcal_is_configured') && gcal_is_configured();
}

function _crm_case_info(int $case_id): ?array {
    $stmt = get_db()->prepare(
        "SELECT cs.case_name, cl.name AS client_name
         FROM crm_cases cs JOIN crm_clients cl ON cl.id=cs.client_id WHERE cs.id=?"
    );
    $stmt->execute([$case_id]);
    return $stmt->fetch() ?: null;
}

// ===== 活動記録 =====

function crm_get_activities(array $f = [], int $limit = 50): array {
    $db = get_db(); $where = ['1=1']; $params = [];
    if (!empty($f['case_id']))   { $where[] = 'a.case_id=?';   $params[] = $f['case_id']; }
    if (!empty($f['client_id'])) { $where[] = 'a.client_id=?'; $params[] = $f['client_id']; }
    $w = implode(' AND ', $where);
    $stmt = $db->prepare(
        "SELECT a.*, cs.case_name, cl.name AS client_name
         FROM crm_activities a
         LEFT JOIN crm_cases cs ON cs.id=a.case_id
         JOIN crm_clients cl ON cl.id=a.client_id
         WHERE $w ORDER BY a.activity_at DESC LIMIT $limit"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function crm_save_activity(array $d, ?int $id = null): int {
    $db = get_db();
    $fields = ['case_id','client_id','activity_type','activity_at','duration_min','title','content','result'];
    $vals = array_map(fn($k) => (($d[$k] ?? '') === '') ? null : $d[$k], $fields);
    if ($id) {
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE crm_activities SET $set WHERE id=?")->execute([...$vals, $id]);
        return $id;
    } else {
        $cols = implode(',', $fields);
        $ph   = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO crm_activities ($cols) VALUES ($ph)")->execute($vals);
        return (int)$db->lastInsertId();
    }
}

// ===== 請求 =====

function crm_get_billings(int $case_id): array {
    $stmt = get_db()->prepare("SELECT * FROM crm_billing WHERE case_id=? ORDER BY billed_date DESC, id DESC");
    $stmt->execute([$case_id]); return $stmt->fetchAll();
}

function crm_get_billing_summary(int $case_id): array {
    $stmt = get_db()->prepare(
        "SELECT COALESCE(SUM(amount),0) AS total, COALESCE(SUM(CASE WHEN is_paid=1 THEN amount ELSE 0 END),0) AS paid
         FROM crm_billing WHERE case_id=?"
    );
    $stmt->execute([$case_id]); return $stmt->fetch();
}

function crm_save_billing(array $d, ?int $id = null): int {
    $db = get_db();
    $fields = ['case_id','billing_type','title','amount','hours','billed_date','paid_date','is_paid','memo'];
    $vals = array_map(fn($k) => (($d[$k] ?? '') === '') ? null : $d[$k], $fields);
    $vals[7] = (int)($d['is_paid'] ?? 0);
    if ($id) {
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $db->prepare("UPDATE crm_billing SET $set WHERE id=?")->execute([...$vals, $id]);
        return $id;
    } else {
        $cols = implode(',', $fields);
        $ph   = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO crm_billing ($cols) VALUES ($ph)")->execute($vals);
        return (int)$db->lastInsertId();
    }
}

// ===== 書類 =====

function crm_get_documents(int $case_id): array {
    $stmt = get_db()->prepare("SELECT * FROM crm_documents WHERE case_id=? ORDER BY created_at DESC");
    $stmt->execute([$case_id]); return $stmt->fetchAll();
}

function crm_upload_document(int $case_id, array $file, string $memo = ''): string {
    if ($file['error'] !== UPLOAD_ERR_OK) return 'アップロードエラーが発生しました。';
    if ($file['size'] > 20 * 1024 * 1024) return 'ファイルサイズは20MB以内にしてください。';
    $allowed = ['application/pdf','image/jpeg','image/png','application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','text/plain'];
    if (!in_array($file['type'], $allowed)) return '対応していないファイル形式です（PDF/Word/Excel/画像）。';
    $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $stored = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], CRM_UPLOAD_DIR . $stored)) return 'ファイルの保存に失敗しました。';
    get_db()->prepare(
        "INSERT INTO crm_documents (case_id, original_name, stored_name, file_size, mime_type, memo) VALUES (?,?,?,?,?,?)"
    )->execute([$case_id, $file['name'], $stored, $file['size'], $file['type'], $memo]);
    return '';
}

// ===== ダッシュボード =====

function crm_dashboard_stats(): array {
    $db = get_db();
    return [
        'active_clients'     => (int)$db->query("SELECT COUNT(*) FROM crm_clients WHERE status='active'")->fetchColumn(),
        'active_cases'       => (int)$db->query("SELECT COUNT(*) FROM crm_cases WHERE status='active'")->fetchColumn(),
        'upcoming_deadlines' => (int)$db->query("SELECT COUNT(*) FROM crm_deadlines WHERE is_done=0 AND deadline_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
        'unpaid'             => (int)$db->query("SELECT COALESCE(SUM(amount),0) FROM crm_billing WHERE is_paid=0")->fetchColumn(),
    ];
}

function crm_upcoming_deadlines(int $days = 14): array {
    $stmt = get_db()->prepare(
        "SELECT d.*, cs.case_name, cl.name AS client_name
         FROM crm_deadlines d JOIN crm_cases cs ON cs.id=d.case_id
         JOIN crm_clients cl ON cl.id=cs.client_id
         WHERE d.is_done=0 AND d.deadline_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
         ORDER BY d.deadline_date ASC LIMIT 20"
    );
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}
