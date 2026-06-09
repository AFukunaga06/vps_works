<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/log_helper.php';
// log用PDO（後でDB定数定義後に初期化）
$_log_pdo = null;

// ── CSRF ──────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function verifyCsrf(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('不正なリクエストです（CSRFトークン不一致）。');
    }
}

// ── 設定 ──────────────────────────────────────────────────────────────
define('BACKUP_DIR',   '/var/backups/mysql/sugitach02');
define('DB_HOST_CONF', 'localhost');
define('DB_NAME_CONF', 'tubasa_meibo');
define('DB_USER_CONF', 'tubasa_user');
define('DB_PASS_CONF', 'REDACTED_FOR_PUBLIC');
define('KEEP_DAYS',    30);
try {
    $_log_pdo = new PDO('mysql:host='.DB_HOST_CONF.';dbname='.DB_NAME_CONF.';charset=utf8mb4',
        DB_USER_CONF, DB_PASS_CONF, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) { /* ログ失敗は無視 */ }
define('AUTO_LOG',     '/var/log/sugitach02_backup.log');

$dir_ok  = is_dir(BACKUP_DIR) || @mkdir(BACKUP_DIR, 0750, true);
$msg     = '';
$msg_ok  = false;

// ── POST: 既存ファイルのダウンロード ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download'])) {
    verifyCsrf();
    $name     = basename($_POST['dl_file'] ?? '');
    $filepath = BACKUP_DIR . '/' . $name;
    if ($name && preg_match('/^[a-zA-Z0-9_\-]+\.sql$/', $name) && is_file($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        readfile($filepath);
        exit;
    }
    $msg = 'ファイルが見つかりません。';
}

// ── POST: 手動バックアップ実行＆ダウンロード ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup'])) {
    verifyCsrf();
    if (!$dir_ok) {
        $msg = 'バックアップ保存先ディレクトリを作成できません。';
    } else {
        $filename = 'backup_' . date('Ymd_His') . '.sql';
        $filepath = BACKUP_DIR . '/' . $filename;
        $cmd = sprintf(
            'mysqldump --host=%s --user=%s --password=%s'
            . ' --single-transaction --routines --triggers --hex-blob %s > %s 2>/dev/null',
            escapeshellarg(DB_HOST_CONF), escapeshellarg(DB_USER_CONF),
            escapeshellarg(DB_PASS_CONF), escapeshellarg(DB_NAME_CONF),
            escapeshellarg($filepath)
        );
        exec($cmd, $output, $ret);
        if ($ret === 0 && is_file($filepath) && filesize($filepath) > 0) {
            // 世代管理：KEEP_DAYS 日以上古い手動バックアップを削除
            foreach (glob(BACKUP_DIR . '/backup_*.sql') ?: [] as $old) {
                if ($old !== $filepath && filemtime($old) < strtotime('-' . KEEP_DAYS . ' days')) {
                    @unlink($old);
                }
            }
            if ($_log_pdo) log_action($_log_pdo, '手動バックアップ', $filename . ' を作成・ダウンロード');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store');
            readfile($filepath);
            exit;
        } else {
            @unlink($filepath);
            $msg = 'バックアップに失敗しました。mysqldump コマンドが利用可能か確認してください。';
        }
    }
}

