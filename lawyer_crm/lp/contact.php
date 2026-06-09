<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_lp'])) $_SESSION['csrf_lp'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_lp'];

$is_demo  = isset($_GET['demo']);
$plan_qs  = $_GET['plan'] ?? '';
$plan_map = ['solo'=>'Solo','standard'=>'Standard','pro'=>'Pro','enterprise'=>'Enterprise'];
$plan_pref = $plan_map[$plan_qs] ?? '';

$errors = [];
$ok = false;
$form = [
    'name'    => '',
    'office'  => '',
    'email'   => '',
    'tel'     => '',
    'plan'    => $plan_pref,
    'subject' => $is_demo ? 'demo' : ($plan_pref ? 'plan' : 'other'),
    'message' => '',
    'agree'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $errors[] = '不正なリクエストです。ページを再読込してください。';
    } else {
        foreach (array_keys($form) as $k) {
            $form[$k] = trim((string)($_POST[$k] ?? ''));
        }
        if ($form['name'] === '')      $errors[] = 'お名前を入力してください。';
        if ($form['email'] === '')     $errors[] = 'メールアドレスを入力してください。';
        elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'メールアドレスの形式が正しくありません。';
        if ($form['message'] === '' && !$is_demo) $errors[] = 'お問合せ内容を入力してください。';
        if ($form['agree'] !== '1')    $errors[] = '利用規約・プライバシーポリシーへの同意が必要です。';

        // Honeypot (hidden field; bots fill anything)
        if (!empty($_POST['website'] ?? '')) $errors[] = '送信できませんでした。';

        if (!$errors) {
            try {
                $pdo = get_db();
                // Ensure table exists (idempotent)
                $pdo->exec("CREATE TABLE IF NOT EXISTS lp_contacts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    office VARCHAR(150) NOT NULL DEFAULT '',
                    email VARCHAR(255) NOT NULL,
                    tel VARCHAR(30) NOT NULL DEFAULT '',
                    plan VARCHAR(30) NOT NULL DEFAULT '',
                    subject VARCHAR(30) NOT NULL DEFAULT 'other',
                    message TEXT NOT NULL,
                    ip VARCHAR(45) NOT NULL DEFAULT '',
                    user_agent VARCHAR(255) NOT NULL DEFAULT '',
                    status ENUM('new','responded','closed') NOT NULL DEFAULT 'new',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $stmt = $pdo->prepare("INSERT INTO lp_contacts
                    (name, office, email, tel, plan, subject, message, ip, user_agent)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $form['name'], $form['office'], $form['email'], $form['tel'],
                    $form['plan'], $form['subject'], $form['message'],
                    $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
                ]);

                // Notify admin (best-effort; ignore failure)
                $admin = 'afky5906@gmail.com';
                $subj_label = $is_demo ? '【無料デモ申込】' : ($plan_pref ? "【{$plan_pref}プラン問合せ】" : '【お問合せ】');
                $body = "■LegalDesk LPからのお問合せが届きました\n\n"
                      . "種別: " . ($is_demo ? 'デモ申込' : ($plan_pref ?: 'その他')) . "\n"
                      . "お名前: {$form['name']}\n"
                      . "事務所名: {$form['office']}\n"
                      . "メール: {$form['email']}\n"
                      . "電話: {$form['tel']}\n"
                      . "希望プラン: {$form['plan']}\n"
                      . "----\n{$form['message']}\n----\n"
                      . "IP: " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n"
                      . "受信時刻: " . date('Y-m-d H:i:s') . "\n";
                @mb_send_mail($admin, $subj_label . 'LegalDesk', $body,
                    "From: no-reply@legaldesk.example.com\r\nReply-To: {$form['email']}\r\n");

                $ok = true;
                // Reset form on success
                $form = array_fill_keys(array_keys($form), '');
                unset($_SESSION['csrf_lp']);
            } catch (Throwable $e) {
                $errors[] = '送信に失敗しました。時間をおいて再度お試しください。';
                error_log('[LP-contact] ' . $e->getMessage());
            }
        }
    }
}

