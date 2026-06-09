<?php
require __DIR__ . '/config.php';
start_session();
$_SESSION = [];
session_destroy();
header('Location: login.php');
