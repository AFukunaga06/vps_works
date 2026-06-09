<?php
require_once 'config.php';
session_start();

$error = '';
$success = '';

// ログイン処理
if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['fuku_admin'] = true;
    } else {
        $error = 'パスワードが違います。';
    }
}

// ログアウト
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$isLoggedIn = !empty($_SESSION['fuku_admin']);

if ($isLoggedIn) {
    try {
        $pdo = getDB();

        // 作品削除
        if (isset($_POST['delete_id'])) {
            $stmt = $pdo->prepare("DELETE FROM works WHERE id = ?");
            $stmt->execute([(int)$_POST['delete_id']]);
            $success = '削除しました。';
        }

        // 作品追加・更新
        if (isset($_POST['action']) && $_POST['action'] === 'save') {
            $title = trim($_POST['title'] ?? '');
            $desc  = trim($_POST['description'] ?? '');
            $url   = trim($_POST['url'] ?? '#');
            $sort  = (int)($_POST['sort_order'] ?? 0);
            $active = isset($_POST['is_active']) ? 1 : 0;
            $id    = (int)($_POST['id'] ?? 0);

            if ($title === '') {
                $error = '作品名を入力してください。';
            } elseif ($id > 0) {
                $stmt = $pdo->prepare("UPDATE works SET title=?, description=?, url=?, sort_order=?, is_active=? WHERE id=?");
                $stmt->execute([$title, $desc, $url, $sort, $active, $id]);
                $success = '更新しました。';
            } else {
                $stmt = $pdo->prepare("INSERT INTO works (title, description, url, sort_order, is_active) VALUES (?,?,?,?,?)");
                $stmt->execute([$title, $desc, $url, $sort, $active]);
                $success = '追加しました。';
            }
        }

        // 作品一覧取得
        $works = $pdo->query("SELECT * FROM works ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

        // 編集対象
        $editing = null;
        if (isset($_GET['edit'])) {
            $stmt = $pdo->prepare("SELECT * FROM works WHERE id = ?");
            $stmt->execute([(int)$_GET['edit']]);
            $editing = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = 'DB接続エラー: ' . htmlspecialchars($e->getMessage());
        $works = [];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - フククの作品集</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Hiragino Sans', sans-serif;
            background: linear-gradient(135deg, #1a0835, #6b1f7a, #e8621a, #0a3c78);
            min-height: 100vh;
            color: #fff;
            padding: 40px 16px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { font-size: 1.8rem; margin-bottom: 8px; color: #ffe0b2; }
        h2 { font-size: 1.2rem; margin-bottom: 20px; color: #ffcc80; }
        .card {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,220,180,0.25);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 24px;
            backdrop-filter: blur(12px);
        }
        .alert-success { color: #a5d6a7; margin-bottom: 16px; }
        .alert-error   { color: #ef9a9a; margin-bottom: 16px; }
        label { display: block; font-size: 0.88rem; color: #ffcc80; margin-bottom: 6px; }
        input[type=text], input[type=url], input[type=number], textarea {
            width: 100%;
            padding: 10px 14px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,200,140,0.3);
            border-radius: 8px;
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            margin-bottom: 16px;
        }
        input:focus, textarea:focus { border-color: rgba(255,150,80,0.7); }
        textarea { min-height: 90px; resize: vertical; }
        .checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 0.92rem; color: #ffe0b2; margin-bottom: 16px; }
        .btn { padding: 10px 24px; border: none; border-radius: 50px; font-size: 0.9rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary   { background: linear-gradient(135deg, #e8621a, #c94075); color: #fff; }
        .btn-secondary { background: rgba(255,255,255,0.15); color: #ffe0b2; }
        .btn-danger    { background: rgba(200,50,50,0.5); color: #fff; }
        .btn + .btn { margin-left: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(255,200,140,0.15); font-size: 0.9rem; }
        th { color: #ffcc80; font-weight: 600; }
        td { color: rgba(255,235,210,0.9); }
        tr:last-child td { border-bottom: none; }
        .badge-on  { color: #a5d6a7; }
        .badge-off { color: #ef9a9a; }
        .nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
        .login-box { max-width: 400px; margin: 80px auto; }
        input[type=password] {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,200,140,0.3);
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            margin-bottom: 16px;
            outline: none;
        }
    </style>
</head>
<body>
<div class="container">
<?php if (!$isLoggedIn): ?>
    <div class="login-box">
        <div class="card">
            <h1 style="text-align:center;margin-bottom:24px;">管理ログイン</h1>
            <?php if ($error): ?><p class="alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <form method="post">
                <label>パスワード</label>
                <input type="password" name="password" autofocus>
                <button type="submit" class="btn btn-primary" style="width:100%">ログイン</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="nav">
        <div>
            <h1>管理画面</h1>
            <p style="color:rgba(255,200,140,0.6);font-size:0.85rem;">フククの作品集</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">← サイトを見る</a>
            <a href="?logout=1" class="btn btn-danger">ログアウト</a>
        </div>
    </div>

    <?php if ($success): ?><p class="alert-success">✅ <?= htmlspecialchars($success) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="alert-error">❌ <?= htmlspecialchars($error) ?></p><?php endif; ?>

    <!-- 追加・編集フォーム -->
    <div class="card">
        <h2><?= $editing ? '作品を編集' : '作品を追加' ?></h2>
        <form method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
            <label>作品名 *</label>
            <input type="text" name="title" value="<?= htmlspecialchars($editing['title'] ?? '') ?>" required>
            <label>説明文</label>
            <textarea name="description"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
            <label>URL</label>
            <input type="text" name="url" value="<?= htmlspecialchars($editing['url'] ?? '#') ?>">
            <label>表示順（数字が小さいほど上）</label>
            <input type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>">
            <label class="checkbox-label">
                <input type="checkbox" name="is_active" <?= !isset($editing) || $editing['is_active'] ? 'checked' : '' ?>>
                表示する
            </label>
            <button type="submit" class="btn btn-primary"><?= $editing ? '更新する' : '追加する' ?></button>
            <?php if ($editing): ?>
                <a href="admin.php" class="btn btn-secondary">キャンセル</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- 作品一覧 -->
    <div class="card">
        <h2>作品一覧（<?= count($works) ?>件）</h2>
        <?php if (empty($works)): ?>
            <p style="color:rgba(255,200,140,0.5);">まだ作品がありません。</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>順</th><th>作品名</th><th>説明</th><th>状態</th><th>操作</th></tr>
            </thead>
            <tbody>
            <?php foreach ($works as $w): ?>
                <tr>
                    <td><?= (int)$w['sort_order'] ?></td>
                    <td><?= htmlspecialchars($w['title']) ?></td>
                    <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($w['description']) ?></td>
                    <td class="<?= $w['is_active'] ? 'badge-on' : 'badge-off' ?>"><?= $w['is_active'] ? '表示' : '非表示' ?></td>
                    <td>
                        <a href="?edit=<?= $w['id'] ?>" class="btn btn-secondary" style="font-size:0.8rem;padding:6px 14px;">編集</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('削除しますか？')">
                            <input type="hidden" name="delete_id" value="<?= $w['id'] ?>">
                            <button type="submit" class="btn btn-danger" style="font-size:0.8rem;padding:6px 14px;">削除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
</body>
</html>
