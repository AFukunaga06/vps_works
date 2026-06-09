<?php
require_once __DIR__ . '/../lib/db.php';

$pdo = db();
$plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll();

// 既ログインしている受講者のメールがあれば初期表示に使う（任意）
$prefill_name  = trim((string)($_GET['name']  ?? ''));
$prefill_email = trim((string)($_GET['email'] ?? ''));

$flash = flash_take();
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>受講料お支払い - <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
  body{background:#faf8f5;color:#333;font-family:"Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif}
  h1{color:#4a7c59;border-bottom:2px solid #d4a574;padding-bottom:8px}
  .plan-card{border:2px solid #e0d8c8;border-radius:12px;padding:20px;cursor:pointer;background:#fff;transition:.2s}
  .plan-card:hover{border-color:#4a7c59}
  .plan-card.selected{border-color:#4a7c59;background:#f0f7f2;box-shadow:0 2px 12px rgba(74,124,89,.2)}
  .plan-price{font-size:2em;color:#4a7c59;font-weight:700}
  .plan-unit{font-size:.6em;color:#666;font-weight:400}
  .plan-desc{font-size:.92em;color:#555;margin-top:8px}
  .badge-recommend{background:#d4a574;color:#fff;font-size:.7em;padding:2px 8px;border-radius:8px;vertical-align:middle}
</style>
</head>
<body>
<main class="container" style="max-width:760px;padding:32px 16px">
<h1>受講料 お支払い</h1>
<p class="text-muted">プランを選択し、お支払いに進んでください。決済はSquareの安全な決済画面で行われます。</p>

<?php foreach ($flash as $f):
    $cls = ($f['type'] === 'error') ? 'danger' : (($f['type'] === 'success') ? 'success' : 'info'); ?>
  <div class="alert alert-<?= h($cls) ?>"><?= h($f['msg']) ?></div>
<?php endforeach; ?>

<form action="checkout.php" method="post">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

  <div class="row g-3 my-4">
    <?php foreach ($plans as $i => $p): ?>
      <div class="col-md-6">
        <label class="plan-card d-block <?= $i === 0 ? 'selected' : '' ?>" data-plan="<?= h($p['code']) ?>">
          <input type="radio" name="plan_code" value="<?= h($p['code']) ?>" class="d-none plan-radio" <?= $i === 0 ? 'checked' : '' ?> required>
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <strong style="font-size:1.1em"><?= h($p['name']) ?></strong>
              <?php if ($p['code'] === 'monthly'): ?><span class="badge-recommend">おすすめ</span><?php endif; ?>
            </div>
          </div>
          <div class="plan-price mt-2">
            <?= number_format((int)$p['amount']) ?><span class="plan-unit">円<?= $p['type']==='subscription'?'/月':'' ?></span>
          </div>
          <div class="plan-desc"><?= h($p['description']) ?></div>
        </label>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card p-4 mb-3">
    <h5 class="mb-3">お支払い者情報</h5>
    <div class="mb-3">
      <label class="form-label">お名前 <span class="text-danger">*</span></label>
      <input type="text" name="name" class="form-control" maxlength="100" required value="<?= h($prefill_name) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">メールアドレス <span class="text-danger">*</span></label>
      <input type="email" name="email" class="form-control" maxlength="255" required value="<?= h($prefill_email) ?>">
      <div class="form-text">決済受領メール（Squareより）が届きます。</div>
    </div>
  </div>

  <div class="d-grid gap-2 mb-5">
    <button type="submit" class="btn btn-lg" style="background:#4a7c59;color:#fff">
      Squareで決済に進む →
    </button>
    <a href="<?= h(APP_ROOT_URL) ?>/" class="btn btn-link text-muted">トップへ戻る</a>
  </div>
</form>

<div class="alert alert-light small text-muted">
  <strong>ご注意</strong><br>
  ・決済はSquareの決済画面に遷移します。<br>
  ・領収書はSquareより自動発行されます。<br>
  ・月謝プランは月途中の回数管理を講師側で行います。<br>
  ・キャンセル・返金はメールでご連絡ください（<?= h(ADMIN_MAIL) ?>）。
</div>

</main>
<script>
document.querySelectorAll('.plan-card').forEach(card => {
  card.addEventListener('click', () => {
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    card.querySelector('.plan-radio').checked = true;
  });
});
</script>
</body>
</html>
