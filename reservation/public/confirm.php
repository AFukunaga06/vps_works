<?php
require_once __DIR__ . '/../includes/functions.php';

session_name(RESV_SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

$tenant_id = resolve_public_tenant_id();
$settings  = get_settings($tenant_id);

$date  = $_REQUEST['date']  ?? '';
$start = $_REQUEST['start'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
 || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start)) {
    redirect('index.php?t=' . (int)$tenant_id);
}
if (strlen($start) === 5) $start .= ':00';

// 枠が今でも空きかチェック
$info = compute_day_slots($settings, $tenant_id, $date);
$slotOk = false;
$slotEnd = '';
foreach ($info['slots'] as $sl) {
    if ($sl['start'] === $start) {
        $slotEnd = $sl['end'];
        if (!$sl['booked']) $slotOk = true;
        break;
    }
}

if (!$slotOk) {
    redirect('thanks.php?error=conflict&t=' . (int)$tenant_id);
}

$types = array_filter(array_map('trim', explode(',', $settings['consultation_types'])), 'strlen');
$err = '';
$values = [
    'customer_name'     => '',
    'customer_kana'     => '',
    'customer_tel'      => '',
    'customer_email'    => '',
    'consultation_type' => '',
    'memo'              => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'セッションが無効です。最初からやり直してください。';
    } else {
        // Honeypot — 隠しフィールドに値が入っていたら拒否
        if (!empty($_POST['website'])) {
            redirect('thanks.php?error=spam&t=' . (int)$tenant_id);
        }

        foreach ($values as $k => $_) {
            $values[$k] = trim((string)($_POST[$k] ?? ''));
        }

        if ($values['customer_name'] === '') {
            $err = '氏名は必須です。';
        } elseif (strlen($values['customer_name']) > 100) {
            $err = '氏名が長すぎます。';
        } elseif ($values['customer_email'] !== '' &&
                  !filter_var($values['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $err = 'メールアドレスの形式が不正です。';
        } else {
            try {
                $stmt = db()->prepare(
                    'INSERT INTO lc_appointments
                       (tenant_id, reservation_date, start_time, end_time,
                        status, customer_name, customer_kana, customer_tel,
                        customer_email, consultation_type, memo, ip, user_agent)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $tenant_id, $date, $start, $slotEnd,
                    'pending',
                    $values['customer_name'],
                    $values['customer_kana'],
                    $values['customer_tel'],
                    $values['customer_email'],
                    $values['consultation_type'],
                    $values['memo'],
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]);
                $newId = (int)db()->lastInsertId();
                redirect('thanks.php?id=' . $newId . '&t=' . (int)$tenant_id);
            } catch (PDOException $e) {
                // UNIQUE 制約違反 = 二重予約
                if ($e->getCode() === '23000') {
                    redirect('thanks.php?error=conflict&t=' . (int)$tenant_id);
                }
                throw $e;
            }
        }
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>予約内容確認｜<?= h($settings['business_name']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="public-body">
<header class="public-header">
    <h1><?= h($settings['business_name']) ?></h1>
    <p class="subtitle">ご予約内容の入力</p>
</header>
<main class="public-main">

<p><a href="index.php?t=<?= (int)$tenant_id ?>&d=<?= h($date) ?>">← カレンダーへ戻る</a></p>

<div class="selected-slot">
    <strong>選択された日時:</strong>
    <?= h($date) ?>
    (<?= h(dow_label((int)date('w', strtotime($date)))) ?>)
    <?= h(substr($start,0,5)) ?>〜<?= h(substr($slotEnd,0,5)) ?>
</div>

<?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>

<form method="post" class="form-vert">
<input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
<input type="hidden" name="date"  value="<?= h($date) ?>">
<input type="hidden" name="start" value="<?= h($start) ?>">

<!-- Honeypot -->
<div style="position:absolute;left:-9999px;">
    <label>Website
        <input type="text" name="website" tabindex="-1" autocomplete="off">
    </label>
</div>

<label>氏名 <span class="required">必須</span>
    <input type="text" name="customer_name" required maxlength="100"
           value="<?= h($values['customer_name']) ?>">
</label>

<label>ふりがな
    <input type="text" name="customer_kana" maxlength="100"
           value="<?= h($values['customer_kana']) ?>">
</label>

<div class="row2">
    <label>電話番号
        <input type="tel" name="customer_tel" maxlength="30"
               value="<?= h($values['customer_tel']) ?>">
    </label>
    <label>メールアドレス
        <input type="email" name="customer_email" maxlength="255"
               value="<?= h($values['customer_email']) ?>">
    </label>
</div>

<label>ご相談内容
    <select name="consultation_type">
        <option value="">—（選択してください）</option>
        <?php foreach ($types as $t): ?>
            <option value="<?= h($t) ?>" <?= $t === $values['consultation_type'] ? 'selected' : '' ?>>
                <?= h($t) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>備考（ご相談の概要など）
    <textarea name="memo" rows="4"><?= h($values['memo']) ?></textarea>
</label>

<p class="muted">
    送信後、上記の日時で<strong>仮予約</strong>として承ります。<br>
    確定のご連絡を担当者よりお送りいたします。
</p>

<button type="submit" class="btn-primary btn-large">この内容で予約する</button>
</form>

</main>
<footer class="public-footer">
    <small><?= h($settings['business_name']) ?></small>
</footer>
</body>
</html>
