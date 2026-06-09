<?php
require __DIR__ . '/config.php';
session_start();
csrf_token();

$action = $_POST['action'] ?? '';

if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $count = 0; $errors = [];
    if (!empty($_FILES['files']['name'][0])) {
        $names = $_FILES['files']['name'];
        for ($i = 0; $i < count($names); $i++) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) { $errors[] = $names[$i] . ': アップロード失敗'; continue; }
            $tmp = $_FILES['files']['tmp_name'][$i];
            $size = (int)$_FILES['files']['size'][$i];
            if ($size > MAX_BYTES) { $errors[] = $names[$i] . ': サイズ超過'; continue; }
            $mime = mime_content_type($tmp) ?: 'application/octet-stream';
            if (!isset($ALLOWED[$mime])) { $errors[] = $names[$i] . ': 許可されない形式 (' . $mime . ')'; continue; }
            $ext = $ALLOWED[$mime];
            $stored = bin2hex(random_bytes(8)) . '_' . date('Ymd') . '.' . $ext;
            $dest = UPLOAD_DIR . '/' . $stored;
            if (!move_uploaded_file($tmp, $dest)) { $errors[] = $names[$i] . ': 保存失敗'; continue; }
            $isImage = strpos($mime, 'image/') === 0 && $mime !== 'image/svg+xml';
            $thumb = null; $w = null; $h = null;
            if ($isImage) {
                $thumb = 'thumb_' . $stored . '.jpg';
                if (!make_thumb($dest, THUMB_DIR . '/' . $thumb)) $thumb = null;
                $info = @getimagesize($dest);
                if ($info) { $w = $info[0]; $h = $info[1]; }
            }
            db()->prepare('INSERT INTO files (orig_name,stored_name,thumb_name,mime,size_bytes,title,description,is_image,width,height) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$names[$i],$stored,$thumb,$mime,$size,$title,$desc, $isImage?1:0, $w, $h]);
            $count++;
        }
    }
    if ($count) $_SESSION['flash'] = ['type'=>'success','msg'=>$count . ' 件アップロードしました' . ($errors? ' (一部失敗:'.count($errors).'件)':'')];
    elseif ($errors) $_SESSION['flash'] = ['type'=>'error','msg'=>'失敗: ' . implode('; ', array_slice($errors,0,3))];
    else $_SESSION['flash'] = ['type'=>'error','msg'=>'ファイルが選択されていません'];
    header('Location: ./'); exit;
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $st = db()->prepare('SELECT stored_name,thumb_name FROM files WHERE id=?'); $st->execute([$id]); $r = $st->fetch();
    if ($r) {
        @unlink(UPLOAD_DIR . '/' . $r['stored_name']);
        if ($r['thumb_name']) @unlink(THUMB_DIR . '/' . $r['thumb_name']);
        db()->prepare('DELETE FROM files WHERE id=?')->execute([$id]);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'削除しました'];
    }
    header('Location: ./'); exit;
}

$mode = $_GET['view'] ?? 'grid';
$filter = $_GET['type'] ?? 'all';
$where = ''; $params = [];
if ($filter === 'image') { $where = 'WHERE is_image=1'; }
elseif ($filter === 'other') { $where = 'WHERE is_image=0'; }
$rows = db()->prepare("SELECT * FROM files $where ORDER BY uploaded_at DESC LIMIT 500");
$rows->execute($params); $rows = $rows->fetchAll();
$total = (int)db()->query('SELECT COUNT(*) c FROM files')->fetch()['c'];
$totalSize = (int)db()->query('SELECT COALESCE(SUM(size_bytes),0) s FROM files')->fetch()['s'];
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ファイルギャラリー</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
  <h1>🖼️ ファイルギャラリー</h1>
  <div class="meta-stats"><?=number_format($total)?> ファイル / <?=h(format_bytes($totalSize))?></div>
</header>

<?php if ($flash): ?><div class="flash flash-<?=h($flash['type'])?>"><?=h($flash['msg'])?></div><?php endif; ?>

