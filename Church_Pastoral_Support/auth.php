<?php
require_once __DIR__.'/config.php';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: '.BASE_URL.'/login.php');
        exit;
    }
}

function require_role(array $roles) {
    require_login();
    if (!in_array($_SESSION['user_role'] ?? '', $roles)) {
        http_response_code(403);
        die('<div style="padding:40px;text-align:center"><h2>アクセス権限がありません</h2><a href="'.BASE_URL.'/">戻る</a></div>');
    }
}

function can_view_confidential() {
    return in_array($_SESSION['user_role'] ?? '', ['admin','pastor']);
}
function is_admin() { return ($_SESSION['user_role'] ?? '') === 'admin'; }
function can_edit_persons() {
    return in_array($_SESSION['user_role'] ?? '', ['admin','pastor','secretary']);
}
function can_manage_users() { return is_admin(); }

function status_label($s) {
    return ['inquiry'=>'問い合わせ者','first_visit'=>'初来会者','regular'=>'継続来会者',
            'seeker'=>'求道者','baptism_prep'=>'洗礼準備中','member'=>'会員',
            'inactive'=>'休会中','ended'=>'終了'][$s] ?? $s;
}
function status_badge($s) {
    $cls = ['inquiry'=>'bg-info text-dark','first_visit'=>'bg-success','regular'=>'bg-primary',
            'seeker'=>'bg-warning text-dark','baptism_prep'=>'bg-purple','member'=>'bg-success',
            'inactive'=>'bg-secondary','ended'=>'bg-danger'];
    $c = $cls[$s] ?? 'bg-secondary';
    return '<span class="badge '.$c.'">'.status_label($s).'</span>';
}
function role_label($r) {
    return ['admin'=>'管理者','pastor'=>'牧師','secretary'=>'事務',
            'officer'=>'役員','reception'=>'受付'][$r] ?? $r;
}
function follow_type_label($t) {
    return ['phone'=>'電話','meeting'=>'面談','visit'=>'訪問',
            'email'=>'メール','letter'=>'手紙','other'=>'その他'][$t] ?? $t;
}
function inquiry_type_label($t) {
    return ['phone'=>'電話','email'=>'メール','form'=>'フォーム',
            'walk_in'=>'来訪','other'=>'その他'][$t] ?? $t;
}
function csrf_token() {
    if (empty($_SESSION['csrf_token']))
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verify_csrf() {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? ''))
        die('不正なリクエストです。');
}
function log_activity(PDO $pdo, string $action, $type=null, $id=null, $desc=null) {
    try {
        $pdo->prepare("INSERT INTO activity_logs (user_id,action,target_type,target_id,description,ip_address) VALUES (?,?,?,?,?,?)")
            ->execute([$_SESSION['user_id']??null,$action,$type,$id,$desc,$_SERVER['REMOTE_ADDR']??'']);
    } catch(Exception $e) {}
}
function flash($msg, $type='success') {
    $_SESSION['flash'] = ['msg'=>$msg,'type'=>$type];
}
function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
