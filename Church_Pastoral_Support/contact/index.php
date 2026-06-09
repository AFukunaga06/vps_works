<?php
ob_start();
require_once dirname(__DIR__).'/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '/var/www/html/fuku_soudan/vendor/autoload.php';

define('NOTIFY_FROM',      'afky5906@gmail.com');
define('NOTIFY_FROM_NAME', '教会牧会支援');
define('NOTIFY_TO',        'afky5906@gmail.com');
define('NOTIFY_APP_PASS',  'REDACTED_FOR_PUBLIC');

function send_contact_mail(array $p, int $id): void {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = NOTIFY_FROM;
        $mail->Password   = NOTIFY_APP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(NOTIFY_FROM, NOTIFY_FROM_NAME);
        $mail->addAddress(NOTIFY_TO);
        $mail->Subject = '【お問い合わせ】' . $p['name'] . ' 様';
        $id_str = '#' . str_pad($id, 5, '0', STR_PAD_LEFT);
        $mail->Body = <<<TEXT
お問い合わせが届きました。（{$id_str}）

【お名前】  {$p['name']}
【ふりがな】{$p['kana']}
【メール】  {$p['email']}
【電話】    {$p['tel']}
【連絡希望時間帯】{$p['preferred_time']}

【内容】
{$p['content']}

管理画面: http://162.43.14.130/Church_Pastoral_Support/contact/admin.php
TEXT;
        $mail->send();
    } catch (Exception $e) {
        error_log('Contact mail error: ' . $mail->ErrorInfo);
    }
}

$errors = [];
$post   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = [
        'name'           => trim($_POST['name'] ?? ''),
        'kana'           => trim($_POST['kana'] ?? ''),
        'email'          => trim($_POST['email'] ?? ''),
        'tel'            => trim($_POST['tel'] ?? ''),
        'preferred_time' => $_POST['preferred_time'] ?? '',
        'content'        => trim($_POST['content'] ?? ''),
    ];

    if ($post['name'] === '')           $errors[] = 'お名前は必須です。';
    if ($post['email'] === '' && $post['tel'] === '') $errors[] = 'メールアドレスまたは電話番号のどちらかは必須です。';
    if ($post['email'] !== '' && !filter_var($post['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'メールアドレスの形式が正しくありません。';
    if (!in_array($post['preferred_time'], ['午前中', '午後', '夜'], true)) $errors[] = 'ご希望の連絡時間帯を選択してください。';
    if ($post['content'] === '')        $errors[] = 'お問い合わせ内容は必須です。';

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO contact_requests (name, kana, email, tel, preferred_time, content)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $post['name'], $post['kana'], $post['email'],
            $post['tel'],  $post['preferred_time'], $post['content'],
        ]);
        $id = $pdo->lastInsertId();

        // inquiriesテーブルにも登録（管理画面の問い合わせ管理に表示）
        $pdo->prepare(
            "INSERT INTO inquiries
             (inquirer_name, inquirer_kana, inquirer_email, inquirer_tel, preferred_time,
              inquiry_type, inquiry_date, content, status)
             VALUES (?, ?, ?, ?, ?, 'form', CURDATE(), ?, 'pending')"
        )->execute([
            $post['name'], $post['kana'], $post['email'],
            $post['tel'],  $post['preferred_time'], $post['content'],
        ]);

        // 管理者へメール通知
        send_contact_mail($post, (int)$id);

        ob_end_clean();
        header('Location: complete.php?id=' . $id);
        exit;
    }
}

