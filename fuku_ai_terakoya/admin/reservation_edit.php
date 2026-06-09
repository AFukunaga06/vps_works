<?php
require_once __DIR__ . '/_layout.php';
$user = require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash_set('error','IDが不正です'); header('Location: reservations.php'); exit; }

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM reservations WHERE id = ?');
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) { flash_set('error','データが見つかりません'); header('Location: reservations.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM reservations WHERE id = ?')->execute([$id]);
        flash_set('success', "予約 #{$id} を削除しました");
        header('Location: reservations.php');
        exit;
    }

    $status     = $_POST['status']     ?? $r['status'];
    $zoom_url   = trim((string)($_POST['zoom_url']   ?? ''));
    $admin_note = trim((string)($_POST['admin_note'] ?? ''));
    if (!in_array($status, ['pending','confirmed','done','cancelled'], true)) $status = $r['status'];

    $u = $pdo->prepare('UPDATE reservations SET status=?, zoom_url=?, admin_note=? WHERE id=?');
    $u->execute([$status, $zoom_url, $admin_note, $id]);
    flash_set('success', "予約 #{$id} を更新しました");
    header("Location: reservation_edit.php?id={$id}");
    exit;
}

$csrf = csrf_token();
admin_header("予約 #{$id} 編集", $user);
?>
<h2 class="mb-3">予約 #<?= (int)$r['id'] ?> 編集</h2>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card p-3 bg-white">
      <h6 class="text-muted">予約情報</h6>
      <dl class="row mb-0">
        <dt class="col-4">日時</dt><dd class="col-8"><?= h($r['reserve_date']) ?> <?= h($r['reserve_time']) ?></dd>
        <dt class="col-4">種別</dt><dd class="col-8"><?= h($r['type']==='session' ? '本講座' : 'オリエンテーション') ?></dd>
        <dt class="col-4">お名前</dt><dd class="col-8"><?= h($r['name']) ?></dd>
        <dt class="col-4">メール</dt><dd class="col-8"><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a></dd>
        <dt class="col-4">目的</dt><dd class="col-8"><?= nl2br(h($r['goal'])) ?></dd>
        <dt class="col-4">作成</dt><dd class="col-8 text-muted small"><?= h($r['created_at']) ?></dd>
        <dt class="col-4">更新</dt><dd class="col-8 text-muted small"><?= h($r['updated_at']) ?></dd>
      </dl>
    </div>
  </div>
  <div class="col-md-6">
    <form method="post" class="card p-3 bg-white">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <div class="mb-3">
        <label class="form-label">ステータス</label>
        <select class="form-select" name="status">
          <?php foreach (['pending'=>'未確定','confirmed'=>'確定','done'=>'完了','cancelled'=>'キャンセル'] as $k=>$v): ?>
            <option value="<?= h($k) ?>" <?= $r['status']===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Zoom URL</label>
        <input class="form-control" name="zoom_url" value="<?= h($r['zoom_url']) ?>" placeholder="https://...">
      </div>
      <div class="mb-3">
        <label class="form-label">管理メモ</label>
        <textarea class="form-control" name="admin_note" rows="4"><?= h($r['admin_note']) ?></textarea>
      </div>
      <div class="d-flex justify-content-between">
        <a href="reservations.php" class="btn btn-outline-secondary">一覧へ戻る</a>
        <div>
          <button class="btn btn-outline-danger" name="action" value="delete" formnovalidate
                  onclick="return confirm('削除しますか？');">削除</button>
          <button class="btn btn-primary" type="submit">保存</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php admin_footer();
