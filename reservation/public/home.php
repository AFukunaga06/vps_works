<?php
require_once __DIR__ . '/../includes/functions.php';

$tenant_id = resolve_public_tenant_id();
$settings  = get_settings($tenant_id);

// 事務所名：設定があれば使う（既定はプレースホルダ）
$firm = trim((string)($settings['business_name'] ?? ''));
if ($firm === '' || $firm === '法律事務所') $firm = '〇〇〇法律事務所';
$tel  = trim((string)($settings['contact_tel'] ?? '')) ?: '03-0000-0000';

// 予約フォームへのリンク（カレンダー＋入力フォーム一体型ページ）
$reserveUrl = 'reserve.php?t=' . (int)$tenant_id;

// 取扱分野（設定の相談類型から「その他」を除いて使用）
$areaIcons = [
    '一般相談'   => '⚖️',
    '離婚'       => '💔',
    '相続'       => '📜',
    '刑事'       => '🛡️',
    '労働'       => '🏢',
    '債務整理'   => '💴',
];
$areaDesc = [
    '一般相談' => '「これは相談していいのか」という段階からお気軽に。初回はお話を伺うことに専念します。',
    '離婚'     => '慰謝料・財産分与・親権・養育費まで。感情面に配慮しながら有利な解決を目指します。',
    '相続'     => '遺産分割・遺言書作成・相続放棄・遺留分。揉める前の予防から紛争解決まで対応。',
    '刑事'     => '逮捕・勾留からの早期釈放、示談交渉、不起訴・執行猶予に向けた迅速な弁護活動。',
    '労働'     => '不当解雇・残業代請求・ハラスメント。労働者・使用者どちらの立場もご相談可能。',
    '債務整理' => '任意整理・個人再生・自己破産。借金の悩みを生活再建の視点から整理します。',
];
$types = array_filter(array_map('trim', explode(',', (string)$settings['consultation_types'])), 'strlen');
$areas = [];
foreach ($types as $t) {
    if ($t === 'その他') continue;
    $areas[$t] = [
        'icon' => $areaIcons[$t] ?? '⚖️',
        'desc' => $areaDesc[$t] ?? 'ご相談内容に応じて、最適な解決策をご提案します。',
    ];
}
if (!$areas) {
    foreach (['一般相談','離婚','相続','刑事','労働','債務整理'] as $t) {
        $areas[$t] = ['icon' => $areaIcons[$t], 'desc' => $areaDesc[$t]];
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($firm) ?>｜法律のご相談</title>
<meta name="description" content="<?= h($firm) ?>は、相続・離婚・刑事・労働・債務整理など幅広い分野に対応する総合法律事務所です。初回相談無料、オンライン予約受付中。">
<style>
:root{
  --navy:#1f2d4d; --navy-d:#16203a; --gold:#b8924a; --gold-d:#9a7836;
  --ink:#23282f; --muted:#6b7280; --line:#e6e6e6; --bg:#f7f5f1; --paper:#fff;
}
*{box-sizing:border-box}
body{margin:0;font-family:"Hiragino Mincho ProN","Yu Mincho",serif;color:var(--ink);
     background:var(--bg);line-height:1.85;-webkit-font-smoothing:antialiased}
.sans{font-family:"Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif}
a{color:var(--navy);text-decoration:none}
img{max-width:100%}
.container{max-width:1040px;margin:0 auto;padding:0 22px}

/* ===== Header ===== */
.site-header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.96);
  border-bottom:1px solid var(--line);backdrop-filter:blur(6px)}
.site-header .inner{display:flex;align-items:center;justify-content:space-between;
  gap:16px;padding:12px 22px;max-width:1040px;margin:0 auto}
.brand{font-size:1.25rem;font-weight:700;color:var(--navy);letter-spacing:.04em}
.brand small{display:block;font-size:.62rem;color:var(--gold);letter-spacing:.3em;
  font-family:"Hiragino Kaku Gothic ProN",sans-serif;margin-top:2px}
.header-actions{display:flex;align-items:center;gap:14px}
.header-tel{font-family:"Hiragino Kaku Gothic ProN",sans-serif;font-weight:700;
  font-size:1.15rem;color:var(--navy)}
.header-tel small{display:block;font-size:.62rem;color:var(--muted);font-weight:400}
.btn{display:inline-block;font-family:"Hiragino Kaku Gothic ProN",sans-serif;
  font-weight:700;border-radius:6px;padding:.7rem 1.3rem;cursor:pointer;transition:.18s;
  text-align:center;border:2px solid transparent}
.btn-gold{background:var(--gold);color:#fff}
.btn-gold:hover{background:var(--gold-d);color:#fff}
.btn-navy{background:var(--navy);color:#fff}
.btn-navy:hover{background:var(--navy-d);color:#fff}
.btn-ghost{background:transparent;color:#fff;border-color:rgba(255,255,255,.8)}
.btn-ghost:hover{background:#fff;color:var(--navy)}
.btn-lg{font-size:1.1rem;padding:.95rem 2rem}
@media(max-width:640px){ .header-tel{display:none} }

/* ===== Hero ===== */
.hero{position:relative;background:
   linear-gradient(120deg,rgba(22,32,58,.82),rgba(31,45,77,.62)),
   url('../assets/img/hero.png') center/cover no-repeat;
  color:#fff;padding:120px 0 110px}
.hero .eyebrow{font-family:"Hiragino Kaku Gothic ProN",sans-serif;letter-spacing:.34em;
  font-size:.78rem;color:var(--gold);margin:0 0 18px}
.hero h1{font-size:2.5rem;line-height:1.5;margin:0 0 22px;font-weight:700}
.hero h1 .em{color:#f3e7cf}
.hero p.lead{font-size:1.08rem;max-width:640px;color:#e8eaf0;margin:0 0 34px}
.hero-cta{display:flex;gap:14px;flex-wrap:wrap}
.hero-note{margin-top:20px;font-size:.82rem;color:#c9cedd;font-family:sans-serif}
@media(max-width:640px){ .hero h1{font-size:1.7rem} .hero{padding:64px 0 56px} }

/* ===== Section common ===== */
.section{padding:78px 0}
.section.alt{background:var(--paper)}
.sec-head{text-align:center;margin-bottom:48px}
.sec-head .en{font-family:"Hiragino Kaku Gothic ProN",sans-serif;letter-spacing:.3em;
  color:var(--gold);font-size:.78rem;display:block;margin-bottom:10px}
.sec-head h2{font-size:1.85rem;margin:0;color:var(--navy);position:relative;display:inline-block}
.sec-head h2::after{content:"";display:block;width:46px;height:3px;background:var(--gold);
  margin:14px auto 0}

/* ===== Practice areas ===== */
.area-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
@media(max-width:820px){ .area-grid{grid-template-columns:repeat(2,1fr)} }
@media(max-width:520px){ .area-grid{grid-template-columns:1fr} }
.area-card{background:var(--paper);border:1px solid var(--line);border-radius:10px;
  padding:28px 24px;transition:.2s}
.section.alt .area-card{background:var(--bg)}
.area-card:hover{transform:translateY(-4px);box-shadow:0 10px 26px rgba(31,45,77,.10);
  border-color:var(--gold)}
.area-card .ic{font-size:2rem}
.area-card h3{font-size:1.18rem;margin:.5rem 0 .6rem;color:var(--navy)}
.area-card p{font-size:.95rem;color:var(--muted);margin:0}

/* ===== Strengths ===== */
.str-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:26px}
@media(max-width:760px){ .str-grid{grid-template-columns:1fr} }
.str{text-align:center;padding:8px}
.str .no{font-family:"Hiragino Kaku Gothic ProN",sans-serif;color:var(--gold);
  font-size:.8rem;letter-spacing:.2em}
.str h3{color:var(--navy);font-size:1.25rem;margin:.4rem 0 .7rem}
.str p{color:var(--muted);font-size:.96rem;margin:0}

/* ===== Flow ===== */
.flow{counter-reset:step;max-width:760px;margin:0 auto}
.flow-item{display:flex;gap:22px;align-items:flex-start;padding:22px 0;
  border-bottom:1px dashed var(--line)}
.flow-item:last-child{border-bottom:none}
.flow-item .step{flex:0 0 54px;height:54px;border-radius:50%;background:var(--navy);
  color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;
  font-family:"Hiragino Kaku Gothic ProN",sans-serif;font-weight:700;position:relative}
.flow-item .step::before{counter-increment:step;content:"STEP";position:absolute;top:-16px;
  font-size:.5rem;color:var(--gold);letter-spacing:.15em}
.flow-item h3{margin:.2rem 0 .4rem;color:var(--navy);font-size:1.15rem}
.flow-item p{margin:0;color:var(--muted);font-size:.95rem}

/* ===== Fee ===== */
.fee-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;max-width:880px;margin:0 auto}
@media(max-width:680px){ .fee-grid{grid-template-columns:1fr} }
.fee{background:var(--paper);border:1px solid var(--line);border-radius:10px;
  padding:26px 22px;text-align:center}
.section.alt .fee{background:var(--bg)}
.fee .label{font-family:sans-serif;color:var(--navy);font-weight:700}
.fee .price{font-size:2rem;color:var(--gold-d);font-weight:700;margin:.3rem 0;
  font-family:"Hiragino Kaku Gothic ProN",sans-serif}
.fee .price small{font-size:.9rem;color:var(--muted)}
.fee p{font-size:.88rem;color:var(--muted);margin:.4rem 0 0}

/* ===== FAQ ===== */
.faq{max-width:780px;margin:0 auto}
.faq details{background:var(--paper);border:1px solid var(--line);border-radius:8px;
  margin-bottom:12px;padding:0 20px}
.section.alt .faq details{background:var(--bg)}
.faq summary{cursor:pointer;padding:16px 0;font-weight:700;color:var(--navy);
  font-family:sans-serif;list-style:none;display:flex;gap:12px;align-items:flex-start}
.faq summary::-webkit-details-marker{display:none}
.faq summary::before{content:"Q";color:var(--gold);font-weight:700;flex:0 0 auto}
.faq details[open] summary{border-bottom:1px solid var(--line)}
.faq .a{padding:14px 0 18px;color:var(--muted);font-size:.95rem;display:flex;gap:12px}
.faq .a::before{content:"A";color:var(--navy);font-weight:700;flex:0 0 auto;font-family:sans-serif}

/* ===== About ===== */
.about{display:grid;grid-template-columns:1.1fr 1fr;gap:40px;align-items:center}
@media(max-width:760px){ .about{grid-template-columns:1fr} }
.about table{width:100%;border-collapse:collapse;font-family:sans-serif;font-size:.92rem}
.about th,.about td{text-align:left;padding:12px 10px;border-bottom:1px solid var(--line);
  vertical-align:top}
.about th{width:32%;color:var(--navy);font-weight:700;white-space:nowrap}
.about td{color:var(--muted)}
.about .photo{background:var(--navy);border-radius:12px;min-height:240px;
  display:flex;align-items:center;justify-content:center;color:#7f8bb0;
  font-family:sans-serif;font-size:.85rem;border:1px solid var(--line)}

/* ===== CTA band ===== */
.cta-band{background:linear-gradient(120deg,var(--navy),var(--navy-d));color:#fff;
  text-align:center;padding:70px 0}
.cta-band h2{font-size:1.9rem;margin:0 0 12px}
.cta-band p{color:#d7dbea;margin:0 0 28px}
.cta-band .row{display:flex;gap:16px;justify-content:center;flex-wrap:wrap}
.cta-tel{font-family:"Hiragino Kaku Gothic ProN",sans-serif;font-size:2rem;font-weight:700;
  color:#fff;display:inline-flex;align-items:baseline;gap:10px}
.cta-tel small{font-size:.8rem;color:var(--gold)}

/* ===== Footer ===== */
.site-footer{background:#12182b;color:#aeb6cc;font-family:sans-serif;font-size:.86rem;
  padding:42px 0 30px}
.site-footer .cols{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap}
.site-footer .fbrand{font-family:"Hiragino Mincho ProN",serif;color:#fff;font-size:1.1rem}
.site-footer a{color:#aeb6cc}
.site-footer a:hover{color:#fff}
.site-footer .copy{margin-top:26px;border-top:1px solid #232c45;padding-top:16px;
  font-size:.76rem;color:#7b8099}
.notice-strip{background:var(--gold);color:#fff;text-align:center;font-family:sans-serif;
  font-size:.9rem;padding:10px 16px}
</style>
</head>
<body>

<?php if (trim((string)$settings['notice_message']) !== ''): ?>
<div class="notice-strip"><?= h($settings['notice_message']) ?></div>
<?php endif; ?>

<!-- Header -->
<header class="site-header">
  <div class="inner">
    <div class="brand"><?= h($firm) ?><small>LAW OFFICE</small></div>
    <div class="header-actions">
      <span class="header-tel"><?= h($tel) ?><small>平日 9:00–18:00</small></span>
      <a class="btn btn-gold sans" href="<?= h($reserveUrl) ?>">予約する</a>
    </div>
  </div>
</header>

<!-- Hero -->
<section class="hero">
  <div class="container">
    <p class="eyebrow">YOUR LEGAL PARTNER</p>
    <h1>その悩み、<span class="em">一人で</span>抱えていませんか。<br>はじめの一歩を、<span class="em">私たちと。</span></h1>
    <p class="lead">相続・離婚・刑事・労働・債務整理まで。<?= h($firm) ?>は、身近な法律トラブルから企業の課題まで、幅広く対応する総合法律事務所です。まずはお気軽にご相談ください。</p>
    <div class="hero-cta">
      <a class="btn btn-gold btn-lg sans" href="<?= h($reserveUrl) ?>">かんたんネット予約</a>
      <a class="btn btn-ghost btn-lg sans" href="#contact">お問い合わせ</a>
    </div>
    <p class="hero-note">初回相談 30分無料 ／ オンライン相談対応 ／ 秘密は厳守します</p>
  </div>
</section>

<!-- Practice areas -->
<section class="section" id="areas">
  <div class="container">
    <div class="sec-head"><span class="en">PRACTICE AREAS</span><h2>取扱分野</h2></div>
    <div class="area-grid">
      <?php foreach ($areas as $name => $a): ?>
      <a class="area-card" href="<?= h($reserveUrl) ?>">
        <div class="ic"><?= $a['icon'] ?></div>
        <h3><?= h($name) ?></h3>
        <p><?= h($a['desc']) ?></p>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Strengths -->
<section class="section alt" id="strengths">
  <div class="container">
    <div class="sec-head"><span class="en">OUR STRENGTHS</span><h2>選ばれる理由</h2></div>
    <div class="str-grid">
      <div class="str">
        <div class="no">POINT 01</div>
        <h3>初回相談 無料</h3>
        <p>「依頼するか分からない」段階でも大丈夫。まずはお話を伺い、見通しと費用を明確にお伝えします。</p>
      </div>
      <div class="str">
        <div class="no">POINT 02</div>
        <h3>分かりやすい説明</h3>
        <p>専門用語をかみ砕き、今どういう状況で、何が選べるのかを丁寧にご説明。納得して進められます。</p>
      </div>
      <div class="str">
        <div class="no">POINT 03</div>
        <h3>幅広い分野に対応</h3>
        <p>個人の身近な悩みから企業法務まで、複数分野を横断する事案もワンストップでお引き受けします。</p>
      </div>
    </div>
  </div>
</section>

<!-- Flow -->
<section class="section" id="flow">
  <div class="container">
    <div class="sec-head"><span class="en">FLOW</span><h2>ご相談の流れ</h2></div>
    <div class="flow">
      <div class="flow-item"><div class="step">1</div>
        <div><h3>ネット予約</h3><p>カレンダーから空いている日時を選び、お名前・ご連絡先をご入力ください。24時間受付。</p></div></div>
      <div class="flow-item"><div class="step">2</div>
        <div><h3>初回相談（30分無料）</h3><p>来所またはオンラインで、状況を伺います。今後の見通しと費用の目安をご提示します。</p></div></div>
      <div class="flow-item"><div class="step">3</div>
        <div><h3>ご依頼・委任契約</h3><p>方針と費用にご納得いただけたら、正式にご依頼。無理な勧誘は一切いたしません。</p></div></div>
      <div class="flow-item"><div class="step">4</div>
        <div><h3>解決へ</h3><p>交渉・調停・裁判など、最適な手段で解決を目指します。進捗はその都度ご報告します。</p></div></div>
    </div>
    <p style="text-align:center;margin-top:36px">
      <a class="btn btn-navy btn-lg sans" href="<?= h($reserveUrl) ?>">いますぐ予約する</a>
    </p>
  </div>
</section>

<!-- Fee -->
<section class="section alt" id="fee">
  <div class="container">
    <div class="sec-head"><span class="en">FEE</span><h2>費用の目安</h2></div>
    <div class="fee-grid">
      <div class="fee"><div class="label">初回相談</div>
        <div class="price">0<small>円 / 30分</small></div><p>まずはお気軽に。費用が発生する前に必ずご説明します。</p></div>
      <div class="fee"><div class="label">着手金</div>
        <div class="price">11<small>万円〜</small></div><p>事案の内容・難易度に応じて、ご依頼前に明示します。</p></div>
      <div class="fee"><div class="label">報酬金</div>
        <div class="price">成功報酬<small>制</small></div><p>得られた経済的利益に応じた分かりやすい料金体系。</p></div>
    </div>
    <p style="text-align:center;color:var(--muted);font-family:sans-serif;font-size:.85rem;margin-top:22px">
      ※ 金額は一例です。分野・事案により異なります。詳細は初回相談時にお見積りいたします。
    </p>
  </div>
</section>

<!-- About -->
<section class="section" id="about">
  <div class="container">
    <div class="sec-head"><span class="en">ABOUT US</span><h2>事務所概要</h2></div>
    <div class="about">
      <table>
        <tr><th>事務所名</th><td><?= h($firm) ?></td></tr>
        <tr><th>代表弁護士</th><td>山田 太郎（第〇〇弁護士会 所属）</td></tr>
        <tr><th>所在地</th><td>〒000-0000　東京都〇〇区〇〇 0-0-0 〇〇ビル0F</td></tr>
        <tr><th>電話</th><td><?= h($tel) ?>（平日 9:00–18:00）</td></tr>
        <?php if (!empty($settings['contact_email'])): ?>
        <tr><th>メール</th><td><?= h($settings['contact_email']) ?></td></tr>
        <?php endif; ?>
        <tr><th>取扱分野</th><td><?= h(implode('／', array_keys($areas))) ?></td></tr>
        <tr><th>アクセス</th><td>〇〇線「〇〇駅」徒歩〇分</td></tr>
      </table>
      <div class="photo">事務所・弁護士の写真をここに掲載</div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section alt" id="faq">
  <div class="container">
    <div class="sec-head"><span class="en">FAQ</span><h2>よくあるご質問</h2></div>
    <div class="faq">
      <details open><summary>相談だけでも大丈夫ですか？</summary>
        <div class="a">もちろんです。初回30分は無料で、依頼を前提とせずにお話を伺います。無理な勧誘もいたしません。</div></details>
      <details><summary>夜間や土日でも相談できますか？</summary>
        <div class="a">事前予約で対応できる場合があります。ネット予約で空き枠をご確認いただくか、お電話でご相談ください。</div></details>
      <details><summary>オンライン相談はできますか？</summary>
        <div class="a">はい、ビデオ通話での相談に対応しています。ご予約時に「オンライン希望」と備考にご記入ください。</div></details>
      <details><summary>費用はいつ発生しますか？</summary>
        <div class="a">初回相談は無料です。ご依頼いただく場合のみ着手金等が発生し、契約前に必ず金額をご説明します。</div></details>
      <details><summary>秘密は守られますか？</summary>
        <div class="a">弁護士には法律上の守秘義務があります。ご相談内容が外部に漏れることはありません。</div></details>
    </div>
  </div>
</section>

<!-- CTA band -->
<section class="cta-band" id="contact">
  <div class="container">
    <h2>まずは、お話を聞かせてください。</h2>
    <p>初回相談30分は無料です。ネット予約なら24時間いつでも受付。</p>
    <div class="row">
      <a class="btn btn-gold btn-lg sans" href="<?= h($reserveUrl) ?>">ネットで予約する</a>
      <a class="cta-tel sans" href="tel:<?= h(preg_replace('/[^0-9]/','',$tel)) ?>">☎ <?= h($tel) ?><small>平日9:00-18:00</small></a>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="site-footer">
  <div class="container">
    <div class="cols">
      <div>
        <div class="fbrand"><?= h($firm) ?></div>
        <p>〒000-0000　東京都〇〇区〇〇 0-0-0<br>TEL <?= h($tel) ?>（平日 9:00–18:00）</p>
      </div>
      <div>
        <p>
          <a href="#areas">取扱分野</a>
          <a href="#flow">ご相談の流れ</a>
          <a href="#fee">費用</a>
          <a href="#about">事務所概要</a>
          <a href="#faq">FAQ</a>
          <a href="<?= h($reserveUrl) ?>">予約する</a>
        </p>
      </div>
    </div>
    <div class="copy">&copy; <?= date('Y') ?> <?= h($firm) ?>. All rights reserved.</div>
  </div>
</footer>

</body>
</html>
