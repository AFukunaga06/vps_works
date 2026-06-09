<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$gc_user = gc_require_login();

$id = (int)($_GET['id'] ?? 0);
$cl = $id ? get_client($id) : null;
if (!$cl) { header('Location: '.GC_BASE_URL.'/clients.php'); exit; }

$cases = get_cases(['client_id' => $id, 'status' => ''], 1, 100);
$page_title = h($cl['name']) . ($cl['company_name'] ? ' (' . h($cl['company_name']) . ')' : '');
$page_nav   = 'clients';
$page_actions = '<a href="'.GC_BASE_URL.'/client_edit.php?id='.$id.'" class="btn btn-sm btn-gc"><i class="bi bi-pencil me-1"></i>編集</a>';
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success">保存しました。</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-4">
    <div class="page-card">
      <h2 class="h6 fw-bold mb-3 pb-2 border-bottom">基本情報</h2>
      <dl class="row small mb-0">
        <dt class="col-5 text-muted">氏名</dt><dd class="col-7 fw-bold"><?= h($cl['name']) ?></dd>
        <dt class="col-5 text-muted">読み</dt><dd class="col-7"><?= h($cl['kana']) ?></dd>
        <?php if ($cl['company_name']): ?>
        <dt class="col-5 text-muted">会社名</dt><dd class="col-7"><?= h($cl['company_name']) ?></dd>
        <dt class="col-5 text-muted">会社読み</dt><dd class="col-7"><?= h($cl['company_kana']) ?></dd>
        <?php endif; ?>
        <dt class="col-5 text-muted">電話1</dt><dd class="col-7"><?= h($cl['tel']) ?></dd>
        <?php if ($cl['tel2']): ?>
        <dt class="col-5 text-muted">電話2</dt><dd class="col-7"><?= h($cl['tel2']) ?></dd>
        <?php endif; ?>
        <dt class="col-5 text-muted">メール</dt><dd class="col-7"><?= h($cl['email']) ?></dd>
        <dt class="col-5 text-muted">住所</dt><dd class="col-7"><?= h($cl['address']) ?></dd>
        <?php if ($cl['birth_date']): ?>
        <dt class="col-5 text-muted">生年月日</dt><dd class="col-7"><?= fmt_date($cl['birth_date']) ?></dd>
        <?php endif; ?>
        <dt class="col-5 text-muted">担当者</dt><dd class="col-7"><?= h($cl['user_name'] ?? '未割当') ?></dd>
        <dt class="col-5 text-muted">ステータス</dt><dd class="col-7"><?= client_status_badge($cl['status']) ?></dd>
        <dt class="col-5 text-muted">登録日</dt><dd class="col-7"><?= fmt_date($cl['created_at']) ?></dd>
      </dl>
      <?php if ($cl['memo']): ?>
      <hr>
      <div class="text-muted small"><?= nl2br(h($cl['memo'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-md-8">
    <div class="page-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">関連案件（<?= $cases['total'] ?>件）</h2>
        <a href="<?= GC_BASE_URL ?>/case_edit.php?client_id=<?= $id ?>" class="btn btn-sm btn-gc">
          <i class="bi bi-plus-lg me-1"></i>案件追加
        </a>
      </div>
      <?php if (empty($cases['rows'])): ?>
        <p class="text-muted small">案件が登録されていません。</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr><th>案件番号</th><th>案件名</th><th>種別</th><th>段階</th><th>ステータス</th><th>開始日</th><th></th></tr>
          </thead>
          <tbody>
          <?php foreach ($cases['rows'] as $cs): ?>
          <tr>
            <td class="text-muted"><?= h($cs['case_number']) ?></td>
            <td><a href="<?= GC_BASE_URL ?>/case_view.php?id=<?= $cs['id'] ?>" class="fw-bold text-decoration-none"><?= h($cs['case_name']) ?></a></td>
            <td><span class="badge badge-case-type"><?= h(CASE_TYPE_MAP[$cs['case_type']] ?? '') ?></span></td>
            <td><?= progress_stage_badge($cs['progress_stage']) ?></td>
            <td><?= case_status_badge($cs['status']) ?></td>
            <td><?= fmt_date($cs['opened_date']) ?></td>
            <td><a href="<?= GC_BASE_URL ?>/case_view.php?id=<?= $cs['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">詳細</a></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/_footer.php'; ?>
