<?php
require_once dirname(__DIR__).'/auth.php';
require_login();

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete') {
    require_role(['admin']);
    if ($id) {
        $pdo->prepare("DELETE FROM persons WHERE id=?")->execute([$id]);
        log_activity($pdo,'person_delete','person',$id,'人物を削除');
        flash('削除しました。');
    }
    header('Location: '.BASE_URL.'/persons/');
    exit;
}

header('Location: '.BASE_URL.'/persons/');
exit;
