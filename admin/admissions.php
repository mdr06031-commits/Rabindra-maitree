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
ensure_admission_application_system_schema($db);
ensure_admission_payment_gateway_schema($db);
$currentAdmin = current_user();
$adminId = (int)($currentAdmin['id'] ?? ($_SESSION['user_id'] ?? 1));

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } elseif (!is_admin_manager_role()) {
        log_audit('ADMISSION_ACTION_DENIED', "Unauthorized role '" . current_admin_role() . "' attempted action '" . ($_POST['action'] ?? '') . "' in admissions.php");
        flash_set('error', 'Access denied: Only Administrator accounts may accept, reject, modify, or delete admission applications.');
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'accept_application' && $id > 0) {
            $notes = trim($_POST['admin_notes'] ?? 'Application accepted by admissions committee.');
            $upd = $db->prepare("UPDATE admissions SET status = 'Accepted', admin_notes = ?, accepted_at = NOW(), accepted_by = ? WHERE id = ?");
            $upd->execute([$notes, $adminId, $id]);

            // Provision student account & sync structure
            $chk = $db->prepare("SELECT * FROM admissions WHERE id = ?");
            $chk->execute([$id]);
            $admRow = $chk->fetch(PDO::FETCH_ASSOC);
            $existingStId = $admRow['student_id'] ?? '';
            if (empty($existingStId)) {
                $prov = provision_student_from_admission($id);
                $stId = $prov['student_id'] ?? 'Generated';
                log_audit('ADMISSION_ACCEPTED', "Accepted application #{$id} and provisioned Student ID {$stId}");
                flash_set('success', "Application #{$id} accepted! Student account created (ID: {$stId}) and unlocked for Result Management.");
            } else {
                sync_existing_student_from_admission($id);
                log_audit('ADMISSION_ACCEPTED', "Accepted application #{$id} (Student ID {$existingStId} unlocked for Result Management)");
                flash_set('success', "Application #{$id} status updated to Accepted and unlocked for Result Management.");
            }
        } elseif ($action === 'reject_application' && $id > 0) {
            $notes = trim($_POST['admin_notes'] ?? 'Application rejected.');
            $upd = $db->prepare("UPDATE admissions SET status = 'Rejected', admin_notes = ?, accepted_at = NULL, accepted_by = NULL WHERE id = ?");
            $upd->execute([$notes, $id]);

            log_audit('ADMISSION_REJECTED', "Rejected application #{$id}. Notes: {$notes}");
            flash_set('warning', "Application #{$id} has been marked as Rejected.");
        } elseif ($action === 'pending_application' && $id > 0) {
            $notes = trim($_POST['admin_notes'] ?? 'Application kept under review.');
            $upd = $db->prepare("UPDATE admissions SET status = 'Under Review', admin_notes = ?, accepted_at = NULL, accepted_by = NULL WHERE id = ?");
            $upd->execute([$notes, $id]);

            log_audit('ADMISSION_UNDER_REVIEW', "Application #{$id} marked Under Review");
            flash_set('info', "Application #{$id} is now Under Review.");
        } elseif ($action === 'update_status' && $id > 0) {
            $newStatus = trim($_POST['status'] ?? '');
            $adminNotes = trim($_POST['admin_notes'] ?? '');

            $validStatuses = ['Pending', 'Submitted', 'Under Review', 'Additional Information Required', 'Accepted', 'Rejected'];
            if (in_array($newStatus, $validStatuses, true)) {
                if ($newStatus === 'Accepted') {
                    $update = $db->prepare("UPDATE admissions SET status = ?, admin_notes = ?, accepted_at = NOW(), accepted_by = ? WHERE id = ?");
                    $update->execute([$newStatus, $adminNotes, $adminId, $id]);
                } else {
                    $update = $db->prepare("UPDATE admissions SET status = ?, admin_notes = ?, accepted_at = NULL, accepted_by = NULL WHERE id = ?");
                    $update->execute([$newStatus, $adminNotes, $id]);
                }

                // Auto-provision or sync if Accepted
                if ($newStatus === 'Accepted') {
                    $chk = $db->prepare("SELECT student_id FROM admissions WHERE id = ?");
                    $chk->execute([$id]);
                    $stCode = $chk->fetchColumn();
                    if (empty($stCode)) {
                        provision_student_from_admission($id);
                    } else {
                        sync_existing_student_from_admission($id);
                    }
                }

                log_audit('ADMISSION_STATUS_UPDATED', "Updated application ID {$id} to status '{$newStatus}' with notes: '{$adminNotes}'");
                flash_set('success', "Application #{$id} status updated to '{$newStatus}' successfully.");
            } else {
                flash_set('error', 'Invalid status selection.');
            }
        } elseif ($action === 'verify_payment' && $id > 0) {
            $suppliedTrx = trim((string)($_POST['transaction_id'] ?? ''));
            $verRes = rmu_admin_verify_admission_payment($db, $id, $suppliedTrx, $adminId);
            if (!empty($verRes['success'])) {
                flash_set(!empty($verRes['already_paid']) ? 'info' : 'success', (string)$verRes['message']);
            } else {
                flash_set('error', (string)($verRes['error'] ?? "Could not verify payment for application #{$id}."));
            }
        } elseif ($action === 'update_payment_status' && $id > 0) {
            $payStatus = in_array($_POST['payment_status'] ?? '', ['Pending', 'Paid', 'Under Verification', 'Failed', 'Refunded']) ? $_POST['payment_status'] : 'Pending';
            $trx = strtoupper(trim((string)($_POST['transaction_id'] ?? '')));
            if ($payStatus === 'Paid') {
                $verRes = rmu_admin_verify_admission_payment($db, $id, $trx, $adminId);
                if (!empty($verRes['success'])) {
                    flash_set(!empty($verRes['already_paid']) ? 'info' : 'success', (string)$verRes['message']);
                } else {
                    flash_set('error', (string)($verRes['error'] ?? 'Could not verify payment as Paid.'));
                }
            } else {
                if ($trx !== '') {
                    $dupChk = $db->prepare("SELECT id FROM admissions WHERE transaction_id = ? AND id != ? LIMIT 1");
                    $dupChk->execute([$trx, $id]);
                    if ($dupChk->fetchColumn()) {
                        $trx = '';
                    }
                }
                $upd = $db->prepare("UPDATE admissions SET payment_status = ?, transaction_id = COALESCE(?, transaction_id), paid_at = NULL WHERE id = ?");
                $upd->execute([$payStatus, $trx !== '' ? $trx : null, $id]);
                if ($payStatus === 'Failed') {
                    $db->prepare("UPDATE admission_payment_transactions SET gateway_status = 'FAILED', status = 'failed', failure_reason = COALESCE(NULLIF(failure_reason, ''), 'Marked Failed by Admin'), updated_at = NOW() WHERE admission_id = ? AND validated_once_at IS NULL AND gateway_status IN ('INITIATED', 'AUTHORIZED')")->execute([$id]);
                }
                invalidate_admission_application_pdf_cache($id, $db);
                log_audit('ADMISSION_PAYMENT_STATUS_UPDATED', "Updated payment status of application #{$id} to {$payStatus} (Trx: {$trx})");
                flash_set('success', "Payment status for application #{$id} updated to {$payStatus}.");
            }
        } elseif ($action === 'approve_historical' && $id > 0) {
            $note = trim($_POST['approval_note'] ?? '');
            $res = approve_historical_academic_record('admission', $id, $adminId, $note);
            if (!empty($res['success'])) {
                flash_set('success', "Historical Session & Passing Year record approved for Application #{$id} and synced to any linked student account.");
            } else {
                flash_set('error', $res['error'] ?? 'Failed to approve historical record.');
            }
        } elseif ($action === 'official_academic_edit' && $id > 0) {
            $reason = trim($_POST['reason'] ?? '');
            $passingYrIn = trim($_POST['passing_year'] ?? '');
            $appYrIn = trim($_POST['application_year'] ?? '');
            $durIn = trim($_POST['duration_years'] ?? '');
            $semIn = trim($_POST['total_semesters'] ?? '');
            if ($reason === '') {
                flash_set('error', 'Mandatory reason is required for any official academic edit.');
            } elseif ($passingYrIn !== '' && !preg_match('/^\d{4}$/', $passingYrIn)) {
                flash_set('error', 'Actual Passing Year must be a 4-digit year (e.g., 2016) or left blank.');
            } elseif ($appYrIn !== '' && (!ctype_digit($appYrIn) || (int)$appYrIn < 2015)) {
                flash_set('error', 'Online Application Record Year must start from 2015 or later.');
            } else {
                $updates = [
                    'academic_session' => trim($_POST['academic_session'] ?? ''),
                    'passing_year'     => $passingYrIn,
                    'program_id'       => (int)($_POST['program_id'] ?? 0),
                    'degree_type'      => trim($_POST['degree_type'] ?? ''),
                ];
                if ($appYrIn !== '') {
                    $updates['application_year'] = (int)$appYrIn;
                }
                if ($durIn !== '' && is_numeric($durIn) && (float)$durIn > 0) {
                    $updates['duration_years'] = (float)$durIn;
                }
                if ($semIn !== '' && ctype_digit($semIn) && (int)$semIn > 0) {
                    $updates['total_semesters'] = (int)$semIn;
                }
                $res = update_academic_record_with_audit('admission', $id, $updates, $adminId, $reason);
                if (!empty($res['success'])) {
                    flash_set('success', "Official academic update saved for Application #{$id} (logged in Academic Edit History & Audit Logs; Original applicant submission snapshot preserved).");
                } else {
                    flash_set('error', $res['error'] ?? 'Failed to update academic record.');
                }
            }
        } elseif ($action === 'delete' && $id > 0) {
            $pdfStmt = $db->prepare("SELECT pdf_path, payment_screenshot FROM admissions WHERE id = ?");
            $pdfStmt->execute([$id]);
            $delFiles = $pdfStmt->fetch(PDO::FETCH_ASSOC);
            if ($delFiles) {
                $oldPdfRel = trim((string)($delFiles['pdf_path'] ?? ''));
                if ($oldPdfRel !== '' && is_file(BASE_PATH . '/' . $oldPdfRel)) {
                    @unlink(BASE_PATH . '/' . $oldPdfRel);
                }
                $oldSlip = basename(trim((string)($delFiles['payment_screenshot'] ?? '')));
                if ($oldSlip !== '' && is_file(STORAGE_PATH . '/uploads/payments/' . $oldSlip)) {
                    @unlink(STORAGE_PATH . '/uploads/payments/' . $oldSlip);
                }
            }
            $db->prepare("DELETE FROM admission_payment_transactions WHERE admission_id = ?")->execute([$id]);
            $del = $db->prepare("DELETE FROM admissions WHERE id = ?");
            $del->execute([$id]);
            log_audit('ADMISSION_DELETED', "Admin deleted admission application ID #{$id}");
            flash_set('success', "Application #{$id} deleted successfully.");
        }

        if ($action !== 'delete' && $id > 0) {
            refresh_admission_application_pdf_async($id, $db);
        }
    }
    $redirectParams = [];
    $rStatus = trim((string)($_GET['status'] ?? ($_POST['return_status'] ?? '')));
    $rPay = trim((string)($_GET['payment_status'] ?? ($_POST['return_payment_status'] ?? '')));
    $rAppYr = trim((string)($_GET['app_year'] ?? ($_POST['return_app_year'] ?? '')));
    $rDept = trim((string)($_GET['department_id'] ?? ($_POST['return_department_id'] ?? '')));
    $rSession = trim((string)($_GET['academic_session'] ?? ($_POST['return_academic_session'] ?? '')));
    $rSearch = trim((string)($_GET['search'] ?? ($_POST['return_search'] ?? '')));
    if ($rStatus !== '' && $rStatus !== 'All') $redirectParams['status'] = $rStatus;
    if ($rPay !== '' && $rPay !== 'All') $redirectParams['payment_status'] = $rPay;
    if ($rAppYr !== '' && $rAppYr !== 'All') $redirectParams['app_year'] = $rAppYr;
    if ($rDept !== '' && $rDept !== 'All' && ctype_digit($rDept)) $redirectParams['department_id'] = $rDept;
    if ($rSession !== '' && $rSession !== 'All') $redirectParams['academic_session'] = $rSession;
    if ($rSearch !== '') $redirectParams['search'] = $rSearch;
    $redirectSuffix = !empty($redirectParams) ? ('?' . http_build_query($redirectParams)) : '';
    header("Location: " . APP_URL . "/admin/admissions.php" . $redirectSuffix);
    exit;
}

