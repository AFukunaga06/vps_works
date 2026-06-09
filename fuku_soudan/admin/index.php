<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$db = get_db();

// ステータス変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id     = (int)$_POST['id'];
    $action = $_POST['action'];
    $valid  = ['confirmed','cancelled','completed','pending','pending_payment'];
    if (in_array($action, $valid, true)) {
        $db->prepare("UPDATE reservations SET status=? WHERE id=?")->execute([$action, $id]);
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// フィルター
$filter_status = $_GET['status'] ?? '';
$filter_type   = $_GET['type']   ?? '';
$filter_date   = $_GET['date']   ?? '';

$where  = ['1=1'];
$params = [];
if ($filter_status) { $where[] = 'status = ?';            $params[] = $filter_status; }
if ($filter_type)   { $where[] = 'consultation_type = ?'; $params[] = $filter_type; }
if ($filter_date)   { $where[] = 'reserve_date = ?';      $params[] = $filter_date; }

$sql = 'SELECT * FROM reservations WHERE ' . implode(' AND ', $where)
     . ' ORDER BY reserve_date, start_time, consultation_type';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?> - 管理：予約一覧</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
body { background: #f8f9fa; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.admin-nav { background: #2c5f44; }
.admin-nav .navbar-brand, .admin-nav .nav-link { color: #fff !important; }
.admin-nav .nav-link:hover { opacity: .8; }
th { white-space: nowrap; }
.type-general { color: #0d47a1; font-weight: bold; }
.type-legal   { color: #4a148c; font-weight: bold; }
</style>
</head>
<body>
<nav class="navbar admin-nav mb-4">
  <div class="container">
    <span class="navbar-brand"><?= SITE_NAME ?> 管理</span>
    <div class="d-flex gap-3 align-items-center">
      <a href="blocked.php" class="nav-link">休業日設定</a>
      <a href="../reserve/index.php" class="nav-link" target="_blank">予約ページ</a>
      <a href="../logout.php" class="nav-link">ログアウト</a>
    </div>
  </div>
</nav>

<div class="container pb-5">
  <h2 class="h5 mb-3">予約一覧</h2>

  <!-- フィルター -->
  <form method="get" class="row g-2 mb-4 align-items-end">
    <div class="col-auto">
      <label class="form-label small">ステータス</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">すべて</option>
        <?php foreach (['pending'=>'申込済み','pending_payment'=>'入金待ち','confirmed'=>'予約確定','completed'=>'完了','cancelled'=>'キャンセル'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $filter_status===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label small">種別</label>
      <select name="type" class="form-select form-select-sm">
        <option value="">すべて</option>
        <?php foreach (CONSULT_TYPES as $t): ?>
        <option value="<?= h($t) ?>" <?= $filter_type===$t?'selected':'' ?>><?= h($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label small">日付</label>
      <input type="date" name="date" class="form-control form-control-sm" value="<?= h($filter_date) ?>">
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-sm btn-outline-secondary">絞り込み</button>
      <a href="index.php" class="btn btn-sm btn-link">クリア</a>
    </div>
  </form>

  <!-- 件数 -->
  <p class="small text-muted mb-2"><?= count($rows) ?>件</p>

  <!-- 予約一覧テーブル -->
  <?php if (empty($rows)): ?>
  <p class="text-muted">該当する予約がありません。</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-bordered table-hover table-sm bg-white shadow-sm">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>種別</th>
          <th>日付</th>
          <th>時間</th>
          <th>お名前</th>
          <th>連絡先</th>
          <th>回数</th>
          <th>方法</th>
          <th>ステータス</th>
          <th>操作</th>
          <th>申込日時</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
      <?php
        $type_cls = ($r['consultation_type'] === '一般相談') ? 'type-general' : 'type-legal';
        $end = date('H:i', strtotime($r['start_time']) + 2700);
      ?>
      <tr>
        <td class="text-muted">#<?= str_pad($r['id'],5,'0',STR_PAD_LEFT) ?></td>
        <td class="<?= $type_cls ?>"><?= h($r['consultation_type']) ?></td>
        <td><?= date('n/j(D)', strtotime($r['reserve_date'])) ?></td>
        <td><?= date('H:i', strtotime($r['start_time'])) ?>〜<?= $end ?></td>
        <td>
          <?= h($r['name']) ?><br>
          <small class="text-muted"><?= h($r['kana']) ?></small>
        </td>
        <td>
          <small>
            <?= h($r['email']) ?><br>
            <?= h($r['tel']) ?>
          </small>
        </td>
        <td><?= $r['is_first'] ? '<span class="badge bg-warning text-dark">初回</span>' : '2回目以降' ?></td>
        <td><?= h($r['consult_method']) ?></td>
        <td><?= status_badge($r['status']) ?></td>
        <td>
          <form method="post" class="d-flex gap-1 flex-wrap">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <?php if ($r['status'] !== 'confirmed'): ?>
            <button name="action" value="confirmed" class="btn btn-success btn-sm py-0">確定</button>
            <?php endif; ?>
            <?php if ($r['status'] !== 'completed'): ?>
            <button name="action" value="completed" class="btn btn-primary btn-sm py-0">完了</button>
            <?php endif; ?>
            <?php if ($r['status'] !== 'cancelled'): ?>
            <button name="action" value="cancelled" class="btn btn-outline-danger btn-sm py-0"
                    onclick="return confirm('キャンセルしますか？')">キャンセル</button>
            <?php endif; ?>
          </form>
        </td>
        <td><small class="text-muted"><?= date('n/j H:i', strtotime($r['created_at'])) ?></small></td>
      </tr>
      <!-- 相談内容 行 -->
      <tr class="table-light">
        <td colspan="11" class="small text-muted py-1 ps-3">
          <strong>内容：</strong><?= h(mb_strimwidth($r['content'], 0, 120, '…')) ?>
          <?php if ($r['note']): ?>　<strong>備考：</strong><?= h($r['note']) ?><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
