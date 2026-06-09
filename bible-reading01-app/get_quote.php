<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('GET リクエストのみ受け付けています。', 405);
}

try {
    $bookName = trim((string) ($_GET['book_name'] ?? ''));
    $chapter = (int) ($_GET['chapter'] ?? 0);

    if ($bookName === '' || $chapter < 1) {
        throw new InvalidArgumentException('書名と章を指定してください。');
    }

    $pdo = getPdo();

    if (!bookAndChapterExists($pdo, $bookName, $chapter)) {
        throw new InvalidArgumentException('存在しない聖書箇所です。');
    }

    $quoteStmt = $pdo->prepare(
        'SELECT verse_start, verse_end, quote_text FROM bible_quotes WHERE book_name = :book_name AND chapter = :chapter ORDER BY verse_start ASC'
    );
    $quoteStmt->execute([
        'book_name' => $bookName,
        'chapter' => $chapter,
    ]);
    $quotes = $quoteStmt->fetchAll();

    if ($quotes === []) {
        jsonResponse([
            'success' => true,
            'data' => [
                'book_name' => $bookName,
                'chapter' => $chapter,
                'quotes' => [],
                'display_text' => 'この章の引用データはまだ登録されていません。`seed_quotes.sql` に仮引用を追加し、後から正式な本文データへ差し替えられる構造です。',
            ],
        ]);
    }

    $parts = [];
    foreach ($quotes as $quote) {
        $parts[] = sprintf(
            '%d-%d節: %s',
            (int) $quote['verse_start'],
            (int) $quote['verse_end'],
            $quote['quote_text']
        );
    }

    jsonResponse([
        'success' => true,
        'data' => [
            'book_name' => $bookName,
            'chapter' => $chapter,
            'quotes' => $quotes,
            'display_text' => implode("\n\n", $parts),
        ],
    ]);
} catch (InvalidArgumentException $exception) {
    errorResponse($exception->getMessage(), 422);
} catch (Throwable $exception) {
    errorResponse('引用取得中にエラーが発生しました。' . $exception->getMessage(), 500);
}
