<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/log_helper.php';

$today = date('Y-m-d');
$date  = $_GET['date'] ?? $today;

// CSRF トークン生成
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// 削除パスワード
define('DEL_PASSWORD', 'REDACTED_FOR_PUBLIC');

$add_error   = '';
$show_modal  = false;
$modal_name  = '';
$modal_kana  = '';
$modal_group = '';


// ── 水曜日チェック ────────────────────────────────────────
function isSunday(string $d): bool {
    return (int)date('w', strtotime($d)) === 3; // 3=水曜
}

// ── 会員追加 ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_member') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $add_error  = 'セキュリティエラーが発生しました';
        $show_modal = true;
    } else {
        $modal_name  = trim($_POST['name'] ?? '');
        $modal_kana  = trim($_POST['kana'] ?? '');
        $modal_group = $_POST['group_name'] ?? '';
        if ($modal_name === '') {
            $add_error = '氏名を入力してください';
            $show_modal = true;
        } elseif ($modal_kana === '') {
            $add_error = '読み仮名を入力してください';
            $show_modal = true;
        } elseif (!in_array($modal_group, ['男性', '女性'])) {
            $add_error = '性別を選択してください';
            $show_modal = true;
        } else {
            $pdo->prepare("INSERT INTO members (name, kana, group_name, is_active) VALUES (?, ?, ?, 1)")
                ->execute([$modal_name, $modal_kana, $modal_group]);
            header("Location: index_amk.php?date=" . urlencode($date) . "&added=" . urlencode($modal_name));
            exit;
        }
    }
}

// ── 会員削除（1件） ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_member') {
    $redir_date = $_POST['redir_date'] ?? $today;
    if (hash_equals($csrf, $_POST['csrf'] ?? '') && !empty($_SESSION['del_auth'])) {
        $del_id = (int)($_POST['member_id'] ?? 0);
        if ($del_id > 0) {
            foreach (['attendance_tr','attendance_sr','attendance_live',
                      'attendance_amk','attendance_pmk','attendance_livek',
                      'attendance_chy01','attendance_sonota'] as $_att_tbl) {
                $pdo->prepare("DELETE FROM {$_att_tbl} WHERE member_id=?")->execute([$del_id]);
            }
            $pdo->prepare("DELETE FROM members WHERE id=?")->execute([$del_id]);
        }
    }
    header("Location: index_amk.php?date=" . urlencode($redir_date) . "&deleted=1");
    exit;
}


// ── 削除パスワード認証 ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_del') {
    $redir_date = $_POST['redir_date'] ?? $today;
    $next       = $_POST['next'] ?? '';
    if (($_POST['del_password'] ?? '') === DEL_PASSWORD) {
        $_SESSION['del_auth'] = true;
        $extra = ($next === 'del_mode') ? '&del_mode=1' : '';
        header("Location: index_amk.php?date=" . urlencode($redir_date) . $extra);
    } else {
        header("Location: index_amk.php?date=" . urlencode($redir_date)
            . "&del_err=1&next=" . urlencode($next));
    }
    exit;
}

// ── 会員削除（複数） ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    $redir_date = $_POST['redir_date'] ?? $today;
    $del_count  = 0;
    if (hash_equals($csrf, $_POST['csrf'] ?? '') && !empty($_SESSION['del_auth'])) {
        $ids = array_filter(
            array_map('intval', $_POST['del_ids'] ?? []),
            fn($id) => $id > 0
        );
        foreach ($ids as $del_id) {
            foreach (['attendance_tr','attendance_sr','attendance_live',
                      'attendance_amk','attendance_pmk','attendance_livek',
                      'attendance_chy01','attendance_sonota'] as $_att_tbl) {
                $pdo->prepare("DELETE FROM {$_att_tbl} WHERE member_id=?")->execute([$del_id]);
            }
            $pdo->prepare("DELETE FROM members WHERE id=?")->execute([$del_id]);
            $del_count++;
        }
    }
    header("Location: index_amk.php?date=" . urlencode($redir_date) . "&bulk_deleted=" . $del_count);
    exit;
}

// ── 確定解除 ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unconfirm') {
    $post_date = $_POST['date'] ?? $today;
    $pdo->prepare("DELETE FROM confirmed_dates_amk WHERE attend_date=?")->execute([$post_date]);
    log_action($pdo, "確定解除", "午前祈祷会: $post_date の確定を解除");
    header("Location: index_amk.php?date=" . urlencode($post_date));
    exit;
}

