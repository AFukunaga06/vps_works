'use strict';

let qrCode    = null;
let logoDataUrl = null;
let currentTab  = 'url';
let toastTimer  = null;

document.addEventListener('DOMContentLoaded', () => {
  initTabs();
  initColorPickers();
  initLogoUpload();
  initAutoGenerate();

  document.getElementById('generate-btn').addEventListener('click', generateQR);
  document.getElementById('dl-png').addEventListener('click', downloadPNG);
  document.getElementById('dl-svg').addEventListener('click', downloadSVG);
});

/* ---------- Tabs ---------- */
function initTabs() {
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.tab').forEach(t => {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
      });
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');
      currentTab = tab.dataset.tab;
      document.getElementById(`tab-${currentTab}`).classList.add('active');
    });
  });
}

/* ---------- Color Pickers ---------- */
function initColorPickers() {
  ['fg', 'bg'].forEach(type => {
    const input = document.getElementById(`${type}-color`);
    const span  = document.getElementById(`${type}-color-text`);
    input.addEventListener('input', () => { span.textContent = input.value; });
  });
}

/* ---------- Logo Upload ---------- */
function initLogoUpload() {
  const fileInput = document.getElementById('logo-file');
  const logoName  = document.getElementById('logo-name');

  fileInput.addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = ev => {
      logoDataUrl = ev.target.result;
      logoName.textContent = `選択中: ${file.name}`;
      logoName.hidden = false;

      const lvl = document.getElementById('error-level');
      if (lvl.value !== 'H') {
        lvl.value = 'H';
        showToast('ロゴ使用のため誤り訂正レベルを H に変更しました');
      }
    };
    reader.readAsDataURL(file);
  });

  document.getElementById('clear-logo').addEventListener('click', () => {
    logoDataUrl = null;
    fileInput.value = '';
    logoName.hidden = true;
    showToast('ロゴをクリアしました');
  });
}

/* ---------- Auto-Generate on Input ---------- */
function initAutoGenerate() {
  const ids = [
    'url-text',
    'vcard-name', 'vcard-tel', 'vcard-email', 'vcard-org', 'vcard-addr',
    'wifi-ssid', 'wifi-password', 'wifi-security',
    'email-addr', 'email-subject', 'email-body',
    'fg-color', 'bg-color', 'qr-size', 'error-level',
  ];
  const debouncedGen = debounce(generateQR, 600);
  ids.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input',  debouncedGen);
    el.addEventListener('change', debouncedGen);
  });
}

/* ---------- QR Data Builders ---------- */
function getQRData() {
  switch (currentTab) {
    case 'url':   return getUrlData();
    case 'vcard': return buildVCard();
    case 'wifi':  return buildWiFi();
    case 'email': return buildEmail();
  }
  return null;
}

function getUrlData() {
  const v = document.getElementById('url-text').value.trim();
  return v || null;
}

function buildVCard() {
  const name  = document.getElementById('vcard-name').value.trim();
  const tel   = document.getElementById('vcard-tel').value.trim();
  const email = document.getElementById('vcard-email').value.trim();
  const org   = document.getElementById('vcard-org').value.trim();
  const addr  = document.getElementById('vcard-addr').value.trim();

  if (!name && !tel && !email && !org && !addr) return null;

  const parts = name.split(/\s+/);
  const family = parts[0] || '';
  const given  = parts.slice(1).join(' ');

  let v = 'BEGIN:VCARD\r\nVERSION:3.0\r\n';
  if (name)  v += `FN:${name}\r\nN:${family};${given};;;\r\n`;
  if (tel)   v += `TEL;TYPE=CELL:${tel}\r\n`;
  if (email) v += `EMAIL:${email}\r\n`;
  if (org)   v += `ORG:${org}\r\n`;
  if (addr)  v += `ADR:;;${addr};;;;\r\n`;
  v += 'END:VCARD';
  return v;
}

function buildWiFi() {
  const ssid     = document.getElementById('wifi-ssid').value.trim();
  if (!ssid) return null;
  const password = document.getElementById('wifi-password').value;
  const security = document.getElementById('wifi-security').value;
  return `WIFI:T:${security};S:${wifiEscape(ssid)};P:${wifiEscape(password)};;`;
}

function wifiEscape(str) {
  return str.replace(/([\\;,":])/, '\\$1');
}

function buildEmail() {
  const addr = document.getElementById('email-addr').value.trim();
  if (!addr) return null;
  const subject = document.getElementById('email-subject').value.trim();
  const body    = document.getElementById('email-body').value.trim();
  let mailto = `mailto:${addr}`;
  const params = [];
  if (subject) params.push(`subject=${encodeURIComponent(subject)}`);
  if (body)    params.push(`body=${encodeURIComponent(body)}`);
  if (params.length) mailto += '?' + params.join('&');
  return mailto;
}

/* ---------- Generate ---------- */
function generateQR() {
  const data = getQRData();
  if (!data) {
    resetPreview();
    return;
  }

  const fgColor    = document.getElementById('fg-color').value;
  const bgColor    = document.getElementById('bg-color').value;
  const size       = parseInt(document.getElementById('qr-size').value, 10);
  const errorLevel = document.getElementById('error-level').value;

  const options = {
    width:  size,
    height: size,
    data,
    image: logoDataUrl || undefined,
    dotsOptions:       { color: fgColor, type: 'rounded' },
    backgroundOptions: { color: bgColor },
    imageOptions:      { crossOrigin: 'anonymous', margin: 10, imageSize: 0.3 },
    qrOptions:         { errorCorrectionLevel: errorLevel },
  };

  const previewEl = document.getElementById('qr-preview');

  /* 毎回クリーンに再作成（サイズ変更を確実に反映） */
  previewEl.innerHTML = '';
  document.getElementById('placeholder-text').style.display = 'none';

  try {
    qrCode = new QRCodeStyling(options);
    qrCode.append(previewEl);
    document.getElementById('download-buttons').hidden = false;
  } catch (err) {
    resetPreview();
    showToast('QRコードの生成に失敗しました: ' + err.message);
  }
}

function resetPreview() {
  document.getElementById('qr-preview').innerHTML = '';
  document.getElementById('placeholder-text').style.display = '';
  document.getElementById('download-buttons').hidden = true;
  qrCode = null;
}

/* ---------- Download ---------- */
function downloadPNG() {
  if (!qrCode) return;
  qrCode.download({ name: 'qrcode', extension: 'png' });
}

function downloadSVG() {
  if (!qrCode) return;
  qrCode.download({ name: 'qrcode', extension: 'svg' });
}

/* ---------- Utilities ---------- */
function debounce(fn, ms) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), ms);
  };
}

function showToast(msg) {
  const toast = document.getElementById('toast');
  clearTimeout(toastTimer);
  toast.textContent = msg;
  toast.classList.add('show');
  toastTimer = setTimeout(() => toast.classList.remove('show'), 3200);
}
