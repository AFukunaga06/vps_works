<?php
require_once 'config.php';
require_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: cases.php'); exit; }

$st = $db->prepare(
    'SELECT c.*, cl.name AS client_name, cl.id AS client_id FROM cases c
     JOIN clients cl ON c.client_id=cl.id WHERE c.id=?'
);
$st->execute([$id]);
$case = $st->fetch();
if (!$case) { header('Location: cases.php'); exit; }
$page_title = $case['title'];

// 活動記録
$acts = $db->prepare('SELECT * FROM case_activities WHERE case_id=? ORDER BY activity_date DESC, id DESC');
$acts->execute([$id]);
$activities = $acts->fetchAll();

// 期日
$dls = $db->prepare('SELECT * FROM deadlines WHERE case_id=? ORDER BY is_completed ASC, deadline_date ASC');
$dls->execute([$id]);
$deadlines = $dls->fetchAll();

// 請求
$bills = $db->prepare('SELECT * FROM billing WHERE case_id=? ORDER BY billing_date ASC');
$bills->execute([$id]);
$billing = $bills->fetchAll();

// 書類
$docs = $db->prepare('SELECT * FROM documents WHERE case_id=? ORDER BY upload_date DESC');
$docs->execute([$id]);
$documents = $docs->fetchAll();

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_activity') {
        $st = $db->prepare(
            'INSERT INTO case_activities (case_id,activity_type,content,activity_date,created_by) VALUES (?,?,?,?,?)'
        );
        $st->execute([
            $id,
            $_POST['activity_type'] ?? 'その他',
            trim($_POST['content'] ?? ''),
            $_POST['activity_date'] ?? date('Y-m-d'),
            $_SESSION['user_name'] ?? '',
        ]);
    } elseif ($action === 'add_deadline') {
        $st = $db->prepare(
            'INSERT INTO deadlines (case_id,title,deadline_type,deadline_date,description) VALUES (?,?,?,?,?)'
        );
        $st->execute([
            $id,
            trim($_POST['dl_title'] ?? ''),
            $_POST['deadline_type'] ?? 'その他',
            $_POST['deadline_date'] ?? date('Y-m-d'),
            trim($_POST['dl_desc'] ?? ''),
        ]);
    } elseif ($action === 'complete_deadline') {
        $dl_id = (int)($_POST['dl_id'] ?? 0);
        $db->prepare('UPDATE deadlines SET is_completed=1, completed_at=NOW() WHERE id=? AND case_id=?')
           ->execute([$dl_id, $id]);
    } elseif ($action === 'add_billing') {
        $st = $db->prepare(
            'INSERT INTO billing (case_id,billing_type,amount,billing_date,is_paid,notes) VALUES (?,?,?,?,?,?)'
        );
        $st->execute([
            $id,
            $_POST['billing_type'] ?? '着手金',
            (int)str_replace(',', '', $_POST['amount'] ?? '0'),
            $_POST['billing_date'] ?? date('Y-m-d'),
            isset($_POST['is_paid']) ? 1 : 0,
            trim($_POST['bill_notes'] ?? ''),
        ]);
    } elseif ($action === 'paid_billing') {
        $b_id = (int)($_POST['bill_id'] ?? 0);
        $db->prepare('UPDATE billing SET is_paid=1, payment_date=CURDATE() WHERE id=? AND case_id=?')
           ->execute([$b_id, $id]);
    }

    header('Location: case_detail.php?id=' . $id . '#' . ($_POST['redirect_anchor'] ?? ''));
    exit;
}

$status_color = STATUS_COLORS[$case['status']] ?? 'secondary';
require 'includes/header.php';

if (!empty($_GET['saved'])): ?>
<div class="alert alert-success alert-dismissible fade show">
  <i class="bi bi-check-circle me-1"></i>保存しました。
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="cases.php">案件管理</a></li>
    <li class="breadcrumb-item active"><?= h(mb_strimwidth($case['title'],0,40,'…')) ?></li>
  </ol>
</nav>

