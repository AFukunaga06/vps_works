<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$mode         = $_GET['mode'] ?? 'monthly';
$this_month   = date('Y-m');
$this_year    = (int)date('Y');
$target_month = $_GET['month'] ?? $this_month;
$target_year  = (int)($_GET['year'] ?? $this_year);
$do_csv       = isset($_GET['csv']);

$tc_eval_rates = ['A' => 1300, 'B' => 1200, 'C' => 1100];
function tc_evalRate(string $e, array $rates): int { return $rates[$e] ?? 1200; }

function tc_calcWorkMin(?string $ci, ?string $co): ?int {
    if (!$ci || !$co) return null;
    [$ih,$im] = array_map('intval', explode(':', substr($ci, 0, 5)));
    [$oh,$om] = array_map('intval', explode(':', substr($co, 0, 5)));
    $inM  = $ih * 60 + $im;
    $outM = $oh * 60 + $om;
    if ($outM <= $inM) return null;
    $work = $outM - $inM;
    if ($inM < 780 && $outM > 780) $work -= 60;
    return max(0, $work);
}
function tc_fmtHM(int $min): string {
    return intdiv($min, 60) . ':' . str_pad($min % 60, 2, '0', STR_PAD_LEFT);
}
function tc_fmtWage(int $min, int $rate): int {
    return (int)round($min / 60 * $rate);
}

// ── データ取得共通 ──────────────────────────────────────────
function fetchMemberTcData(\PDO $pdo, string $where_col, $where_val, array $rates): array {
    $stmt = $pdo->prepare(
        "SELECT m.id, m.name, m.group_name, COALESCE(m.evaluation,'B') AS evaluation,
                t.work_date, t.clock_in, t.clock_out
         FROM members m
         LEFT JOIN timecard t ON t.member_id = m.id AND $where_col = ?
         WHERE m.is_active = 1
         ORDER BY m.group_name, m.kana, t.work_date"
    );
    $stmt->execute([$where_val]);
    $rows = $stmt->fetchAll();

    $data = [];
    foreach ($rows as $r) {
        $id = $r['id'];
        if (!isset($data[$id])) {
            $data[$id] = [
                'name'    => $r['name'],
                'group'   => $r['group_name'],
                'eval'    => $r['evaluation'],
                'rate'    => tc_evalRate($r['evaluation'], $rates),
                'days'    => 0,
                'minutes' => 0,
                'wage'    => 0,
            ];
        }
        $wm = tc_calcWorkMin($r['clock_in'], $r['clock_out']);
        if ($wm !== null) {
            $data[$id]['days']++;
            $data[$id]['minutes'] += $wm;
            $data[$id]['wage']    += tc_fmtWage($wm, $data[$id]['rate']);
        }
    }
    return $data;
}

// ── 月計データ ─────────────────────────────────────────────
if ($mode === 'monthly') {
    $member_data = fetchMemberTcData(
        $pdo,
        "DATE_FORMAT(t.work_date,'%Y-%m')",
        $target_month,
        $tc_eval_rates
    );
    $men_data   = array_filter($member_data, fn($m) => $m['group'] === '男性');
    $women_data = array_filter($member_data, fn($m) => $m['group'] === '女性');

    // 前月・翌月
    $dt        = \DateTime::createFromFormat('Y-m', $target_month);
    $prev_month = (clone $dt)->modify('-1 month')->format('Y-m');
    $next_month = (clone $dt)->modify('+1 month')->format('Y-m');
}

