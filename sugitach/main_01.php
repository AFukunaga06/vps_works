<?php
// 集会出席状況 メインページ
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>集会出席状況</title>

  <style>
    *{ margin:0; padding:0; box-sizing:border-box; }

    /* 背景（リンク先の淡いグリーン系に寄せる） */
    body{
      background:#e6f4e8;
      font-family: system-ui, -apple-system, "Segoe UI",
                   "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif;
      min-height:100vh;
      padding:40px 18px;
      display:flex;
      align-items:flex-start;
      justify-content:flex-start;
      color:#0b1f12;
    }

    /* 外枠（白いカード） */
    .container{
      width:100%;
      max-width:720px;
      background:#fff;
      border:1px solid #d8eadc;
      border-radius:18px;
      box-shadow:0 10px 24px rgba(0,0,0,.08);
      padding:22px 22px 20px;
    }

    /* タイトルボックス（やわらかい青系） */
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

    /* ボタン行 */
    .button-row{
      display:flex;
      gap:14px;
      flex-wrap:wrap;
      align-items:center;
    }

    /* 各ボタン（丸み・白・影・やさしい動き） */
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
    }

    .menu-btn:hover{
      transform: translateY(-1px);
      box-shadow:0 8px 16px rgba(0,0,0,.10);
      background:#f6fff8;
    }

    .menu-btn:active{
      transform: translateY(0);
      box-shadow: inset 0 3px 8px rgba(0,0,0,.12);
      background:#eefaf0;
    }
  </style>
</head>

<body>
  <div class="container">
    <!-- タイトル -->
    <div class="title-box">集会出席状況</div>

    <!-- メニューボタン（内容はそのまま） -->
    <div class="button-row">
      <button class="menu-btn" onclick="location.href='reihai.php'">礼拝会</button>
      <button class="menu-btn" onclick="location.href='kitou.php'">祈祷会</button>
      <button class="menu-btn" onclick="location.href='live.php'">ライブ集会</button>
      <button class="menu-btn" onclick="location.href='sonota.php'">その他</button>
    </div>
  </div>
</body>
</html>