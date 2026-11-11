<?php
/**
 * RMU Sandbox Payment Gateway Checkout Page
 *
 * Interactive MFS / Card Sandbox Gateway simulating external gateway authorization:
 *  - Displays Sandbox Session details, Merchant, Amount (৳150.00 BDT), and Test OTP
 *  - Zero Transaction ID prompt to applicant (gateway generates & HMAC-signs TrxID internally)
 *  - Supports Authorize (Success), Invalid OTP retry/lockout, Simulate Gateway Failure, & Cancel
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDb();
ensure_admission_payment_gateway_schema($db);

$sessionId = trim((string)($_REQUEST['session'] ?? ''));
if ($sessionId === '') {
    flash_set('error', 'Missing Sandbox Gateway session identifier.');
    header('Location: ' . APP_URL . '/admission-status.php');
    exit;
}

$tx = rmu_sandbox_get_session($db, $sessionId);
if (!$tx) {
    flash_set('error', 'Invalid or expired Sandbox Gateway session.');
    header('Location: ' . APP_URL . '/admission-status.php');
    exit;
}

$canonicalRef = (string)$tx['adm_application_ref'];
$appIdDisplay = !empty($tx['adm_application_id']) ? (string)$tx['adm_application_id'] : $canonicalRef;
authorize_applicant_session_ref($canonicalRef, $tx['adm_application_id'] ?? null, $tx['adm_roll_number'] ?? null);

// If application is already Paid / verified, return directly to Application Card (never re-prompt)
if (($tx['adm_payment_status'] ?? '') === 'Paid' || ($tx['gateway_status'] ?? '') === 'VERIFIED') {
    flash_set('info', 'This ৳150 admission fee is already paid and verified (TrxID: ' . ($tx['adm_transaction_id'] ?: ($tx['transaction_id'] ?: 'Verified')) . ').');
    header('Location: ' . APP_URL . '/admission-status.php?ref=' . urlencode($canonicalRef));
    exit;
}

// If this session was already AUTHORIZED (e.g., interrupted callback), forward directly to single-use callback verification
if (($tx['gateway_status'] ?? '') === 'AUTHORIZED' && !empty($tx['transaction_id']) && !empty($tx['verification_signature'])) {
    $cbUrl = APP_URL . '/actions/process-admission-payment.php?action=gateway_callback'
        . '&session=' . urlencode($sessionId)
        . '&trx=' . urlencode((string)$tx['transaction_id'])
        . '&sig=' . urlencode((string)$tx['verification_signature']);
    header('Location: ' . $cbUrl);
    exit;
}

$inlineError = '';

// Handle Gateway Checkout Actions (POST)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Gateway security token expired. Please initiate checkout again.');
        header('Location: ' . APP_URL . '/admission-payment.php?ref=' . urlencode($canonicalRef));
        exit;
    }

    $gwAction = trim((string)($_POST['gw_action'] ?? 'authorize'));

    if ($gwAction === 'authorize') {
        $submittedOtp = trim((string)($_POST['sandbox_otp'] ?? ''));
        $authRes = rmu_sandbox_authorize_session($db, $sessionId, $submittedOtp);

        if (!empty($authRes['already_paid'])) {
            flash_set('info', 'Application fee of ৳150 has already been paid and verified.');
            header('Location: ' . APP_URL . '/admission-status.php?ref=' . urlencode($canonicalRef));
            exit;
        }

        if (!empty($authRes['success'])) {
            $cbUrl = APP_URL . '/actions/process-admission-payment.php?action=gateway_callback'
                . '&session=' . urlencode((string)$authRes['session_id'])
                . '&trx=' . urlencode((string)$authRes['transaction_id'])
                . '&sig=' . urlencode((string)$authRes['signature']);
            header('Location: ' . $cbUrl);
            exit;
        }

        if (!empty($authRes['fatal'])) {
            flash_set('error', (string)($authRes['error'] ?? 'Sandbox payment authorization failed.'));
            header('Location: ' . APP_URL . '/admission-payment.php?ref=' . urlencode($canonicalRef));
            exit;
        }

        // Non-fatal OTP mismatch -> re-fetch updated attempt_count and show error cleanly
        $inlineError = (string)($authRes['error'] ?? 'Invalid Sandbox OTP entered.');
        $tx = rmu_sandbox_get_session($db, $sessionId) ?: $tx;
    } elseif ($gwAction === 'simulate_fail') {
        $failReason = trim((string)($_POST['fail_reason'] ?? 'Simulated gateway failure (Insufficient balance / issuer declined)'));
        $markRes = rmu_sandbox_mark_session_outcome($db, $sessionId, 'FAILED', $failReason);
        if (!empty($markRes['already_paid'])) {
            flash_set('info', 'This application is already Paid.');
            header('Location: ' . APP_URL . '/admission-status.php?ref=' . urlencode($canonicalRef));
            exit;
        }
        flash_set('error', 'Payment verification failed at Sandbox Gateway: ' . $failReason . '. No Application Card was generated. Please retry.');
        header('Location: ' . APP_URL . '/admission-payment.php?ref=' . urlencode($canonicalRef));
        exit;
    } elseif ($gwAction === 'cancel') {
        $markRes = rmu_sandbox_mark_session_outcome($db, $sessionId, 'CANCELLED', 'Cancelled by applicant at Sandbox Gateway');
        if (!empty($markRes['already_paid'])) {
            flash_set('info', 'This application is already Paid.');
            header('Location: ' . APP_URL . '/admission-status.php?ref=' . urlencode($canonicalRef));
            exit;
        }
        flash_set('warning', 'Payment checkout was cancelled before completion. Your Application Card remains locked until ৳150 is paid.');
        header('Location: ' . APP_URL . '/admission-payment.php?ref=' . urlencode($canonicalRef));
        exit;
    }
}

// Reject inactive/terminal sessions gracefully by sending back to payment retry screen
if (($tx['gateway_status'] ?? '') !== 'INITIATED') {
    $statusDesc = (string)($tx['failure_reason'] ?: ('Session status: ' . $tx['gateway_status']));
    flash_set('error', 'Sandbox payment session is no longer active (' . $statusDesc . '). Please start a new payment.');
    header('Location: ' . APP_URL . '/admission-payment.php?ref=' . urlencode($canonicalRef));
    exit;
}

$provider = (string)$tx['gateway_provider'];
$brandTheme = match ($provider) {
    'bKash' => ['primary' => '#e2136e', 'dark' => '#9d0b4b', 'soft' => '#fff1f5', 'border' => '#fbcfe8'],
    'Nagad' => ['primary' => '#ea580c', 'dark' => '#9a3412', 'soft' => '#fff7ed', 'border' => '#fed7aa'],
    'Rocket' => ['primary' => '#7c3aed', 'dark' => '#5b21b6', 'soft' => '#faf5ff', 'border' => '#ddd6fe'],
    'Upay' => ['primary' => '#2563eb', 'dark' => '#1e40af', 'soft' => '#eff6ff', 'border' => '#bfdbfe'],
    default => ['primary' => '#0f2c59', 'dark' => '#0a1f3f', 'soft' => '#f8fafc', 'border' => '#cbd5e1'],
};

$expiresTs = strtotime((string)$tx['expires_at']) ?: (time() + 900);
$secondsRemaining = max(0, $expiresTs - time());
$attemptsLeft = max(0, 3 - (int)($tx['attempt_count'] ?? 0));

$pageTitle = 'Sandbox Payment Gateway - ' . $provider;
include __DIR__ . '/includes/header.php';
?>

<style>
.rmu-sbx-shell {
  max-width: 560px;
  margin: 0 auto;
}
.rmu-sbx-card {
  background: #ffffff;
  border: 2px solid <?= e($brandTheme['primary']) ?>;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 18px 40px -12px rgba(15, 23, 42, 0.16);
}
.rmu-sbx-head {
  background: linear-gradient(135deg, <?= e($brandTheme['primary']) ?> 0%, <?= e($brandTheme['dark']) ?> 100%);
  color: #ffffff;
  padding: 1.4rem 1.6rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.rmu-sbx-pill {
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.45);
  color: #ffffff;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.6px;
  text-transform: uppercase;
}
.rmu-sbx-body {
  padding: 1.6rem;
}
.rmu-sbx-summary {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1rem 1.2rem;
  margin-bottom: 1.25rem;
}
.rmu-sbx-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  padding: 0.36rem 0;
  font-size: 0.88rem;
  border-bottom: 1px dashed #e2e8f0;
}
.rmu-sbx-row:last-child {
  border-bottom: none;
}
.rmu-sbx-otp-guide {
  background: <?= e($brandTheme['soft']) ?>;
  border: 1.5px solid <?= e($brandTheme['border']) ?>;
  border-radius: 10px;
  padding: 0.9rem 1.1rem;
  margin-bottom: 1.15rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.65rem;
}
@media (max-width: 520px) {
  .rmu-sbx-body {
    padding: 1.15rem;
  }
  .rmu-sbx-head {
    padding: 1.1rem;
  }
}
</style>

<section class="section section-bg-light" style="padding: 2.5rem 0 3.5rem;">
  <div class="container">
    <div class="rmu-sbx-shell">
      <div class="rmu-sbx-card">
        <div class="rmu-sbx-head">
          <div>
            <span class="rmu-sbx-pill"><i class="fas fa-flask"></i> Sandbox Test Environment</span>
            <h2 style="margin: 0.45rem 0 0; font-size: 1.32rem; font-weight: 800; color: #ffffff;">
              <?= e($provider) ?> Payment Gateway
            </h2>
            <div style="font-size: 0.8rem; opacity: 0.9; margin-top: 2px;">
              Merchant: <strong>Rabindra Maitree University</strong> (<?= RMU_SANDBOX_OFFICIAL_MERCHANT ?>)
            </div>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 0.72rem; text-transform: uppercase; opacity: 0.85; font-weight: 700;">Total Payable</div>
            <div style="font-size: 1.65rem; font-weight: 900; line-height: 1.1; color: #ffffff;">৳150.00</div>
            <div style="font-size: 0.74rem; opacity: 0.9;" id="sbxExpiryTimer">Expires in <?= floor($secondsRemaining / 60) ?>m <?= $secondsRemaining % 60 ?>s</div>
          </div>
        </div>

        <div class="rmu-sbx-body">
          <?php if ($inlineError !== ''): ?>
            <div class="alert alert-error" role="alert" id="sbxInlineError" style="margin-bottom: 1rem;">
              <i class="fas fa-exclamation-triangle"></i>
              <div><?= e($inlineError) ?></div>
            </div>
          <?php endif; ?>

          <div class="rmu-sbx-summary">
            <div class="rmu-sbx-row">
              <span style="color:#64748b;">Application ID</span>
              <strong style="font-family:monospace; color:#0f2c59;"><?= e($appIdDisplay) ?></strong>
            </div>
            <div class="rmu-sbx-row">
              <span style="color:#64748b;">Candidate Name</span>
              <strong style="color:#0f172a;"><?= e($tx['adm_full_name']) ?></strong>
            </div>
            <div class="rmu-sbx-row">
              <span style="color:#64748b;">Payer Wallet / Mobile</span>
              <strong style="font-family:monospace; color:#0f172a;"><?= e($tx['sender_number']) ?></strong>
            </div>
            <div class="rmu-sbx-row">
              <span style="color:#64748b;">Gateway Session ID</span>
              <code style="font-size:0.78rem; color:#475569;"><?= e($sessionId) ?></code>
            </div>
            <div class="rmu-sbx-row">
              <span style="color:#64748b;">Currency &amp; Fee</span>
              <strong style="color:#15803d;">৳150.00 BDT</strong>
            </div>
          </div>

          <div class="rmu-sbx-otp-guide">
            <div>
              <div style="font-size:0.74rem; text-transform:uppercase; font-weight:800; color:<?= e($brandTheme['dark']) ?>;">
                <i class="fas fa-key"></i> Sandbox Test Verification Code (OTP)
              </div>
              <div style="font-size:0.8rem; color:#475569; margin-top:2px;">
                Gateway verifies OTP once &amp; generates signed TrxID automatically.
              </div>
            </div>
            <div id="sandboxTestOtpBadge" style="background:#ffffff; border:2px dashed <?= e($brandTheme['primary']) ?>; border-radius:8px; padding:6px 14px; font-family:monospace; font-size:1.3rem; font-weight:900; letter-spacing:3px; color:<?= e($brandTheme['dark']) ?>;">
              <?= e($tx['sandbox_otp']) ?>
            </div>
          </div>

          <form action="<?= APP_URL ?>/sandbox-gateway.php?session=<?= urlencode($sessionId) ?>" method="POST" id="sbxAuthorizeForm">
            <?= csrf_field() ?>
            <input type="hidden" name="session" value="<?= e($sessionId) ?>">
            <input type="hidden" name="gw_action" value="authorize">

            <div class="form-group" style="margin-bottom: 1.15rem;">
              <label class="form-label" for="sandboxOtpInput" style="font-weight:700; color:#1e293b;">
                Enter 6-Digit Sandbox OTP Code <span class="required">*</span>
              </label>
              <input
                type="text"
                name="sandbox_otp"
                id="sandboxOtpInput"
                value="<?= e($tx['sandbox_otp']) ?>"
                class="form-control"
                maxlength="6"
                inputmode="numeric"
                pattern="\d{6}"
                required
                style="font-family:monospace; font-size:1.25rem; font-weight:800; letter-spacing:4px; text-align:center;"
              >
              <small style="color:#64748b; font-size:0.78rem; display:block; margin-top:4px;">
                Remaining attempts: <strong><?= $attemptsLeft ?></strong> of 3 &bull; No manual Transaction ID entry required.
              </small>
            </div>

            <button type="submit" id="confirmSandboxPayBtn" class="btn-primary" style="background:<?= e($brandTheme['primary']) ?>; width:100%; justify-content:center; padding:0.95rem; font-size:1rem; font-weight:800; border-radius:8px;">
              <i class="fas fa-shield-alt"></i> Confirm &amp; Complete ৳150 Payment
            </button>
          </form>

          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 165px), 1fr)); gap:0.65rem; margin-top:0.85rem;">
            <form action="<?= APP_URL ?>/sandbox-gateway.php?session=<?= urlencode($sessionId) ?>" method="POST" style="margin:0;">
              <?= csrf_field() ?>
              <input type="hidden" name="session" value="<?= e($sessionId) ?>">
              <input type="hidden" name="gw_action" value="simulate_fail">
              <input type="hidden" name="fail_reason" value="Simulated gateway failure (Insufficient balance)">
              <button type="submit" id="simulateFailBtn" style="width:100%; background:#fef2f2; color:#b91c1c; border:1px solid #fca5a5; border-radius:7px; padding:0.6rem 0.75rem; font-size:0.8rem; font-weight:700; cursor:pointer;">
                <i class="fas fa-times-circle"></i> Simulate Failure
              </button>
            </form>

            <form action="<?= APP_URL ?>/sandbox-gateway.php?session=<?= urlencode($sessionId) ?>" method="POST" style="margin:0;">
              <?= csrf_field() ?>
              <input type="hidden" name="session" value="<?= e($sessionId) ?>">
              <input type="hidden" name="gw_action" value="cancel">
              <button type="submit" id="cancelGatewayBtn" style="width:100%; background:#f8fafc; color:#475569; border:1px solid #cbd5e1; border-radius:7px; padding:0.6rem 0.75rem; font-size:0.8rem; font-weight:700; cursor:pointer;">
                <i class="fas fa-arrow-left"></i> Cancel Payment
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
(function() {
  var remaining = <?= (int)$secondsRemaining ?>;
  var timerEl = document.getElementById('sbxExpiryTimer');
  if (timerEl && remaining > 0) {
    setInterval(function() {
      remaining = Math.max(0, remaining - 1);
      var m = Math.floor(remaining / 60);
      var s = remaining % 60;
      timerEl.textContent = 'Expires in ' + m + 'm ' + (s < 10 ? '0' : '') + s + 's';
    }, 1000);
  }

  var authForm = document.getElementById('sbxAuthorizeForm');
  var btn = document.getElementById('confirmSandboxPayBtn');
  function resetAuthBtn() {
    if (authForm) authForm.dataset.submitting = '0';
    if (btn) {
      btn.disabled = false;
      btn.style.opacity = '1';
    }
  }
  window.addEventListener('pageshow', resetAuthBtn);
  if (authForm) {
    authForm.addEventListener('submit', function(e) {
      if (authForm.dataset.submitting === '1') {
        e.preventDefault();
        return;
      }
      authForm.dataset.submitting = '1';
      if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.75';
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authorizing &amp; Verifying Payment...';
      }
    });
  }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
