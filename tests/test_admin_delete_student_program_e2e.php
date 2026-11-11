<?php
/**
 * Comprehensive End-to-End Test Suite:
 * Admin Dashboard & Directory Permanent Delete Student / Program Functionality
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$baseUrl = 'http://127.0.0.1:8000';
$db = getDb();

$anonCookie       = sys_get_temp_dir() . '/rmu_del_anon_' . uniqid() . '.txt';
$adminCookie      = sys_get_temp_dir() . '/rmu_del_admin_' . uniqid() . '.txt';
$superadminCookie = sys_get_temp_dir() . '/rmu_del_super_' . uniqid() . '.txt';
$editorCookie     = sys_get_temp_dir() . '/rmu_del_editor_' . uniqid() . '.txt';
$studentCookie    = sys_get_temp_dir() . '/rmu_del_stud_' . uniqid() . '.txt';

function del_http(string $url, string $cookieFile, $postData = null, array $headers = [], bool $followLocation = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $followLocation,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : (string)$postData);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP error for {$url}: {$err}");
    }
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'url' => $effectiveUrl];
}

function del_csrf(string $html): string {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/dashCsrfToken\s*=\s*"([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function del_assert(bool $cond, string $msg): void {
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

$exitCode = 0;
$uniq = (string)rand(50000, 99999);
$studentAId = 0;
$studentBId = 0;
$studentCId = 0;
$admissionAId = 0;
$admissionCId = 0;
$progT1Id = 0;
$progT2Id = 0;
$editorUserId = 0;
$tempAdminUserId = 0;
$studentACode = 'RMUDELA' . $uniq;
$studentBCode = 'RMUDELB' . $uniq;
$studentCCode = 'RMUDELC' . $uniq;
$admARef = 'RMU-DELA-' . $uniq;
$admCRef = 'RMU-DELC-' . $uniq;

try {
    echo "===========================================================================\n";
    echo "  RMU ADMIN DELETE STUDENT & PROGRAM - COMPREHENSIVE E2E TEST SUITE\n";
    echo "===========================================================================\n\n";

    // Baseline check on seed student RMU2601001
    $seed = $db->query("SELECT id, student_id, full_name, status, cgpa FROM students WHERE student_id = 'RMU2601001' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    del_assert(!empty($seed), "Seed student RMU2601001 must exist");
    $seedId = (int)$seed['id'];
    $seedResBefore = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$seedId}")->fetchColumn();

    $progId = (int)$db->query("SELECT id FROM programs WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1;
    $deptId = (int)$db->query("SELECT id FROM departments WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1;
    $superadminRow = $db->query("SELECT id, username FROM users WHERE role = 'superadmin' AND status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    del_assert(!empty($superadminRow), "At least one active superadmin user must exist");
    $adminIdRef = (int)$superadminRow['id'];

    // Create dedicated Student A, Student B, Student C + rich related child rows
    $passHash = password_hash('DelTestPass!2026', PASSWORD_DEFAULT);
    $insSt = $db->prepare("
        INSERT INTO students (
            student_id, roll_number, application_number, username, full_name,
            email, phone, password_hash, program_id, degree_type, department_id,
            batch, academic_session, duration_years, program_duration, total_semesters,
            current_year, current_semester, cgpa, results_visible, credits_completed, total_credits_required, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Bachelor / Honours', ?, 'Fall 2026', 'Fall 2026', 4.0, '4 Years (8 Semesters)', 8, '1st Year', '1st Semester', 3.75, 1, 6, 140, 'Active')
    ");

    $insSt->execute([$studentACode, 'R-DELA-' . $uniq, 'APP-DELA-' . $uniq, $studentACode, 'Delete Target A ' . $uniq, "dela.{$uniq}@rmu.test", '01711' . $uniq, $passHash, $progId, $deptId]);
    $studentAId = (int)$db->lastInsertId();

    $insSt->execute([$studentBCode, 'R-DELB-' . $uniq, 'APP-DELB-' . $uniq, $studentBCode, 'Unrelated Keep B ' . $uniq, "delb.{$uniq}@rmu.test", '01811' . $uniq, $passHash, $progId, $deptId]);
    $studentBId = (int)$db->lastInsertId();

    // Create temporary custom programs T1 & T2
    $db->prepare("INSERT INTO programs (department_id, slug, title_en, title_bn, degree_level, degree_type, duration, duration_years, total_semesters, total_credits, tuition_fee, sort_order, is_active) VALUES (?, ?, ?, 'T1', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', 4.0, 8, 120, 'BDT 1,00,000', 998, 1)")
       ->execute([$deptId, 'temp-prog-1-' . $uniq, 'Temp Delete Program One ' . $uniq]);
    $progT1Id = (int)$db->lastInsertId();

    $db->prepare("INSERT INTO programs (department_id, slug, title_en, title_bn, degree_level, degree_type, duration, duration_years, total_semesters, total_credits, tuition_fee, sort_order, is_active) VALUES (?, ?, ?, 'T2', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', 4.0, 8, 120, 'BDT 1,00,000', 999, 1)")
       ->execute([$deptId, 'temp-prog-2-' . $uniq, "Temp \"Quote's\" Program " . $uniq]);
    $progT2Id = (int)$db->lastInsertId();

    $insSt->execute([$studentCCode, 'R-DELC-' . $uniq, 'APP-DELC-' . $uniq, $studentCCode, 'Program Linked Student C ' . $uniq, "delc.{$uniq}@rmu.test", '01911' . $uniq, $passHash, $progT1Id, $deptId]);
    $studentCId = (int)$db->lastInsertId();

    // Populate ALL related tables for both Student A (to be deleted) and Student B (must stay 100% intact)
    foreach ([$studentAId, $studentBId] as $sidPk) {
        $db->prepare("INSERT INTO student_results (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, is_published) VALUES (?, '1st Semester', 'CSE 101', 'Intro CS', 3.0, 100, 82, 'A+', 4.00, 1)")->execute([$sidPk]);
        $resPk = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO student_semester_results (student_id, semester_number, semester_name, academic_session, credit_hours, semester_gpa, completion_status, is_published) VALUES (?, 1, '1st Semester', 'Fall 2026', 3.0, 4.00, 'Completed', 1)")->execute([$sidPk]);
        $db->prepare("INSERT INTO student_courses (student_id, course_code, course_name, credit, instructor_name, class_schedule, room_no, semester, status) VALUES (?, 'CSE 101', 'Intro CS', 3.0, 'Prof Test', 'Sun 10:00', '301', '1st Semester', 'Enrolled')")->execute([$sidPk]);
        $db->prepare("INSERT INTO student_fees (student_id, fee_title, amount, due_date, status) VALUES (?, 'Semester Tuition', 25000.00, '2026-12-01', 'Pending')")->execute([$sidPk]);
        $db->prepare("INSERT INTO student_notifications (student_id, title, message, type) VALUES (?, 'Welcome', 'Test notification', 'general')")->execute([$sidPk]);
        $db->prepare("INSERT INTO result_requests (student_id, semester, course_code, request_type, subject, description, status) VALUES (?, '1st Semester', 'CSE 101', 'Result Review', 'Check Marks', 'Request test', 'Pending')")->execute([$sidPk]);
        $db->prepare("INSERT INTO result_modification_history (result_id, student_id, admin_id, course_code, semester, field_name, old_value, new_value, reason) VALUES (?, ?, ?, 'CSE 101', '1st Semester', 'marks', '80', '82', 'Recheck')")->execute([$resPk, $sidPk, $adminIdRef]);
        $db->prepare("INSERT INTO academic_edit_history (entity_type, entity_id, student_id_code, admin_id, field_name, old_value, new_value, reason) VALUES ('student', ?, ?, ?, 'batch', 'Spring 2026', 'Fall 2026', 'Test edit')")->execute([$sidPk, $sidPk === $studentAId ? $studentACode : $studentBCode, $adminIdRef]);
    }
    $db->prepare("INSERT INTO student_retention_archives (student_pk_id, student_id, full_name, snapshot_json, deleted_by, deletion_reason) VALUES (?, ?, 'Prearchive', '{}', ?, 'Test archive')")->execute([$studentAId, $studentACode, $adminIdRef]);
    $db->prepare("INSERT INTO academic_edit_history (entity_type, entity_id, admin_id, field_name, old_value, new_value, reason) VALUES ('program', ?, ?, 'total_credits', '110', '120', 'Temp credit adj')")->execute([$progT1Id, $adminIdRef]);

    // Linked admissions rows for Student A & Program T1
    $db->prepare("INSERT INTO admissions (application_ref, application_id, roll_number, full_name, date_of_birth, gender, mobile, email, ssc_gpa, ssc_year, hsc_group, hsc_gpa, hsc_year, interested_subject, program_id, department_id, status, payment_status, student_id, portal_password) VALUES (?, ?, ?, ?, '2004-01-01', 'Male', ?, ?, 5.00, '2020', 'Science', 5.00, '2022', 'CSE', ?, ?, 'Accepted', 'Paid', ?, 'TempPassA')")
       ->execute([$admARef, $admARef, '9988-' . $uniq, 'Delete Target A ' . $uniq, '01711' . $uniq, "dela.{$uniq}@rmu.test", $progId, $deptId, $studentACode]);
    $admissionAId = (int)$db->lastInsertId();

    $db->prepare("INSERT INTO admissions (application_ref, application_id, roll_number, full_name, date_of_birth, gender, mobile, email, ssc_gpa, ssc_year, hsc_group, hsc_gpa, hsc_year, interested_subject, program_id, department_id, status, payment_status, student_id) VALUES (?, ?, ?, ?, '2004-02-02', 'Male', ?, ?, 5.00, '2020', 'Science', 5.00, '2022', 'Temp', ?, ?, 'Accepted', 'Paid', ?)")
       ->execute([$admCRef, $admCRef, '9977-' . $uniq, 'Program Linked C ' . $uniq, '01911' . $uniq, "delc.{$uniq}@rmu.test", $progT1Id, $deptId, $studentCCode]);
    $admissionCId = (int)$db->lastInsertId();

    // Create temporary 'editor' user and temporary 'admin' user
    $editorPwd = 'EdPass!2026';
    $adminPwd  = 'AdmPass!2026';
    $editorUsername = 'e2e_editor_' . $uniq;
    $adminUsername  = 'e2e_admin_' . $uniq;
    $db->prepare("INSERT INTO users (username, full_name, email, password_hash, role, status) VALUES (?, 'E2E Editor', ?, ?, 'editor', 'active')")->execute([$editorUsername, "ed.{$uniq}@rmu.test", password_hash($editorPwd, PASSWORD_DEFAULT)]);
    $editorUserId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO users (username, full_name, email, password_hash, role, status) VALUES (?, 'E2E Admin', ?, ?, 'admin', 'active')")->execute([$adminUsername, "adm.{$uniq}@rmu.test", password_hash($adminPwd, PASSWORD_DEFAULT)]);
    $tempAdminUserId = (int)$db->lastInsertId();

    // -------------------------------------------------------------------------
    // STEP 1: Unauthenticated & Student Session Security Checks
    // -------------------------------------------------------------------------
    echo "1. Testing Unauthenticated & Student Role Guards:\n";
    $getMethod = del_http($baseUrl . '/actions/admin-delete-student.php', $anonCookie);
    del_assert($getMethod['code'] === 405, "GET /actions/admin-delete-student.php must return 405, got {$getMethod['code']}");

    $unauthDel = del_http($baseUrl . '/actions/admin-delete-student.php', $anonCookie, ['id' => $studentAId]);
    $unauthJson = json_decode($unauthDel['body'], true);
    del_assert($unauthDel['code'] === 401 && is_array($unauthJson) && $unauthJson['success'] === false, "Unauthenticated delete must return 401 JSON error");

    $unauthProg = del_http($baseUrl . '/actions/admin-delete-program.php', $anonCookie, ['id' => $progT1Id]);
    del_assert($unauthProg['code'] === 401, "Unauthenticated program delete must return 401");

    // Log in Student A to student portal and verify student cannot delete any student
    $stLoginGet = del_http($baseUrl . '/student/login.php', $studentCookie);
    del_http($baseUrl . '/student/login.php', $studentCookie, [
        'csrf_token' => del_csrf($stLoginGet['body']),
        'student_id' => $studentACode,
        'password'   => 'DelTestPass!2026',
    ]);
    $stAttemptDel = del_http($baseUrl . '/actions/admin-delete-student.php', $studentCookie, ['id' => $studentBId]);
    del_assert($stAttemptDel['code'] === 401, "Student session attempting admin delete API must return 401");
    del_assert((int)$db->query("SELECT COUNT(*) FROM students WHERE id = {$studentBId}")->fetchColumn() === 1, "Student B must still exist");
    echo "   [PASS] Unauthenticated and Student sessions strictly blocked.\n\n";

    // -------------------------------------------------------------------------
    // STEP 2: Unauthorized 'editor' Role Must Neither See Buttons Nor Call API
    // -------------------------------------------------------------------------
    echo "2. Testing Non-Admin ('editor') Authorization Enforcement:\n";
    $edLoginGet = del_http($baseUrl . '/admin/login.php', $editorCookie);
    del_http($baseUrl . '/admin/login.php', $editorCookie, [
        'csrf_token' => del_csrf($edLoginGet['body']),
        'username'   => $editorUsername,
        'password'   => $editorPwd,
    ]);
    $edDash = del_http($baseUrl . '/admin/dashboard.php', $editorCookie);
    del_assert($edDash['code'] === 200, "Editor dashboard loads 200");
    del_assert(strpos($edDash['body'], 'dash-delete-student-btn') === false, "Editor must NOT see delete buttons on dashboard.php");
    $edDir = del_http($baseUrl . '/admin/students.php?search=' . urlencode($studentACode), $editorCookie);
    del_assert(strpos($edDir['body'], 'dir-delete-student-btn') === false, "Editor must NOT see delete buttons on students.php");

    $edProg = del_http($baseUrl . '/admin/programs.php', $editorCookie);
    del_assert(strpos($edProg['body'], 'deleteProgramRecord') === false, "Editor must NOT see delete buttons on programs.php");

    $edCsrf = del_csrf($edDir['body']);
    del_assert($edCsrf !== '', "Extracted CSRF token for editor guard check");
    $edTryStudent = del_http($baseUrl . '/actions/admin-delete-student.php', $editorCookie, ['csrf_token' => $edCsrf, 'id' => $studentAId]);
    $edTryJson = json_decode($edTryStudent['body'], true);
    del_assert($edTryStudent['code'] === 403 && ($edTryJson['success'] ?? true) === false, "Editor POST delete student must return 403 JSON error");
    $edTryProg = del_http($baseUrl . '/actions/admin-delete-program.php', $editorCookie, ['csrf_token' => $edCsrf, 'id' => $progT1Id]);
    del_assert($edTryProg['code'] === 403, "Editor POST delete program must return 403 JSON error");
    del_assert((int)$db->query("SELECT COUNT(*) FROM students WHERE id = {$studentAId}")->fetchColumn() === 1, "Student A must remain intact after editor denial");
    echo "   [PASS] Editor role concealed & rejected with HTTP 403.\n\n";

    // -------------------------------------------------------------------------
    // STEP 3: Admin & Superadmin UI Presence, Prompt Text & Invalid Input Handling
    // -------------------------------------------------------------------------
    echo "3. Verifying Dashboard/Directory/Program Trash Buttons & Error Handling:\n";
    $supLoginGet = del_http($baseUrl . '/admin/login.php', $superadminCookie);
    $supLoginPost = del_http($baseUrl . '/admin/login.php', $superadminCookie, [
        'csrf_token' => del_csrf($supLoginGet['body']),
        'username'   => $superadminRow['username'],
        'password'   => 'Admin@RMU2026!',
    ]);
    del_assert($supLoginPost['code'] === 200 && strpos($supLoginPost['url'], '/admin/dashboard.php') !== false, "Superadmin login succeeded");

    $dashHtml = del_http($baseUrl . '/admin/dashboard.php', $superadminCookie)['body'];
    del_assert(strpos($dashHtml, 'dash-delete-student-btn') !== false, "dashboard.php renders Delete (Trash) buttons for students");
    del_assert(strpos($dashHtml, 'data-student-id="' . $studentBId . '"') !== false || strpos($dashHtml, 'data-student-id="' . $studentAId . '"') !== false, "dashboard.php binds real unique DB id on button");
    del_assert(strpos($dashHtml, "Are you sure you want to delete this student?") !== false, "dashboard.php uses exact required confirm popup string");

    $dirHtml = del_http($baseUrl . '/admin/students.php?search=' . urlencode($studentACode), $superadminCookie)['body'];
    del_assert(strpos($dirHtml, 'dir-delete-student-btn') !== false && strpos($dirHtml, 'data-student-id="' . $studentAId . '"') !== false, "students.php directory binds real unique DB id on Trash button");
    del_assert(strpos($dirHtml, "Are you sure you want to delete this student?") !== false, "students.php directory uses exact required confirm popup string");

    $progHtml = del_http($baseUrl . '/admin/programs.php', $superadminCookie)['body'];
    del_assert(strpos($progHtml, 'data-program-row-id="' . $progT1Id . '"') !== false && strpos($progHtml, 'data-program-id="' . $progT1Id . '"') !== false, "programs.php renders program rows and Delete buttons with exact DB ID");

    $supCsrf = del_csrf($dashHtml);

    // Test invalid CSRF
    $badCsrfRes = del_http($baseUrl . '/actions/admin-delete-student.php', $superadminCookie, ['csrf_token' => 'invalid-token', 'id' => $studentAId]);
    del_assert($badCsrfRes['code'] === 403 && (json_decode($badCsrfRes['body'], true)['success'] ?? true) === false, "Invalid CSRF must return 403 error JSON");

    // Test invalid / row-index 0 / non-existent ID
    $badIdRes = del_http($baseUrl . '/actions/admin-delete-student.php', $superadminCookie, ['csrf_token' => $supCsrf, 'id' => '0']);
    del_assert($badIdRes['code'] === 400, "ID=0 must return 400 error JSON");

    $missingIdRes = del_http($baseUrl . '/actions/admin-delete-student.php', $superadminCookie, ['csrf_token' => $supCsrf, 'id' => '999999999']);
    del_assert($missingIdRes['code'] === 404, "Nonexistent student ID must return 404 JSON error");
    echo "   [PASS] UI attributes, confirmation prompt, CSRF & invalid-ID errors verified.\n\n";

    // -------------------------------------------------------------------------
    // STEP 4: Permanent Student A Deletion + Child Cleanup + Student B & Seed Isolation
    // -------------------------------------------------------------------------
    echo "4. Testing Permanent Student Deletion, Related Tables & Strict Isolation:\n";
    $delStudentRes = del_http($baseUrl . '/actions/admin-delete-student.php', $superadminCookie, [
        'csrf_token' => $supCsrf,
        'id'         => $studentAId,
    ]);
    $delStudentJson = json_decode($delStudentRes['body'], true);
    del_assert($delStudentRes['code'] === 200 && ($delStudentJson['success'] ?? false) === true, "Delete API must succeed with HTTP 200 (got {$delStudentRes['code']}: {$delStudentRes['body']})");
    del_assert(($delStudentJson['message'] ?? '') === 'Student deleted successfully.', "Success message must match 'Student deleted successfully.'");
    del_assert((int)($delStudentJson['deleted_id'] ?? 0) === $studentAId, "Response deleted_id must match Student A DB PK");
    del_assert(isset($delStudentJson['stats']['total_students']), "Response must include updated total_students for live dashboard update");

    // Verify Student A row AND ALL child rows are genuinely deleted from MySQL
    del_assert((int)$db->query("SELECT COUNT(*) FROM students WHERE id = {$studentAId}")->fetchColumn() === 0, "Student A must be permanently removed from students table");
    foreach ([
        'student_results'             => "student_id = {$studentAId}",
        'student_semester_results'    => "student_id = {$studentAId}",
        'student_courses'             => "student_id = {$studentAId}",
        'student_fees'                => "student_id = {$studentAId}",
        'student_notifications'       => "student_id = {$studentAId}",
        'result_requests'             => "student_id = {$studentAId}",
        'result_modification_history' => "student_id = {$studentAId}",
        'academic_edit_history'       => "entity_type = 'student' AND entity_id = {$studentAId}",
        'student_retention_archives'  => "student_pk_id = {$studentAId}",
    ] as $tbl => $cond) {
        del_assert((int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE {$cond}")->fetchColumn() === 0, "Related rows in {$tbl} for Student A must be cleaned cleanly");
    }

    // Verify Admission A was safely unlinked (NOT deleted, portal_password cleared, student_id NULL)
    $admARow = $db->query("SELECT id, student_id, portal_password FROM admissions WHERE id = {$admissionAId}")->fetch(PDO::FETCH_ASSOC);
    del_assert(!empty($admARow) && $admARow['student_id'] === null && $admARow['portal_password'] === null, "Linked admissions row A must be safely unlinked with student_id=NULL & portal_password=NULL");

    // Verify Student B AND ALL of Student B's child rows are 100% untouched
    del_assert((int)$db->query("SELECT COUNT(*) FROM students WHERE id = {$studentBId}")->fetchColumn() === 1, "Unrelated Student B must NOT be deleted");
    foreach ([
        'student_results'             => "student_id = {$studentBId}",
        'student_semester_results'    => "student_id = {$studentBId}",
        'student_courses'             => "student_id = {$studentBId}",
        'student_fees'                => "student_id = {$studentBId}",
        'student_notifications'       => "student_id = {$studentBId}",
        'result_requests'             => "student_id = {$studentBId}",
        'result_modification_history' => "student_id = {$studentBId}",
        'academic_edit_history'       => "entity_type = 'student' AND entity_id = {$studentBId}",
    ] as $tbl => $cond) {
        del_assert((int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE {$cond}")->fetchColumn() === 1, "Unrelated Student B row in {$tbl} must remain 100% intact");
    }

    // Verify seed student RMU2601001 untouched
    $seedAfter = $db->query("SELECT id, status, cgpa FROM students WHERE id = {$seedId}")->fetch(PDO::FETCH_ASSOC);
    $seedResAfter = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$seedId}")->fetchColumn();
    del_assert(!empty($seedAfter) && $seedAfter['status'] === $seed['status'] && (int)$seedResAfter === $seedResBefore, "Seed student RMU2601001 must remain 100% intact");

    // Verify second attempt on already-deleted Student A returns 404 error
    $dupAttempt = del_http($baseUrl . '/actions/admin-delete-student.php', $superadminCookie, ['csrf_token' => $supCsrf, 'id' => $studentAId]);
    del_assert($dupAttempt['code'] === 404, "Deleting already-deleted Student A must return 404");

    // Log in with regular 'admin' role and delete Student B via JSON payload
    $admLoginGet = del_http($baseUrl . '/admin/login.php', $adminCookie);
    del_http($baseUrl . '/admin/login.php', $adminCookie, [
        'csrf_token' => del_csrf($admLoginGet['body']),
        'username'   => $adminUsername,
        'password'   => $adminPwd,
    ]);
    $admDash = del_http($baseUrl . '/admin/dashboard.php', $adminCookie);
    $admCsrf = del_csrf($admDash['body']);
    $delBJsonRes = del_http(
        $baseUrl . '/actions/admin-delete-student.php',
        $adminCookie,
        json_encode(['csrf_token' => $admCsrf, 'id' => $studentBId]),
        ['Content-Type: application/json']
    );
    $delBJson = json_decode($delBJsonRes['body'], true);
    del_assert($delBJsonRes['code'] === 200 && ($delBJson['success'] ?? false) === true, "Regular admin deleting Student B via JSON must succeed");
    del_assert((int)$db->query("SELECT COUNT(*) FROM students WHERE id = {$studentBId}")->fetchColumn() === 0, "Student B permanently deleted from DB");
    echo "   [PASS] Permanent DB delete, child cleanup, admission unlink, and unrelated-student isolation verified!\n\n";

    // -------------------------------------------------------------------------
    // STEP 5: Permanent Program Deletion (AJAX + POST fallback) + Student Preservation
    // -------------------------------------------------------------------------
    echo "5. Testing Permanent Program Deletion (AJAX & POST) with Student Preservation:\n";
    $delProg1Res = del_http($baseUrl . '/actions/admin-delete-program.php', $adminCookie, [
        'csrf_token' => $admCsrf,
        'id'         => $progT1Id,
    ]);
    $delProg1Json = json_decode($delProg1Res['body'], true);
    del_assert($delProg1Res['code'] === 200 && ($delProg1Json['success'] ?? false) === true, "Program T1 AJAX deletion must succeed");
    del_assert((int)$db->query("SELECT COUNT(*) FROM programs WHERE id = {$progT1Id}")->fetchColumn() === 0, "Program T1 permanently removed from programs table");
    del_assert((int)$db->query("SELECT COUNT(*) FROM academic_edit_history WHERE entity_type = 'program' AND entity_id = {$progT1Id}")->fetchColumn() === 0, "Program T1 edit history cleaned");

    // Verify Student C and Admission C still exist safely with program_id = NULL
    $stCRow = $db->query("SELECT id, program_id, full_name FROM students WHERE id = {$studentCId}")->fetch(PDO::FETCH_ASSOC);
    $admCRow = $db->query("SELECT id, program_id, full_name FROM admissions WHERE id = {$admissionCId}")->fetch(PDO::FETCH_ASSOC);
    del_assert(!empty($stCRow) && $stCRow['program_id'] === null, "Student C preserved intact with program_id safely set to NULL");
    del_assert(!empty($admCRow) && $admCRow['program_id'] === null, "Admission C preserved intact with program_id safely set to NULL");

    // Verify standard POST delete on admin/programs.php with quotes in title
    $delProg2Post = del_http($baseUrl . '/admin/programs.php', $adminCookie, [
        'csrf_token' => $admCsrf,
        'action'     => 'delete',
        'id'         => $progT2Id,
    ]);
    del_assert($delProg2Post['code'] === 200 && (int)$db->query("SELECT COUNT(*) FROM programs WHERE id = {$progT2Id}")->fetchColumn() === 0, "Program T2 (with quotes in title) deleted cleanly via admin/programs.php POST");

    // Now permanently delete Student C using admin-delete-student.php
    $delCRes = del_http($baseUrl . '/actions/admin-delete-student.php', $adminCookie, ['csrf_token' => $admCsrf, 'id' => $studentCId]);
    del_assert($delCRes['code'] === 200 && (int)$db->query("SELECT COUNT(*) FROM students WHERE id = {$studentCId}")->fetchColumn() === 0, "Student C cleanly deleted via API");
    echo "   [PASS] Program deletion & dependent record safety verified!\n\n";

    echo "===========================================================================\n";
    echo ">>> ALL ADMIN DELETE STUDENT & PROGRAM E2E TESTS PASSED (100% PASS)! <<<\n";
    echo "===========================================================================\n";
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] " . $e->getMessage() . "\n");
    $exitCode = 1;
} finally {
    // Cleanup temporary test rows cleanly
    foreach ([$studentAId, $studentBId, $studentCId] as $sPk) {
        if ($sPk > 0) {
            try {
                permanently_delete_student_record($sPk, 1, 'E2E cleanup');
            } catch (Throwable $ignore) {}
        }
    }
    foreach ([$progT1Id, $progT2Id] as $pPk) {
        if ($pPk > 0) {
            try {
                permanently_delete_program_record($pPk, 1);
            } catch (Throwable $ignore) {}
        }
    }
    foreach ([$admissionAId, $admissionCId] as $aPk) {
        if ($aPk > 0) {
            $db->prepare("DELETE FROM admissions WHERE id = ?")->execute([$aPk]);
        }
    }
    foreach ([$editorUserId, $tempAdminUserId] as $uPk) {
        if ($uPk > 0) {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uPk]);
        }
    }
    $db->prepare("DELETE FROM audit_logs WHERE details LIKE ? OR details LIKE ? OR details LIKE ?")->execute([
        "%{$uniq}%",
        "%{$studentACode}%",
        "%{$studentBCode}%",
    ]);
    foreach ([$anonCookie, $adminCookie, $superadminCookie, $editorCookie, $studentCookie] as $cf) {
        if (file_exists($cf)) @unlink($cf);
    }
}

exit($exitCode);
