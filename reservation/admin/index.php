<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$me = require_admin();
$tenant_id = admin_tenant_id();

// フィルタ
$from   = $_GET['from']   ?? date('Y-m-d');
$to     = $_GET['to']     ?? date('Y-m-d', strtotime('+30 days'));
$status = $_GET['status'] ?? '';
$kw     = trim($_GET['kw'] ?? '');

// ステータス変更 (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $id = (int)($_POST['id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $allowed = ['pending','confirmed','canceled','noshow'];
    if ($id > 0 && in_array($newStatus, $allowed, true)) {
        $stmt = db()->prepare(
            'UPDATE lc_appointments SET status = ?, handled_by = ?
              WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([$newStatus, $me['id'], $id, $tenant_id]);
    }
    $qs = http_build_query(compact('from','to','status','kw'));
    redirect('index.php?' . $qs);
}

// 一覧取得
$sql = 'SELECT * FROM lc_appointments
         WHERE tenant_id = ?
           AND reservation_date BETWEEN ? AND ?';
$params = [$tenant_id, $from, $to];
if ($status !== '') {
    $sql .= ' AND status = ?';
    $params[] = $status;
}
if ($kw !== '') {
    $sql .= ' AND (customer_name LIKE ? OR customer_kana LIKE ? OR customer_tel LIKE ? OR customer_email LIKE ?)';
    $like = '%' . $kw . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= ' ORDER BY reservation_date, start_time';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$csrf = csrf_token();
$page_title = '予約一覧';
include __DIR__ . '/_header.php';
?>
<h2>予約一覧</h2>

<p><a href="reservation_new.php" class="btn-primary">＋ 新規予約を登録</a></p>

<form method="get" class="filter">
    <label>期間
        <input type="date" name="from" value="<?= h($from) ?>"> 〜
        <input type="date" name="to"   value="<?= h($to) ?>">
    </label>
    <label>ステータス
        <select name="status">
            <option value="">すべて</option>
            <?php foreach (['pending','confirmed','canceled','noshow'] as $s): ?>
                <option value="<?= h($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                    <?= h(status_label($s)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>検索
        <input type="text" name="kw" value="<?= h($kw) ?>" placeholder="氏名/電話/メール">
    </label>
    <button type="submit" class="btn-primary">絞り込み</button>
</form>

<p class="muted"><?= count($rows) ?> 件</p>

<table class="grid">
<thead>
<tr>
    <th>日時</th><th>氏名</th><th>連絡先</th><th>相談類型</th>
    <th>ステータス</th><th>操作</th>
</tr>
</thead>
<tbody>
<?php if (!$rows): ?>
<tr><td colspan="6" class="muted">該当する予約はありません。</td></tr>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
<tr>
    <td>
        <?= h($r['reservation_date']) ?> (<?= h(dow_label((int)date('w', strtotime($r['reservation_date'])))) ?>)<br>
        <?= h(substr($r['start_time'],0,5)) ?>〜<?= h(substr($r['end_time'],0,5)) ?>
    </td>
    <td>
        <a href="reservation_edit.php?id=<?= (int)$r['id'] ?>">
            <?= h($r['customer_name']) ?>
        </a>
        <?php if ($r['customer_kana']): ?>
            <br><small class="muted"><?= h($r['customer_kana']) ?></small>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($r['customer_tel']): ?><?= h($r['customer_tel']) ?><br><?php endif; ?>
        <?php if ($r['customer_email']): ?><small><?= h($r['customer_email']) ?></small><?php endif; ?>
    </td>
    <td><?= h($r['consultation_type']) ?></td>
    <td>
        <span class="status status-<?= h($r['status']) ?>">
            <?= h(status_label($r['status'])) ?>
        </span>
    </td>
    <td class="actions">
        <form method="post" class="inline">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <select name="new_status">
                <?php foreach (['pending','confirmed','canceled','noshow'] as $s): ?>
                    <option value="<?= h($s) ?>" <?= $s === $r['status'] ? 'selected' : '' ?>>
                        <?= h(status_label($s)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn-mini" type="submit">変更</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php include __DIR__ . '/_footer.php'; ?>
