<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/includes/crm_functions.php';
require_once __DIR__ . '/includes/gcal_functions.php';
require_admin();

$id       = (int)($_GET['id'] ?? 0);
$case_obj = crm_get_case($id);
if (!$case_obj) { header('Location: '.CRM_URL.'/cases.php'); exit; }

$nav        = 'cases';
$page_title = $case_obj['case_name'];

$deadlines  = crm_get_deadlines(['case_id' => $id]);
$activities = crm_get_activities(['case_id' => $id]);
$billings   = crm_get_billings($id);
$bill_sum   = crm_get_billing_summary($id);
$documents  = crm_get_documents($id);

// 期日追加
$gcal_conflict = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_a']??'')==='add_deadline') {
    $d = $_POST; $d["case_id"] = $id; $d["is_done"] = 0;
    if (!empty($d["dl_date"])) $d["deadline_date"] = $d["dl_date"] . " " . ($d["dl_time"] ?? "00:00") . ":00";
    if (!empty($d['title']) && !empty($d['deadline_date'])) {
        if (empty($d['confirmed']) && _gcal_enabled()) {
            $gcal_conflict = gcal_check_conflict($d['deadline_date']);
        }
        if (empty($gcal_conflict)) {
            crm_save_deadline($d);
            header('Location: '.CRM_URL.'/case_view.php?id='.$id.'#deadlines'); exit;
        }
    }
}
// 期日 完了toggle
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_a']??'')==='toggle_dl') {
    $dl = crm_get_deadline((int)$_POST['dl_id']);
    if ($dl) { $dl['is_done'] = $dl['is_done'] ? 0 : 1; crm_save_deadline($dl, (int)$_POST['dl_id']); }
    header('Location: '.CRM_URL.'/case_view.php?id='.$id.'#deadlines'); exit;
}
// 期日削除
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_a']??'')==='delete_dl') {
    $dl_id = (int)$_POST['dl_id'];
    $dl = crm_get_deadline($dl_id);
    if ($dl && !empty($dl['gcal_event_id']) && _gcal_enabled()) {
        gcal_delete_event($dl['gcal_event_id']);
    }
    get_db()->prepare("DELETE FROM crm_deadlines WHERE id=?")->execute([$dl_id]);
    header('Location: '.CRM_URL.'/case_view.php?id='.$id.'#deadlines'); exit;
}
// 活動記録追加
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_a']??'')==='add_activity') {
    $d = $_POST; $d['case_id'] = $id; $d['client_id'] = $case_obj['client_id'];
    if (!empty($d['activity_at'])) crm_save_activity($d);
    header('Location: '.CRM_URL.'/case_view.php?id='.$id.'#activities'); exit;
}
// 請求追加
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_a']??'')==='add_billing') {
    $d = $_POST; $d['case_id'] = $id;
    if (!empty($d['title'])) crm_save_billing($d);
    header('Location: '.CRM_URL.'/case_view.php?id='.$id.'#billing'); exit;
}
// 請求 入金更新
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_a']??'')==='mark_paid') {
    get_db()->prepare("UPDATE crm_billing SET is_paid=1, paid_date=CURDATE() WHERE id=?")->execute([(int)$_POST['bid']]);
    header('Location: '.CRM_URL.'/case_view.php?id='.$id.'#billing'); exit;
}
// 書類アップロード
$doc_err = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_a']??'')==='upload_doc') {
    if (!empty($_FILES['docfile']['name'])) {
        $doc_err = crm_upload_document($id, $_FILES['docfile'], $_POST['doc_memo'] ?? '');
        if (!$doc_err) { header('Location: '.CRM_URL.'/case_view.php?id='.$id.'#documents'); exit; }
    }
}
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>保存しました。<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- 案件サマリ -->
<div class="page-card mb-3">
  <div class="row">
    <div class="col-md-8">
      <div class="d-flex align-items-center gap-2 mb-2">
        <?= crm_case_badge($case_obj['status']) ?>
        <span class="badge badge-type"><?= crm_h(CASE_TYPE_MAP[$case_obj['case_type']] ?? '') ?></span>
        <?php if ($case_obj['case_number']): ?><span class="text-muted small"><?= crm_h($case_obj['case_number']) ?></span><?php endif; ?>
      </div>
      <table class="table table-sm table-borderless mb-0">
        <tr><th class="text-muted" style="width:25%">依頼者</th><td><a href="<?= CRM_URL ?>/client_view.php?id=<?= $case_obj['client_id'] ?>"><?= crm_h($case_obj['client_name']) ?></a></td></tr>
        <?php if ($case_obj['opponent']): ?><tr><th class="text-muted">相手方</th><td><?= crm_h($case_obj['opponent']) ?></td></tr><?php endif; ?>
        <?php if ($case_obj['court_name']): ?><tr><th class="text-muted">裁判所</th><td><?= crm_h($case_obj['court_name']) ?></td></tr><?php endif; ?>
        <tr><th class="text-muted">開始日</th><td><?= crm_fmt_date($case_obj['opened_date']) ?><?= $case_obj['closed_date'] ? ' 〜 '.crm_fmt_date($case_obj['closed_date']) : '' ?></td></tr>
      </table>
    </div>
    <div class="col-md-4 text-md-end">
      <div class="mb-2">
        <div class="text-muted small">請求合計</div>
        <div class="h5 fw-bold"><?= crm_fmt_money((int)$bill_sum['total']) ?></div>
        <div class="small text-muted">入金済: <?= crm_fmt_money((int)$bill_sum['paid']) ?> / 未入金: <?= crm_fmt_money((int)$bill_sum['total'] - (int)$bill_sum['paid']) ?></div>
      </div>
      <a href="<?= CRM_URL ?>/case_edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">案件編集</a>
    </div>
  </div>
  <?php if ($case_obj['memo']): ?>
  <hr class="my-2"><div class="small text-muted" style="white-space:pre-wrap"><?= crm_h($case_obj['memo']) ?></div>
  <?php endif; ?>
