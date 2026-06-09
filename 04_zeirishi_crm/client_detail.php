<?php
require_once 'config.php';
$pdo = getPdo();

$id = (int)($_GET['id'] ?? 0);
$client = $pdo->prepare("SELECT * FROM clients WHERE id=:id");
$client->execute([':id' => $id]);
$client = $client->fetch();
if (!$client) { header('Location: clients.php'); exit; }

// 申告期限
$deadlines = $pdo->prepare("SELECT * FROM tax_deadlines WHERE client_id=:id ORDER BY deadline DESC");
$deadlines->execute([':id' => $id]);
$deadlines = $deadlines->fetchAll();

// タスク
$tasks = $pdo->prepare("SELECT * FROM tasks WHERE client_id=:id ORDER BY FIELD(status,'in_progress','todo','done','cancelled'), due_date");
$tasks->execute([':id' => $id]);
$tasks = $tasks->fetchAll();

// 月次報酬（直近12ヶ月）
$fees = $pdo->prepare("SELECT * FROM monthly_fees WHERE client_id=:id ORDER BY `year_month` DESC LIMIT 12");
$fees->execute([':id' => $id]);
$fees = $fees->fetchAll();

// 進捗ログ
$logs = $pdo->prepare("SELECT p.*, t.title as task_title FROM progress_logs p LEFT JOIN tasks t ON t.id=p.task_id WHERE p.client_id=:id ORDER BY p.logged_at DESC");
$logs->execute([':id' => $id]);
$logs = $logs->fetchAll();

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_deadline') {
        $pdo->prepare("INSERT INTO tax_deadlines (client_id,tax_type,fiscal_year,deadline,status,notes) VALUES (:cid,:type,:fy,:dl,:st,:n)")
            ->execute([':cid'=>$id,':type'=>$_POST['tax_type'],':fy'=>$_POST['fiscal_year'],':dl'=>$_POST['deadline'],':st'=>$_POST['dl_status'],':n'=>$_POST['dl_notes']]);
        header("Location: client_detail.php?id={$id}#deadlines"); exit;
    }
    if ($action === 'update_deadline_status') {
        $pdo->prepare("UPDATE tax_deadlines SET status=:s, filed_at=:f WHERE id=:did AND client_id=:cid")
            ->execute([':s'=>$_POST['dl_status'],':f'=>($_POST['filed_at']?:null),':did'=>(int)$_POST['deadline_id'],':cid'=>$id]);
        header("Location: client_detail.php?id={$id}#deadlines"); exit;
    }
    if ($action === 'add_task') {
        $pdo->prepare("INSERT INTO tasks (client_id,title,category,due_date,priority,status,assignee,notes) VALUES (:c,:t,:cat,:d,:p,:s,:a,:n)")
            ->execute([':c'=>$id,':t'=>$_POST['title'],':cat'=>$_POST['category'],':d'=>($_POST['due_date']?:null),':p'=>$_POST['priority'],':s'=>'todo',':a'=>$_POST['assignee'],':n'=>$_POST['task_notes']]);
        header("Location: client_detail.php?id={$id}#tasks"); exit;
    }
    if ($action === 'update_task_status') {
        $pdo->prepare("UPDATE tasks SET status=:s WHERE id=:tid AND client_id=:cid")
            ->execute([':s'=>$_POST['task_status'],':tid'=>(int)$_POST['task_id'],':cid'=>$id]);
        header("Location: client_detail.php?id={$id}#tasks"); exit;
    }
    if ($action === 'add_log') {
        $pdo->prepare("INSERT INTO progress_logs (client_id,task_id,author,content) VALUES (:c,:t,:a,:co)")
            ->execute([':c'=>$id,':t'=>($_POST['task_id']?:null),':a'=>$_POST['author'],':co'=>$_POST['content']]);
        header("Location: client_detail.php?id={$id}#logs"); exit;
    }
    if ($action === 'update_client') {
        $f = $_POST;
        $pdo->prepare("UPDATE clients SET company_name=:cn,company_kana=:ck,representative=:r,zip_code=:z,address=:ad,phone=:ph,email=:em,industry=:in,corp_type=:ct,fiscal_month=:fm,contract_start=:cs,monthly_fee=:mf,notes=:n,status=:st WHERE id=:id")
            ->execute([':cn'=>$f['company_name'],':ck'=>$f['company_kana'],':r'=>$f['representative'],':z'=>$f['zip_code'],':ad'=>$f['address'],':ph'=>$f['phone'],':em'=>$f['email'],':in'=>$f['industry'],':ct'=>$f['corp_type'],':fm'=>(int)$f['fiscal_month'],':cs'=>($f['contract_start']?:null),':mf'=>(int)$f['monthly_fee'],':n'=>$f['notes'],':st'=>$f['status'],':id'=>$id]);
        header("Location: client_detail.php?id={$id}&msg=updated"); exit;
    }
}

$pageTitle = h($client['company_name']) . ' 詳細';
$f = $client;
include 'layout/header.php';
$today = date('Y-m-d');
?>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i>更新しました<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb"><li class="breadcrumb-item"><a href="clients.php">顧問先台帳</a></li><li class="breadcrumb-item active"><?= h($client['company_name']) ?></li></ol>
</nav>

