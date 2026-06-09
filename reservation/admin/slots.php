<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$me = require_admin();
$tenant_id = admin_tenant_id();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'セッションが無効です。';
    } else {
        $business_name         = trim($_POST['business_name'] ?? '');
        $slot_minutes          = (int)($_POST['slot_minutes'] ?? 30);
        $open_time             = $_POST['open_time'] ?? '10:00';
        $close_time            = $_POST['close_time'] ?? '18:00';
        $open_days_arr         = $_POST['open_days'] ?? [];
        $open_days             = implode(',', array_map('intval', $open_days_arr));
        $buffer_minutes        = (int)($_POST['buffer_minutes'] ?? 0);
        $advance_days          = (int)($_POST['advance_days'] ?? 30);
        $cancel_deadline_hours = (int)($_POST['cancel_deadline_hours'] ?? 24);
        $contact_email         = trim($_POST['contact_email'] ?? '');
        $contact_tel           = trim($_POST['contact_tel'] ?? '');
        $consultation_types    = trim($_POST['consultation_types'] ?? '');
        $notice_message        = $_POST['notice_message'] ?? '';

        if ($business_name === '') $err = '事務所名は必須です。';
        if (!in_array($slot_minutes, [15, 30, 45, 60], true)) $err = 'スロット長が不正です。';

        if (!$err) {
            $stmt = db()->prepare(
                'INSERT INTO lc_settings_reserve
                  (tenant_id, business_name, slot_minutes, open_time, close_time, open_days,
                   buffer_minutes, advance_days, cancel_deadline_hours,
                   contact_email, contact_tel, consultation_types, notice_message)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   business_name=VALUES(business_name),
                   slot_minutes=VALUES(slot_minutes),
                   open_time=VALUES(open_time),
                   close_time=VALUES(close_time),
                   open_days=VALUES(open_days),
                   buffer_minutes=VALUES(buffer_minutes),
                   advance_days=VALUES(advance_days),
                   cancel_deadline_hours=VALUES(cancel_deadline_hours),
                   contact_email=VALUES(contact_email),
                   contact_tel=VALUES(contact_tel),
                   consultation_types=VALUES(consultation_types),
                   notice_message=VALUES(notice_message)'
            );
            $stmt->execute([
                $tenant_id, $business_name, $slot_minutes, $open_time, $close_time, $open_days,
                $buffer_minutes, $advance_days, $cancel_deadline_hours,
                $contact_email, $contact_tel, $consultation_types, $notice_message,
            ]);
            $msg = '保存しました。';
        }
    }
}

$s = get_settings($tenant_id);
$opens = array_map('intval', array_filter(explode(',', $s['open_days']), 'strlen'));

$csrf = csrf_token();
$page_title = '営業設定';
include __DIR__ . '/_header.php';
?>
<h2>営業設定</h2>
<?php if ($msg): ?><p class="ok"><?= h($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>

<form method="post" class="form-vert">
<input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

<label>事務所名（公開ページに表示）
    <input type="text" name="business_name" required value="<?= h($s['business_name']) ?>">
</label>

<label>スロット長（1枠の長さ）
    <select name="slot_minutes">
        <?php foreach ([15,30,45,60] as $m): ?>
            <option value="<?= $m ?>" <?= (int)$s['slot_minutes'] === $m ? 'selected' : '' ?>>
                <?= $m ?>分
            </option>
        <?php endforeach; ?>
    </select>
</label>

<div class="row2">
    <label>営業開始時刻
        <input type="time" name="open_time" value="<?= h(substr($s['open_time'],0,5)) ?>" required>
    </label>
    <label>営業終了時刻
        <input type="time" name="close_time" value="<?= h(substr($s['close_time'],0,5)) ?>" required>
    </label>
</div>

<fieldset>
    <legend>営業曜日</legend>
    <?php for ($d = 0; $d <= 6; $d++): ?>
        <label class="inline">
            <input type="checkbox" name="open_days[]" value="<?= $d ?>"
                <?= in_array($d, $opens, true) ? 'checked' : '' ?>>
            <?= h(dow_label($d)) ?>
        </label>
    <?php endfor; ?>
</fieldset>

<div class="row2">
    <label>枠間バッファ（分）
        <input type="number" name="buffer_minutes" min="0" max="60"
               value="<?= (int)$s['buffer_minutes'] ?>">
    </label>
    <label>何日先まで予約可
        <input type="number" name="advance_days" min="1" max="365"
               value="<?= (int)$s['advance_days'] ?>">
    </label>
</div>

<label>キャンセル期限（時間前まで）
    <input type="number" name="cancel_deadline_hours" min="0" max="240"
           value="<?= (int)$s['cancel_deadline_hours'] ?>">
</label>

<div class="row2">
    <label>事務所連絡先（メール）
        <input type="email" name="contact_email" value="<?= h($s['contact_email']) ?>">
    </label>
    <label>事務所連絡先（電話）
        <input type="text" name="contact_tel" value="<?= h($s['contact_tel']) ?>">
    </label>
</div>

<label>相談類型（カンマ区切り）
    <input type="text" name="consultation_types" value="<?= h($s['consultation_types']) ?>">
</label>

<label>公開ページ案内文
    <textarea name="notice_message" rows="4"><?= h($s['notice_message']) ?></textarea>
</label>

<button type="submit" class="btn-primary">保存</button>
</form>

<?php include __DIR__ . '/_footer.php'; ?>
