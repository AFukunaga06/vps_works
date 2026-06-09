<?php
/**
 * 見積の削除
 *   POST id=X のみ受け付け（テナント所有確認）
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$lc_user = lc_require_login();
$tid     = _tid();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST only');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . LC_BASE_URL . '/quotes/index.php');
    exit;
}

$stmt = get_db()->prepare("DELETE FROM lc_quotes WHERE id=? AND tenant_id=?");
$stmt->execute([$id, $tid]);

header('Location: ' . LC_BASE_URL . '/quotes/index.php?deleted=' . $id);
exit;
