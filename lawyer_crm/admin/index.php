<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$lc_user    = lc_require_admin();
$page_title = 'スタッフ管理';
$page_nav   = 'admin';

$tid = lc_current_tenant_id();
$errors  = [];
$success = '';

// ユーザー数の上限チェック用
$quota = lc_check_quota('users');

// 新規追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $role  = $_POST['role'] ?? 'staff';

    if (!$name)  $errors[] = '氏名は必須です。';
    if (!$email) $errors[] = 'メールアドレスは必須です。';
    if (strlen($pass) < 8) $errors[] = 'パスワードは8文字以上で入力してください。';

    if (!$quota['ok']) {
        $errors[] = "ユーザー数が上限（{$quota['limit']}名）に達しています。プランをアップグレードしてください。";
    }

    if (empty($errors)) {
        try {
            get_db()->prepare(
                "INSERT INTO lc_users (tenant_id, name, email, password_hash, role) VALUES (?,?,?,?,?)"
            )->execute([$tid, $name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
            $success = 'スタッフを追加しました。';
            lc_audit_log('create_user', 'user', null, ['email' => $email, 'role' => $role]);
        } catch (PDOException $e) {
            $errors[] = 'このメールアドレスは既に使用されています。';
        }
    }
}

// パスワード変更
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'change_pass') {
    $uid  = (int)$_POST['uid'];
    $pass = $_POST['new_password'] ?? '';
    if (strlen($pass) < 8) {
        $errors[] = 'パスワードは8文字以上で入力してください。';
    } else {
        get_db()->prepare("UPDATE lc_users SET password_hash=? WHERE id=? AND tenant_id=?")
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $uid, $tid]);
        $success = 'パスワードを変更しました。';
        lc_audit_log('change_password', 'user', $uid);
    }
}

// 有効/無効切替
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'toggle_active') {
    $uid = (int)$_POST['uid'];
    if ($uid !== $lc_user['id']) {
        get_db()->prepare("UPDATE lc_users SET is_active = 1 - is_active WHERE id=? AND tenant_id=?")
            ->execute([$uid, $tid]);
        lc_audit_log('toggle_user', 'user', $uid);
    }
    header('Location: ' . LC_BASE_URL . '/admin/index.php'); exit;
}

$users_stmt = get_db()->prepare("SELECT * FROM lc_users WHERE tenant_id=? ORDER BY role, name");
$users_stmt->execute([$tid]);
$users = $users_stmt->fetchAll();

// 再計算（追加後の利用枠表示用）
$quota = lc_check_quota('users');
?>
<?php require __DIR__ . '/../includes/_header.php'; ?>

<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-3">
  <!-- スタッフ一覧 -->
  <div class="col-md-7">
    <div class="page-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">スタッフ一覧</h2>
        <span class="badge bg-light text-dark">
          利用 <?= $quota['used'] ?> / <?= $quota['limit'] ?? '∞' ?>名
        </span>
      </div>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>氏名</th><th>メール</th><th>ロール</th><th>状態</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr class="<?= !$u['is_active'] ? 'text-muted' : '' ?>">
          <td><?= h($u['name']) ?> <?= $u['id'] === $lc_user['id'] ? '<span class="badge bg-primary">自分</span>' : '' ?></td>
          <td><?= h($u['email']) ?></td>
          <td><span class="badge bg-secondary"><?= h(ROLE_MAP[$u['role']] ?? $u['role']) ?></span></td>
          <td><?= $u['is_active'] ? '<span class="badge bg-success">有効</span>' : '<span class="badge bg-secondary">無効</span>' ?></td>
          <td>
            <?php if ($u['id'] !== $lc_user['id']): ?>
            <form method="post" class="d-inline">
              <input type="hidden" name="_action" value="toggle_active">
              <input type="hidden" name="uid" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-sm <?= $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?> py-0 px-2">
                <?= $u['is_active'] ? '無効化' : '有効化' ?>
              </button>
            </form>
            <?php endif; ?>
            <button class="btn btn-xs btn-sm btn-outline-secondary py-0 px-2"
                    data-bs-toggle="modal" data-bs-target="#pwModal" data-uid="<?= $u['id'] ?>" data-name="<?= h($u['name']) ?>">
              PW変更
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 新規追加フォーム -->
  <div class="col-md-5">
    <div class="page-card">
      <h2 class="h6 fw-bold mb-3">スタッフ追加</h2>
      <?php if (!$quota['ok']): ?>
        <div class="alert alert-warning small">
          ユーザー数が上限（<?= $quota['limit'] ?>名）に達しています。<br>
          <a href="<?= LC_BASE_URL ?>/billing/portal.php" class="alert-link">プランをアップグレード</a>してください。
        </div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="_action" value="add">
        <div class="mb-2">
          <label class="form-label small">氏名 <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control form-control-sm" required>
        </div>
        <div class="mb-2">
          <label class="form-label small">メールアドレス <span class="text-danger">*</span></label>
          <input type="email" name="email" class="form-control form-control-sm" required>
        </div>
        <div class="mb-2">
          <label class="form-label small">パスワード（8文字以上）<span class="text-danger">*</span></label>
          <input type="password" name="password" class="form-control form-control-sm" required minlength="8">
        </div>
        <div class="mb-3">
          <label class="form-label small">ロール</label>
          <select name="role" class="form-select form-select-sm">
            <?php foreach (ROLE_MAP as $k => $v): ?>
            <option value="<?= $k ?>"><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-sm text-white" style="background:#1a3a5c" <?= !$quota['ok'] ? 'disabled' : '' ?>>追加</button>
      </form>
    </div>
  </div>
</div>

<!-- パスワード変更モーダル -->
<div class="modal fade" id="pwModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title h6">パスワード変更</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="_action" value="change_pass">
        <input type="hidden" name="uid" id="pw_uid">
        <div class="modal-body">
          <p class="small mb-2" id="pw_name"></p>
          <input type="password" name="new_password" class="form-control" placeholder="新しいパスワード（8文字以上）" required minlength="8">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-sm btn-primary">変更</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('pwModal').addEventListener('show.bs.modal', e => {
    const btn = e.relatedTarget;
    document.getElementById('pw_uid').value  = btn.dataset.uid;
    document.getElementById('pw_name').textContent = btn.dataset.name + ' のパスワードを変更します。';
});
</script>

<?php require __DIR__ . '/../includes/_footer.php'; ?>
