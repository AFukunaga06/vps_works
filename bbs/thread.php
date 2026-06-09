<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/db.php';

$id     = (int)($_GET['id'] ?? 0);
$thread = $id > 0 ? get_thread($id) : null;

if ($thread === null) {
    header('Location: index.php');
    exit;
}

$replies = get_replies($id);

$flash_ok  = $_SESSION['flash_ok']  ?? '';
$flash_err = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

$old = $_SESSION['reply_old'] ?? [];
unset($_SESSION['reply_old']);

$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $h($thread['title']) ?> | <?= $h(SITE_NAME) ?></title>
<link rel="stylesheet" href="css/bbs.css">
</head>
<body>

<header class="site-header">
  <div class="inner">
    <h1><a href="index.php"><?= $h(SITE_NAME) ?></a></h1>
  </div>
</header>

<div class="container">

  <p style="margin-bottom:14px;font-size:0.85rem;">
    <a href="index.php">← スレッド一覧へ</a>
  </p>

  <?php if ($flash_ok): ?>
    <div class="flash flash-ok"><?= $h($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="flash flash-err"><?= $h($flash_err) ?></div>
  <?php endif; ?>

  <!-- スレッド本文 -->
  <div class="thread-header">
    <h2><?= $h($thread['title']) ?></h2>
    <div class="post-meta">
      <?php if (!empty($thread['email'])): ?>
        <a href="mailto:<?= $h($thread['email']) ?>"><?= $h($thread['author'] ?: '名無し') ?></a>
      <?php else: ?>
        <?= $h($thread['author'] ?: '名無し') ?>
      <?php endif; ?>
      &nbsp;·&nbsp;
      <?= $h(substr($thread['created_at'], 0, 16)) ?>
    </div>
    <div class="thread-body"><?= $h($thread['body']) ?></div>
  </div>

  <!-- 返信一覧 -->
  <?php if (!empty($replies)): ?>
  <div class="reply-list">
    <?php foreach ($replies as $i => $r): ?>
    <div class="reply-item">
      <div class="reply-num">
        #<?= $i + 1 ?> &nbsp;
        <?php if (!empty($r['email'])): ?>
          <a href="mailto:<?= $h($r['email']) ?>"><?= $h($r['author'] ?: '名無し') ?></a>
        <?php else: ?>
          <?= $h($r['author'] ?: '名無し') ?>
        <?php endif; ?>
        &nbsp;·&nbsp;
        <?= $h(substr($r['created_at'], 0, 16)) ?>
      </div>
      <div class="reply-body"><?= $h($r['body']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- 返信フォーム -->
  <div class="card">
    <div class="card-title">返信する</div>
    <form action="reply.php" method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="thread_id" value="<?= $id ?>">
      <div class="honeypot" aria-hidden="true">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off">
      </div>

      <div class="form-group">
        <label for="author">名前（省略可）</label>
        <input type="text" id="author" name="author"
               value="<?= $h($old['author'] ?? '') ?>"
               maxlength="<?= MAX_AUTHOR_LEN ?>" placeholder="名無し">
      </div>

      <div class="form-group">
        <label for="email">メールアドレス（省略可）</label>
        <input type="email" id="email" name="email"
               value="<?= $h($old['email'] ?? '') ?>"
               maxlength="<?= MAX_EMAIL_LEN ?>" placeholder="example@mail.com">
      </div>

      <div class="form-group">
        <label for="body">本文<span class="required-mark">*</span></label>
        <textarea id="body" name="body"
                  maxlength="<?= MAX_BODY_LEN ?>" required><?= $h($old['body'] ?? '') ?></textarea>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">返信する</button>
      </div>
    </form>
  </div>

</div>
</body>
</html>
