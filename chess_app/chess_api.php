<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json');

$input       = json_decode(file_get_contents('php://input'), true) ?: [];
$fen         = trim($input['fen'] ?? '');
$valid_moves = $input['valid_moves'] ?? [];
$difficulty  = $input['difficulty'] ?? 'easy';

if (empty($fen) || empty($valid_moves)) {
    http_response_code(400);
    echo json_encode(['error' => 'FEN または合法手リストが必要です']);
    exit;
}

$model = DIFFICULTY_MODELS[$difficulty]['model'] ?? DIFFICULTY_MODELS['easy']['model'];

// API失敗時のフォールバック: 合法手の中からランダムに選択
function fallback_move(array $valid_moves): string {
    return $valid_moves[array_rand($valid_moves)];
}

$api_key = anthropic_api_key();
if (empty($api_key)) {
    // APIキー未設定 → ランダム合法手で継続
    echo json_encode(['move' => fallback_move($valid_moves), 'fallback' => 'no_api_key']);
    exit;
}

$moves_str = implode(', ', array_map(fn($m) => preg_replace('/[^a-h1-8qrbn]/i', '', $m), $valid_moves));

// 難易度ごとにシステムプロンプトを調整
$sys_msgs = [
    'easy'   => 'You are a casual chess player playing as Black. Pick a reasonable move but no need to play perfectly. Respond with ONLY one move from the list — no explanation.',
    'medium' => 'You are an intermediate chess player playing as Black. Choose a solid move. Respond with ONLY one move from the list — no explanation.',
    'hard'   => 'You are a strong chess engine playing as Black. Choose the best move you can find. Respond with ONLY one move from the list — no explanation, no other text.',
];
$system = $sys_msgs[$difficulty] ?? $sys_msgs['easy'];

$payload = [
    'model'      => $model,
    'max_tokens' => 16,
    'system'     => $system,
    'messages'   => [[
        'role'    => 'user',
        'content' => "Position (FEN): {$fen}\nLegal moves (UCI): {$moves_str}\n\nChoose your move."
    ]],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT => 30,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    // API失敗 → ランダム合法手
    echo json_encode(['move' => fallback_move($valid_moves), 'fallback' => 'api_error_' . $http_code]);
    exit;
}

$result = json_decode($response, true);
$raw    = strtolower(trim($result['content'][0]['text'] ?? ''));

if (!in_array($raw, $valid_moves, true)) {
    if (preg_match('/[a-h][1-8][a-h][1-8][qrbn]?/', $raw, $m) && in_array($m[0], $valid_moves, true)) {
        $raw = $m[0];
    } else {
        $raw = fallback_move($valid_moves);
    }
}

echo json_encode(['move' => $raw, 'model' => $model]);
