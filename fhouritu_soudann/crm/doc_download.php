<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/crm_functions.php';
require_admin();

$id   = (int)($_GET['id'] ?? 0);
$stmt = get_db()->prepare("SELECT * FROM crm_documents WHERE id=?");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) { http_response_code(404); exit('ファイルが見つかりません。'); }

$path = CRM_UPLOAD_DIR . $doc['stored_name'];
if (!file_exists($path)) { http_response_code(404); exit('ファイルが見つかりません。'); }

header('Content-Type: ' . $doc['mime_type']);
header('Content-Disposition: attachment; filename="' . rawurlencode($doc['original_name']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
