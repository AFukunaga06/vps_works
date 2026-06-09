<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('POST リクエストのみ受け付けています。', 405);
}

try {
    $input = getInputData();

    $progressScope = trim((string) ($input['progress_scope'] ?? 'chapter'));
    $testament     = trim((string) ($input['testament'] ?? ''));
    $bookName      = trim((string) ($input['book_name'] ?? ''));
    $chapter       = (int) ($input['chapter'] ?? 0);
    $verseStart    = (int) ($input['verse_start'] ?? 0);
    $verseEnd      = (int) ($input['verse_end'] ?? 0);
    $isRead        = !empty($input['is_read']) ? 1 : 0;
    $readDate      = validateDateString(isset($input['read_date']) ? (string) $input['read_date'] : null);
    $memo          = trim((string) ($input['memo'] ?? ''));

    if (!in_array($progressScope, ['chapter', 'verse'], true)) {
        throw new InvalidArgumentException('進捗種別が不正です。');
    }

    if (!in_array($testament, ['旧約', '新約'], true)) {
        throw new InvalidArgumentException('区分が不正です。');
    }

    if ($bookName === '') {
        throw new InvalidArgumentException('書名は必須です。');
    }

    if ($chapter < 1) {
        throw new InvalidArgumentException('章番号が不正です。');
    }

    if (mb_strlen($memo) > 500) {
        throw new InvalidArgumentException('メモは500文字以内で入力してください。');
    }

    if ($progressScope === 'chapter') {
        $verseStart = 0;
        $verseEnd   = 0;
    } else {
        $maxVerseCount = getMaxVerseCount($bookName, $chapter);
        if ($maxVerseCount === null) {
            throw new InvalidArgumentException('この章の節数データが見つかりません。');
        }
        if ($verseStart < 1 || $verseEnd < 1) {
            throw new InvalidArgumentException('節範囲を指定してください。');
        }
        if ($verseStart > $verseEnd) {
            throw new InvalidArgumentException('終了節は開始節以上にしてください。');
        }
        if ($verseEnd > $maxVerseCount) {
            throw new InvalidArgumentException('指定章の最大節数を超えています。');
        }
    }

    if ($isRead === 1 && $readDate === null) {
        $readDate = (new DateTimeImmutable('today'))->format('Y-m-d');
    }

    if ($isRead === 0) {
        $readDate = null;
    }

    $pdo    = getPdo();
    $userId = getCurrentMemberId();

    if (!bookAndChapterExists($pdo, $bookName, $chapter)) {
        throw new InvalidArgumentException('存在しない聖書箇所です。');
    }

    if ($isRead === 0 && $memo === '' && $readDate === null) {
        $deleteStmt = $pdo->prepare(
            'DELETE FROM reading_progress
             WHERE user_id = :user_id
               AND book_name = :book_name
               AND chapter = :chapter
               AND progress_scope = :progress_scope
               AND verse_start = :verse_start
               AND verse_end = :verse_end'
        );
        $deleteStmt->bindValue(':user_id',        $userId,        PDO::PARAM_INT);
        $deleteStmt->bindValue(':book_name',       $bookName,      PDO::PARAM_STR);
        $deleteStmt->bindValue(':chapter',         $chapter,       PDO::PARAM_INT);
        $deleteStmt->bindValue(':progress_scope',  $progressScope, PDO::PARAM_STR);
        $deleteStmt->bindValue(':verse_start',     $verseStart,    PDO::PARAM_INT);
        $deleteStmt->bindValue(':verse_end',       $verseEnd,      PDO::PARAM_INT);
        $deleteStmt->execute();
    } else {
        $saveStmt = $pdo->prepare(
            "INSERT INTO reading_progress
                (user_id, progress_scope, testament, book_name, chapter, verse_start, verse_end, is_read, read_date, memo)
             VALUES
                (:user_id, :progress_scope, :testament, :book_name, :chapter, :verse_start, :verse_end, :is_read, :read_date, :memo)
             ON DUPLICATE KEY UPDATE
                progress_scope = VALUES(progress_scope),
                testament      = VALUES(testament),
                verse_start    = VALUES(verse_start),
                verse_end      = VALUES(verse_end),
                is_read        = VALUES(is_read),
                read_date      = VALUES(read_date),
                memo           = VALUES(memo),
                updated_at     = CURRENT_TIMESTAMP"
        );
        $saveStmt->bindValue(':user_id',        $userId,        PDO::PARAM_INT);
        $saveStmt->bindValue(':progress_scope',  $progressScope, PDO::PARAM_STR);
        $saveStmt->bindValue(':testament',        $testament,     PDO::PARAM_STR);
        $saveStmt->bindValue(':book_name',        $bookName,      PDO::PARAM_STR);
        $saveStmt->bindValue(':chapter',          $chapter,       PDO::PARAM_INT);
        $saveStmt->bindValue(':verse_start',      $verseStart,    PDO::PARAM_INT);
        $saveStmt->bindValue(':verse_end',        $verseEnd,      PDO::PARAM_INT);
        $saveStmt->bindValue(':is_read',          $isRead,        PDO::PARAM_INT);
        $saveStmt->bindValue(':read_date',        $readDate,      $readDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $saveStmt->bindValue(':memo',             $memo === '' ? null : $memo, $memo === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $saveStmt->execute();
    }

    jsonResponse([
        'success' => true,
        'message' => '保存しました。',
        'data'    => [
            'progress_scope' => $progressScope,
            'testament'      => $testament,
            'book_name'      => $bookName,
            'chapter'        => $chapter,
            'verse_start'    => $verseStart,
            'verse_end'      => $verseEnd,
            'is_read'        => $isRead,
            'read_date'      => $readDate,
            'memo'           => $memo,
        ],
        'summary' => buildProgressSummary($pdo, $userId),
    ]);
} catch (InvalidArgumentException $exception) {
    errorResponse($exception->getMessage(), 422);
} catch (Throwable $exception) {
    errorResponse('保存中にエラーが発生しました。' . $exception->getMessage(), 500);
}
