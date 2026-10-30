<?php
/**
 * Admin Panel: Admissions Management & Review Workflow
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
$adminPageTitle = 'Admissions Review';
$db = getDb();

// Handle Status Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $adminNotes = trim($_POST['admin_notes'] ?? '');

        $validStatuses = ['Submitted', 'Under Review', 'Additional Information Required', 'Accepted', 'Rejected'];
        if (in_array($newStatus, $validStatuses, true) && $id > 0) {
            $update = $db->prepare("UPDATE admissions SET status = ?, admin_notes = ? WHERE id = ?");
            $update->execute([$newStatus, $adminNotes, $id]);
            log_audit('ADMISSION_STATUS_UPDATED', "Updated application ID {$id} to status '{$newStatus}' with notes: '{$adminNotes}'");
            flash_set('success', "Application #{$id} status updated to '{$newStatus}' successfully.");
        } else {
            flash_set('error', 'Invalid status selection.');
        }
    }
    header("Location: " . APP_URL . "/admin/admissions.php");
    exit;
}

// Filters & Search
$statusFilter = $_GET['status'] ?? 'All';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($statusFilter !== 'All') {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $where[] = "(application_ref LIKE ? OR full_name LIKE ? OR mobile LIKE ? OR home_district LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count Total
$countStmt = $db->prepare("SELECT COUNT(*) FROM admissions $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Fetch Data
$query = "SELECT * FROM admissions $whereSql ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $db->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue($k + 1, $v);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$admissions = $stmt->fetchAll();

$totalPages = ceil($total / $perPage);

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0 0 0.2rem;">Admissions Review Console</h2>
    <span style="color: #64748b; font-size: 0.88rem;">Review, evaluate, and update applicant statuses for Fall 2026 intake.</span>
  </div>
</div>

<!-- Search & Filter Bar -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem 1.5rem; margin-bottom: 2rem;">
  <form action="<?= APP_URL ?>/admin/admissions.php" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
    <div style="flex: 1; min-width: 240px;">
      <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by Ref ID, Candidate Name, or Mobile..." class="form-control" style="padding: 0.65rem 1rem;">
    </div>

    <div style="min-width: 180px;">
      <select name="status" class="form-select" style="padding: 0.65rem 1rem;">
        <option value="All" <?= ($statusFilter === 'All') ? 'selected' : '' ?>>All Statuses</option>
        <option value="Submitted" <?= ($statusFilter === 'Submitted') ? 'selected' : '' ?>>Submitted</option>
        <option value="Under Review" <?= ($statusFilter === 'Under Review') ? 'selected' : '' ?>>Under Review</option>
        <option value="Accepted" <?= ($statusFilter === 'Accepted') ? 'selected' : '' ?>>Accepted</option>
        <option value="Additional Information Required" <?= ($statusFilter === 'Additional Information Required') ? 'selected' : '' ?>>Info Required</option>
        <option value="Rejected" <?= ($statusFilter === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
      </select>
    </div>

    <button type="submit" class="btn-primary" style="padding: 0.65rem 1.4rem;">
      <i class="fas fa-filter"></i> Apply Filter
    </button>
    <?php if (!empty($search) || $statusFilter !== 'All'): ?>
      <a href="<?= APP_URL ?>/admin/admissions.php" style="color: #ef4444; font-size: 0.88rem; font-weight: 600;">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Applications Table -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
  <div class="table-responsive">
    <table class="rmu-table" style="box-shadow: none;">
      <thead>
        <tr>
          <th>Reference &bull; Date</th>
          <th>Applicant Particulars</th>
          <th>Scores &amp; Stream</th>
          <th>Program &amp; Quota</th>
          <th>Current Status</th>
          <th>Evaluate &amp; Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($admissions)): ?>
          <?php foreach ($admissions as $adm): ?>
            <tr>
              <td>
                <strong style="color: var(--rmu-primary); font-size: 0.92rem; display: block;"><?= e($adm['application_ref']) ?></strong>
                <small style="color: #64748b;"><?= format_date($adm['created_at']) ?></small>
                <div style="font-size: 0.72rem; color: #94a3b8;">IP: <?= e($adm['ip_address']) ?></div>
              </td>
              <td>
                <strong style="color: #0f172a; font-size: 0.95rem; display: block;"><?= e($adm['full_name']) ?></strong>
                <div style="font-size: 0.82rem; color: #334155;"><i class="fas fa-phone-alt"></i> <?= e($adm['mobile']) ?></div>
                <?php if (!empty($adm['email'])): ?>
                  <div style="font-size: 0.78rem; color: #64748b;"><?= e($adm['email']) ?></div>
                <?php endif; ?>
                <small style="color: #64748b;"><?= e($adm['last_college']) ?> (<?= e($adm['home_district']) ?>)</small>
              </td>
              <td style="font-size: 0.85rem;">
                <div><strong>SSC:</strong> <?= number_format($adm['ssc_gpa'], 2) ?> <?= !empty($adm['ssc_roll']) ? '(Roll: ' . e($adm['ssc_roll']) . ')' : '' ?></div>
                <div><strong>HSC:</strong> <?= number_format($adm['hsc_gpa'], 2) ?> <?= !empty($adm['hsc_roll']) ? '(Roll: ' . e($adm['hsc_roll']) . ')' : '' ?></div>
                <div style="color: #64748b;">Board: <?= e($adm['hsc_board'] ?? 'Dinajpur') ?> &bull; <?= e($adm['hsc_group']) ?></div>
                <?php if (!empty($adm['grad_applicable'])): ?>
                  <div style="margin-top: 3px; color: var(--rmu-primary); font-size: 0.78rem;">
                    <strong>Grad:</strong> <?= e($adm['grad_exam']) ?> (<?= e($adm['grad_result_type']) ?> <?= e($adm['grad_result']) ?>)
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <strong style="color: #0f172a; font-size: 0.9rem; display: block;"><?= e($adm['interested_subject']) ?></strong>
                <?php if ($adm['quota'] === 'YES'): ?>
                  <span style="background: #fef3c7; color: #92400e; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 4px;">
                    Quota Claimed
                  </span>
                <?php else: ?>
                  <small style="color: #94a3b8;">No quota</small>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $s = $adm['status'];
                  $color = ($s === 'Accepted') ? 'background:#d1fae5; color:#065f46;' : (($s === 'Under Review') ? 'background:#fef3c7; color:#92400e;' : (($s === 'Rejected') ? 'background:#fee2e2; color:#991b1b;' : 'background:#e0e7ff; color:#3730a3;'));
                ?>
                <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.78rem; font-weight: 700; <?= $color ?>">
                  <?= e($s) ?>
                </span>
                <?php if (($adm['payment_status'] ?? '') === 'Paid'): ?>
                  <div style="margin-top:4px;">
                    <span style="background:#dcfce7; color:#15803d; font-size:0.72rem; font-weight:700; padding:0.15rem 0.5rem; border-radius:4px; display:inline-block;">
                      ✓ ৳150 Paid (<?= e($adm['payment_method'] ?? 'bKash') ?>)
                    </span>
                    <?php if (!empty($adm['transaction_id'])): ?>
                      <div style="font-size:0.72rem; color:#b91c1c; font-family:monospace; margin-top:2px;">
                        Trx: <?= e($adm['transaction_id']) ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($adm['sender_number'])): ?>
                      <div style="font-size:0.7rem; color:#475569;">
                        Sender: <?= e($adm['sender_number']) ?>
                      </div>
                    <?php endif; ?>
                    <?php 
                      $slip = get_payment_screenshot_url($adm['payment_screenshot'] ?? null);
                      if ($slip): 
                    ?>
                      <div style="margin-top:3px;">
                        <a href="<?= e($slip) ?>" target="_blank" style="font-size:0.72rem; color:#2563eb; font-weight:700; text-decoration:underline;">
                          <i class="fas fa-receipt"></i> View Slip
                        </a>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($adm['student_id'])): ?>
                      <div style="font-size:0.72rem; color:#1e40af; font-family:monospace; margin-top:2px;">
                        ID: <?= e($adm['student_id']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <div style="margin-top:4px;">
                    <span style="background:#fee2e2; color:#b91c1c; font-size:0.72rem; font-weight:700; padding:0.15rem 0.5rem; border-radius:4px; display:inline-block;">
                      Fee Unpaid
                    </span>
                  </div>
                <?php endif; ?>
                <?php if (!empty($adm['admin_notes'])): ?>
                  <div style="font-size: 0.75rem; color: #64748b; margin-top: 4px; max-width: 180px;">
                    <em>"<?= e($adm['admin_notes']) ?>"</em>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <!-- Quick Inline Evaluation Modal / Form -->
                <form action="<?= APP_URL ?>/admin/admissions.php" method="POST" style="display: flex; flex-direction: column; gap: 0.4rem;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="id" value="<?= (int)$adm['id'] ?>">

                  <select name="status" class="form-select" style="padding: 0.3rem 0.5rem; font-size: 0.8rem;">
                    <option value="Submitted" <?= ($adm['status'] === 'Submitted') ? 'selected' : '' ?>>Submitted</option>
                    <option value="Under Review" <?= ($adm['status'] === 'Under Review') ? 'selected' : '' ?>>Under Review</option>
                    <option value="Additional Information Required" <?= ($adm['status'] === 'Additional Information Required') ? 'selected' : '' ?>>Info Required</option>
                    <option value="Accepted" <?= ($adm['status'] === 'Accepted') ? 'selected' : '' ?>>Accepted</option>
                    <option value="Rejected" <?= ($adm['status'] === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                  </select>

                  <input type="text" name="admin_notes" value="<?= e($adm['admin_notes'] ?? '') ?>" placeholder="Counselor feedback notes" style="padding: 0.3rem 0.5rem; font-size: 0.78rem; border: 1px solid #cbd5e1; border-radius: 4px;">

                  <button type="submit" class="btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.78rem; justify-content: center;">
                    Save Update
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">No admissions match the criteria.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div style="padding: 1rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: center;">
      <ul class="pagination" style="margin: 0;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
            <a href="<?= APP_URL ?>/admin/admissions.php?status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>" class="page-link"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

