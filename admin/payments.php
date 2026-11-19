<?php
/**
 * Admin Panel: Payment Management
 * Rabindra Maitree University (RMU)
 * Tracks Admission Fees (৳150) and Student Semester Tuition Fees with sandbox/live gateway auditing
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_manager_role();
$adminPageTitle = 'Payment Management';
$db = getDb();
ensure_admission_payment_gateway_schema($db);

// Handle Actions (Verify, Mark Failed, Refund)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $type = $_POST['type'] ?? 'admission'; // 'admission' or 'tuition'
        $id = (int)($_POST['id'] ?? 0);

        if ($type === 'admission' && $id > 0) {
            if ($action === 'verify') {
                $suppliedTrx = trim((string)($_POST['transaction_id'] ?? ''));
                $verRes = rmu_admin_verify_admission_payment($db, $id, $suppliedTrx, (int)($_SESSION['user_id'] ?? 1));
                if (!empty($verRes['success'])) {
                    flash_set(!empty($verRes['already_paid']) ? 'info' : 'success', (string)$verRes['message']);
                } else {
                    flash_set('error', (string)($verRes['error'] ?? 'Could not verify admission payment.'));
                }
            } elseif ($action === 'mark_failed') {
                $db->prepare("UPDATE admissions SET payment_status = 'Failed' WHERE id = ?")->execute([$id]);
                $db->prepare("UPDATE admission_payment_transactions SET gateway_status = 'FAILED', status = 'failed', failure_reason = COALESCE(NULLIF(failure_reason, ''), 'Marked Failed by Administrator'), updated_at = NOW() WHERE admission_id = ? AND validated_once_at IS NULL AND gateway_status IN ('INITIATED', 'AUTHORIZED')")->execute([$id]);
                invalidate_admission_application_pdf_cache($id, $db);
                log_audit('PAYMENT_FAILED_ADMISSION', "Marked admission payment as Failed for application ID #{$id}");
                flash_set('warning', "Payment marked as Failed and Application Card remains locked.");
            } elseif ($action === 'refund') {
                $db->prepare("UPDATE admissions SET payment_status = 'Refunded' WHERE id = ?")->execute([$id]);
                $db->prepare("UPDATE admission_payment_transactions SET failure_reason = 'Refunded by Administrator', updated_at = NOW() WHERE admission_id = ?")->execute([$id]);
                invalidate_admission_application_pdf_cache($id, $db);
                log_audit('PAYMENT_REFUNDED_ADMISSION', "Refunded admission fee for application ID #{$id}");
                flash_set('info', "Payment marked as Refunded.");
            }
        } elseif ($type === 'tuition' && $id > 0) {
            if ($action === 'verify') {
                $tStmt = $db->prepare("SELECT id, student_id, fee_title, transaction_id, status FROM student_fees WHERE id = ? LIMIT 1");
                $tStmt->execute([$id]);
                $tRow = $tStmt->fetch(PDO::FETCH_ASSOC);
                if (!$tRow) {
                    flash_set('error', 'Tuition fee voucher not found.');
                } else {
                    $suppliedTrx = strtoupper(trim((string)($_POST['transaction_id'] ?? '')));
                    $existingTrx = trim((string)($tRow['transaction_id'] ?? ''));
                    $trx = ($suppliedTrx !== '')
                        ? $suppliedTrx
                        : (($existingTrx !== '') ? $existingTrx : ('RMU-FEE-' . strtoupper(bin2hex(random_bytes(4)))));

                    $dupChk = $db->prepare("SELECT id FROM student_fees WHERE transaction_id = ? AND id != ? LIMIT 1");
                    $dupChk->execute([$trx, $id]);
                    if ($dupChk->fetch()) {
                        flash_set('error', "Duplicate Transaction ID ({$trx}) is already assigned to another tuition fee voucher.");
                    } else {
                        $db->prepare("UPDATE student_fees SET status = 'Paid', transaction_id = ?, paid_at = NOW() WHERE id = ?")->execute([$trx, $id]);
                        log_audit('PAYMENT_VERIFIED_TUITION', "Verified semester tuition fee for fee voucher ID #{$id} (TrxID: {$trx})");
                        flash_set('success', "Semester fee voucher marked as Paid (TrxID: {$trx}).");
                    }
                }
            } elseif ($action === 'mark_failed') {
                $db->prepare("UPDATE student_fees SET status = 'Failed' WHERE id = ?")->execute([$id]);
                log_audit('PAYMENT_FAILED_TUITION', "Marked semester tuition fee voucher ID #{$id} as Failed");
                flash_set('warning', "Tuition fee payment marked as Failed.");
            }
        }
    }
    $payRedir = [];
    $rTab = trim((string)($_POST['return_tab'] ?? ($_GET['tab'] ?? '')));
    $rSt = trim((string)($_POST['return_status'] ?? ($_GET['status'] ?? '')));
    $rSrch = trim((string)($_POST['return_search'] ?? ($_GET['search'] ?? '')));
    if ($rTab !== '' && $rTab !== 'all') $payRedir['tab'] = $rTab;
    if ($rSt !== '' && $rSt !== 'All') $payRedir['status'] = $rSt;
    if ($rSrch !== '') $payRedir['search'] = $rSrch;
    $payQuerySuffix = !empty($payRedir) ? ('?' . http_build_query($payRedir)) : '';
    header("Location: " . APP_URL . "/admin/payments.php" . $payQuerySuffix);
    exit;
}

// Active tab ('all', 'admissions', 'tuition')
$tab = trim($_GET['tab'] ?? 'all');
$filterStatus = trim($_GET['status'] ?? 'All');
$search = trim($_GET['search'] ?? '');

// Metrics
$totalAdmPaid = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM admissions WHERE payment_status = 'Paid'")->fetchColumn();
$totalTuitionPaid = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM student_fees WHERE status = 'Paid'")->fetchColumn();
$totalPendingCount = (int)$db->query("SELECT (SELECT COUNT(*) FROM admissions WHERE payment_status IN ('Pending', 'Under Verification')) + (SELECT COUNT(*) FROM student_fees WHERE status IN ('Pending', 'Under Verification'))")->fetchColumn();

// Fetch Admission Payments (with latest Sandbox Gateway transaction metadata)
$admSql = "
    SELECT a.id, a.application_ref, a.application_id, a.roll_number, a.exam_roll, a.application_year,
           a.interested_subject, a.academic_session, a.status, a.full_name, a.mobile,
           a.payment_status, a.payment_amount, a.payment_method, a.payment_gateway_mode,
           a.transaction_id, a.sender_number, a.payment_screenshot, a.pdf_path, a.paid_at, a.created_at,
           apt.gateway_session_id, apt.gateway_provider AS tx_gateway_provider, apt.gateway_mode AS tx_gateway_mode,
           apt.gateway_status, apt.validated_once_at, apt.verified_at, apt.failure_reason
    FROM admissions a
    LEFT JOIN admission_payment_transactions apt ON apt.id = (
        SELECT t2.id FROM admission_payment_transactions t2
        WHERE t2.admission_id = a.id
        ORDER BY (t2.gateway_status = 'VERIFIED') DESC, t2.id DESC
        LIMIT 1
    )
    WHERE 1=1
";
$admParams = [];
if ($filterStatus !== 'All') {
    $admSql .= " AND a.payment_status = ?";
    $admParams[] = $filterStatus;
}
if (!empty($search)) {
    $admSql .= " AND (a.application_ref LIKE ? OR a.application_id LIKE ? OR a.roll_number LIKE ? OR a.exam_roll LIKE ? OR a.full_name LIKE ? OR a.transaction_id LIKE ? OR a.mobile LIKE ? OR COALESCE(apt.gateway_session_id, '') LIKE ?)";
    $like = "%$search%";
    $admParams = array_merge($admParams, [$like, $like, $like, $like, $like, $like, $like, $like]);
}
$admSql .= " ORDER BY a.id DESC LIMIT 50";
$admStmt = $db->prepare($admSql);
$admStmt->execute($admParams);
$admissionPayments = $admStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Tuition Fee Payments
$feeSql = "SELECT sf.*, s.student_id as st_code, s.roll_number as st_roll, s.application_number as st_app_no, s.full_name as st_name FROM student_fees sf JOIN students s ON sf.student_id = s.id WHERE 1=1";
$feeParams = [];
if ($filterStatus !== 'All') {
    $feeSql .= " AND sf.status = ?";
    $feeParams[] = $filterStatus;
}
if (!empty($search)) {
    $feeSql .= " AND (s.student_id LIKE ? OR s.roll_number LIKE ? OR s.application_number LIKE ? OR s.full_name LIKE ? OR sf.transaction_id LIKE ? OR sf.fee_title LIKE ?)";
    $like = "%$search%";
    $feeParams = array_merge($feeParams, [$like, $like, $like, $like, $like, $like]);
}
$feeSql .= " ORDER BY sf.id DESC LIMIT 50";
$feeStmt = $db->prepare($feeSql);
$feeStmt->execute($feeParams);
$tuitionPayments = $feeStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem;">
      <i class="fas fa-receipt" style="color:var(--rmu-primary);"></i> Payment Management
    </h2>
    <p style="color:#64748b; font-size:0.88rem; margin:0;">
      Admission Application Fees (৳150) &amp; Semester Tuition Invoices &bull; Gateway Audit &amp; Verification
    </p>
  </div>
  <div>
    <a href="<?= APP_URL ?>/admin/settings.php#payment-settings" class="btn" style="background:#15803d; color:#ffffff; padding:0.6rem 1.15rem; border-radius:6px; font-size:0.88rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:8px; box-shadow:0 2px 5px rgba(21,128,61,0.25);">
      <i class="fas fa-mobile-alt"></i> Change Payment Numbers (পেমেন্ট নম্বর পরিবর্তন)
    </a>
  </div>
</div>

<!-- KPI Cards -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap:1.25rem; margin-bottom:2rem;">
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; display:flex; align-items:center; gap:1rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="width:52px; height:52px; border-radius:10px; background:#f0fdf4; color:#15803d; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
      <i class="fas fa-hand-holding-usd"></i>
    </div>
    <div>
      <div style="font-size:1.6rem; font-weight:800; color:#15803d; line-height:1;">৳ <?= number_format($totalAdmPaid + $totalTuitionPaid, 2) ?></div>
      <div style="font-size:0.78rem; color:#64748b; font-weight:600; margin-top:3px;">Total University Revenue Collected</div>
    </div>
  </div>

  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; display:flex; align-items:center; gap:1rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="width:52px; height:52px; border-radius:10px; background:#eff6ff; color:#1e40af; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
      <i class="fas fa-user-graduate"></i>
    </div>
    <div>
      <div style="font-size:1.6rem; font-weight:800; color:#1e40af; line-height:1;">৳ <?= number_format($totalAdmPaid, 2) ?></div>
      <div style="font-size:0.78rem; color:#64748b; font-weight:600; margin-top:3px;">Admission Intake Fees (৳150)</div>
    </div>
  </div>

  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; display:flex; align-items:center; gap:1rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="width:52px; height:52px; border-radius:10px; background:#fef3c7; color:#b45309; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
      <i class="fas fa-clock"></i>
    </div>
    <div>
      <div style="font-size:1.6rem; font-weight:800; color:#b45309; line-height:1;"><?= $totalPendingCount ?></div>
      <div style="font-size:0.78rem; color:#64748b; font-weight:600; margin-top:3px;">Pending Vouchers / In Review</div>
    </div>
  </div>
</div>

<!-- Search & Filter Controls -->
<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem 1.5rem; margin-bottom:1.5rem;">
  <form method="GET" action="" class="pay-filter-grid" style="display:grid; grid-template-columns:1.5fr 1fr 1fr auto; gap:1rem; align-items:flex-end;">

    <div>
      <label style="font-size:0.8rem; font-weight:700; color:#0f2c59; display:block; margin-bottom:4px;">Search App ID / Roll / Student / TrxID:</label>
      <input type="text" name="search" value="<?= e($search) ?>" placeholder="e.g. RMU-2026-0001, 2026-0001, RMU26..., TRX-..." class="form-control" style="font-size:0.88rem;">
    </div>

    <div>
      <label style="font-size:0.8rem; font-weight:700; color:#0f2c59; display:block; margin-bottom:4px;">Payment Status:</label>
      <select name="status" class="form-select" style="font-size:0.88rem;">
        <option value="All" <?= ($filterStatus === 'All') ? 'selected' : '' ?>>All Statuses</option>
        <option value="Paid" <?= ($filterStatus === 'Paid') ? 'selected' : '' ?>>Paid (Verified)</option>
        <option value="Pending" <?= ($filterStatus === 'Pending') ? 'selected' : '' ?>>Pending Verification</option>
        <option value="Under Verification" <?= ($filterStatus === 'Under Verification') ? 'selected' : '' ?>>Under Verification</option>
        <option value="Failed" <?= ($filterStatus === 'Failed') ? 'selected' : '' ?>>Failed</option>
        <option value="Refunded" <?= ($filterStatus === 'Refunded') ? 'selected' : '' ?>>Refunded</option>
      </select>
    </div>

    <div>
      <label style="font-size:0.8rem; font-weight:700; color:#0f2c59; display:block; margin-bottom:4px;">Category Filter:</label>
      <select name="tab" class="form-select" onchange="this.form.submit()" style="font-size:0.88rem;">
        <option value="all" <?= ($tab === 'all') ? 'selected' : '' ?>>All Transaction Types</option>
        <option value="admissions" <?= ($tab === 'admissions') ? 'selected' : '' ?>>Admission Fees (৳150)</option>
        <option value="tuition" <?= ($tab === 'tuition') ? 'selected' : '' ?>>Semester Tuition Fees</option>
      </select>
    </div>

    <button type="submit" class="btn-primary" style="padding:0.65rem 1.25rem; font-size:0.88rem;">
      <i class="fas fa-filter"></i> Apply Filter
    </button>
  </form>
</div>

<!-- Transactions Table -->
<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05); margin-bottom:2rem;">
  <div style="padding:1rem 1.25rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
    <strong style="font-size:0.95rem; color:#0f172a;">
      <i class="fas fa-list" style="color:var(--rmu-primary);"></i> Verified Payment Transactions &amp; Receipts
    </strong>
  </div>

  <div class="table-responsive">
    <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.88rem;">
      <thead>
        <tr>
          <th>Reference / Student</th>
          <th>Description &amp; Channel</th>
          <th>Amount (BDT)</th>
          <th>Transaction ID (TrxID)</th>
          <th>Status</th>
          <th>Date</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <!-- 1. Admission Payments -->
        <?php if ($tab === 'all' || $tab === 'admissions'): ?>
          <?php foreach ($admissionPayments as $adm): ?>
            <tr>
              <td>
                <span style="font-size:0.72rem; font-weight:700; color:#6366f1; background:#eef2ff; padding:1px 6px; border-radius:4px;">ADMISSION INTAKE</span>
                <strong style="display:block; color:#0f172a; margin-top:2px;"><?= e($adm['full_name']) ?></strong>
                <small style="font-family:monospace; color:#1e40af; font-weight:700;">App ID: <?= e(!empty($adm['application_id']) ? $adm['application_id'] : $adm['application_ref']) ?></small>
                <?php $admRollDisplay = !empty($adm['roll_number']) ? $adm['roll_number'] : ($adm['exam_roll'] ?? ''); ?>
                <?php if ($admRollDisplay !== ''): ?>
                  <small style="font-family:monospace; color:#6d28d9; font-weight:700; display:block;">Roll No: <?= e($admRollDisplay) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <div>৳150 Online Admission Fee</div>
                <?php if (!empty($adm['interested_subject']) || !empty($adm['academic_session'])): ?>
                  <small style="display:block; color:#334155; font-weight:600;"><?= e($adm['interested_subject'] ?: '') ?><?= !empty($adm['academic_session']) ? ' &bull; ' . e($adm['academic_session']) : '' ?></small>
                <?php endif; ?>
                <small style="color:#64748b;">
                  <?= e($adm['payment_method'] ?? ($adm['tx_gateway_provider'] ?? 'bKash')) ?>
                  (<?= e(strtoupper((string)($adm['tx_gateway_mode'] ?? ($adm['payment_gateway_mode'] ?? 'sandbox')))) ?>)
                  &bull; Sender: <?= e($adm['sender_number'] ?? 'N/A') ?>
                </small>
                <?php if (!empty($adm['gateway_session_id'])): ?>
                  <small style="display:block; font-family:monospace; color:#475569;">Session: <?= e($adm['gateway_session_id']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <strong style="color:#15803d; font-size:0.95rem;">৳ <?= number_format((float)($adm['payment_amount'] ?? 150), 2) ?></strong>
              </td>
              <td>
                <span style="font-family:monospace; color:#b91c1c; font-weight:700;"><?= e($adm['transaction_id'] ?: '—') ?></span>
                <?php if (!empty($adm['validated_once_at'])): ?>
                  <small style="display:block; color:#15803d; font-weight:600;">
                    <i class="fas fa-lock"></i> Verified Once: <?= e($adm['validated_once_at']) ?>
                  </small>
                <?php elseif (!empty($adm['gateway_status'])): ?>
                  <small style="display:block; color:#64748b;">Gateway: <?= e($adm['gateway_status']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $st = $adm['payment_status'];
                  $badgeBg = ($st === 'Paid')
                    ? '#dcfce7; color:#15803d;'
                    : (($st === 'Pending' || $st === 'Under Verification')
                        ? '#fef3c7; color:#b45309;'
                        : '#fee2e2; color:#b91c1c;');
                ?>
                <span style="padding:2px 8px; border-radius:12px; font-weight:700; font-size:0.75rem; background:<?= $badgeBg ?>">
                  <?= e($st) ?>
                </span>
                <?php if ($st !== 'Paid' && !empty($adm['failure_reason'])): ?>
                  <small style="display:block; color:#b91c1c; max-width:190px; margin-top:2px;"><?= e($adm['failure_reason']) ?></small>
                <?php endif; ?>
              </td>
              <td style="font-size:0.8rem; color:#64748b;">
                <?= $adm['paid_at'] ? format_date($adm['paid_at']) : format_date($adm['created_at']) ?>
              </td>
              <td style="text-align:right; white-space:nowrap;">
                <a href="<?= APP_URL ?>/admin/admissions.php?search=<?= urlencode($adm['application_ref']) ?>" style="background:#f1f5f9; color:#0f2c59; border:1px solid #cbd5e1; padding:3px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; text-decoration:none; margin-right:4px;">
                  Review
                </a>
                <?php if (($adm['payment_status'] ?? '') === 'Paid'): ?>
                  <a href="<?= APP_URL ?>/admission-document.php?id=<?= (int)$adm['id'] ?>&amp;doc=application&amp;action=view" target="_blank" style="background:#eef2ff; color:#1e40af; border:1px solid #c7d2fe; padding:3px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; text-decoration:none; margin-right:4px;">
                    <i class="fas fa-id-card"></i> Card
                  </a>
                  <a href="<?= APP_URL ?>/admission-document.php?id=<?= (int)$adm['id'] ?>&amp;doc=receipt&amp;action=view" target="_blank" style="background:#eff6ff; color:#0284c7; padding:3px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; text-decoration:none; margin-right:4px;">
                    <i class="fas fa-receipt"></i> Receipt
                  </a>
                <?php else: ?>
                  <span style="font-size:0.74rem; color:#94a3b8; margin-right:4px;" title="Application Card is locked until payment is verified">
                    <i class="fas fa-lock"></i> Locked
                  </span>
                <?php endif; ?>
                <?php $slipUrl = get_payment_screenshot_url($adm['payment_screenshot'] ?? null); ?>
                <?php if ($slipUrl && ($adm['payment_status'] ?? '') === 'Paid'): ?>
                  <a href="<?= e($slipUrl) ?>" target="_blank" style="background:#f0fdf4; color:#15803d; border:1px solid #86efac; padding:3px 7px; border-radius:4px; font-size:0.75rem; font-weight:700; text-decoration:none; margin-right:4px;" title="Gateway Slip SVG">
                    <i class="fas fa-file-image"></i>
                  </a>
                <?php endif; ?>

                <?php if (in_array($adm['payment_status'], ['Pending', 'Under Verification', 'Failed'], true)): ?>
                  <form action="" method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="type" value="admission">
                    <input type="hidden" name="id" value="<?= (int)$adm['id'] ?>">
                    <input type="hidden" name="return_tab" value="<?= e($tab) ?>">
                    <input type="hidden" name="return_status" value="<?= e($filterStatus) ?>">
                    <input type="hidden" name="return_search" value="<?= e($search) ?>">
                    <button type="submit" style="background:#16a34a; color:#fff; border:none; padding:3px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; cursor:pointer;">
                      ✓ Verify
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- 2. Tuition Fee Payments -->
        <?php if ($tab === 'all' || $tab === 'tuition'): ?>
          <?php foreach ($tuitionPayments as $tf): ?>
            <tr>
              <td>
                <span style="font-size:0.72rem; font-weight:700; color:#059669; background:#d1fae5; padding:1px 6px; border-radius:4px;">SEMESTER TUITION</span>
                <strong style="display:block; color:#0f172a; margin-top:2px;"><?= e($tf['st_name']) ?></strong>
                <small style="font-family:monospace; color:#1e40af; font-weight:700;"><?= e($tf['st_code']) ?></small>
                <?php if (!empty($tf['st_app_no']) || !empty($tf['st_roll'])): ?>
                  <small style="font-family:monospace; color:#475569; display:block;">
                    <?= !empty($tf['st_app_no']) ? 'App: ' . e($tf['st_app_no']) : '' ?>
                    <?= (!empty($tf['st_app_no']) && !empty($tf['st_roll'])) ? ' &bull; ' : '' ?>
                    <?= !empty($tf['st_roll']) ? 'Roll: ' . e($tf['st_roll']) : '' ?>
                  </small>
                <?php endif; ?>
              </td>
              <td>
                <div><?= e($tf['fee_title']) ?></div>
                <small style="color:#64748b;">Voucher #<?= str_pad((string)$tf['id'], 6, '0', STR_PAD_LEFT) ?></small>
              </td>
              <td>
                <strong style="color:#15803d; font-size:0.95rem;">৳ <?= number_format((float)$tf['amount'], 2) ?></strong>
              </td>
              <td>
                <span style="font-family:monospace; color:#b91c1c; font-weight:700;"><?= e($tf['transaction_id'] ?? '—') ?></span>
              </td>
              <td>
                <?php
                  $tst = $tf['status'];
                  $tbadgeBg = ($tst === 'Paid') ? '#dcfce7; color:#15803d;' : (($tst === 'Pending') ? '#fef3c7; color:#b45309;' : '#fee2e2; color:#b91c1c;');
                ?>
                <span style="padding:2px 8px; border-radius:12px; font-weight:700; font-size:0.75rem; background:<?= $tbadgeBg ?>">
                  <?= e($tst) ?>
                </span>
              </td>
              <td style="font-size:0.8rem; color:#64748b;">
                <?= $tf['paid_at'] ? format_date($tf['paid_at']) : format_date($tf['created_at']) ?>
              </td>
              <td style="text-align:right; white-space:nowrap;">
                <?php if (in_array($tf['status'], ['Pending', 'Under Verification', 'Failed'], true)): ?>
                  <form action="" method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="type" value="tuition">
                    <input type="hidden" name="id" value="<?= (int)$tf['id'] ?>">
                    <input type="hidden" name="return_tab" value="<?= e($tab) ?>">
                    <input type="hidden" name="return_status" value="<?= e($filterStatus) ?>">
                    <input type="hidden" name="return_search" value="<?= e($search) ?>">
                    <button type="submit" style="background:#16a34a; color:#fff; border:none; padding:3px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; cursor:pointer;">
                      ✓ Verify
                    </button>
                  </form>
                  <?php if ($tf['status'] !== 'Failed'): ?>
                    <form action="" method="POST" style="display:inline; margin-left:4px;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="mark_failed">
                      <input type="hidden" name="type" value="tuition">
                      <input type="hidden" name="id" value="<?= (int)$tf['id'] ?>">
                      <input type="hidden" name="return_tab" value="<?= e($tab) ?>">
                      <input type="hidden" name="return_status" value="<?= e($filterStatus) ?>">
                      <input type="hidden" name="return_search" value="<?= e($search) ?>">
                      <button type="submit" style="background:#fee2e2; color:#b91c1c; border:none; padding:3px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; cursor:pointer;">
                        Reject
                      </button>
                    </form>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="color:#64748b; font-size:0.75rem;">Verified</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

