<?php
require __DIR__ . '/config.php';

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
    DB_USER, DB_PASS,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);

function check_ssl(string $domain, int $port = 443): array {
    $ctx = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'SNI_enabled'       => true,
        ],
    ]);
    $errno = 0; $errstr = '';
    $client = @stream_socket_client(
        "ssl://{$domain}:{$port}",
        $errno, $errstr, 10,
        STREAM_CLIENT_CONNECT, $ctx
    );
    if (!$client) {
        return ['ok' => false, 'error' => $errstr !== '' ? $errstr : '接続失敗'];
    }
    $params = stream_context_get_params($client);
    @fclose($client);
    if (empty($params['options']['ssl']['peer_certificate'])) {
        return ['ok' => false, 'error' => '証明書を取得できませんでした'];
    }
    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
    if (!$cert) {
        return ['ok' => false, 'error' => '証明書の解析に失敗しました'];
    }
    $issuer = $cert['issuer']['CN'] ?? ($cert['issuer']['O'] ?? '');
    return [
        'ok'          => true,
        'valid_from'  => (int)($cert['validFrom_time_t'] ?? 0),
        'valid_to'    => (int)($cert['validTo_time_t'] ?? 0),
        'issuer'      => (string)$issuer,
        'common_name' => (string)($cert['subject']['CN'] ?? ''),
    ];
}

function refresh_domain(PDO $pdo, int $id): void {
    $row = $pdo->prepare('SELECT domain, port FROM ssl_domains WHERE id = ?');
    $row->execute([$id]);
    $r = $row->fetch();
    if (!$r) return;
    $res = check_ssl($r['domain'], (int)$r['port']);
    if ($res['ok']) {
        $stmt = $pdo->prepare(
            'UPDATE ssl_domains
                SET valid_from   = FROM_UNIXTIME(?),
                    valid_to     = FROM_UNIXTIME(?),
                    issuer       = ?,
                    common_name  = ?,
                    last_checked = NOW(),
                    last_error   = NULL
              WHERE id = ?'
        );
        $stmt->execute([
            $res['valid_from'], $res['valid_to'],
            $res['issuer'], $res['common_name'], $id
        ]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE ssl_domains
                SET last_checked = NOW(),
                    last_error   = ?
              WHERE id = ?'
        );
        $stmt->execute([$res['error'], $id]);
    }
}