// ── POST: SQLファイルをアップロードして復元 ───────────────────────────
$restore_msg = '';
$restore_ok  = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore'])) {
    verifyCsrf();
    $file = $_FILES['sql_file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $restore_msg = 'ファイルのアップロードに失敗しました。';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'sql') {
        $restore_msg = '.sql ファイルのみ指定できます。';
    } else {
        // mysqldump の警告行を除去してクリーンな SQL を作成
        $fh       = fopen($file['tmp_name'], 'r');
        $cleanSql = '';
        while (($line = fgets($fh)) !== false) {
            if (strncmp($line, 'mysqldump:', 10) === 0) continue;
            $cleanSql .= $line;
        }
        fclose($fh);
        $cleanTmp = tempnam(sys_get_temp_dir(), 'restore_');
        file_put_contents($cleanTmp, $cleanSql);

        // 復元前に自動バックアップを保存
        $safeName = 'backup_before_restore_' . date('Ymd_His') . '.sql';
        exec(sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction --hex-blob %s > %s 2>/dev/null',
            escapeshellarg(DB_HOST_CONF), escapeshellarg(DB_USER_CONF),
            escapeshellarg(DB_PASS_CONF), escapeshellarg(DB_NAME_CONF),
            escapeshellarg(BACKUP_DIR . '/' . $safeName)
        ));

        // 復元実行
        $cmd = sprintf(
            'mysql --host=%s --user=%s --password=%s %s < %s 2>&1',
            escapeshellarg(DB_HOST_CONF), escapeshellarg(DB_USER_CONF),
            escapeshellarg(DB_PASS_CONF), escapeshellarg(DB_NAME_CONF),
            escapeshellarg($cleanTmp)
        );
        exec($cmd, $out, $ret);
        @unlink($cleanTmp);
        $errors = array_filter($out, fn($l) => stripos($l, '[Warning]') === false);
        if ($ret === 0 || empty($errors)) {
            $restore_ok  = true;
            $restore_msg = '復元が完了しました。（復元前のデータは ' . $safeName . ' として保存済み）';
            if ($_log_pdo) log_action($_log_pdo, 'DB復元', $file['name'] . ' から復元実行（事前バックアップ: ' . $safeName . '）');
        } else {
            $restore_msg = '復元に失敗しました：' . implode(' ', $errors);
        }
    }
}

// ── バックアップ一覧 ──────────────────────────────────────────────────
$files = glob(BACKUP_DIR . '/*.sql') ?: [];
usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

