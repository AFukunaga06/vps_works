<?php
// fuguAI にオセロの一手を選ばせるエンドポイント
// 人間の着手を適用 → fugu の手番を進めて、結果の盤面を返す
declare(strict_types=1);
require __DIR__ . '/othello.php';
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$SYMBOL = [OTH_EMPTY => '.', OTH_BLACK => '●', OTH_WHITE => '○'];

function render_board(array $board): string {
    global $SYMBOL;
    $rows = ['  0 1 2 3 4 5 6 7'];
    for ($r = 0; $r < OTH_SIZE; $r++) {
        $line = "$r ";
        $cells = [];
        for ($c = 0; $c < OTH_SIZE; $c++) $cells[] = $SYMBOL[$board[$r][$c]];
        $rows[] = $line . implode(' ', $cells);
    }
    return implode("\n", $rows);
}

function build_prompt(array $board, int $player, array $moves): string {
    $you = $player === OTH_WHITE ? '○(白)' : '●(黒)';
    $moveList = implode(', ', array_map(fn($m) => "({$m[0]},{$m[1]})", $moves));
    [$black, $white] = oth_count($board);
    $boardStr = render_board($board);
    return <<<PROMPT
あなたはオセロ（リバーシ）の強いプレイヤーです。あなたの石は {$you} です。

現在の盤面（●=黒, ○=白, .=空き、行・列は0始まり）:
{$boardStr}

現在の石数: 黒(●)={$black}, 白(○)={$white}

あなたが打てる合法手の一覧（行,列）:
{$moveList}

戦略のヒント: 角(0,0)(0,7)(7,0)(7,7)は非常に強い。角の隣は相手に角を取られるので避ける。

上記の合法手の中から最善と思う一手を選び、必ず「行,列」の形式（例: 2,3）の数字だけを1行で答えてください。説明は不要です。
PROMPT;
}

