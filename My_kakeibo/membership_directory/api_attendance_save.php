<?php
// api_attendance_save.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$year = (int)($data['year'] ?? 0);
$date = (string)($data['date'] ?? '');
$items = $data['items'] ?? null;

if ($year <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !is_array($items)) {
  echo json_encode(['ok'=>false, 'error'=>'入力が不正です'], JSON_UNESCAPED_UNICODE);
  exit;
}

// items: [{gender:"male|female", name:"山田太郎", present:1|0}, ...]
try {
  $pdo->beginTransaction();

  $sql = "
    INSERT INTO attendance (year, date, gender, name, present)
    VALUES (:year, :date, :gender, :name, :present)
    ON DUPLICATE KEY UPDATE
      present = VALUES(present),
      updated_at = CURRENT_TIMESTAMP
  ";
  $stmt = $pdo->prepare($sql);

  $count = 0;
  foreach ($items as $it) {
    $gender = (string)($it['gender'] ?? '');
    $name = (string)($it['name'] ?? '');
    $present = (int)($it['present'] ?? 0);

    if (!in_array($gender, ['male','female'], true)) continue;
    if ($name === '' || mb_strlen($name) > 100) continue;
    $present = $present ? 1 : 0;

    $stmt->execute([
      ':year' => $year,
      ':date' => $date,
      ':gender' => $gender,
      ':name' => $name,
      ':present' => $present,
    ]);
    $count++;
  }

  $pdo->commit();
  echo json_encode(['ok'=>true, 'saved'=>$count], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
