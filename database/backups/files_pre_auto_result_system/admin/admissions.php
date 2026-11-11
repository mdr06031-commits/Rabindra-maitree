<?php
/**
 * Admin Panel: Comprehensive Admission Management & Review Console
 * Rabindra Maitree University (RMU)
 * Full Dossier Inspection, Document Verification, ৳150 Payment Auditing & Auto-Provisioning
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Admission Management';
$db = getDb();
$currentAdmin = current_user();
$adminId = (int)($currentAdmin['id'] ?? ($_SESSION['user_id'] ?? 1));

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'accept_application' && $id > 0) {
            $notes = trim($_POST['admin_notes'] ?? 'Application accepted by admissions committee.');
            $upd = $db->prepare("UPDATE admissions SET status = 'Accepted', admin_notes = ? WHERE id = ?");
            $upd->execute([$notes, $id]);

            // Provision student account
            $chk = $db->prepare("SELECT student_id FROM admissions WHERE id = ?");
            $chk->execute([$id]);
            $existingStId = $chk->fetchColumn();
            if (empty($existingStId)) {
                $prov = provision_student_from_admission($id);
                $stId = $prov['student_id'] ?? 'Generated';
                log_audit('ADMISSION_ACCEPTED', "Accepted application #{$id} and provisioned Student ID {$stId}");
                flash_set('success', "Application #{$id} accepted! Student account created (ID: {$stId}).");
            } else {
                log_audit('ADMISSION_ACCEPTED', "Accepted application #{$id} (Student ID {$existingStId} already exists)");
                flash_set('success', "Application #{$id} status updated to Accepted.");
            }
        } elseif ($action === 'reject_application' && $id > 0) {
            $notes = trim($_POST['admin_notes'] ?? 'Application rejected.');
            $upd = $db->prepare("UPDATE admissions SET status = 'Rejected', admin_notes = ? WHERE id = ?");
            $upd->execute([$notes, $id]);

            log_audit('ADMISSION_REJECTED', "Rejected application #{$id}. Notes: {$notes}");
            flash_set('warning', "Application #{$id} has been marked as Rejected.");
        } elseif ($action === 'pending_application' && $id > 0) {
            $notes = trim($_POST['admin_notes'] ?? 'Application kept under review.');
            $upd = $db->prepare("UPDATE admissions SET status = 'Under Review', admin_notes = ? WHERE id = ?");
            $upd->execute([$notes, $id]);

            log_audit('ADMISSION_UNDER_REVIEW', "Application #{$id} marked Under Review");
            flash_set('info', "Application #{$id} is now Under Review.");
        } elseif ($action === 'update_status' && $id > 0) {
            $newStatus = trim($_POST['status'] ?? '');
            $adminNotes = trim($_POST['admin_notes'] ?? '');

            $validStatuses = ['Pending', 'Submitted', 'Under Review', 'Additional Information Required', 'Accepted', 'Rejected'];
            if (in_array($newStatus, $validStatuses, true)) {
                $update = $db->prepare("UPDATE admissions SET status = ?, admin_notes = ? WHERE id = ?");
                $update->execute([$newStatus, $adminNotes, $id]);

                // Auto-provision if Accepted
                if ($newStatus === 'Accepted') {
                    $chk = $db->prepare("SELECT student_id FROM admissions WHERE id = ?");
                    $chk->execute([$id]);
                    if (empty($chk->fetchColumn())) {
                        provision_student_from_admission($id);
                    }
                }

                log_audit('ADMISSION_STATUS_UPDATED', "Updated application ID {$id} to status '{$newStatus}' with notes: '{$adminNotes}'");
                flash_set('success', "Application #{$id} status updated to '{$newStatus}' successfully.");
            } else {
                flash_set('error', 'Invalid status selection.');
            }
        } elseif ($action === 'verify_payment' && $id > 0) {
            $trx = trim($_POST['transaction_id'] ?? 'RMU-VERIFIED-' . rand(10000, 99999));
            $upd = $db->prepare("UPDATE admissions SET payment_status = 'Paid', payment_amount = 150.00, transaction_id = COALESCE(NULLIF(transaction_id, ''), ?), paid_at = NOW(), status = CASE WHEN status = 'Submitted' THEN 'Under Review' ELSE status END WHERE id = ?");
            $upd->execute([$trx, $id]);
            log_audit('ADMISSION_PAYMENT_VERIFIED', "Admin manually verified payment for application ID #{$id} (Trx: {$trx})");
            flash_set('success', "Payment for application #{$id} verified (৳150 Paid). Application is now ready for committee review.");
        } elseif ($action === 'update_payment_status' && $id > 0) {
            $payStatus = in_array($_POST['payment_status'] ?? '', ['Pending', 'Paid', 'Failed', 'Refunded']) ? $_POST['payment_status'] : 'Pending';
            $trx = trim($_POST['transaction_id'] ?? '');
            $paidAt = ($payStatus === 'Paid') ? date('Y-m-d H:i:s') : null;
            $upd = $db->prepare("UPDATE admissions SET payment_status = ?, transaction_id = ?, paid_at = ? WHERE id = ?");
            $upd->execute([$payStatus, $trx ?: null, $paidAt, $id]);
            log_audit('ADMISSION_PAYMENT_STATUS_UPDATED', "Updated payment status of application #{$id} to {$payStatus} (Trx: {$trx})");
            flash_set('success', "Payment status for application #{$id} updated to {$payStatus}.");
        } elseif ($action === 'delete' && $id > 0) {
            $del = $db->prepare("DELETE FROM admissions WHERE id = ?");
            $del->execute([$id]);
            log_audit('ADMISSION_DELETED', "Admin deleted admission application ID #{$id}");
            flash_set('success', "Application #{$id} deleted successfully.");
        }
    }
    header("Location: " . APP_URL . "/admin/admissions.php");
    exit;
}

// Filters & Search
$statusFilter = $_GET['status'] ?? 'All';
$paymentFilter = $_GET['payment_status'] ?? 'All';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($statusFilter !== 'All') {
    $where[] = "a.status = ?";
    $params[] = $statusFilter;
}

if ($paymentFilter !== 'All') {
    $where[] = "a.payment_status = ?";
    $params[] = $paymentFilter;
}

if (!empty($search)) {
    $where[] = "(a.application_ref LIKE ? OR a.full_name LIKE ? OR a.mobile LIKE ? OR a.email LIKE ? OR a.student_id LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// KPI Counts
$totalApps = (int)$db->query("SELECT COUNT(*) FROM admissions")->fetchColumn();
$totalPending = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE status IN ('Submitted', 'Under Review', 'Pending')")->fetchColumn();
$totalPaid = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE payment_status = 'Paid'")->fetchColumn();
$totalAccepted = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE status = 'Accepted'")->fetchColumn();
$totalRejected = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE status = 'Rejected'")->fetchColumn();

// Count Total Filtered
$countStmt = $db->prepare("SELECT COUNT(*) FROM admissions a $whereSql");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();

// Fetch Data with Student profile info
$query = "
    SELECT a.*, s.id AS student_table_id, s.cgpa AS student_cgpa, s.current_semester AS student_sem,
           p.title_en AS program_title, d.name_en AS dept_name
    FROM admissions a 
    LEFT JOIN students s ON a.student_id = s.student_id 
    LEFT JOIN programs p ON a.program_id = p.id
    LEFT JOIN departments d ON a.department_id = d.id
    $whereSql 
    ORDER BY a.id DESC 
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue($k + 1, $v);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$admissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPages = ceil($totalFiltered / $perPage);

include __DIR__ . '/includes/admin-header.php';
?>

<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem;">
      <i class="fas fa-user-graduate" style="color: var(--rmu-primary);"></i> Admission Management & Review
    </h2>
    <span style="color: #64748b; font-size: 0.9rem;">
      Review online admission applications, inspect applicant dossiers, verify ৳150 application fees, and provision student accounts.
    </span>
  </div>
  <div style="display:flex; gap:0.5rem;">
    <a href="<?= APP_URL ?>/admission-form.php" target="_blank" class="btn-primary" style="padding:0.6rem 1.2rem; font-size:0.88rem; background:#0284c7;">
      <i class="fas fa-plus"></i> Open Apply Online Form
    </a>
  </div>
</div>

<!-- KPI Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
  <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem; border-left:4px solid #3b82f6;">
    <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Total Applications</div>
    <div style="font-size:1.8rem; font-weight:800; color:#0f172a; margin-top:0.25rem;"><?= number_format($totalApps) ?></div>
    <small style="color:#64748b;">All time submissions</small>
  </div>
  <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem; border-left:4px solid #f59e0b;">
    <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Pending Review</div>
    <div style="font-size:1.8rem; font-weight:800; color:#b45309; margin-top:0.25rem;"><?= number_format($totalPending) ?></div>
    <small style="color:#64748b;">Awaiting committee action</small>
  </div>
  <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem; border-left:4px solid #10b981;">
    <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Verified Paid (৳150)</div>
    <div style="font-size:1.8rem; font-weight:800; color:#047857; margin-top:0.25rem;"><?= number_format($totalPaid) ?></div>
    <small style="color:#64748b;">৳<?= number_format($totalPaid * 150) ?> collected</small>
  </div>
  <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem; border-left:4px solid #059669;">
    <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Accepted / Provisioned</div>
    <div style="font-size:1.8rem; font-weight:800; color:#059669; margin-top:0.25rem;"><?= number_format($totalAccepted) ?></div>
    <small style="color:#64748b;">Students registered</small>
  </div>
  <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem; border-left:4px solid #ef4444;">
    <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Rejected</div>
    <div style="font-size:1.8rem; font-weight:800; color:#b91c1c; margin-top:0.25rem;"><?= number_format($totalRejected) ?></div>
    <small style="color:#64748b;">Unsuccessful applicants</small>
  </div>
</div>

<!-- Search & Filter Bar -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem 1.5rem; margin-bottom: 2rem;">
  <form action="<?= APP_URL ?>/admin/admissions.php" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
    <div style="flex: 2; min-width: 250px;">
      <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by Application Ref, Candidate Name, Mobile, or Student ID..." class="form-control" style="padding: 0.65rem 1rem;">
    </div>

    <div style="flex: 1; min-width: 170px;">
      <select name="status" class="form-select" style="padding: 0.65rem 1rem;">
        <option value="All" <?= ($statusFilter === 'All') ? 'selected' : '' ?>>All Admission Statuses</option>
        <option value="Submitted" <?= ($statusFilter === 'Submitted') ? 'selected' : '' ?>>Submitted</option>
        <option value="Under Review" <?= ($statusFilter === 'Under Review') ? 'selected' : '' ?>>Under Review</option>
        <option value="Accepted" <?= ($statusFilter === 'Accepted') ? 'selected' : '' ?>>Accepted</option>
        <option value="Additional Information Required" <?= ($statusFilter === 'Additional Information Required') ? 'selected' : '' ?>>Info Required</option>
        <option value="Rejected" <?= ($statusFilter === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
      </select>
    </div>

    <div style="flex: 1; min-width: 160px;">
      <select name="payment_status" class="form-select" style="padding: 0.65rem 1rem;">
        <option value="All" <?= ($paymentFilter === 'All') ? 'selected' : '' ?>>All Payment Statuses</option>
        <option value="Paid" <?= ($paymentFilter === 'Paid') ? 'selected' : '' ?>>Paid (৳150 Verified)</option>
        <option value="Pending" <?= ($paymentFilter === 'Pending') ? 'selected' : '' ?>>Pending Payment</option>
        <option value="Failed" <?= ($paymentFilter === 'Failed') ? 'selected' : '' ?>>Failed</option>
        <option value="Refunded" <?= ($paymentFilter === 'Refunded') ? 'selected' : '' ?>>Refunded</option>
      </select>
    </div>

    <button type="submit" class="btn-primary" style="padding: 0.65rem 1.4rem;">
      <i class="fas fa-filter"></i> Apply Filter
    </button>
    <?php if (!empty($search) || $statusFilter !== 'All' || $paymentFilter !== 'All'): ?>
      <a href="<?= APP_URL ?>/admin/admissions.php" style="color: #ef4444; font-size: 0.88rem; font-weight: 600;">Clear Filters</a>
    <?php endif; ?>
  </form>
</div>

<!-- Applications Table -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
  <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
    <h3 style="margin: 0; font-size: 1.1rem; color: #0f172a; font-weight: 700;">
      Admission Applicants (Showing <?= count($admissions) ?> of <?= $totalFiltered ?> records)
    </h3>
  </div>

  <div class="table-responsive">
    <table class="rmu-table" style="box-shadow: none; margin: 0;">
      <thead>
        <tr>
          <th>Application &bull; Date</th>
          <th>Applicant Particulars</th>
          <th>Academic Background</th>
          <th>Program &amp; Session</th>
          <th>৳150 Fee Status</th>
          <th>Admission Status</th>
          <th style="text-align: right;">Review &amp; Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($admissions)): ?>
          <?php foreach ($admissions as $adm): ?>
            <tr>
              <td>
                <strong style="color: var(--rmu-primary); font-size: 0.95rem; font-family: monospace; display: block;"><?= e($adm['application_ref']) ?></strong>
                <small style="color: #64748b;"><?= format_date($adm['created_at']) ?></small>
                <?php if (!empty($adm['student_id'])): ?>
                  <div style="margin-top:4px;">
                    <span style="background:#e0f2fe; color:#0369a1; font-size:0.75rem; font-weight:700; padding:0.15rem 0.5rem; border-radius:4px; font-family:monospace;">
                      ID: <?= e($adm['student_id']) ?>
                    </span>
                  </div>
                <?php endif; ?>
              </td>

              <td>
                <strong style="color: #0f172a; font-size: 0.95rem; display: block;"><?= e($adm['full_name']) ?></strong>
                <div style="font-size: 0.82rem; color: #334155;"><i class="fas fa-phone-alt"></i> <?= e($adm['mobile']) ?></div>
                <?php if (!empty($adm['email'])): ?>
                  <div style="font-size: 0.78rem; color: #64748b;"><?= e($adm['email']) ?></div>
                <?php endif; ?>
                <?php if (!empty($adm['father_name'])): ?>
                  <div style="font-size: 0.75rem; color: #64748b;">Father: <?= e($adm['father_name']) ?></div>
                <?php endif; ?>
                <div style="font-size: 0.75rem; color: #94a3b8;"><?= e($adm['home_district'] ?? '') ?></div>
              </td>

              <td style="font-size: 0.85rem;">
                <div><strong>SSC:</strong> <?= number_format($adm['ssc_gpa'] ?? 0, 2) ?> <?= !empty($adm['ssc_roll']) ? '(Roll: ' . e($adm['ssc_roll']) . ')' : '' ?></div>
                <div><strong>HSC:</strong> <?= number_format($adm['hsc_gpa'] ?? 0, 2) ?> <?= !empty($adm['hsc_roll']) ? '(Roll: ' . e($adm['hsc_roll']) . ')' : '' ?></div>
                <div style="color: #64748b; font-size: 0.75rem;">Board: <?= e($adm['hsc_board'] ?? 'Dinajpur') ?> &bull; <?= e($adm['hsc_group'] ?? 'Science') ?></div>
                <?php if (!empty($adm['academic_documents'])): ?>
                  <div style="margin-top: 4px;">
                    <a href="<?= APP_URL . '/' . e($adm['academic_documents']) ?>" target="_blank" style="color: #2563eb; font-size: 0.78rem; font-weight: 600; text-decoration: underline;">
                      <i class="fas fa-file-pdf"></i> Academic Docs
                    </a>
                  </div>
                <?php endif; ?>
              </td>

              <td>
                <strong style="color: #0f172a; font-size: 0.88rem; display: block;"><?= e($adm['program_title'] ?? $adm['interested_subject']) ?></strong>
                <div style="font-size: 0.78rem; color: #64748b;"><?= e($adm['dept_name'] ?? 'Faculty Dept') ?></div>
                <div style="font-size: 0.78rem; color: #475569; font-weight: 600; margin-top: 2px;">
                  Session: <?= e($adm['academic_session'] ?? 'Batch 26 (Fall 2026)') ?>
                </div>
              </td>

              <td>
                <?php if (($adm['payment_status'] ?? '') === 'Paid'): ?>
                  <span style="background: #dcfce7; color: #15803d; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 4px; display: inline-block;">
                    ✓ ৳150 Paid
                  </span>
                  <?php if (!empty($adm['transaction_id'])): ?>
                    <div style="font-size: 0.72rem; color: #b91c1c; font-family: monospace; margin-top: 3px;">
                      Trx: <?= e($adm['transaction_id']) ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($adm['payment_method'])): ?>
                    <div style="font-size: 0.7rem; color: #64748b;">
                      via <?= e($adm['payment_method']) ?> (<?= e($adm['payment_gateway_mode'] ?? 'sandbox') ?>)
                    </div>
                  <?php endif; ?>
                <?php elseif (($adm['payment_status'] ?? '') === 'Failed'): ?>
                  <span style="background: #fee2e2; color: #991b1b; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 4px;">
                    Failed
                  </span>
                <?php elseif (($adm['payment_status'] ?? '') === 'Refunded'): ?>
                  <span style="background: #f1f5f9; color: #475569; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 4px;">
                    Refunded
                  </span>
                <?php else: ?>
                  <span style="background: #fef3c7; color: #92400e; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 4px; display: inline-block;">
                    Pending ৳150
                  </span>
                <?php endif; ?>
              </td>

              <td>
                <?php
                  $s = $adm['status'];
                  $color = ($s === 'Accepted') ? 'background:#d1fae5; color:#065f46;' : (($s === 'Under Review' || $s === 'Submitted') ? 'background:#fef3c7; color:#92400e;' : (($s === 'Rejected') ? 'background:#fee2e2; color:#991b1b;' : 'background:#e0e7ff; color:#3730a3;'));
                ?>
                <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 4px; font-size: 0.78rem; font-weight: 700; <?= $color ?>">
                  <?= e($s) ?>
                </span>
                <?php if (!empty($adm['admin_notes'])): ?>
                  <div style="font-size: 0.75rem; color: #64748b; margin-top: 4px; max-width: 170px;">
                    <em>"<?= e($adm['admin_notes']) ?>"</em>
                  </div>
                <?php endif; ?>
              </td>

              <td style="text-align: right;">
                <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                  <!-- Inspect Dossier Modal Button -->
                  <button type="button" class="btn-primary" onclick="openDossier(<?= (int)$adm['id'] ?>)" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; background: #3b82f6;" title="Inspect Full Dossier">
                    <i class="fas fa-folder-open"></i> Dossier
                  </button>

                  <!-- Direct Accept Button -->
                  <?php if ($adm['status'] !== 'Accepted'): ?>
                    <form action="<?= APP_URL ?>/admin/admissions.php" method="POST" style="display: inline;" onsubmit="return confirm('Accept application for <?= addslashes(e($adm['full_name'])) ?> and automatically generate Student ID & Portal account?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="accept_application">
                      <input type="hidden" name="id" value="<?= (int)$adm['id'] ?>">
                      <button type="submit" class="btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; background: #16a34a;" title="Accept & Create Student Account">
                        <i class="fas fa-check"></i> Accept
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- Direct Reject Button -->
                  <?php if ($adm['status'] !== 'Rejected'): ?>
                    <form action="<?= APP_URL ?>/admin/admissions.php" method="POST" style="display: inline;" onsubmit="return confirm('Mark application for <?= addslashes(e($adm['full_name'])) ?> as Rejected?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="reject_application">
                      <input type="hidden" name="id" value="<?= (int)$adm['id'] ?>">
                      <button type="submit" style="background: none; border: 1px solid #ef4444; color: #ef4444; border-radius: 4px; padding: 0.3rem 0.5rem; font-size: 0.75rem; cursor: pointer;" title="Reject Application">
                        <i class="fas fa-times"></i>
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- Admit Card View -->
                  <a href="<?= APP_URL ?>/admission-status.php?ref=<?= urlencode($adm['application_ref']) ?>" target="_blank" style="font-size: 0.75rem; color: #0284c7; padding: 0.3rem 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; text-decoration: none;" title="Admit Card">
                    <i class="fas fa-id-card"></i>
                  </a>

                  <!-- Student Portal / Details if provisioned -->
                  <?php if (!empty($adm['student_table_id'])): ?>
                    <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= $adm['student_table_id'] ?>" style="font-size: 0.75rem; color: #059669; padding: 0.3rem 0.5rem; border: 1px solid #10b981; border-radius: 4px; text-decoration: none; font-weight: 600;" title="Manage Academic Record">
                      <i class="fas fa-user-graduate"></i> Portal
                    </a>
                  <?php endif; ?>

                  <!-- Delete -->
                  <form action="<?= APP_URL ?>/admin/admissions.php" method="POST" style="display: inline;" onsubmit="return confirm('Permanently delete application for <?= addslashes(e($adm['full_name'])) ?>?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$adm['id'] ?>">
                    <button type="submit" style="background: none; border: none; color: #94a3b8; font-size: 0.8rem; cursor: pointer; padding: 4px;" title="Delete Application">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>

            <!-- Embedded Hidden JSON for Dossier Modal -->
            <script type="application/json" id="dossier-data-<?= (int)$adm['id'] ?>">
              <?= json_encode($adm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
            </script>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" style="text-align: center; padding: 2.5rem; color: #64748b;">No admissions match the criteria.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div style="padding: 1rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: center;">
      <ul class="pagination" style="margin: 0; display:flex; gap:0.3rem; list-style:none; padding:0;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
            <a href="<?= APP_URL ?>/admin/admissions.php?status=<?= urlencode($statusFilter) ?>&payment_status=<?= urlencode($paymentFilter) ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>" class="page-link" style="padding: 0.4rem 0.8rem; border: 1px solid #cbd5e1; border-radius: 4px; text-decoration: none; color: <?= ($page === $i) ? '#ffffff' : '#334155' ?>; background: <?= ($page === $i) ? 'var(--rmu-primary)' : '#ffffff' ?>;">
              <?= $i ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================== -->
<!-- APPLICANT DOSSIER MODAL -->
<!-- ============================================================== -->
<div id="dossierModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.65); z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem; backdrop-filter: blur(2px);">
  <div style="background: #ffffff; border-radius: 12px; width: 100%; max-width: 850px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); display: flex; flex-direction: column;">
    
    <!-- Modal Header -->
    <div style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-radius: 12px 12px 0 0;">
      <div>
        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a;" id="m-title">Applicant Dossier</h3>
        <span style="font-size: 0.82rem; color: #64748b;" id="m-ref-subtitle">Application Reference: #</span>
      </div>
      <button type="button" onclick="closeDossier()" style="background: none; border: none; font-size: 1.25rem; color: #64748b; cursor: pointer; padding: 0.5rem;">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Modal Body -->
    <div style="padding: 1.75rem; display: flex; flex-direction: column; gap: 1.5rem;">
      
      <!-- Section 1: Personal Particulars -->
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
        <h4 style="margin: 0 0 0.75rem; font-size: 0.95rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px;">
          <i class="fas fa-user"></i> 1. Personal &amp; Contact Particulars
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; font-size: 0.88rem;">
          <div><strong style="color: #64748b;">Full Name:</strong> <span id="m-name" style="font-weight: 600; color: #0f172a;"></span></div>
          <div><strong style="color: #64748b;">Date of Birth:</strong> <span id="m-dob"></span></div>
          <div><strong style="color: #64748b;">Gender:</strong> <span id="m-gender"></span></div>
          <div><strong style="color: #64748b;">Mobile:</strong> <span id="m-mobile"></span></div>
          <div><strong style="color: #64748b;">Email:</strong> <span id="m-email"></span></div>
          <div><strong style="color: #64748b;">Father's Name:</strong> <span id="m-father"></span></div>
          <div><strong style="color: #64748b;">Mother's Name:</strong> <span id="m-mother"></span></div>
          <div><strong style="color: #64748b;">Home District:</strong> <span id="m-district"></span></div>
          <div style="grid-column: 1 / -1;"><strong style="color: #64748b;">Present Address:</strong> <span id="m-present-addr"></span></div>
          <div style="grid-column: 1 / -1;"><strong style="color: #64748b;">Permanent Address:</strong> <span id="m-permanent-addr"></span></div>
        </div>
      </div>

      <!-- Section 2: Academic Qualifications & Selected Program -->
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
        <h4 style="margin: 0 0 0.75rem; font-size: 0.95rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px;">
          <i class="fas fa-graduation-cap"></i> 2. Academic Background &amp; Program Choice
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; font-size: 0.88rem;">
          <div><strong style="color: #64748b;">Target Program:</strong> <span id="m-prog" style="font-weight: 600; color: #0f172a;"></span></div>
          <div><strong style="color: #64748b;">Academic Session:</strong> <span id="m-session" style="font-weight: 600; color: var(--rmu-primary);"></span></div>
          <div><strong style="color: #64748b;">SSC Examination:</strong> <span id="m-ssc"></span></div>
          <div><strong style="color: #64748b;">HSC Examination:</strong> <span id="m-hsc"></span></div>
          <div><strong style="color: #64748b;">Last College:</strong> <span id="m-college"></span></div>
          <div><strong style="color: #64748b;">Quota Claim:</strong> <span id="m-quota"></span></div>
          <div style="grid-column: 1 / -1;" id="m-docs-container">
            <strong style="color: #64748b;">Academic Documents:</strong> 
            <span id="m-docs"></span>
          </div>
        </div>
      </div>

      <!-- Section 3: ৳150 Payment Status & Verification -->
      <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1.25rem;">
        <h4 style="margin: 0 0 0.75rem; font-size: 0.95rem; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.5px;">
          <i class="fas fa-file-invoice-dollar"></i> 3. ৳150 Application Fee Payment Status
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; font-size: 0.88rem; align-items: center;">
          <div><strong style="color: #4b5563;">Status:</strong> <span id="m-pay-status" style="font-weight: 700;"></span></div>
          <div><strong style="color: #4b5563;">Amount:</strong> ৳150.00 BDT</div>
          <div><strong style="color: #4b5563;">Transaction ID:</strong> <span id="m-pay-trx" style="font-family: monospace; font-weight: 600;"></span></div>
          <div><strong style="color: #4b5563;">Payment Method:</strong> <span id="m-pay-method"></span></div>
          <div><strong style="color: #4b5563;">Paid Date:</strong> <span id="m-pay-date"></span></div>
          <div id="m-pay-slip-container"></div>
        </div>
      </div>

      <!-- Section 4: Admissions Committee Review & Provisioning -->
      <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1.25rem;">
        <h4 style="margin: 0 0 0.75rem; font-size: 0.95rem; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
          <i class="fas fa-tasks"></i> 4. Committee Decision &amp; Actions
        </h4>

        <form action="<?= APP_URL ?>/admin/admissions.php" method="POST" id="dossierForm">
          <?= csrf_field() ?>
          <input type="hidden" name="id" id="m-form-id" value="">
          <input type="hidden" name="action" id="m-form-action" value="update_status">

          <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
              <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Set Status</label>
              <select name="status" id="m-status-select" class="form-select">
                <option value="Submitted">Submitted</option>
                <option value="Under Review">Under Review</option>
                <option value="Additional Information Required">Additional Information Required</option>
                <option value="Accepted">Accepted (Provision Student)</option>
                <option value="Rejected">Rejected</option>
              </select>
            </div>
            <div>
              <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Committee / Counselor Notes</label>
              <input type="text" name="admin_notes" id="m-admin-notes" class="form-control" placeholder="Notes regarding candidate interview, payment or documentation...">
            </div>
          </div>

          <div style="display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap;">
            <button type="button" onclick="submitDossierAction('pending_application')" class="btn-primary" style="background: #f59e0b; padding: 0.6rem 1.2rem; font-size: 0.88rem;">
              <i class="fas fa-clock"></i> Keep Under Review
            </button>
            <button type="button" onclick="submitDossierAction('reject_application')" style="background: #ef4444; color: white; border: none; border-radius: 6px; padding: 0.6rem 1.2rem; font-size: 0.88rem; font-weight: 600; cursor: pointer;">
              <i class="fas fa-times-circle"></i> Reject Application
            </button>
            <button type="button" onclick="submitDossierAction('accept_application')" class="btn-primary" style="background: #15803d; padding: 0.6rem 1.4rem; font-size: 0.88rem; font-weight: 700;">
              <i class="fas fa-check-circle"></i> Accept &amp; Create Student ID
            </button>
            <button type="submit" class="btn-primary" style="padding: 0.6rem 1.2rem; font-size: 0.88rem;">
              <i class="fas fa-save"></i> Save Notes
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<script>
function openDossier(id) {
  var dataElem = document.getElementById('dossier-data-' + id);
  if (!dataElem) return;
  var d = JSON.parse(dataElem.textContent);

  document.getElementById('m-form-id').value = d.id;
  document.getElementById('m-title').textContent = 'Dossier: ' + d.full_name;
  document.getElementById('m-ref-subtitle').textContent = 'Application Ref: ' + d.application_ref + ' • Submitted: ' + (d.created_at || 'N/A');

  document.getElementById('m-name').textContent = d.full_name || 'N/A';
  document.getElementById('m-dob').textContent = d.date_of_birth || 'N/A';
  document.getElementById('m-gender').textContent = d.gender || 'N/A';
  document.getElementById('m-mobile').textContent = d.mobile || 'N/A';
  document.getElementById('m-email').textContent = d.email || 'N/A';
  document.getElementById('m-father').textContent = d.father_name || 'N/A';
  document.getElementById('m-mother').textContent = d.mother_name || 'N/A';
  document.getElementById('m-district').textContent = d.home_district || 'N/A';
  document.getElementById('m-present-addr').textContent = d.present_address || 'N/A';
  document.getElementById('m-permanent-addr').textContent = d.permanent_address || 'N/A';

  document.getElementById('m-prog').textContent = d.program_title || d.interested_subject || 'N/A';
  document.getElementById('m-session').textContent = d.academic_session || 'Batch 26';
  document.getElementById('m-ssc').textContent = (d.ssc_exam || 'SSC') + ' - GPA ' + (d.ssc_gpa || '0.00') + ' (Board: ' + (d.ssc_board || 'N/A') + ', Roll: ' + (d.ssc_roll || 'N/A') + ')';
  document.getElementById('m-hsc').textContent = (d.hsc_exam || 'HSC') + ' - GPA ' + (d.hsc_gpa || '0.00') + ' (Board: ' + (d.hsc_board || 'N/A') + ', Roll: ' + (d.hsc_roll || 'N/A') + ')';
  document.getElementById('m-college').textContent = d.last_college || 'N/A';
  document.getElementById('m-quota').textContent = (d.quota === 'YES') ? 'Yes (Quota Claimed)' : 'None';

  var docsSpan = document.getElementById('m-docs');
  if (d.academic_documents) {
    docsSpan.innerHTML = '<a href="<?= APP_URL ?>/' + d.academic_documents + '" target="_blank" style="color:#2563eb; font-weight:700; text-decoration:underline;"><i class="fas fa-file-download"></i> View / Download Uploaded Documents</a>';
  } else {
    docsSpan.textContent = 'No documents attached';
  }

  var payStatus = d.payment_status || 'Pending';
  var payColor = (payStatus === 'Paid') ? '#15803d' : ((payStatus === 'Failed') ? '#b91c1c' : '#b45309');
  var payStatusElem = document.getElementById('m-pay-status');
  payStatusElem.textContent = payStatus;
  payStatusElem.style.color = payColor;

  document.getElementById('m-pay-trx').textContent = d.transaction_id || 'Awaiting Payment';
  document.getElementById('m-pay-method').textContent = (d.payment_method || 'bKash') + ' (' + (d.payment_gateway_mode || 'sandbox') + ')';
  document.getElementById('m-pay-date').textContent = d.paid_at || 'Not paid yet';

  var slipCont = document.getElementById('m-pay-slip-container');
  if (d.payment_screenshot) {
    slipCont.innerHTML = '<a href="<?= APP_URL ?>/storage/uploads/payments/' + d.payment_screenshot + '" target="_blank" style="font-size:0.8rem; color:#2563eb; font-weight:600;"><i class="fas fa-image"></i> View Payment Slip</a>';
  } else {
    slipCont.innerHTML = '';
  }

  document.getElementById('m-status-select').value = d.status || 'Submitted';
  document.getElementById('m-admin-notes').value = d.admin_notes || '';

  var modal = document.getElementById('dossierModal');
  modal.style.display = 'flex';
}

function closeDossier() {
  document.getElementById('dossierModal').style.display = 'none';
}

function submitDossierAction(actionName) {
  if (actionName === 'accept_application') {
    if (!confirm('Are you sure you want to ACCEPT this application and generate an official Student Account and ID?')) return;
  } else if (actionName === 'reject_application') {
    if (!confirm('Are you sure you want to REJECT this application?')) return;
  }
  document.getElementById('m-form-action').value = actionName;
  document.getElementById('dossierForm').submit();
}

// Close on outside click
window.onclick = function(event) {
  var modal = document.getElementById('dossierModal');
  if (event.target === modal) {
    modal.style.display = "none";
  }
};
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
