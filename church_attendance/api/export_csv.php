<?php
/**
 * CSV出力API
 * GET: year, month を指定して月集計＋年集計をCSVダウンロード
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$year  = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('n'));

try {
    $pdo = getDB();

    // BOM付きCSV
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="attendance_' . $year . '.csv"');
    
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // BOM

    // ヘッダー行
    fputcsv($output, ['区分', '年', '月', '項目', '男性', '女性', '合計']);

    // 月集計（指定月）
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate   = date('Y-m-t', strtotime($startDate));

    $stmt = $pdo->prepare('
        SELECT r.gender, COUNT(*) as cnt
        FROM attendance a
        JOIN roster r ON a.roster_id = r.id
        WHERE a.attendance_date BETWEEN ? AND ? AND a.is_present = 1
        GROUP BY r.gender
    ');
    $stmt->execute([$startDate, $endDate]);
    $monthData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $maleCount   = (int)($monthData['male'] ?? 0);
    $femaleCount = (int)($monthData['female'] ?? 0);
    fputcsv($output, ['月集計', $year, $month, '合計', $maleCount, $femaleCount, $maleCount + $femaleCount]);

    // 年集計（1〜12月）
    $yearMale = 0;
    $yearFemale = 0;
    
    for ($m = 1; $m <= 12; $m++) {
        $ms = sprintf('%04d-%02d-01', $year, $m);
        $me = date('Y-m-t', strtotime($ms));
        
        $stmt = $pdo->prepare('
            SELECT r.gender, COUNT(*) as cnt
            FROM attendance a
            JOIN roster r ON a.roster_id = r.id
            WHERE a.attendance_date BETWEEN ? AND ? AND a.is_present = 1
            GROUP BY r.gender
        ');
        $stmt->execute([$ms, $me]);
        $mData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $mm = (int)($mData['male'] ?? 0);
        $fm = (int)($mData['female'] ?? 0);
        fputcsv($output, ['年集計', $year, $m, '月合計', $mm, $fm, $mm + $fm]);
        
        $yearMale += $mm;
        $yearFemale += $fm;
    }

    fputcsv($output, ['年集計', $year, '', '年間合計', $yearMale, $yearFemale, $yearMale + $yearFemale]);

    fclose($output);
} catch (Exception $e) {
    error_log('CSV出力エラー: ' . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'CSV出力に失敗しました']);
}
