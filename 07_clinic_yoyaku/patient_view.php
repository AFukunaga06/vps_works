<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);
$patient = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
$patient->execute([$id]);
$p = $patient->fetch();
if (!$p) { header('Location: patients.php'); exit; }

// 予約履歴
$appts = $pdo->prepare(
    'SELECT a.*, d.name AS doctor_name
     FROM appointments a
     JOIN doctors d ON d.id = a.doctor_id
     WHERE a.patient_id = ?
     ORDER BY a.appt_date DESC, a.start_time DESC'
);
$appts->execute([$id]);
$apptList = $appts->fetchAll();

// 診察履歴
$consts = $pdo->prepare(
    'SELECT c.*, a.appt_date, d.name AS doctor_name
     FROM consultations c
     JOIN appointments a ON a.id = c.appointment_id
     JOIN doctors d ON d.id = c.doctor_id
     WHERE c.patient_id = ?
     ORDER BY a.appt_date DESC'
);
$consts->execute([$id]);
$constList = $consts->fetchAll();

// 次回受診提案
$lastConst = $constList[0] ?? null;
$suggested = null;
if ($lastConst && $lastConst['next_visit_days']) {
    $suggested = date('Y-m-d', strtotime($lastConst['appt_date'] . ' +' . $lastConst['next_visit_days'] . ' days'));
}

$msg = $_GET['msg'] ?? '';
pageHead(h($p['last_name'] . '　' . $p['first_name']) . ' 患者詳細');
navbar();
?>
<div class="container-fluid">
  <?php if ($msg === 'created'): ?>
    <div class="alert alert-success alert-dismissible fade show">患者を登録しました。<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($msg === 'updated'): ?>
    <div class="alert alert-success alert-dismissible fade show">患者情報を更新しました。<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
      <i class="bi bi-person-badge"></i>
      <?= h($p['last_name'] . '　' . $p['first_name']) ?>
      <small class="text-muted ms-2"><?= h($p['patient_no']) ?></small>
    </h5>
    <div>
      <a href="patient_edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-pencil"></i> 編集
      </a>
      <a href="appointment_new.php?patient_id=<?= $id ?>" class="btn btn-sm btn-primary ms-1">
        <i class="bi bi-calendar-plus"></i> 予約追加
      </a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-person-lines-fill"></i> 基本情報</div>
        <div class="card-body">
          <table class="table table-sm mb-0">
            <tr><th>氏名</th><td><?= h($p['last_name'] . '　' . $p['first_name']) ?><br>
              <small class="text-muted"><?= h($p['last_name_kana'] . '　' . $p['first_name_kana']) ?></small>
            </td></tr>
            <tr><th>生年月日</th><td>
              <?= $p['birth_date'] ? h(formatDate($p['birth_date'])) . '（' . age($p['birth_date']) . '歳）' : '未登録' ?>
            </td></tr>
            <tr><th>性別</th><td><?= h(genderLabel($p['gender'])) ?></td></tr>
            <tr><th>血液型</th><td><?= h($p['blood_type']) ?></td></tr>
            <tr><th>電話</th><td><?= h($p['phone']) ?></td></tr>
            <tr><th>メール</th><td><?= h($p['email']) ?></td></tr>
            <tr><th>住所</th><td><?= h($p['postal_code']) ?> <?= h($p['address']) ?></td></tr>
            <tr><th>保険証番号</th><td><?= h($p['insurance_no']) ?></td></tr>
            <tr><th>アレルギー</th><td class="text-danger"><?= h($p['allergies']) ?></td></tr>
            <tr><th>既往歴</th><td><?= nl2br(h($p['medical_history'])) ?></td></tr>
            <tr><th>備考</th><td><?= nl2br(h($p['note'])) ?></td></tr>
          </table>
        </div>
      </div>

      <?php if ($suggested): ?>
      <div class="card border-info mb-3">
        <div class="card-header bg-info text-white"><i class="bi bi-calendar-check"></i> 次回受診提案</div>
        <div class="card-body">
          <p class="mb-1">推奨受診日：<strong><?= h(formatDate($suggested)) ?>頃</strong></p>
          <p class="mb-2 small text-muted"><?= h($lastConst['next_visit_note']) ?></p>
          <a href="appointment_new.php?patient_id=<?= $id ?>&suggest_date=<?= h($suggested) ?>"
             class="btn btn-sm btn-info text-white">
            <i class="bi bi-calendar-plus"></i> この日で予約する
          </a>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="col-lg-8">
      <ul class="nav nav-tabs mb-3" id="patientTabs">
        <li class="nav-item">
          <a class="nav-link active" data-bs-toggle="tab" href="#tabAppts">予約履歴</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#tabConst">診察履歴</a>
        </li>
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="tabAppts">
          <div class="table-responsive">
            <table class="table table-sm table-hover">
              <thead class="table-light">
                <tr><th>日付</th><th>時間</th><th>担当医</th><th>来院理由</th><th>状態</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($apptList as $a): ?>
                <tr>
                  <td><?= h(formatDate($a['appt_date'])) ?></td>
                  <td><?= h(formatTime($a['start_time'])) ?></td>
                  <td><?= h($a['doctor_name']) ?></td>
                  <td class="text-truncate" style="max-width:120px"><?= h($a['reason']) ?></td>
                  <td><?= statusBadge($a['status']) ?></td>
                  <td><a href="appointment_view.php?id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-secondary">詳細</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($apptList)): ?>
                  <tr><td colspan="6" class="text-center text-muted">予約履歴なし</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="tab-pane fade" id="tabConst">
          <?php foreach ($constList as $c): ?>
          <div class="card mb-3 border-success">
            <div class="card-header d-flex justify-content-between">
              <span><i class="bi bi-clipboard2-pulse"></i> <?= h(formatDate($c['appt_date'])) ?></span>
              <small class="text-muted">担当：<?= h($c['doctor_name']) ?></small>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <table class="table table-sm mb-0">
                    <?php if ($c['bp_sys']): ?>
                    <tr><th class="text-nowrap">血圧</th><td><?= h($c['bp_sys']) ?>/<?= h($c['bp_dia']) ?> mmHg</td></tr>
                    <?php endif; ?>
                    <?php if ($c['temperature']): ?>
                    <tr><th>体温</th><td><?= h($c['temperature']) ?> ℃</td></tr>
                    <?php endif; ?>
                    <?php if ($c['spo2']): ?>
                    <tr><th>SpO2</th><td><?= h($c['spo2']) ?> %</td></tr>
                    <?php endif; ?>
                    <?php if ($c['weight']): ?>
                    <tr><th>体重</th><td><?= h($c['weight']) ?> kg</td></tr>
                    <?php endif; ?>
                  </table>
                </div>
                <div class="col-md-6">
                  <?php if ($c['assessment']): ?>
                    <strong class="text-success">診断：</strong><?= h($c['assessment']) ?><br>
                  <?php endif; ?>
                  <?php if ($c['prescription']): ?>
                    <strong>処方：</strong><?= nl2br(h($c['prescription'])) ?><br>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($c['plan']): ?>
                <p class="mt-2 mb-0"><strong>方針：</strong><?= nl2br(h($c['plan'])) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($constList)): ?>
            <p class="text-muted text-center py-3">診察履歴なし</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php pageFooter(); ?>
