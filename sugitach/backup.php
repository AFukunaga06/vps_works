<?php
require_once __DIR__ . '/auth.php';

define('DB_BACKUP_DIR', __DIR__ . '/backup');
define('DB_HOST_CONF', 'localhost');
define('DB_NAME_CONF', 'church_attendance');
define('DB_USER_CONF', 'church_user');
define('DB_PASS_CONF', 'REDACTED_FOR_PUBLIC');

// バックアップディレクトリ確認
if (!is_dir(DB_BACKUP_DIR)) mkdir(DB_BACKUP_DIR, 0750, true);

$msg = '';

// ── POST: 既存ファイルのダウンロード ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download'])) {
    $name     = basename($_POST['dl_file'] ?? '');
    $filepath = DB_BACKUP_DIR . '/' . $name;
    if ($name && preg_match('/^church_attendance_[\w_]+\.sql$/', $name) && file_exists($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        readfile($filepath);
        exit;
    }
}

// ── POST: バックアップ実行＆ダウンロード ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup'])) {
    $filename  = 'church_attendance_' . date('Ymd_His') . '.sql';
    $filepath  = DB_BACKUP_DIR . '/' . $filename;

    $cmd = sprintf(
        'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines %s > %s 2>/dev/null',
        escapeshellarg(DB_HOST_CONF),
        escapeshellarg(DB_USER_CONF),
        escapeshellarg(DB_PASS_CONF),
        escapeshellarg(DB_NAME_CONF),
        escapeshellarg($filepath)
    );
    exec($cmd, $output, $ret);

    if ($ret === 0 && file_exists($filepath)) {
        // サーバー保存 + ブラウザダウンロード
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        readfile($filepath);
        exit;
    } else {
        $msg = 'バックアップに失敗しました。';
    }
}

// ── POST: SQLファイルをアップロードして復元 ───────────────
$restore_msg = '';
$restore_ok  = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore'])) {
    $file = $_FILES['sql_file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $restore_msg = 'ファイルのアップロードに失敗しました。';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'sql') {
        $restore_msg = '.sql ファイルのみ指定できます。';
    } else {
        // アップロードされたファイルから警告行を除去してクリーンなSQLを作成
        $tmpPath  = $file['tmp_name'];
        $cleanSql = '';
        $fh = fopen($tmpPath, 'r');
        while (($line = fgets($fh)) !== false) {
            if (strncmp($line, 'mysqldump:', 10) === 0) continue; // 警告行を除去
            $cleanSql .= $line;
        }
        fclose($fh);
        $cleanTmp = tempnam(sys_get_temp_dir(), 'restore_');
        file_put_contents($cleanTmp, $cleanSql);

        // 復元前に自動バックアップを取っておく
        $safeName = 'church_attendance_before_restore_' . date('Ymd_His') . '.sql';
        $safeFile = DB_BACKUP_DIR . '/' . $safeName;
        exec(sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction %s > %s 2>/dev/null',
            escapeshellarg(DB_HOST_CONF), escapeshellarg(DB_USER_CONF),
            escapeshellarg(DB_PASS_CONF), escapeshellarg(DB_NAME_CONF),
            escapeshellarg($safeFile)
        ));

        // 復元実行
        $cmd = sprintf(
            'mysql --host=%s --user=%s --password=%s %s < %s 2>&1',
            escapeshellarg(DB_HOST_CONF), escapeshellarg(DB_USER_CONF),
            escapeshellarg(DB_PASS_CONF), escapeshellarg(DB_NAME_CONF),
            escapeshellarg($cleanTmp)
        );
        exec($cmd, $out, $ret);
        @unlink($cleanTmp); // 一時ファイルを削除

        // 警告のみで実際のエラーがなければ成功とみなす
        $errors = array_filter($out, fn($l) => stripos($l, '[Warning]') === false);
        if ($ret === 0 || empty($errors)) {
            $restore_ok  = true;
            $restore_msg = '復元が完了しました。（復元前のデータは ' . $safeName . ' として保存済み）';
        } else {
            $restore_msg = '復元に失敗しました：' . implode(' ', $errors);
        }
    }
}

