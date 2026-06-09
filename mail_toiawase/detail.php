<?php
require_once __DIR__ . '/imap_helper.php';
require_once __DIR__ . '/claude_helper.php';
date_default_timezone_set(TIMEZONE);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

try {
    $email = fetch_email_detail($id);
} catch (RuntimeException $e) {
    die('メール取得エラー: ' . htmlspecialchars($e->getMessage()));
}

$subject      = $email['subject'];
$from         = $email['from'];
$body         = $email['body'];
$category     = classify_email($subject, $body);
$reply        = generate_reply($subject, $body, $category['label']);
$replySubject = preg_match('/^Re:/i', $subject) ? $subject : 'Re: ' . $subject;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>メール詳細 | <?= APP_NAME ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <header>
    <h1>📬 メール詳細</h1>
  </header>

  <a href="index.php" class="back-link">← 一覧に戻る</a>

  <div class="email-detail-card">
    <div class="detail-header">
      <span class="category-badge" style="background:<?= $category['color'] ?>">
        <?= htmlspecialchars($category['label']) ?>
      </span>
      <h2><?= htmlspecialchars($subject) ?></h2>
      <div class="detail-meta">
        <span>📨 <?= htmlspecialchars($from) ?></span>
        <span>🕐 <?= htmlspecialchars($email['dateStr']) ?></span>
      </div>
    </div>
    <div class="email-body"><?= nl2br(htmlspecialchars($body)) ?></div>
  </div>

  <div class="reply-section">
    <h3>✍️ AI生成 返信文案</h3>
    <p class="reply-note">内容を確認・編集してから送信してください</p>
    <form action="send.php" method="post">
      <input type="hidden" name="to" value="<?= htmlspecialchars($from) ?>">
      <div class="form-group">
        <label>件名</label>
        <input type="text" name="reply_subject" value="<?= htmlspecialchars($replySubject) ?>">
      </div>
      <div class="form-group">
        <label>返信本文（編集可）</label>
        <textarea name="reply_body" rows="14"><?= htmlspecialchars($reply) ?></textarea>
      </div>
      <div class="form-actions">
        <a href="index.php" class="btn-cancel">キャンセル</a>
        <button type="submit" class="btn-submit">📨 この内容で送信</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
