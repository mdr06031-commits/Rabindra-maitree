<?php
/**
 * Comprehensive End-to-End Test Suite:
 * Rabindra Maitri University - Automatic Student Result Management System
 *
 * Tests over real HTTP (http://127.0.0.1:8000) & MySQL (rmu_db):
 * 1. Online Admission Intake (Name, App ID, Roll, Mobile, Dept, Degree Type, Program, Passing Year)
 * 2. Admin Application Management & Pending -> Reject/Accept Acceptance Gate
 * 3. Automatic & Configurable Semester Structures (Bachelor 8 sem, B.Ed 2->3 sem, Master's 2/3/4 sem)
 * 4. Simple Admin CGPA Input (CGPA, Session, Publication Date) -> Credit-Weighted Auto Distribution
 * 5. Edit Individual Semester GPA & Administrator Official Verification Workflow
 * 6. Live Student Portal Sync (Dashboard, Profile, Results, Transcript) + Mandatory Disclaimers
 * 7. Result Access Suspension (keeps login & profile active) & Safe Result Deletion (keeps account)
 * 8. Role-Based Authorization, Cross-Student IDOR Prevention, Seed Integrity & Full Cleanup
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$baseUrl = 'http://127.0.0.1:8000';
$db = getDb();

$adminCookie   = sys_get_temp_dir() . '/rmu_auto_admin_' . uniqid() . '.txt';
$editorCookie  = sys_get_temp_dir() . '/rmu_auto_editor_' . uniqid() . '.txt';
$stud1Cookie   = sys_get_temp_dir() . '/rmu_auto_stud1_' . uniqid() . '.txt';
$stud2Cookie   = sys_get_temp_dir() . '/rmu_auto_stud2_' . uniqid() . '.txt';
$anonCookie    = sys_get_temp_dir() . '/rmu_auto_anon_' . uniqid() . '.txt';

function e2e_http(string $url, string $cookieFile, ?array $postData = null, bool $followLocation = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $followLocation,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_TIMEOUT        => 25,
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

function e2e_csrf(string $html): string {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function e2e_assert(bool $cond, string $msg): void {
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

$exitCode = 0;
$createdAdmissionIds = [];
$createdStudentPks = [];
$tempEditorUserId = 0;
$suffix = (string)rand(50000, 99999);
$bedOriginalRow = null;
$seedBefore = null;
$seedResBefore = 0;
$gatedTempStudentPk = 0;
$gatedTempAdmId = 0;
ensure_admission_id_generation_schema($db);
$seqSnapshotArm = $db->query("SELECT seq_year, last_app_seq, last_roll_seq FROM admission_sequences")->fetchAll(PDO::FETCH_ASSOC);
$maxResIdBeforeArm = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM admission_id_reservations")->fetchColumn();

try {
    echo "===============================================================================\n";
    echo "  RMU AUTOMATIC STUDENT RESULT MANAGEMENT SYSTEM - COMPREHENSIVE E2E TEST SUITE\n";
    echo "===============================================================================\n\n";

    // Snapshot baseline seed student & B.Ed program structure
    $seedBefore = $db->query("SELECT id, student_id, cgpa, status, results_visible, result_access_status FROM students WHERE student_id = 'RMU2601001' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    e2e_assert(!empty($seedBefore), "Seed student RMU2601001 must exist");
    $seedId = (int)$seedBefore['id'];
    $seedResBefore = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$seedId}")->fetchColumn();

    $bedOriginalRow = $db->query("SELECT * FROM programs WHERE slug = 'b-ed' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    e2e_assert(!empty($bedOriginalRow), "B.Ed program must exist in programs table");

    $cseProg = $db->query("SELECT id, department_id, title_en FROM programs WHERE slug = 'bsc-in-cse' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $bedProg = $bedOriginalRow;
    $mbaProg = $db->query("SELECT id, department_id, title_en FROM programs WHERE slug = 'mba' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    e2e_assert(!empty($cseProg) && !empty($mbaProg), "CSE and MBA programs must exist");

    // -------------------------------------------------------------------------
    // STEP 1: Online Admission Submissions (Bachelor, B.Ed, Master's)
    // -------------------------------------------------------------------------
    echo "1. Testing Online Application Submissions (Bachelor / Honours, B.Ed, Master's):\n";
    $formPage = e2e_http($baseUrl . '/admission-form.php', $anonCookie);
    e2e_assert($formPage['code'] === 200, "admission-form.php must return HTTP 200");
    foreach (['Bachelor / Honours', 'B.Ed', "Master's", 'name="application_id"', 'name="roll_number"', 'name="passing_year"', 'name="degree_type"'] as $fChk) {
        e2e_assert(strpos($formPage['body'], $fChk) !== false, "admission-form.php missing required control: {$fChk}");
    }

    $applicantsSpec = [
        'bach' => [
            'full_name'          => "E2E Bachelor Applicant {$suffix}",
            'application_id'     => "APP-BACH-{$suffix}",
            'roll_number'        => "ROLL-BACH-{$suffix}",
            'mobile'             => '017' . rand(10000000, 99999999),
            'email'              => "bach.{$suffix}@rmu.test",
            'degree_type'        => 'Bachelor / Honours',
            'department_id'      => (int)$cseProg['department_id'],
            'program_id'         => (int)$cseProg['id'],
            'interested_subject' => $cseProg['title_en'],
            'passing_year'       => '2025',
            'academic_session'   => 'Fall 2026',
        ],
        'bed' => [
            'full_name'          => "E2E BEd Applicant {$suffix}",
            'application_id'     => "APP-BED-{$suffix}",
            'roll_number'        => "ROLL-BED-{$suffix}",
            'mobile'             => '018' . rand(10000000, 99999999),
            'email'              => "bed.{$suffix}@rmu.test",
            'degree_type'        => 'B.Ed',
            'department_id'      => (int)$bedProg['department_id'],
            'program_id'         => (int)$bedProg['id'],
            'interested_subject' => $bedProg['title_en'],
            'passing_year'       => '2024',
            'academic_session'   => 'Fall 2026',
        ],
        'mast' => [
            'full_name'          => "E2E Masters Applicant {$suffix}",
            'application_id'     => "APP-MAST-{$suffix}",
            'roll_number'        => "ROLL-MAST-{$suffix}",
            'mobile'             => '019' . rand(10000000, 99999999),
            'email'              => "mast.{$suffix}@rmu.test",
            'degree_type'        => "Master's",
            'department_id'      => (int)$mbaProg['department_id'],
            'program_id'         => (int)$mbaProg['id'],
            'interested_subject' => $mbaProg['title_en'],
            'passing_year'       => '2023',
            'academic_session'   => 'Fall 2026',
        ],
    ];

    $admRows = [];
    foreach ($applicantsSpec as $key => $spec) {
        $freshForm = e2e_http($baseUrl . '/admission-form.php', $anonCookie);
        $csrf = e2e_csrf($freshForm['body']);
        preg_match('/name="draft_token"[^>]*value="([^"]*)"/', $freshForm['body'], $dtMatchArm);
        $subRes = e2e_http($baseUrl . '/actions/submit-admission.php', $anonCookie, [
            'csrf_token'         => $csrf,
            'draft_token'        => $dtMatchArm[1] ?? '',
            'full_name'          => $spec['full_name'],
            'application_id'     => $spec['application_id'],
            'roll_number'        => $spec['roll_number'],
            'father_name'        => 'Md. Father Test',
            'mother_name'        => 'Mrs. Mother Test',
            'date_of_birth'      => '2003-04-12',
            'gender'             => 'Male',
            'mobile'             => $spec['mobile'],
            'email'              => $spec['email'],
            'present_address'    => 'Kushtia Sadar, Kushtia',
            'permanent_address'  => 'Kushtia Sadar, Kushtia',
            'last_college'       => 'Kushtia Govt College',
            'home_district'      => 'Kushtia',
            'ssc_exam'           => 'S.S.C',
            'ssc_roll'           => '112233',
            'ssc_group'          => 'Science',
            'ssc_board'          => 'Jashore',
            'ssc_result_type'    => 'GPA(out of 5)',
            'ssc_gpa'            => '5.00',
            'ssc_year'           => '2020',
            'hsc_exam'           => 'H.S.C',
            'hsc_roll'           => '445566',
            'hsc_group'          => 'Science',
            'hsc_board'          => 'Jashore',
            'hsc_result_type'    => 'GPA(out of 5)',
            'hsc_gpa'            => '4.90',
            'hsc_year'           => '2022',
            'degree_type'        => $spec['degree_type'],
            'department_id'      => $spec['department_id'],
            'academic_session'   => $spec['academic_session'],
            'passing_year'       => $spec['passing_year'],
            'program_id'         => $spec['program_id'],
            'interested_subject' => $spec['interested_subject'],
            'quota'              => 'NO',
        ]);
        e2e_assert($subRes['code'] === 200, "Admission submission for {$key} must return 200");

        $rowStmt = $db->prepare("SELECT * FROM admissions WHERE mobile = ? ORDER BY id DESC LIMIT 1");
        $rowStmt->execute([$spec['mobile']]);
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
        e2e_assert(!empty($row), "Submitted application for {$key} must exist in admissions table");
        e2e_assert($row['status'] === 'Pending', "New online application status must be Pending, got {$row['status']}");
        e2e_assert(preg_match('/^RMU-\d{4}-\d{4,}$/', $row['application_ref']) === 1 && $row['application_id'] === $row['application_ref'], "Application ID must be backend-generated RMU-YYYY-XXXX (got {$row['application_ref']})");
        e2e_assert(preg_match('/^\d{4}-\d{4,}$/', $row['roll_number']) === 1 && $row['exam_roll'] === $row['roll_number'], "Roll number must be backend-generated YYYY-XXXX (got {$row['roll_number']})");
        e2e_assert($row['application_id'] !== $spec['application_id'] && $row['roll_number'] !== $spec['roll_number'], "Client-supplied spoofed ID/Roll must be ignored for {$key}");
        e2e_assert($row['degree_type'] === $spec['degree_type'], "Degree type mismatch for {$key}");
        e2e_assert((string)$row['passing_year'] === $spec['passing_year'], "Passing year mismatch for {$key}");

        $applicantsSpec[$key]['application_id'] = $row['application_ref'];
        $applicantsSpec[$key]['roll_number']    = $row['roll_number'];
        $admRows[$key] = $row;
        $createdAdmissionIds[] = (int)$row['id'];
    }
    echo "   [PASS] Submitted 3 online applications (Bachelor / Honours, B.Ed, Master's) cleanly into Pending state.\n\n";

    // -------------------------------------------------------------------------
    // STEP 2: Admin Login, Pending Queue, Reject & Acceptance Gate Verification
    // -------------------------------------------------------------------------
    echo "2. Testing Pending Applications Queue, Reject/Accept & Acceptance Gate:\n";
    $adminLoginGet = e2e_http($baseUrl . '/admin/login.php', $adminCookie);
    $adminCsrf = e2e_csrf($adminLoginGet['body']);
    $adminLoginPost = e2e_http($baseUrl . '/admin/login.php', $adminCookie, [
        'csrf_token' => $adminCsrf,
        'username'   => 'admin',
        'password'   => 'Admin@RMU2026!',
    ]);
    e2e_assert($adminLoginPost['code'] === 200 && strpos($adminLoginPost['url'], '/admin/dashboard.php') !== false, "Admin login must succeed");

    // Verify Pending Queue displays our 3 applications and NO Result Management link for Pending ones
    $pendingQueueHtml = e2e_http($baseUrl . '/admin/admissions.php?status=PendingQueue&search=' . urlencode($suffix), $adminCookie);
    e2e_assert($pendingQueueHtml['code'] === 200, "Pending queue page must return 200");
    foreach ($applicantsSpec as $spec) {
        e2e_assert(strpos($pendingQueueHtml['body'], $spec['application_id']) !== false, "Pending queue missing App ID {$spec['application_id']}");
        e2e_assert(strpos($pendingQueueHtml['body'], $spec['roll_number']) !== false, "Pending queue missing Roll {$spec['roll_number']}");
    }
    e2e_assert(preg_match('/<tbody>(.*?)<\/tbody>/s', $pendingQueueHtml['body'], $tbMatch) === 1, "Pending queue must render table tbody");
    $pendingTbody = $tbMatch[1];
    e2e_assert(strpos($pendingTbody, 'student-detail.php') === false && strpos($pendingTbody, 'cgpaGeneratorPanel') === false && strpos($pendingTbody, 'Open Result Management') === false, "Pending unaccepted applications must NOT expose Result Management portal links");

    // Verify Acceptance Gate blocks Result Management & CGPA generation on a Rejected/Pending application
    $admCsrf = e2e_csrf($pendingQueueHtml['body']);
    $rejectRes = e2e_http($baseUrl . '/admin/admissions.php', $adminCookie, [
        'csrf_token'  => $admCsrf,
        'action'      => 'reject_application',
        'id'          => (int)$admRows['bed']['id'],
        'admin_notes' => 'E2E Gate Test Reject',
    ]);
    e2e_assert($rejectRes['code'] === 200, "Reject POST must return 200");
    $rejStat = (string)$db->query("SELECT status FROM admissions WHERE id = " . (int)$admRows['bed']['id'])->fetchColumn();
    e2e_assert($rejStat === 'Rejected', "B.Ed admission status must become Rejected");

    // Create a linked temporary student pointing to a Pending application to verify HTTP Result Gate Enforcement
    $gateRef = "RMU-GATE-{$suffix}";
    $db->prepare("INSERT INTO admissions (application_ref, application_id, full_name, mobile, email, last_college, ssc_gpa, hsc_gpa, home_district, hsc_group, interested_subject, degree_type, roll_number, quota, status, payment_status) VALUES (?, ?, 'Gate Blocked Applicant', '01700011223', ?, 'Test College', 4.50, 4.50, 'Kushtia', 'Science', ?, 'Bachelor / Honours', ?, 'NO', 'Pending', 'Pending')")
       ->execute([$gateRef, $gateRef, "gate.{$suffix}@rmu.test", $cseProg['title_en'], "ROLL-GATE-{$suffix}"]);
    $gatedTempAdmId = (int)$db->lastInsertId();
    $gateStCode = "RMU26GATE{$suffix}";
    $db->prepare("INSERT INTO students (student_id, roll_number, application_number, username, full_name, email, phone, password_hash, program_id, degree_type, department_id, status) VALUES (?, ?, ?, ?, 'Gate Blocked Applicant', ?, '01700011223', 'hash', ?, 'Bachelor / Honours', ?, 'Active')")
       ->execute([$gateStCode, "ROLL-GATE-{$suffix}", $gateRef, $gateStCode, "gate.{$suffix}@rmu.test", (int)$cseProg['id'], (int)$cseProg['department_id']]);
    $gatedTempStudentPk = (int)$db->lastInsertId();

    $gateCheck = can_manage_student_results($gatedTempStudentPk);
    e2e_assert(empty($gateCheck['allowed']), "can_manage_student_results() must return allowed=false for Pending application");

    $rmGateGet = e2e_http($baseUrl . '/admin/results-manager.php?student_id=' . $gatedTempStudentPk, $adminCookie);
    e2e_assert(strpos($rmGateGet['body'], 'Result Management Locked') !== false, "Results Manager UI must show Lock notice for unaccepted application");
    $rmGatePost = e2e_http($baseUrl . '/admin/results-manager.php?student_id=' . $gatedTempStudentPk, $adminCookie, [
        'csrf_token'       => e2e_csrf($rmGateGet['body']),
        'action'           => 'generate_cgpa_results',
        'student_id'       => $gatedTempStudentPk,
        'final_cgpa'       => '3.50',
        'academic_session' => 'Fall 2026',
        'publication_date' => '2026-11-10',
    ]);
    $gatedCountAfter = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$gatedTempStudentPk}")->fetchColumn();
    e2e_assert($gatedCountAfter === 0, "Acceptance Gate must prevent any DB result insertion for unaccepted student");
    echo "   [PASS] Acceptance Gate blocks unaccepted/rejected students cleanly across backend & HTTP!\n";

    // Now Accept all 3 applicants cleanly via admin/admissions.php
    $provisioned = [];
    foreach (['bach', 'bed', 'mast'] as $key) {
        $admId = (int)$admRows[$key]['id'];
        $accRes = e2e_http($baseUrl . '/admin/admissions.php', $adminCookie, [
            'csrf_token'  => $admCsrf,
            'action'      => 'accept_application',
            'id'          => $admId,
            'admin_notes' => 'Accepted for E2E Test',
        ]);
        e2e_assert($accRes['code'] === 200, "Accept POST for {$key} must return 200");

        $aRow = $db->query("SELECT * FROM admissions WHERE id = {$admId}")->fetch(PDO::FETCH_ASSOC);
        e2e_assert($aRow['status'] === 'Accepted' && !empty($aRow['student_id']) && !empty($aRow['portal_password']), "Accepted application must provision Student ID & password");

        $sStmt = $db->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
        $sStmt->execute([$aRow['student_id']]);
        $sRow = $sStmt->fetch(PDO::FETCH_ASSOC);
        e2e_assert(!empty($sRow), "Provisioned student row must exist for {$key}");
        e2e_assert($sRow['roll_number'] === $applicantsSpec[$key]['roll_number'], "Roll number did not transfer to student record for {$key}");
        e2e_assert($sRow['application_number'] === $applicantsSpec[$key]['application_id'], "Application ID did not transfer to student record for {$key}");
        e2e_assert((string)$sRow['passing_year'] === $applicantsSpec[$key]['passing_year'], "Passing year did not transfer to student record for {$key}");

        $provisioned[$key] = ['adm' => $aRow, 'student' => $sRow];
        $createdStudentPks[] = (int)$sRow['id'];
    }

    // Verify Result Management button now appears on Accepted application in admin/admissions.php
    $accListPage = e2e_http($baseUrl . '/admin/admissions.php?status=Accepted&search=' . urlencode($applicantsSpec['bach']['application_id']), $adminCookie);
    e2e_assert(preg_match('/<tbody>(.*?)<\/tbody>/s', $accListPage['body'], $accTbMatch) === 1, "Accepted list must render table tbody");
    $accTbody = $accTbMatch[1];
    e2e_assert(strpos($accTbody, 'student-detail.php') !== false && strpos($accTbody, 'Open Result Management') !== false && strpos($accTbody, 'cgpaGeneratorPanel') !== false, "Accepted application row must render Result Management link");
    echo "   [PASS] All 3 applications accepted, accounts provisioned, and Result Management unlocked!\n\n";

    // -------------------------------------------------------------------------
    // STEP 3: Automatic & Configurable Semester Structures (8, 2->3, 2/3/4)
    // -------------------------------------------------------------------------
    echo "3. Testing Configurable Semester Structures (Bachelor 8, B.Ed 2->3, Master's 2/3/4):\n";
    e2e_assert((int)$provisioned['bach']['student']['total_semesters'] === 8, "Bachelor student default semesters must be 8");
    e2e_assert((int)$provisioned['bed']['student']['total_semesters'] === 2, "B.Ed student default semesters must be 2");
    e2e_assert((int)$provisioned['mast']['student']['total_semesters'] === 4, "MBA Master's student default semesters must be 4");

    // Verify initial student_semester_results skeleton rows synced
    $bedInitSems = (int)$db->query("SELECT COUNT(*) FROM student_semester_results WHERE student_id = " . (int)$provisioned['bed']['student']['id'])->fetchColumn();
    e2e_assert($bedInitSems === 2, "B.Ed initial semester skeleton count must be 2, got {$bedInitSems}");

    // Reconfigure B.Ed program via admin/semesters.php to 3 Semesters (1.5 Years), 54 Credits
    $semMgmtPage = e2e_http($baseUrl . '/admin/semesters.php', $adminCookie);
    e2e_assert($semMgmtPage['code'] === 200 && strpos($semMgmtPage['body'], 'Automatic Program Duration, Semesters') !== false, "admin/semesters.php must expose program structure configuration");
    $updBedProg = e2e_http($baseUrl . '/admin/semesters.php', $adminCookie, [
        'csrf_token'      => e2e_csrf($semMgmtPage['body']),
        'action'          => 'update_program_structure',
        'program_id'      => (int)$bedProg['id'],
        'total_semesters' => '3',
        'total_credits'   => '54',
    ]);
    e2e_assert($updBedProg['code'] === 200, "Updating B.Ed program structure must return 200");

    $bedAfterCfg = $db->query("SELECT total_semesters, program_duration, total_credits_required FROM students WHERE id = " . (int)$provisioned['bed']['student']['id'])->fetch(PDO::FETCH_ASSOC);
    e2e_assert((int)$bedAfterCfg['total_semesters'] === 3 && (int)$bedAfterCfg['total_credits_required'] === 54, "Reconfiguring B.Ed program must propagate 3 semesters / 54 credits to active B.Ed students");
    $bedUpdatedSems = (int)$db->query("SELECT COUNT(*) FROM student_semester_results WHERE student_id = " . (int)$provisioned['bed']['student']['id'])->fetchColumn();
    e2e_assert($bedUpdatedSems === 3, "B.Ed student_semester_results count must sync to 3, got {$bedUpdatedSems}");
    echo "   [PASS] Program structure configuration & active student sync (B.Ed 2 -> 3 semesters) verified!\n\n";

    // -------------------------------------------------------------------------
    // STEP 4: Simple Admin CGPA Generator (3.50) + Session + Publication Date
    // -------------------------------------------------------------------------
    echo "4. Testing Simple Admin CGPA Result Generator across Bachelor (8), B.Ed (3), Master's (2/3/4):\n";
    $bachPk = (int)$provisioned['bach']['student']['id'];
    $bedPk  = (int)$provisioned['bed']['student']['id'];
    $mastPk = (int)$provisioned['mast']['student']['id'];

    // 4a. Generate Bachelor 3.50 via admin/student-detail.php
    $sdBach = e2e_http($baseUrl . '/admin/student-detail.php?id=' . $bachPk, $adminCookie);
    e2e_assert($sdBach['code'] === 200, "student-detail.php must return 200 for Bachelor student");
    $genBachPost = e2e_http($baseUrl . '/admin/student-detail.php?id=' . $bachPk, $adminCookie, [
        'csrf_token'       => e2e_csrf($sdBach['body']),
        'action'           => 'generate_cgpa_results',
        'final_cgpa'       => '3.50',
        'academic_session' => 'Fall 2026 (Honours)',
        'publication_date' => '2026-11-10',
        'publish_mode'     => '1',
    ]);
    e2e_assert($genBachPost['code'] === 200, "Bachelor CGPA generator POST must return 200");

    $bachStAfter = $db->query("SELECT cgpa, academic_session, result_publication_date, result_status FROM students WHERE id = {$bachPk}")->fetch(PDO::FETCH_ASSOC);
    e2e_assert(abs((float)$bachStAfter['cgpa'] - 3.50) <= 0.01, "Bachelor generated CGPA expected 3.50, got {$bachStAfter['cgpa']}");
    e2e_assert($bachStAfter['academic_session'] === 'Fall 2026 (Honours)', "Academic session must be saved");
    e2e_assert($bachStAfter['result_publication_date'] === '2026-11-10', "Publication date must be saved");

    $bachSemCount = (int)$db->query("SELECT COUNT(DISTINCT semester) FROM student_results WHERE student_id = {$bachPk}")->fetchColumn();
    $bachEstFlags = $db->query("SELECT MIN(is_estimated) AS min_est, MAX(is_estimated) AS max_est, MIN(grade_point) AS min_gp, MAX(grade_point) AS max_gp FROM student_results WHERE student_id = {$bachPk}")->fetch(PDO::FETCH_ASSOC);
    e2e_assert($bachSemCount === 8, "Bachelor must generate 8 distinct semesters, got {$bachSemCount}");
    e2e_assert((int)$bachEstFlags['min_est'] === 1 && (int)$bachEstFlags['max_est'] === 1, "All auto-generated results must have is_estimated = 1");
    e2e_assert((float)$bachEstFlags['min_gp'] >= 2.00 && (float)$bachEstFlags['max_gp'] <= 4.00, "All grade points must be strictly within valid 2.00-4.00 scale");

    // 4b. Generate B.Ed (3 Semesters) 3.65 via admin/results-manager.php
    $rmBed = e2e_http($baseUrl . '/admin/results-manager.php?student_id=' . $bedPk, $adminCookie);
    e2e_assert($rmBed['code'] === 200, "results-manager.php must return 200 for B.Ed student");
    $genBedPost = e2e_http($baseUrl . '/admin/results-manager.php?student_id=' . $bedPk, $adminCookie, [
        'csrf_token'       => e2e_csrf($rmBed['body']),
        'action'           => 'generate_cgpa_results',
        'student_id'       => $bedPk,
        'final_cgpa'       => '3.65',
        'academic_session' => 'B.Ed Session 2026',
        'publication_date' => '2026-11-05',
        'total_semesters'  => '3',
        'publish_mode'     => 'published',
    ]);
    e2e_assert($genBedPost['code'] === 200, "B.Ed CGPA generator POST must return 200");
    $bedStAfter = $db->query("SELECT cgpa FROM students WHERE id = {$bedPk}")->fetchColumn();
    $bedResSemCount = (int)$db->query("SELECT COUNT(DISTINCT semester) FROM student_results WHERE student_id = {$bedPk}")->fetchColumn();
    e2e_assert($bedResSemCount === 3, "B.Ed must generate exactly 3 semesters, got {$bedResSemCount}");
    e2e_assert(abs((float)$bedStAfter - 3.65) <= 0.01, "B.Ed CGPA expected 3.65, got {$bedStAfter}");

    // 4c. Master's: Test 2 Semesters -> 3 Semesters -> 4 Semesters without duplicate rows
    foreach ([2 => 3.40, 3 => 3.55, 4 => 3.75] as $mSems => $mCgpa) {
        $rmMast = e2e_http($baseUrl . '/admin/results-manager.php?student_id=' . $mastPk, $adminCookie);
        $genMastPost = e2e_http($baseUrl . '/admin/results-manager.php?student_id=' . $mastPk, $adminCookie, [
            'csrf_token'       => e2e_csrf($rmMast['body']),
            'action'           => 'generate_cgpa_results',
            'student_id'       => $mastPk,
            'final_cgpa'       => number_format($mCgpa, 2, '.', ''),
            'academic_session' => 'MBA 2026',
            'publication_date' => '2026-11-08',
            'total_semesters'  => (string)$mSems,
            'publish_mode'     => 'published',
        ]);
        e2e_assert($genMastPost['code'] === 200, "Master's {$mSems}-semester generation must return 200");
        $actualMSems = (int)$db->query("SELECT COUNT(DISTINCT semester) FROM student_results WHERE student_id = {$mastPk}")->fetchColumn();
        $actualMCgpa = (float)$db->query("SELECT cgpa FROM students WHERE id = {$mastPk}")->fetchColumn();
        e2e_assert($actualMSems === $mSems, "Master's configured for {$mSems} semesters must produce {$mSems} distinct semesters, got {$actualMSems}");
        e2e_assert(abs($actualMCgpa - $mCgpa) <= 0.01, "Master's {$mSems}-semester CGPA expected {$mCgpa}, got {$actualMCgpa}");
    }
    echo "   [PASS] Verified exact CGPA distribution for Bachelor (8), B.Ed (3), and Master's (2/3/4 semesters)!\n\n";

    // -------------------------------------------------------------------------
    // STEP 5: Update Individual Semester GPA & Student Portal Mandatory Disclaimers
    // -------------------------------------------------------------------------
    echo "5. Testing Semester GPA Adjustment, Mandatory Disclaimers & Live Student Portal Sync:\n";
    $rmBachPage = e2e_http($baseUrl . '/admin/results-manager.php?student_id=' . $bachPk, $adminCookie);
    e2e_assert(strpos($rmBachPage['body'], 'ESTIMATED / GENERATED') !== false, "Admin results manager must label estimated results clearly");
    e2e_assert(strpos($rmBachPage['body'], 'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT') !== false, "Admin results manager must display full disclaimer");

    $updSem1 = e2e_http($baseUrl . '/admin/results-manager.php?student_id=' . $bachPk, $adminCookie, [
        'csrf_token'      => e2e_csrf($rmBachPage['body']),
        'action'          => 'update_semester_gpa',
        'student_id'      => $bachPk,
        'semester_number' => '1',
        'semester_gpa'    => '3.90',
    ]);
    e2e_assert($updSem1['code'] === 200, "Update semester GPA must return 200");
    $bachSummaryAfterSemEdit = get_student_academic_summary($bachPk, true);
    e2e_assert(abs((float)$bachSummaryAfterSemEdit['semester_gpas'][1] - 3.90) <= 0.08, "Semester 1 SGPA should be adjusted near 3.90, got " . ($bachSummaryAfterSemEdit['semester_gpas'][1] ?? 'null'));

    // Login as Bachelor Student & B.Ed Student in Student Portal
    foreach ([['key' => 'bach', 'cookie' => $stud1Cookie], ['key' => 'bed', 'cookie' => $stud2Cookie]] as $stLogin) {
        $info = $provisioned[$stLogin['key']]['adm'];
        $sGet = e2e_http($baseUrl . '/student/login.php', $stLogin['cookie']);
        $sPost = e2e_http($baseUrl . '/student/login.php', $stLogin['cookie'], [
            'csrf_token' => e2e_csrf($sGet['body']),
            'student_id' => $info['student_id'],
            'password'   => $info['portal_password'],
        ]);
        e2e_assert($sPost['code'] === 200 && strpos($sPost['url'], '/student/dashboard.php') !== false, "Provisioned student {$info['student_id']} login must succeed");
    }

    $disclaimer = 'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT';
    foreach ([
        '/student/dashboard.php',
        '/student/profile.php',
        '/student/results.php',
        '/student/semester-transcript.php?semester=all',
    ] as $route) {
        $pg = e2e_http($baseUrl . $route, $stud1Cookie);
        e2e_assert($pg['code'] === 200, "{$route} must return 200 for student 1");
        e2e_assert(strpos($pg['body'], $disclaimer) !== false, "{$route} missing mandatory disclaimer banner");
        e2e_assert(strpos($pg['body'], 'ESTIMATED / GENERATED') !== false, "{$route} missing ESTIMATED / GENERATED label");
        e2e_assert(strpos($pg['body'], 'Fall 2026 (Honours)') !== false, "{$route} missing Academic Session");
    }

    // Check profile.php contains Roll No, App ID, Degree Type, Passing Year
    $prof1 = e2e_http($baseUrl . '/student/profile.php', $stud1Cookie);
    e2e_assert(strpos($prof1['body'], $applicantsSpec['bach']['roll_number']) !== false, "student/profile.php must show Roll Number");
    e2e_assert(strpos($prof1['body'], $applicantsSpec['bach']['application_id']) !== false, "student/profile.php must show Application ID");
    e2e_assert(strpos($prof1['body'], 'Bachelor / Honours') !== false, "student/profile.php must show Degree Type");
    e2e_assert(strpos($prof1['body'], '2025') !== false, "student/profile.php must show Passing Year");

    // Check B.Ed transcript shows ONLY 3 semesters (never Semester 4..8)
    $bedTr = e2e_http($baseUrl . '/student/semester-transcript.php?semester=all', $stud2Cookie);
    e2e_assert(strpos($bedTr['body'], '3rd Semester') !== false && strpos($bedTr['body'], '8th Semester') === false, "B.Ed 3-semester transcript must only render configured 3 semesters");

    // Admin verifies all results as Official -> disclaimer disappears cleanly
    $sdBachRefresh = e2e_http($baseUrl . '/admin/student-detail.php?id=' . $bachPk, $adminCookie);
    $verAllPost = e2e_http($baseUrl . '/admin/student-detail.php?id=' . $bachPk, $adminCookie, [
        'csrf_token' => e2e_csrf($sdBachRefresh['body']),
        'action'     => 'verify_all_results_official',
    ]);
    e2e_assert($verAllPost['code'] === 200, "Verify Official POST must return 200");
    $unverifiedLeft = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$bachPk} AND is_estimated = 1")->fetchColumn();
    e2e_assert($unverifiedLeft === 0, "All results must now have is_estimated = 0 after Verify Official");

    $resVerifiedHtml = e2e_http($baseUrl . '/student/results.php', $stud1Cookie);
    e2e_assert(strpos($resVerifiedHtml['body'], $disclaimer) === false, "Mandatory estimated disclaimer must vanish once all results are officially verified");
    echo "   [PASS] Semester GPA edit, Student Portal sync, disclaimers & Official Verification verified!\n\n";

    // -------------------------------------------------------------------------
    // STEP 6: Result Access Suspension (Keep Login & Profile) + Safe Result Delete
    // -------------------------------------------------------------------------
    echo "6. Testing Result Access Suspension, Restore & Delete All Results (Keep Account):\n";
    $susPost = e2e_http($baseUrl . '/admin/student-detail.php?id=' . $bachPk, $adminCookie, [
        'csrf_token' => e2e_csrf($sdBachRefresh['body']),
        'action'     => 'suspend_result_access',
        'reason'     => 'E2E Clearance Suspension',
    ]);
    e2e_assert($susPost['code'] === 200, "Suspend result access POST must return 200");

    // Student stays logged in & can open profile.php registration info, while results/GPAs/transcripts are blocked
    $susProf = e2e_http($baseUrl . '/student/profile.php', $stud1Cookie);
    e2e_assert($susProf['code'] === 200 && strpos($susProf['url'], '/student/profile.php') !== false, "Suspended-result student MUST still access profile.php");
    e2e_assert(strpos($susProf['body'], $applicantsSpec['bach']['roll_number']) !== false && strpos($susProf['body'], 'Result Access Suspended') !== false, "profile.php must keep registration details visible while suspending result card");
    e2e_assert(strpos($susProf['body'], 'E2E Clearance Suspension') !== false, "profile.php must show suspension reason");

    foreach (['/student/dashboard.php', '/student/results.php', '/student/semester-transcript.php?semester=all'] as $sRoute) {
        $susPage = e2e_http($baseUrl . $sRoute, $stud1Cookie);
        e2e_assert($susPage['code'] === 200 && strpos($susPage['url'], '/student/login.php') === false, "{$sRoute} must keep student logged in");
        e2e_assert(strpos($susPage['body'], 'Result Access Suspended') !== false, "{$sRoute} must clearly show Result Access Suspended");
        e2e_assert(strpos($susPage['body'], 'Semester-Wise GPA Overview') === false, "{$sRoute} must conceal Semester GPA Overview when suspended");
        if ($sRoute !== '/student/dashboard.php') {
            e2e_assert(strpos($susPage['body'], 'Structured Programming') === false && strpos($susPage['body'], 'CSE 101') === false, "{$sRoute} must conceal all course results when suspended");
        }
    }

    // Restore Result Access
    $rstPost = e2e_http($baseUrl . '/admin/student-detail.php?id=' . $bachPk, $adminCookie, [
        'csrf_token' => e2e_csrf($sdBachRefresh['body']),
        'action'     => 'restore_result_access',
    ]);
    e2e_assert($rstPost['code'] === 200, "Restore result access POST must return 200");
    $restoredResPage = e2e_http($baseUrl . '/student/results.php', $stud1Cookie);
    e2e_assert(strpos($restoredResPage['body'], 'CSE 101') !== false, "Restored result access must immediately display courses again");

    // Delete All Results Safely (Keep Student Account & Login Intact)
    $delAllRes = e2e_http($baseUrl . '/admin/results-manager.php?student_id=' . $bachPk, $adminCookie, [
        'csrf_token'                 => e2e_csrf($rmBachPage['body']),
        'action'                     => 'delete_all_results',
        'student_id'                 => $bachPk,
        'confirm_delete_all_results' => 'DELETE',
        'reason'                     => 'E2E Safe Clear Results Test',
    ]);
    e2e_assert($delAllRes['code'] === 200, "Delete all results POST must return 200");
    $remResRows = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$bachPk}")->fetchColumn();
    $completedSemRows = (int)$db->query("SELECT COUNT(*) FROM student_semester_results WHERE student_id = {$bachPk} AND (completion_status != 'Pending' OR semester_gpa > 0 OR credit_hours > 0 OR is_published = 1)")->fetchColumn();
    $stAfterDel = $db->query("SELECT status, cgpa, result_status FROM students WHERE id = {$bachPk}")->fetch(PDO::FETCH_ASSOC);
    e2e_assert($remResRows === 0 && $completedSemRows === 0, "All academic course results & completed semester summaries must be deleted/reset");
    e2e_assert($stAfterDel['status'] === 'Active' && (float)$stAfterDel['cgpa'] == 0.00 && $stAfterDel['result_status'] === 'Pending', "Student account must stay Active with CGPA 0.00 and result_status Pending");

    // Student can STILL access Student Portal cleanly after all results deleted
    $afterDelProf = e2e_http($baseUrl . '/student/results.php', $stud1Cookie);
    e2e_assert($afterDelProf['code'] === 200 && strpos($afterDelProf['body'], 'No academic results published yet') !== false, "Student account must stay logged in & see clean empty result state");
    echo "   [PASS] Result Access Suspension, Restore, and Safe Result Deletion (Account Kept) verified!\n\n";

    // -------------------------------------------------------------------------
    // STEP 7: Role-Based Security, Cross-Student IDOR & Seed Isolation
    // -------------------------------------------------------------------------
    echo "7. Testing Role-Based Security Guard, Student IDOR Prevention & Seed Isolation:\n";
    // Create temporary non-manager 'editor' admin account
    $edUser = 'e2e_editor_' . $suffix;
    $edPass = 'EditorPass!' . $suffix;
    $db->prepare("INSERT INTO users (username, full_name, email, password_hash, role, status) VALUES (?, 'Temp Editor', ?, ?, 'editor', 'active')")
       ->execute([$edUser, "editor.{$suffix}@rmu.test", password_hash($edPass, PASSWORD_DEFAULT)]);
    $tempEditorUserId = (int)$db->lastInsertId();

    $edLoginGet = e2e_http($baseUrl . '/admin/login.php', $editorCookie);
    $edLoginPost = e2e_http($baseUrl . '/admin/login.php', $editorCookie, [
        'csrf_token' => e2e_csrf($edLoginGet['body']),
        'username'   => $edUser,
        'password'   => $edPass,
    ]);
    e2e_assert($edLoginPost['code'] === 200 && strpos($edLoginPost['url'], '/admin/dashboard.php') !== false, "Editor login must succeed for permission test");

    $edRmGet = e2e_http($baseUrl . '/admin/results-manager.php?student_id=' . $bachPk, $editorCookie);
    $edTryGen = e2e_http($baseUrl . '/admin/results-manager.php?student_id=' . $bachPk, $editorCookie, [
        'csrf_token'       => e2e_csrf($edRmGet['body']),
        'action'           => 'generate_cgpa_results',
        'student_id'       => $bachPk,
        'final_cgpa'       => '3.99',
        'academic_session' => 'HACKED',
        'publication_date' => '2026-11-10',
    ]);
    $edBlockedRowCnt = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$bachPk}")->fetchColumn();
    e2e_assert($edBlockedRowCnt === 0, "Editor role must be strictly blocked from generating results");

    $edSemGet = e2e_http($baseUrl . '/admin/semesters.php', $editorCookie);
    e2e_http($baseUrl . '/admin/semesters.php', $editorCookie, [
        'csrf_token'      => e2e_csrf($edSemGet['body']),
        'action'          => 'update_program_structure',
        'program_id'      => (int)$bedProg['id'],
        'total_semesters' => '2',
        'total_credits'   => '999',
    ]);
    $bedCredCheck = (int)$db->query("SELECT total_credits FROM programs WHERE id = " . (int)$bedProg['id'])->fetchColumn();
    e2e_assert($bedCredCheck !== 999, "Editor role must NOT be able to mutate program semester structure");

    // Verify Student IDOR blocked: Student 2 (B.Ed) attempting ?student_id=4 cannot read Seed Student #4
    $idorRes = e2e_http($baseUrl . '/student/semester-transcript.php?student_id=' . $seedId . '&semester=all', $stud2Cookie);
    e2e_assert(strpos($idorRes['body'], $provisioned['adm']['student_id'] ?? $provisioned['bed']['student']['student_id']) !== false && strpos($idorRes['body'], 'RMU2601001') === false, "Student must NEVER access another student's transcript via ?student_id=");

    // Verify Seed Student RMU2601001 completely untouched
    $seedAfter = $db->query("SELECT id, student_id, cgpa, status, results_visible, result_access_status FROM students WHERE id = {$seedId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $seedResAfter = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$seedId}")->fetchColumn();
    e2e_assert((float)$seedAfter['cgpa'] === (float)$seedBefore['cgpa'] && $seedResAfter === $seedResBefore && $seedAfter['status'] === $seedBefore['status'], "Seeded student RMU2601001 must remain 100% untouched");

    echo "   [PASS] Role-based RBAC, Cross-Student IDOR protection & Seed isolation verified!\n\n";
    echo "===============================================================================\n";
    echo ">>> ALL AUTOMATIC STUDENT RESULT MANAGEMENT E2E TESTS PASSED (100% PASS)! <<<\n";
    echo "===============================================================================\n";
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] " . $e->getMessage() . "\n");
    $exitCode = 1;
} finally {
    // Restore original B.Ed program structure if modified
    if (!empty($bedOriginalRow)) {
        $db->prepare("UPDATE programs SET duration_years = ?, total_semesters = ?, total_credits = ?, duration = ? WHERE id = ?")
           ->execute([
               $bedOriginalRow['duration_years'],
               $bedOriginalRow['total_semesters'],
               $bedOriginalRow['total_credits'],
               $bedOriginalRow['duration'],
               $bedOriginalRow['id']
           ]);
    }
    // Clean up test students & linked child records
    if ($gatedTempStudentPk > 0) $createdStudentPks[] = $gatedTempStudentPk;
    if ($gatedTempAdmId > 0) $createdAdmissionIds[] = $gatedTempAdmId;

    foreach ($createdStudentPks as $pk) {
        if ($pk <= 0) continue;
        $db->prepare("DELETE FROM student_results WHERE student_id = ?")->execute([$pk]);
        $db->prepare("DELETE FROM student_semester_results WHERE student_id = ?")->execute([$pk]);
        $db->prepare("DELETE FROM student_courses WHERE student_id = ?")->execute([$pk]);
        $db->prepare("DELETE FROM student_notifications WHERE student_id = ?")->execute([$pk]);
        $db->prepare("DELETE FROM result_modification_history WHERE student_id = ?")->execute([$pk]);
        $db->prepare("DELETE FROM audit_logs WHERE details LIKE ? OR details LIKE ? OR details LIKE ? OR details LIKE ?")->execute([
            "%#{$pk} %", "%#{$pk})%", "%#{$pk}.%", "%#{$pk}]%"
        ]);
        $db->prepare("DELETE FROM students WHERE id = ?")->execute([$pk]);
    }
    foreach ($createdAdmissionIds as $aid) {
        if ($aid <= 0) continue;
        $db->prepare("DELETE FROM audit_logs WHERE details LIKE ?")->execute(["%application #{$aid}%"]);
        $db->prepare("DELETE FROM admissions WHERE id = ?")->execute([$aid]);
    }
    if ($tempEditorUserId > 0) {
        $db->prepare("DELETE FROM audit_logs WHERE user_id = ?")->execute([$tempEditorUserId]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$tempEditorUserId]);
    }
    $db->prepare("DELETE FROM admission_id_reservations WHERE id > ?")->execute([$maxResIdBeforeArm]);
    foreach ($seqSnapshotArm as $srArm) {
        $db->prepare("UPDATE admission_sequences SET last_app_seq = ?, last_roll_seq = ?, updated_at = NOW() WHERE seq_year = ?")
           ->execute([(int)$srArm['last_app_seq'], (int)$srArm['last_roll_seq'], (int)$srArm['seq_year']]);
    }
    $db->prepare("DELETE FROM audit_logs WHERE details LIKE ? OR details LIKE ?")->execute(["%{$suffix}%", "%Bachelor of Education%"]);

    foreach ([$adminCookie, $editorCookie, $stud1Cookie, $stud2Cookie, $anonCookie] as $cf) {
        if (file_exists($cf)) @unlink($cf);
    }
}
exit($exitCode);
