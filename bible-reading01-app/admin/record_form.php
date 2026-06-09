<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$pdo = getDB();
$message = '';
$error   = '';
$editId  = intval($_GET['id'] ?? 0);
$record  = null;

// --- 既存レコード取得 ---
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM reading_daily_logs WHERE id = ?');
    $stmt->execute([$editId]);
    $record = $stmt->fetch();
    if (!$record) { header('Location: records.php'); exit; }
}

// --- POST処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('不正なリクエストです。');
    }

    $memberId      = intval($_POST['member_id'] ?? 0);
    $readingDate   = $_POST['reading_date'] ?? '';
    $passageSummary = trim($_POST['passage_summary'] ?? '') ?: null;
    $status        = intval($_POST['status'] ?? 0);
    $note          = trim($_POST['note'] ?? '') ?: null;
    $postId        = intval($_POST['id'] ?? 0);

    // バリデーション
    if ($memberId <= 0) {
        $error = 'メンバーを選択してください。';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $readingDate)) {
        $error = '日付を正しく入力してください。';
    } elseif ($status < 0 || $status > 2) {
        $error = '通読状態が不正です。';
    } else {
        try {
            if ($postId > 0) {
                $stmt = $pdo->prepare('
                    UPDATE reading_daily_logs
                    SET member_id=?, reading_date=?, passage_summary=?, status=?, note=?
                    WHERE id=?
                ');
                $stmt->execute([$memberId, $readingDate, $passageSummary, $status, $note, $postId]);
                $message = '通読記録を更新しました。';
                $editId  = $postId;
                $record  = $pdo->prepare('SELECT * FROM reading_daily_logs WHERE id = ?');
                $record->execute([$editId]);
                $record  = $record->fetch();
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO reading_daily_logs (member_id, reading_date, passage_summary, status, note)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE passage_summary=VALUES(passage_summary), status=VALUES(status), note=VALUES(note)
                ');
                $stmt->execute([$memberId, $readingDate, $passageSummary, $status, $note]);
                $message = '通読記録を登録しました。';
                // フォームをリセット（継続入力用に日付・メンバーは保持）
                $record = null;
                $editId = 0;
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'データの保存に失敗しました。';
        }
    }
}

// メンバー一覧
$members = $pdo->query('SELECT id, name, furigana FROM members WHERE is_active=1 ORDER BY furigana, name')->fetchAll();

// デフォルト値
$defaultMemberId    = $record['member_id']      ?? intval($_GET['member_id'] ?? 0);
$defaultDate        = $record['reading_date']   ?? ($_GET['date'] ?? date('Y-m-d'));
$defaultPassage     = $record['passage_summary'] ?? '';
$defaultStatus      = $record['status']         ?? 2;
$defaultNote        = $record['note']           ?? '';

renderHeader($editId > 0 ? '通読記録 編集' : '通読記録 追加', 'records');
$statusLabels = STATUS_LABELS;
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h4 class="mb-0">
    <i class="bi bi-<?= $editId > 0 ? 'pencil' : 'plus-circle' ?> me-2"></i>
    通読記録 <?= $editId > 0 ? '編集' : '追加' ?>
  </h4>
  <a href="records.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> 一覧へ戻る</a>
</div>

<?php if ($message): ?>
  <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i><?= h($message) ?>
    <?php if ($editId === 0): ?>
      続けて追加できます。
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= h($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width: 640px;">
  <div class="card-body p-4">
    <form method="post">
      <?= csrfInput() ?>
      <input type="hidden" name="id" value="<?= $editId ?>">

      <div class="mb-3">
        <label class="form-label">メンバー <span class="text-danger">*</span></label>
        <select name="member_id" class="form-select" required>
          <option value="">選択してください</option>
          <?php foreach ($members as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $defaultMemberId == $m['id'] ? 'selected' : '' ?>>
              <?= h($m['name']) ?><?= $m['furigana'] ? '（' . h($m['furigana']) . '）' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">通読日 <span class="text-danger">*</span></label>
        <input type="date" name="reading_date" class="form-control" required
               value="<?= h($defaultDate) ?>" max="<?= date('Y-m-d') ?>">
        <div class="form-text">同じメンバー・同じ日付の記録は上書きされます。</div>
      </div>

      <div class="mb-3">
        <label class="form-label">通読箇所</label>
        <input type="text" name="passage_summary" class="form-control" maxlength="500"
               placeholder="例: 創世記1〜3章、マタイ1章"
               value="<?= h($defaultPassage) ?>">
        <div class="form-text">読んだ聖書箇所をテキストで入力してください。</div>
      </div>

      <div class="mb-3">
        <label class="form-label">通読状態 <span class="text-danger">*</span></label>
        <div class="d-flex gap-3 mt-1">
          <?php foreach ($statusLabels as $val => $label): ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="status" id="status_<?= $val ?>"
                     value="<?= $val ?>" <?= $defaultStatus == $val ? 'checked' : '' ?>>
              <label class="form-check-label" for="status_<?= $val ?>"><?= $label ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label">メモ・感想</label>
        <textarea name="note" class="form-control" rows="4"
                  placeholder="気づいたこと、感想など自由に記入してください"><?= h($defaultNote) ?></textarea>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg me-1"></i><?= $editId > 0 ? '更新する' : '登録する' ?>
        </button>
        <?php if ($editId > 0): ?>
          <a href="records.php" class="btn btn-outline-secondary">キャンセル</a>
        <?php else: ?>
          <a href="records.php" class="btn btn-outline-secondary">一覧へ</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- 聖書箇所入力ヘルパー -->
<div class="card mt-3" style="max-width: 640px;">
  <div class="card-header small fw-semibold">よく使う聖書箇所（クリックで入力）</div>
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2" id="bookButtons">
      <?php
      $quickBooks = ['創世記', '出エジプト記', 'レビ記', '民数記', '申命記',
                     'ヨシュア記', 'ルツ記', 'サムエル記', 'マタイ', 'マルコ',
                     'ルカ', 'ヨハネ', '使徒言行録', 'ローマ', 'コリント',
                     'ガラテヤ', 'エフェソ', 'フィリピ', 'コロサイ', 'ヨハネの黙示録'];
      foreach ($quickBooks as $book): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary"
                onclick="appendPassage('<?= h($book) ?>')">
          <?= h($book) ?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function appendPassage(book) {
    const input = document.querySelector('input[name="passage_summary"]');
    const current = input.value.trim();
    input.value = current ? current + '、' + book : book;
    input.focus();
}
</script>

<?php renderFooter(); ?>
