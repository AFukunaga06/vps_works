<?php
// roster.php  名簿表示（DB: members）
// 前提：同じフォルダに config.php / db.php があること

// もしログイン必須にしたい場合は、あなたの仕組みに合わせてここに入れてください。
// 例：session_start(); if (empty($_SESSION['login_ok'])) { header("Location: login.php"); exit; }

require_once __DIR__ . '/db.php';

// 年（year）をURLで指定できるようにする：例 roster.php?year=2026
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// 年の候補（DBにあるyear一覧）
$years = [];
try {
  $stmtYears = $pdo->query("SELECT DISTINCT year FROM members ORDER BY year DESC");
  $years = $stmtYears->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
  // 年一覧が取れなくても致命的ではないので空で続行
}

// members を取得（sort_order → name → id の順で安定ソート）
$sql = "SELECT id, year, gender, name, sort_order, updated_at
        FROM members
        WHERE year = ?
        ORDER BY sort_order ASC, name ASC, id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$year]);
$rows = $stmt->fetchAll();

// 男女などでグループ分け
$groups = [
  'male' => [],
  'female' => [],
  'other' => [],
];

foreach ($rows as $r) {
  $g = $r['gender'] ?? 'other';
  if (!isset($groups[$g])) $g = 'other';
  $groups[$g][] = $r;
}

// 表示ラベル
$labels = [
  'male' => '男性',
  'female' => '女性',
  'other' => 'その他',
];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>名簿（<?= h($year) ?>年）</title>
<style>
:root{
  --bg:#e9f6ea;
  --card:#ffffff;
  --ink:#0b1f12;
  --muted:#4a6b57;
  --accent:#1f7a3b;
  --line:#d6e6da;
  --chip:#e7f6ec;
  --shadow:0 8px 22px rgba(0,0,0,.06);
  --radius:18px;
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family: system-ui, -apple-system, "Segoe UI", "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif;
  background: var(--bg);
  color: var(--ink);
}
.wrap{ max-width: 980px; margin: 0 auto; padding: 18px 14px 40px; }
.header{
  display:flex; align-items:center; justify-content:space-between;
  gap:12px; flex-wrap:wrap;
  margin-bottom: 14px;
}
.h1{
  font-size: 20px;
  font-weight: 800;
  letter-spacing:.02em;
}
.sub{
  color: var(--muted);
  font-size: 13px;
}
.card{
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 14px;
}
.controls{
  display:flex; align-items:center; gap:10px; flex-wrap:wrap;
}
select{
  border:1px solid var(--line);
  border-radius: 12px;
  padding: 10px 12px;
  background:#fff;
  font-size: 14px;
}
.btn{
  appearance:none;
  border:1px solid var(--line);
  background: var(--chip);
  color: var(--ink);
  border-radius: 12px;
  padding: 10px 12px;
  font-weight: 700;
  cursor:pointer;
}
.btn:hover{ filter: brightness(.98); }

