<?php
require_once 'config.php';
requireLogin();

$search = trim($_GET['q'] ?? '');

try {
    $db = getDB();
    if ($search !== '') {
        $like = '%' . $search . '%';
        $stmt = $db->prepare("SELECT * FROM personal_info WHERE name LIKE ? OR furigana LIKE ? OR email LIKE ? ORDER BY id DESC");
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = $db->query("SELECT * FROM personal_info ORDER BY id DESC");
    }
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    die('DBエラー：' . $e->getMessage());
}

$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理画面 - 個人情報一覧</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container" style="max-width:1100px;">
    <div class="top-bar">
        <h1>管理画面 — 個人情報一覧</h1>
        <div>
            <a href="index.php" class="btn btn-success btn-sm">新規登録</a>
            &nbsp;
            <a href="logout.php" class="btn btn-secondary btn-sm">ログアウト</a>
        </div>
    </div>

    <?php if ($success === '1'): ?>
    <div class="alert alert-success">更新しました。</div>
    <?php elseif ($success === 'del'): ?>
    <div class="alert alert-success">削除しました。</div>
    <?php endif; ?>

    <form method="get" class="search-form" style="margin-bottom:16px;">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="氏名・ふりがな・メールで検索">
        <button type="submit" class="btn btn-primary btn-sm">検索</button>
        <?php if ($search): ?>
        <a href="admin.php" class="btn btn-secondary btn-sm">クリア</a>
        <?php endif; ?>
    </form>

    <p style="font-size:13px; color:#888; margin-bottom:10px;">
        <?= count($rows) ?> 件
    </p>

    <div style="overflow-x:auto;">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>氏名</th>
                <th>ふりがな</th>
                <th>性別</th>
                <th>生年月日</th>
                <th>メール</th>
                <th>電話</th>
                <th>学生/社会人</th>
                <th>既婚/独身</th>
                <th>同意</th>
                <th>登録日</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="12" style="text-align:center; padding:20px; color:#999;">データがありません</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['furigana']) ?></td>
                <td><?= htmlspecialchars($row['gender']) ?></td>
                <td><?= $row['birthday'] ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['phone']) ?></td>
                <td><?= htmlspecialchars($row['student_worker']) ?></td>
                <td><?= htmlspecialchars($row['marital_status']) ?></td>
                <td><?= $row['consent'] ? '✓' : '' ?></td>
                <td><?= date('Y/m/d', strtotime($row['created_at'])) ?></td>
                <td style="white-space:nowrap;">
                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">編集</a>
                    <a href="delete.php?id=<?= $row['id'] ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('ID<?= $row['id'] ?> を削除しますか？')">削除</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
</body>
</html>
