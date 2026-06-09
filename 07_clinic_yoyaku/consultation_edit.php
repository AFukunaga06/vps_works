<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();

$pdo    = getPDO();
$apptId = (int)($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? 0);

$apptStmt = $pdo->prepare(
    'SELECT a.*, CONCAT(p.last_name," ",p.first_name) AS patient_name,
            p.id AS patient_id, p.allergies,
            d.name AS doctor_name
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     JOIN doctors  d ON d.id = a.doctor_id
     WHERE a.id = ?'
);
$apptStmt->execute([$apptId]);
$appt = $apptStmt->fetch();
if (!$appt) { header('Location: appointments.php'); exit; }

// 問診票参照
$q = $pdo->prepare('SELECT * FROM questionnaires WHERE appointment_id = ?');
$q->execute([$apptId]);
$qData = $q->fetch();

$existing = $pdo->prepare('SELECT * FROM consultations WHERE appointment_id = ?');
$existing->execute([$apptId]);
$c = $existing->fetch();

$errors = [];
$data   = $c ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'セッションエラーが発生しました。';
    } else {
        $textFields = ['subjective','objective','assessment','plan','prescription',
                       'next_visit_note'];
        foreach ($textFields as $f) $data[$f] = trim($_POST[$f] ?? '');
        $data['next_visit_days'] = $_POST['next_visit_days'] !== '' ? (int)$_POST['next_visit_days'] : null;
        $data['bp_sys']      = $_POST['bp_sys'] !== '' ? (int)$_POST['bp_sys'] : null;
        $data['bp_dia']      = $_POST['bp_dia'] !== '' ? (int)$_POST['bp_dia'] : null;
        $data['temperature'] = $_POST['temperature'] !== '' ? (float)$_POST['temperature'] : null;
        $data['spo2']        = $_POST['spo2'] !== '' ? (int)$_POST['spo2'] : null;
        $data['weight']      = $_POST['weight'] !== '' ? (float)$_POST['weight'] : null;
        $data['height']      = $_POST['height'] !== '' ? (float)$_POST['height'] : null;

        if ($data['assessment'] === '') $errors[] = '診断（A）を入力してください。';

        if (empty($errors)) {
            $vals = [
                $data['subjective'], $data['objective'], $data['assessment'], $data['plan'],
                $data['prescription'], $data['next_visit_note'], $data['next_visit_days'],
                $data['bp_sys'], $data['bp_dia'], $data['temperature'],
                $data['spo2'], $data['weight'], $data['height'],
            ];
            if ($c) {
                $pdo->prepare(
                    'UPDATE consultations SET
                       subjective=?, objective=?, assessment=?, plan=?,
                       prescription=?, next_visit_note=?, next_visit_days=?,
                       bp_sys=?, bp_dia=?, temperature=?, spo2=?, weight=?, height=?
                     WHERE appointment_id=?'
                )->execute(array_merge($vals, [$apptId]));
            } else {
                $pdo->prepare(
                    'INSERT INTO consultations
                       (appointment_id, patient_id, doctor_id,
                        subjective, objective, assessment, plan,
                        prescription, next_visit_note, next_visit_days,
                        bp_sys, bp_dia, temperature, spo2, weight, height)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute(array_merge(
                    [$apptId, $appt['patient_id'], $appt['doctor_id']],
                    $vals
                ));
                // 予約ステータスを completed に
                $pdo->prepare('UPDATE appointments SET status="completed" WHERE id=?')
                    ->execute([$apptId]);
            }
            header('Location: appointment_view.php?id=' . $apptId . '&msg=updated');
            exit;
        }
    }
}

