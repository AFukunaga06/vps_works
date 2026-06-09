<?php
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

require_once __DIR__ . '/login_config.php';

if (!empty($_SESSION['login_ok'])) {
    header("Location: main_03.php");
    exit;
}

$error      = '';
$change_msg = '';
$change_err = '';

// ── ログイン処理 ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $user = $_POST['userId']   ?? '';
    $pass = $_POST['password'] ?? '';
    if ($user === $LOGIN_USER && $pass === $LOGIN_PASS) {
        session_regenerate_id(true);
        $_SESSION['login_ok'] = true;
        header("Location: main_03.php");
        exit;
    } else {
        $error = "IDまたはパスワードが違います";
    }
}

// ── 認証情報変更 ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_cred') {
    $cur_pass  = $_POST['cur_pass']  ?? '';
    $new_user  = trim($_POST['new_user']  ?? '');
    $new_pass  = $_POST['new_pass']  ?? '';
    $new_pass2 = $_POST['new_pass2'] ?? '';

    if ($cur_pass !== $LOGIN_PASS) {
        $change_err = '現在のパスワードが違います';
    } elseif ($new_user === '') {
        $change_err = '新しいIDを入力してください';
    } elseif ($new_pass === '') {
        $change_err = '新しいパスワードを入力してください';
    } elseif ($new_pass !== $new_pass2) {
        $change_err = '新しいパスワードが一致しません';
    } else {
        $content = "<?php\n\$LOGIN_USER = " . var_export($new_user, true) . ";\n\$LOGIN_PASS = "REDACTED_FOR_PUBLIC";\n";
        file_put_contents(__DIR__ . '/login_config.php', $content);
        $LOGIN_USER  = $new_user;
        $LOGIN_PASS  = $new_pass;
        $change_msg  = 'IDとパスワードを変更しました';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>つばさ名簿 - ログイン</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    min-height: 100vh;
    background: #f0f4f8;
    display: flex;
    flex-direction: column;
    align-items: center;
    font-family: 'Helvetica Neue', Arial, sans-serif;
    padding-top: 70px;
    padding-bottom: 40px;
}
.login-header {
    background: #2c5f8a;
    color: #fff;
    width: 100%;
    padding: 12px 20px;
    font-size: 1.1rem;
    font-weight: bold;
    position: fixed;
    top: 0;
    left: 0;
}
.login-box {
    background: #fff;
    padding: 36px 32px;
    border-radius: 10px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    width: 340px;
    margin-top: 20px;
}
.change-box {
    background: #fff;
    padding: 28px 32px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    width: 340px;
    margin-top: 16px;
}
h2 {
    text-align: center;
    margin-bottom: 20px;
    color: #2c5f8a;
    font-size: 1.15rem;
}
h3 {
    font-size: .95rem;
    color: #555;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
}
label {
    display: block;
    font-size: .83rem;
    color: #555;
    margin-bottom: 4px;
    margin-top: 12px;
}
label:first-of-type { margin-top: 0; }
input[type=text],
input[type=password] {
    width: 100%;
    padding: 9px 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: .95rem;
}
input:focus {
    outline: none;
    border-color: #2c5f8a;
    box-shadow: 0 0 0 2px rgba(44,95,138,0.15);
}
button {
    width: 100%;
    padding: 10px;
    border: none;
    border-radius: 6px;
    background: #2c5f8a;
    color: #fff;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 16px;
}
button:hover { background: #1a4a70; }
.btn-change {
    background: #6d4c41;
    margin-top: 12px;
}
.btn-change:hover { background: #5d4037; }
.error   { color: #c62828; background: #ffebee; border-radius: 6px; padding: 8px 12px; font-size: .88rem; margin-bottom: 12px; }
.success { color: #1b5e20; background: #e8f5e9; border-radius: 6px; padding: 8px 12px; font-size: .88rem; margin-bottom: 12px; }
.cred-hint {
    background: #f5f8fc;
    border: 1px solid #d0e0f0;
    border-radius: 6px;
    padding: 10px 14px;
    margin-bottom: 18px;
    font-size: .85rem;
    color: #444;
    line-height: 1.8;
}
.cred-hint strong { color: #2c5f8a; }
</style>
</head>
<body>
<div class="login-header">つばさ名簿</div>

<!-- ログインフォーム -->
<div class="login-box">
  <h2>ログイン</h2>
  <div class="cred-hint">
    ID：<strong><?= htmlspecialchars($LOGIN_USER) ?></strong><br>
    PW：<strong><?= htmlspecialchars($LOGIN_PASS) ?></strong>
  </div>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <input type="hidden" name="action" value="login">
    <label>ユーザーID</label>
    <input type="text" name="userId" placeholder="ユーザーID" required autofocus>
    <label>パスワード</label>
    <input type="password" name="password" placeholder="パスワード" required>
    <button type="submit">ログイン</button>
  </form>
</div>

<!-- ID・パスワード変更フォーム -->
<div class="change-box">
  <h3>🔧 IDとパスワードを変更</h3>
  <?php if ($change_msg): ?>
    <div class="success"><?= htmlspecialchars($change_msg) ?></div>
  <?php endif; ?>
  <?php if ($change_err): ?>
    <div class="error"><?= htmlspecialchars($change_err) ?></div>
  <?php endif; ?>
  <form method="POST">
    <input type="hidden" name="action" value="change_cred">
    <label>現在のパスワード</label>
    <input type="password" name="cur_pass" placeholder="現在のパスワード" required>
    <label>新しいID</label>
    <input type="text" name="new_user" value="<?= htmlspecialchars($LOGIN_USER) ?>" required>
    <label>新しいパスワード</label>
    <input type="password" name="new_pass" placeholder="新しいパスワード" required>
    <label>新しいパスワード（確認）</label>
    <input type="password" name="new_pass2" placeholder="もう一度入力" required>
    <button type="submit" class="btn-change">変更する</button>
  </form>
</div>
</body>
</html>