$time_slots = [
    '午前中' => '9:30 〜 12:00',
    '午後'   => '13:00 〜 17:00',
    '夜'     => '18:00 〜 20:00',
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>お問い合わせ - 教会牧会支援</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root { --church-blue: #1e3a5f; --church-light: #e8f0f8; }
body { background: #f0f4f8; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.site-header {
    background: var(--church-blue);
    color: #fff;
    padding: 1rem 0;
    border-bottom: 4px solid #2c5f8a;
}
.site-header h1 { font-size: 1.2rem; }
.section-title {
    font-size: 1rem;
    font-weight: bold;
    color: var(--church-blue);
    border-left: 4px solid var(--church-blue);
    padding-left: .6rem;
    margin-bottom: 1rem;
}
.time-slot-card {
    cursor: pointer;
    border: 2px solid #dee2e6;
    border-radius: .5rem;
    transition: all .2s;
    padding: 1rem;
    text-align: center;
    background: #fff;
}
.time-slot-card:hover {
    border-color: var(--church-blue);
    background: var(--church-light);
}
.time-slot-card.selected {
    border-color: var(--church-blue);
    background: var(--church-blue);
}
.time-slot-card.selected .time-slot-label,
.time-slot-card.selected .time-slot-range { color: #fff; }
.time-slot-card input[type=radio] { display: none; }
.time-slot-label { font-size: 1.1rem; font-weight: bold; color: var(--church-blue); }
.time-slot-range { font-size: .85rem; color: #666; }
.required { color: #dc3545; font-size: .8rem; margin-left: .3rem; }
</style>
</head>
<body>

<header class="site-header">
  <div class="container d-flex justify-content-between align-items-center">
    <h1 class="mb-0"><i class="bi bi-church"></i> 教会牧会支援 — お問い合わせ</h1>
    <a href="/Church_Pastoral_Support/" class="btn btn-sm btn-outline-light">
      <i class="bi bi-speedometer2"></i> ダッシュボードへ
    </a>
  </div>
</header>

<div class="container py-5" style="max-width: 680px;">

  <p class="text-muted mb-4">
    ご相談・お問い合わせはこちらのフォームよりお気軽にご連絡ください。<br>
    担当者よりご希望の時間帯にご連絡いたします。
  </p>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <form method="post" novalidate>

    <!-- お名前 -->
    <div class="mb-3">
      <label class="form-label fw-bold">お名前<span class="required">必須</span></label>
      <input type="text" name="name" class="form-control"
             value="<?= htmlspecialchars($post['name'] ?? '') ?>"
             placeholder="例：田中 太郎">
    </div>

    <!-- ふりがな -->
    <div class="mb-3">
      <label class="form-label">ふりがな</label>
      <input type="text" name="kana" class="form-control"
             value="<?= htmlspecialchars($post['kana'] ?? '') ?>"
             placeholder="例：たなか たろう">
    </div>

    <!-- メール -->
    <div class="mb-3">
      <label class="form-label fw-bold">メールアドレス<span class="required">※どちらか必須</span></label>
      <input type="email" name="email" class="form-control"
             value="<?= htmlspecialchars($post['email'] ?? '') ?>"
             placeholder="例：example@mail.com">
    </div>

    <!-- 電話 -->
    <div class="mb-4">
      <label class="form-label fw-bold">電話番号<span class="required">※どちらか必須</span></label>
      <input type="tel" name="tel" class="form-control"
             value="<?= htmlspecialchars($post['tel'] ?? '') ?>"
             placeholder="例：080-1234-5678">
    </div>

    <!-- 連絡希望時間帯 -->
    <div class="mb-4">
      <div class="section-title"><i class="bi bi-clock"></i> ご希望の連絡時間帯<span class="required">必須</span></div>
      <div class="row g-3">
        <?php foreach ($time_slots as $val => $range):
            $selected = (($post['preferred_time'] ?? '') === $val) ? 'selected' : '';
        ?>
        <div class="col-4">
          <label class="time-slot-card w-100 <?= $selected ?>" id="card-<?= $val ?>">
            <input type="radio" name="preferred_time" value="<?= $val ?>"
                   <?= $selected ? 'checked' : '' ?>>
            <div class="time-slot-label"><?= $val ?></div>
            <div class="time-slot-range"><?= $range ?></div>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- お問い合わせ内容 -->
    <div class="mb-4">
      <label class="form-label fw-bold">お問い合わせ内容<span class="required">必須</span><span class="text-muted fw-normal" style="font-size:.8rem">（200字以内で）</span></label>
      <textarea name="content" class="form-control" rows="6"
                placeholder="ご相談内容をご記入ください"><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
    </div>

    <div class="d-grid">
      <button type="submit" class="btn btn-primary btn-lg" style="background:var(--church-blue);border-color:var(--church-blue);">
        <i class="bi bi-send"></i> 送信する
      </button>
    </div>

  </form>
</div>

<script>
document.querySelectorAll('input[name="preferred_time"]').forEach(radio => {
    radio.addEventListener('change', () => {
        document.querySelectorAll('.time-slot-card').forEach(c => c.classList.remove('selected'));
        radio.closest('.time-slot-card').classList.add('selected');
    });
});
</script>
</body>
</html>
