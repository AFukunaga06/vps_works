<?php
require_once __DIR__ . '/_layout.php';
$user = require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash_set('error','IDが不正です'); header('Location: instructor_applications.php'); exit; }

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM instructor_applications WHERE id = ?');
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) { flash_set('error','データが見つかりません'); header('Location: instructor_applications.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM instructor_applications WHERE id = ?')->execute([$id]);
        flash_set('success', "応募 #{$id} を削除しました");
        header('Location: instructor_applications.php');
        exit;
    }

    $status     = $_POST['status']     ?? $r['status'];
    $admin_note = trim((string)($_POST['admin_note'] ?? ''));
    if (!in_array($status, ['new','reviewing','accepted','rejected'], true)) $status = $r['status'];

    $u = $pdo->prepare('UPDATE instructor_applications SET status=?, admin_note=? WHERE id=?');
    $u->execute([$status, $admin_note, $id]);
    flash_set('success', "応募 #{$id} を更新しました");
    header("Location: instructor_application_edit.php?id={$id}");
    exit;
}

$csrf = csrf_token();
admin_header("講師応募 #{$id} 編集", $user);
?>
<h2 class="mb-3">講師応募 #<?= (int)$r['id'] ?> 編集</h2>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card p-3 bg-white">
      <h6 class="text-muted">応募情報</h6>
      <dl class="row mb-0">
        <dt class="col-3">お名前</dt><dd class="col-9"><?= h($r['name']) ?></dd>
        <dt class="col-3">メール</dt><dd class="col-9"><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a></dd>
        <dt class="col-3">電話</dt><dd class="col-9"><?= h($r['phone']) ?></dd>
        <dt class="col-3">プロフィール</dt><dd class="col-9"><?= nl2br(h($r['profile'])) ?></dd>
        <dt class="col-3">スキル</dt><dd class="col-9"><?= nl2br(h($r['skills'])) ?></dd>
        <dt class="col-3">対応可能曜日</dt><dd class="col-9"><?= h($r['available_days']) ?></dd>
        <dt class="col-3">志望動機</dt><dd class="col-9"><?= nl2br(h($r['motivation'])) ?></dd>
        <dt class="col-3">応募日時</dt><dd class="col-9 text-muted small"><?= h($r['created_at']) ?></dd>
      </dl>
    </div>
  </div>
  <div class="col-md-5">
    <form method="post" class="card p-3 bg-white">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <div class="mb-3">
        <label class="form-label">ステータス</label>
        <select class="form-select" name="status">
          <?php foreach (['new'=>'新規','reviewing'=>'検討中','accepted'=>'採用','rejected'=>'不採用'] as $k=>$v): ?>
            <option value="<?= h($k) ?>" <?= $r['status']===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">管理メモ</label>
        <textarea class="form-control" name="admin_note" rows="6"><?= h($r['admin_note']) ?></textarea>
      </div>
      <div class="d-flex justify-content-between">
        <a href="instructor_applications.php" class="btn btn-outline-secondary">一覧へ</a>
        <div>
          <button class="btn btn-outline-danger" name="action" value="delete"
                  onclick="return confirm('削除しますか？');">削除</button>
          <button class="btn btn-primary" type="submit">保存</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php admin_footer();
