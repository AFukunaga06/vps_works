<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();

$pdo = getPDO();
$today = date('Y-m-d');
$dow   = (int)date('N'); // 1=月...7=日

// 本日の予約数
$todayCount = $pdo->prepare(
    'SELECT COUNT(*) FROM appointments WHERE appt_date = ? AND status NOT IN ("cancelled","no_show")'
);
$todayCount->execute([$today]);
$cntToday = (int)$todayCount->fetchColumn();

// 今週の予約数
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));
$weekStmt  = $pdo->prepare(
    'SELECT COUNT(*) FROM appointments WHERE appt_date BETWEEN ? AND ? AND status NOT IN ("cancelled","no_show")'
);
$weekStmt->execute([$weekStart, $weekEnd]);
$cntWeek = (int)$weekStmt->fetchColumn();

// 患者総数
$cntPatients = (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();

// 未対応（reserved/confirmed）
$pendingStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM appointments WHERE appt_date >= ? AND status IN ("reserved","confirmed")'
);
$pendingStmt->execute([$today]);
$cntPending = (int)$pendingStmt->fetchColumn();

// 本日の予約一覧
$todayAppts = $pdo->prepare(
    'SELECT a.*, CONCAT(p.last_name," ",p.first_name) AS patient_name,
            d.name AS doctor_name
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     JOIN doctors  d ON d.id = a.doctor_id
     WHERE a.appt_date = ?
     ORDER BY a.start_time'
);
$todayAppts->execute([$today]);
$appts = $todayAppts->fetchAll();

// 次回受診提案（next_visit_days が設定されていて期日が近い）
$nextVisit = $pdo->query(
    'SELECT c.*, CONCAT(p.last_name," ",p.first_name) AS patient_name,
            p.id AS patient_id,
            a.appt_date,
            DATE_ADD(a.appt_date, INTERVAL c.next_visit_days DAY) AS suggested_date
     FROM consultations c
     JOIN appointments a ON a.id = c.appointment_id
     JOIN patients    p ON p.id = c.patient_id
     WHERE c.next_visit_days IS NOT NULL
       AND DATE_ADD(a.appt_date, INTERVAL c.next_visit_days DAY) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
       AND NOT EXISTS (
         SELECT 1 FROM appointments a2
         WHERE a2.patient_id = c.patient_id
           AND a2.appt_date > a.appt_date
           AND a2.status NOT IN ("cancelled","no_show")
       )
     ORDER BY suggested_date
     LIMIT 10'
)->fetchAll();

pageHead('ダッシュボード');
navbar();
?>
<div class="container-fluid">
  <h5 class="mb-3 text-secondary"><i class="bi bi-speedometer2"></i> ダッシュボード</h5>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card stat-card text-center border-primary">
        <div class="card-body">
          <div class="text-primary display-6"><?= $cntToday ?></div>
          <div class="text-muted small">本日の予約</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card stat-card text-center border-success">
        <div class="card-body">
          <div class="text-success display-6"><?= $cntWeek ?></div>
          <div class="text-muted small">今週の予約</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card stat-card text-center border-info">
        <div class="card-body">
          <div class="text-info display-6"><?= $cntPatients ?></div>
          <div class="text-muted small">患者総数</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card stat-card text-center border-warning">
        <div class="card-body">
          <div class="text-warning display-6"><?= $cntPending ?></div>
          <div class="text-muted small">未対応予約</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-calendar-day"></i> 本日（<?= h(date('Y/m/d (D)', strtotime($today))) ?>）の予約</span>
          <a href="appointment_new.php" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle"></i> 新規
          </a>
        </div>
        <div class="card-body p-0">
          <?php if (empty($appts)): ?>
            <p class="text-center text-muted py-4">本日の予約はありません</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>時間</th><th>患者名</th><th>担当医</th><th>来院理由</th><th>状態</th><th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($appts as $a): ?>
                <tr class="<?= $a['status'] === 'completed' ? 'appt-row-completed' : ($a['status'] === 'cancelled' ? 'appt-row-cancelled' : '') ?>">
                  <td><?= h(formatTime($a['start_time'])) ?></td>
                  <td>
                    <a href="patient_view.php?id=<?= (int)$a['patient_id'] ?>">
                      <?= h($a['patient_name']) ?>
                    </a>
                  </td>
                  <td><?= h($a['doctor_name']) ?></td>
                  <td class="text-truncate" style="max-width:160px"><?= h($a['reason']) ?></td>
                  <td><?= statusBadge($a['status']) ?></td>
                  <td>
                    <a href="appointment_view.php?id=<?= (int)$a['id'] ?>"
                       class="btn btn-sm btn-outline-secondary">詳細</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header"><i class="bi bi-bell"></i> 次回受診提案（14日以内）</div>
        <div class="card-body">
          <?php if (empty($nextVisit)): ?>
            <p class="text-muted small">対象患者はいません</p>
          <?php else: ?>
            <?php foreach ($nextVisit as $nv): ?>
            <div class="next-visit-box mb-2">
              <div class="d-flex justify-content-between">
                <strong><a href="patient_view.php?id=<?= (int)$nv['patient_id'] ?>">
                  <?= h($nv['patient_name']) ?>
                </a></strong>
                <span class="text-muted small"><?= h(formatDate($nv['suggested_date'])) ?>頃</span>
              </div>
              <div class="small text-muted"><?= h(mb_strimwidth($nv['next_visit_note'] ?? '', 0, 40, '…')) ?></div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php pageFooter(); ?>
