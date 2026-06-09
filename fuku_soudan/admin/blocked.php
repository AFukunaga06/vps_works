<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$db = get_db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_date']) && $_POST['add_date']) {
        $date   = $_POST['add_date'];
        $reason = trim($_POST['reason'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                $db->prepare("INSERT IGNORE INTO blocked_dates (blocked_date, reason) VALUES (?,?)")
                   ->execute([$date, $reason]);
                $msg = '休業日を追加しました。';
            } catch (Exception $e) {
                $msg = 'エラー：' . h($e->getMessage());
            }
        }
    }
    if (isset($_POST['del_id'])) {
        $db->prepare("DELETE FROM blocked_dates WHERE id=?")->execute([(int)$_POST['del_id']]);
        $msg = '削除しました。';
    }
    header('Location: blocked.php?msg=' . urlencode($msg));
    exit;
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

$blocked = $db->query("SELECT * FROM blocked_dates ORDER BY blocked_date")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> - 管理：休業日設定</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
body { background: #f8f9fa; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.admin-nav { background: #2c5f44; }
.admin-nav .navbar-brand, .admin-nav .nav-link { color: #fff !important; }
</style>
</head>
<body>
<nav class="navbar admin-nav mb-4">
  <div class="container">
    <span class="navbar-brand"><?= SITE_NAME ?> 管理</span>
    <div class="d-flex gap-3">
      <a href="index.php" class="nav-link">予約一覧</a>
      <a href="../logout.php" class="nav-link">ログアウト</a>
    </div>
  </div>
</nav>

<div class="container pb-5" style="max-width: 640px;">
  <h2 class="h5 mb-3">休業日設定</h2>
  <p class="text-muted small">追加した日は予約カレンダーで「休業日」として表示されます（日曜は自動で定休）。</p>

  <?php if ($msg): ?>
  <div class="alert alert-success py-2 small"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- 追加フォーム -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h3 class="h6 mb-3">休業日を追加</h3>
      <form method="post" class="row g-2 align-items-end">
        <div class="col-auto">
          <label class="form-label small">日付</label>
          <input type="date" name="add_date" class="form-control" required
                 min="<?= date('Y-m-d') ?>">
        </div>
        <div class="col">
          <label class="form-label small">理由（任意）</label>
          <input type="text" name="reason" class="form-control" placeholder="例：祝日、研修">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn text-white" style="background:#3a7d5c">追加</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 一覧 -->
  <h3 class="h6 mb-2">設定済みの休業日</h3>
  <?php if (empty($blocked)): ?>
  <p class="text-muted small">設定済みの休業日はありません。</p>
  <?php else: ?>
  <table class="table table-bordered bg-white shadow-sm table-sm">
    <thead class="table-light">
      <tr><th>日付</th><th>曜日</th><th>理由</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($blocked as $b): ?>
    <?php $dow = ['日','月','火','水','木','金','土'][(int)date('w', strtotime($b['blocked_date']))]; ?>
    <tr>
      <td><?= date('Y年n月j日', strtotime($b['blocked_date'])) ?></td>
      <td><?= $dow ?></td>
      <td><?= h($b['reason'] ?? '') ?></td>
      <td>
        <form method="post" style="margin:0">
          <input type="hidden" name="del_id" value="<?= $b['id'] ?>">
          <button type="submit" class="btn btn-outline-danger btn-sm py-0"
                  onclick="return confirm('削除しますか？')">削除</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
</body>
</html>
