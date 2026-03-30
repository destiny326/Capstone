<?php
// logout.php
require_once 'auth.php';
$authType = $_SESSION['auth_type'] ?? 'microsoft';
logout();
header('Location: ' . ($authType === 'database' ? 'admin_login.php' : 'login.php'));
exit;
