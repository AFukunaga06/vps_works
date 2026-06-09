<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$staff = crm_require_login();

$type = $_GET['type'] ?? '';
if ($type === 'customers') { export_customers_csv(); exit; }
if ($type === 'consultations') { export_consultations_csv(); exit; }

$page_title = 'CSV出力';
include __DIR__ . '/includes/_header.php';
?>

<div style="max-width:500px">
  <div class="list-group shadow-sm">
    <a href="export.php?type=customers" class="list-group-item list-group-item-action">
      <div class="fw-bold">顧客一覧 CSV</div>
      <div class="text-muted small">氏名・連絡先・ステータス・タグ・メモ</div>
    </a>
    <a href="export.php?type=consultations" class="list-group-item list-group-item-action">
      <div class="fw-bold">相談履歴 CSV</div>
      <div class="text-muted small">全相談記録（初回・2回目以降の項目を含む）</div>
    </a>
  </div>
</div>

<?php include __DIR__ . '/includes/_footer.php'; ?>