pageHead('診察記録');
navbar();
?>
<div class="container">
  <h5 class="mb-3">
    <i class="bi bi-clipboard2-pulse"></i> 診察記録 (SOAP)
    <small class="text-muted ms-2">
      <?= h($appt['patient_name']) ?> — <?= h(formatDate($appt['appt_date'])) ?>
      担当：<?= h($appt['doctor_name']) ?>
    </small>
  </h5>

  <?php if ($appt['allergies']): ?>
    <div class="alert alert-danger py-2">
      <i class="bi bi-exclamation-triangle"></i>
      <strong>アレルギー：</strong><?= h($appt['allergies']) ?>
    </div>
  <?php endif; ?>

  <?php if ($qData): ?>
  <div class="card border-warning mb-3">
    <div class="card-header bg-warning text-dark">
      <i class="bi bi-file-earmark-medical"></i> 問診票サマリ
    </div>
    <div class="card-body py-2">
      <strong>主訴：</strong><?= h($qData['chief_complaint']) ?>
      / <strong>症状から：</strong><?= h($qData['symptom_since']) ?>
      / <strong>痛み：</strong><?= h($qData['pain_scale']) ?>/10
      <?php if ($qData['current_meds']): ?>
        / <strong>服薬：</strong><?= h($qData['current_meds']) ?>
      <?php endif; ?>
      <?php if ($qData['pregnancy']): ?>
        <span class="badge bg-danger ms-2">妊娠中</span>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="appointment_id" value="<?= $apptId ?>">

        <h6 class="fw-bold text-secondary mb-2">バイタル</h6>
        <div class="row g-2 mb-3">
          <div class="col-md-2">
            <label class="form-label">血圧（収）</label>
            <div class="input-group input-group-sm">
              <input type="number" name="bp_sys" class="form-control"
                value="<?= h($data['bp_sys'] ?? '') ?>" min="50" max="300">
              <span class="input-group-text">mmHg</span>
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label">血圧（拡）</label>
            <div class="input-group input-group-sm">
              <input type="number" name="bp_dia" class="form-control"
                value="<?= h($data['bp_dia'] ?? '') ?>" min="30" max="200">
              <span class="input-group-text">mmHg</span>
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label">体温</label>
            <div class="input-group input-group-sm">
              <input type="number" name="temperature" class="form-control"
                value="<?= h($data['temperature'] ?? '') ?>" step="0.1" min="30" max="45">
              <span class="input-group-text">℃</span>
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label">SpO2</label>
            <div class="input-group input-group-sm">
              <input type="number" name="spo2" class="form-control"
                value="<?= h($data['spo2'] ?? '') ?>" min="50" max="100">
              <span class="input-group-text">%</span>
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label">体重</label>
            <div class="input-group input-group-sm">
              <input type="number" name="weight" class="form-control"
                value="<?= h($data['weight'] ?? '') ?>" step="0.1">
              <span class="input-group-text">kg</span>
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label">身長</label>
            <div class="input-group input-group-sm">
              <input type="number" name="height" class="form-control"
                value="<?= h($data['height'] ?? '') ?>" step="0.1">
              <span class="input-group-text">cm</span>
            </div>
          </div>
        </div>

        <h6 class="fw-bold text-secondary mb-2">SOAP記録</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label"><span class="badge bg-secondary">S</span> 主訴・自覚症状</label>
            <textarea name="subjective" class="form-control" rows="3"><?= h($data['subjective'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label"><span class="badge bg-secondary">O</span> 客観的所見</label>
            <textarea name="objective" class="form-control" rows="3"><?= h($data['objective'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">
              <span class="badge bg-success">A</span> 診断
              <span class="text-danger">*</span>
            </label>
            <textarea name="assessment" class="form-control" rows="2" required><?= h($data['assessment'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label"><span class="badge bg-primary">P</span> 治療計画・方針</label>
            <textarea name="plan" class="form-control" rows="2"><?= h($data['plan'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label"><i class="bi bi-capsule"></i> 処方</label>
            <textarea name="prescription" class="form-control" rows="2"
              placeholder="例：アモキシシリン250mg 5日分"><?= h($data['prescription'] ?? '') ?></textarea>
          </div>
        </div>

        <hr>
        <h6 class="fw-bold text-secondary mb-2">次回受診提案</h6>
        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label">推奨日数</label>
            <div class="input-group input-group-sm">
              <input type="number" name="next_visit_days" class="form-control"
                value="<?= h($data['next_visit_days'] ?? '') ?>" min="1" max="365"
                placeholder="例：14">
              <span class="input-group-text">日後</span>
            </div>
          </div>
          <div class="col-md-9">
            <label class="form-label">次回受診メモ</label>
            <input type="text" name="next_visit_note" class="form-control form-control-sm"
              value="<?= h($data['next_visit_note'] ?? '') ?>"
              placeholder="例：1ヶ月後に血圧再評価">
          </div>
        </div>

        <div class="mt-4">
          <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle"></i> 診察記録を保存
          </button>
          <a href="appointment_view.php?id=<?= $apptId ?>" class="btn btn-outline-secondary ms-2">
            キャンセル
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php pageFooter(); ?>
