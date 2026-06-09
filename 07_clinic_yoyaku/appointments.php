<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();

$pdo = getPDO();

$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d', strtotime('+6 days'));
$status   = $_GET['status']    ?? '';
$doctorId = (int)($_GET['doctor_id'] ?? 0);

$where  = ['a.appt_date BETWEEN ? AND ?'];
$params = [$dateFrom, $dateTo];

if ($status !== '') {
    $where[]  = 'a.status = ?';
    $params[] = $status;
}
if ($doctorId > 0) {
    $where[]  = 'a.doctor_id = ?';
    $params[] = $doctorId;
}

$sql = 'SELECT a.*,
               CONCAT(p.last_name," ",p.first_name) AS patient_name,
               p.patient_no,
               d.name AS doctor_name
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        JOIN doctors  d ON d.id = a.doctor_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY a.appt_date, a.start_time';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appts = $stmt->fetchAll();

$doctors = $pdo->query('SELECT * FROM doctors ORDER BY id')->fetchAll();

pageHead('予約一覧');
navbar();
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-calendar3"></i> 予約一覧</h5>
    <a href="appointment_new.php" class="btn btn-primary btn-sm">
      <i class="bi bi-calendar-plus"></i> 新規予約
    </a>
  </div>

  <form class="row g-2 mb-3" method="get">
    <div class="col-auto">
      <input type="date" name="date_from" class="form-control form-control-sm" value="<?= h($dateFrom) ?>">
    </div>
    <div class="col-auto align-self-center">〜</div>
    <div class="col-auto">
      <input type="date" name="date_to" class="form-control form-control-sm" value="<?= h($dateTo) ?>">
    </div>
    <div class="col-auto">
      <select name="doctor_id" class="form-select form-select-sm">
        <option value="">全医師</option>
        <?php foreach ($doctors as $doc): ?>
          <option value="<?= (int)$doc['id'] ?>" <?= $doctorId === (int)$doc['id'] ? 'selected' : '' ?>>
            <?= h($doc['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <select name="status" class="form-select form-select-sm">
        <option value="">全ステータス</option>
        <?php foreach (['reserved','confirmed','completed','cancelled','no_show'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= statusLabel($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-sm btn-secondary">
        <i class="bi bi-funnel"></i> 絞込
      </button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-hover table-sm">
      <thead class="table-light">
        <tr>
          <th>日付</th><th>時間</th><th>患者番号</th><th>患者名</th>
          <th>担当医</th><th>来院理由</th><th>状態</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($appts as $a): ?>
        <tr class="<?= $a['status'] === 'completed' ? 'appt-row-completed' : ($a['status'] === 'cancelled' ? 'appt-row-cancelled' : '') ?>">
          <td><?= h(formatDate($a['appt_date'])) ?></td>
          <td class="text-nowrap"><?= h(formatTime($a['start_time'])) ?>-<?= h(formatTime($a['end_time'])) ?></td>
          <td><?= h($a['patient_no']) ?></td>
          <td><a href="patient_view.php?id=<?= (int)$a['patient_id'] ?>"><?= h($a['patient_name']) ?></a></td>
          <td><?= h($a['doctor_name']) ?></td>
          <td class="text-truncate" style="max-width:150px"><?= h($a['reason']) ?></td>
          <td><?= statusBadge($a['status']) ?></td>
          <td class="text-nowrap">
            <a href="appointment_view.php?id=<?= (int)$a['id'] ?>"
               class="btn btn-sm btn-outline-secondary">詳細</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($appts)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">予約が見つかりません</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted small"><?= count($appts) ?> 件</p>
</div>
<?php pageFooter(); ?>
