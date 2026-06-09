<?php
require_once __DIR__ . "/auth.php";
require_login();
<?php
/**
 * 出欠データ保存API (POST)
 * チェックボックスの変更を即座にDBへ保存
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$date      = $input['date'] ?? '';       // YYYY-MM-DD
$rosterId  = intval($input['roster_id'] ?? 0);
$isPresent = $input['is_present'] ? 1 : 0;

// バリデーション
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $rosterId <= 0) {
    echo json_encode(['success' => false, 'message' => 'パラメータが不正です']);
    exit;
}

try {
    $pdo = getDB();
    
    // UPSERT（存在すれば更新、なければ挿入）
    $stmt = $pdo->prepare('
        INSERT INTO attendance (attendance_date, roster_id, is_present, updated_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE is_present = VALUES(is_present), updated_by = VALUES(updated_by), updated_at = NOW()
    ');
    $stmt->execute([$date, $rosterId, $isPresent, $_SESSION['user_id']]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('出欠保存エラー: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '保存に失敗しました']);
}
