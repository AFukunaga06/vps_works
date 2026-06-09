<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/database.php';
$pdo = getDB();

$today = date('Y-m-d');
$date  = $_GET['date'] ?? $today;

// 確定解除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unconfirm') {
    $post_date = $_POST['date'] ?? $today;
    $pdo->prepare("DELETE FROM confirmed_dates WHERE attend_date=?")->execute([$post_date]);
    header("Location: index01_reihai.php?date=" . urlencode($post_date));
    exit;
}

// 保存 or 確定
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    in_array($_POST['action'] ?? '', ['save', 'confirm'])) {

    $post_date = $_POST['date'] ?? $today;

    $checked = $_POST['present'] ?? [];
    $all_ids = $pdo->query("SELECT id FROM roster")->fetchAll(\PDO::FETCH_COLUMN);
    $pdo->prepare("DELETE FROM attendance WHERE attendance_date=?")->execute([$post_date]);
    $stmt = $pdo->prepare(
        "INSERT INTO attendance (roster_id, attendance_date, is_present) VALUES (?, ?, ?)"
    );
    foreach ($all_ids as $mid) {
        $is_present = in_array((string)$mid, $checked) ? 1 : 0;
        $stmt->execute([(int)$mid, $post_date, $is_present]);
    }

    if ($_POST['action'] === 'confirm') {
        $pdo->prepare("INSERT IGNORE INTO confirmed_dates (attend_date) VALUES (?)")
            ->execute([$post_date]);
    }

    $param = ($_POST['action'] === 'confirm') ? '&confirmed=1' : '&saved=1';
    header("Location: index01_reihai.php?date=" . urlencode($post_date) . $param);
    exit;
}

// 確定済み判定
$chk = $pdo->prepare("SELECT confirmed_at FROM confirmed_dates WHERE attend_date=?");
$chk->execute([$date]);
$confirmed_row = $chk->fetch();
$is_confirmed  = (bool)$confirmed_row;

// 会員取得
$men   = $pdo->query("SELECT id,name FROM roster WHERE gender='male'   ORDER BY sort_order,id")->fetchAll();
$women = $pdo->query("SELECT id,name FROM roster WHERE gender='female' ORDER BY sort_order,id")->fetchAll();

// 出席者IDセット
$att = $pdo->prepare("SELECT roster_id FROM attendance WHERE attendance_date=? AND is_present=1");
$att->execute([$date]);
$present_ids = array_flip($att->fetchAll(\PDO::FETCH_COLUMN));

$men_present   = count(array_filter($men,   fn($m) => isset($present_ids[$m['id']])));
$women_present = count(array_filter($women, fn($m) => isset($present_ids[$m['id']])));

