<?php
// api_attendance_get.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$date = $_GET['date'] ?? '';

if ($year <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  echo json_encode(['ok'=>false, 'error'=>'year/dateが不正です'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT gender, name, present
    FROM attendance
    WHERE year = ? AND date = ?
  ");
  $stmt->execute([$year, $date]);
  $rows = $stmt->fetchAll();

  // key: gender|name → present(0/1)
  $map = [];
  foreach ($rows as $r) {
    $k = $r['gender'] . '|' . $r['name'];
    $map[$k] = (int)$r['present'];
  }

  echo json_encode(['ok'=>true, 'year'=>$year, 'date'=>$date, 'map'=>$map], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
