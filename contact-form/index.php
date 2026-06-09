<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/csrf.php';

// 確認画面からの「戻る」で入力値を復元
$old    = $_SESSION['form_data'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);

?>

<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>お問い合わせ｜<?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="css/form.css">
<?php if (RECAPTCHA_ENABLED && RECAPTCHA_SITE_KEY !== ''): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
</head>
<body>
<div class="form-wrap">
  <h1 class="form-title">お問い合わせ</h1>
  <p class="form-lead"><span class="required-mark">*</span> は必須項目です。</p>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <p>入力内容にエラーがあります。ご確認ください。</p>
  </div>
  <?php endif; ?>

  <form action="confirm.php" method="post" id="contact-form" novalidate>
    <?= csrf_field() ?>
    <!-- ハニーポット（スパムBot対策：人間には見えない） -->
    <div class="honeypot" aria-hidden="true">
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off">
    </div>

    <?php foreach (FORM_FIELDS as $field):
      $name  = $field['name'];
      $label = $field['label'];
      $type  = $field['type'];
      $req   = $field['required'] ?? false;
      $max   = $field['max'] ?? '';
      $val   = htmlspecialchars($old[$name] ?? '', ENT_QUOTES, 'UTF-8');
      $err   = $errors[$name] ?? '';
    ?>
    <div class="form-group<?= $err ? ' has-error' : '' ?>">
      <label for="<?= $name ?>">
        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        <?php if ($req): ?><span class="required-mark">*</span><?php endif; ?>
      </label>

      <?php if ($type === 'textarea'): ?>
        <textarea id="<?= $name ?>" name="<?= $name ?>"
          rows="6"<?= $max ? ' maxlength="' . $max . '"' : '' ?>
          <?= $req ? 'required' : '' ?>><?= $val ?></textarea>

      <?php elseif ($type === 'select'): ?>
        <select id="<?= $name ?>" name="<?= $name ?>" <?= $req ? 'required' : '' ?>>
          <option value="">--- 選択してください ---</option>
          <?php foreach ($field['options'] as $opt): ?>
          <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"
            <?= ($old[$name] ?? '') === $opt ? 'selected' : '' ?>>
            <?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>
          </option>
          <?php endforeach; ?>
        </select>

      <?php elseif ($type === 'radio'): ?>
        <div class="radio-group">
        <?php foreach ($field['options'] as $opt): ?>
          <label class="radio-label">
            <input type="radio" name="<?= $name ?>"
              value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"
              <?= ($old[$name] ?? '') === $opt ? 'checked' : '' ?>
              <?= $req ? 'required' : '' ?>>
            <?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>
          </label>
        <?php endforeach; ?>
        </div>

      <?php elseif ($type === 'checkbox'): ?>
        <div class="checkbox-group">
        <?php
          $checked_vals = isset($old[$name]) ? (array)$old[$name] : [];
          foreach ($field['options'] as $opt):
        ?>
          <label class="checkbox-label">
            <input type="checkbox" name="<?= $name ?>[]"
              value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"
              <?= in_array($opt, $checked_vals, true) ? 'checked' : '' ?>>
            <?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>
          </label>
        <?php endforeach; ?>
        </div>

      <?php else: ?>
        <input type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
          id="<?= $name ?>" name="<?= $name ?>"
          value="<?= $val ?>"
          <?= $max ? 'maxlength="' . $max . '"' : '' ?>
          <?= $req ? 'required' : '' ?>>
      <?php endif; ?>

      <?php if ($err): ?>
        <p class="error-msg"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (RECAPTCHA_ENABLED && RECAPTCHA_SITE_KEY !== ''): ?>
    <input type="hidden" name="recaptcha_token" id="recaptcha_token">
    <?php endif; ?>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">確認画面へ</button>
    </div>
  </form>
</div>

<?php if (RECAPTCHA_ENABLED && RECAPTCHA_SITE_KEY !== ''): ?>
<script>
document.getElementById('contact-form').addEventListener('submit', function(e) {
  e.preventDefault();
  const form = this;
  grecaptcha.ready(function() {
    grecaptcha.execute('<?= htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8') ?>', {action: 'submit'}).then(function(token) {
      document.getElementById('recaptcha_token').value = token;
      form.submit();
    });
  });
});
</script>
<?php endif; ?>
</body>
</html>
