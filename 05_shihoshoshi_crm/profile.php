<?php
require_once 'config.php';
require_login();

$page_title = 'アカウント設定';
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm']  ?? '';
    $current_pass = $_POST['current_password'] ?? '';

    // 現在のパスワード確認
    $stmt = get_db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current_pass, $user['password_hash'])) {
        $error = '現在のパスワードが正しくありません。';
    } elseif ($new_username === '') {
        $error = 'ユーザー名を入力してください。';
    } elseif ($new_password !== '' && $new_password !== $confirm) {
        $error = '新しいパスワードと確認用パスワードが一致しません。';
    } elseif ($new_password !== '' && mb_strlen($new_password) < 8) {
        $error = 'パスワードは8文字以上で設定してください。';
    } else {
        // ユーザー名の重複チェック（自分以外）
        $chk = get_db()->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $chk->execute([$new_username, $_SESSION['user_id']]);
        if ($chk->fetch()) {
            $error = 'そのユーザー名はすでに使用されています。';
        } else {
            if ($new_password !== '') {
                $hash = password_hash($new_password, PASSWORD_BCRYPT);
                $upd  = get_db()->prepare('UPDATE users SET username = ?, password_hash = ? WHERE id = ?');
                $upd->execute([$new_username, $hash, $_SESSION['user_id']]);
            } else {
                $upd = get_db()->prepare('UPDATE users SET username = ? WHERE id = ?');
                $upd->execute([$new_username, $_SESSION['user_id']]);
            }
            $success = 'アカウント情報を更新しました。';
        }
    }
}

// 現在のユーザー情報取得
$stmt = get_db()->prepare('SELECT username, name, role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

require 'includes/header.php';
?>
<div class="container-fluid" style="max-width:600px">
  <div class="card shadow-sm">
    <div class="card-header bg-white fw-bold py-3">
      <i class="bi bi-person-gear me-2 text-primary"></i>アカウント設定
    </div>
    <div class="card-body p-4">
      <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?= h($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label fw-semibold">表示名</label>
          <input type="text" class="form-control" value="<?= h($current_user['name']) ?>" disabled>
          <div class="form-text">表示名の変更はシステム管理者にお問い合わせください。</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">ユーザー名 <span class="text-danger">*</span></label>
          <input type="text" name="username" class="form-control"
                 value="<?= h($current_user['username']) ?>" required>
        </div>
        <hr>
        <p class="text-muted small mb-3">パスワードを変更する場合のみ入力してください。変更しない場合は空欄のままにしてください。</p>
        <div class="mb-3">
          <label class="form-label fw-semibold">新しいパスワード</label>
          <input type="password" name="password" class="form-control" placeholder="8文字以上">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">新しいパスワード（確認）</label>
          <input type="password" name="confirm" class="form-control">
        </div>
        <hr>
        <div class="mb-4">
          <label class="form-label fw-semibold">現在のパスワード <span class="text-danger">*</span></label>
          <input type="password" name="current_password" class="form-control" required>
          <div class="form-text">変更を保存するには現在のパスワードが必要です。</div>
        </div>
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-save me-1"></i>保存する
        </button>
      </form>
    </div>
  </div>
</div>
<?php require 'includes/footer.php'; ?>
