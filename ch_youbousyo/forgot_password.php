<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name === '' || $email === '') {
        $error = '氏名とメールアドレスを入力してください。';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM personal_info WHERE name = ? AND email = ?");
        $stmt->execute([$name, $email]);
        $row  = $stmt->fetch();

        if ($row) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $db->prepare("UPDATE personal_info SET reset_token=?, reset_expires=? WHERE id=?")
               ->execute([$token, $expires, $row['id']]);

            $resetUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/ch_youbousyo/reset_password.php?token=' . $token;
            $subject  = 'パスワードリセットのご案内';
            $body     = "氏名：{$name} 様\n\n以下のURLからパスワードを再設定してください。\n有効期限：1時間\n\n{$resetUrl}\n\nこのメールに心当たりのない場合は無視してください。";
            $headers  = 'From: noreply@' . $_SERVER['HTTP_HOST'];

            mail($email, $subject, $body, $headers);
            $message = '登録済みのメールアドレスにリセット用URLを送信しました。';
        } else {
            // 存在しない場合も同じメッセージ（セキュリティ対策）
            $message = '登録済みのメールアドレスにリセット用URLを送信しました。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>パスワードリセット</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-box">
    <h1>パスワードリセット</h1>
    <p style="font-size:13px; color:#888; margin-bottom:20px; text-align:center;">登録時の氏名とメールアドレスを入力してください</p>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php else: ?>
    <form method="post">
        <div class="form-group">
            <label>氏名</label>
            <input type="text" name="name" required autofocus>
        </div>
        <div class="form-group">
            <label>メールアドレス</label>
            <input type="email" name="email" required>
        </div>
        <div style="text-align:center; margin-top:24px;">
            <button type="submit" class="btn btn-primary" style="width:100%;">送信</button>
        </div>
    </form>
    <?php endif; ?>
    <div style="text-align:center; margin-top:16px;">
        <a href="login.php" style="font-size:13px; color:#3498db;">ログイン画面に戻る</a>
    </div>
</div>
</body>
</html>
