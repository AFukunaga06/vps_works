<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/db.php';

$page    = max(1, (int)($_GET['page'] ?? 1));
$total   = count_threads();
$pages   = max(1, (int)ceil($total / THREADS_PER_PAGE));
$page    = min($page, $pages);
$threads = get_threads($page, THREADS_PER_PAGE);

$flash_ok  = $_SESSION['flash_ok']  ?? '';
$flash_err = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

$old = $_SESSION['post_old'] ?? [];
unset($_SESSION['post_old']);

$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $h(SITE_NAME) ?></title>
<link rel="stylesheet" href="css/bbs.css">
</head>
<body>

<header class="site-header">
  <div class="inner">
    <h1><a href="index.php"><?= $h(SITE_NAME) ?></a></h1>
  </div>
</header>

<div class="container">

  <?php if ($flash_ok): ?>
    <div class="flash flash-ok"><?= $h($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="flash flash-err"><?= $h($flash_err) ?></div>
  <?php endif; ?>

  <!-- 新規スレッド投稿フォーム -->
  <div class="card">
    <div class="card-title">新しいスレッドを立てる</div>
    <form action="post.php" method="post" novalidate>
      <?= csrf_field() ?>
      <div class="honeypot" aria-hidden="true">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off">
      </div>

      <div class="form-group">
        <label for="title">タイトル<span class="required-mark">*</span></label>
        <input type="text" id="title" name="title"
               value="<?= $h($old['title'] ?? '') ?>"
               maxlength="<?= MAX_TITLE_LEN ?>" required>
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
        <button type="submit" class="btn btn-primary">スレッドを立てる</button>
      </div>
    </form>
  </div>

  <!-- スレッド一覧 -->
  <?php if (empty($threads)): ?>
    <div class="empty-msg"><p>まだスレッドがありません。最初の投稿をどうぞ。</p></div>
  <?php else: ?>
    <div class="thread-list">
      <?php foreach ($threads as $t): ?>
      <div class="thread-item">
        <div class="thread-main">
          <a class="thread-title" href="thread.php?id=<?= (int)$t['id'] ?>">
            <?= $h($t['title']) ?>
          </a>
          <div class="thread-meta">
            <?= $h($t['author'] ?: '名無し') ?> &nbsp;·&nbsp;
            <?= $h(substr($t['created_at'], 0, 16)) ?>
          </div>
        </div>
        <span class="thread-badge"><?= (int)$t['reply_count'] ?> 件</span>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>">‹ 前</a>
      <?php endif; ?>
      <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
        <?php if ($i === $page): ?>
          <span class="current"><?= $i ?></span>
        <?php else: ?>
          <a href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $pages): ?>
        <a href="?page=<?= $page + 1 ?>">次 ›</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>

</div>
</body>
</html>
