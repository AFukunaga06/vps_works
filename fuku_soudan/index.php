<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>フクの法律相談窓口（初回無料）</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
:root {
  --fuku-green: #3a7d5c;
  --fuku-green-dark: #2c5f44;
  --fuku-light: #e8f5ee;
  --general-color: #0d47a1;
  --general-bg: #e3f2fd;
  --legal-color: #4a148c;
  --legal-bg: #f3e5f5;
}
body {
  font-family: 'Hiragino Sans', 'Meiryo', 'Yu Gothic', sans-serif;
  background: #f8f9fa;
  color: #333;
}

/* ヘッダー */
.site-header {
  background: var(--fuku-green);
  color: #fff;
  padding: 2.5rem 1rem 2rem;
  text-align: center;
}
.site-header h1 { font-size: 1.8rem; font-weight: bold; margin-bottom: .5rem; }
.site-header .tagline { font-size: 1rem; opacity: .9; margin-bottom: 1.2rem; }
.site-header .sub { font-size: .9rem; opacity: .8; }
.btn-reserve {
  background: #fff;
  color: var(--fuku-green-dark);
  font-size: 1.15rem;
  font-weight: bold;
  padding: .75rem 2.5rem;
  border-radius: 50px;
  border: none;
  box-shadow: 0 4px 12px rgba(0,0,0,.2);
  transition: .2s;
  text-decoration: none;
  display: inline-block;
}
.btn-reserve:hover {
  background: var(--fuku-light);
  color: var(--fuku-green-dark);
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0,0,0,.25);
}

/* セクション共通 */
.section { padding: 2.5rem 0; }
.section-title {
  font-size: 1.2rem;
  font-weight: bold;
  color: var(--fuku-green-dark);
  border-left: 5px solid var(--fuku-green);
  padding-left: .75rem;
  margin-bottom: 1.5rem;
}
.section + .section { border-top: 1px solid #e0e0e0; }

/* 相談メニューカード */
.menu-card {
  border: none;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,.08);
  overflow: hidden;
  height: 100%;
}
.menu-card-header {
  padding: 1rem 1.25rem;
  font-weight: bold;
  font-size: 1.05rem;
}
.menu-card-header.general { background: var(--general-bg); color: var(--general-color); }
.menu-card-header.legal   { background: var(--legal-bg);   color: var(--legal-color); }
.example-list { padding-left: 1.2rem; }
.example-list li { margin-bottom: .3rem; font-size: .9rem; }
.price-box {
  background: #f9f9f9;
  border-radius: 6px;
  padding: .75rem 1rem;
  font-size: .9rem;
  margin-top: .75rem;
}
.price-box li { margin-bottom: .2rem; }
.notice-box {
  background: #fff8e1;
  border-left: 4px solid #f9a825;
  border-radius: 4px;
  padding: .75rem 1rem;
  font-size: .85rem;
  margin-top: .75rem;
}

/* フロー */
.flow-step {
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  margin-bottom: .9rem;
}
.flow-num {
  background: var(--fuku-green);
  color: #fff;
  border-radius: 50%;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .85rem;
  font-weight: bold;
  flex-shrink: 0;
  margin-top: 2px;
}
.flow-text { font-size: .95rem; line-height: 1.6; }

/* 料金テーブル */
.price-table th { background: var(--fuku-light); }

/* チェックリスト */
.check-list { list-style: none; padding-left: 0; }
.check-list li { padding: .3rem 0 .3rem 1.6rem; position: relative; font-size: .93rem; }
.check-list li::before {
  content: "✓";
  position: absolute;
  left: 0;
  color: var(--fuku-green);
  font-weight: bold;
}

/* おすすめカード */
.rec-card {
  background: var(--fuku-light);
  border-radius: 8px;
  padding: .7rem 1rem;
  font-size: .9rem;
  display: flex;
  align-items: center;
  gap: .5rem;
}
.rec-icon { color: var(--fuku-green); font-size: 1.1rem; }

