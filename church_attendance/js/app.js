/************************************************************
 * 日曜礼拝 出欠管理システム - フロントエンドJS
 * localStorage → PHP API (fetch) に書き換え済み
 ************************************************************/

// 名簿キャッシュ
let rosterMale = [];
let rosterFemale = [];

// 状態
const state = {
  year: new Date().getFullYear(),
  monthIndex: new Date().getMonth(),
  view: "male",
  activeIso: null,
  attendance: {}, // { "YYYY-MM-DD": { roster_id: true/false, ... } }
};

/************************************************************
 * API通信ヘルパー
 ************************************************************/
async function apiGet(url) {
  const res = await fetch(url);
  return res.json();
}
async function apiPost(url, body) {
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return res.json();
}

/************************************************************
 * 名簿の読み込み（DB → キャッシュ）
 ************************************************************/
async function loadRoster() {
  const maleData = await apiGet("api/roster.php?gender=male");
  const femaleData = await apiGet("api/roster.php?gender=female");
  
  if (maleData.success) {
    rosterMale = maleData.roster.map(r => ({
      id: r.id,
      label: r.name.trim() ? r.name : `（空欄）`,
      is_newcomer: r.is_newcomer,
    }));
  }
  if (femaleData.success) {
    rosterFemale = femaleData.roster.map(r => ({
      id: r.id,
      label: r.name.trim() ? r.name : `（空欄）`,
      is_newcomer: r.is_newcomer,
    }));
  }
}

/************************************************************
 * 出欠データの読み込み（DB → キャッシュ）
 ************************************************************/
async function loadAttendance(year, month) {
  const data = await apiGet(`api/load_attendance.php?year=${year}&month=${month}`);
  if (data.success) {
    // attendance をマージ
    for (const [date, records] of Object.entries(data.attendance)) {
      if (!state.attendance[date]) state.attendance[date] = {};
      for (const rec of records) {
        state.attendance[date][rec.roster_id] = rec.is_present;
      }
    }
  }
}

/************************************************************
 * 出欠の保存（チェック変更 → DB）
 ************************************************************/
async function saveCheck(date, rosterId, isPresent) {
  // ローカルキャッシュ即座更新
  if (!state.attendance[date]) state.attendance[date] = {};
  state.attendance[date][rosterId] = isPresent;
  
  // DB保存
  await apiPost("api/save_attendance.php", {
    date: date,
    roster_id: rosterId,
    is_present: isPresent,
  });
}

/************************************************************
 * 日曜抽出
 ************************************************************/
