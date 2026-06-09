<?php
require __DIR__ . '/config.php';
$user = require_login();

$difficulty = $_GET['difficulty'] ?? 'all';
$diff_filter = isset(DIFFICULTY_MODELS[$difficulty]) ? $difficulty : 'all';

if ($diff_filter === 'all') {
    $sql = "SELECT u.id, u.display_name, u.username,
                   COUNT(*) AS total,
                   SUM(CASE WHEN g.result='win' THEN 1 ELSE 0 END) AS wins,
                   SUM(CASE WHEN g.result='loss' THEN 1 ELSE 0 END) AS losses,
                   SUM(CASE WHEN g.result IN('draw','resign') THEN 1 ELSE 0 END) AS draws
            FROM games g JOIN users u ON u.id = g.user_id
            GROUP BY u.id, u.display_name, u.username
            HAVING total >= 1
            ORDER BY (wins * 100.0 / total) DESC, wins DESC, total DESC
            LIMIT 50";
    $rows = db()->query($sql)->fetchAll();
} else {
    $stmt = db()->prepare("SELECT u.id, u.display_name, u.username,
                   COUNT(*) AS total,
                   SUM(CASE WHEN g.result='win' THEN 1 ELSE 0 END) AS wins,
                   SUM(CASE WHEN g.result='loss' THEN 1 ELSE 0 END) AS losses,
                   SUM(CASE WHEN g.result IN('draw','resign') THEN 1 ELSE 0 END) AS draws
            FROM games g JOIN users u ON u.id = g.user_id
            WHERE g.difficulty = ?
            GROUP BY u.id, u.display_name, u.username
            HAVING total >= 1
            ORDER BY (wins * 100.0 / total) DESC, wins DESC, total DESC
            LIMIT 50");
    $stmt->execute([$diff_filter]);
    $rows = $stmt->fetchAll();
}

$diff_label = ['easy' => '弱', 'medium' => '中', 'hard' => '強'];
?>
<!DOCTYPE html>
<html lang="ja"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ランキング - チェスvsClaude</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; padding: 20px 16px; }
header { display: flex; justify-content: space-between; align-items: center; max-width: 800px; margin: 0 auto 18px; }
.who { font-size: 0.85em; color: #aaa; }
.who b { color: #e0c870; }
.navlinks a { color: #4fc3f7; text-decoration: none; font-size: 0.82em; margin-left: 12px; }
main { max-width: 800px; margin: 0 auto; }
h1 { color: #e0c870; font-size: 1.6em; margin-bottom: 12px; }
.tabs { display: flex; gap: 8px; margin-bottom: 16px; }
.tab { padding: 7px 14px; background: #16213e; color: #aaa; border-radius: 6px; text-decoration: none; font-size: 0.85em; }
.tab.active { background: #e0c870; color: #1a1a2e; font-weight: bold; }
table { width: 100%; border-collapse: collapse; background: #16213e; border-radius: 8px; overflow: hidden; }
th, td { padding: 11px 12px; text-align: left; font-size: 0.88em; border-bottom: 1px solid #1f2f4e; }
th { background: #0f1830; color: #888; font-weight: normal; font-size: 0.75em; text-transform: uppercase; letter-spacing: 1px; }
tr:last-child td { border-bottom: none; }
.rank { font-weight: bold; color: #888; width: 50px; }
.rank-1 { color: #ffd700; }
.rank-2 { color: #c0c0c0; }
.rank-3 { color: #cd7f32; }
.me { background: rgba(224, 200, 112, 0.08); }
.empty { padding: 40px; text-align: center; color: #555; }
</style></head><body>
<header>
  <div class="who">プレイヤー: <b><?= e($user['display_name']) ?></b></div>
  <div class="navlinks">
    <a href="index.php">対局</a>
    <a href="history.php">履歴</a>
    <a href="logout.php">ログアウト</a>
  </div>
</header>
<main>
  <h1>🏆 ランキング（勝率順）</h1>
  <div class="tabs">
    <a class="tab <?= $diff_filter === 'all' ? 'active' : '' ?>" href="?difficulty=all">全難易度</a>
    <a class="tab <?= $diff_filter === 'easy' ? 'active' : '' ?>" href="?difficulty=easy">弱</a>
    <a class="tab <?= $diff_filter === 'medium' ? 'active' : '' ?>" href="?difficulty=medium">中</a>
    <a class="tab <?= $diff_filter === 'hard' ? 'active' : '' ?>" href="?difficulty=hard">強</a>
  </div>
  <?php if (empty($rows)): ?>
    <div class="empty">まだ対局データがありません</div>
  <?php else: ?>
  <table>
    <thead><tr><th>順位</th><th>プレイヤー</th><th>勝率</th><th>勝</th><th>負</th><th>分・投</th><th>計</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r):
      $rank = $i + 1;
      $wr = $r['total'] > 0 ? round($r['wins']/$r['total']*100, 1) : 0;
      $is_me = (int)$r['id'] === $user['id'];
    ?>
      <tr class="<?= $is_me ? 'me' : '' ?>">
        <td class="rank rank-<?= $rank <= 3 ? $rank : 'x' ?>"><?= $rank ?></td>
        <td><?= e($r['display_name']) ?><?= $is_me ? ' <span style="color:#e0c870;font-size:0.75em;">← you</span>' : '' ?></td>
        <td><b><?= $wr ?>%</b></td>
        <td style="color:#66bb6a;"><?= (int)$r['wins'] ?></td>
        <td style="color:#ef5350;"><?= (int)$r['losses'] ?></td>
        <td style="color:#888;"><?= (int)$r['draws'] ?></td>
        <td><?= (int)$r['total'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</main>
</body></html>
