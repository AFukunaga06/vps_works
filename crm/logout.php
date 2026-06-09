<?php
require_once __DIR__ . '/includes/auth.php';
crm_logout();
header('Location: ' . CRM_BASE_URL . '/login.php');
exit;
