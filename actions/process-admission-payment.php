<?php
/**
 * Action: Admission ৳150 Fee Payment Gateway Controller & Single-Use Callback Verifier
 *
 * Supported flows:
 *  1) POST action=initiate_sandbox_payment -> Creates/reuses idempotent Sandbox checkout session & redirects to sandbox-gateway.php
 *  2) GET/POST action=gateway_callback   -> Validates HMAC-signed Sandbox authorization ONCE (`validated_once_at`),
 *                                           marks application Paid atomically, writes SVG slip, generates A4 PDF,
 *                                           and redirects to admission-status.php?ref=...&paid=1
 *  3) Rejects any legacy/direct transaction_id spoof attempt without a verified Sandbox Gateway session.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
ensure_admission_payment_gateway_schema($db);

$requestAction = trim((string)($_REQUEST['action'] ?? ''));

// =========================================================================
// 1. SANDBOX GATEWAY CALLBACK VERIFICATION (SINGLE-USE)
// =========================================================================
if ($requestAction === 'gateway_callback') {
    $sessionId = trim((string)($_REQUEST['session'] ?? ''));
    $trxId     = trim((string)($_REQUEST['trx'] ?? ''));
    $signature = trim((string)($_REQUEST['sig'] ?? ''));

    $verify = rmu_sandbox_verify_and_complete_payment($db, $sessionId, $trxId, $signature);

    if (!empty($verify['success'])) {
        $verifiedAdm = $verify['admission'] ?? [];
        $canonicalRef = (string)($verifiedAdm['application_ref'] ?? '');
        if ($canonicalRef !== '') {
            authorize_applicant_session_ref(
                $canonicalRef,
                $verifiedAdm['application_id'] ?? null,
                $verifiedAdm['roll_number'] ?? null
            );
        }

        if (!empty($verify['already_verified'])) {
            flash_set('info', (string)($verify['message'] ?? 'This payment transaction has already been verified once.'));
            header("Location: " . APP_URL . "/admission-status.php?ref=" . urlencode($canonicalRef));
            exit;
        }

        flash_set('success', (string)($verify['message'] ?? '৳150 Application Fee verified successfully!'));
        header("Location: " . APP_URL . "/admission-status.php?ref=" . urlencode($canonicalRef) . "&paid=1");
        exit;
    }

    // Verification failed or replay blocked: keep Paid card locked and show clear error
    $errRef = trim((string)($verify['admission_ref'] ?? ''));
    if ($errRef !== '') {
        $admRow = fetch_admission_full_record(0, $errRef, $db, false);
        if ($admRow) {
            authorize_applicant_session_ref(
                (string)$admRow['application_ref'],
                $admRow['application_id'] ?? null,
                $admRow['roll_number'] ?? null
            );
            $errRef = (string)$admRow['application_ref'];
        }
        flash_set('error', (string)($verify['error'] ?? 'Payment verification failed. Please retry via the Sandbox Gateway.'));
        header("Location: " . APP_URL . "/admission-payment.php?ref=" . urlencode($errRef));
        exit;
    }

    flash_set('error', (string)($verify['error'] ?? 'Payment verification failed.'));
    header("Location: " . APP_URL . "/admission-status.php");
    exit;
}

// Non-callback GET requests redirect cleanly to Admission Form
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header("Location: " . APP_URL . "/admission-form.php");
    exit;
}

// =========================================================================
// 2. INITIATE SANDBOX GATEWAY SESSION (POST)
// =========================================================================
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    flash_set('error', 'Session expired. Please start your payment checkout again.');
    $postedRef = trim((string)($_POST['ref'] ?? ''));
    if ($postedRef !== '') {
        header("Location: " . APP_URL . "/admission-payment.php?ref=" . urlencode($postedRef));
    } else {
        header("Location: " . APP_URL . "/admission-form.php");
    }
    exit;
}

$ref = trim((string)($_POST['ref'] ?? ''));
$stmt = $db->prepare("SELECT * FROM admissions WHERE application_ref = ? OR application_id = ? OR roll_number = ? LIMIT 1");
$stmt->execute([$ref, $ref, $ref]);
$adm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adm) {
    flash_set('error', 'Application record not found.');
    header("Location: " . APP_URL . "/admission-status.php");
    exit;
}

$canonicalRef = (string)$adm['application_ref'];
authorize_applicant_session_ref(
    $canonicalRef,
    $adm['application_id'] ?? null,
    $adm['roll_number'] ?? null
);

// Strictly block any client POST attempting to bypass Sandbox Gateway with a direct fake/manual Transaction ID
if ($requestAction !== 'initiate_sandbox_payment' || isset($_POST['transaction_id'])) {
    log_audit(
        'ADMISSION_PAYMENT_BYPASS_BLOCKED',
        "Blocked direct/unverified payment submission for {$canonicalRef} (action='{$requestAction}')"
    );
    flash_set('error', 'Direct Transaction ID submission is disabled. Please complete verification securely through the Sandbox Payment Gateway.');
    header("Location: " . APP_URL . "/admission-payment.php?ref=" . urlencode($canonicalRef));
    exit;
}

// Idempotent guard: never create a duplicate checkout if application is already Paid
if (($adm['payment_status'] ?? '') === 'Paid') {
    flash_set('info', 'This admission application fee has already been paid and verified (TrxID: ' . ($adm['transaction_id'] ?: 'Verified') . ').');
    header("Location: " . APP_URL . "/admission-status.php?ref=" . urlencode($canonicalRef));
    exit;
}

$method = rmu_sandbox_normalize_method($_POST['payment_method'] ?? 'bKash');
$senderInput = trim((string)($_POST['sender_number'] ?? ''));

if ($senderInput !== '') {
    if (!validate_bd_mobile($senderInput)) {
        flash_set('error', 'Please enter a valid 11-digit Bangladeshi mobile number (e.g. 017XXXXXXXX).');
        header("Location: " . APP_URL . "/admission-payment.php?ref=" . urlencode($canonicalRef));
        exit;
    }
    $senderNumber = normalize_bd_mobile($senderInput);
} else {
    $senderNumber = normalize_bd_mobile((string)$adm['mobile']);
}

$idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
$checkout = rmu_sandbox_create_checkout_session($db, $adm, $method, $senderNumber, $idempotencyKey);

if (!empty($checkout['already_paid'])) {
    flash_set('info', 'Application fee of ৳150 is already paid and verified.');
    header("Location: " . APP_URL . "/admission-status.php?ref=" . urlencode($canonicalRef));
    exit;
}

if (empty($checkout['success']) || empty($checkout['session']['gateway_session_id'])) {
    flash_set('error', (string)($checkout['error'] ?? 'Could not initialize Sandbox Payment Gateway session. Please try again.'));
    header("Location: " . APP_URL . "/admission-payment.php?ref=" . urlencode($canonicalRef));
    exit;
}

$gatewaySessionId = (string)$checkout['session']['gateway_session_id'];
header("Location: " . APP_URL . "/sandbox-gateway.php?session=" . urlencode($gatewaySessionId));
exit;
