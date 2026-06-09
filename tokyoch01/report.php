<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$mode         = $_GET['mode']  ?? 'daily';
$today        = date('Y-m-d');
$this_month   = date('Y-m');
$this_year    = (int)date('Y');
$target_date  = $_GET['date']  ?? $today;
$target_month = $_GET['month'] ?? $this_month;
$target_year  = (int)($_GET['year'] ?? $this_year);
$show_db      = isset($_GET['db']);
$do_csv       = isset($_GET['csv']);

// 集会テーブル選択
$_meeting_map = [
    'tr'    => 'attendance_tr',
    'sr'    => 'attendance_sr',
    'live'  => 'attendance_live',
    'amk'   => 'attendance_amk',
    'pmk'   => 'attendance_pmk',
    'livek' => 'attendance_livek',
    'chy01'  => 'attendance_chy01',
    'sonota' => 'attendance_sonota',
    '02d'    => 'attendance',
];
$m         = $_GET['m'] ?? '';
$att_table = $_meeting_map[$m] ?? 'attendance';
$m_label   = ['tr'=>'通常礼拝','sr'=>'早朝礼拝','live'=>'ライブ礼拝',
              'amk'=>'午前祈祷会','pmk'=>'午後祈祷会','livek'=>'ライブ祈祷会',
              'chy01'=>'朝祈会','sonota'=>'その他集会','02d'=>'土曜開所'][$m] ?? 'その他集会';
$m_param   = $m ? '&m='.$m : '';

// 確定テーブル選択
$_cd_map = [
    'tr'    => 'confirmed_dates_tr',
    'sr'    => 'confirmed_dates_sr',
    'live'  => 'confirmed_dates_live',
    'amk'   => 'confirmed_dates_amk',
    'pmk'   => 'confirmed_dates_pmk',
    'livek' => 'confirmed_dates_livek',
    'chy01'  => 'confirmed_dates_chy01',
    'sonota' => 'confirmed_dates_sonota',
    '02d'    => 'confirmed_dates',
];
$cd_table = $_cd_map[$m] ?? 'confirmed_dates';