// ── 年計データ ─────────────────────────────────────────────
if ($mode === 'yearly') {
    $stmt = $pdo->prepare(
        "SELECT m.id, m.name, m.group_name, COALESCE(m.evaluation,'B') AS evaluation,
                t.work_date, t.clock_in, t.clock_out
         FROM members m
         LEFT JOIN timecard t ON t.member_id = m.id AND YEAR(t.work_date) = ?
         WHERE m.is_active = 1
         ORDER BY m.group_name, m.kana, t.work_date"
    );
    $stmt->execute([$target_year]);
    $rows_y = $stmt->fetchAll();

    $monthly_totals = [];  // ['Y-m' => ['work_dates'=>[], 'minutes'=>0, 'wage'=>0]]
    $member_data    = [];

    foreach ($rows_y as $r) {
        $id   = $r['id'];
        $eval = $r['evaluation'] ?? 'B';
        $rate = tc_evalRate($eval, $tc_eval_rates);
        if (!isset($member_data[$id])) {
            $member_data[$id] = [
                'name'    => $r['name'],
                'group'   => $r['group_name'],
                'eval'    => $eval,
                'rate'    => $rate,
                'days'    => 0,
                'minutes' => 0,
                'wage'    => 0,
            ];
        }
        $wm = tc_calcWorkMin($r['clock_in'], $r['clock_out']);
        if ($wm !== null && $r['work_date']) {
            $ym = substr($r['work_date'], 0, 7);
            $member_data[$id]['days']++;
            $member_data[$id]['minutes'] += $wm;
            $member_data[$id]['wage']    += tc_fmtWage($wm, $rate);
            if (!isset($monthly_totals[$ym])) {
                $monthly_totals[$ym] = ['work_dates' => [], 'minutes' => 0, 'wage' => 0];
            }
            $monthly_totals[$ym]['work_dates'][$r['work_date']] = true;
            $monthly_totals[$ym]['minutes'] += $wm;
            $monthly_totals[$ym]['wage']    += tc_fmtWage($wm, $rate);
        }
    }
    ksort($monthly_totals);
    $men_data   = array_filter($member_data, fn($m) => $m['group'] === '男性');
    $women_data = array_filter($member_data, fn($m) => $m['group'] === '女性');
}

