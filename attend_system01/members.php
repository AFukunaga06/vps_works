<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$msg = '';
$msg_type = 'ok';

// 会員追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name  = trim($_POST['name']  ?? '');
    $kana  = trim($_POST['kana']  ?? '');
    $group = $_POST['group_name'] ?? '男性';
    if ($name !== '') {
        $pdo->prepare("INSERT INTO members (name, kana, group_name) VALUES (?, ?, ?)")
            ->execute([$name, $kana, $group]);
        $msg = "「{$name}」を追加しました。";
    } else {
        $msg = "氏名を入力してください。"; $msg_type = 'err';
    }
}

// 退会
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deactivate') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE members SET is_active=0 WHERE id=?")->execute([$id]);
        $msg = "退会処理を行いました。";
    }
}

// 復会
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'activate') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE members SET is_active=1 WHERE id=?")->execute([$id]);
        $msg = "復会処理を行いました。";
    }
}

$show_inactive = isset($_GET['show_inactive']);

$members = $pdo->query(
    "SELECT id, name, kana, group_name, is_active FROM members ORDER BY group_name, kana, id"
)->fetchAll();

$men   = array_filter($members, fn($m) => $m['group_name'] === '男性');
$women = array_filter($members, fn($m) => $m['group_name'] === '女性');

$men_active   = count(array_filter($men,   fn($m) => $m['is_active']));
$women_active = count(array_filter($women, fn($m) => $m['is_active']));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>つばさ名簿 - 会員管理</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #f0f4f8; color: #333; font-size: 15px; }
header { background: #2c5f8a; color: #fff; padding: 12px 20px; display: flex; align-items: center; gap: 10px; }
header h1 { font-size: 1.1rem; }
nav a { color: #cde; text-decoration: none; font-size: .85rem; margin-left: 14px; }
nav a:hover { color: #fff; }
.container { max-width: 860px; margin: 16px auto; padding: 0 14px; }
.msg-ok  { padding: 10px 14px; background: #e8f5e9; color: #2e7d32; border-radius: 6px; margin-bottom: 14px; }
.msg-err { padding: 10px 14px; background: #ffebee; color: #c62828; border-radius: 6px; margin-bottom: 14px; }

/* 追加フォーム */
.add-forms { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
@media (max-width: 560px) { .add-forms { grid-template-columns: 1fr; } }
.add-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
.add-card-header { padding: 10px 14px; font-weight: bold; font-size: .9rem; }
.add-men   .add-card-header { background: #d0e8ff; color: #1a5c99; }
.add-women .add-card-header { background: #ffd0e8; color: #99195c; }
.add-card-body { padding: 12px 14px; display: flex; flex-direction: column; gap: 8px; }
.add-card-body input { padding: 7px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: .9rem; width: 100%; }
.btn-add-men   { background: #2c5f8a; color: #fff; border: none; padding: 8px; border-radius: 4px; cursor: pointer; font-size: .9rem; width: 100%; }
.btn-add-women { background: #99195c; color: #fff; border: none; padding: 8px; border-radius: 4px; cursor: pointer; font-size: .9rem; width: 100%; }
.btn-add-men:hover   { background: #1a4a70; }
.btn-add-women:hover { background: #7a1249; }

/* 名簿テーブル */
.list-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 560px) { .list-columns { grid-template-columns: 1fr; } }
.list-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
.list-card-header { padding: 10px 14px; font-weight: bold; font-size: .9rem; display: flex; justify-content: space-between; align-items: center; }
.list-men   .list-card-header { background: #d0e8ff; color: #1a5c99; }
.list-women .list-card-header { background: #ffd0e8; color: #99195c; }
.list-card-header .count { font-size: .8rem; font-weight: normal; }
.toggle-link { font-size: .78rem; color: inherit; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 8px 12px; text-align: left; font-size: .88rem; border-bottom: 1px solid #f0f0f0; }
thead tr { background: #f5f8fc; }
tbody tr.inactive { opacity: .5; }
tbody tr:hover { background: #f8fbff; }
.btn-sm { padding: 3px 10px; font-size: .8rem; border: none; border-radius: 4px; cursor: pointer; }
.btn-danger  { background: #e53935; color: #fff; }
.btn-success { background: #43a047; color: #fff; }
</style>
</head>
<body>
<header>
  <h1>つばさ名簿</h1>
  <nav>
    <a href="index.php">出欠入力</a>
    <a href="members.php">会員管理</a>
    <a href="report.php">レポート</a>
    <a href="timecard_report.php?mode=monthly">月計</a>
    <a href="timecard_report.php?mode=yearly">年計</a>
    <a href="logout.php" style="margin-left:auto;color:#ffd0d0;">ログアウト</a>
  </nav>
</header>

<div class="container">
  <?php if ($msg): ?>
    <div class="msg-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- 追加フォーム（男性・女性） -->
  <div class="add-forms">
    <div class="add-card add-men">
      <div class="add-card-header">男性を追加</div>
      <form method="post">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="group_name" value="男性">
        <div class="add-card-body">
          <input type="text" name="name" required maxlength="50" placeholder="氏名（必須）">
          <input type="text" name="kana" maxlength="50" placeholder="フリガナ（任意）">
          <button class="btn-add-men" type="submit">＋ 男性を追加</button>
        </div>
      </form>
    </div>

    <div class="add-card add-women">
      <div class="add-card-header">女性を追加</div>
      <form method="post">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="group_name" value="女性">
        <div class="add-card-body">
          <input type="text" name="name" required maxlength="50" placeholder="氏名（必須）">
          <input type="text" name="kana" maxlength="50" placeholder="フリガナ（任意）">
          <button class="btn-add-women" type="submit">＋ 女性を追加</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 名簿一覧（男性・女性） -->
  <div class="list-columns">
    <?php foreach ([['men', '男性', $men, $men_active], ['women', '女性', $women, $women_active]] as [$cls, $label, $list, $active_cnt]): ?>
    <div class="list-card list-<?= $cls ?>">
      <div class="list-card-header">
        <span><?= $label ?></span>
        <span class="count">
          在籍 <?= $active_cnt ?> 名 &nbsp;
          <a class="toggle-link" href="?<?= $show_inactive ? '' : 'show_inactive=1' ?>">
            <?= $show_inactive ? '退会者を隠す' : '退会者も表示' ?>
          </a>
        </span>
      </div>
      <table>
        <thead><tr><th>#</th><th>氏名</th><th>フリガナ</th><th></th></tr></thead>
        <tbody>
        <?php
        $i = 0;
        foreach ($list as $m):
          if (!$m['is_active'] && !$show_inactive) continue;
          $i++;
        ?>
          <tr class="<?= $m['is_active'] ? '' : 'inactive' ?>">
            <td><?= $i ?></td>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td style="font-size:.82rem;color:#777"><?= htmlspecialchars($m['kana']) ?></td>
            <td>
              <?php if ($m['is_active']): ?>
                <form method="post" style="display:inline"
                      onsubmit="return confirm('「<?= htmlspecialchars($m['name']) ?>」を退会にしますか？')">
                  <input type="hidden" name="action" value="deactivate">
                  <input type="hidden" name="id" value="<?= $m['id'] ?>">
                  <button class="btn-sm btn-danger" type="submit">退会</button>
                </form>
              <?php else: ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="activate">
                  <input type="hidden" name="id" value="<?= $m['id'] ?>">
                  <button class="btn-sm btn-success" type="submit">復会</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
