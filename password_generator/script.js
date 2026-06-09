'use strict';

/* ===================================================
   定数
   =================================================== */
const CHARS_UPPER  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const CHARS_LOWER  = 'abcdefghijklmnopqrstuvwxyz';
const CHARS_DIGIT  = '0123456789';
const CHARS_SYMBOL = '!@#$%^&*()-_=+[]{}|;:,.<>?';
const AMBIGUOUS    = new Set([...'0O1lI']);

const STRENGTH_LABELS = ['非常に弱い', '弱い', '普通', '強い', '非常に強い'];
const STRENGTH_COLORS = ['var(--s0)', 'var(--s1)', 'var(--s2)', 'var(--s3)', 'var(--s4)'];
const STRENGTH_WIDTHS = ['15%', '30%', '55%', '80%', '100%'];

const MAX_HISTORY = 20;

/* ===================================================
   状態
   =================================================== */
let currentMode     = 'random';
let currentWordCount = 3;
let currentPhraseCount = 3;
let lastPassword    = '';
let toastTimer      = null;

/* ===================================================
   初期化
   =================================================== */
document.addEventListener('DOMContentLoaded', () => {
  initTabs();
  initRangeSlider();
  initSegButtons();
  initGenerateButtons();
  initCopyButton();
  initHistoryPanel();
  renderHistory();
  updateWordExample();
  updatePhraseExample();
});

/* ===================================================
   タブ
   =================================================== */
function initTabs() {
  document.querySelectorAll('.tabs .tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.tabs .tab').forEach(t => {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
      });
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');
      currentMode = tab.dataset.tab;
      document.getElementById(`tab-${currentMode}`).classList.add('active');
    });
  });
}

/* ===================================================
   スライダー
   =================================================== */
function initRangeSlider() {
  const slider  = document.getElementById('length');
  const display = document.getElementById('length-display');
  slider.addEventListener('input', () => { display.textContent = slider.value; });
}

/* ===================================================
   セグメントボタン（単語数）
   =================================================== */
function initSegButtons() {
  document.querySelectorAll('#tab-word .seg-buttons').forEach(group => {
    group.querySelectorAll('.seg').forEach(btn => {
      btn.addEventListener('click', () => {
        group.querySelectorAll('.seg').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentWordCount = parseInt(btn.dataset.value, 10);
        updateWordExample();
      });
    });
  });

  document.querySelectorAll('#tab-phrase .seg-buttons').forEach(group => {
    group.querySelectorAll('.seg').forEach(btn => {
      btn.addEventListener('click', () => {
        group.querySelectorAll('.seg').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentPhraseCount = parseInt(btn.dataset.value, 10);
        updatePhraseExample();
      });
    });
  });

  /* チェックボックス変更でサンプル更新 */
  ['word-capitalize','word-digits','phrase-digits'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', () => {
      if (id.startsWith('word')) updateWordExample();
      else updatePhraseExample();
    });
  });
}

/* ===================================================
   生成ボタン群
   =================================================== */
function initGenerateButtons() {
  document.getElementById('btn-generate').addEventListener('click', () => {
    const pw = generateOne();
    showResult(pw);
    addHistory(pw);
  });

  document.querySelectorAll('.btn-bulk').forEach(btn => {
    btn.addEventListener('click', () => {
      const n = parseInt(btn.dataset.count, 10);
      const passwords = Array.from({ length: n }, () => generateOne());
      showBulkResults(passwords);
      passwords.forEach(addHistory);
    });
  });
}

/* ===================================================
   コピーボタン（メイン結果）
   =================================================== */
function initCopyButton() {
  document.getElementById('btn-copy').addEventListener('click', () => {
    if (!lastPassword) return;
    copyToClipboard(lastPassword, document.getElementById('btn-copy'));
  });
}

/* ===================================================
   パスワード生成: エントリポイント
   =================================================== */
function generateOne() {
  switch (currentMode) {
    case 'random': return generateRandom();
    case 'word':   return generateWord();
    case 'phrase': return generatePhrase();
  }
  return generateRandom();
}

/* ===================================================
   ランダム生成
   =================================================== */
