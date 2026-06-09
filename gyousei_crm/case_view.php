<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$gc_user = gc_require_login();

$id = (int)($_GET['id'] ?? 0);
$cs = $id ? get_case($id) : null;
if (!$cs) { header('Location: '.GC_BASE_URL.'/cases.php'); exit; }

// POST処理
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    if ($act === 'add_deadline') {
        save_deadline(['case_id' => $id, 'title' => $_POST['dl_title'], 'deadline_date' => $_POST['dl_date'],
                       'deadline_type' => $_POST['dl_type'], 'memo' => $_POST['dl_memo'] ?? '', 'is_done' => 0]);
        $msg = '期日を追加しました。';
    } elseif ($act === 'done_deadline') {
        $dlid = (int)$_POST['deadline_id'];
        $dl = get_db()->prepare("SELECT * FROM gc_deadlines WHERE id=?");
        $dl->execute([$dlid]); $dl = $dl->fetch();
        if ($dl) save_deadline(array_merge($dl, ['is_done' => 1]), $dlid);
        $msg = '期日を完了にしました。';
    } elseif ($act === 'add_doc') {
        save_checklist_item(['case_id' => $id, 'doc_name' => $_POST['doc_name'],
                             'is_obtained' => 0, 'note' => '', 'sort_order' => (int)$_POST['sort_order']]);
        $msg = '書類を追加しました。';
    } elseif ($act === 'toggle_doc') {
        $docid = (int)$_POST['doc_id'];
        $doc = get_db()->prepare("SELECT * FROM gc_doc_checklist WHERE id=?");
        $doc->execute([$docid]); $doc = $doc->fetch();
        if ($doc) {
            get_db()->prepare("UPDATE gc_doc_checklist SET is_obtained=?,note=? WHERE id=?")
                    ->execute([$doc['is_obtained']?0:1, $_POST['doc_note']??$doc['note'], $docid]);
        }
    } elseif ($act === 'del_doc') {
        get_db()->prepare("DELETE FROM gc_doc_checklist WHERE id=? AND case_id=?")->execute([(int)$_POST['doc_id'], $id]);
        $msg = '書類を削除しました。';
    } elseif ($act === 'add_progress') {
        save_progress(['case_id' => $id, 'user_id' => $gc_user['id'],
                       'progress_type' => $_POST['p_type'], 'content' => $_POST['p_content'],
                       'recorded_at' => $_POST['p_date'] ?? date('Y-m-d H:i:s')]);
        $msg = '進捗を記録しました。';
    } elseif ($act === 'add_billing') {
        save_billing(['case_id' => $id, 'billing_type' => $_POST['b_type'], 'title' => $_POST['b_title'],
                      'amount' => (int)preg_replace('/[^0-9]/', '', $_POST['b_amount']),
                      'billed_date' => $_POST['b_date'] ?? null, 'is_paid' => 0, 'memo' => $_POST['b_memo'] ?? '']);
        $msg = '報酬を追加しました。';
    } elseif ($act === 'paid_billing') {
        $bid = (int)$_POST['billing_id'];
        get_db()->prepare("UPDATE gc_billing SET is_paid=1,paid_date=CURDATE() WHERE id=? AND case_id=?")->execute([$bid, $id]);
        $msg = '入金済みにしました。';
    } elseif ($act === 'del_billing') {
        get_db()->prepare("DELETE FROM gc_billing WHERE id=? AND case_id=?")->execute([(int)$_POST['billing_id'], $id]);
        $msg = '報酬を削除しました。';
    } elseif ($act === 'update_stage') {
        get_db()->prepare("UPDATE gc_cases SET progress_stage=? WHERE id=?")->execute([$_POST['new_stage'], $id]);
        $msg = '進捗段階を更新しました。';
        $cs = get_case($id);
    }
    if ($act !== 'toggle_doc') header('Location: '.GC_BASE_URL.'/case_view.php?id='.$id.'&tab='.($_POST['_tab']??'').'&msg='.urlencode($msg));
    else header('Location: '.GC_BASE_URL.'/case_view.php?id='.$id.'&tab=checklist');
    exit;
}

