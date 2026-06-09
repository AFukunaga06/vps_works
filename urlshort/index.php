<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
csrf_token();

$action = $_POST['action'] ?? '';
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $url = trim($_POST['url'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $custom = trim($_POST['custom'] ?? '');
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'有効なURLを入力してください'];
    } else {
        if ($custom !== '') {
            if (!preg_match('/^[a-zA-Z0-9_-]{2,32}$/', $custom)) {
                $_SESSION['flash'] = ['type'=>'error','msg'=>'カスタムコードは英数字_-のみ、2〜32文字'];
                header('Location: ./'); exit;
            }
            $code = $custom;
            $exists = db()->prepare('SELECT 1 FROM short_urls WHERE code=?');
            $exists->execute([$code]);
            if ($exists->fetch()) {
                $_SESSION['flash'] = ['type'=>'error','msg'=>'そのコードは既に使われています'];
                header('Location: ./'); exit;
            }
        } else {
            do { $code = gen_code(6); $st = db()->prepare('SELECT 1 FROM short_urls WHERE code=?'); $st->execute([$code]); } while ($st->fetch());
        }
        db()->prepare('INSERT INTO short_urls (code,long_url,title) VALUES (?,?,?)')->execute([$code,$url,$title]);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'短縮URL: ' . SHORT_DOMAIN . '/' . $code];
        $_SESSION['last_code'] = $code;
    }
    header('Location: ./'); exit;
}
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('DELETE FROM short_urls WHERE id=?')->execute([$id]);
    $_SESSION['flash'] = ['type'=>'success','msg'=>'削除しました'];
    header('Location: ./'); exit;
}

$rows = db()->query('SELECT * FROM short_urls ORDER BY created_at DESC LIMIT 200')->fetchAll();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$lastCode = $_SESSION['last_code'] ?? null; unset($_SESSION['last_code']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>URL短縮ツール</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
  <h1>🔗 URL短縮ツール</h1>
  <div class="sub">短縮ドメイン: <code><?=h(SHORT_DOMAIN)?></code> <a href="logout.php" class="logout">ログアウト</a></div>
</header>

<?php if ($flash): ?><div class="flash flash-<?=h($flash['type'])?>"><?=h($flash['msg'])?></div><?php endif; ?>

<main class="container">
  <section class="card">
    <h2>新しい短縮URLを作成</h2>
    <form method="post">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <div class="row"><input type="url" name="url" placeholder="短縮したい長いURL（https://...）" required></div>
      <div class="row two">
        <input type="text" name="title" placeholder="メモ（任意）">
        <div class="custom-wrap">
          <span class="prefix"><?=h(SHORT_DOMAIN)?>/</span>
          <input type="text" name="custom" placeholder="カスタムコード（任意）" maxlength="32">
        </div>
      </div>
      <button type="submit" class="btn-primary">短縮URLを生成</button>
    </form>
  </section>

  <?php if ($lastCode): ?>
  <section class="card highlight">
    <h3>✨ 生成された短縮URL</h3>
    <div class="share">
      <input type="text" id="shareUrl" value="<?=h(SHORT_DOMAIN . '/' . $lastCode)?>" readonly onclick="this.select()">
      <button type="button" onclick="copyUrl()">📋 コピー</button>
    </div>
  </section>
  <?php endif; ?>

  <section>
    <h2>履歴 <span class="count"><?=count($rows)?>件</span></h2>
    <?php if (!$rows): ?><p class="empty">まだURLがありません</p><?php endif; ?>
    <table class="list">
      <thead><tr><th>短縮URL</th><th>元のURL</th><th>クリック</th><th>作成</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $short = SHORT_DOMAIN . '/' . $r['code']; ?>
        <tr>
          <td><a href="<?=h($short)?>" target="_blank" class="shortlink"><?=h($short)?></a><?php if ($r['title']): ?><div class="memo"><?=h($r['title'])?></div><?php endif; ?></td>
          <td class="longurl"><a href="<?=h($r['long_url'])?>" target="_blank"><?=h(mb_strimwidth($r['long_url'],0,60,'…'))?></a></td>
          <td class="num"><?=number_format($r['clicks'])?></td>
          <td class="date"><?=h($r['created_at'])?></td>
          <td>
            <a class="btn-mini" href="stats.php?id=<?=$r['id']?>">📊</a>
            <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="id" value="<?=$r['id']?>">
              <button class="btn-mini danger">×</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>
<script>
function copyUrl(){var i=document.getElementById('shareUrl');i.select();document.execCommand('copy');alert('コピーしました');}
</script>
</body></html>
