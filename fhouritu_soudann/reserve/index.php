<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// ヘッダーからのログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (trim($_POST['password'] ?? '') === ADMIN_PASSWORD) {
        $_SESSION[ADMIN_SESSION_KEY] = true;
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    }
    $login_error = true;
}

// 弁護士スケジュールログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lawyer_login'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (trim($_POST['password'] ?? '') === LAWYER_PASSWORD) {
        $_SESSION[LAWYER_SESSION_KEY] = true;
        header('Location: ' . BASE_URL . '/admin/schedule.php');
        exit;
    }
    $lawyer_error = true;
}

$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');

// 範囲補正
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$today      = date('Y-m-d');
$first_day  = sprintf('%04d-%02d-01', $year, $month);
$start_dow  = (int)date('w', strtotime($first_day)); // 0=日
$days_in_month = (int)date('t', strtotime($first_day));

$prev_m = $month - 1; $prev_y = $year;
if ($prev_m < 1) { $prev_m = 12; $prev_y--; }
$next_m = $month + 1; $next_y = $year;
if ($next_m > 12) { $next_m = 1; $next_y++; }

// 当月の予約可能日の空き枠を一括取得
$avail = [];
for ($d = 1; $d <= $days_in_month; $d++) {
    $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
    if (is_bookable_date($date)) {
        foreach (CONSULT_TYPES as $type) {
            $avail[$date][$type] = get_available_count($date, $type);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> - 予約カレンダー</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
:root { --fuku-green: #3a7d5c; --fuku-light: #e8f5ee; }
body { background: #f8f9fa; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.site-header { background: var(--fuku-green); color: #fff; padding: 1rem; }
.site-header small { opacity: .8; font-size: .85rem; }
.calendar-table th, .calendar-table td { text-align: center; vertical-align: middle; padding: 0; }
.calendar-table { width: 100%; border-collapse: collapse; }
.calendar-table th { background: var(--fuku-green); color: #fff; padding: 8px 0; font-size: .85rem; }
.day-cell { border: 1px solid #dee2e6; height: 90px; position: relative; }
.day-cell.today { background: #fffde7; }
.day-cell.disabled { background: #f0f0f0; color: #bbb; }
.day-cell.sunday th { color: #dc3545; }
.day-num { font-size: .85rem; font-weight: bold; padding: 4px; }
.avail-badge { font-size: .7rem; line-height: 1.4; }
.avail-badge a { text-decoration: none; display: block; border-radius: 4px; padding: 1px 4px; margin: 1px 4px; }
.avail-badge .general { background: #e3f2fd; color: #0d47a1; }
.avail-badge .legal   { background: #f3e5f5; color: #4a148c; }
.avail-badge .full    { background: #ffebee; color: #b71c1c; }
.legend { font-size: .8rem; }
.sun { color: #dc3545; }
</style>
</head>
<body>

<div class="site-header">
  <div class="container d-flex justify-content-between align-items-center">
    <div>
      <h1 class="h4 mb-0"><?= SITE_NAME ?></h1>
      <small>初回無料・ご予約はカレンダーから日程をお選びください</small>
    </div>
    <div class="d-flex align-items-center gap-3 flex-wrap justify-content-end">
      <a href="https://houritusoudann01.afuku5906.com/" class="btn btn-sm btn-outline-light">メインへ</a>
      <form method="post" class="d-flex align-items-center gap-2">
        <input type="hidden" name="lawyer_login" value="1">
        <input type="password" name="password" placeholder="パスワード"
               class="form-control form-control-sm <?= !empty($lawyer_error) ? 'border-danger' : '' ?>"
               style="width:110px">
        <button type="submit" class="btn btn-sm btn-outline-light">弁護士予定</button>
      </form>
      <form method="post" class="d-flex align-items-center gap-2">
        <input type="hidden" name="admin_login" value="1">
        <input type="password" name="password" placeholder="パスワード"
               class="form-control form-control-sm <?= !empty($login_error) ? 'border-danger' : '' ?>"
               style="width:110px">
        <button type="submit" class="btn btn-sm btn-outline-light">管理画面</button>
      </form>
    </div>
  </div>
</div>

<div class="container py-4">

  <!-- 相談メニュー案内 -->
  <div class="row mb-3 g-3">
    <div class="col-12">
      <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #4a148c !important; border-left-width: 4px !important;">
        <div class="card-body">
          <h5 class="card-title" style="color:#4a148c">法律相談（相談前整理サポート）</h5>
          <p class="card-text small mb-1">専門家に相談する前の状況・資料の整理</p>
          <p class="card-text small text-muted">初回無料（45分）／2回目以降 2,000円（税込）</p>
        </div>
      </div>
    </div>
  </div>

  <!-- カレンダーナビ -->
  <div class="d-flex align-items-center justify-content-between mb-2">
    <a href="?y=<?= $prev_y ?>&m=<?= sprintf('%02d', $prev_m) ?>" class="btn btn-outline-secondary btn-sm">◀ 前月</a>
    <h2 class="h5 mb-0"><?= $year ?>年<?= $month ?>月</h2>
    <a href="?y=<?= $next_y ?>&m=<?= sprintf('%02d', $next_m) ?>" class="btn btn-outline-secondary btn-sm">次月 ▶</a>
  </div>

  <!-- 凡例 -->
  <div class="d-flex gap-3 legend mb-2 flex-wrap">
    <span><span class="badge" style="background:#f3e5f5; color:#4a148c">法律 ○枠</span> 法律相談の空き</span>
    <span><span class="badge bg-secondary">休業</span> 予約不可</span>
  </div>

  <!-- カレンダー本体 -->
  <div class="table-responsive">
  <table class="calendar-table shadow-sm rounded">
    <thead>
      <tr>
        <th class="sun">日</th><th>月</th><th>火</th><th>水</th><th>木</th><th>金</th><th>土</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $col = 0;
    echo '<tr>';
    // 先頭の空白セル
    for ($i = 0; $i < $start_dow; $i++) {
        echo '<td class="day-cell disabled"></td>';
        $col++;
    }
    for ($d = 1; $d <= $days_in_month; $d++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $dow  = (int)date('w', strtotime($date));
        $is_today = ($date === $today);
        $bookable = is_bookable_date($date);

        $cell_class = 'day-cell';
        if ($is_today) $cell_class .= ' today';
        if (!$bookable) $cell_class .= ' disabled';

        $num_class = ($dow === 0) ? 'day-num sun' : 'day-num';

        echo '<td class="' . $cell_class . '">';
        echo '<div class="' . $num_class . '">' . $d . '</div>';

        if ($bookable) {
            echo '<div class="avail-badge">';
            foreach (['法律相談' => 'legal'] as $type => $cls) {
                $cnt = $avail[$date][$type] ?? 0;
                $short = '法律';
                if ($cnt > 0) {
                    echo '<a href="../reserve/form.php?date=' . $date . '&type=' . urlencode($type) . '" class="' . $cls . '">'
                       . $short . ' ' . $cnt . '枠</a>';
                } else {
                    echo '<span class="full d-block px-1">予約済み</span>';
                }
            }
            echo '</div>';
        } elseif ($date <= $today) {
            // 過去
        } elseif ($dow === 0) {
            echo '<small class="text-muted" style="font-size:.7rem">定休日</small>';
        } else {
            echo '<small class="text-muted" style="font-size:.7rem">休業日</small>';
        }
        echo '</td>';

        $col++;
        if ($col % 7 === 0) {
            echo '</tr>';
            if ($d < $days_in_month) echo '<tr>';
        }
    }
    // 末尾の空白セル
    $remain = 7 - ($col % 7);
    if ($remain < 7) {
        for ($i = 0; $i < $remain; $i++) {
            echo '<td class="day-cell disabled"></td>';
        }
    }
    echo '</tr>';
    ?>
    </tbody>
  </table>
  </div>

  <p class="mt-3 small text-muted">
    ※ 営業時間：月〜土 10:00〜18:00（日曜定休）<br>
    ※ 初回無料相談は事前予約が必要です。<br>
    ※ 2回目以降は事前入金確認後に予約確定となります。
  </p>
</div>
</body>
</html>
