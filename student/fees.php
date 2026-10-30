<?php
/**
 * Student Portal - Tuition Fees & Payment History
 */
$portalTitle = 'Tuition Fees & Payments';
require_once __DIR__ . '/includes/student-header.php';

$db = getDb();
$studentId = $student['id'];

// Handle payment simulation
$paymentSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_fee_id'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $payFeeId = (int)$_POST['pay_fee_id'];
        $txnId = 'RMU-TXN-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $db->prepare("
          UPDATE student_fees 
          SET status = 'Paid', transaction_id = ?, paid_at = NOW() 
          WHERE id = ? AND student_id = ?
        ");
        $stmt->execute([$txnId, $payFeeId, $studentId]);
        flash_set('success', "Payment successfully completed! Transaction ID: {$txnId}");
        header("Location: " . APP_URL . "/student/fees.php");
        exit;
    }
}

// Retrieve all fees
$stmt = $db->prepare("
  SELECT * FROM student_fees 
  WHERE student_id = ? 
  ORDER BY id DESC
");
$stmt->execute([$studentId]);
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPaid = 0;
$totalDue = 0;
foreach ($fees as $f) {
    if ($f['status'] === 'Paid') {
        $totalPaid += (float)$f['amount'];
    } else {
        $totalDue += (float)$f['amount'];
    }
}
$csrfToken = csrf_token();
?>

<div class="sp-main-container">

  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0;" class="no-print">
    <div>
      <h2 style="margin:0 0 0.3rem; font-size:1.45rem; font-weight:700; color:#0f172a;">
        <i class="fas fa-receipt" style="color:#16a34a;"></i> Student Accounts &amp; Tuition Fees
      </h2>
      <p style="margin:0; font-size:0.88rem; color:#64748b;">
        Student ID: <strong><?= e($student['student_id']) ?></strong> • Total Paid to Date: <strong>৳<?= number_format($totalPaid, 2) ?></strong>
      </p>
    </div>

    <div>
      <button type="button" onclick="window.print()" class="sp-btn-back-website" style="cursor:pointer;">
        <i class="fas fa-print"></i> Print Account Statement
      </button>
    </div>
  </div>

  <!-- Accounts Summary Grid -->
  <div class="sp-kpi-grid no-print">
    <div class="sp-kpi-card">
      <div class="sp-kpi-icon" style="background:#f0fdf4; color:#16a34a;">
        <i class="fas fa-check-circle"></i>
      </div>
      <div>
        <div class="sp-kpi-val" style="color:#16a34a;">৳<?= number_format($totalPaid, 2) ?></div>
        <div class="sp-kpi-label">Total Fees Cleared</div>
      </div>
    </div>

    <div class="sp-kpi-card">
      <div class="sp-kpi-icon" style="background:<?= ($totalDue > 0) ? '#fef2f2' : '#f8fafc' ?>; color:<?= ($totalDue > 0) ? '#dc2626' : '#64748b' ?>;">
        <i class="fas fa-exclamation-circle"></i>
      </div>
      <div>
        <div class="sp-kpi-val" style="color:<?= ($totalDue > 0) ? '#dc2626' : '#64748b' ?>;">৳<?= number_format($totalDue, 2) ?></div>
        <div class="sp-kpi-label">Current Outstanding Dues</div>
      </div>
    </div>

    <div class="sp-kpi-card">
      <div class="sp-kpi-icon" style="background:#eff6ff; color:#1e73be;">
        <i class="fas fa-shield-alt"></i>
      </div>
      <div>
        <div class="sp-kpi-val" style="font-size:1.2rem; color:#16a34a;">
          <i class="fas fa-check-double"></i> Cleared
        </div>
        <div class="sp-kpi-label">Exam Clearance Status</div>
      </div>
    </div>
  </div>

  <!-- Fees Table Card -->
  <div class="sp-card">
    <div class="sp-card-header">
      <h3><i class="fas fa-file-invoice-dollar" style="color:var(--sp-primary);"></i> Tuition &amp; Fee Vouchers</h3>
    </div>
    <div class="sp-card-body" style="padding:0;">
      <div style="overflow-x:auto;">
        <table class="sp-table">
          <thead>
            <tr>
              <th>Voucher / Fee Description</th>
              <th>Due Date</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Transaction ID</th>
              <th>Paid At</th>
              <th class="no-print" style="text-align:center;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($fees as $f): ?>
              <tr>
                <td>
                  <strong><?= e($f['fee_title']) ?></strong><br>
                  <small style="color:#64748b;">Voucher #<?= str_pad((string)$f['id'], 6, '0', STR_PAD_LEFT) ?></small>
                </td>
                <td><?= date('M d, Y', strtotime($f['due_date'])) ?></td>
                <td><strong style="color:#0f172a; font-size:0.95rem;">৳<?= number_format((float)$f['amount'], 2) ?></strong></td>
                <td>
                  <?php if ($f['status'] === 'Paid'): ?>
                    <span class="badge-paid"><i class="fas fa-check"></i> Paid</span>
                  <?php else: ?>
                    <span style="background:#fee2e2; color:#b91c1c; font-weight:700; padding:3px 9px; border-radius:12px; font-size:0.75rem;">Due</span>
                  <?php endif; ?>
                </td>
                <td style="font-family:monospace; font-size:0.8rem; color:#64748b;"><?= e($f['transaction_id'] ?? '—') ?></td>
                <td style="font-size:0.82rem; color:#64748b;"><?= !empty($f['paid_at']) ? date('M d, Y h:i A', strtotime($f['paid_at'])) : '—' ?></td>
                <td class="no-print" style="text-align:center;">
                  <?php if ($f['status'] === 'Paid'): ?>
                    <button type="button" onclick="window.print()" style="background:#eff6ff; border:1px solid #bfdbfe; color:#1e73be; padding:4px 10px; border-radius:4px; font-size:0.78rem; font-weight:600; cursor:pointer;">
                      <i class="fas fa-receipt"></i> Receipt
                    </button>
                  <?php else: ?>
                    <form action="<?= APP_URL ?>/student/fees.php" method="POST" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                      <input type="hidden" name="pay_fee_id" value="<?= (int)$f['id'] ?>">
                      <button type="submit" style="background:#16a34a; border:none; color:#fff; padding:4px 12px; border-radius:4px; font-size:0.78rem; font-weight:700; cursor:pointer;">
                        Pay Online
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Online Payment Channels Reference -->
  <div class="sp-card no-print">
    <div class="sp-card-header">
      <h3><i class="fas fa-wallet" style="color:var(--sp-primary);"></i> Authorized Online Payment Methods</h3>
    </div>
    <div class="sp-card-body">
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
        <div style="background:#fdf2f8; border:1px solid #fbcfe8; padding:1rem; border-radius:8px;">
          <h4 style="margin:0 0 0.3rem; color:#be185d;"><i class="fas fa-mobile-alt"></i> bKash Merchant Payment</h4>
          <p style="margin:0; font-size:0.8rem; color:#64748b;">Merchant A/C: <strong>01302991188</strong> (Use Student ID as reference)</p>
        </div>
        <div style="background:#fff7ed; border:1px solid #fed7aa; padding:1rem; border-radius:8px;">
          <h4 style="margin:0 0 0.3rem; color:#c2410c;"><i class="fas fa-mobile-alt"></i> Nagad Online Gateway</h4>
          <p style="margin:0; font-size:0.8rem; color:#64748b;">Merchant A/C: <strong>01302991188</strong> (Enter 11-digit Student ID)</p>
        </div>
        <div style="background:#eff6ff; border:1px solid #bfdbfe; padding:1rem; border-radius:8px;">
          <h4 style="margin:0 0 0.3rem; color:#1d4ed8;"><i class="fas fa-university"></i> Bank Direct Transfer</h4>
          <p style="margin:0; font-size:0.8rem; color:#64748b;">Dutch-Bangla Bank Ltd, Kushtia Branch. A/C: 115-120-998822</p>
        </div>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/student-footer.php'; ?>
