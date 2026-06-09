<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT a.*,
            CONCAT(p.last_name," ",p.first_name) AS patient_name,
            p.patient_no, p.id AS patient_id, p.allergies, p.blood_type,
            d.name AS doctor_name
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     JOIN doctors  d ON d.id = a.doctor_id
     WHERE a.id = ?'
);
$stmt->execute([$id]);
$appt = $stmt->fetch();
if (!$appt) { header('Location: appointments.php'); exit; }

// 問診票
$qStmt = $pdo->prepare('SELECT * FROM questionnaires WHERE appointment_id = ?');
$qStmt->execute([$id]);
$q = $qStmt->fetch();

// 診察記録
$cStmt = $pdo->prepare('SELECT * FROM consultations WHERE appointment_id = ?');
$cStmt->execute([$id]);
$c = $cStmt->fetch();

// ステータス変更
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $newStatus = $_POST['new_status'] ?? '';
        $allowed = ['reserved','confirmed','completed','cancelled','no_show'];
        if (in_array($newStatus, $allowed, true)) {
            $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?')
                ->execute([$newStatus, $id]);
            header('Location: appointment_view.php?id=' . $id . '&msg=updated');
            exit;
        }
    }
}

$msg = $_GET['msg'] ?? '';
pageHead('予約詳細');
navbar();
?>
<div class="container-fluid">
  <?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
      更新しました。<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
      <i class="bi bi-calendar-event"></i> 予約詳細 #<?= $id ?>
    </h5>
    <a href="appointments.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left"></i> 一覧へ
    </a>
  </div>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-calendar3"></i> 予約情報</div>
        <div class="card-body">
          <table class="table table-sm mb-0">
            <tr><th>患者</th><td>
              <a href="patient_view.php?id=<?= (int)$appt['patient_id'] ?>">
                <?= h($appt['patient_name']) ?>
              </a>
              <small class="text-muted ms-1"><?= h($appt['patient_no']) ?></small>
            </td></tr>
            <tr><th>血液型</th><td><?= h($appt['blood_type']) ?></td></tr>
            <tr><th class="text-danger">アレルギー</th><td class="text-danger"><?= h($appt['allergies']) ?></td></tr>
            <tr><th>担当医</th><td><?= h($appt['doctor_name']) ?></td></tr>
            <tr><th>日時</th><td>
              <?= h(formatDate($appt['appt_date'])) ?>
              <?= h(formatTime($appt['start_time'])) ?>〜<?= h(formatTime($appt['end_time'])) ?>
            </td></tr>
            <tr><th>来院理由</th><td><?= h($appt['reason']) ?></td></tr>
            <tr><th>メモ</th><td><?= nl2br(h($appt['note'])) ?></td></tr>
            <tr><th>状態</th><td><?= statusBadge($appt['status']) ?></td></tr>
          </table>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-arrow-repeat"></i> ステータス変更</div>
        <div class="card-body">
          <form method="post" class="d-flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="change_status" value="1">
            <select name="new_status" class="form-select form-select-sm">
              <?php foreach (['reserved','confirmed','completed','cancelled','no_show'] as $s): ?>
                <option value="<?= $s ?>" <?= $appt['status'] === $s ? 'selected' : '' ?>>
                  <?= statusLabel($s) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-secondary text-nowrap">変更</button>
          </form>
        </div>
      </div>

      <?php if (!$q): ?>
      <div class="card border-warning">
        <div class="card-body text-center py-3">
          <p class="mb-2 text-muted">問診票が未提出です</p>
          <a href="questionnaire.php?appointment_id=<?= $id ?>"
             class="btn btn-warning btn-sm">
            <i class="bi bi-file-earmark-medical"></i> 問診票を入力
          </a>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="col-lg-7">
      <!-- 問診票 -->
      <?php if ($q): ?>
      <div class="card mb-3 border-warning">
        <div class="card-header bg-warning text-dark">
          <i class="bi bi-file-earmark-medical"></i> 問診票
          <a href="questionnaire.php?appointment_id=<?= $id ?>"
             class="btn btn-sm btn-outline-dark ms-2">編集</a>
        </div>
        <div class="card-body">
          <div class="row g-2">
            <div class="col-md-8">
              <strong>主訴：</strong><?= h($q['chief_complaint']) ?><br>
              <strong>症状の始まり：</strong><?= h($q['symptom_since']) ?><br>
              <?php if ($q['other_symptoms']): ?>
                <strong>その他症状：</strong><?= h($q['other_symptoms']) ?><br>
              <?php endif; ?>
              <?php if ($q['current_meds']): ?>
                <strong>服薬中：</strong><?= h($q['current_meds']) ?><br>
              <?php endif; ?>
            </div>
            <div class="col-md-4">
              <strong>痛みの強さ：</strong><?= h($q['pain_scale']) ?>/10<br>
              <?php
              $symMap = ['fever'=>'発熱','cough'=>'咳','nausea'=>'吐気','diarrhea'=>'下痢','fatigue'=>'倦怠感'];
              $syms = [];
              foreach ($symMap as $k => $label) if ($q[$k]) $syms[] = $label;
              if ($syms): ?>
                <strong>症状：</strong><?= h(implode('・', $syms)) ?><br>
              <?php endif; ?>
              <?php if ($q['pregnancy']): ?><span class="badge bg-danger">妊娠中</span><?php endif; ?>
            </div>
          </div>
          <?php if ($q['emergency_contact_name']): ?>
            <hr class="my-2">
            <small class="text-muted">
              緊急連絡先：<?= h($q['emergency_contact_name']) ?> <?= h($q['emergency_contact_phone']) ?>
            </small>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- 診察記録 -->
      <?php if ($c): ?>
      <div class="card border-success">
        <div class="card-header bg-success text-white">
          <i class="bi bi-clipboard2-pulse"></i> 診察記録 (SOAP)
          <a href="consultation_edit.php?appointment_id=<?= $id ?>"
             class="btn btn-sm btn-outline-light ms-2">編集</a>
        </div>
        <div class="card-body">
          <div class="row g-2 mb-2">
            <?php
            $vitals = [
              '血圧' => $c['bp_sys'] ? "{$c['bp_sys']}/{$c['bp_dia']} mmHg" : null,
              '体温' => $c['temperature'] ? "{$c['temperature']} ℃" : null,
              'SpO2' => $c['spo2'] ? "{$c['spo2']} %" : null,
              '体重' => $c['weight'] ? "{$c['weight']} kg" : null,
              '身長' => $c['height'] ? "{$c['height']} cm" : null,
            ];
            ?>
            <?php foreach ($vitals as $vk => $vv): if ($vv): ?>
              <div class="col-auto">
                <span class="badge bg-light text-dark border"><?= $vk ?>: <?= h($vv) ?></span>
              </div>
            <?php endif; endforeach; ?>
          </div>
          <?php foreach ([
              'S（主訴）'  => $c['subjective'],
              'O（所見）'  => $c['objective'],
              'A（診断）'  => $c['assessment'],
              'P（方針）'  => $c['plan'],
              '処方'        => $c['prescription'],
          ] as $lbl => $val): if ($val): ?>
            <p class="mb-1"><strong><?= $lbl ?>：</strong><?= nl2br(h($val)) ?></p>
          <?php endif; endforeach; ?>

          <?php if ($c['next_visit_note'] || $c['next_visit_days']): ?>
          <div class="next-visit-box mt-3">
            <i class="bi bi-calendar-check"></i>
            <strong>次回受診提案：</strong>
            <?php if ($c['next_visit_days']): ?>
              <?= h($c['next_visit_days']) ?>日後（<?= h(formatDate(date('Y-m-d', strtotime($appt['appt_date'] . ' +' . $c['next_visit_days'] . ' days')))) ?>頃）
            <?php endif; ?>
            <?php if ($c['next_visit_note']): ?>
              — <?= h($c['next_visit_note']) ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="card border-secondary">
        <div class="card-body text-center py-3">
          <p class="mb-2 text-muted">診察記録が未入力です</p>
          <a href="consultation_edit.php?appointment_id=<?= $id ?>"
             class="btn btn-success btn-sm">
            <i class="bi bi-clipboard2-pulse"></i> 診察記録を入力
          </a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php pageFooter(); ?>
