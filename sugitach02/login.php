<?php
session_start();

/* ★ 自動入力（オートコンプリート）設定
   true  = ブラウザの自動入力を許可（IDとパスワードが自動で入る）
   false = 自動入力を無効化 */
define('AUTOCOMPLETE', true);

/* ★ ログイン情報（固定） */
$VALID_USER = 'sugita';
$VALID_PASS_HASH = 'REDACTED_FOR_PUBLIC';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user = $_POST['userId'] ?? '';
    $pass = $_POST['password'] ?? '';

    /* 認証チェック */
    if ($user === $VALID_USER && password_verify($pass, $VALID_PASS_HASH)) {

        // 同時ログインチェック（設定値を使用）
        require_once __DIR__ . '/config.php';
        $s = $pdo->query("SELECT value FROM settings WHERE key_name = 'lock_timeout_minutes'")->fetch();
        $TIMEOUT = (int)($s ? $s['value'] : 5) * 60;
        $stmt = $pdo->query("SELECT session_id, last_activity FROM active_session LIMIT 1");
        $active = $stmt->fetch();
        if ($active && (time() - $active['last_activity']) < $TIMEOUT) {
            $error = "現在、他の端末でログイン中です。
しばらく待ってから再度お試しください。";
        } else {
            // 古いレコードを削除して新規登録
            $pdo->exec("DELETE FROM active_session");
            $pdo->prepare("INSERT INTO active_session (session_id, last_activity) VALUES (?, ?)")
                ->execute([session_id(), time()]);
            $_SESSION['login_ok'] = true;
            header("Location: main02.php");
            exit;
        }
    } else {
        $error = "IDまたはパスワードが違います";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>出席管理ログイン</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@300;400;600&family=Zen+Kaku+Gothic+New:wght@300;400;500&display=swap" rel="stylesheet">

<style>
:root {
    --green-deep:   #1a3d23;
    --green-mid:    #2e6b3e;
    --green-light:  #5a9e66;
    --green-pale:   #c8e6cc;
    --cream:        #f5f0e8;
    --white:        #ffffff;
    --shadow:       rgba(26, 61, 35, 0.18);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    min-height: 100vh;
    background-color: #dff0e1;
    background-image:
        radial-gradient(ellipse 80% 60% at 20% 80%, rgba(180,220,185,0.4) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 10%, rgba(160,210,168,0.3) 0%, transparent 55%);
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: 'Zen Kaku Gothic New', 'Yu Gothic', sans-serif;
    overflow: hidden;
}

/* 装飾的な背景の葉模様 */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
        radial-gradient(circle at 15% 25%, rgba(200,230,204,0.04) 0%, transparent 40%),
        radial-gradient(circle at 85% 75%, rgba(200,230,204,0.04) 0%, transparent 40%);
    pointer-events: none;
}

.container {
    width: 420px;
    max-width: 95vw;
    position: relative;
    animation: slideUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* アクセントライン（上部） */
.accent-line {
    height: 4px;
    background: linear-gradient(90deg, var(--green-light), var(--green-pale), var(--green-light));
    border-radius: 2px 2px 0 0;
}

.card {
    background: rgba(255,255,255,0.97);
    padding: 48px 44px 40px;
    border-radius: 0 0 16px 16px;
    box-shadow:
        0 25px 60px rgba(0,0,0,0.35),
        0 8px 20px rgba(0,0,0,0.15),
        0 0 0 1px rgba(255,255,255,0.08);
    position: relative;
}

/* ロゴ・タイトル */
.logo-area {
    text-align: center;
    margin-bottom: 36px;
}

.logo-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--green-mid), var(--green-light));
    border-radius: 50%;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 20px rgba(46,107,62,0.35);
}

.logo-icon svg {
    width: 30px;
    height: 30px;
    fill: white;
}

.logo-area h1 {
    font-family: 'Noto Serif JP', serif;
    font-size: 22px;
    font-weight: 600;
    color: var(--green-deep);
    letter-spacing: 0.08em;
    line-height: 1.3;
}

.logo-area p {
    font-size: 12px;
    color: #8aad90;
    letter-spacing: 0.15em;
    margin-top: 6px;
    text-transform: uppercase;
}

/* フォーム */
.form-group {
    margin-bottom: 18px;
    position: relative;
}

label {
    display: block;
    font-size: 11px;
    font-weight: 500;
    color: #6b8f72;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    margin-bottom: 7px;
}

.input-wrap {
    position: relative;
}

.input-wrap svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    stroke: #aac8ae;
    pointer-events: none;
    transition: stroke 0.2s;
}

