<?php
require_once 'config.php';
require_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: clients.php'); exit; }

$st = $db->prepare('SELECT * FROM clients WHERE id=?');
$st->execute([$id]);
$client = $st->fetch();
if (!$client) { header('Location: clients.php'); exit; }

$page_title = $client['name'] . ' 様';

// 案件一覧
$cs = $db->prepare(
    "SELECT c.*, (SELECT COUNT(*) FROM deadlines d WHERE d.case_id=c.id AND d.is_completed=0) AS open_dl
     FROM cases c WHERE c.client_id=? ORDER BY c.start_date DESC"
);
$cs->execute([$id]);
$cases = $cs->fetchAll();

require 'includes/header.php';

if (!empty($_GET['saved'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <i class="bi bi-check-circle me-1"></i>保存しました。
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="clients.php">依頼人台帳</a></li>
    <li class="breadcrumb-item active"><?= h($client['name']) ?></li>
  </ol>
</nav>

<div class="row g-3">
  <!-- 基本情報 -->
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-person-fill text-primary me-2"></i>基本情報</span>
        <a href="client_form.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-pencil me-1"></i>編集
        </a>
      </div>
      <div class="card-body">
        <div class="text-center mb-3">
          <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center"
               style="width:72px;height:72px;font-size:1.6rem">
            <?= mb_substr($client['name'], 0, 1) ?>
          </div>
          <h5 class="mt-2 mb-0 fw-bold"><?= h($client['name']) ?></h5>
          <?php if ($client['name_kana']): ?>
          <div class="text-muted small"><?= h($client['name_kana']) ?></div>
          <?php endif; ?>
        </div>
        <table class="table table-sm table-borderless mb-0">
          <tr><th class="text-muted small ps-0" style="width:80px">性別</th><td><?= h($client['gender'] ?? '—') ?></td></tr>
          <tr><th class="text-muted small ps-0">生年月日</th><td><?= format_date($client['birth_date']) ?></td></tr>
          <tr><th class="text-muted small ps-0">電話</th><td><?= h($client['phone'] ?? '—') ?></td></tr>
          <tr><th class="text-muted small ps-0">メール</th><td class="small"><?= h($client['email'] ?? '—') ?></td></tr>
          <tr><th class="text-muted small ps-0">郵便番号</th><td><?= h($client['postal_code'] ?? '—') ?></td></tr>
          <tr><th class="text-muted small ps-0">住所</th><td class="small"><?= h($client['address'] ?? '—') ?></td></tr>
          <tr><th class="text-muted small ps-0">登録日</th><td class="small"><?= format_date(substr($client['created_at'],0,10)) ?></td></tr>
        </table>
        <?php if ($client['notes']): ?>
        <div class="mt-3 p-2 bg-light rounded small">
          <strong class="d-block text-muted mb-1">メモ</strong>
          <?= nl2br(h($client['notes'])) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 案件一覧 -->
  <div class="col-lg-8">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="fw-semibold mb-0"><i class="bi bi-folder2-open text-primary me-2"></i>案件一覧</h6>
      <a href="case_form.php?client_id=<?= $id ?>" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>案件を追加
      </a>
    </div>

    <?php if ($cases): ?>
    <?php foreach ($cases as $case):
      $status_color = STATUS_COLORS[$case['status']] ?? 'secondary';
    ?>
    <div class="card mb-2">
      <div class="card-body py-3">
        <div class="d-flex align-items-start justify-content-between">
          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
              <span class="badge bg-<?= $status_color ?>"><?= h($case['status']) ?></span>
              <span class="badge bg-light text-dark border">
                <i class="bi <?= TYPE_ICONS[$case['case_type']] ?? 'bi-folder' ?> me-1"></i><?= h($case['case_type']) ?>
              </span>
              <small class="text-muted"><?= h($case['case_number'] ?? '') ?></small>
            </div>
            <a href="case_detail.php?id=<?= $case['id'] ?>" class="fw-semibold text-decoration-none d-block">
              <?= h($case['title']) ?>
            </a>
            <div class="text-muted small mt-1">
              <i class="bi bi-calendar3 me-1"></i>着手: <?= format_date($case['start_date']) ?>
              <?php if ($case['end_date']): ?> 〜 <?= format_date($case['end_date']) ?><?php endif; ?>
              &nbsp;｜&nbsp;<i class="bi bi-person me-1"></i><?= h($case['assigned_lawyer'] ?? '—') ?>
            </div>
          </div>
          <div class="text-end ms-3">
            <?php if ($case['open_dl'] > 0): ?>
            <span class="badge bg-danger rounded-pill mb-1"><?= $case['open_dl'] ?>件の期日</span><br>
            <?php endif; ?>
            <a href="case_detail.php?id=<?= $case['id'] ?>" class="btn btn-sm btn-outline-primary">詳細</a>
          </div>
        </div>
        <div class="row g-2 mt-2">
          <div class="col-6">
            <div class="p-2 bg-light rounded text-center">
              <div class="small text-muted">着手金</div>
              <div class="fw-semibold"><?= format_money((int)$case['retainer_fee']) ?></div>
            </div>
          </div>
          <div class="col-6">
            <div class="p-2 bg-light rounded text-center">
              <div class="small text-muted">成功報酬</div>
              <div class="fw-semibold"><?= format_money((int)$case['success_fee']) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="card">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-folder-x fs-1 d-block mb-2"></i>
        案件はまだ登録されていません
        <br><a href="case_form.php?client_id=<?= $id ?>" class="btn btn-sm btn-primary mt-3">最初の案件を登録</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
