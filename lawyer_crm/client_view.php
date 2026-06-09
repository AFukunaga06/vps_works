<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$lc_user = lc_require_login();

$id = (int)($_GET['id'] ?? 0);
$client = get_client($id);
if (!$client) { header('Location: ' . LC_BASE_URL . '/clients.php'); exit; }

$page_title = h($client['name']) . ' 様';
$page_nav   = 'clients';

$cases = get_cases(['client_id' => $id]);
$activities = get_activities(['client_id' => $id]);
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>保存しました。<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-3">
  <!-- 基本情報 -->
  <div class="col-md-4">
    <div class="page-card">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
          <h2 class="h5 fw-bold mb-0"><?= h($client['name']) ?></h2>
          <div class="text-muted small"><?= h($client['kana']) ?></div>
        </div>
        <?= client_status_badge($client['status']) ?>
      </div>
      <table class="table table-sm table-borderless mb-0">
        <tr><th class="text-muted w-35">電話</th><td><?= h($client['tel']) ?><?= $client['tel2'] ? ' / ' . h($client['tel2']) : '' ?></td></tr>
        <tr><th class="text-muted">メール</th><td><?= $client['email'] ? '<a href="mailto:' . h($client['email']) . '">' . h($client['email']) . '</a>' : '' ?></td></tr>
        <tr><th class="text-muted">住所</th><td><?= h($client['address']) ?></td></tr>
        <tr><th class="text-muted">生年月日</th><td><?= fmt_date($client['birth_date']) ?></td></tr>
        <tr><th class="text-muted">職業</th><td><?= h($client['occupation']) ?></td></tr>
        <tr><th class="text-muted">担当</th><td><?= h($client['lawyer_name'] ?? '未割当') ?></td></tr>
        <tr><th class="text-muted">登録日</th><td><?= fmt_date($client['created_at']) ?></td></tr>
      </table>
      <?php if ($client['memo']): ?>
      <hr>
      <div class="small text-muted">メモ</div>
      <div class="small" style="white-space:pre-wrap"><?= h($client['memo']) ?></div>
      <?php endif; ?>
      <div class="d-flex gap-2 mt-3">
        <a href="<?= LC_BASE_URL ?>/client_edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">編集</a>
        <a href="<?= LC_BASE_URL ?>/case_edit.php?client_id=<?= $id ?>" class="btn btn-sm text-white" style="background:#1a3a5c">案件追加</a>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <!-- 案件一覧 -->
    <div class="page-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-bold mb-0"><i class="bi bi-folder2-open me-2"></i>案件 (<?= count($cases['rows']) ?>)</h3>
        <a href="<?= LC_BASE_URL ?>/case_edit.php?client_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">+ 案件追加</a>
      </div>
      <?php if (empty($cases['rows'])): ?>
      <p class="text-muted small mb-0">案件がありません。</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>案件名</th><th>種別</th><th>ステータス</th><th>開始日</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($cases['rows'] as $cs): ?>
          <tr>
            <td><a href="<?= LC_BASE_URL ?>/case_view.php?id=<?= $cs['id'] ?>"><?= h($cs['case_name']) ?></a></td>
            <td><span class="badge badge-case-type"><?= h(lc_case_type_map()[$cs['case_type']] ?? '') ?></span></td>
            <td><?= case_status_badge($cs['status']) ?></td>
            <td><?= fmt_date($cs['opened_date']) ?></td>
            <td><a href="<?= LC_BASE_URL ?>/case_view.php?id=<?= $cs['id'] ?>" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2">詳細</a></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- 活動記録 -->
    <div class="page-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-bold mb-0"><i class="bi bi-journal-text me-2"></i>活動記録</h3>
      </div>
      <?php if (empty($activities)): ?>
      <p class="text-muted small mb-0">活動記録がありません。</p>
      <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($activities as $a): ?>
        <li class="list-group-item px-0 py-2">
          <div class="d-flex justify-content-between align-items-center">
            <span class="badge" style="background:#e8f0fe;color:#1a3a5c"><?= h(ACTIVITY_TYPE_MAP[$a['activity_type']] ?? '') ?></span>
            <small class="text-muted"><?= fmt_datetime($a['activity_at']) ?> <?= h($a['user_name']) ?></small>
          </div>
          <?php if ($a['title']): ?><div class="fw-bold small mt-1"><?= h($a['title']) ?></div><?php endif; ?>
          <?php if ($a['content']): ?><div class="small text-muted mt-1" style="white-space:pre-wrap"><?= h(mb_strimwidth($a['content'], 0, 100, '…')) ?></div><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
