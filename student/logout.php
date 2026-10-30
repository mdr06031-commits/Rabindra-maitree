<?php
/**
 * Student Portal - Logout
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

unset($_SESSION['student_pk_id'], $_SESSION['student_id'], $_SESSION['student_name'], $_SESSION['student_email']);
flash_set('success', 'You have been successfully logged out from the Student Portal.');

header("Location: " . APP_URL . "/student/login.php");
exit;

