<?php
/**
 * Rabindra Maitree University (RMU) Application Configuration
 */

// Timezone
date_default_timezone_set('Asia/Dhaka');

// Session configuration for security
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    // Start session
    session_start();
}

// Dynamically determine Base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// If running in subfolder or root
if ($scriptDir === '/' || $scriptDir === '.') {
    $baseUrl = $protocol . $host;
} else {
    // If inside admin/, actions/, tests/, or student/ folder, strip that out
    $cleanPath = preg_replace('/(\/admin|\/actions|\/tests|\/student)(\/.*)?$/', '', $scriptDir);
    $baseUrl = $protocol . $host . rtrim($cleanPath, '/');
}

define('APP_NAME', 'Rabindra Maitree University');
define('APP_NAME_BN', 'Rabindra Maitree University');
define('APP_URL', $baseUrl);
define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOADS_PATH', STORAGE_PATH . '/uploads');

// Security Headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Error reporting: E_ALL in development
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide raw errors from public; log to server
ini_set('log_errors', 1);
