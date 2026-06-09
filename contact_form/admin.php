<?php
session_start();
require_once __DIR__ . '/config.php';

if (isset($_POST['logout'])) { session_destroy(); header('Location: admin.php'); exit; }
if (isset($_POST['pass'])) {
    if ($_POST['pass'] === ADMIN_PASS) $_SESSION['cf_admin'] = true;
    else $login_error = 'パスワードが違います';
}
$logged_in = !empty($_SESSION['cf_admin']);

if ($logged_in) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    } catch (Exception $e) {
        die('DB接続エラー: ' . htmlspecialchars($e->getMessage()));
    }

    // 既読/未読切り替え
    if (isset($_POST['toggle_read'])) {
        $id = (int)$_POST['toggle_read'];
        $cur = (int)$pdo->query("SELECT is_read FROM inquiries WHERE id={$id}")->fetchColumn();
        $pdo->prepare("UPDATE inquiries SET is_read=? WHERE id=?")->execute([1-$cur, $id]);
        header('Location: admin.php?filter=' . ($_GET['filter'] ?? 'all')); exit;
    }

    // 削除
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $pdo->prepare("DELETE FROM inquiries WHERE id=?")->execute([$id]);
        header('Location: admin.php?filter=' . ($_GET['filter'] ?? 'all')); exit;
    }

    // CSV出力
    if (isset($_GET['csv'])) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="inquiries_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM
        fputcsv($out, ['ID','お名前','メール','電話','種別','内容','既読','受信日時']);
        $rows = $pdo->query("SELECT * FROM inquiries ORDER BY id DESC")->fetchAll();
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'], $r['name'], $r['email'], $r['phone'],
                $r['subject'], $r['message'],
                $r['is_read'] ? '既読' : '未読',
                $r['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    // 詳細表示
    $detail = null;
    if (isset($_GET['id'])) {
        $detail = $pdo->prepare("SELECT * FROM inquiries WHERE id=?")->execute([(int)$_GET['id']]) ? null : null;
        $stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id=?");
        $stmt->execute([(int)$_GET['id']]);
        $detail = $stmt->fetch();
        if ($detail && !$detail['is_read']) {
            $pdo->prepare("UPDATE inquiries SET is_read=1 WHERE id=?")->execute([$detail['id']]);
            $detail['is_read'] = 1;
        }
    }

    $filter = $_GET['filter'] ?? 'all';
    $where  = $filter === 'unread' ? 'WHERE is_read=0' : ($filter === 'read' ? 'WHERE is_read=1' : '');
    $total_all    = (int)$pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
    $total_unread = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE is_read=0")->fetchColumn();

    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $total_filtered = (int)$pdo->query("SELECT COUNT(*) FROM inquiries {$where}")->fetchColumn();
    $total_pages = max(1, ceil($total_filtered / $limit));

    $inquiries = $pdo->query(
        "SELECT id, name, email, subject, is_read, created_at FROM inquiries {$where}
         ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}"
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>お問い合わせ管理</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body { background:#f4f6fb; font-family:'Hiragino Kaku Gothic ProN','Yu Gothic',sans-serif; }
.navbar { background:linear-gradient(135deg,#1a2744 0%,#2a4a8a 100%); }
.card-main { background:#fff; border-radius:.75rem; border:1px solid #e0e6f0;
             box-shadow:0 2px 8px rgba(0,0,0,.06); }
.badge-unread { background:#e8f0ff; color:#1a2744; font-weight:700; }
.badge-read   { background:#f0f0f0; color:#888; }
.row-unread { background:#f8f9ff !important; font-weight:600; }
.table th { background:#f0f4ff; color:#1a2744; font-size:.82rem; }
.table td { font-size:.83rem; vertical-align:middle; }
</style>
</head>
<body>
<nav class="navbar navbar-dark px-4 py-2 mb-4">
  <span class="navbar-brand fw-bold"><i class="bi bi-envelope-fill me-2"></i>お問い合わせ管理</span>
  <?php if ($logged_in): ?>
  <div class="d-flex align-items-center gap-3">
    <a href="?csv=1" class="btn btn-sm btn-outline-light"><i class="bi bi-download me-1"></i>CSV</a>
    <form method="post" class="mb-0">
      <button name="logout" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>ログアウト</button>
    </form>
  </div>
  <?php endif; ?>
</nav>

<div class="container" style="max-width:1100px">
<?php if (!$logged_in): ?>
<div class="row justify-content-center mt-5">
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h5 class="mb-3 fw-bold">管理者ログイン</h5>
        <?php if (!empty($login_error)): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label small">パスワード</label>
            <input type="password" name="pass" class="form-control" autofocus required>
          </div>
          <button class="btn btn-primary w-100">ログイン</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php elseif ($detail): ?>
<!-- 詳細画面 -->
<div class="mb-3">
  <a href="admin.php?filter=<?= htmlspecialchars($filter) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>一覧に戻る
  </a>
</div>
<div class="card-main p-4 mb-4">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <h5 class="fw-bold mb-0"><?= htmlspecialchars($detail['subject']) ?></h5>
    <span class="badge <?= $detail['is_read'] ? 'badge-read' : 'badge-unread' ?>">
      <?= $detail['is_read'] ? '既読' : '未読' ?>
    </span>
  </div>
  <table class="table table-sm table-bordered mb-3">
    <tr><th width="120">受信日時</th><td><?= htmlspecialchars($detail['created_at']) ?></td></tr>
    <tr><th>お名前</th><td><?= htmlspecialchars($detail['name']) ?></td></tr>
    <tr><th>メール</th><td><a href="mailto:<?= htmlspecialchars($detail['email']) ?>"><?= htmlspecialchars($detail['email']) ?></a></td></tr>
    <tr><th>電話</th><td><?= htmlspecialchars($detail['phone'] ?: 'なし') ?></td></tr>
  </table>
  <div class="bg-light rounded p-3" style="white-space:pre-wrap;font-size:.9rem"><?= htmlspecialchars($detail['message']) ?></div>
  <div class="mt-3 d-flex gap-2">
    <form method="post">
      <input type="hidden" name="toggle_read" value="<?= $detail['id'] ?>">
      <button class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-<?= $detail['is_read'] ? 'envelope' : 'envelope-open' ?> me-1"></i>
        <?= $detail['is_read'] ? '未読に戻す' : '既読にする' ?>
      </button>
    </form>
    <form method="post" onsubmit="return confirm('削除しますか？')">
      <input type="hidden" name="delete_id" value="<?= $detail['id'] ?>">
      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>削除</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- 一覧画面 -->
<div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
  <div class="d-flex gap-2">
    <a href="?filter=all"    class="btn btn-sm <?= $filter==='all'    ? 'btn-primary' : 'btn-outline-secondary' ?>">全件 (<?= $total_all ?>)</a>
    <a href="?filter=unread" class="btn btn-sm <?= $filter==='unread' ? 'btn-primary' : 'btn-outline-secondary' ?>">
      未読 <?php if ($total_unread > 0): ?><span class="badge bg-danger ms-1"><?= $total_unread ?></span><?php endif; ?>
    </a>
    <a href="?filter=read"   class="btn btn-sm <?= $filter==='read'   ? 'btn-primary' : 'btn-outline-secondary' ?>">既読</a>
  </div>
</div>

<div class="card-main">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th width="40">#</th>
          <th>お名前</th>
          <th>種別</th>
          <th>受信日時</th>
          <th width="80">状態</th>
          <th width="100">操作</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($inquiries as $inq): ?>
      <tr class="<?= !$inq['is_read'] ? 'row-unread' : '' ?>">
        <td><?= $inq['id'] ?></td>
        <td>
          <a href="?id=<?= $inq['id'] ?>&filter=<?= $filter ?>" class="text-decoration-none text-dark">
            <?= htmlspecialchars($inq['name']) ?>
          </a>
          <div class="text-muted small"><?= htmlspecialchars($inq['email']) ?></div>
        </td>
        <td><?= htmlspecialchars($inq['subject']) ?></td>
        <td class="text-nowrap"><?= date('m/d H:i', strtotime($inq['created_at'])) ?></td>
        <td>
          <span class="badge <?= $inq['is_read'] ? 'badge-read' : 'badge-unread' ?>">
            <?= $inq['is_read'] ? '既読' : '未読' ?>
          </span>
        </td>
        <td>
          <div class="d-flex gap-1">
            <form method="post">
              <input type="hidden" name="toggle_read" value="<?= $inq['id'] ?>">
              <button class="btn btn-outline-secondary btn-sm py-0 px-1" title="<?= $inq['is_read'] ? '未読に戻す' : '既読にする' ?>">
                <i class="bi bi-<?= $inq['is_read'] ? 'envelope' : 'envelope-check' ?>"></i>
              </button>
            </form>
            <form method="post" onsubmit="return confirm('削除しますか？')">
              <input type="hidden" name="delete_id" value="<?= $inq['id'] ?>">
              <button class="btn btn-outline-danger btn-sm py-0 px-1" title="削除">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($inquiries)): ?>
      <tr><td colspan="6" class="text-center text-muted py-4">お問い合わせはありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ページネーション -->
<?php if ($total_pages > 1): ?>
<nav class="mt-3">
  <ul class="pagination pagination-sm justify-content-center">
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
      <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $p ?>"><?= $p ?></a>
    </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
