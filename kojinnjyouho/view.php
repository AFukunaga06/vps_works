<?php
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$db = get_db();

$stmt = $db->prepare("SELECT * FROM research_requests WHERE id = ?");
$stmt->execute([$id]);
$req = $stmt->fetch();

if (!$req) {
    echo '見つかりません。<a href="index.php">一覧へ</a>';
    exit;
}

// ログ取得
$stmt2 = $db->prepare("SELECT * FROM research_log WHERE request_id = ? ORDER BY iteration ASC");
$stmt2->execute([$id]);
$logs = $stmt2->fetchAll();

function status_label(string $status): string {
    return match($status) {
        'pending'    => '待機中',
        'processing' => '処理中',
        'completed'  => '完了',
        'failed'     => 'エラー',
        default      => $status,
    };
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>調査詳細 #<?= $id ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="style.css">
<?php if ($req['status'] === 'pending' || $req['status'] === 'processing'): ?>
<meta http-equiv="refresh" content="10"><!-- 処理中は10秒ごとに自動更新 -->
<?php endif; ?>
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
    <h2>調査 #<?= $id ?> — <?= htmlspecialchars(mb_strimwidth($req['topic'], 0, 80, '…')) ?></h2>

    <table style="width:auto; margin-bottom:16px">
      <tr><th>投稿者</th><td><?= $req['submitter'] ? htmlspecialchars($req['submitter']) : '<span class="anon-label">匿名</span>' ?></td></tr>
      <tr><th>状態</th><td><span class="badge badge-<?= $req['status'] ?>"><?= status_label($req['status']) ?></span></td></tr>
      <tr><th>試行回数</th><td><?= $req['iterations'] ?></td></tr>
      <tr><th>登録日時</th><td><?= $req['created_at'] ?></td></tr>
      <?php if ($req['completed_at']): ?>
      <tr><th>完了日時</th><td><?= $req['completed_at'] ?></td></tr>
      <?php endif; ?>
    </table>

    <?php if ($req['detail']): ?>
    <p><strong>補足：</strong><?= nl2br(htmlspecialchars($req['detail'])) ?></p>
    <?php endif; ?>
  </div>

  <?php if ($req['status'] === 'pending' || $req['status'] === 'processing'): ?>
  <div class="card">
    <p>⏳ 調査ループが処理中です。このページは10秒ごとに自動更新されます。</p>
  </div>
  <?php endif; ?>

  <?php if ($req['result']): ?>
  <div class="card">
    <h2>調査結果</h2>
    <div class="result-box"><?= htmlspecialchars($req['result']) ?></div>
  </div>
  <?php endif; ?>

  <?php if (!empty($logs)): ?>
  <div class="card">
    <h2>実験ログ（<?= count($logs) ?> 回）</h2>
    <?php foreach ($logs as $log): ?>
    <div class="log-entry <?= $log['status'] === 'failed' ? 'failed' : '' ?>">
      <strong>Iteration <?= $log['iteration'] ?></strong>
      [<?= $log['status'] ?>] — <?= $log['logged_at'] ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <p><a href="index.php">← 一覧へ戻る</a></p>
</div>
</body>
</html>
