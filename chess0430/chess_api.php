<?php
header('Content-Type: application/json');

$input       = json_decode(file_get_contents('php://input'), true);
$fen         = trim($input['fen'] ?? '');
$valid_moves = $input['valid_moves'] ?? [];   // chess.jsから渡された合法手リスト

if (empty($fen) || empty($valid_moves)) {
    http_response_code(400);
    echo json_encode(['error' => 'FEN または合法手リストが必要です']);
    exit;
}

$api_key = getenv('ANTHROPIC_API_KEY')
        ?: ($_ENV['ANTHROPIC_API_KEY']    ?? '')
        ?: ($_SERVER['ANTHROPIC_API_KEY'] ?? '');

if (empty($api_key)) {
    http_response_code(500);
    echo json_encode(['error' => 'ANTHROPIC_API_KEY が設定されていません']);
    exit;
}

$moves_str = implode(', ', array_map('htmlspecialchars', $valid_moves));

$payload = [
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 10,
    'system'     => 'You are a chess engine playing as Black. You will be given a position and a list of legal moves in UCI format. Respond with ONLY one move from the list — no explanation, no other text.',
    'messages'   => [
        [
            'role'    => 'user',
            'content' => "Position (FEN): {$fen}\nLegal moves: {$moves_str}\n\nChoose your best move."
        ]
    ]
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: '          . $api_key,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    http_response_code(500);
    echo json_encode(['error' => 'Claude API エラー (HTTP ' . $http_code . ')']);
    exit;
}

$result = json_decode($response, true);
$raw    = strtolower(trim($result['content'][0]['text'] ?? ''));

// 返答が合法手リストに含まれていれば採用、なければ正規表現で抽出して再確認
if (!in_array($raw, $valid_moves)) {
    if (preg_match('/[a-h][1-8][a-h][1-8][qrbn]?/', $raw, $matches)
        && in_array($matches[0], $valid_moves)) {
        $raw = $matches[0];
    } else {
        // フォールバック：合法手リストの先頭を使用
        $raw = $valid_moves[0];
    }
}

echo json_encode(['move' => $raw]);
