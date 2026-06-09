<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config.php';
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    echo 'Received user: [' . htmlspecialchars($user) . ']<br>';
    echo 'Received pass: [' . htmlspecialchars($pass) . ']<br>';
    echo 'User match: ' . ($user === ADMIN_USER ? 'OK' : 'NG') . '<br>';
    echo 'Pass verify: ' . (password_verify($pass, ADMIN_PASS) ? 'OK' : 'NG') . '<br>';
} else {
    echo '<form method=post>
    user: <input name=username value=admin><br>
    pass: <input name=password value=1192><br>
    <button type=submit>テスト</button>
    </form>';
}
