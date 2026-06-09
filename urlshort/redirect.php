<?php
require __DIR__ . '/config.php';
$code = $_GET['c'] ?? '';
if (!preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $code)) { http_response_code(404); exit('Invalid'); }
$st = db()->prepare('SELECT id,long_url FROM short_urls WHERE code=?');
$st->execute([$code]);
$r = $st->fetch();
if (!$r) { http_response_code(404); exit('Not Found'); }
db()->prepare('UPDATE short_urls SET clicks=clicks+1, last_access=NOW() WHERE id=?')->execute([$r['id']]);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
$rf = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 2048);
db()->prepare('INSERT INTO click_log (short_id,referer,user_agent,ip) VALUES (?,?,?,?)')->execute([$r['id'],$rf,$ua,$ip]);
header('Location: ' . $r['long_url'], true, 302);
exit;
