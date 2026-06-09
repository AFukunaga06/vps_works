<?php
// ===== セッション強化（先に設定 → session_start）=====
ini_set('session.use_strict_mode', '1');         // 変なセッションIDを拒否
ini_set('session.cookie_httponly', '1');         // JSからCookieを読めない
ini_set('session.cookie_samesite', 'Lax');       // CSRF緩和（通常用途向け）
// HTTPS運用なら下も推奨（HTTPだとログインできなくなるので注意）
// ini_set('session.cookie_secure', '1');

session_start();

// ===== 未ログインならログインへ =====
if (empty($_SESSION['login_ok'])) {
    header("Location: login.php");
    exit;
}

// ===== セッションID再生成は「最初の1回だけ」=====
if (empty($_SESSION['regen_done'])) {
    session_regenerate_id(true);
    $_SESSION['regen_done'] = 1;
}

// ===== 放置タイムアウト（例：30分）=====
$TIMEOUT = 30 * 60;
$now = time();
if (!empty($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > $TIMEOUT) {
    require_once __DIR__ . '/config.php';
    $pdo->exec("DELETE FROM active_session");
    $_SESSION = [];
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = $now;

// active_session の最終活動時刻を更新
require_once __DIR__ . '/config.php';
$pdo->prepare("UPDATE active_session SET last_activity = ? WHERE session_id = ?")
    ->execute([$now, session_id()]);

// ===== 設定保存 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lock_timeout_minutes'])) {
    $minutes = max(1, min(90, (int)$_POST['lock_timeout_minutes']));
    $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'lock_timeout_minutes'")
        ->execute([$minutes]);
    $return_page = $_POST['selected_page'] ?? 'index_tr02.php';
    header("Location: main02.php?page=" . urlencode($return_page) . "&saved=1");
    exit;
}
$s = $pdo->query("SELECT value FROM settings WHERE key_name = 'lock_timeout_minutes'")->fetch();
$lock_minutes = (int)($s ? $s['value'] : 5);

$meeting_groups = [
    [
        'title' => '礼拝会',
        'items' => [
            ['label' => '早朝礼拝', 'page' => 'index_sr02.php'],
            ['label' => '通常礼拝', 'page' => 'index_tr02.php'],
            ['label' => 'ライブ日曜礼拝', 'page' => 'index_live_r.php'],
            ['label' => '礼拝合計人数', 'page' => 'report_total.php'],
        ],
    ],
    [
        'title' => '祈祷会',
        'items' => [
            ['label' => '午前祈祷会', 'page' => 'index_amk.php'],
            ['label' => '午後祈祷会', 'page' => 'index_pmk.php'],
            ['label' => 'ライブ祈祷会', 'page' => 'index_livek.php'],
            ['label' => '祈祷会合計人数', 'page' => 'report_total02.php'],
        ],
    ],
    [
        'title' => 'その他',
        'items' => [
            ['label' => '朝祈会', 'page' => 'index_chy01.php'],
            ['label' => '朝祈会合計人数', 'page' => 'report_total_chy.php'],
            ['label' => 'その他集会', 'page' => 'index_sonota.php'],
            ['label' => '各集会総合計', 'page' => 'report_total_all.php'],
        ],
    ],
];

$utility_links = [
    ['label' => '取扱説明書', 'href' => 'manual.html', 'target' => '_blank', 'class' => 'is-accent'],
    ['label' => 'バックアップ', 'href' => 'backup.php', 'class' => 'is-secondary'],
    ['label' => '操作ログ', 'href' => 'operation_log.php', 'class' => 'is-secondary'],
    ['label' => 'ログアウト', 'href' => 'logout.php', 'class' => 'is-danger'],
];

$allowed_pages = [];
$page_labels = [];
foreach ($meeting_groups as $group) {
    foreach ($group['items'] as $item) {
        $allowed_pages[$item['page']] = true;
        $page_labels[$item['page']] = $item['label'];
    }
}

$default_page = 'index_tr02.php';
$requested_page = $_GET['page'] ?? $_POST['selected_page'] ?? $default_page;
$current_page = isset($allowed_pages[$requested_page]) ? $requested_page : $default_page;
$current_label = $page_labels[$current_page] ?? '通常礼拝';
$settings_saved = isset($_GET['saved']);

// ===== キャッシュ禁止（戻るボタンで見える事故防止）=====
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>集会出席状況</title>

<style>
*{ margin:0; padding:0; box-sizing:border-box; }

:root{
  --bg:#e6f4e8;
  --panel:#ffffff;
  --line:#d8eadc;
  --text:#153322;
  --muted:#567160;
  --nav:#1e3a5f;
  --nav-deep:#162d4a;
  --nav-soft:#cce0f5;
  --nav-line:#254d7a;
  --nav-accent:#7aadd4;
  --accent:#fff4c9;
  --danger-bg:#ffe4ec;
  --danger-border:#f4a7b9;
  --secondary-bg:#e4eeff;
  --secondary-border:#a7c4f4;
}

body{
  background:
    radial-gradient(circle at top right, rgba(223,240,255,.75), transparent 28%),
    linear-gradient(180deg, #eef8f0 0%, var(--bg) 100%);
  font-family: system-ui, -apple-system, "Segoe UI",
               "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif;
  padding:24px 16px;
  color:var(--text);
}

.shell{
  width:100%;
  max-width:1380px;
  margin:0 auto;
  display:flex;
  gap:20px;
  align-items:stretch;
}

.sidebar{
  width:230px;
  background:var(--nav);
  border:0;
  border-radius:0;
  padding:0;
  box-shadow:none;
  position:sticky;
  top:0;
  min-height:100vh;
  display:flex;
  flex-direction:column;
  overflow:auto;
}

.sidebar-brand{
  background:var(--nav-deep);
  padding:16px;
  color:#fff;
}

.sidebar-title{
  font-size:13px;
  font-weight:700;
  line-height:1.5;
  letter-spacing:0;
  margin-bottom:2px;
}

.sidebar-subtitle{
  font-size:11px;
  color:rgba(255,255,255,.78);
  line-height:1.5;
}

.nav-group + .nav-group{
  margin-top:0;
}

.nav-label{
  display:block;
  color:var(--nav-accent);
  font-size:10px;
  text-transform:uppercase;
  padding:10px 16px 4px;
  letter-spacing:1px;
  margin-bottom:0;
}

.nav-links{
  display:flex;
  flex-direction:column;
  gap:0;
}

.nav-link{
  display:block;
  text-decoration:none;
  color:var(--nav-soft);
  background:transparent;
  border:0;
  border-bottom:1px solid var(--nav-line);
  border-radius:0;
  padding:9px 16px;
  font-size:13px;
  font-weight:400;
  line-height:1.45;
  box-shadow:none;
  transition:background-color .15s ease, color .15s ease;
}

.nav-link:hover{
  background:var(--nav-line);
  color:#fff;
  text-decoration:none;
}

.nav-link.is-accent{
  color:var(--nav-soft);
}

.nav-link.is-secondary{
  color:var(--nav-soft);
}

.nav-link.is-danger{
  color:var(--nav-soft);
}

.nav-link.is-active{
  background:var(--nav-line);
  color:#fff;
  font-weight:400;
}

.sidebar-tools{
  margin-top:0;
  padding-top:0;
  border-top:0;
}

.sidebar-settings{
  margin-top:auto;
  padding-top:10px;
  padding-bottom:14px;
}

.settings-title{
  display:block;
  color:var(--nav-accent);
  font-size:10px;
  text-transform:uppercase;
  padding:10px 16px 4px;
  letter-spacing:1px;
  margin-bottom:0;
}

.main{
  flex:1;
  min-width:0;
  background:var(--panel);
  border:1px solid var(--line);
  border-radius:0;
  box-shadow:0 16px 34px rgba(16, 44, 26, .10);
  padding:18px;
  min-height:calc(100vh - 32px);
  display:flex;
  flex-direction:column;
}

.viewer-header{
  display:flex;
  justify-content:space-between;
  gap:18px;
  align-items:center;
  margin-bottom:16px;
  padding:8px 8px 12px;
  border-bottom:1px solid #e5efe7;
}

.viewer-title{
  font-size:28px;
  font-weight:900;
  letter-spacing:.08em;
  margin-bottom:4px;
}

.viewer-copy{
  font-size:14px;
  line-height:1.8;
  color:var(--muted);
}

.viewer-note{
  min-width:240px;
  background:linear-gradient(135deg, #eef3ff, #f7fbff);
  border:1px solid var(--line);
  border-radius:4px;
  padding:12px 16px;
  box-shadow:0 8px 18px rgba(13, 31, 18, .05);
}

.viewer-note strong{
  display:block;
  font-size:13px;
  margin-bottom:6px;
}

.viewer-note span{
  display:block;
  font-size:13px;
  color:var(--muted);
  line-height:1.7;
}

.action-link,
.save-btn{
  display:inline-block;
  text-decoration:none;
  border-radius:4px;
  padding:11px 16px;
  font-size:14px;
  font-weight:800;
  letter-spacing:.04em;
  transition:transform .15s ease, box-shadow .15s ease, background-color .15s ease, border-color .15s ease;
}

.actions{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
}

.action-link{
  color:var(--text);
  background:#fff;
  border:1px solid #d7e8db;
  box-shadow:0 4px 10px rgba(13, 31, 18, .05);
}

.action-link.is-accent{
  background:var(--accent);
  border-color:#ead88a;
}

.action-link.is-secondary{
  background:var(--secondary-bg);
  border-color:var(--secondary-border);
  color:#1a3a8b;
}

.action-link.is-danger{
  background:var(--danger-bg);
  border-color:var(--danger-border);
  color:#8b1a3a;
}

.settings-form{
  display:flex;
  align-items:flex-start;
  gap:8px;
  flex-wrap:wrap;
  flex-direction:column;
  padding:8px 16px 0;
}

.settings-label{
  font-size:11px;
  font-weight:400;
  color:var(--nav-soft);
}

.settings-input{
  width:100%;
  padding:9px 10px;
  border:1px solid var(--nav-line);
  border-radius:6px;
  font-size:13px;
  text-align:center;
  background:rgba(255,255,255,.06);
  color:#fff;
}

.settings-unit{
  font-size:11px;
  color:var(--nav-accent);
}

.save-btn{
  color:#fff;
  background:var(--nav-line);
  border:1px solid var(--nav-line);
  cursor:pointer;
  box-shadow:none;
  width:100%;
  text-align:center;
  border-radius:6px;
  padding:9px 12px;
  font-size:12px;
}

.save-btn:hover{
  color:#fff;
  background:#2d5b8f;
  border-color:#2d5b8f;
}

.viewer-status{
  margin-top:8px;
  font-size:12px;
  color:#245d39;
  background:#ecf8ef;
  border:1px solid #cfe5d5;
  border-radius:4px;
  padding:7px 12px;
  display:inline-block;
}

.frame-wrap{
  flex:1;
  min-height:720px;
  background:#f5fbf6;
  border:1px solid #dbe9df;
  border-radius:0;
  overflow:hidden;
}

.content-frame{
  width:100%;
  height:100%;
  min-height:720px;
  border:0;
  background:#fff;
}

@media (max-width: 920px){
  body{
    padding:12px 10px 18px;
  }

  .shell{
    flex-direction:column;
    gap:12px;
  }

  .sidebar{
    width:100%;
    position:sticky;
    top:8px;
    z-index:20;
    height:auto;
    max-height:46vh;
    overflow:auto;
    min-height:0;
    border-radius:0;
  }

  .main{
    width:100%;
    min-height:auto;
    padding:12px;
    border-radius:0;
  }

  .viewer-header{
    flex-direction:column;
    align-items:flex-start;
    gap:12px;
    padding:4px 4px 10px;
  }

  .viewer-note{
    width:100%;
  }

  .viewer-title{
    font-size:24px;
  }

  .frame-wrap,
  .content-frame{
    min-height:78vh;
  }
}

@media (max-width: 640px){
  body{
    padding:8px 8px 14px;
  }

  .sidebar{
    top:6px;
    max-height:42vh;
    border-radius:0;
  }

  .sidebar-subtitle{
    font-size:10px;
  }

  .nav-group + .nav-group,
  .sidebar-tools,
  .sidebar-settings{
    margin-top:0;
    padding-top:0;
  }

  .nav-links{
    display:flex;
    gap:0;
  }

  .nav-link{
    min-height:0;
    padding:8px 14px;
    font-size:13px;
    line-height:1.4;
    display:block;
    text-align:left;
  }

  .settings-form{
    gap:8px;
  }

  .settings-input,
  .save-btn{
    min-height:44px;
  }

  .main{
    padding:10px;
    border-radius:0;
  }

  .viewer-title{
    font-size:21px;
  }

  .viewer-copy{
    font-size:12px;
    line-height:1.6;
  }

  .viewer-note{
    display:none;
  }

  .viewer-status{
    font-size:11px;
    padding:6px 10px;
  }

  .frame-wrap{
    min-height:72vh;
    border-radius:0;
  }

  .content-frame{
    min-height:72vh;
  }
}
</style>

</head>
<body>

<div class="shell">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-title">集会出席状況</div>
      <p class="sidebar-subtitle">左メニューを固定し、選択画面を右側に表示します。</p>
    </div>

    <?php foreach ($meeting_groups as $group): ?>
      <div class="nav-group">
        <span class="nav-label"><?= htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8') ?></span>
        <div class="nav-links">
          <?php foreach ($group['items'] as $item): ?>
            <?php $is_active = $current_page === $item['page']; ?>
            <a
              href="?page=<?= urlencode($item['page']) ?>"
              class="nav-link<?= $is_active ? ' is-active' : '' ?>"
            >
              <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="sidebar-tools">
      <span class="nav-label">共通メニュー</span>
      <div class="nav-links">
        <?php foreach ($utility_links as $link): ?>
          <a
            href="<?= htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') ?>"
            class="nav-link <?= htmlspecialchars($link['class'], ENT_QUOTES, 'UTF-8') ?>"
            <?php if (!empty($link['target'])): ?>target="<?= htmlspecialchars($link['target'], ENT_QUOTES, 'UTF-8') ?>" rel="noopener noreferrer"<?php endif; ?>
          >
            <?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="sidebar-settings">
      <span class="settings-title">同時ログイン制限</span>
      <form method="post" class="settings-form">
        <input type="hidden" name="selected_page" value="<?= htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8') ?>">
        <label for="lock-timeout-minutes" class="settings-label">制限時間（1分から90分）</label>
        <input
          id="lock-timeout-minutes"
          type="number"
          name="lock_timeout_minutes"
          value="<?= $lock_minutes ?>"
          min="1"
          max="90"
          class="settings-input"
        >
        <span class="settings-unit">分</span>
        <button type="submit" class="save-btn">保存</button>
      </form>
    </div>
  </aside>

  <main class="main">
    <section class="viewer-header">
      <div>
        <h1 class="viewer-title"><?= htmlspecialchars($current_label, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="viewer-copy">
          左側サイドバーを残したまま、選択した画面を右側に表示しています。
        </p>
        <?php if ($settings_saved): ?>
          <div class="viewer-status">同時ログイン制限時間を保存しました。</div>
        <?php endif; ?>
      </div>
      <div class="viewer-note">
        <strong>表示中</strong>
        <span><?= htmlspecialchars($current_label, ENT_QUOTES, 'UTF-8') ?> の画面を右側に読み込んでいます。</span>
      </div>
    </section>

    <div class="frame-wrap">
      <iframe
        src="<?= htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8') ?>"
        title="<?= htmlspecialchars($current_label, ENT_QUOTES, 'UTF-8') ?>"
        class="content-frame"
      ></iframe>
    </div>
  </main>
</div>

</body>
</html>