// ── 保存 or 確定 ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    in_array($_POST['action'] ?? '', ['save', 'confirm'])) {

    $post_date = $_POST['date'] ?? $today;

    // 未来日付チェック
    if ($post_date > $today) {
        header('Location: ' . basename(__FILE__) . '?date=' . urlencode($post_date) . '&future_err=1');
        exit;
    }

    // 水曜日チェック
    if (!isSunday($post_date)) {
        header("Location: index_amk.php?date=" . urlencode($post_date) . "&weekday_err=1");
        exit;
    }

    $chk = $pdo->prepare("SELECT COUNT(*) FROM confirmed_dates_amk WHERE attend_date=?");
    $chk->execute([$post_date]);
    if ((int)$chk->fetchColumn() === 0) {
        $checked = $_POST['present'] ?? [];
        $all_ids = $pdo->query("SELECT id FROM members WHERE is_active=1")
                       ->fetchAll(\PDO::FETCH_COLUMN);
        $pdo->prepare("DELETE FROM attendance_amk WHERE attend_date=?")->execute([$post_date]);
        $stmt = $pdo->prepare(
            "INSERT INTO attendance_amk (member_id, attend_date, status) VALUES (?, ?, ?)"
        );
        foreach ($all_ids as $mid) {
            $status = in_array((string)$mid, $checked) ? '出席' : '欠席';
            $stmt->execute([(int)$mid, $post_date, $status]);
        }
    }

    if ($_POST['action'] === 'confirm') {
        $pdo->prepare("INSERT IGNORE INTO confirmed_dates_amk (attend_date) VALUES (?)")
            ->execute([$post_date]);
    }

    $log_act = ($_POST['action'] === 'confirm') ? '確定保存' : '一時保存';
    $present_cnt = count($checked ?? []);
    log_action($pdo, $log_act, "午前祈祷会: $post_date 出席{$present_cnt}名");
    $param = ($_POST['action'] === 'confirm') ? '&confirmed=1' : '&saved=1';
    header("Location: index_amk.php?date=" . urlencode($post_date) . $param);
    exit;
}

// ── 表示データ取得 ─────────────────────────────────────────
$chk = $pdo->prepare("SELECT confirmed_at FROM confirmed_dates_amk WHERE attend_date=?");
$chk->execute([$date]);
$confirmed_row = $chk->fetch();
$is_confirmed  = (bool)$confirmed_row;

$men   = $pdo->query("SELECT id,name FROM members WHERE is_active=1 AND group_name='男性' ORDER BY kana")->fetchAll();
$women = $pdo->query("SELECT id,name FROM members WHERE is_active=1 AND group_name='女性' ORDER BY kana")->fetchAll();

$att = $pdo->prepare("SELECT member_id FROM attendance_amk WHERE attend_date=? AND status='出席'");
$att->execute([$date]);
$present_ids = array_flip($att->fetchAll(\PDO::FETCH_COLUMN));

$men_present   = count(array_filter($men,   fn($m) => isset($present_ids[$m['id']])));
$women_present = count(array_filter($women, fn($m) => isset($present_ids[$m['id']])));

