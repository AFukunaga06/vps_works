<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$db = get_db();

$filter_status = $_GET['status'] ?? '';
$filter_type   = $_GET['type']   ?? '';
$filter_date   = $_GET['date']   ?? '';

$where  = ['1=1'];
$params = [];
if ($filter_status) { $where[] = 'status = ?';            $params[] = $filter_status; }
if ($filter_type)   { $where[] = 'consultation_type = ?'; $params[] = $filter_type; }
if ($filter_date)   { $where[] = 'reserve_date = ?';      $params[] = $filter_date; }

$sql  = 'SELECT * FROM reservations WHERE ' . implode(' AND ', $where)
      . ' ORDER BY reserve_date, start_time';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = '予約一覧_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// BOM（Excelで文字化けしないよう）
fwrite($out, "\xEF\xBB\xBF");

// ヘッダー行
fputcsv($out, [
    '予約番号', '種別', '日付', '開始時間', '終了時間',
    'お名前', 'ふりがな', 'メール', '電話番号',
    '相談回数', '相談方法', 'ステータス', 'ご相談内容', '備考', '申込日時'
]);

foreach ($rows as $r) {
    $end_time = date('H:i', strtotime($r['start_time']) + 2700);
    fputcsv($out, [
        '#' . str_pad($r['id'], 5, '0', STR_PAD_LEFT),
        $r['consultation_type'],
        $r['reserve_date'],
        date('H:i', strtotime($r['start_time'])),
        $end_time,
        $r['name'],
        $r['kana'],
        $r['email'],
        $r['tel'],
        $r['is_first'] ? '初回' : '2回目以降',
        $r['consult_method'],
        status_label($r['status']),
        $r['content'],
        $r['note'] ?? '',
        $r['created_at'],
    ]);
}

fclose($out);
exit;
