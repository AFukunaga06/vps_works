"""fuguAI と対戦するオセロゲーム（Flask）。

プレイヤー = 黒(●)、fuguAI = 白(○)。
ロジックは全て othello.py / fugu_ai.py に持ち、サーバはステートレス。
盤面はクライアントが保持し、各リクエストで送受信する。
"""
from flask import Flask, request, jsonify, render_template

import othello
import fugu_ai

app = Flask(__name__)


def _state(board, message="", ai_move=None, source=None):
    """盤面から、フロントに返す状態をまとめる。"""
    black, white = othello.count_stones(board)
    human_moves = othello.legal_moves(board, othello.BLACK)
    fugu_moves = othello.legal_moves(board, othello.WHITE)
    game_over = not human_moves and not fugu_moves

    if game_over:
        if black > white:
            message = f"ゲーム終了！ あなたの勝ち！ (黒{black} - 白{white})"
        elif white > black:
            message = f"ゲーム終了！ fuguの勝ち… (黒{black} - 白{white})"
        else:
            message = f"ゲーム終了！ 引き分け (黒{black} - 白{white})"

    return {
        "board": board,
        "black": black,
        "white": white,
        "humanMoves": [list(m) for m in human_moves],
        "gameOver": game_over,
        "message": message,
        "aiMove": list(ai_move) if ai_move else None,
        "aiSource": source,
    }


def _ai_turns(board):
    """人間の着手後、fugu の手番を進める。

    人間が再び打てるようになるか、両者打てなくなる（終局）まで繰り返す。
    人間に合法手が無い場合はパスして fugu が連続で打つ。
    """
    last_move = last_source = None
    human_passed = False
    while True:
        # fugu の手番（合法手があれば打つ。無ければパス）
        if othello.legal_moves(board, othello.WHITE):
            move, source = fugu_ai.choose_move(board, othello.WHITE)
            board = othello.apply_move(board, move[0], move[1], othello.WHITE)
            last_move, last_source = move, source

        if othello.legal_moves(board, othello.BLACK):
            break  # 人間が打てる → ターン交代
        if not othello.legal_moves(board, othello.WHITE):
            break  # 両者打てない → 終局
        human_passed = True  # 人間は打てないが fugu は打てる → もう一度 fugu

    extra = "あなたは打てる手がないのでパスしました。" if human_passed else ""
    return board, last_move, last_source, extra


@app.route("/")
def index():
    return render_template("index.html")


@app.route("/new", methods=["POST"])
def new_game():
    board = othello.initial_board()
    return jsonify(_state(board, message="あなた(●黒)の番です。"))


@app.route("/move", methods=["POST"])
def move():
    data = request.get_json()
    board = data["board"]
    row, col = data["row"], data["col"]

    new_board = othello.apply_move(board, row, col, othello.BLACK)
    if new_board is None:
        return jsonify({"error": "そこには置けません"}), 400

    # fugu のターンを進める
    new_board, ai_move, source, extra = _ai_turns(new_board)

    msg = "あなた(●黒)の番です。"
    if ai_move is not None:
        tag = "fugu" if source == "fugu" else "fugu(代替手)"
        msg = f"{tag} が ({ai_move[0]},{ai_move[1]}) に置きました。 {extra}".strip()
    return jsonify(_state(new_board, message=msg, ai_move=ai_move, source=source))


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5005, debug=True)