$saved          = isset($_GET['saved']);
$confirmed_flag = isset($_GET['confirmed']);
$added          = $_GET['added'] ?? '';
$deleted        = isset($_GET['deleted']);
$bulk_deleted   = isset($_GET['bulk_deleted']) ? (int)$_GET['bulk_deleted'] : 0;
$del_mode       = isset($_GET['del_mode']);
$del_err        = isset($_GET['del_err']);
$del_authorized    = !empty($_SESSION['del_auth']);
$weekday_err       = isset($_GET['weekday_err']);
$weekday_ok        = isSunday($date);
$weekday_err_msg   = $weekday_ok ? '' : '水曜日が選ばれてません。';
$future_err        = ($date > $today);
$future_err_msg    = $future_err
    ? date("Y年n月j日", strtotime($date)) . "はまだ来てません。"
    : "";
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>つばさ名簿 - 平日（土日祝を除く）</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #f0f4f8; color: #333; font-size: 15px; }
header { background: #2c5f8a; color: #fff; padding: 12px 20px; display: flex; align-items: center; gap: 10px; }
header h1 { font-size: 1.1rem; }
nav a { color: #cde; text-decoration: none; font-size: .85rem; margin-left: 14px; }
nav a:hover { color: #fff; }
.container { max-width: 760px; margin: 16px auto; padding: 0 14px; }
.date-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.date-bar label { font-weight: bold; }
.date-bar input[type=date] { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: .95rem; }
.btn-nav   { padding: 6px 12px; background: #fff; border: 1px solid #bbb; border-radius: 4px; cursor: pointer; font-size: .9rem; }
.btn-nav:hover { background: #e8f0f8; border-color: #2c5f8a; }
.btn-today { padding: 6px 12px; background: #2c5f8a; border: none; border-radius: 4px; cursor: pointer; font-size: .85rem; color: #fff; }
.btn-today:hover { background: #1a4a70; }
.badge { padding: 3px 10px; border-radius: 12px; font-size: .82rem; font-weight: bold; }
.badge-saved     { background: #c8e6c9; color: #1b5e20; }
.badge-confirmed { background: #fff9c4; color: #7a5c00; }
.badge-added     { background: #c8e6c9; color: #1b5e20; }
.badge-deleted   { background: #ffcdd2; color: #b71c1c; }
.weekday-msg { width: 100%; padding: 6px 12px; background: #ffebee; color: #c62828; border-radius: 6px; font-size: .88rem; font-weight: bold; display: none; }
.confirm-bar { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; flex-wrap: wrap; }
.confirm-bar.is-locked   { background: #fff8e1; border: 2px solid #f9c00d; }
.confirm-bar.is-unlocked { background: #f1f8e9; border: 1px solid #aed581; }
.confirm-info { flex: 1; font-size: .88rem; color: #555; }
.confirm-info strong { color: #333; }
.btn-confirm   { padding: 8px 20px; background: #f9c00d; color: #3a2a00; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: .9rem; }
.btn-confirm:hover { background: #e6b000; }
.btn-unconfirm { padding: 7px 16px; background: #fff; color: #777; border: 1px solid #ccc; border-radius: 5px; cursor: pointer; font-size: .85rem; }
.btn-unconfirm:hover { background: #f5f5f5; }
/* ツールバー */
.toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 10px; }
.btn-add { background: #2e7d52; color: #fff; border: none; padding: 8px 18px; border-radius: 6px; font-size: .9rem; cursor: pointer; font-weight: bold; }
.btn-add:hover { background: #1b5e38; }
.btn-bulk-del { background: #fff; color: #c44; border: 1px solid #e88; padding: 8px 18px; border-radius: 6px; font-size: .9rem; cursor: pointer; font-weight: bold; }
.btn-bulk-del:hover { background: #fff0f0; }
/* セクション */
.columns { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 560px) { .columns { grid-template-columns: 1fr; } }
.section { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
.section-header { padding: 10px 14px; font-weight: bold; font-size: .95rem; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.sec-men   .section-header { background: #d0e8ff; color: #1a5c99; }
.sec-women .section-header { background: #ffd0e8; color: #99195c; }
.section-header .count { font-size: .82rem; opacity: .85; }
.select-all { font-size: .78rem; padding: 3px 8px; border: 1px solid currentColor; border-radius: 4px; cursor: pointer; background: transparent; color: inherit; }
/* 全選択エリア（削除モード時のみ表示） */
.del-header-wrap { display: none; align-items: center; gap: 4px; font-size: .82rem; font-weight: normal; }
.del-header-wrap input[type=checkbox] { width: 15px; height: 15px; accent-color: #c44; cursor: pointer; }
/* メンバー行 */
.member-list { padding: 6px 0; }
.member-row { display: flex; align-items: center; padding: 0 14px; }
.member-row.is-present { background: #f0f7ff; }
.member-row.editable:hover { background: #f5f8fc; }
.member-label { flex: 1; display: flex; align-items: center; gap: 10px; padding: 7px 0; }
.member-label.editable { cursor: pointer; }
.member-label input[type=checkbox] { width: 18px; height: 18px; accent-color: #2c5f8a; flex-shrink: 0; }
.member-label.editable input { cursor: pointer; }
/* ── スマホ: ボタン式切り替え ──────────────────── */
.attendance-summary { margin: 10px 0 0; padding: 0 4px; }
.summary-table { border-collapse: collapse; min-width: 180px; }
.summary-table th { text-align: left; padding: 5px 14px 5px 0; font-size: .88rem; color: #555; font-weight: normal; }
.summary-table td { padding: 5px 0; font-size: .95rem; font-weight: bold; color: #333; }
.sum-total-row th, .sum-total-row td { border-top: 2px solid #bbb; padding-top: 7px; color: #2d6a3f; font-size: 1.05rem; }
.row-num { display:inline-block; width:1.8em; text-align:right; color:#aaa; font-size:.8em; margin-right:.4em; }
.status-badge { display: none; font-size: .85rem; font-weight: bold; padding: 4px 12px; border-radius: 14px; white-space: nowrap; flex-shrink: 0; }
.status-present { background: #1e88e5; color: #fff; }
.status-absent  { background: #e0e0e0; color: #757575; }
@media (max-width: 600px) {
  .member-label input[type=checkbox] { display: none; }
  .status-badge { display: inline-flex; align-items: center; }
  .member-row { min-height: 54px; border-bottom: 1px solid #eee; }
  .member-row.editable { cursor: pointer; }
  .member-label { padding: 12px 14px; min-height: 54px; }
  .member-label .name { font-size: 1rem; }
  .member-row.is-present { background: #e3f2fd; }
  .member-row.is-present .name { font-weight: bold; color: #1565c0; }
  .section-header .select-all { font-size: .82rem; padding: 5px 14px; min-height: 36px; }
  .columns { gap: 10px; }
  .footer-bar { padding: 12px 14px; }
  .btn-save { padding: 13px 32px; font-size: 1.05rem; width: 100%; }
  .confirm-bar { padding: 12px 14px; }
  .btn-confirm { width: 100%; padding: 12px; font-size: 1rem; }
}
.member-label .name { flex: 1; }
.member-row.is-present .name { font-weight: bold; color: #1a5c99; }
/* 削除チェックボックス（通常時は非表示） */
.del-cb-wrap { display: none; padding-right: 8px; }
.del-cb { width: 17px; height: 17px; accent-color: #c44; cursor: pointer; }
/* 行が選択された状態 */
.member-row.del-selected { background: #fff0f0 !important; }
/* フッター */
.footer-bar { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #ddd; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.total-info { font-size: .9rem; color: #555; }
.btn-save { background: #2c5f8a; color: #fff; border: none; padding: 10px 28px; border-radius: 6px; font-size: 1rem; cursor: pointer; }
.btn-save:hover { background: #1a4a70; }
.locked-notice { font-size: .88rem; color: #999; }
/* 削除モードのフッターボタン */
#del-btns { display: none; gap: 8px; }
.btn-exec-del { background: #c44; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; font-size: .95rem; cursor: pointer; font-weight: bold; }
.btn-exec-del:hover { background: #a33; }
.btn-cancel-del { background: #fff; color: #555; border: 1px solid #ccc; padding: 10px 20px; border-radius: 6px; font-size: .95rem; cursor: pointer; }
.btn-cancel-del:hover { background: #f5f5f5; }
/* モーダル */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 100; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal { background: #fff; border-radius: 10px; padding: 32px 28px; width: 340px; box-shadow: 0 8px 32px rgba(0,0,0,0.18); }
.modal h3 { color: #2c5f8a; margin-bottom: 20px; font-size: 1.05rem; }
.modal-field { margin-top: 14px; }
.modal-field label { display: block; font-size: .85rem; color: #555; margin-bottom: 4px; }
.modal-field input[type=text],
.modal-field select { width: 100%; padding: 9px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: .95rem; }
.modal-field input:focus,
.modal-field select:focus { outline: none; border-color: #2c5f8a; box-shadow: 0 0 0 2px rgba(44,95,138,.15); }
.modal-error { color: #c62828; background: #ffebee; border-radius: 6px; padding: 8px 12px; font-size: .88rem; margin-bottom: 4px; }
.modal-btns { display: flex; gap: 10px; margin-top: 22px; }
.btn-modal-submit { flex: 1; background: #2c5f8a; color: #fff; border: none; padding: 10px; border-radius: 6px; font-size: 1rem; cursor: pointer; }
.btn-modal-submit:hover { background: #1a4a70; }
.btn-modal-cancel { flex: 1; background: #fff; color: #555; border: 1px solid #ccc; padding: 10px; border-radius: 6px; font-size: 1rem; cursor: pointer; }
.btn-modal-cancel:hover { background: #f5f5f5; }
</style>
</head>
<body>
<header>
  
  <nav>
    <a href="index_amk.php">出席入力</a>
    <a href="members.php">会員管理</a>
    <a href="report.php?m=amk">レポート</a>
    <a href="backup.php">バックアップ</a>
    <a href="logout.php" style="margin-left:auto;color:#ffd0d0;">ログアウト</a>
    <a href="main02.php" style="color:#cde;">メニュー画面へ</a>
  </nav>
</header>

<!-- メインフォーム（チェックボックス・保存・確定） -->
<form method="post" id="main-form">
  <input type="hidden" name="action" id="form-action" value="save">
  <input type="hidden" name="date"   value="<?= htmlspecialchars($date) ?>">

  <div class="container">
    <!-- 日付ナビ -->
    <div class="date-bar">
      <label>日付：</label>
      <button type="button" class="btn-nav" onclick="moveDate(-1)">◀ 前日</button>
      <input type="date" id="date-input" value="<?= htmlspecialchars($date) ?>"
             onchange="onDateChange(this.value)">
      <button type="button" class="btn-nav" onclick="moveDate(1)">翌日 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='index_amk.php?date=<?= $today ?>'">今日</button>
      <?php if ($future_err): ?>
        <span class="badge badge-deleted"><?= htmlspecialchars($future_err_msg) ?></span>
      <?php elseif ($weekday_err || !$weekday_ok): ?>
        <span class="badge badge-deleted"><?= htmlspecialchars($weekday_err_msg) ?></span>
      <?php elseif ($saved): ?>
        <span class="badge badge-saved">✓ 保存しました</span>
      <?php elseif ($confirmed_flag): ?>
        <span class="badge badge-confirmed">🔒 確定しました</span>
      <?php elseif ($added !== ''): ?>
        <span class="badge badge-added">✓ <?= htmlspecialchars($added) ?> を追加しました</span>
      <?php elseif ($bulk_deleted > 0): ?>
        <span class="badge badge-deleted"><?= $bulk_deleted ?>件削除しました</span>
      <?php elseif ($deleted): ?>
        <span class="badge badge-deleted">削除しました</span>
      <?php endif; ?>
    </div>
    <div id="weekday-msg" class="weekday-msg"><?= htmlspecialchars($future_err ? $future_err_msg : $weekday_err_msg) ?></div>

    <!-- 確定バー -->
    <?php if ($is_confirmed): ?>
      <div class="confirm-bar is-locked">
        <span style="font-size:1.2rem">🔒</span>
        <div class="confirm-info">
          <strong><?= date('Y年n月j日', strtotime($date)) ?></strong> は確定済みです。
          （<?= date('Y/m/d H:i', strtotime($confirmed_row['confirmed_at'])) ?>）
        </div>
        <form method="post" style="display:inline"
              onsubmit="return confirm('確定を解除して編集可能にしますか？')">
          <input type="hidden" name="action" value="unconfirm">
          <input type="hidden" name="date"   value="<?= htmlspecialchars($date) ?>">
          <button class="btn-unconfirm" type="submit">🔓 確定を解除</button>
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

    <!-- ツールバー -->
    <div class="toolbar">
      <button type="button" class="btn-add" id="btn-add" onclick="openModal()">＋ 新規追加</button>
      <button type="button" class="btn-bulk-del" id="btn-bulk-del" onclick="startBulkDelMode()">🗑 名簿削除</button>
    </div>

    <!-- 会員リスト -->
    <div class="columns">
      <!-- 男性 -->
      <div class="section sec-men">
        <div class="section-header">
          <span class="del-header-wrap" id="del-header-men">
            <input type="checkbox" id="del-all-men" onchange="toggleSelectAllDel('men', this.checked)">
            <label for="del-all-men">全選択</label>
          </span>
          <span>男性</span>
          <span class="count">
            <span id="men-count"><?= $men_present ?></span>/<?= count($men) ?>名
            <?php if (!$is_confirmed): ?>
              <button type="button" class="select-all" onclick="selectAll('men',false)">クリア</button>
            <?php endif; ?>
          </span>
        </div>
        <div class="member-list" id="men-list">
          <?php $row_num = 0; foreach ($men as $m):
            $chk = isset($present_ids[$m['id']]);
            $row_num++;
          ?>
            <div class="member-row <?= $chk?'is-present':'' ?> <?= ($is_confirmed||$future_err)?'':'editable' ?>">
              <label class="member-label <?= ($is_confirmed||$future_err)?'':'editable' ?>">
                <input type="checkbox" name="present[]" value="<?= $m['id'] ?>"
                       <?= $chk          ? 'checked'  : '' ?>
                       <?= ($is_confirmed||$future_err) ? 'disabled' : 'onchange="updateRow(this)"' ?>>
                <span class="row-num"><?= $row_num ?></span><span class="name"><?= htmlspecialchars($m['name']) ?></span>
                <span class="status-badge <?= $chk ? 'status-present' : 'status-absent' ?>"><?= $chk ? '出席' : '欠席' ?></span>
              </label>
              <span class="del-cb-wrap">
                <input type="checkbox" class="del-cb" value="<?= $m['id'] ?>"
                       onchange="onDelCbChange(this)">
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 女性 -->
      <div class="section sec-women">
        <div class="section-header">
          <span class="del-header-wrap" id="del-header-women">
            <input type="checkbox" id="del-all-women" onchange="toggleSelectAllDel('women', this.checked)">
            <label for="del-all-women">全選択</label>
          </span>
          <span>女性</span>
          <span class="count">
            <span id="women-count"><?= $women_present ?></span>/<?= count($women) ?>名
            <?php if (!$is_confirmed): ?>
              <button type="button" class="select-all" onclick="selectAll('women',false)">クリア</button>
            <?php endif; ?>
          </span>
        </div>
        <div class="member-list" id="women-list">
          <?php $row_num = 0; foreach ($women as $m):
            $chk = isset($present_ids[$m['id']]);
            $row_num++;
          ?>
            <div class="member-row <?= $chk?'is-present':'' ?> <?= ($is_confirmed||$future_err)?'':'editable' ?>">
              <label class="member-label <?= ($is_confirmed||$future_err)?'':'editable' ?>">
                <input type="checkbox" name="present[]" value="<?= $m['id'] ?>"
                       <?= $chk          ? 'checked'  : '' ?>
                       <?= ($is_confirmed||$future_err) ? 'disabled' : 'onchange="updateRow(this)"' ?>>
                <span class="row-num"><?= $row_num ?></span><span class="name"><?= htmlspecialchars($m['name']) ?></span>
                <span class="status-badge <?= $chk ? 'status-present' : 'status-absent' ?>"><?= $chk ? '出席' : '欠席' ?></span>
              </label>
              <span class="del-cb-wrap">
                <input type="checkbox" class="del-cb" value="<?= $m['id'] ?>"
                       onchange="onDelCbChange(this)">
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- 合計欄 -->
  <div class="attendance-summary">
    <table class="summary-table">
      <tbody>
        <tr>
          <th>男性</th>
          <td><span id="sum-men"><?= $men_present ?></span> / <?= count($men) ?>名</td>
        </tr>
        <tr>
          <th>女性</th>
          <td><span id="sum-women"><?= $women_present ?></span> / <?= count($women) ?>名</td>
        </tr>
        <tr class="sum-total-row">
          <th>合計</th>
          <td><span id="sum-total"><?= $men_present + $women_present ?></span> / <?= count($men) + count($women) ?>名</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="footer-bar">
    <span class="total-info" id="normal-total">
      合計出席：<strong id="total-count"><?= $men_present + $women_present ?></strong>/<?= count($men) + count($women) ?>名
    </span>
    <span class="total-info" id="del-total" style="display:none">
      選択中：<strong id="del-count">0</strong> 件
    </span>
    <div id="normal-btns">
      <?php if ($is_confirmed): ?>
        <span class="locked-notice">🔒 確定済みのため編集できません</span>
      <?php else: ?>
        <button class="btn-save" type="submit" id="btn-save" <?= (!$weekday_ok || $future_err) ? 'disabled style="opacity:.45;cursor:not-allowed;"' : '' ?>>保存する</button>
      <?php endif; ?>
    </div>
    <div id="del-btns">
      <button type="button" class="btn-cancel-del" onclick="exitDelMode()">キャンセル</button>
      <button type="button" class="btn-exec-del"   onclick="execBulkDelete()">削除実行</button>
    </div>
  </div>
</form>

<!-- ── 新規追加モーダル ─────────────────────────────── -->
<div class="modal-overlay <?= $show_modal?'open':'' ?>" id="modal">
  <div class="modal">
    <h3>新規メンバー追加</h3>
    <?php if ($add_error): ?>
      <div class="modal-error"><?= htmlspecialchars($add_error) ?></div>
    <?php endif; ?>
    <form method="post" action="index_amk.php?date=<?= urlencode($date) ?>">
      <input type="hidden" name="action" value="add_member">
      <input type="hidden" name="csrf"   value="<?= htmlspecialchars($csrf) ?>">
      <div class="modal-field">
        <label>氏名</label>
        <input type="text" name="name" value="<?= htmlspecialchars($modal_name) ?>"
               placeholder="例：山田太郎" required autofocus>
      </div>
      <div class="modal-field">
        <label>読み仮名</label>
        <input type="text" name="kana" value="<?= htmlspecialchars($modal_kana) ?>"
               placeholder="例：やまだたろう" required>
      </div>
      <div class="modal-field">
        <label>性別</label>
        <select name="group_name" required>
          <option value="">選択してください</option>
          <option value="男性" <?= $modal_group==='男性'?'selected':'' ?>>男性</option>
          <option value="女性" <?= $modal_group==='女性'?'selected':'' ?>>女性</option>
        </select>
      </div>
      <div class="modal-btns">
        <button type="button" class="btn-modal-cancel" onclick="closeModal()">キャンセル</button>
        <button type="submit" class="btn-modal-submit">登録</button>
      </div>
    </form>
  </div>
</div>


<!-- ── 削除パスワードモーダル ──────────────────────────── -->
<div class="modal-overlay" id="del-pw-modal">
  <div class="modal">
    <h3>🔒 削除認証</h3>
    <?php if ($del_err): ?>
      <div class="modal-error">パスワードが違います。再度入力してください。</div>
    <?php endif; ?>
    <form method="post" action="index_amk.php?date=<?= urlencode($date) ?>">
      <input type="hidden" name="action"     value="verify_del">
      <input type="hidden" name="csrf"       value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="redir_date" value="<?= htmlspecialchars($date) ?>">
      <input type="hidden" name="next"       id="del-next" value="">
      <div class="modal-field">
        <label>削除パスワード</label>
        <input type="password" name="del_password" placeholder="パスワードを入力" required>
      </div>
      <div class="modal-btns">
        <button type="button" class="btn-modal-cancel" onclick="closePwModal()">キャンセル</button>
        <button type="submit" class="btn-modal-submit">認証</button>
      </div>
    </form>
  </div>
</div>

<!-- ── 削除フォーム（非表示） ─────────────────────────── -->
<form id="delete-form" method="post" style="display:none">
  <input type="hidden" name="action"     value="delete_member">
  <input type="hidden" name="csrf"       value="<?= htmlspecialchars($csrf) ?>">
  <input type="hidden" name="redir_date" value="<?= htmlspecialchars($date) ?>">
  <input type="hidden" name="member_id"  id="del-member-id" value="">
</form>

<script>

// ── 削除パスワード認証 ────────────────────────────────────
var delAuthorized = <?= $del_authorized ? 'true' : 'false' ?>;

function openDelPwModal(next) {
  document.getElementById('del-next').value = next;
  document.getElementById('del-pw-modal').classList.add('open');
}
function closePwModal() {
  document.getElementById('del-pw-modal').classList.remove('open');
}
function startBulkDelMode() {
  if (!delAuthorized) { openDelPwModal('del_mode'); return; }
  enterDelMode();
}


// ── 水曜日チェック ────────────────────────────────────────
function isSundayJS(dateStr) {
  const d = new Date(dateStr + 'T00:00:00');
  return d.getDay() === 3; // 3=水曜
}
function checkWeekday(dateStr) {
  const msgEl  = document.getElementById('weekday-msg');
  const savBtn = document.getElementById('btn-save');
  if (!dateStr || !msgEl) return;
  const todayD = new Date(); todayD.setHours(0,0,0,0);
  const selD   = new Date(dateStr + 'T00:00:00');
  if (selD > todayD) {
    const y = selD.getFullYear();
    const m = selD.getMonth() + 1;
    const d = selD.getDate();
    msgEl.textContent   = y + '年' + m + '月' + d + '日はまだ来てません。';
    msgEl.style.display = '';
    if (savBtn) { savBtn.disabled = true; savBtn.style.opacity = '.45'; savBtn.style.cursor = 'not-allowed'; }
    document.querySelectorAll('input[name="present[]"]').forEach(function(cb) {
      cb.disabled = true;
      var row = cb.closest('.member-row'); if (row) row.classList.remove('editable');
      var lbl = cb.closest('.member-label'); if (lbl) lbl.classList.remove('editable');
    });
  } else if (!isSundayJS(dateStr)) {
    const label = '水曜日が選ばれてません。';
    msgEl.textContent  = label;
    msgEl.style.display = '';
    if (savBtn) { savBtn.disabled = true; savBtn.style.opacity = '.45'; savBtn.style.cursor = 'not-allowed'; }
  } else {
    msgEl.style.display = 'none';
    if (savBtn) { savBtn.disabled = false; savBtn.style.opacity = ''; savBtn.style.cursor = ''; }
  }
}
function onDateChange(val) {
  checkWeekday(val);
  location.href = 'index_amk.php?date=' + val;
}

// ── 出欠関連 ───────────────────────────────────────────────
function doConfirm() {
  if (!confirm('<?= date('Y年n月j日', strtotime($date)) ?> の出欠を確定します。\n確定後は編集できなくなります。よろしいですか？')) return;
  document.getElementById('form-action').value = 'confirm';
  document.getElementById('main-form').submit();
}
function moveDate(delta) {
  const d = new Date(document.getElementById('date-input').value + 'T00:00:00');
  d.setDate(d.getDate() + delta);
  const y = d.getFullYear();
  const m = String(d.getMonth()+1).padStart(2,'0');
  const day = String(d.getDate()).padStart(2,'0');
  location.href = 'index_amk.php?date=' + y + '-' + m + '-' + day;
}
function updateRow(cb) {
  const row = cb.closest('.member-row');
  row.classList.toggle('is-present', cb.checked);
  const badge = row.querySelector('.status-badge');
  if (badge) {
    badge.textContent = cb.checked ? '出席' : '欠席';
    badge.className = 'status-badge ' + (cb.checked ? 'status-present' : 'status-absent');
  }
  updateCounts();
}
function selectAll(group, checked) {
  document.querySelectorAll('#'+group+'-list .member-label input[type=checkbox]').forEach(cb => {
    cb.checked = checked;
    const row = cb.closest('.member-row');
    row.classList.toggle('is-present', checked);
    const badge = row.querySelector('.status-badge');
    if (badge) {
      badge.textContent = checked ? '出席' : '欠席';
      badge.className = 'status-badge ' + (checked ? 'status-present' : 'status-absent');
    }
  });
  updateCounts();
}
function updateCounts() {
  const mc = document.querySelectorAll('#men-list .member-label input:checked').length;
  const wc = document.querySelectorAll('#women-list .member-label input:checked').length;
  document.getElementById('men-count').textContent   = mc;
  document.getElementById('women-count').textContent = wc;
  document.getElementById('total-count').textContent = mc + wc;
  const sm = document.getElementById('sum-men');    if (sm) sm.textContent = mc;
  const sw = document.getElementById('sum-women');  if (sw) sw.textContent = wc;
  const st = document.getElementById('sum-total');  if (st) st.textContent = mc + wc;
}

// ── モーダル ──────────────────────────────────────────────
function openModal() {
  document.getElementById('modal').classList.add('open');
}
function closeModal() {
  document.getElementById('modal').classList.remove('open');
}
document.getElementById('modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
// ページロード時に現在日付をチェック
window.addEventListener('DOMContentLoaded', function() {
  checkWeekday('<?= htmlspecialchars($date) ?>');
});

// ページロード時の処理
<?php if ($del_mode && $del_authorized): ?>
window.addEventListener('DOMContentLoaded', function(){ enterDelMode(); });
<?php endif; ?>
<?php if ($del_err): ?>
window.addEventListener('DOMContentLoaded', function(){
  openDelPwModal('<?= htmlspecialchars($_GET['next'] ?? '') ?>');
});
<?php endif; ?>


// ── 1件削除 ───────────────────────────────────────────────
function deleteMember(id, name) {
  if (!delAuthorized) { openDelPwModal(''); return; }
  if (!confirm(name + ' を名簿から削除します。\n出欠データも削除されます。\n本当に削除しますか？')) return;
  var csrf      = document.querySelector('#delete-form input[name=csrf]').value;
  var redirDate = document.querySelector('#delete-form input[name=redir_date]').value;
  var f = document.createElement('form');
  f.method = 'post';
  f.style.display = 'none';
  [['action','delete_member'],['csrf',csrf],['redir_date',redirDate],['member_id',id]].forEach(function(p){
    var i = document.createElement('input');
    i.type='hidden'; i.name=p[0]; i.value=p[1];
    f.appendChild(i);
  });
  document.body.appendChild(f);
  f.submit();
}

// ── 複数削除モード ────────────────────────────────────────
function enterDelMode() {
  document.querySelectorAll('.del-cb-wrap').forEach(el => el.style.display = 'flex');
  document.querySelectorAll('.del-header-wrap').forEach(el => el.style.display = 'flex');
  document.getElementById('normal-btns').style.display = 'none';
  document.getElementById('del-btns').style.display    = 'flex';
  document.getElementById('normal-total').style.display = 'none';
  document.getElementById('del-total').style.display    = '';
  document.getElementById('btn-add').style.display      = 'none';
  document.getElementById('btn-bulk-del').style.display = 'none';
  updateDelCount();
}
function exitDelMode() {
  document.querySelectorAll('.del-cb-wrap').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.del-header-wrap').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.del-cb').forEach(cb => { cb.checked = false; });
  document.querySelectorAll('.del-select-all').forEach(cb => { cb.checked = false; });
  document.querySelectorAll('.member-row').forEach(r => r.classList.remove('del-selected'));
  document.getElementById('del-all-men').checked   = false;
  document.getElementById('del-all-women').checked = false;
  document.getElementById('normal-btns').style.display  = '';
  document.getElementById('del-btns').style.display     = 'none';
  document.getElementById('normal-total').style.display = '';
  document.getElementById('del-total').style.display    = 'none';
  document.getElementById('btn-add').style.display      = '';
  document.getElementById('btn-bulk-del').style.display = '';
}
function toggleSelectAllDel(group, checked) {
  document.querySelectorAll('#'+group+'-list .del-cb').forEach(cb => {
    cb.checked = checked;
    cb.closest('.member-row').classList.toggle('del-selected', checked);
  });
  updateDelCount();
}
function onDelCbChange(cb) {
  cb.closest('.member-row').classList.toggle('del-selected', cb.checked);
  updateDelCount();
}
function updateDelCount() {
  var count = document.querySelectorAll('.del-cb:checked').length;
  document.getElementById('del-count').textContent = count;
}
function execBulkDelete() {
  var checked = document.querySelectorAll('.del-cb:checked');
  if (checked.length === 0) {
    alert('削除する項目を選択してください');
    return;
  }
  if (!confirm('選択した ' + checked.length + ' 件を削除しますか？\n出欠データも削除されます。\nこの操作は取り消せません。')) return;
  var csrf      = document.querySelector('#delete-form input[name=csrf]').value;
  var redirDate = document.querySelector('#delete-form input[name=redir_date]').value;
  var f = document.createElement('form');
  f.method = 'post';
  f.style.display = 'none';
  [['action','bulk_delete'],['csrf',csrf],['redir_date',redirDate]].forEach(function(p){
    var i = document.createElement('input');
    i.type='hidden'; i.name=p[0]; i.value=p[1];
    f.appendChild(i);
  });
  checked.forEach(function(cb){
    var i = document.createElement('input');
    i.type='hidden'; i.name='del_ids[]'; i.value=cb.value;
    f.appendChild(i);
  });
  document.body.appendChild(f);
  f.submit();
}
</script>
</body>
</html>
