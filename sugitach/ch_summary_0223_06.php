<?php
// ch_summary_0207_02.php
session_start();
if (empty($_SESSION['login_ok'])) {
  header("Location: login.php");
  exit;
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>日曜礼拝 出欠・人数集計（年集計つき）</title>
  <style>
    :root{
      --bg:#e7f6e7;
      --card:#ffffff;
      --text:#123;
      --muted:rgba(0,0,0,.55);
      --line:rgba(0,0,0,.12);
      --accent:#1f7a3a;
      --accent2:#0f5f2a;
      --shadow:0 10px 26px rgba(0,0,0,.08);
      --radius:18px;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans JP","Hiragino Kaku Gothic ProN","Yu Gothic",sans-serif;
      background:var(--bg);
      color:var(--text);
    }
    header{
      position:sticky; top:0; z-index:20;
      background:linear-gradient(180deg, rgba(231,246,231,.98), rgba(231,246,231,.88));
      backdrop-filter: blur(6px);
      border-bottom:1px solid var(--line);
    }
    .wrap{max-width:1200px;margin:0 auto;padding:14px 14px 10px}
    .title{display:flex;align-items:baseline;justify-content:space-between;gap:12px;flex-wrap:wrap}
    h1{margin:4px 0 0;font-size:18px;letter-spacing:.02em}
    .sub{font-size:12px;color:var(--muted)}
    .controls{display:grid;grid-template-columns:1fr;gap:10px;margin-top:10px}
    .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    .monthBox{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .btn, select{
      border:1px solid var(--line);
      background:#fff;
      color:var(--text);
      border-radius:999px;
      padding:10px 14px;
      font-size:14px;
      line-height:1;
      cursor:pointer;
      user-select:none;
    }
    .btn:active{transform:translateY(1px)}
    .seg{display:flex;border:1px solid var(--line);border-radius:999px;overflow:hidden;background:#fff}
    .seg button{border:0;background:transparent;padding:10px 14px;font-size:14px;cursor:pointer}
    .seg button.active{background:var(--accent);color:#fff}
    .hint{font-size:12px;color:var(--muted)}
    main{padding:12px 14px 170px} /* 下のバー+年表ぶん余白 */
    .cards{
      display:grid;
      grid-auto-flow:column;
      grid-auto-columns:minmax(280px,360px);
      gap:12px;
      overflow-x:auto;
      padding-bottom:8px;
      scroll-snap-type:x mandatory;
    }
    .card{
      scroll-snap-align:start;
      background:var(--card);
      border:1px solid var(--line);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
      display:flex;
      flex-direction:column;
      min-height:420px;
    }
    .cardHead{
      padding:12px 12px 10px;
      border-bottom:1px solid var(--line);
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:10px;
    }
    .dateBig{font-size:20px;font-weight:800;letter-spacing:.02em}
    .dateSmall{margin-top:2px;font-size:12px;color:var(--muted)}
    .badgeBox{display:grid;gap:6px;justify-items:end}
    .badge{
      display:inline-flex;align-items:center;gap:8px;
      padding:8px 10px;border-radius:12px;border:1px solid var(--line);
      background:#f7fff7;font-size:12px;white-space:nowrap;
    }
    .badge strong{font-size:16px;line-height:1}
    .card.active{outline:3px solid rgba(31,122,58,.35)}
    .list{padding:10px 12px 12px;overflow:auto}
    .listHeader{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}
    .listHeader .label{font-size:13px;font-weight:700}
    table{width:100%;border-collapse:collapse;border:1px solid var(--line);border-radius:12px;overflow:hidden}
    th,td{border-bottom:1px solid var(--line);padding:10px 10px;font-size:14px;vertical-align:middle}
    tr:last-child td{border-bottom:0}
    th{text-align:left;background:#f4fff4;font-size:12px;color:var(--muted)}
    .nameCell{width:1%;white-space:nowrap}
    input[type="checkbox"]{width:22px;height:22px;accent-color:var(--accent)}
    /* 年集計テーブル */
    .yearPanel{
      margin-top:16px;
      background:#fff;
      border:1px solid var(--line);
      border-radius:18px;
      box-shadow:var(--shadow);
      padding:12px;
    }
    .yearPanelHead{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
      margin-bottom:10px;
    }
    .yearPanelHead h2{
      margin:0;
      font-size:16px;
      letter-spacing:.02em;
    }

    .yearActions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}

    .yearTable{
      width:100%;
      border-collapse:collapse;
      border:1px solid var(--line);
      border-radius:12px;
      overflow:hidden;
    }
    .yearTable th, .yearTable td{
      padding:10px 10px;
      border-bottom:1px solid var(--line);
      font-size:14px;
    }
    .yearTable th{
      background:#f4fff4;
      color:var(--muted);
      font-size:12px;
      text-align:left;
    }
    .yearTable th.num{
      text-align:right;
      padding-right:18px;   /* ラベルを約3文字分右にずらす */
    }
    .yearTable td.num{
      text-align:right;
      padding-right:18px;   /* 数字も同じ位置に揃える */
    }
    .yearTable tr:last-child td{border-bottom:0}
    .yearTable tfoot td{
      font-weight:800;
      background:#f4fff4;
    }
    .num{text-align:right;font-variant-numeric:tabular-nums}
    .yearTotalRow td{
      font-weight:800;
      background:#f7fff7;
    }
    .footerBar{
      position:fixed;left:0;right:0;bottom:0;z-index:30;
      border-top:1px solid var(--line);
      background:rgba(255,255,255,.92);
      backdrop-filter: blur(8px);
    }
    .footerInner{max-width:1200px;margin:0 auto;padding:10px 14px;display:grid;gap:8px}
    .bigTotals{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .tile{
      background:#fff;border:1px solid var(--line);border-radius:16px;
      padding:10px 12px;
      box-shadow:0 8px 18px rgba(0,0,0,.06);
    }
    .tile .k{font-size:12px;color:var(--muted);margin-bottom:6px}
    .tile .v{font-size:26px;font-weight:900;letter-spacing:.02em;color:var(--accent2)}
    .monthSum{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:13px}
    .monthSum strong{color:var(--accent2)}
    @media (max-width:820px){
      .cards{grid-auto-columns:minmax(260px,86vw)}
      .bigTotals{grid-template-columns:1fr}
      .tile .v{font-size:24px}
    }
      /* 横スクロール操作ボタン（PCのみ表示） */
    .scrollNav{
      display:flex;
      gap:8px;
      margin:6px 0 10px 0;
      align-items:center;
    }
    .btn:disabled{
      opacity:.45;
      cursor:not-allowed;
      transform:none;
    }
    @media (max-width: 820px){
      .scrollNav{ display:none; } /* モバイルはスワイプのみ */
    }

    /* 名簿CSV読み込み（任意） */
    .rosterImport{
      margin-top:10px;
      padding:10px 12px;
      border:1px dashed rgba(0,0,0,.22);
      background:rgba(255,255,255,.75);
      border-radius:16px;
    }
    .rosterImport .titleRow{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      margin-bottom:8px;
    }
    .rosterImport h3{
      margin:0;
      font-size:13px;
      letter-spacing:.02em;
    }
    .rosterImport .desc{
      margin:6px 0 10px 0;
      font-size:12px;
      color:var(--muted);
      line-height:1.55;
    }
    .rosterImport .inputs{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      align-items:center;
    }
    .rosterImport label{
      font-size:12px;
      color:var(--text);
      display:flex;
      gap:8px;
      align-items:center;
      flex-wrap:wrap;
    }
    .rosterImport input[type="file"]{
      font-size:12px;
    }


    /* 名簿行数の増減（+10/-10など） */
    .rosterRows{
      margin-top:10px;
      padding:10px 12px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.85);
      border-radius:16px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
    }
    .rosterRows .left{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
    }
    .rosterRows .who{
      font-size:12px;
      color:var(--muted);
    }
    .rosterRows .who strong{
      color:var(--accent2);
      font-weight:800;
    }
    .rosterRows .btns{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      align-items:center;
    }
    .btn.small{
      padding:8px 12px;
      font-size:13px;
    }

    /* CSV出力（タブと同じ見た目） */
    .csvExport{margin-left:8px}
    @media (max-width: 820px){
      .csvExport{margin-left:0}
    }

    /* CSV出力（下部：年集計パネル内） */
    .btn.csv{
      background: var(--accent);
      color:#fff;
      border-color: rgba(31,122,58,.35);
    }
    .btn.csv:hover{opacity:.9}

    /* CSV出力（最下部：月集計の横） */
    .footerCsv{white-space:nowrap}
    @media (max-width: 820px){
      .footerCsv{width:100%;}
    }

    /* ========================================
       礼拝日確定パネル
       ======================================== */
    .workday-panel{
      max-width:1200px;margin:0 auto;padding:0 14px 14px;
    }
    .workday-box{
      background:var(--card);border:1px solid var(--line);border-radius:var(--radius);
      padding:16px 18px;box-shadow:var(--shadow);
    }
    .workday-box h2{
      margin:0 0 12px;font-size:15px;font-weight:900;color:var(--accent);
      display:flex;align-items:center;gap:8px;
    }
    .workday-box h2::before{content:"📅";font-size:18px}
    .workday-input-row{
      display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px;
    }
    .workday-input-row input[type="date"]{
      border:1px solid var(--line);border-radius:999px;padding:8px 14px;
      font-size:14px;font-family:inherit;color:var(--text);background:#fff;
      outline:none;transition:border-color .2s,box-shadow .2s;
    }
    .workday-input-row input[type="date"]:focus{
      border-color:var(--accent);box-shadow:0 0 0 3px rgba(31,122,58,.15);
    }
    .btn-confirm-workday{
      background:var(--accent);color:#fff;border:none;border-radius:999px;
      padding:9px 20px;font-size:13px;font-weight:800;cursor:pointer;
      box-shadow:var(--shadow);transition:background .2s;
    }
    .btn-confirm-workday:hover{background:var(--accent2)}
    .workday-list{
      display:flex;flex-wrap:wrap;gap:8px;
      max-height:200px;overflow-y:auto;padding:2px 0;
    }
    .workday-chip{
      display:inline-flex;align-items:center;gap:6px;
      background:#f0faf2;border:1px solid var(--line);border-radius:999px;
      padding:7px 8px 7px 14px;transition:all .15s;cursor:default;
    }
    .workday-chip:hover{background:#d6f0dc;border-color:var(--accent)}
    .workday-chip .chip-date{
      font-weight:900;font-size:14px;color:var(--text);cursor:pointer;
    }
    .workday-chip .chip-date:hover{color:var(--accent);text-decoration:underline}
    .workday-chip .chip-wd{font-size:11px;color:var(--muted);font-weight:600}
    .workday-chip .chip-show{
      background:var(--accent);color:#fff;border:none;border-radius:999px;
      padding:4px 12px;font-size:11px;font-weight:800;cursor:pointer;transition:background .2s;
    }
    .workday-chip .chip-show:hover{background:var(--accent2)}
    .workday-chip .chip-del{
      background:#fdecea;color:#c0392b;border:1px solid #e8c4c0;
      border-radius:50%;width:22px;height:22px;font-size:12px;font-weight:900;
      cursor:pointer;display:flex;align-items:center;justify-content:center;
      transition:background .2s;padding:0;line-height:1;
    }
    .workday-chip .chip-del:hover{background:#f5c6c2}
    .workday-empty{color:var(--muted);font-size:13px;font-style:italic;padding:4px 0}

    /* ========================================
       フィルター表示バー
       ======================================== */
    .filter-bar{max-width:1200px;margin:0 auto;padding:0 14px 10px;display:none}
    .filter-bar.active{display:block}
    .filter-inner{
      background:linear-gradient(135deg,#d4edda 0%,#c3e6cb 100%);
      border:2px solid var(--accent);border-radius:var(--radius);
      padding:14px 20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;
      box-shadow:0 4px 16px rgba(31,122,58,.12);
    }
    .filter-inner .filter-icon{font-size:20px}
    .filter-inner .filter-label{font-weight:900;font-size:14px;color:var(--accent)}
    .filter-inner .filter-date{font-weight:900;font-size:17px;color:var(--text)}
    .filter-inner .filter-note{font-size:12px;color:var(--muted);font-weight:600}
    .btn-back-normal{
      background:#fff;color:var(--accent);border:2px solid var(--accent);
      border-radius:999px;padding:8px 20px;font-size:13px;font-weight:800;
      cursor:pointer;margin-left:auto;transition:all .2s;
    }
    .btn-back-normal:hover{background:var(--accent);color:#fff}

    /* ========================================
       日付指定時 2カラムレイアウト（男女並列）
       ======================================== */
    .twoCol{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:22px;
      padding:14px 14px 0;
    }
    .twoCol .col{min-width:0}
    .twoCol .col-label{
      padding:10px 14px 6px;
      font-size:12px;font-weight:900;color:var(--accent);
      background:#f4fff4;border-bottom:1px solid var(--line);
      text-align:center;letter-spacing:.5px;
    }
    .twoCol .col table{width:100%;border-collapse:collapse}
    .twoCol .col table td{
      padding:8px 10px;border-bottom:1px solid var(--line);font-size:13px;
    }
    .twoCol .col table td:last-child{text-align:right;width:44px}
    .twoCol .col table tr:last-child td{border-bottom:none}
    @media (max-width:720px){
      .twoCol{grid-template-columns:1fr}
      .filter-card-wide{min-width:100% !important;max-width:100% !important}
    }
</style>
</head>
<body>
<header>
  <div class="wrap">
    <div class="title">
      <div>
        <h1>日曜礼拝 出欠・人数集計（年収計つき）</h1>
        <div class="sub">日曜日だけ自動抽出／月切替／年（1〜12月）合計表示／保存：localStorage</div>
      </div>
      <div class="hint">カードをタップすると「その日」の下部合計が切り替わります（全チェック機能なし）</div>

      <form action="logout.php" method="post" style="margin:0;">
        <button class="btn" type="submit" aria-label="ログアウト">ログアウト</button>
      </form>
    </div>

    <div class="controls">
      <div class="row">
        <div class="monthBox">
          <button class="btn" id="prevMonth">◀ 前月</button>
          <select id="monthSelect" aria-label="月の選択"></select>
          <button class="btn" id="nextMonth">次月 ▶</button>
          

          <!-- 年選択（年集計用） -->
          <select id="yearSelect" aria-label="年の選択"></select>
        </div>

        <div class="seg" role="tablist" aria-label="表示切替">
          <button id="tabMale" class="active" role="tab" aria-selected="true">男性</button>
          <button id="tabFemale" role="tab" aria-selected="false">女性</button>
          <button id="tabTotal" role="tab" aria-selected="false">男女合計</button>
        </div>
      </div>

      <div class="hint">※ 日曜ごとにカードを並べ、各カード内で出欠チェックができます（表示中のタブ分だけ描画）／全チェック機能なし</div>
</div>
  </div>
</header>

<!-- ========================================
     礼拝日確定パネル
     ======================================== -->
<div class="workday-panel">
  <div class="workday-box">
    <h2>礼拝日</h2>
    <div class="workday-input-row">
      <input type="date" id="workdayDateInput" />
      <button class="btn-confirm-workday" id="confirmWorkdayBtn">礼拝日を確定</button>
    </div>
    <div class="workday-list" id="workdayList">
      <div class="workday-empty">確定した礼拝日はまだありません</div>
    </div>
  </div>
</div>

<!-- ========================================
     フィルター表示バー
     ======================================== -->
<div class="filter-bar" id="filterBar">
  <div class="filter-inner">
    <span class="filter-icon">📌</span>
    <span class="filter-label">表示中：</span>
    <span class="filter-date" id="filterDateLabel"></span>
    <span class="filter-note" id="filterNote">男性・女性 両方の名簿を表示 / チェックの入れ直しが可能です</span>
    <button class="btn-back-normal" id="backNormalBtn">通常表示へ戻る</button>
  </div>
</div>

<main>
  <div class="wrap">    <!-- 横スクロール操作ボタン（PCのみ表示／モバイルはスワイプ） -->
    <div class="scrollNav" aria-label="カード横スクロール操作">
      <button class="btn" id="scrollLeft" type="button">← 左へ</button>
      <button class="btn" id="scrollRight" type="button">右へ →</button>
      <span class="hint" id="scrollHint" style="margin-left:6px;">（横にスクロールできます）</span>
    </div>


    <div id="cards" class="cards"></div>

    <!-- 日計テーブル（表示月の日曜日ごと） -->
    <section class="yearPanel" aria-label="日計" id="dailyPanel">
      <div class="yearPanelHead">
        <h2>📋 <span id="dailyPanelLabel">----</span> 日計（日曜日ごと）</h2>
        <div class="yearActions">
          <button class="btn csv" id="exportDailyCsv" type="button">日計CSV出力</button>
        </div>
      </div>
      <table class="yearTable" id="dailyTable">
        <thead>
          <tr>
            <th style="width:130px">日付</th>
            <th class="num">男性</th>
            <th class="num">女性</th>
            <th class="num">合計</th>
          </tr>
        </thead>
        <tbody id="dailyTableBody"></tbody>
        <tfoot>
          <tr class="yearTotalRow">
            <td>月合計</td>
            <td class="num" id="dailySumMale">0</td>
            <td class="num" id="dailySumFemale">0</td>
            <td class="num" id="dailySumTotal">0</td>
          </tr>
        </tfoot>
      </table>
    </section>

    <!-- 年集計（1〜12月の表） -->
    <section class="yearPanel" aria-label="年集計">
      <div class="yearPanelHead">
        <h2><span id="yearLabel">----</span> 年：月別合計（1〜12月）</h2>
        <div class="yearActions">
</div>
      </div>
<table class="yearTable" id="yearTable">
        <thead>
          <tr>
            <th style="width:120px">月</th>
            <th class="num">男性</th>
            <th class="num">女性</th>
            <th class="num">合計</th>
          </tr>
        </thead>
        <tbody id="yearTableBody"></tbody>
      </table>
    </section>

    <!-- 名簿CSV読み込み（任意） -->
    <div class="rosterImport" aria-label="名簿CSVの説明と読み込み">
      <div class="titleRow">
        <h3>名簿CSVについて（任意）</h3>
        <button class="btn" id="initRoster" type="button">名簿を初期状態に戻す</button>
      </div>


      <!-- 追加: 名簿の行数を増減（+10/-10 など） -->
      <div class="rosterRows" aria-label="名簿の行数を増減">
        <div class="left">
          <div class="who">対象：<strong id="rosterTarget">---</strong>（表示中のタブ）</div>
          <div class="btns">
            <button class="btn small" id="rosterAdd10" type="button">＋10行</button>
            <button class="btn small" id="rosterRemove10" type="button">−10行</button>
            <button class="btn small" id="rosterAdd1" type="button">＋1行</button>
            <button class="btn small" id="rosterRemove1" type="button">−1行</button>
          </div>
        </div>
        <span class="hint" id="rosterRowHint">（男性/女性タブで操作できます）</span>
      </div>

      <div class="desc">
        <div><strong>男性名簿CSV</strong>：男性の名前一覧です。</div>
        <div><strong>女性名簿CSV</strong>：女性の名前一覧です。</div>
        <div style="margin-top:6px;">
          <strong>新来会者等</strong>＝名簿に載っていない方（初めての方・ゲスト等）を数えるための枠
        </div>
        <div style="margin-top:4px;">
          ・CSVに入れなくても、男女それぞれ <strong>自動で15枠（1〜15）</strong>を追加します
        </div>

        <div style="margin-top:10px;">
          CSVは <strong>1行に1名</strong>で作成してください（例：<code>青木健太</code> または <code>青木,健太</code> どちらでもOK）。
          カンマ区切りの場合はカンマを除いて結合します。空行は無視します。
        </div>
        <div style="margin-top:8px;">
          ※ 名簿を読み込むとチェック対象IDが変わるため、<strong>安全のため保存済みの出欠データ（全月）をリセット</strong>します。
        </div>
      </div>

      <div class="inputs">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <label>男性名簿CSV：
            <input type="file" id="maleCsv" accept=".csv,text/csv" />
          </label>
          <button class="btn" id="applyMaleCsv" type="button" disabled>名簿に反映</button>
          <span id="maleCsvStatus" class="hint">ファイルが選択されていません</span>
        </div>
        
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:8px;">
          <label>女性名簿CSV：
            <input type="file" id="femaleCsv" accept=".csv,text/csv" />
          </label>
          <button class="btn" id="applyFemaleCsv" type="button" disabled>名簿に反映</button>
          <span id="femaleCsvStatus" class="hint">ファイルが選択されていません</span>
        </div>
      </div>

      <div class="inputs" style="margin-top:12px;">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <label>追加 男性名簿CSV：
            <input type="file" id="maleCsvAdd" accept=".csv,text/csv" />
          </label>
          <button class="btn" id="applyMaleCsvAdd" type="button" disabled>名簿に追加</button>
          <span id="maleCsvAddStatus" class="hint">ファイルが選択されていません</span>
        </div>
        
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:8px;">
          <label>追加 女性名簿CSV：
            <input type="file" id="femaleCsvAdd" accept=".csv,text/csv" />
          </label>
          <button class="btn" id="applyFemaleCsvAdd" type="button" disabled>名簿に追加</button>
          <span id="femaleCsvAddStatus" class="hint">ファイルが選択されていません</span>
        </div>
        <span class="hint" style="margin-top:6px;display:block;">（既存名簿は保持され、追加分のみ反映されます）</span>
      </div>

    </div>

  </div>
</main>

<div class="footerBar">
  <div class="footerInner">
    <div class="monthSum" id="monthSummary"></div>
    <button class="btn csv footerCsv" id="exportCsv" type="button">CSV出力</button>
    <div class="bigTotals">
      <div class="tile">
        <div class="k">選択中の日曜：男性 合計</div>
        <div class="v" id="activeMale">0</div>
      </div>
      <div class="tile">
        <div class="k">選択中の日曜：女性 合計</div>
        <div class="v" id="activeFemale">0</div>
      </div>
      <div class="tile">
        <div class="k">選択中の日曜：男女 合計</div>
        <div class="v" id="activeTotal">0</div>
      </div>
    </div>
  </div>
</div>

<script>
/************************************************************
 * 設定（名簿）
 ************************************************************/
const maleNamesBase = [
  "青木 健太","石井 直樹","上田 翔太","大野 和也","岡田 拓也",
  "小川 裕樹","河野 智也","菊地 大輔","黒田 亮","坂本 正人",
  "塩谷 拓海","杉山 健一","関口 誠","高木 直人","武田 俊也",
  "谷口 祐介","永井 健","野村 拓真","原田 直哉","藤原 誠",
  "堀内 祐樹","増田 健","三浦 隆志","宮本 大樹","村上 恒一",
];
// 女性名簿（例）。必要なら差し替えてください。
const femaleNamesBase = [
  "佐藤 彩花","鈴木 美咲","高橋 玲奈","田中 結衣","伊藤 陽菜",
  "渡辺 さくら","山本 りお","中村 愛","小林 えま","加藤 みお",
  "吉田 ひなた","山田 こころ","佐々木 杏","山口 莉子","松本 結菜",
  "井上 乃愛","木村 葵","林 美月","斎藤 心春","清水 まゆ",
  "山崎 ほのか","阿部 かのん","森 ひかり","池田 いちか","橋本 みなみ",
];

const NEWCOMER_COUNT = 15;

// 追加: 空欄名の表示（名簿の増減で空行を作れるようにする）
function displayLabelForBaseName(name, idx){
  const trimmed = String(name ?? "").trim();
  return trimmed ? trimmed : `（空欄${idx+1}）`;
}

function buildRoster(baseList, prefix){
  const list = baseList.map((name, idx)=>({ id:`${prefix}_base_${idx+1}`, label: displayLabelForBaseName(name, idx) }));
  for(let i=1;i<=NEWCOMER_COUNT;i++){
    list.push({ id:`${prefix}_new_${i}`, label:`新来会者等${i}` });
  }
  return list;
}

let rosterMale = buildRoster(maleNamesBase,"m");
let rosterFemale = buildRoster(femaleNamesBase,"f");

/************************************************************
 * 名簿データの保存・読み込み（localStorage）
 * ※ STORAGE_PREFIXの定義後に使用するため、関数内で動的に参照
 ************************************************************/

function saveRosterMale(names){
  localStorage.setItem(`attendance_sunday_cards_v1:roster_male`, JSON.stringify(names));
}
function saveRosterFemale(names){
  localStorage.setItem(`attendance_sunday_cards_v1:roster_female`, JSON.stringify(names));
}
function loadRosterMale(){
  const json = localStorage.getItem(`attendance_sunday_cards_v1:roster_male`);
  return json ? JSON.parse(json) : null;
}
function loadRosterFemale(){
  const json = localStorage.getItem(`attendance_sunday_cards_v1:roster_female`);
  return json ? JSON.parse(json) : null;
}

function applyStoredRosterIfAny(){
  const storedMale = loadRosterMale();
  const storedFemale = loadRosterFemale();
  if(storedMale){
    rosterMale = buildRoster(storedMale, "m");
  }
  if(storedFemale){
    rosterFemale = buildRoster(storedFemale, "f");
  }
}

/************************************************************
 * 追加: 名簿の行数を増減（+10/-10）
 * - 対象は「表示中タブ（男性/女性）」の名簿
 * - 新来会者等（15枠）は固定（増減対象外）
 * - 削除は安全対策：末尾の削除対象に「名前あり」or「チェックあり」があれば中止
 ************************************************************/
function getBaseRoster(gender){
  if(gender === "male") return (loadRosterMale() || maleNamesBase).slice();
  if(gender === "female") return (loadRosterFemale() || femaleNamesBase).slice();
  return [];
}
function setBaseRoster(gender, baseNames){
  if(gender === "male") saveRosterMale(baseNames);
  if(gender === "female") saveRosterFemale(baseNames);
}
function rebuildRostersFromStorage(){
  rosterMale = buildRoster(getBaseRoster("male"), "m");
  rosterFemale = buildRoster(getBaseRoster("female"), "f");
}

function displayLabelForBaseName(name, idx){
  const trimmed = String(name ?? "").trim();
  return trimmed ? trimmed : `（空欄${idx+1}）`;
}

function getBaseId(prefix, idx){
  // buildRoster の base id と一致させる（1始まり）
  return `${prefix}_base_${idx+1}`;
}

function scanAnyCheckedForPersonIds(personIds){
  const set = new Set(personIds);
  const prefix = STORAGE_PREFIX + ":"; // attendance_sunday_cards_v1:
  for(let i=0; i<localStorage.length; i++){
    const key = localStorage.key(i);
    if(!key || !key.startsWith(prefix)) continue;

    // 名簿キーは除外
    if(key === `${STORAGE_PREFIX}:roster_male` || key === `${STORAGE_PREFIX}:roster_female`) continue;

    const monthObj = safeParseJSON(localStorage.getItem(key));
    if(!monthObj) continue;

    for(const iso of Object.keys(monthObj)){
      const day = monthObj[iso];
      if(!day) continue;
      for(const gender of ["male","female"]){
        const g = day[gender] || {};
        for(const pid of Object.keys(g)){
          if(set.has(pid) && g[pid]) return true;
        }
      }
    }
  }
  return false;
}

function removePersonIdsFromAllMonths(personIds){
  // tidy: 削除したIDのチェック記録を全月から消す（true/false問わず）
  const set = new Set(personIds);
  const prefix = STORAGE_PREFIX + ":";
  for(let i=0; i<localStorage.length; i++){
    const key = localStorage.key(i);
    if(!key || !key.startsWith(prefix)) continue;
    if(key === `${STORAGE_PREFIX}:roster_male` || key === `${STORAGE_PREFIX}:roster_female`) continue;

    const monthObj = safeParseJSON(localStorage.getItem(key));
    if(!monthObj) continue;

    let changed = false;
    for(const iso of Object.keys(monthObj)){
      const day = monthObj[iso];
      if(!day) continue;
      for(const gender of ["male","female"]){
        if(!day[gender]) continue;
        for(const pid of Object.keys(day[gender])){
          if(set.has(pid)){
            delete day[gender][pid];
            changed = true;
          }
        }
      }
    }
    if(changed){
      localStorage.setItem(key, JSON.stringify(monthObj));
      // キャッシュも合わせておく（存在していれば）
      const mKey = key.slice(prefix.length);
      if(state.data[mKey]) state.data[mKey] = monthObj;
    }
  }
}

function addRosterRows(gender, n){
  const base = getBaseRoster(gender);
  for(let i=0;i<n;i++) base.push(""); // 空欄を追加
  setBaseRoster(gender, base);
  rebuildRostersFromStorage();
}

function removeRosterRows(gender, n){
  const base = getBaseRoster(gender);
  const minBase = 0; // 男性/女性の最低行（必要なら 1 に変更可）
  if(base.length <= minBase){
    alert("これ以上減らせません。");
    return false;
  }

  const actualN = Math.min(n, base.length - minBase);
  const startIdx = base.length - actualN; // 0-based
  const slice = base.slice(startIdx); // 削除候補の「名前」
  // 1) 名前入り行が含まれるなら削除不可
  const hasName = slice.some(v => String(v ?? "").trim().length > 0);
  if(hasName){
    alert("名前が入っている行が含まれるため削除できません。先に空欄にしてください。");
    return false;
  }

  // 2) チェック済みが含まれるなら削除不可（IDで判定）
  const prefix = (gender==="male") ? "m" : "f";
  const personIds = [];
  for(let i=startIdx; i<base.length; i++){
    personIds.push(getBaseId(prefix, i));
  }
  if(scanAnyCheckedForPersonIds(personIds)){
    alert("チェック済みの行があるため削除できません。先にチェックを解除してください。");
    return false;
  }

  // OK → 削除
  base.splice(startIdx, actualN);
  setBaseRoster(gender, base);

  // 念のため：過去データに残っている該当IDを掃除
  removePersonIdsFromAllMonths(personIds);

  rebuildRostersFromStorage();
  return true;
}

function updateRosterControls(){
  const target = document.getElementById("rosterTarget");
  const hint = document.getElementById("rosterRowHint");
  const btns = ["rosterAdd10","rosterRemove10","rosterAdd1","rosterRemove1"].map(id=>document.getElementById(id));

  const can = (state.view === "male" || state.view === "female");
  const label = (state.view === "male") ? "男性名簿" : (state.view === "female") ? "女性名簿" : "（男女合計では操作不可）";
  if(target) target.textContent = label;

  btns.forEach(b=>{
    if(!b) return;
    b.disabled = !can;
  });

  if(hint){
    if(!can){
      hint.textContent = "（男女合計タブでは名簿の増減はできません。男性/女性に切り替えてください）";
    }else{
      const gender = state.view;
      const baseLen = getBaseRoster(gender).length;
      hint.textContent = `（現在の名簿行：${baseLen}行 ＋ 新来会者等15枠）`;
    }
  }
}

/************************************************************
 * CSVパース（1行1名、カンマあれば除去して結合）
 ************************************************************/
function parseCsvLines(text){
  const lines = text.split(/\r?\n/);
  const names = [];
  for(let line of lines){
    line = line.trim();
    if(!line) continue; // 空行スキップ
    // カンマ区切りの場合は結合
    const parts = line.split(",").map(p => p.trim()).filter(p => p);
    if(parts.length > 0){
      names.push(parts.join(""));
    }
  }
  return names;
}

function readCsvFile(file){
  return new Promise((resolve, reject)=>{
    const reader = new FileReader();
    reader.onload = e => resolve(e.target.result);
    reader.onerror = reject;
    reader.readAsText(file, "UTF-8");
  });
}

/************************************************************
 * 名簿CSV読み込み処理
 ************************************************************/
async function handleMaleCsvImport(file, addMode = false){
  try{
    const text = await readCsvFile(file);
    const newNames = parseCsvLines(text);
    if(newNames.length === 0){
      alert("有効な名前が見つかりませんでした。");
      return;
    }
    
    let baseNames;
    if(addMode){
      // 追加モード：既存名簿に追加
      const current = loadRosterMale() || maleNamesBase;
      baseNames = [...current, ...newNames];
    }else{
      // 完全置換モード：全出欠データをリセット
      if(!confirm(`男性名簿を更新します。\n安全のため、保存済みの出欠データ（全月）をリセットします。\nよろしいですか？`)){
        return;
      }
      clearAllAttendanceData();
      baseNames = newNames;
    }
    
    saveRosterMale(baseNames);
    rosterMale = buildRoster(baseNames, "m");
    syncUI();
    alert(`男性名簿を更新しました（${newNames.length}名${addMode ? '追加' : '登録'}）`);
  }catch(err){
    alert("CSVの読み込みに失敗しました: " + err.message);
  }
}

async function handleFemaleCsvImport(file, addMode = false){
  try{
    const text = await readCsvFile(file);
    const newNames = parseCsvLines(text);
    if(newNames.length === 0){
      alert("有効な名前が見つかりませんでした。");
      return;
    }
    
    let baseNames;
    if(addMode){
      // 追加モード：既存名簿に追加
      const current = loadRosterFemale() || femaleNamesBase;
      baseNames = [...current, ...newNames];
    }else{
      // 完全置換モード：全出欠データをリセット
      if(!confirm(`女性名簿を更新します。\n安全のため、保存済みの出欠データ（全月）をリセットします。\nよろしいですか？`)){
        return;
      }
      clearAllAttendanceData();
      baseNames = newNames;
    }
    
    saveRosterFemale(baseNames);
    rosterFemale = buildRoster(baseNames, "f");
    syncUI();
    alert(`女性名簿を更新しました（${newNames.length}名${addMode ? '追加' : '登録'}）`);
  }catch(err){
    alert("CSVの読み込みに失敗しました: " + err.message);
  }
}

function clearAllAttendanceData(){
  // localStorageから全ての出欠データを削除
  const STORAGE_PREFIX = "attendance_sunday_cards_v1";
  const ROSTER_MALE_KEY = `${STORAGE_PREFIX}:roster_male`;
  const ROSTER_FEMALE_KEY = `${STORAGE_PREFIX}:roster_female`;
  
  const keys = [];
  for(let i=0; i<localStorage.length; i++){
    const key = localStorage.key(i);
    if(key && key.startsWith(STORAGE_PREFIX + ":")){
      // 名簿データ以外を削除
      if(key !== ROSTER_MALE_KEY && key !== ROSTER_FEMALE_KEY){
        keys.push(key);
      }
    }
  }
  keys.forEach(k => localStorage.removeItem(k));
  state.data = {};
  state.activeIso = null;
}

function initializeRoster(){
  if(!confirm("名簿を初期値にリセットし、出欠データも全て削除します。\nよろしいですか？")){
    return;
  }
  localStorage.removeItem(`attendance_sunday_cards_v1:roster_male`);
  localStorage.removeItem(`attendance_sunday_cards_v1:roster_female`);
  clearAllAttendanceData();
  rosterMale = buildRoster(maleNamesBase, "m");
  rosterFemale = buildRoster(femaleNamesBase, "f");
  syncUI();
  alert("名簿と出欠データを初期化しました。");
}

/************************************************************
 * 状態＆保存（localStorage）
 ************************************************************/
const STORAGE_PREFIX = "attendance_sunday_cards_v1";
const state = {
  year: new Date().getFullYear(),
  monthIndex: new Date().getMonth(), // 0-11
  view: "male", // "male" | "female" | "total"
  activeIso: null,
  data: {}, // キャッシュ { [monthKey]: monthObj }
  filterDate: null, // 礼拝日フィルター "YYYY-MM-DD" | null
};

// LS key for workdays
const LS_WORKDAYS_KEY = `${STORAGE_PREFIX}:workdays`;

function monthKey(y, mIndex){
  const mm = String(mIndex+1).padStart(2,"0");
  return `${y}-${mm}`;
}
function storageKey(mKey){
  return `${STORAGE_PREFIX}:${mKey}`;
}
function safeParseJSON(raw){
  if(!raw) return null;
  try{
    const obj = JSON.parse(raw);
    return (obj && typeof obj === "object") ? obj : null;
  }catch{
    return null;
  }
}
function loadMonthData(mKey){
  return safeParseJSON(localStorage.getItem(storageKey(mKey))) || {};
}
function saveMonthData(mKey, obj){
  localStorage.setItem(storageKey(mKey), JSON.stringify(obj));
}
function ensureMonthLoaded(mKey){
  if(!state.data[mKey]) state.data[mKey] = loadMonthData(mKey);
}

/************************************************************
 * 日曜抽出（指定月の全日曜）※Dateで実際の暦に基づいて算出
 ************************************************************/
function getSundaysOfMonth(y, mIndex){
  const last = new Date(y, mIndex+1, 0); // 月末
  const sundays = [];
  for(let d=1; d<=last.getDate(); d++){
    const dt = new Date(y, mIndex, d);
    if(dt.getDay()===0) sundays.push(dt);
  }
  return sundays;
}
function toISODate(dt){
  const y = dt.getFullYear();
  const m = String(dt.getMonth()+1).padStart(2,"0");
  const d = String(dt.getDate()).padStart(2,"0");
  return `${y}-${m}-${d}`;
}
function toMMDD(dt){
  const m = String(dt.getMonth()+1).padStart(2,"0");
  const d = String(dt.getDate()).padStart(2,"0");
  return `${m}/${d}`;
}
function jpWeekday(dt){
  const w = ["日","月","火","水","木","金","土"][dt.getDay()];
  return w;
}

/************************************************************
 * 集計（当月）
 ************************************************************/
function countChecked(iso, gender){
  const mKey = monthKey(state.year, state.monthIndex);
  ensureMonthLoaded(mKey);
  const day = state.data[mKey][iso];
  if(!day) return 0;
  const obj = day[gender] || {};
  return Object.values(obj).filter(Boolean).length;
}
function setCheck(iso, gender, personId, checked){
  const mKey = monthKey(state.year, state.monthIndex);
  ensureMonthLoaded(mKey);
  const monthObj = state.data[mKey];
  if(!monthObj[iso]) monthObj[iso] = { male:{}, female:{} };
  if(!monthObj[iso][gender]) monthObj[iso][gender] = {};
  monthObj[iso][gender][personId] = !!checked;
  saveMonthData(mKey, monthObj);
}
function getCheck(iso, gender, personId){
  const mKey = monthKey(state.year, state.monthIndex);
  ensureMonthLoaded(mKey);
  const day = state.data[mKey][iso];
  return !!(day && day[gender] && day[gender][personId]);
}
function getMonthTotals(y, mIndex){
  // storageから直接集計（キャッシュに頼らない）
  const mKey = monthKey(y, mIndex);
  const monthObj = loadMonthData(mKey);
  let male=0, female=0;
  for(const iso of Object.keys(monthObj)){
    male += Object.values(monthObj[iso]?.male || {}).filter(Boolean).length;
    female += Object.values(monthObj[iso]?.female || {}).filter(Boolean).length;
  }
  return { male, female, total: male+female, mKey };
}
function getCurrentMonthTotals(){
  return getMonthTotals(state.year, state.monthIndex);
}

/************************************************************
 * 年集計（1〜12月）
 ************************************************************/
function getYearTotals(year){
  const months = [];
  let male=0, female=0;
  for(let m=0;m<12;m++){
    const t = getMonthTotals(year, m);
    months.push({ monthIndex:m, ...t });
    male += t.male;
    female += t.female;
  }
  return { year, months, male, female, total: male+female };
}

/************************************************************
 * UI
 ************************************************************/
const elCards = document.getElementById("cards");
const elMonthSelect = document.getElementById("monthSelect");
const elPrev = document.getElementById("prevMonth");
const elNext = document.getElementById("nextMonth");
// const elClear = document.getElementById("clearMonth"); // 存在しないためコメントアウト

const elYearSelect = document.getElementById("yearSelect");
const elYearLabel = document.getElementById("yearLabel");
const elYearBody = document.getElementById("yearTableBody");

const elTabMale = document.getElementById("tabMale");
const elTabFemale = document.getElementById("tabFemale");
const elTabTotal = document.getElementById("tabTotal");

const elActiveMale = document.getElementById("activeMale");
const elActiveFemale = document.getElementById("activeFemale");
const elActiveTotal = document.getElementById("activeTotal");
const elMonthSummary = document.getElementById("monthSummary");

/************************************************************
 * 横スクロール操作（PC用）＋ 端まで行ったらボタン無効化
 ************************************************************/
const elScrollLeft = document.getElementById("scrollLeft");
const elScrollRight = document.getElementById("scrollRight");
const elScrollHint = document.getElementById("scrollHint");

function getScrollStep(){
  const card = elCards.querySelector(".card");
  if(!card) return 320;
  const cs = window.getComputedStyle(elCards);
  const gap = parseFloat(cs.columnGap || cs.gap || "12") || 12;
  return card.offsetWidth + gap;
}

function updateScrollButtons(){
  if(!elScrollLeft || !elScrollRight) return;
  const maxLeft = elCards.scrollWidth - elCards.clientWidth;
  const atStart = elCards.scrollLeft <= 1;
  const atEnd = elCards.scrollLeft >= (maxLeft - 1);

  elScrollLeft.disabled = atStart;
  elScrollRight.disabled = atEnd;

  if(elScrollHint){
    if(atStart && atEnd){
      elScrollHint.textContent = "（この月はカードが少ないため横スクロール不要）";
    }else if(atEnd){
      elScrollHint.textContent = "（最後まで表示中）";
    }else if(atStart){
      elScrollHint.textContent = "（先頭を表示中）";
    }else{
      elScrollHint.textContent = "（横にスクロールできます）";
    }
  }
}

function scrollByCard(dir){
  elCards.scrollBy({ left: dir * getScrollStep(), behavior: "smooth" });
}

if(elScrollLeft && elScrollRight){
  elScrollLeft.addEventListener("click", ()=>scrollByCard(-1));
  elScrollRight.addEventListener("click", ()=>scrollByCard(1));

  let rafId = null;
  elCards.addEventListener("scroll", ()=>{
    if(rafId) cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(updateScrollButtons);
  });
}


function buildYearSelect(){
  // 表示する年は「現在年±2年」。必要なら増やしてください。
  const nowY = new Date().getFullYear();
  const years = [nowY-2, nowY-1, nowY, nowY+1, nowY+2];
  elYearSelect.innerHTML = "";
  for(const y of years){
    const opt = document.createElement("option");
    opt.value = String(y);
    opt.textContent = `${y}年（年集計）`;
    if(y === state.year) opt.selected = true;
    elYearSelect.appendChild(opt);
  }
}

function buildMonthSelect(){
  // 現在選択中の state.year の 1〜12月を作る（年を変えたら作り直し）
  elMonthSelect.innerHTML = "";
  for(let m=0;m<12;m++){
    const opt = document.createElement("option");
    opt.value = `${state.year}-${m}`;
    opt.textContent = `${state.year}年 ${String(m+1).padStart(2,"0")}月`;
    if(m === state.monthIndex) opt.selected = true;
    elMonthSelect.appendChild(opt);
  }
}

function setTabs(){
  const map = { male: elTabMale, female: elTabFemale, total: elTabTotal };
  for(const k of Object.keys(map)){
    map[k].classList.toggle("active", state.view===k);
    map[k].setAttribute("aria-selected", state.view===k ? "true" : "false");
  }
}

function setActiveIso(iso){
  state.activeIso = iso;
  const m = countChecked(iso, "male");
  const f = countChecked(iso, "female");
  elActiveMale.textContent = m;
  elActiveFemale.textContent = f;
  elActiveTotal.textContent = m+f;
}

function renderMonthSummary(){
  const mm = String(state.monthIndex+1).padStart(2,"0");
  const totals = getCurrentMonthTotals();
  elMonthSummary.innerHTML = `
    <div>表示月：<strong>${state.year}-${mm}</strong>（日曜のみ）</div>
    <div>月累計：男性 <strong>${totals.male}</strong> ／ 女性 <strong>${totals.female}</strong> ／ 男女合計 <strong>${totals.total}</strong></div>
  `;
}

function renderYearTable(){
  const y = state.year;
  elYearLabel.textContent = y;
  const yt = getYearTotals(y);

  // 1〜12月の行
  let rows = "";
  for(const m of yt.months){
    const mm = String(m.monthIndex+1).padStart(2,"0");
    rows += `
      <tr>
        <td>${mm}月（${y}-${mm}）</td>
        <td class="num">${m.male}</td>
        <td class="num">${m.female}</td>
        <td class="num">${m.total}</td>
      </tr>
    `;
  }
  // 年合計行
  rows += `
    <tr class="yearTotalRow">
      <td>年間合計</td>
      <td class="num">${yt.male}</td>
      <td class="num">${yt.female}</td>
      <td class="num">${yt.total}</td>
    </tr>
  `;
  elYearBody.innerHTML = rows;
}

/************************************************************
 * 日計テーブル（表示月の日曜日ごと）
 ************************************************************/
const JP_WEEK_NAMES = ["日","月","火","水","木","金","土"];

function renderDailyTable(){
  const y = state.year;
  const mIndex = state.monthIndex;
  const mm = String(mIndex + 1).padStart(2, "0");
  const sundays = getSundaysOfMonth(y, mIndex);

  const labelEl = document.getElementById("dailyPanelLabel");
  const bodyEl  = document.getElementById("dailyTableBody");
  const sumMEl  = document.getElementById("dailySumMale");
  const sumFEl  = document.getElementById("dailySumFemale");
  const sumTEl  = document.getElementById("dailySumTotal");
  if(!bodyEl) return;

  if(labelEl) labelEl.textContent = `${y}年${mm}月`;

  const mKey = monthKey(y, mIndex);
  ensureMonthLoaded(mKey);

  let sumM = 0, sumF = 0;
  let rows = "";

  for(const dt of sundays){
    const iso = toISODate(dt);
    const wd  = JP_WEEK_NAMES[dt.getDay()];
    const mc  = countChecked(iso, "male");
    const fc  = countChecked(iso, "female");
    const tc  = mc + fc;
    sumM += mc; sumF += fc;

    const mmdd = toMMDD(dt);
    rows += `
      <tr class="daily-row" data-iso="${iso}" title="クリックで名簿表示" style="cursor:pointer">
        <td>${mmdd}（${wd}）</td>
        <td class="num">${mc}</td>
        <td class="num">${fc}</td>
        <td class="num">${tc}</td>
      </tr>
    `;
  }

  if(rows === ""){
    rows = `<tr><td colspan="4" style="text-align:center;color:var(--muted)">この月には日曜日がありません</td></tr>`;
  }

  bodyEl.innerHTML = rows;
  if(sumMEl) sumMEl.textContent = sumM;
  if(sumFEl) sumFEl.textContent = sumF;
  if(sumTEl) sumTEl.textContent = sumM + sumF;

  // 行クリックで礼拝日フィルター表示（tubasa風）
  bodyEl.querySelectorAll(".daily-row").forEach(tr => {
    tr.addEventListener("click", ()=>{
      const iso = tr.dataset.iso;
      showDailyFilter(iso);
    });
  });
}

function renderCards(){
  // ---- フィルターモード（特定日を両性カードで表示） ----
  if(state.filterDate){
    elCards.innerHTML = "";
    const iso = state.filterDate;
    const dt = new Date(iso + "T00:00:00");
    const card = buildFilterCard(dt, iso);
    elCards.appendChild(card);
    requestAnimationFrame(updateScrollButtons);
    return;
  }

  // ---- 通常モード（日曜カード） ----
  const sundays = getSundaysOfMonth(state.year, state.monthIndex);
  elCards.innerHTML = "";

  if(sundays.length === 0){
    elCards.innerHTML = `<div class="hint">この月には日曜日がありません（通常は起きません）</div>`;
    return;
  requestAnimationFrame(updateScrollButtons);
  }

  const isoList = sundays.map(toISODate);
  if(!state.activeIso || !isoList.includes(state.activeIso)){
    setActiveIso(isoList[0]);
  }else{
    setActiveIso(state.activeIso);
  }

  for(const dt of sundays){
    const iso = toISODate(dt);
    const card = document.createElement("section");
    card.className = "card" + (iso===state.activeIso ? " active" : "");
    card.dataset.iso = iso;

    const mmdd = toMMDD(dt);
    const weekday = jpWeekday(dt);

    const maleCnt = countChecked(iso, "male");
    const femaleCnt = countChecked(iso, "female");
    const totalCnt = maleCnt + femaleCnt;

    const badgeHtml = (()=>{
      if(state.view==="male") return `<div class="badge"><span>男性</span> <strong>${maleCnt}</strong></div>`;
      if(state.view==="female") return `<div class="badge"><span>女性</span> <strong>${femaleCnt}</strong></div>`;
      return `
        <div class="badge"><span>男性</span> <strong>${maleCnt}</strong></div>
        <div class="badge"><span>女性</span> <strong>${femaleCnt}</strong></div>
        <div class="badge"><span>合計</span> <strong>${totalCnt}</strong></div>
      `;
    })();

    card.innerHTML = `
      <div class="cardHead">
        <div>
          <div class="dateBig">${mmdd}</div>
          <div class="dateSmall">${state.year}年 / ${weekday}曜日（${iso}）</div>
        </div>
        <div class="badgeBox">${badgeHtml}</div>
      </div>
      <div class="list">
        ${
          state.view==="total"
          ? `
            <div class="hint">※「男女合計」タブではチェック表は表示しません（重くなるため）。人数と月累計・年集計を確認できます。</div>
            <div style="margin-top:10px" class="hint">必要なら男性/女性タブに切り替えてチェックしてください。</div>
          `
          : `
            <div class="listHeader">
              <div class="label">${state.view==="male" ? "男性名簿" : "女性名簿"}</div>
            </div>
            ${renderRosterTableHTML(iso, state.view)}
          `
        }
      </div>
    `;

    // カードタップでアクティブ切替
    card.addEventListener("click", ()=>{
      setActiveIso(iso);
      for(const c of elCards.querySelectorAll(".card")) c.classList.remove("active");
      card.classList.add("active");
    });

    // チェックイベント
    if(state.view!=="total"){
      card.addEventListener("change", (e)=>{
        const t = e.target;
        if(t && t.matches('input[type="checkbox"][data-person-id]')){
          const pid = t.getAttribute("data-person-id");
          const gender = state.view; // "male" or "female"
          setCheck(iso, gender, pid, t.checked);

          updateCardBadges(card, iso);
          renderMonthSummary();
          renderYearTable(); // 年表も即更新
          renderDailyTable(); // 日計も即更新
          setActiveIso(state.activeIso);
        }
      });
    }

    elCards.appendChild(card);
  }
  // 描画後に端の判定（カード数・横幅が確定してから）
  requestAnimationFrame(updateScrollButtons);
}

function renderRosterTableHTML(iso, view){
  const gender = view; // "male"|"female"
  const roster = (gender==="male") ? rosterMale : rosterFemale;

  let rows = "";
  for(const person of roster){
    const checked = getCheck(iso, gender, person.id);
    rows += `
      <tr>
        <td class="nameCell">${escapeHtml(person.label)}</td>
        <td style="text-align:center">
          <input type="checkbox" data-person-id="${person.id}" ${checked ? "checked" : ""} />
        </td>
      </tr>
    `;
  }

  return `
    <table>
      <thead>
        <tr><th>名前</th><th style="text-align:center">出席</th></tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
  `;
}

/************************************************************
 * フィルターカード（指定日を男女2カラムで表示）
 ************************************************************/
function buildFilterCard(dt, iso){
  const mmdd    = toMMDD(dt);
  const weekday = jpWeekday(dt);
  const maleCnt   = countChecked(iso, "male");
  const femaleCnt = countChecked(iso, "female");

  const card = document.createElement("section");
  card.className = "card filter-card-wide";
  card.style.minWidth = "620px";
  card.style.maxWidth = "700px";
  card.dataset.iso = iso;

  // ヘッダー
  const head = document.createElement("div");
  head.className = "cardHead";
  head.innerHTML = `
    <div>
      <div class="dateBig">${mmdd}</div>
      <div class="dateSmall">${dt.getFullYear()}年 / ${weekday}曜日（${iso}）</div>
    </div>
    <div class="badgeBox">
      <div class="badge">
        <span>男</span> <strong id="fc-male-cnt">${maleCnt}</strong>
        &nbsp;/&nbsp;
        <span>女</span> <strong id="fc-female-cnt">${femaleCnt}</strong>
        &nbsp;/&nbsp;
        <span>計</span> <strong id="fc-total-cnt">${maleCnt+femaleCnt}</strong>
      </div>
    </div>
  `;
  card.appendChild(head);

  // 2カラム
  const twoCol = document.createElement("div");
  twoCol.className = "twoCol";

  function buildCol(gender, roster, label){
    const col = document.createElement("div");
    col.className = "col";
    col.innerHTML = `<div class="col-label">${label}（${roster.length}名）</div>`;
    const tbl   = document.createElement("table");
    const tbody = document.createElement("tbody");
    for(const person of roster){
      const checked = getCheck(iso, gender, person.id);
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(person.label)}</td>
        <td><input class="chk" type="checkbox"
             data-person-id="${person.id}" data-gender="${gender}"
             ${checked ? "checked" : ""} /></td>
      `;
      tr.querySelector("input").addEventListener("change", (e)=>{
        const pid = e.target.getAttribute("data-person-id");
        const g   = e.target.getAttribute("data-gender");
        setCheck(iso, g, pid, e.target.checked);
        // バッジ更新
        const mc = countChecked(iso, "male");
        const fc = countChecked(iso, "female");
        card.querySelector("#fc-male-cnt").textContent  = mc;
        card.querySelector("#fc-female-cnt").textContent = fc;
        card.querySelector("#fc-total-cnt").textContent  = mc + fc;
        renderMonthSummary();
        renderYearTable();
        renderDailyTable();
      });
      tbody.appendChild(tr);
    }
    tbl.appendChild(tbody);
    col.appendChild(tbl);
    return col;
  }

  twoCol.appendChild(buildCol("male",   rosterMale,   "♂ 男性"));
  twoCol.appendChild(buildCol("female", rosterFemale, "♀ 女性"));
  card.appendChild(twoCol);
  return card;
}

/************************************************************
 * 礼拝日管理（localStorage）
 ************************************************************/
function getWorkdays(){
  try{
    const raw = localStorage.getItem(LS_WORKDAYS_KEY);
    return raw ? JSON.parse(raw) : [];
  }catch(e){ return []; }
}
function saveWorkdays(arr){
  arr.sort();
  localStorage.setItem(LS_WORKDAYS_KEY, JSON.stringify(arr));
}

function renderWorkdayList(){
  const dates = getWorkdays();
  const el = document.getElementById("workdayList");
  if(!el) return;
  el.innerHTML = "";

  if(dates.length === 0){
    el.innerHTML = `<div class="workday-empty">確定した礼拝日はまだありません</div>`;
    return;
  }

  for(const dateStr of dates){
    const d   = new Date(dateStr + "T00:00:00");
    const m   = d.getMonth() + 1;
    const day = d.getDate();
    const wd  = JP_WEEK_NAMES[d.getDay()];
    const shortLabel = `${m}/${day}`;

    const chip = document.createElement("div");
    chip.className = "workday-chip";

    chip.innerHTML = `
      <span class="chip-date" title="${dateStr}（クリックで表示）">${shortLabel}</span>
      <span class="chip-wd">${wd}</span>
      <button class="chip-show" title="${dateStr} の名簿を表示">表示</button>
      <button class="chip-del" title="削除">×</button>
    `;

    chip.querySelector(".chip-date").addEventListener("click", ()=> showDailyFilter(dateStr));
    chip.querySelector(".chip-show").addEventListener("click", ()=> showDailyFilter(dateStr));
    chip.querySelector(".chip-del").addEventListener("click",  ()=> removeWorkday(dateStr));

    el.appendChild(chip);
  }
}

function confirmWorkday(){
  const inp = document.getElementById("workdayDateInput");
  const val = inp ? inp.value : "";
  if(!val){ alert("日付を選択してください。"); return; }

  const d = new Date(val + "T00:00:00");

  // 日曜日でない場合は確認ダイアログ
  if(d.getDay() !== 0){
    const wd = JP_WEEK_NAMES[d.getDay()];
    const ok = confirm(`${d.getMonth()+1}/${d.getDate()}（${val}）は${wd}曜日です。\n日曜日ではありませんが、よろしいですか？`);
    if(!ok) return;
  }

  const dates = getWorkdays();
  if(dates.includes(val)){
    alert(`${d.getMonth()+1}/${d.getDate()}（${val}）は既に確定されています。`);
    return;
  }

  dates.push(val);
  saveWorkdays(dates);
  if(inp) inp.value = "";
  renderWorkdayList();
}

function removeWorkday(dateStr){
  const d = new Date(dateStr + "T00:00:00");
  const shortLabel = `${d.getMonth()+1}/${d.getDate()}`;
  if(!confirm(`礼拝日 ${shortLabel}（${dateStr}）を削除しますか？`)) return;

  let dates = getWorkdays();
  dates = dates.filter(x => x !== dateStr);
  saveWorkdays(dates);

  if(state.filterDate === dateStr){
    clearDailyFilter();
    renderCards();
  }
  renderWorkdayList();
}

function updateCardBadges(card, iso){
  const maleCnt = countChecked(iso, "male");
  const femaleCnt = countChecked(iso, "female");
  const totalCnt = maleCnt + femaleCnt;

  const box = card.querySelector(".badgeBox");
  if(!box) return;

  if(state.view==="male"){
    box.innerHTML = `<div class="badge"><span>男性</span> <strong>${maleCnt}</strong></div>`;
  }else if(state.view==="female"){
    box.innerHTML = `<div class="badge"><span>女性</span> <strong>${femaleCnt}</strong></div>`;
  }else{
    box.innerHTML = `
      <div class="badge"><span>男性</span> <strong>${maleCnt}</strong></div>
      <div class="badge"><span>女性</span> <strong>${femaleCnt}</strong></div>
      <div class="badge"><span>合計</span> <strong>${totalCnt}</strong></div>
    `;
  }
}

function escapeHtml(s){
  return String(s)
    .replaceAll("&","&amp;")
    .replaceAll("<","&lt;")
    .replaceAll(">","&gt;")
    .replaceAll('"',"&quot;")
    .replaceAll("'","&#39;");
}

/************************************************************
 * 月移動・同期
 ************************************************************/
function changeMonth(delta){
  const d = new Date(state.year, state.monthIndex + delta, 1);
  state.year = d.getFullYear();
  state.monthIndex = d.getMonth();
  // 年が変わった可能性があるので年選択も追従
  syncUI();
}

function syncUI(){
  buildYearSelect();
  buildMonthSelect();
  setTabs();
  renderMonthSummary();
  renderYearTable();
  renderDailyTable();
  renderCards();
  updateRosterControls();
}

elPrev.addEventListener("click", ()=>changeMonth(-1));
elNext.addEventListener("click", ()=>changeMonth(1));

elMonthSelect.addEventListener("change", ()=>{
  const [yStr, mStr] = elMonthSelect.value.split("-");
  state.year = Number(yStr);
  state.monthIndex = Number(mStr);
  syncUI();
});

elYearSelect.addEventListener("change", ()=>{
  const y = Number(elYearSelect.value);
  state.year = y;
  // 月はそのまま（例：2月のまま年だけ変更）
  syncUI();
});

elTabMale.addEventListener("click", ()=>{ state.view="male"; syncUI(); });
elTabFemale.addEventListener("click", ()=>{ state.view="female"; syncUI(); });
elTabTotal.addEventListener("click", ()=>{ state.view="total"; syncUI(); });

// clearMonthボタンが存在しないためコメントアウト
/*
elClear.addEventListener("click", ()=>{
  const mKey = monthKey(state.year, state.monthIndex);
  if(!confirm(`この月（${mKey}）の出欠データを全削除します。よろしいですか？`)) return;
  localStorage.removeItem(storageKey(mKey));
  state.data[mKey] = {};
  state.activeIso = null;
  syncUI();
});
*/


/************************************************************
 * 日付フィルター表示（日計テーブル行クリック時）
 ************************************************************/
function showDailyFilter(iso){
  state.filterDate = iso;
  const dt = new Date(iso + "T00:00:00");
  const m  = dt.getMonth() + 1;
  const d  = dt.getDate();
  const wd = JP_WEEK_NAMES[dt.getDay()];

  const fbEl  = document.getElementById("filterBar");
  const fdlEl = document.getElementById("filterDateLabel");
  const fnEl  = document.getElementById("filterNote");
  if(fbEl)  fbEl.classList.add("active");
  if(fdlEl) fdlEl.textContent = `${iso}（${m}/${d} ${wd}曜日）を表示中`;
  if(fnEl)  fnEl.textContent  = "男性・女性 両方の名簿を表示 / チェックの入れ直しが可能です";

  // 月を合わせる
  const newMIndex = dt.getMonth();
  if(state.monthIndex !== newMIndex){
    state.monthIndex = newMIndex;
    buildMonthSelect();
  }

  renderCards();
  window.scrollTo({ top: elCards.offsetTop - 100, behavior: "smooth" });
}

function clearDailyFilter(){
  state.filterDate = null;
  const fbEl = document.getElementById("filterBar");
  if(fbEl) fbEl.classList.remove("active");
}

// 「通常表示へ戻る」ボタン
const elBackNormal = document.getElementById("backNormalBtn");
if(elBackNormal){
  elBackNormal.addEventListener("click", ()=>{
    clearDailyFilter();
    renderCards();
  });
}

/************************************************************
 * 日計 CSV 出力
 ************************************************************/
function exportDailyCsv(){
  const y = state.year;
  const mIndex = state.monthIndex;
  const mm = String(mIndex + 1).padStart(2, "0");
  const sundays = getSundaysOfMonth(y, mIndex);

  if(!confirm(`${y}年${mm}月の日計（日曜日ごとの男女別人数）をCSVで出力します。よろしいですか？`)) return;

  const mKey = monthKey(y, mIndex);
  ensureMonthLoaded(mKey);

  const rows = [];
  rows.push(["日付", "曜日", "男性", "女性", "合計"]);

  let sumM = 0, sumF = 0;
  for(const dt of sundays){
    const iso = toISODate(dt);
    const wd  = JP_WEEK_NAMES[dt.getDay()];
    const mc  = countChecked(iso, "male");
    const fc  = countChecked(iso, "female");
    sumM += mc; sumF += fc;
    rows.push([iso, wd, mc, fc, mc + fc]);
  }
  rows.push(["月合計", "", sumM, sumF, sumM + sumF]);

  downloadCSV(`daily_attendance_${y}-${mm}.csv`, rows);
  alert("日計CSVを保存しました。");
}

const elExportDailyCsv = document.getElementById("exportDailyCsv");
if(elExportDailyCsv){
  elExportDailyCsv.addEventListener("click", exportDailyCsv);
}

/************************************************************
 * CSV出力（表示中の月＋年集計）
 ************************************************************/
const elExportCsv = document.getElementById("exportCsv");

function toCsvCell(v){
  // CSVに安全に入れる（カンマ・改行・ダブルクォート対策）
  const s = String(v ?? "");
  if(/[",\r\n]/.test(s)){
    return '"' + s.replace(/"/g, '""') + '"';
  }
  return s;
}

function downloadCSV(filename, rows){
  const bom = "\uFEFF"; // Excel向け
  const csv = rows.map(r => r.map(toCsvCell).join(",")).join("\n");
  const blob = new Blob([bom + csv], { type: "text/csv;charset=utf-8" });
  const url = URL.createObjectURL(blob);

  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();

  URL.revokeObjectURL(url);
}

function exportCsv(){
  if(!confirm("表示中の月の日計・月集計と年集計をCSVで出力します。よろしいですか？")) return;

  const y = state.year;
  const m = state.monthIndex + 1;
  const mm = String(m).padStart(2, "0");
  const sundays = getSundaysOfMonth(y, state.monthIndex);
  const mKey = monthKey(y, state.monthIndex);
  ensureMonthLoaded(mKey);

  const rows = [];
  rows.push(["区分","年","月","日付","曜日","男性","女性","合計"]);

  // 日計（表示月の各日曜）
  for(const dt of sundays){
    const iso = toISODate(dt);
    const wd  = JP_WEEK_NAMES[dt.getDay()];
    const mc  = countChecked(iso, "male");
    const fc  = countChecked(iso, "female");
    rows.push(["日計", y, m, iso, wd, mc, fc, mc + fc]);
  }

  // 月集計（表示中）
  const mt = getCurrentMonthTotals();
  rows.push(["月集計", y, m, "", "", mt.male, mt.female, mt.total]);

  // 年集計（1〜12月 + 年間合計）
  const yt = getYearTotals(y);
  yt.months.forEach(mm2=>{
    rows.push(["年集計", y, mm2.monthIndex + 1, "", "", mm2.male, mm2.female, mm2.total]);
  });
  rows.push(["年集計", y, "", "", "年間合計", yt.male, yt.female, yt.total]);

  downloadCSV(`attendance_${y}-${mm}.csv`, rows);
  alert("CSVを保存しました。");
}

if(elExportCsv){
  elExportCsv.addEventListener("click", exportCsv);
}

/************************************************************
 * 初期化
 ************************************************************/
(function init(){
  applyStoredRosterIfAny(); // localStorageから名簿を読み込み
  rebuildRostersFromStorage(); // 追加: 名簿増減のため再構築
  const mKey = monthKey(state.year, state.monthIndex);
  ensureMonthLoaded(mKey);
  syncUI();
  
  
  // 追加: 名簿の行数を増減（+10/-10）
  const elRosterAdd10 = document.getElementById("rosterAdd10");
  const elRosterRemove10 = document.getElementById("rosterRemove10");
  const elRosterAdd1 = document.getElementById("rosterAdd1");
  const elRosterRemove1 = document.getElementById("rosterRemove1");

  function currentGenderForRoster(){
    return (state.view === "male" || state.view === "female") ? state.view : null;
  }

  if(elRosterAdd10){
    elRosterAdd10.addEventListener("click", ()=>{
      const g = currentGenderForRoster();
      if(!g) return;
      addRosterRows(g, 10);
      syncUI();
    });
  }
  if(elRosterRemove10){
    elRosterRemove10.addEventListener("click", ()=>{
      const g = currentGenderForRoster();
      if(!g) return;
      if(!confirm("末尾の空欄行を10行削除します。よろしいですか？")) return;
      const ok = removeRosterRows(g, 10);
      if(ok) syncUI();
    });
  }
  if(elRosterAdd1){
    elRosterAdd1.addEventListener("click", ()=>{
      const g = currentGenderForRoster();
      if(!g) return;
      addRosterRows(g, 1);
      syncUI();
    });
  }
  if(elRosterRemove1){
    elRosterRemove1.addEventListener("click", ()=>{
      const g = currentGenderForRoster();
      if(!g) return;
      if(!confirm("末尾の空欄行を1行削除します。よろしいですか？")) return;
      const ok = removeRosterRows(g, 1);
      if(ok) syncUI();
    });
  }

  // CSV読み込みイベントリスナー
  const elMaleCsv = document.getElementById("maleCsv");
  const elFemaleCsv = document.getElementById("femaleCsv");
  const elMaleCsvAdd = document.getElementById("maleCsvAdd");
  const elFemaleCsvAdd = document.getElementById("femaleCsvAdd");
  const elInitRoster = document.getElementById("initRoster");
  
  const elApplyMaleCsv = document.getElementById("applyMaleCsv");
  const elApplyFemaleCsv = document.getElementById("applyFemaleCsv");
  const elApplyMaleCsvAdd = document.getElementById("applyMaleCsvAdd");
  const elApplyFemaleCsvAdd = document.getElementById("applyFemaleCsvAdd");
  
  const elMaleCsvStatus = document.getElementById("maleCsvStatus");
  const elFemaleCsvStatus = document.getElementById("femaleCsvStatus");
  const elMaleCsvAddStatus = document.getElementById("maleCsvAddStatus");
  const elFemaleCsvAddStatus = document.getElementById("femaleCsvAddStatus");
  
  // 男性名簿CSV - ファイル選択時
  if(elMaleCsv){
    elMaleCsv.addEventListener("change", (e)=>{
      if(e.target.files && e.target.files[0]){
        elMaleCsvStatus.textContent = `選択: ${e.target.files[0].name}`;
        elMaleCsvStatus.style.color = "var(--accent2)";
        if(elApplyMaleCsv) elApplyMaleCsv.disabled = false;
      }else{
        elMaleCsvStatus.textContent = "ファイルが選択されていません";
        elMaleCsvStatus.style.color = "";
        if(elApplyMaleCsv) elApplyMaleCsv.disabled = true;
      }
    });
  }
  
  // 男性名簿CSV - 反映ボタンクリック時
  if(elApplyMaleCsv){
    elApplyMaleCsv.addEventListener("click", async ()=>{
      if(elMaleCsv && elMaleCsv.files && elMaleCsv.files[0]){
        await handleMaleCsvImport(elMaleCsv.files[0], false);
        elMaleCsv.value = ""; // リセット
        elMaleCsvStatus.textContent = "ファイルが選択されていません";
        elMaleCsvStatus.style.color = "";
        elApplyMaleCsv.disabled = true;
      }
    });
  }
  
  // 女性名簿CSV - ファイル選択時
  if(elFemaleCsv){
    elFemaleCsv.addEventListener("change", (e)=>{
      if(e.target.files && e.target.files[0]){
        elFemaleCsvStatus.textContent = `選択: ${e.target.files[0].name}`;
        elFemaleCsvStatus.style.color = "var(--accent2)";
        if(elApplyFemaleCsv) elApplyFemaleCsv.disabled = false;
      }else{
        elFemaleCsvStatus.textContent = "ファイルが選択されていません";
        elFemaleCsvStatus.style.color = "";
        if(elApplyFemaleCsv) elApplyFemaleCsv.disabled = true;
      }
    });
  }
  
  // 女性名簿CSV - 反映ボタンクリック時
  if(elApplyFemaleCsv){
    elApplyFemaleCsv.addEventListener("click", async ()=>{
      if(elFemaleCsv && elFemaleCsv.files && elFemaleCsv.files[0]){
        await handleFemaleCsvImport(elFemaleCsv.files[0], false);
        elFemaleCsv.value = ""; // リセット
        elFemaleCsvStatus.textContent = "ファイルが選択されていません";
        elFemaleCsvStatus.style.color = "";
        elApplyFemaleCsv.disabled = true;
      }
    });
  }
  
  // 追加 男性名簿CSV - ファイル選択時
  if(elMaleCsvAdd){
    elMaleCsvAdd.addEventListener("change", (e)=>{
      if(e.target.files && e.target.files[0]){
        elMaleCsvAddStatus.textContent = `選択: ${e.target.files[0].name}`;
        elMaleCsvAddStatus.style.color = "var(--accent2)";
        if(elApplyMaleCsvAdd) elApplyMaleCsvAdd.disabled = false;
      }else{
        elMaleCsvAddStatus.textContent = "ファイルが選択されていません";
        elMaleCsvAddStatus.style.color = "";
        if(elApplyMaleCsvAdd) elApplyMaleCsvAdd.disabled = true;
      }
    });
  }
  
  // 追加 男性名簿CSV - 追加ボタンクリック時
  if(elApplyMaleCsvAdd){
    elApplyMaleCsvAdd.addEventListener("click", async ()=>{
      if(elMaleCsvAdd && elMaleCsvAdd.files && elMaleCsvAdd.files[0]){
        await handleMaleCsvImport(elMaleCsvAdd.files[0], true);
        elMaleCsvAdd.value = ""; // リセット
        elMaleCsvAddStatus.textContent = "ファイルが選択されていません";
        elMaleCsvAddStatus.style.color = "";
        elApplyMaleCsvAdd.disabled = true;
      }
    });
  }
  
  // 追加 女性名簿CSV - ファイル選択時
  if(elFemaleCsvAdd){
    elFemaleCsvAdd.addEventListener("change", (e)=>{
      if(e.target.files && e.target.files[0]){
        elFemaleCsvAddStatus.textContent = `選択: ${e.target.files[0].name}`;
        elFemaleCsvAddStatus.style.color = "var(--accent2)";
        if(elApplyFemaleCsvAdd) elApplyFemaleCsvAdd.disabled = false;
      }else{
        elFemaleCsvAddStatus.textContent = "ファイルが選択されていません";
        elFemaleCsvAddStatus.style.color = "";
        if(elApplyFemaleCsvAdd) elApplyFemaleCsvAdd.disabled = true;
      }
    });
  }
  
  // 追加 女性名簿CSV - 追加ボタンクリック時
  if(elApplyFemaleCsvAdd){
    elApplyFemaleCsvAdd.addEventListener("click", async ()=>{
      if(elFemaleCsvAdd && elFemaleCsvAdd.files && elFemaleCsvAdd.files[0]){
        await handleFemaleCsvImport(elFemaleCsvAdd.files[0], true);
        elFemaleCsvAdd.value = ""; // リセット
        elFemaleCsvAddStatus.textContent = "ファイルが選択されていません";
        elFemaleCsvAddStatus.style.color = "";
        elApplyFemaleCsvAdd.disabled = true;
      }
    });
  }
  
  if(elInitRoster){
    elInitRoster.addEventListener("click", initializeRoster);
  }

  // 礼拝日確定パネルのイベント
  const elConfirmWorkday = document.getElementById("confirmWorkdayBtn");
  const elWorkdayDate    = document.getElementById("workdayDateInput");
  if(elConfirmWorkday){
    elConfirmWorkday.addEventListener("click", confirmWorkday);
  }
  if(elWorkdayDate){
    elWorkdayDate.addEventListener("keydown", (e)=>{
      if(e.key === "Enter") confirmWorkday();
    });
  }

  // 礼拝日リストの初期描画
  renderWorkdayList();
})();
</script>
</body>
</html>