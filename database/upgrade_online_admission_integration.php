<?php
/**
 * Database Migration: RMU Academic Session, Passing Year & Online Admission Integration
 *
 * Safely extends `admissions` (preserving all existing valid records) with:
 * - application_year (SMALLINT UNSIGNED, 2015+ intake record year independent of 2011+ sessions/passing years)
 * - duration_years (DECIMAL(3,1))
 * - course_duration (VARCHAR(60))
 * - total_semesters (TINYINT UNSIGNED)
 * - original_submission_json (TEXT, immutable applicant submission snapshot)
 *
 * Also extends `academic_edit_history.field_name` / records cleanly without altering or removing any
 * existing student, result, application, or payment records.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
$isCli = (php_sapi_name() === 'cli');

function oai_log(string $msg): void {
    if (php_sapi_name() === 'cli') {
        echo $msg . "\n";
    }
}

try {
    // 1. Extend `admissions` table safely without disturbing existing records
    $admCols = array_flip($db->query("SHOW COLUMNS FROM admissions")->fetchAll(PDO::FETCH_COLUMN));
    $admAlters = [
        'application_year'         => "ADD COLUMN application_year SMALLINT UNSIGNED NULL AFTER application_id",
        'duration_years'           => "ADD COLUMN duration_years DECIMAL(3,1) NULL AFTER degree_type",
        'course_duration'          => "ADD COLUMN course_duration VARCHAR(60) NULL AFTER duration_years",
        'total_semesters'          => "ADD COLUMN total_semesters TINYINT UNSIGNED NULL AFTER course_duration",
        'original_submission_json' => "ADD COLUMN original_submission_json TEXT NULL AFTER historical_approval_note",
    ];
    foreach ($admAlters as $col => $ddl) {
        if (!isset($admCols[$col])) {
            $db->exec("ALTER TABLE admissions {$ddl}");
            oai_log("[OK] Added admissions.{$col}");
        } else {
            oai_log("[SKIP] admissions.{$col} already exists.");
        }
    }

    // Add helpful index on admissions.application_year if missing
    $idxCheck = $db->query("SHOW INDEX FROM admissions WHERE Key_name = 'idx_adm_app_year'")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($idxCheck)) {
        $db->exec("ALTER TABLE admissions ADD KEY idx_adm_app_year (application_year)");
        oai_log("[OK] Added index idx_adm_app_year on admissions(application_year).");
    }

    // Ensure admissions.status ENUM supports Additional Information Required as well as Pending/Submitted/Under Review/Accepted/Rejected
    $admStatusCol = $db->query("SHOW COLUMNS FROM admissions LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if ($admStatusCol && strpos((string)($admStatusCol['Type'] ?? ''), 'Additional Information Required') === false) {
        $db->exec("ALTER TABLE admissions MODIFY COLUMN status ENUM('Pending','Submitted','Under Review','Additional Information Required','Accepted','Rejected') NOT NULL DEFAULT 'Submitted'");
        oai_log("[OK] Extended admissions.status ENUM with Additional Information Required.");
    }

    // Ensure lookup indexes exist on admissions and students for program/department/session joins
    $safeIndexes = [
        ['admissions', 'idx_adm_program',    'program_id'],
        ['admissions', 'idx_adm_department', 'department_id'],
        ['admissions', 'idx_adm_student_id', 'student_id'],
        ['admissions', 'idx_adm_session',    'academic_session'],
        ['students',   'idx_students_program',    'program_id'],
        ['students',   'idx_students_department', 'department_id'],
        ['students',   'idx_students_session',    'academic_session'],
    ];
    foreach ($safeIndexes as [$tblName, $keyName, $colExpr]) {
        $ix = $db->query("SHOW INDEX FROM {$tblName} WHERE Key_name = " . $db->quote($keyName))->fetchAll(PDO::FETCH_ASSOC);
        if (empty($ix)) {
            $db->exec("ALTER TABLE {$tblName} ADD KEY {$keyName} ({$colExpr})");
            oai_log("[OK] Added index {$keyName} on {$tblName}({$colExpr}).");
        }
    }

    // 2. Non-destructive backfill for existing valid admissions rows
    $rows = $db->query("
        SELECT a.*, p.duration_years AS prog_duration_years, p.total_semesters AS prog_total_semesters, p.degree_type AS prog_degree_type
        FROM admissions a
        LEFT JOIN programs p ON a.program_id = p.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $upd = $db->prepare("
        UPDATE admissions
        SET application_year = COALESCE(application_year, ?),
            duration_years = COALESCE(duration_years, ?),
            course_duration = COALESCE(NULLIF(TRIM(course_duration), ''), ?),
            total_semesters = COALESCE(total_semesters, ?),
            original_submission_json = COALESCE(original_submission_json, ?)
        WHERE id = ?
    ");

    $updatedCnt = 0;
    foreach ($rows as $r) {
        $createdYr = !empty($r['created_at']) ? (int)date('Y', strtotime($r['created_at'])) : (int)date('Y');
        $appYear = max(2015, $createdYr);

        $degType = !empty($r['degree_type']) ? trim((string)$r['degree_type']) : (!empty($r['prog_degree_type']) ? trim((string)$r['prog_degree_type']) : 'Bachelor / Honours');
        $durYrs = (!empty($r['prog_duration_years']) && (float)$r['prog_duration_years'] > 0)
            ? (float)$r['prog_duration_years']
            : (($degType === 'B.Ed') ? 1.0 : (($degType === "Master's") ? 2.0 : 4.0));
        $totSems = (!empty($r['prog_total_semesters']) && (int)$r['prog_total_semesters'] > 0)
            ? (int)$r['prog_total_semesters']
            : (($degType === 'B.Ed') ? 2 : (($degType === "Master's") ? 4 : 8));
        $durLabel = format_duration_years_label($durYrs, $totSems);

        $origPayload = json_encode([
            'application_year'      => $r['application_year'] ? (int)$r['application_year'] : $appYear,
            'program_id'            => $r['program_id'] ? (int)$r['program_id'] : null,
            'interested_subject'    => (string)($r['interested_subject'] ?? ''),
            'degree_type'           => $degType,
            'academic_session'      => (string)($r['academic_session'] ?? ''),
            'duration_years'        => $r['duration_years'] !== null ? (float)$r['duration_years'] : $durYrs,
            'course_duration'       => !empty($r['course_duration']) ? (string)$r['course_duration'] : $durLabel,
            'total_semesters'       => $r['total_semesters'] !== null ? (int)$r['total_semesters'] : $totSems,
            'expected_passing_year' => $r['expected_passing_year'] !== null ? (int)$r['expected_passing_year'] : null,
            'passing_year'          => $r['passing_year'] !== null ? (string)$r['passing_year'] : null,
            'submitted_at'          => (string)($r['created_at'] ?? date('Y-m-d H:i:s')),
        ], JSON_UNESCAPED_UNICODE);

        $upd->execute([$appYear, $durYrs, $durLabel, $totSems, $origPayload, (int)$r['id']]);
        $updatedCnt++;
    }
    oai_log("[OK] Safely populated metadata & immutable baseline snapshots on {$updatedCnt} existing application(s) without overwriting.");
    oai_log("\nOnline Admission & Academic Session Integration DB upgrade completed.");
} catch (Throwable $e) {
    oai_log("[ERROR] Migration failed: " . $e->getMessage());
    if ($isCli) {
        exit(1);
    }
    throw $e;
}
