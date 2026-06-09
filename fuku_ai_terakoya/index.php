<?php
require_once __DIR__ . '/lib/db.php';
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h(APP_NAME) ?></title>
<style>
  body{font-family:"Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif;
       background:#faf8f5;color:#333;max-width:760px;margin:0 auto;padding:40px 24px;line-height:1.7}
  h1{color:#4a7c59;border-bottom:2px solid #d4a574;padding-bottom:8px}
  .links a{display:inline-block;margin:6px 12px 6px 0;color:#3d6649;text-decoration:none;
           border:1px solid #4a7c59;padding:8px 14px;border-radius:8px}
  .links a:hover{background:#4a7c59;color:#fff}
  footer{margin-top:48px;color:#888;font-size:.9em}
</style>
</head>
<body>
<h1><?= h(APP_NAME) ?></h1>
<p>受講者・講師の予約と応募を管理しています。</p>
<div class="links">
  <a href="/terakoya_03/">受講LP</a>
  <a href="/terakoya_01/">受講者募集</a>
  <a href="/fuku_ai_terakoya_bosyuu_01.html">講師募集要項</a>
  <a href="<?= h(APP_ROOT_URL) ?>/payments/plans.php">受講料お支払い</a>
  <a href="<?= h(APP_ROOT_URL) ?>/admin/">管理画面</a>
</div>
<footer>&copy; <?= date('Y') ?> <?= h(APP_NAME) ?></footer>
</body>
</html>
