"""fuguAI に次の一手を選ばせるモジュール。

合法手のリストを Python 側で計算し、その中から fugu に選ばせる。
fugu が無効な応答を返した場合はヒューリスティックにフォールバックするので、
ゲームが壊れない。
"""
import os
import re

from openai import OpenAI

import othello

_client = OpenAI(
    api_key=os.environ.get("SAKANA_API_KEY", ""),
    base_url="https://api.sakana.ai/v1",
)

SYMBOL = {othello.EMPTY: ".", othello.BLACK: "●", othello.WHITE: "○"}


def _render_board(board):
    header = "  " + " ".join(str(c) for c in range(othello.SIZE))
    rows = [header]
    for r in range(othello.SIZE):
        rows.append(f"{r} " + " ".join(SYMBOL[v] for v in board[r]))
    return "\n".join(rows)


def _build_prompt(board, player, moves):
    you = "○(白)" if player == othello.WHITE else "●(黒)"
    move_list = ", ".join(f"({r},{c})" for r, c in moves)
    black, white = othello.count_stones(board)
    return f"""あなたはオセロ（リバーシ）の強いプレイヤーです。あなたの石は {you} です。

現在の盤面（●=黒, ○=白, .=空き、行・列は0始まり）:
{_render_board(board)}

現在の石数: 黒(●)={black}, 白(○)={white}

あなたが打てる合法手の一覧（行,列）:
{move_list}

戦略のヒント: 角(0,0)(0,7)(7,0)(7,7)は非常に強い。角の隣は相手に角を取られるので避ける。

上記の合法手の中から最善と思う一手を選び、必ず「行,列」の形式（例: 2,3）の数字だけを1行で答えてください。説明は不要です。"""


def _parse_move(text, moves):
    """fugu の応答から (row, col) を抜き出し、合法手集合に含まれるか確認。"""
    if not text:
        return None
    nums = re.findall(r"\d+", text)
    if len(nums) >= 2:
        candidate = (int(nums[0]), int(nums[1]))
        if candidate in moves:
            return candidate
    return None


def choose_move(board, player):
    """fugu に一手選ばせる。(row, col) または打てる手が無ければ None。

    返り値: (move, source)  source は "fugu" か "fallback"。
    """
    moves = othello.legal_moves(board, player)
    if not moves:
        return None, None

    try:
        res = _client.chat.completions.create(
            model="fugu",
            messages=[{"role": "user", "content": _build_prompt(board, player, moves)}],
            temperature=0.3,
        )
        text = res.choices[0].message.content
        move = _parse_move(text, moves)
        if move is not None:
            return move, "fugu"
    except Exception as e:  # API エラー時もゲームを止めない
        print("fugu API error:", e)

    return othello.best_heuristic_move(board, player), "fallback"
