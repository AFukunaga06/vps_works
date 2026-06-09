<?php
require_once __DIR__.'/auth.php';
require_login();

// Stats
$today = date('Y-m-d');
$month_start = date('Y-m-01');

$pending_inquiries = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE status='pending'")->fetchColumn();
$first_visit_month = $pdo->prepare("SELECT COUNT(*) FROM persons WHERE status='first_visit' AND first_visit_date>=?");
$first_visit_month->execute([$month_start]);
$first_visit_month = $first_visit_month->fetchColumn();

$long_absent = $pdo->query("SELECT COUNT(*) FROM persons WHERE status IN ('regular','member','seeker') AND (last_attend_date IS NULL OR last_attend_date < DATE_SUB(CURDATE(),INTERVAL 2 MONTH))")->fetchColumn();

$today_tasks = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE due_date<=? AND status IN ('pending','in_progress') AND (assigned_user_id=? OR ? IN (SELECT id FROM users WHERE role='admin'))");
$today_tasks->execute([$today, $_SESSION['user_id'], $_SESSION['user_id']]);
$today_tasks = $today_tasks->fetchColumn();

$upcoming_bdays = $pdo->query("SELECT COUNT(*) FROM persons WHERE status NOT IN ('ended') AND birth_date IS NOT NULL AND DATE_FORMAT(birth_date,'%m-%d') BETWEEN DATE_FORMAT(CURDATE(),'%m-%d') AND DATE_FORMAT(DATE_ADD(CURDATE(),INTERVAL 7 DAY),'%m-%d')")->fetchColumn();

$alert_count = $pdo->query("SELECT COUNT(*) FROM persons WHERE alert_flag=1 AND status NOT IN ('ended')")->fetchColumn();

// Pending inquiries list
$inq_list = $pdo->query("SELECT i.*, p.last_name, p.first_name FROM inquiries i LEFT JOIN persons p ON i.person_id=p.id WHERE i.status='pending' ORDER BY i.inquiry_date DESC LIMIT 5")->fetchAll();

// Today's tasks
$task_list = $pdo->prepare("SELECT t.*, p.last_name, p.first_name FROM tasks t LEFT JOIN persons p ON t.person_id=p.id WHERE t.due_date<=? AND t.status IN ('pending','in_progress') ORDER BY t.due_date ASC LIMIT 10");
$task_list->execute([$today]);
$task_list = $task_list->fetchAll();

// Recent first visitors
$recent_first = $pdo->query("SELECT * FROM persons WHERE status='first_visit' ORDER BY first_visit_date DESC LIMIT 5")->fetchAll();

include __DIR__.'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-speedometer2"></i> ダッシュボード</h4>
  <small class="text-muted"><?= date('Y年m月d日') ?></small>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-2">
    <div class="card stat-card text-center p-3">
      <div class="fs-2 fw-bold text-primary"><?= $pending_inquiries ?></div>
      <div class="small text-muted">未対応問い合わせ</div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card stat-card text-center p-3" style="border-top-color:#198754">
      <div class="fs-2 fw-bold text-success"><?= $first_visit_month ?></div>
      <div class="small text-muted">今月の初来会者</div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card stat-card text-center p-3" style="border-top-color:#fd7e14">
      <div class="fs-2 fw-bold text-warning"><?= $long_absent ?></div>
      <div class="small text-muted">長期未出席</div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card stat-card text-center p-3" style="border-top-color:#6f42c1">
      <div class="fs-2 fw-bold" style="color:#6f42c1"><?= $today_tasks ?></div>
      <div class="small text-muted">今日のタスク</div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card stat-card text-center p-3" style="border-top-color:#0dcaf0">
      <div class="fs-2 fw-bold text-info"><?= $upcoming_bdays ?></div>
      <div class="small text-muted">今週の誕生日</div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card stat-card text-center p-3" style="border-top-color:#dc3545">
      <div class="fs-2 fw-bold text-danger"><?= $alert_count ?></div>
      <div class="small text-muted">要注意フラグ</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-chat-dots"></i> 未対応の問い合わせ</span>
        <a href="<?= BASE_URL ?>/inquiries/" class="btn btn-sm btn-outline-primary">一覧</a>
      </div>
      <div class="card-body p-0">
        <?php if(empty($inq_list)): ?>
        <p class="text-muted p-3 mb-0 small">未対応の問い合わせはありません</p>
        <?php else: ?>
        <table class="table table-sm mb-0">
          <tr><th>日付</th><th>種別</th><th>氏名/問い合わせ者</th><th>状況</th></tr>
          <?php foreach($inq_list as $i): ?>
          <tr>
            <td class="small"><?= h($i['inquiry_date']) ?></td>
            <td class="small"><?= inquiry_type_label($i['inquiry_type']) ?></td>
            <td><a href="<?= BASE_URL ?>/inquiries/form.php?id=<?= $i['id'] ?>" class="small">
              <?= $i['last_name'] ? h($i['last_name'].$i['first_name']) : h($i['inquirer_name'] ?? '不明') ?>
            </a></td>
            <td><span class="badge bg-warning text-dark small">未対応</span></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-check2-square"></i> 期限のタスク</span>
        <a href="<?= BASE_URL ?>/tasks/" class="btn btn-sm btn-outline-primary">一覧</a>
      </div>
      <div class="card-body p-0">
        <?php if(empty($task_list)): ?>
        <p class="text-muted p-3 mb-0 small">期限のタスクはありません</p>
        <?php else: ?>
        <table class="table table-sm mb-0">
          <tr><th>期限</th><th>タイトル</th><th>対象者</th></tr>
          <?php foreach($task_list as $t): ?>
          <tr>
            <td class="small <?= $t['due_date']<$today?'text-danger fw-bold':'' ?>"><?= h($t['due_date']) ?></td>
            <td><a href="<?= BASE_URL ?>/tasks/form.php?id=<?= $t['id'] ?>" class="small"><?= h($t['title']) ?></a></td>
            <td class="small"><?= $t['last_name'] ? h($t['last_name'].$t['first_name']) : '-' ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-person-plus"></i> 最近の初来会者</span>
        <a href="<?= BASE_URL ?>/persons/?status=first_visit" class="btn btn-sm btn-outline-success">一覧</a>
      </div>
      <div class="card-body p-0">
        <?php if(empty($recent_first)): ?>
        <p class="text-muted p-3 mb-0 small">初来会者はいません</p>
        <?php else: ?>
        <table class="table table-sm mb-0">
          <tr><th>来会日</th><th>氏名</th><th>電話</th><th>メール</th><th>担当者</th></tr>
          <?php foreach($recent_first as $p): ?>
          <tr>
            <td class="small"><?= h($p['first_visit_date']) ?></td>
            <td><a href="<?= BASE_URL ?>/persons/view.php?id=<?= $p['id'] ?>"><?= h($p['last_name'].$p['first_name']) ?></a></td>
            <td class="small"><?= h($p['phone']) ?></td>
            <td class="small"><?= h($p['email']) ?></td>
            <td class="small">-</td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__.'/footer.php'; ?>
