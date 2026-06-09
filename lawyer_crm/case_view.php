<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$lc_user = lc_require_login();

$id = (int)($_GET['id'] ?? 0);
$case_obj = get_case($id);
if (!$case_obj) { header('Location: ' . LC_BASE_URL . '/cases.php'); exit; }

$page_title = h($case_obj['case_name']);
$page_nav   = 'cases';

$deadlines  = get_deadlines(['case_id' => $id]);
$activities = get_activities(['case_id' => $id]);
$billings   = get_billings($id);
$billing_summary = get_billing_summary($id);
$documents  = get_documents($id);

// 活動記録追加
$act_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add_activity') {
    $d = $_POST;
    if (empty(trim($d['activity_at'] ?? ''))) $act_errors[] = '日時は必須です。';
    if (empty($d['activity_type'])) $act_errors[] = '種別は必須です。';
    if (empty($act_errors)) {
        $d['case_id']   = $id;
        $d['client_id'] = $case_obj['client_id'];
        $d['user_id']   = $lc_user['id'];
        save_activity($d);
        header('Location: ' . LC_BASE_URL . '/case_view.php?id=' . $id . '#activities'); exit;
    }
}

// 期日追加
$dl_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add_deadline') {
    $d = $_POST;
    if (empty(trim($d['title'] ?? ''))) $dl_errors[] = 'タイトルは必須です。';
    if (empty($d['deadline_date'])) $dl_errors[] = '日時は必須です。';
    if (empty($dl_errors)) {
        $d['case_id'] = $id;
        $d['is_done'] = 0;
        save_deadline($d);
        header('Location: ' . LC_BASE_URL . '/case_view.php?id=' . $id . '#deadlines'); exit;
    }
}

// 期日完了toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'toggle_deadline') {
    $dl = get_deadline((int)$_POST['dl_id']);
    if ($dl) {
        $dl['is_done'] = $dl['is_done'] ? 0 : 1;
        save_deadline($dl, (int)$_POST['dl_id']);
    }
    header('Location: ' . LC_BASE_URL . '/case_view.php?id=' . $id . '#deadlines'); exit;
}

// 請求追加
$bill_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add_billing') {
    $d = $_POST;
    if (empty(trim($d['title'] ?? ''))) $bill_errors[] = '項目名は必須です。';
    if (empty($dl_errors)) {
        $d['case_id'] = $id;
        save_billing($d);
        header('Location: ' . LC_BASE_URL . '/case_view.php?id=' . $id . '#billing'); exit;
    }
}

