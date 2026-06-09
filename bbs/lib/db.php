<?php

function db_connect(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function count_threads(): int
{
    $stmt = db_connect()->query('SELECT COUNT(*) FROM threads');
    return (int) $stmt->fetchColumn();
}

function get_threads(int $page, int $per_page): array
{
    $offset = ($page - 1) * $per_page;
    $stmt   = db_connect()->prepare('
        SELECT t.id, t.title, t.author, t.email, t.created_at,
               COUNT(r.id) AS reply_count
        FROM threads t
        LEFT JOIN replies r ON r.thread_id = t.id
        GROUP BY t.id
        ORDER BY t.created_at DESC
        LIMIT :lim OFFSET :off
    ');
    $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_thread(int $id): ?array
{
    $stmt = db_connect()->prepare('SELECT * FROM threads WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_replies(int $thread_id): array
{
    $stmt = db_connect()->prepare(
        'SELECT * FROM replies WHERE thread_id = :tid ORDER BY created_at ASC'
    );
    $stmt->execute([':tid' => $thread_id]);
    return $stmt->fetchAll();
}

function insert_thread(string $title, string $body, string $author, string $email, string $ip): int
{
    $stmt = db_connect()->prepare(
        'INSERT INTO threads (title, body, author, email, ip_address)
         VALUES (:t, :b, :a, :e, :ip)'
    );
    $stmt->execute([':t' => $title, ':b' => $body, ':a' => $author, ':e' => $email, ':ip' => $ip]);
    return (int) db_connect()->lastInsertId();
}

function insert_reply(int $thread_id, string $body, string $author, string $email, string $ip): void
{
    $stmt = db_connect()->prepare(
        'INSERT INTO replies (thread_id, body, author, email, ip_address)
         VALUES (:tid, :b, :a, :e, :ip)'
    );
    $stmt->execute([':tid' => $thread_id, ':b' => $body, ':a' => $author, ':e' => $email, ':ip' => $ip]);
}
