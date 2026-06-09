<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$pdo = getDB();
$action  = $_GET['action'] ?? 'list';
$message = '';
$error   = '';

// --- POST処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('不正なリクエストです。');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $id       = intval($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $furigana = trim($_POST['furigana'] ?? '');
        $email    = trim($_POST['email'] ?? '') ?: null;
        $tel      = trim($_POST['tel'] ?? '') ?: null;
        $note     = trim($_POST['note'] ?? '') ?: null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $error = '氏名は必須です。';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE members SET name=?, furigana=?, email=?, tel=?, note=?, is_active=? WHERE id=?');
                    $stmt->execute([$name, $furigana ?: null, $email, $tel, $note, $isActive, $id]);
                    $message = 'メンバー情報を更新しました。';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO members (name, furigana, email, tel, note, is_active) VALUES (?,?,?,?,?,?)');
                    $stmt->execute([$name, $furigana ?: null, $email, $tel, $note, $isActive]);
                    $message = 'メンバーを追加しました。';
                }
            } catch (PDOException $e) {
                $error = 'メールアドレスが重複しています。';
            }
        }
    } elseif ($postAction === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM members WHERE id = ?')->execute([$id]);
            $message = 'メンバーを削除しました。';
        }
    }
    $action = 'list';
}

// --- 編集対象取得 ---
$editMember = null;
if ($action === 'edit') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $editMember = $pdo->prepare('SELECT * FROM members WHERE id = ?');
        $editMember->execute([$id]);
        $editMember = $editMember->fetch();
    }
    if (!$editMember) { $action = 'list'; }
}

// --- メンバー一覧取得 ---
$showInactive = isset($_GET['show_inactive']);
$sql = 'SELECT m.*, (SELECT COUNT(*) FROM reading_daily_logs l WHERE l.member_id = m.id) AS record_count FROM members m';
if (!$showInactive) { $sql .= ' WHERE m.is_active = 1'; }
$sql .= ' ORDER BY m.furigana, m.name';
$members = $pdo->query($sql)->fetchAll();

renderHeader('メンバー管理', 'members');
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h4 class="mb-0"><i class="bi bi-people me-2"></i>メンバー管理</h4>
  <a href="?action=add" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> 新規追加</a>
</div>

<?php if ($message): ?>
  <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i><?= h($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= h($error) ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- 追加・編集フォーム -->
<div class="card mb-4">
  <div class="card-header fw-semibold">
    <?= ($action === 'edit') ? 'メンバー編集' : 'メンバー新規追加' ?>
  </div>
  <div class="card-body">
    <form method="post">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="save">
      <?php if ($editMember): ?>
        <input type="hidden" name="id" value="<?= $editMember['id'] ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">氏名 <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required maxlength="100"
                 value="<?= h($editMember['name'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">フリガナ</label>
          <input type="text" name="furigana" class="form-control" maxlength="100"
                 value="<?= h($editMember['furigana'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">メールアドレス</label>
          <input type="email" name="email" class="form-control" maxlength="255"
                 value="<?= h($editMember['email'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">電話番号</label>
          <input type="tel" name="tel" class="form-control" maxlength="20"
                 value="<?= h($editMember['tel'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">備考</label>
          <input type="text" name="note" class="form-control" maxlength="500"
                 value="<?= h($editMember['note'] ?? '') ?>">
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                   <?= (($editMember['is_active'] ?? 1) == 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">有効（通読記録に表示する）</label>
          </div>
        </div>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= ($action === 'edit') ? '更新する' : '追加する' ?></button>
        <a href="members.php" class="btn btn-outline-secondary">キャンセル</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- メンバー一覧 -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>メンバー一覧（<?= count($members) ?>件）</span>
    <a href="?<?= $showInactive ? '' : 'show_inactive=1' ?>" class="btn btn-sm btn-outline-secondary">
      <?= $showInactive ? '有効のみ表示' : '無効も表示' ?>
    </a>
  </div>
  <div class="card-body p-0">
    <?php if (empty($members)): ?>
      <p class="text-muted text-center py-4">メンバーが登録されていません。</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>氏名</th><th>フリガナ</th><th>メール</th><th>電話</th><th>記録数</th><th>状態</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($members as $m): ?>
          <tr class="<?= $m['is_active'] ? '' : 'text-muted' ?>">
            <td><a href="records.php?member_id=<?= $m['id'] ?>"><?= h($m['name']) ?></a></td>
            <td class="small"><?= h($m['furigana'] ?? '—') ?></td>
            <td class="small"><?= h($m['email'] ?? '—') ?></td>
            <td class="small"><?= h($m['tel'] ?? '—') ?></td>
            <td class="small"><?= $m['record_count'] ?></td>
            <td>
              <?php if ($m['is_active']): ?>
                <span class="badge bg-success">有効</span>
              <?php else: ?>
                <span class="badge bg-secondary">無効</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a href="?action=edit&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                <i class="bi bi-pencil"></i>
              </a>
              <button type="button" class="btn btn-sm btn-outline-danger"
                      onclick="confirmDelete(<?= $m['id'] ?>, '<?= h($m['name']) ?>')">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
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
        <p><strong id="deleteName"></strong> を削除しますか？</p>
        <p class="text-danger small">関連する通読記録もすべて削除されます。この操作は取り消せません。</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
        <form method="post" id="deleteForm">
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
function confirmDelete(id, name) {
    document.getElementById('deleteName').textContent = name;
    document.getElementById('deleteId').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php renderFooter(); ?>
