<?php
require_once __DIR__.'/auth.php';
require_login();
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']??'');
        $pass = $_POST['password']??'';
        $display = trim($_POST['display_name']??'');
        $role = $_POST['role']??'reception';
        if ($username && $pass && $display) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            try {
                $pdo->prepare("INSERT INTO users (username,password_hash,display_name,role,email) VALUES (?,?,?,?,?)")
                    ->execute([$username,$hash,$display,$role,$_POST['email']??null]);
                flash('ユーザーを追加しました。');
            } catch(Exception $e) {
                flash('ユーザー名が重複しています。','error');
            }
        }
    } elseif (isset($_POST['toggle_active'])) {
        $uid = (int)$_POST['user_id'];
        if ($uid !== $_SESSION['user_id']) {
            $pdo->prepare("UPDATE users SET is_active=1-is_active WHERE id=?")->execute([$uid]);
            flash('更新しました。');
        }
    } elseif (isset($_POST['change_password'])) {
        $uid = (int)$_POST['user_id'];
        $newpass = $_POST['new_password']??'';
        if ($newpass && strlen($newpass)>=6) {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($newpass,PASSWORD_DEFAULT),$uid]);
            flash('パスワードを変更しました。');
        }
    }
    header('Location: '.BASE_URL.'/users.php');
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY role,display_name")->fetchAll();
include __DIR__.'/header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h4 class="mb-0"><i class="bi bi-shield-lock"></i> ユーザー管理</h4>
</div>

<div class="row g-3">
<div class="col-md-8">
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead><tr><th>ユーザー名</th><th>表示名</th><th>権限</th><th>メール</th><th>状態</th><th></th></tr></thead>
        <tbody>
        <?php foreach($users as $u): ?>
        <tr class="<?= !$u['is_active']?'table-secondary':'' ?>">
          <td class="small"><?= h($u['username']) ?></td>
          <td class="small"><?= h($u['display_name']) ?></td>
          <td><span class="badge bg-primary"><?= role_label($u['role']) ?></span></td>
          <td class="small"><?= h($u['email']??'-') ?></td>
          <td><span class="badge <?= $u['is_active']?'bg-success':'bg-secondary' ?>"><?= $u['is_active']?'有効':'無効' ?></span></td>
          <td>
            <?php if($u['id']!==$_SESSION['user_id']): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button name="toggle_active" class="btn btn-outline-secondary btn-sm py-0"><?= $u['is_active']?'無効化':'有効化' ?></button>
            </form>
            <?php endif; ?>
            <button class="btn btn-outline-warning btn-sm py-0" onclick="document.getElementById('pw_<?= $u['id'] ?>').style.display='block'"><i class="bi bi-key"></i></button>
            <div id="pw_<?= $u['id'] ?>" style="display:none;margin-top:4px">
              <form method="post" class="d-flex gap-1">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="password" name="new_password" class="form-control form-control-sm" placeholder="新パスワード(6文字以上)" style="width:180px">
                <button name="change_password" class="btn btn-warning btn-sm">変更</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="col-md-4">
  <div class="card">
    <div class="card-header fw-bold">ユーザー追加</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="mb-2">
          <label class="form-label small fw-bold">ユーザー名</label>
          <input type="text" name="username" class="form-control form-control-sm" required>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">パスワード</label>
          <input type="password" name="password" class="form-control form-control-sm" required minlength="6">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-bold">表示名</label>
          <input type="text" name="display_name" class="form-control form-control-sm" required>
        </div>
        <div class="mb-2">
          <label class="form-label small">メール</label>
          <input type="email" name="email" class="form-control form-control-sm">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">権限</label>
          <select name="role" class="form-select form-select-sm">
            <option value="reception">受付</option>
            <option value="officer">役員</option>
            <option value="secretary">事務</option>
            <option value="pastor">牧師</option>
            <option value="admin">管理者</option>
          </select>
        </div>
        <button name="add_user" class="btn btn-primary w-100 btn-sm">追加</button>
      </form>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-header fw-bold small">権限一覧</div>
    <div class="card-body small">
      <table class="table table-sm table-borderless mb-0" style="font-size:11px">
        <tr><td><span class="badge bg-danger">管理者</span></td><td>全機能</td></tr>
        <tr><td><span class="badge bg-purple" style="background:#7b2d8b">牧師</span></td><td>牧会メモ含む全閲覧・編集</td></tr>
        <tr><td><span class="badge bg-primary">事務</span></td><td>基本情報・出席・問い合わせ</td></tr>
        <tr><td><span class="badge bg-info text-dark">役員</span></td><td>担当者分のみ・フォロー入力</td></tr>
        <tr><td><span class="badge bg-secondary">受付</span></td><td>簡易登録・出席入力のみ</td></tr>
      </table>
    </div>
  </div>
</div>
</div>
<?php include __DIR__.'/footer.php'; ?>
