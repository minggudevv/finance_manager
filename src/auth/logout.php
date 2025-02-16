<?php
session_start();
require_once __DIR__ . '/../helpers/auth_helper.php';

clearRememberMeToken();
session_destroy();
header('Location: login.php');
exit;
