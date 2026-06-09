<?php
session_start();
require_once __DIR__ . '/config.php';

// CSRF token生成
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// フラッシュメッセージ
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>お問い合わせ | <?= htmlspecialchars(SITE_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<?php if (RECAPTCHA_ENABLED && RECAPTCHA_SITE_KEY): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars(RECAPTCHA_SITE_KEY) ?>"></script>
<?php endif; ?>
<style>
body { background:#f4f6fb; font-family:'Hiragino Kaku Gothic ProN','Yu Gothic',sans-serif; }
.hero { background:linear-gradient(135deg,#1a2744 0%,#2a4a8a 100%);
        color:#fff; padding:2.5rem 1.5rem; text-align:center; }
.hero h1 { font-size:1.8rem; font-weight:700; }
.card-form { background:#fff; border-radius:.75rem; border:1px solid #e0e6f0;
             box-shadow:0 2px 12px rgba(0,0,0,.07); }
.btn-submit { background:#2a4a8a; border:none; color:#fff; padding:.6rem 2rem;
              font-size:1rem; border-radius:.4rem; transition:background .15s; }
.btn-submit:hover { background:#1a3060; color:#fff; }
.required::after { content:' *'; color:#dc3545; font-size:.85em; }
</style>
</head>
<body>

<div class="hero">
  <i class="bi bi-envelope-fill fs-1 mb-2 d-block"></i>
  <h1>お問い合わせ</h1>
  <p class="mb-0" style="color:rgba(255,255,255,.7)"><?= htmlspecialchars(SITE_NAME) ?></p>
</div>

<div class="container py-5" style="max-width:680px">

<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible" role="alert">
  <?= htmlspecialchars($flash['msg']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card-form p-4 p-md-5">
  <p class="text-muted small mb-4">
    <i class="bi bi-info-circle me-1"></i>
    <span class="required">必須</span>は必ずご記入ください。通常2〜3営業日以内にご返信します。
  </p>

  <form method="post" action="submit.php" id="contactForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <!-- Honeypot: botが入力するフィールド（人間には不可視）-->
    <div style="display:none" aria-hidden="true">
      <input type="text" name="website" tabindex="-1" autocomplete="off">
    </div>
    <?php if (RECAPTCHA_ENABLED && RECAPTCHA_SITE_KEY): ?>
    <input type="hidden" name="g_recaptcha_response" id="g_recaptcha_response">
    <?php endif; ?>

    <!-- お名前 -->
    <div class="mb-3">
      <label class="form-label required fw-semibold">お名前</label>
      <input type="text" name="name" class="form-control" maxlength="100"
             value="<?= htmlspecialchars($_SESSION['form_data']['name'] ?? '') ?>" required>
    </div>

    <!-- メール -->
    <div class="mb-3">
      <label class="form-label required fw-semibold">メールアドレス</label>
      <input type="email" name="email" class="form-control" maxlength="200"
             value="<?= htmlspecialchars($_SESSION['form_data']['email'] ?? '') ?>" required>
    </div>

    <!-- 電話 -->
    <div class="mb-3">
      <label class="form-label fw-semibold">電話番号 <span class="text-muted small">(任意)</span></label>
      <input type="tel" name="phone" class="form-control" maxlength="30"
             value="<?= htmlspecialchars($_SESSION['form_data']['phone'] ?? '') ?>">
    </div>

    <!-- 種別 -->
    <div class="mb-3">
      <label class="form-label required fw-semibold">お問い合わせ種別</label>
      <select name="subject" class="form-select" required>
        <option value="">-- 選択してください --</option>
        <?php
        $subjects = ['Webシステム制作について','料金・見積もりについて','機能追加・カスタマイズ','その他'];
        $sel_sub  = $_SESSION['form_data']['subject'] ?? '';
        foreach ($subjects as $s): ?>
        <option value="<?= htmlspecialchars($s) ?>" <?= $sel_sub === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- 本文 -->
    <div class="mb-4">
      <label class="form-label required fw-semibold">お問い合わせ内容</label>
      <textarea name="message" class="form-control" rows="6" maxlength="3000" required
                style="resize:vertical"><?= htmlspecialchars($_SESSION['form_data']['message'] ?? '') ?></textarea>
      <div class="form-text text-end small" id="charCount"></div>
    </div>

    <?php unset($_SESSION['form_data']); ?>

    <div class="d-grid">
      <button type="submit" class="btn btn-submit" id="submitBtn">
        <i class="bi bi-send me-1"></i>送信する
      </button>
    </div>
  </form>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// 文字数カウント
const msg = document.querySelector('textarea[name="message"]');
const cnt = document.getElementById('charCount');
if (msg && cnt) {
  const update = () => cnt.textContent = msg.value.length + ' / 3000 文字';
  msg.addEventListener('input', update);
  update();
}

<?php if (RECAPTCHA_ENABLED && RECAPTCHA_SITE_KEY): ?>
document.getElementById('contactForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>送信中...';
  grecaptcha.ready(function() {
    grecaptcha.execute('<?= htmlspecialchars(RECAPTCHA_SITE_KEY) ?>', {action:'contact'}).then(function(token) {
      document.getElementById('g_recaptcha_response').value = token;
      document.getElementById('contactForm').submit();
    });
  });
});
<?php else: ?>
document.getElementById('contactForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>送信中...';
});
<?php endif; ?>
</script>
</body>
</html>
