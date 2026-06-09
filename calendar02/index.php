<?php
require_once __DIR__ . '/config.php';
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= APP_NAME ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Hiragino Sans', 'Meiryo', sans-serif;
    background: #f0f2f5;
    color: #1a1a1a;
    min-height: 100vh;
}

/* ─── ヘッダー ─── */
.header {
    background: #1D9E75;
    padding: 14px 24px;
    display: flex;
    align-items: center;
}
.header-title { font-size: 18px; font-weight: 600; color: #fff; letter-spacing: 0.3px; }

/* ─── レイアウト ─── */
.main {
    display: grid;
    grid-template-columns: 1fr 310px;
    gap: 20px;
    padding: 24px;
    max-width: 1200px;
    margin: 0 auto;
}

/* ─── カレンダーカード ─── */
.cal-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    overflow: hidden;
}

/* 月ヘッダー */
.cal-month-header {
    background: #1D9E75;
    padding: 20px 24px 16px;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
}
.cal-month-label {
    display: flex;
    flex-direction: column;
    line-height: 1;
}
.cal-month-label .year {
    font-size: 14px;
    color: rgba(255,255,255,0.75);
    margin-bottom: 4px;
    font-weight: 400;
}
.cal-month-label .month {
    font-size: 42px;
    font-weight: 700;
    color: #fff;
    letter-spacing: -1px;
}
.cal-month-label .month-ja {
    font-size: 16px;
    color: rgba(255,255,255,0.85);
    font-weight: 500;
    margin-left: 6px;
}
.nav-wrap { display: flex; gap: 8px; align-items: center; padding-bottom: 6px; }
.nav-btn {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.4);
    border-radius: 8px;
    padding: 7px 14px;
    cursor: pointer;
    font-size: 14px;
    color: #fff;
    font-family: inherit;
    transition: background 0.15s;
}
.nav-btn:hover { background: rgba(255,255,255,0.35); }
.today-btn {
    font-size: 12px;
    padding: 7px 12px;
    border-color: rgba(255,255,255,0.6);
}

