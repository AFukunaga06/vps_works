<?php
require_once __DIR__ . '/includes/auth.php';
gc_logout();
header('Location: ' . GC_BASE_URL . '/login.php');
exit;
