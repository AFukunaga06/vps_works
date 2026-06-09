<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// セッション開始
if (session_status() === PHP_SESSION_NONE) session_start();

$login_error = false;

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lawyer_login'])) {
    $pw = trim($_POST['password'] ?? '');
    if ($pw === LAWYER_PASSWORD || $pw === ADMIN_PASSWORD) {
        $_SESSION[LAWYER_SESSION_KEY] = true;
        header('Location: schedule.php');
        exit;
    }
    $login_error = true;
}

// ログアウト
if (isset($_GET['logout'])) {
    unset($_SESSION[LAWYER_SESSION_KEY]);
    header('Location: schedule.php');
    exit;
}

// 認証チェック（弁護士セッション or 管理者セッション）
$authenticated = !empty($_SESSION[LAWYER_SESSION_KEY]) || !empty($_SESSION[ADMIN_SESSION_KEY]);

// 未認証ならログインフォームを表示して終了
if (!$authenticated) {
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>スケジュール管理 - ログイン</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
body { background: #f8f9fa; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
</style>
</head>
<body>
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh">
  <div class="card shadow-sm" style="max-width:340px; width:100%">
    <div class="card-body p-4">
      <h1 class="h5 mb-1 text-center">弁護士スケジュール管理</h1>
      <p class="text-muted small text-center mb-4">パスワードを入力してください</p>
      <?php if ($login_error): ?>
      <div class="alert alert-danger py-2 small">パスワードが違います。</div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="lawyer_login" value="1">
        <div class="mb-3">
          <input type="password" name="password" class="form-control" placeholder="パスワード" autofocus required>
        </div>
        <div class="d-grid">
          <button type="submit" class="btn text-white" style="background:#3a7d5c">ログイン</button>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>
<?php
    exit;
}

// ===== 認証済み以降の処理 =====

$db  = get_db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 追加
    if (isset($_POST['add_date']) && $_POST['add_date'] && !empty($_POST['slots'])) {
        $date   = $_POST['add_date'];
        $reason = trim($_POST['reason'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $added = 0;
            foreach ($_POST['slots'] as $slot) {
                if (in_array($slot, TIME_SLOTS)) {
                    try {
                        $db->prepare("INSERT IGNORE INTO blocked_slots (blocked_date, start_time, reason) VALUES (?,?,?)")
                           ->execute([$date, $slot . ':00', $reason]);
                        $added++;
                    } catch (Exception $e) {}
                }
            }
            $msg = $added . '枠をブロックしました。';
        }
    }
    // 削除（1件）
    if (isset($_POST['del_id'])) {
        $db->prepare("DELETE FROM blocked_slots WHERE id=?")->execute([(int)$_POST['del_id']]);
        $msg = '削除しました。';
    }
    // 日付ごと一括削除
    if (isset($_POST['del_date'])) {
        $date = $_POST['del_date'];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $db->prepare("DELETE FROM blocked_slots WHERE blocked_date=?")->execute([$date]);
            $msg = $date . ' のブロックをすべて削除しました。';
        }
    }
    header('Location: schedule.php?msg=' . urlencode($msg));
    exit;
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

// 今後のブロック済み枠を日付ごとにまとめて取得
$rows = $db->query(
    "SELECT id, DATE_FORMAT(blocked_date,'%Y-%m-%d') AS bd,
            TIME_FORMAT(start_time,'%H:%i') AS st, reason
     FROM blocked_slots
     WHERE blocked_date >= CURDATE()
     ORDER BY blocked_date, start_time"
)->fetchAll();

$by_date = [];
foreach ($rows as $r) {
    $by_date[$r['bd']][] = $r;
}

$dow_jp = ['日','月','火','水','木','金','土'];
$is_admin = !empty($_SESSION[ADMIN_SESSION_KEY]);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> - 弁護士スケジュール管理</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
body { background: #f8f9fa; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.admin-nav { background: #2c5f44; }
.admin-nav .navbar-brand, .admin-nav .nav-link { color: #fff !important; }
.slot-check label { cursor: pointer; }
</style>
</head>
<body>
<nav class="navbar admin-nav mb-4">
  <div class="container">
    <span class="navbar-brand"><?= SITE_NAME ?> 管理</span>
    <div class="d-flex gap-3">
      <?php if ($is_admin): ?>
      <a href="index.php" class="nav-link">予約一覧</a>
      <a href="blocked.php" class="nav-link">休業日設定</a>
      <?php endif; ?>
      <a href="schedule.php" class="nav-link fw-bold">スケジュール管理</a>
      <a href="../reserve/index.php" class="nav-link">メインへ</a>
      <a href="schedule.php?logout=1" class="nav-link">ログアウト</a>
    </div>
  </div>
</nav>

<div class="container pb-5" style="max-width: 680px;">
  <h2 class="h5 mb-1">弁護士スケジュール管理</h2>
  <p class="text-muted small mb-3">弁護士の予定が入っている日時をブロックすると、その時間帯は「予定あり」として相談者が予約できなくなります。</p>

  <?php if ($msg): ?>
  <div class="alert alert-success py-2 small"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- 追加フォーム -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h3 class="h6 mb-3">予定をブロックする</h3>
      <form method="post">
        <div class="row g-2 mb-3">
          <div class="col-auto">
            <label class="form-label small">日付</label>
            <input type="date" name="add_date" id="add_date" class="form-control" required
                   min="<?= date('Y-m-d') ?>">
          </div>
          <div class="col">
            <label class="form-label small">メモ（任意）</label>
            <input type="text" name="reason" class="form-control" placeholder="例：打ち合わせ、研修">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small">ブロックする時間帯（複数選択可）</label>
          <div class="d-flex flex-wrap gap-2 slot-check">
            <?php foreach (TIME_SLOTS as $slot): ?>
            <div class="form-check form-check-inline border rounded px-3 py-2 bg-white">
              <input class="form-check-input" type="checkbox" name="slots[]"
                     value="<?= h($slot) ?>" id="slot_<?= str_replace(':','', $slot) ?>">
              <label class="form-check-label" for="slot_<?= str_replace(':','', $slot) ?>">
                <?= h($slot) ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_am">午前（〜12:00）</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_pm">午後（13:00〜）</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_all">全選択</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_none">解除</button>
          </div>
        </div>
        <button type="submit" class="btn text-white" style="background:#3a7d5c">ブロックする</button>
      </form>
    </div>
  </div>

  <!-- 設定済み一覧 -->
  <h3 class="h6 mb-2">設定済みのブロック枠（今日以降）</h3>
  <?php if (empty($by_date)): ?>
  <p class="text-muted small">設定済みのブロック枠はありません。</p>
  <?php else: ?>
  <?php foreach ($by_date as $date => $slots): ?>
  <?php
    $ts  = strtotime($date);
    $dow = $dow_jp[(int)date('w', $ts)];
    $label = date('Y年n月j日', $ts) . '（' . $dow . '）';
    $reason = $slots[0]['reason'] ?? '';
  ?>
  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <strong><?= h($label) ?></strong>
        <?php if ($reason): ?>
        <span class="badge bg-secondary"><?= h($reason) ?></span>
        <?php endif; ?>
        <form method="post" class="d-inline ms-2">
          <input type="hidden" name="del_date" value="<?= h($date) ?>">
          <button type="submit" class="btn btn-outline-danger btn-sm py-0"
                  onclick="return confirm('<?= h($date) ?> のブロックをすべて削除しますか？')">この日をすべて削除</button>
        </form>
      </div>
      <div class="d-flex flex-wrap gap-1">
        <?php foreach ($slots as $s): ?>
        <form method="post" class="d-inline">
          <input type="hidden" name="del_id" value="<?= $s['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger"
                  onclick="return confirm('<?= h($s['st']) ?> を削除しますか？')"
                  title="削除">
            <?= h($s['st']) ?> ×
          </button>
        </form>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
const AM = ['10:00','11:00','12:00'];
const PM = ['13:00','14:00','15:00','16:00','17:00'];
function setSlots(arr, val) {
  document.querySelectorAll('input[name="slots[]"]').forEach(cb => {
    if (arr === null || arr.includes(cb.value)) cb.checked = val;
  });
}
document.getElementById('btn_am').addEventListener('click', () => setSlots(AM, true));
document.getElementById('btn_pm').addEventListener('click', () => setSlots(PM, true));
document.getElementById('btn_all').addEventListener('click', () => setSlots(null, true));
document.getElementById('btn_none').addEventListener('click', () => setSlots(null, false));
</script>
</body>
</html>
