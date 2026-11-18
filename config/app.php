<?php
/**
 * Rabindra Maitree University (RMU) Application Configuration
 */

// Timezone
date_default_timezone_set('Asia/Dhaka');

// Detect HTTPS (supports direct TLS and reverse-proxy / load-balancer SSL termination)
$isHttpsRequest = (
    (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443)
    || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
    || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on')
    || (strtolower((string)($_SERVER['HTTP_FRONT_END_HTTPS'] ?? '')) === 'on')
);

// Session configuration for security
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    // Ensure a valid, writable session directory on shared cPanel/CloudLinux hosts
    $currentSavePath = (string)ini_get('session.save_path');
    if ($currentSavePath === '' || !@is_dir($currentSavePath) || !@is_writable($currentSavePath)) {
        $fallbackSessionDir = dirname(__DIR__) . '/storage/sessions';
        if (!is_dir($fallbackSessionDir)) {
            @mkdir($fallbackSessionDir, 0700, true);
        }
        if (is_dir($fallbackSessionDir) && is_writable($fallbackSessionDir)) {
            session_save_path($fallbackSessionDir);
        }
    }
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $isHttpsRequest ? '1' : '0');
    // Start session
    session_start();
}

// Dynamically determine Base URL (with safe CLI & RMU_APP_URL support)
$envAppUrl = trim((string)(getenv('RMU_APP_URL') ?: ($_ENV['RMU_APP_URL'] ?? '')));
if ($envAppUrl !== '' && preg_match('#^https?://#i', $envAppUrl)) {
    $baseUrl = rtrim($envAppUrl, '/');
} elseif (php_sapi_name() === 'cli') {
    $baseUrl = 'http://127.0.0.1:8000';
} else {
    $protocol = $isHttpsRequest ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $scriptDir = '/' . ltrim($scriptDir, '/');

    if ($scriptDir === '/' || $scriptDir === '/.') {
        $baseUrl = $protocol . $host;
    } else {
        // If inside admin/, actions/, tests/, or student/ folder, strip that out
        $cleanPath = preg_replace('/(\/admin|\/actions|\/tests|\/student)(\/.*)?$/', '', $scriptDir);
        $baseUrl = $protocol . $host . rtrim((string)$cleanPath, '/');
    }
}

define('APP_NAME', 'Rabindra Maitree University');
define('APP_NAME_BN', 'Rabindra Maitree University');
define('APP_URL', $baseUrl);
if (!defined('RMU_IS_HTTPS')) {
    define('RMU_IS_HTTPS', $isHttpsRequest || stripos($baseUrl, 'https://') === 0);
}
define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOADS_PATH', STORAGE_PATH . '/uploads');

// Security Headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if ($isHttpsRequest) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Error reporting: E_ALL in development
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide raw errors from public; log to server
ini_set('log_errors', 1);
