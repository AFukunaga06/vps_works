<?php
/** Stripe Customer Portal へリダイレクト（カード変更・解約等） */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/_lib.php';

$user   = lc_require_login();
$tenant = lc_current_tenant();
if ($user['role'] !== 'admin') { http_response_code(403); echo '管理者のみ操作可能です。'; exit; }

$r = stripe_create_portal_session($tenant);
if (!empty($r['error'])) {
    echo '<p>' . htmlspecialchars($r['error']) . '</p><a href="portal.php">戻る</a>';
    exit;
}
header('Location: ' . $r['url']);
