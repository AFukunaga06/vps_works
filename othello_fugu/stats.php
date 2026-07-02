<?php
require __DIR__ . '/config.php';

$totals = ['win' => 0, 'lose' => 0, 'draw' => 0];
$total_games = 0;
$recent = [];
$ranking = [];
$db_error = '';

try {
    $rows = db()->query("SELECT result, COUNT(*) AS c FROM games GROUP BY result")->fetchAll();
    foreach ($rows as $r) {
        $totals[$r['result']] = (int)$r['c'];
    }
    $total_games = array_sum($totals);

    // 難易度別 勝敗集計
    $by_level = [];
    foreach (['easy', 'medium', 'hard'] as $lv) {
        $by_level[$lv] = ['win' => 0, 'lose' => 0, 'draw' => 0, 'total' => 0];
    }
    $lrows = db()->query("SELECT level, result, COUNT(*) AS c FROM games GROUP BY level, result")->fetchAll();
    foreach ($lrows as $r) {
        if (!isset($by_level[$r['level']])) continue;
        $by_level[$r['level']][$r['result']] = (int)$r['c'];
        $by_level[$r['level']]['total'] += (int)$r['c'];
    }

    $recent = db()->query(
        "SELECT player_name, level, result, black, white, created_at
         FROM games ORDER BY created_at DESC LIMIT 30"
    )->fetchAll();

    // プレイヤー別 勝利数ランキング（上位10）
    $ranking = db()->query(
        "SELECT player_name,
                SUM(result='win')  AS wins,
                SUM(result='lose') AS loses,
                SUM(result='draw') AS draws,
                COUNT(*) AS total
         FROM games
         GROUP BY player_name
         ORDER BY wins DESC, total DESC
         LIMIT 10"
    )->fetchAll();
} catch (Throwable $ex) {
    $db_error = 'データの取得に失敗しました。';
}

$result_label = ['win' => '勝ち', 'lose' => '負け', 'draw' => '引分'];
$result_class = ['win' => 'win', 'lose' => 'lose', 'draw' => 'draw'];
$level_label = ['easy' => '初級', 'medium' => '中級', 'hard' => '上級'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🐡 fuguAI オセロ ― 成績</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: "Hiragino Sans","Yu Gothic",sans-serif; background:#0d3b2e; color:#fff;
         margin:0; padding:24px; }
  .wrap { max-width: 720px; margin: 0 auto; }
  h1 { font-size: 24px; margin: 0 0 4px; text-align:center; }
  .sub { color:#9fd9c4; font-size:13px; text-align:center; margin-bottom:20px; }
  a.btn { display:inline-block; background:#ffd34d; color:#0d3b2e; text-decoration:none;
          padding:9px 20px; border-radius:8px; font-weight:bold; }
  .center { text-align:center; margin-bottom:22px; }
  .cards { display:flex; gap:12px; justify-content:center; margin-bottom:26px; flex-wrap:wrap; }
  .card { background:rgba(255,255,255,.08); border-radius:10px; padding:14px 22px; min-width:96px; }
  .card .n { font-size:28px; font-weight:bold; }
  .card .l { font-size:12px; color:#9fd9c4; }
  .card.win .n { color:#7CFF9B; }
  .card.lose .n { color:#FF8F8F; }
  .card.draw .n { color:#ffe9a8; }
  h2 { font-size:17px; border-left:4px solid #ffd34d; padding-left:8px; margin:24px 0 10px; }
  table { width:100%; border-collapse:collapse; background:rgba(255,255,255,.05); border-radius:8px;
          overflow:hidden; }
  th, td { padding:8px 10px; text-align:center; font-size:14px; }
  th { background:rgba(255,255,255,.1); color:#9fd9c4; font-weight:normal; }
  tr:nth-child(even) td { background:rgba(255,255,255,.03); }
  .tag { padding:2px 10px; border-radius:999px; font-size:12px; font-weight:bold; }
  .tag.win { background:#1f6b3a; color:#7CFF9B; }
  .tag.lose { background:#6b1f1f; color:#FF8F8F; }
  .tag.draw { background:#6b5e1f; color:#ffe9a8; }
  .name { text-align:left; }
  .empty { text-align:center; color:#9fd9c4; padding:20px; }
  .rank { color:#ffd34d; font-weight:bold; }
</style>
</head>
<body>
<div class="wrap">
  <h1>🐡 fuguAI オセロ ― 成績</h1>
  <div class="sub">あなた(黒) vs fuguAI(白) の通算記録</div>
  <div class="center"><a class="btn" href="index.php">▶ ゲームに戻る</a></div>

  <?php if ($db_error): ?>
    <p class="empty"><?= e($db_error) ?></p>
  <?php else: ?>
    <div class="cards">
      <div class="card"><div class="n"><?= $total_games ?></div><div class="l">総対戦</div></div>
      <div class="card win"><div class="n"><?= $totals['win'] ?></div><div class="l">あなたの勝ち</div></div>
      <div class="card lose"><div class="n"><?= $totals['lose'] ?></div><div class="l">fuguの勝ち</div></div>
      <div class="card draw"><div class="n"><?= $totals['draw'] ?></div><div class="l">引き分け</div></div>
    </div>

    <h2>🎚 難易度別 成績</h2>
    <table>
      <tr><th>難易度</th><th>対戦数</th><th>勝</th><th>負</th><th>分</th><th>勝率</th></tr>
      <?php foreach (['easy', 'medium', 'hard'] as $lv):
        $d = $by_level[$lv];
        $rate = $d['total'] > 0 ? round($d['win'] * 100 / $d['total']) : 0; ?>
      <tr>
        <td><?= $level_label[$lv] ?><?= $lv === 'medium' ? '(fugu)' : '' ?></td>
        <td><?= $d['total'] ?></td>
        <td><?= $d['win'] ?></td>
        <td><?= $d['lose'] ?></td>
        <td><?= $d['draw'] ?></td>
        <td><?= $d['total'] > 0 ? $rate . '%' : '-' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <h2>🏆 勝利数ランキング</h2>
    <?php if ($ranking): ?>
    <table>
      <tr><th>順位</th><th class="name">プレイヤー</th><th>勝</th><th>負</th><th>分</th><th>対戦数</th></tr>
      <?php foreach ($ranking as $i => $r): ?>
      <tr>
        <td class="rank"><?= $i + 1 ?></td>
        <td class="name"><?= e($r['player_name']) ?></td>
        <td><?= (int)$r['wins'] ?></td>
        <td><?= (int)$r['loses'] ?></td>
        <td><?= (int)$r['draws'] ?></td>
        <td><?= (int)$r['total'] ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
      <p class="empty">まだ対戦記録がありません。</p>
    <?php endif; ?>

    <h2>🕒 最近の対戦</h2>
    <?php if ($recent): ?>
    <table>
      <tr><th class="name">プレイヤー</th><th>難易度</th><th>結果</th><th>石数(黒-白)</th><th>日時</th></tr>
      <?php foreach ($recent as $g): ?>
      <tr>
        <td class="name"><?= e($g['player_name']) ?></td>
        <td><?= $level_label[$g['level']] ?? e($g['level']) ?></td>
        <td><span class="tag <?= $result_class[$g['result']] ?>"><?= $result_label[$g['result']] ?></span></td>
        <td><?= (int)$g['black'] ?> - <?= (int)$g['white'] ?></td>
        <td><?= e(date('n/j H:i', strtotime($g['created_at']))) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
      <p class="empty">まだ対戦記録がありません。最初の一局を遊んでみましょう！</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