/* メッセージ */
.message-box {
  background: var(--fuku-green-dark);
  color: #fff;
  border-radius: 10px;
  padding: 2rem;
  text-align: center;
}
.message-box p { margin-bottom: .5rem; opacity: .95; }
.message-box strong { color: #a8e6c1; }

/* フッター */
footer { background: #2c3e35; color: #aaa; font-size: .8rem; padding: 1.2rem; text-align: center; }
</style>
</head>
<body>

<!-- ===== ヘッダー ===== -->
<div class="site-header">
  <h1>フクの法律相談窓口</h1>
  <p class="tagline"><strong style="color:#a8e6c1">初回無料。</strong>お困りごとを一緒に整理し、次の一歩を分かりやすくします。</p>
  <p class="sub">「何から始めればよいか分からない」「一人で考えると不安」という方のための相談窓口です。<br>
  <strong>法律相談（相談前整理サポート）</strong>をご用意しています。</p>
  <div class="mt-4">
    <a href="reserve/index.php" class="btn-reserve">相談窓口予約（無料）</a>
  </div>
</div>

<div class="container py-2" style="max-width: 860px;">

  <!-- ===== 相談メニュー ===== -->
  <div class="section">
    <div class="section-title">相談メニュー</div>
    <div class="row g-4">

      <!-- 法律相談 -->
      <div class="col-12">
        <div class="menu-card card">
          <div class="menu-card-header legal">2．法律相談（相談前整理サポート）</div>
          <div class="card-body">
            <p class="small mb-2">法律に関するお困りごとについて、<strong>弁護士・司法書士などの専門家に相談する前の整理サポート</strong>を行います。状況整理、時系列整理、質問事項の整理、資料整理などをお手伝いします。</p>
            <p class="fw-bold small mb-1" style="color:var(--legal-color)">相談例</p>
            <ul class="example-list">
              <li>相続・遺言の相談前準備（状況整理、確認項目の整理）</li>
              <li>家族間トラブルの整理（事実関係・時系列の整理）</li>
              <li>契約や請求に関する不安の整理（資料の整理、論点の整理）</li>
              <li>弁護士や司法書士に相談する前の「質問メモ」の作成</li>
              <li>相談時に必要な資料の確認（どれを揃えるべきか）</li>
              <li>公的機関・相談先の検討（法テラス、自治体窓口など）</li>
            </ul>
            <div class="price-box">
              <p class="fw-bold small mb-1" style="color:var(--legal-color)">料金</p>
              <ul class="example-list">
                <li><strong>初回：無料（30分〜45分）</strong></li>
                <li>2回目以降：45分 2,000円（税込）</li>
                <li>前払い制</li>
              </ul>
            </div>
            <div class="notice-box">
              <strong>ご注意（重要）</strong>
              <ul class="check-list mt-1">
                <li>当窓口では、法律問題の整理やご相談準備のサポートを行っております。</li>
                <li>具体的な法的判断や、相手方との交渉、訴訟対応、法律文書の作成などの実際の法的対応については、弁護士が個別にご相談内容を確認したうえで行います。</li>
                <li>なお、正式なご依頼となる場合には、別途、弁護士との委任契約および着手金等が必要となります。</li>
                <li>必要に応じて、司法書士・公的機関等をご案内することもあります。</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== 初回無料で行うこと ===== -->
  <div class="section">
    <div class="section-title">初回無料相談で行うこと（目安：30分〜45分）</div>
    <p class="mb-3">初回無料相談では、次の流れで進めます。</p>
    <div class="flow-step"><div class="flow-num">1</div><div class="flow-text">ご相談内容の確認</div></div>
    <div class="flow-step"><div class="flow-num">2</div><div class="flow-text">現在の状況整理（要点を整理します）</div></div>
    <div class="flow-step"><div class="flow-num">3</div><div class="flow-text">不安点・問題点の整理</div></div>
    <div class="flow-step"><div class="flow-num">4</div><div class="flow-text">次にやること（ToDo）の作成</div></div>
    <div class="flow-step"><div class="flow-num">5</div><div class="flow-text">必要に応じて継続相談や専門機関のご案内</div></div>
    <p class="mt-2 small text-muted">※ 初回無料は「お試し」ではなく、<strong>必ず"次の一手"が分かる形</strong>にまとめます。</p>
  </div>

  <!-- ===== 料金・お支払い ===== -->
  <div class="section">
    <div class="section-title">ご利用料金とお支払い（前払い制）</div>
    <div class="row g-4">
      <div class="col-md-6">
        <h3 class="h6 fw-bold mb-2">料金</h3>
        <table class="table table-bordered price-table table-sm">
          <thead>
            <tr><th>種別</th><th>料金</th></tr>
          </thead>
          <tbody>
            <tr>
              <td style="color:var(--legal-color)">法律相談</td>
              <td>初回<strong>無料</strong><br><small>2回目以降 45分 2,000円（税込）</small></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="col-md-6">
        <h3 class="h6 fw-bold mb-2">お支払いについて</h3>
        <ul class="check-list">
          <li>前払い制です。</li>
          <li>ご予約受付後、こちらからお支払い方法（振込／決済方法など）をご案内します。</li>
          <li><strong>入金確認後に予約確定</strong>となります。</li>
          <li>初回無料相談は料金はかかりませんが、<strong>事前予約は必要</strong>です。</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- ===== ご予約の流れ ===== -->
  <div class="section">
    <div class="section-title">ご予約の流れ</div>
    <div class="flow-step"><div class="flow-num">1</div><div class="flow-text">予約フォームからお申し込み</div></div>
    <div class="flow-step"><div class="flow-num">2</div><div class="flow-text">日時調整（こちらからご連絡）</div></div>
    <div class="flow-step"><div class="flow-num">3</div><div class="flow-text">（2回目以降の場合）料金・支払い方法のご案内</div></div>
    <div class="flow-step"><div class="flow-num">4</div><div class="flow-text">事前入金（2回目以降）</div></div>
    <div class="flow-step"><div class="flow-num">5</div><div class="flow-text">入金確認後、予約確定</div></div>
    <div class="flow-step"><div class="flow-num">6</div><div class="flow-text">当日ご相談（Zoom / 電話 / 対面）</div></div>
  </div>

  <!-- ===== 予約時にご記入いただく内容 ===== -->
  <div class="section">
    <div class="section-title">予約時にご記入いただく内容</div>
    <div class="row g-2">
      <?php
      $items = [
        'お名前','ふりがな','メールアドレス','電話番号',
        'ご希望の相談種類（法律相談）','初回無料か、2回目以降か',
        'ご希望日時（第1〜第3希望があるとスムーズです）',
        'ご相談内容の概要（短くでOK）','ご希望の相談方法（Zoom／電話／対面）',
      ];
      foreach ($items as $item): ?>
      <div class="col-md-6">
        <div class="rec-card">
          <span class="rec-icon">✔</span>
          <span><?= htmlspecialchars($item) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ===== キャンセル ===== -->
  <div class="section">
    <div class="section-title">キャンセル・日程変更について</div>
    <ul class="check-list">
      <li>キャンセル・日程変更は、<strong>ご予約日の前日まで</strong>にご連絡をお願いいたします。</li>
      <li><strong>当日キャンセルは原則として返金不可</strong>とさせていただきます（2回目以降の前払い分）。</li>
      <li>やむを得ない事情がある場合は、個別にご相談ください。</li>
    </ul>
  </div>

  <!-- ===== おすすめ ===== -->
  <div class="section">
    <div class="section-title">このような方におすすめです</div>
    <div class="row g-2">
      <?php
      $recs = [
        '何から始めればよいか分からない方',
        '頭の中を整理して、次の行動を決めたい方',
        '書類や状況を一緒にまとめたい方',
        '専門家に相談する前に準備しておきたい方',
        '一人で悩まず、まず話を聞いてほしい方',
      ];
      foreach ($recs as $r): ?>
      <div class="col-md-6">
        <div class="rec-card">
          <span class="rec-icon">👤</span>
          <span><?= htmlspecialchars($r) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ===== メッセージ ===== -->
  <div class="section pb-1">
    <div class="message-box">
      <h2 class="h5 mb-3" style="color:#a8e6c1">フクの法律相談窓口からのメッセージ</h2>
      <p>一人で抱え込んでしまうと、何から手をつければよいか分からなくなることがあります。</p>
      <p>フクの法律相談窓口では、まずお話を丁寧にうかがい、状況を整理し、次に進むためのお手伝いをいたします。</p>
      <p><strong>初回は無料</strong>ですので、まずはお気軽にご相談ください。</p>
      <div class="mt-4">
        <a href="reserve/index.php" class="btn-reserve">相談窓口予約（無料）</a>
      </div>
    </div>
  </div>

</div>

<footer>
  <p class="mb-0">フクの法律相談窓口 &nbsp;|&nbsp; 初回無料</p>
</footer>

</body>
</html>
