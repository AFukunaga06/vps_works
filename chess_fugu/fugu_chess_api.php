<?php
// fuguAI チェス API
//  action=move : 中級。FEN と合法手(UCI: e2e4 等)を渡し fugu に一手選ばせる
//  action=save : 終局結果を匿名でDBに保存
declare(strict_types=1);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function normalize_level($level): string {
    return in_array($level, ['easy', 'medium', 'hard'], true) ? $level : 'medium';
}

// fugu に合法手の中から一手選ばせる。失敗時は null
function fugu_pick(string $fen, array $moves): ?array {
    $api_key = sakana_api_key();
    if (empty($api_key)) return null;

    // 合法手を正規化（UCI形式のみ許可）
    $clean = [];
    foreach ($moves as $m) {
        $m = strtolower(preg_replace('/[^a-h1-8qrbnA-H]/', '', (string)$m));
        if (preg_match('/^[a-h][1-8][a-h][1-8][qrbn]?$/', $m)) $clean[] = $m;
    }
    $clean = array_values(array_unique($clean));
    if (empty($clean)) return null;

    $moveList = implode(', ', $clean);
    $prompt = <<<PROMPT
あなたはチェスの強いプレイヤーで、黒(Black)を担当します。
現在の局面(FEN): {$fen}

あなたが指せる合法手の一覧(UCI形式、例 e7e5):
{$moveList}

戦略: 駒得・中央支配・キング安全を意識し、ただ取られる手は避ける。
上記の合法手の中から最善と思う一手を選び、UCI形式(例: e7e5)だけを1行で答えてください。説明は不要です。
PROMPT;

    $payload = [
        'model'       => 'fugu',
        'temperature' => 0.3,
        'max_tokens'  => 1024, // fugu は内部推論にトークンを使うため余裕を持たせる
        'messages'    => [['role' => 'user', 'content' => $prompt]],
    ];

    $ch = curl_init('https://api.sakana.ai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$response) return null;
    $result = json_decode($response, true);
    $text = strtolower($result['choices'][0]['message']['content'] ?? '');
    if (preg_match('/[a-h][1-8][a-h][1-8][qrbn]?/', $text, $mm) && in_array($mm[0], $clean, true)) {
        return ['move' => $mm[0], 'source' => 'fugu'];
    }
    return null;
}

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? '';

if ($action === 'move') {
    $fen   = trim((string)($input['fen'] ?? ''));
    $moves = $input['moves'] ?? [];
    if ($fen === '' || !is_array($moves) || empty($moves)) {
        http_response_code(400);
        echo json_encode(['error' => 'FENと合法手が必要です']);
        exit;
    }
    $picked = fugu_pick($fen, $moves);
    if ($picked === null) {
        // フォールバック: ランダムな合法手（中級でもゲームを止めない）
        $rand = $moves[array_rand($moves)];
        echo json_encode(['move' => strtolower((string)$rand), 'source' => 'fallback']);
        exit;
    }
    echo json_encode($picked);
    exit;
}

if ($action === 'save') {
    $name   = trim((string)($input['name'] ?? ''));
    $level  = normalize_level($input['level'] ?? 'medium');
    $result = $input['result'] ?? '';
    $moves  = (int)($input['moves'] ?? 0);
    $reason = trim((string)($input['reason'] ?? ''));
    if (!in_array($result, ['win', 'lose', 'draw'], true)) {
        http_response_code(400);
        echo json_encode(['error' => '不正な結果です']);
        exit;
    }
    if ($name === '') $name = 'ゲスト';
    if (mb_strlen($name) > 32) $name = mb_substr($name, 0, 32);
    if ($moves < 0) $moves = 0;
    if ($moves > 65000) $moves = 65000;
    if (mb_strlen($reason) > 24) $reason = mb_substr($reason, 0, 24);
    try {
        $stmt = db()->prepare(
            'INSERT INTO games (player_name, level, result, moves, end_reason) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $level, $result, $moves, $reason]);
        echo json_encode(['saved' => true, 'result' => $result, 'level' => $level]);
    } catch (Throwable $ex) {
        http_response_code(500);
        echo json_encode(['error' => '保存に失敗しました']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown action']);
