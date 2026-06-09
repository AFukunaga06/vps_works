<?php
require_once __DIR__ . '/imap_helper.php';
date_default_timezone_set(TIMEZONE);

$filter  = $_GET['filter'] ?? 'unread';
$sent    = isset($_GET['sent']);
$error   = '';
$emails  = [];

try {
    $emails = fetch_emails(25, $filter === 'unread');
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>問い合わせ管理 | <?= APP_NAME ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <header>
    <h1>📬 問い合わせ管理</h1>
    <p class="subtitle"><?= APP_NAME ?></p>
  </header>

  <?php if ($sent): ?>
    <div class="alert-success">✅ 返信を送信しました</div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="filter-bar">
    <a href="?filter=unread" class="filter-btn <?= $filter==='unread'?'active':'' ?>">未読</a>
    <a href="?filter=all"    class="filter-btn <?= $filter==='all'   ?'active':'' ?>">すべて（25件）</a>
  </div>

  <?php if (empty($emails) && !$error): ?>
    <p class="no-events">メールはありません</p>
  <?php else: ?>
    <div class="email-list">
      <?php foreach ($emails as $e): ?>
      <a href="detail.php?id=<?= urlencode($e['id']) ?>" class="email-row <?= $e['unread']?'unread':'' ?>">
        <div class="email-top">
          <span class="category-badge" style="background:<?= $e['category']['color'] ?>">
            <?= htmlspecialchars($e['category']['label']) ?>
          </span>
          <span class="email-date"><?= htmlspecialchars($e['dateStr']) ?></span>
        </div>
        <div class="email-subject"><?= htmlspecialchars(mb_substr($e['subject'], 0, 60)) ?></div>
        <div class="email-from"><?= htmlspecialchars($e['from']) ?></div>
      </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
