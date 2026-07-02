<?php
// オセロ（リバーシ）ゲームロジック
// 盤面は 8x8 の配列: 0=空き, 1=黒(プレイヤー), 2=白(fugu AI)
declare(strict_types=1);

const OTH_EMPTY = 0;
const OTH_BLACK = 1;
const OTH_WHITE = 2;
const OTH_SIZE  = 8;

const OTH_DIRS = [
    [-1, -1], [-1, 0], [-1, 1],
    [0, -1],           [0, 1],
    [1, -1],  [1, 0],  [1, 1],
];

// 位置の評価値（角は高く、角の隣は危険なのでマイナス）
const OTH_WEIGHTS = [
    [120, -20, 20,  5,  5, 20, -20, 120],
    [-20, -40, -5, -5, -5, -5, -40, -20],
    [20,   -5, 15,  3,  3, 15,  -5,  20],
    [5,    -5,  3,  3,  3,  3,  -5,   5],
    [5,    -5,  3,  3,  3,  3,  -5,   5],
    [20,   -5, 15,  3,  3, 15,  -5,  20],
    [-20, -40, -5, -5, -5, -5, -40, -20],
    [120, -20, 20,  5,  5, 20, -20, 120],
];

function oth_initial_board(): array {
    $b = array_fill(0, OTH_SIZE, array_fill(0, OTH_SIZE, OTH_EMPTY));
    $b[3][3] = OTH_WHITE;
    $b[3][4] = OTH_BLACK;
    $b[4][3] = OTH_BLACK;
    $b[4][4] = OTH_WHITE;
    return $b;
}

function oth_opponent(int $p): int {
    return $p === OTH_BLACK ? OTH_WHITE : OTH_BLACK;
}

// (row,col) に player が置いたとき裏返る石の座標リスト
function oth_flips_for(array $board, int $row, int $col, int $player): array {
    if ($board[$row][$col] !== OTH_EMPTY) return [];
    $opp = oth_opponent($player);
    $flips = [];
    foreach (OTH_DIRS as [$dr, $dc]) {
        $line = [];
        $r = $row + $dr; $c = $col + $dc;
        while ($r >= 0 && $r < OTH_SIZE && $c >= 0 && $c < OTH_SIZE && $board[$r][$c] === $opp) {
            $line[] = [$r, $c];
            $r += $dr; $c += $dc;
        }
        if (!empty($line) && $r >= 0 && $r < OTH_SIZE && $c >= 0 && $c < OTH_SIZE && $board[$r][$c] === $player) {
            foreach ($line as $pos) $flips[] = $pos;
        }
    }
    return $flips;
}

function oth_legal_moves(array $board, int $player): array {
    $moves = [];
    for ($r = 0; $r < OTH_SIZE; $r++) {
        for ($c = 0; $c < OTH_SIZE; $c++) {
            if (!empty(oth_flips_for($board, $r, $c, $player))) {
                $moves[] = [$r, $c];
            }
        }
    }
    return $moves;
}

// 着手を適用した新しい盤面を返す。合法でなければ null
function oth_apply_move(array $board, int $row, int $col, int $player): ?array {
    $flips = oth_flips_for($board, $row, $col, $player);
    if (empty($flips)) return null;
    $board[$row][$col] = $player;
    foreach ($flips as [$r, $c]) $board[$r][$c] = $player;
    return $board;
}

function oth_count(array $board): array {
    $black = 0; $white = 0;
    foreach ($board as $row) {
        foreach ($row as $v) {
            if ($v === OTH_BLACK) $black++;
            elseif ($v === OTH_WHITE) $white++;
        }
    }
    return [$black, $white];
}

// fugu が無効な手を返したときのフォールバック（1手評価）
function oth_best_heuristic_move(array $board, int $player): ?array {
    $moves = oth_legal_moves($board, $player);
    if (empty($moves)) return null;
    $best = null; $bestScore = PHP_INT_MIN;
    foreach ($moves as [$r, $c]) {
        $score = OTH_WEIGHTS[$r][$c] + count(oth_flips_for($board, $r, $c, $player));
        if ($score > $bestScore) { $bestScore = $score; $best = [$r, $c]; }
    }
    return $best;
}

