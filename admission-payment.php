<?php
/**
 * Online Admission Fee Payment Checkout (৳150 BDT)
 * Integrates with the RMU Sandbox Payment Gateway (single-use TrxID verification).
 * No manual Transaction ID prompt or client-side bypass is permitted.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$ref = trim((string)($_GET['ref'] ?? ''));
$db = getDb();
ensure_admission_payment_gateway_schema($db);

$stmt = $db->prepare("SELECT * FROM admissions WHERE application_ref = ? OR application_id = ? OR roll_number = ? LIMIT 1");
$stmt->execute([$ref, $ref, $ref]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    flash_set('error', 'Invalid application reference. Please check your tracking number.');
    header("Location: " . APP_URL . "/admission-status.php");
    exit;
}

$canonicalRef = (string)$application['application_ref'];
authorize_applicant_session_ref(
    $canonicalRef,
    $application['application_id'] ?? null,
    $application['roll_number'] ?? null
);

// If already paid, redirect straight to Application Card on status page
if (($application['payment_status'] ?? '') === 'Paid') {
    flash_set('info', 'Application fee of ৳150 is already paid and verified. Your Official Application Card is ready below.');
    header("Location: " . APP_URL . "/admission-status.php?ref=" . urlencode($canonicalRef));
    exit;
}

// Check latest sandbox gateway transaction for retry context / failure messaging
$latestTxStmt = $db->prepare("
    SELECT gateway_session_id, gateway_provider, gateway_status, status, failure_reason, expires_at, created_at
    FROM admission_payment_transactions
    WHERE admission_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$latestTxStmt->execute([(int)$application['id']]);
$latestTx = $latestTxStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$idempotencyKey = 'idem_' . bin2hex(random_bytes(16));
$defaultMethod = rmu_sandbox_normalize_method($application['payment_method'] ?? 'bKash');

$pageTitle = 'Admission Fee Payment (৳150)';
$breadcrumbTitle = 'Online Application Fee Checkout';
$breadcrumbs = [
    ['title' => 'Admission', 'url' => APP_URL . '/admissions.php'],
    ['title' => 'Application Form', 'url' => APP_URL . '/admission-form.php'],
    ['title' => 'Fee Payment']
];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<style>
.rmu-pay-channel-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
  gap: 10px;
  margin-bottom: 1.5rem;
}
.rmu-pay-channel-card {
  border: 1.5px solid #cbd5e1;
  background: #f8fafc;
  border-radius: 10px;
  padding: 12px 10px;
  text-align: center;
  cursor: pointer;
  display: block;
  transition: all 0.18s ease;
}
.rmu-pay-channel-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(15, 44, 89, 0.08);
}
.rmu-pay-channel-card.is-selected {
  border-width: 2px;
  box-shadow: 0 6px 14px rgba(15, 44, 89, 0.12);
}
</style>

<section class="section section-bg-white">
  <div class="container" style="max-width: 860px;">

    <div class="section-header" style="margin-bottom: 2rem;">
      <span class="section-subtitle">Sandbox Gateway Checkout</span>
      <h2 class="section-title">Admission Application Fee Payment</h2>
      <div class="section-line"></div>
      <p style="color: var(--rmu-muted); margin-top: 0.8rem; font-size: 0.95rem;">
        Complete your verified <strong>৳150 Application Fee</strong> through our <strong>Sandbox Payment Gateway</strong> to unlock and automatically generate your <strong>Official Application Card &amp; PDF</strong>.
      </p>
    </div>

    <?php if ($latestTx && in_array($latestTx['gateway_status'], ['FAILED', 'EXPIRED', 'CANCELLED'], true)): ?>
      <div id="gatewayOutcomeNotice" style="background:#fef2f2; border:1.5px solid #fecaca; border-radius:10px; padding:1rem 1.25rem; margin-bottom:1.5rem; display:flex; gap:0.85rem; align-items:flex-start;">
        <i class="fas fa-exclamation-circle" style="color:#dc2626; font-size:1.25rem; margin-top:2px;"></i>
        <div style="font-size:0.9rem; color:#7f1d1d;">
          <strong>Previous Payment Attempt (<?= e($latestTx['gateway_provider']) ?>): <?= e($latestTx['gateway_status']) ?></strong>
          <?php if (!empty($latestTx['failure_reason'])): ?>
            <div style="margin-top:2px;"><?= e($latestTx['failure_reason']) ?></div>
          <?php endif; ?>
          <div style="font-size:0.82rem; color:#991b1b; margin-top:4px;">
            Your Paid Application Card remains locked until payment verification succeeds. You can retry safely below.
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Application Summary Invoice Card -->
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.06); padding:1.5rem 2rem; margin-bottom:2rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #f1f5f9; padding-bottom:1rem; margin-bottom:1.2rem; flex-wrap:wrap; gap:1rem;">
        <div style="display:flex; gap:1.5rem; flex-wrap:wrap; align-items:center;">
          <div>
            <span style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; font-weight:700;">Application ID</span>
            <div style="font-size:1.35rem; font-weight:800; color:var(--rmu-primary); font-family:monospace;"><?= e(!empty($application['application_id']) ? $application['application_id'] : $canonicalRef) ?></div>
          </div>
          <div style="border-left:2px solid #e2e8f0; padding-left:1.25rem;">
            <span style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; font-weight:700;">Admission Roll Number</span>
            <div style="font-size:1.35rem; font-weight:800; color:#6d28d9; font-family:monospace;"><?= e(!empty($application['roll_number']) ? $application['roll_number'] : ($application['exam_roll'] ?? 'Pending')) ?></div>
          </div>
        </div>
        <div style="text-align:right;">
          <?php
            $curPaySt = (string)($application['payment_status'] ?? 'Pending');
            $badgeLabel = ($curPaySt === 'Failed') ? 'PAYMENT FAILED' : (($curPaySt === 'Under Verification') ? 'CHECKOUT IN PROGRESS' : 'PAYMENT PENDING');
          ?>
          <span style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:4px 12px; border-radius:20px; font-weight:700; font-size:0.82rem;">
            <i class="fas fa-clock"></i> <?= e($badgeLabel) ?>
          </span>
          <div style="font-size:0.78rem; color:#64748b; margin-top:3px;">Date: <?= format_date($application['created_at']) ?></div>
        </div>
      </div>

      <?php
        $payDegType = $application['degree_type'] ?? 'Bachelor / Honours';
        $payDurYrs = !empty($application['duration_years']) ? (float)$application['duration_years'] : ($payDegType === 'B.Ed' ? 1.0 : ($payDegType === "Master's" ? 2.0 : 4.0));
        $payTotalSems = !empty($application['total_semesters']) ? (int)$application['total_semesters'] : ($payDegType === 'B.Ed' ? 2 : ($payDegType === "Master's" ? 4 : 8));
        $payCourseDur = !empty($application['course_duration']) ? $application['course_duration'] : format_duration_years_label($payDurYrs, $payTotalSems);
      ?>
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; font-size:0.9rem; margin-bottom:1.5rem;">
        <div>
          <span style="color:#64748b; display:block; font-size:0.8rem;">Candidate Legal Name</span>
          <strong style="color:#0f172a; font-size:1rem;"><?= e($application['full_name']) ?></strong>
        </div>
        <div>
          <span style="color:#64748b; display:block; font-size:0.8rem;">Contact Mobile Number</span>
          <strong style="color:#0f172a; font-size:1rem;"><?= e($application['mobile']) ?></strong>
        </div>
        <div>
          <span style="color:#64748b; display:block; font-size:0.8rem;">Degree &amp; Selected Program</span>
          <strong style="color:var(--rmu-accent); font-size:0.95rem;"><?= e($application['interested_subject']) ?></strong>
          <small style="display:block; color:#475569;"><?= e($payDegType) ?></small>
        </div>
        <div>
          <span style="color:#64748b; display:block; font-size:0.8rem;">Academic Session &amp; Intake Year</span>
          <strong style="color:#0f172a;"><?= e(!empty($application['academic_session']) ? $application['academic_session'] : 'N/A') ?></strong>
          <small style="display:block; color:#64748b;">App Year: <?= (int)($application['application_year'] ?? max(2015, (int)date('Y'))) ?></small>
        </div>
        <div>
          <span style="color:#64748b; display:block; font-size:0.8rem;">Course Duration &amp; Semesters</span>
          <strong style="color:#0f172a;"><?= e($payCourseDur) ?></strong>
          <small style="display:block; color:#64748b;"><?= $payTotalSems ?> Semesters</small>
        </div>
        <div>
          <span style="color:#64748b; display:block; font-size:0.8rem;">Actual / Expected Passing Year</span>
          <strong style="color:#0f172a;"><?= e(!empty($application['passing_year']) ? $application['passing_year'] : 'N/A') ?></strong>
          <?php if (!empty($application['expected_passing_year'])): ?>
            <small style="display:block; color:#1e40af;">Expected (Advisory): <?= (int)$application['expected_passing_year'] ?></small>
          <?php endif; ?>
        </div>
      </div>

      <!-- Amount Banner -->
      <div style="background:#f8fafc; border:1.5px dashed #cbd5e1; border-radius:8px; padding:1rem 1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
        <div>
          <div style="font-weight:700; color:#1e293b; font-size:0.95rem;">Admission Application Fee &amp; Official Card Generation</div>
          <small style="color:#64748b;">Verified via RMU Sandbox Payment Gateway &bull; Merchant: <?= RMU_SANDBOX_OFFICIAL_MERCHANT ?></small>
        </div>
        <div style="text-align:right;">
          <div style="font-size:1.6rem; font-weight:900; color:#15803d;">৳ 150.00 <span style="font-size:0.85rem; font-weight:600; color:#64748b;">BDT</span></div>
        </div>
      </div>
    </div>

    <!-- Sandbox Gateway Checkout Form -->
    <div style="background:#ffffff; border:1.5px solid #bfdbfe; border-radius:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.06); padding:2rem; margin-bottom:2rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:0.5rem;">
        <h3 style="font-size:1.18rem; font-weight:800; color:#0f172a; margin:0;">
          <i class="fas fa-shield-alt" style="color:#16a34a;"></i> Select Payment Channel &amp; Launch Sandbox Gateway
        </h3>
        <span style="background:#dcfce7; color:#166534; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:800;">
          1-Step Verified Flow &bull; No TrxID Prompt
        </span>
      </div>
      <p style="color:#64748b; font-size:0.88rem; margin-bottom:1.5rem;">
        Choose your payment wallet or card channel below, confirm your mobile number, and click <strong>Pay ৳150 &amp; Generate Application Card</strong> to authorize payment directly in the Sandbox Gateway.
      </p>

      <form action="<?= APP_URL ?>/actions/process-admission-payment.php" method="POST" id="admissionPayForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="initiate_sandbox_payment">
        <input type="hidden" name="ref" value="<?= e($canonicalRef) ?>">
        <input type="hidden" name="idempotency_key" value="<?= e($idempotencyKey) ?>">

        <?php
          $channels = [
              ['value' => 'bKash', 'label' => 'bKash', 'sub' => 'MFS Wallet', 'accent' => '#e11d48', 'bg' => '#fff1f2', 'title' => '#be123c'],
              ['value' => 'Nagad', 'label' => 'Nagad', 'sub' => 'Postal MFS', 'accent' => '#ea580c', 'bg' => '#fff7ed', 'title' => '#c2410c'],
              ['value' => 'Rocket', 'label' => 'Rocket', 'sub' => 'DBBL MFS', 'accent' => '#7c3aed', 'bg' => '#faf5ff', 'title' => '#6d28d9'],
              ['value' => 'Upay', 'label' => 'Upay', 'sub' => 'UCB Wallet', 'accent' => '#2563eb', 'bg' => '#eff6ff', 'title' => '#1d4ed8'],
              ['value' => 'Online Banking / Card', 'label' => 'Card / Bank', 'sub' => 'Visa / Master', 'accent' => '#0f2c59', 'bg' => '#f8fafc', 'title' => '#0f2c59'],
          ];
        ?>
        <div class="rmu-pay-channel-grid" id="payChannelGrid">
          <?php foreach ($channels as $ch): ?>
            <?php $isSel = ($defaultMethod === $ch['value']); ?>
            <label
              class="rmu-pay-channel-card <?= $isSel ? 'is-selected' : '' ?>"
              data-accent="<?= e($ch['accent']) ?>"
              data-bg="<?= e($ch['bg']) ?>"
              style="border-color:<?= $isSel ? e($ch['accent']) : '#cbd5e1' ?>; background:<?= $isSel ? e($ch['bg']) : '#f8fafc' ?>;"
            >
              <input
                type="radio"
                name="payment_method"
                value="<?= e($ch['value']) ?>"
                <?= $isSel ? 'checked' : '' ?>
                style="accent-color:<?= e($ch['accent']) ?>; margin-right:4px;"
              >
              <strong style="color:<?= e($ch['title']) ?>; font-size:0.95rem; display:block; margin-top:4px;"><?= e($ch['label']) ?></strong>
              <small style="font-size:0.75rem; color:#475569;"><?= e($ch['sub']) ?></small>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="form-group" style="margin-bottom:1.5rem;">
          <label class="form-label" for="senderNumber" style="font-weight:700; color:#1e293b;">
            Payer Mobile / Wallet Number <span class="required" style="color:#e11d48;">*</span>
          </label>
          <div style="position:relative;">
            <input
              type="tel"
              name="sender_number"
              id="senderNumber"
              class="form-control"
              placeholder="01XXXXXXXXX"
              value="<?= e(!empty($application['sender_number']) ? $application['sender_number'] : $application['mobile']) ?>"
              required
              maxlength="15"
              style="padding-left:2.5rem; font-size:1rem; font-weight:600;"
            >
            <i class="fas fa-phone-alt" style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:#94a3b8;"></i>
          </div>
          <small style="color:#64748b; font-size:0.8rem;">Enter your 11-digit Bangladeshi mobile wallet number (e.g., 017XXXXXXXX). The Sandbox Gateway will generate &amp; validate your Transaction ID once automatically.</small>
        </div>

        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:0.95rem 1.15rem; margin-bottom:1.5rem; font-size:0.85rem; color:#14532d; display:flex; align-items:flex-start; gap:0.75rem;">
          <i class="fas fa-lock" style="color:#15803d; font-size:1.05rem; margin-top:2px;"></i>
          <div>
            <strong>How verification works:</strong> Clicking below launches the encrypted Sandbox Checkout session -> authorizes via single-use OTP -> verifies HMAC signature once -> writes your Receipt &amp; generates your <strong>Official Application Card + A4 PDF</strong> immediately.
          </div>
        </div>

        <button type="submit" id="payAndGenerateBtn" class="btn-primary" style="background:#e11d48; width:100%; justify-content:center; padding:1rem; font-size:1.05rem; font-weight:800; border-radius:8px; box-shadow:0 4px 10px rgba(225,29,72,0.25);">
          <i class="fas fa-check-circle"></i> Pay ৳150 &amp; Generate Application Card
        </button>
      </form>
    </div>

    <!-- Security & Instruction Note -->
    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:1.2rem; font-size:0.88rem; color:#1e40af;">
      <div style="font-weight:700; margin-bottom:4px;"><i class="fas fa-info-circle"></i> Important Payment Policies</div>
      <ul style="margin:0.4rem 0 0 1.2rem; line-height:1.6;">
        <li>Each Sandbox Transaction ID is cryptographically signed and validated <strong>only once</strong> to prevent duplicate billing or replay attacks.</li>
        <li>A Paid Application Card &amp; official PDF are generated strictly after genuine gateway verification succeeds.</li>
        <li>Once approved by the Admissions Committee, your unique <strong>Student ID</strong> and <strong>Student Portal credentials</strong> will appear automatically on your Application Status page.</li>
      </ul>
    </div>

  </div>
</section>

<script>
(function() {
  var grid = document.getElementById('payChannelGrid');
  if (grid) {
    var radios = grid.querySelectorAll('input[name="payment_method"]');
    function syncChannelStyles() {
      grid.querySelectorAll('.rmu-pay-channel-card').forEach(function(card) {
        var radio = card.querySelector('input[type="radio"]');
        if (radio && radio.checked) {
          card.classList.add('is-selected');
          card.style.borderColor = card.dataset.accent || '#e11d48';
          card.style.background = card.dataset.bg || '#fff1f2';
        } else {
          card.classList.remove('is-selected');
          card.style.borderColor = '#cbd5e1';
          card.style.background = '#f8fafc';
        }
      });
    }
    radios.forEach(function(r) {
      r.addEventListener('change', syncChannelStyles);
    });
  }

  var form = document.getElementById('admissionPayForm');
  var submitBtn = document.getElementById('payAndGenerateBtn');
  var originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

  function resetPayBtn() {
    if (form) form.dataset.submitting = '0';
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
      if (originalBtnHtml) submitBtn.innerHTML = originalBtnHtml;
    }
  }
  window.addEventListener('pageshow', resetPayBtn);

  if (form) {
    form.addEventListener('submit', function(e) {
      if (form.dataset.submitting === '1') {
        e.preventDefault();
        return;
      }
      form.dataset.submitting = '1';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.78';
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Launching Sandbox Gateway...';
      }
    });
  }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
