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
$order        = (($_GET['order'] ?? 'asc') === 'desc') ? 'desc' : 'asc';
$order_sql    = $order === 'desc' ? 'DESC' : 'ASC';
$show_db      = isset($_GET['db']);
$do_csv       = isset($_GET['csv']);

// ---- CSV出力 ----
if ($do_csv) {
    $rows_csv = [];
    if ($mode === 'daily') {
        $filename = 'daily_' . $target_date . '.csv';
        $stmt = $pdo->prepare(
            "SELECT m.name, m.group_name, COALESCE(a.status,'未入力') AS status
             FROM members m
             LEFT JOIN attendance a ON a.member_id=m.id AND a.attend_date=?
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
        foreach ($men_data as $r) { $rows_csv[] = [$r['name'], $r['group_name'], $r['status']]; }
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
             LEFT JOIN attendance a ON a.member_id=m.id
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

    } else {
        $filename = 'yearly_' . $target_year . '.csv';
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(attend_date,'%Y-%m') AS ym,
                    COUNT(DISTINCT attend_date) AS days,
                    SUM(status='出席') AS present,
                    SUM(status='欠席') AS absent
             FROM attendance WHERE YEAR(attend_date)=?
             GROUP BY ym ORDER BY ym $order_sql"
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
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM（Excel文字化け防止）
    foreach ($rows_csv as $row) { fputcsv($fp, $row); }
    fclose($fp);
    exit;
}

// 記録済み日付（直近5件）
$dates_with_data = $pdo->query(
    "SELECT DISTINCT attend_date FROM attendance ORDER BY attend_date DESC LIMIT 5"
)->fetchAll(PDO::FETCH_COLUMN);
$total_recorded = (int)$pdo->query(
    "SELECT COUNT(DISTINCT attend_date) FROM attendance"
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>つばさ名簿 - レポート</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #f0f4f8; color: #333; font-size: 15px; }
header { background: #2c5f8a; color: #fff; padding: 12px 20px; display: flex; align-items: center; gap: 10px; }
header h1 { font-size: 1.1rem; }
nav a { color: #cde; text-decoration: none; font-size: .85rem; margin-left: 14px; }
nav a:hover { color: #fff; }
.container { max-width: 980px; margin: 16px auto; padding: 0 14px; }
.tabs { display: flex; gap: 4px; margin-bottom: 0; }
.tab { padding: 9px 24px; border-radius: 6px 6px 0 0; text-decoration: none; font-size: .95rem; background: #dce8f5; color: #2c5f8a; }
.tab.active { background: #2c5f8a; color: #fff; }
.card { background: #fff; border-radius: 0 8px 8px 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.1); margin-bottom: 16px; }
.nav-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
.nav-bar label { font-weight: bold; font-size: .9rem; }
.nav-bar input { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: .95rem; }
.btn-nav   { padding: 6px 12px; background: #fff; border: 1px solid #bbb; border-radius: 4px; cursor: pointer; font-size: .9rem; }
.btn-nav:hover { background: #e8f0f8; border-color: #2c5f8a; }
.btn-today { padding: 6px 12px; background: #2c5f8a; border: none; border-radius: 4px; cursor: pointer; font-size: .85rem; color: #fff; }
.btn-today:hover { background: #1a4a70; }
.btn-db  { padding: 6px 14px; border: 1px solid #888; border-radius: 4px; font-size: .82rem; cursor: pointer; background: #fff; color: #555; text-decoration: none; white-space: nowrap; }
.btn-db.active { background: #3a3a3a; color: #fff; border-color: #3a3a3a; }
.btn-db:hover { background: #e0e0e0; }
.btn-csv { padding: 6px 14px; background: #2e7d32; color: #fff; border-radius: 4px; font-size: .82rem; text-decoration: none; white-space: nowrap; border: none; }
.btn-csv:hover { background: #1b5e20; }
.btn-order { padding: 6px 14px; background: #6d4c41; color: #fff; border-radius: 4px; font-size: .82rem; text-decoration: none; white-space: nowrap; border: none; }
.btn-order:hover { background: #5d4037; }
.btn-order.active { background: #3e2723; pointer-events: none; }
.date-list { margin-bottom: 14px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.date-chip { padding: 4px 10px; border-radius: 14px; background: #e8f0f8; color: #2c5f8a; text-decoration: none; font-size: .82rem; border: 1px solid #c5d8ef; }
.date-chip:hover { background: #2c5f8a; color: #fff; }
.date-chip.active { background: #2c5f8a; color: #fff; }
.date-list-label { font-size: .8rem; color: #888; }
.summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.summary-box { flex: 1; min-width: 90px; background: #f0f4f8; border-radius: 8px; padding: 12px; text-align: center; }
.summary-box .lbl { font-size: .78rem; color: #666; margin-bottom: 3px; }
.summary-box .val { font-size: 1.7rem; font-weight: bold; }
.v-total   { color: #2c5f8a; }
.v-present { color: #2e7d32; }
.v-absent  { color: #c62828; }
.columns2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media(max-width:600px){ .columns2 { grid-template-columns:1fr; } }
.section { border-radius: 8px; overflow: hidden; border: 1px solid #e0e8f0; }
.sec-header { padding: 8px 12px; font-weight: bold; font-size: .88rem; }
.sec-men   .sec-header { background: #d0e8ff; color: #1a5c99; }
.sec-women .sec-header { background: #ffd0e8; color: #99195c; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f0f4f8; }
th, td { padding: 8px 10px; text-align: center; font-size: .86rem; border-bottom: 1px solid #eee; }
td.left { text-align: left; }
tbody tr:hover { background: #f5f9ff; }
.s-出席 { color: #2e7d32; font-weight: bold; }
.s-欠席 { color: #c62828; }
.s-未入力 { color: #aaa; }
.bar { height: 7px; background: #e0e0e0; border-radius: 4px; overflow: hidden; margin-top: 3px; }
.bar-fill { height: 100%; background: #4caf50; border-radius: 4px; }
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
</style>
</head>
<body>
<header>
  <h1>つばさ名簿</h1>
  <nav>
    <a href="index.php">出欠入力</a>
    <a href="members.php">会員管理</a>
    <a href="report.php">レポート</a>
    <a href="timecard_report.php?mode=monthly">月計</a>
    <a href="timecard_report.php?mode=yearly">年計</a>
    <a href="logout.php" style="margin-left:auto;color:#ffd0d0;">ログアウト</a>
  </nav>
</header>

<div class="container">
  <div class="tabs">
    <a class="tab <?= $mode==='daily'   ? 'active':'' ?>" href="?mode=daily&order=<?= $order ?>">日計</a>
    <a class="tab <?= $mode==='monthly' ? 'active':'' ?>" href="?mode=monthly&order=<?= $order ?>">月計</a>
    <a class="tab <?= $mode==='yearly'  ? 'active':'' ?>" href="?mode=yearly&order=<?= $order ?>">年計</a>
  </div>
  <div class="card">
  <?php
  $base = ['mode'=>$mode, 'order'=>$order];
  if ($mode==='daily')   $base['date']  = $target_date;
  if ($mode==='monthly') $base['month'] = $target_month;
  if ($mode==='yearly')  $base['year']  = $target_year;
  $db_url   = '?' . http_build_query($base + ['db'=>1]);
  $nodb_url = '?' . http_build_query($base);
  $csv_url  = '?' . http_build_query($base + ['csv'=>1]);
  $desc_url = '?' . http_build_query(array_merge($base, ['order'=>'desc']));
  ?>

  <?php if ($mode === 'daily'): ?>

    <div class="nav-bar">
      <label>日付：</label>
      <button type="button" class="btn-nav" onclick="moveDate(-1)">◀ 前日</button>
      <input type="date" id="d-input" value="<?= htmlspecialchars($target_date) ?>"
             onchange="location.href='?mode=daily&date='+this.value+'&order=<?= $order ?>'">
      <button type="button" class="btn-nav" onclick="moveDate(1)">翌日 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=daily&date=<?= $today ?>&order=<?= $order ?>'">今日</button>
      <a href="<?= $show_db ? $nodb_url : $db_url ?>"
         class="btn-db <?= $show_db ? 'active':'' ?>">🗄 DBデータ</a>
      <a href="<?= $csv_url ?>" class="btn-csv">⬇ CSV</a>
      <a href="<?= $desc_url ?>" class="btn-order <?= $order === 'desc' ? 'active' : '' ?>">降順</a>
    </div>

    <?php if ($dates_with_data): ?>
    <div class="date-list">
      <span class="date-list-label">直近：</span>
      <?php foreach ($dates_with_data as $d): ?>
        <a class="date-chip <?= $d===$target_date?'active':'' ?>"
           href="?mode=daily&date=<?= $d ?>&order=<?= $order ?>">
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
         LEFT JOIN attendance a ON a.member_id=m.id AND a.attend_date=?
         WHERE m.is_active=1 ORDER BY m.group_name, m.kana"
    );
    $stmt->execute([$target_date]);
    $rows = $stmt->fetchAll();
    $men_rows   = array_values(array_filter($rows, fn($r)=>$r['group_name']==='男性'));
    $women_rows = array_values(array_filter($rows, fn($r)=>$r['group_name']==='女性'));
    $present = count(array_filter($rows, fn($r)=>$r['status']==='出席'));
    $absent  = count(array_filter($rows, fn($r)=>$r['status']==='欠席'));
    $no_data = count(array_filter($rows, fn($r)=>$r['status']===null));
    $conf = $pdo->prepare("SELECT confirmed_at FROM confirmed_dates WHERE attend_date=?");
    $conf->execute([$target_date]);
    $conf_row = $conf->fetch();
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
      <?php foreach([['men','男性',$men_rows],['women','女性',$women_rows]] as [$cls,$lbl,$list]): ?>
      <div class="section sec-<?= $cls ?>">
        <div class="sec-header"><?= $lbl ?>（<?= count(array_filter($list,fn($r)=>$r['status']==='出席')) ?>/<?= count($list) ?>名）</div>
        <table><thead><tr><th class="left">氏名</th><th>出欠</th></tr></thead><tbody>
        <?php foreach($list as $r): $s=$r['status']??'未入力'; ?>
          <tr><td class="left"><?= htmlspecialchars($r['name']) ?></td>
              <td class="s-<?= $s ?>"><?= $s ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($show_db):
      $db_rows = $pdo->prepare(
          "SELECT a.id, a.attend_date, m.name, m.group_name, a.status, a.created_at
           FROM attendance a JOIN members m ON m.id=a.member_id
           WHERE a.attend_date=? ORDER BY m.group_name, m.kana"
      );
      $db_rows->execute([$target_date]);
      $db_data = $db_rows->fetchAll();
    ?>
    <div class="db-card">
      <h3>📋 attendance テーブル — <?= htmlspecialchars($target_date) ?> （<?= count($db_data) ?>件）</h3>
      <div style="overflow-x:auto">
      <table class="db-table">
        <thead><tr><th>id</th><th>attend_date</th><th>name</th><th>group</th><th>status</th><th>created_at</th></tr></thead>
        <tbody>
        <?php foreach($db_data as $r): ?>
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

  <?php elseif ($mode === 'monthly'): ?>

    <div class="nav-bar">
      <label>年月：</label>
      <button type="button" class="btn-nav" onclick="moveMonth(-1)">◀ 前月</button>
      <input type="month" id="m-input" value="<?= htmlspecialchars($target_month) ?>"
             onchange="location.href='?mode=monthly&month='+this.value+'&order=<?= $order ?>'">
      <button type="button" class="btn-nav" onclick="moveMonth(1)">翌月 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=monthly&month=<?= $this_month ?>&order=<?= $order ?>'">今月</button>
      <a href="<?= $show_db ? $nodb_url : $db_url ?>"
         class="btn-db <?= $show_db ? 'active':'' ?>">🗄 DBデータ</a>
      <a href="<?= $csv_url ?>" class="btn-csv">⬇ CSV</a>
      <a href="<?= $desc_url ?>" class="btn-order <?= $order === 'desc' ? 'active' : '' ?>">降順</a>
    </div>

    <?php
    $stmt = $pdo->prepare(
        "SELECT m.name, m.group_name,
                COALESCE(SUM(a.status='出席'),0) AS present,
                COALESCE(SUM(a.status='欠席'),0) AS absent
         FROM members m
         LEFT JOIN attendance a ON a.member_id=m.id AND DATE_FORMAT(a.attend_date,'%Y-%m')=?
         WHERE m.is_active=1
         GROUP BY m.id, m.name, m.group_name ORDER BY m.group_name, m.kana"
    );
    $stmt->execute([$target_month]);
    $rows = $stmt->fetchAll();
    $men_rows   = array_values(array_filter($rows, fn($r)=>$r['group_name']==='男性'));
    $women_rows = array_values(array_filter($rows, fn($r)=>$r['group_name']==='女性'));
    $t_present = array_sum(array_column($rows,'present'));
    $t_absent  = array_sum(array_column($rows,'absent'));
    $day_stmt  = $pdo->prepare("SELECT COUNT(DISTINCT attend_date) FROM attendance WHERE DATE_FORMAT(attend_date,'%Y-%m')=?");
    $day_stmt->execute([$target_month]);
    $day_count = (int)$day_stmt->fetchColumn();
    ?>
    <div class="summary">
      <div class="summary-box"><div class="lbl">記録日数</div><div class="val v-total"><?= $day_count ?></div></div>
      <div class="summary-box"><div class="lbl">出席延べ</div><div class="val v-present"><?= $t_present ?></div></div>
      <div class="summary-box"><div class="lbl">欠席延べ</div><div class="val v-absent"><?= $t_absent ?></div></div>
    </div>
    <?php if ($day_count===0): ?>
      <p class="no-data"><?= htmlspecialchars($target_month) ?> のデータはありません。</p>
    <?php else: ?>
    <div class="columns2">
      <?php foreach([['men','男性',$men_rows],['women','女性',$women_rows]] as [$cls,$lbl,$list]): ?>
      <div class="section sec-<?= $cls ?>">
        <div class="sec-header"><?= $lbl ?></div>
        <table><thead><tr><th class="left">氏名</th><th>出席数</th><th>欠席数</th><th>出席率</th></tr></thead><tbody>
        <?php foreach($list as $r):
          $total=$r['present']+$r['absent'];
          $rate=$total>0?round($r['present']/$total*100):0;
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
           FROM attendance a JOIN members m ON m.id=a.member_id
           LEFT JOIN confirmed_dates cd ON cd.attend_date=a.attend_date
           WHERE DATE_FORMAT(a.attend_date,'%Y-%m')=?
           ORDER BY a.attend_date $order_sql, m.group_name, m.kana"
      );
      $db_rows->execute([$target_month]);
      $db_data = $db_rows->fetchAll();
    ?>
    <div class="db-card">
      <h3>📋 attendance テーブル — <?= htmlspecialchars($target_month) ?> （<?= count($db_data) ?>件）</h3>
      <div style="overflow-x:auto">
      <table class="db-table">
        <thead><tr><th>id</th><th>attend_date</th><th>name</th><th>group</th><th>status</th><th>確定</th></tr></thead>
        <tbody>
        <?php $prev=''; foreach($db_data as $r):
          $new=$r['attend_date']!==$prev; $prev=$r['attend_date']; ?>
          <tr <?= $new?'style="border-top:2px solid #45475a"':'' ?>>
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

  <?php else: // yearly ?>

    <div class="nav-bar">
      <label>年：</label>
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=yearly&year=<?= $target_year-1 ?>&order=<?= $order ?>'">◀ 前年</button>
      <span style="font-size:1.1rem;font-weight:bold;padding:0 8px"><?= $target_year ?>年</span>
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=yearly&year=<?= $target_year+1 ?>&order=<?= $order ?>'">翌年 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=yearly&year=<?= $this_year ?>&order=<?= $order ?>'">今年</button>
      <a href="<?= $show_db ? $nodb_url : $db_url ?>"
         class="btn-db <?= $show_db ? 'active':'' ?>">🗄 DBデータ</a>
      <a href="<?= $csv_url ?>" class="btn-csv">⬇ CSV</a>
      <a href="<?= $desc_url ?>" class="btn-order <?= $order === 'desc' ? 'active' : '' ?>">降順</a>
    </div>

    <?php
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(attend_date,'%Y-%m') AS ym,
                COUNT(DISTINCT attend_date) AS days,
                SUM(status='出席') AS present,
                SUM(status='欠席') AS absent
         FROM attendance WHERE YEAR(attend_date)=?
         GROUP BY ym ORDER BY ym $order_sql"
    );
    $stmt->execute([$target_year]);
    $rows = $stmt->fetchAll();
    ?>
    <?php if (empty($rows)): ?>
      <p class="no-data"><?= $target_year ?>年のデータはありません。</p>
    <?php else: ?>
    <table>
      <thead><tr><th class="left">年月</th><th>記録日数</th><th>出席延べ</th><th>欠席延べ</th><th>出席率</th></tr></thead>
      <tbody>
      <?php foreach($rows as $r):
        $total=$r['present']+$r['absent'];
        $rate=$total>0?round($r['present']/$total*100):0;
      ?>
        <tr>
          <td class="left"><a href="?mode=monthly&month=<?= $r['ym'] ?>&order=<?= $order ?>" style="color:#2c5f8a"><?= $r['ym'] ?></a></td>
          <td><?= $r['days'] ?>日</td>
          <td style="color:#2e7d32"><?= $r['present'] ?></td>
          <td style="color:#c62828"><?= $r['absent'] ?></td>
          <td><?= $rate ?>%<div class="bar"><div class="bar-fill" style="width:<?= $rate ?>%"></div></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <?php if ($show_db):
      $db_rows = $pdo->prepare(
          "SELECT a.id, a.attend_date, m.name, m.group_name, a.status, cd.confirmed_at
           FROM attendance a JOIN members m ON m.id=a.member_id
           LEFT JOIN confirmed_dates cd ON cd.attend_date=a.attend_date
           WHERE YEAR(a.attend_date)=?
           ORDER BY a.attend_date $order_sql, m.group_name, m.kana"
      );
      $db_rows->execute([$target_year]);
      $db_data = $db_rows->fetchAll();
    ?>
    <div class="db-card">
      <h3>📋 attendance テーブル — <?= $target_year ?>年 （<?= count($db_data) ?>件）</h3>
      <div style="overflow-x:auto">
      <table class="db-table">
        <thead><tr><th>id</th><th>attend_date</th><th>name</th><th>group</th><th>status</th><th>確定</th></tr></thead>
        <tbody>
        <?php $prev=''; foreach($db_data as $r):
          $new=$r['attend_date']!==$prev; $prev=$r['attend_date']; ?>
          <tr <?= $new?'style="border-top:2px solid #45475a"':'' ?>>
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

  <?php endif; ?>
  </div>
</div>

<script>
const orderParam = '<?= $order ?>';
function moveDate(delta) {
  const d = new Date(document.getElementById('d-input').value + 'T00:00:00');
  d.setDate(d.getDate() + delta);
  location.href = '?mode=daily&date=' + d.toISOString().slice(0,10) + '&order=' + orderParam;
}
function moveMonth(delta) {
  const [y,m] = document.getElementById('m-input').value.split('-').map(Number);
  const d = new Date(y, m-1+delta, 1);
  const ym = d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0');
  location.href = '?mode=monthly&month='+ym+'&order='+orderParam;
}
</script>
</body>
</html>
