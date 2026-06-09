<?php
require __DIR__ . '/config.php';
$user = require_login();
$difficulty = $_GET['difficulty'] ?? '';
if (!isset(DIFFICULTY_MODELS[$difficulty])) {
    // 難易度未選択ならトップ画面表示
    ?>
    <!DOCTYPE html><html lang="ja"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チェスvsClaude</title>
    <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; padding: 24px 16px; }
    header { display: flex; justify-content: space-between; align-items: center; max-width: 800px; margin: 0 auto 24px; }
    .who { font-size: 0.9em; color: #aaa; }
    .who b { color: #e0c870; }
    .navlinks a { color: #4fc3f7; text-decoration: none; font-size: 0.85em; margin-left: 14px; }
    main { max-width: 800px; margin: 0 auto; }
    h1 { color: #e0c870; font-size: 1.9em; text-align: center; margin-bottom: 8px; }
    .lead { text-align: center; color: #888; font-size: 0.9em; margin-bottom: 36px; }
    .levels { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    .level { background: #16213e; border: 2px solid transparent; border-radius: 12px; padding: 24px 20px; text-align: center; text-decoration: none; color: #eee; transition: all 0.15s; }
    .level:hover { border-color: #e0c870; transform: translateY(-2px); }
    .lv { font-size: 3em; font-weight: bold; line-height: 1; margin-bottom: 8px; }
    .lv.easy   { color: #66bb6a; }
    .lv.medium { color: #ffa726; }
    .lv.hard   { color: #ef5350; }
    .lname { font-size: 1.05em; font-weight: bold; margin-bottom: 4px; }
    .ldesc { font-size: 0.8em; color: #888; }
    .actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-top: 36px; }
    .action-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 18px; background: #16213e; border: 1px solid #2a3a5a; border-radius: 10px; color: #eee; text-decoration: none; font-size: 1em; font-weight: bold; transition: all 0.15s; }
    .action-btn:hover { background: #1f2f4e; border-color: #4fc3f7; transform: translateY(-1px); }
    .action-btn .ico { font-size: 1.6em; }
    .action-btn.history .ico { color: #e0c870; }
    .action-btn.ranking .ico { color: #ffd700; }
    .section-label { color: #666; font-size: 0.75em; text-transform: uppercase; letter-spacing: 2px; text-align: center; margin: 36px 0 14px; }
    @media (max-width: 600px) { .levels, .actions { grid-template-columns: 1fr; } }
    </style></head><body>
    <header>
      <div class="who">プレイヤー: <b><?= e($user['display_name']) ?></b></div>
      <div class="navlinks">
        <a href="history.php">対戦履歴</a>
        <a href="ranking.php">ランキング</a>
        <a href="logout.php">ログアウト</a>
      </div>
    </header>
    <main>
      <h1>♟ チェス vs Claude</h1>
      <p class="lead">難易度を選んで対局を始めましょう</p>
      <div class="levels">
        <a class="level" href="?difficulty=easy">
          <div class="lv easy">弱</div>
          <div class="lname">EASY</div>
          <div class="ldesc">初心者向け<br>Claude Haiku</div>
        </a>
        <a class="level" href="?difficulty=medium">
          <div class="lv medium">中</div>
          <div class="lname">MEDIUM</div>
          <div class="ldesc">中級者向け<br>Claude Sonnet</div>
        </a>
        <a class="level" href="?difficulty=hard">
          <div class="lv hard">強</div>
          <div class="lname">HARD</div>
          <div class="ldesc">上級者向け<br>Claude Opus</div>
        </a>
      </div>

      <div class="section-label">📁 過去の対戦記録</div>
      <div class="actions">
        <a class="action-btn history" href="history.php">
          <span class="ico">📊</span>
          <span>対戦履歴を見る</span>
        </a>
        <a class="action-btn ranking" href="ranking.php">
          <span class="ico">🏆</span>
          <span>ランキングを見る</span>
        </a>
      </div>
    </main>
    </body></html>
    <?php
    exit;
}

$cfg = DIFFICULTY_MODELS[$difficulty];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>チェス vs Claude (<?= e($cfg['label']) ?>)</title>
<link rel="stylesheet" href="chessboard.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #1a1a2e; color: #eee; display: flex; flex-direction: column; align-items: center; min-height: 100vh; padding: 16px; }
header { width: 100%; max-width: 760px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.who { font-size: 0.85em; color: #aaa; }
.who b { color: #e0c870; }
.navlinks a { color: #4fc3f7; text-decoration: none; font-size: 0.82em; margin-left: 12px; }
h1 { font-size: 1.5em; color: #e0c870; margin-bottom: 2px; }
.subtitle { font-size: 0.85em; color: #888; margin-bottom: 18px; }
.subtitle .lv-easy   { color: #66bb6a; font-weight: bold; }
.subtitle .lv-medium { color: #ffa726; font-weight: bold; }
.subtitle .lv-hard   { color: #ef5350; font-weight: bold; }

.game-wrap { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; justify-content: center; }
.board-col { display: flex; flex-direction: column; align-items: center; gap: 6px; }
#board { width: 480px; max-width: 90vw; }
[data-square] { position: relative; }
.hl-selected { background: rgba(246, 246, 105, 0.85) !important; }
.hl-lastmove { background: rgba(205, 210, 106, 0.65) !important; }
.hl-valid::after { content: ''; position: absolute; width: 34%; height: 34%; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0, 0, 0, 0.18); border-radius: 50%; pointer-events: none; z-index: 2; }
.hl-capture::after { content: ''; position: absolute; inset: 0; border-radius: 50%; box-shadow: inset 0 0 0 6px rgba(0, 0, 0, 0.2); pointer-events: none; z-index: 2; }
.player-label { font-size: 0.82em; font-weight: bold; letter-spacing: 0.5px; }
.label-claude { color: #4fc3f7; }
.label-you    { color: #e0c870; }
.hint { font-size: 0.76em; color: #555; margin-top: 4px; }

.side-panel { width: 220px; }
#status { padding: 12px 14px; border-radius: 8px; font-size: 0.9em; line-height: 1.5; margin-bottom: 12px; background: #16213e; border-left: 4px solid #e0c870; min-height: 52px; }
#status.thinking { border-left-color: #4fc3f7; animation: pulse 1.4s ease-in-out infinite; }
#status.error    { border-left-color: #ef5350; }
#status.gameover { border-left-color: #66bb6a; font-weight: bold; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.55; } }
.btnrow { display: flex; gap: 8px; margin-bottom: 14px; }
.btn { flex: 1; padding: 9px; border: none; border-radius: 6px; font-size: 0.85em; font-weight: bold; cursor: pointer; transition: background 0.15s; }
.btn-primary { background: #e0c870; color: #1a1a2e; }
.btn-primary:hover { background: #f0d880; }
.btn-danger { background: #ef5350; color: #fff; }
.btn-danger:hover { background: #ff6b6b; }
.btn:disabled { opacity: 0.4; cursor: not-allowed; }
.history-box { background: #16213e; border-radius: 8px; padding: 10px 12px; max-height: 320px; overflow-y: auto; }
.history-box h3 { font-size: 0.74em; color: #666; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
.move-row { display: flex; gap: 6px; font-size: 0.88em; padding: 3px 0; border-bottom: 1px solid #1f2f4e; }
.move-row:last-child { border-bottom: none; }
.mn { color: #555; width: 24px; flex-shrink: 0; }
.mw { width: 75px; color: #e0c870; }
.mb { width: 75px; color: #4fc3f7; }
</style>
</head>
<body>
<header>
  <div class="who">プレイヤー: <b><?= e($user['display_name']) ?></b></div>
  <div class="navlinks">
    <a href="index.php">難易度選択</a>
    <a href="history.php">履歴</a>
    <a href="ranking.php">ランキング</a>
    <a href="logout.php">ログアウト</a>
  </div>
</header>
<h1>♟ チェス vs Claude</h1>
<p class="subtitle">難易度: <span class="lv-<?= e($difficulty) ?>"><?= e($cfg['label']) ?></span> （<?= e($cfg['desc']) ?>） / あなた=白先手</p>

<div class="game-wrap">
  <div class="board-col">
    <div class="player-label label-claude">▲ Claude（黒）</div>
    <div id="board"></div>
    <div class="player-label label-you">▼ あなた（白）</div>
    <p class="hint">クリック or ドラッグで駒を動かせます</p>
  </div>
  <div class="side-panel">
    <div id="status">ゲーム開始！あなた（白）の手番です。</div>
    <div class="btnrow">
      <button class="btn btn-primary" id="resetBtn">↺ 新規</button>
      <button class="btn btn-danger" id="resignBtn">🏳 投了</button>
    </div>
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
const DIFFICULTY = <?= json_encode($difficulty) ?>;
let board, game;
let isThinking = false;
let selectedSq = null;
let moveCount  = 0;
let currentRow = null;
let startedAt  = null;
let gameSaved  = false;

function getSqEl(sq) { return document.querySelector('[data-square="' + sq + '"]'); }

function clearHighlights() {
    document.querySelectorAll('[data-square]').forEach(el => {
        el.classList.remove('hl-selected', 'hl-valid', 'hl-capture', 'hl-lastmove');
    });
    selectedSq = null;
}

function showValidMoves(sq) {
    clearHighlights();
    selectedSq = sq;
    const el = getSqEl(sq);
    if (el) el.classList.add('hl-selected');
    game.moves({ square: sq, verbose: true }).forEach(m => {
        const target = getSqEl(m.to);
        if (!target) return;
        target.classList.add(game.get(m.to) ? 'hl-capture' : 'hl-valid');
    });
}

function markLastMove(from, to) {
    const f = getSqEl(from), t = getSqEl(to);
    if (f) f.classList.add('hl-lastmove');
    if (t) t.classList.add('hl-lastmove');
}

function setStatus(msg, cls) {
    const el = document.getElementById('status');
    el.textContent = msg;
    el.className   = cls || '';
}

function endReason() {
    if (game.in_checkmate())            return 'checkmate';
    if (game.in_stalemate())            return 'stalemate';
    if (game.in_threefold_repetition()) return 'threefold';
    if (game.insufficient_material())   return 'insufficient';
    if (game.in_draw())                 return 'draw';
    return null;
}

function resultForWhite() {
    if (game.in_checkmate()) return game.turn() === 'w' ? 'loss' : 'win';
    if (game.in_draw() || game.in_stalemate() || game.in_threefold_repetition() || game.insufficient_material()) return 'draw';
    return null;
}

function saveGame(result, reason) {
    if (gameSaved) return;
    gameSaved = true;
    const payload = {
        difficulty: DIFFICULTY,
        result, end_reason: reason,
        total_moves: Math.ceil(game.history().length / 2),
        duration_sec: Math.round((Date.now() - startedAt) / 1000),
        pgn: game.pgn(),
        final_fen: game.fen(),
        started_at: new Date(startedAt).toISOString().slice(0, 19).replace('T', ' ')
    };
    fetch('chess_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(data => {
        if (data.ok && data.game_id) {
            const btn = document.createElement('a');
            btn.href = 'replay.php?id=' + data.game_id;
            btn.textContent = '▶ この対局を再生';
            btn.style.cssText = 'display:block;margin-top:10px;padding:8px;background:#1a1a2e;color:#4fc3f7;border-radius:6px;text-align:center;text-decoration:none;font-size:0.85em;';
            document.getElementById('status').appendChild(btn);
        }
    }).catch(() => {});
}

function updateStatus() {
    const reason = endReason();
    if (reason) {
        const result = resultForWhite();
        let msg;
        if (reason === 'checkmate') {
            msg = (result === 'win' ? 'あなたの勝利！' : 'Claudeの勝利！') + ' チェックメイト';
        } else if (reason === 'stalemate') {
            msg = 'ステイルメイト（引き分け）';
        } else if (reason === 'threefold') {
            msg = 'スリーフォールド（引き分け）';
        } else if (reason === 'insufficient') {
            msg = '駒不足（引き分け）';
        } else {
            msg = '引き分け';
        }
        setStatus(msg, 'gameover');
        document.getElementById('resignBtn').disabled = true;
        saveGame(result, reason);
    } else if (game.in_check()) {
        setStatus(game.turn() === 'w' ? 'チェック！あなた（白）の手番' : 'チェック！Claudeが考えています...', 'thinking');
    } else if (game.turn() === 'w') {
        setStatus('あなた（白）の手番です', '');
    }
}

function addMove(san, color) {
    const list = document.getElementById('moveList');
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

function applyWhiteMove(from, to) {
    const move = game.move({ from, to, promotion: 'q' });
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

function onSquareClick(sq) {
    if (isThinking || game.game_over()) return;
    if (game.turn() !== 'w') return;
    if (selectedSq) {
        if (selectedSq === sq) { clearHighlights(); return; }
        if (!applyWhiteMove(selectedSq, sq)) {
            const piece = game.get(sq);
            if (piece && piece.color === 'w') showValidMoves(sq);
            else clearHighlights();
        }
        return;
    }
    const piece = game.get(sq);
    if (piece && piece.color === 'w') showValidMoves(sq);
}

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

function onSnapEnd() { board.position(game.fen()); }

function claudeMove() {
    const validMoves = game.moves({ verbose: true }).map(m => m.from + m.to + (m.promotion || ''));
    fetch('chess_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ fen: game.fen(), valid_moves: validMoves, difficulty: DIFFICULTY })
    })
    .then(r => r.json())
    .then(data => {
        isThinking = false;
        if (data.error) { setStatus('エラー: ' + data.error, 'error'); return; }
        const uci   = data.move;
        const from  = uci.substring(0, 2);
        const to    = uci.substring(2, 4);
        const promo = uci.length > 4 ? uci[4] : 'q';
        const move  = game.move({ from, to, promotion: promo });
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
    .catch(() => { isThinking = false; setStatus('通信エラー', 'error'); });
}

function resignGame() {
    if (gameSaved || game.game_over()) return;
    if (!confirm('投了しますか？')) return;
    setStatus('投了。Claudeの勝利', 'gameover');
    document.getElementById('resignBtn').disabled = true;
    saveGame('resign', 'resign');
}

function initGame() {
    game       = new Chess();
    isThinking = false;
    moveCount  = 0;
    currentRow = null;
    selectedSq = null;
    startedAt  = Date.now();
    gameSaved  = false;
    document.getElementById('moveList').innerHTML = '';
    document.getElementById('resignBtn').disabled = false;
    setStatus('ゲーム開始！あなた（白）の手番です。', '');
    if (board) { board.start(); clearHighlights(); }
    else {
        board = Chessboard('board', {
            draggable: true, position: 'start',
            pieceTheme: 'img/chesspieces/wikipedia/{piece}.png',
            onDragStart, onDrop, onSnapEnd
        });
        document.getElementById('board').addEventListener('click', function(e) {
            let el = e.target;
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
document.getElementById('resignBtn').addEventListener('click', resignGame);
initGame();
</script>
</body>
</html>
