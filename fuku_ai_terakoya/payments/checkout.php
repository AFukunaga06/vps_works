<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/square.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: plans.php');
    exit;
}
csrf_check();

$plan_code = trim((string)($_POST['plan_code'] ?? ''));
$name      = trim((string)($_POST['name']      ?? ''));
$email     = trim((string)($_POST['email']     ?? ''));

if ($plan_code === '' || $name === '' || $email === '') {
    flash_set('error', '入力に不備があります');
    header('Location: plans.php'); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'メールアドレスの形式が正しくありません');
    header('Location: plans.php'); exit;
}
if (mb_strlen($name) > 100 || mb_strlen($email) > 255) {
    flash_set('error', '入力が長すぎます');
    header('Location: plans.php'); exit;
}
if (!square_is_configured()) {
    flash_set('error', '決済の準備が整っていません。管理者にお問い合わせください。');
    header('Location: plans.php'); exit;
}

$pdo = db();
$plan = $pdo->prepare("SELECT * FROM plans WHERE code = ? AND is_active = 1 LIMIT 1");
$plan->execute([$plan_code]);
$plan = $plan->fetch();
if (!$plan) {
    flash_set('error', '指定されたプランが見つかりません');
    header('Location: plans.php'); exit;
}

try {
    $pdo->beginTransaction();

    // students upsert
    $st = $pdo->prepare("SELECT id FROM students WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $studentId = (int)($st->fetchColumn() ?: 0);
    if ($studentId === 0) {
        $ins = $pdo->prepare("INSERT INTO students (name, email) VALUES (?, ?)");
        $ins->execute([$name, $email]);
        $studentId = (int)$pdo->lastInsertId();
    } else {
        $pdo->prepare("UPDATE students SET name = ? WHERE id = ?")->execute([$name, $studentId]);
    }

    // payments を pending で先に作成（reference_id にIDを使う）
    $insP = $pdo->prepare(
        "INSERT INTO payments
          (student_id, plan_id, amount, currency, status,
           square_environment, customer_name, customer_email, source_ip, user_agent)
         VALUES (?, ?, ?, 'JPY', 'pending', ?, ?, ?, ?, ?)"
    );
    $insP->execute([
        $studentId, (int)$plan['id'], (int)$plan['amount'],
        SQUARE_ENV, $name, $email,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
    $paymentId = (int)$pdo->lastInsertId();

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_set('error', 'システムエラー: ' . $e->getMessage());
    header('Location: plans.php'); exit;
}

// 戻り先URL（HTTP/HTTPS自動判定）
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$redirect_url = $scheme . '://' . $host . APP_ROOT_URL . '/payments/complete.php?p=' . $paymentId;

$resp = square_create_payment_link([
    'name'         => $plan['name'] . ' - ' . APP_NAME,
    'amount'       => (int)$plan['amount'],
    'reference_id' => 'PAYMENT_' . $paymentId,
    'description'  => sprintf('%s（%s様）', $plan['name'], $name),
    'redirect_url' => $redirect_url,
    'buyer_email'  => $email,
]);

// 結果を payments に保存
$updRaw = $pdo->prepare("UPDATE payments SET raw_response = ? WHERE id = ?");
$updRaw->execute([substr((string)$resp['raw'], 0, 65000), $paymentId]);

if (!$resp['ok'] || empty($resp['body']['payment_link'])) {
    $err = $resp['error'] ?? 'Square API error';
    $pdo->prepare("UPDATE payments SET status = 'failed', admin_note = ? WHERE id = ?")
        ->execute(['payment_link発行失敗: ' . $err, $paymentId]);
    flash_set('error', '決済リンクの発行に失敗しました: ' . $err);
    header('Location: plans.php'); exit;
}

$link = $resp['body']['payment_link'];
$pdo->prepare(
    "UPDATE payments
       SET square_payment_link_id = ?, square_order_id = ?, square_checkout_url = ?
     WHERE id = ?"
)->execute([
    $link['id']        ?? null,
    $link['order_id']  ?? null,
    $link['url']       ?? null,
    $paymentId,
]);

if (empty($link['url'])) {
    flash_set('error', '決済URLが取得できませんでした');
    header('Location: plans.php'); exit;
}

// Square決済画面へリダイレクト
header('Location: ' . $link['url']);
exit;
