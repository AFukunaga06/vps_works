<?php
/**
 * 出欠管理 + CSV出力
 * attendance.php
 *
 * ── DB設定をここで変更 ──────────────────────
 */
$DB_HOST = 'localhost';
$DB_NAME = 'attendance_db';
$DB_USER = 'root';
$DB_PASS = 'REDACTED_FOR_PUBLIC';
// ────────────────────────────────────────────

function get_pdo($host, $name, $user, $pass): PDO {
    $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

$pdo = get_pdo($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS);

// ─── 出欠登録 ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $date   = $_POST['attend_date'] ?? '';
    $name   = trim($_POST['name'] ?? '');
    $status = $_POST['status'] ?? '出席';

    if ($date && $name) {
        // 同じ日付・名前が既にあれば更新
        $check = $pdo->prepare("SELECT id FROM attendance WHERE attend_date=? AND name=?");
        $check->execute([$date, $name]);
        if ($check->fetch()) {
            $stmt = $pdo->prepare("UPDATE attendance SET status=? WHERE attend_date=? AND name=?");
            $stmt->execute([$status, $date, $name]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO attendance (attend_date, name, status) VALUES (?,?,?)");
            $stmt->execute([$date, $name, $status]);
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ─── 削除 ─────────────────────────────────
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE id=?");
    $stmt->execute([(int)$_GET['delete']]);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ─── CSV出力 ──────────────────────────────
if (isset($_GET['csv'])) {
    $type  = $_GET['csv'];
    $rows  = [];
    $fname = 'attendance.csv';

    if ($type === 'daily' && !empty($_GET['d'])) {
        $d    = $_GET['d'];
        $stmt = $pdo->prepare(
            "SELECT attend_date AS '日付', name AS '氏名', status AS '出欠'
             FROM attendance WHERE attend_date=? ORDER BY name"
        );
        $stmt->execute([$d]);
        $rows  = $stmt->fetchAll();
        $fname = 'daily_' . str_replace('-', '', $d) . '.csv';

    } elseif ($type === 'monthly' && !empty($_GET['m'])) {
        $m    = $_GET['m'];
        $stmt = $pdo->prepare(
            "SELECT attend_date AS '日付', name AS '氏名', status AS '出欠'
             FROM attendance WHERE DATE_FORMAT(attend_date,'%Y-%m')=? ORDER BY attend_date, name"
        );
        $stmt->execute([$m]);
        $rows  = $stmt->fetchAll();
        $fname = 'monthly_' . str_replace('-', '', $m) . '.csv';

    } elseif ($type === 'yearly' && !empty($_GET['y'])) {
        $y    = (int)$_GET['y'];
        $stmt = $pdo->prepare(
            "SELECT attend_date AS '日付', name AS '氏名', status AS '出欠'
             FROM attendance WHERE YEAR(attend_date)=? ORDER BY attend_date, name"
        );
        $stmt->execute([$y]);
        $rows  = $stmt->fetchAll();
        $fname = 'yearly_' . $y . '.csv';
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    echo "\xEF\xBB\xBF"; // BOM（Excel文字化け防止）
    $fp = fopen('php://output', 'w');
    if ($rows) {
        fputcsv($fp, array_keys($rows[0]));
        foreach ($rows as $row) fputcsv($fp, $row);
    }
    fclose($fp);
    exit;
}

// ─── 一覧取得 ─────────────────────────────
$filter_date  = $_GET['filter_date']  ?? '';
$filter_name  = $_GET['filter_name']  ?? '';
$filter_month = $_GET['filter_month'] ?? '';
$filter_year  = $_GET['filter_year']  ?? '';

$where = [];
$params = [];

if ($filter_date) {
    $where[] = 'attend_date = ?';
    $params[] = $filter_date;
}
if ($filter_name) {
    $where[] = 'name LIKE ?';
    $params[] = '%' . $filter_name . '%';
}
if ($filter_month) {
    $where[] = "DATE_FORMAT(attend_date,'%Y-%m') = ?";
    $params[] = $filter_month;
}
if ($filter_year) {
    $where[] = "YEAR(attend_date) = ?";
    $params[] = (int)$filter_year;
}

$sql  = "SELECT * FROM attendance";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY attend_date DESC, name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// 集計
$total    = count($records);
$present  = count(array_filter($records, fn($r) => $r['status'] === '出席'));
$absent   = $total - $present;

// 年一覧（CSV用）
$years = $pdo->query("SELECT DISTINCT YEAR(attend_date) AS y FROM attendance ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>出欠管理</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif;
    background: #f0f4f8;
    color: #2d3748;
    min-height: 100vh;
  }

  header {
    background: #2b6cb0;
    color: #fff;
    padding: 16px 24px;
    font-size: 1.3rem;
    font-weight: bold;
    letter-spacing: .05em;
  }

  .container { max-width: 960px; margin: 0 auto; padding: 24px 16px; }

  /* カード */
  .card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
    padding: 20px 24px;
    margin-bottom: 20px;
  }
  .card h2 {
    font-size: 1rem;
    font-weight: bold;
    color: #2b6cb0;
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 2px solid #ebf4ff;
  }

  /* フォーム */
  .form-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
  .form-group { display: flex; flex-direction: column; gap: 4px; }
  .form-group label { font-size: .8rem; color: #718096; }
  input[type="date"], input[type="text"], select {
    padding: 8px 10px;
    border: 1px solid #cbd5e0;
    border-radius: 6px;
    font-size: .95rem;
    outline: none;
    transition: border-color .2s;
  }
  input:focus, select:focus { border-color: #2b6cb0; }

  /* ボタン */
  .btn {
    padding: 8px 18px;
    border: none;
    border-radius: 6px;
    font-size: .9rem;
    cursor: pointer;
    font-weight: bold;
    transition: opacity .2s;
    text-decoration: none;
    display: inline-block;
  }
  .btn:hover { opacity: .85; }
  .btn-blue   { background: #2b6cb0; color: #fff; }
  .btn-green  { background: #276749; color: #fff; }
  .btn-gray   { background: #718096; color: #fff; }
  .btn-red    { background: #c53030; color: #fff; font-size: .8rem; padding: 4px 10px; }

  /* 集計バッジ */
  .stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
  .stat-box {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: bold;
    font-size: .95rem;
  }
  .stat-total   { background: #ebf4ff; color: #2b6cb0; }
  .stat-present { background: #f0fff4; color: #276749; }
  .stat-absent  { background: #fff5f5; color: #c53030; }

  /* テーブル */
  table { width: 100%; border-collapse: collapse; font-size: .9rem; }
  th {
    background: #ebf4ff;
    color: #2b6cb0;
    padding: 10px 12px;
    text-align: left;
    font-weight: bold;
  }
  td { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; }
  tr:hover td { background: #f7fafc; }
  .badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: .8rem;
    font-weight: bold;
  }
  .badge-present { background: #f0fff4; color: #276749; }
  .badge-absent  { background: #fff5f5; color: #c53030; }

  /* CSV エリア */
  .csv-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }

  .no-data { text-align: center; color: #a0aec0; padding: 30px; }

  @media (max-width: 600px) {
    .form-row, .csv-row { flex-direction: column; }
    .btn { width: 100%; text-align: center; }
  }
</style>
</head>
<body>

<header>📋 出欠管理</header>

<div class="container">

  <!-- ── 登録フォーム ───────────────────── -->
  <div class="card">
    <h2>出欠を登録</h2>
    <form method="post">
      <div class="form-row">
        <div class="form-group">
          <label>日付</label>
          <input type="date" name="attend_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label>氏名</label>
          <input type="text" name="name" placeholder="例：田中 太郎" required style="width:160px">
        </div>
        <div class="form-group">
          <label>出欠</label>
          <select name="status">
            <option value="出席">出席</option>
            <option value="欠席">欠席</option>
          </select>
        </div>
        <button type="submit" name="register" class="btn btn-blue">登録</button>
      </div>
    </form>
  </div>

  <!-- ── 絞り込み + CSV ────────────────── -->
  <div class="card">
    <h2>絞り込み・CSV出力</h2>

    <form method="get" style="margin-bottom:16px">
      <div class="form-row">
        <div class="form-group">
          <label>日付で絞り込み</label>
          <input type="date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>">
        </div>
        <div class="form-group">
          <label>氏名で絞り込み</label>
          <input type="text" name="filter_name" value="<?= htmlspecialchars($filter_name) ?>" placeholder="部分一致" style="width:130px">
        </div>
        <div class="form-group">
          <label>年月で絞り込み</label>
          <input type="month" name="filter_month" value="<?= htmlspecialchars($filter_month) ?>">
        </div>
        <div class="form-group">
          <label>年で絞り込み</label>
          <select name="filter_year">
            <option value="">-- 年 --</option>
            <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y ?>年</option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-blue">絞り込む</button>
        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-gray">リセット</a>
      </div>
    </form>

    <!-- CSV ボタン -->
    <div class="csv-row">
      <?php if ($filter_date): ?>
      <a href="?csv=daily&d=<?= urlencode($filter_date) ?>" class="btn btn-green">
        📥 日計CSV（<?= htmlspecialchars($filter_date) ?>）
      </a>
      <?php endif; ?>

      <?php if ($filter_month): ?>
      <a href="?csv=monthly&m=<?= urlencode($filter_month) ?>" class="btn btn-green">
        📥 月計CSV（<?= htmlspecialchars($filter_month) ?>）
      </a>
      <?php endif; ?>

      <?php if ($filter_year): ?>
      <a href="?csv=yearly&y=<?= (int)$filter_year ?>" class="btn btn-green">
        📥 年計CSV（<?= (int)$filter_year ?>年）
      </a>
      <?php endif; ?>

      <?php if (!$filter_date && !$filter_month && !$filter_year): ?>
      <span style="color:#a0aec0; font-size:.9rem;">
        ※ 日付・年月・年で絞り込むとCSVダウンロードボタンが表示されます
      </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── 一覧テーブル ───────────────────── -->
  <div class="card">
    <h2>出欠一覧</h2>

    <div class="stats">
      <div class="stat-box stat-total">全 <?= $total ?> 件</div>
      <div class="stat-box stat-present">出席 <?= $present ?> 件</div>
      <div class="stat-box stat-absent">欠席 <?= $absent ?> 件</div>
    </div>

    <?php if ($records): ?>
    <table>
      <thead>
        <tr>
          <th>日付</th>
          <th>氏名</th>
          <th>出欠</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['attend_date']) ?></td>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td>
            <span class="badge <?= $r['status'] === '出席' ? 'badge-present' : 'badge-absent' ?>">
              <?= htmlspecialchars($r['status']) ?>
            </span>
          </td>
          <td>
            <a href="?delete=<?= $r['id'] ?>"
               class="btn btn-red"
               onclick="return confirm('削除しますか？')">削除</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="no-data">データがありません</div>
    <?php endif; ?>
  </div>

</div><!-- /container -->
</body>
</html>
