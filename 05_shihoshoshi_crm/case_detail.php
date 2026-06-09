<?php
require_once 'config.php';
require_login();

$db  = get_db();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$case = $db->query("
  SELECT c.*, cl.name AS client_name, cl.phone AS client_phone, cl.email AS client_email
  FROM cases c JOIN clients cl ON cl.id = c.client_id WHERE c.id = $id
")->fetch();
if (!$case) { header('Location: cases.php'); exit; }

// POST処理（活動/期日/物件/報酬/書類チェック）
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_activity') {
        $db->prepare("INSERT INTO case_activities (case_id,activity_type,content,activity_date,created_by) VALUES (?,?,?,?,?)")
           ->execute([$id, $_POST['activity_type'], trim($_POST['content']), $_POST['activity_date'], $_SESSION['user_name']]);
        $msg = '活動記録を追加しました。';
    } elseif ($action === 'add_deadline') {
        $db->prepare("INSERT INTO deadlines (case_id,title,deadline_type,deadline_date,description) VALUES (?,?,?,?,?)")
           ->execute([$id, trim($_POST['title']), $_POST['deadline_type'], $_POST['deadline_date'], trim($_POST['description'])]);
        $msg = '期日を追加しました。';
    } elseif ($action === 'complete_deadline') {
        $db->prepare("UPDATE deadlines SET is_completed=1, completed_at=NOW() WHERE id=? AND case_id=?")
           ->execute([(int)$_POST['deadline_id'], $id]);
    } elseif ($action === 'add_property') {
        $db->prepare("INSERT INTO properties (case_id,property_type,location,land_area,building_area,lot_number,property_value,notes) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$id, $_POST['property_type'], trim($_POST['location']),
             $_POST['land_area'] ?: null, $_POST['building_area'] ?: null,
             trim($_POST['lot_number']), (int)str_replace(',', '', $_POST['property_value'] ?? '0'),
             trim($_POST['notes'])]);
        $msg = '物件情報を追加しました。';
    } elseif ($action === 'add_billing') {
        $db->prepare("INSERT INTO billing (case_id,billing_type,amount,billing_date,notes) VALUES (?,?,?,?,?)")
           ->execute([$id, $_POST['billing_type'], (int)str_replace(',', '', $_POST['amount']),
             $_POST['billing_date'] ?: null, trim($_POST['notes'])]);
        $msg = '報酬情報を追加しました。';
    } elseif ($action === 'toggle_billing_paid') {
        $db->prepare("UPDATE billing SET is_paid = NOT is_paid, payment_date = IF(is_paid=0, CURDATE(), NULL) WHERE id=? AND case_id=?")
           ->execute([(int)$_POST['billing_id'], $id]);
    } elseif ($action === 'add_doc') {
        $db->prepare("INSERT INTO doc_checklist (case_id,doc_name,sort_order) VALUES (?,?,?)")
           ->execute([$id, trim($_POST['doc_name']), $db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM doc_checklist WHERE case_id=$id")->fetchColumn()]);
        $msg = '書類を追加しました。';
    } elseif ($action === 'toggle_doc') {
        $doc = $db->query("SELECT * FROM doc_checklist WHERE id=" . (int)$_POST['doc_id'])->fetch();
        $received = $doc['is_received'] ? 0 : 1;
        $db->prepare("UPDATE doc_checklist SET is_received=?, received_at=? WHERE id=?")
           ->execute([$received, $received ? date('Y-m-d') : null, (int)$_POST['doc_id']]);
    } elseif ($action === 'delete_doc') {
        $db->prepare("DELETE FROM doc_checklist WHERE id=? AND case_id=?")->execute([(int)$_POST['doc_id'], $id]);
    } elseif ($action === 'delete_case') {
        $db->prepare("DELETE FROM cases WHERE id=?")->execute([$id]);
        header('Location: cases.php'); exit;
    }
    header("Location: case_detail.php?id=$id&msg=" . urlencode($msg));
    exit;
}

$activities = $db->query("SELECT * FROM case_activities WHERE case_id=$id ORDER BY activity_date DESC, id DESC")->fetchAll();
$deadlines  = $db->query("SELECT * FROM deadlines WHERE case_id=$id ORDER BY deadline_date ASC")->fetchAll();
$properties = $db->query("SELECT * FROM properties WHERE case_id=$id ORDER BY id ASC")->fetchAll();
$billings   = $db->query("SELECT * FROM billing WHERE case_id=$id ORDER BY billing_date ASC")->fetchAll();
$docs       = $db->query("SELECT * FROM doc_checklist WHERE case_id=$id ORDER BY sort_order, id")->fetchAll();

$total_bill   = array_sum(array_column($billings, 'amount'));
$total_paid   = array_sum(array_map(fn($b) => $b['is_paid'] ? $b['amount'] : 0, $billings));
$docs_done    = count(array_filter($docs, fn($d) => $d['is_received']));
$docs_total   = count($docs);

$page_title = h($case['title']);
require 'includes/header.php';

$flash = $_GET['msg'] ?? '';
?>

<?php if ($flash): ?>
<div class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i><?= h($flash) ?></div>
<?php endif; ?>

<!-- ヘッダー -->
<div class="d-flex align-items-start justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <a href="cases.php" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>案件一覧</a>
    <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
      <span class="badge bg-<?= STATUS_COLORS[$case['status']] ?> fs-6"><?= h($case['status']) ?></span>
      <h2 class="h5 mb-0"><?= h($case['title']) ?></h2>
    </div>
    <div class="text-muted small mt-1">
      <?= h($case['case_number'] ?? '') ?>
      &nbsp;|&nbsp;<i class="bi bi-person me-1"></i>
      <a href="client_detail.php?id=<?= $case['client_id'] ?>" class="text-decoration-none"><?= h($case['client_name']) ?></a>
      &nbsp;|&nbsp;<i class="bi bi-building me-1"></i><?= h($case['registry_office'] ?? '—') ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="case_form.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>編集</a>
    <form method="post" onsubmit="return confirm('この案件を削除します。よろしいですか？')">
      <input type="hidden" name="action" value="delete_case">
      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
    </form>
  </div>
</div>

<!-- サマリー行 -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card text-center py-2">
      <div class="small text-muted">種別</div>
      <div class="fw-semibold small"><i class="bi <?= TYPE_ICONS[$case['case_type']] ?? 'bi-folder' ?> me-1"></i><?= h($case['case_type']) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center py-2">
      <div class="small text-muted">担当</div>
      <div class="fw-semibold small"><?= h($case['assigned_staff'] ?? '—') ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center py-2">
      <div class="small text-muted">報酬合計</div>
      <div class="fw-semibold">¥<?= number_format($total_bill) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center py-2">
      <div class="small text-muted">書類収取</div>
      <div class="fw-semibold"><?= $docs_done ?> / <?= $docs_total ?></div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- 左カラム -->
  <div class="col-lg-6">

    <!-- 物件情報 -->
    <div class="card mb-4">
      <div class="card-header bg-white py-2 d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-house text-success me-2"></i>物件情報</span>
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="collapse" data-bs-target="#propForm">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
      <div class="collapse px-3 pt-2" id="propForm">
        <form method="post" class="pb-3">
          <input type="hidden" name="action" value="add_property">
          <div class="row g-2">
            <div class="col-sm-6">
              <select name="property_type" class="form-select form-select-sm">
                <?php foreach (PROPERTY_TYPES as $pt): ?><option><?= $pt ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <input type="text" name="lot_number" class="form-control form-control-sm" placeholder="地番・家屋番号">
            </div>
            <div class="col-12">
              <input type="text" name="location" class="form-control form-control-sm" placeholder="所在">
            </div>
            <div class="col-sm-4">
              <input type="number" name="land_area" class="form-control form-control-sm" placeholder="地積(㎡)" step="0.01">
            </div>
            <div class="col-sm-4">
              <input type="number" name="building_area" class="form-control form-control-sm" placeholder="床面積(㎡)" step="0.01">
            </div>
            <div class="col-sm-4">
              <input type="number" name="property_value" class="form-control form-control-sm" placeholder="評価額(円)">
            </div>
            <div class="col-12">
              <input type="text" name="notes" class="form-control form-control-sm" placeholder="メモ">
            </div>
          </div>
          <button type="submit" class="btn btn-success btn-sm mt-2 w-100">追加</button>
        </form>
        <hr>
      </div>
      <?php if (empty($properties)): ?>
      <div class="card-body text-muted small">物件情報なし</div>
      <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($properties as $p): ?>
        <div class="list-group-item px-3 py-2">
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-secondary"><?= h($p['property_type']) ?></span>
            <span class="small fw-semibold"><?= h($p['lot_number'] ?? '') ?></span>
          </div>
          <div class="small"><?= h($p['location']) ?></div>
          <div class="small text-muted">
            <?php if ($p['land_area']): ?>地積: <?= format_area($p['land_area']) ?>&nbsp;&nbsp;<?php endif; ?>
            <?php if ($p['building_area']): ?>床面積: <?= format_area($p['building_area']) ?>&nbsp;&nbsp;<?php endif; ?>
            <?php if ($p['property_value']): ?>評価額: ¥<?= number_format($p['property_value']) ?><?php endif; ?>
          </div>
          <?php if ($p['notes']): ?><div class="small text-muted"><?= h($p['notes']) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 期日管理 -->
    <div class="card mb-4">
      <div class="card-header bg-white py-2 d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-calendar-event text-danger me-2"></i>期日管理</span>
        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#deadlineForm">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
      <div class="collapse px-3 pt-2" id="deadlineForm">
        <form method="post" class="pb-3">
          <input type="hidden" name="action" value="add_deadline">
          <div class="row g-2">
            <div class="col-12"><input type="text" name="title" class="form-control form-control-sm" placeholder="期日タイトル" required></div>
            <div class="col-sm-6">
              <select name="deadline_type" class="form-select form-select-sm">
                <?php foreach (DEADLINE_TYPES as $dt): ?><option><?= $dt ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6"><input type="date" name="deadline_date" class="form-control form-control-sm" required></div>
            <div class="col-12"><input type="text" name="description" class="form-control form-control-sm" placeholder="備考"></div>
          </div>
          <button type="submit" class="btn btn-danger btn-sm mt-2 w-100">追加</button>
        </form>
        <hr>
      </div>
      <?php if (empty($deadlines)): ?>
      <div class="card-body text-muted small">期日なし</div>
      <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($deadlines as $d):
          $days = days_until($d['deadline_date']);
        ?>
        <li class="list-group-item px-3 py-2 d-flex align-items-start justify-content-between <?= $d['is_completed'] ? 'text-muted' : '' ?>">
          <div>
            <div class="small fw-semibold <?= $d['is_completed'] ? 'text-decoration-line-through' : '' ?>">
              <?= h($d['title']) ?>
            </div>
            <div style="font-size:.78rem" class="text-muted">
              <span class="badge bg-light text-dark me-1"><?= h($d['deadline_type']) ?></span>
              <?= format_date($d['deadline_date']) ?>
              <?php if (!$d['is_completed']): ?>
                <span class="ms-1 text-<?= $days < 0 ? 'danger' : ($days <= 3 ? 'warning' : 'muted') ?>">
                  (<?= $days < 0 ? abs($days).'日超過' : ($days === 0 ? '本日' : $days.'日後') ?>)
                </span>
              <?php endif; ?>
            </div>
          </div>
          <?php if (!$d['is_completed']): ?>
          <form method="post" class="ms-2">
            <input type="hidden" name="action" value="complete_deadline">
            <input type="hidden" name="deadline_id" value="<?= $d['id'] ?>">
            <button class="btn btn-sm btn-outline-success py-0" title="完了">
              <i class="bi bi-check-lg"></i>
            </button>
          </form>
          <?php else: ?>
          <span class="badge bg-success ms-2">完了</span>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

  </div>

  <!-- 右カラム -->
  <div class="col-lg-6">

    <!-- 書類チェックリスト -->
    <div class="card mb-4">
      <div class="card-header bg-white py-2 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
          <i class="bi bi-check2-square text-info me-2"></i>書類チェックリスト
          <span class="badge bg-<?= $docs_done === $docs_total && $docs_total > 0 ? 'success' : 'secondary' ?> ms-1">
            <?= $docs_done ?>/<?= $docs_total ?>
          </span>
        </span>
        <button class="btn btn-outline-info btn-sm" data-bs-toggle="collapse" data-bs-target="#docForm">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
      <div class="collapse px-3 pt-2" id="docForm">
        <form method="post" class="pb-3 d-flex gap-2">
          <input type="hidden" name="action" value="add_doc">
          <input type="text" name="doc_name" class="form-control form-control-sm" placeholder="書類名" required>
          <button type="submit" class="btn btn-info btn-sm text-white text-nowrap">追加</button>
        </form>
        <hr>
      </div>
      <?php if (empty($docs)): ?>
      <div class="card-body text-muted small">書類チェックリストなし</div>
      <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($docs as $doc): ?>
        <li class="list-group-item px-3 py-1">
          <div class="d-flex align-items-center justify-content-between gap-2">
            <form method="post" class="d-flex align-items-center gap-2 flex-1">
              <input type="hidden" name="action" value="toggle_doc">
              <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
              <button type="submit" class="btn btn-sm p-0 border-0" title="切替">
                <i class="bi <?= $doc['is_received'] ? 'bi-check-square-fill text-success' : 'bi-square text-muted' ?> fs-5"></i>
              </button>
              <span class="small <?= $doc['is_received'] ? 'text-decoration-line-through text-muted' : '' ?>">
                <?= h($doc['doc_name']) ?>
              </span>
              <?php if ($doc['is_received'] && $doc['received_at']): ?>
              <small class="text-muted ms-auto"><?= format_date($doc['received_at']) ?></small>
              <?php endif; ?>
            </form>
            <form method="post">
              <input type="hidden" name="action" value="delete_doc">
              <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
              <button type="submit" class="btn btn-sm p-0 border-0 text-danger" title="削除">
                <i class="bi bi-x"></i>
              </button>
            </form>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

    <!-- 報酬計算 -->
    <div class="card mb-4">
      <div class="card-header bg-white py-2 d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-currency-yen text-warning me-2"></i>報酬・費用</span>
        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="collapse" data-bs-target="#billingForm">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
      <div class="collapse px-3 pt-2" id="billingForm">
        <form method="post" class="pb-3">
          <input type="hidden" name="action" value="add_billing">
          <div class="row g-2">
            <div class="col-sm-6">
              <select name="billing_type" class="form-select form-select-sm">
                <?php foreach (BILLING_TYPES as $bt): ?><option><?= $bt ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <input type="number" name="amount" class="form-control form-control-sm" placeholder="金額（円）" required>
            </div>
            <div class="col-sm-6">
              <input type="date" name="billing_date" class="form-control form-control-sm">
            </div>
            <div class="col-sm-6">
              <input type="text" name="notes" class="form-control form-control-sm" placeholder="メモ">
            </div>
          </div>
          <button type="submit" class="btn btn-warning btn-sm mt-2 w-100">追加</button>
        </form>
        <hr>
      </div>
      <?php if (empty($billings)): ?>
      <div class="card-body text-muted small">報酬情報なし</div>
      <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($billings as $b): ?>
        <li class="list-group-item px-3 py-2 d-flex align-items-center justify-content-between">
          <div>
            <span class="badge bg-light text-dark me-1"><?= h($b['billing_type']) ?></span>
            <span class="fw-semibold">¥<?= number_format($b['amount']) ?></span>
            <?php if ($b['notes']): ?><small class="text-muted ms-1"><?= h($b['notes']) ?></small><?php endif; ?>
            <?php if ($b['billing_date']): ?><div class="small text-muted"><?= format_date($b['billing_date']) ?></div><?php endif; ?>
          </div>
          <form method="post">
            <input type="hidden" name="action" value="toggle_billing_paid">
            <input type="hidden" name="billing_id" value="<?= $b['id'] ?>">
            <button type="submit" class="btn btn-sm <?= $b['is_paid'] ? 'btn-success' : 'btn-outline-secondary' ?>">
              <?= $b['is_paid'] ? '収納済' : '未収納' ?>
            </button>
          </form>
        </li>
        <?php endforeach; ?>
        <li class="list-group-item px-3 py-2 bg-light d-flex justify-content-between">
          <span class="fw-semibold">合計</span>
          <span class="fw-bold">¥<?= number_format($total_bill) ?>
            <small class="text-success ms-2">収納: ¥<?= number_format($total_paid) ?></small>
          </span>
        </li>
      </ul>
      <?php endif; ?>
    </div>

    <!-- 進捗タイムライン -->
    <div class="card">
      <div class="card-header bg-white py-2 d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-clock-history text-primary me-2"></i>進捗タイムライン</span>
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#actForm">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
      <div class="collapse px-3 pt-2" id="actForm">
        <form method="post" class="pb-3">
          <input type="hidden" name="action" value="add_activity">
          <div class="row g-2">
            <div class="col-sm-6">
              <select name="activity_type" class="form-select form-select-sm">
                <?php foreach (ACTIVITY_TYPES as $at): ?><option><?= $at ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6"><input type="date" name="activity_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-12"><textarea name="content" class="form-control form-control-sm" rows="2" placeholder="内容" required></textarea></div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm mt-2 w-100">追加</button>
        </form>
        <hr>
      </div>
      <div class="card-body">
        <?php if (empty($activities)): ?>
        <div class="text-muted small">活動記録なし</div>
        <?php else: ?>
        <div class="timeline">
          <?php foreach ($activities as $a): ?>
          <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="small fw-semibold"><?= h($a['activity_type']) ?> <span class="text-muted fw-normal"><?= format_date($a['activity_date']) ?></span></div>
            <div class="small"><?= nl2br(h($a['content'])) ?></div>
            <?php if ($a['created_by']): ?><div style="font-size:.75rem" class="text-muted"><?= h($a['created_by']) ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php require 'includes/footer.php'; ?>
