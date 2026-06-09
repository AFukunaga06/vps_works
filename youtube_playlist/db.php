<?php
require __DIR__ . '/config.php';

$pdo = db();
$videos = $pdo->query('SELECT id, video_id, title, is_checked, sort_order, created_at FROM videos ORDER BY sort_order ASC, id ASC')->fetchAll();
$total = count($videos);
$checked = 0;
foreach ($videos as $v) if ($v['is_checked']) $checked++;

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DB一覧 - YouTube プレイリスト</title>
<style>
  * { box-sizing: border-box; }
  body {
    font-family: -apple-system, "Segoe UI", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
    margin: 0;
    background: #f5f5f5;
    color: #222;
  }
  .container { max-width: 1200px; margin: 0 auto; padding: 16px; }
  h1 { font-size: 1.4rem; margin: 0 0 12px; }
  .panel {
    background: #fff;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  }
  .summary { display: flex; gap: 16px; margin-bottom: 12px; flex-wrap: wrap; }
  .stat { background: #e3f2fd; padding: 8px 16px; border-radius: 4px; }
  .stat b { color: #1976d2; font-size: 1.2em; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #eee; }
  th { background: #f5f5f5; font-size: 13px; }
  td { font-size: 14px; vertical-align: middle; }
  td.id { color: #777; }
  td.vid { font-family: monospace; font-size: 12px; }
  td.title { word-break: break-all; }
  td.empty-title { color: #aaa; font-style: italic; }
  td.chk { text-align: center; }
  td.order { text-align: right; }
  td.created { color: #777; font-size: 12px; }
  .badge-on { background: #c8e6c9; color: #2e7d32; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
  .badge-off { background: #ffcdd2; color: #c62828; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
  a { color: #1976d2; text-decoration: none; }
  a:hover { text-decoration: underline; }
  .actions { margin-bottom: 12px; }
  .btn {
    display: inline-block;
    padding: 8px 16px;
    background: #1976d2;
    color: #fff !important;
    border-radius: 4px;
    text-decoration: none !important;
    font-size: 14px;
    margin-right: 8px;
  }
  .btn:hover { background: #1565c0; }
  .empty { color: #999; padding: 24px; text-align: center; }
  .yt-link { color: #d32f2f; font-size: 12px; margin-left: 6px; }
</style>
</head>
<body>
<div class="container">
  <h1>📊 DB一覧 - YouTube プレイリスト</h1>

  <div class="panel">
    <div class="actions">
      <a href="index.html" class="btn">← プレイリストへ戻る</a>
    </div>
    <div class="summary">
      <div class="stat">合計 <b><?= $total ?></b> 件</div>
      <div class="stat">チェックON <b><?= $checked ?></b> 件</div>
      <div class="stat">チェックOFF <b><?= $total - $checked ?></b> 件</div>
    </div>

    <?php if ($total === 0): ?>
      <div class="empty">データがありません</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>video_id</th>
            <th>タイトル</th>
            <th>チェック</th>
            <th>順序</th>
            <th>登録日時</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($videos as $v): ?>
          <tr>
            <td class="id"><?= h($v['id']) ?></td>
            <td class="vid">
              <?= h($v['video_id']) ?>
              <a class="yt-link" href="https://www.youtube.com/watch?v=<?= h($v['video_id']) ?>" target="_blank" rel="noopener">▶YT</a>
            </td>
            <td class="title <?= $v['title'] === '' ? 'empty-title' : '' ?>">
              <?= $v['title'] === '' ? '(空)' : h($v['title']) ?>
            </td>
            <td class="chk">
              <?php if ($v['is_checked']): ?>
                <span class="badge-on">ON</span>
              <?php else: ?>
                <span class="badge-off">OFF</span>
              <?php endif; ?>
            </td>
            <td class="order"><?= h($v['sort_order']) ?></td>
            <td class="created"><?= h($v['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div style="font-size: 12px; color: #666;">
      テーブル: <code>youtube_playlist_db.videos</code><br>
      最終更新: <?= date('Y-m-d H:i:s') ?>
    </div>
  </div>
</div>
</body>
</html>
