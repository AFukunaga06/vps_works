<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/crm_functions.php';
require_admin();

$id     = (int)($_GET['id'] ?? 0);
$client = crm_get_client($id);
if (!$client) { header('Location: '.CRM_URL.'/clients.php'); exit; }

$nav        = 'clients';
$page_title = $client['name'] . ' 様';
$cases      = crm_get_cases(['client_id' => $id]);
$activities = crm_get_activities(['client_id' => $id], 20);
?>
<?php require __DIR__ . '/includes/_header.php'; ?>
<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>保存しました。<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<div class="row g-3">
  <div class="col-md-4">
    <div class="page-card">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <div><h2 class="h5 fw-bold mb-0"><?= crm_h($client['name']) ?></h2>
          <div class="text-muted small"><?= crm_h($client['kana']) ?></div></div>
        <?= crm_client_badge($client['status']) ?>
      </div>
      <table class="table table-sm table-borderless mb-0">
        <tr><th class="text-muted" style="width:35%">電話</th><td><?= crm_h($client['tel']) ?><?= $client['tel2'] ? ' / '.crm_h($client['tel2']) : '' ?></td></tr>
        <tr><th class="text-muted">メール</th><td><?= $client['email'] ? '<a href="mailto:'.crm_h($client['email']).'">'.crm_h($client['email']).'</a>' : '' ?></td></tr>
        <tr><th class="text-muted">住所</th><td><?= crm_h($client['address']) ?></td></tr>
        <tr><th class="text-muted">生年月日</th><td><?= crm_fmt_date($client['birth_date']) ?></td></tr>
        <tr><th class="text-muted">職業</th><td><?= crm_h($client['occupation']) ?></td></tr>
        <tr><th class="text-muted">登録日</th><td><?= crm_fmt_date($client['created_at']) ?></td></tr>
      </table>
      <?php if ($client['memo']): ?>
      <hr><div class="small text-muted">メモ</div>
      <div class="small" style="white-space:pre-wrap"><?= crm_h($client['memo']) ?></div>
      <?php endif; ?>
      <div class="d-flex gap-2 mt-3">
        <a href="<?= CRM_URL ?>/client_edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">編集</a>
        <a href="<?= CRM_URL ?>/case_edit.php?client_id=<?= $id ?>" class="btn btn-sm text-white" style="background:var(--g)">案件追加</a>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="page-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-bold mb-0"><i class="bi bi-folder2-open me-1"></i>案件 (<?= count($cases['rows']) ?>)</h3>
        <a href="<?= CRM_URL ?>/case_edit.php?client_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">+ 追加</a>
      </div>
      <?php if (empty($cases['rows'])): ?>
      <p class="text-muted small mb-0">案件がありません。</p>
      <?php else: ?>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>案件名</th><th>種別</th><th>状態</th><th>開始日</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($cases['rows'] as $cs): ?>
        <tr>
          <td><a href="<?= CRM_URL ?>/case_view.php?id=<?= $cs['id'] ?>"><?= crm_h($cs['case_name']) ?></a></td>
          <td><span class="badge badge-type"><?= crm_h(CASE_TYPE_MAP[$cs['case_type']] ?? '') ?></span></td>
          <td><?= crm_case_badge($cs['status']) ?></td>
          <td><?= crm_fmt_date($cs['opened_date']) ?></td>
          <td><a href="<?= CRM_URL ?>/case_view.php?id=<?= $cs['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">詳細</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <div class="page-card">
      <h3 class="h6 fw-bold mb-3"><i class="bi bi-journal-text me-1"></i>活動記録</h3>
      <?php if (empty($activities)): ?>
      <p class="text-muted small mb-0">活動記録がありません。</p>
      <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($activities as $a): ?>
        <li class="list-group-item px-0 py-2">
          <div class="d-flex justify-content-between">
            <span class="badge badge-type"><?= crm_h(ACTIVITY_TYPE_MAP[$a['activity_type']] ?? '') ?></span>
            <small class="text-muted"><?= crm_fmt_dt($a['activity_at']) ?></small>
          </div>
          <?php if ($a['title']): ?><div class="fw-bold small mt-1"><?= crm_h($a['title']) ?></div><?php endif; ?>
          <?php if ($a['content']): ?><div class="small text-muted mt-1"><?= crm_h(mb_strimwidth($a['content'], 0, 80, '…')) ?></div><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/_footer.php'; ?>
