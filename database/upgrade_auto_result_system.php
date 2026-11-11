<?php
/**
 * Database Migration: Rabindra Maitree University — Automatic Student Result Management System
 * Idempotent migration adding:
 * - programs: degree_type, duration_years, total_semesters, semester_credits_json + B.Ed program & Education department
 * - admissions: Pending status support, application_id, roll_number, degree_type, passing_year, accepted_at, accepted_by
 * - students: degree_type, academic_session, program_duration, total_semesters, passing_year,
 *             result_publication_date, result_status, result_access_status, result_suspended_reason
 * - student_results: academic_session, publication_date
 * - student_semester_results: per-semester GPAs & completion states table
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
$isCli = (php_sapi_name() === 'cli');

function auto_mig_log(string $msg): void {
    if (php_sapi_name() === 'cli') {
        echo $msg . "\n";
    }
}

try {
    // -------------------------------------------------------------------------
    // 1. Upgrade `programs` table & backfill academic durations/semesters
    // -------------------------------------------------------------------------
    $progCols = array_flip($db->query("SHOW COLUMNS FROM programs")->fetchAll(PDO::FETCH_COLUMN));

    $progAlters = [
        'degree_type'           => "ADD COLUMN degree_type ENUM('Bachelor / Honours', 'B.Ed', 'Master\\'s') NOT NULL DEFAULT 'Bachelor / Honours' AFTER degree_level",
        'duration_years'        => "ADD COLUMN duration_years DECIMAL(3,1) NOT NULL DEFAULT 4.0 AFTER duration",
        'total_semesters'       => "ADD COLUMN total_semesters TINYINT UNSIGNED NOT NULL DEFAULT 8 AFTER duration_years",
        'semester_credits_json' => "ADD COLUMN semester_credits_json TEXT NULL AFTER total_credits",
    ];
    foreach ($progAlters as $col => $ddl) {
        if (!isset($progCols[$col])) {
            $db->exec("ALTER TABLE programs {$ddl}");
            auto_mig_log("[OK] Added programs.{$col}");
        } else {
            auto_mig_log("[SKIP] programs.{$col} already exists.");
        }
    }

    // Backfill existing programs cleanly (keep MBA = 2 Years / 4 Semesters for compatibility)
    $db->exec("
        UPDATE programs
        SET degree_type = 'Master\\'s',
            duration_years = 2.0,
            total_semesters = 4,
            duration = '2 Years (4 Semesters)'
        WHERE slug = 'mba' AND (total_semesters = 8 OR degree_type = 'Bachelor / Honours')
    ");
    $db->exec("
        UPDATE programs
        SET degree_type = 'Master\\'s',
            duration_years = 1.0,
            total_semesters = 2
        WHERE slug IN ('ma-in-english', 'ma-in-bangla', 'mped') AND (degree_type = 'Bachelor / Honours' OR total_semesters = 8)
    ");
    $db->exec("
        UPDATE programs
        SET degree_type = 'Bachelor / Honours',
            duration_years = 4.0,
            total_semesters = 8
        WHERE degree_level = 'Undergraduate' AND slug != 'b-ed' AND degree_type = 'Bachelor / Honours' AND total_semesters = 8
    ");

    // Seed active Department of Education (100% English-only) & B.Ed program if missing
    $eduDeptId = (int)$db->query("SELECT id FROM departments WHERE slug = 'dept-education' OR code = 'EDU' LIMIT 1")->fetchColumn();
    if ($eduDeptId <= 0) {
        $artsFacultyId = (int)$db->query("SELECT id FROM faculties WHERE slug = 'arts-and-social-science' LIMIT 1")->fetchColumn() ?: 1;
        $insDept = $db->prepare("
            INSERT INTO departments (faculty_id, slug, name_en, name_bn, code, head_name, email, overview, sort_order, is_active)
            VALUES (?, 'dept-education', 'Department of Education', 'Department of Education', 'EDU', 'Head of Education Studies', 'education@rmu.ac.bd',
                    'The Department of Education prepares skilled teachers, instructional designers, and academic leaders through modern pedagogy and supervised classroom practice.',
                    12, 1)
        ");
        $insDept->execute([$artsFacultyId]);
        $eduDeptId = (int)$db->lastInsertId();
        auto_mig_log("[OK] Seeded Department of Education (ID #{$eduDeptId}).");
    } else {
        auto_mig_log("[SKIP] Department of Education already exists (ID #{$eduDeptId}).");
    }

    $bedProgId = (int)$db->query("SELECT id FROM programs WHERE slug = 'b-ed' LIMIT 1")->fetchColumn();
    if ($bedProgId <= 0) {
        $defaultBedCredits = json_encode(['1' => 24, '2' => 24]);
        $insProg = $db->prepare("
            INSERT INTO programs (
                department_id, slug, title_en, title_bn, degree_level, degree_type,
                duration, duration_years, total_semesters, total_credits, semester_credits_json,
                eligibility, description, tuition_fee, sort_order, is_active
            ) VALUES (
                ?, 'b-ed', 'Bachelor of Education (B.Ed)', 'Bachelor of Education (B.Ed)', 'Undergraduate', 'B.Ed',
                '1 Year (2 Semesters)', 1.0, 2, 48, ?,
                'Candidates must hold a recognized Bachelor degree (BA / BSS / BSc / BBS or equivalent) from an accredited university.',
                'The Bachelor of Education (B.Ed) program equips future and practicing educators with educational psychology, pedagogical methodology, curriculum evaluation, and supervised teaching practicum. Supports 2-semester default or admin-configured 3-semester curriculum.',
                'Tk. 45,000 Total Tuition Fee', 12, 1
            )
        ");
        $insProg->execute([$eduDeptId, $defaultBedCredits]);
        $bedProgId = (int)$db->lastInsertId();
        auto_mig_log("[OK] Seeded Bachelor of Education (B.Ed) program (ID #{$bedProgId}).");
    } else {
        auto_mig_log("[SKIP] Bachelor of Education (B.Ed) program already exists (ID #{$bedProgId}).");
    }

    // -------------------------------------------------------------------------
    // 2. Upgrade `admissions` table
    // -------------------------------------------------------------------------
    $admStatusCol = $db->query("SHOW COLUMNS FROM admissions LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if ($admStatusCol && strpos($admStatusCol['Type'], "'Pending'") === false) {
        $db->exec("ALTER TABLE admissions MODIFY COLUMN status ENUM('Submitted','Pending','Under Review','Additional Information Required','Accepted','Rejected') NOT NULL DEFAULT 'Submitted'");
        auto_mig_log("[OK] Extended admissions.status ENUM with Pending.");
    }

    $admCols = array_flip($db->query("SHOW COLUMNS FROM admissions")->fetchAll(PDO::FETCH_COLUMN));
    $admAlters = [
        'application_id' => "ADD COLUMN application_id VARCHAR(50) NULL AFTER application_ref",
        'roll_number'    => "ADD COLUMN roll_number VARCHAR(50) NULL AFTER student_id",
        'degree_type'    => "ADD COLUMN degree_type VARCHAR(60) NULL AFTER interested_subject",
        'passing_year'   => "ADD COLUMN passing_year VARCHAR(10) NULL AFTER hsc_year",
        'accepted_at'    => "ADD COLUMN accepted_at DATETIME NULL AFTER updated_at",
        'accepted_by'    => "ADD COLUMN accepted_by INT UNSIGNED NULL AFTER accepted_at",
    ];
    foreach ($admAlters as $col => $ddl) {
        if (!isset($admCols[$col])) {
            $db->exec("ALTER TABLE admissions {$ddl}");
            auto_mig_log("[OK] Added admissions.{$col}");
        } else {
            auto_mig_log("[SKIP] admissions.{$col} already exists.");
        }
    }

    $admIdx = [];
    foreach ($db->query("SHOW INDEX FROM admissions")->fetchAll(PDO::FETCH_ASSOC) as $idxRow) {
        $admIdx[$idxRow['Key_name']] = true;
    }
    if (!isset($admIdx['idx_adm_app_id'])) {
        $db->exec("CREATE INDEX idx_adm_app_id ON admissions (application_id)");
    }
    if (!isset($admIdx['idx_adm_roll'])) {
        $db->exec("CREATE INDEX idx_adm_roll ON admissions (roll_number)");
    }

    // Backfill admissions metadata safely
    $db->exec("UPDATE admissions SET application_id = application_ref WHERE application_id IS NULL OR TRIM(application_id) = ''");
    $db->exec("UPDATE admissions SET roll_number = COALESCE(NULLIF(TRIM(exam_roll), ''), NULLIF(TRIM(hsc_roll), '')) WHERE roll_number IS NULL OR TRIM(roll_number) = ''");
    $db->exec("UPDATE admissions SET passing_year = NULLIF(TRIM(hsc_year), '') WHERE passing_year IS NULL OR TRIM(passing_year) = ''");
    $db->exec("
        UPDATE admissions a
        LEFT JOIN programs p ON a.program_id = p.id
        SET a.degree_type = CASE
            WHEN p.degree_type IS NOT NULL THEN p.degree_type
            WHEN a.interested_subject LIKE '%B.Ed%' THEN 'B.Ed'
            WHEN a.interested_subject LIKE '%MA %' OR a.interested_subject LIKE '%MBA%' OR a.interested_subject LIKE '%MPEd%' OR a.interested_subject LIKE '%Master%' THEN 'Master\\'s'
            ELSE 'Bachelor / Honours'
        END
        WHERE a.degree_type IS NULL OR TRIM(a.degree_type) = ''
    ");
    auto_mig_log("[OK] Backfilled admissions.application_id, roll_number, passing_year, degree_type.");

    // -------------------------------------------------------------------------
    // 3. Upgrade `students` table
    // -------------------------------------------------------------------------
    $stCols = array_flip($db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN));
    $stAlters = [
        'degree_type'             => "ADD COLUMN degree_type VARCHAR(60) NOT NULL DEFAULT 'Bachelor / Honours' AFTER program_id",
        'academic_session'        => "ADD COLUMN academic_session VARCHAR(80) NULL AFTER batch",
        'program_duration'        => "ADD COLUMN program_duration VARCHAR(60) NULL AFTER academic_session",
        'total_semesters'         => "ADD COLUMN total_semesters TINYINT UNSIGNED NOT NULL DEFAULT 8 AFTER program_duration",
        'passing_year'            => "ADD COLUMN passing_year VARCHAR(10) NULL AFTER home_district",
        'result_publication_date' => "ADD COLUMN result_publication_date DATE NULL AFTER cgpa",
        'result_status'           => "ADD COLUMN result_status ENUM('Pending','Estimated','Verified','Published','Suspended') NOT NULL DEFAULT 'Pending' AFTER result_publication_date",
        'result_access_status'    => "ADD COLUMN result_access_status ENUM('Active','Suspended') NOT NULL DEFAULT 'Active' AFTER results_visible",
        'result_suspended_reason' => "ADD COLUMN result_suspended_reason VARCHAR(255) NULL AFTER result_access_status",
    ];
    foreach ($stAlters as $col => $ddl) {
        if (!isset($stCols[$col])) {
            $db->exec("ALTER TABLE students {$ddl}");
            auto_mig_log("[OK] Added students.{$col}");
        } else {
            auto_mig_log("[SKIP] students.{$col} already exists.");
        }
    }

    // Backfill students program structure & session
    $db->exec("
        UPDATE students s
        LEFT JOIN programs p ON s.program_id = p.id
        SET s.degree_type = COALESCE(p.degree_type, 'Bachelor / Honours'),
            s.program_duration = COALESCE(p.duration, '4 Years'),
            s.total_semesters = COALESCE(p.total_semesters, 8),
            s.academic_session = COALESCE(NULLIF(TRIM(s.academic_session), ''), NULLIF(TRIM(s.batch), ''), 'Fall 2026')
    ");
    $db->exec("
        UPDATE students s
        SET s.result_status = CASE
            WHEN s.result_access_status = 'Suspended' THEN 'Suspended'
            WHEN EXISTS (SELECT 1 FROM student_results sr WHERE sr.student_id = s.id AND sr.is_published = 1) THEN 'Published'
            WHEN EXISTS (SELECT 1 FROM student_results sr WHERE sr.student_id = s.id AND sr.is_estimated = 1) THEN 'Estimated'
            WHEN EXISTS (SELECT 1 FROM student_results sr WHERE sr.student_id = s.id AND sr.is_estimated = 0) THEN 'Verified'
            ELSE 'Pending'
        END
    ");
    auto_mig_log("[OK] Backfilled students degree_type, duration, semesters, session, and result_status.");

    // -------------------------------------------------------------------------
    // 4. Upgrade `student_results` table
    // -------------------------------------------------------------------------
    $srCols = array_flip($db->query("SHOW COLUMNS FROM student_results")->fetchAll(PDO::FETCH_COLUMN));
    if (!isset($srCols['academic_session'])) {
        $db->exec("ALTER TABLE student_results ADD COLUMN academic_session VARCHAR(80) NULL AFTER exam_term");
        auto_mig_log("[OK] Added student_results.academic_session.");
    }
    if (!isset($srCols['publication_date'])) {
        $db->exec("ALTER TABLE student_results ADD COLUMN publication_date DATE NULL AFTER published_at");
        auto_mig_log("[OK] Added student_results.publication_date.");
    }

    // -------------------------------------------------------------------------
    // 5. Create `student_semester_results` table
    // -------------------------------------------------------------------------
    $db->exec("
        CREATE TABLE IF NOT EXISTS student_semester_results (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            semester_number TINYINT UNSIGNED NOT NULL,
            semester_name VARCHAR(80) NOT NULL,
            academic_session VARCHAR(80) NULL,
            credit_hours DECIMAL(5,1) NOT NULL DEFAULT 0.0,
            semester_gpa DECIMAL(3,2) NOT NULL DEFAULT 0.00,
            completion_status ENUM('Completed','Pending') NOT NULL DEFAULT 'Pending',
            result_type ENUM('Estimated','Verified') NOT NULL DEFAULT 'Estimated',
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            published_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_student_sem (student_id, semester_number),
            KEY idx_ssr_student (student_id),
            CONSTRAINT fk_ssr_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    auto_mig_log("[OK] Verified student_semester_results table.");

    auto_mig_log("\nAuto Result System DB migration completed successfully.");
} catch (Throwable $e) {
    auto_mig_log("[ERROR] Migration failed: " . $e->getMessage());
    if ($isCli) {
        exit(1);
    }
    throw $e;
}
