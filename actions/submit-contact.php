<?php
/**
 * Action: Handle Contact Form Inquiries
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . APP_URL . "/contact.php");
    exit;
}

// 1. Verify CSRF Token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    flash_set('error', 'Security session expired. Please refresh and try again.');
    header("Location: " . APP_URL . "/contact.php");
    exit;
}

// 2. Extract & Sanitize
$fullName = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$subject  = trim($_POST['subject'] ?? '');
$message  = trim($_POST['message'] ?? '');

$errors = [];

if (empty($fullName) || mb_strlen($fullName) < 3) {
    $errors[] = 'Please provide your full name (minimum 3 characters).';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}

if (empty($subject)) {
    $errors[] = 'Please enter an inquiry subject.';
}

if (empty($message) || mb_strlen($message) < 10) {
    $errors[] = 'Message content must be at least 10 characters long.';
}

if (!empty($errors)) {
    flash_set('error', implode(' ', $errors));
    header("Location: " . APP_URL . "/contact.php");
    exit;
}

try {
    $db = getDb();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $stmt = $db->prepare("
        INSERT INTO contact_messages (full_name, email, phone, subject, message, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$fullName, $email, $phone, $subject, $message, $ip]);

    flash_set('success', 'Thank you for contacting Rabindra Maitree University! Your inquiry has been received. Our admissions and counseling staff will get back to you shortly.');
    header("Location: " . APP_URL . "/contact.php");
    exit;
} catch (Exception $e) {
    error_log("Contact submission error: " . $e->getMessage());
    flash_set('error', 'A database error occurred while recording your message. Please try again.');
    header("Location: " . APP_URL . "/contact.php");
    exit;
}