// ---- CSV出力 ----
if ($do_csv && $mode !== 'graph') {
    $rows_csv = [];

    if ($mode === 'daily') {
        $filename = 'daily_' . $target_date . '.csv';
        $stmt = $pdo->prepare(
            "SELECT m.name, m.group_name, COALESCE(a.status,'未入力') AS status
             FROM members m
             LEFT JOIN {$att_table} a ON a.member_id=m.id AND a.attend_date=?
             WHERE m.is_active=1 ORDER BY m.group_name, m.kana"
        );
        $stmt->execute([$target_date]);
        $all        = $stmt->fetchAll();
        $men_data   = array_values(array_filter($all, fn($r) => $r['group_name'] === '男性'));
        $women_data = array_values(array_filter($all, fn($r) => $r['group_name'] === '女性'));
        $men_p      = count(array_filter($men_data,   fn($r) => $r['status'] === '出席'));
        $women_p    = count(array_filter($women_data, fn($r) => $r['status'] === '出席'));

        $rows_csv[] = [$target_date . ' 出欠日計', '', ''];
        $rows_csv[] = ['', '', ''];
        $rows_csv[] = ['【男性】', '', ''];
        $rows_csv[] = ['氏名', '区分', '出欠'];
        foreach ($men_data   as $r) { $rows_csv[] = [$r['name'], $r['group_name'], $r['status']]; }
        $rows_csv[] = ['男性 出席数', $men_p . ' / ' . count($men_data) . '名', ''];
        $rows_csv[] = ['', '', ''];
        $rows_csv[] = ['【女性】', '', ''];
        $rows_csv[] = ['氏名', '区分', '出欠'];
        foreach ($women_data as $r) { $rows_csv[] = [$r['name'], $r['group_name'], $r['status']]; }
        $rows_csv[] = ['女性 出席数', $women_p . ' / ' . count($women_data) . '名', ''];
        $rows_csv[] = ['', '', ''];
        $rows_csv[] = ['合計 出席数', ($men_p + $women_p) . ' / ' . count($all) . '名', ''];

    } elseif ($mode === 'monthly') {
        $filename = 'monthly_' . $target_month . '.csv';
        $stmt = $pdo->prepare(
            "SELECT m.name, m.group_name,
                    COALESCE(SUM(a.status='出席'),0) AS present,
                    COALESCE(SUM(a.status='欠席'),0) AS absent
             FROM members m
             LEFT JOIN {$att_table} a ON a.member_id=m.id
                 AND DATE_FORMAT(a.attend_date,'%Y-%m')=?
             WHERE m.is_active=1
             GROUP BY m.id, m.name, m.group_name ORDER BY m.group_name, m.kana"
        );
        $stmt->execute([$target_month]);
        $all        = $stmt->fetchAll();
        $men_data   = array_values(array_filter($all, fn($r) => $r['group_name'] === '男性'));
        $women_data = array_values(array_filter($all, fn($r) => $r['group_name'] === '女性'));
        $sum = function($list) {
            $p = array_sum(array_column($list, 'present'));
            $a = array_sum(array_column($list, 'absent'));
            $t = $p + $a;
            return [$p, $a, $t > 0 ? round($p / $t * 100) . '%' : '-'];
        };
        [$mp, $ma, $mr] = $sum($men_data);
        [$wp, $wa, $wr] = $sum($women_data);
        [$tp, $ta, $tr] = $sum($all);

        $rows_csv[] = [$target_month . ' 出欠月計', '', '', '', ''];
        $rows_csv[] = ['', '', '', '', ''];
        $rows_csv[] = ['【男性】', '', '', '', ''];
        $rows_csv[] = ['氏名', '区分', '出席数', '欠席数', '出席率'];
        foreach ($men_data as $r) {
            $t = $r['present'] + $r['absent'];
            $rows_csv[] = [$r['name'], $r['group_name'], $r['present'], $r['absent'],
                           $t > 0 ? round($r['present'] / $t * 100) . '%' : '-'];
        }
        $rows_csv[] = ['男性合計', '', $mp, $ma, $mr];
        $rows_csv[] = ['', '', '', '', ''];
        $rows_csv[] = ['【女性】', '', '', '', ''];
        $rows_csv[] = ['氏名', '区分', '出席数', '欠席数', '出席率'];
        foreach ($women_data as $r) {
            $t = $r['present'] + $r['absent'];
            $rows_csv[] = [$r['name'], $r['group_name'], $r['present'], $r['absent'],
                           $t > 0 ? round($r['present'] / $t * 100) . '%' : '-'];
        }
        $rows_csv[] = ['女性合計', '', $wp, $wa, $wr];
        $rows_csv[] = ['', '', '', '', ''];
        $rows_csv[] = ['総合計', '', $tp, $ta, $tr];

    } else { // yearly
        $filename = 'yearly_' . $target_year . '.csv';
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(attend_date,'%Y-%m') AS ym,
                    COUNT(DISTINCT attend_date) AS days,
                    SUM(status='出席') AS present,
                    SUM(status='欠席') AS absent
             FROM {$att_table} WHERE YEAR(attend_date)=?
             GROUP BY ym ORDER BY ym"
        );
        $stmt->execute([$target_year]);
        $rows_csv[] = [$target_year . '年 出欠年計', '', '', '', ''];
        $rows_csv[] = ['年月', '記録日数', '出席延べ', '欠席延べ', '出席率'];
        foreach ($stmt->fetchAll() as $r) {
            $total = $r['present'] + $r['absent'];
            $rate  = $total > 0 ? round($r['present'] / $total * 100) . '%' : '-';
            $rows_csv[] = [$r['ym'], $r['days'] . '日', $r['present'], $r['absent'], $rate];
        }
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');
    $fp = fopen('php://output', 'w');
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
    foreach ($rows_csv as $row) { fputcsv($fp, $row); }
    fclose($fp);
    exit;
}

