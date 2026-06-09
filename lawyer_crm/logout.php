<?php
require_once __DIR__ . '/includes/auth.php';
lc_logout();
header('Location: ' . LC_BASE_URL . '/login.php');
exit;