</div>

<!-- タブ -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#deadlines"><i class="bi bi-calendar-event me-1"></i>期日 (<?= count($deadlines) ?>)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#activities"><i class="bi bi-journal-text me-1"></i>活動記録 (<?= count($activities) ?>)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#billing"><i class="bi bi-currency-yen me-1"></i>請求 (<?= count($billings) ?>)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#documents"><i class="bi bi-files me-1"></i>書類 (<?= count($documents) ?>)</a></li>
</ul>

<div class="tab-content">
  <!-- 期日タブ -->
  <div class="tab-pane fade show active" id="deadlines">
    <div class="page-card">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="h6 fw-bold mb-0">期日追加</h3>
        <?php if (gcal_is_configured()): ?>
        <span class="badge bg-success"><i class="bi bi-calendar-check me-1"></i>Googleカレンダーに自動登録</span>
        <?php else: ?>
        <a href="<?= CRM_URL ?>/gcal/setup.php" class="badge bg-secondary text-decoration-none"><i class="bi bi-calendar-x me-1"></i>Googleカレンダー未連携</a>
        <?php endif; ?>
      </div>
      <?php if (!empty($gcal_conflict)): ?>
      <div class="alert alert-warning mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <strong>その日時にはすでに予定が入っています：</strong>
        </div>
        <ul class="mb-2">
          <?php foreach ($gcal_conflict as $ev): ?><li><?= crm_h($ev) ?></li><?php endforeach; ?>
        </ul>
        <p class="mb-0 small"><strong>↓ 日時を変更して「登録する」を押してください。</strong>そのまま登録したい場合は「重複を無視して登録する」をクリックしてください。</p>
      </div>
      <?php endif; ?>
      <form method="post" class="row g-2 mb-4">
        <input type="hidden" name="_a" value="add_deadline">
        <?php $pf = !empty($gcal_conflict) ? $_POST : []; ?>
        <div class="col-md-3"><input type="text" name="title" class="form-control form-control-sm" placeholder="内容（例: 第1回口頭弁論）" value="<?= crm_h($pf['title'] ?? '') ?>" required></div>
        <div class="col-md-2"><input type="date" name="dl_date" class="form-control form-control-sm <?php if(!empty($gcal_conflict)) echo 'border-warning border-2'; ?>" value="<?= crm_h($pf['dl_date'] ?? '') ?>" required></div>
        <div class="col-md-2"><input type="time" name="dl_time" class="form-control form-control-sm <?php if(!empty($gcal_conflict)) echo 'border-warning border-2'; ?>" value="<?= crm_h($pf['dl_time'] ?? '') ?>"></div>
        <div class="col-md-2">
          <select name="deadline_type" class="form-select form-select-sm">
            <?php foreach (DEADLINE_TYPE_MAP as $k => $v): ?><option value="<?= $k ?>" <?= ($pf['deadline_type'] ?? '') === $k ? 'selected' : '' ?>><?= crm_h($v) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3"><input type="text" name="memo" class="form-control form-control-sm" placeholder="メモ（任意）" value="<?= crm_h($pf['memo'] ?? '') ?>"></div>
        <div class="col-12 d-flex gap-2 flex-wrap">
          <button class="btn btn-primary px-4">
            <i class="bi bi-check-lg me-1"></i>登録する
            <?php if (gcal_is_configured()): ?><small class="ms-2 opacity-75"><i class="bi bi-calendar-plus"></i> Googleカレンダーにも追加</small><?php endif; ?>
          </button>
          <?php if (!empty($gcal_conflict)): ?>
          <button type="submit" name="confirmed" value="1" class="btn btn-warning px-4">
            <i class="bi bi-calendar-x me-1"></i>重複を無視してこのまま登録する
          </button>
          <?php endif; ?>
        </div>
      </form>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th></th><th>日時</th><th>種別</th><th>内容</th><th>メモ</th><th class="text-center" title="Googleカレンダー"><i class="bi bi-calendar-check"></i></th><th></th></tr></thead>
        <tbody>
        <?php if (empty($deadlines)): ?>
          <tr><td colspan="5" class="text-center text-muted py-3">期日がありません。</td></tr>
        <?php else: foreach ($deadlines as $dl):
          $dt = strtotime($dl['deadline_date']);
          $is_past = $dt < time() && !$dl['is_done'];
        ?>
          <tr class="<?= $dl['is_done'] ? 'text-muted' : ($is_past ? 'table-danger' : '') ?>">
            <td>
              <form method="post" class="d-inline">
                <input type="hidden" name="_a" value="toggle_dl">
                <input type="hidden" name="dl_id" value="<?= $dl['id'] ?>">
                <button class="btn btn-sm py-0 px-1 <?= $dl['is_done'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                  <i class="bi <?= $dl['is_done'] ? 'bi-check-square' : 'bi-square' ?>"></i>
                </button>
              </form>
            </td>
            <td><?= date('Y/m/d H:i', $dt) ?>
              <?php if (!$dl['is_done'] && $is_past): ?><span class="badge bg-danger ms-1">超過</span>
              <?php elseif (!$dl['is_done'] && ($dt-time())/86400 <= 3): ?><span class="badge bg-warning text-dark ms-1"><?= (int)(($dt-time())/86400) ?>日後</span>
              <?php endif; ?>
            </td>
            <td><?= crm_h(DEADLINE_TYPE_MAP[$dl['deadline_type']] ?? '') ?></td>
            <td><?= $dl['is_done'] ? '<s>'.crm_h($dl['title']).'</s>' : crm_h($dl['title']) ?></td>
            <td class="text-muted small"><?= crm_h($dl['memo']) ?></td>
            <td class="text-center">
              <?php if (!empty($dl['gcal_event_id'])): ?><i class="bi bi-calendar-check text-success" title="Googleカレンダー登録済"></i><?php else: ?><i class="bi bi-calendar-x text-muted opacity-25"></i><?php endif; ?>
            </td>
            <td>
              <form method="post" class="d-inline" onsubmit="return confirm('この期日を削除しますか？')">
                <input type="hidden" name="_a" value="delete_dl">
                <input type="hidden" name="dl_id" value="<?= $dl['id'] ?>">
                <button class="btn btn-sm py-0 px-1 btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 活動記録タブ -->
  <div class="tab-pane fade" id="activities">
    <div class="page-card">
      <h3 class="h6 fw-bold mb-3">活動記録追加</h3>
      <form method="post" class="mb-4">
        <input type="hidden" name="_a" value="add_activity">
        <div class="row g-2">
          <div class="col-md-2">
            <select name="activity_type" class="form-select form-select-sm">
              <?php foreach (ACTIVITY_TYPE_MAP as $k => $v): ?><option value="<?= $k ?>"><?= crm_h($v) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3"><input type="datetime-local" name="activity_at" class="form-control form-control-sm" value="<?= date('Y-m-d\TH:i') ?>" required></div>
          <div class="col-md-2"><input type="number" name="duration_min" class="form-control form-control-sm" placeholder="所要時間（分）" min="0"></div>
          <div class="col-md-5"><input type="text" name="title" class="form-control form-control-sm" placeholder="タイトル"></div>
          <div class="col-md-6"><textarea name="content" class="form-control form-control-sm" rows="2" placeholder="内容"></textarea></div>
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
          <div class="d-flex justify-content-between mb-1">
            <div class="d-flex align-items-center gap-2">
              <span class="badge badge-type"><?= crm_h(ACTIVITY_TYPE_MAP[$a['activity_type']] ?? '') ?></span>
              <?php if ($a['duration_min']): ?><span class="badge bg-light text-muted"><?= $a['duration_min'] ?>分</span><?php endif; ?>
              <?php if ($a['title']): ?><strong><?= crm_h($a['title']) ?></strong><?php endif; ?>
            </div>
            <small class="text-muted"><?= crm_fmt_dt($a['activity_at']) ?></small>
          </div>
          <?php if ($a['content']): ?><div class="small mb-1" style="white-space:pre-wrap"><?= crm_h($a['content']) ?></div><?php endif; ?>
          <?php if ($a['result']): ?><div class="small text-muted border-start border-2 ps-2" style="white-space:pre-wrap"><?= crm_h($a['result']) ?></div><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>

  <!-- 請求タブ -->
  <div class="tab-pane fade" id="billing">
    <div class="page-card">
      <h3 class="h6 fw-bold mb-3">請求追加</h3>
      <form method="post" class="row g-2 mb-4">
        <input type="hidden" name="_a" value="add_billing">
        <div class="col-md-2">
          <select name="billing_type" class="form-select form-select-sm">
            <?php foreach (BILLING_TYPE_MAP as $k => $v): ?><option value="<?= $k ?>"><?= crm_h($v) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3"><input type="text" name="title" class="form-control form-control-sm" placeholder="項目名" required></div>
        <div class="col-md-2"><input type="number" name="amount" class="form-control form-control-sm" placeholder="金額（円）" min="0"></div>
        <div class="col-md-2"><input type="number" name="hours" class="form-control form-control-sm" placeholder="時間（h）" step="0.25" min="0"></div>
        <div class="col-md-2"><input type="date" name="billed_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
        <div class="col-auto d-flex align-items-center gap-2">
          <div class="form-check mb-0"><input type="checkbox" name="is_paid" value="1" id="chk_paid" class="form-check-input"><label for="chk_paid" class="form-check-label small">入金済</label></div>
          <button class="btn btn-sm btn-primary">追加</button>
        </div>
      </form>
      <?php if (empty($billings)): ?>
      <p class="text-muted small">請求記録がありません。</p>
      <?php else: ?>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>種別</th><th>項目</th><th>金額</th><th>時間</th><th>請求日</th><th>入金</th></tr></thead>
        <tbody>
        <?php $total = 0; foreach ($billings as $b): $total += (int)$b['amount']; ?>
        <tr>
          <td><?= crm_h(BILLING_TYPE_MAP[$b['billing_type']] ?? '') ?></td>
          <td><?= crm_h($b['title']) ?></td>
          <td class="fw-bold"><?= crm_fmt_money((int)$b['amount']) ?></td>
          <td><?= $b['hours'] ? $b['hours'].'h' : '' ?></td>
          <td><?= crm_fmt_date($b['billed_date']) ?></td>
          <td>
            <?php if ($b['is_paid']): ?>
            <span class="badge bg-success">入金済</span> <small class="text-muted"><?= crm_fmt_date($b['paid_date']) ?></small>
            <?php else: ?>
            <span class="badge bg-danger">未入金</span>
            <form method="post" class="d-inline ms-1">
              <input type="hidden" name="_a" value="mark_paid">
              <input type="hidden" name="bid" value="<?= $b['id'] ?>">
              <button class="btn btn-xs btn-sm btn-success py-0 px-1">入金</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-light fw-bold"><td colspan="2" class="text-end">合計</td><td><?= crm_fmt_money($total) ?></td><td colspan="3"></td></tr>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- 書類タブ -->
  <div class="tab-pane fade" id="documents">
    <div class="page-card">
      <h3 class="h6 fw-bold mb-3">書類アップロード</h3>
      <?php if ($doc_err): ?><div class="alert alert-danger"><?= crm_h($doc_err) ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="row g-2 mb-4">
        <input type="hidden" name="_a" value="upload_doc">
        <div class="col-md-5"><input type="file" name="docfile" class="form-control form-control-sm" required></div>
        <div class="col-md-4"><input type="text" name="doc_memo" class="form-control form-control-sm" placeholder="メモ（任意）"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary">アップロード</button></div>
        <div class="col-12 small text-muted">PDF・Word・Excel・画像（JPG/PNG）・テキスト ／ 最大20MB</div>
      </form>
      <?php if (empty($documents)): ?>
      <p class="text-muted small">書類がありません。</p>
      <?php else: ?>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>ファイル名</th><th>サイズ</th><th>日時</th><th>メモ</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($documents as $doc): ?>
        <tr>
          <td><i class="bi bi-file-earmark me-1 text-muted"></i><?= crm_h($doc['original_name']) ?></td>
          <td class="text-muted small"><?= round($doc['file_size']/1024) ?>KB</td>
          <td><?= crm_fmt_dt($doc['created_at']) ?></td>
          <td class="text-muted small"><?= crm_h($doc['memo']) ?></td>
          <td><a href="<?= CRM_URL ?>/doc_download.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="bi bi-download"></i></a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const hash = location.hash;
if (hash) { const t = document.querySelector('[href="'+hash+'"]'); if(t) new bootstrap.Tab(t).show(); }
</script>

<?php require __DIR__ . '/includes/_footer.php'; ?>
