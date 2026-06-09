<?php
// 集会出席状況 メインページ
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>集会出席状況</title>

<style>
body{
    background:#e6f4e8;
    font-family: "Yu Gothic", sans-serif;
    display:flex;
    justify-content:center;
    padding:40px;
}

/* 全体 */
.container{
    width:800px;
}

/* タイトル */
.title{
    background:#cfd8e3;
    text-align:center;
    padding:10px;
    font-weight:bold;
    border:1px solid #666;
    margin-bottom:30px;
}

/* 行 */
.row{
    display:flex;
    gap:40px;
    margin-bottom:25px;
}

/* ボタン風 */
.box{
    background:#eadfbf;
    border:1px solid #333;
    padding:10px 20px;
    font-weight:bold;
    box-shadow:2px 2px 0 #333;
}

/* 区切り線 */
.line{
    border-bottom:3px solid #333;
    margin:20px 0;
}
</style>

</head>
<body>

<div class="container">

    <div class="title">集会出席状況</div>

    <!-- 1行目 -->
    <div class="row">
        <div class="box">礼拝会</div>
        <div class="box">早天礼拝</div>
        <div class="box">通常礼拝</div>
    </div>

    <!-- 2行目 -->
    <div class="row">
        <div class="box">祈祷会</div>
        <div class="box">午前</div>
        <div class="box">午後</div>
    </div>

    <div class="line"></div>

    <!-- 3行目 -->
    <div class="row">
        <div class="box">ライブ配信</div>
        <div class="box">礼拝会</div>
        <div class="box">祈祷会</div>
        <div class="box">その他</div>
    </div>

    <div class="line"></div>

    <!-- 4行目 -->
    <div class="row">
        <div class="box">朝祈会</div>
        <div class="box">その他集会</div>
    </div>

</div>

</body>
</html>