function generateRandom() {
  const length     = parseInt(document.getElementById('length').value, 10);
  const useUpper   = document.getElementById('use-upper').checked;
  const useLower   = document.getElementById('use-lower').checked;
  const useDigit   = document.getElementById('use-digit').checked;
  const useSymbol  = document.getElementById('use-symbol').checked;
  const excAmbig   = document.getElementById('exclude-ambig').checked;

  let pool = '';
  if (useUpper)  pool += CHARS_UPPER;
  if (useLower)  pool += CHARS_LOWER;
  if (useDigit)  pool += CHARS_DIGIT;
  if (useSymbol) pool += CHARS_SYMBOL;

  if (excAmbig) {
    pool = [...pool].filter(c => !AMBIGUOUS.has(c)).join('');
  }

  if (!pool) {
    showToast('文字種を最低1つ選択してください');
    return '(文字種未選択)';
  }

  /* 各文字種から最低1文字を確実に含める */
  const required = [];
  if (useUpper)  required.push(pick(filterPool(CHARS_UPPER,  excAmbig)));
  if (useLower)  required.push(pick(filterPool(CHARS_LOWER,  excAmbig)));
  if (useDigit)  required.push(pick(filterPool(CHARS_DIGIT,  excAmbig)));
  if (useSymbol) required.push(pick(filterPool(CHARS_SYMBOL, excAmbig)));

  const result = required.slice(0, length);
  while (result.length < length) result.push(pick(pool));

  return shuffle(result).join('');
}

function filterPool(chars, excAmbig) {
  if (!excAmbig) return chars;
  return [...chars].filter(c => !AMBIGUOUS.has(c)).join('');
}

/* ===================================================
   単語ベース生成
   =================================================== */
function generateWord() {
  const n          = currentWordCount;
  const capitalize = document.getElementById('word-capitalize').checked;
  const addDigits  = document.getElementById('word-digits').checked;

  const words = pickWords(ENGLISH_WORDS, n).map(w =>
    capitalize ? w.charAt(0).toUpperCase() + w.slice(1) : w
  );

  let pw = words.join('-');
  if (addDigits) pw += '-' + String(rand(10, 99));
  return pw;
}

/* ===================================================
   パスフレーズ生成（ひらがな→ローマ字）
   =================================================== */
function generatePhrase() {
  const n         = currentPhraseCount;
  const addDigits = document.getElementById('phrase-digits').checked;

  const words = pickWords(HIRAGANA_WORDS, n);
  /* "tsuki2" のような衝突防止エイリアスを除去 */
  const cleaned = words.map(w => w.replace(/\d+$/, ''));

  let pw = cleaned.join('-');
  if (addDigits) {
    const year = rand(2020, 2099);
    pw += '-' + year;
  }
  return pw;
}

/* ===================================================
   例示更新
   =================================================== */
function updateWordExample() {
  const n          = currentWordCount;
  const capitalize = document.getElementById('word-capitalize').checked;
  const addDigits  = document.getElementById('word-digits').checked;

  const sample = ['correct','horse','battery','staple','noble'].slice(0, n)
    .map(w => capitalize ? w.charAt(0).toUpperCase() + w.slice(1) : w)
    .join('-');

  const el = document.getElementById('word-example');
  if (el) el.textContent = sample + (addDigits ? '-42' : '');
}

function updatePhraseExample() {
  const n         = currentPhraseCount;
  const addDigits = document.getElementById('phrase-digits').checked;
  const samples   = ['sakura','yume','tsuki','kaze','hikari'].slice(0, n);
  const el = document.getElementById('phrase-example');
  if (el) el.textContent = samples.join('-') + (addDigits ? '-2047' : '');
}

/* ===================================================
   結果表示
   =================================================== */
function showResult(pw) {
  lastPassword = pw;

  const card    = document.getElementById('result-card');
  const display = document.getElementById('password-display');
  display.textContent = pw;
  card.hidden = false;

  updateStrength(pw);
  resetCopyButton();
}

function updateStrength(pw) {
  let score = 0;
  let timeStr = '';

  if (typeof zxcvbn === 'function') {
    const result = zxcvbn(pw);
    score   = result.score; /* 0-4 */
    const crackTime = result.crack_times_display.offline_slow_hashing_1e4_per_second;
    timeStr = `解読推定時間: ${crackTime}`;
  } else {
    /* zxcvbn未ロード時の簡易フォールバック */
    score = Math.min(4, Math.floor(pw.length / 8));
  }

  const bar   = document.getElementById('strength-bar');
  const label = document.getElementById('strength-label');
  const time  = document.getElementById('strength-time');

  bar.style.width           = STRENGTH_WIDTHS[score];
  bar.style.backgroundColor = STRENGTH_COLORS[score];
  label.textContent         = STRENGTH_LABELS[score];
  label.style.color         = STRENGTH_COLORS[score];
  time.textContent          = timeStr;
}

function resetCopyButton() {
  const btn = document.getElementById('btn-copy');
  btn.classList.remove('copied');
  btn.innerHTML = `
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
      <rect x="9" y="9" width="13" height="13" rx="2"/>
      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
    </svg>`;
}

/* ===================================================
   一括結果表示
   =================================================== */
