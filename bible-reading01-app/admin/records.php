<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$pdo = getDB();
$message = '';

// --- POST: 削除処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('不正なリクエストです。');
    }
    if (($_POST['action'] ?? '') === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM reading_daily_logs WHERE id = ?')->execute([$id]);
            $message = '通読記録を削除しました。';
        }
    }
}

// --- フィルター ---
$filterMemberId = intval($_GET['member_id'] ?? 0);
$filterDate     = $_GET['date'] ?? '';
$filterStatus   = isset($_GET['status']) && $_GET['status'] !== '' ? intval($_GET['status']) : null;
$filterMonth    = $_GET['month'] ?? '';   // YYYY-MM
$page           = max(1, intval($_GET['page'] ?? 1));
$perPage        = 30;
$offset         = ($page - 1) * $perPage;

// メンバー一覧（フィルター用プルダウン）
$allMembers = $pdo->query('SELECT id, name, furigana FROM members WHERE is_active=1 ORDER BY furigana, name')->fetchAll();

// クエリ組み立て
$where  = [];
$params = [];
if ($filterMemberId > 0) { $where[] = 'l.member_id = ?'; $params[] = $filterMemberId; }
if ($filterDate !== '')  { $where[] = 'l.reading_date = ?'; $params[] = $filterDate; }
if ($filterStatus !== null) { $where[] = 'l.status = ?'; $params[] = $filterStatus; }
if ($filterMonth !== '') { $where[] = 'DATE_FORMAT(l.reading_date, "%Y-%m") = ?'; $params[] = $filterMonth; }

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalCount = $pdo->prepare("SELECT COUNT(*) FROM reading_daily_logs l $whereClause");
$totalCount->execute($params);
$totalCount = (int)$totalCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));

$stmt = $pdo->prepare("
    SELECT l.id, l.reading_date, l.passage_summary, l.status, l.note,
           l.updated_at, m.id AS member_id, m.name AS member_name
    FROM reading_daily_logs l
    JOIN members m ON l.member_id = m.id
    $whereClause
    ORDER BY l.reading_date DESC, l.updated_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$records = $stmt->fetchAll();

// フィルター中のメンバー名
$filterMemberName = '';
if ($filterMemberId > 0) {
    foreach ($allMembers as $am) {
        if ($am['id'] == $filterMemberId) { $filterMemberName = $am['name']; break; }
    }
}

renderHeader('通読記録', 'records');
$statusLabels = STATUS_LABELS;
$statusBadges = STATUS_BADGES;

// クエリ文字列（ページネーション用）
function buildQuery(array $overrides = []): string {
    global $filterMemberId, $filterDate, $filterStatus, $filterMonth;
    $params = [
        'member_id' => $filterMemberId ?: '',
        'date'      => $filterDate,
        'status'    => $filterStatus !== null ? $filterStatus : '',
        'month'     => $filterMonth,
    ];
    return http_build_query(array_merge($params, $overrides));
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h4 class="mb-0">
    <i class="bi bi-journal-text me-2"></i>通読記録
    <?php if ($filterMemberName): ?>
      <small class="text-muted fs-6">— <?= h($filterMemberName) ?></small>
    <?php elseif ($filterDate): ?>
      <small class="text-muted fs-6">— <?= h(date('Y年n月j日', strtotime($filterDate))) ?></small>
    <?php endif; ?>
  </h4>
  <a href="record_form.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> 記録を追加</a>
</div>

<?php if ($message): ?>
  <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i><?= h($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- フィルターフォーム -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-6 col-md-3">
        <label class="form-label form-label-sm mb-1">メンバー</label>
        <select name="member_id" class="form-select form-select-sm">
          <option value="">すべて</option>
          <?php foreach ($allMembers as $am): ?>
            <option value="<?= $am['id'] ?>" <?= $filterMemberId == $am['id'] ? 'selected' : '' ?>>
              <?= h($am['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label form-label-sm mb-1">日付</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= h($filterDate) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label form-label-sm mb-1">年月</label>
        <input type="month" name="month" class="form-control form-control-sm" value="<?= h($filterMonth) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label form-label-sm mb-1">状態</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">すべて</option>
          <?php foreach ($statusLabels as $val => $label): ?>
            <option value="<?= $val ?>" <?= $filterStatus === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary flex-fill">絞り込む</button>
        <a href="records.php" class="btn btn-sm btn-outline-secondary">クリア</a>
      </div>
    </form>
  </div>
</div>

<!-- 記録一覧 -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>通読記録一覧（<?= number_format($totalCount) ?>件）</span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($records)): ?>
      <p class="text-muted text-center py-4">記録が見つかりません。</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th style="width:90px">日付</th>
            <th>メンバー</th>
            <th>通読箇所</th>
            <th style="width:80px">状態</th>
            <th>メモ</th>
            <th style="width:80px"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($records as $rec): ?>
          <tr>
            <td class="small"><?= date('Y/n/j', strtotime($rec['reading_date'])) ?></td>
            <td>
              <a href="?member_id=<?= $rec['member_id'] ?>"><?= h($rec['member_name']) ?></a>
            </td>
            <td class="small"><?= h($rec['passage_summary'] ?? '—') ?></td>
            <td>
              <span class="badge bg-<?= $statusBadges[$rec['status']] ?>">
                <?= $statusLabels[$rec['status']] ?>
              </span>
            </td>
            <td class="small text-muted"><?= h(mb_strimwidth($rec['note'] ?? '', 0, 30, '…')) ?></td>
            <td class="text-end">
              <a href="record_form.php?id=<?= $rec['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                <i class="bi bi-pencil"></i>
              </a>
              <button type="button" class="btn btn-sm btn-outline-danger"
                      onclick="confirmDelete(<?= $rec['id'] ?>, '<?= h($rec['member_name']) ?>', '<?= h(date('n/j', strtotime($rec['reading_date']))) ?>')">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ページネーション -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center py-3">
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
              <a class="page-link" href="?<?= buildQuery(['page' => $p]) ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- 削除確認モーダル -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">削除の確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p><strong id="deleteName"></strong> の <strong id="deleteDate"></strong> の記録を削除しますか？</p>
        <p class="text-danger small">この操作は取り消せません。</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
        <form method="post">
          <?= csrfInput() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" id="deleteId">
          <button type="submit" class="btn btn-danger">削除する</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function confirmDelete(id, name, date) {
    document.getElementById('deleteName').textContent = name;
    document.getElementById('deleteDate').textContent = date;
    document.getElementById('deleteId').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php renderFooter(); ?>