// ── 自動バックアップログ（最新10行）──────────────────────────────────
$auto_log_lines = [];
if (is_readable(AUTO_LOG)) {
    $all_lines      = file(AUTO_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $auto_log_lines = array_slice($all_lines, -10);
}
$last_auto = '';
foreach (array_reverse($auto_log_lines) as $line) {
    if (str_contains($line, '[OK]') || str_contains($line, '[NG]')) {
        $last_auto = $line;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>杉田教会名簿 - バックアップ</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #e6f4e8; color: #333; font-size: 15px; }
header {
  background: #2d6a3f; color: #fff;
  padding: 10px 20px;
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.site-title {
  font-size: 1rem; font-weight: bold; white-space: nowrap;
  color: #fff; text-decoration: none; margin-right: 6px;
}
nav { display: flex; align-items: center; gap: 2px; flex-wrap: wrap; flex: 1; }
nav a {
  color: #cef0d4; text-decoration: none; font-size: .82rem;
  padding: 5px 10px; border-radius: 4px; white-space: nowrap;
}
nav a:hover   { color: #fff; background: rgba(255,255,255,.18); }
nav a.active  { color: #fff; background: rgba(255,255,255,.25); font-weight: bold; }
nav a.logout  { margin-left: auto; color: #ffd0d0; }
nav a.logout:hover { background: rgba(255,80,80,.25); color: #fff; }
.container { max-width: 760px; margin: 24px auto; padding: 0 16px; }
.card {
  background: #fff; border-radius: 8px;
  padding: 22px 24px; box-shadow: 0 1px 4px rgba(0,0,0,.1); margin-bottom: 20px;
}
.card h2 {
  font-size: 1rem; margin-bottom: 14px; color: #2d6a3f;
  border-bottom: 2px solid #c8e6c9; padding-bottom: 8px;
}
.card p.note { font-size: .83rem; color: #555; margin-bottom: 14px; line-height: 1.7; }
.btn-backup {
  padding: 12px 32px; background: #2d6a3f; color: #fff;
  border: none; border-radius: 6px; font-size: 1rem; cursor: pointer; font-weight: bold;
}
.btn-backup:hover { background: #1e4e2c; }
.msg-err { color: #c62828; margin-top: 12px; font-weight: bold; font-size: .9rem; }
.msg-ok  { color: #2e7d32; margin-top: 12px; font-weight: bold; font-size: .9rem; }
.dir-warn {
  background: #ffebee; border: 1px solid #ef9a9a; border-radius: 6px;
  padding: 10px 14px; font-size: .85rem; color: #b71c1c; margin-bottom: 16px; line-height: 1.7;
}
.dir-warn code { background: #fce4ec; padding: 1px 5px; border-radius: 3px; font-size: .82rem; }
.warn-box {
  background: #fff3e0; border: 1px solid #ffb74d; border-radius: 6px;
  padding: 10px 14px; font-size: .85rem; color: #7c4700; margin-bottom: 14px; line-height: 1.6;
}
.info-box {
  background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 6px;
  padding: 10px 14px; font-size: .84rem; color: #1b5e20; margin-bottom: 14px; line-height: 1.7;
}
.info-box code { background: #c8e6c9; padding: 1px 5px; border-radius: 3px; font-size: .82rem; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: .88rem; }
th { background: #f1f8e9; padding: 8px 10px; text-align: left; border-bottom: 2px solid #c8e6c9; }
td { padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
.btn-dl {
  padding: 4px 12px; background: #1565c0; color: #fff;
  border: none; border-radius: 4px; font-size: .82rem; cursor: pointer;
}
.btn-dl:hover { background: #0d47a1; }
.btn-restore {
  padding: 10px 28px; background: #c62828; color: #fff;
  border: none; border-radius: 6px; font-size: .95rem; cursor: pointer; font-weight: bold;
}
.btn-restore:hover { background: #b71c1c; }
input[type=file] {
  padding: 6px; border: 1px solid #ccc; border-radius: 4px;
  font-size: .9rem; width: 100%; margin-bottom: 12px;
}
.badge {
  display: inline-block; border-radius: 4px;
  font-size: .72rem; padding: 2px 6px; margin-left: 5px; font-weight: bold;
}
.badge-auto    { background: #e8f5e9; color: #2e7d32; }
.badge-manual  { background: #e3f2fd; color: #1565c0; }
.badge-restore { background: #fff3e0; color: #e65100; }
.log-box {
  background: #1e1e2e; color: #cdd6f4; border-radius: 6px;
  padding: 12px 14px; font-family: 'Consolas','Monaco',monospace; font-size: .8rem;
  line-height: 1.7; max-height: 200px; overflow-y: auto; margin-top: 10px; white-space: pre-wrap;
}
.log-ok { color: #a6e3a1; }
.log-ng { color: #f38ba8; }
.status-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.status-chip {
  flex: 1; min-width: 140px; background: #f1f8e9; border-radius: 8px;
  padding: 10px 14px; font-size: .84rem;
}
.status-chip .lbl { color: #666; font-size: .78rem; margin-bottom: 3px; }
.status-chip .val { font-weight: bold; color: #2d6a3f; }
</style>
</head>
<body>

<header>
  <a class="site-title" href="main02.php">杉田教会名簿</a>
  <nav>
    <a href="main02.php">メニュー画面へ</a>
    <a href="backup.php" class="active">バックアップ</a>
    <a href="logout.php" class="logout">ログアウト</a>
  </nav>
</header>

<div class="container">

  <?php if (!$dir_ok): ?>
  <div class="dir-warn">
    ⚠ バックアップ保存先 <code><?= htmlspecialchars(BACKUP_DIR) ?></code> が存在しないか書き込みできません。<br>
    VPS で以下を実行してください：<br>
    <code>sudo mkdir -p <?= htmlspecialchars(BACKUP_DIR) ?></code><br>
    <code>sudo chown www-data:www-data <?= htmlspecialchars(BACKUP_DIR) ?></code>
  </div>
  <?php endif; ?>

  <!-- ── 自動バックアップ状況 ──────────────────────────────────── -->
  <div class="card">
    <h2>自動バックアップ（深夜 0:00）</h2>

    <div class="status-row">
      <div class="status-chip">
        <div class="lbl">スケジュール</div>
        <div class="val">毎日 0:00（cron）</div>
      </div>
      <div class="status-chip">
        <div class="lbl">保存先</div>
        <div class="val"><?= htmlspecialchars(BACKUP_DIR) ?></div>
      </div>
      <div class="status-chip">
        <div class="lbl">世代管理</div>
        <div class="val">自動バックアップ <?= KEEP_DAYS ?> 日分</div>
      </div>
    </div>

    <?php if ($last_auto): ?>
    <div class="lbl" style="font-size:.8rem;color:#666;margin-bottom:4px">最終実行ログ</div>
    <div class="log-box">
      <?php foreach ($auto_log_lines as $line):
        $cls = str_contains($line, '[OK]') ? 'log-ok' : (str_contains($line, '[NG]') ? 'log-ng' : '');
      ?>
        <span class="<?= $cls ?>"><?= htmlspecialchars($line) ?></span><?= "\n" ?>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="info-box">
      ℹ 自動バックアップはまだ実行されていません。または<br>
      ログファイル <code><?= htmlspecialchars(AUTO_LOG) ?></code> が存在しません。<br><br>
      <strong>cron 設定手順（VPS で実行）：</strong><br>
      <code>sudo cp /var/www/html/sugitach02/sugitach02_auto_backup.sh /usr/local/bin/</code><br>
      <code>sudo chmod +x /usr/local/bin/sugitach02_auto_backup.sh</code><br>
      <code>sudo crontab -e</code><br>
      → 以下を追加：<code>0 0 * * * /usr/local/bin/sugitach02_auto_backup.sh</code>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── 手動バックアップ ───────────────────────────────────── -->
  <div class="card">
    <h2>手動バックアップ</h2>
    <p class="note">
      「今すぐバックアップ」を押すと <code><?= htmlspecialchars(BACKUP_DIR) ?></code> に SQL ファイルを保存し、
      そのままダウンロードします。<br>
      ファイル名：<code>backup_YYYYMMDD_HHmmss.sql</code>　世代管理：最新 <?= KEEP_DAYS ?> 日分を自動保持
    </p>
    <form method="post" onsubmit="return confirm('今すぐバックアップを実行しますか？')">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <button type="submit" name="backup" class="btn-backup">今すぐバックアップ</button>
    </form>
    <?php if ($msg): ?>
      <p class="msg-err"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>
  </div>

  <!-- ── データ復元 ─────────────────────────────────────────── -->
  <div class="card">
    <h2>データ復元（SQL ファイル読み込み）</h2>
    <div class="warn-box">
      ⚠️ <strong>注意：</strong>復元を実行すると現在のデータはすべて上書きされます。<br>
      実行前に自動で安全バックアップを保存しますが、慎重に行ってください。
    </div>
    <form method="post" enctype="multipart/form-data"
          onsubmit="return confirm('現在のデータはすべて上書きされます。\n本当に復元しますか？')">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="file" name="sql_file" accept=".sql" required>
      <button type="submit" name="restore" class="btn-restore">復元を実行する</button>
    </form>
    <?php if ($restore_msg): ?>
      <p class="<?= $restore_ok ? 'msg-ok' : 'msg-err' ?>" style="margin-top:10px;">
        <?= htmlspecialchars($restore_msg) ?>
      </p>
    <?php endif; ?>
  </div>

  <!-- ── バックアップ一覧 ───────────────────────────────────── -->
  <div class="card">
    <h2>バックアップ一覧（<?= count($files) ?> 件）</h2>
    <?php if (empty($files)): ?>
      <p style="color:#999;font-size:.9rem;">バックアップファイルがありません。</p>
    <?php else: ?>
    <table>
      <thead>
        <tr><th>ファイル名</th><th>サイズ</th><th>作成日時</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($files as $f):
        $name      = basename($f);
        $size      = number_format(filesize($f) / 1024, 1) . ' KB';
        $mtime     = date('Y/m/d H:i', filemtime($f));
        $isAuto    = str_starts_with($name, 'auto_');
        $isRestore = str_contains($name, 'before_restore');
      ?>
        <tr>
          <td>
            <?= htmlspecialchars($name) ?>
            <?php if ($isRestore): ?>
              <span class="badge badge-restore">復元前</span>
            <?php elseif ($isAuto): ?>
              <span class="badge badge-auto">自動</span>
            <?php else: ?>
              <span class="badge badge-manual">手動</span>
            <?php endif; ?>
          </td>
          <td><?= $size ?></td>
          <td><?= $mtime ?></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
              <input type="hidden" name="dl_file"    value="<?= htmlspecialchars($name) ?>">
              <button type="submit" name="download" class="btn-dl">DL</button>
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
