<?php
require_once __DIR__ . '/auth.php';     // セッションチェック（未ログインはlogin.phpへ）
require_once __DIR__ . '/config.php';   // DB接続（$pdo）

// ── CSRF トークン ──────────────────────────────────────────────────────
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

// ── バックアップ設定 ────────────────────────────────────────────────────
define('BACKUP_DIR',     '/var/backups/mysql/tubasa_meibo');
define('BACKUP_DB_HOST', 'localhost');
define('BACKUP_DB_NAME', 'tubasa_meibo');
define('BACKUP_DB_USER', 'tubasa_user');
define('BACKUP_DB_PASS', 'REDACTED_FOR_PUBLIC');
define('KEEP_DAYS',      30);

// バックアップディレクトリ確認（なければ作成）
$dir_ok = is_dir(BACKUP_DIR) || @mkdir(BACKUP_DIR, 0750, true);

$msg    = '';
$msg_ok = false;

// ── POST: 既存ファイルのダウンロード ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download'])) {
    verifyCsrf();
    $name     = basename($_POST['dl_file'] ?? '');
    $filepath = BACKUP_DIR . '/' . $name;
    // ファイル名をホワイトリスト検証（パストラバーサル対策）
    if ($name && preg_match('/^[a-zA-Z0-9_\-]+\.sql$/', $name) && is_file($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        readfile($filepath);
        exit;
    }
    $msg = 'ファイルが見つかりません。';
}

// ── POST: バックアップ実行＆ダウンロード ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup'])) {
    verifyCsrf();

    if (!$dir_ok) {
        $msg = 'バックアップ保存先ディレクトリを作成できません。サーバー管理者に確認してください。';
    } else {
        $filename = 'backup_' . date('Ymd_Hi') . '.sql';
        $filepath = BACKUP_DIR . '/' . $filename;

        // コマンドはすべて escapeshellarg でエスケープ（コマンドインジェクション対策）
        $cmd = sprintf(
            'mysqldump --host=%s --user=%s --password=%s'
            . ' --single-transaction --routines --triggers %s > %s 2>/dev/null',
            escapeshellarg(BACKUP_DB_HOST),
            escapeshellarg(BACKUP_DB_USER),
            escapeshellarg(BACKUP_DB_PASS),
            escapeshellarg(BACKUP_DB_NAME),
            escapeshellarg($filepath)
        );
        exec($cmd, $output, $ret);

        if ($ret === 0 && is_file($filepath) && filesize($filepath) > 0) {
            // 手動バックアップの世代管理（KEEP_DAYS 日超を削除）
            foreach (glob(BACKUP_DIR . '/backup_*.sql') ?: [] as $old) {
                if ($old !== $filepath && filemtime($old) < strtotime('-' . KEEP_DAYS . ' days')) {
                    @unlink($old);
                }
            }
            // そのままダウンロード
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store');
            readfile($filepath);
            exit;
        } else {
            @unlink($filepath);
            $msg = 'バックアップに失敗しました。mysqldump が利用可能か確認してください。';
        }
    }
}

// ── POST: SQL ファイルをアップロードして復元 ──────────────────────────
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
        // mysqldump の警告行・DEFINER句を除去（MySQL8権限エラー対策）
        $fh = fopen($file['tmp_name'], 'r');
        $cleanSql = '';
        while (($line = fgets($fh)) !== false) {
            if (strncmp($line, 'mysqldump:', 10) === 0) continue;
            // DEFINER=`user`@`host` を除去
            $line = preg_replace('/\bDEFINER\s*=\s*`[^`]+`\s*@\s*`[^`]+`\s*/i', '', $line);
            $cleanSql .= $line;
        }
        fclose($fh);
        $cleanTmp = tempnam(sys_get_temp_dir(), 'restore_');
        file_put_contents($cleanTmp, $cleanSql);

        // 復元前に自動バックアップ
        $safeName = 'backup_before_restore_' . date('Ymd_His') . '.sql';
        $safeFile = BACKUP_DIR . '/' . $safeName;
        exec(sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction %s > %s 2>/dev/null',
            escapeshellarg(BACKUP_DB_HOST), escapeshellarg(BACKUP_DB_USER),
            escapeshellarg(BACKUP_DB_PASS), escapeshellarg(BACKUP_DB_NAME),
            escapeshellarg($safeFile)
        ));

        // 復元実行
        $cmd = sprintf(
            'mysql --host=%s --user=%s --password=%s %s < %s 2>&1',
            escapeshellarg(BACKUP_DB_HOST), escapeshellarg(BACKUP_DB_USER),
            escapeshellarg(BACKUP_DB_PASS), escapeshellarg(BACKUP_DB_NAME),
            escapeshellarg($cleanTmp)
        );
        exec($cmd, $out, $ret);
        @unlink($cleanTmp);

        $errors = array_filter($out, fn($l) => stripos($l, '[Warning]') === false);
        if ($ret === 0 || empty($errors)) {
            $restore_ok  = true;
            $restore_msg = '復元が完了しました。（復元前のデータは ' . $safeName . ' として保存済み）';
        } else {
            $restore_msg = '復元に失敗しました：' . implode(' ', $errors);
        }
    }
}