function he(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$page_title = $is_demo ? '無料デモ申込' : ($plan_pref ? "{$plan_pref}プランお問合せ" : 'お問合せ');
$lead       = $is_demo
    ? '14日間の無料トライアルにお申込みいただけます。クレジットカード登録は不要です。'
    : ($plan_pref
        ? "{$plan_pref}プランに関するご質問・お申込みはこちらから。担当者より2営業日以内にご連絡いたします。"
        : 'LegalDeskに関するご質問・資料請求はこちらから。担当者より2営業日以内にご返信いたします。');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= he($page_title) ?> - LegalDesk</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="_lp_styles.css">
<style>
.form-card { max-width:720px; margin:0 auto; background:#fff; padding:2.5rem 2rem; border-radius:.75rem; box-shadow:0 2px 12px rgba(26,58,92,.06); }
.form-card label { font-weight:600; color:var(--lc-navy); font-size:.92rem; }
.form-card .form-control, .form-card .form-select { font-size:.95rem; }
.required { color:#dc3545; font-weight:700; margin-left:.25rem; font-size:.85rem; }
.success-box { max-width:720px; margin:0 auto; background:#fff; border-radius:.75rem; padding:3rem 2rem; text-align:center; box-shadow:0 2px 12px rgba(26,58,92,.06); }
.success-box i { font-size:4rem; color:#28a745; }
</style>
</head>
<body style="background:#f8f9fb;">

<nav class="navbar navbar-expand-md navbar-dark navbar-lp fixed-top">
  <div class="container">
    <a class="navbar-brand" href="index.html"><i class="bi bi-briefcase-fill me-2"></i>LegalDesk</a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <i class="bi bi-list fs-4"></i>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
        <li class="nav-item"><a class="nav-link" href="index.html#features">機能</a></li>
        <li class="nav-item"><a class="nav-link" href="pricing.html">料金</a></li>
        <li class="nav-item"><a class="nav-link" href="index.html#faq">FAQ</a></li>
        <li class="nav-item"><a class="nav-link active fw-bold" href="contact.php">お問合せ</a></li>
        <li class="nav-item ms-md-2"><a class="btn btn-sm btn-outline-gold" href="/lawyer_crm/login.php">ログイン</a></li>
      </ul>
    </div>
  </div>
</nav>

<section class="page-hero">
  <div class="container">
    <h1><?= he($page_title) ?></h1>
    <p><?= he($lead) ?></p>
  </div>
</section>

<section class="py-5">
  <div class="container">
    <?php if ($ok): ?>
      <div class="success-box">
        <i class="bi bi-check-circle-fill"></i>
        <h2 class="h4 fw-bold mt-3" style="color:var(--lc-navy);">送信が完了しました</h2>
        <p class="text-muted mt-2">
          お問合せありがとうございました。<br>
          担当者より<strong>2営業日以内</strong>にご連絡いたします。<br>
          確認のため、ご入力いただいたメールアドレスにも自動返信メールをお送りしています。
        </p>
        <a class="btn btn-outline-secondary mt-3" href="index.html"><i class="bi bi-house me-1"></i>トップに戻る</a>
      </div>
    <?php else: ?>
      <div class="form-card">
        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <ul class="mb-0 small">
              <?php foreach ($errors as $e) echo '<li>' . he($e) . '</li>'; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <input type="hidden" name="csrf" value="<?= he($csrf) ?>">
          <input type="text" name="website" tabindex="-1" autocomplete="off"
                 style="position:absolute;left:-9999px;" aria-hidden="true">

          <div class="mb-3">
            <label>お問合せ種別<span class="required">*</span></label>
            <select name="subject" class="form-select" required>
              <option value="demo"     <?= $form['subject']==='demo'?'selected':'' ?>>無料デモ・トライアル申込</option>
              <option value="plan"     <?= $form['subject']==='plan'?'selected':'' ?>>プランに関する質問</option>
              <option value="estimate" <?= $form['subject']==='estimate'?'selected':'' ?>>見積依頼</option>
              <option value="document" <?= $form['subject']==='document'?'selected':'' ?>>資料請求</option>
              <option value="custom"   <?= $form['subject']==='custom'?'selected':'' ?>>カスタマイズ・連携相談</option>
              <option value="other"    <?= $form['subject']==='other'?'selected':'' ?>>その他</option>
            </select>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label>お名前<span class="required">*</span></label>
              <input type="text" name="name" class="form-control" maxlength="100" required
                     value="<?= he($form['name']) ?>" placeholder="山田 太郎">
            </div>
            <div class="col-md-6">
              <label>事務所名</label>
              <input type="text" name="office" class="form-control" maxlength="150"
                     value="<?= he($form['office']) ?>" placeholder="山田法律事務所">
            </div>
          </div>

          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label>メールアドレス<span class="required">*</span></label>
              <input type="email" name="email" class="form-control" maxlength="255" required
                     value="<?= he($form['email']) ?>" placeholder="example@example.com">
            </div>
            <div class="col-md-6">
              <label>電話番号</label>
              <input type="tel" name="tel" class="form-control" maxlength="30"
                     value="<?= he($form['tel']) ?>" placeholder="03-0000-0000">
            </div>
          </div>

          <div class="mb-3 mt-3">
            <label>ご興味のあるプラン</label>
            <select name="plan" class="form-select">
              <option value=""           <?= $form['plan']===''?'selected':'' ?>>未定・後で決める</option>
              <option value="Solo"       <?= $form['plan']==='Solo'?'selected':'' ?>>Solo（月額3,300円）</option>
              <option value="Standard"   <?= $form['plan']==='Standard'?'selected':'' ?>>Standard（月額9,800円）</option>
              <option value="Pro"        <?= $form['plan']==='Pro'?'selected':'' ?>>Pro（月額19,800円）</option>
              <option value="Enterprise" <?= $form['plan']==='Enterprise'?'selected':'' ?>>Enterprise（11名以上）</option>
            </select>
          </div>

          <div class="mb-3">
            <label>お問合せ内容<?= $is_demo ? '' : '<span class="required">*</span>' ?></label>
            <textarea name="message" class="form-control" rows="5" maxlength="2000"
                      <?= $is_demo ? '' : 'required' ?>
                      placeholder="<?= $is_demo
                        ? '事務所の規模・現在の課題などをお聞かせください（任意）。'
                        : 'ご質問・ご要望をご記入ください。' ?>"><?= he($form['message']) ?></textarea>
          </div>

          <div class="form-check mb-4">
            <input type="checkbox" name="agree" value="1" id="agree" class="form-check-input" required
                   <?= $form['agree']==='1'?'checked':'' ?>>
            <label class="form-check-label small" for="agree">
              <a href="terms.html" target="_blank">利用規約</a> および
              <a href="privacy.html" target="_blank">プライバシーポリシー</a>
              に同意します<span class="required">*</span>
            </label>
          </div>

          <div class="d-grid">
            <button class="btn btn-gold btn-lg">
              <i class="bi bi-send me-1"></i>送信する
            </button>
          </div>
          <p class="small text-muted text-center mt-3 mb-0">
            通常2営業日以内に担当者よりご連絡いたします。
          </p>
        </form>
      </div>
    <?php endif; ?>
  </div>
</section>

<footer>
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <h6><i class="bi bi-briefcase-fill me-1" style="color:var(--lc-gold);"></i>LegalDesk</h6>
        <p class="small mb-0">個人・小規模事務所のための弁護士CRM。</p>
      </div>
      <div class="col-6 col-md-2">
        <h6>サービス</h6>
        <a href="index.html#features">機能</a>
        <a href="pricing.html">料金プラン</a>
        <a href="index.html#faq">FAQ</a>
      </div>
      <div class="col-6 col-md-2">
        <h6>サポート</h6>
        <a href="contact.php">お問合せ</a>
        <a href="contact.php?demo=1">デモ申込</a>
        <a href="/lawyer_crm/login.php">ログイン</a>
      </div>
      <div class="col-md-4">
        <h6>規約・運営</h6>
        <a href="terms.html">利用規約</a>
        <a href="privacy.html">プライバシーポリシー</a>
        <a href="tokushoho.html">特定商取引法に基づく表記</a>
      </div>
    </div>
    <div class="footer-bottom">&copy; 2026 LegalDesk. All rights reserved.</div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
