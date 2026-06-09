<?php
require_once 'config.php';

$works = [];
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM works WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $works = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // DBに接続できない場合はサンプルデータを表示
    $works = [
        ['id'=>1,'title'=>'出席簿システム','description'=>'教会の出欠管理を効率化するWebアプリです。','url'=>'#'],
        ['id'=>2,'title'=>'聖書通読表アプリ','description'=>'聖書の通読計画を管理・記録できるアプリです。','url'=>'#'],
        ['id'=>3,'title'=>'顧客管理システム','description'=>'顧客情報を一元管理するCRMシステムです。','url'=>'#'],
        ['id'=>4,'title'=>'作品紹介ページ','description'=>'制作した作品をまとめたポートフォリオです。','url'=>'#'],
        ['id'=>5,'title'=>'AI相談サイト','description'=>'AIを活用した相談・サポートサービスです。','url'=>'#'],
    ];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>フククの作品集</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- 夕焼け空の背景 -->
<div class="sky-scene">
    <div class="sun-container">
        <div class="sun-glow"></div>
        <div class="sun"></div>
    </div>
    <div class="cloud cloud-1"></div>
    <div class="cloud cloud-2"></div>
    <div class="cloud cloud-3"></div>
    <div class="cloud cloud-4"></div>
</div>

<!-- 海の波 -->
<div class="ocean-container">
    <svg class="wave wave-1" viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0,40 C180,80 360,0 540,40 C720,80 900,0 1080,40 C1260,80 1440,0 1440,40 L1440,80 L0,80 Z" fill="rgba(10,60,120,0.5)"/>
    </svg>
    <svg class="wave wave-2" viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0,50 C200,10 400,70 600,50 C800,30 1000,70 1200,50 C1320,35 1400,55 1440,50 L1440,80 L0,80 Z" fill="rgba(10,80,150,0.6)"/>
    </svg>
    <svg class="wave wave-3" viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0,60 C240,20 480,70 720,60 C960,50 1200,20 1440,60 L1440,80 L0,80 Z" fill="rgba(8,50,100,0.8)"/>
    </svg>
</div>

<!-- メインコンテンツ -->
<div class="page-wrapper">
    <header class="site-header">
        <div class="header-decoration">
            <span class="star">✦</span>
            <span class="star">✦</span>
            <span class="star">✦</span>
        </div>
        <h1 class="site-title">フククの作品集</h1>
        <p class="site-subtitle">
            こちらはフククが制作した作品をまとめたページです。<br>
            気になる作品があれば、ぜひご覧ください。
        </p>
    </header>

    <main class="works-section">
        <div class="section-label">作品一覧</div>
        <div class="works-grid">
            <?php foreach ($works as $work): ?>
            <article class="work-card">
                <div class="card-inner">
                    <div class="card-icon">
                        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="20" cy="20" r="18" stroke="currentColor" stroke-width="1.5" opacity="0.6"/>
                            <path d="M12 20 L18 26 L28 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h2 class="card-title"><?= htmlspecialchars($work['title']) ?></h2>
                    <p class="card-description"><?= htmlspecialchars($work['description']) ?></p>
                    <div class="card-footer">
                        <a href="<?= htmlspecialchars($work['url']) ?>" class="card-btn" target="_blank" rel="noopener">
                            作品を見る
                            <svg class="btn-arrow" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </main>

    <footer class="site-footer">
        <p>© <?= date('Y') ?> フクク All Rights Reserved.</p>
        <a href="admin.php" class="admin-link">管理</a>
    </footer>
</div>

</body>
</html>