function getSundaysOfMonth(y, mIndex) {
  const last = new Date(y, mIndex + 1, 0);
  const sundays = [];
  for (let d = 1; d <= last.getDate(); d++) {
    const dt = new Date(y, mIndex, d);
    if (dt.getDay() === 0) sundays.push(dt);
  }
  return sundays;
}
function toISODate(dt) {
  const y = dt.getFullYear();
  const m = String(dt.getMonth() + 1).padStart(2, "0");
  const d = String(dt.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}
function toMMDD(dt) {
  return `${String(dt.getMonth()+1).padStart(2,"0")}/${String(dt.getDate()).padStart(2,"0")}`;
}
function jpWeekday(dt) {
  return ["日","月","火","水","木","金","土"][dt.getDay()];
}

/************************************************************
 * 集計
 ************************************************************/
function countChecked(iso, gender) {
  const dayData = state.attendance[iso];
  if (!dayData) return 0;
  
  const roster = (gender === "male") ? rosterMale : rosterFemale;
  let count = 0;
  for (const person of roster) {
    if (dayData[person.id]) count++;
  }
  return count;
}

function getCheck(iso, rosterId) {
  return !!(state.attendance[iso] && state.attendance[iso][rosterId]);
}

function getMonthTotals(y, mIndex) {
  const sundays = getSundaysOfMonth(y, mIndex);
  let male = 0, female = 0;
  for (const dt of sundays) {
    const iso = toISODate(dt);
    male += countChecked(iso, "male");
    female += countChecked(iso, "female");
  }
  return { male, female, total: male + female };
}

function getCurrentMonthTotals() {
  return getMonthTotals(state.year, state.monthIndex);
}

function getYearTotals(year) {
  const months = [];
  let male = 0, female = 0;
  for (let m = 0; m < 12; m++) {
    const t = getMonthTotals(year, m);
    months.push({ monthIndex: m, ...t });
    male += t.male;
    female += t.female;
  }
  return { year, months, male, female, total: male + female };
}

/************************************************************
 * UI要素
 ************************************************************/
const elCards = document.getElementById("cards");
const elMonthSelect = document.getElementById("monthSelect");
const elPrev = document.getElementById("prevMonth");
const elNext = document.getElementById("nextMonth");
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
const elScrollLeft = document.getElementById("scrollLeft");
const elScrollRight = document.getElementById("scrollRight");
const elScrollHint = document.getElementById("scrollHint");

/************************************************************
 * 横スクロール操作
 ************************************************************/
function getScrollStep() {
  const card = elCards.querySelector(".card");
  if (!card) return 320;
  const cs = window.getComputedStyle(elCards);
  const gap = parseFloat(cs.columnGap || cs.gap || "12") || 12;
  return card.offsetWidth + gap;
}

function updateScrollButtons() {
  if (!elScrollLeft || !elScrollRight) return;
  const maxLeft = elCards.scrollWidth - elCards.clientWidth;
  const atStart = elCards.scrollLeft <= 1;
  const atEnd = elCards.scrollLeft >= (maxLeft - 1);
  elScrollLeft.disabled = atStart;
  elScrollRight.disabled = atEnd;
  if (elScrollHint) {
    if (atStart && atEnd) elScrollHint.textContent = "（この月はカードが少ないため横スクロール不要）";
    else if (atEnd) elScrollHint.textContent = "（最後まで表示中）";
    else if (atStart) elScrollHint.textContent = "（先頭を表示中）";
    else elScrollHint.textContent = "（横にスクロールできます）";
  }
}

if (elScrollLeft && elScrollRight) {
  elScrollLeft.addEventListener("click", () => elCards.scrollBy({ left: -getScrollStep(), behavior: "smooth" }));
  elScrollRight.addEventListener("click", () => elCards.scrollBy({ left: getScrollStep(), behavior: "smooth" }));
  let rafId = null;
  elCards.addEventListener("scroll", () => {
    if (rafId) cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(updateScrollButtons);
  });
}

/************************************************************
 * セレクトボックス構築
 ************************************************************/
function buildYearSelect() {
  const nowY = new Date().getFullYear();
  const years = [nowY - 2, nowY - 1, nowY, nowY + 1, nowY + 2];
  elYearSelect.innerHTML = "";
  for (const y of years) {
    const opt = document.createElement("option");
    opt.value = String(y);
    opt.textContent = `${y}年（年集計）`;
    if (y === state.year) opt.selected = true;
    elYearSelect.appendChild(opt);
  }
}

function buildMonthSelect() {
  elMonthSelect.innerHTML = "";
  for (let m = 0; m < 12; m++) {
    const opt = document.createElement("option");
    opt.value = `${state.year}-${m}`;
    opt.textContent = `${state.year}年 ${String(m + 1).padStart(2, "0")}月`;
    if (m === state.monthIndex) opt.selected = true;
    elMonthSelect.appendChild(opt);
  }
}

function setTabs() {
  const map = { male: elTabMale, female: elTabFemale, total: elTabTotal };
  for (const k of Object.keys(map)) {
    map[k].classList.toggle("active", state.view === k);
    map[k].setAttribute("aria-selected", state.view === k ? "true" : "false");
  }
}

/************************************************************
 * フッター集計
 ************************************************************/
function setActiveIso(iso) {
  state.activeIso = iso;
  const m = countChecked(iso, "male");
  const f = countChecked(iso, "female");
  elActiveMale.textContent = m;
  elActiveFemale.textContent = f;
  elActiveTotal.textContent = m + f;
}

function renderMonthSummary() {
  const mm = String(state.monthIndex + 1).padStart(2, "0");
  const totals = getCurrentMonthTotals();
  elMonthSummary.innerHTML = `
    <div>表示月：<strong>${state.year}-${mm}</strong>（日曜のみ）</div>
    <div>月累計：男性 <strong>${totals.male}</strong> ／ 女性 <strong>${totals.female}</strong> ／ 男女合計 <strong>${totals.total}</strong></div>
  `;
}

/************************************************************
 * 年集計テーブル
 ************************************************************/
function renderYearTable() {
  const y = state.year;
  elYearLabel.textContent = y;
  const yt = getYearTotals(y);
  let rows = "";
  for (const m of yt.months) {
    const mm = String(m.monthIndex + 1).padStart(2, "0");
    rows += `<tr><td>${mm}月（${y}-${mm}）</td><td class="num">${m.male}</td><td class="num">${m.female}</td><td class="num">${m.total}</td></tr>`;
  }
  rows += `<tr class="yearTotalRow"><td>年間合計</td><td class="num">${yt.male}</td><td class="num">${yt.female}</td><td class="num">${yt.total}</td></tr>`;
  elYearBody.innerHTML = rows;
}

/************************************************************
 * カード描画
 ************************************************************/
function escapeHtml(s) {
  return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#39;");
}

function renderRosterTableHTML(iso, view) {
  const roster = (view === "male") ? rosterMale : rosterFemale;
  let rows = "";
  for (const person of roster) {
    const checked = getCheck(iso, person.id);
    rows += `<tr><td class="nameCell">${escapeHtml(person.label)}</td><td style="text-align:center"><input type="checkbox" data-roster-id="${person.id}" ${checked ? "checked" : ""} /></td></tr>`;
  }
  return `<table><thead><tr><th>名前</th><th style="text-align:center">出席</th></tr></thead><tbody>${rows}</tbody></table>`;
}

function updateCardBadges(card, iso) {
  const maleCnt = countChecked(iso, "male");
  const femaleCnt = countChecked(iso, "female");
  const totalCnt = maleCnt + femaleCnt;
  const box = card.querySelector(".badgeBox");
  if (!box) return;
  if (state.view === "male") box.innerHTML = `<div class="badge"><span>男性</span> <strong>${maleCnt}</strong></div>`;
  else if (state.view === "female") box.innerHTML = `<div class="badge"><span>女性</span> <strong>${femaleCnt}</strong></div>`;
  else box.innerHTML = `<div class="badge"><span>男性</span> <strong>${maleCnt}</strong></div><div class="badge"><span>女性</span> <strong>${femaleCnt}</strong></div><div class="badge"><span>合計</span> <strong>${totalCnt}</strong></div>`;
}

function renderCards() {
  const sundays = getSundaysOfMonth(state.year, state.monthIndex);
  elCards.innerHTML = "";
  if (sundays.length === 0) {
    elCards.innerHTML = `<div class="hint">この月には日曜日がありません</div>`;
    requestAnimationFrame(updateScrollButtons);
    return;
  }

  const isoList = sundays.map(toISODate);
  if (!state.activeIso || !isoList.includes(state.activeIso)) {
    setActiveIso(isoList[0]);
  } else {
    setActiveIso(state.activeIso);
  }

  for (const dt of sundays) {
    const iso = toISODate(dt);
    const card = document.createElement("section");
    card.className = "card" + (iso === state.activeIso ? " active" : "");
    card.dataset.iso = iso;

    const maleCnt = countChecked(iso, "male");
    const femaleCnt = countChecked(iso, "female");
    const totalCnt = maleCnt + femaleCnt;

    const badgeHtml = (() => {
      if (state.view === "male") return `<div class="badge"><span>男性</span> <strong>${maleCnt}</strong></div>`;
      if (state.view === "female") return `<div class="badge"><span>女性</span> <strong>${femaleCnt}</strong></div>`;
      return `<div class="badge"><span>男性</span> <strong>${maleCnt}</strong></div><div class="badge"><span>女性</span> <strong>${femaleCnt}</strong></div><div class="badge"><span>合計</span> <strong>${totalCnt}</strong></div>`;
    })();

    card.innerHTML = `
      <div class="cardHead">
        <div>
          <div class="dateBig">${toMMDD(dt)}</div>
          <div class="dateSmall">${state.year}年 / ${jpWeekday(dt)}曜日（${iso}）</div>
        </div>
        <div class="badgeBox">${badgeHtml}</div>
      </div>
      <div class="list">
        ${state.view === "total"
          ? `<div class="hint">※「男女合計」タブではチェック表は表示しません。人数と月累計・年集計を確認できます。</div><div style="margin-top:10px" class="hint">男性/女性タブに切り替えてチェックしてください。</div>`
          : `<div class="listHeader"><div class="label">${state.view === "male" ? "男性名簿" : "女性名簿"}</div></div>${renderRosterTableHTML(iso, state.view)}`
        }
      </div>
    `;

    // カードタップでアクティブ切替
    card.addEventListener("click", () => {
      setActiveIso(iso);
      for (const c of elCards.querySelectorAll(".card")) c.classList.remove("active");
      card.classList.add("active");
    });

    // チェックイベント → DB保存
    if (state.view !== "total") {
      card.addEventListener("change", async (e) => {
        const t = e.target;
        if (t && t.matches('input[type="checkbox"][data-roster-id]')) {
          const rosterId = parseInt(t.getAttribute("data-roster-id"));
          await saveCheck(iso, rosterId, t.checked);
          updateCardBadges(card, iso);
          renderMonthSummary();
          renderYearTable();
          setActiveIso(state.activeIso);
        }
      });
    }

    elCards.appendChild(card);
  }
  requestAnimationFrame(updateScrollButtons);
}

/************************************************************
 * 名簿行数の増減
 ************************************************************/
function updateRosterControls() {
  const target = document.getElementById("rosterTarget");
  const hint = document.getElementById("rosterRowHint");
  const btns = ["rosterAdd10","rosterRemove10","rosterAdd1","rosterRemove1"].map(id => document.getElementById(id));
  const can = (state.view === "male" || state.view === "female");
  const label = can ? (state.view === "male" ? "男性名簿" : "女性名簿") : "（男女合計では操作不可）";
  if (target) target.textContent = label;
  btns.forEach(b => { if (b) b.disabled = !can; });
  if (hint) {
    const roster = (state.view === "male") ? rosterMale : rosterFemale;
    hint.textContent = can ? `（現在の名簿行：${roster.length}行）` : "（男女合計タブでは操作不可）";
  }
}

/************************************************************
 * CSV読み込み処理
 ************************************************************/
function parseCsvLines(text) {
  const lines = text.split(/\r?\n/);
  const names = [];
  for (let line of lines) {
    line = line.trim();
    if (!line) continue;
    const parts = line.split(",").map(p => p.trim()).filter(p => p);
    if (parts.length > 0) names.push(parts.join(""));
  }
  return names;
}

function readCsvFile(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = e => resolve(e.target.result);
    reader.onerror = reject;
    reader.readAsText(file, "UTF-8");
  });
}

