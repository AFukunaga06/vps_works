<?php
require_once 'config.php';
$pdo = getPdo();

// POST: 新規登録 / 編集
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $fields = [
        'company_name'   => trim($_POST['company_name'] ?? ''),
        'company_kana'   => trim($_POST['company_kana'] ?? ''),
        'representative' => trim($_POST['representative'] ?? ''),
        'zip_code'       => trim($_POST['zip_code'] ?? ''),
        'address'        => trim($_POST['address'] ?? ''),
        'phone'          => trim($_POST['phone'] ?? ''),
        'email'          => trim($_POST['email'] ?? ''),
        'industry'       => trim($_POST['industry'] ?? ''),
        'corp_type'      => $_POST['corp_type'] ?? '法人',
        'fiscal_month'   => (int)($_POST['fiscal_month'] ?? 3),
        'contract_start' => $_POST['contract_start'] ?: null,
        'monthly_fee'    => (int)($_POST['monthly_fee'] ?? 0),
        'notes'          => trim($_POST['notes'] ?? ''),
        'status'         => $_POST['status'] ?? 'active',
    ];

    if ($action === 'create') {
        $sql = "INSERT INTO clients (company_name,company_kana,representative,zip_code,address,phone,email,industry,corp_type,fiscal_month,contract_start,monthly_fee,notes,status)
                VALUES (:company_name,:company_kana,:representative,:zip_code,:address,:phone,:email,:industry,:corp_type,:fiscal_month,:contract_start,:monthly_fee,:notes,:status)";
        $pdo->prepare($sql)->execute($fields);
        header('Location: clients.php?msg=created');
        exit;
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $sql = "UPDATE clients SET company_name=:company_name,company_kana=:company_kana,representative=:representative,zip_code=:zip_code,address=:address,phone=:phone,email=:email,industry=:industry,corp_type=:corp_type,fiscal_month=:fiscal_month,contract_start=:contract_start,monthly_fee=:monthly_fee,notes=:notes,status=:status WHERE id=:id";
        $fields[':id'] = $id;
        $pdo->prepare($sql)->execute($fields);
        header("Location: client_detail.php?id={$id}&msg=updated");
        exit;
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM clients WHERE id=:id")->execute([':id' => $id]);
        header('Location: clients.php?msg=deleted');
        exit;
    }
}

// 検索・フィルタ
$search = trim($_GET['q'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$params = [];
$where = ['1=1'];
if ($search !== '') {
    $where[] = '(company_name LIKE :q OR company_kana LIKE :q OR representative LIKE :q)';
    $params[':q'] = "%{$search}%";
}
if ($filterStatus !== '') {
    $where[] = 'status = :status';
    $params[':status'] = $filterStatus;
}
$whereStr = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT * FROM clients WHERE {$whereStr} ORDER BY company_kana");
$stmt->execute($params);
$clients = $stmt->fetchAll();

$pageTitle = '顧問先台帳';
include 'layout/header.php';
?>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?= ['created'=>'登録しました','updated'=>'更新しました','deleted'=>'削除しました'][$_GET['msg']] ?? '' ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-md-5">
        <input type="text" name="q" class="form-control" placeholder="会社名・カナ・代表者で検索" value="<?= h($search) ?>">
      </div>
      <div class="col-md-3">
        <select name="status" class="form-select">
          <option value="">すべて</option>
          <option value="active" <?= $filterStatus==='active'?'selected':'' ?>>稼働中</option>
          <option value="inactive" <?= $filterStatus==='inactive'?'selected':'' ?>>停止</option>
        </select>
      </div>
      <div class="col-auto"><button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>検索</button></div>
      <div class="col-auto ms-auto">
        <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#modalCreate">
          <i class="bi bi-plus-lg me-1"></i>新規登録
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-dark">
        <tr>
          <th>会社名</th><th>代表者</th><th>業種</th><th>法人/個人</th>
          <th>決算月</th><th>月次顧問料</th><th>状態</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $c): ?>
        <tr>
          <td><a href="client_detail.php?id=<?= h((string)$c['id']) ?>" class="fw-bold text-decoration-none"><?= h($c['company_name']) ?></a></td>
          <td><?= h((string)($c['representative'] ?? '')) ?></td>
          <td><?= h((string)($c['industry'] ?? '')) ?></td>
          <td><?= h($c['corp_type']) ?></td>
          <td><?= h((string)$c['fiscal_month']) ?>月</td>
          <td class="text-end">¥<?= number_format((int)$c['monthly_fee']) ?></td>
          <td><?= $c['status']==='active' ? '<span class="badge bg-success">稼働</span>' : '<span class="badge bg-secondary">停止</span>' ?></td>
          <td>
            <a href="client_detail.php?id=<?= h((string)$c['id']) ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-eye"></i></a>
            <form method="post" class="d-inline" onsubmit="return confirm('削除しますか？')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= h((string)$c['id']) ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$clients): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">該当する顧問先がありません</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 新規登録モーダル -->
<div class="modal fade" id="modalCreate" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="modal-header"><h5 class="modal-title">顧問先 新規登録</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <?php include 'layout/client_form_fields.php'; ?>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-success">登録</button></div>
      </form>
    </div>
  </div>
</div>

<?php include 'layout/footer.php'; ?>
