<?php
/**
 * Comprehensive End-to-End Test Suite:
 * Sandbox Payment Gateway, Single-Use Transaction Verification, Duplicate Prevention,
 * Failure Handling & Genuine-Payment-Gated Application Card Generation.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = 'http://127.0.0.1:8000';
$db = getDb();

$passed = 0;
$failed = 0;

function spg_assert(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

function spg_http(string $url, string $cookieFile, ?array $postData = null, bool $follow = true): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS      => 8,
        CURLOPT_TIMEOUT        => 35,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_USERAGENT      => 'RMU-SandboxGateway-E2E/1.0',
    ];
    if ($postData !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($postData);
    }
    curl_setopt_array($ch, $opts);
    $body = (string)curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $eff  = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $loc  = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $ct   = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    return ['code' => $code, 'url' => $eff, 'redirect_url' => $loc, 'type' => $ct, 'body' => $body, 'error' => $err];
}

function spg_csrf(string $html): string {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/value="([^"]+)"\s+name="csrf_token"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

$cookieDir = sys_get_temp_dir();
$anonCookie  = $cookieDir . '/rmu_spg_anon_' . getmypid() . '.txt';
$anonCookie2 = $cookieDir . '/rmu_spg_anon2_' . getmypid() . '.txt';
$adminCookie = $cookieDir . '/rmu_spg_admin_' . getmypid() . '.txt';
foreach ([$anonCookie, $anonCookie2, $adminCookie] as $cf) {
    @unlink($cf);
}

ensure_admission_id_generation_schema($db);
ensure_admission_application_system_schema($db);
ensure_admission_payment_gateway_schema($db);

$seqSnapshot    = $db->query("SELECT seq_year, last_app_seq, last_roll_seq FROM admission_sequences")->fetchAll(PDO::FETCH_ASSOC);
$maxResIdBefore = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM admission_id_reservations")->fetchColumn();
$maxAuditBefore = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM audit_logs")->fetchColumn();

$createdAdmIds = [];
$filesToDelete = [];

try {
    echo "\n=== RMU Sandbox Payment Gateway & Application Card Generation E2E Test ===\n\n";

    $prog = $db->query("SELECT * FROM programs WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    spg_assert(!empty($prog), "Active degree program loaded for test admission");

    $suffix = substr((string)time(), -5) . rand(100, 999);
    $mob1 = '017' . rand(10000000, 99999999);
    $mob2 = '018' . rand(10000000, 99999999);

    // Helper closure to submit clean admission via admission-form.php
    $submitTestAdmission = static function (string $cookieFile, string $name, string $mobile, string $email) use ($baseUrl, $prog, $db): array {
        $fg = spg_http($baseUrl . '/admission-form.php', $cookieFile);
        preg_match('/id="draftTokenInput"\s+value="([^"]+)"/', $fg['body'], $dtM);
        $res = spg_http($baseUrl . '/actions/submit-admission.php', $cookieFile, [
            'csrf_token'         => spg_csrf($fg['body']),
            'draft_token'        => $dtM[1] ?? '',
            'application_year'   => '2026',
            'full_name'          => $name,
            'father_name'        => 'Test Guardian Father',
            'mother_name'        => 'Test Guardian Mother',
            'date_of_birth'      => '2004-08-15',
            'gender'             => 'Male',
            'mobile'             => $mobile,
            'email'              => $email,
            'present_address'    => 'Kushtia Sadar, 7000',
            'permanent_address'  => 'Kushtia Sadar, 7000',
            'last_college'       => 'Kushtia Govt. College',
            'home_district'      => 'Kushtia',
            'ssc_exam'           => 'S.S.C',
            'ssc_roll'           => '223344',
            'ssc_reg_no'         => '1122334455',
            'ssc_board'          => 'Jashore',
            'ssc_result_type'    => 'GPA(out of 5)',
            'ssc_gpa'            => '5.00',
            'ssc_group'          => 'Science',
            'ssc_year'           => '2020',
            'hsc_exam'           => 'H.S.C',
            'hsc_roll'           => '556677',
            'hsc_reg_no'         => '6677889900',
            'hsc_board'          => 'Jashore',
            'hsc_result_type'    => 'GPA(out of 5)',
            'hsc_gpa'            => '4.95',
            'hsc_group'          => 'Science',
            'hsc_year'           => '2022',
            'degree_type'        => (string)($prog['degree_type'] ?? 'Bachelor / Honours'),
            'department_id'      => (string)$prog['department_id'],
            'program_id'         => (string)$prog['id'],
            'interested_subject' => (string)$prog['title_en'],
            'academic_session'   => 'Fall 2026',
            'passing_year'       => '2026',
            'quota'              => 'NO',
        ]);
        $s = $db->prepare("SELECT * FROM admissions WHERE mobile = ? ORDER BY id DESC LIMIT 1");
        $s->execute([$mobile]);
        $row = $s->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['http' => $res, 'row' => $row];
    };

    // -------------------------------------------------------------------------
    // 1. Pre-Payment Strict Lock Verification
    // -------------------------------------------------------------------------
    echo "1. Verifying Strict Pre-Payment Application Card Lock:\n";
    $sub1 = $submitTestAdmission($anonCookie, "Sandbox Applicant One {$suffix}", $mob1, "sbx1.{$suffix}@rmu.test");
    $adm1 = $sub1['row'];
    spg_assert(!empty($adm1['id']), "Application #1 submitted cleanly ({$adm1['application_ref']})");
    $admId1 = (int)$adm1['id'];
    $createdAdmIds[] = $admId1;
    $ref1 = (string)$adm1['application_ref'];

    spg_assert(
        ($adm1['payment_status'] ?? '') === 'Pending'
        && empty($adm1['transaction_id'])
        && empty($adm1['pdf_path']),
        "Initial submitted application has Pending payment, NULL transaction_id, and NULL pdf_path"
    );

    $unpaidStatus = spg_http($baseUrl . '/admission-status.php?ref=' . urlencode($ref1), $anonCookie);
    spg_assert(
        $unpaidStatus['code'] === 200
        && strpos($unpaidStatus['body'], 'pendingApplicationSummary') !== false
        && strpos($unpaidStatus['body'], 'admitCardPrint') === false,
        "Unpaid admission-status.php renders pending summary and hides Paid Application Card"
    );

    foreach (['view', 'pdf', 'download', 'print'] as $docAct) {
        $docBlock = spg_http($baseUrl . "/admission-document.php?id={$admId1}&doc=application&action={$docAct}", $anonCookie);
        spg_assert(
            $docBlock['code'] === 403 && strpos($docBlock['body'], '%PDF-') === false,
            "Unpaid admission-document.php?doc=application&action={$docAct} strictly blocked with HTTP 403"
        );
    }

    // -------------------------------------------------------------------------
    // 2. Payment Form Check + Direct Fake TrxID Bypass Rejection
    // -------------------------------------------------------------------------
    echo "\n2. Testing Checkout Form & Blocking Direct Transaction ID Spoofing:\n";
    $payPage = spg_http($baseUrl . '/admission-payment.php?ref=' . urlencode($ref1), $anonCookie);
    spg_assert($payPage['code'] === 200, "admission-payment.php loads with HTTP 200");
    spg_assert(
        strpos($payPage['body'], 'name="transaction_id"') === false
        && strpos($payPage['body'], 'quickPayDemo') === false
        && strpos($payPage['body'], 'Pay ৳150 &amp; Generate Application Card') !== false,
        "Payment page has single 'Pay ৳150 & Generate Application Card' button with ZERO manual TrxID input and ZERO quickPayDemo shortcut"
    );

    $payCsrf = spg_csrf($payPage['body']);
    preg_match('/name="idempotency_key"\s+value="([^"]+)"/', $payPage['body'], $idemM1);
    $idem1 = $idemM1[1] ?? '';
    spg_assert($payCsrf !== '' && $idem1 !== '', "Payment form includes valid CSRF token & idempotency_key");

    // Attempt direct bypass POST submitting fake TrxID
    $bypassTry = spg_http($baseUrl . '/actions/process-admission-payment.php', $anonCookie, [
        'csrf_token'     => $payCsrf,
        'ref'            => $ref1,
        'payment_method' => 'bKash',
        'sender_number'  => $mob1,
        'transaction_id' => 'FAKE-TRX-' . $suffix,
    ]);
    $afterBypass = $db->query("SELECT payment_status, transaction_id, pdf_path FROM admissions WHERE id = {$admId1}")->fetch(PDO::FETCH_ASSOC);
    spg_assert(
        strpos($bypassTry['url'], 'admission-payment.php') !== false
        && strpos($bypassTry['body'], 'Direct Transaction ID submission is disabled') !== false
        && $afterBypass['payment_status'] !== 'Paid'
        && empty($afterBypass['transaction_id'])
        && empty($afterBypass['pdf_path']),
        "Direct POST with fake client transaction_id was rejected, error shown, and Paid Application Card NOT generated"
    );

    // -------------------------------------------------------------------------
    // 3. Idempotent Session Initiation & Failure / Cancellation / OTP Lockout
    // -------------------------------------------------------------------------
    echo "\n3. Testing Idempotent Session Creation, Cancel, Simulated Failure & Wrong OTP Lockout:\n";
    $payPage2 = spg_http($baseUrl . '/admission-payment.php?ref=' . urlencode($ref1), $anonCookie);
    $csrf2 = spg_csrf($payPage2['body']);
    preg_match('/name="idempotency_key"\s+value="([^"]+)"/', $payPage2['body'], $idemM2);
    $idem2 = $idemM2[1] ?? '';

    $initA = spg_http($baseUrl . '/actions/process-admission-payment.php', $anonCookie, [
        'csrf_token'      => $csrf2,
        'action'          => 'initiate_sandbox_payment',
        'ref'             => $ref1,
        'idempotency_key' => $idem2,
        'payment_method'  => 'bKash',
        'sender_number'   => $mob1,
    ]);
    preg_match('/session=([A-Za-z0-9_-]+)/', $initA['url'], $sidMA);
    $sidA = $sidMA[1] ?? '';
    spg_assert($initA['code'] === 200 && $sidA !== '', "Initiate action opened Sandbox Gateway session {$sidA}");

    // Submitting identical idempotency_key again returns SAME active INITIATED gateway session (no duplicate sessions)
    $initADup = spg_http($baseUrl . '/actions/process-admission-payment.php', $anonCookie, [
        'csrf_token'      => spg_csrf($payPage2['body']),
        'action'          => 'initiate_sandbox_payment',
        'ref'             => $ref1,
        'idempotency_key' => $idem2,
        'payment_method'  => 'bKash',
        'sender_number'   => $mob1,
    ]);
    preg_match('/session=([A-Za-z0-9_-]+)/', $initADup['url'], $sidMADup);
    spg_assert(($sidMADup[1] ?? '') === $sidA, "Duplicate initiation with same idempotency_key reused exact active session ({$sidA})");

    // Attempt premature callback bypass on INITIATED session without OTP auth
    $prematureCb = spg_http($baseUrl . '/actions/process-admission-payment.php?action=gateway_callback&session=' . urlencode($sidA) . '&trx=FAKETRX&sig=bad', $anonCookie);
    $admPremature = $db->query("SELECT payment_status, pdf_path FROM admissions WHERE id = {$admId1}")->fetch(PDO::FETCH_ASSOC);
    spg_assert(
        strpos($prematureCb['url'], 'admission-payment.php') !== false
        && $admPremature['payment_status'] !== 'Paid'
        && empty($admPremature['pdf_path']),
        "Premature/tampered callback rejected without marking Paid or generating Application Card"
    );

    // Test Simulate Failure path on fresh session
    $payPageFail = spg_http($baseUrl . '/admission-payment.php?ref=' . urlencode($ref1), $anonCookie);
    preg_match('/name="idempotency_key"\s+value="([^"]+)"/', $payPageFail['body'], $idemFailM);
    $gwFail = spg_http($baseUrl . '/actions/process-admission-payment.php', $anonCookie, [
        'csrf_token'      => spg_csrf($payPageFail['body']),
        'action'          => 'initiate_sandbox_payment',
        'ref'             => $ref1,
        'idempotency_key' => $idemFailM[1] ?? '',
        'payment_method'  => 'Nagad',
        'sender_number'   => $mob1,
    ]);
    preg_match('/session=([A-Za-z0-9_-]+)/', $gwFail['url'], $sidFailM);
    $sidFail = $sidFailM[1] ?? '';

    $simFailPost = spg_http($baseUrl . '/sandbox-gateway.php?session=' . urlencode($sidFail), $anonCookie, [
        'csrf_token'  => spg_csrf($gwFail['body']),
        'session'     => $sidFail,
        'gw_action'   => 'simulate_fail',
        'fail_reason' => 'Insufficient sandbox balance',
    ]);
    $admAfterFail = $db->query("SELECT payment_status, pdf_path FROM admissions WHERE id = {$admId1}")->fetch(PDO::FETCH_ASSOC);
    spg_assert(
        strpos($simFailPost['url'], 'admission-payment.php') !== false
        && strpos($simFailPost['body'], 'Insufficient sandbox balance') !== false
        && $admAfterFail['payment_status'] === 'Failed'
        && empty($admAfterFail['pdf_path']),
        "Simulated Gateway failure marks Failed, shows clear failure reason banner, and keeps Application Card locked"
    );

    // Test 3 wrong OTP attempts lock session cleanly
    $payPageOtp = spg_http($baseUrl . '/admission-payment.php?ref=' . urlencode($ref1), $anonCookie);
    preg_match('/name="idempotency_key"\s+value="([^"]+)"/', $payPageOtp['body'], $idemOtpM);
    $gwOtp = spg_http($baseUrl . '/actions/process-admission-payment.php', $anonCookie, [
        'csrf_token'      => spg_csrf($payPageOtp['body']),
        'action'          => 'initiate_sandbox_payment',
        'ref'             => $ref1,
        'idempotency_key' => $idemOtpM[1] ?? '',
        'payment_method'  => 'Rocket',
        'sender_number'   => $mob1,
    ]);
    preg_match('/session=([A-Za-z0-9_-]+)/', $gwOtp['url'], $sidOtpM);
    $sidOtp = $sidOtpM[1] ?? '';
    $otpFormHtml = $gwOtp['body'];
    for ($try = 1; $try <= 3; $try++) {
        $otpTryRes = spg_http($baseUrl . '/sandbox-gateway.php?session=' . urlencode($sidOtp), $anonCookie, [
            'csrf_token'  => spg_csrf($otpFormHtml),
            'session'     => $sidOtp,
            'gw_action'   => 'authorize',
            'sandbox_otp' => '000000',
        ]);
        if ($try < 3) {
            spg_assert(strpos($otpTryRes['body'], 'Invalid Sandbox Verification Code') !== false, "Wrong OTP attempt #{$try} shows inline retry error");
            $otpFormHtml = $otpTryRes['body'];
        } else {
            $admAfterLock = $db->query("SELECT payment_status, pdf_path FROM admissions WHERE id = {$admId1}")->fetch(PDO::FETCH_ASSOC);
            spg_assert(
                strpos($otpTryRes['url'], 'admission-payment.php') !== false
                && strpos($otpTryRes['body'], '3 incorrect Sandbox OTP attempts') !== false
                && $admAfterLock['payment_status'] === 'Failed'
                && empty($admAfterLock['pdf_path']),
                "3rd incorrect Sandbox OTP locks session as Failed without generating Paid Application Card"
            );
        }
    }

    // Test Cancel Gateway flow cleanly keeps Application Card locked and sets CANCELLED
    $payPageCancel = spg_http($baseUrl . '/admission-payment.php?ref=' . urlencode($ref1), $anonCookie);
    preg_match('/name="idempotency_key"\s+value="([^"]+)"/', $payPageCancel['body'], $idemCancelM);
    $gwCancel = spg_http($baseUrl . '/actions/process-admission-payment.php', $anonCookie, [
        'csrf_token'      => spg_csrf($payPageCancel['body']),
        'action'          => 'initiate_sandbox_payment',
        'ref'             => $ref1,
        'idempotency_key' => $idemCancelM[1] ?? '',
        'payment_method'  => 'Upay',
        'sender_number'   => $mob1,
    ]);
    preg_match('/session=([A-Za-z0-9_-]+)/', $gwCancel['url'], $sidCancelM);
    $sidCancel = $sidCancelM[1] ?? '';

    $cancelPost = spg_http($baseUrl . '/sandbox-gateway.php?session=' . urlencode($sidCancel), $anonCookie, [
        'csrf_token' => spg_csrf($gwCancel['body']),
        'session'    => $sidCancel,
        'gw_action'  => 'cancel',
    ]);
    $txCancelRow = $db->query("SELECT gateway_status FROM admission_payment_transactions WHERE gateway_session_id = " . $db->quote($sidCancel))->fetch(PDO::FETCH_ASSOC);
    $admCancelRow = $db->query("SELECT payment_status, pdf_path FROM admissions WHERE id = {$admId1}")->fetch(PDO::FETCH_ASSOC);
    spg_assert(
        strpos($cancelPost['url'], 'admission-payment.php') !== false
        && strpos($cancelPost['body'], 'cancelled before completion') !== false
        && ($txCancelRow['gateway_status'] ?? '') === 'CANCELLED'
        && ($admCancelRow['payment_status'] ?? '') === 'Pending'
        && empty($admCancelRow['pdf_path']),
        "Gateway cancellation marks session CANCELLED, reverts to Pending, and keeps Application Card locked"
    );

    // Test Tampered Callback Signature on an AUTHORIZED session -> rejected cleanly without generating card
    $payPageTamper = spg_http($baseUrl . '/admission-payment.php?ref=' . urlencode($ref1), $anonCookie);
    preg_match('/name="idempotency_key"\s+value="([^"]+)"/', $payPageTamper['body'], $idemTamperM);
    $gwTamper = spg_http($baseUrl . '/actions/process-admission-payment.php', $anonCookie, [
        'csrf_token'      => spg_csrf($payPageTamper['body']),
        'action'          => 'initiate_sandbox_payment',
        'ref'             => $ref1,
        'idempotency_key' => $idemTamperM[1] ?? '',
        'payment_method'  => 'bKash',
        'sender_number'   => $mob1,
    ]);
    preg_match('/session=([A-Za-z0-9_-]+)/', $gwTamper['url'], $sidTamperM);
    preg_match('/id="sandboxTestOtpBadge"[^>]*>\s*([0-9]{6})\s*</', $gwTamper['body'], $otpTamperM);
    $sidTamper = $sidTamperM[1] ?? '';
    $otpTamper = $otpTamperM[1] ?? '';
    $authTamperOnly = spg_http($baseUrl . '/sandbox-gateway.php?session=' . urlencode($sidTamper), $anonCookie, [
        'csrf_token'  => spg_csrf($gwTamper['body']),
        'session'     => $sidTamper,
        'gw_action'   => 'authorize',
        'sandbox_otp' => $otpTamper,
    ], false);
    $tamperedCbUrl = preg_replace('/sig=[^&]+/', 'sig=tampered_hmac_signature_0000', (string)$authTamperOnly['redirect_url']);
    $tamperCbRes = spg_http($tamperedCbUrl, $anonCookie, null, true);
    $txTamperRow = $db->query("SELECT gateway_status, validated_once_at FROM admission_payment_transactions WHERE gateway_session_id = " . $db->quote($sidTamper))->fetch(PDO::FETCH_ASSOC);
    $admTamperRow = $db->query("SELECT payment_status, pdf_path FROM admissions WHERE id = {$admId1}")->fetch(PDO::FETCH_ASSOC);
    spg_assert(
        strpos($tamperCbRes['url'], 'admission-payment.php') !== false
        && strpos($tamperCbRes['body'], 'Invalid gateway signature') !== false
        && ($txTamperRow['gateway_status'] ?? '') === 'FAILED'
        && empty($txTamperRow['validated_once_at'])
        && ($admTamperRow['payment_status'] ?? '') === 'Failed'
        && empty($admTamperRow['pdf_path']),
        "Tampered HMAC signature on AUTHORIZED callback fails closed without validating or generating Card"
    );

    // -------------------------------------------------------------------------
    // 4. Genuine Sandbox Payment Success + Single-Use Validation + Replay Protection
    // -------------------------------------------------------------------------
    echo "\n4. Testing Genuine Sandbox OTP Payment, Single-Use Verification & Replay Block:\n";
    $payGoodPg = spg_http($baseUrl . '/admission-payment.php?ref=' . urlencode($ref1), $anonCookie);
    preg_match('/name="idempotency_key"\s+value="([^"]+)"/', $payGoodPg['body'], $idemGoodM);

    $gwGood = spg_http($baseUrl . '/actions/process-admission-payment.php', $anonCookie, [
        'csrf_token'      => spg_csrf($payGoodPg['body']),
        'action'          => 'initiate_sandbox_payment',
        'ref'             => $ref1,
        'idempotency_key' => $idemGoodM[1] ?? '',
        'payment_method'  => 'bKash',
        'sender_number'   => $mob1,
    ]);
    preg_match('/session=([A-Za-z0-9_-]+)/', $gwGood['url'], $sidGoodM);
    preg_match('/id="sandboxTestOtpBadge"[^>]*>\s*([0-9]{6})\s*</', $gwGood['body'], $otpBadgeM);
    $sidGood = $sidGoodM[1] ?? '';
    $goodOtp = $otpBadgeM[1] ?? '';
    spg_assert($sidGood !== '' && strlen($goodOtp) === 6, "Fresh Sandbox checkout session ({$sidGood}) issued valid 6-digit OTP");

    // Step A: Authorize without following callback yet so we capture the exact callback URL for replay testing
    $authOnly = spg_http($baseUrl . '/sandbox-gateway.php?session=' . urlencode($sidGood), $anonCookie, [
        'csrf_token'  => spg_csrf($gwGood['body']),
        'session'     => $sidGood,
        'gw_action'   => 'authorize',
        'sandbox_otp' => $goodOtp,
    ], false);
    $callbackUrl = $authOnly['redirect_url'];
    spg_assert(
        $authOnly['code'] === 302 && strpos($callbackUrl, 'action=gateway_callback') !== false,
        "Sandbox OTP authorization issued single-use signed callback redirect"
    );

    // Tampered callback signature test BEFORE valid consumption -> wait: use a separate tampered URL won't mark valid one failed unless we want to test good one first. Let's execute genuine callback first!
    $cbFirst = spg_http($callbackUrl, $anonCookie, null, true);
    spg_assert(
        $cbFirst['code'] === 200
        && strpos($cbFirst['url'], 'admission-status.php?ref=' . urlencode($ref1) . '&paid=1') !== false
        && strpos($cbFirst['body'], 'paymentVerifiedCardBanner') !== false
        && strpos($cbFirst['body'], 'admitCardPrint') !== false,
        "First genuine callback immediately displays Payment Verified Banner & Official Application Card"
    );

    $admPaid = $db->query("SELECT * FROM admissions WHERE id = {$admId1}")->fetch(PDO::FETCH_ASSOC);
    $txPaid = $db->query("SELECT * FROM admission_payment_transactions WHERE gateway_session_id = " . $db->quote($sidGood))->fetch(PDO::FETCH_ASSOC);
    if (!empty($admPaid['payment_screenshot'])) {
        $filesToDelete[] = BASE_PATH . '/storage/uploads/payments/' . basename($admPaid['payment_screenshot']);
    }
    if (!empty($admPaid['pdf_path'])) {
        $filesToDelete[] = BASE_PATH . '/' . $admPaid['pdf_path'];
    }

    spg_assert(
        $admPaid['payment_status'] === 'Paid'
        && !empty($admPaid['transaction_id'])
        && $txPaid['gateway_status'] === 'VERIFIED'
        && !empty($txPaid['validated_once_at'])
        && $admPaid['transaction_id'] === $txPaid['transaction_id'],
        "Database updated atomically: Paid + verified TrxID ({$admPaid['transaction_id']}) + single-use validated_once_at lock"
    );
    spg_assert(
        !empty($admPaid['pdf_path']) && is_file(BASE_PATH . '/' . $admPaid['pdf_path']) && filesize(BASE_PATH . '/' . $admPaid['pdf_path']) > 5000,
        "Application Card PDF synchronously generated on disk upon genuine verification"
    );

    // Download generated Application Card PDF & Payment Receipt via HTTP 200
    $paidPdfRes = spg_http($baseUrl . "/admission-document.php?id={$admId1}&doc=application&action=pdf", $anonCookie);
    spg_assert($paidPdfRes['code'] === 200 && strpos($paidPdfRes['body'], '%PDF-1.') === 0, "Verified Paid applicant can view/download Application Card PDF (HTTP 200)");
    $paidRecRes = spg_http($baseUrl . "/admission-document.php?id={$admId1}&doc=receipt&action=view", $anonCookie);
    spg_assert($paidRecRes['code'] === 200 && strpos($paidRecRes['body'], $admPaid['transaction_id']) !== false, "Verified Paid applicant can view Payment Receipt containing verified TrxID");

    // Step B: Replay EXACT SAME callback URL -> MUST validate transaction ONLY ONCE!
    $validatedOnceBefore = (string)$txPaid['validated_once_at'];
    $screenshotBefore    = (string)$admPaid['payment_screenshot'];

    $cbReplay = spg_http($callbackUrl, $anonCookie, null, true);
    $txAfterReplay  = $db->query("SELECT validated_once_at FROM admission_payment_transactions WHERE gateway_session_id = " . $db->quote($sidGood))->fetch(PDO::FETCH_ASSOC);
    $admAfterReplay = $db->query("SELECT payment_screenshot FROM admissions WHERE id = {$admId1}")->fetch(PDO::FETCH_ASSOC);

    spg_assert(
        $cbReplay['code'] === 200
        && strpos($cbReplay['body'], 'already verified once') !== false
        && (string)$txAfterReplay['validated_once_at'] === $validatedOnceBefore
        && (string)$admAfterReplay['payment_screenshot'] === $screenshotBefore,
        "Replaying identical callback does NOT revalidate Transaction ID or duplicate receipt"
    );

    // Re-visiting admission-payment.php or sandbox-gateway.php after Paid redirects directly to Application Card (never re-prompts)
    $revisitPay = spg_http($baseUrl . '/admission-payment.php?ref=' . urlencode($ref1), $anonCookie);
    spg_assert(strpos($revisitPay['url'], 'admission-status.php') !== false && strpos($revisitPay['body'], 'admitCardPrint') !== false, "Visiting admission-payment.php after Paid redirects straight to Application Card");
    $revisitGw = spg_http($baseUrl . '/sandbox-gateway.php?session=' . urlencode($sidGood), $anonCookie);
    spg_assert(strpos($revisitGw['url'], 'admission-status.php') !== false && strpos($revisitGw['body'], 'admitCardPrint') !== false, "Visiting sandbox-gateway.php after Paid redirects straight to Application Card");

    // Telemetry API returns verified status without exposing sensitive secrets
    $apiRes = spg_http($baseUrl . '/actions/sandbox-gateway-api.php?action=status&session=' . urlencode($sidGood), $anonCookie);
    $apiJson = json_decode($apiRes['body'], true);
    $apiData = $apiJson['data'] ?? [];
    spg_assert(
        $apiRes['code'] === 200
        && is_array($apiJson)
        && !empty($apiJson['ok'])
        && !empty($apiData['found'])
        && ($apiData['gateway_status'] ?? '') === 'VERIFIED'
        && !empty($apiData['validated_once'])
        && !array_key_exists('sandbox_otp', $apiData)
        && !array_key_exists('gateway_nonce', $apiData)
        && !array_key_exists('verification_signature', $apiData),
        "Sandbox Gateway API reports VERIFIED/validated_once=true while hiding OTP, nonce & signature secrets"
    );

    // -------------------------------------------------------------------------
    // 5. Admin Panel Verification, Visibility & Unpaid Card Locking
    // -------------------------------------------------------------------------
    echo "\n5. Verifying Admin Panel Payment & Application Visibility + Unpaid Record Lock:\n";
    $sub2 = $submitTestAdmission($anonCookie2, "Sandbox Unpaid Applicant Two {$suffix}", $mob2, "sbx2.{$suffix}@rmu.test");
    $adm2 = $sub2['row'];
    spg_assert(!empty($adm2['id']), "Unpaid Application #2 submitted ({$adm2['application_ref']})");
    $admId2 = (int)$adm2['id'];
    $createdAdmIds[] = $admId2;

    $admLoginGet = spg_http($baseUrl . '/admin/login.php', $adminCookie);
    spg_http($baseUrl . '/admin/login.php', $adminCookie, [
        'csrf_token' => spg_csrf($admLoginGet['body']),
        'username'   => 'admin',
        'password'   => 'Admin@RMU2026!',
    ]);

    $payAdminView = spg_http($baseUrl . '/admin/payments.php?tab=admissions&search=' . urlencode($ref1), $adminCookie);
    spg_assert(
        $payAdminView['code'] === 200
        && strpos($payAdminView['body'], $admPaid['transaction_id']) !== false
        && strpos($payAdminView['body'], $sidGood) !== false
        && strpos($payAdminView['body'], 'Verified Once:') !== false,
        "admin/payments.php displays TrxID, Sandbox Gateway Session ID & single-use verification lock"
    );

    $admConsolePaid = spg_http($baseUrl . '/admin/admissions.php?search=' . urlencode($ref1), $adminCookie);
    spg_assert(
        $admConsolePaid['code'] === 200
        && strpos($admConsolePaid['body'], $admPaid['transaction_id']) !== false
        && strpos($admConsolePaid['body'], "admission-document.php?id={$admId1}&amp;doc=application&amp;action=pdf") !== false,
        "admin/admissions.php exposes full Payment details & Application Card PDF link for Paid applicant"
    );

    $admConsoleUnpaid = spg_http($baseUrl . '/admin/admissions.php?search=' . urlencode((string)$adm2['application_ref']), $adminCookie);
    spg_assert(
        $admConsoleUnpaid['code'] === 200
        && strpos($admConsoleUnpaid['body'], 'Card Locked') !== false
        && strpos($admConsoleUnpaid['body'], "admission-document.php?id={$admId2}&amp;doc=application&amp;action=pdf") === false,
        "admin/admissions.php hides Paid Card/PDF buttons and shows 'Card Locked' for Unpaid applicant"
    );

    $payAdminUnpaid = spg_http($baseUrl . '/admin/payments.php?tab=admissions&search=' . urlencode((string)$adm2['application_ref']), $adminCookie);
    spg_assert(
        $payAdminUnpaid['code'] === 200 && spg_csrf($payAdminUnpaid['body']) !== '',
        "admin/payments.php renders Verify form & CSRF token for Pending applicant"
    );

    // Verify duplicate TrxID rejection in admin verification + genuine admin manual verification on #2
    $dupAdminTry = spg_http($baseUrl . '/admin/payments.php', $adminCookie, [
        'csrf_token'     => spg_csrf($payAdminUnpaid['body']),
        'action'         => 'verify',
        'type'           => 'admission',
        'id'             => $admId2,
        'transaction_id' => $admPaid['transaction_id'], // Collision with App #1!
    ]);
    $adm2AfterDup = $db->query("SELECT payment_status, pdf_path FROM admissions WHERE id = {$admId2}")->fetch(PDO::FETCH_ASSOC);
    spg_assert(
        strpos($dupAdminTry['body'], 'already linked to another application') !== false
        && $adm2AfterDup['payment_status'] !== 'Paid'
        && empty($adm2AfterDup['pdf_path']),
        "Admin manual verify blocked duplicate Transaction ID assignment across applications"
    );

    // Now verify Application #2 cleanly with unique TrxID -> generates PDF + slip
    $admVerifyOk = spg_http($baseUrl . '/admin/payments.php', $adminCookie, [
        'csrf_token' => spg_csrf($dupAdminTry['body']),
        'action'     => 'verify',
        'type'       => 'admission',
        'id'         => $admId2,
    ]);
    $adm2Verified = $db->query("SELECT payment_status, transaction_id, payment_screenshot, pdf_path FROM admissions WHERE id = {$admId2}")->fetch(PDO::FETCH_ASSOC);
    if (!empty($adm2Verified['payment_screenshot'])) {
        $filesToDelete[] = BASE_PATH . '/storage/uploads/payments/' . basename($adm2Verified['payment_screenshot']);
    }
    if (!empty($adm2Verified['pdf_path'])) {
        $filesToDelete[] = BASE_PATH . '/' . $adm2Verified['pdf_path'];
    }
    spg_assert(
        $admVerifyOk['code'] === 200
        && $adm2Verified['payment_status'] === 'Paid'
        && !empty($adm2Verified['transaction_id'])
        && !empty($adm2Verified['pdf_path'])
        && is_file(BASE_PATH . '/' . $adm2Verified['pdf_path']),
        "Admin payment verification creates verified ledger entry, SVG slip & Paid Application Card PDF"
    );

} finally {
    echo "\n6. Cleaning up Sandbox Gateway E2E artifacts:\n";
    foreach ([$anonCookie, $anonCookie2, $adminCookie] as $cf) {
        @unlink($cf);
    }
    foreach ($filesToDelete as $fp) {
        if ($fp !== '' && is_file($fp)) {
            @unlink($fp);
        }
    }
    foreach ($createdAdmIds as $aid) {
        foreach (glob(BASE_PATH . '/storage/admissions/pdf/*_' . $aid . '.pdf') ?: [] as $pg) {
            if (is_file($pg)) {
                @unlink($pg);
            }
        }
        $db->prepare("DELETE FROM admission_payment_transactions WHERE admission_id = ?")->execute([$aid]);
        $db->prepare("DELETE FROM academic_edit_history WHERE entity_type = 'admission' AND entity_id = ?")->execute([$aid]);
        $db->prepare("DELETE FROM admissions WHERE id = ?")->execute([$aid]);
    }
    $db->prepare("DELETE FROM admission_id_reservations WHERE id > ?")->execute([$maxResIdBefore]);
    $db->prepare("DELETE FROM audit_logs WHERE id > ?")->execute([$maxAuditBefore]);
    foreach ($seqSnapshot as $sr) {
        $db->prepare("UPDATE admission_sequences SET last_app_seq = ?, last_roll_seq = ?, updated_at = NOW() WHERE seq_year = ?")
           ->execute([(int)$sr['last_app_seq'], (int)$sr['last_roll_seq'], (int)$sr['seq_year']]);
    }
    echo "  Cleanup complete (" . count($createdAdmIds) . " test applications removed).\n";
}

echo "\n-------------------------------------------------------\n";
echo "SANDBOX PAYMENT GATEWAY E2E SUMMARY: PASSED = {$passed} | FAILED = {$failed}\n";
echo "-------------------------------------------------------\n";
exit($failed > 0 ? 1 : 0);