<!-- 基本情報 -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-bold"><i class="bi bi-building me-1"></i>基本情報</span>
    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEdit">編集</button>
  </div>
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-3"><small class="text-muted">代表者</small><div><?= h((string)($client['representative'] ?? '─')) ?></div></div>
      <div class="col-md-2"><small class="text-muted">法人/個人</small><div><?= h($client['corp_type']) ?></div></div>
      <div class="col-md-2"><small class="text-muted">決算月</small><div><?= h((string)$client['fiscal_month']) ?>月</div></div>
      <div class="col-md-2"><small class="text-muted">月次顧問料</small><div>¥<?= number_format((int)$client['monthly_fee']) ?></div></div>
      <div class="col-md-3"><small class="text-muted">業種</small><div><?= h((string)($client['industry'] ?? '─')) ?></div></div>
      <div class="col-md-5"><small class="text-muted">住所</small><div><?= h((string)($client['address'] ?? '─')) ?></div></div>
      <div class="col-md-3"><small class="text-muted">電話</small><div><?= h((string)($client['phone'] ?? '─')) ?></div></div>
      <div class="col-md-4"><small class="text-muted">メール</small><div><?= h((string)($client['email'] ?? '─')) ?></div></div>
      <?php if ($client['notes']): ?>
      <div class="col-12"><small class="text-muted">備考</small><div><?= nl2br(h($client['notes'])) ?></div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- 申告期限 -->