async function handleCsvImport(file, gender, addMode) {
  try {
    const text = await readCsvFile(file);
    const names = parseCsvLines(text);
    if (names.length === 0) { alert("有効な名前が見つかりませんでした。"); return; }
    
    if (!addMode && !confirm(`${gender === "male" ? "男性" : "女性"}名簿を更新します。\n既存の出欠データがリセットされます。よろしいですか？`)) return;
    
    const result = await apiPost("api/roster.php", {
      action: "import_csv",
      gender: gender,
      names: names,
      mode: addMode ? "append" : "replace",
    });
    
    if (result.success) {
      alert(`名簿を${addMode ? "追加" : "更新"}しました（${names.length}名）`);
      await loadRoster();
      if (!addMode) { state.attendance = {}; await loadAttendanceForYear(); }
      syncUI();
    } else {
      alert("エラー: " + result.message);
    }
  } catch (err) {
    alert("CSVの読み込みに失敗しました: " + err.message);
  }
}

/************************************************************
 * 月移動・同期
 ************************************************************/
async function changeMonth(delta) {
  const d = new Date(state.year, state.monthIndex + delta, 1);
  state.year = d.getFullYear();
  state.monthIndex = d.getMonth();
  await loadAttendance(state.year, state.monthIndex + 1);
  syncUI();
}