// fugu に問い合わせて (row,col) を返す。失敗時は null
function fugu_choose(array $board, int $player, array $moves): ?array {
    $api_key = sakana_api_key();
    if (empty($api_key)) return null;

    $payload = [
        'model'       => 'fugu',
        'temperature' => 0.3,
        'max_tokens'  => 1024,  // fugu は内部推論にトークンを使うため余裕を持たせる
        'messages'    => [[
            'role'    => 'user',
            'content' => build_prompt($board, $player, $moves),
        ]],
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
    $text = $result['choices'][0]['message']['content'] ?? '';
    if (!preg_match_all('/\d+/', $text, $m) || count($m[0]) < 2) return null;
    $cand = [(int)$m[0][0], (int)$m[0][1]];
    foreach ($moves as $mv) {
        if ($mv[0] === $cand[0] && $mv[1] === $cand[1]) return $cand;
    }
    return null;
}

// 難易度に応じて白(AI)の一手を選ぶ。戻り値 [move, source]
function choose_ai_move(array $board, array $moves, string $level): array {
    if ($level === 'easy') {
        // 初級: ランダムな合法手
        return [oth_random_move($board, OTH_WHITE), 'easy'];
    }
    if ($level === 'hard') {
        // 上級: ミニマックス探索
        return [oth_minimax_best_move($board, OTH_WHITE, 4), 'hard'];
    }
    // 中級: fugu API（失敗時はヒューリスティック）
    $mv = fugu_choose($board, OTH_WHITE, $moves);
    if ($mv !== null) return [$mv, 'fugu'];
    return [oth_best_heuristic_move($board, OTH_WHITE), 'fallback'];
}

// 人間の着手後、AI の手番を進める（人間パス対応）
function ai_turns(array $board, string $level): array {
    $lastMove = null; $source = null; $humanPassed = false;
    while (true) {
        $aiMoves = oth_legal_moves($board, OTH_WHITE);
        if (!empty($aiMoves)) {
            [$mv, $src] = choose_ai_move($board, $aiMoves, $level);
            $board = oth_apply_move($board, $mv[0], $mv[1], OTH_WHITE);
            $lastMove = $mv; $source = $src;
        }
        if (!empty(oth_legal_moves($board, OTH_BLACK))) break;          // 人間が打てる
        if (empty(oth_legal_moves($board, OTH_WHITE))) break;           // 両者打てない＝終局
        $humanPassed = true;                                           // 人間パス→再度AI
    }
    return [$board, $lastMove, $source, $humanPassed];
}

// 入力の難易度を正規化
function normalize_level($level): string {
    return in_array($level, ['easy', 'medium', 'hard'], true) ? $level : 'medium';
}

function state(array $board, string $message = '', ?array $aiMove = null, ?string $source = null): array {
    [$black, $white] = oth_count($board);
    $humanMoves = oth_legal_moves($board, OTH_BLACK);
    $fuguMoves  = oth_legal_moves($board, OTH_WHITE);
    $gameOver = empty($humanMoves) && empty($fuguMoves);
    if ($gameOver) {
        if ($black > $white)      $message = "ゲーム終了！ あなたの勝ち！ (黒{$black} - 白{$white})";
        elseif ($white > $black)  $message = "ゲーム終了！ fuguの勝ち… (黒{$black} - 白{$white})";
        else                      $message = "ゲーム終了！ 引き分け (黒{$black} - 白{$white})";
    }
    return [
        'board'      => $board,
        'black'      => $black,
        'white'      => $white,
        'humanMoves' => $humanMoves,
        'gameOver'   => $gameOver,
        'message'    => $message,
        'aiMove'     => $aiMove,
        'aiSource'   => $source,
    ];
}

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? '';

if ($action === 'new') {
    echo json_encode(state(oth_initial_board(), 'あなた(●黒)の番です。'));
    exit;
}

if ($action === 'move') {
    $board = $input['board'] ?? null;
    $row   = $input['row'] ?? null;
    $col   = $input['col'] ?? null;
    if (!oth_validate_board($board) || !is_int($row) || !is_int($col)
        || $row < 0 || $row > 7 || $col < 0 || $col > 7) {
        http_response_code(400);
        echo json_encode(['error' => '不正なリクエストです']);
        exit;
    }
    $level = normalize_level($input['level'] ?? 'medium');

    $newBoard = oth_apply_move($board, $row, $col, OTH_BLACK);
    if ($newBoard === null) {
        http_response_code(400);
        echo json_encode(['error' => 'そこには置けません']);
        exit;
    }
    [$newBoard, $aiMove, $source, $humanPassed] = ai_turns($newBoard, $level);
    $names = ['easy' => 'CPU初級', 'fugu' => 'fugu(中級)', 'fallback' => 'fugu中級(代替手)', 'hard' => 'CPU上級'];
    $msg = 'あなた(●黒)の番です。';
    if ($aiMove !== null) {
        $tag = $names[$source] ?? 'CPU';
        $msg = "{$tag} が ({$aiMove[0]},{$aiMove[1]}) に置きました。";
        if ($humanPassed) $msg .= ' あなたは打てる手がないのでパスしました。';
    }
    echo json_encode(state($newBoard, $msg, $aiMove, $source));
    exit;
}

if ($action === 'save') {
    $board = $input['board'] ?? null;
    $name  = trim((string)($input['name'] ?? ''));
    if (!oth_validate_board($board)) {
        http_response_code(400);
        echo json_encode(['error' => '不正な盤面です']);
        exit;
    }
    // 本当に終局しているか（両者打てない）をサーバ側で確認
    $gameOver = empty(oth_legal_moves($board, OTH_BLACK)) && empty(oth_legal_moves($board, OTH_WHITE));
    if (!$gameOver) {
        http_response_code(400);
        echo json_encode(['error' => 'まだゲーム中です']);
        exit;
    }
    [$black, $white] = oth_count($board);
    $result = $black > $white ? 'win' : ($white > $black ? 'lose' : 'draw');
    $level = normalize_level($input['level'] ?? 'medium');
    if ($name === '') $name = 'ゲスト';
    if (mb_strlen($name) > 32) $name = mb_substr($name, 0, 32);
    try {
        $stmt = db()->prepare(
            'INSERT INTO games (player_name, level, result, black, white) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $level, $result, $black, $white]);
        echo json_encode(['saved' => true, 'result' => $result, 'level' => $level, 'black' => $black, 'white' => $white]);
    } catch (Throwable $ex) {
        http_response_code(500);
        echo json_encode(['error' => '保存に失敗しました']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown action']);