$saved     = isset($_GET['saved']);
$confirmed = isset($_GET['confirmed']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>集会出席状況 - 通常礼拝（礼拝）出欠入力</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #e6f4e8; color: #333; font-size: 15px; }
header { background: #2d6a3f; color: #fff; padding: 12px 20px; display: flex; align-items: center; gap: 10px; }
header h1 { font-size: 1.1rem; }
nav a { color: #cef0d4; text-decoration: none; font-size: .85rem; margin-left: 14px; }
nav a:hover { color: #fff; }
.container { max-width: 760px; margin: 16px auto; padding: 0 14px; }
.date-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.date-bar label { font-weight: bold; }
.date-bar input[type=date] { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: .95rem; }
.btn-nav   { padding: 6px 12px; background: #fff; border: 1px solid #bbb; border-radius: 4px; cursor: pointer; font-size: .9rem; }
.btn-nav:hover { background: #e8f4ea; border-color: #2d6a3f; }
.btn-today { padding: 6px 12px; background: #2d6a3f; border: none; border-radius: 4px; cursor: pointer; font-size: .85rem; color: #fff; }
.btn-today:hover { background: #1e4e2c; }
.badge { padding: 3px 10px; border-radius: 12px; font-size: .82rem; font-weight: bold; }
.badge-saved      { background: #c8e6c9; color: #1b5e20; }
.badge-confirmed  { background: #fff9c4; color: #7a5c00; }
.confirm-bar { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; flex-wrap: wrap; }
.confirm-bar.is-locked   { background: #fff8e1; border: 2px solid #f9c00d; }
.confirm-bar.is-unlocked { background: #f1f8e9; border: 1px solid #aed581; }
.confirm-info { flex: 1; font-size: .88rem; color: #555; }
.confirm-info strong { color: #333; }
.btn-confirm   { padding: 8px 20px; background: #f9c00d; color: #3a2a00; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: .9rem; }
.btn-confirm:hover { background: #e6b000; }
.btn-unconfirm { padding: 7px 16px; background: #fff; color: #777; border: 1px solid #ccc; border-radius: 5px; cursor: pointer; font-size: .85rem; }
.btn-unconfirm:hover { background: #f5f5f5; }
.columns { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 560px) { .columns { grid-template-columns: 1fr; } }
.section { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
.section-header { padding: 10px 14px; font-weight: bold; font-size: .95rem; display: flex; justify-content: space-between; align-items: center; }
.sec-men   .section-header { background: #d0e8ff; color: #1a5c99; }
.sec-women .section-header { background: #ffd0e8; color: #99195c; }
.section-header .count { font-size: .82rem; opacity: .85; }
.select-all { font-size: .78rem; padding: 3px 8px; border: 1px solid currentColor; border-radius: 4px; cursor: pointer; background: transparent; color: inherit; }
.member-list { padding: 6px 0; }
.member-row { display: flex; align-items: center; gap: 10px; padding: 7px 14px; }
.member-row.editable { cursor: pointer; }
.member-row.editable:hover { background: #f5f8fc; }
.member-row input[type=checkbox] { width: 18px; height: 18px; accent-color: #2d6a3f; flex-shrink: 0; }
.member-row.editable input { cursor: pointer; }
.member-row .name { flex: 1; }
.member-row.is-present .name { font-weight: bold; color: #1a5c99; }
.member-row.is-present { background: #f0f7ff; }
.footer-bar { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #ddd; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.total-info { font-size: .9rem; color: #555; }
.btn-save { background: #2d6a3f; color: #fff; border: none; padding: 10px 28px; border-radius: 6px; font-size: 1rem; cursor: pointer; }
.btn-save:hover { background: #1e4e2c; }
.locked-notice { font-size: .88rem; color: #999; }
</style>
</head>
<body>
<header>
  <h1>集会出席状況</h1>
  <nav>
    <a href="index01_reihai.php">通常礼拝</a>
    <a href="main02.php">メニューへ戻る</a>
    <a href="logout.php" style="margin-left:auto;color:#cef0d4;">ログアウト</a>
  </nav>
</header>

<form method="post" id="main-form">
  <input type="hidden" name="action" id="form-action" value="save">
  <input type="hidden" name="date"   value="<?= htmlspecialchars($date) ?>">

  <div class="container">
    <div class="date-bar">
      <label>日付：</label>
      <button type="button" class="btn-nav" onclick="moveDate(-1)">◀ 前日</button>
      <input type="date" id="date-input" value="<?= htmlspecialchars($date) ?>"
             onchange="goDate(this.value)">
      <button type="button" class="btn-nav" onclick="moveDate(1)">翌日 ▶</button>
      <button type="button" class="btn-today"
              onclick="goDate('<?= $today ?>')">今日</button>
      <?php if ($saved): ?>
        <span class="badge badge-saved">✓ 保存しました</span>
      <?php elseif ($confirmed): ?>
        <span class="badge badge-confirmed">🔒 確定しました</span>
      <?php endif; ?>
    </div>

    <?php if ($is_confirmed): ?>
      <div class="confirm-bar is-unlocked" style="border-color:#f9c00d;background:#fffde7;">
        <span style="font-size:1.2rem">✔</span>
        <div class="confirm-info">
          <strong><?= date('Y年n月j日', strtotime($date)) ?></strong> は確定済みです。
          （<?= date('Y/m/d H:i', strtotime($confirmed_row['confirmed_at'])) ?>）編集できます。
        </div>
        <form method="post" style="display:inline"
              onsubmit="return confirm('確定を解除しますか？')">
          <input type="hidden" name="action" value="unconfirm">
          <input type="hidden" name="date"   value="<?= htmlspecialchars($date) ?>">
          <button class="btn-unconfirm" type="submit">確定を解除</button>
        </form>
      </div>
    <?php else: ?>
      <div class="confirm-bar is-unlocked">
        <span style="font-size:1.2rem">🔓</span>
        <div class="confirm-info">
          <strong><?= date('Y年n月j日', strtotime($date)) ?></strong>
          — 出欠を入力して保存または確定してください。
        </div>
        <button type="button" class="btn-confirm" onclick="doConfirm()">✔ 確定する</button>
      </div>
    <?php endif; ?>

    <div class="columns">
      <div class="section sec-men">
        <div class="section-header">
          <span>男性</span>
          <span class="count">
            <span id="men-count"><?= $men_present ?></span>/<?= count($men) ?>名
              <button type="button" class="select-all" onclick="selectAll('men',false)">クリア</button>
          </span>
        </div>
        <div class="member-list" id="men-list">
          <?php foreach ($men as $m):
            $checked = isset($present_ids[$m['id']]);
          ?>
            <label class="member-row <?= $checked?'is-present':'' ?> editable">
              <input type="checkbox" name="present[]" value="<?= $m['id'] ?>"
                     <?= $checked      ? 'checked'  : '' ?>
                     onchange="updateRow(this)">
              <span class="name"><?= htmlspecialchars($m['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="section sec-women">
        <div class="section-header">
          <span>女性</span>
          <span class="count">
            <span id="women-count"><?= $women_present ?></span>/<?= count($women) ?>名
              <button type="button" class="select-all" onclick="selectAll('women',false)">クリア</button>
          </span>
        </div>
        <div class="member-list" id="women-list">
          <?php foreach ($women as $m):
            $checked = isset($present_ids[$m['id']]);
          ?>
            <label class="member-row <?= $checked?'is-present':'' ?> editable">
              <input type="checkbox" name="present[]" value="<?= $m['id'] ?>"
                     <?= $checked      ? 'checked'  : '' ?>
                     onchange="updateRow(this)">
              <span class="name"><?= htmlspecialchars($m['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="footer-bar">
    <span class="total-info">
      合計出席：<strong id="total-count"><?= $men_present + $women_present ?></strong>/<?= count($men) + count($women) ?>名
    </span>
      <button class="btn-save" type="submit">保存する</button>
  </div>
</form>

<script>
function isSunday(dateStr) {
  const d = new Date(dateStr + 'T00:00:00');
  return d.getDay() === 0;
}
function toJpDate(dateStr) {
  const d = new Date(dateStr + 'T00:00:00');
  return d.getFullYear() + '年' + (d.getMonth()+1) + '月' + d.getDate() + '日';
}
function goDate(dateStr) {
  if (!isSunday(dateStr)) {
    if (!confirm(toJpDate(dateStr) + 'は日曜日ではありません。宜しいですか？')) return;
  }
  location.href = 'index01_reihai.php?date=' + dateStr;
}
function doConfirm() {
  if (!confirm('<?= date('Y年n月j日', strtotime($date)) ?> の出欠を確定します。よろしいですか？')) return;
  document.getElementById('form-action').value = 'confirm';
  document.getElementById('main-form').submit();
}
function moveDate(delta) {
  const d = new Date(document.getElementById('date-input').value + 'T00:00:00');
  d.setDate(d.getDate() + delta);
  const y = d.getFullYear();
  const m = String(d.getMonth()+1).padStart(2,'0');
  const day = String(d.getDate()).padStart(2,'0');
  goDate(y + '-' + m + '-' + day);
}
function updateRow(cb) {
  cb.closest('.member-row').classList.toggle('is-present', cb.checked);
  updateCounts();
}
function selectAll(group, checked) {
  document.querySelectorAll('#'+group+'-list input[type=checkbox]').forEach(cb => {
    cb.checked = checked;
    cb.closest('.member-row').classList.toggle('is-present', checked);
  });
  updateCounts();
}
function updateCounts() {
  const mc = document.querySelectorAll('#men-list input:checked').length;
  const wc = document.querySelectorAll('#women-list input:checked').length;
  document.getElementById('men-count').textContent   = mc;
  document.getElementById('women-count').textContent = wc;
  document.getElementById('total-count').textContent = mc + wc;
}
</script>
</body>
</html>