// 初級: ランダムな合法手
function oth_random_move(array $board, int $player): ?array {
    $moves = oth_legal_moves($board, $player);
    if (empty($moves)) return null;
    return $moves[array_rand($moves)];
}

// 盤面評価（white=AI視点。正ならwhite有利）
function oth_evaluate(array $board): int {
    $score = 0;
    for ($r = 0; $r < OTH_SIZE; $r++) {
        for ($c = 0; $c < OTH_SIZE; $c++) {
            if ($board[$r][$c] === OTH_WHITE)      $score += OTH_WEIGHTS[$r][$c];
            elseif ($board[$r][$c] === OTH_BLACK)  $score -= OTH_WEIGHTS[$r][$c];
        }
    }
    // 着手可能数（mobility）の差も加味
    $mob = count(oth_legal_moves($board, OTH_WHITE)) - count(oth_legal_moves($board, OTH_BLACK));
    return $score + 8 * $mob;
}

// ミニマックス（αβ枝刈り）。aiPlayer視点のスコアを返す
function oth_minimax(array $board, int $depth, int $alpha, int $beta, int $turn, int $aiPlayer): int {
    if ($depth === 0) {
        return oth_evaluate($board);
    }
    $moves = oth_legal_moves($board, $turn);
    if (empty($moves)) {
        $opp = oth_opponent($turn);
        if (empty(oth_legal_moves($board, $opp))) {
            // 両者打てない＝終局。石数で大きく評価
            [$black, $white] = oth_count($board);
            $diff = $white - $black;
            return $diff > 0 ? 100000 + $diff : ($diff < 0 ? -100000 + $diff : 0);
        }
        // パスして相手の手番（深さは消費）
        return oth_minimax($board, $depth - 1, $alpha, $beta, $opp, $aiPlayer);
    }

    if ($turn === $aiPlayer) { // 最大化
        $value = PHP_INT_MIN;
        foreach ($moves as [$r, $c]) {
            $child = oth_apply_move($board, $r, $c, $turn);
            $v = oth_minimax($child, $depth - 1, $alpha, $beta, oth_opponent($turn), $aiPlayer);
            if ($v > $value) $value = $v;
            if ($value > $alpha) $alpha = $value;
            if ($alpha >= $beta) break;
        }
        return $value;
    } else { // 最小化
        $value = PHP_INT_MAX;
        foreach ($moves as [$r, $c]) {
            $child = oth_apply_move($board, $r, $c, $turn);
            $v = oth_minimax($child, $depth - 1, $alpha, $beta, oth_opponent($turn), $aiPlayer);
            if ($v < $value) $value = $v;
            if ($value < $beta) $beta = $value;
            if ($alpha >= $beta) break;
        }
        return $value;
    }
}

// 上級: ミニマックスで最善手を選ぶ
function oth_minimax_best_move(array $board, int $player, int $depth = 4): ?array {
    $moves = oth_legal_moves($board, $player);
    if (empty($moves)) return null;
    $best = null; $bestScore = PHP_INT_MIN;
    foreach ($moves as [$r, $c]) {
        $child = oth_apply_move($board, $r, $c, $player);
        $v = oth_minimax($child, $depth - 1, PHP_INT_MIN, PHP_INT_MAX, oth_opponent($player), $player);
        if ($v > $bestScore) { $bestScore = $v; $best = [$r, $c]; }
    }
    return $best;
}

// 盤面が合法な8x8の0/1/2配列かを検証
function oth_validate_board($board): bool {
    if (!is_array($board) || count($board) !== OTH_SIZE) return false;
    foreach ($board as $row) {
        if (!is_array($row) || count($row) !== OTH_SIZE) return false;
        foreach ($row as $v) {
            if (!in_array($v, [0, 1, 2], true)) return false;
        }
    }
    return true;
}
