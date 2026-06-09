<?php
session_start();
if (empty($_SESSION['login_ok'])) {
    header("Location: login.php");
    exit;
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>平日 出欠・人数集計（2026年）</title>
  <style>
    /* ========================================
       ベースCSS変数（既存・緑系トーン維持）
       ======================================== */
    :root{
      --bg:#e9f6ea;
      --card:#ffffff;
      --ink:#0b1f12;
      --muted:#4a6b57;
      --accent:#1f7a3b;
      --line:#d6e6da;
      --chip:#e7f6ec;
      --shadow:0 8px 22px rgba(0,0,0,.06);
      --radius:18px;
      --danger:#c0392b;
      --dangerLight:#fdecea;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: system-ui, -apple-system, "Segoe UI", "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif;
      color:var(--ink);
      background:var(--bg);
    }

    /* ========================================
       ▼ 追加CSS：右上ログアウトボタン（固定）
       ======================================== */
    .logout-fixed{
      position:fixed;
      top:14px;
      right:16px;
      z-index:9999;
    }
    .logout-btn{
      display:inline-flex;
      align-items:center;
      gap:8px;
      background:var(--danger);
      color:#fff;
      padding:10px 18px;
      border:none;
      border-radius:999px;
      font-weight:900;
      cursor:pointer;
      box-shadow:0 6px 16px rgba(0,0,0,.15);
      font-size:14px;
      letter-spacing:.2px;
      transition:transform .15s, filter .15s;
    }
    .logout-btn:hover{ transform:translateY(-1px); filter:brightness(0.98); }
    .logout-btn:active{ transform:translateY(0); }
    .logout-btn:focus{ outline: none; box-shadow:0 0 0 4px rgba(192,57,43,.18), 0 6px 16px rgba(0,0,0,.15); }

    /* ========================================
       ヘッダー・ツールバー（既存）
       ======================================== */
    header{
      padding:18px 16px 8px;
      max-width:1200px;
      margin:0 auto;
    }
    h1{margin:0 0 4px;font-size:18px;font-weight:900}
    .sub{color:var(--muted);font-size:12px;line-height:1.4}
    .toolbar{
      max-width:1200px;margin:10px auto 0;padding:0 16px 14px;
      display:flex;gap:10px;flex-wrap:wrap;align-items:center;
    }
    .pill{
      background:var(--card);border:1px solid var(--line);border-radius:999px;
      padding:8px 10px;display:flex;gap:8px;align-items:center;box-shadow:var(--shadow);
    }
    .pill button, .pill select, .pill input[type="file"]{
      border:1px solid var(--line);background:#fff;border-radius:999px;
      padding:8px 10px;font-size:13px;cursor:pointer;
    }
    .seg{
      display:flex;background:#fff;border:1px solid var(--line);border-radius:999px;overflow:hidden;
    }
    .seg button{
      border:0;background:transparent;padding:9px 14px;cursor:pointer;
      font-weight:800;color:var(--muted);font-size:13px;
    }
    .seg button.active{background:var(--accent);color:#fff}

    /* ========================================
       集計エリア（既存）
       ======================================== */
    .stats{
      max-width:1200px;margin:6px auto 0;padding:0 16px 10px;
      display:flex;gap:10px;flex-wrap:wrap;align-items:stretch;
    }
    .stat{
      flex:1 1 280px;background:var(--card);border:1px solid var(--line);
      border-radius:var(--radius);padding:12px 14px;box-shadow:var(--shadow);
    }
    .stat .k{font-size:12px;color:var(--muted);margin-bottom:4px}
    .stat .v{font-size:16px;font-weight:900}
    .hint{font-size:12px;color:var(--muted)}

    /* ========================================
       カード表示エリア（既存）
       ======================================== */
    .row{max-width:1200px;margin:0 auto;padding:8px 16px 16px}
    .scrollerWrap{display:flex;gap:10px;align-items:center;margin:10px 0 8px}
    .navBtn{
      border:1px solid var(--line);background:#fff;border-radius:999px;
      padding:10px 14px;cursor:pointer;box-shadow:var(--shadow);font-weight:900;
    }
    .cards{
      display:flex;gap:14px;overflow:auto;scroll-snap-type:x mandatory;padding:6px 2px 14px;
    }
    .card{
      min-width:290px;max-width:290px;background:var(--card);border:1px solid var(--line);
      border-radius:var(--radius);box-shadow:var(--shadow);scroll-snap-align:start;overflow:hidden;
    }
    .cardHead{
      padding:12px 14px 8px;border-bottom:1px solid var(--line);
      display:flex;justify-content:space-between;align-items:flex-start;gap:8px;
    }
    .dateBig{font-weight:900;font-size:20px;letter-spacing:.3px;margin:0;line-height:1.1}
    .dateSub{margin-top:3px;font-size:12px;color:var(--muted)}
    .badge{
      background:var(--chip);border:1px solid var(--line);
      padding:6px 10px;border-radius:999px;font-weight:900;font-size:12px;white-space:nowrap;
    }
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px 14px;border-bottom:1px solid var(--line);font-size:13px}
    th{text-align:left;color:var(--muted);font-size:12px;background:#f6fbf7;position:sticky;top:0;z-index:1}
    td:last-child,th:last-child{text-align:right}
    .chk{width:20px;height:20px;accent-color:var(--accent);cursor:pointer}

    /* ========================================
       アクション・フッター（既存）
       ======================================== */
    .actions{
      max-width:1200px;margin:0 auto;padding:0 16px 20px;
      display:flex;gap:10px;flex-wrap:wrap;align-items:center;
    }
    .primary{
      background:var(--accent);border:1px solid rgba(0,0,0,.05);color:#fff;font-weight:900;
      padding:12px 16px;border-radius:999px;cursor:pointer;box-shadow:var(--shadow);
    }
    .ghost{
      background:#fff;border:1px solid var(--line);color:var(--ink);font-weight:900;
      padding:12px 16px;border-radius:999px;cursor:pointer;box-shadow:var(--shadow);
    }
    .foot{max-width:1200px;margin:0 auto;padding:0 16px 24px;color:var(--muted);font-size:12px;line-height:1.6}
    .small{font-size:12px;color:var(--muted);font-weight:800}

    /* ========================================
       月別集計テーブル（既存）
       ======================================== */
    .yearTableWrap{margin-top:10px}
    .yearTable{
      width:100%;
      border-collapse:collapse;
      overflow:hidden;
      border:1px solid var(--line);
      border-radius:14px;
    }
    .yearTable th{
      position:static;
      background:#f6fbf7;
      font-size:12px;
      color:var(--muted);
    }
    .yearTable td{ font-size:13px; }
    .yearTable tr.activeRow td{
      background:rgba(31,122,59,.07);
      font-weight:900;
    }
    .yearTable tfoot td{
      font-weight:900;
      background:#f6fbf7;
    }

    /* ========================================
       ▼ 追加CSS①：出勤日確定パネル
       ======================================== */
    .workday-panel{ max-width:1200px;margin:0 auto;padding:0 16px 14px; }
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
      font-size:14px;font-family:inherit;color:var(--ink);background:#fff;
      outline:none;transition:border-color .2s, box-shadow .2s;
    }
    .workday-input-row input[type="date"]:focus{
      border-color:var(--accent);box-shadow:0 0 0 3px rgba(31,122,59,.15);
    }
    .btn-confirm-workday{
      background:var(--accent);color:#fff;border:none;border-radius:999px;
      padding:9px 20px;font-size:13px;font-weight:800;cursor:pointer;
      box-shadow:var(--shadow);transition:background .2s;
    }
    .btn-confirm-workday:hover{background:#166a30}
    .workday-list{
      display:flex;flex-wrap:wrap;gap:8px;
      max-height:200px;overflow-y:auto;padding:2px 0;
    }
    .workday-chip{
      display:inline-flex;align-items:center;gap:6px;
      background:var(--chip);border:1px solid var(--line);border-radius:999px;
      padding:7px 8px 7px 14px;transition:all .15s;cursor:default;
    }
    .workday-chip:hover{background:#d0eddb;border-color:var(--accent)}
    .workday-chip .chip-date{
      font-weight:900;font-size:14px;color:var(--ink);cursor:pointer;
    }
    .workday-chip .chip-date:hover{color:var(--accent);text-decoration:underline}
    .workday-chip .chip-wd{ font-size:11px;color:var(--muted);font-weight:600; }
    .workday-chip .chip-show{
      background:var(--accent);color:#fff;border:none;border-radius:999px;
      padding:4px 12px;font-size:11px;font-weight:800;cursor:pointer;
      transition:background .2s;
    }
    .workday-chip .chip-show:hover{background:#166a30}
    .workday-chip .chip-del{
      background:var(--dangerLight);color:var(--danger);border:1px solid #e8c4c0;
      border-radius:50%;width:22px;height:22px;font-size:12px;font-weight:900;
      cursor:pointer;display:flex;align-items:center;justify-content:center;
      transition:background .2s;padding:0;line-height:1;
    }
    .workday-chip .chip-del:hover{background:#f5c6c2}
    .workday-empty{ color:var(--muted);font-size:13px;font-style:italic;padding:4px 0; }

    /* ========================================
       ▼ 追加CSS②：フィルター表示バー
       ======================================== */
    .filter-bar{ max-width:1200px;margin:0 auto;padding:0 16px 10px;display:none; }
    .filter-bar.active{display:block}
    .filter-inner{
      background:linear-gradient(135deg,#d4edda 0%,#c3e6cb 100%);
      border:2px solid var(--accent);border-radius:var(--radius);
      padding:14px 20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;
      box-shadow:0 4px 16px rgba(31,122,59,.12);
    }
    .filter-inner .filter-icon{font-size:20px}
    .filter-inner .filter-label{ font-weight:900;font-size:14px;color:var(--accent); }
    .filter-inner .filter-date{ font-weight:900;font-size:17px;color:var(--ink); }
    .filter-inner .filter-note{ font-size:12px;color:var(--muted);font-weight:600; }
    .btn-back-normal{
      background:#fff;color:var(--accent);border:2px solid var(--accent);
      border-radius:999px;padding:8px 20px;font-size:13px;font-weight:800;
      cursor:pointer;margin-left:auto;transition:all .2s;
    }
    .btn-back-normal:hover{background:var(--accent);color:#fff}

    .weekend-warning{
      background:#fff8e1;border:2px solid #ffe082;border-radius:var(--radius);
      padding:18px 22px;text-align:center;font-size:14px;color:#795548;
      box-shadow:var(--shadow);
    }
    .weekend-warning .warn-icon{font-size:28px;display:block;margin-bottom:6px}
    .weekend-warning strong{color:#e65100}

    /* ========================================
       ▼ 指定日2カラムレイアウト（男女間隔を広げる）
       ======================================== */
    .twoCol{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap:22px;              /* ← 男女の間隔 */
      padding:14px 14px 0;   /* ← 余白も少し増やす */
    }
    .twoCol .col{ min-width:0; }
    .twoCol .col-label{
      padding:10px 14px 6px;
      font-size:12px;
      font-weight:900;
      color:var(--accent);
      background:#f6fbf7;
      border-bottom:1px solid var(--line);
      text-align:center;
      letter-spacing:.5px;
    }
    .twoCol .col table{ width:100%;border-collapse:collapse; }
    .twoCol .col table td{
      padding:8px 10px;
      border-bottom:1px solid var(--line);
      font-size:13px;
    }
    .twoCol .col table td:last-child{
      text-align:right;
      width:44px;
    }
    .twoCol .col table tr:last-child td{ border-bottom:none; }

    @media (max-width: 720px){
      .twoCol{ grid-template-columns: 1fr; }
      /* スマホではフィルター時のカードも幅100%にする */
      .cards .card[style]{
        min-width:100% !important;
        max-width:100% !important;
      }
    }

    /* ========================================
       ▼ 取扱説明書ボタン（既存）
       ======================================== */
    .btn-manual{
      background:linear-gradient(135deg,#2d8a4e 0%,#1f7a3b 100%);
      color:#fff;border:none;border-radius:999px;
      padding:10px 20px;font-size:13px;font-weight:800;cursor:pointer;
      box-shadow:var(--shadow);transition:all .25s;
      display:inline-flex;align-items:center;gap:6px;
    }
    .btn-manual:hover{background:linear-gradient(135deg,#1f7a3b 0%,#14612e 100%);transform:translateY(-1px)}
    .btn-manual::before{content:"📖";font-size:15px}
  </style>
</head>
<body>

<!-- ========================================
     右上ログアウト（アイコン付き / 確認ダイアログ）
     ======================================== -->
<div class="logout-fixed">
  <button type="button" id="logoutBtn" class="logout-btn" title="ログアウト">
    🔒 ログアウト
  </button>
</div>

<!-- ========================================
     ヘッダー
     ======================================== -->
<header>
  <h1>平日 出欠・人数集計（2026年）</h1>
  <div class="sub">月〜金のみ / 月切替 / 男性・女性・男女合計 / 名簿CSV差し替え / 出勤日確定・名簿表示 / 保存：この端末のブラウザ（localStorage）</div>
</header>

<!-- ========================================
     ツールバー（既存＋取扱説明書ボタン）
     ======================================== -->
<div class="toolbar">
  <div class="pill">
    <button id="prevBtn">◀ 前月</button>
    <select id="monthSel" aria-label="月"></select>
    <button id="nextBtn">次月 ▶</button>
  </div>

  <div class="pill">
    <div class="seg" role="tablist" aria-label="表示切替">
      <button class="active" data-view="male" type="button">男性</button>
      <button data-view="female" type="button">女性</button>
      <button data-view="both" type="button">男女合計</button>
    </div>
  </div>

  <div class="pill">
    <span class="small">男性CSV</span>
    <input id="maleCsv" type="file" accept=".csv,text/csv" />
    <span class="small">女性CSV</span>
    <input id="femaleCsv" type="file" accept=".csv,text/csv" />
  </div>

  <button class="btn-manual" id="manualBtn" title="取扱説明書を開く">取扱説明書</button>
</div>

<!-- ========================================
     出勤日確定パネル
     ======================================== -->
<div class="workday-panel">
  <div class="workday-box">
    <h2>出勤日</h2>
    <div class="workday-input-row">
      <input type="date" id="workdayDateInput" min="2026-01-01" max="2026-12-31" />
      <button class="btn-confirm-workday" id="confirmWorkdayBtn">日付を確定</button>
    </div>
    <div class="workday-list" id="workdayList">
      <div class="workday-empty">確定した出勤日はまだありません</div>
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
    <span class="filter-note" id="filterNote"></span>
    <button class="btn-back-normal" id="backNormalBtn">通常表示へ戻る</button>
  </div>
</div>

<!-- ========================================
     カード表示
     ======================================== -->
<div class="row">
  <div class="scrollerWrap" id="scrollerWrap">
    <button class="navBtn" id="scrollLeft">← 左へ</button>
    <button class="navBtn" id="scrollRight">右へ →</button>
    <div class="hint">カードを横スクロールできます</div>
  </div>
  <div class="cards" id="cards"></div>
</div>

<!-- ========================================
     アクションボタン
     ======================================== -->
<div class="actions">
  <button class="primary" id="exportCsvBtn">CSV出力（表示月の明細）</button>
</div>

<!-- ========================================
     集計エリア
     ======================================== -->
<div class="stats">
  <div class="stat">
    <div class="k">表示月</div>
    <div class="v" id="monthLabel">2026-01（平日のみ）</div>
    <div class="hint">※土日（Sat/Sun）は自動除外。祝日は区別しません。</div>
  </div>

  <div class="stat">
    <div class="k">月計（表示月の合計）</div>
    <div class="v" id="monthTotal">男性 0 / 女性 0 / 合計 0</div>
    <div class="hint">表示月の平日分の出席合計</div>
  </div>

  <div class="stat">
    <div class="k">年計（2026年の合計）</div>
    <div class="v" id="yearTotal">男性 0 / 女性 0 / 合計 0</div>
    <div class="hint">2026年（1〜12月）の平日分の出席合計</div>
  </div>

  <div class="stat" style="flex: 1 1 580px;">
    <div class="k">年内 月別集計（1〜12月）</div>
    <div class="hint">※行が薄緑の月＝現在表示中の月</div>
    <div class="yearTableWrap">
      <table class="yearTable" aria-label="年内月別集計">
        <thead>
          <tr>
            <th style="width:80px;">月</th>
            <th>男性</th>
            <th>女性</th>
            <th>合計</th>
          </tr>
        </thead>
        <tbody id="yearMonthsBody"></tbody>
        <tfoot>
          <tr>
            <td>年計</td>
            <td id="yearSumM">0</td>
            <td id="yearSumF">0</td>
            <td id="yearSumAll">0</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<!-- ========================================
     フッター
     ======================================== -->
<div class="foot">
  <div>・名簿CSVは <b>1行に1名</b>（例：佐藤太郎）でOK。読み込み後、カードが自動更新されます。</div>
  <div>・保存は端末ごと（ブラウザごと）です。共有したい場合は次に「サーバー保存版（DB/GAS）」に移行できます。</div>
</div>

<!-- ========================================
     JavaScript
     ======================================== -->
<script>
/*
 * =====================================================
 *  localStorage キー設計（コメント説明）
 * =====================================================
 */
const YEAR = 2026;
const LS_MEMBERS_M  = "wk2026_members_male_v2";
const LS_MEMBERS_F  = "wk2026_members_female_v2";
const LS_ATT_PREFIX = "wk2026_att_v2:";
const LS_WORKDAYS   = "wk2026_workdays";
const JP_WEEK = ["日","月","火","水","木","金","土"];

/* 初期名簿 */
const defaultMale = [
  "青木健太","石井直樹","上田翔太","大野和也","岡田拓海",
  "小川裕樹","河野智也","菊地大輔","黒田亮太","坂本誠",
  "杉山健一","高木直人","武田俊也","谷口祐介","永井健太郎",
  "野村拓真","原田直哉","藤原誠也","堀内祐樹","三浦隆志"
];
const defaultFemale = [
  "青木彩花","石井美咲","上田愛美","大野真由","岡田莉子",
  "小川優奈","河野結衣","菊地沙織","黒田菜々","坂本美優",
  "杉山由香","高木明日香","武田真理","谷口優衣","永井里奈",
  "野村彩乃","原田真帆","藤原美月","堀内香織","三浦遥"
];

/* ユーティリティ */
function pad2(n){ return String(n).padStart(2,"0"); }
function ymd(d){ return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`; }
function isWeekday(d){ const w=d.getDay(); return w>=1 && w<=5; }

function monthDays(year, month1to12){
  const start = new Date(year, month1to12-1, 1);
  const end   = new Date(year, month1to12, 0);
  const days  = [];
  for(let dt = new Date(start); dt <= end; dt.setDate(dt.getDate()+1)){
    const d = new Date(dt);
    if(isWeekday(d)) days.push(d);
  }
  return days;
}

/* localStorage helpers */
function lsGetJson(key, fallback){
  try{ const s=localStorage.getItem(key); return s ? JSON.parse(s) : fallback; }
  catch(e){ return fallback; }
}
function lsSetJson(key, val){ localStorage.setItem(key, JSON.stringify(val)); }

/* 名簿 */
function getMembers(gender){
  if(gender==="male") return lsGetJson(LS_MEMBERS_M, null) ?? defaultMale;
  return lsGetJson(LS_MEMBERS_F, null) ?? defaultFemale;
}
function setMembers(gender, arr){
  const cleaned = arr.map(s=>String(s).trim()).filter(Boolean);
  if(gender==="male") lsSetJson(LS_MEMBERS_M, cleaned);
  else lsSetJson(LS_MEMBERS_F, cleaned);
}

/* 出席 */
function attKey(dateStr, gender, name){ return `${LS_ATT_PREFIX}${dateStr}:${gender}:${name}`; }
function getPresent(dateStr, gender, name){ return localStorage.getItem(attKey(dateStr, gender, name)) === "1"; }
function setPresent(dateStr, gender, name, present){ localStorage.setItem(attKey(dateStr, gender, name), present ? "1" : "0"); }

/* 状態 */
let state = { month: 1, view: "male", filterDate: null };

/* DOM */
const monthSel      = document.getElementById("monthSel");
const prevBtn       = document.getElementById("prevBtn");
const nextBtn       = document.getElementById("nextBtn");
const monthLabel    = document.getElementById("monthLabel");
const monthTotal    = document.getElementById("monthTotal");
const yearTotal     = document.getElementById("yearTotal");
const cardsEl       = document.getElementById("cards");
const exportCsvBtn  = document.getElementById("exportCsvBtn");
const maleCsv       = document.getElementById("maleCsv");
const femaleCsv     = document.getElementById("femaleCsv");
const scrollLeftEl  = document.getElementById("scrollLeft");
const scrollRightEl = document.getElementById("scrollRight");
const scrollerWrap  = document.getElementById("scrollerWrap");
const yearMonthsBody= document.getElementById("yearMonthsBody");
const yearSumM      = document.getElementById("yearSumM");
const yearSumF      = document.getElementById("yearSumF");
const yearSumAll    = document.getElementById("yearSumAll");

const workdayDateInput  = document.getElementById("workdayDateInput");
const confirmWorkdayBtn = document.getElementById("confirmWorkdayBtn");
const workdayListEl     = document.getElementById("workdayList");
const filterBar         = document.getElementById("filterBar");
const filterDateLabel   = document.getElementById("filterDateLabel");
const filterNote        = document.getElementById("filterNote");
const backNormalBtn     = document.getElementById("backNormalBtn");
const manualBtn         = document.getElementById("manualBtn");

/* 月プル */
for(let m=1;m<=12;m++){
  const opt=document.createElement("option");
  opt.value=String(m);
  opt.textContent=`${YEAR}年 ${pad2(m)}月`;
  monthSel.appendChild(opt);
}
monthSel.value=String(state.month);

/* 月切替 */
prevBtn.addEventListener("click", ()=>{
  state.month=Math.max(1,state.month-1);
  monthSel.value=String(state.month);
  clearFilter();
  render();
});
nextBtn.addEventListener("click", ()=>{
  state.month=Math.min(12,state.month+1);
  monthSel.value=String(state.month);
  clearFilter();
  render();
});
monthSel.addEventListener("change", ()=>{
  state.month=Number(monthSel.value);
  clearFilter();
  render();
});

/* 男女切替 */
document.querySelectorAll(".seg button").forEach(btn=>{
  btn.addEventListener("click", ()=>{
    document.querySelectorAll(".seg button").forEach(b=>b.classList.remove("active"));
    btn.classList.add("active");
    state.view = btn.dataset.view;
    render();
  });
});

/* 横スクロール */
scrollLeftEl.addEventListener("click",  ()=> cardsEl.scrollBy({left:-900, behavior:"smooth"}));
scrollRightEl.addEventListener("click", ()=> cardsEl.scrollBy({left:900, behavior:"smooth"}));

/* CSV読み込み */
async function readTextFile(file){
  return new Promise((resolve,reject)=>{
    const fr=new FileReader();
    fr.onload=()=>resolve(fr.result);
    fr.onerror=()=>reject(fr.error);
    fr.readAsText(file,"utf-8");
  });
}
function parseCsvLines(text){
  return text.replace(/\r\n/g,"\n").replace(/\r/g,"\n").split("\n")
    .map(line => line.split(",")[0].trim())
    .filter(Boolean);
}
maleCsv.addEventListener("change", async ()=>{
  const f=maleCsv.files?.[0]; if(!f) return;
  setMembers("male", parseCsvLines(await readTextFile(f)));
  maleCsv.value="";
  render();
});
femaleCsv.addEventListener("change", async ()=>{
  const f=femaleCsv.files?.[0]; if(!f) return;
  setMembers("female", parseCsvLines(await readTextFile(f)));
  femaleCsv.value="";
  render();
});

/* CSV出力 */
function download(filename, content, mime="text/csv;charset=utf-8"){
  const blob=new Blob([content],{type:mime});
  const url=URL.createObjectURL(blob);
  const a=document.createElement("a");
  a.href=url; a.download=filename;
  document.body.appendChild(a); a.click(); a.remove();
  URL.revokeObjectURL(url);
}
function exportMonthCsv(){
  const days=monthDays(YEAR,state.month);
  const males=getMembers("male");
  const females=getMembers("female");
  const rows=[];
  rows.push(["date","weekday","gender","name","present"].join(","));
  for(const d of days){
    const ds=ymd(d), wd=JP_WEEK[d.getDay()];
    for(const name of males)   rows.push([ds,wd,"male",`"${name}"`,getPresent(ds,"male",name)?1:0].join(","));
    for(const name of females) rows.push([ds,wd,"female",`"${name}"`,getPresent(ds,"female",name)?1:0].join(","));
  }
  download(`weekday_attendance_${YEAR}-${pad2(state.month)}.csv`, rows.join("\n"));
}
exportCsvBtn.addEventListener("click", exportMonthCsv);


/* 集計 */
function calcDayTotal(dateStr, gender){
  const members=getMembers(gender);
  let c=0;
  for(const name of members) if(getPresent(dateStr, gender, name)) c++;
  return c;
}
function calcMonthTotals(month){
  const days=monthDays(YEAR, month);
  let m=0,f=0;
  for(const d of days){
    const ds=ymd(d);
    m+=calcDayTotal(ds,"male");
    f+=calcDayTotal(ds,"female");
  }
  return {m,f,all:m+f};
}
function calcYearTotals(){
  let m=0,f=0;
  for(let month=1; month<=12; month++){
    const mt = calcMonthTotals(month);
    m += mt.m;  f += mt.f;
  }
  return {m,f,all:m+f};
}
function calcYearMonthlyTotals(){
  const arr=[];
  for(let month=1; month<=12; month++){
    arr.push({month, ...calcMonthTotals(month)});
  }
  return arr;
}

/* 月別集計テーブル */
function renderYearMonthsTable(){
  const list = calcYearMonthlyTotals();
  yearMonthsBody.innerHTML = "";
  let sumM=0, sumF=0;
  for(const row of list){
    sumM += row.m; sumF += row.f;
    const tr = document.createElement("tr");
    if(row.month === state.month) tr.classList.add("activeRow");


    tr.innerHTML = `
      <td>${pad2(row.month)}月</td>
      <td>${row.m}</td>
      <td>${row.f}</td>
      <td>${row.all}</td>
    `;



    tr.style.cursor = "pointer";
    tr.title = "クリックでその月に切替";
    tr.addEventListener("click", ()=>{
      state.month = row.month;
      monthSel.value = String(state.month);
      clearFilter();
      render();
    });
    yearMonthsBody.appendChild(tr);
  }
  yearSumM.textContent   = String(sumM);
  yearSumF.textContent   = String(sumF);
  yearSumAll.textContent = String(sumM + sumF);
}
function renderTotalsOnly(){
  const mt = calcMonthTotals(state.month);
  monthTotal.textContent=`男性 ${mt.m} / 女性 ${mt.f} / 合計 ${mt.all}`;
  const yt = calcYearTotals();
  yearTotal.textContent=`男性 ${yt.m} / 女性 ${yt.f} / 合計 ${yt.all}`;
  renderYearMonthsTable();
}

/* カード生成 */
function buildCard(d, forceBoth){
  const ds     = ymd(d);
  const wd     = JP_WEEK[d.getDay()];
  const pretty = `${pad2(d.getMonth()+1)}/${pad2(d.getDate())}`;
  const sub    = `${YEAR}年 / ${wd}曜日（${ds}）`;
  const membersMale   = getMembers("male");
  const membersFemale = getMembers("female");

  const dm = calcDayTotal(ds,"male");
  const df = calcDayTotal(ds,"female");

  const showMode = forceBoth ? "both" : state.view;
  const badgeText =
    showMode==="male"   ? `男性 ${dm}` :
    showMode==="female" ? `女性 ${df}` :
    `男${dm} / 女${df} / 計${dm+df}`;

  const card = document.createElement("div");
  card.className = "card";
  card.dataset.date = ds;

  if(forceBoth){
    card.style.minWidth = "620px";
    card.style.maxWidth = "700px";
  }

  const head = document.createElement("div");
  head.className = "cardHead";
  head.innerHTML = `
    <div>
      <div class="dateBig">${pretty}</div>
      <div class="dateSub">${sub}</div>
    </div>
    <div class="badge">${badgeText}</div>
  `;
  card.appendChild(head);

  function buildRows(tbl, gender, names){
    const tbody = document.createElement("tbody");
    for(const name of names){
      const tr = document.createElement("tr");
      const checked = getPresent(ds, gender, name);
      tr.innerHTML = `
        <td>${name}</td>
        <td><input class="chk" type="checkbox" data-gender="${gender}" data-name="${name}" data-date="${ds}" ${checked?"checked":""} /></td>
      `;
      const chk = tr.querySelector("input");
      chk.addEventListener("change", ()=>{
        setPresent(ds, gender, name, chk.checked);
        queueSaveAttendanceToDB(ds);
        renderTotalsOnly();
        const newDm = calcDayTotal(ds,"male");
        const newDf = calcDayTotal(ds,"female");
        const newMode = forceBoth ? "both" : state.view;
        head.querySelector(".badge").textContent =
          newMode==="male"   ? `男性 ${newDm}` :
          newMode==="female" ? `女性 ${newDf}` :
          `男${newDm} / 女${newDf} / 計${newDm+newDf}`;
      });
      tbody.appendChild(tr);
    }
    tbl.appendChild(tbody);
  }

  if(forceBoth){
    const twoCol = document.createElement("div");
    twoCol.className = "twoCol";

    const colM = document.createElement("div");
    colM.className = "col";
    colM.innerHTML = `<div class="col-label">♂ 男性（${membersMale.length}名）</div>`;
    const tblM = document.createElement("table");
    buildRows(tblM, "male", membersMale);
    colM.appendChild(tblM);
    twoCol.appendChild(colM);

    const colF = document.createElement("div");
    colF.className = "col";
    colF.innerHTML = `<div class="col-label">♀ 女性（${membersFemale.length}名）</div>`;
    const tblF = document.createElement("table");
    buildRows(tblF, "female", membersFemale);
    colF.appendChild(tblF);
    twoCol.appendChild(colF);

    card.appendChild(twoCol);
  } else {
    const table = document.createElement("table");
    const thead = document.createElement("thead");
    const tbody = document.createElement("tbody");

    function addSection(title, gender, names){
      const trH = document.createElement("tr");
      trH.innerHTML = `<th colspan="2">${title}</th>`;
      thead.appendChild(trH);
      const trC = document.createElement("tr");
      trC.innerHTML = `<th>名前</th><th>出席</th>`;
      thead.appendChild(trC);
      for(const name of names){
        const tr = document.createElement("tr");
        const checked = getPresent(ds, gender, name);

       
        tr.innerHTML = `
          <td>${name}</td>
          <td><input class="chk" type="checkbox" data-gender="${gender}" data-name="${name}" data-date="${ds}" ${checked?"checked":""} /></td>
        `;
        const chk = tr.querySelector("input");
        chk.addEventListener("change", ()=>{
          setPresent(ds, gender, name, chk.checked);
        queueSaveAttendanceToDB(ds);
          renderTotalsOnly();
          const newDm = calcDayTotal(ds,"male");
          const newDf = calcDayTotal(ds,"female");
          head.querySelector(".badge").textContent =
            state.view==="male"   ? `男性 ${newDm}` :
            state.view==="female" ? `女性 ${newDf}` :
            `合計 ${newDm+newDf}`;
        });
        tbody.appendChild(tr);
      }
    }

    if(showMode==="male")        addSection("男性名簿", "male", membersMale);
    else if(showMode==="female") addSection("女性名簿", "female", membersFemale);
    else{
      addSection("男性名簿", "male", membersMale);
      addSection("女性名簿", "female", membersFemale);
    }

    table.appendChild(thead);
    table.appendChild(tbody);
    card.appendChild(table);
  }

  return card;
}

/* 描画 */
function render(){
  monthLabel.textContent = `${YEAR}-${pad2(state.month)}（平日のみ）`;
  cardsEl.innerHTML = "";

  if(state.filterDate){
    const targetDate  = new Date(state.filterDate);
    const targetMonth = targetDate.getMonth() + 1;

    if(targetMonth !== state.month){
      state.month = targetMonth;
      monthSel.value = String(state.month);
      monthLabel.textContent = `${YEAR}-${pad2(state.month)}（平日のみ）`;
    }

    scrollerWrap.style.display = "none";

    if(isWeekday(targetDate)){
      const card = buildCard(targetDate, true);
      cardsEl.appendChild(card);
    } else {
      const wd = JP_WEEK[targetDate.getDay()];
      cardsEl.innerHTML = `
        <div class="weekend-warning">
          <span class="warn-icon">⚠️</span>
          <strong>${state.filterDate}（${wd}曜日）は平日ではありません。</strong><br>
          この画面は平日（月〜金）の出欠管理用です。<br>
          「通常表示へ戻る」ボタンで月カード一覧に戻れます。
        </div>
      `;
    }
  } else {
    scrollerWrap.style.display = "flex";
    const days = monthDays(YEAR, state.month);
    for(const d of days){
      const card = buildCard(d, false);
      cardsEl.appendChild(card);
    }
  }

  renderTotalsOnly();
}

/* 礼拝日 */
function getWorkdays(){ return lsGetJson(LS_WORKDAYS, []); }
function saveWorkdays(arr){ arr.sort(); lsSetJson(LS_WORKDAYS, arr); }

function renderWorkdayList(){
  const dates = getWorkdays();
  workdayListEl.innerHTML = "";

  if(dates.length === 0){
    workdayListEl.innerHTML = `<div class="workday-empty">確定した出勤日はまだありません</div>`;
    return;
  }

  for(const dateStr of dates){
    const d    = new Date(dateStr);
    const m    = d.getMonth() + 1;
    const day  = d.getDate();
    const wd   = JP_WEEK[d.getDay()];
    const isWd = isWeekday(d);
    const shortLabel = `${m}/${day}`;

    const chip = document.createElement("div");
    chip.className = "workday-chip";
    if(!isWd) chip.style.opacity = "0.55";

    chip.innerHTML = `
      <span class="chip-date" title="${dateStr}（クリックで表示）">${shortLabel}</span>
      <span class="chip-wd">${wd}${isWd ? "" : " 休"}</span>
      <button class="chip-show" title="${dateStr} の名簿を表示">表示</button>
      <button class="chip-del" title="削除">×</button>
    `;

    chip.querySelector(".chip-date").addEventListener("click", ()=> showDateFilter(dateStr));
    chip.querySelector(".chip-show").addEventListener("click", ()=> showDateFilter(dateStr));
    chip.querySelector(".chip-del").addEventListener("click", ()=> removeWorkday(dateStr));

    workdayListEl.appendChild(chip);
  }
}

function confirmWorkday(){
  const val = workdayDateInput.value;
  if(!val){ alert("日付を選択してください。"); return; }

  const dates = getWorkdays();
  if(dates.includes(val)){
    const d = new Date(val);
    alert(`${d.getMonth()+1}/${d.getDate()}（${val}）は既に確定されています。`);
    return;
  }

  dates.push(val);
  saveWorkdays(dates);
  workdayDateInput.value = "";
  renderWorkdayList();
}

function removeWorkday(dateStr){
  const d = new Date(dateStr);
  const shortLabel = `${d.getMonth()+1}/${d.getDate()}`;
  if(!confirm(`礼拝日 ${shortLabel}（${dateStr}）を削除しますか？`)) return;

  let dates = getWorkdays();
  dates = dates.filter(x => x !== dateStr);
  saveWorkdays(dates);

  if(state.filterDate === dateStr){ clearFilter(); }

  renderWorkdayList();
  render();
}

/* フィルター */
function showDateFilter(dateStr){
  state.filterDate = dateStr;
  window.selectedDateISO = dateStr;

  const d    = new Date(dateStr);
  const m    = d.getMonth() + 1;
  const day  = d.getDate();
  const wd   = JP_WEEK[d.getDay()];
  const isWd = isWeekday(d);

  filterDateLabel.textContent = `${dateStr}（${m}/${day} ${wd}曜日）を表示中`;
  filterNote.textContent = isWd
    ? "男性・女性 両方の名簿を表示 / チェックの入れ直しが可能です"
    : "⚠️ この日は平日ではありません";

  filterBar.classList.add("active");
  state.month = m;
  monthSel.value = String(m);

  render();
  setTimeout(()=>loadAttendanceFromDB(dateStr), 0);
  window.scrollTo({top: cardsEl.offsetTop - 100, behavior: "smooth"});
}
function clearFilter(){
  state.filterDate = null;
  filterBar.classList.remove("active");
}

/* イベント */
confirmWorkdayBtn.addEventListener("click", confirmWorkday);
workdayDateInput.addEventListener("keydown", (e)=>{ if(e.key === "Enter") confirmWorkday(); });
backNormalBtn.addEventListener("click", ()=>{ clearFilter(); render(); });

manualBtn.addEventListener("click", ()=>{ window.open("manual.html", "_blank"); });

/* ========================================
   右上ログアウト：保存してログアウト確認
   ※このアプリはチェック変更時に localStorage へ即保存されます
   ======================================== */
(function(){
  const btn = document.getElementById("logoutBtn");
  if(!btn) return;
  btn.addEventListener("click", ()=>{
    const ok = confirm(
      "保存してログアウトしますか？\n\n" +
      "※チェック内容はこの端末に保存されています。\n" +
      "※OKを押すとログイン画面に戻ります。"
    );
    if(ok) window.location.href = "logout.php";
  });
})();

/* 初期化 */
renderWorkdayList();
render();


/* ========= DB化：ここから追加 ========= */

// 選択中日付（YYYY-MM-DD）を取得：フィルター表示時に showDateFilter() がセットします
function getSelectedDateISO(){
  return window.selectedDateISO || (state && state.filterDate) || "";
}
function getSelectedYear(dateStr){
  const d = dateStr || getSelectedDateISO();
  if(!d) return new Date().getFullYear();
  return parseInt(d.slice(0,4),10);
}

// 指定日のチェックボックスだけ集める（data-date を使う）
function getCheckboxesByDate(dateStr){
  return Array.from(document.querySelectorAll(`input[type="checkbox"][data-gender][data-name][data-date="${dateStr}"]`));
}

// DB読込（指定日）
async function loadAttendanceFromDB(dateStr){
  const date = dateStr || getSelectedDateISO();
  if(!date) return;

  const year = getSelectedYear(date);
  const url = `api_attendance_get.php?year=${encodeURIComponent(year)}&date=${encodeURIComponent(date)}`;

  const res = await fetch(url, {cache:'no-store'});
  const json = await res.json();
  if(!json.ok){
    alert("出欠読込エラー: " + (json.error || "unknown"));
    return;
  }

  const map = json.map || {};
  const cbs = getCheckboxesByDate(date);
  for(const cb of cbs){
    const k = cb.dataset.gender + "|" + cb.dataset.name;
    cb.checked = (map[k] === 1);
  }
}

// DB保存（指定日）— 成功時は通知しない（既存画面を崩さない）
async function saveAttendanceToDB(dateStr){
  const date = dateStr || getSelectedDateISO();
  if(!date) return;

  const year = getSelectedYear(date);
  const cbs  = getCheckboxesByDate(date);

  const items = cbs.map(cb => ({
    gender: cb.dataset.gender,
    name: cb.dataset.name,
    present: cb.checked ? 1 : 0
  }));

  const res = await fetch('api_attendance_save.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({year, date, items})
  });
  const json = await res.json();
  if(!json.ok){
    alert("保存エラー: " + (json.error || "unknown"));
    return;
  }
}

// 連打対策：チェック変更が続いたら少し待ってから保存（1日ごとにタイマー管理）
const _saveTimers = new Map();
function queueSaveAttendanceToDB(dateStr){
  const date = dateStr || getSelectedDateISO();
  if(!date) return;

  if(_saveTimers.has(date)) clearTimeout(_saveTimers.get(date));
  const t = setTimeout(() => {
    _saveTimers.delete(date);
    saveAttendanceToDB(date);
  }, 600);
  _saveTimers.set(date, t);
}

/* ========= DB化：ここまで追加 ========= */

</script>
</body>
</html>
