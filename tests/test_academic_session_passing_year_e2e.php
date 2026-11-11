<?php
/**
 * Comprehensive End-to-End Test Suite:
 * Rabindra Maitri University - Academic Session & Passing Year Management System
 *
 * Tests over real HTTP (http://127.0.0.1:8000) & MySQL (rmu_db):
 * 1. Seeded Academic Sessions (2011..Future, Spring/Summer/Fall) & Passing Years (2011..Future)
 * 2. Default Program Durations & Semesters (Honours/CSE/EEE/ICT/Agri=4yr/8sem, B.Ed=1yr/2sem, Master's=2yr/4sem)
 * 3. Expected Passing Year Formula (Session Year + Duration) — strictly advisory, never overwrites passing_year
 * 4. Admin Session/Year Enable/Disable/Add/Edit + Admission Form Availability + Server Validation
 * 5. Historical Online Application -> Admin Acceptance -> CGPA Gen (Passing Year Preserved 100%)
 * 6. Custom Program Duration & Semester Synchronization (1..12 semesters, 0.5..6.0 years) + 11th/12th ordinals
 * 7. Discrepancy Oversight -> Approve Historical -> Audited Official Edit (Mandatory Reason) + History Logs
 * 8. Student Portal Read-Only Profile Guard, Dynamic ID Card, Role Guards & Seed Isolation
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$baseUrl = 'http://127.0.0.1:8000';
$db = getDb();

$adminCookie  = sys_get_temp_dir() . '/rmu_sess_admin_' . uniqid() . '.txt';
$editorCookie = sys_get_temp_dir() . '/rmu_sess_editor_' . uniqid() . '.txt';
$studCookie   = sys_get_temp_dir() . '/rmu_sess_stud_' . uniqid() . '.txt';
$anonCookie   = sys_get_temp_dir() . '/rmu_sess_anon_' . uniqid() . '.txt';

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
$suffix = (string)rand(60000, 99999);
$createdAdmissionIds = [];
$createdStudentPks = [];
$createdSessionIds = [];
$createdPassingYearIds = [];
$tempEditorUserId = 0;
$bbaOriginalRow = null;
$bedOriginalRow = null;
$originalCurrentSessionId = 0;
$summer2013SessId = 0;
$summer2013WasEnabled = 1;
$year2013Id = 0;
$year2013WasEnabled = 1;
$seedBefore = null;
$seedResBefore = 0;
ensure_admission_id_generation_schema($db);
$seqSnapshotAcad = $db->query("SELECT seq_year, last_app_seq, last_roll_seq FROM admission_sequences")->fetchAll(PDO::FETCH_ASSOC);
$maxResIdBeforeAcad = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM admission_id_reservations")->fetchColumn();

try {
    echo "===============================================================================\n";
    echo "  RMU ACADEMIC SESSION & PASSING YEAR MANAGEMENT - COMPREHENSIVE E2E TEST SUITE\n";
    echo "===============================================================================\n\n";

    // Snapshot baseline records for strict restoration & isolation verification
    $seedBefore = $db->query("SELECT id, student_id, cgpa, passing_year, academic_session, status FROM students WHERE student_id = 'RMU2601001' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    e2e_assert(!empty($seedBefore), "Seed student RMU2601001 must exist");
    $seedId = (int)$seedBefore['id'];
    $seedResBefore = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$seedId}")->fetchColumn();

    $bbaProg = $db->query("SELECT * FROM programs WHERE slug = 'bba' OR title_en LIKE '%BBA%' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $bedProg = $db->query("SELECT * FROM programs WHERE slug = 'b-ed' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $cseProg = $db->query("SELECT * FROM programs WHERE slug = 'bsc-in-cse' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $mbaProg = $db->query("SELECT * FROM programs WHERE slug = 'mba' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    e2e_assert(!empty($bbaProg) && !empty($bedProg) && !empty($cseProg) && !empty($mbaProg), "Core programs (BBA, B.Ed, CSE, MBA) must exist");
    $bbaOriginalRow = $bbaProg;
    $bedOriginalRow = $bedProg;

    $originalCurrentSessionId = (int)$db->query("SELECT id FROM academic_sessions WHERE is_current = 1 LIMIT 1")->fetchColumn();

    // -------------------------------------------------------------------------
    // STEP 1: Seeded Sessions (2011+), Passing Years (2011+), Defaults & Formula
    // -------------------------------------------------------------------------
    echo "1. Verifying Seeded Sessions (2011+), Passing Years (2011+), Program Defaults & Expected Formula:\n";
    $sessCount = (int)$db->query("SELECT COUNT(*) FROM academic_sessions")->fetchColumn();
    $pyCount   = (int)$db->query("SELECT COUNT(*) FROM passing_years")->fetchColumn();
    e2e_assert($sessCount >= 60, "Expected at least 60 seeded sessions (Spring/Summer/Fall 2011..2030), got {$sessCount}");
    e2e_assert($pyCount >= 20, "Expected at least 20 seeded passing years (2011..2030), got {$pyCount}");

    foreach (['Spring 2011', 'Summer 2011', 'Fall 2011', 'Spring 2012', 'Summer 2012', 'Fall 2012', 'Fall 2026', 'Fall 2030'] as $chkSess) {
        $st = $db->prepare("SELECT id, is_enabled FROM academic_sessions WHERE session_name = ?");
        $st->execute([$chkSess]);
        $sr = $st->fetch(PDO::FETCH_ASSOC);
        e2e_assert(!empty($sr) && (int)$sr['is_enabled'] === 1, "Seeded session '{$chkSess}' must exist and be enabled");
    }
    foreach ([2011, 2012, 2015, 2020, 2026, 2030] as $chkPy) {
        $st = $db->prepare("SELECT id, is_enabled FROM passing_years WHERE year_value = ?");
        $st->execute([$chkPy]);
        $pr = $st->fetch(PDO::FETCH_ASSOC);
        e2e_assert(!empty($pr) && (int)$pr['is_enabled'] === 1, "Seeded passing year {$chkPy} must exist and be enabled");
    }

    // Check default program structures
    $chkPrograms = [
        'BBA' => ['prog' => $bbaProg, 'years' => 4.0, 'sems' => 8],
        'CSE' => ['prog' => $cseProg, 'years' => 4.0, 'sems' => 8],
        'B.Ed' => ['prog' => $bedProg, 'years' => 1.0, 'sems' => 2],
        'MBA' => ['prog' => $mbaProg, 'years' => 2.0, 'sems' => 4],
    ];
    foreach ($chkPrograms as $lbl => $spec) {
        $ps = get_program_academic_structure((int)$spec['prog']['id']);
        e2e_assert(abs((float)$ps['duration_years'] - $spec['years']) < 0.01, "{$lbl} default duration must be {$spec['years']} years, got {$ps['duration_years']}");
        e2e_assert((int)$ps['total_semesters'] === $spec['sems'], "{$lbl} default semesters must be {$spec['sems']}, got {$ps['total_semesters']}");
    }
    foreach (['eee', 'ict', 'agriculture', 'english', 'bangla', 'fine', 'social'] as $kw) {
        $row = $db->query("SELECT title_en, duration_years, total_semesters FROM programs WHERE degree_type = 'Bachelor / Honours' AND (LOWER(slug) LIKE '%{$kw}%' OR LOWER(title_en) LIKE '%{$kw}%') LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            e2e_assert(abs((float)$row['duration_years'] - 4.0) < 0.01 && (int)$row['total_semesters'] === 8, "Bachelor program '{$row['title_en']}' must default to 4.0 Years / 8 Semesters");
        }
    }

    // Verify Expected Passing Year Formula Calculation
    $expBba  = calculate_expected_passing_year('Spring 2011', 4.0);
    $expBed  = calculate_expected_passing_year('Summer 2011', 1.0);
    $expMast = calculate_expected_passing_year('Fall 2011', 2.0);
    e2e_assert($expBba && (int)$expBba['expected_year'] === 2015, "2011 + 4yr BBA expected passing year must be 2015");
    e2e_assert($expBed && (int)$expBed['expected_year'] === 2012, "2011 + 1yr B.Ed expected passing year must be 2012");
    e2e_assert($expMast && (int)$expMast['expected_year'] === 2013, "2011 + 2yr Master's expected passing year must be 2013");

    // Verify ZERO Bengali Unicode characters introduced in academic tables
    $bnSessFound = false;
    foreach ($db->query("SELECT session_name FROM academic_sessions")->fetchAll(PDO::FETCH_COLUMN) as $sName) {
        if (preg_match('/[\x{0980}-\x{09FF}]/u', (string)$sName)) {
            $bnSessFound = true;
            break;
        }
    }
    $bnPyFound = false;
    foreach ($db->query("SELECT label FROM passing_years")->fetchAll(PDO::FETCH_COLUMN) as $pLbl) {
        if (preg_match('/[\x{0980}-\x{09FF}]/u', (string)$pLbl)) {
            $bnPyFound = true;
            break;
        }
    }
    e2e_assert(!$bnSessFound && !$bnPyFound, "Seeded session/year tables must contain zero Bengali Unicode text");
    echo "   [PASS] Seeded sessions (60+), passing years (20+), program defaults & Expected Year formulas verified.\n\n";

    // -------------------------------------------------------------------------
    // STEP 2: Admin Login + Session/Passing Year CRUD + Form Filtering & Guard
    // -------------------------------------------------------------------------
    echo "2. Testing Admin Session/Year Management, Form Filtering & Server Validation:\n";
    $adminLoginGet = e2e_http($baseUrl . '/admin/login.php', $adminCookie);
    $adminLoginPost = e2e_http($baseUrl . '/admin/login.php', $adminCookie, [
        'csrf_token' => e2e_csrf($adminLoginGet['body']),
        'username'   => 'admin',
        'password'   => 'Admin@RMU2026!',
    ]);
    e2e_assert($adminLoginPost['code'] === 200 && strpos($adminLoginPost['url'], '/admin/dashboard.php') !== false, "Admin login must succeed");
    e2e_assert(strpos($adminLoginPost['body'], 'Sessions &amp; Passing Years') !== false || strpos($adminLoginPost['body'], 'Sessions & Passing Years') !== false, "Admin dashboard missing Academic Sessions quick link");

    $sessAdminPage = e2e_http($baseUrl . '/admin/academic-sessions.php?tab=sessions', $adminCookie);
    e2e_assert($sessAdminPage['code'] === 200 && strpos($sessAdminPage['body'], 'Academic Sessions &amp; Passing Year Management') !== false, "admin/academic-sessions.php must load");
    $sessCsrf = e2e_csrf($sessAdminPage['body']);

    // Add custom future session Spring 2036 & edit its note
    $addSessPost = e2e_http($baseUrl . '/admin/academic-sessions.php?tab=sessions', $adminCookie, [
        'csrf_token'   => $sessCsrf,
        'action'       => 'add_session',
        'redirect_tab' => 'sessions',
        'term_name'    => 'Spring',
        'session_year' => '2036',
        'is_enabled'   => '1',
        'notes'        => "E2E Temp Session {$suffix}",
    ]);
    e2e_assert($addSessPost['code'] === 200, "Add Spring 2036 session must return 200");
    $spr2036Id = (int)$db->query("SELECT id FROM academic_sessions WHERE session_name = 'Spring 2036' LIMIT 1")->fetchColumn();
    e2e_assert($spr2036Id > 0, "Spring 2036 must be persisted in academic_sessions");
    $createdSessionIds[] = $spr2036Id;

    $editSessPost = e2e_http($baseUrl . '/admin/academic-sessions.php?tab=sessions', $adminCookie, [
        'csrf_token'   => e2e_csrf($addSessPost['body']),
        'action'       => 'edit_session',
        'redirect_tab' => 'sessions',
        'id'           => $spr2036Id,
        'term_name'    => 'Spring',
        'session_year' => '2036',
        'is_enabled'   => '1',
        'notes'        => "Edited Note {$suffix}",
    ]);
    e2e_assert($editSessPost['code'] === 200, "Edit Spring 2036 must return 200");
    $sprNote = (string)$db->query("SELECT notes FROM academic_sessions WHERE id = {$spr2036Id}")->fetchColumn();
    e2e_assert($sprNote === "Edited Note {$suffix}", "Edited session note mismatch");

    // Add custom passing year 2036
    $pyAdminPage = e2e_http($baseUrl . '/admin/academic-sessions.php?tab=passing_years', $adminCookie);
    e2e_assert($pyAdminPage['code'] === 200, "Passing Years tab must return 200");
    $addPyPost = e2e_http($baseUrl . '/admin/academic-sessions.php?tab=passing_years', $adminCookie, [
        'csrf_token'   => e2e_csrf($pyAdminPage['body']),
        'action'       => 'add_passing_year',
        'redirect_tab' => 'passing_years',
        'year_value'   => '2036',
        'label'        => '2036',
        'is_enabled'   => '1',
        'notes'        => "E2E Temp Year {$suffix}",
    ]);
    e2e_assert($addPyPost['code'] === 200, "Add passing year 2036 must return 200");
    $py2036Id = (int)$db->query("SELECT id FROM passing_years WHERE year_value = 2036 LIMIT 1")->fetchColumn();
    e2e_assert($py2036Id > 0, "Passing Year 2036 must exist");
    $createdPassingYearIds[] = $py2036Id;

    // Temporarily disable Summer 2013 & Passing Year 2013 to verify admission-form hiding & server validation
    $sum2013Row = $db->query("SELECT id, is_enabled FROM academic_sessions WHERE session_name = 'Summer 2013' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $yr2013Row  = $db->query("SELECT id, is_enabled FROM passing_years WHERE year_value = 2013 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    e2e_assert(!empty($sum2013Row) && !empty($yr2013Row), "Summer 2013 and Passing Year 2013 must exist");
    $summer2013SessId = (int)$sum2013Row['id'];
    $summer2013WasEnabled = (int)$sum2013Row['is_enabled'];
    $year2013Id = (int)$yr2013Row['id'];
    $year2013WasEnabled = (int)$yr2013Row['is_enabled'];

    e2e_http($baseUrl . '/admin/academic-sessions.php?tab=sessions', $adminCookie, [
        'csrf_token'   => $sessCsrf,
        'action'       => 'toggle_session',
        'redirect_tab' => 'sessions',
        'id'           => $summer2013SessId,
        'is_enabled'   => '0',
    ]);
    e2e_http($baseUrl . '/admin/academic-sessions.php?tab=passing_years', $adminCookie, [
        'csrf_token'   => $sessCsrf,
        'action'       => 'toggle_passing_year',
        'redirect_tab' => 'passing_years',
        'id'           => $year2013Id,
        'is_enabled'   => '0',
    ]);

    // Check admission-form.php renders ONLY enabled options
    $admForm = e2e_http($baseUrl . '/admission-form.php', $anonCookie);
    e2e_assert($admForm['code'] === 200, "admission-form.php must return 200");
    e2e_assert(strpos($admForm['body'], 'value="Spring 2011"') !== false, "Enabled session Spring 2011 must appear on admission-form.php");
    e2e_assert(strpos($admForm['body'], 'value="Spring 2036"') !== false, "Enabled session Spring 2036 must appear on admission-form.php");
    e2e_assert(strpos($admForm['body'], 'value="Summer 2013"') === false, "Disabled session Summer 2013 must NOT appear on admission-form.php");
    e2e_assert(preg_match('/<select[^>]+name="passing_year"[^>]*>(.*?)<\/select>/s', $admForm['body'], $pySelMatch) === 1, "admission-form.php missing passing_year select");
    e2e_assert(strpos($pySelMatch[1], 'value="2016"') !== false, "Enabled passing year 2016 must appear in dropdown");
    e2e_assert(strpos($pySelMatch[1], 'value="2036"') !== false, "Enabled passing year 2036 must appear in dropdown");
    e2e_assert(strpos($pySelMatch[1], 'value="2013"') === false, "Disabled passing year 2013 must NOT appear in dropdown");

    // Server-side validation must REJECT disabled Summer 2013 & disabled Passing Year 2013
    $disMob = '017' . rand(10000000, 99999999);
    $badSub = e2e_http($baseUrl . '/actions/submit-admission.php', $anonCookie, [
        'csrf_token'         => e2e_csrf($admForm['body']),
        'full_name'          => "Disabled Check {$suffix}",
        'application_id'     => "APP-DIS-{$suffix}",
        'roll_number'        => "ROLL-DIS-{$suffix}",
        'father_name'        => 'Md. Father',
        'mother_name'        => 'Mrs. Mother',
        'date_of_birth'      => '1993-02-15',
        'gender'             => 'Male',
        'mobile'             => $disMob,
        'email'              => "disabled.{$suffix}@rmu.test",
        'present_address'    => 'Kushtia',
        'permanent_address'  => 'Kushtia',
        'last_college'       => 'Kushtia Govt College',
        'home_district'      => 'Kushtia',
        'ssc_gpa'            => '4.80',
        'ssc_year'           => '2011',
        'hsc_group'          => 'Science',
        'hsc_gpa'            => '4.70',
        'hsc_year'           => '2013',
        'degree_type'        => 'Bachelor / Honours',
        'department_id'      => (int)$bbaProg['department_id'],
        'program_id'         => (int)$bbaProg['id'],
        'interested_subject' => $bbaProg['title_en'],
        'academic_session'   => 'Summer 2013',
        'passing_year'       => '2013',
        'quota'              => 'NO',
    ]);
    $disCheckRow = $db->prepare("SELECT id FROM admissions WHERE mobile = ?");
    $disCheckRow->execute([$disMob]);
    e2e_assert(!$disCheckRow->fetchColumn(), "Server validation MUST reject disabled Academic Session and Passing Year");
    e2e_assert(strpos($badSub['body'], 'enabled Academic Session') !== false || strpos($badSub['body'], 'enabled Passing Year') !== false, "Rejected disabled submission must show clear validation message");

    // Re-enable Summer 2013 & Year 2013 cleanly
    e2e_http($baseUrl . '/admin/academic-sessions.php?tab=sessions', $adminCookie, [
        'csrf_token' => $sessCsrf, 'action' => 'toggle_session', 'redirect_tab' => 'sessions', 'id' => $summer2013SessId, 'is_enabled' => '1',
    ]);
    e2e_http($baseUrl . '/admin/academic-sessions.php?tab=passing_years', $adminCookie, [
        'csrf_token' => $sessCsrf, 'action' => 'toggle_passing_year', 'redirect_tab' => 'passing_years', 'id' => $year2013Id, 'is_enabled' => '1',
    ]);
    echo "   [PASS] Admin Session/Year add/edit/toggle, Admission form filtering & server validation verified.\n\n";

    // -------------------------------------------------------------------------
    // STEP 3: Historical Admission Submission -> Acceptance -> CGPA Gen (Preserve Passing Year!)
    // -------------------------------------------------------------------------
    echo "3. Testing Historical Submission (Spring 2011, Selected Passing Year 2016) & Preservation Invariant:\n";
    $freshForm = e2e_http($baseUrl . '/admission-form.php', $anonCookie);
    preg_match('/name="draft_token"[^>]*value="([^"]*)"/', $freshForm['body'], $dtHistMatch);
    $mobileNum = '018' . rand(10000000, 99999999);

    $histSub = e2e_http($baseUrl . '/actions/submit-admission.php', $anonCookie, [
        'csrf_token'         => e2e_csrf($freshForm['body']),
        'draft_token'        => $dtHistMatch[1] ?? '',
        'full_name'          => "Historical Applicant {$suffix}",
        'application_id'     => "SPOOF-HIST-{$suffix}",
        'roll_number'        => "SPOOF-ROLL-{$suffix}",
        'father_name'        => 'Md. Karim',
        'mother_name'        => 'Mrs. Salma',
        'date_of_birth'      => '1993-08-10',
        'gender'             => 'Male',
        'mobile'             => $mobileNum,
        'email'              => "hist.{$suffix}@rmu.test",
        'present_address'    => 'Kushtia Sadar',
        'permanent_address'  => 'Kushtia Sadar',
        'last_college'       => 'Kushtia Govt College',
        'home_district'      => 'Kushtia',
        'ssc_gpa'            => '4.90',
        'ssc_year'           => '2011',
        'hsc_group'          => 'Business Studies',
        'hsc_gpa'            => '4.80',
        'hsc_year'           => '2014', // Explicitly different from 2016 and 2015!
        'degree_type'        => 'Bachelor / Honours',
        'department_id'      => (int)$bbaProg['department_id'],
        'program_id'         => (int)$bbaProg['id'],
        'interested_subject' => $bbaProg['title_en'],
        'academic_session'   => 'Spring 2011',
        'passing_year'       => '2016',
        'quota'              => 'NO',
    ]);
    e2e_assert($histSub['code'] === 200, "Historical application submit must return 200");

    $admStmt = $db->prepare("SELECT * FROM admissions WHERE mobile = ? ORDER BY id DESC LIMIT 1");
    $admStmt->execute([$mobileNum]);
    $admRow = $admStmt->fetch(PDO::FETCH_ASSOC);
    e2e_assert(!empty($admRow), "Submitted historical application must exist in admissions table");
    e2e_assert(preg_match('/^RMU-\d{4}-\d{4,}$/', $admRow['application_ref']) === 1, "Historical submit assigned sequential Application ID ({$admRow['application_ref']})");
    e2e_assert(preg_match('/^\d{4}-\d{4,}$/', $admRow['roll_number']) === 1, "Historical submit assigned sequential Roll Number ({$admRow['roll_number']})");
    $appIdCode = $admRow['application_ref'];
    $rollCode  = $admRow['roll_number'];
    $createdAdmissionIds[] = (int)$admRow['id'];
    e2e_assert($admRow['academic_session'] === 'Spring 2011', "Expected academic_session Spring 2011, got {$admRow['academic_session']}");
    e2e_assert((string)$admRow['passing_year'] === '2016', "Student-selected Passing Year 2016 MUST NEVER be overwritten by expected 2015 or HSC 2014");
    e2e_assert((int)$admRow['expected_passing_year'] === 2015, "Advisory expected_passing_year for Spring 2011 + BBA (4yr) must be 2015, got {$admRow['expected_passing_year']}");

    // Check admission-status.php shows Spring 2011 & 2016 & Expected 2015
    $statusPg = e2e_http($baseUrl . '/admission-status.php?ref=' . urlencode($admRow['application_ref']), $anonCookie);
    e2e_assert($statusPg['code'] === 200 && strpos($statusPg['body'], 'Spring 2011') !== false, "admission-status.php must render applicant's Spring 2011 session");
    e2e_assert(strpos($statusPg['body'], '2016') !== false && strpos($statusPg['body'], '2015') !== false, "admission-status.php must show both selected 2016 and advisory expected 2015");

    // Admin accepts application -> Provisions student -> Verify Passing Year preserved
    $admListPg = e2e_http($baseUrl . '/admin/admissions.php?search=' . urlencode($appIdCode), $adminCookie);
    e2e_assert($admListPg['code'] === 200 && strpos($admListPg['body'], 'Passing Oversight') !== false, "admin/admissions.php must render Passing Oversight metrics");
    e2e_assert(strpos($admListPg['body'], 'Differs (+1 yr)') !== false, "admin/admissions.php must flag Expected 2015 vs Selected 2016 (+1 yr)");

    $accPost = e2e_http($baseUrl . '/admin/admissions.php', $adminCookie, [
        'csrf_token'  => e2e_csrf($admListPg['body']),
        'action'      => 'accept_application',
        'id'          => (int)$admRow['id'],
        'admin_notes' => 'Historical application accepted E2E',
    ]);
    e2e_assert($accPost['code'] === 200, "Accept historical application must return 200");

    $admAccepted = $db->query("SELECT * FROM admissions WHERE id = " . (int)$admRow['id'])->fetch(PDO::FETCH_ASSOC);
    e2e_assert($admAccepted['status'] === 'Accepted' && !empty($admAccepted['student_id']), "Historical application must provision student");

    $stStmt = $db->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
    $stStmt->execute([$admAccepted['student_id']]);
    $stRow = $stStmt->fetch(PDO::FETCH_ASSOC);
    e2e_assert(!empty($stRow), "Provisioned historical student must exist");
    $stPk = (int)$stRow['id'];
    $createdStudentPks[] = $stPk;
    e2e_assert($stRow['academic_session'] === 'Spring 2011', "Student academic_session must transfer as Spring 2011");
    e2e_assert((string)$stRow['passing_year'] === '2016', "Student passing_year MUST remain 2016 after provisioning");
    e2e_assert((int)$stRow['expected_passing_year'] === 2015, "Student expected_passing_year must be 2015");

    // Run CGPA Result Generator -> Verify passing_year 2016 STILL untouched!
    $sdPg = e2e_http($baseUrl . '/admin/student-detail.php?id=' . $stPk, $adminCookie);
    e2e_assert($sdPg['code'] === 200, "student-detail.php must return 200");
    $genPost = e2e_http($baseUrl . '/admin/student-detail.php?id=' . $stPk, $adminCookie, [
        'csrf_token'       => e2e_csrf($sdPg['body']),
        'action'           => 'generate_cgpa_results',
        'final_cgpa'       => '3.55',
        'academic_session' => 'Spring 2011',
        'publication_date' => '2016-06-15',
        'publish_mode'     => '1',
    ]);
    e2e_assert($genPost['code'] === 200, "CGPA generation for historical student must return 200");

    $stAfterGen = $db->query("SELECT passing_year, expected_passing_year, academic_session, cgpa FROM students WHERE id = {$stPk}")->fetch(PDO::FETCH_ASSOC);
    e2e_assert((string)$stAfterGen['passing_year'] === '2016', "CGPA generator MUST NEVER overwrite passing_year (expected 2016, got {$stAfterGen['passing_year']})");
    e2e_assert(abs((float)$stAfterGen['cgpa'] - 3.55) <= 0.01, "Generated CGPA expected 3.55, got {$stAfterGen['cgpa']}");
    echo "   [PASS] Selected Passing Year 2016 preserved across submission, admission acceptance & CGPA generation.\n\n";

    // -------------------------------------------------------------------------
    // STEP 4: Program Duration & Semester Customization (10/12 Semesters & 11th/12th Ordinals)
    // -------------------------------------------------------------------------
    echo "4. Testing Custom Program Duration (5 Years / 10 & 12 Semesters) & Active Student Sync:\n";
    $progPage = e2e_http($baseUrl . '/admin/programs.php?edit=' . (int)$bbaProg['id'], $adminCookie);
    e2e_assert($progPage['code'] === 200, "admin/programs.php edit page must return 200");
    $updBba = e2e_http($baseUrl . '/admin/programs.php', $adminCookie, [
        'csrf_token'            => e2e_csrf($progPage['body']),
        'action'                => 'edit',
        'id'                    => (int)$bbaProg['id'],
        'department_id'         => (int)$bbaProg['department_id'],
        'title_en'              => $bbaProg['title_en'],
        'title_bn'              => (string)($bbaProg['title_bn'] ?? ''),
        'degree_type'           => 'Bachelor / Honours',
        'degree_level'          => 'Undergraduate',
        'total_semesters'       => '12',
        'duration_years'        => '5.0',
        'total_credits'         => '160',
        'tuition_fee'           => (string)($bbaProg['tuition_fee'] ?? ''),
        'sort_order'            => (string)($bbaProg['sort_order'] ?? '0'),
        'structure_edit_reason' => "E2E Extended Honors Test {$suffix}",
    ]);
    e2e_assert($updBba['code'] === 200, "Customizing BBA duration/semesters must return 200");

    $stAfterProgEdit = $db->query("SELECT passing_year, expected_passing_year, duration_years, total_semesters FROM students WHERE id = {$stPk}")->fetch(PDO::FETCH_ASSOC);
    e2e_assert((float)$stAfterProgEdit['duration_years'] === 5.0 && (int)$stAfterProgEdit['total_semesters'] === 12, "Program structure update must propagate 5.0 Years / 12 Semesters to active student");
    e2e_assert((int)$stAfterProgEdit['expected_passing_year'] === 2016, "Advisory Expected Passing Year should update to 2011 + 5 = 2016");
    e2e_assert((string)$stAfterProgEdit['passing_year'] === '2016', "Student-selected Passing Year must stay untouched on program structure edit");

    // Check 11th / 12th ordinals render properly without '11st'/'12nd' bug in admin/results-manager & student/semester-transcript
    e2e_assert(get_semester_ordinal_title(11) === '11th Semester' && get_semester_ordinal_title(12) === '12th Semester', "Ordinal title helper must return 11th & 12th cleanly");
    $rmCustom = e2e_http($baseUrl . '/admin/results-manager.php?student_id=' . $stPk, $adminCookie);
    e2e_assert($rmCustom['code'] === 200 && strpos($rmCustom['body'], '12-Semester Results') !== false, "results-manager.php must reflect customized 12-semester structure");
    e2e_assert(strpos($rmCustom['body'], '11st Semester') === false && strpos($rmCustom['body'], '12nd Semester') === false, "results-manager.php must NOT produce malformed 11st/12nd ordinals");

    // Also test admin/academic-sessions.php?tab=programs updating B.Ed -> 3 Semesters / 1.5 Years
    $sessProgPg = e2e_http($baseUrl . '/admin/academic-sessions.php?tab=programs', $adminCookie);
    $updBedDur = e2e_http($baseUrl . '/admin/academic-sessions.php?tab=programs', $adminCookie, [
        'csrf_token'      => e2e_csrf($sessProgPg['body']),
        'action'          => 'update_program_duration',
        'redirect_tab'    => 'programs',
        'program_id'      => (int)$bedProg['id'],
        'degree_type'     => 'B.Ed',
        'duration_years'  => '1.5',
        'total_semesters' => '3',
        'total_credits'   => '54',
        'reason'          => "E2E BEd 3-Sem Duration {$suffix}",
    ]);
    e2e_assert($updBedDur['code'] === 200, "Customizing B.Ed duration via academic-sessions.php must succeed");
    $bedCheck = $db->query("SELECT duration_years, total_semesters FROM programs WHERE id = " . (int)$bedProg['id'])->fetch(PDO::FETCH_ASSOC);
    e2e_assert((float)$bedCheck['duration_years'] === 1.5 && (int)$bedCheck['total_semesters'] === 3, "B.Ed updated structure mismatch");

    // Restore BBA immediately before Oversight Discrepancy check (so 2011+4=2015 differs from 2016 again!)
    $progRestorePg = e2e_http($baseUrl . '/admin/programs.php?edit=' . (int)$bbaProg['id'], $adminCookie);
    e2e_http($baseUrl . '/admin/programs.php', $adminCookie, [
        'csrf_token'            => e2e_csrf($progRestorePg['body']),
        'action'                => 'edit',
        'id'                    => (int)$bbaProg['id'],
        'department_id'         => (int)$bbaProg['department_id'],
        'title_en'              => $bbaProg['title_en'],
        'title_bn'              => (string)($bbaProg['title_bn'] ?? ''),
        'degree_type'           => $bbaOriginalRow['degree_type'],
        'degree_level'          => $bbaOriginalRow['degree_level'],
        'total_semesters'       => (string)$bbaOriginalRow['total_semesters'],
        'duration_years'        => (string)$bbaOriginalRow['duration_years'],
        'total_credits'         => (string)$bbaOriginalRow['total_credits'],
        'tuition_fee'           => (string)($bbaOriginalRow['tuition_fee'] ?? ''),
        'sort_order'            => (string)($bbaOriginalRow['sort_order'] ?? '0'),
        'structure_edit_reason' => "Restore BBA Default E2E {$suffix}",
    ]);
    echo "   [PASS] Custom Course Duration/Semesters sync & ordinal safety (11th/12th) verified.\n\n";

    // -------------------------------------------------------------------------
    // STEP 5: Oversight Discrepancy Detection, Historical Approval & Audited Edit
    // -------------------------------------------------------------------------
    echo "5. Testing Oversight Discrepancy Detection, Approve Historical & Mandatory Reason Audit Trail:\n";
    $ovPg = e2e_http($baseUrl . '/admin/academic-sessions.php?tab=oversight&search=' . urlencode($admAccepted['student_id']), $adminCookie);
    e2e_assert($ovPg['code'] === 200 && strpos($ovPg['body'], $admAccepted['student_id']) !== false, "Oversight tab must locate student with expected vs selected difference");
    e2e_assert(strpos($ovPg['body'], 'Differs (+1 yr)') !== false, "Oversight tab must display discrepancy badge (+1 yr)");

    // Approve Historical Record -> historical_record_approved = 1 & selected Passing Year preserved!
    $appHistPost = e2e_http($baseUrl . '/admin/academic-sessions.php?tab=oversight', $adminCookie, [
        'csrf_token'      => e2e_csrf($ovPg['body']),
        'action'          => 'approve_historical',
        'redirect_tab'    => 'oversight',
        'redirect_search' => $admAccepted['student_id'],
        'entity_type'     => 'student',
        'record_id'       => $stPk,
        'note'            => "Verified old session 2011 / passed 2016 {$suffix}",
    ]);
    e2e_assert($appHistPost['code'] === 200, "Approve Historical POST must return 200");
    $stAppFlag = (int)$db->query("SELECT historical_record_approved FROM students WHERE id = {$stPk}")->fetchColumn();
    $admAppFlag = (int)$db->query("SELECT historical_record_approved FROM admissions WHERE id = " . (int)$admRow['id'])->fetchColumn();
    e2e_assert($stAppFlag === 1 && $admAppFlag === 1, "Approve Historical must sync historical_record_approved=1 on both student and admission");
    e2e_assert(strpos($appHistPost['body'], 'Historical Approved') !== false, "Oversight view must show Historical Approved badge after approval");

    // Attempt Official Edit WITHOUT a Reason -> MUST REJECT without mutating DB!
    $emptyReasonEdit = e2e_http($baseUrl . '/admin/academic-sessions.php?tab=oversight', $adminCookie, [
        'csrf_token'       => e2e_csrf($ovPg['body']),
        'action'           => 'official_edit_record',
        'redirect_tab'     => 'oversight',
        'redirect_search'  => $admAccepted['student_id'],
        'entity_type'      => 'student',
        'record_id'        => $stPk,
        'academic_session' => 'Summer 2012',
        'passing_year'     => '2017',
        'program_id'       => (int)$bbaProg['id'],
        'reason'           => '   ', // Blank reason!
    ]);
    e2e_assert(strpos($emptyReasonEdit['body'], 'mandatory reason') !== false || strpos($emptyReasonEdit['body'], 'Reason') !== false, "Blank reason edit must show rejection feedback");
    $unchangedSt = $db->query("SELECT academic_session, passing_year FROM students WHERE id = {$stPk}")->fetch(PDO::FETCH_ASSOC);
    e2e_assert($unchangedSt['academic_session'] === 'Spring 2011' && (string)$unchangedSt['passing_year'] === '2016', "Blank reason MUST NOT mutate DB records");

    // Perform Official Edit WITH valid mandatory reason -> Verify sync & academic_edit_history + audit_logs
    $editReasonText = "Controller official correction per archive register {$suffix}";
    $validOfficialEdit = e2e_http($baseUrl . '/admin/academic-sessions.php?tab=oversight', $adminCookie, [
        'csrf_token'       => e2e_csrf($ovPg['body']),
        'action'           => 'official_edit_record',
        'redirect_tab'     => 'oversight',
        'redirect_search'  => $admAccepted['student_id'],
        'entity_type'      => 'student',
        'record_id'        => $stPk,
        'academic_session' => 'Summer 2012',
        'passing_year'     => '2017',
        'program_id'       => (int)$bbaProg['id'],
        'duration_years'   => '4.0',
        'total_semesters'  => '8',
        'reason'           => $editReasonText,
    ]);
    e2e_assert($validOfficialEdit['code'] === 200, "Valid Official Edit must return 200");
    $editedSt = $db->query("SELECT academic_session, passing_year, expected_passing_year FROM students WHERE id = {$stPk}")->fetch(PDO::FETCH_ASSOC);
    e2e_assert($editedSt['academic_session'] === 'Summer 2012' && (string)$editedSt['passing_year'] === '2017', "Official edit failed to save new session/passing year");
    e2e_assert((int)$editedSt['expected_passing_year'] === 2016, "Expected passing year must recalculate to 2012 + 4 = 2016");

    $histCntStmt = $db->prepare("SELECT COUNT(*) FROM academic_edit_history WHERE entity_type = 'student' AND entity_id = ? AND reason = ?");
    $histCntStmt->execute([$stPk, $editReasonText]);
    e2e_assert((int)$histCntStmt->fetchColumn() >= 2, "academic_edit_history must log old->new changes with mandatory reason");

    $histTabPg = e2e_http($baseUrl . '/admin/academic-sessions.php?tab=history&hist_code=' . urlencode($admAccepted['student_id']), $adminCookie);
    e2e_assert($histTabPg['code'] === 200 && strpos($histTabPg['body'], $editReasonText) !== false, "History tab must display audited official changes and reason");
    echo "   [PASS] Oversight discrepancy detection, Historical Approval & Audited Official Edit verified.\n\n";

    // -------------------------------------------------------------------------
    // STEP 6: Student Portal Verification, POST Protection, Dynamic ID Card & Seed Isolation
    // -------------------------------------------------------------------------
    echo "6. Testing Student Portal Display, Read-Only Security Guard, Role RBAC & Seed Isolation:\n";
    $sLoginGet = e2e_http($baseUrl . '/student/login.php', $studCookie);
    $sLoginPost = e2e_http($baseUrl . '/student/login.php', $studCookie, [
        'csrf_token' => e2e_csrf($sLoginGet['body']),
        'student_id' => $admAccepted['student_id'],
        'password'   => $admAccepted['portal_password'],
    ]);
    e2e_assert($sLoginPost['code'] === 200 && strpos($sLoginPost['url'], '/student/dashboard.php') !== false, "Student portal login must succeed");
    e2e_assert(strpos($sLoginPost['body'], 'Summer 2012') !== false && strpos($sLoginPost['body'], '2017') !== false, "Student dashboard must reflect Summer 2012 and Passing Year 2017");

    // Verify Student Profile displays read-only Academic Session, Passing Year, Advisory Expected Passing Year, Duration & Historical Status
    $profPg = e2e_http($baseUrl . '/student/profile.php', $studCookie);
    e2e_assert($profPg['code'] === 200, "student/profile.php must return 200");
    foreach (['Summer 2012', '2017', '2016', 'Verified Historical Record', 'Course Duration:', 'Expected Passing Year (Advisory Only)'] as $pChk) {
        e2e_assert(strpos($profPg['body'], $pChk) !== false, "student/profile.php missing expected field/notice: {$pChk}");
    }

    // Attempt forged Student POST to tamper with passing_year & academic_session -> MUST BE BLOCKED & AUDITED
    $tamperPost = e2e_http($baseUrl . '/student/profile.php', $studCookie, [
        'csrf_token'       => e2e_csrf($profPg['body']),
        'action'           => 'update_profile',
        'academic_session' => 'Fall 2029',
        'passing_year'     => '2029',
        'cgpa'             => '4.00',
    ]);
    e2e_assert(strpos($tamperPost['body'], 'cannot be modified by students') !== false, "Student tamper POST must be explicitly rejected in UI");
    $afterTamperSt = $db->query("SELECT academic_session, passing_year, cgpa FROM students WHERE id = {$stPk}")->fetch(PDO::FETCH_ASSOC);
    e2e_assert($afterTamperSt['academic_session'] === 'Summer 2012' && (string)$afterTamperSt['passing_year'] === '2017', "Forged student POST MUST NEVER change academic_session or passing_year in DB");
    $blockAuditCnt = (int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'STUDENT_ACADEMIC_EDIT_BLOCKED' AND details LIKE '%#{$stPk} %'")->fetchColumn();
    e2e_assert($blockAuditCnt >= 1, "Blocked student tamper attempt must be recorded in audit_logs");

    // Verify Digital ID Card uses real student session instead of hardcoded Fall 2026
    $idCardPg = e2e_http($baseUrl . '/student/id-card.php', $studCookie);
    e2e_assert($idCardPg['code'] === 200, "student/id-card.php must return 200");
    e2e_assert(strpos($idCardPg['body'], 'Semester Final: <strong>Summer 2012</strong>') !== false, "student/id-card.php must display student's actual Academic Session");
    e2e_assert(strpos($idCardPg['body'], 'Semester Final: <strong>Fall 2026</strong>') === false, "student/id-card.php must NOT hardcode Fall 2026");

    // Verify Transcript renders student session, passing year, and dynamic 8th semester cleanly
    $trPg = e2e_http($baseUrl . '/student/semester-transcript.php?semester=all', $studCookie);
    e2e_assert($trPg['code'] === 200 && strpos($trPg['body'], 'Summer 2012') !== false && strpos($trPg['body'], '2017') !== false, "student/semester-transcript.php must render Summer 2012 and Passing Year 2017");

    // Verify non-manager 'editor' admin role is blocked from academic-sessions.php mutations
    $edUser = 'e2e_sess_ed_' . $suffix;
    $edPass = 'EditorSess!' . $suffix;
    $db->prepare("INSERT INTO users (username, full_name, email, password_hash, role, status) VALUES (?, 'Session Test Editor', ?, ?, 'editor', 'active')")
       ->execute([$edUser, "sed.{$suffix}@rmu.test", password_hash($edPass, PASSWORD_DEFAULT)]);
    $tempEditorUserId = (int)$db->lastInsertId();

    $edLoginGet = e2e_http($baseUrl . '/admin/login.php', $editorCookie);
    e2e_http($baseUrl . '/admin/login.php', $editorCookie, [
        'csrf_token' => e2e_csrf($edLoginGet['body']),
        'username'   => $edUser,
        'password'   => $edPass,
    ]);
    $edSessAccess = e2e_http($baseUrl . '/admin/academic-sessions.php', $editorCookie);
    e2e_assert(strpos($edSessAccess['body'], 'Access denied') !== false || strpos($edSessAccess['url'], '/admin/dashboard.php') !== false, "Non-manager Editor role must be denied access to admin/academic-sessions.php");

    // Verify seed student RMU2601001 completely untouched
    $seedAfter = $db->query("SELECT id, student_id, cgpa, passing_year, academic_session, status FROM students WHERE id = {$seedId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $seedResAfter = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$seedId}")->fetchColumn();
    e2e_assert((float)$seedAfter['cgpa'] === (float)$seedBefore['cgpa'] && $seedResAfter === $seedResBefore && $seedAfter['status'] === $seedBefore['status'], "Seeded student RMU2601001 must remain 100% untouched");

    echo "   [PASS] Student portal read-only enforcement, dynamic ID card/transcript, Editor RBAC & Seed isolation verified.\n\n";
    echo "===============================================================================\n";
    echo ">>> ALL ACADEMIC SESSION & PASSING YEAR E2E TESTS PASSED (100% PASS)!        <<<\n";
    echo "===============================================================================\n";
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] " . $e->getMessage() . "\n");
    $exitCode = 1;
} finally {
    // Restore modified programs cleanly
    if (!empty($bbaOriginalRow)) {
        $db->prepare("UPDATE programs SET degree_type = ?, degree_level = ?, duration_years = ?, total_semesters = ?, total_credits = ?, duration = ?, tuition_fee = ? WHERE id = ?")
           ->execute([
               $bbaOriginalRow['degree_type'],
               $bbaOriginalRow['degree_level'],
               $bbaOriginalRow['duration_years'],
               $bbaOriginalRow['total_semesters'],
               $bbaOriginalRow['total_credits'],
               $bbaOriginalRow['duration'],
               $bbaOriginalRow['tuition_fee'],
               $bbaOriginalRow['id']
           ]);
        $db->prepare("UPDATE students SET degree_type = ?, duration_years = ?, program_duration = ?, total_semesters = ?, total_credits_required = ? WHERE program_id = ? AND status NOT IN ('Deleted', 'Graduated')")
           ->execute([
               $bbaOriginalRow['degree_type'],
               $bbaOriginalRow['duration_years'],
               $bbaOriginalRow['duration'],
               $bbaOriginalRow['total_semesters'],
               $bbaOriginalRow['total_credits'],
               $bbaOriginalRow['id']
           ]);
    }
    if (!empty($bedOriginalRow)) {
        $db->prepare("UPDATE programs SET degree_type = ?, degree_level = ?, duration_years = ?, total_semesters = ?, total_credits = ?, duration = ? WHERE id = ?")
           ->execute([
               $bedOriginalRow['degree_type'],
               $bedOriginalRow['degree_level'],
               $bedOriginalRow['duration_years'],
               $bedOriginalRow['total_semesters'],
               $bedOriginalRow['total_credits'],
               $bedOriginalRow['duration'],
               $bedOriginalRow['id']
           ]);
        $db->prepare("UPDATE students SET degree_type = ?, duration_years = ?, program_duration = ?, total_semesters = ?, total_credits_required = ? WHERE program_id = ? AND status NOT IN ('Deleted', 'Graduated')")
           ->execute([
               $bedOriginalRow['degree_type'],
               $bedOriginalRow['duration_years'],
               $bedOriginalRow['duration'],
               $bedOriginalRow['total_semesters'],
               $bedOriginalRow['total_credits'],
               $bedOriginalRow['id']
           ]);
    }
    // Restore original session/year enabled states
    if ($summer2013SessId > 0) {
        $db->prepare("UPDATE academic_sessions SET is_enabled = ? WHERE id = ?")->execute([$summer2013WasEnabled, $summer2013SessId]);
    }
    if ($year2013Id > 0) {
        $db->prepare("UPDATE passing_years SET is_enabled = ? WHERE id = ?")->execute([$year2013WasEnabled, $year2013Id]);
    }
    if ($originalCurrentSessionId > 0) {
        $db->exec("UPDATE academic_sessions SET is_current = 0");
        $db->prepare("UPDATE academic_sessions SET is_current = 1 WHERE id = ?")->execute([$originalCurrentSessionId]);
    }
    // Remove synthetic sessions & passing years created during E2E
    foreach ($createdSessionIds as $sid) {
        if ($sid > 0) {
            $db->prepare("DELETE FROM academic_edit_history WHERE entity_type = 'session' AND entity_id = ?")->execute([$sid]);
            $db->prepare("DELETE FROM academic_sessions WHERE id = ?")->execute([$sid]);
        }
    }
    foreach ($createdPassingYearIds as $pyid) {
        if ($pyid > 0) {
            $db->prepare("DELETE FROM academic_edit_history WHERE entity_type = 'passing_year' AND entity_id = ?")->execute([$pyid]);
            $db->prepare("DELETE FROM passing_years WHERE id = ?")->execute([$pyid]);
        }
    }
    // Clean up test students & linked child records
    foreach ($createdStudentPks as $pk) {
        if ($pk <= 0) continue;
        $db->prepare("DELETE FROM academic_edit_history WHERE entity_type = 'student' AND entity_id = ?")->execute([$pk]);
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
        $db->prepare("DELETE FROM academic_edit_history WHERE entity_type = 'admission' AND entity_id = ?")->execute([$aid]);
        $db->prepare("DELETE FROM audit_logs WHERE details LIKE ? OR details LIKE ?")->execute(["%application #{$aid}%", "%Admission #{$aid} %"]);
        $db->prepare("DELETE FROM admissions WHERE id = ?")->execute([$aid]);
    }
    $db->prepare("DELETE FROM admission_id_reservations WHERE id > ?")->execute([$maxResIdBeforeAcad]);
    foreach ($seqSnapshotAcad as $srAcad) {
        $db->prepare("UPDATE admission_sequences SET last_app_seq = ?, last_roll_seq = ?, updated_at = NOW() WHERE seq_year = ?")
           ->execute([(int)$srAcad['last_app_seq'], (int)$srAcad['last_roll_seq'], (int)$srAcad['seq_year']]);
    }
    $db->prepare("DELETE FROM academic_edit_history WHERE reason LIKE ?")->execute(["%{$suffix}%"]);
    $db->prepare("DELETE FROM audit_logs WHERE details LIKE ?")->execute(["%{$suffix}%"]);
    if ($tempEditorUserId > 0) {
        $db->prepare("DELETE FROM audit_logs WHERE user_id = ?")->execute([$tempEditorUserId]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$tempEditorUserId]);
    }
    foreach ([$adminCookie, $editorCookie, $studCookie, $anonCookie] as $cf) {
        if (file_exists($cf)) @unlink($cf);
    }
}
exit($exitCode);