.tabs{
  display:flex; gap:8px; flex-wrap:wrap;
  margin: 12px 0 10px;
}
.tab{
  border:1px solid var(--line);
  background:#fff;
  border-radius: 999px;
  padding: 8px 12px;
  cursor:pointer;
  font-weight:800;
  font-size: 13px;
}
.tab.active{
  background: var(--accent);
  color:#fff;
  border-color: transparent;
}
.grid{
  display:grid;
  grid-template-columns: 1fr;
  gap: 12px;
}
@media (min-width: 820px){
  .grid{ grid-template-columns: 1fr 1fr; }
}
.list{
  border:1px solid var(--line);
  border-radius: 16px;
  padding: 12px;
}
.list h2{
  margin:0 0 10px;
  font-size: 15px;
}
.item{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  padding: 10px 10px;
  border-radius: 12px;
  background: #fff;
  border:1px solid var(--line);
  margin-bottom: 8px;
}
.item:last-child{ margin-bottom:0; }
.name{
  font-weight: 800;
}
.meta{
  color: var(--muted);
  font-size: 12px;
  white-space:nowrap;
}
.empty{
  color: var(--muted);
  font-size: 13px;
  padding: 12px;
}
.footer{
  margin-top: 10px;
  color: var(--muted);
  font-size: 12px;
}
</style>
</head>
<body>
<header style="background:#2c5f8a;color:#fff;padding:12px 20px;display:flex;align-items:center;gap:10px;">
  <span style="font-size:1.1rem;font-weight:bold;">ABC商事(株)名簿</span>
  <nav style="display:flex;align-items:center;flex:1;">
    <a href="index.php" style="color:#cde;text-decoration:none;font-size:.85rem;margin-left:14px;">出欠入力</a>
    <a href="members.php" style="color:#cde;text-decoration:none;font-size:.85rem;margin-left:14px;">会員管理</a>
    <a href="report.php" style="color:#cde;text-decoration:none;font-size:.85rem;margin-left:14px;">レポート</a>
    <a href="backup.php" style="color:#cde;text-decoration:none;font-size:.85rem;margin-left:14px;">バックアップ</a>
    <a href="logout.php" style="color:#ffd0d0;text-decoration:none;font-size:.85rem;margin-left:auto;">ログアウト</a>
  </nav>
</header>
  <div class="wrap">
    <div class="header">
      <div>
        <div class="h1">名簿（<?= h($year) ?>年）</div>
        <div class="sub">DB: members / 並び順: sort_order → name</div>
      </div>

      <div class="card controls">
        <form method="get" action="roster.php" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <label style="font-weight:800;">年</label>
          <select name="year">
            <?php
              // 年候補がDBに無い場合でも、現在年を選択肢にする
              $opts = $years ?: [$year];
              if (!in_array($year, $opts, true)) $opts[] = $year;
              rsort($opts);

              foreach ($opts as $y):
            ?>
              <option value="<?= h($y) ?>" <?= ((int)$y === (int)$year) ? 'selected' : '' ?>><?= h($y) ?>年</option>
            <?php endforeach; ?>
          </select>
          <button class="btn" type="submit">表示</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="tabs" id="tabs">
        <button class="tab active" data-target="all">全部（<?= count($rows) ?>）</button>
        <button class="tab" data-target="male">男性（<?= count($groups['male']) ?>）</button>
        <button class="tab" data-target="female">女性（<?= count($groups['female']) ?>）</button>
        <button class="tab" data-target="other">その他（<?= count($groups['other']) ?>）</button>
      </div>

      <div class="grid">
        <?php foreach (['male','female','other'] as $k): ?>
          <div class="list section" data-section="<?= h($k) ?>">
            <h2><?= h($labels[$k]) ?></h2>

            <?php if (count($groups[$k]) === 0): ?>
              <div class="empty">この年の「<?= h($labels[$k]) ?>」はまだ登録がありません。</div>
            <?php else: ?>
              <?php foreach ($groups[$k] as $m): ?>
                <div class="item">
                  <div class="name"><?= h($m['name']) ?></div>
                  <div class="meta">ID: <?= h($m['id']) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="footer">
        ※ gender は「male / female / other」を想定しています。<br>
        ※ 追加や修正は members テーブルを更新してください。
      </div>
    </div>
  </div>

<script>
(() => {
  const tabs = document.querySelectorAll('.tab');
  const sections = document.querySelectorAll('.section');

  function setActive(target){
    tabs.forEach(t => t.classList.toggle('active', t.dataset.target === target));

    sections.forEach(s => {
      const key = s.dataset.section;
      if (target === 'all') {
        s.style.display = '';
      } else {
        s.style.display = (key === target) ? '' : 'none';
      }
    });
  }

  tabs.forEach(t => t.addEventListener('click', () => setActive(t.dataset.target)));
})();
</script>
</body>
</html>
