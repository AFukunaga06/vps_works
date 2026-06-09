const puppeteer = require('puppeteer');
const path = require('path');
const ExcelJS = require('exceljs');

const DATE_FROM = new Date(process.argv[2] || '2026-01-01');
const DATE_TO   = new Date(process.argv[3] || '2026-04-28');

function parseJpDate(str) {
  const m = str.match(/(\d{4})年(\d{1,2})月(\d{1,2})日/);
  if (!m) return null;
  return new Date(parseInt(m[1]), parseInt(m[2]) - 1, parseInt(m[3]));
}

function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

const year = DATE_FROM.getFullYear();
const ORDER_URL = `https://www.amazon.co.jp/your-orders/orders?orderFilter=year-${year}&ref_=ppx_yo2ov_dt_b_filter_all_y${year}`;
const shotPath = path.join('C:\\Users\\afky5\\Downloads', 'amazon_debug.png');

// 専用プロファイル（初回のみログイン、以降はセッション自動再利用）
const PROFILE_DIR = 'C:\\Users\\afky5\\AppData\\Local\\AmazonScraper\\ChromeProfile';

(async () => {
  const browser = await puppeteer.launch({
    headless: false,
    userDataDir: PROFILE_DIR,
    args: [
      '--no-sandbox',
      '--disable-blink-features=AutomationControlled',
    ],
    ignoreDefaultArgs: ['--enable-automation'],
  });

  const page = await browser.newPage();

  await page.evaluateOnNewDocument(() => {
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
  });

  console.log('Amazonにアクセス中...');
  await page.goto(ORDER_URL, {
    waitUntil: 'networkidle2',
    timeout: 30000,
  });
  await sleep(3000);

  await page.screenshot({ path: shotPath, fullPage: false });
  console.log(`スクリーンショット保存: ${shotPath}`);

  const currentUrl = page.url();
  console.log(`現在のURL: ${currentUrl}`);

  if (currentUrl.includes('/ap/') || currentUrl.includes('signin')) {
    console.log('ログインページが表示されています。');
    console.log('メールアドレスとパスワードを入力してログインしてください（3分以内）...');

    // page.url() を使ったポーリング（ページ内JSを実行しないため入力の邪魔をしない）
    // URLのパス部分のみを確認（クエリパラメータのreturn_toと混同しないため）
    const deadline = Date.now() + 180000;
    let loggedIn = false;
    while (Date.now() < deadline) {
      await sleep(2000);
      try {
        const pathname = new URL(page.url()).pathname;
        if (pathname.startsWith('/your-orders') || pathname.startsWith('/gp/css/order-history')) {
          loggedIn = true;
          break;
        }
        // まだ認証フロー中（ax/claim, ap/signin 等）の場合はコンソールに表示
        if (pathname.startsWith('/ax/') || pathname.startsWith('/ap/')) {
          process.stdout.write('.');
        }
      } catch (_) {}
    }
    console.log('');

    if (!loggedIn) {
      console.log('タイムアウト：ログインが完了しませんでした。');
      await page.screenshot({ path: shotPath });
      await browser.close();
      return;
    }

    // Amazonがreturn_toへ自動リダイレクトするので再goto不要
    console.log('ログイン完了！注文履歴を読み込んでいます...');
    await sleep(3000);
  }

  await page.screenshot({ path: shotPath, fullPage: false });
  console.log(`スクリーンショット更新: ${shotPath}`);

  const rows = [];
  let pageNum = 1;
  let reachedBefore = false;

  while (true) {
    console.log(`ページ ${pageNum} を処理中...`);

    try {
      await page.waitForSelector('.order-card', { timeout: 20000 });
    } catch (e) {
      const url = page.url();
      console.log(`order-cardが見つかりません。URL: ${url}`);
      await page.screenshot({ path: shotPath });
      console.log(`スクリーンショット更新: ${shotPath}`);
      break;
    }

    const orders = await page.evaluate(() => {
      const result = [];
      document.querySelectorAll('.order-card').forEach(order => {
        const texts = [...order.querySelectorAll('*')]
          .map(el => el.childNodes)
          .reduce((acc, nodes) => {
            nodes.forEach(n => { if (n.nodeType === 3 && n.textContent.trim()) acc.push(n.textContent.trim()); });
            return acc;
          }, []);

        const date = texts.find(t => /\d{4}年\d{1,2}月\d{1,2}日/.test(t)) || '';
        let amount = '';
        for (let i = 0; i < texts.length; i++) {
          if (texts[i] === '合計' && texts[i + 1]) { amount = texts[i + 1]; break; }
        }
        const orderNum = texts.find(t => /^\d{3}-\d{7}-\d{7}$/.test(t)) || '';
        const itemEls = order.querySelectorAll('.yohtmlc-product-title');
        let items = [...itemEls].map(el => el.innerText.trim());
        if (items.length === 0) {
          const sub = texts.find(t => /Audible|Kindle Unlimited|Amazon Music|Prime/.test(t));
          items = sub ? [sub] : ['（商品名なし）'];
        }
        items.forEach(item => result.push({ date, amount, orderNum, item }));
      });
      return result;
    });

    for (const order of orders) {
      const d = parseJpDate(order.date);
      if (!d) continue;
      if (d >= DATE_FROM && d <= DATE_TO) {
        rows.push(order);
      } else if (d < DATE_FROM) {
        reachedBefore = true;
      }
    }
    console.log(`  ${orders.length} 件取得（累計 ${rows.length} 件）`);

    if (reachedBefore) {
      console.log('開始日より前の注文に到達したため終了します。');
      break;
    }

    const nextBtn = await page.$('.a-pagination .a-last:not(.a-disabled) a');
    if (!nextBtn) {
      console.log('最終ページに到達しました。');
      break;
    }
    await nextBtn.click();
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 });
    await sleep(1500);
    pageNum++;
  }

  await browser.close();

  if (rows.length === 0) {
    console.log('\n取得件数が0件です。amazon_debug.png を確認してください。');
    return;
  }

  // 金額文字列（例: ¥1,234）を数値に変換
  const parseAmount = s => {
    const n = parseInt((s || '').replace(/[^\d]/g, ''));
    return isNaN(n) ? 0 : n;
  };

  // 月ごとにグループ化
  const byMonth = {};
  for (const r of rows) {
    const m = r.date.match(/(\d{4})年(\d{1,2})月/);
    if (!m) continue;
    const key = `${m[1]}_${m[2].padStart(2, '0')}`;
    if (!byMonth[key]) byMonth[key] = { label: `${parseInt(m[2])}月`, rows: [] };
    byMonth[key].rows.push(r);
  }

  const wb = new ExcelJS.Workbook();
  const ws = wb.addWorksheet('注文履歴');

  ws.columns = [
    { key: 'date',     width: 18 },
    { key: 'amount',   width: 14 },
    { key: 'orderNum', width: 24 },
    { key: 'item',     width: 62 },
  ];

  // ヘッダー行
  const headerRow = ws.addRow(['注文日', '金額', '注文番号', '商品名']);
  headerRow.font = { bold: true, color: { argb: 'FFFFFFFF' } };
  headerRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFF9900' } };
  headerRow.alignment = { horizontal: 'center' };

  const monthTotals = [];

  for (const { label, rows: mRows } of Object.values(byMonth)) {
    for (const r of mRows) {
      const row = ws.addRow([r.date, parseAmount(r.amount), r.orderNum, r.item]);
      row.getCell(2).numFmt = '#,##0';
    }
    const total = mRows.reduce((s, r) => s + parseAmount(r.amount), 0);
    monthTotals.push(total);
    const subRow = ws.addRow([`${label}計`, total, '', '']);
    subRow.font = { bold: true };
    subRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFF0CC' } };
    subRow.getCell(2).numFmt = '#,##0';
  }

  // 総合計 = 各月計の合算
  const grandTotal = monthTotals.reduce((s, t) => s + t, 0);
  console.log(`月計数: ${monthTotals.length}、総合計: ${grandTotal}`);
  const totalRow = ws.addRow(['総合計', grandTotal, '', '']);
  totalRow.font = { bold: true };
  totalRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFCC99' } };
  totalRow.getCell(2).numFmt = '#,##0';

  const outputPath = 'C:\\Users\\afky5\\Downloads\\amazon_orders_all.xlsx';
  await wb.xlsx.writeFile(outputPath);
  console.log(`\n合計 ${rows.length} 件 → ${outputPath}`);
})();
