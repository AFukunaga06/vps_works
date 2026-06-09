<?php
require __DIR__ . '/config.php';
session_start();
csrf_token();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $url = trim($_POST['url'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'有効なURLを入力してください'];
    } else {
        if ($title === '') {
            $title = parse_url($url, PHP_URL_HOST) ?: $url;
        }
        $favicon = '';
        $host = parse_url($url, PHP_URL_HOST);
        if ($host) {
            $favicon = 'https://www.google.com/s2/favicons?domain=' . urlencode($host) . '&sz=32';
        }
        $stmt = db()->prepare('INSERT INTO bookmarks (url,title,description,tags,favicon) VALUES (?,?,?,?,?)');
        $stmt->execute([$url,$title,$desc,$tags,$favicon]);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'ブックマークを追加しました'];
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'],'?'));
    exit;
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('DELETE FROM bookmarks WHERE id=?')->execute([$id]);
    $_SESSION['flash'] = ['type'=>'success','msg'=>'削除しました'];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'],'?'));
    exit;
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    db()->prepare('UPDATE bookmarks SET title=?, description=?, tags=? WHERE id=?')
        ->execute([$title,$desc,$tags,$id]);
    $_SESSION['flash'] = ['type'=>'success','msg'=>'更新しました'];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'],'?'));
    exit;
}

$q   = trim($_GET['q'] ?? '');
$tag = trim($_GET['tag'] ?? '');

$where = []; $params = [];
if ($q !== '') {
    $where[] = '(title LIKE ? OR url LIKE ? OR description LIKE ?)';
    $like = "%$q%"; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($tag !== '') {
    $where[] = 'tags LIKE ?';
    $params[] = '%' . $tag . '%';
}
$sql = 'SELECT * FROM bookmarks';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC LIMIT 500';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$bookmarks = $stmt->fetchAll();

$tagStmt = db()->query('SELECT tags FROM bookmarks WHERE tags IS NOT NULL AND tags<>""');
$allTags = [];
foreach ($tagStmt as $row) {
    foreach (preg_split('/[,\s]+/', $row['tags']) as $t) {
        $t = trim($t);
        if ($t !== '') $allTags[$t] = ($allTags[$t] ?? 0) + 1;
    }
}
arsort($allTags);

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ブックマーク管理</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
  <h1>📑 ブックマーク管理</h1>
  <form class="search" method="get">
    <input type="text" name="q" value="<?=h($q)?>" placeholder="🔍 検索（タイトル・URL・説明）">
    <?php if ($tag !== ''): ?><input type="hidden" name="tag" value="<?=h($tag)?>"><?php endif; ?>
    <button type="submit">検索</button>
    <?php if ($q !== '' || $tag !== ''): ?>
      <a href="./" class="clear">クリア</a>
    <?php endif; ?>
  </form>
</header>

<?php if ($flash): ?>
  <div class="flash flash-<?=h($flash['type'])?>"><?=h($flash['msg'])?></div>
<?php endif; ?>

<main class="container">
  <section class="add-form">
    <h2>＋ 新規追加</h2>
    <form method="post" class="card">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <div class="row">
        <input type="url" name="url" placeholder="https://example.com" required>
      </div>
      <div class="row two">
        <input type="text" name="title" placeholder="タイトル（空欄なら自動）">
        <input type="text" name="tags" placeholder="タグ（カンマ区切り：tech, news）">
      </div>
      <div class="row">
        <textarea name="description" rows="2" placeholder="メモ・説明（任意）"></textarea>
      </div>
      <div class="row">
        <button type="submit" class="btn-primary">追加</button>
      </div>
    </form>
  </section>

  <?php if ($allTags): ?>
  <section class="tags">
    <h3>タグ</h3>
    <div class="tag-list">
      <?php foreach ($allTags as $t => $c): ?>
        <a class="tag <?= $tag === $t ? 'active' : '' ?>" href="?tag=<?=urlencode($t)?>"><?=h($t)?> <span><?=$c?></span></a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="list">
    <h2>一覧 <span class="count"><?=count($bookmarks)?>件</span></h2>
    <?php if (!$bookmarks): ?>
      <p class="empty">まだブックマークがありません。上のフォームから追加してください。</p>
    <?php endif; ?>
    <?php foreach ($bookmarks as $b): ?>
      <article class="bm-card" id="bm-<?=$b['id']?>">
        <div class="bm-head">
          <?php if ($b['favicon']): ?><img src="<?=h($b['favicon'])?>" alt="" class="favicon" onerror="this.style.display='none'"><?php endif; ?>
          <a href="<?=h($b['url'])?>" target="_blank" rel="noopener" class="bm-title"><?=h($b['title'])?></a>
        </div>
        <div class="bm-url"><?=h($b['url'])?></div>
        <?php if ($b['description']): ?><div class="bm-desc"><?=nl2br(h($b['description']))?></div><?php endif; ?>
        <div class="bm-foot">
          <?php if ($b['tags']): ?>
            <div class="bm-tags">
              <?php foreach (array_filter(array_map('trim', preg_split('/[,\s]+/', $b['tags']))) as $t): ?>
                <a href="?tag=<?=urlencode($t)?>" class="tag-mini">#<?=h($t)?></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="bm-meta">
            <span><?=h($b['created_at'])?></span>
            <button type="button" class="link" onclick="toggleEdit(<?=$b['id']?>)">編集</button>
            <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="id" value="<?=$b['id']?>">
              <button type="submit" class="link danger">削除</button>
            </form>
          </div>
        </div>
        <form method="post" class="bm-edit" id="edit-<?=$b['id']?>" style="display:none">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="id" value="<?=$b['id']?>">
          <input type="text" name="title" value="<?=h($b['title'])?>" placeholder="タイトル">
          <input type="text" name="tags" value="<?=h($b['tags'])?>" placeholder="タグ">
          <textarea name="description" rows="2" placeholder="メモ"><?=h($b['description'])?></textarea>
          <button type="submit" class="btn-primary">保存</button>
        </form>
      </article>
    <?php endforeach; ?>
  </section>
</main>

<script>
function toggleEdit(id) {
  var el = document.getElementById('edit-' + id);
  el.style.display = (el.style.display === 'none' || !el.style.display) ? 'flex' : 'none';
}
</script>
</body>
</html>
