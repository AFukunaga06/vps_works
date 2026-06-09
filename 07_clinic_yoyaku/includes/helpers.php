<?php
function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function formatDate(string $d): string {
    if (!$d) return '';
    return date('Y年m月d日', strtotime($d));
}

function formatTime(string $t): string {
    return substr($t, 0, 5);
}

function age(string $birth_date): int {
    $birth = new DateTime($birth_date);
    $now   = new DateTime();
    return (int)$birth->diff($now)->y;
}

function genderLabel(string $g): string {
    return match ($g) {
        'male'   => '男性',
        'female' => '女性',
        default  => 'その他',
    };
}

function statusLabel(string $s): string {
    return match ($s) {
        'reserved'  => '予約済',
        'confirmed' => '確認済',
        'completed' => '診察済',
        'cancelled' => 'キャンセル',
        'no_show'   => '未来院',
        default     => $s,
    };
}

function statusBadge(string $s): string {
    $cls = match ($s) {
        'reserved'  => 'bg-primary',
        'confirmed' => 'bg-info text-dark',
        'completed' => 'bg-success',
        'cancelled' => 'bg-secondary',
        'no_show'   => 'bg-danger',
        default     => 'bg-light text-dark',
    };
    return '<span class="badge ' . $cls . '">' . statusLabel($s) . '</span>';
}

function dayLabel(int $d): string {
    return ['日','月','火','水','木','金','土'][$d] ?? '';
}

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
