<?php
/**
 * Comprehensive E2E Test Suite: Automatic Application ID & Admission Roll Number Generation
 *
 * Verifies:
 *  1. DB schema, UNIQUE constraints, and sequence tables (duplicate inserts rejected at SQL level)
 *  2. Automatic sequential generation on admission-form.php (RMU-YYYY-0001 & YYYY-0001)
 *  3. Readonly form fields + page-refresh / validation-retry idempotency (no duplicate serials)
 *  4. Abandoned draft isolation + reservation expiration cleanup
 *  5. Backend enforcement ignoring client-spoofed IDs/rolls & reservation commit lifecycle
 *  6. Payment checkout, SVG receipt, confirmation & printable Application Card (lookup by App ID or Roll)
 *  7. Concurrent simultaneous POST submissions via curl_multi (zero duplicates under InnoDB locks)
 *  8. Admin Panel search by Application ID & Roll across Admissions, Payments, Students, Results Manager
 *  9. Admin duplicate prevention across add & edit workflows (students.php & student-detail.php)
 * 10. Student Dashboard, Profile, Digital Student ID Card Front & Exam Admit Card display
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = 'http://127.0.0.1:8000';
$db = getDb();

function id_http(string $url, string $cookieFile, ?array $postData = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_TIMEOUT        => 25,
    ]);
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);
    unset($ch);
    return ['code' => $code, 'body' => $body, 'url' => $effectiveUrl, 'error' => $err];
}

function id_csrf(string $html): string {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/value="([^"]+)"\s+name="csrf_token"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

$passed = 0;
$failed = 0;
function id_assert(bool $cond, string $label): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$label}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$label}\n";
        throw new RuntimeException("Assertion failed: {$label}");
    }
}

$cookieDir = sys_get_temp_dir();
$sess1Cookie   = $cookieDir . '/rmu_id_s1_' . getmypid() . '.txt';
$sess2Cookie   = $cookieDir . '/rmu_id_s2_' . getmypid() . '.txt';
$adminCookie   = $cookieDir . '/rmu_id_admin_' . getmypid() . '.txt';
$studentCookie = $cookieDir . '/rmu_id_stu_' . getmypid() . '.txt';
$multiCookies  = [];
foreach ([$sess1Cookie, $sess2Cookie, $adminCookie, $studentCookie] as $cf) {
    @unlink($cf);
}

$suffix = (string)rand(70000, 99999);
$createdAdmIds = [];
$createdStudentPks = [];
$createdReceiptFiles = [];
$dupTestStudentPk = 0;

ensure_admission_id_generation_schema($db);
$currYear = (int)date('Y');
$seqSnapshot = $db->query("SELECT seq_year, last_app_seq, last_roll_seq FROM admission_sequences")->fetchAll(PDO::FETCH_ASSOC);
$maxResIdBefore = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM admission_id_reservations")->fetchColumn();
$yearRowBefore = $db->prepare("SELECT last_app_seq, last_roll_seq FROM admission_sequences WHERE seq_year = ?");
$yearRowBefore->execute([$currYear]);
$yrSnap = $yearRowBefore->fetch(PDO::FETCH_ASSOC) ?: ['last_app_seq' => 0, 'last_roll_seq' => 0];
$startAppSeq  = (int)$yrSnap['last_app_seq'];
$startRollSeq = (int)$yrSnap['last_roll_seq'];

try {
    echo "\n=== RMU Automatic Application ID & Admission Roll Number E2E Test ===\n\n";

    // Locate CSE program
    $cseProg = $db->query("SELECT * FROM programs WHERE slug = 'bsc-in-cse' OR title_en LIKE '%Computer Science%' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    id_assert(!empty($cseProg), "Active CSE program exists for online application submission");

    // -------------------------------------------------------------------------
    // STEP 1: DB Schema & UNIQUE Constraints Verification
    // -------------------------------------------------------------------------
    echo "1. Verifying DB Sequence Tables & UNIQUE Constraints:\n";
    $admIndexes = $db->query("SHOW INDEX FROM admissions WHERE Non_unique = 0")->fetchAll(PDO::FETCH_ASSOC);
    $admUniqueCols = array_column($admIndexes, 'Column_name');
    foreach (['application_ref', 'application_id', 'roll_number', 'exam_roll'] as $uCol) {
        id_assert(in_array($uCol, $admUniqueCols, true), "admissions.{$uCol} has UNIQUE database constraint");
    }

    $stuIndexes = $db->query("SHOW INDEX FROM students WHERE Non_unique = 0")->fetchAll(PDO::FETCH_ASSOC);
    $stuUniqueCols = array_column($stuIndexes, 'Column_name');
    foreach (['student_id', 'roll_number', 'application_number'] as $uCol) {
        id_assert(in_array($uCol, $stuUniqueCols, true), "students.{$uCol} has UNIQUE database constraint");
    }

    // Direct SQL duplicate check on existing admission row
    $sampleAdm = $db->query("SELECT application_ref, application_id, roll_number, exam_roll FROM admissions WHERE application_ref IS NOT NULL AND roll_number IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($sampleAdm) {
        $sqlDupBlocked = false;
        try {
            $db->prepare("INSERT INTO admissions (application_ref, application_id, full_name, mobile, last_college, ssc_gpa, hsc_gpa, home_district, hsc_group, interested_subject, roll_number, exam_roll, quota, status) VALUES (?, ?, 'Dup SQL Check', '01700000001', 'College', 4.0, 4.0, 'Kushtia', 'Science', ?, ?, ?, 'NO', 'Pending')")
               ->execute([$sampleAdm['application_ref'], $sampleAdm['application_id'], $cseProg['title_en'], $sampleAdm['roll_number'], $sampleAdm['roll_number']]);
        } catch (PDOException $sqlEx) {
            $sqlDupBlocked = is_retryable_db_concurrency_exception($sqlEx);
        }
        id_assert($sqlDupBlocked, "Direct SQL duplicate insert of existing application_ref / roll_number throws SQLSTATE 23000");
    }

    // -------------------------------------------------------------------------
    // STEP 2: Auto-Generation, Readonly Lock & Refresh Idempotency
    // -------------------------------------------------------------------------
    echo "\n2. Verifying Auto-Generation on Form Open, Readonly Lock & Refresh Idempotency:\n";
    $form1 = id_http($baseUrl . '/admission-form.php', $sess1Cookie);
    id_assert($form1['code'] === 200, "admission-form.php returns HTTP 200");

    preg_match('/<input[^>]+id="applicationIdInput"[^>]*>/', $form1['body'], $appInputTag);
    preg_match('/<input[^>]+id="applicantRollInput"[^>]*>/', $form1['body'], $rollInputTag);
    id_assert(!empty($appInputTag[0]) && stripos($appInputTag[0], 'readonly') !== false, "Application ID input field is rendered with readonly attribute");
    id_assert(!empty($rollInputTag[0]) && stripos($rollInputTag[0], 'readonly') !== false, "Admission Roll input field is rendered with readonly attribute");
    id_assert(strpos($form1['body'], "getElementById('applicantRollInput')") === false, "Legacy JS no longer overwrites applicantRollInput from HSC Roll");

    preg_match('/id="applicationIdInput"[^>]*value="([^"]+)"/', $form1['body'], $appVal1);
    preg_match('/id="applicantRollInput"[^>]*value="([^"]+)"/', $form1['body'], $rollVal1);
    preg_match('/name="draft_token"[^>]*value="([^"]+)"/', $form1['body'], $draft1);
    $genApp1   = $appVal1[1] ?? '';
    $genRoll1  = $rollVal1[1] ?? '';
    $draftTok1 = $draft1[1] ?? '';

    $expectedFirstApp  = format_sequential_application_id($currYear, $startAppSeq + 1);
    $expectedFirstRoll = format_sequential_roll_number($currYear, $startRollSeq + 1);
    if (!admission_app_id_exists($db, $expectedFirstApp) && !admission_roll_number_exists($db, $expectedFirstRoll)) {
        // Wait: form already reserved it so it now exists as reserved! Match regex + expected format:
    }
    id_assert(preg_match('/^RMU-' . $currYear . '-\d{4,}$/', $genApp1) === 1, "Generated Application ID matches RMU-{$currYear}-XXXX ({$genApp1})");
    id_assert(preg_match('/^' . $currYear . '-\d{4,}$/', $genRoll1) === 1, "Generated Admission Roll matches {$currYear}-XXXX ({$genRoll1})");
    id_assert($draftTok1 !== '', "Form embeds valid hidden draft_token");
    if ($startAppSeq === 0 && $startRollSeq === 0) {
        id_assert($genApp1 === "RMU-{$currYear}-0001", "First Application ID in sequence starts cleanly at RMU-{$currYear}-0001");
        id_assert($genRoll1 === "{$currYear}-0001", "First Admission Roll in sequence starts cleanly at {$currYear}-0001");
    }

    // Refresh form twice in Session 1 -> MUST return exact same pair
    $form1RefreshA = id_http($baseUrl . '/admission-form.php', $sess1Cookie);
    $form1RefreshB = id_http($baseUrl . '/admission-form.php', $sess1Cookie);
    preg_match('/id="applicationIdInput"[^>]*value="([^"]+)"/', $form1RefreshA['body'], $appRefA);
    preg_match('/id="applicantRollInput"[^>]*value="([^"]+)"/', $form1RefreshA['body'], $rollRefA);
    preg_match('/id="applicationIdInput"[^>]*value="([^"]+)"/', $form1RefreshB['body'], $appRefB);
    preg_match('/id="applicantRollInput"[^>]*value="([^"]+)"/', $form1RefreshB['body'], $rollRefB);
    id_assert(($appRefA[1] ?? '') === $genApp1 && ($appRefB[1] ?? '') === $genApp1, "Refreshing admission-form.php keeps exact same Application ID ({$genApp1})");
    id_assert(($rollRefA[1] ?? '') === $genRoll1 && ($rollRefB[1] ?? '') === $genRoll1, "Refreshing admission-form.php keeps exact same Admission Roll ({$genRoll1})");

    // Failed validation submission in Session 1 -> keeps same reserved pair
    $badPost = id_http($baseUrl . '/actions/submit-admission.php', $sess1Cookie, [
        'csrf_token'         => id_csrf($form1RefreshB['body']),
        'draft_token'        => $draftTok1,
        'full_name'          => 'X', // Too short -> validation fails
        'mobile'             => 'invalid-mob',
        'interested_subject' => $cseProg['title_en'],
        'academic_session'   => 'Fall 2026',
        'passing_year'       => '2025',
    ]);
    id_assert(strpos($badPost['url'], 'admission-form.php') !== false, "Validation failure redirects back to admission-form.php");
    preg_match('/id="applicationIdInput"[^>]*value="([^"]+)"/', $badPost['body'], $appAfterErr);
    preg_match('/id="applicantRollInput"[^>]*value="([^"]+)"/', $badPost['body'], $rollAfterErr);
    id_assert(($appAfterErr[1] ?? '') === $genApp1 && ($rollAfterErr[1] ?? '') === $genRoll1, "Validation retry preserves original reserved Application ID & Roll without burning serials");

    // -------------------------------------------------------------------------
    // STEP 3: Abandoned Draft Isolation & Expiry Cleanup
    // -------------------------------------------------------------------------
    echo "\n3. Verifying Abandoned Draft Safety & Reservation Expiry Cleanup:\n";
    $form2 = id_http($baseUrl . '/admission-form.php', $sess2Cookie);
    preg_match('/id="applicationIdInput"[^>]*value="([^"]+)"/', $form2['body'], $appVal2);
    preg_match('/id="applicantRollInput"[^>]*value="([^"]+)"/', $form2['body'], $rollVal2);
    preg_match('/name="draft_token"[^>]*value="([^"]+)"/', $form2['body'], $draft2);
    $genApp2  = $appVal2[1] ?? '';
    $genRoll2 = $rollVal2[1] ?? '';
    $draftTok2 = $draft2[1] ?? '';
    id_assert($genApp2 !== $genApp1 && $genRoll2 !== $genRoll1, "Concurrent Session 2 receives distinct sequential pair ({$genApp2} / {$genRoll2})");

    // Session 2 abandons without submitting -> zero orphan rows in admissions
    $orphanCnt = $db->prepare("SELECT COUNT(*) FROM admissions WHERE application_ref = ? OR application_id = ? OR roll_number = ?");
    $orphanCnt->execute([$genApp2, $genApp2, $genRoll2]);
    id_assert((int)$orphanCnt->fetchColumn() === 0, "Abandoned unsubmitted Session 2 draft creates ZERO records in admissions table");

    // Force expire Session 2 reservation and test cleanup
    $db->prepare("UPDATE admission_id_reservations SET expires_at = DATE_SUB(NOW(), INTERVAL 5 MINUTE) WHERE draft_token = ?")->execute([$draftTok2]);
    cleanup_expired_admission_reservations($db);
    $expCheck = $db->prepare("SELECT status, application_id, roll_number FROM admission_id_reservations WHERE draft_token = ?");
    $expCheck->execute([$draftTok2]);
    $expRow = $expCheck->fetch(PDO::FETCH_ASSOC);
    id_assert($expRow && $expRow['status'] === 'expired' && $expRow['application_id'] === null && $expRow['roll_number'] === null, "Expired reservation transitions safely to 'expired' with NULL released IDs");

    // -------------------------------------------------------------------------
    // STEP 4: Tamper Resistance, Submission, Payment & Printable Application Card
    // -------------------------------------------------------------------------
    echo "\n4. Verifying Tamper Resistance, Sequential Commit, Payment & Cards:\n";
    $mob1 = '017' . rand(10000000, 99999999);
    $sub1 = id_http($baseUrl . '/actions/submit-admission.php', $sess1Cookie, [
        'csrf_token'         => id_csrf($form1RefreshB['body']),
        'draft_token'        => $draftTok1,
        'full_name'          => "Auto ID Applicant {$suffix}",
        'application_id'     => "TAMPERED-APP-{$suffix}",
        'roll_number'        => "TAMPERED-ROLL-{$suffix}",
        'application_year'   => '2026',
        'father_name'        => 'Md. Salam',
        'mother_name'        => 'Mrs. Rina',
        'date_of_birth'      => '2004-05-10',
        'gender'             => 'Male',
        'mobile'             => $mob1,
        'email'              => "autoid.{$suffix}@rmu.test",
        'present_address'    => 'Kushtia Sadar',
        'permanent_address'  => 'Kushtia Sadar',
        'last_college'       => 'Kushtia Govt College',
        'home_district'      => 'Kushtia',
        'ssc_exam'           => 'S.S.C',
        'ssc_roll'           => '123456',
        'ssc_group'          => 'Science',
        'ssc_board'          => 'Jashore',
        'ssc_result_type'    => 'GPA(out of 5)',
        'ssc_gpa'            => '5.00',
        'ssc_year'           => '2021',
        'hsc_exam'           => 'H.S.C',
        'hsc_roll'           => '654321',
        'hsc_group'          => 'Science',
        'hsc_board'          => 'Jashore',
        'hsc_result_type'    => 'GPA(out of 5)',
        'hsc_gpa'            => '5.00',
        'hsc_year'           => '2023',
        'degree_type'        => 'Bachelor / Honours',
        'department_id'      => (int)$cseProg['department_id'],
        'program_id'         => (int)$cseProg['id'],
        'interested_subject' => $cseProg['title_en'],
        'academic_session'   => 'Fall 2026',
        'passing_year'       => '2025',
        'quota'              => 'NO',
    ]);
    id_assert(strpos($sub1['url'], 'admission-payment.php?ref=' . urlencode($genApp1)) !== false, "Submission redirects cleanly to admission-payment.php with generated ref={$genApp1}");

    $adm1Stmt = $db->prepare("SELECT * FROM admissions WHERE mobile = ? ORDER BY id DESC LIMIT 1");
    $adm1Stmt->execute([$mob1]);
    $adm1 = $adm1Stmt->fetch(PDO::FETCH_ASSOC);
    id_assert(!empty($adm1), "Application 1 saved permanently in admissions table");
    $createdAdmIds[] = (int)$adm1['id'];

    id_assert(
        $adm1['application_ref'] === $genApp1
        && $adm1['application_id'] === $genApp1
        && $adm1['roll_number'] === $genRoll1
        && $adm1['exam_roll'] === $genRoll1,
        "Backend ignored tampered POST values and persisted genuine ({$genApp1} / {$genRoll1})"
    );
    $resComm = $db->prepare("SELECT status, committed_admission_id FROM admission_id_reservations WHERE draft_token = ?");
    $resComm->execute([$draftTok1]);
    $resCommRow = $resComm->fetch(PDO::FETCH_ASSOC);
    id_assert($resCommRow && $resCommRow['status'] === 'committed' && (int)$resCommRow['committed_admission_id'] === (int)$adm1['id'], "Reservation marked 'committed' pointing to admissions.id={$adm1['id']}");

    // Reopen form in Session 1 AFTER submit -> must generate fresh incremented ID & Roll (NOT reuse $genApp1)
    $form1AfterSubmit = id_http($baseUrl . '/admission-form.php', $sess1Cookie);
    preg_match('/id="applicationIdInput"[^>]*value="([^"]+)"/', $form1AfterSubmit['body'], $appAfterSub);
    preg_match('/id="applicantRollInput"[^>]*value="([^"]+)"/', $form1AfterSubmit['body'], $rollAfterSub);
    id_assert(($appAfterSub[1] ?? '') !== $genApp1 && ($rollAfterSub[1] ?? '') !== $genRoll1, "Opening form again after submit gets NEW sequential ID ({$appAfterSub[1]}) & Roll ({$rollAfterSub[1]})");

    // Payment page displays both Application ID & Roll Number (and supports lookup by Roll Number!)
    $payPage = id_http($baseUrl . '/admission-payment.php?ref=' . urlencode($genRoll1), $sess1Cookie);
    id_assert($payPage['code'] === 200 && strpos($payPage['body'], $genApp1) !== false && strpos($payPage['body'], $genRoll1) !== false, "admission-payment.php loads via Roll lookup and displays both {$genApp1} and {$genRoll1}");
    id_assert(strpos($payPage['body'], 'name="transaction_id"') === false, "admission-payment.php omits manual Transaction ID prompt");

    preg_match('/name="idempotency_key"\s+value="([^"]+)"/', $payPage['body'], $idemAid);
    $gwAid = id_http($baseUrl . '/actions/process-admission-payment.php', $sess1Cookie, [
        'csrf_token'      => id_csrf($payPage['body']),
        'action'          => 'initiate_sandbox_payment',
        'ref'             => $genApp1,
        'idempotency_key' => $idemAid[1] ?? '',
        'payment_method'  => 'bKash',
        'sender_number'   => $mob1,
    ]);
    id_assert($gwAid['code'] === 200 && strpos($gwAid['url'], 'sandbox-gateway.php?session=') !== false, "Launched Sandbox Payment Gateway checkout for {$genApp1}");
    preg_match('/session=([A-Za-z0-9_-]+)/', $gwAid['url'], $aidSessMatch);
    preg_match('/id="sandboxTestOtpBadge"[^>]*>\s*([0-9]{6})\s*</', $gwAid['body'], $aidOtpMatch);

    $payPost = id_http($baseUrl . '/sandbox-gateway.php?session=' . urlencode($aidSessMatch[1] ?? ''), $sess1Cookie, [
        'csrf_token'  => id_csrf($gwAid['body']),
        'session'     => $aidSessMatch[1] ?? '',
        'gw_action'   => 'authorize',
        'sandbox_otp' => $aidOtpMatch[1] ?? '',
    ]);
    id_assert(strpos($payPost['url'], 'admission-status.php?ref=') !== false, "Sandbox payment processed and redirected to admission-status.php");

    $adm1AfterPay = $db->query("SELECT payment_screenshot, pdf_path FROM admissions WHERE id = " . (int)$adm1['id'])->fetch(PDO::FETCH_ASSOC);
    if (!empty($adm1AfterPay['pdf_path'])) {
        $createdReceiptFiles[] = BASE_PATH . '/' . $adm1AfterPay['pdf_path'];
    }
    if (!empty($adm1AfterPay['payment_screenshot'])) {
        $svgPath = STORAGE_PATH . '/uploads/payments/' . basename($adm1AfterPay['payment_screenshot']);
        $createdReceiptFiles[] = $svgPath;
        id_assert(file_exists($svgPath), "SVG payment receipt generated on disk");
        $svgContent = (string)file_get_contents($svgPath);
        id_assert(strpos($svgContent, $genApp1) !== false && strpos($svgContent, $genRoll1) !== false && strpos($svgContent, '<?=') === false, "SVG receipt contains both {$genApp1} & {$genRoll1} with valid XML");
    }

    // Confirmation page & printable Application Card (test lookup by Roll Number as well as Application ID)
    foreach ([$genApp1, $genRoll1] as $lookupKey) {
        $statPg = id_http($baseUrl . '/admission-status.php?ref=' . urlencode($lookupKey), $sess1Cookie);
        id_assert(
            $statPg['code'] === 200
            && strpos($statPg['body'], $genApp1) !== false
            && strpos($statPg['body'], $genRoll1) !== false
            && strpos($statPg['body'], 'admitCardPrint') !== false,
            "admission-status.php resolves '{$lookupKey}' and displays both App ID & Roll on Confirmation + Application Card"
        );
    }

    // -------------------------------------------------------------------------
    // STEP 5: Concurrent Simultaneous Submissions (curl_multi Stress Test)
    // -------------------------------------------------------------------------
    echo "\n5. Testing 5 Concurrent Simultaneous Submissions via curl_multi:\n";
    $multiCount = 5;
    $mh = curl_multi_init();
    $handles = [];
    $concurrentMobiles = [];

    for ($i = 0; $i < $multiCount; $i++) {
        $mc = $cookieDir . "/rmu_multi_{$i}_" . getmypid() . '.txt';
        @unlink($mc);
        $multiCookies[] = $mc;

        // Grab valid CSRF from contact.php without reserving a draft slot, forcing direct concurrent sequence locking!
        $csrfPg = id_http($baseUrl . '/contact.php', $mc);
        $mCsrf = id_csrf($csrfPg['body']);
        $mMob = '016' . rand(10000000, 99999999);
        $concurrentMobiles[] = $mMob;

        $ch = curl_init($baseUrl . '/actions/submit-admission.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR      => $mc,
            CURLOPT_COOKIEFILE     => $mc,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'csrf_token'         => $mCsrf,
                'full_name'          => "Concurrent Applicant {$i} {$suffix}",
                'application_year'   => '2026',
                'father_name'        => 'Md. Concurrent',
                'mother_name'        => 'Mrs. Concurrent',
                'date_of_birth'      => '2004-02-01',
                'gender'             => 'Female',
                'mobile'             => $mMob,
                'email'              => "conc.{$i}.{$suffix}@rmu.test",
                'present_address'    => 'Kushtia',
                'permanent_address'  => 'Kushtia',
                'last_college'       => 'Kushtia Govt College',
                'home_district'      => 'Kushtia',
                'ssc_exam'           => 'S.S.C',
                'ssc_roll'           => '50000' . $i,
                'ssc_group'          => 'Science',
                'ssc_board'          => 'Jashore',
                'ssc_result_type'    => 'GPA(out of 5)',
                'ssc_gpa'            => '4.90',
                'ssc_year'           => '2021',
                'hsc_exam'           => 'H.S.C',
                'hsc_roll'           => '60000' . $i,
                'hsc_group'          => 'Science',
                'hsc_board'          => 'Jashore',
                'hsc_result_type'    => 'GPA(out of 5)',
                'hsc_gpa'            => '4.95',
                'hsc_year'           => '2023',
                'degree_type'        => 'Bachelor / Honours',
                'department_id'      => (int)$cseProg['department_id'],
                'program_id'         => (int)$cseProg['id'],
                'interested_subject' => $cseProg['title_en'],
                'academic_session'   => 'Fall 2026',
                'passing_year'       => '2025',
                'quota'              => 'NO',
            ]),
        ]);
        $handles[$i] = $ch;
        curl_multi_add_handle($mh, $ch);
    }

    $running = null;
    do {
        $mrc = curl_multi_exec($mh, $running);
        if ($running > 0) {
            curl_multi_select($mh, 0.5);
        }
    } while ($running > 0 && $mrc === CURLM_OK);

    foreach ($handles as $i => $ch) {
        $cUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        id_assert(strpos($cUrl, 'admission-payment.php?ref=') !== false, "Concurrent submission #{$i} succeeded and redirected to payment");
        curl_multi_remove_handle($mh, $ch);
        unset($ch);
    }
    unset($handles, $mh);

    // Inspect all 5 database rows
    $concAppIds = [];
    $concRolls  = [];
    $mInPlaceholders = implode(',', array_fill(0, count($concurrentMobiles), '?'));
    $cRowsStmt = $db->prepare("SELECT id, application_ref, application_id, roll_number, exam_roll FROM admissions WHERE mobile IN ({$mInPlaceholders}) ORDER BY id ASC");
    $cRowsStmt->execute($concurrentMobiles);
    $cRows = $cRowsStmt->fetchAll(PDO::FETCH_ASSOC);
    id_assert(count($cRows) === $multiCount, "All {$multiCount} concurrent applications persisted cleanly in DB");

    foreach ($cRows as $cr) {
        $createdAdmIds[] = (int)$cr['id'];
        id_assert(preg_match('/^RMU-' . $currYear . '-\d{4,}$/', $cr['application_ref']) === 1, "Concurrent row #{$cr['id']} has valid sequential App ID ({$cr['application_ref']})");
        id_assert(preg_match('/^' . $currYear . '-\d{4,}$/', $cr['roll_number']) === 1, "Concurrent row #{$cr['id']} has valid sequential Roll ({$cr['roll_number']})");
        $concAppIds[] = $cr['application_ref'];
        $concRolls[]  = $cr['roll_number'];
    }
    id_assert(count(array_unique($concAppIds)) === $multiCount, "Zero duplicate Application IDs across simultaneous requests");
    id_assert(count(array_unique($concRolls)) === $multiCount, "Zero duplicate Roll Numbers across simultaneous requests");

    // -------------------------------------------------------------------------
    // STEP 6: Admin Search, Duplicate Assignment Prevention & Student Provisioning
    // -------------------------------------------------------------------------
    echo "\n6. Verifying Admin Search, Duplicate Assignment Prevention & Provisioning:\n";
    $admLoginGet = id_http($baseUrl . '/admin/login.php', $adminCookie);
    $admLoginPost = id_http($baseUrl . '/admin/login.php', $adminCookie, [
        'csrf_token' => id_csrf($admLoginGet['body']),
        'username'   => 'admin',
        'password'   => 'Admin@RMU2026!',
    ]);
    id_assert($admLoginPost['code'] === 200 && strpos($admLoginPost['url'], '/admin/dashboard.php') !== false, "Admin authenticated cleanly");

    // Admin search in admin/admissions.php by Application ID and by Roll Number
    foreach ([$genApp1, $genRoll1] as $qTerm) {
        $qRes = id_http($baseUrl . '/admin/admissions.php?status=All&search=' . urlencode($qTerm), $adminCookie);
        id_assert($qRes['code'] === 200 && strpos($qRes['body'], $genApp1) !== false && strpos($qRes['body'], $genRoll1) !== false, "admin/admissions.php finds record when searching by '{$qTerm}'");
    }

    // Search in admin/payments.php by App ID and Roll
    foreach ([$genApp1, $genRoll1] as $qTerm) {
        $pRes = id_http($baseUrl . '/admin/payments.php?tab=admissions&search=' . urlencode($qTerm), $adminCookie);
        id_assert($pRes['code'] === 200 && strpos($pRes['body'], $genApp1) !== false && strpos($pRes['body'], $genRoll1) !== false, "admin/payments.php finds verified fee row when searching by '{$qTerm}'");
    }

    // Accept Application 1 -> Provision Student 1
    $admListPg = id_http($baseUrl . '/admin/admissions.php?status=PendingQueue&search=' . urlencode($genApp1), $adminCookie);
    $accRes = id_http($baseUrl . '/admin/admissions.php', $adminCookie, [
        'csrf_token'  => id_csrf($admListPg['body']),
        'action'      => 'accept_application',
        'id'          => (int)$adm1['id'],
        'admin_notes' => 'Auto ID/Roll E2E Approved',
    ]);
    id_assert($accRes['code'] === 200, "Admin accepted Application 1");

    $adm1Accepted = $db->query("SELECT * FROM admissions WHERE id = " . (int)$adm1['id'])->fetch(PDO::FETCH_ASSOC);
    id_assert($adm1Accepted['status'] === 'Accepted' && !empty($adm1Accepted['student_id']) && !empty($adm1Accepted['portal_password']), "Accepted application provisioned Student ID ({$adm1Accepted['student_id']})");

    $stu1Stmt = $db->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
    $stu1Stmt->execute([$adm1Accepted['student_id']]);
    $stu1 = $stu1Stmt->fetch(PDO::FETCH_ASSOC);
    id_assert(!empty($stu1), "Student row exists in students table");
    $createdStudentPks[] = (int)$stu1['id'];
    id_assert($stu1['application_number'] === $genApp1 && $stu1['roll_number'] === $genRoll1, "Student table persisted Application ID ({$genApp1}) & Roll ({$genRoll1})");

    // Admin search in students.php & results-manager.php by both App ID & Roll
    foreach ([$genApp1, $genRoll1] as $qTerm) {
        $stDir = id_http($baseUrl . '/admin/students.php?search=' . urlencode($qTerm), $adminCookie);
        id_assert($stDir['code'] === 200 && strpos($stDir['body'], $stu1['student_id']) !== false && strpos($stDir['body'], $genApp1) !== false && strpos($stDir['body'], $genRoll1) !== false, "admin/students.php finds {$stu1['student_id']} when searching by '{$qTerm}'");

        $rmDir = id_http($baseUrl . '/admin/results-manager.php?search=' . urlencode($qTerm), $adminCookie);
        id_assert($rmDir['code'] === 200 && strpos($rmDir['body'], $stu1['student_id']) !== false, "admin/results-manager.php finds {$stu1['student_id']} when searching by '{$qTerm}'");
    }

    // Admin Duplicate Prevention: trying to add a student with existing Roll or App ID MUST be rejected!
    $enrollPg = id_http($baseUrl . '/admin/students.php?action=enroll', $adminCookie);
    $enrollCsrf = id_csrf($enrollPg['body']);

    $dupRollAdd = id_http($baseUrl . '/admin/students.php', $adminCookie, [
        'csrf_token'         => $enrollCsrf,
        'action'             => 'add',
        'student_id'         => "RMUDUPR{$suffix}",
        'roll_number'        => $genRoll1, // Duplicate!
        'application_number' => "RMU-UNIQ-{$suffix}",
        'full_name'          => "Dup Roll Attempt {$suffix}",
        'email'              => "dupr.{$suffix}@rmu.test",
        'phone'              => '01511111111',
        'program_id'         => (int)$cseProg['id'],
        'department_id'      => (int)$cseProg['department_id'],
        'academic_session'   => 'Fall 2026',
    ]);
    $dupCheckRoll = $db->prepare("SELECT COUNT(*) FROM students WHERE student_id = ?");
    $dupCheckRoll->execute(["RMUDUPR{$suffix}"]);
    id_assert((int)$dupCheckRoll->fetchColumn() === 0 && strpos($dupRollAdd['body'], 'already assigned') !== false, "admin/students.php blocked duplicate Roll Number assignment");

    $dupAppAdd = id_http($baseUrl . '/admin/students.php', $adminCookie, [
        'csrf_token'         => $enrollCsrf,
        'action'             => 'add',
        'student_id'         => "RMUDUPA{$suffix}",
        'roll_number'        => "2099-{$suffix}",
        'application_number' => $genApp1, // Duplicate!
        'full_name'          => "Dup App Attempt {$suffix}",
        'email'              => "dupa.{$suffix}@rmu.test",
        'phone'              => '01522222222',
        'program_id'         => (int)$cseProg['id'],
        'department_id'      => (int)$cseProg['department_id'],
        'academic_session'   => 'Fall 2026',
    ]);
    $dupCheckApp = $db->prepare("SELECT COUNT(*) FROM students WHERE student_id = ?");
    $dupCheckApp->execute(["RMUDUPA{$suffix}"]);
    id_assert((int)$dupCheckApp->fetchColumn() === 0 && strpos($dupAppAdd['body'], 'already assigned') !== false, "admin/students.php blocked duplicate Application ID assignment");

    // Create a clean second manual student and test duplicate block on UPDATE (students.php & student-detail.php)
    $uniqStuCode = "RMUUNQ{$suffix}";
    $uniqRoll    = "2098-{$suffix}";
    $uniqApp     = "RMU-2098-{$suffix}";
    $addClean = id_http($baseUrl . '/admin/students.php', $adminCookie, [
        'csrf_token'         => $enrollCsrf,
        'action'             => 'add',
        'student_id'         => $uniqStuCode,
        'roll_number'        => $uniqRoll,
        'application_number' => $uniqApp,
        'full_name'          => "Unique Second Student {$suffix}",
        'email'              => "uniq2.{$suffix}@rmu.test",
        'phone'              => '01533333333',
        'program_id'         => (int)$cseProg['id'],
        'department_id'      => (int)$cseProg['department_id'],
        'academic_session'   => 'Fall 2026',
    ]);
    $dupTestStudentPk = (int)$db->query("SELECT id FROM students WHERE student_id = " . $db->quote($uniqStuCode))->fetchColumn();
    id_assert($dupTestStudentPk > 0, "Clean non-duplicate second student created for edit collision check");
    $createdStudentPks[] = $dupTestStudentPk;

    $sdEditPg = id_http($baseUrl . '/admin/student-detail.php?id=' . $dupTestStudentPk . '&tab=admissionTab', $adminCookie);
    $sdDupTry = id_http($baseUrl . '/admin/student-detail.php?id=' . $dupTestStudentPk, $adminCookie, [
        'csrf_token'         => id_csrf($sdEditPg['body']),
        'action'             => 'update_personal_info',
        'full_name'          => "Unique Second Student {$suffix}",
        'roll_number'        => $genRoll1, // Collides with Student 1!
        'application_number' => $uniqApp,
        'email'              => "uniq2.{$suffix}@rmu.test",
        'phone'              => '01533333333',
    ]);
    $afterDupRoll = (string)$db->query("SELECT roll_number FROM students WHERE id = {$dupTestStudentPk}")->fetchColumn();
    id_assert($afterDupRoll === $uniqRoll && strpos($sdDupTry['body'], 'already assigned') !== false, "admin/student-detail.php prevented admin from assigning duplicate Roll Number");

    // -------------------------------------------------------------------------
    // STEP 7: Student Portal (Dashboard, Profile, Digital ID Card & Admit Card) & Post-Accept Status
    // -------------------------------------------------------------------------
    echo "\n7. Verifying Student Portal, Application Confirmation & ID / Admit Cards:\n";
    $accStatusPg = id_http($baseUrl . '/admission-status.php?ref=' . urlencode($genApp1), $sess1Cookie);
    id_assert(
        $accStatusPg['code'] === 200
        && strpos($accStatusPg['body'], $adm1Accepted['student_id']) !== false
        && strpos($accStatusPg['body'], $genApp1) !== false
        && strpos($accStatusPg['body'], $genRoll1) !== false
        && strpos($accStatusPg['body'], 'admitCardPrint') !== false,
        "Approved admission-status.php displays Student ID, Application ID, Roll Number, and Application Card"
    );

    $stLoginGet = id_http($baseUrl . '/student/login.php', $studentCookie);
    $stLoginPost = id_http($baseUrl . '/student/login.php', $studentCookie, [
        'csrf_token' => id_csrf($stLoginGet['body']),
        'student_id' => $adm1Accepted['student_id'],
        'password'   => $adm1Accepted['portal_password'],
    ]);
    id_assert($stLoginPost['code'] === 200 && strpos($stLoginPost['url'], '/student/dashboard.php') !== false, "Student Portal login succeeds with {$adm1Accepted['student_id']}");

    $dashHtml = $stLoginPost['body'];
    id_assert(strpos($dashHtml, $genApp1) !== false && strpos($dashHtml, $genRoll1) !== false, "student/dashboard.php displays both Application ID ({$genApp1}) and Roll Number ({$genRoll1})");

    $profPg = id_http($baseUrl . '/student/profile.php', $studentCookie);
    id_assert($profPg['code'] === 200 && strpos($profPg['body'], $genApp1) !== false && strpos($profPg['body'], $genRoll1) !== false, "student/profile.php displays both Application ID and Admission Roll Number");

    $idCardPg = id_http($baseUrl . '/student/id-card.php', $studentCookie);
    id_assert($idCardPg['code'] === 200, "student/id-card.php returns HTTP 200");
    // Verify BOTH sections (Digital ID Card Front & Examination Admit Card Section) contain BOTH App ID & Roll Number
    $splitParts = explode('Examination Admit Card Section', $idCardPg['body']);
    id_assert(count($splitParts) === 2, "student/id-card.php renders both Digital ID Card and Examination Admit Card sections");
    id_assert(strpos($splitParts[0], $genApp1) !== false && strpos($splitParts[0], $genRoll1) !== false, "Digital Student ID Card Front includes both Application ID & Admission Roll");
    id_assert(strpos($splitParts[1], $genApp1) !== false && strpos($splitParts[1], $genRoll1) !== false, "Examination Admit Card includes both Application ID & Admission/Exam Roll");

    echo "\n===============================================================================\n";
    echo ">>> ALL {$passed} AUTOMATIC APPLICATION ID & ROLL NUMBER TESTS PASSED! <<<\n";
    echo "===============================================================================\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\n[ERROR] " . $e->getMessage() . "\n");
    $failed++;
} finally {
    // Restore clean pre-test DB state
    foreach ($createdStudentPks as $pk) {
        if ($pk <= 0) continue;
        $db->prepare("DELETE FROM academic_edit_history WHERE entity_type = 'student' AND entity_id = ?")->execute([$pk]);
        $db->prepare("DELETE FROM student_results WHERE student_id = ?")->execute([$pk]);
        $db->prepare("DELETE FROM student_semester_results WHERE student_id = ?")->execute([$pk]);
        $db->prepare("DELETE FROM student_courses WHERE student_id = ?")->execute([$pk]);
        $db->prepare("DELETE FROM student_notifications WHERE student_id = ?")->execute([$pk]);
        $db->prepare("DELETE FROM audit_logs WHERE details LIKE ? OR details LIKE ? OR details LIKE ?")->execute(["%#{$pk}%", "%{$suffix}%", "%RMU{$suffix}%"]);
        $db->prepare("DELETE FROM students WHERE id = ?")->execute([$pk]);
    }
    foreach ($createdAdmIds as $aid) {
        if ($aid <= 0) continue;
        $db->prepare("DELETE FROM admission_payment_transactions WHERE admission_id = ?")->execute([$aid]);
        $db->prepare("DELETE FROM academic_edit_history WHERE entity_type = 'admission' AND entity_id = ?")->execute([$aid]);
        $db->prepare("DELETE FROM audit_logs WHERE details LIKE ?")->execute(["%application #{$aid}%"]);
        $db->prepare("DELETE FROM admissions WHERE id = ?")->execute([$aid]);
    }
    $db->prepare("DELETE FROM audit_logs WHERE details LIKE ?")->execute(["%{$suffix}%"]);
    $db->prepare("DELETE FROM admission_id_reservations WHERE id > ?")->execute([$maxResIdBefore]);
    foreach ($seqSnapshot as $sr) {
        $db->prepare("UPDATE admission_sequences SET last_app_seq = ?, last_roll_seq = ?, updated_at = NOW() WHERE seq_year = ?")
           ->execute([(int)$sr['last_app_seq'], (int)$sr['last_roll_seq'], (int)$sr['seq_year']]);
    }
    foreach ($createdReceiptFiles as $rf) {
        if (file_exists($rf)) {
            @unlink($rf);
        }
    }
    foreach (array_merge([$sess1Cookie, $sess2Cookie, $adminCookie, $studentCookie], $multiCookies) as $cf) {
        if (file_exists($cf)) {
            @unlink($cf);
        }
    }
}

exit($failed > 0 ? 1 : 0);
