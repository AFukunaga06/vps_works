<?php
/**
 * ユーザー登録画面（管理者専用）
 */
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$error = '';
$success = '';
$csrfToken = generateCsrfToken();

// 登録処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'user';
    $token    = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($token)) {
        $error = '不正なリクエストです。';
    } elseif ($username === '' || $password === '') {
        $error = 'ユーザー名とパスワードを入力してください。';
    } elseif (strlen($password) < 8) {
        $error = 'パスワードは8文字以上にしてください。';
    } elseif (!in_array($role, ['admin', 'user'])) {
        $error = '不正な権限です。';
    } else {
        $pdo = getDB();
        
        // 重複チェック
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $error = 'そのユーザー名は既に使用されています。';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
            $stmt->execute([$username, $hash, $role]);
            $success = 'ユーザー「' . h($username) . '」を登録しました。';
        }
    }
    $csrfToken = generateCsrfToken();
}

// ユーザー一覧取得
$pdo = getDB();
$users = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ユーザー管理 - 日曜礼拝 出欠管理</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { background: #e7f6e7; }
    .container {
      max-width: 700px;
      margin: 30px auto;
      padding: 0 14px;
    }
    .card {
      background: #fff;
      border: 1px solid rgba(0,0,0,.12);
      border-radius: 18px;
      box-shadow: 0 10px 26px rgba(0,0,0,.08);
      padding: 28px 24px;
      margin-bottom: 20px;
    }
    h1 { font-size: 20px; color: #0f5f2a; margin: 0 0 20px; }
    h2 { font-size: 16px; color: #0f5f2a; margin: 0 0 14px; }
    .form-group { margin-bottom: 14px; }
    .form-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 4px; }
    .form-group input, .form-group select {
      width: 100%; padding: 10px 12px; font-size: 14px;
      border: 1px solid rgba(0,0,0,.12); border-radius: 10px;
    }
    .btn {
      padding: 10px 20px; font-size: 14px; font-weight: 700;
      color: #fff; background: #1f7a3a; border: none;
      border-radius: 10px; cursor: pointer;
    }
    .btn:hover { opacity: 0.9; }
    .btn-back {
      background: transparent; color: #1f7a3a;
      border: 1px solid rgba(0,0,0,.12); font-weight: 600;
    }
    .error { background: #fff0f0; border: 1px solid #ffcccc; color: #c00; padding: 10px; border-radius: 10px; margin-bottom: 14px; font-size: 13px; }
    .success { background: #f0fff0; border: 1px solid #ccffcc; color: #060; padding: 10px; border-radius: 10px; margin-bottom: 14px; font-size: 13px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { padding: 10px; border-bottom: 1px solid rgba(0,0,0,.12); font-size: 14px; text-align: left; }
    th { background: #f4fff4; font-size: 12px; color: rgba(0,0,0,.55); }
    .nav { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; }
  </style>
</head>
<body>
  <div class="container">
    <div class="nav">
      <a href="index.php" class="btn btn-back">← メイン画面へ戻る</a>
      <a href="logout.php" class="btn btn-back">ログアウト</a>
    </div>

    <div class="card">
      <h1>ユーザー管理</h1>
      <p style="font-size:13px;color:rgba(0,0,0,.55);">ログイン中: <?= h($_SESSION['username']) ?>（<?= h($_SESSION['role']) ?>）</p>

      <?php if ($error): ?>
        <div class="error"><?= h($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
      <?php endif; ?>

      <h2>新規ユーザー登録</h2>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        
        <div class="form-group">
          <label for="username">ユーザー名</label>
          <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
          <label for="password">パスワード（8文字以上）</label>
          <input type="password" id="password" name="password" required minlength="8">
        </div>
        <div class="form-group">
          <label for="role">権限</label>
          <select id="role" name="role">
            <option value="user">一般ユーザー</option>
            <option value="admin">管理者</option>
          </select>
        </div>
        <button type="submit" class="btn">ユーザー登録</button>
      </form>
    </div>

    <div class="card">
      <h2>登録済みユーザー一覧</h2>
      <table>
        <thead>
          <tr><th>ID</th><th>ユーザー名</th><th>権限</th><th>作成日</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?= h($u['id']) ?></td>
            <td><?= h($u['username']) ?></td>
            <td><?= h($u['role']) ?></td>
            <td><?= h($u['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
