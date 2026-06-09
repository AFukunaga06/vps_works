<?php
require_once __DIR__ . '/../lib/auth.php';
admin_logout();
header('Location: ' . APP_ROOT_URL . '/admin/login.php');
exit;
