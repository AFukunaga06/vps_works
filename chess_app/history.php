<?php
require __DIR__ . '/config.php';
$user = require_login();

// フィルタパラメータ
$f_diff   = $_GET['difficulty'] ?? 'all';
$f_result = $_GET['result'] ?? 'all';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

if (!in_array($f_diff,   ['all','easy','medium','hard'], true)) $f_diff   = 'all';
if (!in_array($f_result, ['all','win','loss','draw','resign'], true)) $f_result = 'all';

// WHERE句構築
$where = ['user_id = ?'];
$params = [$user['id']];
if ($f_diff !== 'all')   { $where[] = 'difficulty = ?'; $params[] = $f_diff; }
if ($f_result !== 'all') { $where[] = 'result = ?';     $params[] = $f_result; }
$where_sql = 'WHERE ' . implode(' AND ', $where);

// 件数
$cnt_stmt = db()->prepare("SELECT COUNT(*) FROM games $where_sql");
$cnt_stmt->execute($params);
$total = (int)$cnt_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// データ取得
$sql = "SELECT id, difficulty, result, end_reason, total_moves, duration_sec, ended_at
        FROM games $where_sql ORDER BY ended_at DESC LIMIT $per_page OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$games = $stmt->fetchAll();

// 全体集計（フィルタ無視・ユーザー単位）
$agg = db()->prepare("SELECT
    COUNT(*) total,
    SUM(CASE WHEN result='win' THEN 1 ELSE 0 END) wins,
    SUM(CASE WHEN result='loss' THEN 1 ELSE 0 END) losses,
    SUM(CASE WHEN result='draw' THEN 1 ELSE 0 END) draws,
    SUM(CASE WHEN result='resign' THEN 1 ELSE 0 END) resigns
    FROM games WHERE user_id = ?");
$agg->execute([$user['id']]);
$stats = $agg->fetch() ?: ['total'=>0,'wins'=>0,'losses'=>0,'draws'=>0,'resigns'=>0];
$stats['total'] = (int)$stats['total'];
$win_rate = $stats['total'] > 0 ? round($stats['wins'] / $stats['total'] * 100, 1) : 0;

// 難易度別集計
$by_diff_stmt = db()->prepare("SELECT difficulty,
    COUNT(*) total,
    SUM(CASE WHEN result='win' THEN 1 ELSE 0 END) wins,
    SUM(CASE WHEN result='loss' THEN 1 ELSE 0 END) losses,
    SUM(CASE WHEN result IN('draw','resign') THEN 1 ELSE 0 END) draws
    FROM games WHERE user_id = ? GROUP BY difficulty");
$by_diff_stmt->execute([$user['id']]);
$by_diff = [];
foreach ($by_diff_stmt->fetchAll() as $r) $by_diff[$r['difficulty']] = $r;

$diff_label = ['easy' => '弱', 'medium' => '中', 'hard' => '強'];
$diff_class = ['easy' => 'lv-easy', 'medium' => 'lv-medium', 'hard' => 'lv-hard'];
$res_label  = ['win' => '勝ち', 'loss' => '負け', 'draw' => '引分', 'resign' => '投了'];
$res_class  = ['win' => 'r-win', 'loss' => 'r-loss', 'draw' => 'r-draw', 'resign' => 'r-resign'];
$reason_jp  = ['checkmate' => 'チェックメイト', 'stalemate' => 'ステイルメイト', 'draw' => '引分合意', 'threefold' => 'スリーフォールド', 'insufficient' => '駒不足', 'resign' => '投了', 'timeout' => '時間切れ'];

function qs(array $extra): string {
    $params = array_merge(['difficulty' => $_GET['difficulty'] ?? 'all', 'result' => $_GET['result'] ?? 'all', 'page' => $_GET['page'] ?? 1], $extra);
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="ja"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>対戦履歴 - チェスvsClaude</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; padding: 20px 16px; }
header { display: flex; justify-content: space-between; align-items: center; max-width: 960px; margin: 0 auto 18px; }
.who { font-size: 0.85em; color: #aaa; }
.who b { color: #e0c870; }
.navlinks a { color: #4fc3f7; text-decoration: none; font-size: 0.82em; margin-left: 12px; }
main { max-width: 960px; margin: 0 auto; }
h1 { color: #e0c870; font-size: 1.6em; margin-bottom: 18px; }
h2 { color: #aaa; font-size: 0.92em; margin: 22px 0 10px; }

.stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 8px; }
.stat-card { background: #16213e; border-radius: 8px; padding: 14px 12px; text-align: center; }
.stat-val { font-size: 1.6em; font-weight: bold; color: #e0c870; }
.stat-label { font-size: 0.72em; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }
.stat-card.win  .stat-val { color: #66bb6a; }
.stat-card.loss .stat-val { color: #ef5350; }
.stat-card.draw .stat-val { color: #888; }

.diffstats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 18px; }
.ds-card { background: #16213e; border-radius: 8px; padding: 12px 14px; }
.ds-title { font-size: 0.85em; margin-bottom: 6px; font-weight: bold; }
.ds-line { font-size: 0.8em; color: #aaa; }
.lv-easy   { color: #66bb6a; }
.lv-medium { color: #ffa726; }
.lv-hard   { color: #ef5350; }

.filters { background: #16213e; padding: 14px; border-radius: 8px; margin-bottom: 14px; }
.filter-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 8px; }
.filter-row:last-child { margin-bottom: 0; }
.filter-label { font-size: 0.75em; color: #888; text-transform: uppercase; letter-spacing: 1px; width: 60px; flex-shrink: 0; }
.fbtn { padding: 6px 14px; background: #1a1a2e; color: #aaa; border: 1px solid #2a3a5a; border-radius: 5px; text-decoration: none; font-size: 0.83em; cursor: pointer; }
.fbtn:hover { background: #2a3a5a; color: #eee; }
.fbtn.active { background: #e0c870; color: #1a1a2e; border-color: #e0c870; font-weight: bold; }
.fbtn.active.win    { background: #66bb6a; border-color: #66bb6a; }
.fbtn.active.loss   { background: #ef5350; border-color: #ef5350; color: #fff; }
.fbtn.active.draw   { background: #888;    border-color: #888;    color: #fff; }
.fbtn.active.resign { background: #ff9090; border-color: #ff9090; color: #fff; }

.list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.list-count { font-size: 0.85em; color: #888; }

table { width: 100%; border-collapse: collapse; background: #16213e; border-radius: 8px; overflow: hidden; }
th, td { padding: 9px 12px; text-align: left; font-size: 0.85em; border-bottom: 1px solid #1f2f4e; }
th { background: #0f1830; color: #888; font-weight: normal; font-size: 0.75em; text-transform: uppercase; letter-spacing: 1px; }
tr:last-child td { border-bottom: none; }
tr.row-link { cursor: pointer; transition: background 0.1s; }
tr.row-link:hover { background: #1f2f4e; }
.r-win    { color: #66bb6a; font-weight: bold; }
.r-loss   { color: #ef5350; font-weight: bold; }
.r-draw   { color: #888; font-weight: bold; }
.r-resign { color: #ff9090; font-weight: bold; }
.replay { color: #4fc3f7; text-decoration: none; }
.empty { padding: 40px; text-align: center; color: #555; background: #16213e; border-radius: 8px; }

.pagination { display: flex; gap: 6px; justify-content: center; margin-top: 16px; flex-wrap: wrap; }
.page-btn { padding: 6px 12px; background: #16213e; color: #aaa; border: 1px solid #2a3a5a; border-radius: 5px; text-decoration: none; font-size: 0.85em; }
.page-btn:hover { background: #1f2f4e; color: #eee; }
.page-btn.active { background: #e0c870; color: #1a1a2e; border-color: #e0c870; font-weight: bold; }
.page-btn.disabled { opacity: 0.3; pointer-events: none; }
.page-info { padding: 6px 4px; font-size: 0.8em; color: #666; }

@media (max-width: 700px) {
  .stats, .diffstats { grid-template-columns: repeat(2, 1fr); }
  th.hide-sm, td.hide-sm { display: none; }
  .filter-label { width: 100%; margin-bottom: 4px; }
}
</style></head><body>
<header>
  <div class="who">プレイヤー: <b><?= e($user['display_name']) ?></b></div>
  <div class="navlinks">
    <a href="index.php">対局</a>
    <a href="ranking.php">ランキング</a>
    <a href="logout.php">ログアウト</a>
  </div>
</header>
<main>
  <h1>📊 対戦履歴</h1>

  <div class="stats">
    <div class="stat-card"><div class="stat-val"><?= $stats['total'] ?></div><div class="stat-label">総対局</div></div>
    <div class="stat-card win"><div class="stat-val"><?= $stats['wins'] ?></div><div class="stat-label">勝</div></div>
    <div class="stat-card loss"><div class="stat-val"><?= $stats['losses'] ?></div><div class="stat-label">負</div></div>
    <div class="stat-card draw"><div class="stat-val"><?= $stats['draws'] + $stats['resigns'] ?></div><div class="stat-label">分・投</div></div>
    <div class="stat-card"><div class="stat-val"><?= $win_rate ?>%</div><div class="stat-label">勝率</div></div>
  </div>

  <h2>難易度別</h2>
  <div class="diffstats">
    <?php foreach (['easy','medium','hard'] as $d):
      $s = $by_diff[$d] ?? ['total'=>0,'wins'=>0,'losses'=>0,'draws'=>0];
      $wr = $s['total'] > 0 ? round($s['wins']/$s['total']*100, 1) : 0;
    ?>
    <div class="ds-card">
      <div class="ds-title"><span class="<?= $diff_class[$d] ?>"><?= $diff_label[$d] ?></span> （<?= (int)$s['total'] ?>戦）</div>
      <div class="ds-line">勝 <b><?= (int)$s['wins'] ?></b> / 負 <b><?= (int)$s['losses'] ?></b> / 分・投 <b><?= (int)$s['draws'] ?></b></div>
      <div class="ds-line">勝率 <b><?= $wr ?>%</b></div>
    </div>
    <?php endforeach; ?>
  </div>

  <h2>絞り込み</h2>
  <div class="filters">
    <div class="filter-row">
      <span class="filter-label">難易度</span>
      <?php foreach (['all' => '全て', 'easy' => '弱', 'medium' => '中', 'hard' => '強'] as $k => $v): ?>
        <a class="fbtn <?= $f_diff === $k ? 'active' : '' ?>" href="<?= e(qs(['difficulty' => $k, 'page' => 1])) ?>"><?= e($v) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="filter-row">
      <span class="filter-label">結果</span>
      <?php foreach (['all' => '全て', 'win' => '勝ち', 'loss' => '負け', 'draw' => '引分', 'resign' => '投了'] as $k => $v):
        $cls = $f_result === $k ? 'active ' . ($k !== 'all' ? $k : '') : '';
      ?>
        <a class="fbtn <?= e($cls) ?>" href="<?= e(qs(['result' => $k, 'page' => 1])) ?>"><?= e($v) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="list-header">
    <h2 style="margin:0;">対局一覧</h2>
    <span class="list-count">
      <?= $total ?>件
      <?php if ($total > 0): ?>（<?= $offset + 1 ?>〜<?= min($offset + $per_page, $total) ?>件目）<?php endif; ?>
    </span>
  </div>

  <?php if (empty($games)): ?>
    <div class="empty">
      <?= $stats['total'] === 0 ? 'まだ対戦記録がありません。' : 'この条件の対局はありません。' ?>
      <a href="index.php" style="color:#4fc3f7;">対局する</a>
    </div>
  <?php else: ?>
  <table>
    <thead><tr>
      <th>日時</th><th>難易度</th><th>結果</th><th>終了理由</th>
      <th class="hide-sm">手数</th><th class="hide-sm">所要</th><th>再生</th>
    </tr></thead>
    <tbody>
    <?php foreach ($games as $g): $rid = (int)$g['id']; ?>
      <tr class="row-link" data-href="replay.php?id=<?= $rid ?>">
        <td><?= e(date('Y-m-d H:i', strtotime($g['ended_at']))) ?></td>
        <td><span class="<?= $diff_class[$g['difficulty']] ?>"><?= $diff_label[$g['difficulty']] ?></span></td>
        <td class="<?= $res_class[$g['result']] ?>"><?= $res_label[$g['result']] ?></td>
        <td><?= e($reason_jp[$g['end_reason']] ?? $g['end_reason']) ?></td>
        <td class="hide-sm"><?= (int)$g['total_moves'] ?></td>
        <td class="hide-sm"><?= sprintf('%d:%02d', intdiv($g['duration_sec'], 60), $g['duration_sec'] % 60) ?></td>
        <td><a class="replay" href="replay.php?id=<?= $rid ?>">▶ 再生</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <a class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e(qs(['page' => 1])) ?>">⏮</a>
    <a class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e(qs(['page' => max(1, $page - 1)])) ?>">◀</a>
    <?php
    $start = max(1, $page - 2);
    $end   = min($total_pages, $start + 4);
    $start = max(1, $end - 4);
    for ($p = $start; $p <= $end; $p++):
    ?>
      <a class="page-btn <?= $p === $page ? 'active' : '' ?>" href="<?= e(qs(['page' => $p])) ?>"><?= $p ?></a>
    <?php endfor; ?>
    <a class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" href="<?= e(qs(['page' => min($total_pages, $page + 1)])) ?>">▶</a>
    <a class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" href="<?= e(qs(['page' => $total_pages])) ?>">⏭</a>
    <span class="page-info"><?= $page ?> / <?= $total_pages ?></span>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</main>
<script>
// 行クリックで再生画面へ（再生リンク自体のクリックは伝播停止せず通常通り）
document.querySelectorAll('tr.row-link').forEach(function(tr) {
    tr.addEventListener('click', function(e) {
        if (e.target.closest('a')) return;
        window.location.href = tr.dataset.href;
    });
});
</script>
</body></html>
