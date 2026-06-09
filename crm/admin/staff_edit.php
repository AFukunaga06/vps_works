<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$me = crm_require_admin();

$id = (int)($_GET['id'] ?? 0);
$s  = $id ? get_staff($id) : null;
if ($id && !$s) { header('Location: ' . CRM_BASE_URL . '/admin/index.php'); exit; }

$page_title = $s ? 'スタッフ編集' : 'スタッフ追加';
$errors = [];
$old = $s ?? ['name'=>'','username'=>'','email'=>'','role'=>'staff','is_active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'name'      => trim($_POST['name'] ?? ''),
        'username'  => trim($_POST['username'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'role'      => $_POST['role'] ?? 'staff',
        'is_active' => (int)isset($_POST['is_active']),
    ];
    $pass     = $_POST['password'] ?? '';
    $pass2    = $_POST['password2'] ?? '';

    if (!$old['name']) $errors['name'] = '氏名は必須です。';
    if (!$old['username'] || !preg_match('/^[a-zA-Z0-9_]+$/', $old['username']))
        $errors['username'] = 'ユーザーIDは半角英数字・アンダースコアで入力してください。';
    if ($old['email'] && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = '正しいメールアドレスを入力してください。';
    if (!$id && !$pass) $errors['password'] = '新規追加時はパスワードが必要です。';
    if ($pass && $pass !== $pass2) $errors['password'] = 'パスワードが一致しません。';
    if ($pass && strlen($pass) < 8) $errors['password'] = 'パスワードは8文字以上にしてください。';

    if (empty($errors)) {
        $db = get_db();
        if ($id) {
            $sql = "UPDATE crm_staff SET name=?,username=?,email=?,role=?,is_active=?" . ($pass ? ",password_hash=?" : "") . " WHERE id=?";
            $params = [$old['name'], $old['username'], $old['email'], $old['role'], $old['is_active']];
            if ($pass) $params[] = password_hash($pass, PASSWORD_DEFAULT);
            $params[] = $id;
        } else {
            $sql = "INSERT INTO crm_staff (name,username,email,role,is_active,password_hash) VALUES (?,?,?,?,?,?)";
            $params = [$old['name'], $old['username'], $old['email'], $old['role'], $old['is_active'], password_hash($pass, PASSWORD_DEFAULT)];
        }
        $db->prepare($sql)->execute($params);
        header('Location: ' . CRM_BASE_URL . '/admin/index.php');
        exit;
    }
}

include __DIR__ . '/../includes/_header.php';
?>

<div style="max-width:500px">
  <?php if ($errors): ?><div class="alert alert-danger">入力内容を確認してください。</div><?php endif; ?>

  <form method="post" class="card shadow-sm">
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label fw-bold">氏名 <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
               value="<?= h($old['name']) ?>">
        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= h($errors['name']) ?></div><?php endif; ?>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold">ユーザーID <span class="text-danger">*</span></label>
        <input type="text" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
               value="<?= h($old['username']) ?>" placeholder="例：tanaka（半角英数字・アンダースコア）">
        <?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?= h($errors['username']) ?></div><?php endif; ?>
      </div>
      <div class="mb-3">
        <label class="form-label">メールアドレス</label>
        <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
               value="<?= h($old['email']) ?>">
        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= h($errors['email']) ?></div><?php endif; ?>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold">パスワード <?= $s ? '（変更する場合のみ入力）' : '<span class="text-danger">*</span>' ?></label>
        <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
               placeholder="8文字以上">
        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= h($errors['password']) ?></div><?php endif; ?>
      </div>
      <div class="mb-3">
        <label class="form-label">パスワード（確認）</label>
        <input type="password" name="password2" class="form-control">
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold">権限</label>
        <select name="role" class="form-select">
          <option value="staff" <?= ($old['role'] ?? '') === 'staff' ? 'selected' : '' ?>>スタッフ</option>
          <option value="admin" <?= ($old['role'] ?? '') === 'admin' ? 'selected' : '' ?>>管理者</option>
        </select>
      </div>
      <div class="mb-3 form-check">
        <input type="checkbox" name="is_active" id="is_active" class="form-check-input"
               <?= ($old['is_active'] ?? 1) ? 'checked' : '' ?>>
        <label class="form-check-label" for="is_active">有効（ログイン可能）</label>
      </div>
    </div>
    <div class="card-footer d-flex gap-2">
      <button type="submit" class="btn text-white" style="background:#3a7d5c"><?= $s ? '更新' : '追加' ?></button>
      <a href="<?= CRM_BASE_URL ?>/admin/index.php" class="btn btn-outline-secondary">キャンセル</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/_footer.php'; ?>
