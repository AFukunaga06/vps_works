<?php
require_once __DIR__ . '/../includes/functions.php';

$date = $_GET['date'] ?? '';
$type = $_GET['type'] ?? '';

// バリデーション
if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header('Location: ' . BASE_URL . '/reserve/index.php');
    exit;
}
if (!in_array($type, CONSULT_TYPES, true)) {
    header('Location: ' . BASE_URL . '/reserve/index.php');
    exit;
}
if (!is_bookable_date($date)) {
    header('Location: ' . BASE_URL . '/reserve/index.php');
    exit;
}

$available_slots = get_available_slots($date, $type);
$date_label = date('Y年n月j日（', strtotime($date)) . ['日','月','火','水','木','金','土'][(int)date('w', strtotime($date))] . '）';
$type_color  = ($type === '一般相談') ? '#0d47a1' : '#4a148c';

// POSTエラー表示用
$errors = [];
$old    = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // バリデーション
    $fields = ['name','kana','email','tel','start_time','is_first','consult_method','content'];
    foreach ($fields as $f) {
        $old[$f] = trim($_POST[$f] ?? '');
    }
    if (!$old['name'])         $errors['name'] = 'お名前を入力してください。';
    if (!$old['kana'])         $errors['kana'] = 'ふりがなを入力してください。';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = '正しいメールアドレスを入力してください。';
    if (!preg_match('/^[\d\-\(\)\+]+$/', $old['tel'])) $errors['tel'] = '電話番号を正しく入力してください。';
    if (!in_array($old['start_time'], $available_slots)) $errors['start_time'] = '時間を選択してください。';
    if (!in_array($old['is_first'], ['1','0'])) $errors['is_first'] = '相談回数を選択してください。';
    if (!in_array($old['consult_method'], ['Zoom','電話','対面'])) $errors['consult_method'] = '相談方法を選択してください。';
    if (!$old['content']) $errors['content'] = 'ご相談内容の概要を入力してください。';

    if (empty($errors)) {
        // セッションに保存して確認画面へ
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['reserve_data'] = [
            'date'            => $date,
            'type'            => $type,
            'start_time'      => $old['start_time'],
            'name'            => $old['name'],
            'kana'            => $old['kana'],
            'email'           => $old['email'],
            'tel'             => $old['tel'],
            'is_first'        => (int)$old['is_first'],
            'consult_method'  => $old['consult_method'],
            'content'         => $old['content'],
            'note'            => trim($_POST['note'] ?? ''),
        ];
        header('Location: ' . BASE_URL . '/reserve/confirm.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> - 予約入力</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
:root { --fuku-green: #3a7d5c; }
body { background: #f8f9fa; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.site-header { background: var(--fuku-green); color: #fff; padding: 1rem; }
.required { color: #dc3545; font-size: .8rem; margin-left: 4px; }
.slot-btn input[type=radio] { display: none; }
.slot-btn label { display: inline-block; padding: 6px 14px; border: 2px solid #dee2e6;
  border-radius: 6px; cursor: pointer; font-size: .9rem; transition: .15s; }
.slot-btn input[type=radio]:checked + label { border-color: <?= h($type_color) ?>; background: <?= h($type_color) ?>; color: #fff; }
</style>
</head>
<body>
<div class="site-header">
  <div class="container">
    <h1 class="h4 mb-0"><?= SITE_NAME ?></h1>
  </div>
</div>

<div class="container py-4" style="max-width: 680px;">
  <!-- パンくず -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.php">カレンダー</a></li>
      <li class="breadcrumb-item active">予約入力</li>
    </ol>
  </nav>

  <!-- 選択内容サマリー -->
  <div class="alert mb-4" style="background:#e8f4ee; border-color: <?= h($type_color) ?>; border-left: 4px solid <?= h($type_color) ?>">
    <strong style="color:<?= h($type_color) ?>"><?= h($type) ?></strong>
    <?= h($date_label) ?>
  </div>

  <h2 class="h5 mb-3">ご予約フォーム</h2>

  <?php if ($errors): ?>
  <div class="alert alert-danger">入力内容を確認してください。</div>
  <?php endif; ?>

  <form method="post" action="">
    <!-- 時間帯選択 -->
    <div class="mb-3">
      <label class="form-label fw-bold">ご希望の時間 <span class="required">必須</span></label>
      <?php if (empty($available_slots)): ?>
        <p class="text-danger">この日は満席です。<a href="index.php">カレンダーに戻る</a></p>
      <?php else: ?>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($available_slots as $slot): ?>
        <div class="slot-btn">
          <input type="radio" name="start_time" id="slot_<?= h($slot) ?>"
                 value="<?= h($slot) ?>" <?= (($old['start_time'] ?? '') === $slot ? 'checked' : '') ?>>
          <label for="slot_<?= h($slot) ?>"><?= h($slot) ?>〜<br><small><?= date('H:i', strtotime($slot) + 2700) ?></small></label>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (isset($errors['start_time'])): ?>
        <div class="text-danger small mt-1"><?= h($errors['start_time']) ?></div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- 初回 or 2回目以降 -->
    <div class="mb-3">
      <label class="form-label fw-bold">ご相談回数 <span class="required">必須</span></label>
      <div class="d-flex gap-3">
        <div class="form-check">
          <input class="form-check-input" type="radio" name="is_first" id="first_yes" value="1"
                 <?= (($old['is_first'] ?? '1') === '1' ? 'checked' : '') ?>>
          <label class="form-check-label" for="first_yes">初回（無料）</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="is_first" id="first_no" value="0"
                 <?= (($old['is_first'] ?? '') === '0' ? 'checked' : '') ?>>
          <label class="form-check-label" for="first_no">
            2回目以降
            <?php $price = ($type === '一般相談') ? 1200 : 1500; ?>
            （<?= number_format($price) ?>円・前払い）
          </label>
        </div>
      </div>
      <?php if (isset($errors['is_first'])): ?>
        <div class="text-danger small mt-1"><?= h($errors['is_first']) ?></div>
      <?php endif; ?>
    </div>

    <!-- 相談方法 -->
    <div class="mb-3">
      <label class="form-label fw-bold">ご希望の相談方法 <span class="required">必須</span></label>
      <div class="d-flex gap-3 flex-wrap">
        <?php foreach (['Zoom','電話','対面'] as $m): ?>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="consult_method"
                 id="method_<?= h($m) ?>" value="<?= h($m) ?>"
                 <?= (($old['consult_method'] ?? '') === $m ? 'checked' : '') ?>>
          <label class="form-check-label" for="method_<?= h($m) ?>"><?= h($m) ?></label>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (isset($errors['consult_method'])): ?>
        <div class="text-danger small mt-1"><?= h($errors['consult_method']) ?></div>
      <?php endif; ?>
    </div>

    <hr>

    <!-- お名前 -->
    <div class="mb-3">
      <label for="name" class="form-label fw-bold">お名前 <span class="required">必須</span></label>
      <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
             id="name" name="name" value="<?= h($old['name'] ?? '') ?>" placeholder="例：福田 花子">
      <?php if (isset($errors['name'])): ?>
        <div class="invalid-feedback"><?= h($errors['name']) ?></div>
      <?php endif; ?>
    </div>

    <!-- ふりがな -->
    <div class="mb-3">
      <label for="kana" class="form-label fw-bold">ふりがな <span class="required">必須</span></label>
      <input type="text" class="form-control <?= isset($errors['kana']) ? 'is-invalid' : '' ?>"
             id="kana" name="kana" value="<?= h($old['kana'] ?? '') ?>" placeholder="例：ふくだ はなこ">
      <?php if (isset($errors['kana'])): ?>
        <div class="invalid-feedback"><?= h($errors['kana']) ?></div>
      <?php endif; ?>
    </div>

    <!-- メールアドレス -->
    <div class="mb-3">
      <label for="email" class="form-label fw-bold">メールアドレス <span class="required">必須</span></label>
      <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
             id="email" name="email" value="<?= h($old['email'] ?? '') ?>" placeholder="例：hanako@example.com">
      <?php if (isset($errors['email'])): ?>
        <div class="invalid-feedback"><?= h($errors['email']) ?></div>
      <?php endif; ?>
    </div>

    <!-- 電話番号 -->
    <div class="mb-3">
      <label for="tel" class="form-label fw-bold">電話番号 <span class="required">必須</span></label>
      <input type="tel" class="form-control <?= isset($errors['tel']) ? 'is-invalid' : '' ?>"
             id="tel" name="tel" value="<?= h($old['tel'] ?? '') ?>" placeholder="例：090-0000-0000">
      <?php if (isset($errors['tel'])): ?>
        <div class="invalid-feedback"><?= h($errors['tel']) ?></div>
      <?php endif; ?>
    </div>

    <!-- 相談内容概要 -->
    <div class="mb-3">
      <label for="content" class="form-label fw-bold">ご相談内容の概要 <span class="required">必須</span></label>
      <textarea class="form-control <?= isset($errors['content']) ? 'is-invalid' : '' ?>"
                id="content" name="content" rows="4"
                placeholder="短くで構いません。どのようなことでお悩みかを教えてください。"><?= h($old['content'] ?? '') ?></textarea>
      <?php if (isset($errors['content'])): ?>
        <div class="invalid-feedback"><?= h($errors['content']) ?></div>
      <?php endif; ?>
    </div>

    <!-- 備考 -->
    <div class="mb-4">
      <label for="note" class="form-label">その他・備考（任意）</label>
      <textarea class="form-control" id="note" name="note" rows="2"><?= h($old['note'] ?? '') ?></textarea>
    </div>

    <div class="d-grid gap-2">
      <button type="submit" class="btn btn-lg text-white" style="background: <?= h($type_color) ?>">
        確認画面へ進む →
      </button>
      <a href="index.php" class="btn btn-outline-secondary">カレンダーに戻る</a>
    </div>
  </form>
</div>
</body>
</html>
