<?php
/**
 * 契約書解析ブリッジ（CLI 専用）
 *
 *  使い方: upload.php が
 *    nohup php api_bridge.php <contract_id> < /dev/null > /tmp/log 2>&1 &
 *  で非同期起動する。
 *
 *  処理:
 *    1) lc_contracts.status を 'analyzing' に更新（error_message クリア）
 *    2) Python ai_server (http://127.0.0.1:8000/analyze) に POST（timeout 300s）
 *    3) /analyze は内部で完了時に lc_contracts と lc_contract_clauses を更新する。
 *       cURL レベルで失敗した場合のみここで status='error' を上書きする。
 *
 *  認証: CLI 経由でのみ動作（HTTP からは 403）。テナント越境の心配なし。
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../includes/db.php';

$cid = (int)($argv[1] ?? 0);
if ($cid <= 0) {
    fwrite(STDERR, "usage: php api_bridge.php <contract_id>\n");
    exit(1);
}

$pdo = get_db();

// 対象契約書取得
$stmt = $pdo->prepare("SELECT * FROM lc_contracts WHERE id=?");
$stmt->execute([$cid]);
$contract = $stmt->fetch();
if (!$contract) {
    fwrite(STDERR, "[contract $cid] not found\n");
    exit(1);
}

// 解析中状態へ
$pdo->prepare("UPDATE lc_contracts SET status='analyzing', error_message=NULL WHERE id=?")
    ->execute([$cid]);

$payload = json_encode([
    'contract_id'   => $cid,
    'file_path'     => $contract['file_path'],
    'contract_type' => $contract['contract_type'] ?? '',
    'party_side'    => $contract['party_side']    ?? '甲',
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('http://127.0.0.1:8000/analyze');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 300,   // 5min
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$resp = curl_exec($ch);
$err  = curl_error($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $http >= 400) {
    $msg = $err !== '' ? $err : ('HTTP ' . $http . ': ' . substr((string)$resp, 0, 400));
    fwrite(STDERR, "[contract $cid] /analyze failed: $msg\n");
    $pdo->prepare("UPDATE lc_contracts SET status='error', error_message=? WHERE id=?")
        ->execute([mb_substr($msg, 0, 500), $cid]);
    exit(1);
}

fwrite(STDOUT, "[contract $cid] /analyze ok: " . substr((string)$resp, 0, 500) . "\n");
exit(0);
