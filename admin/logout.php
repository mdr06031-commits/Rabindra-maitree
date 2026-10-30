<?php
/**
 * Administrator Logout
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    $user = current_user();
    log_audit('USER_LOGOUT', "Administrator '{$user['username']}' logged out.");
}

// Clear all session data
$_SESSION = [];

// Destroy session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
session_start();

flash_set('info', 'You have been successfully logged out from the RMU Administration Portal.');
header("Location: " . APP_URL . "/admin/login.php");
exit;

