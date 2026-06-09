<?php
require_once 'config.php';
require_login();

$db = get_db();

// 統計
$stats = $db->query("
  SELECT
    COUNT(*) AS total,
    SUM(status = '受任中') AS active,
    SUM(status = '完了') AS done,
    SUM(status = '相談中') AS consult
  FROM cases
")->fetch();

// 今週の期日（7日以内）
$upcoming = $db->query("
  SELECT d.*, c.title AS case_title, c.case_number
  FROM deadlines d
  JOIN cases c ON c.id = d.case_id
  WHERE d.is_completed = 0
    AND d.deadline_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
  ORDER BY d.deadline_date ASC
  LIMIT 10
")->fetchAll();

// 進行中案件（最新5件）
$active_cases = $db->query("
  SELECT c.*, cl.name AS client_name
  FROM cases c
  JOIN clients cl ON cl.id = c.client_id
  WHERE c.status = '受任中'
  ORDER BY c.updated_at DESC
  LIMIT 5
")->fetchAll();

// 未収件数
$unpaid = $db->query("
  SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
  FROM billing
  WHERE is_paid = 0
")->fetch();

// 書類未収取の件数
$doc_pending = $db->query("
  SELECT COUNT(*) AS cnt FROM doc_checklist WHERE is_received = 0
")->fetch();

// 案件種別集計
$type_stats = $db->query("
  SELECT case_type, COUNT(*) AS cnt
  FROM cases
  GROUP BY case_type
  ORDER BY cnt DESC
  LIMIT 5
")->fetchAll();

$page_title = 'ダッシュボード';
require 'includes/header.php';
?>

<!-- 統計カード -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center gap-3 p-3">
        <div class="rounded-3 p-2 bg-primary bg-opacity-10">
          <i class="bi bi-folder2-open fs-3 text-primary"></i>
        </div>
        <div>
          <div class="text-muted small">総案件数</div>
          <div class="fw-bold fs-4"><?= $stats['total'] ?><span class="small text-muted fw-normal"> 件</span></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center gap-3 p-3">
        <div class="rounded-3 p-2 bg-warning bg-opacity-10">
          <i class="bi bi-hourglass-split fs-3 text-warning"></i>
        </div>
        <div>
          <div class="text-muted small">受任中</div>
          <div class="fw-bold fs-4"><?= $stats['active'] ?><span class="small text-muted fw-normal"> 件</span></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center gap-3 p-3">
        <div class="rounded-3 p-2 bg-danger bg-opacity-10">
          <i class="bi bi-currency-yen fs-3 text-danger"></i>
        </div>
        <div>
          <div class="text-muted small">未収報酬</div>
          <div class="fw-bold fs-5">¥<?= number_format($unpaid['total']) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center gap-3 p-3">
        <div class="rounded-3 p-2 bg-info bg-opacity-10">
          <i class="bi bi-check2-square fs-3 text-info"></i>
        </div>
        <div>
          <div class="text-muted small">書類未収取</div>
          <div class="fw-bold fs-4"><?= $doc_pending['cnt'] ?><span class="small text-muted fw-normal"> 件</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- 近期の期日 -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white d-flex align-items-center justify-content-between py-2">
        <span class="fw-semibold"><i class="bi bi-calendar-event text-danger me-2"></i>直近の期日（14日以内）</span>
        <a href="deadlines.php" class="btn btn-outline-secondary btn-sm">一覧</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($upcoming)): ?>
        <div class="p-4 text-center text-muted"><i class="bi bi-calendar-check fs-3 d-block mb-2"></i>直近の期日はありません</div>
        <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($upcoming as $d):
            $days = days_until($d['deadline_date']);
            $badge = $days < 0 ? 'danger' : ($days <= 3 ? 'warning' : 'primary');
          ?>
          <li class="list-group-item px-3 py-2">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="fw-semibold small"><?= h($d['title']) ?></div>
                <div class="text-muted" style="font-size:.8rem">
                  <?= h($d['case_number']) ?> – <?= h($d['case_title']) ?>
                </div>
              </div>
              <span class="badge bg-<?= $badge ?> ms-2 text-nowrap">
                <?= $days < 0 ? '期限超過' : ($days === 0 ? '本日' : $days . '日後') ?>
              </span>
            </div>
            <div class="text-muted" style="font-size:.8rem"><i class="bi bi-clock me-1"></i><?= format_date($d['deadline_date']) ?></div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 進行中案件 -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white d-flex align-items-center justify-content-between py-2">
        <span class="fw-semibold"><i class="bi bi-folder-fill text-primary me-2"></i>受任中案件（最新）</span>
        <a href="cases.php" class="btn btn-outline-secondary btn-sm">一覧</a>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php foreach ($active_cases as $c): ?>
          <li class="list-group-item px-3 py-2">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <a href="case_detail.php?id=<?= $c['id'] ?>" class="fw-semibold text-decoration-none small">
                  <?= h($c['title']) ?>
                </a>
                <div class="text-muted" style="font-size:.8rem">
                  <i class="bi bi-person me-1"></i><?= h($c['client_name']) ?>
                  &nbsp;|&nbsp;
                  <i class="bi bi-<?= TYPE_ICONS[$c['case_type']] ?? 'folder' ?> me-1"></i><?= h($c['case_type']) ?>
                </div>
              </div>
              <span class="text-muted small text-nowrap"><?= format_date($c['start_date']) ?>〜</span>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- 案件種別内訳 -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header bg-white py-2">
        <span class="fw-semibold"><i class="bi bi-pie-chart text-success me-2"></i>案件種別上位</span>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php foreach ($type_stats as $ts): ?>
          <li class="list-group-item px-3 py-2 d-flex align-items-center justify-content-between">
            <span class="small">
              <i class="bi <?= TYPE_ICONS[$ts['case_type']] ?? 'bi-folder' ?> me-2 text-primary"></i>
              <?= h($ts['case_type']) ?>
            </span>
            <span class="badge bg-primary rounded-pill"><?= $ts['cnt'] ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- 収支サマリ -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header bg-white py-2">
        <span class="fw-semibold"><i class="bi bi-wallet2 text-warning me-2"></i>報酬サマリ</span>
      </div>
      <div class="card-body">
        <?php
        $billing_summary = $db->query("
          SELECT
            COALESCE(SUM(CASE WHEN is_paid = 1 THEN amount END), 0) AS paid,
            COALESCE(SUM(CASE WHEN is_paid = 0 THEN amount END), 0) AS unpaid,
            COALESCE(SUM(CASE WHEN billing_type = '司法書士報酬' THEN amount END), 0) AS fee,
            COALESCE(SUM(CASE WHEN billing_type = '登録免許税' THEN amount END), 0) AS tax
          FROM billing
        ")->fetch();
        ?>
        <div class="row g-3">
          <div class="col-6">
            <div class="p-3 bg-success bg-opacity-10 rounded-3 text-center">
              <div class="small text-muted">収納済み</div>
              <div class="fw-bold text-success">¥<?= number_format($billing_summary['paid']) ?></div>
            </div>
          </div>
          <div class="col-6">
            <div class="p-3 bg-danger bg-opacity-10 rounded-3 text-center">
              <div class="small text-muted">未収</div>
              <div class="fw-bold text-danger">¥<?= number_format($billing_summary['unpaid']) ?></div>
            </div>
          </div>
          <div class="col-6">
            <div class="p-3 bg-primary bg-opacity-10 rounded-3 text-center">
              <div class="small text-muted">司法書士報酬（合計）</div>
              <div class="fw-bold text-primary">¥<?= number_format($billing_summary['fee']) ?></div>
            </div>
          </div>
          <div class="col-6">
            <div class="p-3 bg-warning bg-opacity-10 rounded-3 text-center">
              <div class="small text-muted">登録免許税（合計）</div>
              <div class="fw-bold text-warning">¥<?= number_format($billing_summary['tax']) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
