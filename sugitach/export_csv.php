<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/database.php';

$pdo = getDB();

// CSV セル安全エスケープ
function csvCell(string $val): string {
    if (preg_match('/^[=+\-@\t\r]/', $val)) $val = "'" . $val;
    return '"' . str_replace('"', '""', $val) . '"';
}
function csvRow(array $cells): string {
    return implode(',', array_map('csvCell', array_map('strval', $cells))) . "\r\n";
}

$BOM  = "\xEF\xBB\xBF";
$type = $_GET['type'] ?? '';

// ── 日計 CSV ─────────────────────────────────────────────
if ($type === 'daily') {
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { http_response_code(400); exit('不正な日付'); }

    $label = date('Y年n月j日', strtotime($date));

    // 出席者名（男女別）
    $stmt = $pdo->prepare("
        SELECT r.name, r.gender
        FROM attendance a
        JOIN roster r ON r.id = a.roster_id
        WHERE a.attendance_date = ? AND a.is_present = 1
        ORDER BY r.gender DESC, r.sort_order
    ");
    $stmt->execute([$date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $men_names   = array_filter($rows, fn($r) => $r['gender'] === 'male');
    $women_names = array_filter($rows, fn($r) => $r['gender'] === 'female');
    $men_cnt     = count($men_names);
    $women_cnt   = count($women_names);

    $csv  = $BOM;
    $csv .= csvRow(['通常日曜礼拝出席簿　日計']);
    $csv .= csvRow([$label]);
    $csv .= csvRow(['']);
    $csv .= csvRow(['氏名', '性別']);
    foreach ($men_names as $r)   $csv .= csvRow([$r['name'], '男性']);
    foreach ($women_names as $r) $csv .= csvRow([$r['name'], '女性']);
    $csv .= csvRow(['']);
    $csv .= csvRow(['男性出席者数', $men_cnt]);
    $csv .= csvRow(['女性出席者数', $women_cnt]);
    $csv .= csvRow(['合計', $men_cnt + $women_cnt]);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="日計_' . str_replace('-', '', $date) . '.csv"');
    header('Cache-Control: no-store');
    echo $csv;
    exit;
}

// ── 月計 CSV ─────────────────────────────────────────────
if ($type === 'monthly') {
    $year  = (int)($_GET['year']  ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('n'));
    if ($year < 2020 || $year > 2099 || $month < 1 || $month > 12) { http_response_code(400); exit('不正なパラメータ'); }

    $dateFrom = sprintf('%04d-%02d-01', $year, $month);
    $dateTo   = date('Y-m-t', strtotime($dateFrom)); // 月末

    $stmt = $pdo->prepare("
        SELECT a.attendance_date,
               SUM(CASE WHEN r.gender = 'male'   AND a.is_present = 1 THEN 1 ELSE 0 END) AS men_cnt,
               SUM(CASE WHEN r.gender = 'female' AND a.is_present = 1 THEN 1 ELSE 0 END) AS women_cnt
        FROM attendance a
        JOIN roster r ON r.id = a.roster_id
        WHERE a.attendance_date BETWEEN ? AND ?
        GROUP BY a.attendance_date
        ORDER BY a.attendance_date
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalMen = $totalWomen = 0;
    $monthLabel = $year . '年' . $month . '月';

    $csv  = $BOM;
    $csv .= csvRow(['通常日曜礼拝出席簿　月計']);
    $csv .= csvRow([$monthLabel]);
    $csv .= csvRow(['']);
    $csv .= csvRow(['日付', '男性出席者数', '女性出席者数', '合計']);

    foreach ($rows as $row) {
        $d    = (int)substr($row['attendance_date'], 8, 2);
        $men  = (int)$row['men_cnt'];
        $women = (int)$row['women_cnt'];
        $totalMen   += $men;
        $totalWomen += $women;
        $dayLabel = $year . '年' . $month . '月' . $d . '日';
        $csv .= csvRow([$dayLabel, $men, $women, $men + $women]);
    }

    $csv .= csvRow(['']);
    $csv .= csvRow([$monthLabel . '　月計', $totalMen, $totalWomen, $totalMen + $totalWomen]);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="月計_' . sprintf('%04d%02d', $year, $month) . '.csv"');
    header('Cache-Control: no-store');
    echo $csv;
    exit;
}

// ── 選択画面 ────────────────────────────────────────────
$today = date('Y-m-d');
$thisYear  = (int)date('Y');
$thisMonth = (int)date('n');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CSV出力 - 通常日曜礼拝出席簿</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:system-ui,sans-serif;background:#f0f4f8;color:#333;}
header{background:#2c5f8a;color:#fff;padding:12px 20px;display:flex;align-items:center;gap:12px;}
header h1{font-size:1.05rem;}
nav{margin-left:auto;display:flex;gap:14px;}
nav a{color:#cde;text-decoration:none;font-size:.85rem;}
nav a:hover{color:#fff;}
.container{max-width:680px;margin:28px auto;padding:0 16px;}
.card{background:#fff;border-radius:8px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.1);margin-bottom:20px;}
.card h2{font-size:1rem;margin-bottom:18px;color:#2c5f8a;border-bottom:2px solid #e0eaf4;padding-bottom:8px;}
.form-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
label.lbl{font-weight:bold;font-size:.9rem;white-space:nowrap;}
select,input[type=date]{padding:7px 10px;border:1px solid #ccc;border-radius:4px;font-size:.9rem;}
.btn-dl{padding:10px 28px;border:none;border-radius:6px;font-size:.95rem;cursor:pointer;font-weight:bold;}
.btn-daily  {background:#1e6f40;color:#fff;}
.btn-daily:hover{background:#155230;}
.btn-monthly{background:#1e40af;color:#fff;}
.btn-monthly:hover{background:#1e3a8a;}
</style>
</head>
<body>
<header>
  <h1>通常日曜礼拝出席簿</h1>
  <nav>
    <a href="index_01_tr.php">出欠入力</a>
    <a href="backup.php">バックアップ</a>
    <a href="logout.php">ログアウト</a>
  </nav>
</header>
<div class="container">

  <!-- 日計 -->
  <div class="card">
    <h2>日計 CSV 出力</h2>
    <form method="get">
      <input type="hidden" name="type" value="daily">
      <div class="form-row">
        <label class="lbl">日付：</label>
        <input type="date" name="date" value="<?= htmlspecialchars($today) ?>">
      </div>
      <button type="submit" class="btn-dl btn-daily">日計をダウンロード</button>
    </form>
  </div>

  <!-- 月計 -->
  <div class="card">
    <h2>月計 CSV 出力</h2>
    <form method="get">
      <input type="hidden" name="type" value="monthly">
      <div class="form-row">
        <label class="lbl">年月：</label>
        <select name="year">
          <?php for ($y = 2024; $y <= $thisYear + 1; $y++): ?>
            <option value="<?= $y ?>" <?= $y === $thisYear ? 'selected' : '' ?>><?= $y ?>年</option>
          <?php endfor; ?>
        </select>
        <select name="month">
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $m === $thisMonth ? 'selected' : '' ?>><?= $m ?>月</option>
          <?php endfor; ?>
        </select>
      </div>
      <button type="submit" class="btn-dl btn-monthly">月計をダウンロード</button>
    </form>
  </div>

</div>
</body>
</html>
