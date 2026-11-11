<?php
/**
 * Comprehensive Full-Stack HTTP & Database E2E Test:
 * Advanced Student Result & Account Management System
 *
 * Verifies:
 * 1. Search by Student ID, Roll Number, Application Number, and Name across Admin tools
 * 2. Individual Student Profile Action Panel & Status Labels
 * 3. Upload Draft Result -> Student Isolation -> Publish -> Edit Result -> Verify Marks/GPA/CGPA
 * 4. Result Visibility Control (Hide Result / Show Result without deleting DB data)
 * 5. Publish / Unpublish Control (without deleting DB data)
 * 6. Account Deactivation -> Immediate Session Invalidation -> Blocked Re-login -> Record Preservation -> Reactivation
 * 7. Permanent Deletion with Double Confirmation -> Revoked Credentials -> Retention Archive -> Other Students Untouched
 * 8. Security Guards & Admin Activity Audit Log Verification
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$baseUrl = 'http://127.0.0.1:8000';
$db = getDb();

$adminCookie = sys_get_temp_dir() . '/rmu_e2e_admin_' . uniqid() . '.txt';
$studentCookie = sys_get_temp_dir() . '/rmu_e2e_student_' . uniqid() . '.txt';
$studentCookie2 = sys_get_temp_dir() . '/rmu_e2e_student2_' . uniqid() . '.txt';
$anonCookie = sys_get_temp_dir() . '/rmu_e2e_anon_' . uniqid() . '.txt';

function http_req(string $url, string $cookieFile, ?array $postData = null, bool $followLocation = true): array {
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
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

function extract_csrf(string $html): string {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function check_assert(bool $cond, string $message): void {
    if (!$cond) {
        throw new RuntimeException($message);
    }
}

$exitCode = 0;

$createdStudentPk = 0;
$testSuffix = (string)rand(70000, 99999);
$testStudentCode = 'RMU26E2E' . $testSuffix;
$testRollNo = 'ROLL-E2E-' . $testSuffix;
$testAppNo = 'RMU-APP-E2E-' . $testSuffix;
$testUsername = 'e2euser' . $testSuffix;
$testName = 'E2E Account Control Student ' . $testSuffix;
$testEmail = 'e2e.' . $testSuffix . '@rmu.ac.bd';
$testPhone = '017' . rand(10000000, 99999999);
$testPassword = 'StrongPass!' . $testSuffix;

try {
    echo "===========================================================================\n";
    echo "  RMU ADVANCED STUDENT RESULT & ACCOUNT CONTROL - FULL E2E TEST SUITE\n";
    echo "===========================================================================\n\n";

    // Baseline check on seed student RMU2601001 (#4)
    $seedBefore = $db->query("SELECT id, student_id, status, results_visible FROM students WHERE student_id = 'RMU2601001' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    check_assert(!empty($seedBefore), "Seed student RMU2601001 must exist");
    $seedId = (int)$seedBefore['id'];
    $seedResBefore = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$seedId}")->fetchColumn();

    // -------------------------------------------------------------------------
    // STEP 1: Unauthenticated Security Check + Admin Login
    // -------------------------------------------------------------------------
    echo "1. Security Guard & Admin Authentication:\n";
    $unauthCheck = http_req($baseUrl . '/admin/student-detail.php?id=' . $seedId, $anonCookie, null, false);
    check_assert($unauthCheck['code'] === 302, "Unauthenticated access to student-detail.php must redirect (HTTP 302)");
    echo "   [PASS] Unauthenticated requests blocked cleanly.\n";

    $adminLoginPage = http_req($baseUrl . '/admin/login.php', $adminCookie);
    $adminLoginCsrf = extract_csrf($adminLoginPage['body']);
    check_assert($adminLoginCsrf !== '', "Admin login page must expose CSRF token");

    $adminLoginRes = http_req($baseUrl . '/admin/login.php', $adminCookie, [
        'csrf_token' => $adminLoginCsrf,
        'username'   => 'admin',
        'password'   => 'Admin@RMU2026!',
    ]);
    check_assert($adminLoginRes['code'] === 200 && strpos($adminLoginRes['url'], '/admin/dashboard.php') !== false, "Admin login must succeed");
    echo "   [PASS] Admin authenticated successfully.\n\n";

    // -------------------------------------------------------------------------
    // STEP 2: Create Test Student with Student ID, Roll, App No & Username
    // -------------------------------------------------------------------------
    echo "2. Enrolling Dedicated Test Student ({$testStudentCode}) ...\n";
    $enrollPage = http_req($baseUrl . '/admin/students.php?action=enroll', $adminCookie);
    $enrollCsrf = extract_csrf($enrollPage['body']);
    check_assert($enrollCsrf !== '', "One-page enroll form must expose CSRF token");

    $progId = (int)$db->query("SELECT id FROM programs WHERE slug = 'bsc-in-cse'")->fetchColumn() ?: 1;
    $deptId = (int)$db->query("SELECT id FROM departments WHERE slug = 'dept-cse'")->fetchColumn() ?: 6;

    $createRes = http_req($baseUrl . '/admin/students.php', $adminCookie, [
        'csrf_token'         => $enrollCsrf,
        'action'             => 'add',
        'submit_action'      => 'save_student_only',
        'student_id'         => $testStudentCode,
        'roll_number'        => $testRollNo,
        'application_number' => $testAppNo,
        'username'           => $testUsername,
        'full_name'          => $testName,
        'father_name'        => 'Md. E2E Father',
        'mother_name'        => 'Mrs. E2E Mother',
        'dob'                => '2004-06-15',
        'gender'             => 'Male',
        'email'              => $testEmail,
        'phone'              => $testPhone,
        'present_address'    => 'RMU Residence Hall, Kushtia',
        'permanent_address'  => 'Kushtia Sadar, Kushtia',
        'program_id'         => $progId,
        'department_id'      => $deptId,
        'batch'              => '14th Batch (2026-2030)',
        'current_year'       => '1st Year',
        'current_semester'   => '1st Semester',
        'status'             => 'Active',
        'password'           => $testPassword,
    ]);
    check_assert($createRes['code'] === 200, "Create student request must return HTTP 200");

    $stStmt = $db->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
    $stStmt->execute([$testStudentCode]);
    $createdStudent = $stStmt->fetch(PDO::FETCH_ASSOC);
    check_assert(!empty($createdStudent), "Test student must be persisted in database");
    $createdStudentPk = (int)$createdStudent['id'];
    check_assert($createdStudent['roll_number'] === $testRollNo, "Roll number must be persisted");
    check_assert($createdStudent['application_number'] === $testAppNo, "Application number must be persisted");
    check_assert($createdStudent['username'] === $testUsername, "Username must be persisted");
    echo "   [PASS] Student PK #{$createdStudentPk} created with Roll ({$testRollNo}), App No ({$testAppNo}), Username ({$testUsername}).\n\n";

    // -------------------------------------------------------------------------
    // STEP 3: Search by Student ID, Roll Number, Application Number & Name
    // -------------------------------------------------------------------------
    echo "3. Search Verification across Student Directory & Results Manager:\n";
    $searchTerms = [
        'Student ID'         => $testStudentCode,
        'Roll Number'        => $testRollNo,
        'Application Number' => $testAppNo,
        'Name'               => $testName,
    ];
    foreach ($searchTerms as $label => $term) {
        $dirSearch = http_req($baseUrl . '/admin/students.php?search=' . urlencode($term), $adminCookie);
        check_assert($dirSearch['code'] === 200 && strpos($dirSearch['body'], $testStudentCode) !== false, "students.php search by {$label} must return test student");

        $rmSearch = http_req($baseUrl . '/admin/results-manager.php?search=' . urlencode($term), $adminCookie);
        check_assert($rmSearch['code'] === 200 && strpos($rmSearch['body'], $testStudentCode) !== false, "results-manager.php search by {$label} must return test student");
    }
    echo "   [PASS] Search by Student ID, Roll Number, Application Number, and Name verified!\n\n";

    // -------------------------------------------------------------------------
    // STEP 4: Individual Action Panel Rendering & Draft Result Upload
    // -------------------------------------------------------------------------
    echo "4. Individual Student Action Panel & Draft Result Upload:\n";
    $detailUrl = $baseUrl . '/admin/student-detail.php?id=' . $createdStudentPk;
    $detailPage = http_req($detailUrl, $adminCookie);
    check_assert($detailPage['code'] === 200, "student-detail.php must return HTTP 200");

    $requiredProfileItems = [
        $testName, $testStudentCode, $testRollNo, $testAppNo, $testUsername, $testPhone,
        'Account: Active', 'Pending',
        'Upload Result', 'Edit Result', 'Publish Result', 'Unpublish Result',
        'Hide Result', 'Deactivate Account', 'Delete Account'
    ];
    foreach ($requiredProfileItems as $item) {
        check_assert(strpos($detailPage['body'], $item) !== false, "student-detail.php missing required item/button: {$item}");
    }
    echo "   [PASS] Complete Student Profile, Status Badges, and All Action Buttons verified.\n";

    $detailCsrf = extract_csrf($detailPage['body']);
    // Upload Semester 1 Subject as DRAFT (CSE 101, marks=76 => A / 3.75)
    $addDraftRes = http_req($detailUrl, $adminCookie, [
        'csrf_token'     => $detailCsrf,
        'action'         => 'add_result',
        'semester'       => '1st Semester',
        'course_code'    => 'CSE 101',
        'course_name'    => 'Structured Programming in C',
        'credit'         => '3.0',
        'full_marks'     => '100',
        'marks'          => '76',
        'letter_grade'   => 'A',
        'grade_point'    => '3.75',
        'exam_term'      => 'Semester Final Examination',
        'publish_status' => 'Draft',
    ]);
    check_assert($addDraftRes['code'] === 200, "Upload draft result must return HTTP 200");

    $resRows = $db->query("SELECT * FROM student_results WHERE student_id = {$createdStudentPk} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    check_assert(count($resRows) === 1, "Exactly 1 draft result row must exist");
    check_assert((int)$resRows[0]['is_published'] === 0 && $resRows[0]['publish_status'] === 'Draft', "Uploaded draft result must be saved as Draft (is_published=0)");
    $firstResultId = (int)$resRows[0]['id'];
    echo "   [PASS] Subject CSE 101 uploaded as Draft (76 marks -> Grade A, GP 3.75).\n";

    // Log test student into Student Portal using username
    $sLoginPage = http_req($baseUrl . '/student/login.php', $studentCookie);
    $sCsrf = extract_csrf($sLoginPage['body']);
    $sLoginPost = http_req($baseUrl . '/student/login.php', $studentCookie, [
        'csrf_token' => $sCsrf,
        'student_id' => $testUsername,
        'password'   => $testPassword,
    ]);
    check_assert(strpos($sLoginPost['url'], '/student/dashboard.php') !== false, "Active student login using username must succeed");

    // Verify Draft is NOT visible in Student Portal
    $sDraftResultsPage = http_req($baseUrl . '/student/results.php', $studentCookie);
    check_assert(strpos($sDraftResultsPage['body'], 'CSE 101') === false, "Draft result must NOT leak into Student Portal");
    check_assert(strpos($sDraftResultsPage['body'], 'No academic results published yet') !== false, "Draft state must display 'No academic results published yet'");
    echo "   [PASS] Draft result strictly concealed from Student Portal.\n\n";

    // -------------------------------------------------------------------------
    // STEP 5: Publish Result, Add 2nd Semester Result & Edit Result
    // -------------------------------------------------------------------------
    echo "5. Publish Result, Edit Result, and Live Student Portal Sync:\n";
    $detailPage2 = http_req($detailUrl, $adminCookie);
    $detailCsrf = extract_csrf($detailPage2['body']);

    // Publish all results
    $pubRes = http_req($detailUrl, $adminCookie, [
        'csrf_token' => $detailCsrf,
        'action'     => 'publish_all_results',
    ]);
    check_assert($pubRes['code'] === 200, "Publish results POST must return 200");
    check_assert(strpos($pubRes['body'], 'Result Published') !== false, "Student profile must show Result Published status label");

    // Verify publication in /admin/result-publication.php
    $pubHub = http_req($baseUrl . '/admin/result-publication.php?search=' . urlencode($testStudentCode), $adminCookie);
    check_assert($pubHub['code'] === 200 && strpos($pubHub['body'], $testStudentCode) !== false && strpos($pubHub['body'], 'Result Published') !== false, "result-publication.php must reflect Result Published");

    // Verify live sync in Student Portal
    $sPubResults = http_req($baseUrl . '/student/results.php', $studentCookie);
    check_assert(strpos($sPubResults['body'], 'CSE 101') !== false && strpos($sPubResults['body'], '76.0') !== false && strpos($sPubResults['body'], '3.75') !== false, "Student Portal must display published result CSE 101 with 3.75 GPA");

    // Edit existing result from 76 (A / 3.75) -> 88 (A+ / 4.00)
    $editRes = http_req($detailUrl, $adminCookie, [
        'csrf_token'        => $detailCsrf,
        'action'            => 'edit_result',
        'result_id'         => $firstResultId,
        'semester'          => '1st Semester',
        'course_code'       => 'CSE 101',
        'course_name'       => 'Structured Programming in C',
        'credit'            => '3.0',
        'full_marks'        => '100',
        'marks'             => '88',
        'exam_term'         => 'Semester Final Examination',
        'publish_status'    => 'Published',
        'correction_reason' => 'E2E Re-evaluation correction to 88 marks',
    ]);
    check_assert($editRes['code'] === 200, "Edit result POST must return 200");

    // Add 2nd semester subject (CSE 103, 3.0 cr, 64 marks => B / 3.00) so multi-semester SGPA/CGPA (3.50) is verified
    $addSem2Res = http_req($detailUrl, $adminCookie, [
        'csrf_token'     => $detailCsrf,
        'action'         => 'add_result',
        'semester'       => '2nd Semester',
        'course_code'    => 'CSE 103',
        'course_name'    => 'Discrete Mathematics',
        'credit'         => '3.0',
        'full_marks'     => '100',
        'marks'          => '64',
        'exam_term'      => 'Semester Final Examination',
        'publish_status' => 'Published',
    ]);
    check_assert($addSem2Res['code'] === 200, "Add 2nd semester result must return 200");

    $cgpaAfterEdit = (float)$db->query("SELECT cgpa FROM students WHERE id = {$createdStudentPk}")->fetchColumn();
    check_assert(abs($cgpaAfterEdit - 3.50) <= 0.01, "Recalculated CGPA across Sem 1 (4.00) & Sem 2 (3.00) must equal 3.50, got {$cgpaAfterEdit}");

    $sEditedPortal = http_req($baseUrl . '/student/results.php', $studentCookie);
    check_assert(strpos($sEditedPortal['body'], '88.0') !== false && strpos($sEditedPortal['body'], 'CSE 103') !== false && strpos($sEditedPortal['body'], '3.50') !== false, "Student Portal must immediately show edited marks (88.0), Sem 2 subject, and CGPA 3.50");
    echo "   [PASS] Published, edited existing marks (76 -> 88), added Sem 2, verified CGPA 3.50 in Student Portal.\n\n";

    // -------------------------------------------------------------------------
    // STEP 6: Result Visibility Control (Hide Result & Show Result) + Unpublish
    // -------------------------------------------------------------------------
    echo "6. Testing Result Visibility Control (Hide/Show Result) & Unpublish:\n";
    $hideRes = http_req($detailUrl, $adminCookie, [
        'csrf_token' => $detailCsrf,
        'action'     => 'hide_results',
        'reason'     => 'Pending tuition clearance check',
    ]);
    check_assert($hideRes['code'] === 200, "Hide result POST must return 200");
    check_assert(strpos($hideRes['body'], 'Result Hidden') !== false, "Admin profile must show Result Hidden badge");

    // Verify DB records are completely intact (still 2 rows)
    $countWhileHidden = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$createdStudentPk}")->fetchColumn();
    check_assert($countWhileHidden === 2, "Hiding results must NOT delete original academic data (expected 2 rows, got {$countWhileHidden})");

    // Verify Student Portal hides results across Dashboard, Results, and Transcript
    $sDashHidden = http_req($baseUrl . '/student/dashboard.php', $studentCookie);
    $sResHidden = http_req($baseUrl . '/student/results.php', $studentCookie);
    $sTrHidden = http_req($baseUrl . '/student/semester-transcript.php?semester=all', $studentCookie);

    check_assert(strpos($sDashHidden['body'], 'Hidden') !== false && strpos($sDashHidden['body'], '3.50') === false, "Student Dashboard must hide CGPA when results are hidden");
    check_assert(strpos($sResHidden['body'], 'Academic Results Temporarily Hidden') !== false && strpos($sResHidden['body'], 'CSE 101') === false, "Student Results page must hide subjects & show hidden notice");
    check_assert(strpos($sTrHidden['body'], 'Academic Results Temporarily Hidden') !== false && strpos($sTrHidden['body'], 'CSE 101') === false, "Student Transcript must hide subjects when hidden");
    echo "   [PASS] Hide Result conceals all Student Portal results while keeping DB rows 100% intact.\n";

    // Admin can still preview hidden transcript cleanly
    $adminTrPreview = http_req($baseUrl . '/student/semester-transcript.php?student_id=' . $createdStudentPk . '&semester=all', $adminCookie);
    check_assert(strpos($adminTrPreview['body'], 'ADMIN PREVIEW') !== false && strpos($adminTrPreview['body'], 'CSE 101') !== false, "Admin transcript preview must work while hidden from student");

    // Show Results again via Results Manager
    $showRes = http_req($baseUrl . '/admin/results-manager.php?student_id=' . $createdStudentPk, $adminCookie, [
        'csrf_token' => $detailCsrf,
        'action'     => 'show_results',
        'student_id' => $createdStudentPk,
    ]);
    check_assert($showRes['code'] === 200, "Show result POST must return 200");

    $sResRestored = http_req($baseUrl . '/student/results.php', $studentCookie);
    check_assert(strpos($sResRestored['body'], 'CSE 101') !== false && strpos($sResRestored['body'], '3.50') !== false, "Show Result must immediately restore Student Portal visibility");
    echo "   [PASS] Show Result immediately restored Student Portal grade sheet & transcript.\n";

    // Test Unpublish Result -> verify Draft status without deleting data -> then Republish
    $unpubRes = http_req($detailUrl, $adminCookie, [
        'csrf_token' => $detailCsrf,
        'action'     => 'unpublish_all_results',
    ]);
    check_assert($unpubRes['code'] === 200, "Unpublish POST must return 200");
    $countAfterUnpub = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$createdStudentPk} AND is_published = 0")->fetchColumn();
    check_assert($countAfterUnpub === 2, "Unpublish must convert 2 results to Draft without deleting rows");

    $sResUnpub = http_req($baseUrl . '/student/results.php', $studentCookie);
    check_assert(strpos($sResUnpub['body'], 'No academic results published yet') !== false && strpos($sResUnpub['body'], 'CSE 101') === false, "Unpublished results must not be visible to student");

    // Republish for account control steps
    http_req($detailUrl, $adminCookie, ['csrf_token' => $detailCsrf, 'action' => 'publish_all_results']);
    echo "   [PASS] Unpublish & Republish verified cleanly with zero data loss.\n\n";

    // Verify Student Data Isolation: active student cannot inspect another student's transcript
    $otherTrAttempt = http_req($baseUrl . '/student/semester-transcript.php?student_id=' . $seedId . '&semester=all', $studentCookie);
    check_assert(strpos($otherTrAttempt['body'], $testStudentCode) !== false && strpos($otherTrAttempt['body'], 'RMU2601001') === false, "Student must only see their own transcript even if ?student_id= is supplied");

    // -------------------------------------------------------------------------
    // STEP 7: Deactivate Account -> Immediate Session Invalidation -> Reactivate
    // -------------------------------------------------------------------------
    echo "7. Testing Account Deactivation, Immediate Session Invalidation & Reactivation:\n";
    $deactRes = http_req($detailUrl, $adminCookie, [
        'csrf_token' => $detailCsrf,
        'action'     => 'deactivate_account',
        'reason'     => 'E2E administrative suspension test',
    ]);
    check_assert($deactRes['code'] === 200 && strpos($deactRes['body'], 'Account: Deactivated') !== false, "Profile must display Deactivated badge");

    // Verify directory filter status=Deactivated shows the deactivated student
    $dirDeact = http_req($baseUrl . '/admin/students.php?status=Deactivated&search=' . urlencode($testStudentCode), $adminCookie);
    check_assert(strpos($dirDeact['body'], $testStudentCode) !== false && strpos($dirDeact['body'], 'Deactivated') !== false, "Directory Deactivated filter must include deactivated student");

    // Verify existing logged-in student cookie is IMMEDIATELY invalidated
    $kickedRes = http_req($baseUrl . '/student/results.php', $studentCookie);
    check_assert(strpos($kickedRes['url'], '/student/login.php') !== false, "Active session of deactivated student must be immediately invalidated and redirected to login");
    check_assert(strpos($kickedRes['body'], 'deactivated') !== false, "Login screen must display deactivation notice to kicked session");
    echo "   [PASS] Pre-existing active Student Portal session immediately invalidated upon deactivation!\n";

    // Verify fresh login attempts (with Student ID and Username) are rejected
    $freshTry = http_req($baseUrl . '/student/login.php', $studentCookie, [
        'csrf_token' => extract_csrf($kickedRes['body']),
        'student_id' => $testStudentCode,
        'password'   => $testPassword,
    ]);
    check_assert(strpos($freshTry['url'], '/student/login.php') !== false && strpos($freshTry['body'], 'deactivated') !== false, "Deactivated student must NOT be able to log in with old ID/password");

    // Verify academic records are 100% preserved in DB while deactivated
    $rowsWhileDeact = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$createdStudentPk}")->fetchColumn();
    check_assert($rowsWhileDeact === 2, "Deactivation must preserve all academic records");
    echo "   [PASS] Fresh login blocked & all 2 academic records preserved.\n";

    // Reactivate Account
    $reactRes = http_req($detailUrl, $adminCookie, [
        'csrf_token' => $detailCsrf,
        'action'     => 'activate_account',
        'reason'     => 'E2E clearance approved',
    ]);
    check_assert($reactRes['code'] === 200 && strpos($reactRes['body'], 'Account: Active') !== false, "Reactivated account must show Active status badge");

    // Old invalidated session cookie must remain permanently invalid even after reactivation
    $staleCookieCheck = http_req($baseUrl . '/student/dashboard.php', $studentCookie);
    check_assert(strpos($staleCookieCheck['url'], '/student/login.php') !== false, "Old pre-deactivation cookie must NOT regain access after reactivation");

    // Student can log in again with fresh credentials and view preserved results
    $reloginPage = http_req($baseUrl . '/student/login.php', $studentCookie2);
    $reloginPost = http_req($baseUrl . '/student/login.php', $studentCookie2, [
        'csrf_token' => extract_csrf($reloginPage['body']),
        'student_id' => $testStudentCode,
        'password'   => $testPassword,
    ]);
    check_assert(strpos($reloginPost['url'], '/student/dashboard.php') !== false, "Reactivated student must be able to log in again");
    $reloginResults = http_req($baseUrl . '/student/results.php', $studentCookie2);
    check_assert(strpos($reloginResults['body'], 'CSE 101') !== false && strpos($reloginResults['body'], '3.50') !== false, "Reactivated student must retain full published academic records");
    echo "   [PASS] Account reactivated cleanly & student regained access to preserved results.\n\n";

    // -------------------------------------------------------------------------
    // STEP 8: Permanent Account Deletion with Double Confirmation & Isolation
    // -------------------------------------------------------------------------
    echo "8. Testing Permanent Account Deletion & Confirmation Rules:\n";
    // 8a: Missing confirmation must fail
    $badDel1 = http_req($detailUrl, $adminCookie, [
        'csrf_token'         => $detailCsrf,
        'action'             => 'delete_student_account',
        'reason'             => 'Attempt without confirmation',
        'confirm_student_id' => 'WRONG-ID',
    ]);
    $statusAfterBadDel = (string)$db->query("SELECT status FROM students WHERE id = {$createdStudentPk}")->fetchColumn();
    check_assert($statusAfterBadDel === 'Active', "Deletion without proper checkbox & matching Student ID must be rejected");
    echo "   [PASS] Missing extra confirmation rejected safely.\n";

    // 8b: Valid confirmation deletes account safely
    $goodDel = http_req($detailUrl, $adminCookie, [
        'csrf_token'               => $detailCsrf,
        'action'                   => 'delete_student_account',
        'reason'                   => 'E2E permanent withdrawal retention test',
        'confirm_student_id'       => $testStudentCode,
        'confirm_permanent_delete' => '1',
    ]);
    check_assert($goodDel['code'] === 200 && strpos($goodDel['body'], 'Account: Deleted') !== false, "Deleted account page must display Deleted status badge");

    // Active student cookie2 must immediately be invalidated
    $deletedKicked = http_req($baseUrl . '/student/dashboard.php', $studentCookie2);
    check_assert(strpos($deletedKicked['url'], '/student/login.php') !== false, "Active session of deleted account must be immediately invalidated");

    // Attempt login with old Student ID, Username, and Email + old Password
    foreach ([$testStudentCode, $testUsername, $testEmail] as $ident) {
        $tryDeleted = http_req($baseUrl . '/student/login.php', $studentCookie2, [
            'csrf_token' => extract_csrf($deletedKicked['body']),
            'student_id' => $ident,
            'password'   => $testPassword,
        ]);
        check_assert(strpos($tryDeleted['url'], '/student/login.php') !== false && strpos($tryDeleted['body'], 'permanently deleted') !== false, "Deleted student must never log in using identifier: {$ident}");
    }

    // Verify archive created and password hash revoked in DB
    $delRow = $db->query("SELECT status, password_hash, results_visible FROM students WHERE id = {$createdStudentPk}")->fetch(PDO::FETCH_ASSOC);
    check_assert($delRow['status'] === 'Deleted' && !password_verify($testPassword, $delRow['password_hash']), "Deleted row must have status=Deleted & revoked password_hash");
    $archRow = $db->query("SELECT * FROM student_retention_archives WHERE student_pk_id = {$createdStudentPk} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    check_assert(!empty($archRow) && (int)$archRow['results_count'] === 2 && strpos($archRow['snapshot_json'], 'CSE 101') !== false, "Retention archive must contain complete JSON snapshot with 2 retained subjects");

    // Verify seed student RMU2601001 (#4) was NEVER affected
    $seedAfter = $db->query("SELECT status, results_visible FROM students WHERE id = {$seedId}")->fetch(PDO::FETCH_ASSOC);
    $seedResAfter = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$seedId}")->fetchColumn();
    check_assert($seedAfter['status'] === $seedBefore['status'] && (int)$seedAfter['results_visible'] === (int)$seedBefore['results_visible'] && $seedResAfter === $seedResBefore, "Other students' records must remain 100% untouched");

    // Verify Deleted filter in directory & admin dashboard health
    $dirDeleted = http_req($baseUrl . '/admin/students.php?status=Deleted&search=' . urlencode($testStudentCode), $adminCookie);
    check_assert($dirDeleted['code'] === 200 && strpos($dirDeleted['body'], $testStudentCode) !== false && strpos($dirDeleted['body'], 'Deleted') !== false, "Directory Deleted filter must show Deleted badge cleanly");

    // Verify admin dashboard & reports render cleanly excluding deleted account
    $dashAfterDel = http_req($baseUrl . '/admin/dashboard.php', $adminCookie);
    check_assert($dashAfterDel['code'] === 200 && strpos($dashAfterDel['body'], 'admin-mobile-toggle') !== false, "admin/dashboard.php must render 200 with responsive toggle");
    $repAfterDel = http_req($baseUrl . '/admin/reports.php', $adminCookie);
    check_assert($repAfterDel['code'] === 200, "admin/reports.php must render 200 cleanly");
    echo "   [PASS] Credentials revoked, retention archive created, active session kicked, & other students untouched.\n\n";

    // -------------------------------------------------------------------------
    // STEP 9: Audit Trail & Admin Activity Log Verification
    // -------------------------------------------------------------------------
    echo "9. Verifying Permanent Admin Activity Log & Timestamp Audit Trail:\n";
    $expectedAuditActions = [
        'STUDENT_RESULT_ADDED',
        'STUDENT_RESULT_EDITED',
        'RESULTS_PUBLISHED',
        'RESULTS_UNPUBLISHED',
        'RESULTS_HIDDEN',
        'RESULTS_SHOWN',
        'STUDENT_ACCOUNT_DEACTIVATED',
        'STUDENT_ACCOUNT_REACTIVATED',
        'STUDENT_ACCOUNT_DELETED',
    ];
    foreach ($expectedAuditActions as $act) {
        $aStmt = $db->prepare("SELECT user_id, created_at FROM audit_logs WHERE action = ? AND (details LIKE ? OR details LIKE ?) ORDER BY id DESC LIMIT 1");
        $aStmt->execute([$act, "%{$testStudentCode}%", "%#{$createdStudentPk}%"]);
        $aRow = $aStmt->fetch(PDO::FETCH_ASSOC);
        check_assert(!empty($aRow) && (int)$aRow['user_id'] > 0 && !empty($aRow['created_at']), "Audit log entry missing for action: {$act}");
    }

    $auditTabPage = http_req($detailUrl . '&tab=auditTab', $adminCookie);
    check_assert($auditTabPage['code'] === 200 && strpos($auditTabPage['body'], 'STUDENT_ACCOUNT_DELETED') !== false && strpos($auditTabPage['body'], 'STUDENT_ACCOUNT_DEACTIVATED') !== false, "student-detail.php Admin Activity Log tab must render all recorded actions");
    echo "   [PASS] All 9 administrative actions audited with Admin ID & timestamp and displayed in UI!\n\n";

    echo "===========================================================================\n";
    echo ">>> ALL STUDENT ACCOUNT & RESULT CONTROL E2E TESTS PASSED (100% PASS)! <<<\n";
    echo "===========================================================================\n";
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] " . $e->getMessage() . "\n");
    $exitCode = 1;
} finally {
    // Cleanup temporary test data
    if ($createdStudentPk > 0) {
        $db->prepare("DELETE FROM student_retention_archives WHERE student_pk_id = ?")->execute([$createdStudentPk]);
        $db->prepare("DELETE FROM result_modification_history WHERE student_id = ?")->execute([$createdStudentPk]);
        $db->prepare("DELETE FROM student_notifications WHERE student_id = ?")->execute([$createdStudentPk]);
        $db->prepare("DELETE FROM student_results WHERE student_id = ?")->execute([$createdStudentPk]);
        $db->prepare("DELETE FROM audit_logs WHERE details LIKE ? OR details LIKE ? OR details LIKE ? OR details LIKE ?")->execute([
            "%{$testStudentCode}%",
            "%#{$createdStudentPk} %",
            "%#{$createdStudentPk})%",
            "%#{$createdStudentPk}.%"
        ]);
        $db->prepare("DELETE FROM students WHERE id = ?")->execute([$createdStudentPk]);
    }
    @unlink($adminCookie);
    @unlink($studentCookie);
    @unlink($studentCookie2);
    @unlink($anonCookie);
}
exit($exitCode);
