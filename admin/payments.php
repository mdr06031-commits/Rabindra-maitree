<?php
/**
 * Admin Panel: Payment Management
 * Rabindra Maitree University (RMU)
 * Tracks Admission Fees (৳150) and Student Semester Tuition Fees with sandbox/live gateway auditing
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Payment Management';
$db = getDb();

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
                $trx = trim($_POST['transaction_id'] ?? ('RMU-TRX-' . rand(100000, 999999)));
                $db->prepare("UPDATE admissions SET payment_status = 'Paid', transaction_id = ?, paid_at = NOW(), status = 'Under Review' WHERE id = ?")->execute([$trx, $id]);
                log_audit('PAYMENT_VERIFIED_ADMISSION', "Verified ৳150 admission fee for application ID #{$id}");
                flash_set('success', "Admission application fee verified successfully.");
            } elseif ($action === 'mark_failed') {
                $db->prepare("UPDATE admissions SET payment_status = 'Failed' WHERE id = ?")->execute([$id]);
                log_audit('PAYMENT_FAILED_ADMISSION', "Marked admission payment as Failed for application ID #{$id}");
                flash_set('warning', "Payment marked as Failed.");
            } elseif ($action === 'refund') {
                $db->prepare("UPDATE admissions SET payment_status = 'Refunded' WHERE id = ?")->execute([$id]);
                log_audit('PAYMENT_REFUNDED_ADMISSION', "Refunded admission fee for application ID #{$id}");
                flash_set('info', "Payment marked as Refunded.");
            }
        } elseif ($type === 'tuition' && $id > 0) {
            if ($action === 'verify') {
                $trx = trim($_POST['transaction_id'] ?? ('RMU-TXN-' . rand(100000, 999999)));
                $db->prepare("UPDATE student_fees SET status = 'Paid', transaction_id = ?, paid_at = NOW() WHERE id = ?")->execute([$trx, $id]);
                log_audit('PAYMENT_VERIFIED_TUITION', "Verified semester tuition fee for fee voucher ID #{$id}");
                flash_set('success', "Semester fee voucher marked as Paid.");
            } elseif ($action === 'mark_failed') {
                $db->prepare("UPDATE student_fees SET status = 'Failed' WHERE id = ?")->execute([$id]);
                flash_set('warning', "Tuition fee payment marked as Failed.");
            }
        }
    }
    header("Location: " . APP_URL . "/admin/payments.php");
    exit;
}

// Active tab ('all', 'admissions', 'tuition')
$tab = trim($_GET['tab'] ?? 'all');
$filterStatus = trim($_GET['status'] ?? 'All');
$search = trim($_GET['search'] ?? '');

// Metrics
$totalAdmPaid = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM admissions WHERE payment_status = 'Paid'")->fetchColumn();
$totalTuitionPaid = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM student_fees WHERE status = 'Paid'")->fetchColumn();
$totalPendingCount = (int)$db->query("SELECT (SELECT COUNT(*) FROM admissions WHERE payment_status = 'Pending') + (SELECT COUNT(*) FROM student_fees WHERE status = 'Pending')")->fetchColumn();

// Fetch Admission Payments
$admSql = "SELECT id, application_ref, full_name, mobile, payment_status, payment_amount, payment_method, transaction_id, sender_number, payment_screenshot, paid_at, created_at FROM admissions WHERE 1=1";
$admParams = [];
if ($filterStatus !== 'All') {
    $admSql .= " AND payment_status = ?";
    $admParams[] = $filterStatus;
}
if (!empty($search)) {
    $admSql .= " AND (application_ref LIKE ? OR full_name LIKE ? OR transaction_id LIKE ? OR mobile LIKE ?)";
    $like = "%$search%";
    $admParams = array_merge($admParams, [$like, $like, $like, $like]);
}
$admSql .= " ORDER BY id DESC LIMIT 50";
$admStmt = $db->prepare($admSql);
$admStmt->execute($admParams);
$admissionPayments = $admStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Tuition Fee Payments
$feeSql = "SELECT sf.*, s.student_id as st_code, s.full_name as st_name FROM student_fees sf JOIN students s ON sf.student_id = s.id WHERE 1=1";
$feeParams = [];
if ($filterStatus !== 'All') {
    $feeSql .= " AND sf.status = ?";
    $feeParams[] = $filterStatus;
}
if (!empty($search)) {
    $feeSql .= " AND (s.student_id LIKE ? OR s.full_name LIKE ? OR sf.transaction_id LIKE ? OR sf.fee_title LIKE ?)";
    $like = "%$search%";
    $feeParams = array_merge($feeParams, [$like, $like, $like, $like]);
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
</div>

<!-- KPI Cards -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1.25rem; margin-bottom:2rem;">
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
  <form method="GET" action="" style="display:grid; grid-template-columns:1.5fr 1fr 1fr auto; gap:1rem; align-items:flex-end;">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">

    <div>
      <label style="font-size:0.8rem; font-weight:700; color:#0f2c59; display:block; margin-bottom:4px;">Search Application / Student / TrxID:</label>
      <input type="text" name="search" value="<?= e($search) ?>" placeholder="e.g. RMU26... or TRX-BK..." class="form-control" style="font-size:0.88rem;">
    </div>

    <div>
      <label style="font-size:0.8rem; font-weight:700; color:#0f2c59; display:block; margin-bottom:4px;">Payment Status:</label>
      <select name="status" class="form-select" style="font-size:0.88rem;">
        <option value="All" <?= ($filterStatus === 'All') ? 'selected' : '' ?>>All Statuses</option>
        <option value="Paid" <?= ($filterStatus === 'Paid') ? 'selected' : '' ?>>Paid (Verified)</option>
        <option value="Pending" <?= ($filterStatus === 'Pending') ? 'selected' : '' ?>>Pending Verification</option>
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
                <small style="font-family:monospace; color:#1e40af;"><?= e($adm['application_ref']) ?></small>
              </td>
              <td>
                <div>৳150 Online Admission Fee</div>
                <small style="color:#64748b;"><?= e($adm['payment_method'] ?? 'bKash') ?> &bull; Sender: <?= e($adm['sender_number'] ?? 'N/A') ?></small>
              </td>
              <td>
                <strong style="color:#15803d; font-size:0.95rem;">৳ 150.00</strong>
              </td>
              <td>
                <span style="font-family:monospace; color:#b91c1c; font-weight:700;"><?= e($adm['transaction_id'] ?? '—') ?></span>
              </td>
              <td>
                <?php
                  $st = $adm['payment_status'];
                  $badgeBg = ($st === 'Paid') ? '#dcfce7; color:#15803d;' : (($st === 'Pending') ? '#fef3c7; color:#b45309;' : '#fee2e2; color:#b91c1c;');
                ?>
                <span style="padding:2px 8px; border-radius:12px; font-weight:700; font-size:0.75rem; background:<?= $badgeBg ?>">
                  <?= e($st) ?>
                </span>
              </td>
              <td style="font-size:0.8rem; color:#64748b;">
                <?= $adm['paid_at'] ? format_date($adm['paid_at']) : format_date($adm['created_at']) ?>
              </td>
              <td style="text-align:right; white-space:nowrap;">
                <?php $slipUrl = get_payment_screenshot_url($adm['payment_screenshot'] ?? null); ?>
                <?php if ($slipUrl): ?>
                  <a href="<?= e($slipUrl) ?>" target="_blank" style="background:#eff6ff; color:#0284c7; padding:3px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; text-decoration:none; margin-right:4px;">
                    <i class="fas fa-receipt"></i> Receipt
                  </a>
                <?php endif; ?>

                <?php if ($adm['payment_status'] === 'Pending'): ?>
                  <form action="" method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="type" value="admission">
                    <input type="hidden" name="id" value="<?= (int)$adm['id'] ?>">
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
                <small style="font-family:monospace; color:#1e40af;"><?= e($tf['st_code']) ?></small>
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
                <?php if ($tf['status'] === 'Pending'): ?>
                  <form action="" method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="type" value="tuition">
                    <input type="hidden" name="id" value="<?= (int)$tf['id'] ?>">
                    <button type="submit" style="background:#16a34a; color:#fff; border:none; padding:3px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; cursor:pointer;">
                      ✓ Verify
                    </button>
                  </form>
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

