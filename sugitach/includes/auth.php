<?php
/**
 * 認証チェック共通処理
 */

session_start();

require_once __DIR__ . '/../config/database.php';

/**
 * ログイン済みかチェック。未ログインならlogin.phpへリダイレクト
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * 管理者権限チェック
 */
function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die('管理者権限が必要です。');
    }
}

/**
 * CSRFトークン生成
 */
function generateCsrfToken() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * CSRFトークン検証
 */
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

/**
 * ログイン試行回数チェック（ブルートフォース対策）
 * 5回失敗で15分ロック
 */
function isAccountLocked($username) {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT failed_attempts, locked_until FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) return false;
    
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        return true;
    }
    
    return false;
}

/**
 * ログイン失敗回数を記録
 */
function recordFailedLogin($username) {
    $pdo = getDB();
    $stmt = $pdo->prepare('UPDATE users SET failed_attempts = failed_attempts + 1 WHERE username = ?');
    $stmt->execute([$username]);
    
    // 5回失敗で15分ロック
    $stmt = $pdo->prepare('UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE username = ? AND failed_attempts >= 5');
    $stmt->execute([$username]);
}

/**
 * ログイン成功時にカウンターリセット
 */
function resetFailedLogin($username) {
    $pdo = getDB();
    $stmt = $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE username = ?');
    $stmt->execute([$username]);
}

/**
 * HTMLエスケープ（XSS対策）
 */
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
