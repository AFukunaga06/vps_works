<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST only']);
    exit;
}

require_once __DIR__ . '/config.php';

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Real IP (proxy対応)
$ip = '';
foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
    if (!empty($_SERVER[$key])) {
        $ip = trim(explode(',', $_SERVER[$key])[0]);
        break;
    }
}

// Bot detection
$is_bot = 0;
if (BOT_FILTER) {
    $bots = ['googlebot','bingbot','slurp','duckduckbot','baiduspider','yandexbot',
             'sogou','facebot','ia_archiver','bot/','crawler','spider','wget/',
             'curl/','python-','java/','scrapy','headlesschrome','phantomjs',
             'selenium','lighthouse','pagespeed','uptimerobot','pingdom','prerender'];
    $ua_lc = strtolower($ua);
    foreach ($bots as $b) {
        if (strpos($ua_lc, $b) !== false) { $is_bot = 1; break; }
    }
}

$ip_hash     = hash('sha256', $ip . 'vc_salt_2026_fuku');
$session_id  = hash('sha256', $ip . ($ua ?: 'na') . date('YmdH') . 'sess');
$url         = substr($_POST['url'] ?? '', 0, 500);
$page_title  = substr($_POST['title'] ?? '', 0, 300);

// Referrer: host only
$raw_ref = $_POST['referrer'] ?? '';
$referrer = '';
if ($raw_ref) {
    $host = parse_url($raw_ref, PHP_URL_HOST);
    $referrer = $host ?: substr($raw_ref, 0, 200);
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    echo json_encode(['status' => 'error']);
    exit;
}

// GeoIP (キャッシュ付き、botはスキップ)
$country_code = '';
$country_name = '';
if (GEOIP_ENABLED && !$is_bot && $ip) {
    $stmt = $pdo->prepare(
        "SELECT country_code, country_name FROM ip_country_cache
         WHERE ip_hash = ? AND cached_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    $stmt->execute([$ip_hash]);
    $cached = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cached) {
        $country_code = $cached['country_code'];
        $country_name = $cached['country_name'];
    } else {
        $geo = @json_decode(@file_get_contents(
            "http://ip-api.com/json/{$ip}?fields=country,countryCode&lang=ja", false,
            stream_context_create(['http' => ['timeout' => 2]])
        ), true);
        if ($geo && isset($geo['country']) && $geo['country'] !== 'private range') {
            $country_code = $geo['countryCode'] ?? '';
            $country_name = $geo['country'] ?? '';
        }
        $pdo->prepare(
            "INSERT INTO ip_country_cache (ip_hash, country_code, country_name)
             VALUES (?,?,?) ON DUPLICATE KEY UPDATE
             country_code=VALUES(country_code),
             country_name=VALUES(country_name),
             cached_at=NOW()"
        )->execute([$ip_hash, $country_code, $country_name]);
    }
}

$pdo->prepare(
    "INSERT INTO page_views
     (session_id, url, page_title, ip_hash, user_agent, referrer, country_code, country_name, is_bot)
     VALUES (?,?,?,?,?,?,?,?,?)"
)->execute([$session_id, $url, $page_title, $ip_hash, $ua, $referrer,
            $country_code, $country_name, $is_bot]);

echo json_encode(['status' => 'ok']);
