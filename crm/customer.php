<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$staff = crm_require_login();

$id = (int)($_GET['id'] ?? 0);
$c  = get_customer($id);
if (!$c) { header('Location: ' . CRM_BASE_URL . '/index.php'); exit; }

$tags          = get_customer_tags($id);
$consultations = get_consultations($id);
$page_title    = h($c['name']) . ' 様';

$sm = STATUS_MAP;
$um = URGENCY_MAP;

include __DIR__ . '/includes/_header.php';
?>

<div class="row g-4">
  <!-- 顧客情報 -->
  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>基本情報</strong>
        <a href="customer_edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">編集</a>
      </div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <dt class="col-4">氏名</dt><dd class="col-8"><?= h($c['name']) ?> 様</dd>
          <dt class="col-4">ふりがな</dt><dd class="col-8"><?= h($c['kana']) ?></dd>
          <dt class="col-4">メール</dt><dd class="col-8"><?= h($c['email']) ?></dd>
          <dt class="col-4">電話</dt><dd class="col-8"><?= h($c['tel']) ?></dd>
          <dt class="col-4">住所</dt><dd class="col-8"><?= h($c['address']) ?: '—' ?></dd>
          <dt class="col-4">生年月日</dt><dd class="col-8"><?= $c['birth_date'] ? h($c['birth_date']) : '—' ?></dd>
          <dt class="col-4">ステータス</dt><dd class="col-8"><?= crm_status_badge($c['member_status']) ?></dd>
          <dt class="col-4">会員登録日</dt><dd class="col-8"><?= $c['member_since'] ? h($c['member_since']) : '—' ?></dd>
          <dt class="col-4">登録元</dt><dd class="col-8"><?= $c['source'] === 'fuku_soudan' ? 'フクの相談窓口' : '手動' ?></dd>
        </dl>
      </div>
    </div>

    <!-- タグ -->
    <div class="card shadow-sm mb-3">
      <div class="card-header"><strong>タグ</strong></div>
      <div class="card-body">
        <?php if ($tags): ?>
          <?php foreach ($tags as $t): ?>
          <span class="badge bg-light text-dark border me-1 mb-1"><?= h($t) ?></span>
          <?php endforeach; ?>
        <?php else: ?>
          <span class="text-muted small">タグなし</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- メモ -->
    <?php if ($c['memo']): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header"><strong>メモ</strong></div>
      <div class="card-body small" style="white-space:pre-wrap"><?= h($c['memo']) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- 相談履歴 -->
  <div class="col-lg-8">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h2 class="h6 mb-0">相談履歴（<?= count($consultations) ?>件）</h2>
      <a href="consultation.php?customer_id=<?= $id ?>" class="btn btn-sm text-white" style="background:#3a7d5c">
        ＋ 相談記録を追加
      </a>
    </div>

    <?php if (empty($consultations)): ?>
    <div class="alert alert-light text-muted">相談記録がありません。</div>
    <?php endif; ?>

    <?php foreach ($consultations as $cn): ?>
    <?php $isf = (bool)$cn['is_first']; $color = $isf ? '#0d47a1' : '#4a148c'; ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex justify-content-between align-items-center py-2"
           style="border-left: 4px solid <?= $color ?>">
        <div>
          <span class="badge me-1" style="background:<?= $color ?>"><?= $isf ? '初回' : '2回目以降' ?></span>
          <strong><?= date('Y年n月j日', strtotime($cn['consulted_at'])) ?></strong>
          <span class="text-muted small ms-2"><?= h($cn['consultation_type']) ?> / <?= h($cn['method']) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="text-muted small"><?= h($cn['staff_name'] ?? '') ?></span>
          <a href="consultation.php?id=<?= $cn['id'] ?>&customer_id=<?= $id ?>"
             class="btn btn-sm btn-outline-secondary py-0">編集</a>
        </div>
      </div>
      <div class="card-body small">
        <?php if ($cn['content']): ?>
        <div class="mb-2">
          <span class="fw-bold text-muted">相談内容：</span>
          <span style="white-space:pre-wrap"><?= h($cn['content']) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($isf): ?>
          <?php if ($cn['first_background']): ?>
          <div class="mb-1"><span class="fw-bold text-muted">経緯：</span><?= h($cn['first_background']) ?></div>
          <?php endif; ?>
          <?php if ($cn['first_living_situation']): ?>
          <div class="mb-1"><span class="fw-bold text-muted">生活状況：</span><?= h($cn['first_living_situation']) ?></div>
          <?php endif; ?>
          <?php if ($cn['first_urgency']): ?>
          <div class="mb-1"><span class="fw-bold text-muted">緊急度：</span>
            <span class="badge bg-<?= $cn['first_urgency'] === 'high' ? 'danger' : ($cn['first_urgency'] === 'medium' ? 'warning' : 'secondary') ?>">
              <?= h($um[$cn['first_urgency']] ?? '') ?>
            </span>
          </div>
          <?php endif; ?>
        <?php else: ?>
          <?php if ($cn['repeat_progress']): ?>
          <div class="mb-1"><span class="fw-bold text-muted">前回からの変化：</span><?= h($cn['repeat_progress']) ?></div>
          <?php endif; ?>
          <?php if ($cn['repeat_payment_status']): ?>
          <div class="mb-1"><span class="fw-bold text-muted">入金状況：</span><?= h($cn['repeat_payment_status']) ?></div>
          <?php endif; ?>
          <?php if ($cn['repeat_materials']): ?>
          <div class="mb-1"><span class="fw-bold text-muted">書類等：</span><?= h($cn['repeat_materials']) ?></div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($cn['result']): ?>
        <div class="mb-1"><span class="fw-bold text-muted">対応結果：</span><?= h($cn['result']) ?></div>
        <?php endif; ?>
        <?php if ($cn['next_action']): ?>
        <div class="mb-0 text-primary"><span class="fw-bold">次回対応：</span><?= h($cn['next_action']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="mt-3">
  <a href="index.php" class="btn btn-outline-secondary btn-sm">← 顧客一覧へ</a>
</div>

<?php include __DIR__ . '/includes/_footer.php'; ?>
