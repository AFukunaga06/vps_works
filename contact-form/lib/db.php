<?php

function db_connect(): ?PDO
{
    if (DB_NAME === '') {
        return null;
    }
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('DB connect error: ' . $e->getMessage());
        return null;
    }
}

function db_save_log(array $data, array $fields): void
{
    $pdo = db_connect();
    if ($pdo === null) {
        return;
    }

    $name    = $data['name']    ?? '';
    $email   = $data['email']   ?? '';
    $subject = $data['subject'] ?? '';
    $message = $data['message'] ?? '';

    // FORM_FIELDSのうち基本4項目以外をextra_dataに保存
    $base   = ['name', 'email', 'subject', 'message'];
    $extra  = [];
    foreach ($fields as $f) {
        if (!in_array($f['name'], $base, true)) {
            $extra[$f['name']] = $data[$f['name']] ?? '';
        }
    }

    $sql = '
        INSERT INTO contact_logs
            (ip_address, name, email, subject, message, extra_data)
        VALUES
            (:ip, :name, :email, :subject, :message, :extra)
    ';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
            ':name'    => $name,
            ':email'   => $email,
            ':subject' => $subject,
            ':message' => $message,
            ':extra'   => json_encode($extra, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (PDOException $e) {
        error_log('DB insert error: ' . $e->getMessage());
    }
}
