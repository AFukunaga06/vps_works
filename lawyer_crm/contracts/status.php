<?php
/**
 * 契約書解析の進捗 JSON エンドポイント
 *
 *  GET ?id=X  →  { status, done, total, high, medium, low }
 *  view.php の進捗インジケーターがポーリングで取得する。
 *  テナントスコープでアクセス制御（他テナントの契約は 404）。
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$lc_user = lc_require_login();
$tid     = _tid();
$id      = (int)($_GET['id'] ?? 0);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$st = get_db()->prepare(
    "SELECT status, progress_done, progress_total,
            total_risk_high, total_risk_medium, total_risk_low
       FROM lc_contracts
      WHERE id = ? AND tenant_id = ?"
);
$st->execute([$id, $tid]);
$row = $st->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}

echo json_encode([
    'status' => $row['status'],
    'done'   => (int)$row['progress_done'],
    'total'  => (int)$row['progress_total'],
    'high'   => (int)$row['total_risk_high'],
    'medium' => (int)$row['total_risk_medium'],
    'low'    => (int)$row['total_risk_low'],
], JSON_UNESCAPED_UNICODE);