// 書類アップロード
$doc_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'upload_doc') {
    if (!empty($_FILES['docfile']['name'])) {
        $doc_error = upload_document($id, $_FILES['docfile'], $lc_user['id'], $_POST['doc_memo'] ?? '');
        if (!$doc_error) { header('Location: ' . LC_BASE_URL . '/case_view.php?id=' . $id . '#documents'); exit; }
    }
}
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>保存しました。<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- 案件サマリ -->
<div class="page-card mb-3">
  <div class="row align-items-start">
    <div class="col-md-8">
      <div class="d-flex align-items-center gap-2 mb-2">
        <?= case_status_badge($case_obj['status']) ?>
        <span class="badge badge-case-type"><?= h(lc_case_type_map()[$case_obj['case_type']] ?? '') ?></span>
        <?php if ($case_obj['case_number']): ?><span class="text-muted small"><?= h($case_obj['case_number']) ?></span><?php endif; ?>
      </div>
      <table class="table table-sm table-borderless mb-0">
        <tr><th class="text-muted w-30">依頼者</th><td><a href="<?= LC_BASE_URL ?>/client_view.php?id=<?= $case_obj['client_id'] ?>"><?= h($case_obj['client_name']) ?></a></td></tr>
        <tr><th class="text-muted">担当弁護士</th><td><?= h($case_obj['lawyer_name'] ?? '未割当') ?></td></tr>
        <?php if ($case_obj['opponent']): ?><tr><th class="text-muted">相手方</th><td><?= h($case_obj['opponent']) ?></td></tr><?php endif; ?>
        <?php if ($case_obj['court_name']): ?><tr><th class="text-muted">裁判所</th><td><?= h($case_obj['court_name']) ?></td></tr><?php endif; ?>
        <tr><th class="text-muted">開始日</th><td><?= fmt_date($case_obj['opened_date']) ?><?= $case_obj['closed_date'] ? ' 〜 ' . fmt_date($case_obj['closed_date']) : '' ?></td></tr>
      </table>
    </div>
    <div class="col-md-4 text-md-end">
      <div class="mb-2">
        <div class="text-muted small">請求合計</div>
        <div class="h5 fw-bold"><?= fmt_money((int)($billing_summary['total'] ?? 0)) ?></div>
        <div class="small">入金済: <?= fmt_money((int)($billing_summary['paid'] ?? 0)) ?> / 未入金: <?= fmt_money((int)($billing_summary['total'] ?? 0) - (int)($billing_summary['paid'] ?? 0)) ?></div>
      </div>
      <a href="<?= LC_BASE_URL ?>/case_edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">案件編集</a>
    </div>
  </div>
  <?php if ($case_obj['memo']): ?>
  <hr class="my-2">
  <div class="small text-muted" style="white-space:pre-wrap"><?= h($case_obj['memo']) ?></div>
  <?php endif; ?>
</div>

<!-- タブ -->
<ul class="nav nav-tabs mb-3" id="caseTab">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#deadlines"><i class="bi bi-calendar-event me-1"></i>期日 (<?= count($deadlines) ?>)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#activities"><i class="bi bi-journal-text me-1"></i>活動記録 (<?= count($activities) ?>)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#billing"><i class="bi bi-currency-yen me-1"></i>請求 (<?= count($billings) ?>)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#documents"><i class="bi bi-files me-1"></i>書類 (<?= count($documents) ?>)</a></li>
</ul>

