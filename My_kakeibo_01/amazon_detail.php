<?php
// =====================================================
//  amazon_detail.php  ― Amazon分明細ワークシート
// =====================================================
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Amazon分明細</title>
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: "Yu Gothic", "Meiryo", sans-serif;
      background: linear-gradient(135deg, #fff7ed 0%, #fef3c7 50%, #fde68a 100%);
      background-attachment: fixed;
      margin: 0;
      padding: 20px;
      color: #1e293b;
      min-height: 100vh;
    }

    /* ─── ヘッダーバー ─── */
    .page-header {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .page-header h1 {
      margin: 0;
      font-size: 1.6rem;
      background: linear-gradient(90deg, #d97706, #b45309);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .amazon-badge {
      display: inline-block;
      background: linear-gradient(135deg, #d97706, #b45309);
      color: #fff;
      font-size: 13px;
      font-weight: bold;
      padding: 2px 10px;
      border-radius: 20px;
      letter-spacing: 0.05em;
      -webkit-text-fill-color: #fff;
    }

    /* ─── ボタン ─── */
    button {
      cursor: pointer;
      font-weight: bold;
      border: none;
      border-radius: 10px;
      padding: 9px 18px;
      font-size: 15px;
      transition: opacity 0.2s, transform 0.1s;
      white-space: nowrap;
    }
    button:hover { opacity: 0.85; transform: translateY(-1px); }
    button:active { transform: translateY(0); }
    .btn-back   { background: linear-gradient(135deg, #64748b, #475569); color: #fff; }
    .btn-csv    { background: linear-gradient(135deg, #0ea5e9, #0284c7); color: #fff; }
    .btn-print  { background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; }
    .btn-reload { background: linear-gradient(135deg, #059669, #047857); color: #fff; }

    /* ─── フィルターバー ─── */
    .filter-bar {
      background: #fff;
      border-radius: 14px;
      padding: 16px 20px;
      margin-bottom: 16px;
      display: flex;
      gap: 16px;
      align-items: end;
      flex-wrap: wrap;
      box-shadow: 0 2px 10px rgba(0,0,0,0.07);
      border-left: 5px solid #d97706;
    }
    .filter-bar label {
      font-weight: bold;
      font-size: 13px;
      color: #374151;
      display: block;
      margin-bottom: 4px;
    }
    .filter-bar input, .filter-bar select {
      padding: 8px 10px;
      border: 1.5px solid #d1d5db;
      border-radius: 8px;
      font-size: 14px;
      background: #fff;
    }
    .filter-bar input:focus, .filter-bar select:focus {
      outline: none;
      border-color: #d97706;
      box-shadow: 0 0 0 3px rgba(217,119,6,0.15);
    }

    /* ─── サマリーカード ─── */
    .summary-cards {
      display: flex;
      gap: 14px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }
    .summary-card {
      background: #fff;
      border-radius: 14px;
      padding: 16px 22px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.07);
      min-width: 170px;
      flex: 1;
    }
    .summary-card:nth-child(1) { border-top: 4px solid #ef4444; }
    .summary-card:nth-child(2) { border-top: 4px solid #d97706; }
    .summary-card:nth-child(3) { border-top: 4px solid #10b981; }
    .summary-label { font-size: 12px; color: #6b7280; font-weight: bold; margin-bottom: 6px; }
    .summary-value { font-size: 26px; font-weight: bold; color: #1e293b; }
    .summary-sub   { font-size: 12px; color: #9ca3af; margin-top: 3px; }

    /* ─── ワークシートテーブル ─── */
    .worksheet-wrap {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .worksheet-title {
      background: linear-gradient(135deg, #d97706, #b45309);
      color: #fff;
      padding: 14px 22px;
      font-size: 1.05rem;
      font-weight: bold;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .worksheet-title .record-count {
      font-size: 13px;
      opacity: 0.85;
    }

    .table-scroll { overflow-x: auto; }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }
    thead th {
      background: linear-gradient(135deg, #92400e, #78350f);
      color: #fff;
      padding: 11px 10px;
      text-align: center;
      font-weight: bold;
      white-space: nowrap;
      border-right: 1px solid rgba(255,255,255,0.15);
      position: sticky;
      top: 0;
      z-index: 10;
    }
    thead th:last-child { border-right: none; }

    /* 列幅設定 */
    .col-no       { width: 46px;  min-width: 46px; }
    .col-date     { width: 100px; min-width: 90px; }
    .col-shop     { width: 130px; min-width: 110px; }
    .col-category { width: 120px; min-width: 100px; }
    .col-content  { min-width: 160px; }
    .col-withdraw { width: 110px; min-width: 100px; }
    .col-deposit  { width: 100px; min-width: 90px; }
    .col-charge   { width: 100px; min-width: 90px; }

    tbody tr {
      border-bottom: 1px solid #fde68a;
      transition: background 0.15s;
    }
    tbody tr:nth-child(even) { background: #fffbeb; }
    tbody tr:hover           { background: #fef3c7; }

    td {
      padding: 9px 10px;
      vertical-align: middle;
    }
    .td-no       { text-align: center; color: #9ca3af; font-size: 12px; }
    .td-date     { text-align: center; white-space: nowrap; color: #374151; }
    .td-shop     { text-align: left; font-weight: bold; color: #b45309; }
    .td-category { text-align: center; }
    .td-content  { color: #374151; }
    .td-amount   { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .withdraw-val { color: #b91c1c; font-weight: bold; }
    .deposit-val  { color: #059669; font-weight: bold; }
    .charge-val   { color: #7c3aed; font-weight: bold; }
    .zero-val     { color: #d1d5db; font-size: 13px; }

    /* カテゴリバッジ */
    .cat-badge {
      display: inline-block;
      background: #fef3c7;
      color: #92400e;
      border: 1px solid #fcd34d;
      border-radius: 12px;
      padding: 2px 10px;
      font-size: 12px;
      font-weight: bold;
      white-space: nowrap;
    }

    /* 合計行 */
    tfoot tr { background: #fff7ed !important; }
    tfoot td {
      padding: 11px 10px;
      font-weight: bold;
      border-top: 2px solid #d97706;
    }
    .total-label { text-align: right; color: #374151; font-size: 14px; }
    .total-withdraw { text-align: right; color: #b91c1c; font-size: 16px; }
    .total-deposit  { text-align: right; color: #059669; font-size: 16px; }
    .total-charge   { text-align: right; color: #7c3aed; font-size: 16px; }

    /* 空データ */
    .empty-row td {
      text-align: center;
      color: #9ca3af;
      padding: 40px;
      font-size: 15px;
    }

    /* ローディング */
    .loading {
      text-align: center;
      padding: 40px;
      color: #d97706;
      font-size: 15px;
    }

    /* ─── 印刷 ─── */
    @media print {
      body { background: #fff; padding: 0; }
      .page-header button, .filter-bar, .btn-csv, .btn-print, .btn-reload { display: none; }
      .summary-cards { gap: 8px; }
      .worksheet-wrap { box-shadow: none; border: 1px solid #ccc; }
      tfoot tr { background: #f3f4f6 !important; }
    }

    /* ─── レスポンシブ ─── */
    @media (max-width: 700px) {
      body { padding: 12px; }
      .summary-card { min-width: 130px; }
      .summary-value { font-size: 20px; }
    }
  </style>
</head>
<body>

<!-- ページヘッダー -->
<div class="page-header">
  <h1>
    <span>📦</span>
    Amazon分明細
    <span class="amazon-badge">AMAZON</span>
  </h1>
  <button class="btn-back" onclick="location.href='index.php'">← 家計簿に戻る</button>
  <button class="btn-reload" id="reloadBtn">↺ 再読み込み</button>
  <button class="btn-csv" id="csvBtn">CSV出力</button>
  <button class="btn-print" onclick="window.print()">🖨 印刷</button>
</div>

<!-- フィルターバー -->
<div class="filter-bar">
  <div>
    <label for="filterMonth">絞り込み（月）</label>
    <input type="month" id="filterMonth" />
  </div>
  <div>
    <label for="filterCategory">分類</label>
    <select id="filterCategory">
      <option value="">すべて</option>
    </select>
  </div>
  <div>
    <label for="filterKeyword">キーワード（内容等）</label>
    <input type="text" id="filterKeyword" placeholder="例: ケーブル、本 など" style="min-width:180px;" />
  </div>
  <div>
    <label for="sortOrder">並び順</label>
    <select id="sortOrder">
      <option value="date_asc">日付 昇順</option>
      <option value="date_desc">日付 降順</option>
      <option value="amount_desc">金額 大きい順</option>
    </select>
  </div>
</div>

<!-- サマリーカード -->
<div class="summary-cards">
  <div class="summary-card">
    <div class="summary-label">出金合計</div>
    <div class="summary-value" id="sumWithdraw">－</div>
    <div class="summary-sub" id="subWithdraw">対象件数: 0件</div>
  </div>
  <div class="summary-card">
    <div class="summary-label">入金合計</div>
    <div class="summary-value" id="sumDeposit">－</div>
    <div class="summary-sub" id="subDeposit">返金など</div>
  </div>
  <div class="summary-card">
    <div class="summary-label">チャージ合計</div>
    <div class="summary-value" id="sumCharge">－</div>
    <div class="summary-sub">Amazonギフト券等</div>
  </div>
</div>

<!-- ワークシート -->
<div class="worksheet-wrap">
  <div class="worksheet-title">
    <span>📋 Amazon購入一覧</span>
    <span class="record-count" id="recordCount">読み込み中…</span>
  </div>
  <div class="table-scroll">
    <table id="amazonTable">
      <thead>
        <tr>
          <th class="col-no">No.</th>
          <th class="col-date">日付</th>
          <th class="col-shop">購入先分類</th>
          <th class="col-category">分類</th>
          <th class="col-content">内容等</th>
          <th class="col-withdraw">出金額</th>
          <th class="col-deposit">入金額</th>
          <th class="col-charge">チャージ</th>
        </tr>
      </thead>
      <tbody id="amazonBody">
        <tr><td colspan="8" class="loading">読み込み中…</td></tr>
      </tbody>
      <tfoot id="amazonFoot"></tfoot>
    </table>
  </div>
</div>

<script>
// =====================================================
//  定数・ユーティリティ
// =====================================================
const API = 'api.php';

function fmt(v) {
  const n = Number(v || 0);
  return n === 0 ? '' : '¥' + n.toLocaleString('ja-JP');
}
function fmtSum(v) {
  return '¥' + Number(v || 0).toLocaleString('ja-JP');
}
function esc(s) {
  return String(s || '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// =====================================================
//  データ取得
// =====================================================
let allEntries = [];

async function loadData() {
  const res = await fetch(`${API}?action=entries`);
  const data = await res.json();
  allEntries = (data.entries || []).filter(e =>
    (e.shop || '').toLowerCase().includes('amazon')
  );
  populateCategoryFilter();
  render();
}

function populateCategoryFilter() {
  const sel = document.getElementById('filterCategory');
  const current = sel.value;
  const cats = [...new Set(allEntries.map(e => e.category).filter(Boolean))].sort((a,b) => a.localeCompare(b,'ja'));
  sel.innerHTML = '<option value="">すべて</option>';
  cats.forEach(c => {
    const opt = document.createElement('option');
    opt.value = opt.textContent = c;
    sel.appendChild(opt);
  });
  if (cats.includes(current)) sel.value = current;
}

// =====================================================
//  フィルター＆ソート＆描画
// =====================================================
function getFiltered() {
  const month    = document.getElementById('filterMonth').value;
  const category = document.getElementById('filterCategory').value;
  const keyword  = document.getElementById('filterKeyword').value.trim().toLowerCase();
  const sortKey  = document.getElementById('sortOrder').value;

  let rows = [...allEntries];

  if (month)    rows = rows.filter(e => e.date && e.date.startsWith(month));
  if (category) rows = rows.filter(e => e.category === category);
  if (keyword)  rows = rows.filter(e =>
    (e.content || '').toLowerCase().includes(keyword) ||
    (e.detail  || '').toLowerCase().includes(keyword)
  );

  rows.sort((a, b) => {
    if (sortKey === 'date_desc')    return b.date.localeCompare(a.date);
    if (sortKey === 'amount_desc')  return Number(b.withdrawal||0) - Number(a.withdrawal||0);
    return a.date.localeCompare(b.date); // date_asc
  });

  return rows;
}

function render() {
  const rows = getFiltered();
  const tbody = document.getElementById('amazonBody');
  const tfoot = document.getElementById('amazonFoot');

  // サマリー集計
  const totalW = rows.reduce((s, e) => s + Number(e.withdrawal||0), 0);
  const totalD = rows.reduce((s, e) => s + Number(e.deposit||0),    0);
  const totalC = rows.reduce((s, e) => s + Number(e.charge||0),     0);

  document.getElementById('sumWithdraw').textContent = fmtSum(totalW);
  document.getElementById('sumDeposit').textContent  = fmtSum(totalD);
  document.getElementById('sumCharge').textContent   = fmtSum(totalC);
  document.getElementById('subWithdraw').textContent = `対象件数: ${rows.length}件`;
  document.getElementById('recordCount').textContent = `${rows.length} 件`;

  // テーブル本体
  tbody.innerHTML = '';
  if (rows.length === 0) {
    tbody.innerHTML = `<tr class="empty-row"><td colspan="8">📭 該当するAmazon購入データはありません</td></tr>`;
    tfoot.innerHTML = '';
    return;
  }

  rows.forEach((e, i) => {
    const tr = document.createElement('tr');
    const wVal = Number(e.withdrawal||0);
    const dVal = Number(e.deposit||0);
    const cVal = Number(e.charge||0);
    tr.innerHTML = `
      <td class="td-no">${i + 1}</td>
      <td class="td-date">${esc(e.date)}</td>
      <td class="td-shop">${esc(e.shop)}</td>
      <td class="td-category"><span class="cat-badge">${esc(e.category)}</span></td>
      <td class="td-content">
        ${esc(e.content)}
        ${e.detail ? `<br><span style="font-size:12px;color:#9ca3af;">📝 ${esc(e.detail)}</span>` : ''}
      </td>
      <td class="td-amount">
        ${wVal ? `<span class="withdraw-val">¥${wVal.toLocaleString('ja-JP')}</span>` : '<span class="zero-val">－</span>'}
      </td>
      <td class="td-amount">
        ${dVal ? `<span class="deposit-val">¥${dVal.toLocaleString('ja-JP')}</span>` : '<span class="zero-val">－</span>'}
      </td>
      <td class="td-amount">
        ${cVal ? `<span class="charge-val">¥${cVal.toLocaleString('ja-JP')}</span>` : '<span class="zero-val">－</span>'}
      </td>
    `;
    tbody.appendChild(tr);
  });

  // 合計行
  tfoot.innerHTML = `
    <tr>
      <td colspan="5" class="total-label">合　計（${rows.length}件）</td>
      <td class="total-withdraw">¥${totalW.toLocaleString('ja-JP')}</td>
      <td class="total-deposit">¥${totalD.toLocaleString('ja-JP')}</td>
      <td class="total-charge">¥${totalC.toLocaleString('ja-JP')}</td>
    </tr>
  `;
}

// =====================================================
//  CSV出力
// =====================================================
document.getElementById('csvBtn').addEventListener('click', () => {
  const rows = getFiltered();
  if (rows.length === 0) { alert('出力するデータがありません'); return; }

  const month = document.getElementById('filterMonth').value || 'all';
  const header = ['No.','日付','購入先分類','分類','内容等','メモ','出金額','入金額','チャージ'];
  const data = rows.map((e, i) => [
    i+1, e.date, e.shop, e.category,
    e.content, e.detail,
    e.withdrawal, e.deposit, e.charge
  ]);

  const totalW = rows.reduce((s, e) => s + Number(e.withdrawal||0), 0);
  const totalD = rows.reduce((s, e) => s + Number(e.deposit||0),    0);
  const totalC = rows.reduce((s, e) => s + Number(e.charge||0),     0);
  data.push(['', '', '', '', `合計（${rows.length}件）`, '', totalW, totalD, totalC]);

  const csv = [header, ...data]
    .map(row => row.map(v => `"${String(v??'').replace(/"/g,'""')}"`).join(','))
    .join('\n');

  const bom  = new Uint8Array([0xEF, 0xBB, 0xBF]);
  const blob = new Blob([bom, csv], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = `Amazon明細_${month}.csv`;
  a.click();
  URL.revokeObjectURL(url);
});

// =====================================================
//  イベント
// =====================================================
document.getElementById('reloadBtn').addEventListener('click', loadData);
document.getElementById('filterMonth').addEventListener('change', render);
document.getElementById('filterCategory').addEventListener('change', render);
document.getElementById('filterKeyword').addEventListener('input', render);
document.getElementById('sortOrder').addEventListener('change', render);

// =====================================================
//  初期化
// =====================================================
(function init() {
  // 今月を初期セット
  const d = new Date();
  document.getElementById('filterMonth').value =
    d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0');
  loadData();
})();
</script>
</body>
</html>