input[type="text"],
input[type="password"] {
    width: 100%;
    padding: 13px 14px 13px 42px;
    border: 1.5px solid #d4e8d6;
    border-radius: 8px;
    font-size: 15px;
    font-family: 'Zen Kaku Gothic New', sans-serif;
    color: var(--green-deep);
    background: #fafcfa;
    transition: border-color 0.25s, box-shadow 0.25s, background 0.25s;
    outline: none;
}

input:focus {
    border-color: var(--green-light);
    background: var(--white);
    box-shadow: 0 0 0 3px rgba(90,158,102,0.15);
}

input:focus + svg,
.input-wrap:has(input:focus) svg {
    stroke: var(--green-light);
}

/* ポップアップオーバーレイ */
.popup-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.popup-box {
    background: #fff;
    border-radius: 14px;
    padding: 36px 36px 28px;
    width: 320px;
    max-width: 90vw;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    animation: popIn 0.3s cubic-bezier(0.16,1,0.3,1);
}

@keyframes popIn {
    from { opacity: 0; transform: scale(0.85) translateY(10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

.popup-icon {
    width: 56px;
    height: 56px;
    background: #fff2f2;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.popup-icon svg {
    width: 28px;
    height: 28px;
    color: #e05555;
}

.popup-box h3 {
    font-family: 'Noto Serif JP', serif;
    font-size: 17px;
    font-weight: 600;
    color: #2c2c2c;
    margin-bottom: 10px;
}

.popup-box p {
    font-size: 14px;
    color: #777;
    margin-bottom: 24px;
    line-height: 1.6;
}

.popup-btn {
    background: linear-gradient(135deg, var(--green-mid), var(--green-light));
    color: white;
    border: none;
    border-radius: 8px;
    padding: 11px 32px;
    font-size: 14px;
    font-family: 'Zen Kaku Gothic New', sans-serif;
    font-weight: 500;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.15s;
    box-shadow: 0 4px 12px rgba(46,107,62,0.3);
}

.popup-btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

/* ボタン */
.btn-login {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, var(--green-mid) 0%, var(--green-light) 100%);
    border: none;
    color: white;
    font-size: 15px;
    font-weight: 500;
    font-family: 'Zen Kaku Gothic New', sans-serif;
    letter-spacing: 0.12em;
    border-radius: 8px;
    cursor: pointer;
    margin-top: 8px;
    transition: transform 0.15s, box-shadow 0.2s, opacity 0.2s;
    box-shadow: 0 4px 15px rgba(46,107,62,0.35);
    position: relative;
    overflow: hidden;
}

.btn-login::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.12), transparent);
    border-radius: inherit;
}

.btn-login:hover {
    transform: translateY(-1px);
    box-shadow: 0 7px 22px rgba(46,107,62,0.42);
    opacity: 0.95;
}

.btn-login:active {
    transform: translateY(0);
    box-shadow: 0 3px 10px rgba(46,107,62,0.3);
}

/* フッター */
.card-footer {
    text-align: center;
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid #eef4ef;
    font-size: 11px;
    color: #b0c8b4;
    letter-spacing: 0.05em;
}
</style>
</head>

<body>

<div class="container">
    <div class="accent-line"></div>
    <div class="card">

        <div class="logo-area">
            <div class="logo-icon">
                <!-- 十字架アイコン -->
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <rect x="10" y="2" width="4" height="20"/>
                    <rect x="2" y="8" width="20" height="4"/>
                </svg>
            </div>
            <h1>出席管理システム</h1>
            <p>Attendance Management</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="popup-overlay" id="errorPopup">
            <div class="popup-box">
                <div class="popup-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <h3>ログインエラー</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
                <button class="popup-btn" onclick="document.getElementById('errorPopup').style.display='none'">閉じる</button>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <div class="form-group">
                <label for="userId">ユーザーID</label>
                <div class="input-wrap">
                    <input type="text" id="userId" name="userId" placeholder="ユーザーIDを入力" required autocomplete="<?= AUTOCOMPLETE ? 'username' : 'off' ?>">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password" placeholder="パスワードを入力" required autocomplete="<?= AUTOCOMPLETE ? 'current-password' : 'off' ?>">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="11" width="18" height="11" rx="2" stroke-width="1.5"/>
                        <path d="M7 11V7a5 5 0 0110 0v4" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </div>
            </div>

            <button type="submit" class="btn-login">ログイン</button>

        </form>

        <div class="card-footer">
            © sugita church system
        </div>

    </div>
</div>

</body>
</html>
