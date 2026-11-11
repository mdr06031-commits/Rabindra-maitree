<?php
/**
 * Rabindra Maitree University (RMU)
 * Sandbox Payment Gateway Engine & Single-Use Transaction Verification
 *
 * Enforces:
 *  - Server-side checkout session creation with idempotency keys & expiry
 *  - Sandbox OTP authorization that generates a unique cryptographic TrxID + HMAC signature
 *  - Single-use Transaction ID validation (`validated_once_at` guard + DB UNIQUE constraints)
 *  - Atomic `Paid` state transition + SVG receipt + synchronous Paid Application Card PDF generation
 *  - Clear failure/cancellation state handling (never marks Paid or generates Paid Card on failure)
 */

if (!defined('RMU_ADMISSION_FEE_AMOUNT')) {
    define('RMU_ADMISSION_FEE_AMOUNT', 150.00);
}
if (!defined('RMU_ADMISSION_FEE_CURRENCY')) {
    define('RMU_ADMISSION_FEE_CURRENCY', 'BDT');
}
if (!defined('RMU_SANDBOX_OFFICIAL_MERCHANT')) {
    define('RMU_SANDBOX_OFFICIAL_MERCHANT', '01336367336');
}

function rmu_sandbox_valid_methods(): array {
    return ['bKash', 'Nagad', 'Rocket', 'Upay', 'Online Banking / Card'];
}

function rmu_sandbox_normalize_method(?string $method): string {
    $m = trim((string)$method);
    return in_array($m, rmu_sandbox_valid_methods(), true) ? $m : 'bKash';
}

function rmu_sandbox_trx_prefix(string $method): string {
    return match ($method) {
        'bKash'                 => 'SBK',
        'Nagad'                 => 'SNG',
        'Rocket'                => 'SRK',
        'Upay'                  => 'SUP',
        'Online Banking / Card' => 'SCD',
        default                 => 'SGP',
    };
}

/**
 * Retrieve or initialize server-only HMAC signing secret for Sandbox Gateway verification.
 * Stored as a PHP array return file inside config/ so neither Apache nor PHP built-in server exposes it over HTTP.
 */
function rmu_sandbox_gateway_secret(): string {
    static $cachedSecret = null;
    if ($cachedSecret !== null && $cachedSecret !== '') {
        return $cachedSecret;
    }

    $secretFile = BASE_PATH . '/config/gateway_secret.php';
    if (is_file($secretFile)) {
        $loaded = @include $secretFile;
        if (is_array($loaded) && !empty($loaded['secret']) && is_string($loaded['secret'])) {
            $cachedSecret = $loaded['secret'];
            return $cachedSecret;
        }
    }

    $secret = bin2hex(random_bytes(32));
    $phpPayload = "<?php\n/** Auto-generated server-only Sandbox Gateway HMAC secret. */\nreturn ['secret' => '" . $secret . "'];\n";
    $tmpFile = BASE_PATH . '/config/.gateway_secret_' . bin2hex(random_bytes(4)) . '.tmp.php';
    if (@file_put_contents($tmpFile, $phpPayload, LOCK_EX) !== false) {
        @chmod($tmpFile, 0640);
        if (!@rename($tmpFile, $secretFile)) {
            @unlink($tmpFile);
            @file_put_contents($secretFile, $phpPayload, LOCK_EX);
        }
    }

    if (is_file($secretFile)) {
        $loaded = @include $secretFile;
        if (is_array($loaded) && !empty($loaded['secret'])) {
            $cachedSecret = (string)$loaded['secret'];
            return $cachedSecret;
        }
    }

    // Deterministic process fallback if config dir were read-only
    $dbCfg = is_file(BASE_PATH . '/config/database.php') ? (string)@file_get_contents(BASE_PATH . '/config/database.php') : BASE_PATH;
    $cachedSecret = hash('sha256', 'RMU_SANDBOX_FALLBACK|' . APP_NAME . '|' . $dbCfg);
    return $cachedSecret;
}

/**
 * Sign canonical payment verification payload with HMAC-SHA256.
 */
function rmu_sandbox_sign_payload(
    string $sessionId,
    string $trxId,
    string $appRef,
    float $amount,
    string $currency,
    string $nonce
): string {
    $canonical = sprintf(
        'RMU-SBX-V1|%s|%s|%s|%.2f|%s|%s',
        trim($sessionId),
        strtoupper(trim($trxId)),
        trim($appRef),
        $amount,
        strtoupper(trim($currency)),
        trim($nonce)
    );
    return hash_hmac('sha256', $canonical, rmu_sandbox_gateway_secret());
}

/**
 * Ensure `admission_payment_transactions` ledger table and unique Transaction ID constraint exist.
 */