function showBulkResults(passwords) {
  const card = document.getElementById('bulk-card');
  const list = document.getElementById('bulk-list');
  list.innerHTML = '';

  passwords.forEach(pw => {
    const li = document.createElement('li');
    li.className = 'bulk-item';
    li.innerHTML = `
      <span class="bulk-item-pw">${escHtml(pw)}</span>
      <button class="bulk-copy" type="button">コピー</button>`;
    li.querySelector('.bulk-copy').addEventListener('click', e => {
      copyToClipboard(pw, e.currentTarget);
    });
    list.appendChild(li);
  });

  card.hidden = false;

  document.getElementById('copy-all').onclick = () => {
    copyToClipboard(passwords.join('\n'), null);
    showToast(`${passwords.length}件のパスワードをコピーしました`);
  };
}

/* ===================================================
   履歴管理
   =================================================== */
function loadHistory() {
  try {
    return JSON.parse(localStorage.getItem('pw_history') || '[]');
  } catch { return []; }
}

function saveHistory(arr) {
  localStorage.setItem('pw_history', JSON.stringify(arr));
}

function addHistory(pw) {
  const hist = loadHistory();
  /* 重複排除（同一パスワードは先頭に移動） */
  const idx = hist.indexOf(pw);
  if (idx !== -1) hist.splice(idx, 1);
  hist.unshift(pw);
  if (hist.length > MAX_HISTORY) hist.length = MAX_HISTORY;
  saveHistory(hist);
  renderHistory();
}

function renderHistory() {
  const hist = loadHistory();
  const list = document.getElementById('history-list');
  const count = document.getElementById('history-count');
  count.textContent = `(${hist.length}件)`;

  list.innerHTML = '';
  if (hist.length === 0) {
    list.innerHTML = '<li class="history-empty">履歴はまだありません</li>';
    return;
  }

  hist.forEach(pw => {
    const li = document.createElement('li');
    li.className = 'history-item';

    const masked  = pw.slice(0, 3) + '****';
    const pwSpan  = document.createElement('span');
    pwSpan.className   = 'history-pw masked';
    pwSpan.textContent = masked;
    pwSpan.title       = 'クリックで表示';

    let revealed = false;
    let revealTimer;
    pwSpan.addEventListener('click', () => {
      if (!revealed) {
        pwSpan.textContent = pw;
        pwSpan.classList.remove('masked');
        revealed = true;
        clearTimeout(revealTimer);
        revealTimer = setTimeout(() => {
          pwSpan.textContent = masked;
          pwSpan.classList.add('masked');
          revealed = false;
        }, 5000);
      } else {
        pwSpan.textContent = masked;
        pwSpan.classList.add('masked');
        revealed = false;
        clearTimeout(revealTimer);
      }
    });

    const copyBtn = document.createElement('button');
    copyBtn.className   = 'history-copy';
    copyBtn.textContent = 'コピー';
    copyBtn.addEventListener('click', () => copyToClipboard(pw, copyBtn));

    li.append(pwSpan, copyBtn);
    list.appendChild(li);
  });
}

function initHistoryPanel() {
  document.getElementById('clear-history').addEventListener('click', () => {
    if (!confirm('履歴を全て削除しますか？')) return;
    localStorage.removeItem('pw_history');
    renderHistory();
    showToast('履歴をクリアしました');
  });
}

/* ===================================================
   クリップボードコピー
   =================================================== */
function copyToClipboard(text, buttonEl) {
  navigator.clipboard.writeText(text).then(() => {
    showToast('コピーしました');
    if (buttonEl) {
      const orig = buttonEl.innerHTML || buttonEl.textContent;
      if (buttonEl.id === 'btn-copy') {
        buttonEl.classList.add('copied');
        buttonEl.innerHTML = `
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"/>
          </svg>`;
        setTimeout(() => resetCopyButton(), 2000);
      } else {
        const origText = buttonEl.textContent;
        buttonEl.textContent = '✓';
        setTimeout(() => { buttonEl.textContent = origText; }, 2000);
      }
    }
  }).catch(() => {
    /* フォールバック */
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity  = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
    showToast('コピーしました');
  });
}

/* ===================================================
   ユーティリティ
   =================================================== */
function pick(str) {
  if (!str) return '';
  const arr = new Uint32Array(1);
  crypto.getRandomValues(arr);
  return str[arr[0] % str.length];
}

function rand(min, max) {
  const arr = new Uint32Array(1);
  crypto.getRandomValues(arr);
  return min + (arr[0] % (max - min + 1));
}

function shuffle(arr) {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = rand(0, i);
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

function pickWords(dict, n) {
  const pool = [...dict];
  const result = [];
  for (let i = 0; i < n; i++) {
    if (!pool.length) break;
    const idx = rand(0, pool.length - 1);
    result.push(pool.splice(idx, 1)[0]);
  }
  return result;
}

function escHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function showToast(msg) {
  const toast = document.getElementById('toast');
  clearTimeout(toastTimer);
  toast.textContent = msg;
  toast.classList.add('show');
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
}
