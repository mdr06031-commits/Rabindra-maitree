<?php
/**
 * Student Portal - Logout
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

clear_student_session();
flash_set('success', 'You have been successfully logged out from the Student Portal.');

header("Location: " . APP_URL . "/student/login.php");
exit;

