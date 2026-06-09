<?php
// api_members.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

try {
  $stmt = $pdo->prepare("
    SELECT id, year, gender, name, sort_order
    FROM members
    WHERE year = ?
    ORDER BY sort_order ASC, name ASC, id ASC
  ");
  $stmt->execute([$year]);
  $rows = $stmt->fetchAll();

  $out = [
    'ok' => true,
    'year' => $year,
    'members' => $rows
  ];
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