// Filters & Search
$statusFilter = $_GET['status'] ?? 'All';
$paymentFilter = $_GET['payment_status'] ?? 'All';
$appYearFilter = trim($_GET['app_year'] ?? 'All');
$deptFilter = trim((string)($_GET['department_id'] ?? 'All'));
$sessionFilter = trim((string)($_GET['academic_session'] ?? 'All'));
$search = trim($_GET['search'] ?? '');
$admFilterParams = [];
if ($statusFilter !== 'All' && $statusFilter !== '') $admFilterParams['status'] = $statusFilter;
if ($paymentFilter !== 'All' && $paymentFilter !== '') $admFilterParams['payment_status'] = $paymentFilter;
if ($appYearFilter !== 'All' && $appYearFilter !== '') $admFilterParams['app_year'] = $appYearFilter;
if ($deptFilter !== 'All' && $deptFilter !== '' && ctype_digit($deptFilter)) $admFilterParams['department_id'] = $deptFilter;
if ($sessionFilter !== 'All' && $sessionFilter !== '') $admFilterParams['academic_session'] = $sessionFilter;
if ($search !== '') $admFilterParams['search'] = $search;
$admFilterQuery = !empty($admFilterParams) ? ('?' . http_build_query($admFilterParams)) : '';
$admReturnHiddenInputs =
    '<input type="hidden" name="return_status" value="' . e($statusFilter) . '">' .
    '<input type="hidden" name="return_payment_status" value="' . e($paymentFilter) . '">' .
    '<input type="hidden" name="return_app_year" value="' . e($appYearFilter) . '">' .
    '<input type="hidden" name="return_department_id" value="' . e($deptFilter) . '">' .
    '<input type="hidden" name="return_academic_session" value="' . e($sessionFilter) . '">' .
    '<input type="hidden" name="return_search" value="' . e($search) . '">';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($statusFilter === 'PendingQueue') {
    $where[] = "a.status IN ('Submitted', 'Pending', 'Under Review')";
} elseif ($statusFilter !== 'All') {
    $where[] = "a.status = ?";
    $params[] = $statusFilter;
}

if ($paymentFilter !== 'All') {
    $where[] = "a.payment_status = ?";
    $params[] = $paymentFilter;
}

if ($appYearFilter !== 'All' && ctype_digit($appYearFilter)) {
    $where[] = "COALESCE(a.application_year, YEAR(a.created_at)) = ?";
    $params[] = (int)$appYearFilter;
}

if ($deptFilter !== 'All' && ctype_digit($deptFilter)) {
    $where[] = "(a.department_id = ? OR p.department_id = ?)";
    $params[] = (int)$deptFilter;
    $params[] = (int)$deptFilter;
}

if ($sessionFilter !== 'All' && $sessionFilter !== '') {
    $where[] = "a.academic_session = ?";
    $params[] = $sessionFilter;
}