function syncUI() {
  buildYearSelect();
  buildMonthSelect();
  setTabs();
  renderMonthSummary();
  renderYearTable();
  renderCards();
  updateRosterControls();
}

/************************************************************
 * 年間データ読み込み
 ************************************************************/
async function loadAttendanceForYear() {
  for (let m = 1; m <= 12; m++) {
    await loadAttendance(state.year, m);
  }
}

/************************************************************
 * イベントリスナー
 ************************************************************/
elPrev.addEventListener("click", () => changeMonth(-1));
elNext.addEventListener("click", () => changeMonth(1));

elMonthSelect.addEventListener("change", async () => {
  const [yStr, mStr] = elMonthSelect.value.split("-");
  state.year = Number(yStr);
  state.monthIndex = Number(mStr);
  await loadAttendance(state.year, state.monthIndex + 1);
  syncUI();
});

elYearSelect.addEventListener("change", async () => {
  state.year = Number(elYearSelect.value);
  await loadAttendanceForYear();
  syncUI();
});

elTabMale.addEventListener("click", () => { state.view = "male"; syncUI(); });
elTabFemale.addEventListener("click", () => { state.view = "female"; syncUI(); });
elTabTotal.addEventListener("click", () => { state.view = "total"; syncUI(); });

// CSV出力
const elExportCsv = document.getElementById("exportCsv");
if (elExportCsv) {
  elExportCsv.addEventListener("click", () => {
    if (!confirm("表示中の月と年集計をCSVで出力します。よろしいですか？")) return;
    const m = state.monthIndex + 1;
    window.location.href = `api/export_csv.php?year=${state.year}&month=${m}`;
  });
}

