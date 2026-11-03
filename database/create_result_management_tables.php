<?php
/**
 * Database Migration: Advanced Admin Result Management System
 * Adds result_requests, result_modification_history, student_notifications tables
 * and supplementary columns to student_results.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();

echo "Running Advanced Result Management Database Migration...\n";

// 1. Add supplementary columns to student_results if they do not exist
try {
    $cols = $db->query("SHOW COLUMNS FROM student_results")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('published_at', $cols)) {
        $db->exec("ALTER TABLE student_results ADD COLUMN published_at DATETIME NULL AFTER is_published");
        echo "  [OK] Added published_at column to student_results.\n";
    }
    
    if (!in_array('published_by', $cols)) {
        $db->exec("ALTER TABLE student_results ADD COLUMN published_by INT UNSIGNED NULL AFTER published_at");
        echo "  [OK] Added published_by column to student_results.\n";
    }
    
    if (!in_array('correction_reason', $cols)) {
        $db->exec("ALTER TABLE student_results ADD COLUMN correction_reason TEXT NULL AFTER published_by");
        echo "  [OK] Added correction_reason column to student_results.\n";
    }
} catch (Exception $e) {
    echo "  [NOTE] student_results columns check: " . $e->getMessage() . "\n";
}

// 2. Create result_requests table
$db->exec("
CREATE TABLE IF NOT EXISTS `result_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `semester` VARCHAR(60) NOT NULL,
  `course_code` VARCHAR(30) NULL,
  `request_type` ENUM('Result Review', 'Grade Correction', 'Missing Marks', 'Transcript Inquiry', 'General Query') NOT NULL DEFAULT 'Result Review',
  `subject` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('Pending', 'In Review', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  `admin_notes` TEXT NULL,
  `processed_by` INT UNSIGNED NULL,
  `processed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  [OK] Table result_requests verified/created.\n";

// 3. Create result_modification_history table
$db->exec("
CREATE TABLE IF NOT EXISTS `result_modification_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `result_id` INT UNSIGNED NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `admin_id` INT UNSIGNED NOT NULL,
  `course_code` VARCHAR(30) NOT NULL,
  `semester` VARCHAR(60) NOT NULL,
  `field_name` VARCHAR(50) NOT NULL,
  `old_value` VARCHAR(100) NULL,
  `new_value` VARCHAR(100) NOT NULL,
  `reason` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`result_id`) REFERENCES `student_results`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  [OK] Table result_modification_history verified/created.\n";

// 4. Create student_notifications table
$db->exec("
CREATE TABLE IF NOT EXISTS `student_notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('result_published', 'request_update', 'grade_correction', 'general') NOT NULL DEFAULT 'result_published',
  `related_id` INT UNSIGNED NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  [OK] Table student_notifications verified/created.\n";

echo "Migration completed successfully!\n";

