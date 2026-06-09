<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/validator.php';

// POSTでなければトップへ
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// CSRFチェック
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['form_errors'] = ['_global' => '不正なリクエストです。最初からやり直してください。'];
    header('Location: index.php');
    exit;
}

// ハニーポットチェック
if (!empty($_POST['website'])) {
    // Botと判断して静かに完了画面へ
    header('Location: complete.php');
    exit;
}

// バリデーション
$result = validate_fields($_POST, FORM_FIELDS);
$errors = $result['errors'];
$data   = $result['data'];

if (!empty($errors)) {
    $_SESSION['form_data']   = $data;
    $_SESSION['form_errors'] = $errors;
    header('Location: index.php');
    exit;
}

// 確認画面用にセッションへ保存
$_SESSION['form_data']    = $data;
$_SESSION['confirm_step'] = true;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>入力内容の確認｜<?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="css/form.css">
</head>
<body>
<div class="form-wrap">
  <h1 class="form-title">入力内容の確認</h1>
  <p class="form-lead">以下の内容で送信します。よろしければ「送信する」を押してください。</p>

  <table class="confirm-table">
    <?php foreach (FORM_FIELDS as $field):
      $name  = $field['name'];
      $label = $field['label'];
      $val   = $data[$name] ?? '';
      if (is_array($val)) $val = implode(', ', $val);
    ?>
    <tr>
      <th><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></th>
      <td><?= nl2br(htmlspecialchars($val, ENT_QUOTES, 'UTF-8')) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <form action="submit.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="confirmed" value="1">
    <div class="form-actions">
      <a href="index.php" class="btn btn-back" onclick="history.back(); return false;">◀ 戻る</a>
      <button type="submit" class="btn btn-primary">送信する</button>
    </div>
  </form>
</div>
</body>
</html>