$tab = $_GET['tab'] ?? 'overview';
$msg = $_GET['msg'] ?? '';

$deadlines  = get_deadlines(['case_id' => $id]);
$checklist  = get_checklist($id);
$prog_list  = get_progress_list($id);
$billings   = get_billings($id);
$bill_sum   = get_billing_summary($id);
$doc_done   = count(array_filter($checklist, fn($d) => $d['is_obtained']));

$page_title = h($cs['case_name']);
$page_nav   = 'cases';
$page_actions = '<a href="'.GC_BASE_URL.'/case_edit.php?id='.$id.'" class="btn btn-sm btn-gc"><i class="bi bi-pencil me-1"></i>案件編集</a>';
?>
<?php require __DIR__ . '/includes/_header.php'; ?>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible"><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success alert-dismissible">保存しました。<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- 案件ヘッダー -->
<div class="page-card mb-3">
  <div class="row align-items-start g-3">
    <div class="col-md-8">
      <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
        <span class="badge badge-case-type"><?= h(CASE_TYPE_MAP[$cs['case_type']] ?? '') ?></span>
        <?= case_status_badge($cs['status']) ?>
        <?= progress_stage_badge($cs['progress_stage']) ?>
        <span class="text-muted small"><?= h($cs['case_number']) ?></span>
      </div>
      <div class="small mb-2">
        <i class="bi bi-person me-1"></i>
        <a href="<?= GC_BASE_URL ?>/client_view.php?id=<?= $cs['client_id'] ?>" class="text-decoration-none fw-bold">
          <?= h($cs['company_name'] ?: $cs['client_name']) ?>
          <?php if ($cs['company_name']): ?>（<?= h($cs['client_name']) ?>）<?php endif; ?>
        </a>
      </div>
      <?php if ($cs['government_office']): ?>
      <div class="small text-muted"><i class="bi bi-building me-1"></i><?= h($cs['government_office']) ?>
        <?php if ($cs['application_number']): ?>&nbsp;受付番号：<strong><?= h($cs['application_number']) ?></strong><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($cs['permit_expiry_date']): ?>
      <div class="small text-warning fw-bold mt-1"><i class="bi bi-clock me-1"></i>許可期限：<?= fmt_date($cs['permit_expiry_date']) ?></div>
      <?php endif; ?>
    </div>
    <div class="col-md-4">
      <form method="post" class="d-flex gap-2 align-items-center justify-content-end">
        <input type="hidden" name="_action" value="update_stage">
        <input type="hidden" name="_tab" value="<?= h($tab) ?>">
        <select name="new_stage" class="form-select form-select-sm" style="max-width:160px">
          <?php foreach (PROGRESS_STAGE_MAP as $k => $v): ?>
          <option value="<?= $k ?>" <?= $cs['progress_stage']===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-gc">段階更新</button>
      </form>
    </div>
  </div>
</div>

<!-- タブ -->
<ul class="nav nav-tabs mb-3">
  <?php
  $tabs = ['overview' => '概要', 'checklist' => '必要書類('.count($checklist).')',
           'progress' => '進捗('.count($prog_list).')', 'deadlines' => '期日('.count($deadlines).')',
           'billing' => '報酬'];
  foreach ($tabs as $k => $v): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab===$k?'active':'' ?>" href="?id=<?= $id ?>&tab=<?= $k ?>"><?= $v ?></a>
  </li>
  <?php endforeach; ?>
</ul>

