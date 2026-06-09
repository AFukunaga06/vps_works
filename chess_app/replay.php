<?php
require __DIR__ . '/config.php';
$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT g.*, u.display_name, u.id AS owner_id
    FROM games g JOIN users u ON u.id = g.user_id WHERE g.id = ?');
$stmt->execute([$id]);
$g = $stmt->fetch();
if (!$g) {
    http_response_code(404);
    echo '対局が見つかりません';
    exit;
}

$diff_label = ['easy' => '弱', 'medium' => '中', 'hard' => '強'];
$diff_class = ['easy' => 'lv-easy', 'medium' => 'lv-medium', 'hard' => 'lv-hard'];
$res_label  = ['win' => '勝ち', 'loss' => '負け', 'draw' => '引分', 'resign' => '投了'];
$reason_jp  = ['checkmate' => 'チェックメイト', 'stalemate' => 'ステイルメイト', 'draw' => '引分合意', 'threefold' => 'スリーフォールド', 'insufficient' => '駒不足', 'resign' => '投了', 'timeout' => '時間切れ'];
$lichess_url = 'https://lichess.org/analysis/pgn/' . rawurlencode($g['pgn'] ?? '');
?>
<!DOCTYPE html>
<html lang="ja"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>対局再生 #<?= (int)$g['id'] ?></title>
<link rel="stylesheet" href="chessboard.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; padding: 16px; display: flex; flex-direction: column; align-items: center; }
header { width: 100%; max-width: 760px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.who { font-size: 0.85em; color: #aaa; }
.who b { color: #e0c870; }
.navlinks a { color: #4fc3f7; text-decoration: none; font-size: 0.82em; margin-left: 12px; }
h1 { color: #e0c870; font-size: 1.3em; margin-bottom: 4px; }
.meta { font-size: 0.83em; color: #888; margin-bottom: 16px; }
.lv-easy   { color: #66bb6a; font-weight: bold; }
.lv-medium { color: #ffa726; font-weight: bold; }
.lv-hard   { color: #ef5350; font-weight: bold; }
.r-win    { color: #66bb6a; font-weight: bold; }
.r-loss   { color: #ef5350; font-weight: bold; }
.r-draw   { color: #888; font-weight: bold; }
.r-resign { color: #ff9090; font-weight: bold; }

.wrap { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; justify-content: center; }
#board { width: 440px; max-width: 90vw; }
.side { width: 240px; }
.controls { display: flex; gap: 6px; margin-bottom: 12px; }
.ctlbtn { flex: 1; padding: 9px 6px; background: #16213e; color: #eee; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9em; }
.ctlbtn:hover { background: #1f2f4e; }
.ctlbtn:disabled { opacity: 0.3; cursor: not-allowed; }
.movenum { text-align: center; font-size: 0.85em; color: #888; margin-bottom: 12px; }
.history-box { background: #16213e; border-radius: 8px; padding: 10px 12px; max-height: 280px; overflow-y: auto; margin-bottom: 12px; }
.history-box h3 { font-size: 0.74em; color: #666; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
.move-row { display: flex; gap: 6px; font-size: 0.86em; padding: 3px 0; border-bottom: 1px solid #1f2f4e; cursor: pointer; }
.move-row:last-child { border-bottom: none; }
.move-row:hover { background: #1f2f4e; }
.move-row.cur { background: rgba(224, 200, 112, 0.15); }
.mn { color: #555; width: 24px; flex-shrink: 0; }
.mw { width: 75px; color: #e0c870; }
.mb { width: 75px; color: #4fc3f7; }
.linkbox { background: #16213e; padding: 10px 12px; border-radius: 8px; }
.linkbox a { color: #4fc3f7; text-decoration: none; font-size: 0.85em; display: block; padding: 3px 0; }
.pgnbox { margin-top: 10px; font-family: monospace; font-size: 0.78em; color: #aaa; word-break: break-all; max-height: 100px; overflow-y: auto; background: #0f1830; padding: 8px; border-radius: 4px; }
</style></head><body>
<header>
  <div class="who">プレイヤー: <b><?= e($user['display_name']) ?></b></div>
  <div class="navlinks">
    <a href="index.php">対局</a>
    <a href="history.php">履歴</a>
    <a href="ranking.php">ランキング</a>
    <a href="logout.php">ログアウト</a>
  </div>
</header>

<h1>♟ 対局再生 #<?= (int)$g['id'] ?></h1>
<p class="meta">
  <?= e($g['display_name']) ?> vs Claude
  / 難易度 <span class="<?= $diff_class[$g['difficulty']] ?>"><?= $diff_label[$g['difficulty']] ?></span>
  / 結果 <span class="r-<?= e($g['result']) ?>"><?= $res_label[$g['result']] ?></span>
  / <?= e($reason_jp[$g['end_reason']] ?? $g['end_reason']) ?>
  / <?= (int)$g['total_moves'] ?>手
  / <?= sprintf('%d:%02d', intdiv($g['duration_sec'], 60), $g['duration_sec'] % 60) ?>
  / <?= e(date('Y-m-d H:i', strtotime($g['ended_at']))) ?>
</p>

<div class="wrap">
  <div><div id="board"></div></div>
  <div class="side">
    <div class="controls">
      <button class="ctlbtn" id="btnStart">⏮</button>
      <button class="ctlbtn" id="btnPrev">◀</button>
      <button class="ctlbtn" id="btnNext">▶</button>
      <button class="ctlbtn" id="btnEnd">⏭</button>
    </div>
    <div class="movenum" id="movenum">開始局面</div>
    <div class="history-box">
      <h3>棋譜</h3>
      <div id="moveList"></div>
    </div>
    <div class="linkbox">
      <a href="<?= e($lichess_url) ?>" target="_blank">🔗 lichess.org で解析</a>
      <a href="history.php">← 履歴に戻る</a>
      <div class="pgnbox"><?= e($g['pgn']) ?></div>
    </div>
  </div>
</div>

<script src="jquery.min.js"></script>
<script src="chessboard.min.js"></script>
<script src="chess.min.js"></script>
<script>
const PGN = <?= json_encode($g['pgn'] ?? '') ?>;
const game = new Chess();
game.load_pgn(PGN);
const history = game.history({ verbose: true });
game.reset();

const positions = [game.fen()];
const sans = [];
for (const mv of history) {
    game.move({ from: mv.from, to: mv.to, promotion: mv.promotion });
    positions.push(game.fen());
    sans.push(mv.san);
}

const board = Chessboard('board', {
    position: 'start',
    pieceTheme: 'img/chesspieces/wikipedia/{piece}.png',
    showNotation: true
});

let cur = 0;
function render() {
    board.position(positions[cur], false);
    const mn = document.getElementById('movenum');
    if (cur === 0) mn.textContent = '開始局面';
    else {
        const n = Math.ceil(cur / 2);
        const color = cur % 2 === 1 ? '白' : '黒';
        mn.textContent = n + '. ' + sans[cur - 1] + ' (' + color + ')';
    }
    document.getElementById('btnStart').disabled = cur === 0;
    document.getElementById('btnPrev').disabled  = cur === 0;
    document.getElementById('btnNext').disabled  = cur === positions.length - 1;
    document.getElementById('btnEnd').disabled   = cur === positions.length - 1;
    document.querySelectorAll('.move-row').forEach((el, i) => {
        el.classList.toggle('cur', i === Math.floor((cur - 1) / 2));
    });
}

// 棋譜リスト
const list = document.getElementById('moveList');
for (let i = 0; i < sans.length; i += 2) {
    const row = document.createElement('div');
    row.className = 'move-row';
    row.dataset.idx = i / 2;
    row.innerHTML = '<span class="mn">' + (i / 2 + 1) + '.</span>'
        + '<span class="mw">' + sans[i] + '</span>'
        + '<span class="mb">' + (sans[i+1] || '') + '</span>';
    row.addEventListener('click', () => { cur = Math.min(positions.length - 1, i + 2); render(); });
    list.appendChild(row);
}

document.getElementById('btnStart').addEventListener('click', () => { cur = 0; render(); });
document.getElementById('btnPrev').addEventListener('click',  () => { if (cur > 0) { cur--; render(); } });
document.getElementById('btnNext').addEventListener('click',  () => { if (cur < positions.length - 1) { cur++; render(); } });
document.getElementById('btnEnd').addEventListener('click',   () => { cur = positions.length - 1; render(); });
document.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft')  { if (cur > 0) { cur--; render(); } }
    if (e.key === 'ArrowRight') { if (cur < positions.length - 1) { cur++; render(); } }
});

render();
</script>
</body></html>
