<?php
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    refresh_domain($pdo, $id);
    header('Location: index.php?msg=' . urlencode('再チェックしました'));
    exit;
}

if (!empty($_POST['all'])) {
    $rows = $pdo->query('SELECT id FROM ssl_domains')->fetchAll();
    foreach ($rows as $r) {
        refresh_domain($pdo, (int)$r['id']);
    }
    header('Location: index.php?msg=' . urlencode('全件再チェックしました（' . count($rows) . '件）'));
    exit;
}

header('Location: index.php');