// ── CSV出力 ─────────────────────────────────────────────────
if ($do_csv) {
    if ($mode === 'monthly') {
        $filename = 'timecard_monthly_' . $target_month . '.csv';
        $rows_csv = [];
        $rows_csv[] = [$target_month . ' タイムカード月計', '', '', '', '', ''];
        $rows_csv[] = ['', '', '', '', '', ''];
        foreach (['男性' => $men_data, '女性' => $women_data] as $label => $list) {
            $rows_csv[] = ['【' . $label . '】', '', '', '', '', ''];
            $rows_csv[] = ['氏名', '評価', '時給単価', '出勤日数', '総勤務時間', '支給額'];
            $g_days = 0; $g_min = 0; $g_wage = 0;
            foreach ($list as $m) {
                $rows_csv[] = [
                    $m['name'], $m['eval'],
                    number_format($m['rate']) . '円',
                    $m['days'] . '日',
                    tc_fmtHM($m['minutes']),
                    number_format($m['wage']) . '円',
                ];
                $g_days += $m['days']; $g_min += $m['minutes']; $g_wage += $m['wage'];
            }
            $rows_csv[] = [$label . '計', '', '', $g_days . '日', tc_fmtHM($g_min), number_format($g_wage) . '円'];
            $rows_csv[] = ['', '', '', '', '', ''];
        }
        $t_days = array_sum(array_column($member_data, 'days'));
        $t_min  = array_sum(array_column($member_data, 'minutes'));
        $t_wage = array_sum(array_column($member_data, 'wage'));
        $rows_csv[] = ['合計', '', '', $t_days . '日', tc_fmtHM($t_min), number_format($t_wage) . '円'];
    } else {
        $filename = 'timecard_yearly_' . $target_year . '.csv';
        $rows_csv = [];
        $rows_csv[] = [$target_year . '年 タイムカード年計', '', '', '', ''];
        $rows_csv[] = ['', '', '', '', ''];
        $rows_csv[] = ['■ 月別集計', '', '', '', ''];
        $rows_csv[] = ['月', '出勤日数', '総勤務時間', '支給額合計', ''];
        foreach ($monthly_totals as $ym => $mt) {
            $rows_csv[] = [
                $ym,
                count($mt['work_dates']) . '日',
                tc_fmtHM($mt['minutes']),
                number_format($mt['wage']) . '円',
                '',
            ];
        }
        $all_dates = [];
        $y_min = 0; $y_wage = 0;
        foreach ($monthly_totals as $mt) {
            $all_dates = array_merge($all_dates, array_keys($mt['work_dates']));
            $y_min  += $mt['minutes'];
            $y_wage += $mt['wage'];
        }
        $rows_csv[] = ['年計', count(array_unique($all_dates)) . '日', tc_fmtHM($y_min), number_format($y_wage) . '円', ''];
        $rows_csv[] = ['', '', '', '', ''];
        $rows_csv[] = ['■ メンバー別年計', '', '', '', ''];
        foreach (['男性' => $men_data, '女性' => $women_data] as $label => $list) {
            $rows_csv[] = ['【' . $label . '】', '', '', '', ''];
            $rows_csv[] = ['氏名', '評価', '出勤日数', '総勤務時間', '支給額'];
            $g_days = 0; $g_min = 0; $g_wage = 0;
            foreach ($list as $m) {
                $rows_csv[] = [$m['name'], $m['eval'], $m['days'] . '日', tc_fmtHM($m['minutes']), number_format($m['wage']) . '円'];
                $g_days += $m['days']; $g_min += $m['minutes']; $g_wage += $m['wage'];
            }
            $rows_csv[] = [$label . '計', '', $g_days . '日', tc_fmtHM($g_min), number_format($g_wage) . '円'];
            $rows_csv[] = ['', '', '', '', ''];
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

// ── 合計計算ヘルパー ────────────────────────────────────────
function tc_groupTotals(array $list): array {
    return [
        'days'    => array_sum(array_column($list, 'days')),
        'minutes' => array_sum(array_column($list, 'minutes')),
        'wage'    => array_sum(array_column($list, 'wage')),
    ];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>タイムカード集計</title>
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
.nav-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.nav-bar label { font-weight: bold; font-size: .9rem; }
.nav-bar input[type=month],
.nav-bar input[type=number] { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: .95rem; }
.btn-nav { padding: 6px 12px; background: #fff; border: 1px solid #bbb; border-radius: 4px; cursor: pointer; font-size: .9rem; }
.btn-nav:hover { background: #e8f0f8; border-color: #2c5f8a; }
.btn-today { padding: 6px 12px; background: #2c5f8a; border: none; border-radius: 4px; cursor: pointer; font-size: .85rem; color: #fff; }
.btn-today:hover { background: #1a4a70; }
.btn-csv { padding: 6px 14px; background: #2e7d32; color: #fff; border-radius: 4px; font-size: .82rem; text-decoration: none; white-space: nowrap; border: none; display: inline-block; }
.btn-csv:hover { background: #1b5e20; }
/* サマリー */
.summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
.summary-box { flex: 1; min-width: 100px; background: #f0f4f8; border-radius: 8px; padding: 12px; text-align: center; }
.summary-box .lbl { font-size: .78rem; color: #666; margin-bottom: 3px; }
.summary-box .val { font-size: 1.5rem; font-weight: bold; }
.v-days  { color: #2c5f8a; }
.v-hours { color: #2e7d52; }
.v-wage  { color: #7a5c00; }
/* セクション */
.section { border-radius: 8px; overflow: hidden; border: 1px solid #e0e8f0; margin-bottom: 14px; }
.sec-header { padding: 9px 14px; font-weight: bold; font-size: .9rem; }
.sec-men   .sec-header { background: #d0e8ff; color: #1a5c99; }
.sec-women .sec-header { background: #ffd0e8; color: #99195c; }
.sec-monthly .sec-header { background: #fff3e0; color: #7a4500; }
/* テーブル */
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f5f8fc; }
th { padding: 8px 10px; font-size: .82rem; color: #666; font-weight: bold; border-bottom: 2px solid #e0e8f0; text-align: center; }
th.left { text-align: left; }
td { padding: 8px 10px; font-size: .88rem; border-bottom: 1px solid #f0f0f0; text-align: center; }
td.left { text-align: left; }
tbody tr:hover { background: #f8fbff; }
tr.subtotal td { background: #f0f6ff; font-weight: bold; color: #2c5f8a; border-top: 1px solid #d0e0f0; }
tr.subtotal-women td { background: #fff0f8; color: #99195c; }
tr.grand-total td { background: #fff8e1; font-weight: bold; font-size: .92rem; color: #7a4500; border-top: 2px solid #e0c060; }
/* 評価バッジ */
.eval-a { display: inline-block; padding: 1px 8px; background: #fff3cd; color: #856404; border-radius: 10px; font-size: .82rem; font-weight: bold; }
.eval-b { display: inline-block; padding: 1px 8px; background: #d1ecf1; color: #0c5460; border-radius: 10px; font-size: .82rem; font-weight: bold; }
.eval-c { display: inline-block; padding: 1px 8px; background: #f8d7da; color: #721c24; border-radius: 10px; font-size: .82rem; font-weight: bold; }
.no-data { text-align: center; color: #999; padding: 28px; font-size: .95rem; }
.section-title { font-size: .95rem; font-weight: bold; color: #555; margin: 18px 0 8px; padding-left: 4px; border-left: 4px solid #2c5f8a; padding-left: 10px; }
</style>
</head>
<body>
<header>
  <h1>ABC○○商事㈱</h1>
  <nav>
    <a href="index.php">出欠入力</a>
    <a href="members.php">会員管理</a>
    <a href="report.php">レポート</a>
    <a href="timecard_report.php">タイムカード集計</a>
    <a href="logout.php" style="margin-left:auto;color:#ffd0d0;">ログアウト</a>
  </nav>
</header>

<div class="container">
  <div class="tabs">
    <a class="tab <?= $mode==='monthly'?'active':'' ?>" href="?mode=monthly">月計</a>
    <a class="tab <?= $mode==='yearly' ?'active':'' ?>" href="?mode=yearly">年計</a>
  </div>
  <div class="card">

  <?php if ($mode === 'monthly'): ?>

    <?php
    [$dt_y, $dt_m] = explode('-', $target_month);
    $csv_url = '?mode=monthly&month=' . urlencode($target_month) . '&csv=1';
    ?>
    <div class="nav-bar">
      <label>月：</label>
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=monthly&month=<?= $prev_month ?>'">◀ 前月</button>
      <input type="month" id="m-input" value="<?= htmlspecialchars($target_month) ?>"
             onchange="location.href='?mode=monthly&month='+this.value">
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=monthly&month=<?= $next_month ?>'">翌月 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=monthly&month=<?= $this_month ?>'">今月</button>
      <a href="<?= $csv_url ?>" class="btn-csv">⬇ CSV</a>
    </div>

    <?php
    $men_t   = tc_groupTotals($men_data);
    $women_t = tc_groupTotals($women_data);
    $all_t   = tc_groupTotals($member_data);
    ?>
    <div class="summary">
      <div class="summary-box">
        <div class="lbl">出勤延べ日数</div>
        <div class="val v-days"><?= $all_t['days'] ?><span style="font-size:.9rem">日</span></div>
      </div>
      <div class="summary-box">
        <div class="lbl">総勤務時間</div>
        <div class="val v-hours"><?= $all_t['minutes'] ? tc_fmtHM($all_t['minutes']) : '0:00' ?></div>
      </div>
      <div class="summary-box" style="background:#fff8e1">
        <div class="lbl">支給額合計</div>
        <div class="val v-wage"><?= number_format($all_t['wage']) ?><span style="font-size:.85rem">円</span></div>
      </div>
      <div class="summary-box">
        <div class="lbl">男性 支給計</div>
        <div class="val" style="color:#1a5c99;font-size:1.3rem"><?= number_format($men_t['wage']) ?><span style="font-size:.8rem">円</span></div>
      </div>
      <div class="summary-box">
        <div class="lbl">女性 支給計</div>
        <div class="val" style="color:#99195c;font-size:1.3rem"><?= number_format($women_t['wage']) ?><span style="font-size:.8rem">円</span></div>
      </div>
    </div>

    <?php foreach ([['men','男性',$men_data,$men_t],['women','女性',$women_data,$women_t]] as [$cls,$label,$list,$gtotal]): ?>
    <div class="section sec-<?= $cls ?>">
      <div class="sec-header"><?= $label ?></div>
      <?php if (empty($list) || $gtotal['days'] === 0): ?>
        <div class="no-data">データがありません</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th class="left">氏名</th>
            <th>評価</th>
            <th>時給</th>
            <th>出勤日数</th>
            <th>総勤務時間</th>
            <th>支給額</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($list as $m): ?>
          <tr>
            <td class="left"><?= htmlspecialchars($m['name']) ?></td>
            <td><span class="eval-<?= strtolower($m['eval']) ?>"><?= htmlspecialchars($m['eval']) ?></span></td>
            <td><?= number_format($m['rate']) ?>円</td>
            <td><?= $m['days'] ?>日</td>
            <td><?= $m['minutes'] ? tc_fmtHM($m['minutes']) : '―' ?></td>
            <td><?= $m['wage'] ? number_format($m['wage']) . '円' : '―' ?></td>
          </tr>
          <?php endforeach; ?>
          <tr class="subtotal<?= $cls==='women'?'-women':'' ?>">
            <td class="left" colspan="3"><?= $label ?>計</td>
            <td><?= $gtotal['days'] ?>日</td>
            <td><?= $gtotal['minutes'] ? tc_fmtHM($gtotal['minutes']) : '―' ?></td>
            <td><?= $gtotal['wage'] ? number_format($gtotal['wage']) . '円' : '―' ?></td>
          </tr>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($all_t['wage'] > 0): ?>
    <table style="margin-top:8px">
      <tbody>
        <tr class="grand-total">
          <td class="left" colspan="3" style="font-size:.92rem">合計</td>
          <td><?= $all_t['days'] ?>日</td>
          <td><?= tc_fmtHM($all_t['minutes']) ?></td>
          <td><?= number_format($all_t['wage']) ?>円</td>
        </tr>
      </tbody>
    </table>
    <?php endif; ?>

  <?php else: /* yearly */ ?>

    <?php
    $prev_year = $target_year - 1;
    $next_year = $target_year + 1;
    $csv_url   = '?mode=yearly&year=' . $target_year . '&csv=1';
    $all_t     = tc_groupTotals($member_data);
    $men_t     = tc_groupTotals($men_data);
    $women_t   = tc_groupTotals($women_data);
    $all_dates = [];
    foreach ($monthly_totals as $mt) {
        $all_dates = array_merge($all_dates, array_keys($mt['work_dates']));
    }
    $unique_days = count(array_unique($all_dates));
    ?>
    <div class="nav-bar">
      <label>年：</label>
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=yearly&year=<?= $prev_year ?>'">◀ 前年</button>
      <input type="number" id="y-input" value="<?= $target_year ?>" min="2000" max="2100"
             style="width:80px"
             onchange="location.href='?mode=yearly&year='+this.value">
      <button type="button" class="btn-nav"
              onclick="location.href='?mode=yearly&year=<?= $next_year ?>'">翌年 ▶</button>
      <button type="button" class="btn-today"
              onclick="location.href='?mode=yearly&year=<?= $this_year ?>'">今年</button>
      <a href="<?= $csv_url ?>" class="btn-csv">⬇ CSV</a>
    </div>

    <div class="summary">
      <div class="summary-box">
        <div class="lbl">稼働日数</div>
        <div class="val v-days"><?= $unique_days ?><span style="font-size:.9rem">日</span></div>
      </div>
      <div class="summary-box">
        <div class="lbl">総勤務時間</div>
        <div class="val v-hours"><?= $all_t['minutes'] ? tc_fmtHM($all_t['minutes']) : '0:00' ?></div>
      </div>
      <div class="summary-box" style="background:#fff8e1">
        <div class="lbl">年間支給額合計</div>
        <div class="val v-wage"><?= number_format($all_t['wage']) ?><span style="font-size:.85rem">円</span></div>
      </div>
      <div class="summary-box">
        <div class="lbl">男性 支給計</div>
        <div class="val" style="color:#1a5c99;font-size:1.3rem"><?= number_format($men_t['wage']) ?><span style="font-size:.8rem">円</span></div>
      </div>
      <div class="summary-box">
        <div class="lbl">女性 支給計</div>
        <div class="val" style="color:#99195c;font-size:1.3rem"><?= number_format($women_t['wage']) ?><span style="font-size:.8rem">円</span></div>
      </div>
    </div>

    <!-- 月別集計 -->
    <p class="section-title">月別集計</p>
    <div class="section sec-monthly">
      <div class="sec-header">📅 <?= $target_year ?>年 月別サマリー</div>
      <?php if (empty($monthly_totals)): ?>
        <div class="no-data">データがありません</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th class="left">月</th>
            <th>出勤日数</th>
            <th>総勤務時間</th>
            <th>支給額合計</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($monthly_totals as $ym => $mt): ?>
          <tr>
            <td class="left">
              <a href="?mode=monthly&month=<?= $ym ?>"
                 style="color:#2c5f8a;text-decoration:none;font-weight:bold">
                <?= date('Y年n月', strtotime($ym . '-01')) ?>
              </a>
            </td>
            <td><?= count($mt['work_dates']) ?>日</td>
            <td><?= tc_fmtHM($mt['minutes']) ?></td>
            <td><?= number_format($mt['wage']) ?>円</td>
          </tr>
          <?php endforeach; ?>
          <tr class="grand-total">
            <td class="left">年計</td>
            <td><?= $unique_days ?>日</td>
            <td><?= $all_t['minutes'] ? tc_fmtHM($all_t['minutes']) : '―' ?></td>
            <td><?= number_format($all_t['wage']) ?>円</td>
          </tr>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <!-- メンバー別年計 -->
    <p class="section-title">メンバー別年計</p>
    <?php foreach ([['men','男性',$men_data,$men_t],['women','女性',$women_data,$women_t]] as [$cls,$label,$list,$gtotal]): ?>
    <div class="section sec-<?= $cls ?>" style="margin-bottom:14px">
      <div class="sec-header"><?= $label ?></div>
      <?php if (empty($list) || $gtotal['days'] === 0): ?>
        <div class="no-data">データがありません</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th class="left">氏名</th>
            <th>評価</th>
            <th>時給</th>
            <th>出勤日数</th>
            <th>総勤務時間</th>
            <th>支給額</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($list as $m): ?>
          <tr>
            <td class="left"><?= htmlspecialchars($m['name']) ?></td>
            <td><span class="eval-<?= strtolower($m['eval']) ?>"><?= htmlspecialchars($m['eval']) ?></span></td>
            <td><?= number_format($m['rate']) ?>円</td>
            <td><?= $m['days'] ?>日</td>
            <td><?= $m['minutes'] ? tc_fmtHM($m['minutes']) : '―' ?></td>
            <td><?= $m['wage'] ? number_format($m['wage']) . '円' : '―' ?></td>
          </tr>
          <?php endforeach; ?>
          <tr class="subtotal<?= $cls==='women'?'-women':'' ?>">
            <td class="left" colspan="3"><?= $label ?>計</td>
            <td><?= $gtotal['days'] ?>日</td>
            <td><?= $gtotal['minutes'] ? tc_fmtHM($gtotal['minutes']) : '―' ?></td>
            <td><?= $gtotal['wage'] ? number_format($gtotal['wage']) . '円' : '―' ?></td>
          </tr>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($all_t['wage'] > 0): ?>
    <table>
      <tbody>
        <tr class="grand-total">
          <td class="left" colspan="3">合計</td>
          <td><?= $all_t['days'] ?>日</td>
          <td><?= tc_fmtHM($all_t['minutes']) ?></td>
          <td><?= number_format($all_t['wage']) ?>円</td>
        </tr>
      </tbody>
    </table>
    <?php endif; ?>

  <?php endif; ?>

  </div><!-- .card -->
</div><!-- .container -->
</body>
</html>
