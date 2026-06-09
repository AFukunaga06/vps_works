<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$staff = crm_require_login();

$con_id     = (int)($_GET['id'] ?? 0);
$customer_id= (int)($_GET['customer_id'] ?? 0);
$con        = $con_id ? get_consultation($con_id) : null;
if ($con) $customer_id = (int)$con['customer_id'];

$customer = get_customer($customer_id);
if (!$customer) { header('Location: ' . CRM_BASE_URL . '/index.php'); exit; }

$page_title  = $con ? '相談記録を編集' : '相談記録を追加';
$con_count   = get_consultation_count($customer_id); // 既存の相談件数
$all_staff   = get_all_staff();
$errors      = [];

// デフォルト値
$default_is_first = ($con_count === 0 && !$con) ? '1' : '0';
$old = $con ? array_map(fn($v) => (string)($v ?? ''), $con) : [
    'consulted_at'           => date('Y-m-d\TH:i'),
    'consultation_type'      => '',
    'method'                 => '',
    'is_first'               => $default_is_first,
    'content'                => '',
    'result'                 => '',
    'next_action'            => '',
    'first_background'       => '',
    'first_living_situation' => '',
    'first_urgency'          => '',
    'repeat_progress'        => '',
    'repeat_payment_status'  => '',
    'repeat_materials'       => '',
    'reserve_id'             => '',
    'staff_id'               => (string)$staff['id'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'consulted_at'           => trim($_POST['consulted_at'] ?? ''),
        'consultation_type'      => trim($_POST['consultation_type'] ?? ''),
        'method'                 => trim($_POST['method'] ?? ''),
        'is_first'               => $_POST['is_first'] ?? '0',
        'content'                => trim($_POST['content'] ?? ''),
        'result'                 => trim($_POST['result'] ?? ''),
        'next_action'            => trim($_POST['next_action'] ?? ''),
        'first_background'       => trim($_POST['first_background'] ?? ''),
        'first_living_situation' => trim($_POST['first_living_situation'] ?? ''),
        'first_urgency'          => trim($_POST['first_urgency'] ?? ''),
        'repeat_progress'        => trim($_POST['repeat_progress'] ?? ''),
        'repeat_payment_status'  => trim($_POST['repeat_payment_status'] ?? ''),
        'repeat_materials'       => trim($_POST['repeat_materials'] ?? ''),
        'reserve_id'             => trim($_POST['reserve_id'] ?? ''),
        'staff_id'               => trim($_POST['staff_id'] ?? ''),
        'customer_id'            => $customer_id,
    ];

    if (!$old['consulted_at'])                                    $errors['consulted_at'] = '相談日時は必須です。';
    if (!in_array($old['consultation_type'], CONSULT_TYPES, true)) $errors['consultation_type'] = '相談種別を選択してください。';
    if (!in_array($old['method'], CONSULT_METHODS, true))         $errors['method'] = '相談方法を選択してください。';
    if (!$old['content'])                                         $errors['content'] = '相談内容は必須です。';
    if (!$old['staff_id'])                                        $errors['staff_id'] = '担当者を選択してください。';

    if (empty($errors)) {
        save_consultation($old, $con_id ?: null);
        header('Location: ' . CRM_BASE_URL . '/customer.php?id=' . $customer_id);
        exit;
    }
}

include __DIR__ . '/includes/_header.php';
?>

<div class="mb-3 text-muted small">
  <a href="<?= CRM_BASE_URL ?>/customer.php?id=<?= $customer_id ?>">← <?= h($customer['name']) ?> 様の詳細に戻る</a>
</div>

