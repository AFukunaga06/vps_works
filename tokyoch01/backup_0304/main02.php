<?php
// ===== セッション強化（先に設定 → session_start）=====
ini_set('session.use_strict_mode', '1');         // 変なセッションIDを拒否
ini_set('session.cookie_httponly', '1');         // JSからCookieを読めない
ini_set('session.cookie_samesite', 'Lax');       // CSRF緩和（通常用途向け）
// HTTPS運用なら下も推奨（HTTPだとログインできなくなるので注意）
// ini_set('session.cookie_secure', '1');

session_start();

// ===== 未ログインならログインへ =====
if (empty($_SESSION['login_ok'])) {
    header("Location: login.php");
    exit;
}

// ===== セッションID再生成は「最初の1回だけ」=====
if (empty($_SESSION['regen_done'])) {
    session_regenerate_id(true);
    $_SESSION['regen_done'] = 1;
}

// ===== 放置タイムアウト（例：30分）=====
$TIMEOUT = 30 * 60;
$now = time();
if (!empty($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > $TIMEOUT) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = $now;

// ===== キャッシュ禁止（戻るボタンで見える事故防止）=====
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>集会出席状況</title>

<style>
*{ margin:0; padding:0; box-sizing:border-box; }

/* 背景 */
body{
  background:#e6f4e8;
  font-family: system-ui, -apple-system, "Segoe UI",
               "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif;
  padding:30px 18px;
  color:#0b1f12;
}

/* 外枠カード */
.container{
  width:100%;
  max-width:820px;
  margin:0 auto;
  background:#fff;
  border:1px solid #d8eadc;
  border-radius:18px;
  box-shadow:0 10px 24px rgba(0,0,0,.08);
  padding:22px 22px 18px;
}

/* タイトル */
.title-box{
  background: linear-gradient(135deg,#dff0ff,#eef7ff);
  border:1px solid #d8eadc;
  border-radius:16px;
  padding:14px 16px;
  text-align:center;
  font-size:18px;
  font-weight:800;
  letter-spacing:2px;
  margin-bottom:18px;
  box-shadow:0 6px 14px rgba(0,0,0,.06);
}

/* 行 */
.row{
  display:flex;
  gap:14px;
  flex-wrap:wrap;
  margin-bottom:14px;
}

/* ボタン */
.menu-btn{
  border-radius:999px;
  border:1px solid #d8eadc;
  background:#ffffff;
  padding:10px 18px;
  font-size:15px;
  font-weight:800;
  cursor:pointer;
  letter-spacing:1px;
  color:#1b2b22;
  box-shadow:0 4px 10px rgba(0,0,0,.06);
  transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease;
  min-width:110px;
  text-align:center;
  text-decoration:none;
  display:inline-block;
}

.menu-btn:hover{
  transform: translateY(-1px);
  box-shadow:0 8px 16px rgba(0,0,0,.10);
  background:#f6fff8;
}

.menu-btn:active{
  transform: translateY(0);
  box-shadow:0 3px 8px rgba(0,0,0,.06);
}

/* 区切り線 */
.line{
  border-bottom:1px dashed #cfe3d4;
  margin:16px 0;
}

/* ログアウトボタン */
.logout-btn{
  border-radius:999px;
  border:1px solid #f4a7b9;
  background:#ffe4ec;
  padding:12px 22px;
  font-size:17px;
  font-weight:800;
  cursor:pointer;
  letter-spacing:1px;
  color:#8b1a3a;
  box-shadow:0 4px 10px rgba(0,0,0,.06);
  transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease;
  min-width:125px;
  text-align:center;
  text-decoration:none;
  display:inline-block;
}
.logout-btn:hover{
  transform: translateY(-1px);
  box-shadow:0 8px 16px rgba(0,0,0,.10);
  background:#ffd0df;
}
</style>

</head>
<body>

<div class="container">

  <div class="title-box">集会出席状況</div>

  <!-- 1行目 -->
  <div class="row">
    <div class="menu-btn">礼拝会</div>
    <div class="menu-btn">早天礼拝</div>
    <a href="index_01_tr.php" class="menu-btn">通常礼拝</a>
  </div>

  <!-- 2行目 -->
  <div class="row">
    <div class="menu-btn">祈祷会</div>
    <div class="menu-btn">午前</div>
    <div class="menu-btn">午後</div>
  </div>

  <div class="line"></div>

  <!-- 3行目 -->
  <div class="row">
    <div class="menu-btn">ライブ配信</div>
    <div class="menu-btn">礼拝会</div>
    <div class="menu-btn">祈祷会</div>
    <div class="menu-btn">その他</div>
  </div>

  <div class="line"></div>

  <!-- 4行目 -->
  <div class="row">
    <div class="menu-btn">朝祈会</div>
    <div class="menu-btn">その他集会</div>
  </div>

  <!-- ログアウト -->
  <div class="row">
    <a href="logout.php" class="logout-btn">ログアウト</a>
  </div>

</div>

</body>
</html>
