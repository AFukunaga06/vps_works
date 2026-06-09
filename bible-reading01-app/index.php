<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

// ===== メンバー選択処理 =====
$memberError = null;
$memberSwitched = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'select_member') {
    $inputName = trim((string) ($_POST['member_name'] ?? ''));
    if ($inputName === '') {
        $memberError = 'お名前を入力してください。';
    } elseif (mb_strlen($inputName) > 100) {
        $memberError = 'お名前は100文字以内で入力してください。';
    } else {
        try {
            $pdoForMember = getPdo();
            $member       = getOrCreateMemberByName($pdoForMember, $inputName);
            setCurrentMember((int) $member['id'], (string) $member['name']);
            $memberSwitched = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Throwable $ex) {
            $memberError = 'メンバー登録に失敗しました: ' . $ex->getMessage();
        }
    }
}

$currentUserId   = getCurrentMemberId();
$currentUserName = getCurrentMemberName();

// ===== メインデータ読み込み =====
$errorMessage      = null;
$focusBookName     = '';
$summary = [
    'all' => ['read' => 0, 'total' => 0, 'percent' => 0.0],
    'old' => ['read' => 0, 'total' => 0, 'percent' => 0.0],
    'new' => ['read' => 0, 'total' => 0, 'percent' => 0.0],
];
$books              = [];
$displayBooks       = [];
$verseCounts        = getVerseCounts();
$quoteMap           = [];
$rows               = [];
$recentVerseProgress = [];
$activeMembers      = [];

