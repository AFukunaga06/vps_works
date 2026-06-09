<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$pdo = getDB();

// 統計情報
$totalMembers  = $pdo->query('SELECT COUNT(*) FROM members WHERE is_active = 1')->fetchColumn();
$todayRecords  = $pdo->query("SELECT COUNT(*) FROM reading_daily_logs WHERE reading_date = CURDATE()")->fetchColumn();
$totalRecords  = $pdo->query('SELECT COUNT(*) FROM reading_daily_logs')->fetchColumn();
$doneThisMonth = $pdo->query("SELECT COUNT(*) FROM reading_daily_logs WHERE status = 2 AND reading_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();

// 今日の記録状況
$todayStmt = $pdo->query("
    SELECT m.name, l.passage_summary, l.status, l.note
    FROM reading_daily_logs l
    JOIN members m ON l.member_id = m.id
    WHERE l.reading_date = CURDATE()
    ORDER BY l.status DESC, m.furigana
");
$todayLogs = $todayStmt->fetchAll();

// 最近の記録（直近10件）
$recentStmt = $pdo->query("
    SELECT l.reading_date, m.name, l.passage_summary, l.status
    FROM reading_daily_logs l
    JOIN members m ON l.member_id = m.id
    ORDER BY l.reading_date DESC, l.updated_at DESC
    LIMIT 10
");
$recentLogs = $recentStmt->fetchAll();

// メンバーごとの最終記録日
$memberStatusStmt = $pdo->query("
    SELECT m.id, m.name, m.furigana,
           MAX(l.reading_date) AS last_read,
           SUM(l.status = 2) AS done_count,
           COUNT(l.id) AS total_count
    FROM members m
    LEFT JOIN reading_daily_logs l ON m.id = l.member_id
    WHERE m.is_active = 1
    GROUP BY m.id, m.name, m.furigana
    ORDER BY m.furigana
");
$memberStatuses = $memberStatusStmt->fetchAll();

renderHeader('ダッシュボード', 'dashboard');
$statusLabels = STATUS_LABELS;
$statusBadges = STATUS_BADGES;
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h4 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>ダッシュボード</h4>
  <a href="record_form.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> 通読記録を追加</a>
</div>

<!-- 統計カード -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="fs-2 fw-bold text-primary"><?= $totalMembers ?></div>
      <div class="text-muted small">有効メンバー数</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="fs-2 fw-bold text-success"><?= $todayRecords ?></div>
      <div class="text-muted small">今日の記録件数</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="fs-2 fw-bold text-info"><?= $doneThisMonth ?></div>
      <div class="text-muted small">今月の読了記録</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="fs-2 fw-bold text-secondary"><?= $totalRecords ?></div>
      <div class="text-muted small">総記録件数</div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- 今日の記録 -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar-check me-1"></i>今日の通読状況 (<?= date('Y年n月j日') ?>)</span>
        <a href="records.php?date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-secondary">一覧</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($todayLogs)): ?>
          <p class="text-muted text-center py-4">今日の記録はまだありません。</p>
        <?php else: ?>
          <table class="table table-hover mb-0">
            <thead><tr><th>メンバー</th><th>通読箇所</th><th>状態</th></tr></thead>
            <tbody>
            <?php foreach ($todayLogs as $log): ?>
              <tr>
                <td><?= h($log['name']) ?></td>
                <td class="small"><?= h($log['passage_summary'] ?? '—') ?></td>
                <td><span class="badge bg-<?= $statusBadges[$log['status']] ?>"><?= $statusLabels[$log['status']] ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- メンバー別進捗 -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people me-1"></i>メンバー別 最終通読日</span>
        <a href="members.php" class="btn btn-sm btn-outline-secondary">管理</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th>メンバー</th><th>最終通読日</th><th>読了/記録</th></tr></thead>
          <tbody>
          <?php foreach ($memberStatuses as $ms): ?>
            <tr>
              <td><a href="records.php?member_id=<?= $ms['id'] ?>"><?= h($ms['name']) ?></a></td>
              <td class="small text-muted"><?= $ms['last_read'] ? date('n/j', strtotime($ms['last_read'])) : '—' ?></td>
              <td class="small"><?= $ms['done_count'] ?> / <?= $ms['total_count'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- 最近の記録 -->
<div class="card mt-4">
  <div class="card-header"><i class="bi bi-clock-history me-1"></i>最近の通読記録</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>日付</th><th>メンバー</th><th>通読箇所</th><th>状態</th></tr></thead>
      <tbody>
      <?php foreach ($recentLogs as $log): ?>
        <tr>
          <td class="small"><?= date('n/j', strtotime($log['reading_date'])) ?></td>
          <td><?= h($log['name']) ?></td>
          <td class="small"><?= h($log['passage_summary'] ?? '—') ?></td>
          <td><span class="badge bg-<?= $statusBadges[$log['status']] ?>"><?= $statusLabels[$log['status']] ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php renderFooter(); ?>
