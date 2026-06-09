<?php
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$domain = trim($_POST['domain'] ?? '');
$port   = (int)($_POST['port'] ?? 443);
$note   = trim($_POST['note'] ?? '');

$domain = preg_replace('#^https?://#i', '', $domain);
$domain = rtrim($domain, '/');
$domain = strtolower($domain);

if ($domain === '' || !preg_match('/^[a-z0-9.\-]+$/', $domain)) {
    header('Location: index.php?msg=' . urlencode('ドメインの形式が不正です'));
    exit;
}
if ($port < 1 || $port > 65535) $port = 443;

try {
    $stmt = $pdo->prepare(
        'INSERT INTO ssl_domains (domain, port, note) VALUES (?, ?, ?)'
    );
    $stmt->execute([$domain, $port, $note !== '' ? $note : null]);
    $id = (int)$pdo->lastInsertId();
    refresh_domain($pdo, $id);
    header('Location: index.php?msg=' . urlencode("追加しました: {$domain}"));
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'UNIQUE')) {
        header('Location: index.php?msg=' . urlencode('そのドメインはすでに登録済みです'));
    } else {
        header('Location: index.php?msg=' . urlencode('エラー: ' . $e->getMessage()));
    }
}
