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
      background: #f5f7fb;
      margin: 0;
      padding: 24px;
      color: #222;
    }
    .wrap { max-width: 1180px; margin: 0 auto; }
    .card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.08);
      padding: 24px;
      margin-bottom: 20px;
    }
    h1, h2 { margin-top: 0; }
    .desc { color: #555; margin-bottom: 18px; line-height: 1.7; }
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
    .stat-box {
      border: 1px solid #dbe3f0;
      border-radius: 12px;
      padding: 14px;
      background: #f8fbff;
    }
    .stat-label { font-size: 13px; color: #666; margin-bottom: 8px; }
    .stat-value { font-size: 24px; font-weight: bold; }
    .full { grid-column: 1 / -1; }
    label { display: block; font-weight: bold; margin-bottom: 6px; }
    input, select, textarea, button {
      width: 100%;
      box-sizing: border-box;
      padding: 10px 12px;
      border: 1px solid #cfd6e4;
      border-radius: 10px;
      font-size: 16px;
      background: #fff;
    }
    textarea { min-height: 90px; resize: vertical; }
    .row-buttons {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 20px;
    }
    button {
      cursor: pointer;
      font-weight: bold;
      width: auto;
      min-width: 140px;
      border: none;
      background: #2d6cdf;
      color: #fff;
    }
    button.secondary { background: #6b7280; }
    button.amazon    { background: #d97706; }
    button.danger    { background: #c0392b; min-width: 70px; padding: 8px 10px; }
    button:disabled  { opacity: 0.5; cursor: not-allowed; }
    .inline-add { display: flex; gap: 8px; }
    .inline-add input { flex: 1; }
    .inline-add button { min-width: 100px; }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
    }
    th, td {
      border: 1px solid #dfe5ef;
      padding: 10px;
      text-align: left;
      font-size: 14px;
      vertical-align: top;
    }
    th { background: #eef3fb; }
    .small { font-size: 13px; color: #666; }
    .amount { text-align: right; white-space: nowrap; }
    .toolbar {
      display: flex;
      gap: 12px;
      align-items: end;
      flex-wrap: wrap;
      margin-top: 10px;
    }
    .toolbar > div { min-width: 220px; }
    /* トースト通知 */
    #toast {
      position: fixed;
      bottom: 30px;
      right: 30px;
      background: #1e3a5f;
      color: #fff;
      padding: 14px 22px;
      border-radius: 12px;
      font-size: 15px;
      opacity: 0;
      transition: opacity 0.3s;
      pointer-events: none;
      z-index: 9999;
    }
    #toast.show { opacity: 1; }
    #toast.error { background: #b91c1c; }
    @media (max-width: 800px) {
      .grid, .stats-grid { grid-template-columns: 1fr; }
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

      <div class="grid">
        <div>
          <label for="date">日付</label>
          <input type="date" id="date" required />
        </div>

        <div>
          <label for="category">分類</label>
          <select id="category" required>
            <option value="">読み込み中…</option>
          </select>
        </div>

        <div class="full">
          <label for="newCategory">分類を自由追加</label>
          <div class="inline-add">
            <input type="text" id="newCategory" placeholder="例: 交際費、書籍代 など" />
            <button type="button" id="addCategoryBtn">分類追加</button>
          </div>
        </div>

        <div>
          <label for="shop">購入先</label>
          <select id="shop" required>
            <option value="">読み込み中…</option>
          </select>
        </div>

        <div class="full">
          <label for="newShop">購入先を追加</label>
          <div class="inline-add">
            <input type="text" id="newShop" placeholder="例: 業務スーパー、ダイソー など" />
            <button type="button" id="addShopBtn">購入先追加</button>
          </div>
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

        <div>
          <label for="content">内容等</label>
          <input type="text" id="content" placeholder="例: 物品代、薬代、交通費 など" />
        </div>

        <div class="full">
          <label for="detail">メモ</label>
          <textarea id="detail" placeholder="補足があれば入力"></textarea>
        </div>
      </div>

      <div class="row-buttons">
        <button type="submit" id="submitBtn">保存する</button>
        <button type="button" class="amazon" id="amazonBtn">Amazon入力</button>
        <button type="button" class="secondary" id="clearBtn">入力をクリア</button>
        <button type="button" class="secondary" id="csvBtn">CSV出力</button>
      </div>
    </form>
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

  <!-- ─── 入力一覧 ─── -->
  <div class="card">
    <h2>入力一覧</h2>
    <div class="small">保存先: MySQLデータベース</div>
    <table>
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
}

async function loadShops() {
  const data = await apiGet('shops');
  buildSelect($('shop'), data.shops, '選択してください');
}

async function loadMasters() {
  await Promise.all([loadCategories(), loadShops()]);
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

  $('monthlyWithdrawal').textContent = formatYen(m.withdrawal);
  $('monthlyCharge').textContent     = formatYen(m.charge);
  $('monthlyDeposit').textContent    = formatYen(m.deposit);
  $('monthlyBalance').textContent    = formatYen((+m.deposit||0) + (+m.charge||0) - (+m.withdrawal||0));
}

// =====================================================
//  テーブル描画
// =====================================================
async function renderTable() {
  const data = await apiGet('entries');
  const tbody = $('entryTableBody');
  tbody.innerHTML = '';

  if (data.entries.length === 0) {
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#888;">データがありません</td></tr>';
  } else {
    data.entries.forEach(e => {
      const tr = document.createElement('tr');
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
    btn.addEventListener('click', () => startEdit(btn.dataset.edit, data.entries))
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
//  Amazon入力ボタン
// =====================================================
$('amazonBtn').addEventListener('click', () => {
  $('shop').value     = 'Amazon';
  $('category').value = 'その他';
  $('content').value  = 'Amazon注文分';
  $('withdrawal').focus();
});

// =====================================================
//  CSV出力
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
