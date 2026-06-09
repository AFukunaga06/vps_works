<?php
require_once dirname(__DIR__).'/config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM contact_requests WHERE id=?");
$stmt->execute([$id]);
$req = $stmt->fetch();
if (!$req) {
    header('Location: index.php');
    exit;
}

$time_ranges = [
    '午前中' => '9:30 〜 12:00',
    '午後'   => '13:00 〜 17:00',
    '夜'     => '18:00 〜 20:00',
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>送信完了 - 教会牧会支援</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root { --church-blue: #1e3a5f; }
body { background: #f0f4f8; font-family: 'Hiragino Sans', 'Meiryo', sans-serif; }
.site-header { background: var(--church-blue); color: #fff; padding: 1rem 0; border-bottom: 4px solid #2c5f8a; }
.check-circle {
    width: 72px; height: 72px; border-radius: 50%;
    background: var(--church-blue);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem;
}
</style>
</head>
<body>

<header class="site-header">
  <div class="container">
    <h1 class="mb-0 h4"><i class="bi bi-church"></i> 教会牧会支援 — お問い合わせ</h1>
  </div>
</header>

<div class="container py-5" style="max-width: 640px;">
  <div class="text-center mb-4">
    <div class="check-circle">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" width="40" height="40">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
    </div>
    <h2 class="h4">お問い合わせを受け付けました</h2>
    <p class="text-muted">受付番号：<strong>#<?= str_pad($req['id'], 5, '0', STR_PAD_LEFT) ?></strong></p>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <table class="table table-bordered mb-0">
        <tr>
          <th style="width:35%;background:#e8f0f8">お名前</th>
          <td><?= htmlspecialchars($req['name']) ?> 様</td>
        </tr>
        <?php if ($req['email']): ?>
        <tr>
          <th style="background:#e8f0f8">メール</th>
          <td><?= htmlspecialchars($req['email']) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($req['tel']): ?>
        <tr>
          <th style="background:#e8f0f8">電話</th>
          <td><?= htmlspecialchars($req['tel']) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
          <th style="background:#e8f0f8">ご希望の連絡時間帯</th>
          <td>
            <strong><?= htmlspecialchars($req['preferred_time']) ?></strong>
            （<?= $time_ranges[$req['preferred_time']] ?? '' ?>）
          </td>
        </tr>
      </table>
    </div>
  </div>

  <div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    担当者よりご希望の時間帯にご連絡いたします。しばらくお待ちください。
  </div>

  <div class="text-center mt-4">
    <a href="index.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left"></i> お問い合わせフォームに戻る
    </a>
  </div>
</div>
</body>
</html>
