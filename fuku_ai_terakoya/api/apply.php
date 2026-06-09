<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/mailer.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function api_bad(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_bad('POST only', 405);

$raw = file_get_contents('php://input');
$data = [];
if ($raw) {
    $j = json_decode($raw, true);
    if (is_array($j)) $data = $j;
}
if (!$data) $data = $_POST ?? [];

$name           = trim((string)($data['name']           ?? ''));
$email          = trim((string)($data['email']          ?? ''));
$phone          = trim((string)($data['phone']          ?? ''));
$profile        = trim((string)($data['profile']        ?? ''));
$skills         = trim((string)($data['skills']         ?? ''));
$available_days = trim((string)($data['available_days'] ?? ''));
$motivation     = trim((string)($data['motivation']     ?? ''));

if ($name === '' || $email === '' || $motivation === '') api_bad('missing fields');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) api_bad('invalid email');
if (mb_strlen($name) > 100 || mb_strlen($email) > 255 || mb_strlen($phone) > 40) {
    api_bad('field too long');
}

try {
    $stmt = db()->prepare(
        "INSERT INTO instructor_applications
          (name, email, phone, profile, skills, available_days, motivation, status, source_ip, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'new', ?, ?)"
    );
    $stmt->execute([
        $name, $email, $phone, $profile, $skills, $available_days, $motivation,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
    $applicationId = (int)db()->lastInsertId();
} catch (Throwable $e) {
    api_bad('db error: ' . $e->getMessage(), 500);
}

$subjectAdmin = "【講師応募】{$name}様 ({$email})";
$bodyAdmin =
    "講師応募がありました。\n\n" .
    "【ID】#{$applicationId}\n" .
    "【お名前】{$name}\n" .
    "【メール】{$email}\n" .
    "【電話】{$phone}\n" .
    "【プロフィール】\n{$profile}\n\n" .
    "【スキル】\n{$skills}\n\n" .
    "【対応可能曜日】{$available_days}\n\n" .
    "【志望動機】\n{$motivation}\n\n" .
    "----\nフクのAI寺子屋 / api/apply.php\n";

$subjectUser = "【受付】講師応募を受け付けました";
$bodyUser =
    "{$name} 様\n\n" .
    "ご応募ありがとうございます。下記内容で受け付けました。\n" .
    "後日担当よりご連絡いたします。\n\n" .
    "【お名前】{$name}\n【メール】{$email}\n【電話】{$phone}\n\n" .
    "フクのAI寺子屋\n";

$r1 = send_and_log(ADMIN_MAIL, $subjectAdmin, $bodyAdmin, $email, $name, 'instructor_application', $applicationId);
$r2 = send_and_log($email,     $subjectUser,  $bodyUser,  null,   null,  'instructor_application', $applicationId);

echo json_encode([
    'ok' => true,
    'id' => $applicationId,
    'mail' => ['admin' => $r1['ok'], 'user' => $r2['ok']],
], JSON_UNESCAPED_UNICODE);
