<?php
/**
 * Comprehensive End-to-End Integration Test:
 * RMU Academic Session (2011+), Passing Year (2011+), Online Application Year (2015+),
 * 6-Field Online Admission Form, ৳150 Payment Flow, Admin Pending Applications & Dossier Audit,
 * Accept / Reject Provisioning, and Student Profile Original-vs-Corrected Record Integration.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = 'http://127.0.0.1:8000';
$db = getDb();

$passed = 0;
$failed = 0;

function oai_assert(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

function oai_http(string $url, string $cookieFile, ?array $postData = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 6,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_USERAGENT      => 'RMU-OnlineAdmissionIntegration-E2E/1.0',
    ]);
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $eff  = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    return ['code' => $code, 'url' => $eff, 'body' => (string)$body, 'error' => $err];
}

function oai_csrf(string $html): string {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/value="([^"]+)"\s+name="csrf_token"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

$cookieDir = sys_get_temp_dir();
$anonCookie    = $cookieDir . '/rmu_oai_anon_' . getmypid() . '.txt';
$adminCookie   = $cookieDir . '/rmu_oai_admin_' . getmypid() . '.txt';
$studentCookie = $cookieDir . '/rmu_oai_student_' . getmypid() . '.txt';
foreach ([$anonCookie, $adminCookie, $studentCookie] as $cf) {
    @unlink($cf);
}

$suffix = substr((string)time(), -5) . rand(10, 99);
$createdAdmIds = [];
$createdStudentPks = [];
$createdStudentCodes = [];
$createdAdmRefs = [];

// Record baseline admission IDs & sequence state so we confirm existing valid records remain untouched
$baselineAdmIds = $db->query("SELECT id FROM admissions ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
ensure_admission_id_generation_schema($db);
$seqSnapshotOai = $db->query("SELECT seq_year, last_app_seq, last_roll_seq FROM admission_sequences")->fetchAll(PDO::FETCH_ASSOC);
$maxResIdBeforeOai = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM admission_id_reservations")->fetchColumn();

try {
    echo "\n=== RMU Online Admission Integration End-to-End Test ===\n\n";

    // Locate target programs
    $cseProg = $db->query("SELECT * FROM programs WHERE title_en LIKE '%Computer Science%' OR title_en LIKE '%CSE%' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $mastersProg = $db->query("SELECT * FROM programs WHERE degree_type = \"Master's\" ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    oai_assert(!empty($cseProg) && !empty($mastersProg), "Active CSE (Bachelor) and Master's programs exist in database");

    // 1. Inspect Online Admission Form
    echo "1. Verifying Online Admission Form Integration (6 Fields + 2011 Sessions/Passing + 2015+ App Year):\n";
    $formPage = oai_http($baseUrl . '/admission-form.php', $anonCookie);
    oai_assert($formPage['code'] === 200, "admission-form.php loads with HTTP 200");
    $formHtml = $formPage['body'];
    $formCsrf = oai_csrf($formHtml);
    oai_assert($formCsrf !== '', "admission-form.php renders valid CSRF token");

    foreach ([
        'applicationYearSelect',
        'degreeTypeSelect',
        'academicSessionSelect',
        'passingYearSelect',
        'programSelect',
        'courseDurationDisplay',
        'totalSemestersDisplay',
        'expectedPassingYearDisplay',
        'Spring 2011',
        'name="quota"',
    ] as $expectedEl) {
        oai_assert(strpos($formHtml, $expectedEl) !== false, "Admission form contains required integrated field/option '{$expectedEl}'");
    }

    foreach ([
        'academicDocsInput',
        'name="academic_documents"',
        'Academic Documents',
        'শিক্ষাগত সনদ ও মার্কশীট',
        'Upload your SSC/HSC transcript, certificate or testimonial',
    ] as $removedDocNeedle) {
        oai_assert(strpos($formHtml, $removedDocNeedle) === false, "Admission form omits removed Academic Documents element '{$removedDocNeedle}'");
    }

    // Verify applicationYearSelect starts at 2015 (does not include 2014)
    $hasApp2014 = false;
    $hasApp2015 = false;
    if (preg_match('/<select[^>]*id="applicationYearSelect"[^>]*>(.*?)<\/select>/s', $formHtml, $aySelectMatch)) {
        $hasApp2014 = (strpos($aySelectMatch[1], 'value="2014"') !== false);
        $hasApp2015 = (strpos($aySelectMatch[1], 'value="2015"') !== false);
    }
    oai_assert(!$hasApp2014 && $hasApp2015, "Application Record Year dropdown starts strictly from 2015 (2015 present, 2014 absent)");

    // Ensure Spring 2011, Summer 2011 and passing years 2016, 2026 are enabled
    $enabledSessCheck = is_academic_session_enabled('Spring 2011') && is_academic_session_enabled('Summer 2011') && is_academic_session_enabled('Fall 2024');
    $enabledPassCheck = is_passing_year_enabled(2016) && is_passing_year_enabled(2026);
    oai_assert($enabledSessCheck && $enabledPassCheck, "Required Academic Sessions (2011+) and Passing Years are enabled");

    // 2. Reject invalid application_year < 2015
    echo "\n2. Testing Application Submission Validation (<2015 App Year Blocked) & Valid Submission:\n";
    $badMob = '017' . rand(10000000, 99999999);
    $badSubmit = oai_http($baseUrl . '/actions/submit-admission.php', $anonCookie, [
        'csrf_token'         => $formCsrf,
        'full_name'          => "Invalid Year Test {$suffix}",
        'application_year'   => '2014',
        'roll_number'        => "ROLL-BAD-{$suffix}",
        'father_name'        => 'Test Father',
        'mother_name'        => 'Test Mother',
        'date_of_birth'      => '2003-04-12',
        'gender'             => 'Male',
        'mobile'             => $badMob,
        'email'              => "bad.{$suffix}@rmu.test",
        'present_address'    => 'Kushtia',
        'permanent_address'  => 'Kushtia',
        'last_college'       => 'Kushtia Govt College',
        'home_district'      => 'Kushtia',
        'ssc_exam'           => 'S.S.C',
        'ssc_roll'           => '112233',
        'ssc_group'          => 'Science',
        'ssc_board'          => 'Dinajpur',
        'ssc_result_type'    => 'GPA(out of 5)',
        'ssc_gpa'            => '5.00',
        'ssc_year'           => '2011',
        'hsc_exam'           => 'H.S.C',
        'hsc_roll'           => '445566',
        'hsc_group'          => 'Science',
        'hsc_board'          => 'Dinajpur',
        'hsc_result_type'    => 'GPA(out of 5)',
        'hsc_gpa'            => '4.90',
        'hsc_year'           => '2014',
        'degree_type'        => 'Bachelor / Honours',
        'department_id'      => (int)$cseProg['department_id'],
        'program_id'         => (int)$cseProg['id'],
        'interested_subject' => $cseProg['title_en'],
        'academic_session'   => 'Spring 2011',
        'passing_year'       => '2016',
        'quota'              => 'NO',
    ]);
    $badRowCount = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE mobile = " . $db->quote($badMob))->fetchColumn();
    oai_assert($badRowCount === 0 && strpos($badSubmit['url'], 'admission-form.php') !== false, "Submission with application_year=2014 (<2015) is rejected without saving");

    // Fresh CSRF and valid Application A submission (App Year 2015, Spring 2011, Passing Year 2016 vs Expected 2015)
    $formPage2 = oai_http($baseUrl . '/admission-form.php', $anonCookie);
    $csrfA = oai_csrf($formPage2['body']);
    preg_match('/name="draft_token"[^>]*value="([^"]*)"/', $formPage2['body'], $dtMatchA);
    preg_match('/id="applicationIdInput"[^>]*value="([^"]*)"/', $formPage2['body'], $appValMatchA);
    preg_match('/id="applicantRollInput"[^>]*value="([^"]*)"/', $formPage2['body'], $rollValMatchA);
    $reservedAppA = $appValMatchA[1] ?? '';
    $reservedRollA = $rollValMatchA[1] ?? '';
    oai_assert(preg_match('/^RMU-\d{4}-\d{4,}$/', $reservedAppA) === 1, "admission-form.php auto-generates sequential Application ID (got: {$reservedAppA})");
    oai_assert(preg_match('/^\d{4}-\d{4,}$/', $reservedRollA) === 1, "admission-form.php auto-generates sequential Admission Roll Number (got: {$reservedRollA})");
    $mobA = '018' . rand(10000000, 99999999);

    $subA = oai_http($baseUrl . '/actions/submit-admission.php', $anonCookie, [
        'csrf_token'         => $csrfA,
        'draft_token'        => $dtMatchA[1] ?? '',
        'full_name'          => "Integrated Applicant A {$suffix}",
        'application_id'     => "SPOOFED-APP-{$suffix}",
        'application_year'   => '2015',
        'roll_number'        => "SPOOFED-ROLL-{$suffix}",
        'father_name'        => 'Abdul Karim',
        'mother_name'        => 'Rahima Khatun',
        'date_of_birth'      => '1993-02-10',
        'gender'             => 'Male',
        'mobile'             => $mobA,
        'email'              => "inta.{$suffix}@rmu.test",
        'present_address'    => 'Kushtia Sadar',
        'permanent_address'  => 'Kushtia Sadar',
        'last_college'       => 'Kushtia Govt College',
        'home_district'      => 'Kushtia',
        'ssc_exam'           => 'S.S.C',
        'ssc_roll'           => '100001',
        'ssc_group'          => 'Science',
        'ssc_board'          => 'Jashore',
        'ssc_result_type'    => 'GPA(out of 5)',
        'ssc_gpa'            => '5.00',
        'ssc_year'           => '2008',
        'hsc_exam'           => 'H.S.C',
        'hsc_roll'           => '200001',
        'hsc_group'          => 'Science',
        'hsc_board'          => 'Jashore',
        'hsc_result_type'    => 'GPA(out of 5)',
        'hsc_gpa'            => '4.95',
        'hsc_year'           => '2010',
        'degree_type'        => 'Bachelor / Honours',
        'department_id'      => (int)$cseProg['department_id'],
        'program_id'         => (int)$cseProg['id'],
        'interested_subject' => $cseProg['title_en'],
        'academic_session'   => 'Spring 2011',
        'passing_year'       => '2016',
        'quota'              => 'NO',
    ]);
    oai_assert(strpos($subA['url'], 'admission-payment.php?ref=') !== false, "Valid Application A redirects to admission-payment.php");

    $stmtA = $db->prepare("SELECT * FROM admissions WHERE mobile = ? ORDER BY id DESC LIMIT 1");
    $stmtA->execute([$mobA]);
    $admA = $stmtA->fetch(PDO::FETCH_ASSOC);
    oai_assert(!empty($admA), "Application A persisted in database");
    oai_assert($admA['application_ref'] === $reservedAppA && $admA['application_id'] === $reservedAppA, "Backend assigned reserved Application ID {$reservedAppA}, ignoring spoofed value");
    oai_assert($admA['roll_number'] === $reservedRollA && $admA['exam_roll'] === $reservedRollA, "Backend assigned reserved Roll Number {$reservedRollA}, ignoring spoofed value");
    $appIdA = $admA['application_ref'];
    $rollA  = $admA['roll_number'];
    $createdAdmIds[] = (int)$admA['id'];
    $createdAdmRefs[] = $admA['application_ref'];

    oai_assert((int)$admA['application_year'] === 2015, "Application A saved application_year = 2015 independently");
    oai_assert($admA['academic_session'] === 'Spring 2011', "Application A saved academic_session = 'Spring 2011'");
    oai_assert((string)$admA['passing_year'] === '2016', "Application A preserved Actual Passing Year = 2016 (never auto-overwritten)");
    oai_assert((int)$admA['expected_passing_year'] === 2015, "Application A computed Expected Passing Year = 2015 (2011 + 4)");
    oai_assert(abs((float)$admA['duration_years'] - 4.0) < 0.01 && (int)$admA['total_semesters'] === 8, "Application A stored Course Duration 4.0 years and 8 Semesters");
    $snapA = json_decode((string)$admA['original_submission_json'], true);
    oai_assert(is_array($snapA) && $snapA['academic_session'] === 'Spring 2011' && (string)$snapA['passing_year'] === '2016', "Immutable original_submission_json captured exact submitted values");

    // Also submit Application B (Master's, Reject case)
    $formPage3 = oai_http($baseUrl . '/admission-form.php', $anonCookie);
    $csrfB = oai_csrf($formPage3['body']);
    preg_match('/name="draft_token"[^>]*value="([^"]*)"/', $formPage3['body'], $dtMatchB);
    $mobB = '019' . rand(10000000, 99999999);
    oai_http($baseUrl . '/actions/submit-admission.php', $anonCookie, [
        'csrf_token'         => $csrfB,
        'draft_token'        => $dtMatchB[1] ?? '',
        'full_name'          => "Integrated Applicant B {$suffix}",
        'application_id'     => "SPOOFED-B-{$suffix}",
        'application_year'   => '2026',
        'roll_number'        => "SPOOFED-ROLL-B-{$suffix}",
        'father_name'        => 'Mahmudul Hasan',
        'mother_name'        => 'Salma Begum',
        'date_of_birth'      => '2001-06-15',
        'gender'             => 'Female',
        'mobile'             => $mobB,
        'email'              => "intb.{$suffix}@rmu.test",
        'present_address'    => 'Rajshahi',
        'permanent_address'  => 'Rajshahi',
        'last_college'       => 'Rajshahi College',
        'home_district'      => 'Rajshahi',
        'ssc_exam'           => 'S.S.C',
        'ssc_roll'           => '300001',
        'ssc_group'          => 'Humanities',
        'ssc_board'          => 'Rajshahi',
        'ssc_result_type'    => 'GPA(out of 5)',
        'ssc_gpa'            => '4.50',
        'ssc_year'           => '2017',
        'hsc_exam'           => 'H.S.C',
        'hsc_roll'           => '400001',
        'hsc_group'          => 'Humanities',
        'hsc_board'          => 'Rajshahi',
        'hsc_result_type'    => 'GPA(out of 5)',
        'hsc_gpa'            => '4.60',
        'hsc_year'           => '2019',
        'degree_type'        => "Master's",
        'department_id'      => (int)$mastersProg['department_id'],
        'program_id'         => (int)$mastersProg['id'],
        'interested_subject' => $mastersProg['title_en'],
        'academic_session'   => 'Fall 2024',
        'passing_year'       => '2026',
        'quota'              => 'NO',
    ]);
    $stmtB = $db->prepare("SELECT * FROM admissions WHERE mobile = ? ORDER BY id DESC LIMIT 1");
    $stmtB->execute([$mobB]);
    $admB = $stmtB->fetch(PDO::FETCH_ASSOC);
    oai_assert(!empty($admB) && $admB['application_ref'] !== $admA['application_ref'] && $admB['roll_number'] !== $admA['roll_number'], "Application B persisted with distinct sequential Application ID & Roll Number");
    $createdAdmIds[] = (int)$admB['id'];
    $createdAdmRefs[] = $admB['application_ref'];

    // 3. Complete ৳150 Payment Verification Flow via Sandbox Gateway & Check Application Status
    echo "\n3. Testing ৳150 Sandbox Payment Gateway Verification & Application Status Display:\n";
    $payPg = oai_http($baseUrl . '/admission-payment.php?ref=' . urlencode($admA['application_ref']), $anonCookie);
    oai_assert($payPg['code'] === 200 && strpos($payPg['body'], 'Spring 2011') !== false && strpos($payPg['body'], '4 Years (8 Semesters)') !== false, "admission-payment.php displays Session & Course Duration/Semesters");
    oai_assert(strpos($payPg['body'], 'name="transaction_id"') === false, "admission-payment.php does NOT prompt applicant for Transaction ID");
    $payCsrf = oai_csrf($payPg['body']);
    preg_match('/name="idempotency_key"\s+value="([^"]+)"/', $payPg['body'], $idemMatch);
    $idemKey = $idemMatch[1] ?? '';

    $gwPage = oai_http($baseUrl . '/actions/process-admission-payment.php', $anonCookie, [
        'csrf_token'      => $payCsrf,
        'action'          => 'initiate_sandbox_payment',
        'ref'             => $admA['application_ref'],
        'idempotency_key' => $idemKey,
        'payment_method'  => 'bKash',
        'sender_number'   => $mobA,
    ]);
    oai_assert($gwPage['code'] === 200 && strpos($gwPage['url'], 'sandbox-gateway.php?session=') !== false, "Payment form launches Sandbox Payment Gateway checkout session");
    preg_match('/session=([A-Za-z0-9_-]+)/', $gwPage['url'], $sessMatch);
    preg_match('/id="sandboxTestOtpBadge"[^>]*>\s*([0-9]{6})\s*</', $gwPage['body'], $otpMatch);
    $sbxSession = $sessMatch[1] ?? '';
    $sbxOtp = $otpMatch[1] ?? '';
    oai_assert($sbxSession !== '' && strlen($sbxOtp) === 6, "Sandbox Gateway generated active session ({$sbxSession}) and 6-digit OTP");

    $payPost = oai_http($baseUrl . '/sandbox-gateway.php?session=' . urlencode($sbxSession), $anonCookie, [
        'csrf_token'  => oai_csrf($gwPage['body']),
        'session'     => $sbxSession,
        'gw_action'   => 'authorize',
        'sandbox_otp' => $sbxOtp,
    ]);
    oai_assert(strpos($payPost['url'], 'admission-status.php?ref=') !== false, "Sandbox Gateway verification redirects to admission-status.php");
    oai_assert(strpos($payPost['body'], 'admitCardPrint') !== false, "Official Application Card is generated and displayed immediately after gateway verification");

    $afterPayA = $db->query("SELECT status, payment_status, student_id FROM admissions WHERE id = " . (int)$admA['id'])->fetch(PDO::FETCH_ASSOC);
    oai_assert($afterPayA['payment_status'] === 'Paid' && $afterPayA['status'] === 'Under Review' && empty($afterPayA['student_id']), "Payment Verified -> Under Review, Student account NOT yet created pre-acceptance");

    $statusPg = oai_http($baseUrl . '/admission-status.php?ref=' . urlencode($admA['application_ref']), $anonCookie);
    oai_assert(strpos($statusPg['body'], 'Pending Admin Acceptance') !== false && strpos($statusPg['body'], 'RMU2601004') === false, "admission-status.php shows 'Pending Admin Acceptance' without fake fallback Student ID");
    oai_assert(strpos($statusPg['body'], 'Login to Student Portal') === false, "admission-status.php hides 'Login to Student Portal' button prior to Admin acceptance");
    oai_assert(strpos($statusPg['body'], '8 Semesters') !== false && strpos($statusPg['body'], '2016') !== false && strpos($statusPg['body'], '2015') !== false, "admission-status.php displays Duration/Semesters, Actual 2016, Expected 2015");

    // 4. Admin Pending Queue, Mismatch Warning, Official Academic Edit & Accept/Reject
    echo "\n4. Testing Admin Pending Applications, Mismatch Warning, Official Edit & Accept/Reject:\n";
    $adminLoginGet = oai_http($baseUrl . '/admin/login.php', $adminCookie);
    $adminLoginPost = oai_http($baseUrl . '/admin/login.php', $adminCookie, [
        'csrf_token' => oai_csrf($adminLoginGet['body']),
        'username'   => 'admin',
        'password'   => 'Admin@RMU2026!',
    ]);
    oai_assert($adminLoginPost['code'] === 200 && strpos($adminLoginPost['url'], '/admin/dashboard.php') !== false, "Admin login succeeds");

    $queuePg = oai_http($baseUrl . '/admin/admissions.php?status=PendingQueue&app_year=2015&search=' . urlencode($appIdA), $adminCookie);
    oai_assert($queuePg['code'] === 200, "Admin Pending Applications Queue with App Year 2015 filter returns 200");
    foreach ([$admA['application_ref'], $appIdA, $mobA, 'Spring 2011', '4 Years (8 Semesters)', '8 Semesters', '2015', '2016', 'Mismatch: Differs (+1 yr)', '৳150 Paid', 'Under Review'] as $needle) {
        oai_assert(strpos($queuePg['body'], $needle) !== false, "PendingQueue displays '{$needle}' cleanly");
    }

    // Perform Official Academic Edit (change session to Summer 2011, duration to 3.0 years, total_semesters to 6, keeping passing_year=2016)
    $admCsrf = oai_csrf($queuePg['body']);
    $editReason = "E2E curriculum credit exemption verified {$suffix}";
    $editRes = oai_http($baseUrl . '/admin/admissions.php?status=PendingQueue', $adminCookie, [
        'csrf_token'       => $admCsrf,
        'return_status'    => 'PendingQueue',
        'action'           => 'official_academic_edit',
        'id'               => (int)$admA['id'],
        'application_year' => '2015',
        'academic_session' => 'Summer 2011',
        'passing_year'     => '2016',
        'program_id'       => (int)$cseProg['id'],
        'degree_type'      => 'Bachelor / Honours',
        'duration_years'   => '3.0',
        'total_semesters'  => '6',
        'reason'           => $editReason,
    ]);
    oai_assert($editRes['code'] === 200, "Official Academic Edit POST returned 200");

    $admAAfterEdit = $db->query("SELECT * FROM admissions WHERE id = " . (int)$admA['id'])->fetch(PDO::FETCH_ASSOC);
    oai_assert($admAAfterEdit['academic_session'] === 'Summer 2011', "Admin edit updated academic_session to 'Summer 2011'");
    oai_assert(abs((float)$admAAfterEdit['duration_years'] - 3.0) < 0.01 && (int)$admAAfterEdit['total_semesters'] === 6, "Admin edit updated duration_years=3.0 & total_semesters=6");
    oai_assert((int)$admAAfterEdit['expected_passing_year'] === 2014, "Admin edit recalculated Expected Passing Year = 2014 (2011 + 3)");
    oai_assert((string)$admAAfterEdit['passing_year'] === '2016', "Admin edit preserved Actual Passing Year = 2016");

    $snapAfterEdit = json_decode((string)$admAAfterEdit['original_submission_json'], true);
    oai_assert(
        is_array($snapAfterEdit)
        && $snapAfterEdit['academic_session'] === 'Spring 2011'
        && (int)$snapAfterEdit['total_semesters'] === 8
        && (int)$snapAfterEdit['expected_passing_year'] === 2015,
        "Original applicant submission snapshot remains IMMUTABLE after Admin edit"
    );

    $auditCount = (int)$db->query("SELECT COUNT(*) FROM academic_edit_history WHERE entity_type = 'admission' AND entity_id = " . (int)$admA['id'] . " AND reason = " . $db->quote($editReason))->fetchColumn();
    oai_assert($auditCount >= 1, "Timestamped audit log rows recorded in academic_edit_history with mandatory reason");

    // Reject Application B
    $queuePageForTokens = oai_http($baseUrl . '/admin/admissions.php', $adminCookie);
    $rejCsrf = oai_csrf($queuePageForTokens['body']);
    $rejPost = oai_http($baseUrl . '/admin/admissions.php', $adminCookie, [
        'csrf_token'  => $rejCsrf,
        'action'      => 'reject_application',
        'id'          => (int)$admB['id'],
        'admin_notes' => 'E2E Integration Test Reject Verification',
    ]);
    oai_assert($rejPost['code'] === 200, "Reject Application POST returned 200");
    $admBAfterRej = $db->query("SELECT status, student_id FROM admissions WHERE id = " . (int)$admB['id'])->fetch(PDO::FETCH_ASSOC);
    oai_assert($admBAfterRej['status'] === 'Rejected' && empty($admBAfterRej['student_id']), "Rejected application does NOT provision Student Portal account");

    // Accept Application A
    $accCsrf = oai_csrf($rejPost['body']);
    $accPost = oai_http($baseUrl . '/admin/admissions.php', $adminCookie, [
        'csrf_token'  => $accCsrf,
        'action'      => 'accept_application',
        'id'          => (int)$admA['id'],
        'admin_notes' => 'Accepted after academic audit verification',
    ]);
    oai_assert($accPost['code'] === 200, "Accept Application POST returned 200");

    $admAAccepted = $db->query("SELECT * FROM admissions WHERE id = " . (int)$admA['id'])->fetch(PDO::FETCH_ASSOC);
    oai_assert($admAAccepted['status'] === 'Accepted' && !empty($admAAccepted['student_id']) && !empty($admAAccepted['portal_password']), "Accepted Application A provisioned Student ID & portal credentials");

    $stCodeA = (string)$admAAccepted['student_id'];
    $stPassA = (string)$admAAccepted['portal_password'];
    $createdStudentCodes[] = $stCodeA;

    $stStmt = $db->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
    $stStmt->execute([$stCodeA]);
    $studentA = $stStmt->fetch(PDO::FETCH_ASSOC);
    oai_assert(!empty($studentA), "Student record exists in students table");
    $createdStudentPks[] = (int)$studentA['id'];

    oai_assert($studentA['academic_session'] === 'Summer 2011', "Transferred Admin-verified Academic Session ('Summer 2011') to student profile");
    oai_assert(abs((float)$studentA['duration_years'] - 3.0) < 0.01 && (int)$studentA['total_semesters'] === 6, "Transferred edited Duration (3.0 yrs) & Total Semesters (6) to student profile");
    oai_assert((int)$studentA['expected_passing_year'] === 2014 && (string)$studentA['passing_year'] === '2016', "Transferred Expected (2014) and Actual (2016) Passing Years accurately");
    $semCountA = (int)$db->query("SELECT COUNT(*) FROM student_semester_results WHERE student_id = " . (int)$studentA['id'])->fetchColumn();
    oai_assert($semCountA === 6, "Student semester structure table provisioned 6 semesters matching edited duration/semester structure");

    // Verify status card shows Login to Student Portal only after Accepted & sync_existing_student_from_admission works via update_status
    $statusAcceptedPg = oai_http($baseUrl . '/admission-status.php?ref=' . urlencode($admA['application_ref']), $anonCookie);
    oai_assert(strpos($statusAcceptedPg['body'], 'Login to Student Portal') !== false && strpos($statusAcceptedPg['body'], $stCodeA) !== false, "admission-status.php reveals 'Login to Student Portal' button and Student ID after acceptance");

    $db->prepare("UPDATE admissions SET academic_session = 'Fall 2011', expected_passing_year = 2014 WHERE id = ?")->execute([(int)$admA['id']]);
    $updStatPost = oai_http($baseUrl . '/admin/admissions.php', $adminCookie, [
        'csrf_token'  => oai_csrf($accPost['body']),
        'action'      => 'update_status',
        'id'          => (int)$admA['id'],
        'status'      => 'Accepted',
        'admin_notes' => 'Sync test',
    ]);
    $stSyncedSession = (string)$db->query("SELECT academic_session FROM students WHERE id = " . (int)$studentA['id'])->fetchColumn();
    oai_assert($updStatPost['code'] === 200 && $stSyncedSession === 'Fall 2011', "update_status -> Accepted syncs verified academic fields to already-linked student via sync_existing_student_from_admission()");
    // Restore Application A + Student A back to Summer 2011 for remaining profile assertions
    $db->prepare("UPDATE admissions SET academic_session = 'Summer 2011', expected_passing_year = 2014 WHERE id = ?")->execute([(int)$admA['id']]);
    sync_existing_student_from_admission((int)$admA['id']);

    // 5. Student Portal Login & Profile Verification + Read-Only Guard
    echo "\n5. Testing Student Portal Profile Integration & Original-vs-Corrected Record Display:\n";
    $stLoginGet = oai_http($baseUrl . '/student/login.php', $studentCookie);
    $stLoginPost = oai_http($baseUrl . '/student/login.php', $studentCookie, [
        'csrf_token' => oai_csrf($stLoginGet['body']),
        'student_id' => $stCodeA,
        'password'   => $stPassA,
    ]);
    oai_assert($stLoginPost['code'] === 200 && strpos($stLoginPost['url'], '/student/') !== false && strpos($stLoginPost['url'], 'login.php') === false, "Provisioned student can log in to Student Portal");

    $profGet = oai_http($baseUrl . '/student/profile.php', $studentCookie);
    oai_assert($profGet['code'] === 200, "student/profile.php loads with HTTP 200");
    $profBody = $profGet['body'];

    foreach ([
        'Course Duration:',
        '3 Years (6 Semesters)',
        'Total Semesters:',
        '6 Semesters',
        'Semester 6',
        'Summer 2011',
        'Expected Passing Year (Advisory Only):',
        '2014',
        'Actual Passing Year (Student Selected):',
        '2016',
        'Original Submission Record',
        'Original Application Baseline:',
        'Spring 2011',
        '4 Years (8 Semesters)',
        'Recorded Admin Corrections:',
        $editReason,
    ] as $profNeedle) {
        oai_assert(strpos($profBody, $profNeedle) !== false, "Student profile displays '{$profNeedle}'");
    }
    oai_assert(strpos($profBody, 'Semester 7') === false && strpos($profBody, 'Semester 8') === false, "Student profile semester grid respects edited 6 semesters (no Semester 7/8)");

    // Verify Digital Student ID Card reflects Session, Duration, Passing Year, App ID, Roll & Dynamic Validity Year
    $idCardPg = oai_http($baseUrl . '/student/id-card.php', $studentCookie);
    oai_assert($idCardPg['code'] === 200, "student/id-card.php loads with HTTP 200");
    foreach (['Academic Session:', 'Summer 2011', 'Course Duration:', '3 Years (6 Semesters)', 'Passing Year:', '2016', 'VALID TILL 2014', $appIdA, $rollA] as $idCardNeedle) {
        oai_assert(strpos($idCardPg['body'], $idCardNeedle) !== false, "student/id-card.php renders '{$idCardNeedle}'");
    }

    // Verify Student Dashboard displays Course Duration, Session, Passing Year, App ID & Roll cleanly
    $stuDashPg = oai_http($baseUrl . '/student/dashboard.php', $studentCookie);
    oai_assert(
        $stuDashPg['code'] === 200
        && strpos($stuDashPg['body'], 'Duration: 3 Years (6 Semesters)') !== false
        && strpos($stuDashPg['body'], 'Summer 2011') !== false
        && strpos($stuDashPg['body'], 'Passing Year: 2016') !== false
        && strpos($stuDashPg['body'], $appIdA) !== false
        && strpos($stuDashPg['body'], $rollA) !== false,
        "student/dashboard.php renders verified Course Duration, Session, Passing Year, Application ID, and Roll tags"
    );

    // Attempt unauthorized Student POST tamper on profile
    $profCsrf = oai_csrf($profBody);
    $tamperPost = oai_http($baseUrl . '/student/profile.php', $studentCookie, [
        'csrf_token'       => $profCsrf,
        'academic_session' => 'Fall 2099',
        'passing_year'     => '2099',
        'duration_years'   => '1.0',
        'total_semesters'  => '2',
        'application_year' => '2014',
    ]);
    $stAfterTamper = $db->query("SELECT academic_session, passing_year, total_semesters FROM students WHERE id = " . (int)$studentA['id'])->fetch(PDO::FETCH_ASSOC);
    oai_assert(
        strpos($tamperPost['body'], 'cannot be modified by students') !== false
        && $stAfterTamper['academic_session'] === 'Summer 2011'
        && (string)$stAfterTamper['passing_year'] === '2016'
        && (int)$stAfterTamper['total_semesters'] === 6,
        "Student Portal read-only guard blocked unauthorized mutation of academic fields"
    );

    // 6. Cross-Module Regression Checks (Dashboard, Payments, Academic Sessions HTML, Programs & Doc URL Helper)
    echo "\n6. Testing Dashboard, Payments, Programs, HTML5 Forms & Document URL Security:\n";
    $dashPg = oai_http($baseUrl . '/admin/dashboard.php', $adminCookie);
    oai_assert($dashPg['code'] === 200 && strpos($dashPg['body'], 'status=PendingQueue') !== false, "admin/dashboard.php loads with PendingQueue review link");

    $payAdminPg = oai_http($baseUrl . '/admin/payments.php?tab=admissions&status=Paid&search=' . urlencode($admA['application_ref']), $adminCookie);
    oai_assert($payAdminPg['code'] === 200 && strpos($payAdminPg['body'], 'admissions.php?search=' . urlencode($admA['application_ref'])) !== false, "admin/payments.php renders verified fee row with cross-link to Admission review");

    $sessProgPg = oai_http($baseUrl . '/admin/academic-sessions.php?tab=programs', $adminCookie);
    oai_assert(
        $sessProgPg['code'] === 200
        && preg_match('/<form id="sessProgForm\d+"/i', $sessProgPg['body']) === 1
        && preg_match('/form="sessProgForm\d+"/i', $sessProgPg['body']) === 1
        && preg_match('/<tr[^>]*>\s*<form/i', $sessProgPg['body']) === 0,
        "admin/academic-sessions.php?tab=programs uses valid HTML5 external form= attributes (no <form> inside <tr>)"
    );

    $programsPg = oai_http($baseUrl . '/programs.php', $anonCookie);
    oai_assert(
        $programsPg['code'] === 200
        && strpos($programsPg['body'], '1 Year (2 Semesters)') !== false
        && strpos($programsPg['body'], '2 Years (4 Semesters)') !== false
        && strpos($programsPg['body'], '4-Year Subject Curriculums') === false
        && strpos($programsPg['body'], '4-Year Academic Subjects (Curriculum)') === false,
        "programs.php dynamically displays non-4-year B.Ed/Master's durations without blanket 4-year text"
    );

    $admStatusCol = $db->query("SHOW COLUMNS FROM admissions LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    oai_assert(
        $admStatusCol && strpos((string)($admStatusCol['Type'] ?? ''), 'Additional Information Required') !== false,
        "admissions.status ENUM supports Additional Information Required alongside Pending/Submitted/Under Review/Accepted/Rejected"
    );
    $admProgPg = oai_http($baseUrl . '/admin/programs.php', $adminCookie);
    oai_assert(
        $admProgPg['code'] === 200
        && strpos($admProgPg['body'], 'rmu-prog-2col') !== false
        && strpos($admProgPg['body'], 'rmu-prog-3col') !== false
        && strpos($admProgPg['body'], 'rmu-prog-4col') !== false,
        "admin/programs.php renders responsive mobile grid classes cleanly"
    );

    $tempDocName = "oai_doc_test_{$suffix}.pdf";
    $tempDocDir = BASE_PATH . '/storage/uploads/documents';
    if (!is_dir($tempDocDir)) {
        @mkdir($tempDocDir, 0775, true);
    }
    $tempDocPath = $tempDocDir . '/' . $tempDocName;
    file_put_contents($tempDocPath, "%PDF-1.4\n%test\n");
    oai_assert(
        get_admission_document_url('../../etc/passwd') === null
        && get_admission_document_url($tempDocName) === (APP_URL . '/storage/uploads/documents/' . $tempDocName)
        && get_admission_document_url('storage/uploads/documents/' . $tempDocName) === (APP_URL . '/storage/uploads/documents/' . $tempDocName),
        "get_admission_document_url() resolves bare filenames & storage paths while blocking path traversal"
    );
    @unlink($tempDocPath);

    // 7. Verify existing baseline records untouched
    echo "\n7. Verifying Existing Valid Records Preservation:\n";
    $allPresentAfter = $db->query("SELECT id FROM admissions ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    $missingBaseline = array_diff($baselineAdmIds, $allPresentAfter);
    oai_assert(empty($missingBaseline), "All pre-existing admission application records remain intact in DB");

} finally {
    if (!empty($tempDocPath) && file_exists($tempDocPath)) {
        @unlink($tempDocPath);
    }
    echo "\n8. Cleaning up temporary E2E test records:\n";
    foreach ($createdStudentPks as $spPk) {
        $db->prepare("DELETE FROM student_semester_results WHERE student_id = ?")->execute([$spPk]);
        $db->prepare("DELETE FROM student_results WHERE student_id = ?")->execute([$spPk]);
        $db->prepare("DELETE FROM student_courses WHERE student_id = ?")->execute([$spPk]);
        $db->prepare("DELETE FROM student_notifications WHERE student_id = ?")->execute([$spPk]);
        $db->prepare("DELETE FROM result_requests WHERE student_id = ?")->execute([$spPk]);
        $db->prepare("DELETE FROM academic_edit_history WHERE entity_type = 'student' AND entity_id = ?")->execute([$spPk]);
        $db->prepare("DELETE FROM students WHERE id = ?")->execute([$spPk]);
    }
    foreach ($createdStudentCodes as $sc) {
        $db->prepare("DELETE FROM academic_edit_history WHERE student_id_code = ?")->execute([$sc]);
    }
    foreach ($createdAdmIds as $aid) {
        $fStmt = $db->prepare("SELECT pdf_path, payment_screenshot FROM admissions WHERE id = ?");
        $fStmt->execute([$aid]);
        $fRow = $fStmt->fetch(PDO::FETCH_ASSOC);
        if ($fRow) {
            if (!empty($fRow['pdf_path']) && is_file(BASE_PATH . '/' . $fRow['pdf_path'])) {
                @unlink(BASE_PATH . '/' . $fRow['pdf_path']);
            }
            if (!empty($fRow['payment_screenshot']) && is_file(STORAGE_PATH . '/uploads/payments/' . basename($fRow['payment_screenshot']))) {
                @unlink(STORAGE_PATH . '/uploads/payments/' . basename($fRow['payment_screenshot']));
            }
        }
        $db->prepare("DELETE FROM admission_payment_transactions WHERE admission_id = ?")->execute([$aid]);
        $db->prepare("DELETE FROM academic_edit_history WHERE entity_type = 'admission' AND entity_id = ?")->execute([$aid]);
        $db->prepare("DELETE FROM admissions WHERE id = ?")->execute([$aid]);
    }
    $db->prepare("DELETE FROM admission_id_reservations WHERE id > ?")->execute([$maxResIdBeforeOai]);
    foreach ($seqSnapshotOai as $srOai) {
        $db->prepare("UPDATE admission_sequences SET last_app_seq = ?, last_roll_seq = ?, updated_at = NOW() WHERE seq_year = ?")
           ->execute([(int)$srOai['last_app_seq'], (int)$srOai['last_roll_seq'], (int)$srOai['seq_year']]);
    }
    $db->prepare("DELETE FROM audit_logs WHERE details LIKE ?")->execute(["%{$suffix}%"]);
    foreach ([$anonCookie, $adminCookie, $studentCookie] as $cf) {
        @unlink($cf);
    }
    echo "  Cleaned up " . count($createdAdmIds) . " test admission(s) and " . count($createdStudentPks) . " test student(s).\n";
}

echo "\n=== Online Admission Integration E2E Summary ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
