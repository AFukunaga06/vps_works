<?php
require_once 'config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: admin.php'); exit; }

$db = getDB();
$stmt = $db->prepare("DELETE FROM personal_info WHERE id=?");
$stmt->execute([$id]);

header('Location: admin.php?success=del');
exit;
