<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();

$pdo   = getPDO();
$apptId = (int)($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? 0);

$apptStmt = $pdo->prepare(
    'SELECT a.*, CONCAT(p.last_name," ",p.first_name) AS patient_name, p.id AS patient_id
     FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.id = ?'
);
$apptStmt->execute([$apptId]);
$appt = $apptStmt->fetch();
if (!$appt) { header('Location: appointments.php'); exit; }

$existing = $pdo->prepare('SELECT * FROM questionnaires WHERE appointment_id = ?');
$existing->execute([$apptId]);
$q = $existing->fetch();

$errors = [];
$data = $q ?: ['pain_scale' => 5];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'セッションエラーが発生しました。';
    } else {
        $fields = ['chief_complaint','symptom_since','other_symptoms','current_meds',
                   'emergency_contact_name','emergency_contact_phone'];
        foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');
        $data['pain_scale'] = min(10, max(0, (int)($_POST['pain_scale'] ?? 5)));
        foreach (['fever','cough','nausea','diarrhea','fatigue','pregnancy','smoking','alcohol'] as $cb) {
            $data[$cb] = isset($_POST[$cb]) ? 1 : 0;
        }

        if ($data['chief_complaint'] === '') $errors[] = '主訴を入力してください。';

        if (empty($errors)) {
            if ($q) {
                $pdo->prepare(
                    'UPDATE questionnaires SET
                       chief_complaint=?, symptom_since=?, pain_scale=?,
                       fever=?, cough=?, nausea=?, diarrhea=?, fatigue=?,
                       other_symptoms=?, current_meds=?, pregnancy=?, smoking=?, alcohol=?,
                       emergency_contact_name=?, emergency_contact_phone=?
                     WHERE appointment_id=?'
                )->execute([
                    $data['chief_complaint'], $data['symptom_since'], $data['pain_scale'],
                    $data['fever'], $data['cough'], $data['nausea'],
                    $data['diarrhea'], $data['fatigue'],
                    $data['other_symptoms'], $data['current_meds'],
                    $data['pregnancy'], $data['smoking'], $data['alcohol'],
                    $data['emergency_contact_name'], $data['emergency_contact_phone'],
                    $apptId,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO questionnaires
                       (appointment_id, chief_complaint, symptom_since, pain_scale,
                        fever, cough, nausea, diarrhea, fatigue,
                        other_symptoms, current_meds, pregnancy, smoking, alcohol,
                        emergency_contact_name, emergency_contact_phone)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $apptId,
                    $data['chief_complaint'], $data['symptom_since'], $data['pain_scale'],
                    $data['fever'], $data['cough'], $data['nausea'],
                    $data['diarrhea'], $data['fatigue'],
                    $data['other_symptoms'], $data['current_meds'],
                    $data['pregnancy'], $data['smoking'], $data['alcohol'],
                    $data['emergency_contact_name'], $data['emergency_contact_phone'],
                ]);
            }
            header('Location: appointment_view.php?id=' . $apptId . '&msg=updated');
            exit;
        }
    }
}

pageHead('問診票');
navbar();
?>
<div class="container">
  <h5 class="mb-3">
    <i class="bi bi-file-earmark-medical"></i> 問診票
    <small class="text-muted ms-2"><?= h($appt['patient_name']) ?>
      — <?= h(formatDate($appt['appt_date'])) ?>
    </small>
  </h5>

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

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-bold">主訴（今日の一番の症状・お悩み） <span class="text-danger">*</span></label>
            <textarea name="chief_complaint" class="form-control" rows="2" required><?= h($data['chief_complaint'] ?? '') ?></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">いつから</label>
            <input type="text" name="symptom_since" class="form-control"
              value="<?= h($data['symptom_since'] ?? '') ?>"
              placeholder="例：3日前から、昨日の夜から">
          </div>
          <div class="col-md-8">
            <label class="form-label">
              痛みの強さ：<strong id="pain_scale_val"><?= (int)($data['pain_scale'] ?? 5) ?></strong> / 10
            </label>
            <input type="range" id="pain_scale" name="pain_scale" class="form-range"
              min="0" max="10" value="<?= (int)($data['pain_scale'] ?? 5) ?>">
            <div class="d-flex justify-content-between small text-muted">
              <span>0 痛みなし</span><span>5 中程度</span><span>10 最大の痛み</span>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label fw-bold">現在の症状（当てはまるものすべて）</label>
            <div class="row g-2">
              <?php foreach ([
                'fever'=>'発熱','cough'=>'咳','nausea'=>'吐気・嘔吐',
                'diarrhea'=>'下痢','fatigue'=>'倦怠感・だるさ'
              ] as $key => $label): ?>
              <div class="col-auto">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox"
                    name="<?= $key ?>" id="<?= $key ?>"
                    <?= !empty($data[$key]) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="<?= $key ?>"><?= $label ?></label>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">その他の症状</label>
            <input type="text" name="other_symptoms" class="form-control"
              value="<?= h($data['other_symptoms'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">現在服用中の薬</label>
            <input type="text" name="current_meds" class="form-control"
              value="<?= h($data['current_meds'] ?? '') ?>" placeholder="なし の場合は「なし」">
          </div>

          <div class="col-12">
            <label class="form-label fw-bold">生活情報</label>
            <div class="d-flex gap-4">
              <?php foreach (['pregnancy'=>'妊娠中','smoking'=>'喫煙','alcohol'=>'飲酒'] as $k => $l): ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="<?= $k ?>" id="<?= $k ?>"
                  <?= !empty($data[$k]) ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $k ?>"><?= $l ?></label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="col-md-5">
            <label class="form-label">緊急連絡先（氏名）</label>
            <input type="text" name="emergency_contact_name" class="form-control"
              value="<?= h($data['emergency_contact_name'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">緊急連絡先（電話）</label>
            <input type="tel" name="emergency_contact_phone" class="form-control"
              value="<?= h($data['emergency_contact_phone'] ?? '') ?>">
          </div>
        </div>

        <div class="mt-3">
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-check-circle"></i> 問診票を保存
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
