"""オセロ（リバーシ）のゲームロジック。

盤面は 8x8 の二次元リストで表現する。
  0 = 空き, 1 = 黒(プレイヤー), 2 = 白(fugu AI)
"""

EMPTY, BLACK, WHITE = 0, 1, 2
SIZE = 8
DIRECTIONS = [(-1, -1), (-1, 0), (-1, 1),
              (0, -1),           (0, 1),
              (1, -1),  (1, 0),  (1, 1)]

# 位置の評価値（角は高く、角の隣は危険なのでマイナス）
WEIGHTS = [
    [120, -20, 20,  5,  5, 20, -20, 120],
    [-20, -40, -5, -5, -5, -5, -40, -20],
    [20,   -5, 15,  3,  3, 15,  -5,  20],
    [5,    -5,  3,  3,  3,  3,  -5,   5],
    [5,    -5,  3,  3,  3,  3,  -5,   5],
    [20,   -5, 15,  3,  3, 15,  -5,  20],
    [-20, -40, -5, -5, -5, -5, -40, -20],
    [120, -20, 20,  5,  5, 20, -20, 120],
]


def initial_board():
    board = [[EMPTY] * SIZE for _ in range(SIZE)]
    board[3][3] = WHITE
    board[3][4] = BLACK
    board[4][3] = BLACK
    board[4][4] = WHITE
    return board


def opponent(player):
    return WHITE if player == BLACK else BLACK


def _flips_for(board, row, col, player):
    """(row, col) に player が置いたとき裏返る石の座標リストを返す。"""
    if board[row][col] != EMPTY:
        return []
    opp = opponent(player)
    flips = []
    for dr, dc in DIRECTIONS:
        line = []
        r, c = row + dr, col + dc
        while 0 <= r < SIZE and 0 <= c < SIZE and board[r][c] == opp:
            line.append((r, c))
            r += dr
            c += dc
        if line and 0 <= r < SIZE and 0 <= c < SIZE and board[r][c] == player:
            flips.extend(line)
    return flips


def legal_moves(board, player):
    """player が打てる合法手の座標リスト。"""
    moves = []
    for r in range(SIZE):
        for c in range(SIZE):
            if _flips_for(board, r, c, player):
                moves.append((r, c))
    return moves


def apply_move(board, row, col, player):
    """着手を適用した新しい盤面を返す。合法でない場合は None。"""
    flips = _flips_for(board, row, col, player)
    if not flips:
        return None
    new_board = [row[:] for row in board]
    new_board[row][col] = player
    for r, c in flips:
        new_board[r][c] = player
    return new_board


def count_stones(board):
    black = sum(row.count(BLACK) for row in board)
    white = sum(row.count(WHITE) for row in board)
    return black, white


def evaluate_move(board, row, col, player):
    """ヒューリスティック評価: 位置の重み + 裏返す枚数。"""
    flips = _flips_for(board, row, col, player)
    return WEIGHTS[row][col] + len(flips)


def best_heuristic_move(board, player):
    """fugu が無効な手を返したときのフォールバック。"""
    moves = legal_moves(board, player)
    if not moves:
        return None
    return max(moves, key=lambda m: evaluate_move(board, m[0], m[1], player))
