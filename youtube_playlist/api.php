<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function extract_video_id(string $input): ?string {
    $input = trim($input);
    if ($input === '') return null;
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) return $input;
    $patterns = [
        '/[?&]v=([A-Za-z0-9_-]{11})/',
        '/youtu\.be\/([A-Za-z0-9_-]{11})/',
        '/youtube\.com\/embed\/([A-Za-z0-9_-]{11})/',
        '/youtube\.com\/shorts\/([A-Za-z0-9_-]{11})/',
    ];
    foreach ($patterns as $re) {
        if (preg_match($re, $input, $m)) return $m[1];
    }
    return null;
}

$action = $_GET['action'] ?? '';
$pdo = db();

try {
    switch ($action) {
        case 'list': {
            $stmt = $pdo->query('SELECT id, video_id, title, is_checked, sort_order FROM videos ORDER BY sort_order ASC, id ASC');
            out($stmt->fetchAll());
        }

        case 'add': {
            $in = json_input();
            $lines = preg_split('/\r?\n/', (string)($in['text'] ?? ''));
            $added = 0; $skipped = 0;
            $maxStmt = $pdo->query('SELECT COALESCE(MAX(sort_order), 0) AS m FROM videos');
            $order = (int)$maxStmt->fetch()['m'];
            $ins = $pdo->prepare('INSERT IGNORE INTO videos (video_id, title, is_checked, sort_order) VALUES (?, "", 1, ?)');
            foreach ($lines as $line) {
                $id = extract_video_id($line);
                if (!$id) { if (trim($line) !== '') $skipped++; continue; }
                $order++;
                $ins->execute([$id, $order]);
                if ($ins->rowCount() > 0) $added++;
                else $skipped++;
            }
            out(['added' => $added, 'skipped' => $skipped]);
        }

        case 'update': {
            $in = json_input();
            $id = (int)($in['id'] ?? 0);
            if ($id <= 0) out(['error' => 'invalid id'], 400);
            $fields = []; $params = [];
            if (array_key_exists('title', $in)) {
                $fields[] = 'title = ?';
                $params[] = mb_substr((string)$in['title'], 0, 255);
            }
            if (array_key_exists('is_checked', $in)) {
                $fields[] = 'is_checked = ?';
                $params[] = $in['is_checked'] ? 1 : 0;
            }
            if (!$fields) out(['ok' => true]);
            $params[] = $id;
            $sql = 'UPDATE videos SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $pdo->prepare($sql)->execute($params);
            out(['ok' => true]);
        }

        case 'delete': {
            $in = json_input();
            $id = (int)($in['id'] ?? 0);
            if ($id <= 0) out(['error' => 'invalid id'], 400);
            $pdo->prepare('DELETE FROM videos WHERE id = ?')->execute([$id]);
            out(['ok' => true]);
        }

        case 'clear': {
            $pdo->exec('DELETE FROM videos');
            out(['ok' => true]);
        }

        case 'select_all': {
            $in = json_input();
            $state = !empty($in['state']) ? 1 : 0;
            $pdo->prepare('UPDATE videos SET is_checked = ?')->execute([$state]);
            out(['ok' => true]);
        }

        case 'reorder': {
            $in = json_input();
            $ids = $in['ids'] ?? [];
            if (!is_array($ids)) out(['error' => 'invalid ids'], 400);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE videos SET sort_order = ? WHERE id = ?');
            $order = 0;
            foreach ($ids as $id) {
                $order++;
                $stmt->execute([$order, (int)$id]);
            }
            $pdo->commit();
            out(['ok' => true]);
        }

        default:
            out(['error' => 'unknown action'], 400);
    }
} catch (Throwable $e) {
    out(['error' => $e->getMessage()], 500);
}