<!-- 概要タブ -->
<?php if ($tab === 'overview'): ?>
<div class="row g-3">
  <div class="col-md-6">
    <div class="page-card h-100">
      <h3 class="h6 fw-bold mb-3">案件情報</h3>
      <dl class="row small mb-0">
        <dt class="col-5 text-muted">受任日</dt><dd class="col-7"><?= fmt_date($cs['opened_date']) ?></dd>
        <dt class="col-5 text-muted">担当行政書士</dt><dd class="col-7"><?= h($cs['user_name'] ?? '未割当') ?></dd>
        <?php if ($cs['permit_expiry_date']): ?>
        <dt class="col-5 text-muted">許可期限</dt><dd class="col-7 text-warning fw-bold"><?= fmt_date($cs['permit_expiry_date']) ?></dd>
        <?php endif; ?>
        <?php if ($cs['closed_date']): ?>
        <dt class="col-5 text-muted">終結日</dt><dd class="col-7"><?= fmt_date($cs['closed_date']) ?></dd>
        <?php endif; ?>
      </dl>
      <?php if ($cs['memo']): ?>
      <hr><div class="small text-muted"><?= nl2br(h($cs['memo'])) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="page-card h-100">
      <h3 class="h6 fw-bold mb-3">サマリー</h3>
      <div class="row g-2 text-center">
        <div class="col-4">
          <div class="border rounded p-2">
            <div class="text-muted small">書類</div>
            <div class="fw-bold"><?= $doc_done ?>/<?= count($checklist) ?></div>
          </div>
        </div>
        <div class="col-4">
          <div class="border rounded p-2">
            <div class="text-muted small">未完了期日</div>
            <div class="fw-bold text-danger"><?= count(array_filter($deadlines, fn($d) => !$d['is_done'])) ?></div>
          </div>
        </div>
        <div class="col-4">
          <div class="border rounded p-2">
            <div class="text-muted small">未入金</div>
            <div class="fw-bold text-danger" style="font-size:.85rem"><?= fmt_money((int)$bill_sum['total'] - (int)$bill_sum['paid']) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 必要書類タブ -->
<?php elseif ($tab === 'checklist'): ?>
<div class="page-card">
  <div class="d-flex justify-content-between mb-3">
    <h3 class="h6 fw-bold mb-0">必要書類チェックリスト（<?= $doc_done ?>/<?= count($checklist) ?> 取得済み）</h3>
  </div>

  <?php if (!empty($checklist)): ?>
  <div class="table-responsive mb-3">
    <table class="table align-middle mb-0">
      <thead class="table-light"><tr><th style="width:40px">取得</th><th>書類名</th><th>備考</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($checklist as $doc): ?>
      <tr class="<?= $doc['is_obtained'] ? 'table-success' : '' ?>">
        <td class="text-center">
          <form method="post" class="d-inline">
            <input type="hidden" name="_action" value="toggle_doc">
            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
            <input type="hidden" name="doc_note" value="<?= h($doc['note']) ?>">
            <button class="btn btn-sm <?= $doc['is_obtained']?'btn-success':'btn-outline-secondary' ?> py-0 px-1">
              <i class="bi <?= $doc['is_obtained']?'bi-check-lg':'bi-square' ?>"></i>
            </button>
          </form>
        </td>
        <td><?= h($doc['doc_name']) ?></td>
        <td class="text-muted small"><?= h($doc['note']) ?></td>
        <td>
          <form method="post" class="d-inline" onsubmit="return confirm('削除しますか？')">
            <input type="hidden" name="_action" value="del_doc">
            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
            <input type="hidden" name="_tab" value="checklist">
            <button class="btn btn-sm btn-outline-danger py-0 px-1"><i class="bi bi-trash3"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <form method="post" class="row g-2 align-items-end">
    <input type="hidden" name="_action" value="add_doc">
    <input type="hidden" name="_tab" value="checklist">
    <div class="col-md-5">
      <label class="form-label small fw-bold">書類名</label>
      <input type="text" name="doc_name" class="form-control form-control-sm" required placeholder="例：住民票">
    </div>
    <div class="col-auto">
      <label class="form-label small fw-bold">順番</label>
      <input type="number" name="sort_order" class="form-control form-control-sm" value="<?= count($checklist)+1 ?>" style="width:70px">
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-gc"><i class="bi bi-plus-lg me-1"></i>追加</button>
    </div>
  </form>
</div>

