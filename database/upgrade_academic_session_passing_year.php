<?php
/**
 * Database Migration: Rabindra Maitri University — Academic Session & Passing Year Management System
 *
 * Idempotent migration adding:
 * - `academic_sessions`: Spring, Summer, Fall sessions from 2011 through present and future years (2011..2030 seeded)
 * - `passing_years`: Passing years from 2011 through present and future years (2011..2030 seeded)
 * - `academic_edit_history`: Mandatory-reason audit log for official academic corrections & program structure edits
 * - `admissions`: expected_passing_year, historical_record_approved, historical_approved_by, historical_approved_at, historical_approval_note
 * - `students`: duration_years, expected_passing_year, historical_record_approved, historical_approved_by, historical_approved_at, historical_approval_note
 * - `departments` & `programs`: Department of Social Science + BSS (Honours) [English-only], and Master's default 2 Years / 4 Semesters
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
$isCli = (php_sapi_name() === 'cli');

function ses_mig_log(string $msg): void {
    if (php_sapi_name() === 'cli') {
        echo $msg . "\n";
    }
}

try {
    // -------------------------------------------------------------------------
    // 1. Create & seed `academic_sessions` (2011..2030: Spring, Summer, Fall)
    // -------------------------------------------------------------------------
    $db->exec("
        CREATE TABLE IF NOT EXISTS academic_sessions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_name VARCHAR(60) NOT NULL,
            term_name ENUM('Spring','Summer','Fall') NOT NULL,
            session_year SMALLINT UNSIGNED NOT NULL,
            sort_order INT UNSIGNED NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            notes VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_session_name (session_name),
            KEY idx_as_enabled_sort (is_enabled, sort_order),
            KEY idx_as_year (session_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    ses_mig_log("[OK] Verified academic_sessions table.");

    $sessionCount = (int)$db->query("SELECT COUNT(*) FROM academic_sessions")->fetchColumn();
    if ($sessionCount === 0) {
        $terms = ['Spring' => 1, 'Summer' => 2, 'Fall' => 3];
        $maxYear = max(2030, (int)date('Y') + 4);
        $insSess = $db->prepare("
            INSERT IGNORE INTO academic_sessions (session_name, term_name, session_year, sort_order, is_enabled, is_current, notes)
            VALUES (?, ?, ?, ?, 1, ?, NULL)
        ");
        $insertedSessions = 0;
        for ($yr = 2011; $yr <= $maxYear; $yr++) {
            foreach ($terms as $term => $tOrd) {
                $sName = "{$term} {$yr}";
                $sortOrder = ($yr * 10) + $tOrd;
                $isCurrent = ($sName === 'Fall 2026') ? 1 : 0;
                $insSess->execute([$sName, $term, $yr, $sortOrder, $isCurrent]);
                $insertedSessions += $insSess->rowCount();
            }
        }
        ses_mig_log("[OK] Seeded {$insertedSessions} academic sessions (Spring/Summer/Fall 2011..{$maxYear}).");
    } else {
        $hasCurrent = (int)$db->query("SELECT COUNT(*) FROM academic_sessions WHERE is_current = 1")->fetchColumn();
        if ($hasCurrent === 0) {
            $db->exec("UPDATE academic_sessions SET is_current = 1 WHERE session_name = 'Fall 2026' LIMIT 1");
        }
        ses_mig_log("[SKIP] academic_sessions already populated ({$sessionCount} rows).");
    }

    // -------------------------------------------------------------------------
    // 2. Create & seed `passing_years` (2011..2030)
    // -------------------------------------------------------------------------
    $db->exec("
        CREATE TABLE IF NOT EXISTS passing_years (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            year_value SMALLINT UNSIGNED NOT NULL,
            label VARCHAR(40) NOT NULL,
            sort_order INT UNSIGNED NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            notes VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_year_value (year_value),
            KEY idx_py_enabled_sort (is_enabled, year_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    ses_mig_log("[OK] Verified passing_years table.");

    $pyCount = (int)$db->query("SELECT COUNT(*) FROM passing_years")->fetchColumn();
    if ($pyCount === 0) {
        $maxYear = max(2030, (int)date('Y') + 4);
        $insPy = $db->prepare("
            INSERT IGNORE INTO passing_years (year_value, label, sort_order, is_enabled, notes)
            VALUES (?, ?, ?, 1, NULL)
        ");
        $insertedYears = 0;
        for ($yr = 2011; $yr <= $maxYear; $yr++) {
            $insPy->execute([$yr, (string)$yr, $yr]);
            $insertedYears += $insPy->rowCount();
        }
        ses_mig_log("[OK] Seeded {$insertedYears} passing years (2011..{$maxYear}).");
    } else {
        ses_mig_log("[SKIP] passing_years already populated ({$pyCount} rows).");
    }

    // -------------------------------------------------------------------------
    // 3. Create `academic_edit_history` audit table
    // -------------------------------------------------------------------------
    $db->exec("
        CREATE TABLE IF NOT EXISTS academic_edit_history (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type ENUM('admission','student','program','session','passing_year') NOT NULL,
            entity_id INT UNSIGNED NOT NULL,
            student_id_code VARCHAR(50) NULL,
            admin_id INT UNSIGNED NULL,
            field_name VARCHAR(80) NOT NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aeh_entity (entity_type, entity_id),
            KEY idx_aeh_student_code (student_id_code),
            KEY idx_aeh_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    ses_mig_log("[OK] Verified academic_edit_history table.");

    // -------------------------------------------------------------------------
    // 4. Extend `admissions` table with expected passing year & historical review
    // -------------------------------------------------------------------------
    $admCols = array_flip($db->query("SHOW COLUMNS FROM admissions")->fetchAll(PDO::FETCH_COLUMN));
    $admAlters = [
        'expected_passing_year'      => "ADD COLUMN expected_passing_year SMALLINT UNSIGNED NULL AFTER passing_year",
        'historical_record_approved' => "ADD COLUMN historical_record_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER expected_passing_year",
        'historical_approved_by'     => "ADD COLUMN historical_approved_by INT UNSIGNED NULL AFTER historical_record_approved",
        'historical_approved_at'     => "ADD COLUMN historical_approved_at DATETIME NULL AFTER historical_approved_by",
        'historical_approval_note'   => "ADD COLUMN historical_approval_note VARCHAR(255) NULL AFTER historical_approved_at",
    ];
    foreach ($admAlters as $col => $ddl) {
        if (!isset($admCols[$col])) {
            $db->exec("ALTER TABLE admissions {$ddl}");
            ses_mig_log("[OK] Added admissions.{$col}");
        } else {
            ses_mig_log("[SKIP] admissions.{$col} already exists.");
        }
    }

    // -------------------------------------------------------------------------
    // 5. Extend `students` table with duration_years, expected passing year & historical review
    // -------------------------------------------------------------------------
    $stCols = array_flip($db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN));
    $stAlters = [
        'duration_years'             => "ADD COLUMN duration_years DECIMAL(3,1) NULL AFTER academic_session",
        'expected_passing_year'      => "ADD COLUMN expected_passing_year SMALLINT UNSIGNED NULL AFTER passing_year",
        'historical_record_approved' => "ADD COLUMN historical_record_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER expected_passing_year",
        'historical_approved_by'     => "ADD COLUMN historical_approved_by INT UNSIGNED NULL AFTER historical_record_approved",
        'historical_approved_at'     => "ADD COLUMN historical_approved_at DATETIME NULL AFTER historical_approved_by",
        'historical_approval_note'   => "ADD COLUMN historical_approval_note VARCHAR(255) NULL AFTER historical_approved_at",
    ];
    foreach ($stAlters as $col => $ddl) {
        if (!isset($stCols[$col])) {
            $db->exec("ALTER TABLE students {$ddl}");
            ses_mig_log("[OK] Added students.{$col}");
        } else {
            ses_mig_log("[SKIP] students.{$col} already exists.");
        }
    }

    // -------------------------------------------------------------------------
    // 6. Seed Department of Social Science & BSS (Honours) [100% English-only]
    // -------------------------------------------------------------------------
    $socDeptId = (int)$db->query("SELECT id FROM departments WHERE slug = 'dept-social-science' OR code = 'SOC' LIMIT 1")->fetchColumn();
    if ($socDeptId <= 0) {
        $artsFacultyId = (int)$db->query("SELECT id FROM faculties WHERE slug = 'arts-and-social-science' LIMIT 1")->fetchColumn() ?: 1;
        $insSocDept = $db->prepare("
            INSERT INTO departments (faculty_id, slug, name_en, name_bn, code, head_name, email, overview, sort_order, is_active)
            VALUES (?, 'dept-social-science', 'Department of Social Science', 'Department of Social Science', 'SOC',
                    'Head of Social Science', 'socialscience@rmu.ac.bd',
                    'The Department of Social Science explores sociology, public policy, governance, development studies, and social research methods.',
                    13, 1)
        ");
        $insSocDept->execute([$artsFacultyId]);
        $socDeptId = (int)$db->lastInsertId();
        ses_mig_log("[OK] Seeded Department of Social Science (ID #{$socDeptId}).");
    } else {
        ses_mig_log("[SKIP] Department of Social Science already exists (ID #{$socDeptId}).");
    }

    $bssProgId = (int)$db->query("SELECT id FROM programs WHERE slug = 'bss-honours' LIMIT 1")->fetchColumn();
    if ($bssProgId <= 0) {
        $defaultBssCredits = json_encode([
            '1' => 16, '2' => 16, '3' => 16, '4' => 16,
            '5' => 16, '6' => 16, '7' => 16, '8' => 16,
        ]);
        $insBssProg = $db->prepare("
            INSERT INTO programs (
                department_id, slug, title_en, title_bn, degree_level, degree_type,
                duration, duration_years, total_semesters, total_credits, semester_credits_json,
                eligibility, description, tuition_fee, sort_order, is_active
            ) VALUES (
                ?, 'bss-honours', 'BSS (Honours) in Social Science', 'BSS (Honours)', 'Undergraduate', 'Bachelor / Honours',
                '4 Years (8 Semesters)', 4.0, 8, 128, ?,
                'Candidates must pass HSC or equivalent examination with minimum required GPA as per RMU undergraduate admission regulations.',
                'The four-year eight-semester Bachelor of Social Science (BSS Honours) program offers multidisciplinary coursework in sociology, public administration, development studies, economics, and applied social research.',
                'Tk. 1,40,000 Total Tuition Fee', 13, 1
            )
        ");
        $insBssProg->execute([$socDeptId, $defaultBssCredits]);
        $bssProgId = (int)$db->lastInsertId();
        ses_mig_log("[OK] Seeded BSS (Honours) in Social Science program (ID #{$bssProgId}).");
    } else {
        ses_mig_log("[SKIP] BSS (Honours) program already exists (ID #{$bssProgId}).");
    }

    // -------------------------------------------------------------------------
    // 7. Normalize default Master's structure (2 Years / 4 Semesters) where still on 1 Year / 2 Semesters
    // -------------------------------------------------------------------------
    $updMast = $db->exec("
        UPDATE programs
        SET duration_years = 2.0,
            total_semesters = 4,
            total_credits = CASE WHEN total_credits = 36 THEN 60 ELSE total_credits END,
            duration = '2 Years (4 Semesters)'
        WHERE slug IN ('ma-in-english', 'ma-in-bangla', 'mped')
          AND duration_years = 1.0
          AND total_semesters = 2
    ");
    if ($updMast > 0) {
        ses_mig_log("[OK] Normalized {$updMast} Master's program(s) to default 2 Years (4 Semesters).");
    } else {
        ses_mig_log("[SKIP] Master's programs already configured.");
    }

    // -------------------------------------------------------------------------
    // 8. Safe backfill of students.duration_years & expected_passing_year (NEVER touch passing_year!)
    // -------------------------------------------------------------------------
    $db->exec("
        UPDATE students s
        LEFT JOIN programs p ON s.program_id = p.id
        SET s.duration_years = COALESCE(
            s.duration_years,
            p.duration_years,
            CASE
                WHEN s.degree_type = 'B.Ed' THEN 1.0
                WHEN s.degree_type = 'Master\\'s' THEN 2.0
                ELSE 4.0
            END
        )
        WHERE s.duration_years IS NULL
    ");

    // Backfill expected_passing_year on admissions where NULL
    $admRowsToFill = $db->query("
        SELECT a.id, a.academic_session, a.degree_type, p.duration_years
        FROM admissions a
        LEFT JOIN programs p ON a.program_id = p.id
        WHERE a.expected_passing_year IS NULL
    ")->fetchAll(PDO::FETCH_ASSOC);

    $updAdmExp = $db->prepare("UPDATE admissions SET expected_passing_year = ? WHERE id = ?");
    $admFilled = 0;
    foreach ($admRowsToFill as $ar) {
        if (preg_match('/\b(19\d{2}|20\d{2})\b/', (string)$ar['academic_session'], $ym)) {
            $baseYr = (int)$ym[1];
            $dur = isset($ar['duration_years']) && (float)$ar['duration_years'] > 0
                ? (float)$ar['duration_years']
                : (($ar['degree_type'] === 'B.Ed') ? 1.0 : (($ar['degree_type'] === "Master's") ? 2.0 : 4.0));
            $expYr = $baseYr + (int)round($dur);
            $updAdmExp->execute([$expYr, (int)$ar['id']]);
            $admFilled++;
        }
    }

    // Backfill expected_passing_year on students where NULL
    $stRowsToFill = $db->query("
        SELECT s.id, s.academic_session, s.batch, s.degree_type, s.duration_years, p.duration_years AS prog_duration_years
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        WHERE s.expected_passing_year IS NULL
    ")->fetchAll(PDO::FETCH_ASSOC);

    $updStExp = $db->prepare("UPDATE students SET expected_passing_year = ? WHERE id = ?");
    $stFilled = 0;
    foreach ($stRowsToFill as $sr) {
        $sessSrc = !empty($sr['academic_session']) ? (string)$sr['academic_session'] : (string)($sr['batch'] ?? '');
        if (preg_match('/\b(19\d{2}|20\d{2})\b/', $sessSrc, $ym)) {
            $baseYr = (int)$ym[1];
            $dur = !empty($sr['duration_years']) && (float)$sr['duration_years'] > 0
                ? (float)$sr['duration_years']
                : (!empty($sr['prog_duration_years']) && (float)$sr['prog_duration_years'] > 0
                    ? (float)$sr['prog_duration_years']
                    : (($sr['degree_type'] === 'B.Ed') ? 1.0 : (($sr['degree_type'] === "Master's") ? 2.0 : 4.0)));
            $expYr = $baseYr + (int)round($dur);
            $updStExp->execute([$expYr, (int)$sr['id']]);
            $stFilled++;
        }
    }
    ses_mig_log("[OK] Backfilled expected_passing_year on {$admFilled} admission(s) and {$stFilled} student(s) (passing_year untouched).");
    ses_mig_log("\nAcademic Session & Passing Year Management DB migration completed successfully.");
} catch (Throwable $e) {
    ses_mig_log("[ERROR] Migration failed: " . $e->getMessage());
    if ($isCli) {
        exit(1);
    }
    throw $e;
}
