<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

// フィルター
$filter_page   = trim($_GET['page']   ?? '');
$filter_action = trim($_GET['action'] ?? '');
$filter_date   = trim($_GET['date']   ?? '');
$filter_limit  = max(50, min(500, (int)($_GET['limit'] ?? 100)));

// ページ一覧・アクション一覧
$pages   = $pdo->query("SELECT DISTINCT page   FROM operation_logs ORDER BY page")->fetchAll(PDO::FETCH_COLUMN);
$actions = $pdo->query("SELECT DISTINCT action FROM operation_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// ログ件数
$where = '1=1';
$params = [];
if ($filter_page)   { $where .= ' AND page=?';              $params[] = $filter_page;   }
if ($filter_action) { $where .= ' AND action=?';            $params[] = $filter_action; }
if ($filter_date)   { $where .= ' AND DATE(created_at)=?';  $params[] = $filter_date;   }

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM operation_logs WHERE $where");
$total_stmt->execute($params);
$total = (int)$total_stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT id, user_name, action, page, detail, ip_address, created_at
     FROM operation_logs WHERE $where ORDER BY id DESC LIMIT ?"
);
$stmt->execute(array_merge($params, [$filter_limit]));
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>操作ログ - 杉田教会名簿</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #f0f4f8; color: #333; font-size: 14px; }
header { background: #2c5f8a; color: #fff; padding: 12px 20px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
header h1 { font-size: 1.1rem; }
nav a { color: #cde; text-decoration: none; font-size: .85rem; margin-left: 14px; }
nav a:hover { color: #fff; }
.container { max-width: 1000px; margin: 16px auto; padding: 0 14px; }
.filter-bar { background: #fff; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px;
              box-shadow: 0 1px 4px rgba(0,0,0,.1); display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
.filter-bar label { font-size: .82rem; color: #555; display: flex; flex-direction: column; gap: 3px; }
.filter-bar select, .filter-bar input { padding: 6px 9px; border: 1px solid #ccc; border-radius: 4px; font-size: .88rem; }
.btn-filter { background: #2c5f8a; color: #fff; border: none; padding: 7px 16px; border-radius: 4px; cursor: pointer; font-size: .88rem; }
.btn-filter:hover { background: #1a4a70; }
.btn-reset { background: #777; color: #fff; border: none; padding: 7px 12px; border-radius: 4px; cursor: pointer; font-size: .85rem; text-decoration: none; display: inline-block; line-height: 1.6; }
.total-info { font-size: .85rem; color: #555; margin-bottom: 10px; }
.log-table { width: 100%; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
.log-table table { width: 100%; border-collapse: collapse; }
.log-table th { background: #2c5f8a; color: #fff; padding: 9px 12px; text-align: left; font-size: .83rem; }
.log-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; font-size: .83rem; vertical-align: top; }
.log-table tr:hover td { background: #f0f7ff; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: bold; white-space: nowrap; }
.badge-save     { background: #e3f2fd; color: #1565c0; }
.badge-confirm  { background: #e8f5e9; color: #2e7d32; }
.badge-unconfirm{ background: #fff3e0; color: #e65100; }
.badge-member   { background: #fce4ec; color: #c62828; }
.badge-backup   { background: #f3e5f5; color: #6a1b9a; }
.badge-restore  { background: #ffebee; color: #b71c1c; }
.badge-default  { background: #eceff1; color: #546e7a; }
.empty { text-align: center; padding: 40px; color: #999; }
@media (max-width: 600px) {
  .log-table td:nth-child(5), .log-table th:nth-child(5) { display: none; }
}
</style>
</head>
<body>
<header>
  <h1>操作ログ</h1>
  <nav>
    <a href="main02.php">メニュー</a>
    <a href="members.php">会員管理</a>
    <a href="report.php">レポート</a>
    <a href="backup.php">バックアップ</a>
    <a href="logout.php" style="margin-left:auto;color:#ffd0d0;">ログアウト</a>
  </nav>
</header>

<div class="container">
  <!-- フィルター -->
  <form method="get" class="filter-bar">
    <label>ページ
      <select name="page">
        <option value="">すべて</option>
        <?php foreach ($pages as $p): ?>
          <option value="<?= htmlspecialchars($p) ?>" <?= $filter_page === $p ? 'selected' : '' ?>>
            <?= htmlspecialchars($p) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>操作種別
      <select name="action">
        <option value="">すべて</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= htmlspecialchars($a) ?>" <?= $filter_action === $a ? 'selected' : '' ?>>
            <?= htmlspecialchars($a) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>日付
      <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
    </label>
    <label>表示件数
      <select name="limit">
        <?php foreach ([50,100,200,500] as $lv): ?>
          <option value="<?= $lv ?>" <?= $filter_limit === $lv ? 'selected' : '' ?>><?= $lv ?>件</option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="btn-filter">絞り込み</button>
    <a href="operation_log.php" class="btn-reset">リセット</a>
  </form>

  <div class="total-info">
    全 <?= number_format($total) ?> 件中、最新 <?= count($logs) ?> 件を表示
  </div>

  <div class="log-table">
    <table>
      <thead>
        <tr>
          <th style="width:140px">日時</th>
          <th style="width:90px">ユーザー</th>
          <th style="width:90px">操作</th>
          <th style="width:130px">ページ</th>
          <th>詳細</th>
          <th style="width:110px">IPアドレス</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="6" class="empty">ログがありません</td></tr>
      <?php else: ?>
        <?php foreach ($logs as $log): ?>
          <?php
          $action = $log['action'];
          $badgeClass = match(true) {
            $action === '一時保存'       => 'badge-save',
            $action === '確定保存'       => 'badge-confirm',
            $action === '確定解除'       => 'badge-unconfirm',
            str_contains($action, '会員')|| str_contains($action,'退会')||str_contains($action,'復会') => 'badge-member',
            $action === '手動バックアップ' => 'badge-backup',
            $action === 'DB復元'         => 'badge-restore',
            default                      => 'badge-default',
          };
          ?>
          <tr>
            <td><?= htmlspecialchars($log['created_at']) ?></td>
            <td><?= htmlspecialchars($log['user_name']) ?></td>
            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($action) ?></span></td>
            <td style="font-size:.78rem;color:#555"><?= htmlspecialchars($log['page']) ?></td>
            <td><?= htmlspecialchars($log['detail']) ?></td>
            <td style="font-size:.78rem;color:#888"><?= htmlspecialchars($log['ip_address']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
