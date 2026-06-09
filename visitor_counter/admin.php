<?php
session_start();
require_once __DIR__ . '/config.php';

if (isset($_POST['logout'])) { session_destroy(); header('Location: admin.php'); exit; }
if (isset($_POST['pass'])) {
    if ($_POST['pass'] === ADMIN_PASS) $_SESSION['vc_admin'] = true;
    else $login_error = 'パスワードが違います';
}
$logged_in = !empty($_SESSION['vc_admin']);

if ($logged_in) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Exception $e) {
        die('<p class="text-danger p-4">DB接続エラー: ' . htmlspecialchars($e->getMessage()) . '</p>');
    }

    $show_bots = !empty($_GET['bots']);
    $bc = $show_bots ? '' : 'AND is_bot = 0';

    $today      = date('Y-m-d');
    $this_month = date('Y-m');

    $today_pv  = (int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE DATE(created_at)='{$today}' {$bc}")->fetchColumn();
    $today_uv  = (int)$pdo->query("SELECT COUNT(DISTINCT session_id) FROM page_views WHERE DATE(created_at)='{$today}' {$bc}")->fetchColumn();
    $month_pv  = (int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE DATE_FORMAT(created_at,'%Y-%m')='{$this_month}' {$bc}")->fetchColumn();
    $total_pv  = (int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE 1=1 {$bc}")->fetchColumn();
    $total_bot = (int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE is_bot=1")->fetchColumn();

    // Daily last 30 days
    $daily_rows = $pdo->query(
        "SELECT DATE(created_at) as d, COUNT(*) as pv, COUNT(DISTINCT session_id) as uv
         FROM page_views WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) {$bc}
         GROUP BY DATE(created_at) ORDER BY d ASC"
    )->fetchAll();
    $daily_map = array_column($daily_rows, null, 'd');
    $d_labels = $d_pv = $d_uv = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $d_labels[] = date('m/d', strtotime($d));
        $d_pv[]     = isset($daily_map[$d]) ? (int)$daily_map[$d]['pv'] : 0;
        $d_uv[]     = isset($daily_map[$d]) ? (int)$daily_map[$d]['uv'] : 0;
    }

    // Monthly last 12 months
    $monthly_rows = $pdo->query(
        "SELECT DATE_FORMAT(created_at,'%Y-%m') as m, COUNT(*) as pv, COUNT(DISTINCT session_id) as uv
         FROM page_views WHERE created_at >= DATE_SUB(NOW(), INTERVAL 11 MONTH) {$bc}
         GROUP BY m ORDER BY m ASC"
    )->fetchAll();
    $m_map = array_column($monthly_rows, null, 'm');
    $m_labels = $m_pv = $m_uv = [];
    for ($i = 11; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("first day of -{$i} months"));
        $m_labels[] = date('Y/m', strtotime($m . '-01'));
        $m_pv[]     = isset($m_map[$m]) ? (int)$m_map[$m]['pv'] : 0;
        $m_uv[]     = isset($m_map[$m]) ? (int)$m_map[$m]['uv'] : 0;
    }

    // Countries
    $countries = $pdo->query(
        "SELECT COALESCE(NULLIF(country_name,''),'不明') as cn, COUNT(*) as cnt
         FROM page_views WHERE 1=1 {$bc}
         GROUP BY cn ORDER BY cnt DESC LIMIT 8"
    )->fetchAll();

    // Top pages
    $top_pages = $pdo->query(
        "SELECT url, page_title, COUNT(*) as pv, COUNT(DISTINCT session_id) as uv
         FROM page_views WHERE 1=1 {$bc}
         GROUP BY url, page_title ORDER BY pv DESC LIMIT 10"
    )->fetchAll();

    // Top referrers
    $top_refs = $pdo->query(
        "SELECT referrer, COUNT(*) as cnt
         FROM page_views WHERE referrer != '' {$bc}
         GROUP BY referrer ORDER BY cnt DESC LIMIT 10"
    )->fetchAll();

    // Recent
    $recents = $pdo->query(
        "SELECT url, page_title, country_name, referrer, is_bot, created_at
         FROM page_views ORDER BY created_at DESC LIMIT 30"
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>訪問者カウンター 管理画面</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<style>
body { background:#f4f6fb; font-family:'Hiragino Kaku Gothic ProN','Yu Gothic',sans-serif; }
.navbar { background:linear-gradient(135deg,#1a2744 0%,#2a4a8a 100%); }
.stat-card { background:#fff; border-radius:.75rem; border:1px solid #e0e6f0;
             box-shadow:0 2px 8px rgba(0,0,0,.06); padding:1.25rem 1.5rem; }
.stat-num { font-size:2rem; font-weight:700; color:#1a2744; }
.stat-lbl { font-size:.8rem; color:#6c757d; }
.chart-card { background:#fff; border-radius:.75rem; border:1px solid #e0e6f0;
              box-shadow:0 2px 8px rgba(0,0,0,.06); padding:1.25rem; }
.table th { background:#f0f4ff; color:#1a2744; font-size:.82rem; }
.table td { font-size:.82rem; }
.badge-bot { background:#fce4ec; color:#c62828; }
.badge-human { background:#e8f5e9; color:#2e7d32; }
</style>
</head>
<body>
<nav class="navbar navbar-dark px-4 py-2 mb-4">
  <span class="navbar-brand fw-bold"><i class="bi bi-eye-fill me-2"></i>訪問者カウンター 管理画面</span>
  <?php if ($logged_in): ?>
  <div class="d-flex align-items-center gap-3">
    <a href="?bots=<?= $show_bots ? '0' : '1' ?>" class="btn btn-sm btn-outline-light">
      <i class="bi bi-robot me-1"></i><?= $show_bots ? 'Bot除外' : 'Bot含む' ?>
    </a>
    <form method="post" class="mb-0">
      <button name="logout" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>ログアウト</button>
    </form>
  </div>
  <?php endif; ?>
</nav>

<div class="container-fluid px-4" style="max-width:1400px">
<?php if (!$logged_in): ?>
<div class="row justify-content-center mt-5">
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h5 class="mb-3 fw-bold">管理者ログイン</h5>
        <?php if (!empty($login_error)): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label small">パスワード</label>
            <input type="password" name="pass" class="form-control" autofocus required>
          </div>
          <button class="btn btn-primary w-100">ログイン</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php else: ?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card text-center">
      <div class="stat-num"><?= number_format($today_pv) ?></div>
      <div class="stat-lbl"><i class="bi bi-eye me-1"></i>本日 PV</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card text-center">
      <div class="stat-num"><?= number_format($today_uv) ?></div>
      <div class="stat-lbl"><i class="bi bi-person me-1"></i>本日 UV</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card text-center">
      <div class="stat-num"><?= number_format($month_pv) ?></div>
      <div class="stat-lbl"><i class="bi bi-calendar-month me-1"></i>今月 PV</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card text-center">
      <div class="stat-num"><?= number_format($total_pv) ?></div>
      <div class="stat-lbl"><i class="bi bi-bar-chart me-1"></i>累計 PV</div>
    </div>
  </div>
</div>

<?php if ($total_bot > 0): ?>
<div class="alert alert-secondary py-2 small mb-4">
  <i class="bi bi-robot me-1"></i>bot記録: <?= number_format($total_bot) ?> 件
  <?= $show_bots ? '（現在 Bot含む表示）' : '（現在 Bot除外表示）' ?>
</div>
<?php endif; ?>

<!-- Charts Row -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="chart-card">
      <div class="fw-bold mb-3 small text-secondary">日別 PV / UV（過去30日）</div>
      <canvas id="dailyChart" height="100"></canvas>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="chart-card">
      <div class="fw-bold mb-3 small text-secondary">国別分布</div>
      <canvas id="countryChart" height="160"></canvas>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="chart-card">
      <div class="fw-bold mb-3 small text-secondary">月別 PV / UV（過去12ヶ月）</div>
      <canvas id="monthlyChart" height="70"></canvas>
    </div>
  </div>
</div>

<!-- Top Pages & Referrers -->
<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="chart-card">
      <div class="fw-bold mb-3 small text-secondary"><i class="bi bi-file-earmark me-1"></i>人気ページ TOP10</div>
      <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
          <thead><tr><th>URL</th><th class="text-center">PV</th><th class="text-center">UV</th></tr></thead>
          <tbody>
          <?php foreach ($top_pages as $p): ?>
          <tr>
            <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?php if ($p['page_title']): ?><small class="text-muted d-block"><?= htmlspecialchars($p['page_title']) ?></small><?php endif; ?>
              <small><?= htmlspecialchars($p['url']) ?></small>
            </td>
            <td class="text-center fw-bold"><?= number_format($p['pv']) ?></td>
            <td class="text-center text-secondary"><?= number_format($p['uv']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($top_pages)): ?><tr><td colspan="3" class="text-center text-muted">データなし</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="chart-card">
      <div class="fw-bold mb-3 small text-secondary"><i class="bi bi-link-45deg me-1"></i>参照元 TOP10</div>
      <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
          <thead><tr><th>参照元ドメイン</th><th class="text-center">件数</th></tr></thead>
          <tbody>
          <?php foreach ($top_refs as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['referrer']) ?></td>
            <td class="text-center fw-bold"><?= number_format($r['cnt']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($top_refs)): ?><tr><td colspan="2" class="text-center text-muted">データなし</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Recent Visits -->
<div class="chart-card mb-5">
  <div class="fw-bold mb-3 small text-secondary"><i class="bi bi-clock-history me-1"></i>最近の訪問（最新30件）</div>
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
      <thead><tr><th>日時</th><th>URL</th><th>国</th><th>参照元</th><th>種別</th></tr></thead>
      <tbody>
      <?php foreach ($recents as $v): ?>
      <tr>
        <td class="text-nowrap"><?= date('m/d H:i', strtotime($v['created_at'])) ?></td>
        <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <?= htmlspecialchars($v['url']) ?>
        </td>
        <td><?= htmlspecialchars($v['country_name'] ?: '-') ?></td>
        <td><?= htmlspecialchars($v['referrer'] ?: '-') ?></td>
        <td>
          <?php if ($v['is_bot']): ?>
          <span class="badge badge-bot">Bot</span>
          <?php else: ?>
          <span class="badge badge-human">人</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($recents)): ?><tr><td colspan="5" class="text-center text-muted py-3">データなし</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>
</div>

<?php if ($logged_in): ?>
<script>
const d_labels  = <?= json_encode($d_labels) ?>;
const d_pv      = <?= json_encode($d_pv) ?>;
const d_uv      = <?= json_encode($d_uv) ?>;
const m_labels  = <?= json_encode($m_labels) ?>;
const m_pv      = <?= json_encode($m_pv) ?>;
const m_uv      = <?= json_encode($m_uv) ?>;
const c_labels  = <?= json_encode(array_column($countries, 'cn')) ?>;
const c_data    = <?= json_encode(array_map(fn($r)=>(int)$r['cnt'], $countries)) ?>;

new Chart(document.getElementById('dailyChart'), {
  type: 'line',
  data: {
    labels: d_labels,
    datasets: [
      { label: 'PV', data: d_pv, borderColor: '#2a4a8a', backgroundColor: 'rgba(42,74,138,.1)', tension: .3, fill: true },
      { label: 'UV', data: d_uv, borderColor: '#e65100', backgroundColor: 'rgba(230,81,0,.1)', tension: .3, fill: true },
    ]
  },
  options: { plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('monthlyChart'), {
  type: 'bar',
  data: {
    labels: m_labels,
    datasets: [
      { label: 'PV', data: m_pv, backgroundColor: 'rgba(42,74,138,.7)' },
      { label: 'UV', data: m_uv, backgroundColor: 'rgba(230,81,0,.7)' },
    ]
  },
  options: { plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

if (c_labels.length > 0) {
  new Chart(document.getElementById('countryChart'), {
    type: 'doughnut',
    data: {
      labels: c_labels,
      datasets: [{ data: c_data,
        backgroundColor: ['#2a4a8a','#e65100','#2e7d32','#7b1fa2','#00838f',
                          '#c62828','#f9a825','#1565c0'],
        borderWidth: 2 }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
  });
}
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