<main class="container">
  <section class="upload-card">
    <h2>📤 アップロード</h2>
    <form method="post" enctype="multipart/form-data" id="upForm">
      <input type="hidden" name="action" value="upload">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <label class="drop" id="drop">
        <input type="file" name="files[]" id="fileInput" multiple accept="image/*,application/pdf,video/*,audio/*,.zip,.txt,.doc,.docx,.xls,.xlsx">
        <span class="drop-text">クリックまたはドラッグ&ドロップで選択<br><small>画像・PDF・動画・音声・Office・ZIP（最大20MB/ファイル）</small></span>
        <span class="drop-list" id="fileList"></span>
      </label>
      <div class="row two">
        <input type="text" name="title" placeholder="タイトル（任意）">
        <input type="text" name="description" placeholder="説明（任意）">
      </div>
      <button type="submit" class="btn-primary">アップロード</button>
    </form>
  </section>

  <nav class="toolbar">
    <div class="tabs">
      <a class="<?= $filter==='all'?'active':'' ?>" href="?type=all&view=<?=h($mode)?>">すべて</a>
      <a class="<?= $filter==='image'?'active':'' ?>" href="?type=image&view=<?=h($mode)?>">画像</a>
      <a class="<?= $filter==='other'?'active':'' ?>" href="?type=other&view=<?=h($mode)?>">その他</a>
    </div>
    <div class="view-switch">
      <a class="<?= $mode==='grid'?'active':'' ?>" href="?type=<?=h($filter)?>&view=grid">▦ グリッド</a>
      <a class="<?= $mode==='list'?'active':'' ?>" href="?type=<?=h($filter)?>&view=list">☰ リスト</a>
    </div>
  </nav>

  <?php if (!$rows): ?><p class="empty">まだファイルがありません</p><?php endif; ?>

  <?php if ($mode === 'grid'): ?>
    <div class="grid">
      <?php foreach ($rows as $f):
        $fileUrl = UPLOAD_URL . '/' . rawurlencode($f['stored_name']);
        $thumbUrl = $f['thumb_name'] ? THUMB_URL . '/' . rawurlencode($f['thumb_name']) : '';
        $isImg = (int)$f['is_image']; ?>
        <div class="tile">
          <a href="<?=h($fileUrl)?>" target="_blank" class="tile-link">
            <?php if ($isImg && $thumbUrl): ?>
              <img src="<?=h($thumbUrl)?>" alt="" loading="lazy">
            <?php else: ?>
              <div class="icon"><?php
                $emoji = '📄';
                if ($f['mime']==='application/pdf') $emoji='📕';
                elseif (strpos($f['mime'],'video/')===0) $emoji='🎬';
                elseif (strpos($f['mime'],'audio/')===0) $emoji='🎵';
                elseif ($f['mime']==='application/zip') $emoji='🗜️';
                elseif (strpos($f['mime'],'word')!==false) $emoji='📘';
                elseif (strpos($f['mime'],'excel')!==false || strpos($f['mime'],'spreadsheet')!==false) $emoji='📗';
                elseif ($f['mime']==='image/svg+xml') $emoji='🖼️';
                echo $emoji; ?></div>
              <div class="ext"><?=h(strtoupper(pathinfo($f['orig_name'], PATHINFO_EXTENSION)))?></div>
            <?php endif; ?>
          </a>
          <div class="tile-info">
            <div class="tile-title" title="<?=h($f['orig_name'])?>"><?=h(mb_strimwidth($f['title'] ?: $f['orig_name'], 0, 28, '…'))?></div>
            <div class="tile-meta"><?=h(format_bytes((int)$f['size_bytes']))?></div>
            <div class="tile-actions">
              <a href="<?=h($fileUrl)?>" download="<?=h($f['orig_name'])?>">⬇</a>
              <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                <input type="hidden" name="id" value="<?=$f['id']?>">
                <button class="x">×</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <table class="list">
      <thead><tr><th></th><th>ファイル名</th><th>サイズ</th><th>形式</th><th>アップロード</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $f):
        $fileUrl = UPLOAD_URL . '/' . rawurlencode($f['stored_name']);
        $thumbUrl = $f['thumb_name'] ? THUMB_URL . '/' . rawurlencode($f['thumb_name']) : ''; ?>
        <tr>
          <td><?php if ($thumbUrl): ?><img src="<?=h($thumbUrl)?>" class="row-thumb" alt=""><?php else: ?><span class="row-icon">📄</span><?php endif; ?></td>
          <td><a href="<?=h($fileUrl)?>" target="_blank"><?=h($f['orig_name'])?></a><?php if ($f['title']): ?><div class="memo"><?=h($f['title'])?></div><?php endif; ?></td>
          <td class="num"><?=h(format_bytes((int)$f['size_bytes']))?></td>
          <td class="mime"><?=h($f['mime'])?></td>
          <td class="date"><?=h($f['uploaded_at'])?></td>
          <td>
            <a href="<?=h($fileUrl)?>" download="<?=h($f['orig_name'])?>" class="btn-mini">⬇</a>
            <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="id" value="<?=$f['id']?>">
              <button class="btn-mini danger">×</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>

<script>
const drop = document.getElementById('drop');
const input = document.getElementById('fileInput');
const list = document.getElementById('fileList');
function refresh(){
  list.innerHTML='';
  for(let i=0;i<input.files.length;i++){
    const s=document.createElement('span');s.textContent=input.files[i].name;list.appendChild(s);
  }
}
input.addEventListener('change',refresh);
['dragover','dragenter'].forEach(e=>drop.addEventListener(e,ev=>{ev.preventDefault();drop.classList.add('hover');}));
['dragleave','drop'].forEach(e=>drop.addEventListener(e,ev=>{ev.preventDefault();drop.classList.remove('hover');}));
drop.addEventListener('drop',ev=>{input.files=ev.dataTransfer.files;refresh();});
</script>
</body></html>
