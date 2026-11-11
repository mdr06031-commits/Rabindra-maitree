<?php
/**
 * Admission Application Status Tracking & Official Application Card
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Track Application Status';
$breadcrumbTitle = 'Online Application Status Tracking';
$breadcrumbs = [['title' => 'Admission', 'url' => APP_URL . '/admissions.php'], ['title' => 'Application Status']];

$searchQuery = trim($_GET['ref'] ?? ($_GET['mobile'] ?? ''));
$application = null;

if (!empty($searchQuery)) {
    $db = getDb();
    $application = fetch_admission_full_record(0, $searchQuery, $db, true);
    if ($application) {
        authorize_applicant_session_ref(
            (string)$application['application_ref'],
            $application['application_id'] ?? null,
            $application['roll_number'] ?? null
        );
    }
}

$docStyles = get_admission_document_styles();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<style>
<?= $docStyles ?>
.rmu-status-actions {
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}
.rmu-status-btn-group {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.rmu-status-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  border: 1px solid transparent;
}
@media print {
  .rmu-topbar, .rmu-navbar, .rmu-footer, .breadcrumb-section, .no-print, form {
    display: none !important;
  }
  body, html, .section {
    background: #ffffff !important;
    padding: 0 !important;
  }
  .rmu-doc-card {
    box-shadow: none !important;
  }
}
</style>

<section class="section section-bg-white">
  <div class="container" style="max-width: 920px;">
    <!-- Lookup Search Card -->
    <div class="no-print" style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-lg); padding: 1.75rem; margin-bottom: 2rem;">
      <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.5rem;">
        <i class="fas fa-search-location" style="color: var(--rmu-primary);"></i> Check Application Status
      </h3>
      <p style="color: var(--rmu-muted); font-size: 0.9rem; margin-bottom: 1.15rem;">
        Enter your Application ID (e.g., <code>RMU-2026-0001</code>), Admission Roll Number (e.g., <code>2026-0001</code>), or registered 11-digit mobile number.
      </p>

      <form action="<?= APP_URL ?>/admission-status.php" method="GET" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <input type="text" name="ref" value="<?= e($searchQuery) ?>" placeholder="Enter Application ID (RMU-2026-0001), Roll (2026-0001), or Mobile" class="form-control" style="flex: 1; min-width: min(100%, 240px);" required>
        <button type="submit" class="btn-primary" style="padding: 0.75rem 1.8rem;">
          <i class="fas fa-search"></i> Check Status
        </button>
      </form>
    </div>

    <?php if (!empty($searchQuery) && !$application): ?>
      <div class="alert alert-error no-print">
        <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
        <div>
          No application was found matching <strong><?= e($searchQuery) ?></strong>. Please double-check your Application ID, Roll Number, or mobile number.
        </div>
      </div>
    <?php endif; ?>

    <?php if ($application): ?>
      <?php
        $isPaid = (($application['payment_status'] ?? '') === 'Paid');
        $admLoginUrl = APP_URL . '/student/login.php?student_id=' . urlencode((string)($application['student_id'] ?? ''));
        $appCardUrlBase = APP_URL . '/admission-document.php?id=' . (int)$application['id'] . '&doc=application';
        $receiptUrlBase = APP_URL . '/admission-document.php?id=' . (int)$application['id'] . '&doc=receipt';

        ensure_admission_payment_gateway_schema($db);
        $latestTxStmt = $db->prepare("
            SELECT gateway_session_id, gateway_provider, gateway_mode, transaction_id, gateway_status, validated_once_at, verified_at, failure_reason, created_at
            FROM admission_payment_transactions
            WHERE admission_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $latestTxStmt->execute([(int)$application['id']]);
        $latestPayTx = $latestTxStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $statusSummary = enrich_admission_document_data($application, $db);
      ?>

      <!-- Payment Notice OR Portal Decision Banner -->
      <?php if (!$isPaid): ?>
        <?php
          $rawPaySt = (string)($application['payment_status'] ?? 'Pending');
          $isFailedState = ($rawPaySt === 'Failed');
          $isUnderVerif = ($rawPaySt === 'Under Verification');
          $unpaidBadgeTxt = $isFailedState ? 'Payment Failed' : ($isUnderVerif ? 'Under Verification' : 'Payment Pending');
          $unpaidStatusTxt = $isFailedState ? 'Failed' : ($isUnderVerif ? 'Under Verification' : 'Unpaid');
          $failDetail = trim((string)($latestPayTx['failure_reason'] ?? ''));
        ?>
        <div class="no-print" style="background:<?= $isFailedState ? '#fef2f2' : '#fff1f2' ?>; border:2px solid #fecdd3; border-radius:12px; padding:1.4rem 1.7rem; margin-bottom:1.75rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.15rem;">
          <div style="flex:1; min-width:min(100%, 260px);">
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem;">
              <span style="background:#be123c; color:#ffffff; padding:2px 8px; border-radius:4px; font-weight:700; font-size:0.75rem; text-transform:uppercase;"><?= e($unpaidBadgeTxt) ?></span>
              <h3 style="font-size:1.15rem; font-weight:800; color:#9f1239; margin:0;">Application Fee: ৳150 BDT</h3>
            </div>
            <p style="color:#881337; font-size:0.9rem; margin:0;">
              <?php if ($isFailedState && $failDetail !== ''): ?>
                <strong>Previous payment verification failed:</strong> <?= e($failDetail) ?>. Complete verified checkout via the Sandbox Payment Gateway to generate your Official Application Card.
              <?php elseif ($isUnderVerif): ?>
                Your checkout session is pending gateway authorization. Complete payment verification in the Sandbox Gateway to unlock your Official Application Card &amp; PDF.
              <?php else: ?>
                Complete your <strong>৳150 application fee</strong> payment via our verified Sandbox Payment Gateway to generate your Official Application Card and Payment Receipt.
              <?php endif; ?>
            </p>
          </div>
          <div>
            <a href="<?= APP_URL ?>/admission-payment.php?ref=<?= urlencode($application['application_ref']) ?>" class="btn-primary" style="background:#e11d48; padding:0.85rem 1.6rem; font-size:0.95rem; font-weight:700; text-decoration:none;">
              <i class="fas fa-credit-card"></i> <?= $isFailedState ? 'Retry ৳150 Payment &amp; Generate Card' : 'Pay ৳150 Fee Now &rarr;' ?>
            </a>
          </div>
        </div>
      <?php elseif (($application['status'] ?? '') === 'Accepted' && !empty($application['student_id'])): ?>
        <div class="no-print" style="background:linear-gradient(135deg, #0f2c59 0%, #1e40af 100%); color:#ffffff; border-radius:12px; padding:1.6rem 1.8rem; margin-bottom:1.75rem; border:1.5px solid #c9a227;">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.1rem; border-bottom:1px solid rgba(255,255,255,0.2); padding-bottom:0.9rem;">
            <div>
              <span style="background:rgba(255,255,255,0.2); color:#ffffff; padding:3px 10px; border-radius:20px; font-size:0.76rem; font-weight:700; text-transform:uppercase;">
                <i class="fas fa-check-circle" style="color:#4ade80;"></i> Admission Approved &amp; Enrolled
              </span>
              <h3 style="font-size:1.3rem; font-weight:800; margin:0.4rem 0 0.2rem; color:#ffffff;">
                <i class="fas fa-user-graduate"></i> Student Academic Portal Credentials
              </h3>
            </div>
            <div>
              <a href="<?= e($admLoginUrl) ?>" style="background:#22c55e; color:#ffffff !important; font-weight:800; padding:0.8rem 1.5rem; text-decoration:none; border-radius:8px; display:inline-flex; align-items:center; gap:0.5rem; font-size:0.95rem;">
                <i class="fas fa-sign-in-alt"></i> Login to Student Portal Now &rarr;
              </a>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 190px), 1fr)); gap:0.9rem;">
            <div style="background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.2); border-radius:8px; padding:0.85rem 1.1rem;">
              <small style="color:#bfdbfe; font-size:0.72rem; text-transform:uppercase;">Student ID</small>
              <div style="font-family:monospace; font-size:1.35rem; font-weight:800; color:#ffffff; margin-top:2px;">
                <?= e($application['student_id']) ?>
              </div>
            </div>
            <div style="background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.2); border-radius:8px; padding:0.85rem 1.1rem;">
              <small style="color:#bfdbfe; font-size:0.72rem; text-transform:uppercase;">Application ID &amp; Roll</small>
              <div style="font-family:monospace; font-size:0.95rem; font-weight:800; color:#ffffff; margin-top:2px;">
                <?= e(!empty($application['application_id']) ? $application['application_id'] : $application['application_ref']) ?>
              </div>
              <div style="font-family:monospace; font-size:0.88rem; font-weight:700; color:#fde68a; margin-top:2px;">
                Roll: <?= e(!empty($application['roll_number']) ? $application['roll_number'] : ($application['exam_roll'] ?? 'Assigned')) ?>
              </div>
            </div>
            <div style="background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.2); border-radius:8px; padding:0.85rem 1.1rem;">
              <small style="color:#bfdbfe; font-size:0.72rem; text-transform:uppercase;">Portal Password</small>
              <?php if (!empty($application['portal_password'])): ?>
                <div style="font-family:monospace; font-size:1.35rem; font-weight:800; color:#fde047; margin-top:2px;">
                  <?= e($application['portal_password']) ?>
                </div>
              <?php else: ?>
                <div style="font-size:0.86rem; font-weight:700; color:#e2e8f0; margin-top:4px;">
                  Updated / Protected (Use your portal password)
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if (($application['status'] ?? '') === 'Rejected'): ?>
        <div class="no-print" style="background:#fef2f2; border:2px solid #fecaca; border-radius:12px; padding:1.4rem 1.6rem; margin-bottom:1.75rem;">
          <div style="display:flex; align-items:center; gap:0.6rem; color:#b91c1c; font-weight:800; font-size:1.1rem; margin-bottom:0.35rem;">
            <i class="fas fa-times-circle"></i> Admission Application Rejected
          </div>
          <?php if (!empty($application['admin_notes'])): ?>
            <div style="background:#ffffff; border-left:4px solid #b91c1c; padding:8px 12px; border-radius:0 6px 6px 0; font-size:0.88rem; color:#450a0a; margin-top:6px;">
              <strong>Admissions Committee Note:</strong> <?= e($application['admin_notes']) ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($isPaid): ?>
        <?php if (!empty($_GET['paid'])): ?>
          <div id="paymentVerifiedCardBanner" class="no-print" style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border:2px solid #22c55e; border-radius:12px; padding:1.25rem 1.6rem; margin-bottom:1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
              <div style="display:flex; align-items:center; gap:0.5rem; color:#15803d; font-weight:800; font-size:1.08rem;">
                <i class="fas fa-check-circle" style="font-size:1.25rem;"></i>
                Payment Verified &amp; Official Application Card Generated!
              </div>
              <div style="font-size:0.86rem; color:#166534; margin-top:4px;">
                Transaction ID: <strong style="font-family:monospace; color:#0f2c59;"><?= e($application['transaction_id'] ?? 'Verified') ?></strong>
                &bull; Channel: <strong><?= e($application['payment_method'] ?? 'Sandbox') ?></strong>
                <?php if (!empty($latestPayTx['gateway_session_id'])): ?>
                  &bull; Gateway Ref: <code style="font-size:0.8rem;"><?= e($latestPayTx['gateway_session_id']) ?></code>
                <?php endif; ?>
                <?php if (!empty($application['pdf_path']) && is_file(BASE_PATH . '/' . $application['pdf_path'])): ?>
                  &bull; <span style="color:#15803d; font-weight:700;">PDF Card Ready</span>
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
              <a href="<?= e($appCardUrlBase) ?>&amp;action=download" class="rmu-status-btn" style="background:#0f2c59; color:#ffffff;">
                <i class="fas fa-file-download"></i> Download Card PDF
              </a>
              <a href="<?= e($receiptUrlBase) ?>&amp;action=view" class="rmu-status-btn" style="background:#15803d; color:#ffffff;">
                <i class="fas fa-receipt"></i> View Payment Receipt
              </a>
            </div>
          </div>
        <?php endif; ?>

        <!-- Document Action Toolbar (Real PDF / Print / Separate Payment Receipt) -->
        <div class="rmu-status-actions no-print">
          <div style="font-size:0.9rem; font-weight:700; color:#0f2c59;">
            <i class="fas fa-id-card" style="color:#c9a227;"></i>
            Official Admission Application Card
          </div>
          <div class="rmu-status-btn-group">
            <a href="<?= e($appCardUrlBase) ?>&amp;action=pdf" target="_blank" class="rmu-status-btn" style="background:#0f2c59; color:#ffffff;">
              <i class="fas fa-file-pdf"></i> View PDF
            </a>
            <a href="<?= e($appCardUrlBase) ?>&amp;action=download" class="rmu-status-btn" style="background:#b91c1c; color:#ffffff;">
              <i class="fas fa-download"></i> Download PDF
            </a>
            <button type="button" onclick="window.print()" class="rmu-status-btn" style="background:#1e40af; color:#ffffff;">
              <i class="fas fa-print"></i> Print Application
            </button>
            <a href="<?= e($receiptUrlBase) ?>&amp;action=view" class="rmu-status-btn" style="background:#15803d; color:#ffffff;">
              <i class="fas fa-receipt"></i> Payment Receipt
            </a>
            <a href="<?= e($receiptUrlBase) ?>&amp;action=download" class="rmu-status-btn" style="background:#ffffff; color:#15803d; border-color:#15803d;">
              <i class="fas fa-file-download"></i> Receipt PDF
            </a>
          </div>
        </div>

        <!-- OFFICIAL PREMIUM APPLICATION CARD (Generated Strictly After Verified Payment) -->
        <?= render_premium_application_card_html($application, ['db' => $db]) ?>
      <?php else: ?>
        <!-- Payment Pending Application Summary (Official Paid Application Card locked until verification) -->
        <div id="pendingApplicationSummary" style="background:#ffffff; border:2px solid #cbd5e1; border-radius:10px; overflow:hidden; box-shadow:0 6px 18px rgba(15,44,89,0.06);">
          <div style="background:#0f2c59; color:#ffffff; padding:1rem 1.5rem; border-bottom:3px solid #c9a227; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
            <div>
              <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.8px; color:#fde68a; font-weight:700;">Application Summary &bull; Awaiting Payment Verification</div>
              <h3 style="margin:2px 0 0; font-size:1.15rem; font-weight:800; color:#ffffff;">
                <?= e($statusSummary['application_id']) ?>
                <?php if ($statusSummary['roll_number'] !== ''): ?>
                  <span style="font-family:monospace; font-size:0.95rem; color:#bfdbfe; margin-left:8px;">(Roll: <?= e($statusSummary['roll_number']) ?>)</span>
                <?php endif; ?>
              </h3>
            </div>
            <span style="background:#be123c; color:#ffffff; padding:4px 12px; border-radius:20px; font-size:0.78rem; font-weight:800;">
              <i class="fas fa-lock"></i> <?= e($unpaidStatusTxt) ?>
            </span>
          </div>

          <div style="padding:1.5rem;">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 210px), 1fr)); gap:1rem; font-size:0.9rem; margin-bottom:1.25rem;">
              <div>
                <span style="display:block; font-size:0.76rem; color:#64748b; text-transform:uppercase; font-weight:700;">Applicant Name</span>
                <strong style="color:#0f172a; font-size:1rem;"><?= e($application['full_name']) ?></strong>
              </div>
              <div>
                <span style="display:block; font-size:0.76rem; color:#64748b; text-transform:uppercase; font-weight:700;">Selected Department &amp; Program</span>
                <strong style="color:#0f2c59;"><?= e($statusSummary['program_name']) ?></strong>
                <small style="display:block; color:#475569;"><?= e($statusSummary['department_name']) ?> &bull; <?= e($statusSummary['degree_type']) ?></small>
              </div>
              <div>
                <span style="display:block; font-size:0.76rem; color:#64748b; text-transform:uppercase; font-weight:700;">Academic Session &amp; Duration</span>
                <strong style="color:#0f172a;"><?= e($statusSummary['academic_session']) ?></strong>
                <small style="display:block; color:#475569;"><?= e($statusSummary['duration_label']) ?></small>
              </div>
              <div>
                <span style="display:block; font-size:0.76rem; color:#64748b; text-transform:uppercase; font-weight:700;">Passing Year Details</span>
                <strong style="color:#0f172a;">Actual: <?= e(!empty($statusSummary['actual_passing_year']) ? $statusSummary['actual_passing_year'] : 'N/A') ?></strong>
                <?php if (!empty($application['expected_passing_year'])): ?>
                  <small style="display:block; color:#1e40af;">Expected (Advisory): <?= (int)$application['expected_passing_year'] ?></small>
                <?php endif; ?>
              </div>
              <div>
                <span style="display:block; font-size:0.76rem; color:#64748b; text-transform:uppercase; font-weight:700;">Application Status</span>
                <strong><?= e($statusSummary['app_status_label']) ?></strong>
              </div>
              <div>
                <span style="display:block; font-size:0.76rem; color:#64748b; text-transform:uppercase; font-weight:700;">Student ID Status</span>
                <strong><?= e($statusSummary['student_id_detail']) ?></strong>
              </div>
            </div>

            <div style="background:#f8fafc; border:1.5px dashed #94a3b8; border-radius:8px; padding:1rem 1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
              <div style="font-size:0.88rem; color:#334155;">
                <i class="fas fa-shield-alt" style="color:#0f2c59;"></i>
                <strong>Official Application Card Locked:</strong> Your Paid Application Card, QR Card, and A4 PDF will be generated automatically immediately after verifying your ৳150 fee payment.
              </div>
              <a href="<?= APP_URL ?>/admission-payment.php?ref=<?= urlencode($application['application_ref']) ?>" class="btn-primary" style="background:#e11d48; text-decoration:none; white-space:normal; text-align:center;">
                <i class="fas fa-credit-card"></i> Pay ৳150 &amp; Generate Application Card
              </a>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="no-print" style="margin-top: 1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <a href="<?= APP_URL ?>/admission-form.php" style="font-size: 0.9rem; font-weight: 600;">
          Submit another application &rarr;
        </a>
        <?php if (!empty($application['student_id']) && ($application['status'] ?? '') === 'Accepted'): ?>
          <a href="<?= e($admLoginUrl) ?>" style="font-size: 0.9rem; font-weight: 700; color:#15803d;">
            <i class="fas fa-sign-in-alt"></i> Login to Student Portal &rarr;
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
