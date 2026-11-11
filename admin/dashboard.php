<?php
/**
 * Admin Portal Dashboard Overview
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Dashboard Overview';
$db = getDb();

// Calculate System Statistics
$totalStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status != 'Deleted'")->fetchColumn();
$activeStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
$totalAdmissions = (int)$db->query("SELECT COUNT(*) FROM admissions")->fetchColumn();
$pendingAdmissions = get_pending_admissions_count();
$paidAdmissions = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE payment_status = 'Paid'")->fetchColumn();
$totalPrograms = (int)$db->query("SELECT COUNT(*) FROM programs WHERE is_active = 1")->fetchColumn();
$totalNotices = (int)$db->query("SELECT COUNT(*) FROM notices WHERE is_active = 1")->fetchColumn();
$unreadMessages = (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
$totalFaculty = (int)$db->query("SELECT COUNT(*) FROM faculty_members WHERE is_active = 1")->fetchColumn();
$totalRevenue = (float)$db->query("SELECT SUM(amount) FROM student_fees WHERE status = 'Paid'")->fetchColumn();
$enabledSessionsCount = (int)$db->query("SELECT COUNT(*) FROM academic_sessions WHERE is_enabled = 1")->fetchColumn();
$currentIntakeSession = get_current_academic_session_name('Fall 2026');

$oversightQueueCount = 0;
try {
    $ovAdmStmt = $db->query("SELECT a.academic_session, a.passing_year, a.degree_type, a.duration_years, COALESCE(a.historical_record_approved, 0) AS historical_record_approved, p.duration_years AS prog_duration_years FROM admissions a LEFT JOIN programs p ON a.program_id = p.id WHERE a.status != 'Rejected'");
    foreach ($ovAdmStmt->fetchAll(PDO::FETCH_ASSOC) as $ovRow) {
        $durY = !empty($ovRow['duration_years']) ? (float)$ovRow['duration_years'] : (!empty($ovRow['prog_duration_years']) ? (float)$ovRow['prog_duration_years'] : (($ovRow['degree_type'] === 'B.Ed') ? 1.0 : (($ovRow['degree_type'] === "Master's") ? 2.0 : 4.0)));
        $ev = evaluate_passing_year_difference($ovRow['academic_session'] ?? '', $ovRow['passing_year'] ?? null, $durY, !empty($ovRow['historical_record_approved']));
        if (!empty($ev['needs_review'])) $oversightQueueCount++;
    }
    $ovStStmt = $db->query("SELECT s.academic_session, s.batch, s.passing_year, s.degree_type, s.duration_years, COALESCE(s.historical_record_approved, 0) AS historical_record_approved, p.duration_years AS prog_duration_years FROM students s LEFT JOIN programs p ON s.program_id = p.id WHERE s.status != 'Deleted'");
    foreach ($ovStStmt->fetchAll(PDO::FETCH_ASSOC) as $ovSt) {
        $durY = !empty($ovSt['duration_years']) ? (float)$ovSt['duration_years'] : (!empty($ovSt['prog_duration_years']) ? (float)$ovSt['prog_duration_years'] : (($ovSt['degree_type'] === 'B.Ed') ? 1.0 : (($ovSt['degree_type'] === "Master's") ? 2.0 : 4.0)));
        $ev = evaluate_passing_year_difference($ovSt['academic_session'] ?: ($ovSt['batch'] ?? ''), $ovSt['passing_year'] ?? null, $durY, !empty($ovSt['historical_record_approved']));
        if (!empty($ev['needs_review'])) $oversightQueueCount++;
    }
} catch (Throwable $e) {
    $oversightQueueCount = 0;
}

// Recent Admissions
$recentAdmissions = $db->query("SELECT * FROM admissions ORDER BY id DESC LIMIT 6")->fetchAll();

// Recent Enrolled Students (exclude permanently deleted accounts)
$recentStudents = $db->query("
    SELECT s.*, p.title_en AS program_title 
    FROM students s 
    LEFT JOIN programs p ON s.program_id = p.id 
    WHERE s.status != 'Deleted'
    ORDER BY s.id DESC LIMIT 5
")->fetchAll();

// Recent Inquiries
$recentMessages = $db->query("SELECT * FROM contact_messages ORDER BY id DESC LIMIT 5")->fetchAll();

// Recent Audit Logs
$recentLogs = $db->query("SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.id DESC LIMIT 6")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div id="dashboardAjaxAlert" style="display: none; margin-bottom: 1.5rem;"></div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-size: 1.6rem; font-weight: 700; color: #0f172a; margin: 0 0 0.3rem;">
      <i class="fas fa-university" style="color: var(--rmu-primary);"></i> Master University Control Console
    </h2>
    <span style="color: #64748b; font-size: 0.9rem;">Live system overview of admissions, student portal accounts, academics, and revenue.</span>
  </div>
  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
    <a href="<?= APP_URL ?>/admin/students.php" class="btn-primary" style="padding: 0.6rem 1.1rem; font-size: 0.85rem;">
      <i class="fas fa-user-plus"></i> Enroll Student
    </a>
    <a href="<?= APP_URL ?>/admin/admissions.php" class="btn-primary" style="padding: 0.6rem 1.1rem; font-size: 0.85rem; background: #0284c7;">
      <i class="fas fa-user-graduate"></i> Review Applications
    </a>
    <a href="<?= APP_URL ?>/admin/academic-sessions.php" class="btn-primary" style="padding: 0.6rem 1.1rem; font-size: 0.85rem; background: #0f766e;">
      <i class="fas fa-calendar-alt"></i> Sessions &amp; Passing Years
    </a>
    <a href="<?= APP_URL ?>/admin/notices.php" class="btn-primary" style="padding: 0.6rem 1.1rem; font-size: 0.85rem; background: #16a34a;">
      <i class="fas fa-bullhorn"></i> Publish Notice
    </a>
  </div>
</div>

<!-- Stat Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
  <!-- Card 1: Students -->
  <a href="<?= APP_URL ?>/admin/students.php" style="text-decoration: none; color: inherit;">
    <div class="stat-card" style="transition: transform 0.2s; cursor: pointer;">
      <div class="stat-icon" style="background: #e0f2fe; color: #0369a1;">
        <i class="fas fa-users"></i>
      </div>
      <div>
        <div id="dashTotalStudentsCount" style="font-size: 1.7rem; font-weight: 800; color: #0f172a; line-height: 1.1;"><?= $totalStudents ?></div>
        <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Enrolled Students</div>
      </div>
    </div>
  </a>

  <!-- Card 2: Total Applications -->
  <a href="<?= APP_URL ?>/admin/admissions.php" style="text-decoration: none; color: inherit;">
    <div class="stat-card" style="transition: transform 0.2s; cursor: pointer;">
      <div class="stat-icon" style="background: #e0e7ff; color: #4338ca;">
        <i class="fas fa-user-graduate"></i>
      </div>
      <div>
        <div style="font-size: 1.7rem; font-weight: 800; color: #0f172a; line-height: 1.1;"><?= $totalAdmissions ?></div>
        <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Intake Applications</div>
      </div>
    </div>
  </a>

  <!-- Card 3: Pending Reviews -->
  <a href="<?= APP_URL ?>/admin/admissions.php?status=PendingQueue" style="text-decoration: none; color: inherit;">
    <div class="stat-card" style="transition: transform 0.2s; cursor: pointer;">
      <div class="stat-icon" style="background: #fef3c7; color: #b45309;">
        <i class="fas fa-clock"></i>
      </div>
      <div>
        <div style="font-size: 1.7rem; font-weight: 800; color: #0f172a; line-height: 1.1;"><?= $pendingAdmissions ?></div>
        <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Pending Reviews</div>
      </div>
    </div>
  </a>

  <!-- Card 4: Fees Collected -->
  <a href="<?= APP_URL ?>/admin/students.php" style="text-decoration: none; color: inherit;">
    <div class="stat-card" style="transition: transform 0.2s; cursor: pointer;">
      <div class="stat-icon" style="background: #dcfce7; color: #15803d;">
        <i class="fas fa-money-bill-wave"></i>
      </div>
      <div>
        <div style="font-size: 1.4rem; font-weight: 800; color: #0f172a; line-height: 1.1;">৳<?= number_format($totalRevenue) ?></div>
        <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Tuition Revenue</div>
      </div>
    </div>
  </a>

  <!-- Card 5: Programs -->
  <a href="<?= APP_URL ?>/admin/programs.php" style="text-decoration: none; color: inherit;">
    <div class="stat-card" style="transition: transform 0.2s; cursor: pointer;">
      <div class="stat-icon" style="background: #f1f5f9; color: #334155;">
        <i class="fas fa-graduation-cap"></i>
      </div>
      <div>
        <div style="font-size: 1.7rem; font-weight: 800; color: #0f172a; line-height: 1.1;"><?= $totalPrograms ?></div>
        <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Degree Programs</div>
      </div>
    </div>
  </a>

  <!-- Card 6: Inquiries -->
  <a href="<?= APP_URL ?>/admin/messages.php" style="text-decoration: none; color: inherit;">
    <div class="stat-card" style="transition: transform 0.2s; cursor: pointer;">
      <div class="stat-icon" style="background: #fee2e2; color: #b91c1c;">
        <i class="fas fa-envelope"></i>
      </div>
      <div>
        <div style="font-size: 1.7rem; font-weight: 800; color: #0f172a; line-height: 1.1;"><?= $unreadMessages ?></div>
        <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">New Inquiries</div>
      </div>
    </div>
  </a>

  <!-- Card 7: Academic Sessions -->
  <a href="<?= APP_URL ?>/admin/academic-sessions.php" style="text-decoration: none; color: inherit;">
    <div class="stat-card" style="transition: transform 0.2s; cursor: pointer;">
      <div class="stat-icon" style="background: #ccfbf1; color: #0f766e;">
        <i class="fas fa-calendar-check"></i>
      </div>
      <div>
        <div style="font-size: 1.7rem; font-weight: 800; color: #0f172a; line-height: 1.1;"><?= $enabledSessionsCount ?></div>
        <div style="font-size: 0.82rem; color: #64748b; font-weight: 500;">Sessions &bull; Current: <?= e($currentIntakeSession) ?></div>
      </div>
    </div>
  </a>

  <!-- Card 8: Passing Year Oversight -->
  <a href="<?= APP_URL ?>/admin/academic-sessions.php?tab=oversight" style="text-decoration: none; color: inherit;">
    <div class="stat-card" style="transition: transform 0.2s; cursor: pointer; <?= $oversightQueueCount > 0 ? 'border:1px solid #f59e0b;' : '' ?>">
      <div class="stat-icon" style="background: <?= $oversightQueueCount > 0 ? '#fef3c7' : '#dcfce7' ?>; color: <?= $oversightQueueCount > 0 ? '#b45309' : '#15803d' ?>;">
        <i class="fas fa-balance-scale"></i>
      </div>
      <div>
        <div style="font-size: 1.7rem; font-weight: 800; color: #0f172a; line-height: 1.1;"><?= $oversightQueueCount ?></div>
        <div style="font-size: 0.82rem; color: #64748b; font-weight: 500;">Passing Oversight</div>
      </div>
    </div>
  </a>
</div>

<!-- Two Columns Layout -->
<style>
  .admin-dash-grid > * { min-width: 0; }
  @media (max-width: 960px) { .admin-dash-grid { grid-template-columns: 1fr !important; } }
</style>
<div class="admin-dash-grid" style="display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 2rem; margin-bottom: 2.5rem;">
  
  <!-- Left Column: Recent Applications & Enrolled Students -->
  <div style="display: flex; flex-direction: column; gap: 2rem; min-width: 0;">
    
    <!-- Table 1: Recent Applications -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">
          <i class="fas fa-file-signature" style="color: var(--rmu-primary);"></i> Recent Admission Applications
        </h3>
        <a href="<?= APP_URL ?>/admin/admissions.php" style="font-size: 0.85rem; font-weight: 600; color: var(--rmu-primary);">View All (<?= $totalAdmissions ?>) &rarr;</a>
      </div>

      <div class="table-responsive">
        <table class="rmu-table" style="box-shadow: none; border: none; margin: 0;">
          <thead>
            <tr>
              <th>Ref &bull; Candidate</th>
              <th>Program</th>
              <th>Payment &bull; TrxID</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($recentAdmissions)): ?>
              <?php foreach ($recentAdmissions as $adm): ?>
                <tr>
                  <td>
                    <strong style="color: var(--rmu-primary); display: block; font-size: 0.85rem; font-family: monospace;"><?= e($adm['application_ref']) ?></strong>
                    <span style="font-weight: 600; color: #0f172a; font-size: 0.92rem;"><?= e($adm['full_name']) ?></span>
                    <div style="font-size: 0.78rem; color: #64748b;"><?= e($adm['mobile']) ?></div>
                  </td>
                  <td style="font-size: 0.85rem; color: #334155;">
                    <?= e($adm['interested_subject']) ?>
                    <?php if (!empty($adm['academic_session'])): ?>
                      <small style="display: block; color: #64748b; font-size: 0.74rem;"><?= e($adm['academic_session']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td style="font-size: 0.82rem;">
                    <?php if (($adm['payment_status'] ?? '') === 'Paid'): ?>
                      <span style="color: #15803d; font-weight: 700;">✓ Paid (৳150)</span>
                      <div style="font-family: monospace; font-size: 0.72rem; color: #b91c1c;"><?= e($adm['transaction_id'] ?? '') ?></div>
                    <?php else: ?>
                      <span style="color: #b91c1c; font-weight: 600;">Unpaid</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                      $s = $adm['status'];
                      $color = ($s === 'Accepted') ? 'background:#d1fae5; color:#065f46;' : (($s === 'Under Review' || $s === 'Pending') ? 'background:#fef3c7; color:#92400e;' : (($s === 'Rejected') ? 'background:#fee2e2; color:#991b1b;' : 'background:#e0e7ff; color:#3730a3;'));
                    ?>
                    <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; <?= $color ?>">
                      <?= e($s) ?>
                    </span>
                  </td>
                  <td>
                    <a href="<?= APP_URL ?>/admin/admissions.php?search=<?= urlencode($adm['application_ref']) ?>" class="btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.78rem;">
                      Review
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5" style="text-align: center; color: #64748b;">No admission submissions yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Table 2: Recent Enrolled Students -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">
          <i class="fas fa-user-graduate" style="color: #0369a1;"></i> Enrolled Student Directory
        </h3>
        <a href="<?= APP_URL ?>/admin/students.php" style="font-size: 0.85rem; font-weight: 600; color: var(--rmu-primary);">View All <span id="dashViewAllStudentsCount">(<?= $totalStudents ?>)</span> &rarr;</a>
      </div>

      <div class="table-responsive">
        <table class="rmu-table" style="box-shadow: none; border: none; margin: 0;">
          <thead>
            <tr>
              <th>ID &bull; Full Name</th>
              <th>Program &bull; Batch</th>
              <th>CGPA</th>
              <th>Status</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody id="dashboardStudentsTbody">
            <?php if (!empty($recentStudents)): ?>
              <?php foreach ($recentStudents as $st): ?>
                <tr data-student-row-id="<?= (int)$st['id'] ?>">
                  <td>
                    <strong style="color: var(--rmu-primary); font-family: monospace; font-size: 0.9rem;"><?= e($st['student_id']) ?></strong>
                    <div style="font-weight: 600; color: #0f172a; font-size: 0.9rem;"><?= e($st['full_name']) ?></div>
                  </td>
                  <td style="font-size: 0.82rem; color: #475569;">
                    <div><?= e($st['program_title'] ?? 'N/A') ?></div>
                    <small style="color: #64748b;"><?= e($st['batch']) ?></small>
                  </td>
                  <td>
                    <strong style="color: #047857; font-size: 0.92rem;"><?= number_format($st['cgpa'], 2) ?></strong>
                  </td>
                  <td>
                    <?php $stB = get_student_status_badges($st); ?>
                    <span style="padding: 0.15rem 0.55rem; border-radius: 99px; font-size: 0.72rem; font-weight: 800; background: <?= e($stB['account']['bg']) ?>; color: <?= e($stB['account']['color']) ?>; border: 1px solid <?= e($stB['account']['border']) ?>;">
                      <?= e($stB['account']['label']) ?>
                    </span>
                  </td>
                  <td>
                    <div style="display: inline-flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                      <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= (int)$st['id'] ?>" style="color: #0284c7; font-weight: 600; font-size: 0.82rem;">
                        Manage &rarr;
                      </a>
                      <?php if (is_admin_manager_role()): ?>
                        <button type="button"
                                class="dash-delete-student-btn"
                                data-student-id="<?= (int)$st['id'] ?>"
                                onclick="deleteDashboardStudent(<?= (int)$st['id'] ?>, this)"
                                style="background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; font-size: 0.76rem; cursor: pointer; padding: 0.25rem 0.48rem; border-radius: 4px; line-height: 1;"
                                title="Delete Student"
                                aria-label="Delete Student">
                          <i class="fas fa-trash-alt"></i>
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr id="dashStudentsEmptyRow"><td colspan="5" style="text-align: center; color: #64748b;">No enrolled students found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- Right Column: Quick Links & Recent Audit Logs -->
  <div style="display: flex; flex-direction: column; gap: 2rem; min-width: 0;">
    
    <!-- Quick System Operations -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0 0 1rem;">
        <i class="fas fa-bolt" style="color: #f59e0b;"></i> Quick Controls
      </h3>
      <div style="display: flex; flex-direction: column; gap: 0.6rem;">
        <a href="<?= APP_URL ?>/admin/students.php" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 0.88rem; font-weight: 600; background: #f8fafc;">
          <span><i class="fas fa-user-plus" style="color: var(--rmu-primary); width: 22px;"></i> Add New Student</span>
          <i class="fas fa-chevron-right" style="color: #94a3b8; font-size: 0.75rem;"></i>
        </a>
        <a href="<?= APP_URL ?>/admin/portal-notices.php" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 0.88rem; font-weight: 600; background: #f8fafc;">
          <span><i class="fas fa-broadcast-tower" style="color: #0284c7; width: 22px;"></i> Post Portal Notice</span>
          <i class="fas fa-chevron-right" style="color: #94a3b8; font-size: 0.75rem;"></i>
        </a>
        <a href="<?= APP_URL ?>/admin/notices.php" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 0.88rem; font-weight: 600; background: #f8fafc;">
          <span><i class="fas fa-bullhorn" style="color: #dc2626; width: 22px;"></i> Public Circulars</span>
          <i class="fas fa-chevron-right" style="color: #94a3b8; font-size: 0.75rem;"></i>
        </a>
        <a href="<?= APP_URL ?>/admin/programs.php" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 0.88rem; font-weight: 600; background: #f8fafc;">
          <span><i class="fas fa-book" style="color: #7c3aed; width: 22px;"></i> Degree Curricula</span>
          <i class="fas fa-chevron-right" style="color: #94a3b8; font-size: 0.75rem;"></i>
        </a>
        <a href="<?= APP_URL ?>/admin/academic-sessions.php" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 0.88rem; font-weight: 600; background: #f8fafc;">
          <span><i class="fas fa-calendar-alt" style="color: #0f766e; width: 22px;"></i> Sessions &amp; Passing Years</span>
          <i class="fas fa-chevron-right" style="color: #94a3b8; font-size: 0.75rem;"></i>
        </a>
        <a href="<?= APP_URL ?>/admin/academic-sessions.php?tab=oversight" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 0.88rem; font-weight: 600; background: #f8fafc;">
          <span><i class="fas fa-balance-scale" style="color: #d97706; width: 22px;"></i> Passing Year Oversight <?= $oversightQueueCount > 0 ? "({$oversightQueueCount})" : '' ?></span>
          <i class="fas fa-chevron-right" style="color: #94a3b8; font-size: 0.75rem;"></i>
        </a>
        <a href="<?= APP_URL ?>/admin/users.php" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 0.88rem; font-weight: 600; background: #f8fafc;">
          <span><i class="fas fa-key" style="color: #059669; width: 22px;"></i> Password &amp; Admins</span>
          <i class="fas fa-chevron-right" style="color: #94a3b8; font-size: 0.75rem;"></i>
        </a>
        <a href="<?= APP_URL ?>/admin/settings.php" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 0.88rem; font-weight: 600; background: #f8fafc;">
          <span><i class="fas fa-sliders-h" style="color: #ea580c; width: 22px;"></i> Portal Settings</span>
          <i class="fas fa-chevron-right" style="color: #94a3b8; font-size: 0.75rem;"></i>
        </a>
      </div>
    </div>

    <!-- Right: Recent Activity / Audit Logs -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">
          <i class="fas fa-history" style="color: #64748b;"></i> Live Audit Trail
        </h3>
        <a href="<?= APP_URL ?>/admin/audit-logs.php" style="font-size: 0.85rem; font-weight: 600; color: var(--rmu-primary);">All Logs &rarr;</a>
      </div>

      <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.85rem;">
        <?php foreach ($recentLogs as $log): ?>
          <li style="padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;">
            <div style="display: flex; justify-content: space-between; color: #64748b; font-size: 0.78rem; margin-bottom: 2px;">
              <span><strong style="color: var(--rmu-primary);"><?= e($log['action']) ?></strong></span>
              <span><?= date('M d, H:i', strtotime($log['created_at'])) ?></span>
            </div>
            <div style="color: #334155; line-height: 1.4; font-size: 0.82rem;"><?= e($log['details']) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

  </div>
</div>

<?php if (is_admin_manager_role()): ?>
<script>
(function() {
  var dashCsrfToken = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var deleteStudentApiUrl = <?= json_encode(APP_URL . '/actions/admin-delete-student.php', JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var canManageStudents = <?= is_admin_manager_role() ? 'true' : 'false' ?>;

  function showDashboardAlert(type, message) {
    var box = document.getElementById('dashboardAjaxAlert');
    if (!box) return;
    box.innerHTML = '';
    var alertDiv = document.createElement('div');
    alertDiv.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
    var icon = document.createElement('i');
    icon.className = 'fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle');
    var msgDiv = document.createElement('div');
    msgDiv.textContent = message;
    alertDiv.appendChild(icon);
    alertDiv.appendChild(msgDiv);
    box.appendChild(alertDiv);
    box.style.display = 'block';
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function createDashboardStudentRow(st) {
    var tr = document.createElement('tr');
    tr.setAttribute('data-student-row-id', String(st.id));

    var tdId = document.createElement('td');
    var strongId = document.createElement('strong');
    strongId.style.cssText = 'color: var(--rmu-primary); font-family: monospace; font-size: 0.9rem;';
    strongId.textContent = st.student_id || '';
    var nameDiv = document.createElement('div');
    nameDiv.style.cssText = 'font-weight: 600; color: #0f172a; font-size: 0.9rem;';
    nameDiv.textContent = st.full_name || '';
    tdId.appendChild(strongId);
    tdId.appendChild(nameDiv);

    var tdProg = document.createElement('td');
    tdProg.style.cssText = 'font-size: 0.82rem; color: #475569;';
    var progDiv = document.createElement('div');
    progDiv.textContent = st.program_title || 'N/A';
    var batchSmall = document.createElement('small');
    batchSmall.style.color = '#64748b';
    batchSmall.textContent = st.batch || '';
    tdProg.appendChild(progDiv);
    tdProg.appendChild(batchSmall);

    var tdCgpa = document.createElement('td');
    var cgpaStrong = document.createElement('strong');
    cgpaStrong.style.cssText = 'color: #047857; font-size: 0.92rem;';
    cgpaStrong.textContent = st.cgpa_formatted || '0.00';
    tdCgpa.appendChild(cgpaStrong);

    var tdStatus = document.createElement('td');
    var badgeSpan = document.createElement('span');
    badgeSpan.style.cssText = 'padding: 0.15rem 0.55rem; border-radius: 99px; font-size: 0.72rem; font-weight: 800;';
    badgeSpan.style.background = st.badge_bg || '#dcfce7';
    badgeSpan.style.color = st.badge_color || '#15803d';
    badgeSpan.style.border = '1px solid ' + (st.badge_border || '#86efac');
    badgeSpan.textContent = st.badge_label || 'Active';
    tdStatus.appendChild(badgeSpan);

    var tdAct = document.createElement('td');
    var wrap = document.createElement('div');
    wrap.style.cssText = 'display: inline-flex; align-items: center; gap: 0.5rem; white-space: nowrap;';
    var link = document.createElement('a');
    link.href = st.detail_url || '#';
    link.style.cssText = 'color: #0284c7; font-weight: 600; font-size: 0.82rem;';
    link.innerHTML = 'Manage &rarr;';
    wrap.appendChild(link);

    if (canManageStudents) {
      var delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.className = 'dash-delete-student-btn';
      delBtn.setAttribute('data-student-id', String(st.id));
      delBtn.setAttribute('title', 'Delete Student');
      delBtn.setAttribute('aria-label', 'Delete Student');
      delBtn.style.cssText = 'background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; font-size: 0.76rem; cursor: pointer; padding: 0.25rem 0.48rem; border-radius: 4px; line-height: 1;';
      delBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
      delBtn.onclick = function() { window.deleteDashboardStudent(st.id, delBtn); };
      wrap.appendChild(delBtn);
    }
    tdAct.appendChild(wrap);

    tr.appendChild(tdId);
    tr.appendChild(tdProg);
    tr.appendChild(tdCgpa);
    tr.appendChild(tdStatus);
    tr.appendChild(tdAct);
    return tr;
  }

  window.deleteDashboardStudent = function(studentId, btnEl) {
    var targetId = parseInt(studentId, 10);
    if (!targetId || targetId <= 0) {
      showDashboardAlert('error', 'Invalid student database ID.');
      return;
    }
    if (!window.confirm('Are you sure you want to delete this student?')) {
      return;
    }

    var originalHtml = btnEl ? btnEl.innerHTML : '';
    if (btnEl) {
      btnEl.disabled = true;
      btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }

    fetch(deleteStudentApiUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: new URLSearchParams({
        csrf_token: dashCsrfToken,
        id: String(targetId)
      }).toString()
    })
    .then(function(res) {
      return res.json().catch(function() {
        return { success: false, message: 'Unexpected server response (HTTP ' + res.status + ').' };
      }).then(function(data) {
        return { ok: res.ok, status: res.status, data: data };
      });
    })
    .then(function(result) {
      var data = result.data || {};
      if (!result.ok || !data.success) {
        if (btnEl) {
          btnEl.disabled = false;
          btnEl.innerHTML = originalHtml;
        }
        showDashboardAlert('error', data.message || 'Failed to delete student record.');
        return;
      }

      var tbody = document.getElementById('dashboardStudentsTbody');
      var rowEl = tbody ? tbody.querySelector('tr[data-student-row-id="' + targetId + '"]') : null;
      if (rowEl && rowEl.parentNode) {
        rowEl.parentNode.removeChild(rowEl);
      }

      if (data.stats && typeof data.stats.total_students !== 'undefined') {
        var totalEl = document.getElementById('dashTotalStudentsCount');
        if (totalEl) totalEl.textContent = String(data.stats.total_students);
        var viewAllEl = document.getElementById('dashViewAllStudentsCount');
        if (viewAllEl) viewAllEl.textContent = '(' + String(data.stats.total_students) + ')';
      }

      if (tbody && Array.isArray(data.recent_students)) {
        data.recent_students.forEach(function(item) {
          if (!tbody.querySelector('tr[data-student-row-id="' + item.id + '"]')) {
            tbody.appendChild(createDashboardStudentRow(item));
          }
        });
        if (tbody.querySelectorAll('tr[data-student-row-id]').length === 0 && !document.getElementById('dashStudentsEmptyRow')) {
          var emptyTr = document.createElement('tr');
          emptyTr.id = 'dashStudentsEmptyRow';
          emptyTr.innerHTML = '<td colspan="5" style="text-align: center; color: #64748b;">No enrolled students found.</td>';
          tbody.appendChild(emptyTr);
        }
      }

      showDashboardAlert('success', data.message || 'Student deleted successfully.');
    })
    .catch(function() {
      if (btnEl) {
        btnEl.disabled = false;
        btnEl.innerHTML = originalHtml;
      }
      showDashboardAlert('error', 'Network error while attempting to delete student record.');
    });
  };
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
