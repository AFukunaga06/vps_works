<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$lc_user = lc_require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = get_db()->prepare(
    "SELECT d.* FROM lc_documents d
     JOIN lc_cases cs ON cs.id = d.case_id
     WHERE d.id = ? AND cs.tenant_id = ?"
);
$stmt->execute([$id, lc_current_tenant_id()]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    exit('ファイルが見つかりません。');
}

$path = LC_UPLOAD_DIR . $doc['stored_name'];
if (!file_exists($path)) {
    http_response_code(404);
    exit('ファイルが見つかりません。');
}

header('Content-Type: ' . $doc['mime_type']);
header('Content-Disposition: attachment; filename="' . rawurlencode($doc['original_name']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
