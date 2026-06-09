<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チェス vs Claude AI</title>
    <link rel="stylesheet" href="chessboard.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: #1a1a2e;
            color: #eee;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 24px 16px;
        }

        h1 { font-size: 1.8em; color: #e0c870; margin-bottom: 4px; }
        .subtitle { font-size: 0.9em; color: #888; margin-bottom: 24px; }

        .game-wrap { display: flex; gap: 20px; align-items: flex-start; }

        .board-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        #board { width: 480px; }

        /* マス目のハイライト（chess.comスタイル） */
        [data-square] { position: relative; }

        .hl-selected { background: rgba(246, 246, 105, 0.85) !important; }
        .hl-lastmove { background: rgba(205, 210, 106, 0.65) !important; }

        /* 移動可能マス：中央にドット */
        .hl-valid::after {
            content: '';
            position: absolute;
            width: 34%; height: 34%;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.18);
            border-radius: 50%;
            pointer-events: none;
            z-index: 2;
        }

        /* 取れる駒があるマス：リング */
        .hl-capture::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            box-shadow: inset 0 0 0 6px rgba(0, 0, 0, 0.2);
            pointer-events: none;
            z-index: 2;
        }

        .player-label { font-size: 0.85em; font-weight: bold; letter-spacing: 0.5px; }
        .label-claude { color: #4fc3f7; }
        .label-you    { color: #e0c870; }
        .hint { font-size: 0.78em; color: #555; margin-top: 6px; }

        .side-panel { width: 220px; }

        #status {
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 0.92em;
            line-height: 1.5;
            margin-bottom: 14px;
            background: #16213e;
            border-left: 4px solid #e0c870;
            min-height: 52px;
        }
        #status.thinking { border-left-color: #4fc3f7; animation: pulse 1.4s ease-in-out infinite; }
        #status.error    { border-left-color: #ef5350; }
        #status.gameover { border-left-color: #66bb6a; font-weight: bold; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.55; }
        }

        .btn {
            width: 100%;
            padding: 10px;
            background: #e0c870;
            color: #1a1a2e;
            border: none;
            border-radius: 6px;
            font-size: 0.95em;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 16px;
            transition: background 0.15s;
        }
        .btn:hover { background: #f0d880; }

        .history-box {
            background: #16213e;
            border-radius: 8px;
            padding: 10px 12px;
            max-height: 350px;
            overflow-y: auto;
        }
        .history-box h3 {
            font-size: 0.78em;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .move-row {
            display: flex;
            gap: 6px;
            font-size: 0.9em;
            padding: 3px 0;
            border-bottom: 1px solid #1f2f4e;
        }
        .move-row:last-child { border-bottom: none; }
        .mn { color: #555; width: 24px; flex-shrink: 0; }
        .mw { width: 75px; color: #e0c870; }
        .mb { width: 75px; color: #4fc3f7; }
    </style>
</head>
<body>
    <h1>♟ チェス vs Claude AI</h1>
    <p class="subtitle">あなた（白先手）vs Claude（黒）</p>

    <div class="game-wrap">
        <div class="board-col">
            <div class="player-label label-claude">▲ Claude（黒）</div>
            <div id="board"></div>
            <div class="player-label label-you">▼ あなた（白）</div>
            <p class="hint">クリックまたはドラッグで駒を動かせます</p>
        </div>

        <div class="side-panel">
            <div id="status">ゲーム開始！あなた（白）の手番です。</div>
            <button class="btn" id="resetBtn">↺ 新しいゲーム</button>
            <div class="history-box">
                <h3>棋譜</h3>
                <div id="moveList"></div>
            </div>
        </div>
    </div>

    <script src="jquery.min.js"></script>
    <script src="chessboard.min.js"></script>
    <script src="chess.min.js"></script>
    <script>
    var board, game;
    var isThinking = false;
    var selectedSq = null;
    var moveCount  = 0;
    var currentRow = null;

    /* ---- ハイライト ---- */
    function getSqEl(sq) {
        return document.querySelector('[data-square="' + sq + '"]');
    }

    function clearHighlights() {
        document.querySelectorAll('[data-square]').forEach(function(el) {
            el.classList.remove('hl-selected', 'hl-valid', 'hl-capture', 'hl-lastmove');
        });
        selectedSq = null;
    }

    function showValidMoves(sq) {
        clearHighlights();
        selectedSq = sq;
        var el = getSqEl(sq);
        if (el) el.classList.add('hl-selected');

        game.moves({ square: sq, verbose: true }).forEach(function(m) {
            var target = getSqEl(m.to);
            if (!target) return;
            target.classList.add(game.get(m.to) ? 'hl-capture' : 'hl-valid');
        });
    }

    function markLastMove(from, to) {
        var f = getSqEl(from), t = getSqEl(to);
        if (f) f.classList.add('hl-lastmove');
        if (t) t.classList.add('hl-lastmove');
    }

    /* ---- ステータス ---- */
    function setStatus(msg, cls) {
        var el = document.getElementById('status');
        el.textContent = msg;
        el.className   = cls || '';
    }

    function updateStatus() {
        if (game.in_checkmate()) {
            setStatus((game.turn() === 'w' ? 'Claude（黒）' : 'あなた（白）') + ' の勝利！チェックメイト！', 'gameover');
        } else if (game.in_draw()) {
            setStatus('引き分け！', 'gameover');
        } else if (game.in_check()) {
            setStatus(game.turn() === 'w'
                ? 'チェック！あなた（白）の手番です。'
                : 'チェック！Claudeが考えています...', 'thinking');
        } else if (game.turn() === 'w') {
            setStatus('あなた（白）の手番です。', '');
        }
    }

    /* ---- 棋譜 ---- */
    function addMove(san, color) {
        var list = document.getElementById('moveList');
        if (color === 'white') {
            moveCount++;
            currentRow = document.createElement('div');
            currentRow.className = 'move-row';
            currentRow.innerHTML =
                '<span class="mn">' + moveCount + '.</span>' +
                '<span class="mw">' + san + '</span>' +
                '<span class="mb"></span>';
            list.appendChild(currentRow);
        } else if (currentRow) {
            currentRow.querySelector('.mb').textContent = san;
        }
        list.scrollTop = list.scrollHeight;
    }

    /* ---- 白の手を適用 ---- */
    function applyWhiteMove(from, to) {
        var move = game.move({ from: from, to: to, promotion: 'q' });
        if (!move) return false;

        clearHighlights();
        board.position(game.fen());
        markLastMove(from, to);
        addMove(move.san, 'white');
        updateStatus();

        if (!game.game_over()) {
            isThinking = true;
            setStatus('Claudeが考えています...', 'thinking');
            setTimeout(claudeMove, 400);
        }
        return true;
    }

    /* ---- クリック操作（イベント委譲） ---- */
    function onSquareClick(sq) {
        if (isThinking || game.game_over()) return;
        if (game.turn() !== 'w') return;

        if (selectedSq) {
            if (selectedSq === sq) { clearHighlights(); return; }

            if (!applyWhiteMove(selectedSq, sq)) {
                var piece = game.get(sq);
                if (piece && piece.color === 'w') {
                    showValidMoves(sq);
                } else {
                    clearHighlights();
                }
            }
            return;
        }

        var piece = game.get(sq);
        if (piece && piece.color === 'w') {
            showValidMoves(sq);
        }
    }

    /* ---- ドラッグ操作 ---- */
    function onDragStart(source, piece) {
        if (game.game_over() || isThinking) return false;
        if (game.turn() !== 'w')            return false;
        if (/^b/.test(piece))               return false;
        showValidMoves(source);
    }

    function onDrop(source, target) {
        clearHighlights();
        if (source === target) return 'snapback';
        return applyWhiteMove(source, target) ? undefined : 'snapback';
    }

    function onSnapEnd() {
        board.position(game.fen());
    }

    /* ---- Claudeの手 ---- */
    function claudeMove() {
        var validMoves = game.moves({ verbose: true }).map(function(m) {
            return m.from + m.to + (m.promotion || '');
        });
        fetch('chess_api.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ fen: game.fen(), valid_moves: validMoves })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            isThinking = false;
            if (data.error) { setStatus('エラー: ' + data.error, 'error'); return; }

            var uci   = data.move;
            var from  = uci.substring(0, 2);
            var to    = uci.substring(2, 4);
            var promo = uci.length > 4 ? uci[4] : 'q';
            var move  = game.move({ from: from, to: to, promotion: promo });

            if (move) {
                clearHighlights();
                board.position(game.fen());
                markLastMove(from, to);
                addMove(move.san, 'black');
                updateStatus();
            } else {
                setStatus('Claudeが無効な手を返しました (' + uci + ')', 'error');
            }
        })
        .catch(function() {
            isThinking = false;
            setStatus('通信エラーが発生しました', 'error');
        });
    }

    /* ---- 初期化 ---- */
    function initGame() {
        game       = new Chess();
        isThinking = false;
        moveCount  = 0;
        currentRow = null;
        selectedSq = null;
        document.getElementById('moveList').innerHTML = '';
        setStatus('ゲーム開始！あなた（白）の手番です。', '');

        if (board) {
            board.start();
            clearHighlights();
        } else {
            board = Chessboard('board', {
                draggable:   true,
                position:    'start',
                pieceTheme:  'img/chesspieces/wikipedia/{piece}.png',
                onDragStart: onDragStart,
                onDrop:      onDrop,
                onSnapEnd:   onSnapEnd
            });

            /* クリック操作：イベント委譲でdata-square属性を取得 */
            document.getElementById('board').addEventListener('click', function(e) {
                var el = e.target;
                while (el && el !== this) {
                    if (el.hasAttribute('data-square')) {
                        onSquareClick(el.getAttribute('data-square'));
                        return;
                    }
                    el = el.parentElement;
                }
            });
        }
    }

    document.getElementById('resetBtn').addEventListener('click', initGame);
    initGame();
    </script>
</body>
</html>
