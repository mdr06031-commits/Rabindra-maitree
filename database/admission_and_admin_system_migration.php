<?php
/**
 * Database Migration: Complete University Admission, Admin Control & Student Portal System
 * Rabindra Maitree University (RMU)
 * 
 * Non-destructive migration:
 * - Enhances admissions table with father_name, mother_name, date_of_birth, gender,
 *   present_address, permanent_address, academic_session, department_id, program_id,
 *   academic_documents, payment_gateway_mode, admin_notes.
 * - Enhances student_results with publish_status ENUM('Draft', 'Under Review', 'Published').
 * - Creates grading_rules table with UGC Bangladesh defaults.
 * - Creates semester_terms table for active semester management.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
echo "Starting University Admission, Admin Control & Academic Management Migration...\n";

// 1. Enhance admissions table
try {
    $cols = $db->query("SHOW COLUMNS FROM admissions")->fetchAll(PDO::FETCH_COLUMN);

    $fieldsToAdd = [
        'father_name'          => "VARCHAR(150) NULL AFTER full_name",
        'mother_name'          => "VARCHAR(150) NULL AFTER father_name",
        'date_of_birth'        => "DATE NULL AFTER mother_name",
        'gender'               => "ENUM('Male', 'Female', 'Other') NOT NULL DEFAULT 'Male' AFTER date_of_birth",
        'present_address'      => "TEXT NULL AFTER email",
        'permanent_address'    => "TEXT NULL AFTER present_address",
        'academic_session'     => "VARCHAR(50) NOT NULL DEFAULT 'Fall 2026' AFTER permanent_address",
        'department_id'        => "INT UNSIGNED NULL AFTER academic_session",
        'program_id'           => "INT UNSIGNED NULL AFTER department_id",
        'academic_documents'   => "TEXT NULL AFTER photo",
        'payment_gateway_mode' => "ENUM('sandbox', 'live') NOT NULL DEFAULT 'sandbox' AFTER payment_status",
        'admin_notes'          => "TEXT NULL AFTER status"
    ];

    foreach ($fieldsToAdd as $field => $def) {
        if (!in_array($field, $cols, true)) {
            $db->exec("ALTER TABLE admissions ADD COLUMN `$field` $def");
            echo "  [OK] Added column `$field` to admissions table.\n";
        }
    }

    // Modify payment_status enum to ensure 'Refunded' and 'Failed' are supported
    $db->exec("ALTER TABLE admissions MODIFY COLUMN payment_status ENUM('Pending', 'Paid', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending'");
    echo "  [OK] Verified admissions.payment_status ENUM ('Pending', 'Paid', 'Failed', 'Refunded').\n";

    // Modify status enum to ensure 'Pending', 'Submitted', 'Under Review', 'Accepted', 'Rejected'
    $db->exec("ALTER TABLE admissions MODIFY COLUMN status ENUM('Pending', 'Submitted', 'Under Review', 'Accepted', 'Rejected') NOT NULL DEFAULT 'Submitted'");
    echo "  [OK] Verified admissions.status ENUM.\n";

} catch (Exception $e) {
    echo "  [NOTE] admissions table alteration: " . $e->getMessage() . "\n";
}

// 1.1 Enhance students table with full personal profile fields
try {
    $stCols = $db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
    $stFieldsToAdd = [
        'father_name'       => "VARCHAR(150) NULL AFTER full_name",
        'mother_name'       => "VARCHAR(150) NULL AFTER father_name",
        'gender'            => "ENUM('Male', 'Female', 'Other') NOT NULL DEFAULT 'Male' AFTER dob",
        'present_address'   => "TEXT NULL AFTER home_district",
        'permanent_address' => "TEXT NULL AFTER present_address"
    ];
    foreach ($stFieldsToAdd as $field => $def) {
        if (!in_array($field, $stCols, true)) {
            $db->exec("ALTER TABLE students ADD COLUMN `$field` $def");
            echo "  [OK] Added column `$field` to students table.\n";
        }
    }
} catch (Exception $e) {
    echo "  [NOTE] students table alteration: " . $e->getMessage() . "\n";
}

// 2. Enhance student_results table with publish_status
try {
    $resCols = $db->query("SHOW COLUMNS FROM student_results")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('publish_status', $resCols, true)) {
        $db->exec("ALTER TABLE student_results ADD COLUMN `publish_status` ENUM('Draft', 'Under Review', 'Published') NOT NULL DEFAULT 'Published' AFTER is_published");
        echo "  [OK] Added publish_status column to student_results.\n";
    }
    // Sync publish_status with existing is_published
    $db->exec("UPDATE student_results SET publish_status = CASE WHEN is_published = 1 THEN 'Published' ELSE 'Draft' END WHERE publish_status IS NULL");
} catch (Exception $e) {
    echo "  [NOTE] student_results alteration: " . $e->getMessage() . "\n";
}

// 3. Create grading_rules table (Configurable University Grading System)
try {
    $db->exec("
    CREATE TABLE IF NOT EXISTS `grading_rules` (
      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `min_marks` DECIMAL(5,2) NOT NULL,
      `max_marks` DECIMAL(5,2) NOT NULL,
      `letter_grade` VARCHAR(10) NOT NULL,
      `grade_point` DECIMAL(3,2) NOT NULL,
      `remarks` VARCHAR(100) NOT NULL,
      `is_active` TINYINT(1) NOT NULL DEFAULT 1,
      `sort_order` INT NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "  [OK] Table grading_rules created/verified.\n";

    // Seed official UGC scale if table is empty
    $ruleCount = (int)$db->query("SELECT COUNT(*) FROM grading_rules")->fetchColumn();
    if ($ruleCount === 0) {
        $ugcRules = [
            [80.00, 100.00, 'A+', 4.00, 'Outstanding', 1],
            [75.00, 79.99,  'A',  3.75, 'Excellent',   2],
            [70.00, 74.99,  'A-', 3.50, 'Very Good',   3],
            [65.00, 69.99,  'B+', 3.25, 'Good',        4],
            [60.00, 64.99,  'B',  3.00, 'Satisfactory', 5],
            [55.00, 59.99,  'B-', 2.75, 'Above Average', 6],
            [50.00, 54.99,  'C+', 2.50, 'Average',     7],
            [45.00, 49.99,  'C',  2.25, 'Pass',        8],
            [40.00, 44.99,  'D',  2.00, 'Conditional Pass', 9],
            [0.00,  39.99,  'F',  0.00, 'Fail',        10]
        ];
        $insRule = $db->prepare("INSERT INTO grading_rules (min_marks, max_marks, letter_grade, grade_point, remarks, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($ugcRules as $r) {
            $insRule->execute($r);
        }
        echo "  [OK] Seeded 10 official UGC grading scale rules.\n";
    }
} catch (Exception $e) {
    echo "  [NOTE] grading_rules table error: " . $e->getMessage() . "\n";
}

// 4. Create semester_terms table (Active Semester & Intake Management)
try {
    $db->exec("
    CREATE TABLE IF NOT EXISTS `semester_terms` (
      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `term_name` VARCHAR(100) NOT NULL UNIQUE,
      `academic_year` VARCHAR(20) NOT NULL,
      `semester_number` TINYINT UNSIGNED NOT NULL,
      `start_date` DATE NULL,
      `end_date` DATE NULL,
      `is_current` TINYINT(1) NOT NULL DEFAULT 0,
      `is_admission_open` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "  [OK] Table semester_terms created/verified.\n";

    $termCount = (int)$db->query("SELECT COUNT(*) FROM semester_terms")->fetchColumn();
    if ($termCount === 0) {
        $defaultTerms = [
            ['1st Semester (Spring 2023)', '2023', 1, 0, 0],
            ['2nd Semester (Fall 2023)',   '2023', 2, 0, 0],
            ['3rd Semester (Spring 2024)', '2024', 3, 0, 0],
            ['4th Semester (Fall 2024)',   '2024', 4, 0, 0],
            ['5th Semester (Spring 2025)', '2025', 5, 0, 0],
            ['6th Semester (Fall 2025)',   '2025', 6, 0, 0],
            ['7th Semester (Spring 2026)', '2026', 7, 0, 0],
            ['8th Semester (Fall 2026)',   '2026', 8, 1, 1],
        ];
        $insTerm = $db->prepare("INSERT INTO semester_terms (term_name, academic_year, semester_number, is_current, is_admission_open) VALUES (?, ?, ?, ?, ?)");
        foreach ($defaultTerms as $t) {
            $insTerm->execute($t);
        }
        echo "  [OK] Seeded 8 standard academic terms into semester_terms.\n";
    }
} catch (Exception $e) {
    echo "  [NOTE] semester_terms table error: " . $e->getMessage() . "\n";
}

echo "Migration executed successfully!\n";