function ensure_admission_payment_gateway_schema(PDO $db): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS admission_payment_transactions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admission_id INT UNSIGNED NOT NULL,
            application_ref VARCHAR(50) NOT NULL,
            gateway_session_id VARCHAR(64) NOT NULL UNIQUE,
            idempotency_key VARCHAR(64) NOT NULL,
            transaction_id VARCHAR(80) NULL UNIQUE,
            gateway_provider VARCHAR(60) NOT NULL DEFAULT 'bKash',
            gateway_mode ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox',
            amount DECIMAL(10,2) NOT NULL DEFAULT 150.00,
            paid_amount DECIMAL(10,2) NULL,
            currency CHAR(3) NOT NULL DEFAULT 'BDT',
            sender_number VARCHAR(30) NULL,
            sandbox_otp VARCHAR(12) NOT NULL,
            attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
            gateway_status ENUM('INITIATED','AUTHORIZED','VERIFIED','FAILED','CANCELLED','EXPIRED','SUPERSEDED') NOT NULL DEFAULT 'INITIATED',
            status ENUM('initiated','pending_verification','verified','failed','cancelled','expired','superseded') NOT NULL DEFAULT 'initiated',
            verification_signature VARCHAR(128) NULL,
            gateway_nonce VARCHAR(64) NOT NULL,
            gateway_response_json LONGTEXT NULL,
            failure_reason VARCHAR(255) NULL,
            authorized_at DATETIME NULL,
            validated_once_at DATETIME NULL,
            verified_at DATETIME NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_apt_admission (admission_id),
            INDEX idx_apt_app_idem (admission_id, idempotency_key),
            INDEX idx_apt_status (gateway_status, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    try {
        // Normalize blank strings to NULL so SQL UNIQUE index allows multiple pending unpaid rows cleanly
        $db->exec("UPDATE admissions SET transaction_id = NULL WHERE transaction_id = ''");

        $idxStmt = $db->query("SHOW INDEX FROM admissions WHERE Key_name = 'uq_admissions_transaction_id'");
        $hasUq = $idxStmt && $idxStmt->fetch(PDO::FETCH_ASSOC);
        if (!$hasUq) {
            $dupChk = $db->query("
                SELECT transaction_id FROM admissions
                WHERE transaction_id IS NOT NULL
                GROUP BY transaction_id
                HAVING COUNT(*) > 1
                LIMIT 1
            ");
            if (!$dupChk || !$dupChk->fetchColumn()) {
                $db->exec("ALTER TABLE admissions ADD UNIQUE INDEX uq_admissions_transaction_id (transaction_id)");
            }
        }
    } catch (Throwable $e) {
        error_log('Gateway schema index sync warning: ' . $e->getMessage());
    }

    $ensured = true;
}

/**
 * Generate the official payment receipt SVG slip markup.
 */
function rmu_generate_admission_payment_slip_svg(
    array $adm,
    string $method,
    string $senderNumber,
    string $transactionId,
    ?string $verifiedAt = null
): string {
    $canonicalRef = (string)($adm['application_ref'] ?? '');
    $receiptAppId = htmlspecialchars(!empty($adm['application_id']) ? (string)$adm['application_id'] : $canonicalRef, ENT_XML1, 'UTF-8');
    $receiptRoll  = htmlspecialchars(!empty($adm['roll_number']) ? (string)$adm['roll_number'] : (string)($adm['exam_roll'] ?? 'N/A'), ENT_XML1, 'UTF-8');
    $receiptName  = htmlspecialchars((string)($adm['full_name'] ?? ''), ENT_XML1, 'UTF-8');
    $receiptMethod = htmlspecialchars($method, ENT_XML1, 'UTF-8');
    $receiptSender = htmlspecialchars($senderNumber, ENT_XML1, 'UTF-8');
    $receiptTrx    = htmlspecialchars($transactionId, ENT_XML1, 'UTF-8');
    $receiptTime   = htmlspecialchars($verifiedAt ?: date('Y-m-d H:i:s'), ENT_XML1, 'UTF-8');

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="445" viewBox="0 0 600 445">
  <rect width="600" height="445" rx="16" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2"/>
  <rect width="600" height="85" fill="#15803d"/>
  <circle cx="50" cy="42" r="24" fill="#ffffff" opacity="0.2"/>
  <text x="50" y="49" font-family="Arial, sans-serif" font-size="22" font-weight="bold" fill="#ffffff" text-anchor="middle">&#10003;</text>
  <text x="90" y="40" font-family="Arial, sans-serif" font-size="20" font-weight="bold" fill="#ffffff">Rabindra Maitree University</text>
  <text x="90" y="62" font-family="Arial, sans-serif" font-size="13" fill="#dcfce7">Official Online Intake Fee Receipt - &#2547;150 BDT (Gateway Verified)</text>
  <rect x="25" y="105" width="550" height="315" rx="12" fill="#ffffff" stroke="#e2e8f0"/>
  <text x="45" y="140" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Application ID:</text>
  <text x="230" y="140" font-family="monospace" font-size="15" font-weight="bold" fill="#1e40af">{$receiptAppId}</text>
  <text x="45" y="172" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Admission Roll Number:</text>
  <text x="230" y="172" font-family="monospace" font-size="15" font-weight="bold" fill="#6d28d9">{$receiptRoll}</text>
  <text x="45" y="204" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Candidate Legal Name:</text>
  <text x="230" y="204" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#0f172a">{$receiptName}</text>
  <text x="45" y="236" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Payment Recipient No:</text>
  <text x="230" y="236" font-family="monospace" font-size="14" font-weight="bold" fill="#15803d">01336367336 (RMU Merchant)</text>
  <text x="45" y="268" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Payment Method:</text>
  <text x="230" y="268" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#be123c">{$receiptMethod} (Sandbox)</text>
  <text x="45" y="300" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Sender Mobile Number:</text>
  <text x="230" y="300" font-family="monospace" font-size="14" font-weight="bold" fill="#0f172a">{$receiptSender}</text>
  <text x="45" y="332" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Transaction ID (TrxID):</text>
  <text x="230" y="332" font-family="monospace" font-size="15" font-weight="bold" fill="#b91c1c">{$receiptTrx}</text>
  <line x1="45" y1="354" x2="555" y2="354" stroke="#e2e8f0" stroke-width="1.5" stroke-dasharray="4"/>
  <text x="45" y="384" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#15803d">Total Amount Paid:</text>
  <text x="230" y="384" font-family="Arial, sans-serif" font-size="18" font-weight="900" fill="#15803d">&#2547; 150.00 BDT (Verified)</text>
  <text x="45" y="405" font-family="Arial, sans-serif" font-size="11" fill="#94a3b8">Verified at: {$receiptTime} &bull; Permanent Campus, Kushtia</text>
</svg>
SVG;
}

/**
 * Create (or idempotently reuse) a Sandbox Payment Gateway checkout session for an unpaid admission.
 */
function rmu_sandbox_create_checkout_session(
    PDO $db,
    array $adm,
    string $method,
    string $senderNumber,
    string $idempotencyKey = ''
): array {
    ensure_admission_payment_gateway_schema($db);

    $admId = (int)($adm['id'] ?? 0);
    if ($admId <= 0) {
        return ['success' => false, 'error' => 'Invalid admission application ID.'];
    }

    $cleanMethod = rmu_sandbox_normalize_method($method);
    $cleanSender = normalize_bd_mobile($senderNumber !== '' ? $senderNumber : (string)($adm['mobile'] ?? ''));
    $cleanIdem   = preg_replace('/[^A-Za-z0-9_-]/', '', trim($idempotencyKey));
    if ($cleanIdem === '') {
        $cleanIdem = 'idem_' . bin2hex(random_bytes(16));
    }
    $cleanIdem = substr($cleanIdem, 0, 64);

    $db->beginTransaction();
    try {
        $lockAdm = $db->prepare("SELECT * FROM admissions WHERE id = ? FOR UPDATE");
        $lockAdm->execute([$admId]);
        $lockedAdm = $lockAdm->fetch(PDO::FETCH_ASSOC);

        if (!$lockedAdm) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Application record was not found.'];
        }

        if (($lockedAdm['payment_status'] ?? '') === 'Paid') {
            $db->commit();
            return [
                'success'      => true,
                'already_paid' => true,
                'admission'    => $lockedAdm,
            ];
        }

        // Expire stale unverified sessions first
        $db->prepare("
            UPDATE admission_payment_transactions
            SET gateway_status = 'EXPIRED',
                status = 'expired',
                failure_reason = 'Checkout session timed out',
                updated_at = NOW()
            WHERE admission_id = ?
              AND gateway_status IN ('INITIATED', 'AUTHORIZED')
              AND expires_at < NOW()
        ")->execute([$admId]);

        // Idempotent reuse check for active unverified session matching exact idempotency_key
        $existingStmt = $db->prepare("
            SELECT * FROM admission_payment_transactions
            WHERE admission_id = ? AND idempotency_key = ?
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $existingStmt->execute([$admId, $cleanIdem]);
        $existingTx = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingTx && $existingTx['gateway_status'] === 'INITIATED' && strtotime((string)$existingTx['expires_at']) > time()) {
            $db->commit();
            return [
                'success'      => true,
                'already_paid' => false,
                'reused'       => true,
                'session'      => $existingTx,
                'admission'    => $lockedAdm,
            ];
        }

        // Supersede any other open INITIATED/AUTHORIZED sessions for this admission before creating a fresh checkout
        $db->prepare("
            UPDATE admission_payment_transactions
            SET gateway_status = 'SUPERSEDED',
                status = 'superseded',
                failure_reason = 'Superseded by a newer payment attempt',
                updated_at = NOW()
            WHERE admission_id = ?
              AND gateway_status IN ('INITIATED', 'AUTHORIZED')
              AND validated_once_at IS NULL
        ")->execute([$admId]);

        $sessionId = 'RMUSBX-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(5)));
        $otp       = (string)random_int(100000, 999999);
        $nonce     = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', time() + 900); // 15 minutes
        $appRef    = (string)($lockedAdm['application_id'] ?: $lockedAdm['application_ref']);

        $ins = $db->prepare("
            INSERT INTO admission_payment_transactions (
                admission_id, application_ref, gateway_session_id, idempotency_key,
                gateway_provider, gateway_mode, amount, currency, sender_number,
                sandbox_otp, attempt_count, gateway_status, status, gateway_nonce,
                expires_at, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, 'sandbox', ?, ?, ?,
                ?, 0, 'INITIATED', 'initiated', ?,
                ?, NOW(), NOW()
            )
        ");
        $ins->execute([
            $admId,
            $appRef,
            $sessionId,
            $cleanIdem,
            $cleanMethod,
            RMU_ADMISSION_FEE_AMOUNT,
            RMU_ADMISSION_FEE_CURRENCY,
            $cleanSender,
            $otp,
            $nonce,
            $expiresAt,
        ]);
        $newTxId = (int)$db->lastInsertId();

        $db->prepare("
            UPDATE admissions
            SET payment_status = 'Under Verification',
                payment_amount = ?,
                payment_method = ?,
                sender_number = ?,
                payment_gateway_mode = 'sandbox'
            WHERE id = ? AND payment_status != 'Paid'
        ")->execute([RMU_ADMISSION_FEE_AMOUNT, $cleanMethod, $cleanSender, $admId]);

        $fetchNew = $db->prepare("SELECT * FROM admission_payment_transactions WHERE id = ? LIMIT 1");
        $fetchNew->execute([$newTxId]);
        $txRow = $fetchNew->fetch(PDO::FETCH_ASSOC);

        $db->commit();

        if (function_exists('log_audit')) {
            log_audit(
                'ADMISSION_PAYMENT_INITIATED',
                "Initiated ৳150 Sandbox Gateway session {$sessionId} via {$cleanMethod} for {$lockedAdm['application_ref']}"
            );
        }

        return [
            'success'      => true,
            'already_paid' => false,
            'reused'       => false,
            'session'      => $txRow,
            'admission'    => $lockedAdm,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Sandbox session initiation error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Unable to initialize sandbox payment session. Please try again.'];
    }
}

/**
 * Fetch a Sandbox Gateway checkout session with its parent admission details.
 */
function rmu_sandbox_get_session(PDO $db, string $sessionId): ?array {
    ensure_admission_payment_gateway_schema($db);
    $cleanSid = trim($sessionId);
    if ($cleanSid === '') {
        return null;
    }

    $stmt = $db->prepare("
        SELECT t.*,
               a.id AS adm_pk,
               a.application_ref AS adm_application_ref,
               a.application_id AS adm_application_id,
               a.roll_number AS adm_roll_number,
               a.full_name AS adm_full_name,
               a.mobile AS adm_mobile,
               a.email AS adm_email,
               a.interested_subject AS adm_interested_subject,
               a.academic_session AS adm_academic_session,
               a.payment_status AS adm_payment_status,
               a.transaction_id AS adm_transaction_id,
               a.status AS adm_status
        FROM admission_payment_transactions t
        JOIN admissions a ON t.admission_id = a.id
        WHERE t.gateway_session_id = ?
        LIMIT 1
    ");
    $stmt->execute([$cleanSid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    if (in_array($row['gateway_status'], ['INITIATED', 'AUTHORIZED'], true) && strtotime((string)$row['expires_at']) < time()) {
        $db->prepare("
            UPDATE admission_payment_transactions
            SET gateway_status = 'EXPIRED', status = 'expired', failure_reason = 'Checkout session expired', updated_at = NOW()
            WHERE id = ? AND validated_once_at IS NULL
        ")->execute([(int)$row['id']]);
        $row['gateway_status'] = 'EXPIRED';
        $row['status'] = 'expired';
        $row['failure_reason'] = 'Checkout session expired';
    }

    return $row;
}

/**
 * Authorize payment inside the Sandbox Gateway after validating the 6-digit Sandbox OTP.
 * Produces the unique gateway Transaction ID + HMAC signature for the single callback verification.
 */
function rmu_sandbox_authorize_session(PDO $db, string $sessionId, string $submittedOtp): array {
    ensure_admission_payment_gateway_schema($db);
    $cleanSid = trim($sessionId);
    $cleanOtp = trim($submittedOtp);

    if ($cleanSid === '') {
        return ['success' => false, 'code' => 'MISSING_SESSION', 'fatal' => true, 'error' => 'Missing gateway session identifier.'];
    }

    $db->beginTransaction();
    try {
        $txStmt = $db->prepare("SELECT * FROM admission_payment_transactions WHERE gateway_session_id = ? LIMIT 1 FOR UPDATE");
        $txStmt->execute([$cleanSid]);
        $tx = $txStmt->fetch(PDO::FETCH_ASSOC);

        if (!$tx) {
            $db->rollBack();
            return ['success' => false, 'code' => 'NOT_FOUND', 'fatal' => true, 'error' => 'Sandbox payment session was not found.'];
        }

        $admStmt = $db->prepare("SELECT * FROM admissions WHERE id = ? LIMIT 1 FOR UPDATE");
        $admStmt->execute([(int)$tx['admission_id']]);
        $adm = $admStmt->fetch(PDO::FETCH_ASSOC);

        if (!$adm) {
            $db->rollBack();
            return ['success' => false, 'code' => 'ADM_NOT_FOUND', 'fatal' => true, 'error' => 'Linked admission record does not exist.'];
        }

        if (($adm['payment_status'] ?? '') === 'Paid' || $tx['gateway_status'] === 'VERIFIED') {
            $db->commit();
            return [
                'success'      => true,
                'already_paid' => true,
                'session'      => $tx,
                'admission'    => $adm,
            ];
        }

        if (strtotime((string)$tx['expires_at']) < time()) {
            $db->prepare("
                UPDATE admission_payment_transactions
                SET gateway_status = 'EXPIRED', status = 'expired', failure_reason = 'Checkout session expired', updated_at = NOW()
                WHERE id = ?
            ")->execute([(int)$tx['id']]);
            if ($adm['payment_status'] === 'Under Verification') {
                $db->prepare("UPDATE admissions SET payment_status = 'Failed' WHERE id = ? AND payment_status != 'Paid'")->execute([(int)$adm['id']]);
            }
            $db->commit();
            return [
                'success' => false,
                'code'    => 'EXPIRED',
                'fatal'   => true,
                'error'   => 'Sandbox payment session has expired. Please start a new payment from your admission invoice.',
                'admission_ref' => $adm['application_ref'],
            ];
        }

        // Idempotent double-submit protection if already AUTHORIZED in this exact session
        if ($tx['gateway_status'] === 'AUTHORIZED' && !empty($tx['transaction_id']) && !empty($tx['verification_signature'])) {
            if (!hash_equals((string)$tx['sandbox_otp'], $cleanOtp)) {
                $db->commit();
                return ['success' => false, 'code' => 'INVALID_OTP', 'fatal' => false, 'attempts_left' => max(0, 3 - (int)$tx['attempt_count']), 'error' => 'Invalid Sandbox OTP entered.'];
            }
            $db->commit();
            return [
                'success'         => true,
                'already_paid'    => false,
                'already_authed'  => true,
                'session_id'      => $cleanSid,
                'transaction_id'  => (string)$tx['transaction_id'],
                'signature'       => (string)$tx['verification_signature'],
                'application_ref' => (string)$adm['application_ref'],
                'admission'       => $adm,
            ];
        }

        if ($tx['gateway_status'] !== 'INITIATED') {
            $db->commit();
            return [
                'success'       => false,
                'code'          => 'INVALID_STATE',
                'fatal'         => true,
                'error'         => 'Payment session state (' . $tx['gateway_status'] . ') cannot be authorized. Please initiate a fresh payment.',
                'admission_ref' => $adm['application_ref'],
            ];
        }

        // Validate Sandbox OTP
        if ($cleanOtp === '' || !hash_equals((string)$tx['sandbox_otp'], $cleanOtp)) {
            $newAttempts = (int)$tx['attempt_count'] + 1;
            if ($newAttempts >= 3) {
                $reason = 'Exceeded maximum Sandbox OTP verification attempts (3/3)';
                $db->prepare("
                    UPDATE admission_payment_transactions
                    SET attempt_count = ?, gateway_status = 'FAILED', status = 'failed', failure_reason = ?, updated_at = NOW()
                    WHERE id = ?
                ")->execute([$newAttempts, $reason, (int)$tx['id']]);
                $db->prepare("UPDATE admissions SET payment_status = 'Failed' WHERE id = ? AND payment_status != 'Paid'")
                   ->execute([(int)$adm['id']]);
                $db->commit();
                if (function_exists('invalidate_admission_application_pdf_cache')) {
                    invalidate_admission_application_pdf_cache((int)$adm['id'], $db);
                }
                if (function_exists('log_audit')) {
                    log_audit('ADMISSION_PAYMENT_FAILED', "Sandbox OTP locked for {$adm['application_ref']} (Session: {$cleanSid})");
                }
                return [
                    'success'       => false,
                    'code'          => 'MAX_OTP_ATTEMPTS',
                    'fatal'         => true,
                    'attempts_left' => 0,
                    'error'         => 'Payment failed after 3 incorrect Sandbox OTP attempts. Please retry from the payment page.',
                    'admission_ref' => $adm['application_ref'],
                ];
            }

            $db->prepare("UPDATE admission_payment_transactions SET attempt_count = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$newAttempts, (int)$tx['id']]);
            $db->commit();
            $left = 3 - $newAttempts;
            return [
                'success'       => false,
                'code'          => 'INVALID_OTP',
                'fatal'         => false,
                'attempts_left' => $left,
                'error'         => "Invalid Sandbox Verification Code (OTP). {$left} attempt(s) remaining.",
                'admission_ref' => $adm['application_ref'],
            ];
        }

        // Generate guaranteed-unique Sandbox Transaction ID inside transaction
        $prefix = rmu_sandbox_trx_prefix((string)$tx['gateway_provider']);
        $trxId = '';
        for ($i = 0; $i < 8; $i++) {
            $candidate = $prefix . date('ymd') . strtoupper(bin2hex(random_bytes(4)));
            $c1 = $db->prepare("SELECT 1 FROM admission_payment_transactions WHERE transaction_id = ? LIMIT 1");
            $c1->execute([$candidate]);
            $c2 = $db->prepare("SELECT 1 FROM admissions WHERE transaction_id = ? LIMIT 1");
            $c2->execute([$candidate]);
            if (!$c1->fetchColumn() && !$c2->fetchColumn()) {
                $trxId = $candidate;
                break;
            }
        }
        if ($trxId === '') {
            throw new RuntimeException('Failed to allocate unique Sandbox Transaction ID.');
        }

        $appRefForSig = (string)$tx['application_ref'];
        $amount = (float)$tx['amount'];
        $currency = (string)$tx['currency'];
        $sig = rmu_sandbox_sign_payload($cleanSid, $trxId, $appRefForSig, $amount, $currency, (string)$tx['gateway_nonce']);

        $gatewayPayload = json_encode([
            'gateway'            => 'RMU Sandbox Payment Gateway',
            'provider'           => $tx['gateway_provider'],
            'mode'               => 'sandbox',
            'merchant_account'   => RMU_SANDBOX_OFFICIAL_MERCHANT,
            'gateway_session_id' => $cleanSid,
            'transaction_id'     => $trxId,
            'application_ref'    => $appRefForSig,
            'sender_number'      => $tx['sender_number'],
            'amount'             => number_format($amount, 2, '.', ''),
            'currency'           => $currency,
            'gateway_status'     => 'AUTHORIZED',
            'authorized_at'      => date('c'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $db->prepare("
            UPDATE admission_payment_transactions
            SET transaction_id = ?,
                paid_amount = ?,
                gateway_status = 'AUTHORIZED',
                status = 'pending_verification',
                verification_signature = ?,
                gateway_response_json = ?,
                failure_reason = NULL,
                authorized_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$trxId, $amount, $sig, $gatewayPayload, (int)$tx['id']]);

        $db->commit();

        return [
            'success'         => true,
            'already_paid'    => false,
            'already_authed'  => false,
            'session_id'      => $cleanSid,
            'transaction_id'  => $trxId,
            'signature'       => $sig,
            'application_ref' => (string)$adm['application_ref'],
            'admission'       => $adm,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Sandbox authorization error: ' . $e->getMessage());
        return ['success' => false, 'code' => 'SYSTEM_ERROR', 'fatal' => true, 'error' => 'Gateway authorization error. Please try again.'];
    }
}

/**
 * Mark a Sandbox Gateway session as FAILED or CANCELLED (never touches already Paid admissions).
 */
function rmu_sandbox_mark_session_outcome(PDO $db, string $sessionId, string $outcome, string $reason): array {
    ensure_admission_payment_gateway_schema($db);
    $cleanSid = trim($sessionId);
    $targetOutcome = ($outcome === 'CANCELLED') ? 'CANCELLED' : 'FAILED';
    $targetStatus  = ($targetOutcome === 'CANCELLED') ? 'cancelled' : 'failed';

    $db->beginTransaction();
    try {
        $txStmt = $db->prepare("SELECT * FROM admission_payment_transactions WHERE gateway_session_id = ? LIMIT 1 FOR UPDATE");
        $txStmt->execute([$cleanSid]);
        $tx = $txStmt->fetch(PDO::FETCH_ASSOC);
        if (!$tx) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Gateway session not found.'];
        }

        $admStmt = $db->prepare("SELECT * FROM admissions WHERE id = ? LIMIT 1 FOR UPDATE");
        $admStmt->execute([(int)$tx['admission_id']]);
        $adm = $admStmt->fetch(PDO::FETCH_ASSOC);
        if (!$adm) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Admission record not found.'];
        }

        if (($adm['payment_status'] ?? '') === 'Paid' || $tx['gateway_status'] === 'VERIFIED') {
            $db->commit();
            return ['success' => true, 'already_paid' => true, 'admission' => $adm];
        }

        if (in_array($tx['gateway_status'], ['INITIATED', 'AUTHORIZED'], true) && $tx['validated_once_at'] === null) {
            $db->prepare("
                UPDATE admission_payment_transactions
                SET gateway_status = ?, status = ?, failure_reason = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$targetOutcome, $targetStatus, substr(trim($reason), 0, 250), (int)$tx['id']]);

            $newAdmPayStatus = ($targetOutcome === 'CANCELLED') ? 'Pending' : 'Failed';
            $db->prepare("
                UPDATE admissions
                SET payment_status = ?
                WHERE id = ? AND payment_status != 'Paid'
            ")->execute([$newAdmPayStatus, (int)$adm['id']]);
        }

        $db->commit();

        if (function_exists('invalidate_admission_application_pdf_cache')) {
            invalidate_admission_application_pdf_cache((int)$adm['id'], $db);
        }
        if (function_exists('log_audit')) {
            log_audit(
                'ADMISSION_PAYMENT_' . $targetOutcome,
                "Sandbox Gateway {$targetOutcome} for {$adm['application_ref']} (Session: {$cleanSid}): {$reason}"
            );
        }

        return [
            'success'       => true,
            'already_paid'  => false,
            'outcome'       => $targetOutcome,
            'admission'     => $adm,
            'reason'        => $reason,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Sandbox outcome update error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Could not record payment outcome.'];
    }
}

/**
 * Verify a Sandbox Gateway callback payload ONCE, enforce anti-replay & signature integrity,
 * mark application Paid atomically, save SVG payment receipt, and synchronously generate Application Card PDF.
 */
function rmu_sandbox_verify_and_complete_payment(PDO $db, string $sessionId, string $trxId, string $signature): array {
    ensure_admission_payment_gateway_schema($db);

    $cleanSid = trim($sessionId);
    $cleanTrx = strtoupper(trim($trxId));
    $cleanSig = trim($signature);

    if ($cleanSid === '' || $cleanTrx === '' || $cleanSig === '') {
        if (function_exists('log_audit')) {
            log_audit('ADMISSION_PAYMENT_VERIFY_REJECTED', 'Rejected incomplete gateway callback missing session, transaction ID, or signature.');
        }
        return [
            'success' => false,
            'code'    => 'MISSING_PARAMS',
            'error'   => 'Payment verification failed: incomplete gateway response parameters.',
        ];
    }

    $payDir = STORAGE_PATH . '/uploads/payments';
    if (!is_dir($payDir)) {
        @mkdir($payDir, 0755, true);
    }

    $pendingSvgContent = null;
    $svgFilename = null;
    $admId = 0;

    $db->beginTransaction();
    try {
        $txStmt = $db->prepare("SELECT * FROM admission_payment_transactions WHERE gateway_session_id = ? LIMIT 1 FOR UPDATE");
        $txStmt->execute([$cleanSid]);
        $tx = $txStmt->fetch(PDO::FETCH_ASSOC);

        if (!$tx) {
            $db->rollBack();
            if (function_exists('log_audit')) {
                log_audit('ADMISSION_PAYMENT_VERIFY_REJECTED', "Rejected unknown gateway session {$cleanSid} (Trx: {$cleanTrx}).");
            }
            return [
                'success' => false,
                'code'    => 'UNKNOWN_SESSION',
                'error'   => 'Payment verification failed: Gateway session was not found.',
            ];
        }

        $admStmt = $db->prepare("SELECT * FROM admissions WHERE id = ? LIMIT 1 FOR UPDATE");
        $admStmt->execute([(int)$tx['admission_id']]);
        $adm = $admStmt->fetch(PDO::FETCH_ASSOC);

        if (!$adm) {
            $db->rollBack();
            return [
                'success' => false,
                'code'    => 'ADM_NOT_FOUND',
                'error'   => 'Payment verification failed: Associated application record does not exist.',
            ];
        }

        $admId = (int)$adm['id'];
        $canonicalRef = (string)$adm['application_ref'];

        // STRICT SINGLE-USE TRANSACTION ID VALIDATION:
        // If this Transaction ID / session was already validated once, NEVER re-validate or double-process it.
        if ($tx['validated_once_at'] !== null) {
            if (
                $tx['gateway_status'] === 'VERIFIED'
                && ($adm['payment_status'] ?? '') === 'Paid'
                && !empty($tx['transaction_id'])
                && hash_equals((string)$tx['transaction_id'], $cleanTrx)
                && hash_equals((string)($adm['transaction_id'] ?? ''), $cleanTrx)
            ) {
                $db->commit();
                return [
                    'success'          => true,
                    'already_verified' => true,
                    'revalidated'      => false,
                    'admission'        => $adm,
                    'transaction'      => $tx,
                    'transaction_id'   => $cleanTrx,
                    'message'          => "Payment Transaction ID {$cleanTrx} was already verified once on {$tx['validated_once_at']}. Duplicate verification request ignored; displaying your Official Application Card.",
                ];
            }

            $db->rollBack();
            if (function_exists('log_audit')) {
                log_audit('ADMISSION_PAYMENT_REPLAY_BLOCKED', "Blocked replay/tampered Transaction ID verification for {$canonicalRef} (Session: {$cleanSid}, Trx: {$cleanTrx}).");
            }
            return [
                'success'       => false,
                'code'          => 'TRX_ALREADY_CONSUMED',
                'error'         => 'Payment verification blocked: This Transaction ID has already been validated once and cannot be reused.',
                'admission_ref' => $canonicalRef,
            ];
        }

        // Application already marked Paid through a different verified session
        if (($adm['payment_status'] ?? '') === 'Paid') {
            $db->prepare("
                UPDATE admission_payment_transactions
                SET gateway_status = 'SUPERSEDED', status = 'superseded', failure_reason = 'Application already Paid via another transaction', updated_at = NOW()
                WHERE id = ? AND validated_once_at IS NULL
            ")->execute([(int)$tx['id']]);
            $db->commit();
            return [
                'success'          => true,
                'already_paid'     => true,
                'already_verified' => true,
                'revalidated'      => false,
                'admission'        => $adm,
                'transaction'      => $tx,
                'transaction_id'   => (string)($adm['transaction_id'] ?? ''),
                'message'          => 'Application fee of ৳150 is already paid and verified.',
            ];
        }

        // Validate gateway status, expiry, exact fee amount/currency, and cryptographic signature
        if (($tx['gateway_mode'] ?? '') !== 'sandbox' || $tx['gateway_status'] !== 'AUTHORIZED') {
            $reason = "Session not authorized (status: {$tx['gateway_status']})";
            if ($tx['gateway_status'] === 'INITIATED') {
                $db->prepare("
                    UPDATE admission_payment_transactions
                    SET gateway_status = 'FAILED', status = 'failed', failure_reason = ?, updated_at = NOW()
                    WHERE id = ?
                ")->execute([$reason, (int)$tx['id']]);
                $db->prepare("UPDATE admissions SET payment_status = 'Failed' WHERE id = ? AND payment_status != 'Paid'")->execute([$admId]);
            }
            $db->commit();
            if (function_exists('log_audit')) {
                log_audit('ADMISSION_PAYMENT_VERIFY_REJECTED', "Gateway verification rejected for {$canonicalRef}: {$reason}");
            }
            return [
                'success'       => false,
                'code'          => 'NOT_AUTHORIZED',
                'error'         => 'Payment verification failed: Gateway authorization was not completed for this session.',
                'admission_ref' => $canonicalRef,
            ];
        }

        if (strtotime((string)$tx['expires_at']) < time()) {
            $db->prepare("
                UPDATE admission_payment_transactions
                SET gateway_status = 'EXPIRED', status = 'expired', failure_reason = 'Verification callback exceeded expiry window', updated_at = NOW()
                WHERE id = ?
            ")->execute([(int)$tx['id']]);
            $db->prepare("UPDATE admissions SET payment_status = 'Failed' WHERE id = ? AND payment_status != 'Paid'")->execute([$admId]);
            $db->commit();
            return [
                'success'       => false,
                'code'          => 'SESSION_EXPIRED',
                'error'         => 'Payment verification failed: Checkout session expired before verification completed.',
                'admission_ref' => $canonicalRef,
            ];
        }

        $expectedSig = rmu_sandbox_sign_payload(
            $cleanSid,
            (string)$tx['transaction_id'],
            (string)$tx['application_ref'],
            (float)$tx['amount'],
            (string)$tx['currency'],
            (string)$tx['gateway_nonce']
        );

        $trxMatches = (!empty($tx['transaction_id']) && hash_equals(strtoupper((string)$tx['transaction_id']), $cleanTrx));
        $sigMatches = (
            hash_equals($expectedSig, $cleanSig)
            && !empty($tx['verification_signature'])
            && hash_equals((string)$tx['verification_signature'], $cleanSig)
        );
        $amountMatches = (
            abs((float)$tx['amount'] - RMU_ADMISSION_FEE_AMOUNT) < 0.01
            && abs((float)($tx['paid_amount'] ?? 0) - RMU_ADMISSION_FEE_AMOUNT) < 0.01
            && strtoupper((string)$tx['currency']) === RMU_ADMISSION_FEE_CURRENCY
        );

        if (!$trxMatches || !$sigMatches || !$amountMatches) {
            $reason = !$trxMatches ? 'Transaction ID mismatch' : (!$sigMatches ? 'Cryptographic signature mismatch' : 'Fee amount/currency mismatch');
            $db->prepare("
                UPDATE admission_payment_transactions
                SET gateway_status = 'FAILED', status = 'failed', failure_reason = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$reason, (int)$tx['id']]);
            $db->prepare("UPDATE admissions SET payment_status = 'Failed' WHERE id = ? AND payment_status != 'Paid'")->execute([$admId]);
            $db->commit();
            if (function_exists('log_audit')) {
                log_audit('ADMISSION_PAYMENT_VERIFY_REJECTED', "Gateway verification rejected for {$canonicalRef} (Session: {$cleanSid}): {$reason}");
            }
            return [
                'success'       => false,
                'code'          => 'SIGNATURE_OR_DATA_MISMATCH',
                'error'         => 'Payment verification failed: Invalid gateway signature or transaction details. Please initiate a new payment.',
                'admission_ref' => $canonicalRef,
            ];
        }

        // Cross-application uniqueness check before committing
        $dupAdm = $db->prepare("SELECT id FROM admissions WHERE transaction_id = ? AND id != ? LIMIT 1");
        $dupAdm->execute([$cleanTrx, $admId]);
        $dupTx = $db->prepare("SELECT id FROM admission_payment_transactions WHERE transaction_id = ? AND id != ? LIMIT 1");
        $dupTx->execute([$cleanTrx, (int)$tx['id']]);
        if ($dupAdm->fetchColumn() || $dupTx->fetchColumn()) {
            $db->prepare("
                UPDATE admission_payment_transactions
                SET gateway_status = 'FAILED', status = 'failed', failure_reason = 'Duplicate Transaction ID detected', updated_at = NOW()
                WHERE id = ?
            ")->execute([(int)$tx['id']]);
            $db->prepare("UPDATE admissions SET payment_status = 'Failed' WHERE id = ? AND payment_status != 'Paid'")->execute([$admId]);
            $db->commit();
            return [
                'success'       => false,
                'code'          => 'DUPLICATE_TRX',
                'error'         => 'Duplicate Transaction ID detected. Please retry with a fresh gateway payment.',
                'admission_ref' => $canonicalRef,
            ];
        }

        // Atomic single-use mark as VERIFIED on gateway ledger
        $markTx = $db->prepare("
            UPDATE admission_payment_transactions
            SET gateway_status = 'VERIFIED',
                status = 'verified',
                validated_once_at = NOW(),
                verified_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
              AND gateway_status = 'AUTHORIZED'
              AND validated_once_at IS NULL
        ");
        $markTx->execute([(int)$tx['id']]);
        if ($markTx->rowCount() !== 1) {
            $db->rollBack();
            return [
                'success'       => false,
                'code'          => 'CONCURRENT_CONSUME',
                'error'         => 'Payment transaction was already consumed concurrently.',
                'admission_ref' => $canonicalRef,
            ];
        }

        $method = (string)$tx['gateway_provider'];
        $senderNumber = (string)($tx['sender_number'] ?: $adm['mobile']);
        $cleanRefSlug = preg_replace('/[^a-zA-Z0-9]/', '', $canonicalRef);
        $svgFilename = 'receipt_' . $cleanRefSlug . '_' . time() . '_' . random_int(100, 999) . '.svg';
        $pendingSvgContent = rmu_generate_admission_payment_slip_svg($adm, $method, $senderNumber, $cleanTrx, date('Y-m-d H:i:s'));

        $updAdm = $db->prepare("
            UPDATE admissions
            SET payment_status = 'Paid',
                payment_amount = ?,
                payment_method = ?,
                transaction_id = ?,
                sender_number = ?,
                payment_screenshot = ?,
                payment_gateway_mode = 'sandbox',
                paid_at = NOW(),
                status = CASE WHEN status IN ('Submitted', 'Pending') THEN 'Under Review' ELSE status END
            WHERE id = ? AND payment_status != 'Paid'
        ");
        $updAdm->execute([
            RMU_ADMISSION_FEE_AMOUNT,
            $method,
            $cleanTrx,
            $senderNumber,
            $svgFilename,
            $admId,
        ]);

        if ($updAdm->rowCount() !== 1) {
            $db->rollBack();
            return [
                'success'       => false,
                'code'          => 'RACE_ALREADY_PAID',
                'error'         => 'Application payment status changed concurrently. Please refresh status.',
                'admission_ref' => $canonicalRef,
            ];
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Sandbox verification commit error: ' . $e->getMessage());
        return [
            'success' => false,
            'code'    => 'DB_ERROR',
            'error'   => 'A database error occurred while verifying payment. Your card was not charged twice.',
        ];
    }

    // Write generated SVG slip after DB commit succeeds
    if ($pendingSvgContent !== null && $svgFilename !== null) {
        @file_put_contents($payDir . '/' . $svgFilename, $pendingSvgContent);
    }

    // Synchronously generate Official Paid Application Card PDF so it is immediately ready
    $pdfRes = ['success' => false];
    try {
        if (function_exists('generate_admission_application_pdf_file')) {
            $pdfRes = generate_admission_application_pdf_file($admId, true, $db);
        }
    } catch (Throwable $pdfEx) {
        error_log('Synchronous Application Card PDF generation warning for #' . $admId . ': ' . $pdfEx->getMessage());
    }

    if (function_exists('log_audit')) {
        log_audit(
            'ADMISSION_FEE_PAID',
            "Applicant {$adm['application_ref']} paid ৳150 fee via {$tx['gateway_provider']} Sandbox Gateway (Session: {$cleanSid}, TrxID: {$cleanTrx})"
        );
    }

    $freshAdm = function_exists('fetch_admission_full_record')
        ? (fetch_admission_full_record($admId, '', $db, false) ?: $adm)
        : $adm;

    return [
        'success'          => true,
        'already_verified' => false,
        'revalidated'      => true,
        'admission'        => $freshAdm,
        'transaction'      => $tx,
        'transaction_id'   => $cleanTrx,
        'pdf_result'       => $pdfRes,
        'message'          => "৳150 Application Fee verified successfully (TrxID: {$cleanTrx})! Your Official Application Card & Payment Receipt have been generated.",
    ];
}

/**
 * Read-only Sandbox Gateway status query for API/telemetry endpoints.
 * Never alters `validated_once_at` and never leaks sensitive OTP/nonce/signature secrets.
 */
function rmu_sandbox_query_transaction_status(PDO $db, string $sessionId): array {
    $row = rmu_sandbox_get_session($db, $sessionId);
    if (!$row) {
        return ['found' => false, 'error' => 'Gateway session not found.'];
    }

    $trx = null;
    if (in_array($row['gateway_status'], ['AUTHORIZED', 'VERIFIED'], true) && !empty($row['transaction_id'])) {
        $trx = (string)$row['transaction_id'];
    }

    return [
        'found'              => true,
        'gateway_session_id' => (string)$row['gateway_session_id'],
        'application_ref'    => (string)$row['adm_application_ref'],
        'application_id'     => (string)($row['adm_application_id'] ?: $row['adm_application_ref']),
        'gateway_provider'   => (string)$row['gateway_provider'],
        'gateway_mode'       => (string)$row['gateway_mode'],
        'amount'             => number_format((float)$row['amount'], 2, '.', ''),
        'currency'           => (string)$row['currency'],
        'gateway_status'     => (string)$row['gateway_status'],
        'payment_status'     => (string)$row['adm_payment_status'],
        'transaction_id'     => $trx,
        'validated_once'     => ($row['validated_once_at'] !== null),
        'validated_once_at'  => $row['validated_once_at'],
        'authorized_at'      => $row['authorized_at'],
        'verified_at'        => $row['verified_at'],
        'expires_at'         => $row['expires_at'],
        'failure_reason'     => $row['failure_reason'],
    ];
}

/**
 * Shared atomic administrator payment verification helper (used by admin/payments.php & admin/admissions.php).
 * Records transaction ledger, updates payment_status = 'Paid', saves SVG slip, and generates Paid Application Card PDF.
 */
function rmu_admin_verify_admission_payment(PDO $db, int $admissionId, string $suppliedTrx = '', int $adminUserId = 0): array {
    ensure_admission_payment_gateway_schema($db);

    if ($admissionId <= 0) {
        return ['success' => false, 'error' => 'Invalid admission application ID.'];
    }

    $payDir = STORAGE_PATH . '/uploads/payments';
    if (!is_dir($payDir)) {
        @mkdir($payDir, 0755, true);
    }

    $svgFilename = null;
    $pendingSvgContent = null;
    $finalTrx = '';
    $appRef = '';

    $db->beginTransaction();
    try {
        $admStmt = $db->prepare("SELECT * FROM admissions WHERE id = ? LIMIT 1 FOR UPDATE");
        $admStmt->execute([$admissionId]);
        $adm = $admStmt->fetch(PDO::FETCH_ASSOC);

        if (!$adm) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Admission application record not found.'];
        }

        $appRef = (string)$adm['application_ref'];
        if (($adm['payment_status'] ?? '') === 'Paid') {
            $db->commit();
            return [
                'success'        => true,
                'already_paid'   => true,
                'transaction_id' => (string)($adm['transaction_id'] ?? ''),
                'message'        => "Application {$appRef} is already marked as Paid.",
            ];
        }

        $cleanSupplied = strtoupper(trim($suppliedTrx));
        $existingTrx   = strtoupper(trim((string)($adm['transaction_id'] ?? '')));

        if ($cleanSupplied !== '') {
            $finalTrx = $cleanSupplied;
        } elseif ($existingTrx !== '') {
            $finalTrx = $existingTrx;
        } else {
            $finalTrx = 'ADM-VER-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        }

        // Enforce Transaction ID uniqueness across all admissions & gateway ledger rows
        $chkAdm = $db->prepare("SELECT id FROM admissions WHERE transaction_id = ? AND id != ? LIMIT 1");
        $chkAdm->execute([$finalTrx, $admissionId]);
        $chkTx = $db->prepare("SELECT id FROM admission_payment_transactions WHERE transaction_id = ? AND admission_id != ? LIMIT 1");
        $chkTx->execute([$finalTrx, $admissionId]);
        if ($chkAdm->fetchColumn() || $chkTx->fetchColumn()) {
            $db->rollBack();
            return ['success' => false, 'error' => "Transaction ID '{$finalTrx}' is already linked to another application."];
        }

        // Supersede open unverified sessions and insert verified admin audit ledger entry
        $db->prepare("
            UPDATE admission_payment_transactions
            SET gateway_status = 'SUPERSEDED', status = 'superseded', failure_reason = 'Manually verified by Admin', updated_at = NOW()
            WHERE admission_id = ? AND gateway_status IN ('INITIATED', 'AUTHORIZED') AND validated_once_at IS NULL
        ")->execute([$admissionId]);

        $sessId = 'RMUADM-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $nonce  = bin2hex(random_bytes(16));
        $method = rmu_sandbox_normalize_method($adm['payment_method'] ?? 'bKash');
        $sender = (string)($adm['sender_number'] ?: $adm['mobile']);
        $appIdSig = (string)(!empty($adm['application_id']) ? $adm['application_id'] : $appRef);
        $sig = rmu_sandbox_sign_payload($sessId, $finalTrx, $appIdSig, RMU_ADMISSION_FEE_AMOUNT, RMU_ADMISSION_FEE_CURRENCY, $nonce);

        $existingOwnTx = $db->prepare("SELECT id FROM admission_payment_transactions WHERE transaction_id = ? AND admission_id = ? LIMIT 1");
        $existingOwnTx->execute([$finalTrx, $admissionId]);
        $ownTxId = (int)$existingOwnTx->fetchColumn();
        if ($ownTxId > 0) {
            $db->prepare("
                UPDATE admission_payment_transactions
                SET gateway_status = 'VERIFIED',
                    status = 'verified',
                    paid_amount = ?,
                    verification_signature = ?,
                    validated_once_at = COALESCE(validated_once_at, NOW()),
                    verified_at = COALESCE(verified_at, NOW()),
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([RMU_ADMISSION_FEE_AMOUNT, $sig, $ownTxId]);
        } else {
            $db->prepare("
                INSERT INTO admission_payment_transactions (
                    admission_id, application_ref, gateway_session_id, idempotency_key,
                    transaction_id, gateway_provider, gateway_mode, amount, paid_amount, currency,
                    sender_number, sandbox_otp, attempt_count, gateway_status, status,
                    verification_signature, gateway_nonce, authorized_at, validated_once_at, verified_at, expires_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, 'sandbox', ?, ?, 'BDT',
                    ?, '000000', 0, 'VERIFIED', 'verified',
                    ?, ?, NOW(), NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                )
            ")->execute([
                $admissionId,
                $appIdSig,
                $sessId,
                'adm_' . $adminUserId . '_' . bin2hex(random_bytes(8)),
                $finalTrx,
                $method,
                RMU_ADMISSION_FEE_AMOUNT,
                RMU_ADMISSION_FEE_AMOUNT,
                $sender,
                $sig,
                $nonce,
            ]);
        }

        if (empty($adm['payment_screenshot'])) {
            $cleanSlug = preg_replace('/[^a-zA-Z0-9]/', '', $appRef);
            $svgFilename = 'receipt_' . $cleanSlug . '_' . time() . '_' . random_int(100, 999) . '.svg';
            $pendingSvgContent = rmu_generate_admission_payment_slip_svg($adm, $method, $sender, $finalTrx, date('Y-m-d H:i:s'));
        }

        $db->prepare("
            UPDATE admissions
            SET payment_status = 'Paid',
                payment_amount = ?,
                payment_method = COALESCE(NULLIF(payment_method, ''), ?),
                transaction_id = ?,
                sender_number = COALESCE(NULLIF(sender_number, ''), ?),
                payment_screenshot = COALESCE(?, payment_screenshot),
                payment_gateway_mode = COALESCE(payment_gateway_mode, 'sandbox'),
                paid_at = NOW(),
                status = CASE WHEN status IN ('Submitted', 'Pending') THEN 'Under Review' ELSE status END
            WHERE id = ? AND payment_status != 'Paid'
        ")->execute([RMU_ADMISSION_FEE_AMOUNT, $method, $finalTrx, $sender, $svgFilename, $admissionId]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Admin verify payment error for #' . $admissionId . ': ' . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to verify payment in database.'];
    }

    if ($pendingSvgContent !== null && $svgFilename !== null) {
        @file_put_contents($payDir . '/' . $svgFilename, $pendingSvgContent);
    }

    try {
        if (function_exists('generate_admission_application_pdf_file')) {
            generate_admission_application_pdf_file($admissionId, true, $db);
        }
    } catch (Throwable $pdfEx) {
        error_log('Admin verify PDF generation warning for #' . $admissionId . ': ' . $pdfEx->getMessage());
    }

    if (function_exists('log_audit')) {
        log_audit('ADMISSION_PAYMENT_VERIFIED', "Admin manually verified ৳150 payment for application #{$admissionId} ({$appRef}, TrxID: {$finalTrx})");
    }

    return [
        'success'        => true,
        'already_paid'   => false,
        'transaction_id' => $finalTrx,
        'message'        => "Payment for {$appRef} verified as Paid (TrxID: {$finalTrx}) and Application Card PDF generated.",
    ];
}
