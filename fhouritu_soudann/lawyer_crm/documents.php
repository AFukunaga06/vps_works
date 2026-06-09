<?php
require_once 'config.php';
require_login();
$page_title = '書類管理';

$db      = get_db();
$case_id = (int)($_GET['case_id'] ?? 0);

// Handle upload
$upload_error = '';
$upload_ok    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $fid       = (int)($_POST['case_id'] ?? 0);
    $doc_name  = trim($_POST['document_name'] ?? '');
    $doc_type  = $_POST['document_type'] ?? 'その他';
    $notes     = trim($_POST['notes'] ?? '');
    $file      = $_FILES['document'];

    $allowed = ['application/pdf','image/jpeg','image/png','image/gif',
                'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    if (!$fid || !$doc_name) {
        $upload_error = '案件と書類名は必須です。';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_error = 'ファイルのアップロードに失敗しました（エラーコード: ' . $file['error'] . '）';
    } elseif ($file['size'] > 10 * 1024 * 1024) {
        $upload_error = 'ファイルサイズは10MB以内にしてください。';
    } elseif (!in_array($file['type'], $allowed)) {
        $upload_error = '許可されていないファイル形式です（PDF・Word・画像のみ）。';
    } else {
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = __DIR__ . '/uploads/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $st = $db->prepare(
                'INSERT INTO documents (case_id,document_name,document_type,file_path,file_size,mime_type,notes,uploaded_by)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            $st->execute([
                $fid, $doc_name, $doc_type, 'uploads/' . $filename,
                $file['size'], $file['type'], $notes, $_SESSION['user_name'] ?? '',
            ]);
            $upload_ok = true;
            $case_id   = $fid;
        } else {
            $upload_error = 'ファイルの保存に失敗しました。uploadsディレクトリの書き込み権限を確認してください。';
        }
    }
}

// List documents
$where  = [];
$params = [];
if ($case_id) { $where[] = 'd.case_id = ?'; $params[] = $case_id; }

$sql = "SELECT d.*, c.title AS case_title, cl.name AS client_name
        FROM documents d
        JOIN cases c ON d.case_id = c.id
        JOIN clients cl ON c.client_id = cl.id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY d.upload_date DESC';

$st = $db->prepare($sql);
$st->execute($params);
$documents = $st->fetchAll();

// Cases for dropdown
$cases_list = $db->query(
    "SELECT c.id, c.title, cl.name AS client_name FROM cases c JOIN clients cl ON c.client_id=cl.id
     ORDER BY c.status='受任中' DESC, c.updated_at DESC"
)->fetchAll();

require 'includes/header.php';
?>

<?php if ($upload_ok): ?>
<div class="alert alert-success alert-dismissible fade show">
  <i class="bi bi-check-circle me-1"></i>書類をアップロードしました。
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($upload_error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= h($upload_error) ?></div>
<?php endif; ?>

<div class="row g-3">
  <!-- アップロードフォーム -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header bg-white py-3 fw-semibold">
        <i class="bi bi-cloud-upload text-primary me-2"></i>書類アップロード
      </div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label fw-semibold">案件 <span class="text-danger">*</span></label>
            <select name="case_id" class="form-select" required>
              <option value="">選択してください</option>
              <?php foreach ($cases_list as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $case_id === (int)$c['id'] ? 'selected' : '' ?>>
                <?= h($c['client_name']) ?>：<?= h(mb_strimwidth($c['title'],0,25,'…')) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">書類名 <span class="text-danger">*</span></label>
            <input type="text" name="document_name" class="form-control"
                   placeholder="例：第1回準備書面（原告）" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">書類種別</label>
            <select name="document_type" class="form-select">
              <?php foreach (DOC_TYPES as $t): ?><option><?= $t ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">ファイル <span class="text-danger">*</span></label>
            <input type="file" name="document" class="form-control"
                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
            <div class="form-text">PDF・Word・画像（最大10MB）</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">備考</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="補足メモ"></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-cloud-upload me-1"></i>アップロード
          </button>
        </form>
      </div>
    </div>

    <div class="alert alert-info mt-3 small">
      <i class="bi bi-info-circle me-1"></i>
      アップロードされたファイルは <code>uploads/</code> フォルダに保存されます。
      本番環境ではWebサーバーの外（ドキュメントルート外）への保存を推奨します。
    </div>
  </div>

  <!-- 書類一覧 -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
          <i class="bi bi-files me-2 text-info"></i>書類一覧
          <span class="badge bg-secondary ms-1"><?= count($documents) ?>件</span>
        </span>
        <?php if ($case_id): ?>
        <a href="documents.php" class="btn btn-sm btn-outline-secondary">全案件表示</a>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>書類名</th>
              <th>種別</th>
              <th>案件・依頼人</th>
              <th>アップロード日</th>
              <th>備考</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($documents): ?>
            <?php foreach ($documents as $doc):
              $mime  = $doc['mime_type'] ?? '';
              $icon  = str_contains($mime, 'pdf') ? 'bi-file-earmark-pdf text-danger'
                     : (str_contains($mime, 'word') ? 'bi-file-earmark-word text-primary'
                     : 'bi-file-earmark-image text-success');
            ?>
            <tr>
              <td>
                <i class="bi <?= $icon ?> me-1 fs-5"></i>
                <span class="fw-semibold small"><?= h($doc['document_name']) ?></span>
                <?php if ($doc['file_size']): ?>
                <br><small class="text-muted"><?= number_format($doc['file_size'] / 1024, 0) ?>KB</small>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-light text-dark border"><?= h($doc['document_type']) ?></span></td>
              <td>
                <a href="case_detail.php?id=<?= $doc['case_id'] ?>#documents" class="text-decoration-none small">
                  <?= h(mb_strimwidth($doc['case_title'],0,20,'…')) ?>
                </a>
                <div class="text-muted" style="font-size:.75rem"><?= h($doc['client_name']) ?></div>
              </td>
              <td class="small text-muted"><?= date('Y/m/d', strtotime($doc['upload_date'])) ?></td>
              <td class="small text-muted"><?= h(mb_strimwidth($doc['notes']??'',0,25,'…')) ?></td>
              <td>
                <?php if ($doc['file_path'] && file_exists(__DIR__ . '/' . $doc['file_path'])): ?>
                <a href="<?= h($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="ダウンロード">
                  <i class="bi bi-download"></i>
                </a>
                <?php else: ?>
                <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr><td colspan="6" class="text-center text-muted py-5">
              <i class="bi bi-folder2 fs-1 d-block mb-2"></i>書類がありません
            </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
