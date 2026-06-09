<?php
require_once __DIR__ . '/config.php';

$db = get_db();

// 一覧取得（最新20件）
$stmt = $db->query("SELECT id, topic, submitter, status, iterations, created_at, completed_at FROM research_requests ORDER BY created_at DESC LIMIT 50");
$requests = $stmt->fetchAll();

function status_badge(string $status): string {
    $map = [
        'pending'    => ['pending',    '待機中'],
        'processing' => ['processing', '処理中'],
        'completed'  => ['completed',  '完了'],
        'failed'     => ['failed',     'エラー'],
    ];
    [$cls, $label] = $map[$status] ?? ['pending', $status];
    return "<span class='badge badge-{$cls}'>{$label}</span>";
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_NAME ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <h1><?= APP_NAME ?></h1>
  <nav>
    <a href="index.php">一覧</a>
    <a href="register.php">新規登録</a>
  </nav>
</header>

<div class="container">
  <div class="card">
    <h2>調査依頼一覧</h2>
    <?php if (empty($requests)): ?>
      <p>まだ登録がありません。<a href="register.php">新規登録</a>してください。</p>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>調査テーマ</th>
          <th>投稿者</th>
          <th>状態</th>
          <th>試行</th>
          <th>登録日時</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= htmlspecialchars(mb_strimwidth($r['topic'], 0, 60, '…')) ?></td>
          <td>
            <?php if ($r['submitter']): ?>
              <?= htmlspecialchars($r['submitter']) ?>
            <?php else: ?>
              <span class="anon-label">匿名</span>
            <?php endif; ?>
          </td>
          <td><?= status_badge($r['status']) ?></td>
          <td><?= $r['iterations'] ?></td>
          <td><?= $r['created_at'] ?></td>
          <td><a href="view.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm">詳細</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div style="text-align:right">
    <a href="register.php" class="btn btn-primary">+ 新規調査を登録</a>
  </div>
</div>
</body>
</html>
