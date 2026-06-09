<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';
$id = (int)($_GET['id'] ?? 0);
$row = db()->prepare('SELECT * FROM short_urls WHERE id=?'); $row->execute([$id]); $r = $row->fetch();
if (!$r) { http_response_code(404); exit('Not Found'); }
$logs = db()->prepare('SELECT * FROM click_log WHERE short_id=? ORDER BY accessed_at DESC LIMIT 100');
$logs->execute([$id]);
$logs = $logs->fetchAll();
?>
<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>クリック統計</title><link rel="stylesheet" href="style.css"></head>
<body>
<header class="topbar"><h1>📊 クリック統計</h1><a href="./" class="back">← 戻る</a> <a href="logout.php" class="logout">ログアウト</a></header>
<main class="container">
<section class="card">
<div><strong>短縮URL:</strong> <a href="<?=h(SHORT_DOMAIN.'/'.$r['code'])?>" target="_blank"><?=h(SHORT_DOMAIN.'/'.$r['code'])?></a></div>
<div><strong>転送先:</strong> <a href="<?=h($r['long_url'])?>" target="_blank"><?=h($r['long_url'])?></a></div>
<div><strong>合計クリック:</strong> <?=number_format($r['clicks'])?></div>
<div><strong>作成日時:</strong> <?=h($r['created_at'])?></div>
<div><strong>最終アクセス:</strong> <?=h($r['last_access'] ?? '—')?></div>
</section>
<section><h2>最近のアクセス（最大100件）</h2>
<table class="list"><thead><tr><th>日時</th><th>IP</th><th>リファラ</th><th>UA</th></tr></thead><tbody>
<?php foreach ($logs as $l): ?>
<tr><td class="date"><?=h($l['accessed_at'])?></td><td><?=h($l['ip'])?></td><td><?=h(mb_strimwidth($l['referer']??'',0,40,'…'))?></td><td><?=h(mb_strimwidth($l['user_agent']??'',0,40,'…'))?></td></tr>
<?php endforeach; if (!$logs): ?><tr><td colspan="4" class="empty">まだアクセスがありません</td></tr><?php endif; ?>
</tbody></table>
</section>
</main></body></html>
