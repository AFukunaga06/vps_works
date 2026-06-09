<?php
/**
 * 操作ログ記録ヘルパー
 * 使い方: log_action($pdo, 'add_member', '山田太郎を追加');
 */
function log_action(PDO $pdo, string $action, string $detail = ''): void {
    try {
        $user      = $_SESSION['login_user'] ?? 'sugita';
        $page      = basename($_SERVER['PHP_SELF'] ?? '');
        $ip        = $_SERVER['HTTP_X_FORWARDED_FOR']
                     ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
                     : ($_SERVER['REMOTE_ADDR'] ?? '');
        $ip = trim($ip);
        $pdo->prepare(
            "INSERT INTO operation_logs (user_name, action, page, detail, ip_address)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$user, $action, $page, $detail, $ip]);
    } catch (Throwable $e) {
        // ログ失敗は握りつぶす（本処理を止めない）
        error_log('log_action error: ' . $e->getMessage());
    }
}
