<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🐡 fuguAI オセロ</title>
<style>
  :root { --cell: 52px; }
  * { box-sizing: border-box; }
  body {
    font-family: "Hiragino Sans", "Yu Gothic", sans-serif;
    background: #0d3b2e; color: #fff; text-align: center;
    margin: 0; padding: 24px;
  }
  h1 { font-size: 26px; margin: 0 0 4px; }
  .sub { color: #9fd9c4; font-size: 13px; margin-bottom: 16px; }
  .score {
    display: flex; justify-content: center; gap: 28px;
    margin-bottom: 14px; font-size: 17px; align-items: center;
  }
  .score .me, .score .ai {
    padding: 6px 16px; border-radius: 999px; background: rgba(255,255,255,.08);
    transition: box-shadow .2s;
  }
  .score .active { box-shadow: 0 0 0 2px #ffd34d; }
  .disc { display: inline-block; width: 18px; height: 18px; border-radius: 50%;
          vertical-align: -3px; margin-right: 6px; }
  .disc.black { background: #111; border: 1px solid #444; }
  .disc.white { background: #f5f5f5; }
  #board {
    display: grid; grid-template-columns: repeat(8, var(--cell));
    grid-template-rows: repeat(8, var(--cell));
    gap: 2px; background: #04221a; padding: 6px; border-radius: 8px;
    width: max-content; margin: 0 auto 16px; box-shadow: 0 8px 24px rgba(0,0,0,.4);
  }
  .cell {
    width: var(--cell); height: var(--cell); background: #15715a;
    border-radius: 4px; display: flex; align-items: center; justify-content: center;
    cursor: default; position: relative;
  }
  .cell.playable { cursor: pointer; }
  .cell.playable::after {
    content: ""; width: 30%; height: 30%; border-radius: 50%;
    background: rgba(255,255,255,.28);
  }
  .cell.playable:hover::after { background: rgba(255,255,255,.55); }
  .stone { width: 78%; height: 78%; border-radius: 50%; animation: pop .18s ease-out; }
  .stone.black { background: radial-gradient(circle at 35% 30%, #555, #050505); }
  .stone.white { background: radial-gradient(circle at 35% 30%, #fff, #c8c8c8); }
  .cell.last { box-shadow: inset 0 0 0 3px #ffd34d; }
  @keyframes pop { from { transform: scale(.3); } to { transform: scale(1); } }
  #message { min-height: 22px; font-size: 15px; margin-bottom: 14px; color: #ffe9a8; }
  button {
    background: #ffd34d; color: #0d3b2e; border: none; padding: 10px 22px;
    border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer;
  }
  button:hover { background: #ffdd6e; }
  a.btn { display:inline-block; background:#15715a; color:#fff; text-decoration:none;
          padding:10px 18px; border-radius:8px; font-weight:bold; margin-left:8px; }
  a.btn:hover { background:#1c8a6d; }
  .thinking { color: #9fd9c4; font-style: italic; }
  .namebar { margin-bottom: 14px; }
  .namebar input { padding:7px 10px; border-radius:6px; border:none; font-size:14px; width:160px; }
  .namebar label { font-size:13px; color:#9fd9c4; margin-right:6px; }
  .levelbar { margin-bottom: 16px; display:flex; gap:8px; justify-content:center; }
  .levelbar button {
    background: rgba(255,255,255,.1); color:#fff; padding:8px 18px; font-weight:bold;
    border:2px solid transparent; border-radius:999px; font-size:14px;
  }
  .levelbar button.sel { background:#ffd34d; color:#0d3b2e; }
  .levelbar button:hover { background: rgba(255,255,255,.2); }
  .levelbar button.sel:hover { background:#ffdd6e; }
</style>
</head>
<body>
  <h1>🐡 fuguAI オセロ</h1>
  <div class="sub">あなた(●黒) vs fuguAI(○白) — 黒が先手です</div>

  <div class="namebar">
    <label for="playerName">プレイヤー名</label>
    <input id="playerName" type="text" maxlength="32" placeholder="ゲスト">
  </div>

  <div class="levelbar">
    <button data-level="easy"   onclick="setLevel('easy')">初級</button>
    <button data-level="medium" onclick="setLevel('medium')">中級(fugu)</button>
    <button data-level="hard"   onclick="setLevel('hard')">上級</button>
  </div>

  <div class="score">
    <span class="me" id="scoreMe"><span class="disc black"></span>あなた <b>2</b></span>
    <span class="ai" id="scoreAi"><span class="disc white"></span>fugu <b>2</b></span>
  </div>

  <div id="board"></div>
  <div id="message">読み込み中...</div>
  <button onclick="newGame()">🔄 新しいゲーム</button>
  <a class="btn" href="stats.php">📊 成績を見る</a>

<script>
const API = 'fugu_api.php';
let board = [];
let humanMoves = [];
let lastAiMove = null;
let busy = false;
let saved = false;   // 終局時の二重保存防止
let level = localStorage.getItem('othello_level') || 'medium';

function applyLevelButtons() {
  document.querySelectorAll('.levelbar button').forEach(b => {
    b.classList.toggle('sel', b.dataset.level === level);
  });
}

function setLevel(lv) {
  if (busy) return;
  level = lv;
  localStorage.setItem('othello_level', lv);
  applyLevelButtons();
  newGame();   // 難易度を変えたら新規対局
}

const boardEl = document.getElementById('board');
const msgEl = document.getElementById('message');

function render() {
  boardEl.innerHTML = '';
  const playable = new Set(humanMoves.map(m => m[0] + ',' + m[1]));
  for (let r = 0; r < 8; r++) {
    for (let c = 0; c < 8; c++) {
      const cell = document.createElement('div');
      cell.className = 'cell';
      const v = board[r][c];
      if (v === 1 || v === 2) {
        const s = document.createElement('div');
        s.className = 'stone ' + (v === 1 ? 'black' : 'white');
        cell.appendChild(s);
      } else if (!busy && playable.has(r + ',' + c)) {
        cell.classList.add('playable');
        cell.onclick = () => play(r, c);
      }
      if (lastAiMove && lastAiMove[0] === r && lastAiMove[1] === c) {
        cell.classList.add('last');
      }
      boardEl.appendChild(cell);
    }
  }
}

function updateState(s) {
  board = s.board;
  humanMoves = s.humanMoves;
  lastAiMove = s.aiMove;
  document.querySelector('#scoreMe b').textContent = s.black;
  document.querySelector('#scoreAi b').textContent = s.white;
  msgEl.textContent = s.message;
  msgEl.className = '';
  document.getElementById('scoreMe').classList.toggle('active', !s.gameOver && humanMoves.length > 0);
  document.getElementById('scoreAi').classList.toggle('active', false);
  render();

  if (s.gameOver && !saved) {
    saved = true;
    saveResult();
  }
}

async function saveResult() {
  const name = document.getElementById('playerName').value.trim();
  try {
    const res = await post({ action: 'save', board, name, level });
    if (res.ok) {
      msgEl.textContent += '（成績を保存しました）';
    }
  } catch (e) { /* 保存失敗してもゲームは続行可能 */ }
}

async function post(body) {
  const res = await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return res;
}

async function newGame() {
  busy = false;
  saved = false;
  const res = await post({ action: 'new' });
  updateState(await res.json());
}

async function play(r, c) {
  if (busy) return;
  busy = true;
  const sendBoard = board.map(row => row.slice());
  board[r][c] = 1;          // 見た目だけ先に黒石
  humanMoves = [];
  render();
  const thinkLabel = { easy: 'CPU初級', medium: '🐡 fugu', hard: 'CPU上級' }[level] || 'CPU';
  msgEl.textContent = thinkLabel + ' が考え中...';
  msgEl.className = 'thinking';
  document.getElementById('scoreAi').classList.add('active');
  document.getElementById('scoreMe').classList.remove('active');

  try {
    const res = await post({ action: 'move', board: sendBoard, row: r, col: c, level });
    if (!res.ok) {
      const err = await res.json();
      msgEl.textContent = err.error || 'エラー';
      busy = false;
      return;
    }
    busy = false;
    updateState(await res.json());
  } catch (e) {
    msgEl.textContent = '通信エラー: ' + e;
    busy = false;
  }
}

applyLevelButtons();
newGame();
</script>
</body>
</html>