try {
    $pdo     = getPdo();
    $summary = buildProgressSummary($pdo, $currentUserId);

    $activeMembers = getActiveMembers($pdo);

    $booksStmt = $pdo->query(
        'SELECT testament, book_order, book_name, chapters_count FROM bible_books ORDER BY book_order ASC'
    );
    $books        = $booksStmt->fetchAll();
    $displayBooks = $books;

    $progressStmt = $pdo->prepare(
        "SELECT testament, book_name, chapter, is_read, read_date, memo
         FROM reading_progress
         WHERE user_id = :user_id AND progress_scope = 'chapter'"
    );
    $progressStmt->execute(['user_id' => $currentUserId]);

    $progressMap = [];
    foreach ($progressStmt->fetchAll() as $progress) {
        $progressMap[$progress['book_name'] . ':' . $progress['chapter']] = $progress;
    }

    foreach ($displayBooks as $book) {
        for ($chapter = 1; $chapter <= (int) $book['chapters_count']; $chapter++) {
            $saved  = $progressMap[$book['book_name'] . ':' . $chapter] ?? null;
            $isRead = (int) ($saved['is_read'] ?? 0) === 1;

            $rows[] = [
                'sequence'  => count($rows) + 1,
                'testament' => $book['testament'],
                'book_name' => $book['book_name'],
                'chapter'   => $chapter,
                'passage'   => $book['book_name'] . $chapter . '章',
                'is_read'   => $isRead,
                'read_date' => (string) ($saved['read_date'] ?? ''),
                'memo'      => (string) ($saved['memo'] ?? ''),
            ];
        }
    }

    $quotesStmt = $pdo->query(
        "SELECT book_name, translation, chapter, verse_start, verse_end, quote_text
         FROM bible_quotes
         WHERE translation IN ('文語訳', '口語訳')
         ORDER BY book_name ASC, chapter ASC, verse_start ASC"
    );
    foreach ($quotesStmt->fetchAll() as $quote) {
        $bn  = $quote['book_name'];
        $ch  = (int) $quote['chapter'];
        $tr  = $quote['translation'];
        $quoteMap[$bn][$ch][$tr][] = [
            'verse_start' => (int) $quote['verse_start'],
            'verse_end'   => (int) $quote['verse_end'],
            'quote_text'  => $quote['quote_text'],
        ];
    }

    $recentVerseProgress = fetchRecentVerseProgress($pdo, $currentUserId, 12);
} catch (Throwable $exception) {
    $errorMessage = $exception->getMessage();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$appBasePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
if ($appBasePath === '') {
    $appBasePath = '/';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>聖書通読表・聖書箇所引用システム</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .member-bar {
            background: #1e293b;
            padding: .6rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .member-bar .member-label {
            color: #94a3b8;
            font-size: .85rem;
            white-space: nowrap;
        }
        .member-bar .member-name {
            color: #f1f5f9;
            font-weight: 600;
            font-size: .95rem;
        }
        .member-bar form {
            display: flex;
            gap: .5rem;
            align-items: center;
            flex-wrap: wrap;
            margin-left: auto;
        }
        .member-bar input[type="text"] {
            padding: .3rem .6rem;
            border-radius: 6px;
            border: 1px solid #475569;
            background: #334155;
            color: #f1f5f9;
            font-size: .85rem;
            width: 160px;
        }
        .member-bar input[type="text"]::placeholder { color: #94a3b8; }
        .member-bar select {
            padding: .3rem .6rem;
            border-radius: 6px;
            border: 1px solid #475569;
            background: #334155;
            color: #f1f5f9;
            font-size: .85rem;
        }
        .member-bar button {
            padding: .3rem .8rem;
            border-radius: 6px;
            border: none;
            background: #3b82f6;
            color: #fff;
            font-size: .85rem;
            cursor: pointer;
        }
        .member-bar button:hover { background: #2563eb; }
        .member-error {
            background: #fef2f2;
            color: #b91c1c;
            padding: .5rem 1.5rem;
            font-size: .85rem;
        }
        .member-bar .sep { color: #475569; }
        /* 訳切替ボタン */
        .translation-toggle-wrap {
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .translation-toggle-label {
            font-size: .85rem;
            color: #64748b;
            white-space: nowrap;
        }
        .trans-btn {
            padding: .3rem .8rem;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #475569;
            font-size: .82rem;
            cursor: pointer;
            transition: background .15s;
        }
        .trans-btn:hover { background: #e2e8f0; }
        .trans-btn.active {
            background: #1e40af;
            color: #fff;
            border-color: #1e40af;
            font-weight: 600;
        }
        .no-quote {
            color: #94a3b8;
            font-size: .85rem;
            font-style: italic;
        }
    </style>
</head>
<body>
<div class="page-shell">

    <!-- ===== メンバーバー ===== -->
    <div class="member-bar">
        <span class="member-label">現在のユーザー：</span>
        <span class="member-name"><?= e($currentUserName) ?></span>
        <span class="sep">|</span>
        <form method="post">
            <input type="hidden" name="_action" value="select_member">
            <input type="text" name="member_name" list="memberList"
                   placeholder="名前を入力または選択" maxlength="100"
                   title="登録済みの名前を選ぶか、新しい名前を入力してください">
            <?php if ($activeMembers !== []): ?>
                <datalist id="memberList">
                    <?php foreach ($activeMembers as $m): ?>
                        <option value="<?= e($m['name']) ?>">
                    <?php endforeach; ?>
                </datalist>
            <?php endif; ?>
            <button type="submit">切り替え／登録</button>
        </form>
    </div>
    <?php if ($memberError !== null): ?>
        <div class="member-error"><?= e($memberError) ?></div>
    <?php endif; ?>

    <header class="hero">
        <div class="hero-copy">
            <p class="hero-kicker">Bible Reading Companion</p>
            <h1>聖書通読表・聖書箇所引用システム</h1>
            <p class="hero-text">旧約・新約 全66巻1189章を章単位でチェック、節単位クイック登録、引用表示で管理できます。</p>
            <div class="hero-badges">
                <span>旧約39巻 · 新約27巻</span>
                <span>PHP + MySQL</span>
                <span><?= e($currentUserName) ?>さんの通読記録</span>
            </div>
        </div>
        <div class="hero-side">
            <div class="hero-note">
                <strong>使い分け</strong>
                <p>章ごとに読み終えたら一覧で保存し、細かく管理したい箇所は上部のプルダウンから節範囲を登録できます。</p>
            </div>
        </div>
    </header>

    <?php if ($errorMessage !== null): ?>
        <section class="error-box">
            <h2>データ読み込みエラー</h2>
            <p><?= e($errorMessage) ?></p>
            <p>`schema.sql` と `seed_books.sql` の実行、および `db.php` の接続情報を確認してください。</p>
        </section>
    <?php else: ?>
        <section class="summary-grid">
            <article class="summary-card summary-card-all">
                <span class="summary-label">全体進捗</span>
                <strong class="summary-value" data-summary-value="all"><?= e((string) $summary['all']['read']) ?>章 / <?= e((string) $summary['all']['total']) ?>章</strong>
                <span class="summary-percent" data-summary-percent="all"><?= e(number_format((float) $summary['all']['percent'], 1)) ?>%</span>
            </article>
            <article class="summary-card summary-card-old">
                <span class="summary-label">旧約進捗</span>
                <strong class="summary-value" data-summary-value="old"><?= e((string) $summary['old']['read']) ?>章 / <?= e((string) $summary['old']['total']) ?>章</strong>
                <span class="summary-percent" data-summary-percent="old"><?= e(number_format((float) $summary['old']['percent'], 1)) ?>%</span>
            </article>
            <article class="summary-card summary-card-new">
                <span class="summary-label">新約進捗</span>
                <strong class="summary-value" data-summary-value="new"><?= e((string) $summary['new']['read']) ?>章 / <?= e((string) $summary['new']['total']) ?>章</strong>
                <span class="summary-percent" data-summary-percent="new"><?= e(number_format((float) $summary['new']['percent'], 1)) ?>%</span>
            </article>
        </section>

        <section class="quick-grid">
            <article class="quick-card">
                <div class="section-head">
                    <div>
                        <h2>節単位クイック登録</h2>
                        <p>書名・章・節の範囲を選んで読了保存できます。</p>
                    </div>
                </div>
                <form id="verseQuickForm" class="verse-form">
                    <label>
                        <span>書名</span>
                        <select id="verseBookSelect" required>
                            <?php
                            $currentGroup = null;
                            foreach ($displayBooks as $book):
                                if ($currentGroup !== $book['testament']):
                                    if ($currentGroup !== null) {
                                        echo '</optgroup>';
                                    }
                                    $currentGroup = $book['testament'];
                                    echo '<optgroup label="' . e($currentGroup) . '">';
                                endif;
                                ?>
                                <option
                                    value="<?= e($book['book_name']) ?>"
                                    data-testament="<?= e($book['testament']) ?>"
                                    data-chapters-count="<?= e((string) $book['chapters_count']) ?>"
                                >
                                    <?= e($book['book_name']) ?>
                                </option>
                            <?php endforeach;
                            if ($currentGroup !== null) {
                                echo '</optgroup>';
                            }
                            ?>
                        </select>
                    </label>
                    <label>
                        <span>章</span>
                        <select id="verseChapterSelect" required></select>
                    </label>
                    <label>
                        <span>開始節</span>
                        <select id="verseStartSelect" required></select>
                    </label>
                    <label>
                        <span>終了節</span>
                        <select id="verseEndSelect" required></select>
                    </label>
                    <label class="inline-toggle">
                        <input type="checkbox" id="verseReadCheckbox" checked>
                        <span>この節範囲を読了として記録する</span>
                    </label>
                    <label>
                        <span>読了日</span>
                        <input type="date" id="verseReadDate">
                    </label>
                    <label class="verse-memo-field">
                        <span>メモ</span>
                        <input type="text" id="verseMemoInput" maxlength="500" placeholder="例: 1-2節まで読了">
                    </label>
                    <div class="verse-actions">
                        <button type="submit" class="save-button">節進捗を保存</button>
                        <span class="save-status" id="verseSaveStatus" aria-live="polite"></span>
                    </div>
                </form>
            </article>

            <article class="quick-card recent-card">
                <div class="section-head">
                    <div>
                        <h2>最近の節記録</h2>
                        <p>新しい順に直近12件を表示します。</p>
                    </div>
                </div>
                <div class="recent-list" id="verseRecentList">
                    <?php if ($recentVerseProgress === []): ?>
                        <p class="empty-state">まだ節単位の記録はありません。</p>
                    <?php else: ?>
                        <?php foreach ($recentVerseProgress as $item): ?>
                            <article class="recent-item" data-recent-key="<?= e($item['book_name'] . ':' . $item['chapter'] . ':' . $item['verse_start'] . ':' . $item['verse_end']) ?>">
                                <strong><?= e($item['book_name']) ?> <?= e((string) $item['chapter']) ?>章 <?= e((string) $item['verse_start']) ?>-<?= e((string) $item['verse_end']) ?>節</strong>
                                <span><?= e((string) ($item['read_date'] ?? '日付未設定')) ?></span>
                                <p><?= e((string) ($item['memo'] ?? 'メモなし')) ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>
        </section>

        <section class="filter-panel">
            <div class="filter-head">
                <div>
                    <h2>絞り込み</h2>
                    <p>書名検索・旧約／新約・読了状況で絞り込めます。</p>
                </div>
                <p class="visible-count" id="visibleCount">表示件数: <?= e((string) count($rows)) ?>件</p>
            </div>
            <div class="filter-grid">
                <label>
                    <span>書名検索</span>
                    <input type="search" id="bookSearch" value="<?= e($focusBookName) ?>" placeholder="例: 創世記、ヨハネ">
                </label>
                <label>
                    <span>区分</span>
                    <select id="testamentFilter">
                        <option value="all" selected>すべて</option>
                        <option value="旧約">旧約のみ</option>
                        <option value="新約">新約のみ</option>
                    </select>
                </label>
                <label>
                    <span>読了状況</span>
                    <select id="statusFilter">
                        <option value="all">すべて</option>
                        <option value="unread">未読のみ</option>
                        <option value="read">読了済みのみ</option>
                    </select>
                </label>
                <div class="filter-actions">
                    <button type="button" class="secondary-button" id="resetFilters">絞り込み解除</button>
                </div>
            </div>
        </section>

        <section class="table-card">
            <div class="table-header">
                <div>
                    <h2>聖書通読一覧</h2>
                    <p>旧約・新約 全1189章を、章単位の進捗、読了日、メモ、引用表示で管理します。</p>
                </div>
                <div class="translation-toggle-wrap">
                    <span class="translation-toggle-label">引用訳：</span>
                    <button type="button" class="trans-btn active" data-translation="文語訳" id="btnBungo">文語訳</button>
                    <button type="button" class="trans-btn" data-translation="口語訳" id="btnKogo">口語訳</button>
                </div>
            </div>
            <div class="table-wrap">
                <table class="reading-table" id="readingTable">
                    <thead>
                    <tr>
                        <th>通し番号</th>
                        <th>区分</th>
                        <th>書名</th>
                        <th>章</th>
                        <th>読む箇所</th>
                        <th>読了</th>
                        <th>読了日</th>
                        <th>メモ</th>
                        <th>引用</th>
                        <th>更新</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $bungoQuotes = $quoteMap[$row['book_name']][$row['chapter']]['文語訳'] ?? [];
                        $kogoQuotes  = $quoteMap[$row['book_name']][$row['chapter']]['口語訳'] ?? [];
                        ?>
                        <tr
                            class="reading-row<?= $row['is_read'] ? ' is-read' : '' ?>"
                            data-book-name="<?= e($row['book_name']) ?>"
                            data-testament="<?= e($row['testament']) ?>"
                            data-chapter="<?= e((string) $row['chapter']) ?>"
                            data-is-read="<?= $row['is_read'] ? '1' : '0' ?>"
                        >
                            <td><?= e((string) $row['sequence']) ?></td>
                            <td><span class="testament-pill testament-<?= $row['testament'] === '旧約' ? 'old' : 'new' ?>"><?= e($row['testament']) ?></span></td>
                            <td><?= e($row['book_name']) ?></td>
                            <td><?= e((string) $row['chapter']) ?></td>
                            <td><?= e($row['passage']) ?></td>
                            <td class="checkbox-cell">
                                <input type="checkbox" class="read-checkbox" <?= $row['is_read'] ? 'checked' : '' ?>>
                            </td>
                            <td>
                                <input type="date" class="read-date" value="<?= e($row['read_date']) ?>">
                            </td>
                            <td>
                                <input type="text" class="memo-input" maxlength="500" value="<?= e($row['memo']) ?>" placeholder="メモを入力">
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="secondary-button quote-button"
                                    aria-expanded="false"
                                >引用を見る</button>
                            </td>
                            <td class="action-cell">
                                <button type="button" class="save-button">更新</button>
                                <span class="save-status" aria-live="polite"></span>
                            </td>
                        </tr>
                        <tr class="quote-detail-row" hidden>
                            <td colspan="10">
                                <div class="inline-quote-panel">
                                    <div class="inline-quote-head">
                                        <strong><?= e($row['book_name']) ?><?= e((string) $row['chapter']) ?>章の引用</strong>
                                    </div>
                                    <div class="inline-quote-content trans-content" data-translation="文語訳">
                                        <?php if ($bungoQuotes === []): ?>
                                            <p class="no-quote">この章の文語訳データはまだ登録されていません。</p>
                                        <?php else: ?>
                                            <?php foreach ($bungoQuotes as $quote): ?>
                                                <p><?= e((string) $quote['verse_start']) ?>-<?= e((string) $quote['verse_end']) ?>節: <?= e($quote['quote_text']) ?></p>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="inline-quote-content trans-content" data-translation="口語訳" hidden>
                                        <?php if ($kogoQuotes === []): ?>
                                            <p class="no-quote">この章の口語訳データはまだ登録されていません。</p>
                                        <?php else: ?>
                                            <?php foreach ($kogoQuotes as $quote): ?>
                                                <p><?= e((string) $quote['verse_start']) ?>-<?= e((string) $quote['verse_end']) ?>節: <?= e($quote['quote_text']) ?></p>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<script id="booksData" type="application/json"><?= json_encode($books, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script id="verseCountsData" type="application/json"><?= json_encode($verseCounts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script>
window.BIBLE_READING_APP = {
    basePath: <?= json_encode($appBasePath, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="script.js?v=20260325-1"></script>
</body>
</html>