/* 曜日ヘッダー */
.weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #f7faf9;
    border-bottom: 1px solid #e8ede8;
}
.wd {
    text-align: center;
    font-size: 12px;
    color: #888;
    padding: 10px 0;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.wd.sun { color: #D85A30; }
.wd.sat { color: #378ADD; }

/* 日付グリッド */
.days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    border-left: 1px solid #eee;
    border-top: 1px solid #eee;
}
.day-cell {
    min-height: 90px;
    border-right: 1px solid #eee;
    border-bottom: 1px solid #eee;
    padding: 8px 6px 6px;
    cursor: pointer;
    transition: background 0.1s;
    position: relative;
}
.day-cell:hover { background: #f5fdf9; }
.day-cell.other-month { background: #fafafa; pointer-events: none; }
.day-cell.other-month .day-num { opacity: 0.3; }

.day-num-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    margin-bottom: 4px;
}
.day-cell.today .day-num-wrap {
    background: #1D9E75;
    border-radius: 50%;
}
.day-cell.selected .day-num-wrap {
    background: #d0f2e6;
    border-radius: 50%;
}
.day-cell.today.selected .day-num-wrap {
    background: #1D9E75;
}
.day-num {
    font-size: 15px;
    font-weight: 500;
    color: #333;
    line-height: 1;
}
.day-cell.today .day-num { color: #fff; font-weight: 700; }
.day-num.sun { color: #D85A30; }
.day-num.sat { color: #378ADD; }
.day-cell.today .day-num.sun,
.day-cell.today .day-num.sat { color: #fff; }

.ev-chip {
    font-size: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background: #e6f7f1;
    color: #0F6E56;
    border-radius: 4px;
    padding: 2px 5px;
    margin-top: 2px;
    border-left: 3px solid #1D9E75;
    line-height: 1.4;
}

/* ─── サイドパネル ─── */
.side-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.side-date-header {
    background: #1D9E75;
    padding: 20px;
    color: #fff;
}
.side-date-day {
    font-size: 48px;
    font-weight: 700;
    line-height: 1;
    letter-spacing: -2px;
}
.side-date-label {
    font-size: 14px;
    color: rgba(255,255,255,0.85);
    margin-top: 4px;
}
.side-body {
    flex: 1;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.ev-list { flex: 1; }
.ev-item {
    background: #f7fdf9;
    border: 1px solid #d0ece2;
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 8px;
}
.ev-item-title { font-size: 14px; font-weight: 600; color: #1a1a1a; }
.ev-item-time { font-size: 12px; color: #1D9E75; margin-top: 3px; font-weight: 500; }
.ev-item-memo { font-size: 12px; color: #777; margin-top: 3px; }
.ev-del {
    font-size: 11px;
    color: #c55;
    cursor: pointer;
    margin-top: 6px;
    display: inline-block;
    background: none;
    border: none;
    padding: 0;
    font-family: inherit;
}
.ev-del:hover { color: #a00; }
.no-events {
    font-size: 13px;
    color: #bbb;
    text-align: center;
    padding: 28px 0;
}
.placeholder-msg {
    font-size: 13px;
    color: #bbb;
    text-align: center;
    padding: 40px 0;
}

.add-btn {
    width: 100%;
    padding: 11px;
    font-size: 14px;
    font-family: inherit;
    border: 2px dashed #ccc;
    border-radius: 10px;
    background: none;
    cursor: pointer;
    color: #888;
    transition: border-color 0.15s, color 0.15s;
}
.add-btn:hover { border-color: #1D9E75; color: #1D9E75; }

.notify-section { border-top: 1px solid #f0f0ec; padding-top: 12px; }
.notify-label { font-size: 11px; color: #aaa; margin-bottom: 6px; }
.gmail-btn {
    width: 100%;
    padding: 9px;
    font-size: 13px;
    font-family: inherit;
    border: 1px solid #c5d9f1;
    border-radius: 8px;
    background: none;
    cursor: pointer;
    color: #185FA5;
    transition: background 0.1s;
}
.gmail-btn:hover { background: #f0f5fc; }
.gmail-btn:disabled { opacity: 0.4; cursor: default; }
.notify-status { font-size: 11px; margin-top: 6px; color: #aaa; }
.notify-status.ok { color: #1D9E75; }
.notify-status.err { color: #c00; }

/* ─── モーダル ─── */
.modal-bg {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.3);
    z-index: 100;
    align-items: center;
    justify-content: center;
}
.modal-bg.open { display: flex; }
.modal {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    width: 360px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
}
.modal h3 { font-size: 16px; font-weight: 600; margin-bottom: 18px; color: #1a1a1a; }
.field { margin-bottom: 14px; }
.field label { display: block; font-size: 12px; color: #888; margin-bottom: 5px; font-weight: 500; }
.field input, .field textarea {
    width: 100%;
    padding: 9px 12px;
    font-size: 14px;
    border: 1.5px solid #ddd;
    border-radius: 10px;
    outline: none;
    font-family: inherit;
    transition: border-color 0.15s;
    background: #fff;
}
.field input:focus, .field textarea:focus { border-color: #1D9E75; }
.field textarea { resize: vertical; height: 80px; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.modal-actions button {
    flex: 1;
    padding: 11px;
    font-size: 14px;
    font-family: inherit;
    border: 1.5px solid #ddd;
    border-radius: 10px;
    cursor: pointer;
    background: none;
    color: #555;
    transition: background 0.1s;
}
.modal-actions button:hover { background: #f5f5f0; }
.btn-primary { background: #1D9E75 !important; color: white !important; border-color: #1D9E75 !important; }
.btn-primary:hover { background: #0F6E56 !important; }

@media (max-width: 750px) {
    .main { grid-template-columns: 1fr; padding: 12px; gap: 12px; }
    .day-cell { min-height: 68px; }
    .side-date-day { font-size: 36px; }
}
</style>
</head>
<body>

<div class="header">
    <div class="header-title">📅 <?= APP_NAME ?></div>
</div>

<div class="main">
    <!-- カレンダー -->
    <div class="cal-card">
        <div class="cal-month-header">
            <div class="cal-month-label">
                <span class="year" id="cal-year"></span>
                <div style="display:flex;align-items:baseline;">
                    <span class="month" id="cal-month"></span>
                    <span class="month-ja">月</span>
                </div>
            </div>
            <div class="nav-wrap">
                <button class="nav-btn" id="prev-btn">＜</button>
                <button class="nav-btn today-btn" id="today-btn">今日</button>
                <button class="nav-btn" id="next-btn">＞</button>
            </div>
        </div>
        <div class="weekdays">
            <div class="wd sun">日</div><div class="wd">月</div><div class="wd">火</div>
            <div class="wd">水</div><div class="wd">木</div><div class="wd">金</div>
            <div class="wd sat">土</div>
        </div>
        <div class="days-grid" id="days-grid"></div>
    </div>

    <!-- サイドパネル -->
    <div class="side-card">
        <div class="side-date-header" id="side-date-header">
            <div class="side-date-day" id="side-date-day">—</div>
            <div class="side-date-label" id="side-date-label">日付を選択してください</div>
        </div>
        <div class="side-body">
            <div class="ev-list" id="ev-list">
                <div class="placeholder-msg">← 日付をクリック</div>
            </div>
            <button class="add-btn" id="add-btn">＋ 予定を追加</button>
            <div class="notify-section">
                <div class="notify-label">Gmail で通知</div>
                <button class="gmail-btn" id="gmail-btn">📧 メールで送信</button>
                <div class="notify-status" id="notify-status"></div>
            </div>
        </div>
    </div>
</div>

<!-- 予定追加モーダル -->
<div class="modal-bg" id="modal">
    <div class="modal">
        <h3>予定を追加</h3>
        <div class="field">
            <label>タイトル <span style="color:#c00">*</span></label>
            <input type="text" id="ev-title" placeholder="例：歯科検診">
        </div>
        <div class="field">
            <label>時間（省略可）</label>
            <input type="time" id="ev-time">
        </div>
        <div class="field">
            <label>メモ（省略可）</label>
            <textarea id="ev-memo" placeholder="詳細メモ"></textarea>
        </div>
        <div class="modal-actions">
            <button id="modal-cancel">キャンセル</button>
            <button class="btn-primary" id="modal-save">保存</button>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
let curYear, curMonth, selectedDate;
let eventsCache = {};
const today = new Date();
const DOW_JA = ['日','月','火','水','木','金','土'];

function fmt(y, m, d){ return `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`; }
function todayKey(){ return fmt(today.getFullYear(), today.getMonth()+1, today.getDate()); }

// ─── API ───────────────────────────────────────────
async function loadEvents(year, month){
    const res = await fetch(`/api.php?action=list&year=${year}&month=${month}`);
    const data = await res.json();
    if(data.ok){ eventsCache = {...eventsCache, ...data.events}; }
}
async function addEvent(date, time, title, memo){
    const res = await fetch('/api.php?action=add', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({csrf: CSRF, date, time, title, memo})
    });
    return await res.json();
}
async function deleteEvent(id){
    const res = await fetch('/api.php?action=delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({csrf: CSRF, id})
    });
    return await res.json();
}
async function sendGmail(date, events){
    const res = await fetch('/send_mail.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({csrf: CSRF, date, events})
    });
    return await res.json();
}

// ─── カレンダー描画 ────────────────────────────────
function renderCalendar(){
    document.getElementById('cal-year').textContent  = `${curYear}年`;
    document.getElementById('cal-month').textContent = curMonth;

    const grid = document.getElementById('days-grid');
    grid.innerHTML = '';
    const first = new Date(curYear, curMonth-1, 1).getDay();
    const last  = new Date(curYear, curMonth, 0).getDate();
    const prevL = new Date(curYear, curMonth-1, 0).getDate();

    for(let i=0; i<first; i++){
        const pm = curMonth === 1 ? 12 : curMonth - 1;
        const py = curMonth === 1 ? curYear - 1 : curYear;
        grid.appendChild(makeCell(py, pm, prevL - first + 1 + i, true));
    }
    for(let d=1; d<=last; d++){
        grid.appendChild(makeCell(curYear, curMonth, d, false));
    }
    const rem = 42 - first - last;
    for(let d=1; d<=rem; d++){
        const nm = curMonth === 12 ? 1 : curMonth + 1;
        const ny = curMonth === 12 ? curYear + 1 : curYear;
        grid.appendChild(makeCell(ny, nm, d, true));
    }
}

function makeCell(y, m, d, other){
    const cell = document.createElement('div');
    cell.className = 'day-cell' + (other ? ' other-month' : '');
    const key = fmt(y, m, d);
    const dow = new Date(y, m-1, d).getDay();
    if(key === todayKey()) cell.classList.add('today');
    if(selectedDate && key === selectedDate) cell.classList.add('selected');

    const wrap = document.createElement('div');
    wrap.className = 'day-num-wrap';
    const numDiv = document.createElement('div');
    numDiv.className = 'day-num' + (dow===0?' sun':dow===6?' sat':'');
    numDiv.textContent = d;
    wrap.appendChild(numDiv);
    cell.appendChild(wrap);

    const evs = eventsCache[key] || [];
    evs.slice(0, 3).forEach(ev => {
        const chip = document.createElement('div');
        chip.className = 'ev-chip';
        chip.textContent = (ev.time ? ev.time + ' ' : '') + ev.title;
        cell.appendChild(chip);
    });

    cell.addEventListener('click', () => {
        selectedDate = key;
        renderCalendar();
        renderSide();
    });
    return cell;
}

// ─── サイドパネル ──────────────────────────────────
function renderSide(){
    const dayEl   = document.getElementById('side-date-day');
    const labelEl = document.getElementById('side-date-label');
    const list    = document.getElementById('ev-list');

    if(!selectedDate){
        dayEl.textContent   = '—';
        labelEl.textContent = '日付を選択してください';
        list.innerHTML = '<div class="placeholder-msg">← 日付をクリック</div>';
        return;
    }
    const [y, m, d] = selectedDate.split('-');
    const dow = DOW_JA[new Date(selectedDate).getDay()];
    dayEl.textContent   = parseInt(d);
    labelEl.textContent = `${y}年 ${parseInt(m)}月（${dow}）`;

    const evs = eventsCache[selectedDate] || [];
    if(evs.length === 0){
        list.innerHTML = '<div class="no-events">予定なし</div>';
    } else {
        list.innerHTML = evs.map(ev => `
            <div class="ev-item">
                <div class="ev-item-title">${esc(ev.title)}</div>
                ${ev.time ? `<div class="ev-item-time">🕐 ${esc(ev.time)}</div>` : ''}
                ${ev.memo ? `<div class="ev-item-memo">${esc(ev.memo)}</div>` : ''}
                <button class="ev-del" data-id="${ev.id}">削除</button>
            </div>`).join('');

        list.querySelectorAll('.ev-del').forEach(btn => {
            btn.addEventListener('click', async () => {
                if(!confirm('この予定を削除しますか？')) return;
                const id = btn.dataset.id;
                const res = await deleteEvent(id);
                if(res.ok){
                    eventsCache[selectedDate] = (eventsCache[selectedDate]||[]).filter(e=>e.id!==id);
                    if(!eventsCache[selectedDate].length) delete eventsCache[selectedDate];
                    renderCalendar();
                    renderSide();
                } else {
                    alert('削除に失敗しました');
                }
            });
        });
    }
}

function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ─── ナビゲーション ────────────────────────────────
document.getElementById('prev-btn').onclick = async () => {
    curMonth--; if(curMonth < 1){ curMonth=12; curYear--; }
    await loadEvents(curYear, curMonth);
    renderCalendar();
};
document.getElementById('next-btn').onclick = async () => {
    curMonth++; if(curMonth > 12){ curMonth=1; curYear++; }
    await loadEvents(curYear, curMonth);
    renderCalendar();
};
document.getElementById('today-btn').onclick = async () => {
    curYear=today.getFullYear(); curMonth=today.getMonth()+1;
    selectedDate=todayKey();
    await loadEvents(curYear, curMonth);
    renderCalendar(); renderSide();
};

// ─── 予定追加 ──────────────────────────────────────
document.getElementById('add-btn').onclick = () => {
    if(!selectedDate){ alert('日付を選択してください'); return; }
    document.getElementById('ev-title').value = '';
    document.getElementById('ev-time').value  = '';
    document.getElementById('ev-memo').value  = '';
    document.getElementById('modal').classList.add('open');
};
document.getElementById('modal-cancel').onclick = () => {
    document.getElementById('modal').classList.remove('open');
};
document.getElementById('modal-save').onclick = async () => {
    const title = document.getElementById('ev-title').value.trim();
    if(!title){ alert('タイトルを入力してください'); return; }
    const time = document.getElementById('ev-time').value;
    const memo = document.getElementById('ev-memo').value.trim();
    const res = await addEvent(selectedDate, time, title, memo);
    if(res.ok){
        if(!eventsCache[selectedDate]) eventsCache[selectedDate]=[];
        eventsCache[selectedDate].push({ id: res.id, time, title, memo });
        eventsCache[selectedDate].sort((a,b)=>a.time.localeCompare(b.time));
        renderCalendar(); renderSide();
        document.getElementById('modal').classList.remove('open');
    } else {
        alert('保存に失敗しました: ' + (res.error||'不明'));
    }
};

// ─── Gmail通知 ─────────────────────────────────────
document.getElementById('gmail-btn').onclick = async () => {
    const btn    = document.getElementById('gmail-btn');
    const status = document.getElementById('notify-status');
    const evs    = selectedDate ? (eventsCache[selectedDate]||[]) : [];
    btn.disabled = true;
    status.className = 'notify-status';
    status.textContent = '送信中...';
    const res = await sendGmail(selectedDate, evs);
    if(res.ok){
        status.className = 'notify-status ok';
        status.textContent = '✓ メールを送信しました';
    } else {
        status.className = 'notify-status err';
        status.textContent = 'エラー: ' + (res.error||'不明');
    }
    btn.disabled = false;
};

// ─── 初期化 ────────────────────────────────────────
(async () => {
    curYear  = today.getFullYear();
    curMonth = today.getMonth() + 1;
    selectedDate = todayKey();
    await loadEvents(curYear, curMonth);
    renderCalendar();
    renderSide();
})();
</script>
</body>
</html>