if (!empty($search)) {
    $where[] = "(a.application_ref LIKE ? OR a.application_id LIKE ? OR a.roll_number LIKE ? OR a.exam_roll LIKE ? OR a.full_name LIKE ? OR a.mobile LIKE ? OR a.email LIKE ? OR a.student_id LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
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

$discStmt = $db->query("
    SELECT a.id, a.academic_session, a.passing_year, a.degree_type, a.duration_years, COALESCE(a.historical_record_approved, 0) AS historical_record_approved,
           p.duration_years AS prog_duration_years
    FROM admissions a
    LEFT JOIN programs p ON a.program_id = p.id
    WHERE a.status != 'Rejected'
");
$totalDiscrepancies = 0;
foreach ($discStmt->fetchAll(PDO::FETCH_ASSOC) as $dr) {
    $durY = !empty($dr['duration_years'])
        ? (float)$dr['duration_years']
        : (!empty($dr['prog_duration_years'])
            ? (float)$dr['prog_duration_years']
            : (($dr['degree_type'] === 'B.Ed') ? 1.0 : (($dr['degree_type'] === "Master's") ? 2.0 : 4.0)));
    $ev = evaluate_passing_year_difference($dr['academic_session'] ?? '', $dr['passing_year'] ?? null, $durY, !empty($dr['historical_record_approved']));
    if (!empty($ev['needs_review'])) {
        $totalDiscrepancies++;
    }
}

$departmentsList = $db->query("SELECT id, name_en FROM departments ORDER BY name_en ASC")->fetchAll(PDO::FETCH_ASSOC);
$programsList = $db->query("SELECT id, title_en, degree_type, duration_years, total_semesters FROM programs WHERE is_active = 1 ORDER BY sort_order ASC, title_en ASC")->fetchAll(PDO::FETCH_ASSOC);
$allSessionsList = get_academic_sessions(false, 'DESC');
$allPassingYearsList = get_passing_years(false, 'DESC');
$applicationRecordYears = get_application_record_years(2015, (int)date('Y') + 2, 'DESC');

// Count Total Filtered
$countStmt = $db->prepare("SELECT COUNT(*) FROM admissions a LEFT JOIN programs p ON a.program_id = p.id $whereSql");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();

// Fetch Data with Student profile & latest Gateway transaction info
$query = "
    SELECT a.*, s.id AS student_table_id, s.cgpa AS student_cgpa, s.current_semester AS student_sem,
           p.title_en AS program_title, p.degree_type AS prog_degree_type,
           p.duration_years AS prog_duration_years, p.total_semesters AS prog_total_semesters,
           d.name_en AS dept_name,
           apt.gateway_session_id, apt.gateway_provider AS tx_gateway_provider, apt.gateway_mode AS tx_gateway_mode,
           apt.gateway_status, apt.validated_once_at, apt.verified_at AS tx_verified_at, apt.failure_reason AS tx_failure_reason
    FROM admissions a 
    LEFT JOIN students s ON a.student_id = s.student_id 
    LEFT JOIN programs p ON a.program_id = p.id
    LEFT JOIN departments d ON a.department_id = d.id
    LEFT JOIN admission_payment_transactions apt ON apt.id = (
        SELECT t2.id FROM admission_payment_transactions t2
        WHERE t2.admission_id = a.id
        ORDER BY (t2.gateway_status = 'VERIFIED') DESC, t2.id DESC
        LIMIT 1
    )
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
      <i class="fas fa-user-graduate" style="color:var(--rmu-primary);"></i> Admission Management &amp; Review
      <?php if ($statusFilter === 'PendingQueue'): ?>
        <span style="font-size:0.85rem; background:#fef3c7; color:#b45309; padding:3px 10px; border-radius:20px; vertical-align:middle; margin-left:6px;">Pending Applications Queue</span>
      <?php endif; ?>
    </h2>
    <span style="color: #64748b; font-size: 0.9rem;">
      Review online admission applications, verify Application IDs &amp; Roll Numbers, accept or reject candidates, and unlock Result Management for accepted students.
    </span>
  </div>
  <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
    <a href="<?= APP_URL ?>/admin/admissions.php?status=PendingQueue" class="btn-primary" style="padding:0.6rem 1.1rem; font-size:0.88rem; background:#f59e0b; color:#0f172a; font-weight:700;">
      <i class="fas fa-hourglass-half"></i> Pending Queue (<?= number_format($totalPending) ?>)
    </a>
    <a href="<?= APP_URL ?>/admin/academic-sessions.php?tab=oversight" class="btn-primary" style="padding:0.6rem 1.1rem; font-size:0.88rem; background:#0f2c59;">
      <i class="fas fa-calendar-check"></i> Passing Oversight (<?= number_format($totalDiscrepancies) ?>)
    </a>
    <a href="<?= APP_URL ?>/admission-form.php" target="_blank" class="btn-primary" style="padding:0.6rem 1.2rem; font-size:0.88rem; background:#0284c7;">
      <i class="fas fa-plus"></i> Open Apply Online Form
    </a>
  </div>
</div>

<!-- KPI Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(185px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
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
  <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem; border-left:4px solid #7c3aed;">
    <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Passing Oversight</div>
    <div style="font-size:1.8rem; font-weight:800; color:#6d28d9; margin-top:0.25rem;"><?= number_format($totalDiscrepancies) ?></div>
    <small style="color:#64748b;"><a href="<?= APP_URL ?>/admin/academic-sessions.php?tab=oversight" style="color:#6d28d9; font-weight:600;">Review differences &rarr;</a></small>
  </div>
</div>

<!-- Search & Filter Bar -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem 1.5rem; margin-bottom: 2rem;">
  <form action="<?= APP_URL ?>/admin/admissions.php" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
    <div style="flex: 2; min-width: min(100%, 240px);">
      <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by Application ID / Ref, Roll No, Candidate Name, Mobile, or Student ID..." class="form-control" style="padding: 0.65rem 1rem;">
    </div>

    <div style="flex: 1; min-width: min(100%, 180px);">
      <select name="status" class="form-select" style="padding: 0.65rem 1rem;">
        <option value="All" <?= ($statusFilter === 'All') ? 'selected' : '' ?>>All Admission Statuses</option>
        <option value="PendingQueue" <?= ($statusFilter === 'PendingQueue') ? 'selected' : '' ?>>Pending Applications Queue</option>
        <option value="Pending" <?= ($statusFilter === 'Pending') ? 'selected' : '' ?>>Pending</option>
        <option value="Submitted" <?= ($statusFilter === 'Submitted') ? 'selected' : '' ?>>Submitted</option>
        <option value="Under Review" <?= ($statusFilter === 'Under Review') ? 'selected' : '' ?>>Under Review</option>
        <option value="Accepted" <?= ($statusFilter === 'Accepted') ? 'selected' : '' ?>>Accepted</option>
        <option value="Additional Information Required" <?= ($statusFilter === 'Additional Information Required') ? 'selected' : '' ?>>Info Required</option>
        <option value="Rejected" <?= ($statusFilter === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
      </select>
    </div>

    <div style="flex: 1; min-width: min(100%, 150px);">
      <select name="payment_status" class="form-select" style="padding: 0.65rem 1rem;">
        <option value="All" <?= ($paymentFilter === 'All') ? 'selected' : '' ?>>All Payment Statuses</option>
        <option value="Paid" <?= ($paymentFilter === 'Paid') ? 'selected' : '' ?>>Paid (৳150 Verified)</option>
        <option value="Under Verification" <?= ($paymentFilter === 'Under Verification') ? 'selected' : '' ?>>Under Verification</option>
        <option value="Pending" <?= ($paymentFilter === 'Pending') ? 'selected' : '' ?>>Pending Payment</option>
        <option value="Failed" <?= ($paymentFilter === 'Failed') ? 'selected' : '' ?>>Failed</option>
        <option value="Refunded" <?= ($paymentFilter === 'Refunded') ? 'selected' : '' ?>>Refunded</option>
      </select>
    </div>

    <div style="flex: 1; min-width: min(100%, 170px);">
      <select name="department_id" class="form-select" style="padding: 0.65rem 1rem;">
        <option value="All" <?= ($deptFilter === 'All') ? 'selected' : '' ?>>All Departments</option>
        <?php foreach ($departmentsList as $deptOpt): ?>
          <option value="<?= (int)$deptOpt['id'] ?>" <?= ($deptFilter === (string)$deptOpt['id']) ? 'selected' : '' ?>>
            <?= e($deptOpt['name_en']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="flex: 1; min-width: min(100%, 160px);">
      <select name="academic_session" class="form-select" style="padding: 0.65rem 1rem;">
        <option value="All" <?= ($sessionFilter === 'All') ? 'selected' : '' ?>>All Academic Sessions</option>
        <?php foreach ($allSessionsList as $sessOpt): ?>
          <option value="<?= e($sessOpt['session_name']) ?>" <?= ($sessionFilter === (string)$sessOpt['session_name']) ? 'selected' : '' ?>>
            <?= e($sessOpt['session_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="flex: 0.8; min-width: min(100%, 145px);">
      <select name="app_year" class="form-select" style="padding: 0.65rem 1rem;">
        <option value="All" <?= ($appYearFilter === 'All') ? 'selected' : '' ?>>App Year: All (2015+)</option>
        <?php foreach ($applicationRecordYears as $ayOpt): ?>
          <option value="<?= (int)$ayOpt ?>" <?= ($appYearFilter === (string)$ayOpt) ? 'selected' : '' ?>>App Year: <?= (int)$ayOpt ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <button type="submit" class="btn-primary" style="padding: 0.65rem 1.4rem;">
      <i class="fas fa-filter"></i> Apply Filter
    </button>
    <?php if (!empty($search) || $statusFilter !== 'All' || $paymentFilter !== 'All' || $appYearFilter !== 'All' || $deptFilter !== 'All' || $sessionFilter !== 'All'): ?>
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
            <?php
              $admDocUrl = get_admission_document_url($adm['academic_documents'] ?? null);
              $admSlipUrl = get_payment_screenshot_url($adm['payment_screenshot'] ?? null);
            ?>
            <tr>
              <td>
                <?php
                  $admAppYear = !empty($adm['application_year']) ? (int)$adm['application_year'] : (!empty($adm['created_at']) ? max(2015, (int)date('Y', strtotime($adm['created_at']))) : max(2015, (int)date('Y')));
                  $rowAppId = !empty($adm['application_id']) ? (string)$adm['application_id'] : (string)$adm['application_ref'];
                  $rowRollNo = !empty($adm['roll_number']) ? (string)$adm['roll_number'] : (string)($adm['exam_roll'] ?? '');
                ?>
                <strong style="color: var(--rmu-primary); font-size: 0.95rem; font-family: monospace; display: block;"><?= e($adm['application_ref']) ?></strong>
                <div style="font-size:0.75rem; color:#0f172a; font-family:monospace; font-weight:700;">App ID: <?= e($rowAppId) ?></div>
                <div style="font-size:0.74rem; color:#7c3aed; font-family:monospace; font-weight:700;">Roll No: <?= e($rowRollNo !== '' ? $rowRollNo : 'Pending') ?></div>
                <div style="font-size:0.73rem; color:#475569; font-weight:600;">App Year: <?= $admAppYear ?> &bull; <small style="color: #64748b;"><?= format_date($adm['created_at']) ?></small></div>
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
                <?php if ($admDocUrl): ?>
                  <div style="margin-top: 4px;">
                    <a href="<?= e($admDocUrl) ?>" target="_blank" style="color: #2563eb; font-size: 0.78rem; font-weight: 600; text-decoration: underline;">
                      <i class="fas fa-file-pdf"></i> Academic Docs
                    </a>
                  </div>
                <?php endif; ?>
              </td>

              <td>
                <?php
                  $admDegType = !empty($adm['degree_type']) ? $adm['degree_type'] : ($adm['prog_degree_type'] ?? 'Bachelor / Honours');
                  $admDurYears = (!empty($adm['duration_years']) && (float)$adm['duration_years'] > 0)
                      ? (float)$adm['duration_years']
                      : (!empty($adm['prog_duration_years']) ? (float)$adm['prog_duration_years'] : (($admDegType === 'B.Ed') ? 1.0 : (($admDegType === "Master's") ? 2.0 : 4.0)));
                  $admTotalSems = (!empty($adm['total_semesters']) && (int)$adm['total_semesters'] > 0)
                      ? (int)$adm['total_semesters']
                      : (!empty($adm['prog_total_semesters']) ? (int)$adm['prog_total_semesters'] : (($admDegType === 'B.Ed') ? 2 : (($admDegType === "Master's") ? 4 : 8)));
                  $pyEval = evaluate_passing_year_difference(
                      $adm['academic_session'] ?? '',
                      $adm['passing_year'] ?? null,
                      $admDurYears,
                      !empty($adm['historical_record_approved'])
                  );
                  $expYearVal = !empty($adm['expected_passing_year']) ? (int)$adm['expected_passing_year'] : ($pyEval['expected_year'] ?? null);
                  $adm['computed_application_year'] = $admAppYear;
                  $adm['computed_degree_type'] = $admDegType;
                  $adm['computed_duration_years'] = $admDurYears;
                  $adm['computed_total_semesters'] = $admTotalSems;
                  $adm['computed_duration_label'] = !empty($adm['course_duration']) ? $adm['course_duration'] : format_duration_years_label($admDurYears, $admTotalSems);
                  $adm['computed_expected_passing_year'] = $expYearVal;
                  $adm['oversight_eval'] = $pyEval;
                  $origSnapDecoded = !empty($adm['original_submission_json']) ? json_decode((string)$adm['original_submission_json'], true) : null;
                  if (!is_array($origSnapDecoded)) {
                      $origSnapDecoded = [
                          'application_year'      => $admAppYear,
                          'interested_subject'    => $adm['program_title'] ?? $adm['interested_subject'],
                          'degree_type'           => $admDegType,
                          'academic_session'      => $adm['academic_session'] ?? '',
                          'duration_years'        => $admDurYears,
                          'course_duration'       => $adm['computed_duration_label'],
                          'total_semesters'       => $admTotalSems,
                          'expected_passing_year' => $expYearVal,
                          'passing_year'          => $adm['passing_year'] ?? null,
                          'submitted_at'          => $adm['created_at'] ?? '',
                      ];
                  }
                  $adm['original_submission'] = $origSnapDecoded;
                  $adm['document_url'] = $admDocUrl;
                  $adm['payment_slip_url'] = $admSlipUrl;
                  $adm['edit_history'] = get_academic_edit_history('admission', (int)$adm['id'], $adm['student_id'] ?? null, 25);
                ?>
                <span style="display:inline-block; background:#eef2ff; color:#3730a3; font-size:0.7rem; font-weight:800; padding:1px 7px; border-radius:4px; margin-bottom:3px;"><?= e($admDegType) ?></span>
                <strong style="color: #0f172a; font-size: 0.88rem; display: block;"><?= e($adm['program_title'] ?? $adm['interested_subject']) ?></strong>
                <div style="font-size: 0.78rem; color: #64748b;">
                  <?= e($adm['dept_name'] ?? 'Faculty Dept') ?> &bull;
                  <strong>Duration:</strong> <?= e($adm['computed_duration_label']) ?> &bull;
                  <strong>Semesters:</strong> <?= $admTotalSems ?> Semesters
                </div>
                <div style="font-size: 0.78rem; color: #475569; font-weight: 600; margin-top: 2px;">
                  Session: <?= e(!empty($adm['academic_session']) ? $adm['academic_session'] : get_current_academic_session_name('Not Set')) ?>
                  &bull; Actual Passing: <strong><?= !empty($adm['passing_year']) ? e($adm['passing_year']) : 'Not Selected' ?></strong>
                </div>
                <?php if ($expYearVal): ?>
                  <div style="font-size: 0.74rem; color: #475569; margin-top: 2px;" title="Expected Passing Year is advisory only and never overwrites student selection.">
                    Expected Passing Year: <strong><?= (int)$expYearVal ?></strong>
                    <?php if ($pyEval['status_key'] === 'differs'): ?>
                      <a href="<?= APP_URL ?>/admin/academic-sessions.php?tab=oversight&search=<?= urlencode($adm['application_ref']) ?>" style="display:inline-block; background:#fef3c7; color:#92400e; border:1px solid #f59e0b; padding:1px 6px; border-radius:4px; font-weight:800; font-size:0.68rem; text-decoration:none; margin-left:3px;">
                        <i class="fas fa-exclamation-triangle"></i> Mismatch: <?= e($pyEval['status_label']) ?>
                      </a>
                    <?php elseif ($pyEval['status_key'] === 'approved_historical'): ?>
                      <span style="display:inline-block; background:#dcfce7; color:#15803d; border:1px solid #86efac; padding:0 5px; border-radius:4px; font-weight:800; font-size:0.68rem; margin-left:3px;">Historical Approved</span>
                    <?php elseif ($pyEval['status_key'] === 'match'): ?>
                      <span style="display:inline-block; background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; padding:0 5px; border-radius:4px; font-weight:700; font-size:0.68rem; margin-left:3px;">Matches</span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
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
                  <?php if (!empty($adm['validated_once_at'])): ?>
                    <div style="font-size: 0.68rem; color: #15803d; font-weight: 600;">
                      Verified Once: <?= e($adm['validated_once_at']) ?>
                    </div>
                  <?php endif; ?>
                <?php elseif (($adm['payment_status'] ?? '') === 'Under Verification'): ?>
                  <span style="background: #fef3c7; color: #92400e; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 4px; display: inline-block;">
                    Under Verification
                  </span>
                  <?php if (!empty($adm['gateway_session_id'])): ?>
                    <div style="font-size: 0.68rem; color: #64748b; font-family: monospace; margin-top: 2px;">
                      <?= e($adm['gateway_session_id']) ?>
                    </div>
                  <?php endif; ?>
                <?php elseif (($adm['payment_status'] ?? '') === 'Failed'): ?>
                  <span style="background: #fee2e2; color: #991b1b; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 4px;">
                    Failed
                  </span>
                  <?php if (!empty($adm['tx_failure_reason'])): ?>
                    <div style="font-size: 0.68rem; color: #b91c1c; margin-top: 2px; max-width: 170px;">
                      <?= e($adm['tx_failure_reason']) ?>
                    </div>
                  <?php endif; ?>
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
                  $color = ($s === 'Accepted') ? 'background:#d1fae5; color:#065f46;' : (($s === 'Pending' || $s === 'Under Review' || $s === 'Submitted') ? 'background:#fef3c7; color:#92400e;' : (($s === 'Rejected') ? 'background:#fee2e2; color:#991b1b;' : 'background:#e0e7ff; color:#3730a3;'));
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

                  <?php if (is_admin_manager_role()): ?>
                    <!-- Direct Accept Button -->
                    <?php if ($adm['status'] !== 'Accepted'): ?>
                      <form action="<?= APP_URL ?>/admin/admissions.php<?= e($admFilterQuery) ?>" method="POST" style="display: inline;" onsubmit="return confirm('Accept application for <?= addslashes(e($adm['full_name'])) ?> and automatically generate Student ID & Portal account?');">
                        <?= csrf_field() ?>
<?= $admReturnHiddenInputs ?>
                        <input type="hidden" name="action" value="accept_application">
                        <input type="hidden" name="id" value="<?= (int)$adm['id'] ?>">
                        <button type="submit" class="btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; background: #16a34a;" title="Accept & Create Student Account">
                          <i class="fas fa-check"></i> Accept
                        </button>
                      </form>
                    <?php endif; ?>

                    <!-- Direct Reject Button -->
                    <?php if ($adm['status'] !== 'Rejected'): ?>
                      <form action="<?= APP_URL ?>/admin/admissions.php<?= e($admFilterQuery) ?>" method="POST" style="display: inline;" onsubmit="return confirm('Mark application for <?= addslashes(e($adm['full_name'])) ?> as Rejected?');">
                        <?= csrf_field() ?>
<?= $admReturnHiddenInputs ?>
                        <input type="hidden" name="action" value="reject_application">
                        <input type="hidden" name="id" value="<?= (int)$adm['id'] ?>">
                        <button type="submit" style="background: none; border: 1px solid #ef4444; color: #ef4444; border-radius: 4px; padding: 0.3rem 0.5rem; font-size: 0.75rem; cursor: pointer;" title="Reject Application">
                          <i class="fas fa-times"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>

                  <!-- Official Application Card, PDF & Payment Receipt Actions (strictly gated on Paid) -->
                  <?php if (($adm['payment_status'] ?? '') === 'Paid'): ?>
                    <a href="<?= APP_URL ?>/admission-document.php?id=<?= (int)$adm['id'] ?>&amp;doc=application&amp;action=view" target="_blank" style="font-size: 0.74rem; color: #0f2c59; padding: 0.3rem 0.48rem; border: 1px solid #cbd5e1; border-radius: 4px; text-decoration: none; font-weight: 600;" title="View Application">
                      <i class="fas fa-eye"></i> View
                    </a>
                    <a href="<?= APP_URL ?>/admission-document.php?id=<?= (int)$adm['id'] ?>&amp;doc=application&amp;action=pdf" target="_blank" style="font-size: 0.74rem; color: #b91c1c; padding: 0.3rem 0.48rem; border: 1px solid #fecaca; background:#fef2f2; border-radius: 4px; text-decoration: none; font-weight: 600;" title="View PDF">
                      <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="<?= APP_URL ?>/admission-document.php?id=<?= (int)$adm['id'] ?>&amp;doc=application&amp;action=download" style="font-size: 0.74rem; color: #1e40af; padding: 0.3rem 0.48rem; border: 1px solid #bfdbfe; background:#eff6ff; border-radius: 4px; text-decoration: none; font-weight: 600;" title="Download PDF">
                      <i class="fas fa-download"></i>
                    </a>
                    <a href="<?= APP_URL ?>/admission-document.php?id=<?= (int)$adm['id'] ?>&amp;doc=application&amp;action=print" target="_blank" style="font-size: 0.74rem; color: #475569; padding: 0.3rem 0.45rem; border: 1px solid #cbd5e1; border-radius: 4px; text-decoration: none;" title="Print Application">
                      <i class="fas fa-print"></i>
                    </a>
                    <a href="<?= APP_URL ?>/admission-document.php?id=<?= (int)$adm['id'] ?>&amp;doc=receipt&amp;action=view" target="_blank" style="font-size: 0.74rem; color: #15803d; padding: 0.3rem 0.48rem; border: 1px solid #86efac; background:#f0fdf4; border-radius: 4px; text-decoration: none; font-weight: 600;" title="Payment Receipt">
                      <i class="fas fa-receipt"></i> Receipt
                    </a>
                  <?php else: ?>
                    <span style="font-size: 0.72rem; color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; padding: 0.28rem 0.5rem; border-radius: 4px;" title="Application Card is locked until ৳150 fee is verified as Paid">
                      <i class="fas fa-lock"></i> Card Locked
                    </span>
                  <?php endif; ?>

                  <!-- Acceptance Gate: Result Management & Student Profile ONLY for Accepted applications -->
                  <?php if ($adm['status'] === 'Accepted' && !empty($adm['student_table_id'])): ?>
                    <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= (int)$adm['student_table_id'] ?>#cgpaGeneratorPanel" style="font-size: 0.75rem; background:#7c3aed; color: #ffffff; padding: 0.32rem 0.58rem; border-radius: 4px; text-decoration: none; font-weight: 700;" title="Open Result Management & CGPA Generator">
                      <i class="fas fa-calculator"></i> Result Management
                    </a>
                    <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= (int)$adm['student_table_id'] ?>" style="font-size: 0.75rem; color: #059669; padding: 0.3rem 0.5rem; border: 1px solid #10b981; border-radius: 4px; text-decoration: none; font-weight: 600;" title="Manage Academic Record">
                      <i class="fas fa-user-graduate"></i> Portal
                    </a>
                  <?php endif; ?>

                  <!-- Delete -->
                  <?php if (is_admin_manager_role()): ?>
                    <form action="<?= APP_URL ?>/admin/admissions.php<?= e($admFilterQuery) ?>" method="POST" style="display: inline;" onsubmit="return confirm('Permanently delete application for <?= addslashes(e($adm['full_name'])) ?>?');">
                      <?= csrf_field() ?>
                      <?= $admReturnHiddenInputs ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$adm['id'] ?>">
                      <button type="submit" style="background: none; border: none; color: #94a3b8; font-size: 0.8rem; cursor: pointer; padding: 4px;" title="Delete Application">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  <?php endif; ?>
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
      <ul class="pagination" style="margin: 0; display:flex; flex-wrap:wrap; gap:0.3rem; list-style:none; padding:0; max-width:100%;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
            <a href="<?= APP_URL ?>/admin/admissions.php?status=<?= urlencode($statusFilter) ?>&payment_status=<?= urlencode($paymentFilter) ?>&department_id=<?= urlencode($deptFilter) ?>&academic_session=<?= urlencode($sessionFilter) ?>&app_year=<?= urlencode($appYearFilter) ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>" class="page-link" style="padding: 0.4rem 0.8rem; border: 1px solid #cbd5e1; border-radius: 4px; text-decoration: none; color: <?= ($page === $i) ? '#ffffff' : '#334155' ?>; background: <?= ($page === $i) ? 'var(--rmu-primary)' : '#ffffff' ?>;">
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
<div id="dossierModal" class="adm-dossier-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.65); z-index: 9999; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto; backdrop-filter: blur(2px);">
  <div class="adm-dossier-dialog" style="background: #ffffff; border-radius: 12px; width: 100%; max-width: 850px; max-height: 90vh; max-height: 90dvh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); display: flex; flex-direction: column; min-width: 0;">
    
    <!-- Modal Header -->
    <div class="adm-dossier-header" style="padding: 1.25rem 1.75rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-radius: 12px 12px 0 0; flex-wrap: wrap; gap: 0.75rem;">
      <div>
        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a;" id="m-title">Applicant Dossier</h3>
        <span style="font-size: 0.82rem; color: #64748b;" id="m-ref-subtitle">Application Reference: #</span>
      </div>
      <div style="display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap;">
        <span id="m-doc-locked-badge" style="display:none; padding:0.35rem 0.75rem; font-size:0.78rem; background:#fff1f2; color:#9f1239; border:1px solid #fecdd3; border-radius:5px; font-weight:700;"><i class="fas fa-lock"></i> Card Locked Until Payment Verified</span>
        <a id="m-doc-view-btn" href="#" target="_blank" style="padding:0.35rem 0.7rem; font-size:0.78rem; background:#0f2c59; color:#ffffff; border-radius:5px; text-decoration:none; font-weight:700;"><i class="fas fa-eye"></i> View Application</a>
        <a id="m-doc-pdf-btn" href="#" target="_blank" style="padding:0.35rem 0.7rem; font-size:0.78rem; background:#b91c1c; color:#ffffff; border-radius:5px; text-decoration:none; font-weight:700;"><i class="fas fa-file-pdf"></i> View PDF</a>
        <a id="m-doc-dl-btn" href="#" style="padding:0.35rem 0.7rem; font-size:0.78rem; background:#1e40af; color:#ffffff; border-radius:5px; text-decoration:none; font-weight:700;"><i class="fas fa-download"></i> Download PDF</a>
        <a id="m-doc-print-btn" href="#" target="_blank" style="padding:0.35rem 0.7rem; font-size:0.78rem; background:#ffffff; color:#0f2c59; border:1px solid #cbd5e1; border-radius:5px; text-decoration:none; font-weight:700;"><i class="fas fa-print"></i> Print Application</a>
        <a id="m-doc-receipt-btn" href="#" target="_blank" style="display:none; padding:0.35rem 0.7rem; font-size:0.78rem; background:#15803d; color:#ffffff; border-radius:5px; text-decoration:none; font-weight:700;"><i class="fas fa-receipt"></i> Payment Receipt</a>
        <button type="button" onclick="closeDossier()" aria-label="Close Dossier" style="background: none; border: none; font-size: 1.25rem; color: #64748b; cursor: pointer; padding: 0.4rem; min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>

    <!-- Modal Body -->
    <div class="adm-dossier-body" style="padding: 1.75rem; display: flex; flex-direction: column; gap: 1.5rem;">

      <!-- Passing Year Mismatch Advisory Warning Banner -->
      <div id="m-mismatch-banner" style="display:none; background:#fffbeb; border:1.5px solid #f59e0b; border-radius:8px; padding:0.9rem 1.1rem; color:#92400e; font-size:0.86rem;"></div>
      
      <!-- Section 1: Personal Particulars -->
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
        <h4 style="margin: 0 0 0.75rem; font-size: 0.95rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px;">
          <i class="fas fa-user"></i> 1. Personal &amp; Contact Particulars
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 0.75rem; font-size: 0.88rem;">
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
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 0.75rem; font-size: 0.88rem;">
          <div><strong style="color: #64748b;">Degree / Course Type:</strong> <span id="m-degree-type" style="font-weight: 700; color: #3730a3;"></span></div>
          <div><strong style="color: #64748b;">Target Program:</strong> <span id="m-prog" style="font-weight: 600; color: #0f172a;"></span></div>
          <div><strong style="color: #64748b;">Course Duration:</strong> <span id="m-duration" style="font-weight: 600; color: #0f172a;"></span></div>
          <div><strong style="color: #64748b;">Total Semesters:</strong> <span id="m-semesters" style="font-weight: 700; color: #0f172a;"></span></div>
          <div><strong style="color: #64748b;">Application ID / Roll No:</strong> <span id="m-app-roll" style="font-family:monospace; font-weight:700; color:#7c3aed;"></span></div>
          <div><strong style="color: #64748b;">Application Record Year:</strong> <span id="m-app-year" style="font-weight: 700; color: #0f172a;"></span></div>
          <div><strong style="color: #64748b;">Academic Session:</strong> <span id="m-session" style="font-weight: 700; color: var(--rmu-primary);"></span></div>
          <div><strong style="color: #64748b;">Actual Passing Year (Student Selection):</strong> <span id="m-passing-year" style="font-weight: 700; color: #0f172a;"></span></div>
          <div><strong style="color: #64748b;">Expected Passing Year (Advisory):</strong> <span id="m-expected-year" style="font-weight: 700; color: #0369a1;"></span></div>
          <div><strong style="color: #64748b;">Oversight Status:</strong> <span id="m-oversight-status" style="font-weight: 700;"></span></div>
          <div><strong style="color: #64748b;">SSC Examination:</strong> <span id="m-ssc"></span></div>
          <div><strong style="color: #64748b;">HSC Examination:</strong> <span id="m-hsc"></span></div>
          <div><strong style="color: #64748b;">Last College:</strong> <span id="m-college"></span></div>
          <div><strong style="color: #64748b;">Quota Claim:</strong> <span id="m-quota"></span></div>
          <div style="grid-column: 1 / -1;" id="m-docs-container">
            <strong style="color: #64748b;">Academic Documents:</strong> 
            <span id="m-docs"></span>
          </div>
        </div>

        <!-- Original Applicant Submission vs Verified Record -->
        <div id="m-orig-vs-current" style="margin-top:0.9rem; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:0.75rem 0.9rem; font-size:0.8rem;"></div>

        <!-- Timestamped Audit Edit History -->
        <div id="m-edit-history-box" style="display:none; margin-top:0.75rem; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:0.75rem 0.9rem; font-size:0.8rem;"></div>

        <?php if (is_admin_manager_role()): ?>
          <div style="margin-top:1rem; padding-top:1rem; border-top:1px dashed #cbd5e1; display:flex; flex-direction:column; gap:0.85rem;">
            <form action="<?= APP_URL ?>/admin/admissions.php<?= e($admFilterQuery) ?>" method="POST" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:0.65rem 0.85rem;">
              <?= csrf_field() ?>
              <?= $admReturnHiddenInputs ?>
              <input type="hidden" name="action" value="approve_historical">
              <input type="hidden" name="id" id="m-hist-id" value="">
              <span style="font-size:0.78rem; font-weight:700; color:#92400e;"><i class="fas fa-history"></i> Historical Verification:</span>
              <input type="text" name="approval_note" id="m-hist-note" class="form-control" style="flex:1; min-width:min(100%, 180px); padding:0.35rem 0.65rem; font-size:0.82rem;" placeholder="Verification note (e.g. Verified against 2011 archival ledger)">
              <button type="submit" class="btn-primary" style="background:#0f766e; border:none; padding:0.42rem 0.85rem; font-size:0.8rem;">
                <i class="fas fa-check-double"></i> Approve Historical
              </button>
            </form>

            <details style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:0.7rem 0.9rem;">
              <summary style="cursor:pointer; font-size:0.82rem; font-weight:700; color:#1e3a8a;">
                <i class="fas fa-edit"></i> Official Academic Edit (Mandatory Reason Logged; Original Submission Preserved)
              </summary>
              <form action="<?= APP_URL ?>/admin/admissions.php<?= e($admFilterQuery) ?>" method="POST" style="margin-top:0.75rem;">
                <?= csrf_field() ?>
                <?= $admReturnHiddenInputs ?>
                <input type="hidden" name="action" value="official_academic_edit">
                <input type="hidden" name="id" id="m-edit-id" value="">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 150px), 1fr)); gap:0.65rem; margin-bottom:0.65rem;">
                  <div>
                    <label class="form-label" style="font-size:0.76rem; font-weight:600;">Application Year (2015+)</label>
                    <input type="number" min="2015" max="2100" name="application_year" id="m-edit-app-year" class="form-control" style="font-size:0.84rem;">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:0.76rem; font-weight:600;">Academic Session *</label>
                    <input type="text" name="academic_session" id="m-edit-session" list="admSessionOptions" class="form-control" required style="font-size:0.84rem;">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:0.76rem; font-weight:600;">Actual Passing Year</label>
                    <input type="text" name="passing_year" id="m-edit-passing-year" list="admPassingYearOptions" class="form-control" placeholder="e.g. 2016" style="font-size:0.84rem;">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:0.76rem; font-weight:600;">Program</label>
                    <select name="program_id" id="m-edit-program" class="form-select" style="font-size:0.84rem;" onchange="syncDossierEditStructure()">
                      <?php foreach ($programsList as $pl): ?>
                        <option value="<?= (int)$pl['id'] ?>" data-degree="<?= e($pl['degree_type'] ?? 'Bachelor / Honours') ?>" data-duration-years="<?= number_format((float)($pl['duration_years'] ?? 4.0), 1, '.', '') ?>" data-semesters="<?= (int)($pl['total_semesters'] ?? 8) ?>">
                          <?= e($pl['title_en']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="form-label" style="font-size:0.76rem; font-weight:600;">Degree Type</label>
                    <select name="degree_type" id="m-edit-degree" class="form-select" style="font-size:0.84rem;">
                      <option value="Bachelor / Honours">Bachelor / Honours</option>
                      <option value="B.Ed">B.Ed</option>
                      <option value="Master's">Master's</option>
                    </select>
                  </div>
                  <div>
                    <label class="form-label" style="font-size:0.76rem; font-weight:600;">Course Duration (Years)</label>
                    <input type="number" step="0.5" min="0.5" max="6.0" name="duration_years" id="m-edit-duration-years" class="form-control" style="font-size:0.84rem;">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:0.76rem; font-weight:600;">Total Semesters</label>
                    <input type="number" min="1" max="12" name="total_semesters" id="m-edit-total-semesters" class="form-control" style="font-size:0.84rem;">
                  </div>
                </div>
                <div style="display:flex; gap:0.65rem; flex-wrap:wrap; align-items:center;">
                  <input type="text" name="reason" id="m-edit-reason" required class="form-control" style="flex:1; min-width:min(100%, 210px); font-size:0.84rem;" placeholder="Mandatory Reason for official academic record edit *">
                  <button type="submit" class="btn-primary" style="background:#1e40af; border:none; padding:0.48rem 1rem; font-size:0.82rem;">
                    <i class="fas fa-save"></i> Save Official Edit
                  </button>
                </div>
              </form>
            </details>
          </div>
        <?php endif; ?>
      </div>

      <!-- Section 3: ৳150 Payment Status & Verification -->
      <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1.25rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
          <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.5px;">
            <i class="fas fa-file-invoice-dollar"></i> 3. ৳150 Application Fee Payment Status
          </h4>
          <?php if (is_admin_manager_role()): ?>
            <form action="<?= APP_URL ?>/admin/admissions.php<?= e($admFilterQuery) ?>" method="POST" id="m-verify-pay-form" style="display:none; gap:0.4rem; align-items:center; flex-wrap:wrap;">
              <?= csrf_field() ?>
              <?= $admReturnHiddenInputs ?>
              <input type="hidden" name="action" value="verify_payment">
              <input type="hidden" name="id" id="m-verify-pay-id" value="">
              <input type="text" name="transaction_id" id="m-verify-pay-trx" class="form-control" placeholder="TrxID (optional)" style="width:150px; max-width:100%; padding:0.3rem 0.5rem; font-size:0.78rem;">
              <button type="submit" class="btn-primary" style="background:#15803d; padding:0.35rem 0.75rem; font-size:0.78rem;">
                <i class="fas fa-check"></i> Verify ৳150 Payment
              </button>
            </form>
          <?php endif; ?>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 0.75rem; font-size: 0.88rem; align-items: center;">
          <div><strong style="color: #4b5563;">Status:</strong> <span id="m-pay-status" style="font-weight: 700;"></span></div>
          <div><strong style="color: #4b5563;">Amount:</strong> ৳150.00 BDT</div>
          <div><strong style="color: #4b5563;">Transaction ID:</strong> <span id="m-pay-trx" style="font-family: monospace; font-weight: 600;"></span></div>
          <div><strong style="color: #4b5563;">Payment Method:</strong> <span id="m-pay-method"></span></div>
          <div><strong style="color: #4b5563;">Gateway Session:</strong> <span id="m-pay-session" style="font-family: monospace; font-size: 0.8rem;"></span></div>
          <div><strong style="color: #4b5563;">Paid / Verified Date:</strong> <span id="m-pay-date"></span></div>
          <div style="grid-column: 1 / -1; display:none; color:#b91c1c; font-weight:600;" id="m-pay-failure-row"></div>
          <div id="m-pay-slip-container"></div>
        </div>
      </div>

      <!-- Section 4: Admissions Committee Review & Provisioning -->
      <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1.25rem;">
        <h4 style="margin: 0 0 0.75rem; font-size: 0.95rem; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
          <i class="fas fa-tasks"></i> 4. Committee Decision &amp; Actions
        </h4>

        <form action="<?= APP_URL ?>/admin/admissions.php<?= e($admFilterQuery) ?>" method="POST" id="dossierForm">
          <?= csrf_field() ?>
          <?= $admReturnHiddenInputs ?>
          <input type="hidden" name="id" id="m-form-id" value="">
          <input type="hidden" name="action" id="m-form-action" value="update_status">

          <div class="admissions-dossier-decision-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
              <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Set Status</label>
              <select name="status" id="m-status-select" class="form-select">
                <option value="Pending">Pending</option>
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

          <div style="display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap; align-items:center;">
            <a id="m-result-mgmt-btn" href="#" style="display:none; background:#7c3aed; color:#fff; border-radius:6px; padding:0.6rem 1.25rem; font-size:0.88rem; font-weight:700; text-decoration:none; margin-right:auto;">
              <i class="fas fa-calculator"></i> Result Management
            </a>
            <?php if (is_admin_manager_role()): ?>
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
            <?php else: ?>
              <span style="font-size:0.82rem; color:#64748b; font-style:italic;"><i class="fas fa-lock"></i> Committee decision actions require Administrator privileges.</span>
            <?php endif; ?>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<script>
function escapeHtmlDossier(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function openDossier(id) {
  var dataElem = document.getElementById('dossier-data-' + id);
  if (!dataElem) return;
  var d = JSON.parse(dataElem.textContent);

  document.getElementById('m-form-id').value = d.id;
  document.getElementById('m-title').textContent = 'Dossier: ' + d.full_name;
  document.getElementById('m-ref-subtitle').textContent = 'Application Ref: ' + d.application_ref + ' • Submitted: ' + (d.created_at || 'N/A');

  var docBaseUrl = '<?= APP_URL ?>/admission-document.php?id=' + encodeURIComponent(d.id);
  var isPaidDoc = ((d.payment_status || '') === 'Paid');
  var mDocLocked = document.getElementById('m-doc-locked-badge');
  var mDocView = document.getElementById('m-doc-view-btn');
  var mDocPdf = document.getElementById('m-doc-pdf-btn');
  var mDocDl = document.getElementById('m-doc-dl-btn');
  var mDocPrint = document.getElementById('m-doc-print-btn');
  var mDocReceipt = document.getElementById('m-doc-receipt-btn');

  if (mDocLocked) mDocLocked.style.display = isPaidDoc ? 'none' : 'inline-flex';
  if (mDocView) {
    mDocView.href = docBaseUrl + '&doc=application&action=view';
    mDocView.style.display = isPaidDoc ? 'inline-flex' : 'none';
  }
  if (mDocPdf) {
    mDocPdf.href = docBaseUrl + '&doc=application&action=pdf';
    mDocPdf.style.display = isPaidDoc ? 'inline-flex' : 'none';
  }
  if (mDocDl) {
    mDocDl.href = docBaseUrl + '&doc=application&action=download';
    mDocDl.style.display = isPaidDoc ? 'inline-flex' : 'none';
  }
  if (mDocPrint) {
    mDocPrint.href = docBaseUrl + '&doc=application&action=print';
    mDocPrint.style.display = isPaidDoc ? 'inline-flex' : 'none';
  }
  if (mDocReceipt) {
    if (isPaidDoc) {
      mDocReceipt.href = docBaseUrl + '&doc=receipt&action=view';
      mDocReceipt.style.display = 'inline-flex';
    } else {
      mDocReceipt.style.display = 'none';
    }
  }

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

  document.getElementById('m-degree-type').textContent = d.computed_degree_type || d.degree_type || d.prog_degree_type || 'Bachelor / Honours';
  document.getElementById('m-duration').textContent = d.computed_duration_label || '4 Years (8 Semesters)';
  var semEl = document.getElementById('m-semesters');
  if (semEl) semEl.textContent = (d.computed_total_semesters || d.total_semesters || 8) + ' Semesters';
  document.getElementById('m-prog').textContent = d.program_title || d.interested_subject || 'N/A';
  var appRollParts = [];
  appRollParts.push('App ID: ' + (d.application_id || d.application_ref || 'N/A'));
  appRollParts.push('Roll No: ' + (d.roll_number || d.exam_roll || 'Pending'));
  document.getElementById('m-app-roll').textContent = appRollParts.join(' • ');
  var appYrEl = document.getElementById('m-app-year');
  if (appYrEl) appYrEl.textContent = d.computed_application_year || d.application_year || 'N/A';
  document.getElementById('m-session').textContent = d.academic_session || 'Not Set';
  document.getElementById('m-passing-year').textContent = d.passing_year || 'Not Selected';

  var ev = d.oversight_eval || {};
  var expEl = document.getElementById('m-expected-year');
  if (d.computed_expected_passing_year) {
    expEl.textContent = d.computed_expected_passing_year + (ev.formula ? (' (' + ev.formula + ')') : ' (Advisory)');
  } else {
    expEl.textContent = 'N/A';
  }
  var ovEl = document.getElementById('m-oversight-status');
  ovEl.textContent = ev.status_label || 'N/A';
  if (ev.status_key === 'differs') {
    ovEl.style.color = '#b45309';
  } else if (ev.status_key === 'approved_historical' || ev.status_key === 'match') {
    ovEl.style.color = '#15803d';
  } else {
    ovEl.style.color = '#64748b';
  }

  var mismatchBanner = document.getElementById('m-mismatch-banner');
  if (mismatchBanner) {
    if (ev.status_key === 'differs') {
      mismatchBanner.style.display = 'block';
      mismatchBanner.innerHTML =
        '<i class="fas fa-exclamation-triangle" style="color:#d97706;"></i> ' +
        '<strong>Passing Year Mismatch Warning:</strong> Applicant selected Actual Passing Year <strong>' + escapeHtmlDossier(d.passing_year) + '</strong>, ' +
        'which differs from Calculated Expected Passing Year <strong>' + escapeHtmlDossier(d.computed_expected_passing_year) + '</strong> (' + escapeHtmlDossier(ev.status_label) + '). ' +
        'Do <strong>not</strong> auto-reject — review academic records, keep as Submitted/Under Review, edit officially with audit reason, or approve as Historical Record.';
    } else {
      mismatchBanner.style.display = 'none';
      mismatchBanner.innerHTML = '';
    }
  }

  var origVsCurEl = document.getElementById('m-orig-vs-current');
  if (origVsCurEl) {
    var orig = d.original_submission || {};
    var curDurLbl = d.computed_duration_label || '';
    var origDurLbl = orig.course_duration || curDurLbl;
    var hasMod = (
      String(orig.academic_session || '') !== String(d.academic_session || '') ||
      String(orig.passing_year || '') !== String(d.passing_year || '') ||
      String(orig.interested_subject || '') !== String(d.program_title || d.interested_subject || '') ||
      String(origDurLbl || '') !== String(curDurLbl || '') ||
      String(orig.total_semesters || '') !== String(d.computed_total_semesters || '') ||
      String(orig.application_year || '') !== String(d.computed_application_year || '')
    );
    origVsCurEl.innerHTML =
      '<div style="font-weight:700; color:#0f172a; margin-bottom:4px;"><i class="fas fa-shield-alt" style="color:#0284c7;"></i> Original Applicant Submission Snapshot ' +
      (hasMod ? '<span style="background:#fef3c7; color:#92400e; padding:1px 6px; border-radius:4px; font-size:0.72rem; margin-left:4px;">Modified by Admin Audit</span>' : '<span style="background:#dcfce7; color:#15803d; padding:1px 6px; border-radius:4px; font-size:0.72rem; margin-left:4px;">Unmodified</span>') +
      '</div>' +
      '<div style="color:#475569; line-height:1.5;">' +
        '<strong>Submitted:</strong> Program: <em>' + escapeHtmlDossier(orig.interested_subject || 'N/A') + '</em> &bull; ' +
        'Session: <em>' + escapeHtmlDossier(orig.academic_session || 'N/A') + '</em> &bull; ' +
        'Duration: <em>' + escapeHtmlDossier(origDurLbl || 'N/A') + ' (' + escapeHtmlDossier(orig.total_semesters || '-') + ' Sem)</em> &bull; ' +
        'Actual Pass Yr: <em>' + escapeHtmlDossier(orig.passing_year || 'N/A') + '</em> &bull; ' +
        'Expected Yr: <em>' + escapeHtmlDossier(orig.expected_passing_year || 'N/A') + '</em> &bull; ' +
        'App Yr: <em>' + escapeHtmlDossier(orig.application_year || 'N/A') + '</em>' +
      '</div>';
  }

  var histBox = document.getElementById('m-edit-history-box');
  if (histBox) {
    var edits = Array.isArray(d.edit_history) ? d.edit_history : [];
    if (edits.length > 0) {
      histBox.style.display = 'block';
      var hHtml = '<div style="font-weight:700; color:#0f172a; margin-bottom:4px;"><i class="fas fa-history" style="color:#7c3aed;"></i> Recorded Admin Academic Audit Trail</div><ul style="margin:0; padding-left:1.1rem; color:#334155;">';
      edits.slice(0, 10).forEach(function(it) {
        var who = it.admin_full_name || it.admin_username || 'Admin';
        hHtml += '<li style="margin-bottom:3px;"><strong>' + escapeHtmlDossier(it.field_name) + '</strong>: <code>' + escapeHtmlDossier(it.old_value || '—') + '</code> &rarr; <code>' + escapeHtmlDossier(it.new_value || '—') + '</code> <span style="color:#64748b;">(by ' + escapeHtmlDossier(who) + ' at ' + escapeHtmlDossier(it.created_at) + ')</span> &mdash; <em>' + escapeHtmlDossier(it.reason) + '</em></li>';
      });
      hHtml += '</ul>';
      histBox.innerHTML = hHtml;
    } else {
      histBox.style.display = 'none';
      histBox.innerHTML = '';
    }
  }

  var histIdEl = document.getElementById('m-hist-id');
  if (histIdEl) histIdEl.value = d.id;
  var histNoteEl = document.getElementById('m-hist-note');
  if (histNoteEl) histNoteEl.value = d.historical_approval_note || '';

  var editIdEl = document.getElementById('m-edit-id');
  if (editIdEl) editIdEl.value = d.id;
  var editAppYrEl = document.getElementById('m-edit-app-year');
  if (editAppYrEl) editAppYrEl.value = d.computed_application_year || d.application_year || '';
  var editSessEl = document.getElementById('m-edit-session');
  if (editSessEl) editSessEl.value = d.academic_session || '';
  var editPassEl = document.getElementById('m-edit-passing-year');
  if (editPassEl) editPassEl.value = d.passing_year || '';
  var editProgEl = document.getElementById('m-edit-program');
  if (editProgEl && d.program_id) editProgEl.value = String(d.program_id);
  var editDegEl = document.getElementById('m-edit-degree');
  if (editDegEl) editDegEl.value = d.computed_degree_type || d.degree_type || d.prog_degree_type || 'Bachelor / Honours';
  var editDurEl = document.getElementById('m-edit-duration-years');
  if (editDurEl) editDurEl.value = d.computed_duration_years || d.duration_years || '';
  var editSemEl = document.getElementById('m-edit-total-semesters');
  if (editSemEl) editSemEl.value = d.computed_total_semesters || d.total_semesters || '';
  var editReasonEl = document.getElementById('m-edit-reason');
  if (editReasonEl) editReasonEl.value = '';

  document.getElementById('m-ssc').textContent = (d.ssc_exam || 'SSC') + ' - GPA ' + (d.ssc_gpa || '0.00') + ' (Board: ' + (d.ssc_board || 'N/A') + ', Roll: ' + (d.ssc_roll || 'N/A') + ')';
  document.getElementById('m-hsc').textContent = (d.hsc_exam || 'HSC') + ' - GPA ' + (d.hsc_gpa || '0.00') + ' (Board: ' + (d.hsc_board || 'N/A') + ', Roll: ' + (d.hsc_roll || 'N/A') + ')';
  document.getElementById('m-college').textContent = d.last_college || 'N/A';
  document.getElementById('m-quota').textContent = (d.quota === 'YES') ? 'Yes (Quota Claimed)' : 'None';

  var resMgmtBtn = document.getElementById('m-result-mgmt-btn');
  if (resMgmtBtn) {
    if (d.status === 'Accepted' && d.student_table_id) {
      resMgmtBtn.href = '<?= APP_URL ?>/admin/student-detail.php?id=' + d.student_table_id + '#cgpaGeneratorPanel';
      resMgmtBtn.style.display = 'inline-flex';
      resMgmtBtn.style.alignItems = 'center';
      resMgmtBtn.style.gap = '6px';
    } else {
      resMgmtBtn.style.display = 'none';
    }
  }

  var docsSpan = document.getElementById('m-docs');
  var docHref = d.document_url || (d.academic_documents ? ('<?= APP_URL ?>/storage/uploads/documents/' + encodeURIComponent(d.academic_documents)) : '');
  if (docHref) {
    docsSpan.innerHTML = '<a href="' + escapeHtmlDossier(docHref) + '" target="_blank" style="color:#2563eb; font-weight:700; text-decoration:underline;"><i class="fas fa-file-download"></i> View / Download Uploaded Documents</a>';
  } else {
    docsSpan.textContent = 'No documents attached';
  }

  var payStatus = d.payment_status || 'Pending';
  var payColor = (payStatus === 'Paid') ? '#15803d' : ((payStatus === 'Failed') ? '#b91c1c' : '#b45309');
  var payStatusElem = document.getElementById('m-pay-status');
  payStatusElem.textContent = payStatus;
  payStatusElem.style.color = payColor;

  var vPayForm = document.getElementById('m-verify-pay-form');
  var vPayId = document.getElementById('m-verify-pay-id');
  if (vPayForm && vPayId) {
    vPayId.value = d.id;
    vPayForm.style.display = (['Pending', 'Under Verification', 'Failed'].indexOf(payStatus) !== -1) ? 'flex' : 'none';
  }

  document.getElementById('m-pay-trx').textContent = d.transaction_id || 'Awaiting Payment';
  document.getElementById('m-pay-method').textContent = (d.payment_method || d.tx_gateway_provider || 'bKash') + ' (' + (d.tx_gateway_mode || d.payment_gateway_mode || 'sandbox') + ')';
  var paySessEl = document.getElementById('m-pay-session');
  if (paySessEl) paySessEl.textContent = d.gateway_session_id || 'Not initiated';
  document.getElementById('m-pay-date').textContent = d.paid_at || d.validated_once_at || 'Not paid yet';

  var failRow = document.getElementById('m-pay-failure-row');
  if (failRow) {
    if (d.validated_once_at && payStatus === 'Paid') {
      failRow.style.display = 'block';
      failRow.style.color = '#15803d';
      failRow.innerHTML = '<i class="fas fa-lock"></i> Single-Use Gateway Verification Locked At: ' + escapeHtmlDossier(d.validated_once_at);
    } else if (d.tx_failure_reason) {
      failRow.style.display = 'block';
      failRow.style.color = '#b91c1c';
      failRow.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Gateway Outcome (' + escapeHtmlDossier(d.gateway_status || payStatus) + '): ' + escapeHtmlDossier(d.tx_failure_reason);
    } else {
      failRow.style.display = 'none';
      failRow.innerHTML = '';
    }
  }

  var slipCont = document.getElementById('m-pay-slip-container');
  var slipHref = d.payment_slip_url || (d.payment_screenshot ? ('<?= APP_URL ?>/storage/uploads/payments/' + encodeURIComponent(d.payment_screenshot)) : '');
  if (slipHref && isPaidDoc) {
    slipCont.innerHTML = '<a href="' + escapeHtmlDossier(slipHref) + '" target="_blank" style="font-size:0.8rem; color:#2563eb; font-weight:600;"><i class="fas fa-image"></i> View Gateway Slip</a>';
  } else {
    slipCont.innerHTML = '';
  }

  document.getElementById('m-status-select').value = d.status || 'Submitted';
  document.getElementById('m-admin-notes').value = d.admin_notes || '';

  var modal = document.getElementById('dossierModal');
  modal.style.display = 'flex';
  if (document.body) document.body.classList.add('rmu-drawer-open');
}

function syncDossierEditStructure() {
  var sel = document.getElementById('m-edit-program');
  var deg = document.getElementById('m-edit-degree');
  var dur = document.getElementById('m-edit-duration-years');
  var sem = document.getElementById('m-edit-total-semesters');
  if (!sel || sel.selectedIndex < 0) return;
  var opt = sel.options[sel.selectedIndex];
  var dType = opt ? opt.getAttribute('data-degree') : '';
  var dDur = opt ? opt.getAttribute('data-duration-years') : '';
  var dSem = opt ? opt.getAttribute('data-semesters') : '';
  if (deg && dType) deg.value = dType;
  if (dur && dDur) dur.value = dDur;
  if (sem && dSem) sem.value = dSem;
}
function syncDossierEditDegree() {
  syncDossierEditStructure();
}

function closeDossier() {
  var modal = document.getElementById('dossierModal');
  if (modal) modal.style.display = 'none';
  if (document.body) document.body.classList.remove('rmu-drawer-open');
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
window.addEventListener('click', function(event) {
  var modal = document.getElementById('dossierModal');
  if (event.target === modal) {
    closeDossier();
  }
});
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') {
    closeDossier();
  }
});
</script>

<datalist id="admSessionOptions">
  <?php foreach ($allSessionsList as $sessItem): ?>
    <option value="<?= e($sessItem['session_name']) ?>"></option>
  <?php endforeach; ?>
</datalist>
<datalist id="admPassingYearOptions">
  <?php foreach ($allPassingYearsList as $yrItem): ?>
    <option value="<?= (int)$yrItem['year_value'] ?>"></option>
  <?php endforeach; ?>
</datalist>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
