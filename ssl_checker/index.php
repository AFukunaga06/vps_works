<?php
require __DIR__ . '/db.php';

$rows = $pdo->query("
    SELECT *
      FROM ssl_domains
  ORDER BY (valid_to IS NULL) ASC,
           valid_to ASC, domain ASC
")->fetchAll();

$now  = time();
$msg  = $_GET['msg'] ?? '';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_date($dt) {
    if (!$dt) return '—';
    $ts = strtotime($dt);
    return $ts ? date('Y-m-d H:i', $ts) : '—';
}
function days_left($dt, $now) {
    if (!$dt) return null;
    $ts = strtotime($dt);
    return $ts ? (int)floor(($ts - $now) / 86400) : null;
}
function status_class($d) {
    if ($d === null) return 'unknown';
    if ($d < 0)   return 'expired';
    if ($d < 7)   return 'critical';
    if ($d < 30)  return 'warning';
    if ($d < 60)  return 'caution';
    return 'ok';
}
function status_label($d) {
    if ($d === null) return '未確認';
    if ($d < 0)   return '失効';
    if ($d < 7)   return '危険';
    if ($d < 30)  return '警告';
    if ($d < 60)  return '注意';
    return '正常';
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SSL証明書 期限チェッカー</title>
<style>
  :root { color-scheme: light dark; }
  * { box-sizing: border-box; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Hiragino Kaku Gothic ProN",
                 "Yu Gothic", Meiryo, sans-serif;
    margin: 0; padding: 24px;
    background: #f4f6fa; color: #222;
  }
  h1 { margin: 0 0 16px; font-size: 22px; }
  .container { max-width: 1100px; margin: 0 auto; }
  .card {
    background: #fff; border-radius: 10px; padding: 18px 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 18px;
  }
  form.add { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; }
  form.add label { display: flex; flex-direction: column; font-size: 12px; color: #555; }
  form.add input[type=text], form.add input[type=number] {
    border: 1px solid #ccd; border-radius: 6px; padding: 8px 10px; font-size: 14px;
  }
  form.add input[name=domain] { width: 280px; }
  form.add input[name=port]   { width: 90px; }
  form.add input[name=note]   { width: 240px; }
  button {
    border: 0; border-radius: 6px; padding: 8px 14px;
    background: #2c6cf6; color: #fff; font-size: 14px; cursor: pointer;
  }
  button.secondary { background: #6c757d; }
  button.danger    { background: #d9534f; }
  button:hover { filter: brightness(1.05); }

  table { width: 100%; border-collapse: collapse; font-size: 14px; background: #fff; }
  th, td { padding: 10px 12px; border-bottom: 1px solid #eef; text-align: left; vertical-align: middle; }
  th { background: #f7f9fc; font-size: 12px; color: #555; font-weight: 600; }
  td.num { text-align: right; font-variant-numeric: tabular-nums; }
  tr:last-child td { border-bottom: 0; }

  .badge {
    display: inline-block; padding: 2px 10px; border-radius: 999px;
    font-size: 12px; font-weight: 600;
  }
  .ok       { background: #e3f6e8; color: #1e7a36; }
  .caution  { background: #fff7d6; color: #8a6a00; }
  .warning  { background: #ffe6c7; color: #a35400; }
  .critical { background: #ffd6d6; color: #a31a1a; }
  .expired  { background: #444;     color: #fff; }
  .unknown  { background: #e6e8ee;  color: #555; }

  .msg {
    background: #eaf2ff; color: #1f4ea8;
    padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 14px;
  }
  .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
  .empty { padding: 30px; text-align: center; color: #888; }
  .domain { font-weight: 600; }
  .note { color: #888; font-size: 12px; }
  .err  { color: #b33; font-size: 12px; }
  form.inline { display: inline; }
</style>
</head>
<body>
<div class="container">
  <h1>SSL証明書 期限チェッカー</h1>

  <?php if ($msg !== ''): ?>
    <div class="msg"><?= h($msg) ?></div>
  <?php endif; ?>

  <div class="card">
    <form class="add" method="post" action="add.php">
      <label>ドメイン
        <input type="text" name="domain" placeholder="example.com" required>
      </label>
      <label>ポート
        <input type="number" name="port" value="443" min="1" max="65535">
      </label>
      <label>メモ
        <input type="text" name="note" placeholder="（任意）">
      </label>
      <button type="submit">追加してチェック</button>
    </form>
  </div>

  <div class="toolbar">
    <div>登録 <?= count($rows) ?> 件</div>
    <form method="post" action="check.php">
      <input type="hidden" name="all" value="1">
      <button type="submit" class="secondary">全件 再チェック</button>
    </form>
  </div>

  <div class="card" style="padding:0; overflow:hidden;">
    <?php if (!$rows): ?>
      <div class="empty">まだドメインが登録されていません。上のフォームから追加してください。</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>状態</th>
          <th>ドメイン</th>
          <th>発行者</th>
          <th>有効期限</th>
          <th class="num">残日数</th>
          <th>最終確認</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $d  = days_left($r['valid_to'] ?? null, $now);
        $cl = status_class($d);
        $lb = status_label($d);
      ?>
        <tr>
          <td><span class="badge <?= $cl ?>"><?= $lb ?></span></td>
          <td>
            <div class="domain"><?= h($r['domain']) ?><?= $r['port'] != 443 ? ':' . (int)$r['port'] : '' ?></div>
            <?php if (!empty($r['common_name']) && $r['common_name'] !== $r['domain']): ?>
              <div class="note">CN: <?= h($r['common_name']) ?></div>
            <?php endif; ?>
            <?php if (!empty($r['note'])): ?>
              <div class="note"><?= h($r['note']) ?></div>
            <?php endif; ?>
            <?php if (!empty($r['last_error'])): ?>
              <div class="err">⚠ <?= h($r['last_error']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= h($r['issuer'] ?? '—') ?></td>
          <td><?= fmt_date($r['valid_to'] ?? null) ?></td>
          <td class="num"><?= $d === null ? '—' : ($d . ' 日') ?></td>
          <td><?= fmt_date($r['last_checked'] ?? null) ?></td>
          <td>
            <form class="inline" method="post" action="check.php">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="secondary">再チェック</button>
            </form>
            <form class="inline" method="post" action="delete.php"
                  onsubmit="return confirm('「<?= h($r['domain']) ?>」を削除しますか？');">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="danger">削除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="card" style="font-size:12px; color:#666;">
    <strong>判定基準:</strong>
    残60日以上=正常／30〜59日=注意／7〜29日=警告／7日未満=危険／期限切れ=失効。
    PHP <?= PHP_VERSION ?> / SQLite で動作。データは <code>ssl_checker.sqlite</code> に保存。
  </div>
</div>
</body>
</html>
