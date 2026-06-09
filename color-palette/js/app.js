// ===========================
// 色変換ユーティリティ
// ===========================

const clamp = (n, min, max) => Math.max(min, Math.min(max, n));

function hexToRgb(hex) {
  const m = /^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/.exec(hex.trim());
  if (!m) return null;
  let h = m[1];
  if (h.length === 3) h = h.split('').map(c => c + c).join('');
  return {
    r: parseInt(h.slice(0, 2), 16),
    g: parseInt(h.slice(2, 4), 16),
    b: parseInt(h.slice(4, 6), 16),
  };
}

function rgbToHex(r, g, b) {
  const toHex = n => clamp(Math.round(n), 0, 255).toString(16).padStart(2, '0');
  return '#' + toHex(r) + toHex(g) + toHex(b);
}

function rgbToHsl(r, g, b) {
  r /= 255; g /= 255; b /= 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b);
  let h, s, l = (max + min) / 2;

  if (max === min) {
    h = s = 0;
  } else {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r: h = (g - b) / d + (g < b ? 6 : 0); break;
      case g: h = (b - r) / d + 2; break;
      case b: h = (r - g) / d + 4; break;
    }
    h /= 6;
  }
  return { h: h * 360, s: s * 100, l: l * 100 };
}

function hslToRgb(h, s, l) {
  h = ((h % 360) + 360) % 360 / 360;
  s = clamp(s, 0, 100) / 100;
  l = clamp(l, 0, 100) / 100;

  const hue2rgb = (p, q, t) => {
    if (t < 0) t += 1;
    if (t > 1) t -= 1;
    if (t < 1/6) return p + (q - p) * 6 * t;
    if (t < 1/2) return q;
    if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
    return p;
  };

  let r, g, b;
  if (s === 0) {
    r = g = b = l;
  } else {
    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    const p = 2 * l - q;
    r = hue2rgb(p, q, h + 1/3);
    g = hue2rgb(p, q, h);
    b = hue2rgb(p, q, h - 1/3);
  }
  return { r: r * 255, g: g * 255, b: b * 255 };
}

// ===========================
// パレット生成
// ===========================

function buildMonochromatic(hsl) {
  const out = [];
  const lightnesses = [90, 75, 60, 45, 30, 15];
  for (const l of lightnesses) {
    const { r, g, b } = hslToRgb(hsl.h, hsl.s, l);
    out.push(rgbToHex(r, g, b));
  }
  return out;
}

function buildAnalogous(hsl) {
  const offsets = [-40, -20, 0, 20, 40];
  return offsets.map(off => {
    const { r, g, b } = hslToRgb(hsl.h + off, hsl.s, hsl.l);
    return rgbToHex(r, g, b);
  });
}

function buildComplementary(hsl) {
  const a = hslToRgb(hsl.h, hsl.s, hsl.l);
  const b = hslToRgb(hsl.h + 180, hsl.s, hsl.l);
  return [rgbToHex(a.r, a.g, a.b), rgbToHex(b.r, b.g, b.b)];
}

function buildTriadic(hsl) {
  return [0, 120, 240].map(off => {
    const { r, g, b } = hslToRgb(hsl.h + off, hsl.s, hsl.l);
    return rgbToHex(r, g, b);
  });
}

function buildTetradic(hsl) {
  return [0, 90, 180, 270].map(off => {
    const { r, g, b } = hslToRgb(hsl.h + off, hsl.s, hsl.l);
    return rgbToHex(r, g, b);
  });
}

// ===========================
// レンダリング
// ===========================

const els = {
  picker:     document.getElementById('picker'),
  hex:        document.getElementById('hex'),
  r:          document.getElementById('r'),
  g:          document.getElementById('g'),
  b:          document.getElementById('b'),
  rgbString:  document.getElementById('rgb-string'),
  hslString:  document.getElementById('hsl-string'),
  preview:    document.getElementById('preview'),
  toast:      document.getElementById('toast'),
  paletteMono:   document.getElementById('palette-mono'),
  paletteAnalog: document.getElementById('palette-analog'),
  paletteComp:   document.getElementById('palette-comp'),
  paletteTriad:  document.getElementById('palette-triad'),
  paletteTetra:  document.getElementById('palette-tetra'),
};

function renderSwatches(container, hexList) {
  container.innerHTML = '';
  for (const hex of hexList) {
    const rgb = hexToRgb(hex);
    const sw = document.createElement('div');
    sw.className = 'swatch';
    sw.innerHTML = `
      <div class="swatch-color" style="background:${hex}"></div>
      <div class="swatch-info">
        <div class="swatch-hex">${hex.toUpperCase()}</div>
        <div class="swatch-rgb">rgb(${rgb.r}, ${rgb.g}, ${rgb.b})</div>
      </div>
    `;
    sw.addEventListener('click', () => copyToClipboard(hex.toUpperCase()));
    container.appendChild(sw);
  }
}

function copyToClipboard(text) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(() => showToast(`${text} をコピーしました`));
  } else {
    // フォールバック
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast(`${text} をコピーしました`);
  }
}

let toastTimer = null;
function showToast(msg) {
  els.toast.textContent = msg;
  els.toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => els.toast.classList.remove('show'), 1800);
}

// ===========================
// 状態同期（HEX/RGB/picker をすべて連動）
// ===========================

function update(rgb) {
  const r = clamp(Math.round(rgb.r), 0, 255);
  const g = clamp(Math.round(rgb.g), 0, 255);
  const b = clamp(Math.round(rgb.b), 0, 255);
  const hex = rgbToHex(r, g, b);
  const hsl = rgbToHsl(r, g, b);

  els.hex.value        = hex.toUpperCase();
  els.picker.value     = hex;
  els.r.value          = r;
  els.g.value          = g;
  els.b.value          = b;
  els.rgbString.value  = `rgb(${r}, ${g}, ${b})`;
  els.hslString.value  = `hsl(${Math.round(hsl.h)}, ${Math.round(hsl.s)}%, ${Math.round(hsl.l)}%)`;
  els.preview.style.background = hex;

  renderSwatches(els.paletteMono,   buildMonochromatic(hsl));
  renderSwatches(els.paletteAnalog, buildAnalogous(hsl));
  renderSwatches(els.paletteComp,   buildComplementary(hsl));
  renderSwatches(els.paletteTriad,  buildTriadic(hsl));
  renderSwatches(els.paletteTetra,  buildTetradic(hsl));
}

// イベント
els.picker.addEventListener('input', e => {
  const rgb = hexToRgb(e.target.value);
  if (rgb) update(rgb);
});

els.hex.addEventListener('input', e => {
  const rgb = hexToRgb(e.target.value);
  if (rgb) update(rgb);
});

[els.r, els.g, els.b].forEach(input => {
  input.addEventListener('input', () => {
    update({
      r: parseInt(els.r.value, 10) || 0,
      g: parseInt(els.g.value, 10) || 0,
      b: parseInt(els.b.value, 10) || 0,
    });
  });
});

// 初期化
update({ r: 74, g: 144, b: 217 });
