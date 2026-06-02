<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
session_destroy();
header('Location: ' . APP_URL . '/pages/login.php');
exit;
