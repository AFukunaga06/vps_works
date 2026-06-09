<?php
require_once 'config.php';
$pdo = getPdo();

// POST: ステータス一括更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $pdo->prepare("UPDATE tasks SET status=:s WHERE id=:id")
        ->execute([':s'=>$_POST['task_status'], ':id'=>(int)$_POST['task_id']]);
    header('Location: tasks.php'); exit;
}

$filterStatus   = $_GET['status'] ?? '';
$filterPriority = $_GET['priority'] ?? '';
$search         = trim($_GET['q'] ?? '');
$today          = date('Y-m-d');

$where  = ['1=1'];
$params = [];
if ($filterStatus !== '') { $where[] = 't.status=:status'; $params[':status'] = $filterStatus; }
if ($filterPriority !== '') { $where[] = 't.priority=:priority'; $params[':priority'] = $filterPriority; }
if ($search !== '') { $where[] = '(t.title LIKE :q OR c.company_name LIKE :q)'; $params[':q'] = "%{$search}%"; }
$whereStr = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT t.*, c.company_name
    FROM tasks t
    JOIN clients c ON c.id = t.client_id
    WHERE {$whereStr}
    ORDER BY FIELD(t.status,'in_progress','todo','done','cancelled'), t.due_date
");
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$pageTitle = '依頼タスク一覧';
include 'layout/header.php';
?>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-md-4"><input type="text" name="q" class="form-control" placeholder="タスク名・顧問先" value="<?= h($search) ?>"></div>
      <div class="col-auto">
        <select name="status" class="form-select">
          <option value="">すべての状態</option>
          <?php foreach (['todo'=>'未着手','in_progress'=>'進行中','done'=>'完了','cancelled'=>'キャンセル'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= $filterStatus===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <select name="priority" class="form-select">
          <option value="">すべての優先度</option>
          <option value="high" <?= $filterPriority==='high'?'selected':'' ?>>高</option>
          <option value="medium" <?= $filterPriority==='medium'?'selected':'' ?>>中</option>
          <option value="low" <?= $filterPriority==='low'?'selected':'' ?>>低</option>
        </select>
      </div>
      <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>絞込</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-dark">
        <tr><th>顧問先</th><th>タスク名</th><th>カテゴリ</th><th>期限</th><th>優先度</th><th>担当</th><th>状態</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($tasks as $t):
            $diff = $t['due_date'] ? (int)((strtotime($t['due_date']) - strtotime($today)) / 86400) : null;
            $rc = '';
            if (!in_array($t['status'],['done','cancelled'])) {
                if ($diff !== null && $diff < 0) $rc = 'table-danger';
                elseif ($diff !== null && $diff <= 7) $rc = 'table-warning';
            }
        ?>
        <tr class="<?= $rc ?>">
          <td><a href="client_detail.php?id=<?= h((string)$t['client_id']) ?>"><?= h($t['company_name']) ?></a></td>
          <td><?= h($t['title']) ?></td>
          <td><?= h((string)($t['category'] ?? '')) ?></td>
          <td>
            <?= h((string)($t['due_date'] ?? '─')) ?>
            <?php if ($diff !== null && !in_array($t['status'],['done','cancelled'])): ?>
              <?php if ($diff < 0): ?><span class="text-danger small ms-1">超過</span>
              <?php elseif ($diff <= 7): ?><span class="text-warning small ms-1"><?= $diff ?>日</span><?php endif; ?>
            <?php endif; ?>
          </td>
          <td><?= priorityBadge($t['priority']) ?></td>
          <td><?= h((string)($t['assignee'] ?? '')) ?></td>
          <td><?= statusBadge($t['status']) ?></td>
          <td>
            <form method="post" class="d-inline">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
              <select name="task_status" class="form-select form-select-sm d-inline-block w-auto me-1" onchange="this.form.submit()">
                <?php foreach (['todo'=>'未着手','in_progress'=>'進行中','done'=>'完了','cancelled'=>'キャンセル'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $t['status']===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$tasks): ?><tr><td colspan="8" class="text-center text-muted py-4">該当なし</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'layout/footer.php'; ?>