<div style="max-width:750px">
  <?php if ($errors): ?>
  <div class="alert alert-danger">入力内容を確認してください。</div>
  <?php endif; ?>

  <form method="post">
    <!-- 基本情報 -->
    <div class="card shadow-sm mb-3">
      <div class="card-header"><strong>基本情報</strong></div>
      <div class="card-body row g-3">
        <div class="col-md-5">
          <label class="form-label fw-bold">相談日時 <span class="text-danger">*</span></label>
          <input type="datetime-local" name="consulted_at" class="form-control <?= isset($errors['consulted_at']) ? 'is-invalid' : '' ?>"
                 value="<?= h(str_replace(' ', 'T', $old['consulted_at'])) ?>">
          <?php if (isset($errors['consulted_at'])): ?><div class="invalid-feedback"><?= h($errors['consulted_at']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-bold">担当者 <span class="text-danger">*</span></label>
          <select name="staff_id" class="form-select <?= isset($errors['staff_id']) ? 'is-invalid' : '' ?>">
            <option value="">選択してください</option>
            <?php foreach ($all_staff as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $old['staff_id'] == $s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">相談種別 <span class="text-danger">*</span></label>
          <select name="consultation_type" class="form-select <?= isset($errors['consultation_type']) ? 'is-invalid' : '' ?>">
            <option value="">選択してください</option>
            <?php foreach (CONSULT_TYPES as $t): ?>
            <option value="<?= h($t) ?>" <?= $old['consultation_type'] === $t ? 'selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">相談方法 <span class="text-danger">*</span></label>
          <select name="method" class="form-select <?= isset($errors['method']) ? 'is-invalid' : '' ?>">
            <option value="">選択してください</option>
            <?php foreach (CONSULT_METHODS as $m): ?>
            <option value="<?= h($m) ?>" <?= $old['method'] === $m ? 'selected' : '' ?>><?= h($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- 初回 or 継続 -->
    <div class="card shadow-sm mb-3">
      <div class="card-header"><strong>相談区分</strong></div>
      <div class="card-body">
        <div class="d-flex gap-4 mb-1">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="is_first" id="isf1" value="1"
                   <?= $old['is_first'] === '1' ? 'checked' : '' ?> onchange="toggleFields()">
            <label class="form-check-label fw-bold text-primary" for="isf1">初回</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="is_first" id="isf0" value="0"
                   <?= $old['is_first'] !== '1' ? 'checked' : '' ?> onchange="toggleFields()">
            <label class="form-check-label fw-bold text-purple" for="isf0" style="color:#4a148c">2回目以降</label>
          </div>
        </div>
        <div class="text-muted small">現在の相談履歴：<?= $con_count ?>件</div>

        <!-- 初回専用フィールド -->
        <div id="first_fields" class="mt-3 p-3 rounded" style="background:#e8eef8">
          <div class="fw-bold text-primary mb-2">初回のみ入力</div>
          <div class="mb-3">
            <label class="form-label">相談に至った経緯・背景</label>
            <textarea name="first_background" class="form-control" rows="3"><?= h($old['first_background']) ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">現在の生活状況</label>
            <textarea name="first_living_situation" class="form-control" rows="2"><?= h($old['first_living_situation']) ?></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label">緊急度</label>
            <div class="d-flex gap-3">
              <?php foreach (URGENCY_MAP as $v => $l): ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="first_urgency"
                       id="urg_<?= $v ?>" value="<?= $v ?>" <?= $old['first_urgency'] === $v ? 'checked' : '' ?>>
                <label class="form-check-label" for="urg_<?= $v ?>"><?= h($l) ?></label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- 2回目以降専用フィールド -->
        <div id="repeat_fields" class="mt-3 p-3 rounded" style="background:#f3e8f8">
          <div class="fw-bold mb-2" style="color:#4a148c">2回目以降のみ入力</div>
          <div class="mb-3">
            <label class="form-label">前回からの変化・進捗</label>
            <textarea name="repeat_progress" class="form-control" rows="3"><?= h($old['repeat_progress']) ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">入金状況</label>
            <input type="text" name="repeat_payment_status" class="form-control"
                   value="<?= h($old['repeat_payment_status']) ?>" placeholder="例：入金済み、未入金、免除">
          </div>
          <div class="mb-0">
            <label class="form-label">提出書類・資料等</label>
            <textarea name="repeat_materials" class="form-control" rows="2"><?= h($old['repeat_materials']) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- 共通フィールド -->
    <div class="card shadow-sm mb-3">
      <div class="card-header"><strong>相談内容・対応</strong></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label fw-bold">相談内容 <span class="text-danger">*</span></label>
          <textarea name="content" class="form-control <?= isset($errors['content']) ? 'is-invalid' : '' ?>"
                    rows="4" placeholder="今回の相談内容を記録してください"><?= h($old['content']) ?></textarea>
          <?php if (isset($errors['content'])): ?><div class="invalid-feedback"><?= h($errors['content']) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">対応結果・所見</label>
          <textarea name="result" class="form-control" rows="3"
                    placeholder="担当者の所見・アドバイスの内容"><?= h($old['result']) ?></textarea>
        </div>
        <div class="mb-0">
          <label class="form-label fw-bold">次回対応予定</label>
          <textarea name="next_action" class="form-control" rows="2"
                    placeholder="次回までにやること・確認事項"><?= h($old['next_action']) ?></textarea>
        </div>
      </div>
    </div>

    <input type="hidden" name="reserve_id" value="<?= h($old['reserve_id']) ?>">

    <div class="d-flex gap-2">
      <button type="submit" class="btn text-white" style="background:#3a7d5c">
        <?= $con ? '更新する' : '記録する' ?>
      </button>
      <a href="<?= CRM_BASE_URL ?>/customer.php?id=<?= $customer_id ?>" class="btn btn-outline-secondary">キャンセル</a>
    </div>
  </form>
</div>

<script>
function toggleFields() {
    const isFirst = document.querySelector('input[name="is_first"]:checked')?.value === '1';
    document.getElementById('first_fields').style.display  = isFirst ? '' : 'none';
    document.getElementById('repeat_fields').style.display = isFirst ? 'none' : '';
}
// 初期表示
toggleFields();
</script>

<?php include __DIR__ . '/includes/_footer.php'; ?>
