<?php
require_once dirname(__DIR__).'/auth.php';
require_login();

// Handle member add/remove
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    if (isset($_POST['add_member'])) {
        $pdo->prepare("INSERT IGNORE INTO group_members (group_id,person_id,joined_date) VALUES (?,?,?)")
            ->execute([$_POST['group_id'],$_POST['person_id'],date('Y-m-d')]);
        flash('メンバーを追加しました。');
    } elseif (isset($_POST['remove_member'])) {
        $pdo->prepare("UPDATE group_members SET left_date=? WHERE id=?")
            ->execute([date('Y-m-d'),$_POST['gm_id']]);
        flash('メンバーを削除しました。');
    } elseif (isset($_POST['add_group']) && is_admin()) {
        $pdo->prepare("INSERT INTO groups_tbl (name,description) VALUES (?,?)")
            ->execute([trim($_POST['group_name']),$_POST['group_desc']??null]);
        flash('グループを追加しました。');
    }
    header('Location: '.BASE_URL.'/groups/');
    exit;
}

$groups = $pdo->query("SELECT g.*, COUNT(gm.id) as member_count FROM groups_tbl g LEFT JOIN group_members gm ON g.id=gm.group_id AND gm.left_date IS NULL WHERE g.is_active=1 GROUP BY g.id ORDER BY g.name")->fetchAll();
$active_group = (int)($_GET['group_id'] ?? ($groups[0]['id'] ?? 0));

$members = [];
$non_members = [];
if ($active_group) {
    $members = $pdo->query("SELECT gm.*, p.last_name, p.first_name FROM group_members gm JOIN persons p ON gm.person_id=p.id WHERE gm.group_id=$active_group AND gm.left_date IS NULL ORDER BY p.last_name")->fetchAll();
    $member_ids = array_column($members,'person_id');
    $non_members = $pdo->query("SELECT id,last_name,first_name FROM persons WHERE status NOT IN ('ended') ORDER BY last_name")->fetchAll();
    $non_members = array_filter($non_members, fn($p)=>!in_array($p['id'],$member_ids));
}

include dirname(__DIR__).'/header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h4 class="mb-0"><i class="bi bi-diagram-3"></i> グループ管理</h4>
</div>

<div class="row g-3">
<div class="col-md-3">
  <div class="list-group mb-3">
    <?php foreach($groups as $g): ?>
    <a href="?group_id=<?= $g['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between <?= $active_group===$g['id']?'active':'' ?>">
      <span><?= h($g['name']) ?></span>
      <span class="badge bg-secondary"><?= $g['member_count'] ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if(is_admin()): ?>
  <div class="card">
    <div class="card-header small fw-bold">グループ追加</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="text" name="group_name" class="form-control form-control-sm mb-2" placeholder="グループ名" required>
        <textarea name="group_desc" class="form-control form-control-sm mb-2" rows="2" placeholder="説明"></textarea>
        <button name="add_group" class="btn btn-sm btn-primary w-100">追加</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="col-md-9">
  <?php if($active_group): ?>
  <?php $g=array_filter($groups,fn($x)=>$x['id']===$active_group); $g=reset($g); ?>
  <h5><?= h($g['name']??'') ?> のメンバー</h5>

  <div class="card mb-3">
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead><tr><th>氏名</th><th>参加日</th><th></th></tr></thead>
        <tbody>
        <?php if(empty($members)): ?><tr><td colspan="3" class="text-center text-muted py-3">メンバーなし</td></tr><?php endif; ?>
        <?php foreach($members as $m): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/persons/view.php?id=<?= $m['person_id'] ?>" class="small"><?= h($m['last_name'].$m['first_name']) ?></a></td>
          <td class="small"><?= h($m['joined_date']) ?></td>
          <td>
            <?php if(can_edit_persons()): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="gm_id" value="<?= $m['id'] ?>">
              <button name="remove_member" class="btn btn-outline-danger btn-sm py-0" onclick="return confirm('削除しますか？')"><i class="bi bi-x"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if(can_edit_persons() && !empty($non_members)): ?>
  <div class="card">
    <div class="card-header small fw-bold">メンバーを追加</div>
    <div class="card-body">
      <form method="post" class="d-flex gap-2">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="group_id" value="<?= $active_group ?>">
        <select name="person_id" class="form-select form-select-sm">
          <?php foreach($non_members as $p): ?>
          <option value="<?= $p['id'] ?>"><?= h($p['last_name'].$p['first_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button name="add_member" class="btn btn-primary btn-sm">追加</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>
</div>
<?php include dirname(__DIR__).'/footer.php'; ?>
