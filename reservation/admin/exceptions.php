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
        $action = $_POST['action'] ?? 'add';
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = db()->prepare(
                'DELETE FROM lc_appointment_slots WHERE id = ? AND tenant_id = ?'
            );
            $stmt->execute([$id, $tenant_id]);
            $msg = '削除しました。';
        } else {
            $date  = $_POST['exception_date'] ?? '';
            $type  = $_POST['type'] ?? 'closed';
            $openT = $_POST['open_time']  ?: null;
            $closeT= $_POST['close_time'] ?: null;
            $memo  = trim($_POST['memo'] ?? '');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $err = '日付の形式が不正です。';
            } elseif (!in_array($type, ['closed','open'], true)) {
                $err = '種別が不正です。';
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO lc_appointment_slots
                       (tenant_id, exception_date, type, open_time, close_time, memo)
                     VALUES (?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                       type=VALUES(type),
                       open_time=VALUES(open_time),
                       close_time=VALUES(close_time),
                       memo=VALUES(memo)'
                );
                $stmt->execute([$tenant_id, $date, $type, $openT, $closeT, $memo]);
                $msg = '登録しました。';
            }
        }
    }
}

// 一覧（今日以降を優先表示）
$stmt = db()->prepare(
    'SELECT * FROM lc_appointment_slots
      WHERE tenant_id = ?
      ORDER BY exception_date DESC LIMIT 200'
);
$stmt->execute([$tenant_id]);
$rows = $stmt->fetchAll();

$csrf = csrf_token();
$page_title = '例外日';
include __DIR__ . '/_header.php';
?>
<h2>例外日（休業 / 臨時営業）</h2>
<?php if ($msg): ?><p class="ok"><?= h($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>

<form method="post" class="form-vert">
<input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
<input type="hidden" name="action" value="add">

<div class="row2">
    <label>日付
        <input type="date" name="exception_date" required>
    </label>
    <label>種別
        <select name="type" id="exc-type">
            <option value="closed">臨時休業</option>
            <option value="open">臨時営業</option>
        </select>
    </label>
</div>

<div class="row2" id="exc-times" style="display:none;">
    <label>開始時刻
        <input type="time" name="open_time">
    </label>
    <label>終了時刻
        <input type="time" name="close_time">
    </label>
</div>

<label>メモ
    <input type="text" name="memo" placeholder="例: お盆休み">
</label>

<button type="submit" class="btn-primary">登録 / 更新</button>
</form>

<h3>登録済み一覧</h3>
<table class="grid">
<thead>
<tr><th>日付</th><th>種別</th><th>時間</th><th>メモ</th><th></th></tr>
</thead>
<tbody>
<?php if (!$rows): ?>
<tr><td colspan="5" class="muted">登録なし</td></tr>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
<tr>
    <td><?= h($r['exception_date']) ?> (<?= h(dow_label((int)date('w', strtotime($r['exception_date'])))) ?>)</td>
    <td><?= $r['type'] === 'closed' ? '休業' : '臨時営業' ?></td>
    <td>
        <?php if ($r['type'] === 'open'): ?>
            <?= h(substr($r['open_time'] ?? '',0,5)) ?>〜<?= h(substr($r['close_time'] ?? '',0,5)) ?>
        <?php else: ?>
            <span class="muted">—</span>
        <?php endif; ?>
    </td>
    <td><?= h($r['memo']) ?></td>
    <td>
        <form method="post" class="inline" onsubmit="return confirm('削除しますか?');">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn-mini btn-danger" type="submit">削除</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<script>
(function () {
    var sel = document.getElementById('exc-type');
    var times = document.getElementById('exc-times');
    function toggle() { times.style.display = sel.value === 'open' ? '' : 'none'; }
    sel.addEventListener('change', toggle);
    toggle();
})();
</script>
<?php include __DIR__ . '/_footer.php'; ?>
