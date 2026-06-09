<?php
require_once 'config.php';
$pdo = getPdo();

// POST: 入金日更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_paid') {
        $pdo->prepare("UPDATE monthly_fees SET paid_at=:d WHERE id=:id")
            ->execute([':d'=>$_POST['paid_at'], ':id'=>(int)$_POST['fee_id']]);
        header('Location: fees.php?msg=updated'); exit;
    }
    if ($action === 'add_fee') {
        $pdo->prepare("INSERT INTO monthly_fees (client_id,`year_month`,fee_amount,invoice_no,notes) VALUES (:c,:ym,:fa,:inv,:n)
                       ON DUPLICATE KEY UPDATE fee_amount=VALUES(fee_amount), invoice_no=VALUES(invoice_no), notes=VALUES(notes)")
            ->execute([':c'=>(int)$_POST['client_id'],':ym'=>$_POST['year_month'],':fa'=>(int)$_POST['fee_amount'],':inv'=>$_POST['invoice_no'],':n'=>$_POST['notes']]);
        header('Location: fees.php?msg=created'); exit;
    }
}

$ym = $_GET['ym'] ?? date('Y-m');

// 月次サマリ
$stmt = $pdo->prepare("
    SELECT f.*, c.company_name, c.monthly_fee as base_fee
    FROM monthly_fees f
    JOIN clients c ON c.id = f.client_id
    WHERE f.`year_month` = :ym
    ORDER BY c.company_kana
");
$stmt->execute([':ym' => $ym]);
$fees = $stmt->fetchAll();

$totalBilled = array_sum(array_column($fees, 'fee_amount'));
$totalPaid   = array_sum(array_map(fn($r) => $r['paid_at'] ? $r['fee_amount'] : 0, $fees));
$unpaid      = $totalBilled - $totalPaid;

// 顧問先リスト（追加用）
$clients = $pdo->query("SELECT id, company_name FROM clients WHERE status='active' ORDER BY company_kana")->fetchAll();

$pageTitle = '月次報酬管理';
include 'layout/header.php';
?>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show"><?= ['updated'=>'入金情報を更新しました','created'=>'請求を追加しました'][$_GET['msg']] ?? '' ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- 月切り替え -->
<div class="card mb-3">
  <div class="card-body d-flex align-items-center gap-3 flex-wrap">
    <form method="get" class="d-flex gap-2 align-items-center">
      <label class="fw-bold">対象月：</label>
      <input type="month" name="ym" class="form-control form-control-sm w-auto" value="<?= h($ym) ?>">
      <button class="btn btn-sm btn-primary">表示</button>
    </form>
    <a href="?ym=<?= date('Y-m', strtotime($ym.'-01 -1 month')) ?>" class="btn btn-sm btn-outline-secondary">◀ 前月</a>
    <a href="?ym=<?= date('Y-m', strtotime($ym.'-01 +1 month')) ?>" class="btn btn-sm btn-outline-secondary">翌月 ▶</a>
    <div class="ms-auto">
      <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalAddFee"><i class="bi bi-plus-lg me-1"></i>請求追加</button>
    </div>
  </div>
</div>

<!-- サマリカード -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="card text-center border-primary">
      <div class="card-body"><small class="text-muted">請求合計</small><div class="fs-4 fw-bold text-primary">¥<?= number_format($totalBilled) ?></div></div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card text-center border-success">
      <div class="card-body"><small class="text-muted">入金済</small><div class="fs-4 fw-bold text-success">¥<?= number_format($totalPaid) ?></div></div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card text-center border-warning">
      <div class="card-body"><small class="text-muted">未入金</small><div class="fs-4 fw-bold text-warning">¥<?= number_format($unpaid) ?></div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-dark">
        <tr><th>顧問先</th><th class="text-end">請求額</th><th>請求書番号</th><th>入金日</th><th>状態</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($fees as $fee): ?>
        <tr class="<?= $fee['paid_at'] ? '' : 'table-warning' ?>">
          <td><a href="client_detail.php?id=<?= h((string)$fee['client_id']) ?>"><?= h($fee['company_name']) ?></a></td>
          <td class="text-end">¥<?= number_format((int)$fee['fee_amount']) ?></td>
          <td><?= h((string)($fee['invoice_no'] ?? '')) ?></td>
          <td><?= h((string)($fee['paid_at'] ?? '─')) ?></td>
          <td><?= $fee['paid_at'] ? '<span class="badge bg-success">入金済</span>' : '<span class="badge bg-warning text-dark">未入金</span>' ?></td>
          <td>
            <?php if (!$fee['paid_at']): ?>
            <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalPaid<?= $fee['id'] ?>">入金登録</button>
            <div class="modal fade" id="modalPaid<?= $fee['id'] ?>" tabindex="-1">
              <div class="modal-dialog modal-sm">
                <div class="modal-content">
                  <form method="post">
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="fee_id" value="<?= $fee['id'] ?>">
                    <div class="modal-header"><h6 class="modal-title">入金日登録</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><input type="date" name="paid_at" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="modal-footer"><button class="btn btn-sm btn-success">登録</button></div>
                  </form>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$fees): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= h($ym) ?> の請求データなし</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 請求追加モーダル -->
<div class="modal fade" id="modalAddFee" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="add_fee">
        <div class="modal-header"><h5 class="modal-title">請求追加</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">顧問先</label>
            <select name="client_id" class="form-select" required>
              <option value="">選択</option>
              <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['company_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">対象月</label><input type="month" name="year_month" class="form-control" value="<?= h($ym) ?>" required></div>
          <div class="mb-3"><label class="form-label">請求額（円）</label><input type="number" name="fee_amount" class="form-control" min="0" step="1000" required></div>
          <div class="mb-3"><label class="form-label">請求書番号</label><input type="text" name="invoice_no" class="form-control"></div>
          <div class="mb-3"><label class="form-label">備考</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-success">追加</button></div>
      </form>
    </div>
  </div>
</div>

<?php include 'layout/footer.php'; ?>
