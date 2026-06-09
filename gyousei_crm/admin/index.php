<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$gc_user    = gc_require_admin();
$page_title = 'スタッフ管理';
$page_nav   = 'admin';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    if ($act === 'add_user') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = $_POST['role'] ?? 'staff';
        if ($name && $email && strlen($pass) >= 6) {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            try {
                get_db()->prepare("INSERT INTO gc_users (name,kana,email,password_hash,role) VALUES (?,?,?,?,?)")
                        ->execute([$name, $_POST['kana']??'', $email, $hash, $role]);
                $msg = 'ユーザーを追加しました。';
            } catch (Exception $e) {
                $msg = 'メールアドレスが既に使われています。';
            }
        } else {
            $msg = '入力内容を確認してください（パスワードは6文字以上）。';
        }
    } elseif ($act === 'toggle_user') {
        $uid = (int)$_POST['user_id'];
        if ($uid !== $gc_user['id']) {
            get_db()->prepare("UPDATE gc_users SET is_active = 1 - is_active WHERE id=?")->execute([$uid]);
        }
        $msg = '変更しました。';
    } elseif ($act === 'change_pass') {
        $uid  = (int)$_POST['user_id'];
        $pass = $_POST['new_pass'] ?? '';
        if (strlen($pass) >= 6) {
            get_db()->prepare("UPDATE gc_users SET password_hash=? WHERE id=?")->execute([password_hash($pass, PASSWORD_BCRYPT), $uid]);
            $msg = 'パスワードを変更しました。';
        } else {
            $msg = 'パスワードは6文字以上で入力してください。';
        }
    }
    header('Location: '.GC_BASE_URL.'/admin/index.php?msg='.urlencode($msg)); exit;
}

$msg  = $_GET['msg'] ?? '';
$users = get_all_users();
?>
<?php require __DIR__ . '/../includes/_header.php'; ?>

<?php if ($msg): ?>
<div class="alert alert-info"><?= h($msg) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-8">
    <div class="page-card">
      <h2 class="h6 fw-bold mb-3">スタッフ一覧</h2>
      <table class="table align-middle">
        <thead class="table-light">
          <tr><th>氏名</th><th>メール</th><th>ロール</th><th>状態</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= h($u['name']) ?><div class="text-muted small"><?= h($u['kana']) ?></div></td>
          <td class="small"><?= h($u['email']) ?></td>
          <td><span class="badge bg-secondary"><?= h(ROLE_MAP[$u['role']] ?? '') ?></span></td>
          <td><?= $u['is_active'] ? '<span class="badge bg-success">有効</span>' : '<span class="badge bg-secondary">無効</span>' ?></td>
          <td class="d-flex gap-1 flex-wrap">
            <?php if ($u['id'] !== $gc_user['id']): ?>
            <form method="post" class="d-inline">
              <input type="hidden" name="_action" value="toggle_user">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button class="btn btn-sm <?= $u['is_active']?'btn-outline-danger':'btn-outline-success' ?> py-0 px-2">
                <?= $u['is_active']?'無効化':'有効化' ?>
              </button>
            </form>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-secondary py-0 px-2" data-bs-toggle="modal" data-bs-target="#passModal<?= $u['id'] ?>">
              PW変更
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-md-4">
    <div class="page-card">
      <h2 class="h6 fw-bold mb-3">新規ユーザー追加</h2>
      <form method="post">
        <input type="hidden" name="_action" value="add_user">
        <div class="mb-2">
          <label class="form-label small fw-bold">氏名 *</label>
          <input type="text" name="name" class="form-control form-control-sm" required>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">読み</label>
          <input type="text" name="kana" class="form-control form-control-sm">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">メールアドレス *</label>
          <input type="email" name="email" class="form-control form-control-sm" required>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">パスワード（6文字以上）*</label>
          <input type="password" name="password" class="form-control form-control-sm" required minlength="6">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">ロール</label>
          <select name="role" class="form-select form-select-sm">
            <?php foreach (ROLE_MAP as $k => $v): ?>
            <option value="<?= $k ?>"><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-sm btn-gc w-100">追加</button>
      </form>
    </div>
  </div>
</div>

<!-- PW変更モーダル -->
<?php foreach ($users as $u): ?>
<div class="modal fade" id="passModal<?= $u['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">PW変更：<?= h($u['name']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="_action" value="change_pass">
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <label class="form-label small fw-bold">新しいパスワード（6文字以上）</label>
          <input type="password" name="new_pass" class="form-control" required minlength="6">
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-gc btn-sm">変更</button></div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/_footer.php'; ?>