// 記録済み日付（直近5件）
$dates_with_data = $pdo->query(
    "SELECT DISTINCT attend_date FROM {$att_table} ORDER BY attend_date DESC LIMIT 5"
)->fetchAll(PDO::FETCH_COLUMN);
$total_recorded = (int)$pdo->query(
    "SELECT COUNT(DISTINCT attend_date) FROM {$att_table}"
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>杉田教会名簿 - レポート</title>
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
.container { max-width: 980px; margin: 16px auto; padding: 0 14px; }
.tabs { display: flex; gap: 4px; margin-bottom: 0; flex-wrap: wrap; }
.tab {
  padding: 9px 24px; border-radius: 6px 6px 0 0;
  text-decoration: none; font-size: .92rem;
  background: #c8e6c9; color: #2d6a3f;
}
.tab.active { background: #2d6a3f; color: #fff; }
.tab:hover:not(.active) { background: #a5d6a7; }
.card {
  background: #fff; border-radius: 0 8px 8px 8px;
  padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.1); margin-bottom: 16px;
}
.nav-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
.nav-bar label { font-weight: bold; font-size: .9rem; }
.nav-bar input  { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: .95rem; }
.btn-nav   { padding: 6px 12px; background: #fff; border: 1px solid #bbb; border-radius: 4px; cursor: pointer; font-size: .9rem; }
.btn-nav:hover { background: #e8f5e9; border-color: #2d6a3f; }
.btn-today { padding: 6px 12px; background: #2d6a3f; border: none; border-radius: 4px; cursor: pointer; font-size: .85rem; color: #fff; }
.btn-today:hover { background: #1e4e2c; }
.btn-db  { padding: 6px 14px; border: 1px solid #888; border-radius: 4px; font-size: .82rem; cursor: pointer; background: #fff; color: #555; text-decoration: none; white-space: nowrap; }
.btn-db.active { background: #3a3a3a; color: #fff; border-color: #3a3a3a; }
.btn-db:hover { background: #e0e0e0; }
.btn-csv { padding: 6px 14px; background: #1565c0; color: #fff; border-radius: 4px; font-size: .82rem; text-decoration: none; white-space: nowrap; border: none; }
.btn-csv:hover { background: #0d47a1; }
.date-list { margin-bottom: 14px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.date-chip {
  padding: 4px 10px; border-radius: 14px; background: #e8f5e9;
  color: #2d6a3f; text-decoration: none; font-size: .82rem; border: 1px solid #c8e6c9;
}
.date-chip:hover  { background: #2d6a3f; color: #fff; }
.date-chip.active { background: #2d6a3f; color: #fff; }
.date-list-label  { font-size: .8rem; color: #888; }
.summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.summary-box { flex: 1; min-width: 90px; background: #f1f8e9; border-radius: 8px; padding: 12px; text-align: center; }
.summary-box .lbl { font-size: .78rem; color: #666; margin-bottom: 3px; }
.summary-box .val { font-size: 1.7rem; font-weight: bold; }
.v-total   { color: #2d6a3f; }
.v-present { color: #2e7d32; }
.v-absent  { color: #c62828; }
.columns2  { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media(max-width:600px){ .columns2 { grid-template-columns: 1fr; } }
.section   { border-radius: 8px; overflow: hidden; border: 1px solid #e0e8e0; }
.sec-header { padding: 8px 12px; font-weight: bold; font-size: .88rem; }
.sec-men   .sec-header { background: #d0e8ff; color: #1a5c99; }
.sec-women .sec-header { background: #ffd0e8; color: #99195c; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f1f8e9; }
th, td { padding: 8px 10px; text-align: center; font-size: .86rem; border-bottom: 1px solid #eee; }
td.left { text-align: left; }
tbody tr:hover { background: #f9fbe7; }
.s-出席 { color: #2e7d32; font-weight: bold; }
.s-欠席 { color: #c62828; }
.s-遅刻, .s-早退 { color: #e65100; }
.s-未入力 { color: #aaa; }
.bar { height: 7px; background: #e0e0e0; border-radius: 4px; overflow: hidden; margin-top: 3px; }
.bar-fill { height: 100%; background: #66bb6a; border-radius: 4px; }
.no-data { text-align: center; color: #999; padding: 24px; font-size: .95rem; }
/* DBビュー */
.db-card { background: #1e1e2e; color: #cdd6f4; border-radius: 8px; padding: 16px; margin-top: 16px; }
.db-card h3 { font-size: .85rem; color: #89b4fa; margin-bottom: 12px; border-bottom: 1px solid #313244; padding-bottom: 6px; }
.db-table { width: 100%; border-collapse: collapse; font-size: .82rem; font-family: 'Consolas','Monaco',monospace; }
.db-table thead tr { background: #313244; }
.db-table th { padding: 7px 10px; text-align: left; color: #89b4fa; font-weight: normal; border-bottom: 1px solid #45475a; }
.db-table td { padding: 6px 10px; border-bottom: 1px solid #313244; }
.db-table tbody tr:hover { background: #313244; }
.db-null { color: #6c7086; font-style: italic; }
.db-present { color: #a6e3a1; }
.db-absent  { color: #f38ba8; }
.db-confirmed { color: #f9e2af; }
/* グラフ */
.chart-wrap { position: relative; width: 100%; max-width: 860px; margin: 0 auto; }
</style>
</head>
<body>

<header>
  <a class="site-title" href="main_03.php">杉田教会名簿</a>
  <nav>
    <a href="index_tr02.php">出席入力</a>
    <a href="members.php">会員管理</a>
    <a href="report.php" class="active">レポート</a>
    <a href="backup.php">バックアップ</a>
    <a href="operation_log.php">操作ログ</a>
    <a href="logout.php" class="logout">ログアウト</a>
  </nav>
</header>

<div class="container">
  <div class="tabs">
    <a class="tab <?= $mode==='daily'   ? 'active':'' ?>" href="?mode=daily<?= $m_param ?>">日計</a>
    <a class="tab <?= $mode==='monthly' ? 'active':'' ?>" href="?mode=monthly<?= $m_param ?>">月計</a>
    <a class="tab <?= $mode==='yearly'  ? 'active':'' ?>" href="?mode=yearly<?= $m_param ?>">年計</a>
    <a class="tab <?= $mode==='graph'   ? 'active':'' ?>" href="?mode=graph<?= $m_param ?>">年間グラフ</a>
  </div>
  <!-- 集会選択 -->
  <div style="margin-bottom:8px;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
    <span style="font-size:.85rem;color:#555;font-weight:bold">集会：</span>
    <?php
    $_m_labels = [''=>'全体','tr'=>'通常礼拝','sr'=>'早朝礼拝','live'=>'ライブ礼拝',
                  'amk'=>'午前祈祷会','pmk'=>'午後祈祷会','livek'=>'ライブ祈祷会',
                  'chy01'=>'朝祈会','sonota'=>'その他集会','02d'=>'土曜開所'];
    foreach ($_m_labels as $_mk => $_ml):
        $_active = ($m === $_mk);
        $_href = '?mode='.$mode.($_mk ? '&m='.$_mk : '');
        if ($mode==='daily')                     $_href .= '&date='  .$target_date;
        if ($mode==='monthly')                   $_href .= '&month='.$target_month;
        if (in_array($mode,['yearly','graph'])) $_href .= '&year='. $target_year;
    ?>
    <a href="<?= $_href ?>" style="padding:5px 12px;border-radius:4px;font-size:.82rem;text-decoration:none;background:<?= $_active?'#2d6a3f':'#e8f5e9' ?>;color:<?= $_active?'#fff':'#2d6a3f' ?>;border:1px solid <?= $_active?'#2d6a3f':'#c8e6c9' ?>;"><?= $_ml ?></a>
    <?php endforeach; ?>
  </div>

  <div class="card">
  <?php
  $base = ['mode' => $mode];
  if ($mode === 'daily')                   $base['date']  = $target_date;
  if ($mode === 'monthly')                 $base['month'] = $target_month;
  if (in_array($mode, ['yearly','graph'])) $base['year']  = $target_year;
  $db_url   = '?' . http_build_query($base + ['db' => 1]);
  $nodb_url = '?' . http_build_query($base);
  $csv_url  = '?' . http_build_query($base + ['csv' => 1]);
  ?>

  <?php /* ===================== 日計 ===================== */ ?>
  <?php if ($mode === 'daily'): ?>

    <div class="nav-bar">
      <label>日付：</label>
      <button type="button" class="btn-nav" onclick="moveDate(-1)">◀ 前日</button>
      <input type="date" id="d-input" value="<?= htmlspecialchars($target_date) ?>"
             onchange="location.href='?mode=daily&date=<?= $m_param ?>'+this.value">
      <button type="button" class="btn-nav" onclick="moveDate(1)">翌日 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=daily&date=<?= $today . $m_param ?>'">今日</button>
      <a href="<?= $show_db ? $nodb_url : $db_url ?>"
         class="btn-db <?= $show_db ? 'active':'' ?>">🗄 DBデータ</a>
      <a href="<?= $csv_url ?>" class="btn-csv">⬇ CSV</a>
    </div>

    <?php if ($dates_with_data): ?>
    <div class="date-list">
      <span class="date-list-label">直近：</span>
      <?php foreach ($dates_with_data as $d): ?>
        <a class="date-chip <?= $d===$target_date?'active':'' ?>"
           href="?mode=daily&date=<?= $d ?>">
          <?= date('m/d', strtotime($d)) ?>
        </a>
      <?php endforeach; ?>
      <?php if ($total_recorded > 5): ?>
        <span class="date-list-label">（全<?= $total_recorded ?>件・日付入力で参照可）</span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $stmt = $pdo->prepare(
        "SELECT m.id, m.name, m.group_name, a.status
         FROM members m
         LEFT JOIN {$att_table} a ON a.member_id=m.id AND a.attend_date=?
         WHERE m.is_active=1 ORDER BY m.group_name, m.kana"
    );
    $stmt->execute([$target_date]);
    $rows       = $stmt->fetchAll();
    $men_rows   = array_values(array_filter($rows, fn($r) => $r['group_name'] === '男性'));
    $women_rows = array_values(array_filter($rows, fn($r) => $r['group_name'] === '女性'));
    $present    = count(array_filter($rows, fn($r) => $r['status'] === '出席'));
    $absent     = count(array_filter($rows, fn($r) => $r['status'] === '欠席'));
    $no_data    = count(array_filter($rows, fn($r) => $r['status'] === null));
    $conf       = $pdo->prepare("SELECT confirmed_at FROM {$cd_table} WHERE attend_date=?");
    $conf->execute([$target_date]);
    $conf_row   = $conf->fetch();
    ?>
    <div class="summary">
      <div class="summary-box"><div class="lbl">在籍</div><div class="val v-total"><?= count($rows) ?></div></div>
      <div class="summary-box"><div class="lbl">出席</div><div class="val v-present"><?= $present ?></div></div>
      <div class="summary-box"><div class="lbl">欠席</div><div class="val v-absent"><?= $absent ?></div></div>
      <div class="summary-box"><div class="lbl">未入力</div><div class="val" style="color:#aaa"><?= $no_data ?></div></div>
      <?php if ($conf_row): ?>
      <div class="summary-box" style="background:#fff8e1">
        <div class="lbl">確定日時</div>
        <div style="font-size:.8rem;font-weight:bold;color:#b8860b;margin-top:4px">
          <?= date('m/d H:i', strtotime($conf_row['confirmed_at'])) ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="columns2">
      <?php foreach ([['men','男性',$men_rows],['women','女性',$women_rows]] as [$cls,$lbl,$list]):
        $g_present = count(array_filter($list, fn($r) => $r['status'] === '出席'));
      ?>
      <div class="section sec-<?= $cls ?>">
        <div class="sec-header"><?= $lbl ?>（<?= $g_present ?>/<?= count($list) ?>名）</div>
        <table><thead><tr><th class="left">氏名</th><th>出欠</th></tr></thead><tbody>
        <?php foreach ($list as $r): $s = $r['status'] ?? '未入力'; ?>
          <tr>
            <td class="left"><?= htmlspecialchars($r['name']) ?></td>
            <td class="s-<?= $s ?>"><?= $s ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($show_db):
      $db_rows = $pdo->prepare(
          "SELECT a.id, a.attend_date, m.name, m.group_name, a.status, a.created_at
           FROM {$att_table} a JOIN members m ON m.id=a.member_id
           WHERE a.attend_date=? ORDER BY m.group_name, m.kana"
      );
      $db_rows->execute([$target_date]);
      $db_data = $db_rows->fetchAll();
    ?>
    <div class="db-card">
      <h3>📋 {$att_table} テーブル — <?= htmlspecialchars($target_date) ?> （<?= count($db_data) ?>件）</h3>
      <div style="overflow-x:auto">
      <table class="db-table">
        <thead><tr><th>id</th><th>attend_date</th><th>name</th><th>group</th><th>status</th><th>created_at</th></tr></thead>
        <tbody>
        <?php foreach ($db_data as $r): ?>
          <tr>
            <td><?= $r['id'] ?></td><td><?= $r['attend_date'] ?></td>
            <td><?= htmlspecialchars($r['name']) ?></td><td><?= htmlspecialchars($r['group_name']) ?></td>
            <td class="<?= $r['status']==='出席'?'db-present':'db-absent' ?>"><?= $r['status'] ?></td>
            <td><?= $r['created_at'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <?php endif; ?>

  <?php /* ===================== 月計 ===================== */ ?>
  <?php elseif ($mode === 'monthly'): ?>

    <div class="nav-bar">
      <label>年月：</label>
      <button type="button" class="btn-nav" onclick="moveMonth(-1)">◀ 前月</button>
      <input type="month" id="m-input" value="<?= htmlspecialchars($target_month) ?>"
             onchange="location.href='?mode=monthly&month=<?= $m_param ?>'+this.value">
      <button type="button" class="btn-nav" onclick="moveMonth(1)">翌月 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=monthly&month=<?= $this_month . $m_param ?>'">今月</button>
      <a href="<?= $show_db ? $nodb_url : $db_url ?>"
         class="btn-db <?= $show_db ? 'active':'' ?>">🗄 DBデータ</a>
      <a href="<?= $csv_url ?>" class="btn-csv">⬇ CSV</a>
    </div>

    <?php
    $stmt = $pdo->prepare(
        "SELECT m.name, m.group_name,
                COALESCE(SUM(a.status='出席'),0) AS present,
                COALESCE(SUM(a.status='欠席'),0) AS absent
         FROM members m
         LEFT JOIN {$att_table} a ON a.member_id=m.id AND DATE_FORMAT(a.attend_date,'%Y-%m')=?
         WHERE m.is_active=1
         GROUP BY m.id, m.name, m.group_name ORDER BY m.group_name, m.kana"
    );
    $stmt->execute([$target_month]);
    $rows       = $stmt->fetchAll();
    $men_rows   = array_values(array_filter($rows, fn($r) => $r['group_name'] === '男性'));
    $women_rows = array_values(array_filter($rows, fn($r) => $r['group_name'] === '女性'));
    $t_present  = array_sum(array_column($rows, 'present'));
    $t_absent   = array_sum(array_column($rows, 'absent'));
    $day_stmt   = $pdo->prepare(
        "SELECT COUNT(DISTINCT attend_date) FROM {$att_table} WHERE DATE_FORMAT(attend_date,'%Y-%m')=?"
    );
    $day_stmt->execute([$target_month]);
    $day_count = (int)$day_stmt->fetchColumn();
    ?>
    <div class="summary">
      <div class="summary-box"><div class="lbl">記録日数</div><div class="val v-total"><?= $day_count ?></div></div>
      <div class="summary-box"><div class="lbl">出席延べ</div><div class="val v-present"><?= $t_present ?></div></div>
      <div class="summary-box"><div class="lbl">欠席延べ</div><div class="val v-absent"><?= $t_absent ?></div></div>
    </div>

    <?php if ($day_count === 0): ?>
      <p class="no-data"><?= htmlspecialchars($target_month) ?> のデータはありません。</p>
    <?php else: ?>
    <div class="columns2">
      <?php foreach ([['men','男性',$men_rows],['women','女性',$women_rows]] as [$cls,$lbl,$list]): ?>
      <div class="section sec-<?= $cls ?>">
        <div class="sec-header"><?= $lbl ?></div>
        <table><thead><tr><th class="left">氏名</th><th>出席数</th><th>欠席数</th><th>出席率</th></tr></thead><tbody>
        <?php foreach ($list as $r):
          $total = $r['present'] + $r['absent'];
          $rate  = $total > 0 ? round($r['present'] / $total * 100) : 0;
        ?>
          <tr>
            <td class="left"><?= htmlspecialchars($r['name']) ?></td>
            <td style="color:#2e7d32"><?= $r['present'] ?></td>
            <td style="color:#c62828"><?= $r['absent'] ?></td>
            <td><?= $rate ?>%<div class="bar"><div class="bar-fill" style="width:<?= $rate ?>%"></div></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($show_db):
      $db_rows = $pdo->prepare(
          "SELECT a.id, a.attend_date, m.name, m.group_name, a.status, cd.confirmed_at
           FROM {$att_table} a JOIN members m ON m.id=a.member_id
           LEFT JOIN {$cd_table} cd ON cd.attend_date=a.attend_date
           WHERE DATE_FORMAT(a.attend_date,'%Y-%m')=?
           ORDER BY a.attend_date, m.group_name, m.kana"
      );
      $db_rows->execute([$target_month]);
      $db_data = $db_rows->fetchAll();
    ?>
    <div class="db-card">
      <h3>📋 {$att_table} テーブル — <?= htmlspecialchars($target_month) ?> （<?= count($db_data) ?>件）</h3>
      <div style="overflow-x:auto">
      <table class="db-table">
        <thead><tr><th>id</th><th>attend_date</th><th>name</th><th>group</th><th>status</th><th>確定</th></tr></thead>
        <tbody>
        <?php $prev=''; foreach($db_data as $r):
          $new = $r['attend_date'] !== $prev; $prev = $r['attend_date']; ?>
          <tr <?= $new ? 'style="border-top:2px solid #45475a"' : '' ?>>
            <td><?= $r['id'] ?></td><td><?= $r['attend_date'] ?></td>
            <td><?= htmlspecialchars($r['name']) ?></td><td><?= htmlspecialchars($r['group_name']) ?></td>
            <td class="<?= $r['status']==='出席'?'db-present':'db-absent' ?>"><?= $r['status'] ?></td>
            <td><?= $r['confirmed_at'] ? '<span class="db-confirmed">🔒 '.date('m/d H:i',strtotime($r['confirmed_at'])).'</span>' : '<span class="db-null">未確定</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <?php endif; ?>

  <?php /* ===================== 年計 ===================== */ ?>
  <?php elseif ($mode === 'yearly'): ?>

    <div class="nav-bar">
      <label>年：</label>
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=yearly&year=<?= ($target_year-1) . $m_param ?>'">◀ 前年</button>
      <span style="font-size:1.1rem;font-weight:bold;padding:0 8px"><?= $target_year ?>年</span>
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=yearly&year=<?= ($target_year+1) . $m_param ?>'">翌年 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=yearly&year=<?= $this_year . $m_param ?>'">今年</button>
      <a href="<?= $csv_url ?>" class="btn-csv">⬇ CSV</a>
    </div>

    <?php
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(attend_date,'%Y-%m') AS ym,
                COUNT(DISTINCT attend_date) AS days,
                SUM(status='出席') AS present,
                SUM(status='欠席') AS absent
         FROM {$att_table} WHERE YEAR(attend_date)=?
         GROUP BY ym ORDER BY ym"
    );
    $stmt->execute([$target_year]);
    $rows      = $stmt->fetchAll();
    $y_days    = array_sum(array_column($rows, 'days'));
    $y_present = array_sum(array_column($rows, 'present'));
    $y_absent  = array_sum(array_column($rows, 'absent'));
    $y_total   = $y_present + $y_absent;
    $y_rate    = $y_total > 0 ? round($y_present / $y_total * 100) . '%' : '-';
    ?>

    <?php if (empty($rows)): ?>
      <p class="no-data"><?= $target_year ?>年のデータはありません。</p>
    <?php else: ?>
    <div class="summary">
      <div class="summary-box"><div class="lbl">記録日数計</div><div class="val v-total"><?= $y_days ?></div></div>
      <div class="summary-box"><div class="lbl">出席延べ計</div><div class="val v-present"><?= $y_present ?></div></div>
      <div class="summary-box"><div class="lbl">欠席延べ計</div><div class="val v-absent"><?= $y_absent ?></div></div>
      <div class="summary-box">
        <div class="lbl">年間出席率</div>
        <div class="val v-total" style="font-size:1.3rem"><?= $y_rate ?></div>
      </div>
    </div>
    <table>
      <thead>
        <tr><th class="left">年月</th><th>記録日数</th><th>出席延べ</th><th>欠席延べ</th><th>出席率</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $total = $r['present'] + $r['absent'];
        $rate  = $total > 0 ? round($r['present'] / $total * 100) : 0;
      ?>
        <tr>
          <td class="left">
            <a href="?mode=monthly&month=<?= $r['ym'] ?><?= $m_param ?>" style="color:#2d6a3f"><?= $r['ym'] ?></a>
          </td>
          <td><?= $r['days'] ?>日</td>
          <td style="color:#2e7d32"><?= $r['present'] ?></td>
          <td style="color:#c62828"><?= $r['absent'] ?></td>
          <td><?= $rate ?>%<div class="bar"><div class="bar-fill" style="width:<?= $rate ?>%"></div></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

  <?php /* ===================== 年間グラフ ===================== */ ?>
  <?php else: ?>

    <div class="nav-bar">
      <label>年：</label>
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=graph&year=<?= ($target_year-1) . $m_param ?>'">◀ 前年</button>
      <span style="font-size:1.1rem;font-weight:bold;padding:0 8px"><?= $target_year ?>年</span>
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=graph&year=<?= ($target_year+1) . $m_param ?>'">翌年 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=graph&year=<?= $this_year . $m_param ?>'">今年</button>
    </div>

    <?php
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(a.attend_date,'%Y-%m') AS ym,
                m.group_name,
                SUM(a.status='出席') AS present,
                SUM(a.status='欠席') AS absent
         FROM {$att_table} a JOIN members m ON m.id=a.member_id
         WHERE YEAR(a.attend_date)=? AND m.is_active=1
         GROUP BY ym, m.group_name ORDER BY ym, m.group_name"
    );
    $stmt->execute([$target_year]);
    $graph_rows = $stmt->fetchAll();

    $months  = [];
    for ($mi = 1; $mi <= 12; $mi++) {
        $months[] = sprintf('%d-%02d', $target_year, $mi);
    }
    $men_p   = array_fill_keys($months, 0);
    $women_p = array_fill_keys($months, 0);
    $men_a   = array_fill_keys($months, 0);
    $women_a = array_fill_keys($months, 0);

    foreach ($graph_rows as $r) {
        $ym = $r['ym'];
        if (!array_key_exists($ym, $men_p)) continue;
        if ($r['group_name'] === '男性') {
            $men_p[$ym] = (int)$r['present'];
            $men_a[$ym] = (int)$r['absent'];
        } else {
            $women_p[$ym] = (int)$r['present'];
            $women_a[$ym] = (int)$r['absent'];
        }
    }

    $rate_data = [];
    foreach ($months as $ym) {
        $p = $men_p[$ym] + $women_p[$ym];
        $t = $p + $men_a[$ym] + $women_a[$ym];
        $rate_data[] = $t > 0 ? round($p / $t * 100, 1) : null;
    }

    $labels   = array_map(fn($ym) => ltrim(substr($ym, 5), '0') . '月', $months);
    $has_data = array_sum(array_values($men_p)) + array_sum(array_values($women_p)) > 0;

    $j_lbl  = json_encode($labels,               JSON_UNESCAPED_UNICODE);
    $j_mp   = json_encode(array_values($men_p));
    $j_wp   = json_encode(array_values($women_p));
    $j_ma   = json_encode(array_values($men_a));
    $j_wa   = json_encode(array_values($women_a));
    $j_rate = json_encode($rate_data);
    ?>

    <?php if (!$has_data): ?>
      <p class="no-data"><?= $target_year ?>年のデータはありません。</p>
    <?php else: ?>
    <div class="chart-wrap">
      <canvas id="annualChart"></canvas>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    new Chart(document.getElementById('annualChart').getContext('2d'), {
      type: 'bar',
      data: {
        labels: <?= $j_lbl ?>,
        datasets: [
          {
            label: '男性 出席',
            data: <?= $j_mp ?>,
            backgroundColor: 'rgba(33,150,243,0.75)',
            yAxisID: 'y',
          },
          {
            label: '女性 出席',
            data: <?= $j_wp ?>,
            backgroundColor: 'rgba(233,30,99,0.55)',
            yAxisID: 'y',
          },

        ]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top' },
          title: {
            display: true,
            text: '<?= $target_year ?>年　月別出席状況',
            font: { size: 15 }, padding: { bottom: 14 }
          },

        },
        scales: {
          y: {
            type: 'linear', position: 'left',
            title: { display: true, text: '人数' },
            grid: { color: '#e8e8e8' },
            ticks: { stepSize: 5 }, beginAtZero: true,
          },

        }
      }
    });
    </script>
    <?php endif; ?>

  <?php endif; ?>
  </div>
</div>

<script>
function moveDate(delta) {
  const d = new Date(document.getElementById('d-input').value + 'T00:00:00');
  d.setDate(d.getDate() + delta);
  location.href = '?mode=daily&date=<?= $m_param ?>' + d.toISOString().slice(0, 10);
}
function moveMonth(delta) {
  const [y, m] = document.getElementById('m-input').value.split('-').map(Number);
  const d = new Date(y, m - 1 + delta, 1);
  const ym = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
  location.href = '?mode=monthly&month=<?= $m_param ?>' + ym;
}
</script>
</body>
</html>
