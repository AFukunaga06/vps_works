<?php
require_once __DIR__ . '/../includes/functions.php';

$tenant_id = resolve_public_tenant_id();
$settings  = get_settings($tenant_id);

$id    = (int)($_GET['id'] ?? 0);
$error = $_GET['error'] ?? '';

$row = null;
if ($id > 0) {
    $stmt = db()->prepare(
        'SELECT * FROM lc_appointments WHERE id = ? AND tenant_id = ?'
    );
    $stmt->execute([$id, $tenant_id]);
    $row = $stmt->fetch();
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>予約完了｜<?= h($settings['business_name']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="public-body">
<header class="public-header">
    <h1><?= h($settings['business_name']) ?></h1>
    <p class="subtitle">ご予約のお手続き</p>
</header>
<main class="public-main">

<?php if ($error === 'conflict'): ?>
    <div class="err-box">
        <h2>申し訳ございません</h2>
        <p>ご希望の枠は既に他の方に予約されました。お手数ですが別の日時をお選びください。</p>
        <p><a href="index.php?t=<?= (int)$tenant_id ?>" class="btn-primary">カレンダーへ戻る</a></p>
    </div>

<?php elseif ($error === 'spam'): ?>
    <div class="err-box">
        <h2>送信を受け付けられませんでした</h2>
        <p>もう一度フォームから入力してください。</p>
    </div>

<?php elseif ($row): ?>
    <div class="ok-box">
        <h2>ご予約を受け付けました（仮予約）</h2>
        <p>下記の内容で<strong>仮予約</strong>を承りました。担当者より確定のご連絡を差し上げます。</p>

        <table class="summary">
            <tr><th>予約ID</th><td>#<?= (int)$row['id'] ?></td></tr>
            <tr>
                <th>日時</th>
                <td>
                    <?= h($row['reservation_date']) ?>
                    (<?= h(dow_label((int)date('w', strtotime($row['reservation_date'])))) ?>)
                    <?= h(substr($row['start_time'],0,5)) ?>〜<?= h(substr($row['end_time'],0,5)) ?>
                </td>
            </tr>
            <tr><th>氏名</th><td><?= h($row['customer_name']) ?></td></tr>
            <?php if ($row['consultation_type']): ?>
                <tr><th>相談内容</th><td><?= h($row['consultation_type']) ?></td></tr>
            <?php endif; ?>
        </table>

        <p class="muted">
            ご質問は
            <?php if ($settings['contact_tel']):  ?>TEL <?= h($settings['contact_tel']) ?> <?php endif; ?>
            <?php if ($settings['contact_email']): ?>/ <?= h($settings['contact_email']) ?><?php endif; ?>
            までお願いいたします。
        </p>

        <p><a href="index.php?t=<?= (int)$tenant_id ?>">カレンダーへ戻る</a></p>
    </div>

<?php else: ?>
    <p class="muted">予約情報が見つかりません。</p>
    <p><a href="index.php?t=<?= (int)$tenant_id ?>">カレンダーへ戻る</a></p>
<?php endif; ?>

</main>
<footer class="public-footer">
    <small><?= h($settings['business_name']) ?></small>
</footer>
</body>
</html>
