<?php
// =====================================================
//  index.php  ― かんたん家計簿（PHP + MySQL版）
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
  <title>かんたん家計簿入力</title>
  <style>
    body {
      font-family: "Yu Gothic", "Meiryo", sans-serif;
      background: linear-gradient(135deg, #e0f2fe 0%, #faf5ff 50%, #fef9c3 100%);
      background-attachment: fixed;
      margin: 0;
      padding: 24px;
      color: #1e293b;
      min-height: 100vh;
    }
    .wrap { max-width: 1180px; margin: 0 auto; }

    /* ─── カード（セクションごとに色分け） ─── */
    .card {
      border-radius: 18px;
      box-shadow: 0 6px 24px rgba(0,0,0,0.09);
      padding: 24px;
      margin-bottom: 22px;
      border-top: 5px solid transparent;
    }
    .card:nth-child(1) { background: #fff7ed; border-top-color: #f97316; } /* 入力フォーム：オレンジ */
    .card:nth-child(2) { background: #f0fdf4; border-top-color: #22c55e; } /* 入力一覧：グリーン */
    .card:nth-child(3) { background: #fdf4ff; border-top-color: #a855f7; } /* 管理：パープル */
    .card:nth-child(4) { background: #eff6ff; border-top-color: #3b82f6; } /* 月別集計：ブルー */
    .card:nth-child(5) { background: #fff1f2; border-top-color: #f43f5e; } /* 全体集計：ピンク */

    /* ─── 見出し ─── */
    h1 {
      margin-top: 0;
      background: linear-gradient(90deg, #f97316, #f59e0b);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      font-size: 1.7rem;
    }
    h2 { margin-top: 0; color: #374151; font-size: 1.2rem; border-left: 4px solid #a855f7; padding-left: 10px; }
    .card:nth-child(2) h2 { border-left-color: #22c55e; }
    .card:nth-child(4) h2 { border-left-color: #3b82f6; }
    .card:nth-child(5) h2 { border-left-color: #f43f5e; }

    .desc { color: #64748b; margin-bottom: 18px; line-height: 1.8; }

    /* ─── グリッド ─── */
    .grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(260px, 1fr));
      gap: 16px;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(180px, 1fr));
      gap: 14px;
      margin-top: 14px;
    }

    /* ─── 統計ボックス（月別） ─── */
    .card:nth-child(4) .stat-box:nth-child(1) { background: linear-gradient(135deg, #dbeafe, #bfdbfe); border-color: #93c5fd; }
    .card:nth-child(4) .stat-box:nth-child(2) { background: linear-gradient(135deg, #d1fae5, #a7f3d0); border-color: #6ee7b7; }
    .card:nth-child(4) .stat-box:nth-child(3) { background: linear-gradient(135deg, #fce7f3, #fbcfe8); border-color: #f9a8d4; }
    .card:nth-child(4) .stat-box:nth-child(4) { background: linear-gradient(135deg, #fef9c3, #fde68a); border-color: #fcd34d; }

    /* ─── 統計ボックス（全体） ─── */
    .card:nth-child(5) .stat-box:nth-child(1) { background: linear-gradient(135deg, #fee2e2, #fecaca); border-color: #fca5a5; }
    .card:nth-child(5) .stat-box:nth-child(2) { background: linear-gradient(135deg, #ede9fe, #ddd6fe); border-color: #c4b5fd; }
    .card:nth-child(5) .stat-box:nth-child(3) { background: linear-gradient(135deg, #dcfce7, #bbf7d0); border-color: #86efac; }
    .card:nth-child(5) .stat-box:nth-child(4) { background: linear-gradient(135deg, #ffedd5, #fed7aa); border-color: #fdba74; }

    .stat-box {
      border: 1px solid #e5e7eb;
      border-radius: 14px;
      padding: 16px;
    }
    .stat-label { font-size: 13px; color: #555; margin-bottom: 8px; font-weight: bold; }
    .stat-value { font-size: 24px; font-weight: bold; color: #1e293b; }
    .full { grid-column: 1 / -1; }

    /* ─── フォームコントロール ─── */
    label { display: block; font-weight: bold; margin-bottom: 6px; color: #374151; }
    input, select, textarea {
      width: 100%;
      box-sizing: border-box;
      padding: 10px 12px;
      border: 1.5px solid #d1d5db;
      border-radius: 10px;
      font-size: 16px;
      background: #fff;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: #f97316;
      box-shadow: 0 0 0 3px rgba(249,115,22,0.15);
    }
    textarea { min-height: 90px; resize: vertical; }

    /* ─── ボタン ─── */
    button {
      cursor: pointer;
      font-weight: bold;
      width: auto;
      min-width: 140px;
      border: none;
      border-radius: 10px;
      padding: 10px 12px;
      box-sizing: border-box;
      font-size: 16px;
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      color: #fff;
      transition: opacity 0.2s, transform 0.1s;
    }
    button:hover:not(:disabled) { opacity: 0.88; transform: translateY(-1px); }
    button:active:not(:disabled) { transform: translateY(0); }
    button.secondary { background: linear-gradient(135deg, #64748b, #475569); }
    button.amazon    { background: linear-gradient(135deg, #d97706, #b45309); }
    button.danger    { background: linear-gradient(135deg, #ef4444, #dc2626); min-width: 70px; padding: 8px 10px; }
    button.backup    { background: linear-gradient(135deg, #059669, #047857); }
    button:disabled  { opacity: 0.5; cursor: not-allowed; }
    #submitBtn       { background: linear-gradient(135deg, #f97316, #ea580c); }
    #csvBtn          { background: linear-gradient(135deg, #0ea5e9, #0284c7); }

    .row-buttons {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 20px;
    }
    .inline-add { display: flex; gap: 8px; }
    .inline-add input { flex: 1; }
    .inline-add button { min-width: 100px; }

    /* ─── テーブルラッパー（Excel 画面分割） ─── */
    .table-freeze-wrap {
      overflow: auto;
      max-height: 480px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      margin-top: 10px;
      position: relative;
    }

    /* ─── テーブル ─── */
    table {
      width: 100%;
      border-collapse: separate;   /* sticky に必須 */
      border-spacing: 0;
      background: #fff;
      min-width: 860px;
    }
    th, td {
      border-right:  1px solid #e5e7eb;
      border-bottom: 1px solid #e5e7eb;
      padding: 10px;
      text-align: left;
      font-size: 14px;
      vertical-align: top;
    }
    th:first-child, td:first-child { border-left: 1px solid #e5e7eb; }
    thead th { border-top: 1px solid transparent; }

    /* ─── ヘッダー行固定（縦スクロール時） ─── */
    thead th {
      background: linear-gradient(135deg, #22c55e, #16a34a);
      color: #fff;
      font-weight: bold;
      position: sticky;
      top: 0;
      z-index: 2;
      white-space: nowrap;
    }

    /* ─── 列幅定義 ─── */
    table colgroup col:nth-child(1) { width:  96px; }   /* 日付   */
    table colgroup col:nth-child(2) { width:  90px; }   /* 分類   */
    table colgroup col:nth-child(3) { width: 110px; }   /* 購入先 */

    /* ─── 固定列（横スクロール時）─── */
    th:nth-child(1), td:nth-child(1) {
      position: sticky; left: 0;
      z-index: 1; min-width: 96px;
    }
    th:nth-child(2), td:nth-child(2) {
      position: sticky; left: 96px;
      z-index: 1; min-width: 90px;
    }
    th:nth-child(3), td:nth-child(3) {
      position: sticky; left: 186px;
      z-index: 1; min-width: 110px;
      /* 固定列の右端に区切り線（影） */
      box-shadow: 3px 0 6px -2px rgba(0,0,0,0.18);
    }
    /* ヘッダーコーナー（縦横両方 sticky の交点）*/
    thead th:nth-child(1) { z-index: 3; left:   0; }
    thead th:nth-child(2) { z-index: 3; left:  96px; }
    thead th:nth-child(3) { z-index: 3; left: 186px; }

    /* ─── 固定列の背景（行の縞模様・ホバーに追従） ─── */
    tbody td:nth-child(1),
    tbody td:nth-child(2),
    tbody td:nth-child(3) { background: #fff; }

    tbody tr:nth-child(even) td:nth-child(1),
    tbody tr:nth-child(even) td:nth-child(2),
    tbody tr:nth-child(even) td:nth-child(3) { background: #f0fdf4; }

    tbody tr:hover td:nth-child(1),
    tbody tr:hover td:nth-child(2),
    tbody tr:hover td:nth-child(3) { background: #dcfce7; }

    tbody tr:nth-child(even) { background: #f0fdf4; }
    tbody tr:hover { background: #dcfce7; }

    .small { font-size: 13px; color: #6b7280; }
    .amount { text-align: right; white-space: nowrap; }
    .toolbar {
      display: flex;
      gap: 12px;
      align-items: end;
      flex-wrap: wrap;
      margin-top: 10px;
    }
    .toolbar > div { min-width: 220px; }

    /* ─── トースト通知 ─── */
    #toast {
      position: fixed;
      bottom: 30px;
      right: 30px;
      background: linear-gradient(135deg, #1e3a5f, #1e40af);
      color: #fff;
      padding: 14px 22px;
      border-radius: 14px;
      font-size: 15px;
      opacity: 0;
      transition: opacity 0.3s;
      pointer-events: none;
      z-index: 9999;
      box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    }
    #toast.show { opacity: 1; }
    #toast.error { background: linear-gradient(135deg, #b91c1c, #991b1b); }

    /* ─── Amazon行の強調 ─── */
    .amazon-row td { color: #c0392b; font-weight: bold; }

    /* ─── 入力フォーム 左右分割レイアウト ─── */
    .form-split {
      display: flex;
      gap: 20px;
      align-items: stretch;
    }
    .form-left {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      flex: 0 0 54%;
    }
    .form-right {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .form-right #content {
      flex: 1;
      min-height: 110px;
      font-size: 16px;
      padding: 12px 14px;
      border: 1.5px solid #d1d5db;
      border-radius: 10px;
      background: #fff;
      box-sizing: border-box;
      width: 100%;
      transition: border-color 0.2s, box-shadow 0.2s;
      resize: vertical;
    }
    .form-right #content:focus {
      outline: none;
      border-color: #f97316;
      box-shadow: 0 0 0 3px rgba(249,115,22,0.15);
    }
    .btn-row {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }
    .btn-row button { min-width: 110px; }
    .memo-row { margin-top: 16px; }

    @media (max-width: 800px) {
      .grid, .stats-grid { grid-template-columns: 1fr; }
      .form-split { flex-direction: column; }
      .form-left { flex: none; width: 100%; }
      .btn-row { justify-content: flex-start; }
    }
  </style>
</head>
<body>
<div class="wrap">

  <!-- ─── 入力フォーム ─── -->
  <div class="card">
    <h1>かんたん家計簿入力</h1>
    <div class="desc">
      日付はカレンダー選択、初期値は今日です。購入先はリストから選べます。<br>
      分類は自由追加できます。月ごとの合計、出金・入金・残高も自動計算します。<br>
      データはサーバーのMySQLに保存されます。
    </div>

    <form id="entryForm">
      <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf) ?>" />
      <input type="hidden" id="editId" value="" />

      <!-- 左右分割 -->
      <div class="form-split">

        <!-- 左ペイン：日付・分類・購入先・金額 -->
        <div class="form-left">
          <div>
            <label for="date">日付</label>
            <input type="date" id="date" required value="<?= date('Y-m-d') ?>" />
          </div>

          <div>
            <label for="category">分類</label>
            <select id="category" required>
              <option value="">読み込み中…</option>
            </select>
          </div>

          <div>
            <label for="shop">購入先</label>
            <select id="shop" required>
              <option value="">読み込み中…</option>
            </select>
          </div>

          <div>
            <label for="withdrawal">出金額</label>
            <input type="number" id="withdrawal" min="0" step="1" placeholder="例: 850" />
          </div>

          <div>
            <label for="charge">チャージ額</label>
            <input type="number" id="charge" min="0" step="1" placeholder="例: 10000" />
          </div>

          <div>
            <label for="deposit">入金額</label>
            <input type="number" id="deposit" min="0" step="1" placeholder="例: 5000" />
          </div>
        </div>

        <!-- 右ペイン：内容等（大きく）＋ボタン群 -->
        <div class="form-right">
          <label for="content">内容等</label>
          <input type="text" id="content" list="contentList" placeholder="例: 物品代、薬代、交通費 など" />
          <datalist id="contentList"></datalist>
          <div class="btn-row">
            <button type="submit" id="submitBtn">確定</button>
            <button type="button" id="sortBtn" class="secondary">日付昇順</button>
            <button type="button" id="shopSortBtn" class="secondary">あいうえお順</button>
            <button type="button" id="backupBtn" class="backup">バックアップ</button>
            <button type="button" class="amazon" onclick="location.href='amazon_detail.php'">📦 Amazon分明細</button>
          </div>
        </div>

      </div>

      <!-- メモ（全幅） -->
      <div class="memo-row">
        <label for="detail">メモ</label>
        <textarea id="detail" placeholder="補足があれば入力"></textarea>
      </div>

      <div class="row-buttons">
        <button type="button" class="secondary" id="csvBtn">CSV出力</button>
      </div>
    </form>
  </div>

  <!-- ─── 入力一覧 ─── -->
  <div class="card">
    <h2>入力一覧</h2>
    <div class="small">保存先: MySQLデータベース</div>
    <div class="table-freeze-wrap">
    <table>
      <colgroup>
        <col /><col /><col />
        <col /><col /><col />
        <col /><col /><col /><col />
      </colgroup>
      <thead>
        <tr>
          <th>日付</th><th>分類</th><th>購入先</th>
          <th>出金額</th><th>チャージ額</th><th>入金額</th>
          <th>内容等</th><th>メモ</th><th>編集</th><th>削除</th>
        </tr>
      </thead>
      <tbody id="entryTableBody">
        <tr><td colspan="10" style="text-align:center;color:#888;">読み込み中…</td></tr>
      </tbody>
    </table>
    </div>
  </div>

  <!-- ─── 分類・購入先管理 ─── -->
  <div class="card">
    <div class="grid">
        <div class="full">
          <label for="newCategory">分類を自由追加</label>
          <div class="inline-add">
            <input type="text" id="newCategory" placeholder="例: 交際費、書籍代 など" />
            <button type="button" id="addCategoryBtn">分類追加</button>
          </div>
        </div>

        <div class="full">
          <label for="delCategorySelect">分類を削除</label>
          <div class="inline-add">
            <select id="delCategorySelect"><option value="">選択してください</option></select>
            <button type="button" id="delCategoryBtn" class="danger">分類削除</button>
          </div>
        </div>

        <div class="full">
          <label for="newShop">購入先を追加</label>
          <div class="inline-add">
            <input type="text" id="newShop" placeholder="例: 業務スーパー、ダイソー など" />
            <button type="button" id="addShopBtn">購入先追加</button>
          </div>
        </div>

        <div class="full">
          <label for="delShopSelect">購入先を削除</label>
          <div class="inline-add">
            <select id="delShopSelect"><option value="">選択してください</option></select>
            <button type="button" id="delShopBtn" class="danger">購入先削除</button>
          </div>
        </div>

        <div class="full">
          <label for="newContent">内容等を追加</label>
          <div class="inline-add">
            <input type="text" id="newContent" placeholder="例: 物品代、薬代、交通費 など" />
            <button type="button" id="addContentBtn">内容等追加</button>
          </div>
        </div>

        <div class="full">
          <label for="delContentSelect">内容等を削除</label>
          <div class="inline-add">
            <select id="delContentSelect"><option value="">選択してください</option></select>
            <button type="button" id="delContentBtn" class="danger">内容等削除</button>
          </div>
        </div>
    </div>
  </div>

  <!-- ─── 月別集計 ─── -->
  <div class="card">
    <h2>月ごとの集計</h2>
    <div class="toolbar">
      <div>
        <label for="monthPicker">対象月</label>
        <input type="month" id="monthPicker" />
      </div>
    </div>
    <div class="stats-grid">
      <div class="stat-box">
        <div class="stat-label">月の出金合計</div>
        <div class="stat-value" id="monthlyWithdrawal">－</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">月のチャージ合計</div>
        <div class="stat-value" id="monthlyCharge">－</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">月の入金合計</div>
        <div class="stat-value" id="monthlyDeposit">－</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">月の残高増減</div>
        <div class="stat-value" id="monthlyBalance">－</div>
      </div>
    </div>
    <div class="small" style="margin-top:10px;">残高増減 = 入金額 + チャージ額 − 出金額</div>
  </div>

  <!-- ─── 全体集計 ─── -->
  <div class="card">
    <h2>全体集計</h2>
    <div class="stats-grid">
      <div class="stat-box">
        <div class="stat-label">総出金額</div>
        <div class="stat-value" id="totalWithdrawal">－</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">総チャージ額</div>
        <div class="stat-value" id="totalCharge">－</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">総入金額</div>
        <div class="stat-value" id="totalDeposit">－</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">現在残高増減</div>
        <div class="stat-value" id="totalBalance">－</div>
      </div>
    </div>
    <div class="small" style="margin-top:10px;">現在残高増減 = 総入金額 + 総チャージ額 − 総出金額</div>
  </div>


</div>

<!-- トースト通知 -->
<div id="toast"></div>

<script>
// =====================================================
//  定数・ユーティリティ
// =====================================================
const API = 'api.php';
const csrf = document.getElementById('csrfToken').value;

const $ = id => document.getElementById(id);

function formatYen(v) {
  return Number(v || 0).toLocaleString('ja-JP');
}

function escapeHtml(text) {
  return String(text || '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

let sortDesc = false;
let cachedEntries = [];
let shopSortActive = false;
let toastTimer = null;
function showToast(msg, isError = false) {
  const el = $('toast');
  el.textContent = msg;
  el.className = 'show' + (isError ? ' error' : '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.className = ''; }, 2500);
}

// =====================================================
//  API呼び出し共通関数
// =====================================================
async function apiGet(action, params = {}) {
  const url = new URL(API, location.href);
  url.searchParams.set('action', action);
  for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v);
  const res = await fetch(url);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const data = await res.json();
  if (!data.ok) throw new Error(data.error || 'APIエラー');
  return data;
}

async function apiPost(action, body = {}) {
  const fd = new FormData();
  fd.append('csrf_token', csrf);
  for (const [k, v] of Object.entries(body)) fd.append(k, v);
  const url = `${API}?action=${encodeURIComponent(action)}`;
  const res = await fetch(url, { method: 'POST', body: fd });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const data = await res.json();
  if (!data.ok) throw new Error(data.error || 'APIエラー');
  return data;
}

// =====================================================
//  日付・月ピッカー初期化
// =====================================================
function todayStr() {
  const d = new Date();
  return [d.getFullYear(), String(d.getMonth()+1).padStart(2,'0'), String(d.getDate()).padStart(2,'0')].join('-');
}
function thisMonthStr() {
  const d = new Date();
  return [d.getFullYear(), String(d.getMonth()+1).padStart(2,'0')].join('-');
}

function setToday() {
  $('date').value = todayStr();
}

// =====================================================
//  セレクトボックス構築
// =====================================================
function buildSelect(selectEl, items, placeholder) {
  const prev = selectEl.value;
  selectEl.innerHTML = `<option value="">${escapeHtml(placeholder)}</option>`;
  items.forEach(name => {
    const opt = document.createElement('option');
    opt.value = opt.textContent = name;
    selectEl.appendChild(opt);
  });
  if (items.includes(prev)) selectEl.value = prev;
}

async function loadCategories() {
  const data = await apiGet('categories');
  buildSelect($('category'), data.categories, '選択してください');
  buildSelect($('delCategorySelect'), data.categories, '選択してください');
}

async function loadShops() {
  const data = await apiGet('shops');
  buildSelect($('shop'), data.shops, '選択してください');
  buildSelect($('delShopSelect'), data.shops, '選択してください');
}

async function loadContents() {
  const data = await apiGet('contents');
  // datalist 更新
  const dl = $('contentList');
  dl.innerHTML = '';
  data.contents.forEach(name => {
    const opt = document.createElement('option');
    opt.value = name;
    dl.appendChild(opt);
  });
  // 削除セレクト更新
  buildSelect($('delContentSelect'), data.contents, '選択してください');
}

async function loadMasters() {
  await Promise.all([loadCategories(), loadShops(), loadContents()]);
}

// =====================================================
//  サマリ更新
// =====================================================
async function updateSummary() {
  const month = $('monthPicker').value;
  const data = await apiGet('summary', { month });

  const t = data.total;
  const m = data.monthly;

  $('totalWithdrawal').textContent = formatYen(t.withdrawal);
  $('totalCharge').textContent     = formatYen(t.charge);
  $('totalDeposit').textContent    = formatYen(t.deposit);
  $('totalBalance').textContent    = formatYen((+t.deposit||0) + (+t.charge||0) - (+t.withdrawal||0));

  $('monthlyWithdrawal').textContent = formatYen((+m.withdrawal||0) + (+m.charge||0));
  $('monthlyCharge').textContent     = formatYen(m.charge);
  $('monthlyDeposit').textContent    = formatYen(m.deposit);
  $('monthlyBalance').textContent    = formatYen((+m.deposit||0) - (+m.withdrawal||0) - (+m.charge||0));
}

// =====================================================
//  テーブル描画
// =====================================================
async function renderTable() {
  const data = await apiGet('entries');
  cachedEntries = data.entries;
  const tbody = $('entryTableBody');
  tbody.innerHTML = '';

  // ソート
  const entries = [...cachedEntries].sort((a, b) => {
    if (shopSortActive) return (a.shop||"").localeCompare(b.shop||"", "ja");
    return sortDesc ? b.date.localeCompare(a.date) : a.date.localeCompare(b.date);
  });

  if (entries.length === 0) {
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#888;">データがありません</td></tr>';
  } else {
    entries.forEach(e => {
      const tr = document.createElement('tr');
      // Amazon購入は赤太文字
      if ((e.shop || '').toLowerCase().includes('amazon')) {
        tr.classList.add('amazon-row');
      }
      tr.innerHTML = `
        <td>${escapeHtml(e.date)}</td>
        <td>${escapeHtml(e.category)}</td>
        <td>${escapeHtml(e.shop)}</td>
        <td class="amount">${formatYen(e.withdrawal)}</td>
        <td class="amount">${formatYen(e.charge)}</td>
        <td class="amount">${formatYen(e.deposit)}</td>
        <td>${escapeHtml(e.content)}</td>
        <td>${escapeHtml(e.detail)}</td>
        <td><button type="button" class="secondary" data-edit="${e.id}">編集</button></td>
        <td><button type="button" class="danger"    data-del="${e.id}">削除</button></td>
      `;
      tbody.appendChild(tr);
    });
  }

  // イベント委譲
  tbody.querySelectorAll('[data-edit]').forEach(btn =>
    btn.addEventListener('click', () => startEdit(btn.dataset.edit, entries))
  );
  tbody.querySelectorAll('[data-del]').forEach(btn =>
    btn.addEventListener('click', () => deleteEntry(btn.dataset.del))
  );

  await updateSummary();
}

// =====================================================
//  フォーム操作
// =====================================================
function clearForm() {
  setToday();
  $('category').value  = '';
  $('shop').value      = '';
  $('withdrawal').value = '';
  $('charge').value    = '';
  $('deposit').value   = '';
  $('content').value   = '';
  $('detail').value    = '';
  $('editId').value    = '';
  $('submitBtn').textContent = '保存する';
}

function startEdit(id, entries) {
  const e = entries.find(x => String(x.id) === String(id));
  if (!e) return;
  $('date').value       = e.date       || '';
  $('category').value   = e.category   || '';
  $('shop').value       = e.shop       || '';
  $('withdrawal').value = e.withdrawal || '';
  $('charge').value     = e.charge     || '';
  $('deposit').value    = e.deposit    || '';
  $('content').value    = e.content    || '';
  $('detail').value     = e.detail     || '';
  $('editId').value     = id;
  $('submitBtn').textContent = '更新する';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function deleteEntry(id) {
  if (!confirm('この行を削除しますか？')) return;
  try {
    await apiPost('delete_entry', { id });
    showToast('削除しました');
    await renderTable();
  } catch(err) {
    showToast('削除に失敗: ' + err.message, true);
  }
}

// =====================================================
//  フォーム送信
// =====================================================
$('entryForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = $('submitBtn');
  btn.disabled = true;

  const body = {
    id:         $('editId').value,
    date:       $('date').value,
    category:   $('category').value,
    shop:       $('shop').value,
    withdrawal: $('withdrawal').value || 0,
    charge:     $('charge').value     || 0,
    deposit:    $('deposit').value    || 0,
    content:    $('content').value,
    detail:     $('detail').value,
  };

  try {
    const res = await apiPost('save_entry', body);
    showToast(res.updated ? '更新しました' : '保存しました');
    clearForm();
    await renderTable();
  } catch(err) {
    showToast('保存に失敗: ' + err.message, true);
  } finally {
    btn.disabled = false;
  }
});

// =====================================================
//  分類追加
// =====================================================
$('addCategoryBtn').addEventListener('click', async () => {
  const name = $('newCategory').value.trim();
  if (!name) { showToast('分類名を入力してください', true); return; }
  try {
    await apiPost('add_category', { name });
    await loadCategories();
    $('category').value = name;
    $('newCategory').value = '';
    showToast(`分類「${name}」を追加しました`);
  } catch(err) {
    showToast('追加に失敗: ' + err.message, true);
  }
});

// =====================================================
//  分類削除
// =====================================================
$('delCategoryBtn').addEventListener('click', async () => {
  const name = $('delCategorySelect').value;
  if (!name) { showToast('削除する分類を選択してください', true); return; }
  if (!confirm(`分類「${name}」を削除しますか？`)) return;
  try {
    await apiPost('delete_category', { name });
    await loadCategories();
    buildSelect($('delCategorySelect'), (await apiGet('categories')).categories, '選択してください');
    showToast(`分類「${name}」を削除しました`);
  } catch(err) {
    showToast('削除に失敗: ' + err.message, true);
  }
});

// =====================================================
//  購入先追加
// =====================================================
$('addShopBtn').addEventListener('click', async () => {
  const name = $('newShop').value.trim();
  if (!name) { showToast('購入先名を入力してください', true); return; }
  try {
    await apiPost('add_shop', { name });
    await loadShops();
    $('shop').value = name;
    $('newShop').value = '';
    showToast(`購入先「${name}」を追加しました`);
  } catch(err) {
    showToast('追加に失敗: ' + err.message, true);
  }
});

// =====================================================
//  購入先削除
// =====================================================
$('delShopBtn').addEventListener('click', async () => {
  const name = $('delShopSelect').value;
  if (!name) { showToast('削除する購入先を選択してください', true); return; }
  if (!confirm(`購入先「${name}」を削除しますか？`)) return;
  try {
    await apiPost('delete_shop', { name });
    await loadShops();
    showToast(`購入先「${name}」を削除しました`);
  } catch(err) {
    showToast('削除に失敗: ' + err.message, true);
  }
});

// =====================================================
//  内容等追加
// =====================================================
$('addContentBtn').addEventListener('click', async () => {
  const name = $('newContent').value.trim();
  if (!name) { showToast('内容等を入力してください', true); return; }
  try {
    await apiPost('add_content', { name });
    await loadContents();
    $('newContent').value = '';
    showToast(`内容等「${name}」を追加しました`);
  } catch(err) {
    showToast('追加に失敗: ' + err.message, true);
  }
});

// =====================================================
//  内容等削除
// =====================================================
$('delContentBtn').addEventListener('click', async () => {
  const name = $('delContentSelect').value;
  if (!name) { showToast('削除する内容等を選択してください', true); return; }
  if (!confirm(`内容等「${name}」を削除しますか？`)) return;
  try {
    await apiPost('delete_content', { name });
    await loadContents();
    showToast(`内容等「${name}」を削除しました`);
  } catch(err) {
    showToast('削除に失敗: ' + err.message, true);
  }
});

// =====================================================
//  日付昇順ソート
// =====================================================
$("sortBtn").addEventListener("click", async () => {
  sortDesc = false;
  await renderTable();
});

$("shopSortBtn").addEventListener("click", async () => {
  shopSortActive = !shopSortActive;
  $("shopSortBtn").textContent = shopSortActive ? "あいうえお解除" : "あいうえお順";
  await renderTable();
});

// =====================================================
//  バックアップ
// =====================================================
$('backupBtn').addEventListener('click', async () => {
  const btn = $('backupBtn');
  btn.disabled = true;
  showToast('バックアップ中…');
  try {
    const data = await apiPost('backup', {});
    showToast(`バックアップ完了: ${data.filename} (${data.count}件)`);
  } catch(err) {
    showToast('バックアップ失敗: ' + err.message, true);
  } finally {
    btn.disabled = false;
  }
});

// =====================================================
// =====================================================
$('csvBtn').addEventListener('click', async () => {
  const data = await apiGet('entries');
  if (data.entries.length === 0) {
    showToast('出力するデータがありません', true);
    return;
  }
  const header = ['日付','分類','購入先','出金額','チャージ額','入金額','内容等','メモ'];
  const rows = data.entries.map(e => [
    e.date, e.category, e.shop,
    e.withdrawal, e.charge, e.deposit,
    e.content, e.detail
  ]);
  const csv = [header, ...rows]
    .map(row => row.map(v => `"${String(v??'').replace(/"/g,'""')}"`).join(','))
    .join('\n');
  const bom = new Uint8Array([0xEF, 0xBB, 0xBF]);
  const blob = new Blob([bom, csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = '家計簿データ.csv';
  a.click();
  URL.revokeObjectURL(url);
});

// =====================================================
//  月ピッカー変更
// =====================================================
$('monthPicker').addEventListener('change', updateSummary);

// =====================================================
//  初期化
// =====================================================
(async () => {
  setToday();
  $('monthPicker').value = thisMonthStr();
  await loadMasters();
  await renderTable();
})();
</script>
</body>
</html>