// ── バックアップ一覧 ─────────────────────────────────────────────────
$files = glob(BACKUP_DIR . '/*.sql') ?: [];
usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>つばさ名簿 - バックアップ</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #f0f4f8; color: #333; font-size: 15px; }
header { background: #2c5f8a; color: #fff; padding: 12px 20px; display: flex; align-items: center; gap: 10px; }
header h1 { font-size: 1.1rem; }
nav a { color: #cde; text-decoration: none; font-size: .85rem; margin-left: 14px; }
nav a:hover { color: #fff; }
.container { max-width: 720px; margin: 28px auto; padding: 0 16px; }
.card { background: #fff; border-radius: 8px; padding: 24px;
  box-shadow: 0 1px 4px rgba(0,0,0,.1); margin-bottom: 20px; }
.card h2 { font-size: 1rem; margin-bottom: 16px; color: #2c5f8a;
  border-bottom: 2px solid #e0eaf4; padding-bottom: 8px; }
.card p.note { font-size: .82rem; color: #666; margin-bottom: 14px; line-height: 1.6; }
.btn-backup { padding: 12px 32px; background: #c62828; color: #fff; border: none;
  border-radius: 6px; font-size: 1rem; cursor: pointer; font-weight: bold; }
.btn-backup:hover { background: #b71c1c; }
.msg-err { color: #c62828; margin-top: 12px; font-weight: bold; font-size: .9rem; }
.msg-ok  { color: #2e7d32; margin-top: 12px; font-weight: bold; font-size: .9rem; }
table { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: .88rem; }
th { background: #f0f4f8; padding: 8px 10px; text-align: left; border-bottom: 2px solid #dde; }
td { padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
.btn-dl { padding: 4px 12px; background: #2c5f8a; color: #fff; border: none;
  border-radius: 4px; font-size: .82rem; cursor: pointer; }
.btn-dl:hover { background: #1a4a70; }
.badge { display: inline-block; border-radius: 4px; font-size: .72rem;
  padding: 2px 6px; margin-left: 5px; font-weight: bold; }
.badge-auto    { background: #e8f4e8; color: #2e7d32; }
.badge-manual  { background: #e3f2fd; color: #1565c0; }
.badge-restore { background: #fff3e0; color: #e65100; }
.warn-box { background: #fff3e0; border: 1px solid #ffb74d; border-radius: 6px;
  padding: 10px 14px; font-size: .85rem; color: #7c4700; margin-bottom: 14px; line-height: 1.6; }
.btn-restore { padding: 10px 28px; background: #e65100; color: #fff; border: none;
  border-radius: 6px; font-size: .95rem; cursor: pointer; font-weight: bold; }
.btn-restore:hover { background: #bf360c; }
input[type=file] { padding: 6px; border: 1px solid #ccc; border-radius: 4px;
  font-size: .9rem; width: 100%; margin-bottom: 12px; }
.dir-warn { background: #ffebee; border: 1px solid #ef9a9a; border-radius: 6px;
  padding: 10px 14px; font-size: .85rem; color: #b71c1c; margin-bottom: 14px; line-height: 1.7; }
.dir-warn code { background: #fce4ec; padding: 1px 5px; border-radius: 3px; font-size: .83rem; }
</style>
</head>
<body>
<header>
  <h1>ABC商事(株)名簿</h1>
  <nav>
    <a href="index.php">出欠入力</a>
    <a href="members.php">会員管理</a>
    <a href="report.php">レポート</a>
    <a href="backup.php">バックアップ</a>
    <a href="logout.php" style="margin-left:auto;color:#ffd0d0;">ログアウト</a>
  </nav>
</header>

<div class="container">

  <?php if (!$dir_ok): ?>
    <div class="dir-warn">
      ⚠ バックアップ保存先 <code><?= htmlspecialchars(BACKUP_DIR) ?></code>
      が存在しないか書き込みできません。<br>
      サーバーで以下を実行してください：<br>
      <code>sudo mkdir -p <?= htmlspecialchars(BACKUP_DIR) ?></code><br>
      <code>sudo chown www-data:www-data <?= htmlspecialchars(BACKUP_DIR) ?></code>
    </div>
  <?php endif; ?>

  <!-- 手動バックアップ -->
  <div class="card">
    <h2>手動バックアップ</h2>
    <p class="note">
      「今すぐバックアップ」を押すと
      <code><?= htmlspecialchars(BACKUP_DIR) ?></code>
      に SQL ファイルを保存しつつ、そのままダウンロードします。<br>
      ファイル名：<code>backup_YYYYMMDD_HHMM.sql</code>
      世代管理：最新 <?= KEEP_DAYS ?> 日分を自動保持
    </p>
    <form method="post" onsubmit="return confirm('バックアップを実行しますか？')">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <button type="submit" name="backup" class="btn-backup">今すぐバックアップ</button>
    </form>
    <?php if ($msg): ?>
      <p class="msg-err"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>
  </div>

  <!-- データ復元 -->
  <div class="card">
    <h2>データ復元（SQL ファイル読み込み）</h2>
    <div class="warn-box">
      ⚠️ <strong>注意：</strong>復元を実行すると現在のデータはすべて上書きされます。<br>
      実行前に自動でバックアップを保存しますが、慎重に行ってください。
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

  <!-- バックアップ一覧 -->
  <div class="card">
    <h2>バックアップ一覧（<?= count($files) ?> 件）</h2>
    <?php if (empty($files)): ?>
      <p style="color:#999;">バックアップファイルがありません。</p>
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
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="dl_file"
                       value="<?= htmlspecialchars($name) ?>">
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
