<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>♟ fuguAI チェス</title>
<link rel="stylesheet" href="chessboard.min.css">
<style>
  * { box-sizing: border-box; }
  body { font-family:"Hiragino Sans","Yu Gothic",sans-serif; background:#1f2630; color:#fff;
         margin:0; padding:24px; text-align:center; }
  h1 { font-size:26px; margin:0 0 4px; }
  .sub { color:#9fb6cf; font-size:13px; margin-bottom:16px; }
  .namebar { margin-bottom:12px; }
  .namebar label { font-size:13px; color:#9fb6cf; margin-right:6px; }
  .namebar input { padding:7px 10px; border-radius:6px; border:none; font-size:14px; width:160px; }
  .levelbar { margin-bottom:16px; display:flex; gap:8px; justify-content:center; }
  .levelbar button { background:rgba(255,255,255,.1); color:#fff; padding:8px 18px; font-weight:bold;
    border:2px solid transparent; border-radius:999px; font-size:14px; cursor:pointer; }
  .levelbar button.sel { background:#ffd34d; color:#1f2630; }
  .levelbar button:hover { background:rgba(255,255,255,.2); }
  .levelbar button.sel:hover { background:#ffdd6e; }
  #board { width:400px; margin:0 auto 16px; box-shadow:0 8px 24px rgba(0,0,0,.5); border-radius:4px; }
  #status { min-height:22px; font-size:15px; margin-bottom:14px; color:#ffe9a8; }
  #status.thinking { color:#9fb6cf; font-style:italic; }
  .btns { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }
  button.act { background:#ffd34d; color:#1f2630; border:none; padding:10px 20px; border-radius:8px;
    font-size:15px; font-weight:bold; cursor:pointer; }
  button.act:hover { background:#ffdd6e; }
  button.act.sub2 { background:#3a4a5e; color:#fff; }
  button.act.sub2:hover { background:#4a5d75; }
  a.btn { display:inline-block; background:#3a4a5e; color:#fff; text-decoration:none;
    padding:10px 18px; border-radius:8px; font-weight:bold; }
  a.btn:hover { background:#4a5d75; }
  .highlight { box-shadow: inset 0 0 0 3px #ffd34d99; }
</style>
</head>
<body>
  <h1>♟ fuguAI チェス</h1>
  <div class="sub">あなた(白) vs AI(黒) — あなたが先手です</div>

  <div class="namebar">
    <label for="playerName">プレイヤー名</label>
    <input id="playerName" type="text" maxlength="32" placeholder="ゲスト">
  </div>

  <div class="levelbar">
    <button data-level="easy"   onclick="setLevel('easy')">初級</button>
    <button data-level="medium" onclick="setLevel('medium')">中級(fugu)</button>
    <button data-level="hard"   onclick="setLevel('hard')">上級</button>
  </div>

  <div id="board"></div>
  <div id="status">読み込み中...</div>

  <div class="btns">
    <button class="act" onclick="newGame()">🔄 新しいゲーム</button>
    <button class="act sub2" id="resignBtn" onclick="resign()">🏳 投了</button>
    <a class="btn" href="stats.php">📊 成績を見る</a>
  </div>

<script src="jquery.min.js"></script>
<script src="chessboard.min.js"></script>
<script src="chess.min.js"></script>
<script>
const API = 'fugu_chess_api.php';
let game, board;
let busy = false, saved = false, moveCount = 0, gameGen = 0;
let level = localStorage.getItem('chessfugu_level') || 'medium';

const statusEl = document.getElementById('status');

// ===== 難易度切替 =====
function applyLevelButtons() {
  document.querySelectorAll('.levelbar button').forEach(b =>
    b.classList.toggle('sel', b.dataset.level === level));
}
function setLevel(lv) {
  if (busy) return;
  level = lv;
  localStorage.setItem('chessfugu_level', lv);
  applyLevelButtons();
  newGame();
}

function setStatus(msg, cls) { statusEl.textContent = msg; statusEl.className = cls || ''; }

// ===== 盤の操作 =====
function onDragStart(source, piece) {
  if (busy || game.game_over()) return false;
  if (game.turn() !== 'w' || piece.search(/^b/) !== -1) return false; // 人間=白のみ
}

function onDrop(source, target) {
  const move = game.move({ from: source, to: target, promotion: 'q' });
  if (move === null) return 'snapback';
  moveCount++;
  window.setTimeout(afterHumanMove, 150);
}

function onSnapEnd() { board.position(game.fen()); }

function afterHumanMove() {
  board.position(game.fen());
  if (checkGameOver()) return;
  aiMove();
}

// ===== AIの手番 =====
async function aiMove() {
  busy = true;
  const myGen = gameGen; // この手番が属するゲーム世代を記録
  const labels = { easy: 'CPU初級', medium: '🐡 fugu', hard: 'CPU上級' };
  setStatus((labels[level] || 'CPU') + ' が考え中...', 'thinking');

  let uci = null;
  try {
    if (level === 'easy') {
      uci = randomMove();
    } else if (level === 'hard') {
      uci = await Promise.resolve(minimaxMove(3));
    } else {
      uci = await fuguMove();
    }
  } catch (e) { uci = randomMove(); }

  // 待機中に「新しいゲーム」等で局面が変わっていたら、この古い応答は破棄。
  // （古い手を別局面へ適用して盤面を壊し、誤って終局判定するのを防ぐ）
  if (myGen !== gameGen) return;

  // 黒(AI)の手番のときだけ、合法手として通った場合のみ適用する
  if (uci && game.turn() === 'b') {
    const mv = game.move({ from: uci.slice(0, 2), to: uci.slice(2, 4), promotion: uci.slice(4) || 'q' });
    if (mv) { moveCount++; board.position(game.fen()); }
  }
  busy = false;
  if (!checkGameOver()) {
    setStatus('あなた（白）の手番です。' + (game.in_check() ? ' 王手！' : ''), '');
  }
}

function legalUci() {
  return game.moves({ verbose: true }).map(m => m.from + m.to + (m.promotion || ''));
}

function randomMove() {
  const m = legalUci();
  return m.length ? m[Math.floor(Math.random() * m.length)] : null;
}

async function fuguMove() {
  const res = await fetch(API, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'move', fen: game.fen(), moves: legalUci() })
  });
  if (!res.ok) return randomMove();
  const data = await res.json();
  return data.move || randomMove();
}

// ===== 上級: ミニマックス(αβ) =====
const PVAL = { p: 100, n: 320, b: 330, r: 500, q: 900, k: 20000 };
// 駒の位置評価（白視点・黒は上下反転して使用）
const PST = {
  p: [0,0,0,0,0,0,0,0, 50,50,50,50,50,50,50,50, 10,10,20,30,30,20,10,10,
      5,5,10,25,25,10,5,5, 0,0,0,20,20,0,0,0, 5,-5,-10,0,0,-10,-5,5,
      5,10,10,-20,-20,10,10,5, 0,0,0,0,0,0,0,0],
  n: [-50,-40,-30,-30,-30,-30,-40,-50, -40,-20,0,0,0,0,-20,-40, -30,0,10,15,15,10,0,-30,
      -30,5,15,20,20,15,5,-30, -30,0,15,20,20,15,0,-30, -30,5,10,15,15,10,5,-30,
      -40,-20,0,5,5,0,-20,-40, -50,-40,-30,-30,-30,-30,-40,-50],
  b: [-20,-10,-10,-10,-10,-10,-10,-20, -10,0,0,0,0,0,0,-10, -10,0,5,10,10,5,0,-10,
      -10,5,5,10,10,5,5,-10, -10,0,10,10,10,10,0,-10, -10,10,10,10,10,10,10,-10,
      -10,5,0,0,0,0,5,-10, -20,-10,-10,-10,-10,-10,-10,-20],
  r: [0,0,0,0,0,0,0,0, 5,10,10,10,10,10,10,5, -5,0,0,0,0,0,0,-5, -5,0,0,0,0,0,0,-5,
      -5,0,0,0,0,0,0,-5, -5,0,0,0,0,0,0,-5, -5,0,0,0,0,0,0,-5, 0,0,0,5,5,0,0,0],
  q: [-20,-10,-10,-5,-5,-10,-10,-20, -10,0,0,0,0,0,0,-10, -10,0,5,5,5,5,0,-10,
      -5,0,5,5,5,5,0,-5, 0,0,5,5,5,5,0,-5, -10,5,5,5,5,5,0,-10,
      -10,0,5,0,0,0,0,-10, -20,-10,-10,-5,-5,-10,-10,-20],
  k: [-30,-40,-40,-50,-50,-40,-40,-30, -30,-40,-40,-50,-50,-40,-40,-30, -30,-40,-40,-50,-50,-40,-40,-30,
      -30,-40,-40,-50,-50,-40,-40,-30, -20,-30,-30,-40,-40,-30,-30,-20, -10,-20,-20,-20,-20,-20,-20,-10,
      20,20,0,0,0,0,20,20, 20,30,10,0,0,10,30,20]
};

// 局面評価（黒視点。正=黒有利）。探索用クローン g に対して評価する
function evaluate(g) {
  if (g.in_checkmate()) return g.turn() === 'b' ? -100000 : 100000;
  if (g.in_draw() || g.in_stalemate() || g.in_threefold_repetition() || g.insufficient_material()) return 0;
  let score = 0;
  const b = g.board(); // 8x8, b[0]=rank8(上)
  for (let r = 0; r < 8; r++) {
    for (let c = 0; c < 8; c++) {
      const sq = b[r][c];
      if (!sq) continue;
      const idx = r * 8 + c;                 // 白視点インデックス(上から)
      const val = PVAL[sq.type] + (PST[sq.type] ? PST[sq.type][sq.color === 'w' ? idx : (63 - idx)] : 0);
      score += sq.color === 'b' ? val : -val;
    }
  }
  return score;
}

function search(g, depth, alpha, beta) {
  if (depth === 0 || g.game_over()) return evaluate(g);
  const moves = g.moves({ verbose: true });
  if (g.turn() === 'b') { // 最大化
    let best = -Infinity;
    for (const m of moves) {
      g.move(m); const v = search(g, depth - 1, alpha, beta); g.undo();
      if (v > best) best = v;
      if (best > alpha) alpha = best;
      if (alpha >= beta) break;
    }
    return best;
  } else { // 最小化
    let best = Infinity;
    for (const m of moves) {
      g.move(m); const v = search(g, depth - 1, alpha, beta); g.undo();
      if (v < best) best = v;
      if (best < beta) beta = best;
      if (alpha >= beta) break;
    }
    return best;
  }
}

function minimaxMove(depth) {
  // 本物の game は絶対に触らず、FEN から作ったクローン上だけで探索する。
  // （万一 move/undo が不均衡になっても本番局面を破壊しない＝誤終局を防ぐ）
  const g = new Chess(game.fen());
  const moves = g.moves({ verbose: true });
  let best = null, bestScore = -Infinity;
  for (const m of moves) {
    g.move(m); const v = search(g, depth - 1, -Infinity, Infinity); g.undo();
    if (v > bestScore) { bestScore = v; best = m; }
  }
  return best ? best.from + best.to + (best.promotion || '') : null;
}

// ===== 終局判定・保存 =====
function checkGameOver() {
  if (!game.game_over()) return false;
  board.position(game.fen()); // 判定前に表示を内部状態へ強制同期（盤と結果の食い違い防止）
  let result, reason;
  if (game.in_checkmate()) {
    // 手番側がチェックメイト負け
    if (game.turn() === 'w') { result = 'lose'; reason = 'checkmate'; setStatus('チェックメイト… AIの勝ち。', ''); }
    else { result = 'win'; reason = 'checkmate'; setStatus('チェックメイト！ あなたの勝ち！', ''); }
  } else if (game.in_stalemate()) { result = 'draw'; reason = 'stalemate'; setStatus('ステイルメイト（引き分け）。', ''); }
  else if (game.in_threefold_repetition()) { result = 'draw'; reason = 'threefold'; setStatus('三回同形（引き分け）。', ''); }
  else if (game.insufficient_material()) { result = 'draw'; reason = 'material'; setStatus('駒不足（引き分け）。', ''); }
  else { result = 'draw'; reason = 'draw'; setStatus('引き分け。', ''); }
  saveResult(result, reason);
  document.getElementById('resignBtn').disabled = true;
  return true;
}

async function saveResult(result, reason) {
  if (saved) return;
  saved = true;
  const name = document.getElementById('playerName').value.trim();
  try {
    const res = await fetch(API, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'save', name, level, result, moves: moveCount, reason })
    });
    if (res.ok) statusEl.textContent += '（成績を保存しました）';
  } catch (e) { /* 保存失敗してもゲームは継続 */ }
}

function resign() {
  if (busy || game.game_over() || saved) return;
  setStatus('投了しました。AIの勝ち。', '');
  document.getElementById('resignBtn').disabled = true;
  saveResult('lose', 'resign');
}

// ===== 初期化 =====
function newGame() {
  game = new Chess();
  gameGen++; // 進行中のAI応答(fugu通信待ち等)を無効化するため世代を進める
  busy = false; saved = false; moveCount = 0;
  document.getElementById('resignBtn').disabled = false;
  if (board) { board.start(); }
  else {
    board = Chessboard('board', {
      draggable: true, position: 'start',
      pieceTheme: 'img/chesspieces/wikipedia/{piece}.png',
      onDragStart, onDrop, onSnapEnd
    });
  }
  setStatus('ゲーム開始！ あなた（白）の手番です。', '');
}

applyLevelButtons();
newGame();
$(window).resize(() => { if (board) board.resize(); });
</script>
</body>
</html>
