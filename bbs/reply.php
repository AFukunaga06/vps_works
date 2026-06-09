<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/rate_limiter.php';

// POSTのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// スレッドID取得
$thread_id = (int)($_POST['thread_id'] ?? 0);
if ($thread_id <= 0) {
    header('Location: index.php');
    exit;
}

// CSRFチェック
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_err'] = '不正なリクエストです。最初からやり直してください。';
    header('Location: thread.php?id=' . $thread_id);
    exit;
}

// ハニーポット（Bot対策）
if (!empty($_POST['website'])) {
    header('Location: thread.php?id=' . $thread_id);
    exit;
}

// スレッド存在確認
$thread = get_thread($thread_id);
if ($thread === null) {
    header('Location: index.php');
    exit;
}

// レート制限
if (!rate_limit_check()) {
    $_SESSION['flash_err'] = 'しばらく時間をおいてから再度お試しください。';
    header('Location: thread.php?id=' . $thread_id);
    exit;
}

// 入力取得・サニタイズ
$body   = trim($_POST['body']   ?? '');
$author = trim($_POST['author'] ?? '');
$email  = trim($_POST['email']  ?? '');
$ip     = $_SERVER['REMOTE_ADDR'] ?? '';

// バリデーション
$errors = [];

if ($body === '') {
    $errors[] = '本文を入力してください。';
} elseif (mb_strlen($body) > MAX_BODY_LEN) {
    $errors[] = '本文は' . MAX_BODY_LEN . '文字以内で入力してください。';
}

if (mb_strlen($author) > MAX_AUTHOR_LEN) {
    $errors[] = '名前は' . MAX_AUTHOR_LEN . '文字以内で入力してください。';
}

if ($email !== '') {
    if (mb_strlen($email) > MAX_EMAIL_LEN) {
        $errors[] = 'メールアドレスは' . MAX_EMAIL_LEN . '文字以内で入力してください。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'メールアドレスの形式が正しくありません。';
    }
}

if (!empty($errors)) {
    $_SESSION['flash_err'] = implode(' ', $errors);
    $_SESSION['reply_old'] = compact('body', 'author', 'email');
    header('Location: thread.php?id=' . $thread_id);
    exit;
}

// DB挿入
try {
    insert_reply($thread_id, $body, $author ?: '名無し', $email, $ip);
    $_SESSION['flash_ok'] = '返信しました。';
    header('Location: thread.php?id=' . $thread_id);
} catch (PDOException $e) {
    error_log('BBS insert_reply error: ' . $e->getMessage());
    $_SESSION['flash_err'] = 'データベースエラーが発生しました。';
    $_SESSION['reply_old'] = compact('body', 'author', 'email');
    header('Location: thread.php?id=' . $thread_id);
}
exit;