<div class="card mb-4" id="deadlines">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-bold"><i class="bi bi-calendar-event me-1"></i>申告期限</span>
    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalDeadline">追加</button>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light"><tr><th>種別</th><th>年度</th><th>期限</th><th>申告日</th><th>状態</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($deadlines as $d):
            $diff = (int)((strtotime($d['deadline']) - strtotime($today)) / 86400);
            $rc = '';
            if ($d['status']==='overdue' || ($diff<0 && $d['status']!=='filed')) $rc='table-danger';
            elseif ($diff<=14 && $d['status']!=='filed') $rc='table-warning';
        ?>
        <tr class="<?= $rc ?>">
          <td><?= h($d['tax_type']) ?></td>
          <td><?= h($d['fiscal_year']) ?></td>
          <td><?= h($d['deadline']) ?><?= ($diff<0&&$d['status']!=='filed')?' <span class="text-danger small">超過</span>':'' ?></td>
          <td><?= h((string)($d['filed_at'] ?? '─')) ?></td>
          <td><?= statusBadge($d['status']) ?></td>
          <td>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalDlStatus<?= $d['id'] ?>">更新</button>
            <!-- ステータス更新ミニモーダル -->
            <div class="modal fade" id="modalDlStatus<?= $d['id'] ?>" tabindex="-1">
              <div class="modal-dialog modal-sm">
                <div class="modal-content">
                  <form method="post">
                    <input type="hidden" name="action" value="update_deadline_status">
                    <input type="hidden" name="deadline_id" value="<?= $d['id'] ?>">
                    <div class="modal-header"><h6 class="modal-title">期限ステータス更新</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                      <select name="dl_status" class="form-select mb-2">
                        <?php foreach (['pending'=>'未着手','in_progress'=>'進行中','filed'=>'申告済','overdue'=>'期限超過'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $d['status']===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                      </select>
                      <label class="form-label small">申告完了日</label>
                      <input type="date" name="filed_at" class="form-control form-control-sm" value="<?= h((string)($d['filed_at'] ?? '')) ?>">
                    </div>
                    <div class="modal-footer"><button class="btn btn-sm btn-primary">更新</button></div>
                  </form>
                </div>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$deadlines): ?><tr><td colspan="6" class="text-center text-muted py-3">登録なし</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- タスク -->
<div class="card mb-4" id="tasks">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-bold"><i class="bi bi-list-task me-1"></i>依頼タスク</span>
    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalTask">追加</button>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light"><tr><th>タスク名</th><th>カテゴリ</th><th>期限</th><th>優先度</th><th>担当</th><th>状態</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($tasks as $t): ?>
        <tr>
          <td><?= h($t['title']) ?></td>
          <td><?= h((string)($t['category'] ?? '')) ?></td>
          <td><?= h((string)($t['due_date'] ?? '─')) ?></td>
          <td><?= priorityBadge($t['priority']) ?></td>
          <td><?= h((string)($t['assignee'] ?? '')) ?></td>
          <td><?= statusBadge($t['status']) ?></td>
          <td>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalTaskSt<?= $t['id'] ?>">更新</button>
            <div class="modal fade" id="modalTaskSt<?= $t['id'] ?>" tabindex="-1">
              <div class="modal-dialog modal-sm">
                <div class="modal-content">
                  <form method="post">
                    <input type="hidden" name="action" value="update_task_status">
                    <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                    <div class="modal-header"><h6 class="modal-title">タスク状態更新</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                      <select name="task_status" class="form-select">
                        <?php foreach (['todo'=>'未着手','in_progress'=>'進行中','done'=>'完了','cancelled'=>'キャンセル'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $t['status']===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="modal-footer"><button class="btn btn-sm btn-primary">更新</button></div>
                  </form>
                </div>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$tasks): ?><tr><td colspan="7" class="text-center text-muted py-3">登録なし</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 月次報酬 -->
<div class="card mb-4">
  <div class="card-header fw-bold"><i class="bi bi-cash-coin me-1"></i>月次報酬（直近12ヶ月）</div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light"><tr><th>年月</th><th class="text-end">請求額</th><th>請求書番号</th><th>入金日</th><th>状態</th></tr></thead>
      <tbody>
        <?php foreach ($fees as $fee): ?>
        <tr class="<?= $fee['paid_at'] ? '' : 'table-warning' ?>">
          <td><?= h($fee['year_month']) ?></td>
          <td class="text-end">¥<?= number_format((int)$fee['fee_amount']) ?></td>
          <td><?= h((string)($fee['invoice_no'] ?? '')) ?></td>
          <td><?= h((string)($fee['paid_at'] ?? '─')) ?></td>
          <td><?= $fee['paid_at'] ? '<span class="badge bg-success">入金済</span>' : '<span class="badge bg-warning text-dark">未入金</span>' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$fees): ?><tr><td colspan="5" class="text-center text-muted py-3">登録なし</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 進捗ログ -->
<div class="card mb-4" id="logs">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-bold"><i class="bi bi-journal-text me-1"></i>進捗ログ</span>
    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalLog">記録追加</button>
  </div>
  <ul class="list-group list-group-flush">
    <?php foreach ($logs as $log): ?>
    <li class="list-group-item">
      <div class="d-flex justify-content-between">
        <small class="text-muted"><?= h($log['logged_at']) ?> — <?= h((string)($log['author'] ?? '')) ?></small>
        <?php if ($log['task_title']): ?><small class="badge bg-light text-dark"><?= h($log['task_title']) ?></small><?php endif; ?>
      </div>
      <div class="mt-1"><?= nl2br(h($log['content'])) ?></div>
    </li>
    <?php endforeach; ?>
    <?php if (!$logs): ?><li class="list-group-item text-center text-muted py-3">ログなし</li><?php endif; ?>
  </ul>
</div>

<!-- 編集モーダル -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="update_client">
        <div class="modal-header"><h5 class="modal-title">顧問先情報 編集</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><?php include 'layout/client_form_fields.php'; ?></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-primary">保存</button></div>
      </form>
    </div>
  </div>
</div>

<!-- 申告期限追加モーダル -->
<div class="modal fade" id="modalDeadline" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="add_deadline">
        <div class="modal-header"><h5 class="modal-title">申告期限 追加</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">申告種別</label><input type="text" name="tax_type" class="form-control" required placeholder="法人税・消費税・所得税 など"></div>
          <div class="mb-3"><label class="form-label">対象年度</label><input type="text" name="fiscal_year" class="form-control" placeholder="2024年3月期"></div>
          <div class="mb-3"><label class="form-label">期限日 <span class="text-danger">*</span></label><input type="date" name="deadline" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">状態</label>
            <select name="dl_status" class="form-select">
              <option value="pending">未着手</option><option value="in_progress">進行中</option><option value="filed">申告済</option>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">備考</label><textarea name="dl_notes" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-success">追加</button></div>
      </form>
    </div>
  </div>
</div>

<!-- タスク追加モーダル -->
<div class="modal fade" id="modalTask" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="add_task">
        <div class="modal-header"><h5 class="modal-title">タスク 追加</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">タスク名 <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required></div>
          <div class="row g-2">
            <div class="col-md-6 mb-3"><label class="form-label">カテゴリ</label>
              <select name="category" class="form-select"><option value="月次">月次</option><option value="決算">決算</option><option value="その他">その他</option></select>
            </div>
            <div class="col-md-6 mb-3"><label class="form-label">優先度</label>
              <select name="priority" class="form-select"><option value="high">高</option><option value="medium" selected>中</option><option value="low">低</option></select>
            </div>
          </div>
          <div class="mb-3"><label class="form-label">期限</label><input type="date" name="due_date" class="form-control"></div>
          <div class="mb-3"><label class="form-label">担当者</label><input type="text" name="assignee" class="form-control"></div>
          <div class="mb-3"><label class="form-label">備考</label><textarea name="task_notes" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-success">追加</button></div>
      </form>
    </div>
  </div>
</div>

<!-- 進捗ログ追加モーダル -->
<div class="modal fade" id="modalLog" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="add_log">
        <div class="modal-header"><h5 class="modal-title">進捗ログ 追加</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">記録者</label><input type="text" name="author" class="form-control"></div>
          <div class="mb-3"><label class="form-label">関連タスク（任意）</label>
            <select name="task_id" class="form-select">
              <option value="">─</option>
              <?php foreach ($tasks as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['title']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">内容 <span class="text-danger">*</span></label><textarea name="content" class="form-control" rows="4" required></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-success">記録</button></div>
      </form>
    </div>
  </div>
</div>

<?php include 'layout/footer.php'; ?>