// 名簿初期化
const elInitRoster = document.getElementById("initRoster");
if (elInitRoster) {
  elInitRoster.addEventListener("click", async () => {
    if (!confirm("名簿を初期値にリセットし、出欠データも全て削除します。\nよろしいですか？")) return;
    const result = await apiPost("api/roster.php", { action: "initialize" });
    if (result.success) {
      state.attendance = {};
      await loadRoster();
      syncUI();
      alert("名簿と出欠データを初期化しました。");
    } else {
      alert("エラー: " + result.message);
    }
  });
}

// 名簿行追加・削除
function setupRosterButtons() {
  const ids = [
    { id: "rosterAdd10", action: "add_rows", count: 10 },
    { id: "rosterAdd1", action: "add_rows", count: 1 },
    { id: "rosterRemove10", action: "remove_rows", count: 10 },
    { id: "rosterRemove1", action: "remove_rows", count: 1 },
  ];
  for (const { id, action, count } of ids) {
    const el = document.getElementById(id);
    if (!el) continue;
    el.addEventListener("click", async () => {
      const gender = (state.view === "male" || state.view === "female") ? state.view : null;
      if (!gender) return;
      if (action === "remove_rows" && !confirm(`末尾の空欄行を${count}行削除します。よろしいですか？`)) return;
      const result = await apiPost("api/roster.php", { action, gender, count });
      if (result.success) {
        await loadRoster();
        syncUI();
      } else {
        alert(result.message);
      }
    });
  }
}

// CSV読み込みボタン
function setupCsvImportButtons() {
  const configs = [
    { fileId: "maleCsv", applyId: "applyMaleCsv", statusId: "maleCsvStatus", gender: "male", addMode: false },
    { fileId: "femaleCsv", applyId: "applyFemaleCsv", statusId: "femaleCsvStatus", gender: "female", addMode: false },
    { fileId: "maleCsvAdd", applyId: "applyMaleCsvAdd", statusId: "maleCsvAddStatus", gender: "male", addMode: true },
    { fileId: "femaleCsvAdd", applyId: "applyFemaleCsvAdd", statusId: "femaleCsvAddStatus", gender: "female", addMode: true },
  ];
  for (const cfg of configs) {
    const fileEl = document.getElementById(cfg.fileId);
    const applyEl = document.getElementById(cfg.applyId);
    const statusEl = document.getElementById(cfg.statusId);
    if (!fileEl || !applyEl) continue;

    fileEl.addEventListener("change", (e) => {
      if (e.target.files && e.target.files[0]) {
        statusEl.textContent = `選択: ${e.target.files[0].name}`;
        statusEl.style.color = "var(--accent2)";
        applyEl.disabled = false;
      } else {
        statusEl.textContent = "ファイルが選択されていません";
        statusEl.style.color = "";
        applyEl.disabled = true;
      }
    });

    applyEl.addEventListener("click", async () => {
      if (fileEl.files && fileEl.files[0]) {
        await handleCsvImport(fileEl.files[0], cfg.gender, cfg.addMode);
        fileEl.value = "";
        statusEl.textContent = "ファイルが選択されていません";
        statusEl.style.color = "";
        applyEl.disabled = true;
      }
    });
  }
}

/************************************************************
 * 初期化
 ************************************************************/
(async function init() {
  // 名簿が空なら初期データ投入を確認
  await loadRoster();
  
  if (rosterMale.length === 0 && rosterFemale.length === 0) {
    if (confirm("名簿データがありません。初期名簿を登録しますか？")) {
      await apiPost("api/roster.php", { action: "initialize" });
      await loadRoster();
    }
  }
  
  // 年間の出欠データを読み込み
  await loadAttendanceForYear();
  
  syncUI();
  setupRosterButtons();
  setupCsvImportButtons();
})();
