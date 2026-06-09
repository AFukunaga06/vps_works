<?php
declare(strict_types=1);

const DB_HOST    = 'localhost';
const DB_NAME    = 'bible_reading';
const DB_USER    = 'bible_reader';
const DB_PASS    = 'BibleReader_2026!';
const DB_CHARSET = 'utf8mb4';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getVerseCounts(): array
{
    static $counts = null;
    if (is_array($counts)) {
        return $counts;
    }
    $path = __DIR__ . '/verse_counts.php';
    if (!is_file($path)) {
        $counts = [];
        return $counts;
    }
    $loaded = require $path;
    $counts = is_array($loaded) ? $loaded : [];
    return $counts;
}

function getMaxVerseCount(string $bookName, int $chapter): ?int
{
    $counts = getVerseCounts();
    return isset($counts[$bookName][$chapter]) ? (int) $counts[$bookName][$chapter] : null;
}

function getPdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('SET NAMES ' . DB_CHARSET);
    return $pdo;
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function errorResponse(string $message, int $statusCode = 400): void
{
    jsonResponse(['success' => false, 'message' => $message], $statusCode);
}

function getInputData(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

function validateDateString(?string $date): ?string
{
    if ($date === null) {
        return null;
    }
    $date = trim($date);
    if ($date === '') {
        return null;
    }
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('読了日は YYYY-MM-DD 形式で入力してください。');
    }
    return $date;
}

function bookAndChapterExists(PDO $pdo, string $bookName, int $chapter): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM bible_books WHERE book_name = :book_name AND chapters_count >= :chapter'
    );
    $stmt->bindValue(':book_name', $bookName, PDO::PARAM_STR);
    $stmt->bindValue(':chapter', $chapter, PDO::PARAM_INT);
    $stmt->execute();
    return (int) $stmt->fetchColumn() > 0;
}

function buildProgressSummary(PDO $pdo, int $userId): array
{
    $totalsStmt = $pdo->query(
        "SELECT
            SUM(chapters_count) AS total_count,
            SUM(CASE WHEN testament = '旧約' THEN chapters_count ELSE 0 END) AS old_count,
            SUM(CASE WHEN testament = '新約' THEN chapters_count ELSE 0 END) AS new_count
         FROM bible_books"
    );
    $totals = $totalsStmt->fetch() ?: [];

    $readsStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_read,
            SUM(CASE WHEN testament = '旧約' THEN 1 ELSE 0 END) AS old_read,
            SUM(CASE WHEN testament = '新約' THEN 1 ELSE 0 END) AS new_read
         FROM reading_progress
         WHERE user_id = :user_id AND progress_scope = 'chapter' AND is_read = 1"
    );
    $readsStmt->execute(['user_id' => $userId]);
    $reads = $readsStmt->fetch() ?: [];

    $format = static function (int $read, int $total): array {
        return [
            'read'    => $read,
            'total'   => $total,
            'percent' => $total > 0 ? round(($read / $total) * 100, 1) : 0.0,
        ];
    };

    return [
        'all' => $format((int) ($reads['total_read'] ?? 0), (int) ($totals['total_count'] ?? 0)),
        'old' => $format((int) ($reads['old_read'] ?? 0),   (int) ($totals['old_count'] ?? 0)),
        'new' => $format((int) ($reads['new_read'] ?? 0),   (int) ($totals['new_count'] ?? 0)),
    ];
}

function fetchRecentVerseProgress(PDO $pdo, int $userId, int $limit = 10): array
{
    $stmt = $pdo->prepare(
        "SELECT testament, book_name, chapter, verse_start, verse_end, is_read, read_date, memo, updated_at
         FROM reading_progress
         WHERE user_id = :user_id AND progress_scope = 'verse'
         ORDER BY updated_at DESC
         LIMIT :limit_count"
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit_count', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ===== メンバー管理 =====

function getActiveMembers(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, name FROM members WHERE is_active = 1 ORDER BY furigana, name'
    )->fetchAll();
}

const MEMBER_LIMIT = 200;

function getOrCreateMemberByName(PDO $pdo, string $name): array
{
    $stmt = $pdo->prepare('SELECT id, name FROM members WHERE name = :name');
    $stmt->execute(['name' => $name]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }
    $count = (int) $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn();
    if ($count >= MEMBER_LIMIT) {
        throw new OverflowException('メンバー登録数が上限（' . MEMBER_LIMIT . '名）に達しています。管理者にお問い合わせください。');
    }
    $ins = $pdo->prepare('INSERT INTO members (name) VALUES (:name)');
    $ins->execute(['name' => $name]);
    return ['id' => (int) $pdo->lastInsertId(), 'name' => $name];
}

function getCurrentMemberId(): int
{
    return (int) ($_SESSION['bible_member_id'] ?? 1);
}

function getCurrentMemberName(): string
{
    return (string) ($_SESSION['bible_member_name'] ?? 'ゲスト');
}

function setCurrentMember(int $id, string $name): void
{
    $_SESSION['bible_member_id'] = $id;
    $_SESSION['bible_member_name'] = $name;
}
