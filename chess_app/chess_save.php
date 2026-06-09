<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json');

start_session();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'login required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$difficulty   = $input['difficulty'] ?? '';
$result       = $input['result'] ?? '';
$end_reason   = $input['end_reason'] ?? '';
$total_moves  = (int)($input['total_moves'] ?? 0);
$duration_sec = (int)($input['duration_sec'] ?? 0);
$pgn          = (string)($input['pgn'] ?? '');
$final_fen    = (string)($input['final_fen'] ?? '');
$started_at   = (string)($input['started_at'] ?? '');

if (!isset(DIFFICULTY_MODELS[$difficulty])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid difficulty']);
    exit;
}
$valid_results = ['win', 'loss', 'draw', 'resign'];
$valid_reasons = ['checkmate', 'stalemate', 'draw', 'threefold', 'insufficient', 'resign', 'timeout'];
if (!in_array($result, $valid_results, true) || !in_array($end_reason, $valid_reasons, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid result/reason']);
    exit;
}

// 開始日時のパース（不正なら現在時刻 - duration）
$dt = DateTime::createFromFormat('Y-m-d H:i:s', $started_at);
if (!$dt) {
    $dt = new DateTime('-' . max($duration_sec, 0) . ' seconds');
}
$started_at_db = $dt->format('Y-m-d H:i:s');

try {
    $stmt = db()->prepare('INSERT INTO games
        (user_id, difficulty, ai_model, player_color, result, end_reason, total_moves, duration_sec, pgn, final_fen, started_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        (int)$_SESSION['user_id'],
        $difficulty,
        DIFFICULTY_MODELS[$difficulty]['model'],
        'white',
        $result,
        $end_reason,
        $total_moves,
        $duration_sec,
        $pgn,
        $final_fen,
        $started_at_db,
    ]);
    echo json_encode(['ok' => true, 'game_id' => (int)db()->lastInsertId()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db error: ' . $e->getMessage()]);
}