// ── バックアップ一覧 ──────────────────────────────────────
$files = glob(DB_BACKUP_DIR . '/*.sql');
usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>バックアップ - 通常日曜礼拝出席簿</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:system-ui,sans-serif;background:#f0f4f8;color:#333;}
header{background:#2c5f8a;color:#fff;padding:12px 20px;display:flex;align-items:center;gap:12px;}
header h1{font-size:1.05rem;}
nav{margin-left:auto;display:flex;gap:14px;}
nav a{color:#cde;text-decoration:none;font-size:.85rem;}
nav a:hover{color:#fff;}
.container{max-width:680px;margin:28px auto;padding:0 16px;}
.card{background:#fff;border-radius:8px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.1);margin-bottom:20px;}
.card h2{font-size:1rem;margin-bottom:16px;color:#2c5f8a;border-bottom:2px solid #e0eaf4;padding-bottom:8px;}
.btn-backup{padding:12px 32px;background:#c62828;color:#fff;border:none;border-radius:6px;font-size:1rem;cursor:pointer;font-weight:bold;}
.btn-backup:hover{background:#b71c1c;}
.msg-err{color:#c62828;margin-top:10px;font-weight:bold;}
table{width:100%;border-collapse:collapse;margin-top:14px;font-size:.88rem;}
th{background:#f0f4f8;padding:8px 10px;text-align:left;border-bottom:2px solid #dde;}
td{padding:8px 10px;border-bottom:1px solid #eee;}
.btn-dl-small{padding:4px 12px;background:#2c5f8a;color:#fff;border:none;border-radius:4px;font-size:.82rem;cursor:pointer;}
.auto-badge{display:inline-block;background:#e8f4e8;color:#2e7d32;border-radius:4px;font-size:.75rem;padding:2px 6px;margin-left:6px;}
.manual-badge{display:inline-block;background:#e3f2fd;color:#1565c0;border-radius:4px;font-size:.75rem;padding:2px 6px;margin-left:6px;}
.restore-badge{display:inline-block;background:#fff3e0;color:#e65100;border-radius:4px;font-size:.75rem;padding:2px 6px;margin-left:6px;}
.warn-box{background:#fff3e0;border:1px solid #ffb74d;border-radius:6px;padding:10px 14px;font-size:.85rem;color:#7c4700;margin-bottom:14px;line-height:1.6;}
.btn-restore{padding:10px 28px;background:#e65100;color:#fff;border:none;border-radius:6px;font-size:.95rem;cursor:pointer;font-weight:bold;}
.btn-restore:hover{background:#bf360c;}
.msg-ok{color:#2e7d32;margin-top:10px;font-weight:bold;}
input[type=file]{padding:6px;border:1px solid #ccc;border-radius:4px;font-size:.9rem;width:100%;margin-bottom:12px;}
</style>
</head>
<body>
<header>
  <h1>通常日曜礼拝出席簿</h1>
  <nav>
    <a href="index_01_tr.php">出欠入力</a>
    <a href="export_csv.php">CSV出力</a>
    <a href="logout.php">ログアウト</a>
  </nav>
</header>
<div class="container">

  <div class="card">
    <h2>手動バックアップ</h2>
    <form method="post">
      <button type="submit" name="backup" class="btn-backup">今すぐバックアップ</button>
    </form>
    <?php if ($msg): ?>
      <p class="msg-err"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>
  </div>

  <!-- 復元 -->
  <div class="card">
    <h2>データ復元（SQLファイル読み込み）</h2>
    <div class="warn-box">
      ⚠️ <strong>注意：</strong>復元を実行すると現在のデータはすべて上書きされます。<br>
      実行前に自動でバックアップを保存しますが、慎重に行ってください。
    </div>
    <form method="post" enctype="multipart/form-data"
          onsubmit="return confirm('現在のデータはすべて上書きされます。\n本当に復元しますか？')">
      <input type="file" name="sql_file" accept=".sql" required>
      <button type="submit" name="restore" class="btn-restore">復元を実行する</button>
    </form>
    <?php if ($restore_msg): ?>
      <p class="<?= $restore_ok ? 'msg-ok' : 'msg-err' ?>" style="margin-top:10px;">
        <?= htmlspecialchars($restore_msg) ?>
      </p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>バックアップ一覧</h2>
    <?php if (empty($files)): ?>
      <p style="color:#999;">バックアップファイルがありません。</p>
    <?php else: ?>
      <table>
        <thead><tr><th>ファイル名</th><th>サイズ</th><th>作成日時</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($files as $f):
            $name  = basename($f);
            $size  = round(filesize($f) / 1024, 1) . ' KB';
            $mtime = date('Y/m/d H:i', filemtime($f));
            $isAuto    = strpos($name, 'auto_') !== false;
            $isRestore = strpos($name, 'before_restore') !== false;
        ?>
          <tr>
            <td>
              <?= htmlspecialchars($name) ?>
              <?php if ($isRestore): ?>
                <span class="restore-badge">復元前</span>
              <?php elseif ($isAuto): ?>
                <span class="auto-badge">自動</span>
              <?php else: ?>
                <span class="manual-badge">手動</span>
              <?php endif; ?>
            </td>
            <td><?= $size ?></td>
            <td><?= $mtime ?></td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="dl_file" value="<?= htmlspecialchars($name) ?>">
                <button type="submit" name="download" class="btn-dl-small">DL</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
