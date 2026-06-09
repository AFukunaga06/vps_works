<?php
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$data = $_SESSION['reserve_data'] ?? null;
if (!$data) {
    header('Location: ' . BASE_URL . '/reserve/index.php');
    exit;
}

$date_label = date('Y年n月j日（', strtotime($data['date']))
    . ['日','月','火','水','木','金','土'][(int)date('w', strtotime($data['date']))] . '）';
$end_time = date('H:i', strtotime($data['start_time']) + 2700); // 45分後
$type_color = ($data['type'] === '一般相談') ? '#0d47a1' : '#4a148c';
$is_first = (bool)$data['is_first'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> - 予約確認</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
:root { --fuku-green: #3a7d5c; }
body { background: #f8f9fa; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.site-header { background: var(--fuku-green); color: #fff; padding: 1rem; }
.confirm-table th { width: 38%; background: #f0f4f1; }
</style>
</head>
<body>
<div class="site-header">
  <div class="container">
    <h1 class="h4 mb-0"><?= SITE_NAME ?></h1>
  </div>
</div>

<div class="container py-4" style="max-width: 680px;">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.php">カレンダー</a></li>
      <li class="breadcrumb-item"><a href="javascript:history.back()">予約入力</a></li>
      <li class="breadcrumb-item active">確認</li>
    </ol>
  </nav>

  <h2 class="h5 mb-3">予約内容のご確認</h2>
  <p class="text-muted small mb-3">以下の内容でよろしければ「予約する」ボタンを押してください。</p>

  <div class="card shadow-sm mb-4">
    <div class="card-body p-0">
      <table class="table table-bordered confirm-table mb-0">
        <tr><th>相談種別</th><td><strong style="color:<?= h($type_color) ?>"><?= h($data['type']) ?></strong></td></tr>
        <tr><th>日時</th><td><?= h($date_label) ?> <?= h($data['start_time']) ?>〜<?= h($end_time) ?></td></tr>
        <tr><th>相談方法</th><td><?= h($data['consult_method']) ?></td></tr>
        <tr><th>ご相談回数</th>
            <td>
              <?= $is_first ? '初回（無料）' : '2回目以降' ?>
              <?php if (!$is_first): $price = ($data['type'] === '一般相談') ? 1200 : 2000; ?>
                ／ <strong><?= number_format($price) ?>円（税込）</strong>
              <?php endif; ?>
            </td>
        </tr>
        <tr><th>お名前</th><td><?= h($data['name']) ?> 様</td></tr>
        <tr><th>ふりがな</th><td><?= h($data['kana']) ?></td></tr>
        <tr><th>メールアドレス</th><td><?= h($data['email']) ?></td></tr>
        <tr><th>電話番号</th><td><?= h($data['tel']) ?></td></tr>
        <tr><th>ご相談内容</th><td style="white-space:pre-wrap"><?= h($data['content']) ?></td></tr>
        <?php if ($data['note']): ?>
        <tr><th>備考</th><td style="white-space:pre-wrap"><?= h($data['note']) ?></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <?php if (!$is_first): ?>
  <div class="alert alert-info mb-4">
    <strong>お支払いについて</strong><br>
    2回目以降のご相談は<strong>前払い制</strong>です。<br>
    お申し込み後、担当者よりお支払い方法（振込等）をご案内します。<br>
    <strong>入金確認後に予約が確定</strong>となります。
  </div>
  <?php endif; ?>

  <form method="post" action="complete.php">
    <div class="d-grid gap-2">
      <button type="submit" class="btn btn-lg text-white" style="background:<?= h($type_color) ?>">
        予約する（この内容で確定）
      </button>
      <a href="javascript:history.back()" class="btn btn-outline-secondary">入力に戻る</a>
    </div>
  </form>
</div>
</body>
</html>