<!-- 案件ヘッダー -->
<div class="card mb-4">
  <div class="card-body py-3">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
      <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
          <span class="badge bg-<?= $status_color ?> fs-6"><?= h($case['status']) ?></span>
          <span class="badge bg-light text-dark border">
            <i class="bi <?= TYPE_ICONS[$case['case_type']] ?? 'bi-folder' ?> me-1"></i><?= h($case['case_type']) ?>
          </span>
          <small class="text-muted"><?= h($case['case_number'] ?? '') ?></small>
        </div>
        <h4 class="fw-bold mb-1"><?= h($case['title']) ?></h4>
        <div class="text-muted small">
          <i class="bi bi-person me-1"></i>依頼人: <a href="client_detail.php?id=<?= $case['client_id'] ?>"><?= h($case['client_name']) ?></a>
          &ensp;｜&ensp;<i class="bi bi-person-badge me-1"></i>担当: <?= h($case['assigned_lawyer'] ?? '未設定') ?>
          <?php if ($case['court_name']): ?>&ensp;｜&ensp;<i class="bi bi-bank me-1"></i><?= h($case['court_name']) ?><?php endif; ?>
          &ensp;｜&ensp;<i class="bi bi-calendar3 me-1"></i><?= format_date($case['start_date']) ?>
          <?php if ($case['end_date']): ?>〜<?= format_date($case['end_date']) ?><?php endif; ?>
        </div>
      </div>
      <a href="case_form.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-pencil me-1"></i>案件情報を編集
      </a>
    </div>
    <?php if ($case['description']): ?>
    <div class="mt-2 pt-2 border-top small text-muted"><?= nl2br(h($case['description'])) ?></div>
    <?php endif; ?>
    <div class="row g-2 mt-2">
      <div class="col-6 col-md-3">
        <div class="p-2 rounded bg-light text-center">
          <div class="text-muted small">着手金</div>
          <div class="fw-bold"><?= format_money((int)$case['retainer_fee']) ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="p-2 rounded bg-light text-center">
          <div class="text-muted small">成功報酬</div>
          <div class="fw-bold"><?= format_money((int)$case['success_fee']) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- 左カラム：タイムライン + 期日 -->
  <div class="col-lg-7">
    <!-- 進捗タイムライン -->
    <div class="card mb-3" id="timeline">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history text-primary me-2"></i>進捗タイムライン</h6>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#addActivity">
          <i class="bi bi-plus-circle me-1"></i>追加
        </button>
      </div>
      <div class="collapse" id="addActivity">
        <div class="card-body bg-light border-bottom">
          <form method="post">
            <input type="hidden" name="action" value="add_activity">
            <input type="hidden" name="redirect_anchor" value="timeline">
            <div class="row g-2">
              <div class="col-md-4">
                <select name="activity_type" class="form-select form-select-sm">
                  <?php foreach (ACTIVITY_TYPES as $t): ?><option><?= $t ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <input type="date" name="activity_date" class="form-control form-control-sm"
                       value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-4 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-grow-1">追加</button>
              </div>
              <div class="col-12">
                <textarea name="content" class="form-control form-control-sm" rows="2"
                          placeholder="活動内容を入力..." required></textarea>
              </div>
            </div>
          </form>
        </div>
      </div>
      <div class="card-body">
        <?php if ($activities): ?>
        <div class="timeline">
          <?php foreach ($activities as $act): ?>
          <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="bg-white border rounded p-3">
              <div class="d-flex justify-content-between align-items-start mb-1">
                <div>
                  <span class="badge bg-primary me-2"><?= h($act['activity_type']) ?></span>
                  <small class="text-muted"><?= format_date($act['activity_date']) ?></small>
                </div>
                <small class="text-muted"><?= h($act['created_by'] ?? '') ?></small>
              </div>
              <div class="small"><?= nl2br(h($act['content'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center text-muted py-4">
          <i class="bi bi-clock-history fs-2 d-block mb-2"></i>活動記録がありません
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- 期日 -->
    <div class="card" id="deadlines">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-event text-warning me-2"></i>期日・締切一覧</h6>
        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="collapse" data-bs-target="#addDeadline">
          <i class="bi bi-plus-circle me-1"></i>追加
        </button>
      </div>
      <div class="collapse" id="addDeadline">
        <div class="card-body bg-light border-bottom">
          <form method="post">
            <input type="hidden" name="action" value="add_deadline">
            <input type="hidden" name="redirect_anchor" value="deadlines">
            <div class="row g-2">
              <div class="col-md-6">
                <input type="text" name="dl_title" class="form-control form-control-sm" placeholder="期日名（例：第2回口頭弁論）" required>
              </div>
              <div class="col-md-3">
                <select name="deadline_type" class="form-select form-select-sm">
                  <?php foreach (DEADLINE_TYPES as $t): ?><option><?= $t ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <input type="date" name="deadline_date" class="form-control form-control-sm"
                       value="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-12">
                <textarea name="dl_desc" class="form-control form-control-sm" rows="2"
                          placeholder="詳細（場所・時間など）"></textarea>
              </div>
              <div class="col-12">
                <button class="btn btn-sm btn-warning">期日を追加</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <div class="card-body p-0">
        <?php
        $pending   = array_filter($deadlines, fn($d) => !$d['is_completed']);
        $completed = array_filter($deadlines, fn($d) =>  $d['is_completed']);
        ?>
        <?php if ($pending || $completed): ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($pending as $dl):
            $diff = days_until($dl['deadline_date']);
            $urgency = $diff < 0 ? 'danger' : ($diff <= 3 ? 'warning' : ($diff <= 14 ? 'info' : 'secondary'));
          ?>
          <li class="list-group-item py-3">
            <div class="d-flex align-items-start justify-content-between gap-2">
              <div class="flex-grow-1">
                <div class="fw-semibold small"><?= h($dl['title']) ?></div>
                <div class="mt-1">
                  <span class="badge bg-<?= $urgency ?> me-1">
                    <?= $diff < 0 ? abs($diff).'日超過' : ($diff === 0 ? '今日' : $diff.'日後') ?>
                  </span>
                  <span class="badge bg-light text-dark border me-1"><?= h($dl['deadline_type']) ?></span>
                  <small class="text-muted"><?= format_date($dl['deadline_date']) ?></small>
                </div>
                <?php if ($dl['description']): ?><div class="small text-muted mt-1"><?= h($dl['description']) ?></div><?php endif; ?>
              </div>
              <form method="post" class="flex-shrink-0">
                <input type="hidden" name="action" value="complete_deadline">
                <input type="hidden" name="dl_id" value="<?= $dl['id'] ?>">
                <input type="hidden" name="redirect_anchor" value="deadlines">
                <button class="btn btn-sm btn-outline-success" title="完了にする">
                  <i class="bi bi-check2"></i>
                </button>
              </form>
            </div>
          </li>
          <?php endforeach; ?>
          <?php if ($completed): ?>
          <li class="list-group-item bg-light py-2">
            <small class="text-muted fw-semibold">完了済み</small>
          </li>
          <?php foreach ($completed as $dl): ?>
          <li class="list-group-item py-2 text-muted">
            <i class="bi bi-check-circle-fill text-success me-2"></i>
            <small class="text-decoration-line-through"><?= h($dl['title']) ?></small>
            <small class="ms-2"><?= format_date($dl['deadline_date']) ?></small>
          </li>
          <?php endforeach; ?>
          <?php endif; ?>
        </ul>
        <?php else: ?>
        <div class="text-center text-muted py-4"><i class="bi bi-calendar-check fs-2 d-block mb-2"></i>期日はありません</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 右カラム：請求 + 書類 -->
  <div class="col-lg-5">
    <!-- 着手金・報酬管理 -->
    <div class="card mb-3" id="billing">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-currency-yen text-success me-2"></i>着手金・報酬管理</h6>
        <button class="btn btn-sm btn-outline-success" data-bs-toggle="collapse" data-bs-target="#addBilling">
          <i class="bi bi-plus-circle me-1"></i>追加
        </button>
      </div>
      <div class="collapse" id="addBilling">
        <div class="card-body bg-light border-bottom">
          <form method="post">
            <input type="hidden" name="action" value="add_billing">
            <input type="hidden" name="redirect_anchor" value="billing">
            <div class="row g-2">
              <div class="col-6">
                <select name="billing_type" class="form-select form-select-sm">
                  <?php foreach (BILLING_TYPES as $t): ?><option><?= $t ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-6">
                <div class="input-group input-group-sm">
                  <span class="input-group-text">¥</span>
                  <input type="number" name="amount" class="form-control" placeholder="330000" min="0" step="1000">
                </div>
              </div>
              <div class="col-6">
                <input type="date" name="billing_date" class="form-control form-control-sm"
                       value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-6 d-flex align-items-center">
                <div class="form-check">
                  <input type="checkbox" name="is_paid" id="is_paid_cb" class="form-check-input">
                  <label for="is_paid_cb" class="form-check-label small">支払済み</label>
                </div>
              </div>
              <div class="col-12">
                <input type="text" name="bill_notes" class="form-control form-control-sm" placeholder="備考">
              </div>
              <div class="col-12">
                <button class="btn btn-sm btn-success">追加</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <div class="card-body p-0">
        <?php if ($billing): ?>
        <ul class="list-group list-group-flush">
          <?php
          $total_billed = 0; $total_paid = 0;
          foreach ($billing as $b):
            $total_billed += $b['amount'];
            if ($b['is_paid']) $total_paid += $b['amount'];
          ?>
          <li class="list-group-item py-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <span class="badge bg-<?= $b['is_paid'] ? 'success' : 'warning text-dark' ?> me-2">
                  <?= $b['is_paid'] ? '入金済' : '未収' ?>
                </span>
                <span class="badge bg-light text-dark border me-2"><?= h($b['billing_type']) ?></span>
                <span class="fw-semibold"><?= format_money((int)$b['amount']) ?></span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <small class="text-muted"><?= format_date($b['billing_date']) ?></small>
                <?php if (!$b['is_paid']): ?>
                <form method="post">
                  <input type="hidden" name="action" value="paid_billing">
                  <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
                  <input type="hidden" name="redirect_anchor" value="billing">
                  <button class="btn btn-xs btn-outline-success btn-sm" title="入金確認">
                    <i class="bi bi-check2"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($b['notes']): ?><div class="small text-muted mt-1"><?= h($b['notes']) ?></div><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <div class="p-3 bg-light border-top">
          <div class="d-flex justify-content-between small">
            <span class="text-muted">請求合計</span>
            <strong><?= format_money($total_billed) ?></strong>
          </div>
          <div class="d-flex justify-content-between small">
            <span class="text-muted">入金済み</span>
            <strong class="text-success"><?= format_money($total_paid) ?></strong>
          </div>
          <?php if ($total_billed - $total_paid > 0): ?>
          <div class="d-flex justify-content-between small">
            <span class="text-danger fw-semibold">未収金</span>
            <strong class="text-danger"><?= format_money($total_billed - $total_paid) ?></strong>
          </div>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="text-center text-muted py-4"><i class="bi bi-cash-coin fs-2 d-block mb-2"></i>請求記録がありません</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- 書類管理 -->
    <div class="card" id="documents">
      <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-text text-info me-2"></i>書類管理</h6>
      </div>
      <div class="card-body">
        <?php if ($documents): ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($documents as $doc): ?>
          <li class="list-group-item px-0 py-2">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-file-earmark-pdf text-danger fs-5"></i>
              <div class="flex-grow-1">
                <div class="small fw-semibold"><?= h($doc['document_name']) ?></div>
                <div class="text-muted" style="font-size:.75rem">
                  <?= h($doc['document_type']) ?> &nbsp;|&nbsp;
                  <?= date('Y/m/d', strtotime($doc['upload_date'])) ?>
                </div>
              </div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <div class="text-center text-muted py-3">
          <i class="bi bi-folder2 fs-2 d-block mb-2"></i>
          書類がアップロードされていません
        </div>
        <?php endif; ?>
        <div class="mt-3 p-3 border border-dashed rounded text-center text-muted small"
             style="border-style:dashed!important">
          <i class="bi bi-cloud-upload fs-3 d-block mb-1"></i>
          <strong>書類アップロード</strong>
          <div class="mt-1">（アップロード機能はこちらに実装）</div>
          <a href="documents.php?case_id=<?= $id ?>" class="btn btn-sm btn-outline-info mt-2">
            書類管理へ
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
