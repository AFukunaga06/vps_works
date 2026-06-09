<?php
/**
 * 出欠データ読み込みAPI (GET)
 * パラメータ: year, month（任意）
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$year  = intval($_GET['year'] ?? date('Y'));
$month = isset($_GET['month']) ? intval($_GET['month']) : null;

try {
    $pdo = getDB();

    if ($month !== null) {
        // 特定月の出欠データ
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));
        
        $stmt = $pdo->prepare('
            SELECT a.attendance_date, a.roster_id, a.is_present, r.gender, r.name, r.is_newcomer
            FROM attendance a
            JOIN roster r ON a.roster_id = r.id
            WHERE a.attendance_date BETWEEN ? AND ?
            ORDER BY a.attendance_date, r.gender, r.sort_order
        ');
        $stmt->execute([$startDate, $endDate]);
    } else {
        // 年間の出欠データ
        $startDate = "{$year}-01-01";
        $endDate   = "{$year}-12-31";
        
        $stmt = $pdo->prepare('
            SELECT a.attendance_date, a.roster_id, a.is_present, r.gender, r.name, r.is_newcomer
            FROM attendance a
            JOIN roster r ON a.roster_id = r.id
            WHERE a.attendance_date BETWEEN ? AND ?
            ORDER BY a.attendance_date, r.gender, r.sort_order
        ');
        $stmt->execute([$startDate, $endDate]);
    }

    $data = $stmt->fetchAll();

    // 日付ごとにグループ化
    $grouped = [];
    foreach ($data as $row) {
        $date = $row['attendance_date'];
        if (!isset($grouped[$date])) {
            $grouped[$date] = [];
        }
        $grouped[$date][] = [
            'roster_id'  => (int)$row['roster_id'],
            'is_present' => (bool)$row['is_present'],
            'gender'     => $row['gender'],
            'name'       => $row['name'],
            'is_newcomer'=> (bool)$row['is_newcomer'],
        ];
    }

    echo json_encode(['success' => true, 'year' => $year, 'month' => $month, 'attendance' => $grouped]);
} catch (Exception $e) {
    error_log('出欠読み込みエラー: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '読み込みに失敗しました']);
}
