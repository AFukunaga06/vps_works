<?php

function rate_limit_check(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key     = 'rl_' . md5($ip);
    $limit   = defined('RATE_LIMIT_COUNT')   ? RATE_LIMIT_COUNT   : 3;
    $minutes = defined('RATE_LIMIT_MINUTES') ? RATE_LIMIT_MINUTES : 10;
    $expire  = $minutes * 60;

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'start' => time()];
    }

    $elapsed = time() - $_SESSION[$key]['start'];

    // 制限時間を超えていたらリセット
    if ($elapsed > $expire) {
        $_SESSION[$key] = ['count' => 0, 'start' => time()];
    }

    $_SESSION[$key]['count']++;

    return $_SESSION[$key]['count'] <= $limit;
}
