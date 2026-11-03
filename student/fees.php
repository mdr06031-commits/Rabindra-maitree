<?php
/**
 * Student Portal - Tuition Fees & Payment History
 */
$portalTitle = 'Tuition Fees & Payments';
require_once __DIR__ . '/includes/student-header.php';

$db = getDb();
$studentId = $student['id'];

// Handle payment operations
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? (isset($_POST['pay_fee_id']) ? 'pay_existing' : '');

        // 1. Pay existing fee voucher
        if ($action === 'pay_existing' || isset($_POST['pay_fee_id'])) {
            $payFeeId = (int)($_POST['pay_fee_id'] ?? 0);
            $outcome = in_array($_POST['payment_status'] ?? '', ['Paid', 'Pending', 'Failed']) ? $_POST['payment_status'] : 'Paid';
            $method = trim($_POST['payment_method'] ?? 'bKash');
            $sender = trim($_POST['sender_number'] ?? '');

            if ($outcome === 'Paid') {
                $txnId = 'RMU-TXN-' . strtoupper(bin2hex(random_bytes(4)));
                $stmt = $db->prepare("
                  UPDATE student_fees 
                  SET status = 'Paid', transaction_id = ?, paid_at = NOW() 
                  WHERE id = ? AND student_id = ?
                ");
                $stmt->execute([$txnId, $payFeeId, $studentId]);
                flash_set('success', "Payment successfully completed via {$method}! Transaction ID: {$txnId}");
            } elseif ($outcome === 'Pending') {
                $userTrx = trim($_POST['transaction_id'] ?? '');
                $txnId = !empty($userTrx) ? $userTrx : ('RMU-TXN-' . strtoupper(bin2hex(random_bytes(4))));
                $stmt = $db->prepare("
                  UPDATE student_fees 
                  SET status = 'Pending', transaction_id = ?, paid_at = NULL 
                  WHERE id = ? AND student_id = ?
                ");
                $stmt->execute([$txnId, $payFeeId, $studentId]);
                flash_set('info', "Payment submitted and is pending accounts verification. Reference: {$txnId}");
            } else {
                $failTxn = 'RMU-FAIL-' . strtoupper(bin2hex(random_bytes(3)));
                $stmt = $db->prepare("
                  UPDATE student_fees 
                  SET status = 'Failed', transaction_id = ?, paid_at = NULL 
                  WHERE id = ? AND student_id = ?
                ");
                $stmt->execute([$failTxn, $payFeeId, $studentId]);
                flash_set('error', "Payment gateway transaction failed. The record is tracked as Failed in your history. Reference: {$failTxn}");
            }

            header("Location: " . APP_URL . "/student/fees.php");
            exit;
        }

        // 2. Make new online tuition / semester payment
        elseif ($action === 'new_payment') {
            $title = trim($_POST['fee_title'] ?? 'Semester Tuition Fee');
            $amount = (float)($_POST['amount'] ?? 0);
            $outcome = in_array($_POST['payment_status'] ?? '', ['Paid', 'Pending', 'Failed']) ? $_POST['payment_status'] : 'Paid';
            $method = trim($_POST['payment_method'] ?? 'bKash');

            if ($amount > 0 && !empty($title)) {
                if ($outcome === 'Paid') {
                    $txnId = 'RMU-TXN-' . strtoupper(bin2hex(random_bytes(4)));
                    $stmt = $db->prepare("
                      INSERT INTO student_fees 
                      (student_id, fee_title, amount, due_date, status, transaction_id, paid_at)
                      VALUES (?, ?, ?, CURDATE(), 'Paid', ?, NOW())
                    ");
                    $stmt->execute([$studentId, $title, $amount, $txnId]);
                    flash_set('success', "Payment of ৳" . number_format($amount, 2) . " successfully completed via {$method}! Transaction ID: {$txnId}");
                } elseif ($outcome === 'Pending') {
                    $userTrx = trim($_POST['transaction_id'] ?? '');
                    $txnId = !empty($userTrx) ? $userTrx : ('RMU-TXN-' . strtoupper(bin2hex(random_bytes(4))));
                    $stmt = $db->prepare("
                      INSERT INTO student_fees 
                      (student_id, fee_title, amount, due_date, status, transaction_id, paid_at)
                      VALUES (?, ?, ?, CURDATE(), 'Pending', ?, NULL)
                    ");
                    $stmt->execute([$studentId, $title, $amount, $txnId]);
                    flash_set('info', "Payment of ৳" . number_format($amount, 2) . " submitted for verification. Reference: {$txnId}");
                } else {
                    $failTxn = 'RMU-FAIL-' . strtoupper(bin2hex(random_bytes(3)));
                    $stmt = $db->prepare("
                      INSERT INTO student_fees 
                      (student_id, fee_title, amount, due_date, status, transaction_id, paid_at)
                      VALUES (?, ?, ?, CURDATE(), 'Failed', ?, NULL)
                    ");
                    $stmt->execute([$studentId, $title, $amount, $failTxn]);
                    flash_set('error', "Payment gateway transaction failed. The record is tracked as Failed in your history. Reference: {$failTxn}");
                }

                header("Location: " . APP_URL . "/student/fees.php");
                exit;
            } else {
                flash_set('error', 'Please enter a valid payment description and amount.');
            }
        }
    } else {
        flash_set('error', 'Security token expired. Please retry.');
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

    <div style="display:flex; gap:0.6rem; align-items:center;">
      <button type="button" onclick="document.getElementById('onlinePaymentCard').scrollIntoView({behavior:'smooth'});" style="background:#16a34a; color:#ffffff; border:none; padding:0.55rem 1.1rem; border-radius:6px; font-weight:700; font-size:0.85rem; cursor:pointer; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 6px rgba(22,163,74,0.25);">
        <i class="fas fa-credit-card"></i> Pay Fee Online
      </button>
      <button type="button" onclick="window.print()" class="sp-btn-back-website" style="cursor:pointer;">
        <i class="fas fa-print"></i> Print Statement
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
            <?php if (empty($fees)): ?>
              <tr>
                <td colspan="7" style="text-align:center; padding:3rem 1.5rem; background:#ffffff;">
                  <div style="width:56px; height:56px; margin:0 auto 0.75rem; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:1.5rem;">
                    <i class="fas fa-receipt"></i>
                  </div>
                  <h4 style="margin:0 0 0.35rem; font-size:1.05rem; font-weight:700; color:#1e293b;">No Tuition or Fee Invoices Found</h4>
                  <p style="margin:0; font-size:0.85rem; color:#64748b;">No fees or payment records are currently associated with your student account.</p>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($fees as $f): ?>
                <tr>
                  <td>
                    <strong style="color:#0f172a; font-size:0.9rem; display:block;"><?= e($f['fee_title']) ?></strong>
                    <small style="color:#64748b; font-size:0.76rem;">Voucher #<?= str_pad((string)$f['id'], 6, '0', STR_PAD_LEFT) ?></small>
                  </td>
                  <td style="font-size:0.85rem; color:#475569;"><?= date('M d, Y', strtotime($f['due_date'])) ?></td>
                  <td><strong style="color:#0f172a; font-size:0.95rem;">৳<?= number_format((float)$f['amount'], 2) ?></strong></td>
                  <td>
                    <?php if ($f['status'] === 'Paid'): ?>
                      <span class="badge-paid" style="background:#dcfce7; color:#15803d; font-weight:700; padding:3px 9px; border-radius:12px; font-size:0.75rem; display:inline-flex; align-items:center; gap:4px;">
                        <i class="fas fa-check-circle"></i> Paid
                      </span>
                    <?php elseif ($f['status'] === 'Pending'): ?>
                      <span style="background:#fef3c7; color:#b45309; font-weight:700; padding:3px 9px; border-radius:12px; font-size:0.75rem; display:inline-flex; align-items:center; gap:4px;">
                        <i class="fas fa-clock"></i> Pending
                      </span>
                    <?php elseif ($f['status'] === 'Failed'): ?>
                      <span style="background:#fee2e2; color:#b91c1c; font-weight:700; padding:3px 9px; border-radius:12px; font-size:0.75rem; display:inline-flex; align-items:center; gap:4px;">
                        <i class="fas fa-times-circle"></i> Failed
                      </span>
                    <?php else: ?>
                      <span style="background:#f1f5f9; color:#475569; font-weight:700; padding:3px 9px; border-radius:12px; font-size:0.75rem; display:inline-flex; align-items:center; gap:4px;">
                        <i class="fas fa-hourglass-start"></i> Due
                      </span>
                    <?php endif; ?>
                  </td>
                  <td style="font-family:monospace; font-size:0.82rem; color:<?= !empty($f['transaction_id']) ? '#0369a1' : '#94a3b8' ?>; font-weight:600;">
                    <?= e($f['transaction_id'] ?? '—') ?>
                  </td>
                  <td style="font-size:0.82rem; color:#64748b;">
                    <?= (!empty($f['paid_at']) && $f['status'] === 'Paid') ? date('M d, Y h:i A', strtotime($f['paid_at'])) : '—' ?>
                  </td>
                  <td class="no-print" style="text-align:center; white-space:nowrap;">
                    <?php if ($f['status'] === 'Paid'): ?>
                      <button type="button" onclick="window.print()" style="background:#eff6ff; border:1px solid #bfdbfe; color:#1e73be; padding:4px 10px; border-radius:4px; font-size:0.78rem; font-weight:600; cursor:pointer;">
                        <i class="fas fa-receipt"></i> Voucher
                      </button>
                    <?php else: ?>
                      <form action="<?= APP_URL ?>/student/fees.php" method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="pay_existing">
                        <input type="hidden" name="pay_fee_id" value="<?= (int)$f['id'] ?>">
                        <input type="hidden" name="payment_status" value="Paid">
                        <button type="submit" style="background:#16a34a; border:none; color:#fff; padding:4px 12px; border-radius:4px; font-size:0.78rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px;">
                          <i class="fas fa-wallet"></i> <?= ($f['status'] === 'Failed') ? 'Retry Pay' : 'Pay Online' ?>
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Instant Online Tuition Fee Payment Card -->
  <div class="sp-card no-print" id="onlinePaymentCard">
    <div class="sp-card-header">
      <h3><i class="fas fa-credit-card" style="color:var(--sp-primary);"></i> Make Online Tuition &amp; Semester Fee Payment</h3>
    </div>
    <div class="sp-card-body">
      <form action="<?= APP_URL ?>/student/fees.php" method="POST" style="max-width:850px;">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="action" value="new_payment">

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1.25rem; margin-bottom:1.25rem;">
          <div>
            <label style="display:block; font-size:0.82rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Fee Description / Purpose *</label>
            <input type="text" name="fee_title" list="feeDescriptions" placeholder="e.g. Fall 2026 Semester Tuition Fee" class="form-control" style="width:100%; padding:0.5rem 0.75rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.88rem;" required>
            <datalist id="feeDescriptions">
              <option value="Fall 2026 Semester Tuition Fee">
              <option value="Spring 2027 Semester Tuition Fee">
              <option value="Semester Mid-Term Examination Fee">
              <option value="Semester Final Examination Fee">
              <option value="Computer Lab & Internet Development Fee">
              <option value="Library Access & Activity Fee">
            </datalist>
          </div>

          <div>
            <label style="display:block; font-size:0.82rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Amount (BDT ৳) *</label>
            <input type="number" step="100" min="100" name="amount" placeholder="e.g. 34500" class="form-control" style="width:100%; padding:0.5rem 0.75rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.88rem;" required>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
          <div>
            <label style="display:block; font-size:0.82rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Payment Gateway / Method *</label>
            <select name="payment_method" style="width:100%; padding:0.5rem 0.75rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.88rem; background:#fff; font-weight:600;">
              <option value="bKash">bKash (01302991188)</option>
              <option value="Nagad">Nagad (01302991188)</option>
              <option value="DBBL Rocket">DBBL Rocket</option>
              <option value="Visa / Mastercard">Visa / Mastercard / NexusPay</option>
            </select>
          </div>

          <div>
            <label style="display:block; font-size:0.82rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Payment Processing Status</label>
            <select name="payment_status" style="width:100%; padding:0.5rem 0.75rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.88rem; background:#fff; font-weight:600;">
              <option value="Paid">Instant Complete (Paid)</option>
              <option value="Pending">Submit for Accounts Verification (Pending)</option>
              <option value="Failed">Simulate Transaction Failure (Failed)</option>
            </select>
          </div>
        </div>

        <button type="submit" style="background:#16a34a; color:#ffffff; border:none; padding:0.65rem 1.4rem; border-radius:6px; font-size:0.9rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 2px 6px rgba(22,163,74,0.3);">
          <i class="fas fa-lock"></i> Process Secure Online Payment
        </button>
      </form>
    </div>
  </div>
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
