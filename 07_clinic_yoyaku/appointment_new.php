<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
requireLogin();

$pdo     = getPDO();
$errors  = [];
$doctors = $pdo->query('SELECT * FROM doctors ORDER BY id')->fetchAll();

$data = [
    'patient_name' => '',
    'patient_kana' => '',
    'doctor_id'    => '',
    'appt_date'    => date('Y-m-d'),
    'start_time'   => '',
    'end_time'     => '',
    'reason'       => '',
    'note'         => '',
];

$slots = [];
$selDate   = $_GET['sel_date']   ?? $data['appt_date'];
$selDoctor = (int)($_GET['sel_doctor'] ?? 0);
if ($selDate && $selDoctor > 0) {
    $dow = (int)date('w', strtotime($selDate));
    $tmplStmt = $pdo->prepare(
        'SELECT * FROM slot_templates
         WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1
         ORDER BY start_time'
    );
    $tmplStmt->execute([$selDoctor, $dow]);
    foreach ($tmplStmt->fetchAll() as $tmpl) {
        $taken = $pdo->prepare(
            'SELECT COUNT(*) FROM appointments
             WHERE doctor_id=? AND appt_date=? AND start_time=?
               AND status NOT IN ("cancelled","no_show")'
        );
        $taken->execute([$selDoctor, $selDate, $tmpl['start_time']]);
        if ((int)$taken->fetchColumn() < $tmpl['capacity']) {
            $slots[] = $tmpl;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'セッションエラーが発生しました。';
    } else {
        $data['patient_name'] = trim($_POST['patient_name'] ?? '');
        $data['patient_kana'] = trim($_POST['patient_kana'] ?? '');
        $data['doctor_id']    = (int)($_POST['doctor_id']   ?? 0);
        $data['appt_date']    = trim($_POST['appt_date']    ?? '');
        $data['start_time']   = trim($_POST['start_time']   ?? '');
        $data['end_time']     = trim($_POST['end_time']     ?? '');
        $data['reason']       = trim($_POST['reason']       ?? '');
        $data['note']         = trim($_POST['note']         ?? '');

        if ($data['patient_name'] === '') $errors[] = '姓名を入力してください。';
        if (!$data['doctor_id'])          $errors[] = '担当医を選択してください。';
        if (!$data['appt_date'])          $errors[] = '予約日を入力してください。';
        if (!$data['start_time'])         $errors[] = '開始時間を選択してください。';
        if (!$data['end_time'])           $errors[] = '終了時間を入力してください。';

        if (empty($errors)) {
            $dup = $pdo->prepare(
                'SELECT COUNT(*) FROM appointments
                 WHERE doctor_id=? AND appt_date=? AND start_time=?
                   AND status NOT IN ("cancelled","no_show")'
            );
            $dup->execute([$data['doctor_id'], $data['appt_date'], $data['start_time']]);
            if ((int)$dup->fetchColumn() > 0) {
                $errors[] = '選択した時間帯はすでに予約が入っています。';
            }
        }

        if (empty($errors)) {
            // 同名患者を検索、なければ新規作成
            $pt = $pdo->prepare('SELECT id FROM patients WHERE last_name=? LIMIT 1');
            $pt->execute([$data['patient_name']]);
            $ptRow = $pt->fetch();
            if ($ptRow) {
                $patientId = $ptRow['id'];
            } else {
                $maxNo = $pdo->query("SELECT MAX(CAST(SUBSTRING(patient_no,2) AS UNSIGNED)) FROM patients")->fetchColumn();
                $newNo = 'P' . str_pad((int)$maxNo + 1, 4, '0', STR_PAD_LEFT);
                $ins = $pdo->prepare(
                    "INSERT INTO patients (patient_no,last_name,first_name,last_name_kana,first_name_kana)
                     VALUES (?,?,'',?,'') "
                );
                $ins->execute([$newNo, $data['patient_name'], $data['patient_kana']]);
                $patientId = (int)$pdo->lastInsertId();
            }

            $user = currentUser();
            $insA = $pdo->prepare(
                'INSERT INTO appointments
                   (patient_id,doctor_id,appt_date,start_time,end_time,status,reason,note,created_by)
                 VALUES (?,?,?,?,?,"reserved",?,?,?)'
            );
            $insA->execute([
                $patientId, $data['doctor_id'],
                $data['appt_date'], $data['start_time'], $data['end_time'],
                $data['reason'], $data['note'], $user['id'],
            ]);
            header('Location: appointment_view.php?id=' . $pdo->lastInsertId() . '&msg=created');
            exit;
        }
    }
}

pageHead('新規予約');
navbar();
?>
<div class="container">
  <h5 class="mb-3"><i class="bi bi-calendar-plus"></i> 新規予約</h5>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul></div>
  <?php endif; ?>

  <div class="row g-3">
    <!-- 空き枠検索 -->
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><i class="bi bi-search"></i> 空き枠を確認</div>
        <div class="card-body">
          <form method="get">
            <div class="mb-2">
              <label class="form-label">担当医</label>
              <select name="sel_doctor" class="form-select form-select-sm">
                <option value="">選択...</option>
                <?php foreach ($doctors as $doc): ?>
                  <option value="<?= (int)$doc['id'] ?>"
                    <?= $selDoctor === (int)$doc['id'] ? 'selected' : '' ?>>
                    <?= h($doc['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">日付</label>
              <input type="date" name="sel_date" class="form-control form-control-sm"
                value="<?= h($selDate) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-secondary w-100">
              <i class="bi bi-search"></i> 空き枠を表示
            </button>
          </form>

          <?php if ($selDate && $selDoctor > 0): ?>
          <hr>
          <p class="small fw-bold mb-2">
            <?= h(formatDate($selDate)) ?> の空き枠
            （<?= h(dayLabel((int)date('w', strtotime($selDate)))) ?>）
          </p>
          <?php if (empty($slots)): ?>
            <p class="text-muted small">空き枠なし</p>
          <?php else: ?>
            <div class="list-group list-group-flush small">
              <?php foreach ($slots as $sl): ?>
              <a href="?sel_date=<?= h($selDate) ?>&sel_doctor=<?= $selDoctor ?>&fill_start=<?= h($sl['start_time']) ?>&fill_end=<?= h($sl['end_time']) ?>"
                 class="list-group-item list-group-item-action py-1">
                <?= h(formatTime($sl['start_time'])) ?> 〜 <?= h(formatTime($sl['end_time'])) ?>
              </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- 予約フォーム -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header"><i class="bi bi-pencil-square"></i> 予約情報入力</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <?php
            $fillStart = $_GET['fill_start'] ?? $data['start_time'];
            $fillEnd   = $_GET['fill_end']   ?? $data['end_time'];
            $fillDate  = $selDate ?: $data['appt_date'];
            $fillDoc   = $selDoctor ?: $data['doctor_id'];
            ?>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">姓名 <span class="text-danger">*</span></label>
                <input type="text" name="patient_name" class="form-control"
                  value="<?= h($data['patient_name']) ?>"
                  placeholder="例：山田 太郎" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">セイメイ</label>
                <input type="text" name="patient_kana" class="form-control"
                  value="<?= h($data['patient_kana']) ?>"
                  placeholder="例：ヤマダ タロウ">
              </div>
              <div class="col-md-6">
                <label class="form-label">担当医 <span class="text-danger">*</span></label>
                <select name="doctor_id" class="form-select" required>
                  <option value="">選択...</option>
                  <?php foreach ($doctors as $doc): ?>
                    <option value="<?= (int)$doc['id'] ?>"
                      <?= (int)$fillDoc === (int)$doc['id'] ? 'selected' : '' ?>>
                      <?= h($doc['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">予約日 <span class="text-danger">*</span></label>
                <input type="date" name="appt_date" class="form-control"
                  value="<?= h($fillDate) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">開始時間 <span class="text-danger">*</span></label>
                <input type="time" name="start_time" class="form-control"
                  value="<?= h($fillStart) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">終了時間</label>
                <input type="time" name="end_time" class="form-control"
                  value="<?= h($fillEnd) ?>">
              </div>
              <div class="col-12">
                <label class="form-label">来院理由</label>
                <input type="text" name="reason" class="form-control"
                  value="<?= h($data['reason']) ?>"
                  placeholder="例：頭痛・発熱、定期検診">
              </div>
              <div class="col-12">
                <label class="form-label">メモ</label>
                <textarea name="note" class="form-control" rows="2"><?= h($data['note']) ?></textarea>
              </div>
            </div>
            <div class="mt-3">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle"></i> 予約を登録する
              </button>
              <a href="appointments.php" class="btn btn-outline-secondary ms-2">キャンセル</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php pageFooter(); ?>
