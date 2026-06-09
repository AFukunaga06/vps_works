<?php
require_once __DIR__ . '/../includes/auth.php';
lc_require_login();
?>
<!DOCTYPE html>
<html lang="ja"><head>
<meta charset="UTF-8">
<title>決済キャンセル - LegalDesk</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head><body class="bg-light">
<div class="container py-5" style="max-width:560px;">
  <div class="card border-0 shadow-sm">
    <div class="card-body text-center p-5">
      <i class="bi bi-x-circle-fill text-warning" style="font-size:4rem"></i>
      <h1 class="h4 fw-bold mt-3">決済はキャンセルされました</h1>
      <p class="text-muted">課金は発生していません。再度お試しになるか、ご不明点はお問合せください。</p>
      <a class="btn btn-primary" href="<?= LC_BASE_URL ?>/billing/portal.php">プラン選択に戻る</a>
    </div>
  </div>
</div>
</body></html>
