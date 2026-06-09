<?php
require_once __DIR__ . '/config.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic     = trim($_POST['topic'] ?? '');
    $submitter = trim($_POST['submitter'] ?? ''); // 空白なら匿名
    $detail    = trim($_POST['detail'] ?? '');

    if ($topic === '') {
        $error = '調査テーマを入力してください。';
    } else {
        $db = get_db();
        $stmt = $db->prepare("INSERT INTO research_requests (topic, submitter, detail) VALUES (:topic, :submitter, :detail)");
        $stmt->execute([
            ':topic'     => $topic,
            ':submitter' => $submitter !== '' ? $submitter : null,
            ':detail'    => $detail !== '' ? $detail : null,
        ]);
        $success = true;
        $new_id = $db->lastInsertId();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>新規登録 — <?= APP_NAME ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <h1><?= APP_NAME ?></h1>
  <nav>
    <a href="index.php">一覧</a>
    <a href="register.php">新規登録</a>
  </nav>
</header>

<div class="container">
  <div class="card">
    <h2>調査依頼の登録</h2>

    <?php if ($success): ?>
      <div class="alert alert-success">
        登録しました。調査ループが自動的に処理します。
        <a href="view.php?id=<?= $new_id ?>">結果を確認する</a> / <a href="index.php">一覧へ戻る</a>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="post" action="register.php">
      <label>投稿者名（任意）</label>
      <input type="text" name="submitter" placeholder="名前を入力（空欄で匿名）" maxlength="100"
             value="<?= htmlspecialchars($_POST['submitter'] ?? '') ?>">
      <p class="hint">空欄のまま送信すると「匿名」として登録されます。</p>

      <label>調査テーマ・質問 <span style="color:red">*</span></label>
      <input type="text" name="topic" placeholder="例：再生可能エネルギーの最新動向について" maxlength="500" required
             value="<?= htmlspecialchars($_POST['topic'] ?? '') ?>">

      <label>補足・詳細（任意）</label>
      <textarea name="detail" placeholder="調査してほしい内容の詳細や条件があれば記入してください"><?= htmlspecialchars($_POST['detail'] ?? '') ?></textarea>

      <div class="mt">
        <button type="submit" class="btn btn-primary">登録する</button>
        <a href="index.php" style="margin-left:12px; color:#666;">キャンセル</a>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
