<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$mode        = $_GET['mode'] ?? 'daily';
$today       = date('Y-m-d');
$this_month  = date('Y-m');
$this_year   = (int)date('Y');
$target_date  = $_GET['date']  ?? $today;
$target_month = $_GET['month'] ?? $this_month;
$target_year  = (int)($_GET['year'] ?? $this_year);

$services = [
    'amk'   => '午前祈祷会',
    'pmk'   => '午後祈祷会',
    'livek' => 'ライブ祈祷会',
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>祈祷会合計人数</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui,-apple-system,"Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif; background:#e6f4e8; color:#333; font-size:15px; }
header { background:#2d6a3f; color:#fff; padding:10px 20px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.site-title { font-size:1rem; font-weight:bold; color:#fff; text-decoration:none; margin-right:6px; }
nav a { color:#cef0d4; text-decoration:none; font-size:.82rem; padding:5px 10px; border-radius:4px; }
nav a:hover { color:#fff; background:rgba(255,255,255,.18); }
nav a.logout { margin-left:auto; color:#ffd0d0; }
.container { max-width:900px; margin:16px auto; padding:0 14px; }
.tabs { display:flex; gap:4px; margin-bottom:0; flex-wrap:wrap; }
.tab { padding:9px 24px; border-radius:6px 6px 0 0; text-decoration:none; font-size:.92rem; background:#c8e6c9; color:#2d6a3f; }
.tab.active { background:#2d6a3f; color:#fff; }
.tab:hover:not(.active) { background:#a5d6a7; }
.card { background:#fff; border-radius:0 8px 8px 8px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,.1); margin-bottom:16px; }
.nav-bar { display:flex; align-items:center; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
.nav-bar label { font-weight:bold; font-size:.9rem; }
.btn-nav { padding:6px 12px; background:#fff; border:1px solid #bbb; border-radius:4px; cursor:pointer; font-size:.9rem; }
.btn-nav:hover { background:#e8f5e9; }
.btn-today { padding:6px 12px; background:#2d6a3f; border:none; border-radius:4px; cursor:pointer; font-size:.85rem; color:#fff; }
.summary { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
.summary-box { flex:1; min-width:110px; background:#f1f8e9; border-radius:8px; padding:12px; text-align:center; }
.summary-box .lbl { font-size:.78rem; color:#666; margin-bottom:3px; }
.summary-box .val { font-size:1.6rem; font-weight:bold; color:#2d6a3f; }
table { width:100%; border-collapse:collapse; margin-bottom:16px; }
th { background:#f1f8e9; padding:8px 12px; text-align:center; font-size:.86rem; border-bottom:2px solid #c8e6c9; }
th.left { text-align:left; }
td { padding:8px 12px; text-align:center; font-size:.86rem; border-bottom:1px solid #eee; }
td.left { text-align:left; }
tr.total-row td { font-weight:bold; background:#f1f8e9; border-top:2px solid #a5d6a7; }
.chart-wrap { position:relative; width:100%; max-width:860px; margin:0 auto; }
.no-data { text-align:center; color:#999; padding:24px; }
</style>
</head>
<body>
<header>
  <a class="site-title" href="main02.php">祈祷会合計人数</a>
  <nav>
    <a href="index_amk.php">午前祈祷会</a>
    <a href="index_pmk.php">午後祈祷会</a>
    <a href="index_livek.php">ライブ祈祷会</a>
    <a href="main02.php">メニュー画面へ</a>
    <a href="logout.php" class="logout">ログアウト</a>
  </nav>
</header>

<div class="container">
  <div class="tabs">
    <a class="tab <?= $mode==='daily'  ?'active':'' ?>" href="?mode=daily">日計</a>
    <a class="tab <?= $mode==='monthly'?'active':'' ?>" href="?mode=monthly">月計</a>
    <a class="tab <?= $mode==='yearly' ?'active':'' ?>" href="?mode=yearly">年計</a>
    <a class="tab <?= $mode==='graph'  ?'active':'' ?>" href="?mode=graph">グラフ</a>
  </div>
  <div class="card">

  <?php if ($mode === 'daily'): ?>
    <div class="nav-bar">
      <label>日付：</label>
      <button type="button" class="btn-nav" onclick="moveDate(-1)">◀ 前日</button>
      <input type="date" id="d-input" value="<?= htmlspecialchars($target_date) ?>"
             onchange="location.href='?mode=daily&date='+this.value">
      <button type="button" class="btn-nav" onclick="moveDate(1)">翌日 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=daily&date=<?= $today ?>'">今日</button>
    </div>
    <?php
    $total_present = 0;
    $rows_by_service = [];
    foreach ($services as $key => $label) {
        $tbl = "attendance_{$key}";
        $stmt = $pdo->prepare(
            "SELECT SUM(status='出席') AS present, SUM(status='欠席') AS absent
             FROM {$tbl} WHERE attend_date=?"
        );
        $stmt->execute([$target_date]);
        $r = $stmt->fetch();
        $rows_by_service[$key] = ['label'=>$label, 'present'=>(int)$r['present'], 'absent'=>(int)$r['absent']];
        $total_present += (int)$r['present'];
    }
    ?>
    <div class="summary">
      <div class="summary-box"><div class="lbl">合計出席</div><div class="val"><?= $total_present ?></div></div>
      <?php foreach ($rows_by_service as $r): ?>
      <div class="summary-box"><div class="lbl"><?= $r['label'] ?></div><div class="val"><?= $r['present'] ?></div></div>
      <?php endforeach; ?>
    </div>
    <table>
      <thead><tr><th class="left">集会</th><th>出席数</th><th>欠席数</th></tr></thead>
      <tbody>
      <?php foreach ($rows_by_service as $r): ?>
        <tr><td class="left"><?= $r['label'] ?></td><td style="color:#2e7d32"><?= $r['present'] ?></td><td style="color:#c62828"><?= $r['absent'] ?></td></tr>
      <?php endforeach; ?>
      <tr class="total-row"><td class="left">合計</td><td style="color:#2e7d32"><?= $total_present ?></td><td></td></tr>
      </tbody>
    </table>

  <?php elseif ($mode === 'monthly'): ?>
    <div class="nav-bar">
      <label>年月：</label>
      <button type="button" class="btn-nav" onclick="moveMonth(-1)">◀ 前月</button>
      <input type="month" id="m-input" value="<?= htmlspecialchars($target_month) ?>"
             onchange="location.href='?mode=monthly&month='+this.value">
      <button type="button" class="btn-nav" onclick="moveMonth(1)">翌月 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=monthly&month=<?= $this_month ?>'">今月</button>
    </div>
    <?php
    $total_present = 0;
    $rows_by_service = [];
    foreach ($services as $key => $label) {
        $tbl = "attendance_{$key}";
        $stmt = $pdo->prepare(
            "SELECT SUM(status='出席') AS present, SUM(status='欠席') AS absent,
                    COUNT(DISTINCT attend_date) AS days
             FROM {$tbl} WHERE DATE_FORMAT(attend_date,'%Y-%m')=?"
        );
        $stmt->execute([$target_month]);
        $r = $stmt->fetch();
        $rows_by_service[$key] = ['label'=>$label, 'present'=>(int)$r['present'], 'absent'=>(int)$r['absent'], 'days'=>(int)$r['days']];
        $total_present += (int)$r['present'];
    }
    ?>
    <div class="summary">
      <div class="summary-box"><div class="lbl">合計出席延べ</div><div class="val"><?= $total_present ?></div></div>
      <?php foreach ($rows_by_service as $r): ?>
      <div class="summary-box"><div class="lbl"><?= $r['label'] ?></div><div class="val"><?= $r['present'] ?></div></div>
      <?php endforeach; ?>
    </div>
    <table>
      <thead><tr><th class="left">集会</th><th>記録日数</th><th>出席延べ</th><th>欠席延べ</th></tr></thead>
      <tbody>
      <?php foreach ($rows_by_service as $r): ?>
        <tr><td class="left"><?= $r['label'] ?></td><td><?= $r['days'] ?>日</td><td style="color:#2e7d32"><?= $r['present'] ?></td><td style="color:#c62828"><?= $r['absent'] ?></td></tr>
      <?php endforeach; ?>
      <tr class="total-row"><td class="left">合計</td><td></td><td style="color:#2e7d32"><?= $total_present ?></td><td></td></tr>
      </tbody>
    </table>

  <?php elseif ($mode === 'yearly'): ?>
    <div class="nav-bar">
      <label>年：</label>
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=yearly&year=<?= $target_year-1 ?>'">◀ 前年</button>
      <span style="font-size:1.1rem;font-weight:bold;padding:0 8px"><?= $target_year ?>年</span>
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=yearly&year=<?= $target_year+1 ?>'">翌年 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=yearly&year=<?= $this_year ?>'">今年</button>
    </div>
    <?php
    $months = [];
    for ($m = 1; $m <= 12; $m++) $months[] = sprintf('%d-%02d', $target_year, $m);
    $table_data = [];
    $grand_total = 0;
    foreach ($months as $ym) $table_data[$ym] = ['amk'=>0,'pmk'=>0,'livek'=>0];
    foreach ($services as $key => $label) {
        $tbl = "attendance_{$key}";
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(attend_date,'%Y-%m') AS ym, SUM(status='出席') AS present
             FROM {$tbl} WHERE YEAR(attend_date)=? GROUP BY ym"
        );
        $stmt->execute([$target_year]);
        foreach ($stmt->fetchAll() as $r) {
            if (isset($table_data[$r['ym']])) {
                $table_data[$r['ym']][$key] = (int)$r['present'];
                $grand_total += (int)$r['present'];
            }
        }
    }
    ?>
    <div class="summary">
      <div class="summary-box"><div class="lbl"><?= $target_year ?>年 合計出席延べ</div><div class="val"><?= $grand_total ?></div></div>
    </div>
    <table>
      <thead><tr><th class="left">年月</th><th>午前祈祷会</th><th>午後祈祷会</th><th>ライブ祈祷会</th><th>合計</th></tr></thead>
      <tbody>
      <?php $y_amk=0;$y_pmk=0;$y_livek=0; foreach ($table_data as $ym => $d):
        $sum = $d['amk']+$d['pmk']+$d['livek']; $y_amk+=$d['amk'];$y_pmk+=$d['pmk'];$y_livek+=$d['livek']; ?>
        <tr>
          <td class="left"><a href="?mode=monthly&month=<?= $ym ?>" style="color:#2d6a3f"><?= $ym ?></a></td>
          <td><?= $d['amk']   ?: '-' ?></td>
          <td><?= $d['pmk']   ?: '-' ?></td>
          <td><?= $d['livek'] ?: '-' ?></td>
          <td style="font-weight:bold"><?= $sum ?: '-' ?></td>
        </tr>
      <?php endforeach; ?>
      <tr class="total-row">
        <td class="left">合計</td>
        <td><?= $y_amk ?></td><td><?= $y_pmk ?></td><td><?= $y_livek ?></td>
        <td><?= $y_amk+$y_pmk+$y_livek ?></td>
      </tr>
      </tbody>
    </table>

  <?php else: // graph ?>
    <div class="nav-bar">
      <label>年：</label>
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=graph&year=<?= $target_year-1 ?>'">◀ 前年</button>
      <span style="font-size:1.1rem;font-weight:bold;padding:0 8px"><?= $target_year ?>年</span>
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=graph&year=<?= $target_year+1 ?>'">翌年 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=graph&year=<?= $this_year ?>'">今年</button>
    </div>
    <?php
    $months = [];
    for ($m = 1; $m <= 12; $m++) $months[] = sprintf('%d-%02d', $target_year, $m);
    $g_amk = $g_pmk = $g_livek = array_fill_keys($months, 0);
    foreach ([['amk',&$g_amk],['pmk',&$g_pmk],['livek',&$g_livek]] as [$key, &$arr]) {
        $tbl = "attendance_{$key}";
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(attend_date,'%Y-%m') AS ym, SUM(status='出席') AS present
             FROM {$tbl} WHERE YEAR(attend_date)=? GROUP BY ym"
        );
        $stmt->execute([$target_year]);
        foreach ($stmt->fetchAll() as $r) {
            if (isset($arr[$r['ym']])) $arr[$r['ym']] = (int)$r['present'];
        }
    }
    $labels  = array_map(fn($ym) => ltrim(substr($ym,5),'0').'月', $months);
    $j_lbl   = json_encode($labels, JSON_UNESCAPED_UNICODE);
    $j_amk   = json_encode(array_values($g_amk));
    $j_pmk   = json_encode(array_values($g_pmk));
    $j_livek = json_encode(array_values($g_livek));
    $has_data = array_sum($g_amk)+array_sum($g_pmk)+array_sum($g_livek) > 0;
    ?>
    <?php if (!$has_data): ?>
      <p class="no-data"><?= $target_year ?>年のデータはありません。</p>
    <?php else: ?>
    <div class="chart-wrap">
      <canvas id="totalChart"></canvas>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    new Chart(document.getElementById('totalChart').getContext('2d'), {
      type: 'bar',
      data: {
        labels: <?= $j_lbl ?>,
        datasets: [
          { label: '午前祈祷会',   data: <?= $j_amk ?>,   backgroundColor: 'rgba(33,150,243,0.75)' },
          { label: '午後祈祷会',   data: <?= $j_pmk ?>,   backgroundColor: 'rgba(255,152,0,0.75)' },
          { label: 'ライブ祈祷会', data: <?= $j_livek ?>, backgroundColor: 'rgba(76,175,80,0.75)' },
        ]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'top' },
          title: { display: true, text: '<?= $target_year ?>年　祈祷会別月別出席数', font: { size: 15 }, padding: { bottom: 14 } }
        },
        scales: {
          y: { beginAtZero: true, title: { display: true, text: '人数' }, ticks: { stepSize: 5 } }
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
  location.href = '?mode=daily&date=' + d.toISOString().slice(0,10);
}
function moveMonth(delta) {
  const [y,m] = document.getElementById('m-input').value.split('-').map(Number);
  const d = new Date(y, m-1+delta, 1);
  const ym = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0');
  location.href = '?mode=monthly&month=' + ym;
}
</script>
</body>
</html>