<!-- 進捗タブ -->
<?php elseif ($tab === 'progress'): ?>
<div class="page-card">
  <h3 class="h6 fw-bold mb-3">進捗記録</h3>

  <form method="post" class="row g-2 align-items-end mb-4 p-3 bg-light rounded">
    <input type="hidden" name="_action" value="add_progress">
    <input type="hidden" name="_tab" value="progress">
    <div class="col-md-2">
      <label class="form-label small fw-bold">種別</label>
      <select name="p_type" class="form-select form-select-sm">
        <?php foreach (PROGRESS_TYPE_MAP as $k => $v): ?>
        <option value="<?= $k ?>"><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold">日時</label>
      <input type="datetime-local" name="p_date" class="form-control form-control-sm" value="<?= date('Y-m-d\TH:i') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label small fw-bold">内容</label>
      <input type="text" name="p_content" class="form-control form-control-sm" required placeholder="進捗内容を入力">
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-gc"><i class="bi bi-plus-lg me-1"></i>記録</button>
    </div>
  </form>

  <?php if (empty($prog_list)): ?>
    <p class="text-muted small">進捗記録がありません。</p>
  <?php else: ?>
  <div class="timeline">
    <?php foreach ($prog_list as $p):
      $tc = match($p['progress_type']) {
        'approval' => 'text-success', 'correction' => 'text-danger',
        'submission' => 'text-primary', default => 'text-secondary',
      };
    ?>
    <div class="d-flex gap-3 mb-3">
      <div class="text-center" style="min-width:50px">
        <span class="badge <?= str_replace('text-','bg-',$tc) === 'bg-secondary' ? 'bg-secondary' : str_replace('text-','bg-',$tc) ?> d-block mb-1">
          <?= h(PROGRESS_TYPE_MAP[$p['progress_type']] ?? '') ?>
        </span>
        <div class="text-muted small"><?= fmt_date($p['recorded_at']) ?></div>
      </div>
      <div class="border-start ps-3 flex-grow-1">
        <div class="small"><?= nl2br(h($p['content'])) ?></div>
        <div class="text-muted small mt-1"><?= h($p['user_name']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- 期日タブ -->
<?php elseif ($tab === 'deadlines'): ?>
<div class="page-card">
  <h3 class="h6 fw-bold mb-3">期日管理</h3>

  <form method="post" class="row g-2 align-items-end mb-4 p-3 bg-light rounded">
    <input type="hidden" name="_action" value="add_deadline">
    <input type="hidden" name="_tab" value="deadlines">
    <div class="col-md-4">
      <label class="form-label small fw-bold">タイトル</label>
      <input type="text" name="dl_title" class="form-control form-control-sm" required placeholder="例：申請書類提出期限">
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold">種別</label>
      <select name="dl_type" class="form-select form-select-sm">
        <?php foreach (DEADLINE_TYPE_MAP as $k => $v): ?>
        <option value="<?= $k ?>"><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold">期日</label>
      <input type="datetime-local" name="dl_date" class="form-control form-control-sm" required>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold">メモ</label>
      <input type="text" name="dl_memo" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-gc"><i class="bi bi-plus-lg me-1"></i>追加</button>
    </div>
  </form>

  <?php if (empty($deadlines)): ?>
    <p class="text-muted small">期日が登録されていません。</p>
  <?php else: ?>
  <table class="table align-middle">
    <thead class="table-light"><tr><th>期日</th><th>タイトル</th><th>種別</th><th>メモ</th><th>状態</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($deadlines as $dl):
      $dt = strtotime($dl['deadline_date']); $diff = (int)(($dt - time()) / 86400);
      $cls = $dl['is_done'] ? '' : ($diff < 0 ? 'table-danger' : ($diff <= 3 ? 'table-warning' : ''));
    ?>
    <tr class="<?= $cls ?>">
      <td class="small"><?= fmt_datetime($dl['deadline_date']) ?></td>
      <td><?= h($dl['title']) ?></td>
      <td><span class="badge bg-secondary"><?= h(DEADLINE_TYPE_MAP[$dl['deadline_type']] ?? '') ?></span></td>
      <td class="text-muted small"><?= h($dl['memo']) ?></td>
      <td><?= $dl['is_done'] ? '<span class="badge bg-success">完了</span>' : '<span class="badge bg-warning text-dark">未完了</span>' ?></td>
      <td>
        <?php if (!$dl['is_done']): ?>
        <form method="post" class="d-inline">
          <input type="hidden" name="_action" value="done_deadline">
          <input type="hidden" name="_tab" value="deadlines">
          <input type="hidden" name="deadline_id" value="<?= $dl['id'] ?>">
          <button class="btn btn-sm btn-success py-0 px-2">完了</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- 報酬タブ -->
<?php elseif ($tab === 'billing'): ?>
<div class="page-card">
  <div class="row mb-3">
    <div class="col-auto">
      <div class="border rounded p-2 text-center small">
        <div class="text-muted">合計請求額</div>
        <div class="fw-bold"><?= fmt_money((int)$bill_sum['total']) ?></div>
      </div>
    </div>
    <div class="col-auto">
      <div class="border rounded p-2 text-center small">
        <div class="text-muted">入金済み</div>
        <div class="fw-bold text-success"><?= fmt_money((int)$bill_sum['paid']) ?></div>
      </div>
    </div>
    <div class="col-auto">
      <div class="border rounded p-2 text-center small">
        <div class="text-muted">未入金</div>
        <div class="fw-bold text-danger"><?= fmt_money((int)$bill_sum['total'] - (int)$bill_sum['paid']) ?></div>
      </div>
    </div>
  </div>

  <form method="post" class="row g-2 align-items-end mb-4 p-3 bg-light rounded">
    <input type="hidden" name="_action" value="add_billing">
    <input type="hidden" name="_tab" value="billing">
    <div class="col-md-2">
      <label class="form-label small fw-bold">種別</label>
      <select name="b_type" class="form-select form-select-sm">
        <?php foreach (BILLING_TYPE_MAP as $k => $v): ?>
        <option value="<?= $k ?>"><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold">内容</label>
      <input type="text" name="b_title" class="form-control form-control-sm" required placeholder="例：着手金">
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold">金額（円）</label>
      <input type="number" name="b_amount" class="form-control form-control-sm" required min="0">
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold">請求日</label>
      <input type="date" name="b_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold">メモ</label>
      <input type="text" name="b_memo" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-gc"><i class="bi bi-plus-lg me-1"></i>追加</button>
    </div>
  </form>

  <?php if (empty($billings)): ?>
    <p class="text-muted small">報酬が登録されていません。</p>
  <?php else: ?>
  <table class="table align-middle">
    <thead class="table-light"><tr><th>種別</th><th>内容</th><th>金額</th><th>請求日</th><th>入金日</th><th>状態</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($billings as $b): ?>
    <tr>
      <td><span class="badge bg-secondary"><?= h(BILLING_TYPE_MAP[$b['billing_type']] ?? '') ?></span></td>
      <td><?= h($b['title']) ?></td>
      <td class="fw-bold"><?= fmt_money((int)$b['amount']) ?></td>
      <td class="small"><?= fmt_date($b['billed_date']) ?></td>
      <td class="small"><?= fmt_date($b['paid_date']) ?></td>
      <td><?= billing_paid_badge($b['is_paid']) ?></td>
      <td class="d-flex gap-1">
        <?php if (!$b['is_paid']): ?>
        <form method="post" class="d-inline">
          <input type="hidden" name="_action" value="paid_billing">
          <input type="hidden" name="_tab" value="billing">
          <input type="hidden" name="billing_id" value="<?= $b['id'] ?>">
          <button class="btn btn-sm btn-success py-0 px-2">入金</button>
        </form>
        <?php endif; ?>
        <form method="post" class="d-inline" onsubmit="return confirm('削除しますか？')">
          <input type="hidden" name="_action" value="del_billing">
          <input type="hidden" name="_tab" value="billing">
          <input type="hidden" name="billing_id" value="<?= $b['id'] ?>">
          <button class="btn btn-sm btn-outline-danger py-0 px-1"><i class="bi bi-trash3"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/_footer.php'; ?>
