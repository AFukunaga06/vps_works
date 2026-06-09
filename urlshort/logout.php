<?php
require_once __DIR__ . '/auth_config.php';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
unset($_SESSION[AUTH_SESSION_NAME]);
session_regenerate_id(true);
session_destroy();
header('Location: login.php');
exit;
