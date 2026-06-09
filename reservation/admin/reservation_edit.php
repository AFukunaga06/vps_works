<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$me = require_admin();
$tenant_id = admin_tenant_id();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('index.php');
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'セッションが無効です。';
    } else {
        $status            = $_POST['status'] ?? 'pending';
        $customer_name     = trim($_POST['customer_name'] ?? '');
        $customer_kana     = trim($_POST['customer_kana'] ?? '');
        $customer_tel      = trim($_POST['customer_tel'] ?? '');
        $customer_email    = trim($_POST['customer_email'] ?? '');
        $consultation_type = trim($_POST['consultation_type'] ?? '');
        $memo              = $_POST['memo'] ?? '';
        $admin_memo        = $_POST['admin_memo'] ?? '';

        if (!in_array($status, ['pending','confirmed','canceled','noshow'], true)) {
            $err = 'ステータスが不正です。';
        } elseif ($customer_name === '') {
            $err = '氏名は必須です。';
        } else {
            $stmt = db()->prepare(
                'UPDATE lc_appointments SET
                    status = ?, customer_name = ?, customer_kana = ?,
                    customer_tel = ?, customer_email = ?, consultation_type = ?,
                    memo = ?, admin_memo = ?, handled_by = ?
                  WHERE id = ? AND tenant_id = ?'
            );
            $stmt->execute([
                $status, $customer_name, $customer_kana,
                $customer_tel, $customer_email, $consultation_type,
                $memo, $admin_memo, $me['id'], $id, $tenant_id,
            ]);
            $msg = '保存しました。';
        }
    }
}

$stmt = db()->prepare('SELECT * FROM lc_appointments WHERE id = ? AND tenant_id = ?');
$stmt->execute([$id, $tenant_id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    exit('該当する予約が見つかりません。');
}

$settings = get_settings($tenant_id);
$types = array_filter(array_map('trim', explode(',', $settings['consultation_types'])), 'strlen');

$csrf = csrf_token();
$page_title = '予約編集 #' . $row['id'];
include __DIR__ . '/_header.php';
?>
<p><a href="index.php">← 予約一覧へ</a></p>
<h2>予約 #<?= (int)$row['id'] ?></h2>
<?php if ($msg): ?><p class="ok"><?= h($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>

<form method="post" class="form-vert">
<input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

<p><strong>日時:</strong>
    <?= h($row['reservation_date']) ?>
    (<?= h(dow_label((int)date('w', strtotime($row['reservation_date'])))) ?>)
    <?= h(substr($row['start_time'],0,5)) ?>〜<?= h(substr($row['end_time'],0,5)) ?>
</p>

<label>ステータス
    <select name="status">
        <?php foreach (['pending','confirmed','canceled','noshow'] as $s): ?>
            <option value="<?= h($s) ?>" <?= $s === $row['status'] ? 'selected' : '' ?>>
                <?= h(status_label($s)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<div class="row2">
    <label>氏名
        <input type="text" name="customer_name" required value="<?= h($row['customer_name']) ?>">
    </label>
    <label>ふりがな
        <input type="text" name="customer_kana" value="<?= h($row['customer_kana']) ?>">
    </label>
</div>
<div class="row2">
    <label>電話
        <input type="text" name="customer_tel" value="<?= h($row['customer_tel']) ?>">
    </label>
    <label>メール
        <input type="email" name="customer_email" value="<?= h($row['customer_email']) ?>">
    </label>
</div>

<label>相談類型
    <select name="consultation_type">
        <option value="">—</option>
        <?php foreach ($types as $t): ?>
            <option value="<?= h($t) ?>" <?= $t === $row['consultation_type'] ? 'selected' : '' ?>>
                <?= h($t) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>お客様からのメモ（公開ページで入力）
    <textarea name="memo" rows="3"><?= h($row['memo']) ?></textarea>
</label>

<label>管理者メモ（顧客には見えません）
    <textarea name="admin_memo" rows="3"><?= h($row['admin_memo']) ?></textarea>
</label>

<button type="submit" class="btn-primary">保存</button>
</form>

<p class="muted">
    登録日時: <?= h($row['created_at']) ?> /
    更新日時: <?= h($row['updated_at']) ?> /
    IP: <?= h($row['ip']) ?>
</p>
<?php include __DIR__ . '/_footer.php'; ?>
