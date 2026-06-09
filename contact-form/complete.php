<?php
session_start();
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>送信完了｜<?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="css/form.css">
</head>
<body>
<div class="form-wrap complete-wrap">
  <div class="complete-icon">✓</div>
  <h1 class="form-title">送信が完了しました</h1>
  <p class="form-lead">
    お問い合わせいただきありがとうございます。<br>
    ご入力いただいたメールアドレス宛に自動返信メールをお送りしました。<br>
    担当者より折り返しご連絡いたします。
  </p>
  <div class="form-actions">
    <a href="index.php" class="btn btn-back">トップページへ戻る</a>
  </div>
</div>
</body>
</html>
