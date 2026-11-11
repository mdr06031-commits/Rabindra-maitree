<?php
/**
 * Database Migration: Advanced Student Result & Account Management System
 * Rabindra Maitree University (RMU)
 * Idempotent migration adding:
 * - Student identifiers: roll_number, application_number, username
 * - Account lifecycle controls: status ENUM ('Active','Graduated','Suspended','Deactivated','Deleted'),
 *   session_version, deactivated_at/by/reason, deleted_at/by/reason
 * - Result visibility controls: results_visible, results_hidden_reason
 * - Extended student_notifications.type ENUM ('result','account_status')
 * - Immutable university data retention table: student_retention_archives
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
$isCli = (php_sapi_name() === 'cli');

function mig_log(string $msg): void {
    if (php_sapi_name() === 'cli') {
        echo $msg . "\n";
    }
}

try {
    // 1. Inspect existing columns on `students`
    $cols = $db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
    $colMap = array_flip($cols);

    // Extend `status` ENUM safely to include Deactivated and Deleted
    $statusCol = $db->query("SHOW COLUMNS FROM students LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if ($statusCol && (strpos($statusCol['Type'], "'Deactivated'") === false || strpos($statusCol['Type'], "'Deleted'") === false)) {
        $db->exec("ALTER TABLE students MODIFY COLUMN status ENUM('Active','Graduated','Suspended','Deactivated','Deleted') NOT NULL DEFAULT 'Active'");
        mig_log("[OK] Updated students.status ENUM to include Deactivated & Deleted.");
    } else {
        mig_log("[SKIP] students.status ENUM already supports Deactivated & Deleted.");
    }

    // Add missing columns to `students`
    $alterAdditions = [
        'roll_number'           => "ADD COLUMN roll_number VARCHAR(50) NULL AFTER student_id",
        'application_number'    => "ADD COLUMN application_number VARCHAR(50) NULL AFTER roll_number",
        'username'              => "ADD COLUMN username VARCHAR(60) NULL AFTER application_number",
        'results_visible'       => "ADD COLUMN results_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER cgpa",
        'results_hidden_reason' => "ADD COLUMN results_hidden_reason VARCHAR(255) NULL AFTER results_visible",
        'session_version'       => "ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER status",
        'deactivated_at'        => "ADD COLUMN deactivated_at DATETIME NULL AFTER updated_at",
        'deactivated_by'        => "ADD COLUMN deactivated_by INT UNSIGNED NULL AFTER deactivated_at",
        'deactivation_reason'   => "ADD COLUMN deactivation_reason TEXT NULL AFTER deactivated_by",
        'deleted_at'            => "ADD COLUMN deleted_at DATETIME NULL AFTER deactivation_reason",
        'deleted_by'            => "ADD COLUMN deleted_by INT UNSIGNED NULL AFTER deleted_at",
        'deletion_reason'       => "ADD COLUMN deletion_reason TEXT NULL AFTER deleted_by",
    ];

    foreach ($alterAdditions as $colName => $ddlClause) {
        if (!isset($colMap[$colName])) {
            $db->exec("ALTER TABLE students {$ddlClause}");
            mig_log("[OK] Added column students.{$colName}");
        } else {
            mig_log("[SKIP] Column students.{$colName} already exists.");
        }
    }

    // 2. Add search & filter indexes if missing
    $idxRows = $db->query("SHOW INDEX FROM students")->fetchAll(PDO::FETCH_ASSOC);
    $existingIndexes = [];
    foreach ($idxRows as $r) {
        $existingIndexes[$r['Key_name']] = true;
    }

    $indexesToCreate = [
        'idx_students_roll'            => "CREATE INDEX idx_students_roll ON students (roll_number)",
        'idx_students_appno'           => "CREATE INDEX idx_students_appno ON students (application_number)",
        'idx_students_username'        => "CREATE INDEX idx_students_username ON students (username)",
        'idx_students_status'          => "CREATE INDEX idx_students_status ON students (status)",
        'idx_students_results_visible' => "CREATE INDEX idx_students_results_visible ON students (results_visible)",
    ];
    foreach ($indexesToCreate as $idxName => $idxSql) {
        if (!isset($existingIndexes[$idxName])) {
            $db->exec($idxSql);
            mig_log("[OK] Created index {$idxName}");
        }
    }

    // 3. Backfill username, roll_number, application_number for existing students
    $db->exec("UPDATE students SET username = student_id WHERE username IS NULL OR TRIM(username) = ''");
    // Match against admissions where available
    $db->exec("
        UPDATE students s
        INNER JOIN admissions a
          ON (a.student_id = s.student_id
              OR (a.email IS NOT NULL AND TRIM(a.email) != '' AND a.email = s.email)
              OR (a.mobile IS NOT NULL AND TRIM(a.mobile) != '' AND a.mobile = s.phone))
        SET
          s.application_number = COALESCE(NULLIF(TRIM(s.application_number), ''), a.application_ref),
          s.roll_number        = COALESCE(NULLIF(TRIM(s.roll_number), ''), NULLIF(TRIM(a.exam_roll), ''))
        WHERE s.application_number IS NULL OR TRIM(s.application_number) = '' OR s.roll_number IS NULL OR TRIM(s.roll_number) = ''
    ");
    // Ensure remaining existing students have a searchable deterministic roll_number if still null
    $db->exec("
        UPDATE students
        SET roll_number = CONCAT('ROLL-', LPAD(id, 4, '0'))
        WHERE roll_number IS NULL OR TRIM(roll_number) = ''
    ");
    mig_log("[OK] Backfilled username, roll_number, and application_number on existing students.");

    // 4. Extend student_notifications.type ENUM cleanly
    $notifCol = $db->query("SHOW COLUMNS FROM student_notifications LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
    if ($notifCol && (strpos($notifCol['Type'], "'account_status'") === false || strpos($notifCol['Type'], "'result'") === false)) {
        $db->exec("ALTER TABLE student_notifications MODIFY COLUMN type ENUM('result_published','request_update','grade_correction','general','result','account_status') NOT NULL DEFAULT 'result_published'");
        mig_log("[OK] Extended student_notifications.type ENUM.");
    } else {
        mig_log("[SKIP] student_notifications.type ENUM already up to date.");
    }

    // 5. Create student_retention_archives table for permanent account deletion retention policy
    $db->exec("
        CREATE TABLE IF NOT EXISTS student_retention_archives (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_pk_id INT UNSIGNED NOT NULL,
            student_id VARCHAR(30) NOT NULL,
            roll_number VARCHAR(50) NULL,
            application_number VARCHAR(50) NULL,
            username VARCHAR(60) NULL,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(100) NULL,
            phone VARCHAR(20) NULL,
            program_title VARCHAR(200) NULL,
            department_name VARCHAR(150) NULL,
            batch VARCHAR(30) NULL,
            final_cgpa DECIMAL(3,2) NOT NULL DEFAULT 0.00,
            credits_completed INT UNSIGNED NOT NULL DEFAULT 0,
            results_count INT UNSIGNED NOT NULL DEFAULT 0,
            snapshot_json LONGTEXT NOT NULL,
            deleted_by INT UNSIGNED NULL,
            deletion_reason TEXT NULL,
            deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_archive_student_pk (student_pk_id),
            KEY idx_archive_student_id (student_id),
            KEY idx_archive_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    mig_log("[OK] Verified student_retention_archives retention table.");

    mig_log("\nMigration complete successfully.");
} catch (Throwable $e) {
    mig_log("[ERROR] Migration failed: " . $e->getMessage());
    if ($isCli) {
        exit(1);
    }
    throw $e;
}