<div class="tab-content">
  <!-- 期日 -->
  <div class="tab-pane fade show active" id="deadlines">
    <div class="page-card">
      <h3 class="h6 fw-bold mb-3">期日追加</h3>
      <?php if ($dl_errors): ?><div class="alert alert-danger small"><?= implode('<br>', array_map('h', $dl_errors)) ?></div><?php endif; ?>
      <form method="post" class="row g-2 mb-4">
        <input type="hidden" name="_action" value="add_deadline">
        <div class="col-md-3"><input type="text" name="title" class="form-control form-control-sm" placeholder="タイトル（例: 第1回口頭弁論）" required></div>
        <div class="col-md-3"><input type="datetime-local" name="deadline_date" class="form-control form-control-sm" required></div>
        <div class="col-md-2">
          <select name="deadline_type" class="form-select form-select-sm">
            <?php foreach (DEADLINE_TYPE_MAP as $k => $v): ?><option value="<?= $k ?>"><?= h($v) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3"><input type="text" name="memo" class="form-control form-control-sm" placeholder="メモ（任意）"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary">追加</button></div>
      </form>

      <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th></th><th>日時</th><th>種別</th><th>タイトル</th><th>メモ</th></tr></thead>
        <tbody>
        <?php if (empty($deadlines)): ?>
          <tr><td colspan="5" class="text-center text-muted py-3">期日がありません。</td></tr>
        <?php else: ?>
          <?php foreach ($deadlines as $dl):
            $dt = strtotime($dl['deadline_date']);
            $is_past = $dt < time() && !$dl['is_done'];
          ?>
          <tr class="<?= $dl['is_done'] ? 'text-muted' : ($is_past ? 'table-danger' : '') ?>">
            <td>
              <form method="post" class="d-inline">
                <input type="hidden" name="_action" value="toggle_deadline">
                <input type="hidden" name="dl_id" value="<?= $dl['id'] ?>">
                <button class="btn btn-xs btn-sm py-0 px-1 <?= $dl['is_done'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                  <i class="bi <?= $dl['is_done'] ? 'bi-check-square' : 'bi-square' ?>"></i>
                </button>
              </form>
            </td>
            <td><?= date('Y/m/d H:i', $dt) ?></td>
            <td><?= h(DEADLINE_TYPE_MAP[$dl['deadline_type']] ?? '') ?></td>
            <td><?= $dl['is_done'] ? '<s>' . h($dl['title']) . '</s>' : h($dl['title']) ?></td>
            <td class="text-muted small"><?= h($dl['memo']) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 活動記録 -->
  <div class="tab-pane fade" id="activities">
    <div class="page-card">
      <h3 class="h6 fw-bold mb-3">活動記録追加</h3>
      <?php if ($act_errors): ?><div class="alert alert-danger small"><?= implode('<br>', array_map('h', $act_errors)) ?></div><?php endif; ?>
      <form method="post" class="mb-4">
        <input type="hidden" name="_action" value="add_activity">
        <div class="row g-2">
          <div class="col-md-2">
            <select name="activity_type" class="form-select form-select-sm" required>
              <?php foreach (ACTIVITY_TYPE_MAP as $k => $v): ?><option value="<?= $k ?>"><?= h($v) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3"><input type="datetime-local" name="activity_at" class="form-control form-control-sm" value="<?= date('Y-m-d\TH:i') ?>" required></div>
          <div class="col-md-2"><input type="number" name="duration_min" class="form-control form-control-sm" placeholder="所要時間（分）" min="0"></div>
          <div class="col-md-5"><input type="text" name="title" class="form-control form-control-sm" placeholder="タイトル"></div>
          <div class="col-md-6"><textarea name="content" class="form-control form-control-sm" rows="2" placeholder="活動内容"></textarea></div>
          <div class="col-md-6"><textarea name="result" class="form-control form-control-sm" rows="2" placeholder="結果・対応状況"></textarea></div>
          <div class="col-auto"><button class="btn btn-sm btn-primary">記録</button></div>
        </div>
      </form>

      <?php if (empty($activities)): ?>
      <p class="text-muted small">活動記録がありません。</p>
      <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($activities as $a): ?>
        <li class="list-group-item px-0 py-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <div class="d-flex align-items-center gap-2">
              <span class="badge" style="background:#e8f0fe;color:#1a3a5c"><?= h(ACTIVITY_TYPE_MAP[$a['activity_type']] ?? '') ?></span>
              <?php if ($a['duration_min']): ?><span class="badge bg-light text-muted"><?= $a['duration_min'] ?>分</span><?php endif; ?>
              <?php if ($a['title']): ?><strong><?= h($a['title']) ?></strong><?php endif; ?>
            </div>
            <small class="text-muted"><?= fmt_datetime($a['activity_at']) ?> | <?= h($a['user_name']) ?></small>
          </div>
          <?php if ($a['content']): ?><div class="small mb-1" style="white-space:pre-wrap"><?= h($a['content']) ?></div><?php endif; ?>
          <?php if ($a['result']): ?><div class="small text-muted border-start border-2 ps-2" style="white-space:pre-wrap"><?= h($a['result']) ?></div><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>

  <!-- 請求 -->
  <div class="tab-pane fade" id="billing">
    <div class="page-card">
      <h3 class="h6 fw-bold mb-3">請求追加</h3>
      <?php if ($bill_errors): ?><div class="alert alert-danger small"><?= implode('<br>', array_map('h', $bill_errors)) ?></div><?php endif; ?>
      <form method="post" class="row g-2 mb-4">
        <input type="hidden" name="_action" value="add_billing">
        <div class="col-md-2">
          <select name="billing_type" class="form-select form-select-sm">
            <?php foreach (BILLING_TYPE_MAP as $k => $v): ?><option value="<?= $k ?>"><?= h($v) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3"><input type="text" name="title" class="form-control form-control-sm" placeholder="項目名" required></div>
        <div class="col-md-2"><input type="number" name="amount" class="form-control form-control-sm" placeholder="金額（円）" min="0"></div>
        <div class="col-md-2"><input type="number" name="hours" class="form-control form-control-sm" placeholder="時間（h）" step="0.25" min="0"></div>
        <div class="col-md-2"><input type="date" name="billed_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
        <div class="col-auto d-flex align-items-center gap-2">
          <div class="form-check mb-0">
            <input type="checkbox" name="is_paid" value="1" id="chk_paid" class="form-check-input">
            <label for="chk_paid" class="form-check-label small">入金済</label>
          </div>
          <button class="btn btn-sm btn-primary">追加</button>
        </div>
      </form>

      <?php if (empty($billings)): ?>
      <p class="text-muted small">請求記録がありません。</p>
      <?php else: ?>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>種別</th><th>項目</th><th>金額</th><th>時間</th><th>請求日</th><th>入金</th></tr></thead>
        <tbody>
        <?php $total = 0; ?>
        <?php foreach ($billings as $b):
          $total += (int)$b['amount'];
        ?>
        <tr>
          <td><?= h(BILLING_TYPE_MAP[$b['billing_type']] ?? '') ?></td>
          <td><?= h($b['title']) ?></td>
          <td class="fw-bold"><?= fmt_money((int)$b['amount']) ?></td>
          <td><?= $b['hours'] ? $b['hours'] . 'h' : '' ?></td>
          <td><?= fmt_date($b['billed_date']) ?></td>
          <td><?= billing_paid_badge((int)$b['is_paid']) ?><?= $b['paid_date'] ? ' <small class="text-muted">'.fmt_date($b['paid_date']).'</small>' : '' ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-light fw-bold">
          <td colspan="2" class="text-end">合計</td>
          <td><?= fmt_money($total) ?></td>
          <td colspan="3"></td>
        </tr>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- 書類 -->
  <div class="tab-pane fade" id="documents">
    <div class="page-card">
      <h3 class="h6 fw-bold mb-3">書類アップロード</h3>
      <?php if ($doc_error): ?><div class="alert alert-danger"><?= h($doc_error) ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="row g-2 mb-4">
        <input type="hidden" name="_action" value="upload_doc">
        <div class="col-md-5"><input type="file" name="docfile" class="form-control form-control-sm" required></div>
        <div class="col-md-4"><input type="text" name="doc_memo" class="form-control form-control-sm" placeholder="メモ（任意）"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary">アップロード</button></div>
        <div class="col-12 small text-muted">対応形式: PDF, Word, Excel, 画像（JPG/PNG）, テキスト ／ 最大20MB</div>
      </form>

      <?php if (empty($documents)): ?>
      <p class="text-muted small">書類がありません。</p>
      <?php else: ?>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>ファイル名</th><th>サイズ</th><th>アップロード者</th><th>日時</th><th>メモ</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($documents as $doc): ?>
        <tr>
          <td><i class="bi bi-file-earmark me-1 text-muted"></i><?= h($doc['original_name']) ?></td>
          <td class="text-muted small"><?= round($doc['file_size'] / 1024) ?>KB</td>
          <td><?= h($doc['uploader_name']) ?></td>
          <td><?= fmt_datetime($doc['created_at']) ?></td>
          <td class="text-muted small"><?= h($doc['memo']) ?></td>
          <td><a href="<?= LC_BASE_URL ?>/doc_download.php?id=<?= $doc['id'] ?>" class="btn btn-xs btn-sm btn-outline-secondary py-0 px-2"><i class="bi bi-download"></i></a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// URLハッシュに応じてタブを開く
const hash = location.hash;
if (hash) {
    const tab = document.querySelector('[href="' + hash + '"]');
    if (tab) new bootstrap.Tab(tab).show();
}
</script>

<?php require __DIR__ . '/includes/_footer.php'; ?>
