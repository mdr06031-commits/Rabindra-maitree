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
$totalStudents = (int)$db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$activeStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
$totalAdmissions = (int)$db->query("SELECT COUNT(*) FROM admissions")->fetchColumn();
$pendingAdmissions = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE status IN ('Submitted', 'Under Review')")->fetchColumn();
$paidAdmissions = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE payment_status = 'Paid'")->fetchColumn();
$totalPrograms = (int)$db->query("SELECT COUNT(*) FROM programs WHERE is_active = 1")->fetchColumn();
$totalNotices = (int)$db->query("SELECT COUNT(*) FROM notices WHERE is_active = 1")->fetchColumn();
$unreadMessages = (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
$totalFaculty = (int)$db->query("SELECT COUNT(*) FROM faculty_members WHERE is_active = 1")->fetchColumn();
$totalRevenue = (float)$db->query("SELECT SUM(amount) FROM student_fees WHERE status = 'Paid'")->fetchColumn();

// Recent Admissions
$recentAdmissions = $db->query("SELECT * FROM admissions ORDER BY id DESC LIMIT 6")->fetchAll();

// Recent Enrolled Students
$recentStudents = $db->query("
    SELECT s.*, p.title_en AS program_title 
    FROM students s 
    LEFT JOIN programs p ON s.program_id = p.id 
    ORDER BY s.id DESC LIMIT 5
")->fetchAll();

// Recent Inquiries
$recentMessages = $db->query("SELECT * FROM contact_messages ORDER BY id DESC LIMIT 5")->fetchAll();

// Recent Audit Logs
$recentLogs = $db->query("SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.id DESC LIMIT 6")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

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
        <div style="font-size: 1.7rem; font-weight: 800; color: #0f172a; line-height: 1.1;"><?= $totalStudents ?></div>
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
  <a href="<?= APP_URL ?>/admin/admissions.php?status=Under+Review" style="text-decoration: none; color: inherit;">
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
</div>

<!-- Two Columns Layout -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2.5rem;">
  
  <!-- Left Column: Recent Applications & Enrolled Students -->
  <div style="display: flex; flex-direction: column; gap: 2rem;">
    
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
                      $color = ($s === 'Accepted') ? 'background:#d1fae5; color:#065f46;' : (($s === 'Under Review') ? 'background:#fef3c7; color:#92400e;' : (($s === 'Rejected') ? 'background:#fee2e2; color:#991b1b;' : 'background:#e0e7ff; color:#3730a3;'));
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
        <a href="<?= APP_URL ?>/admin/students.php" style="font-size: 0.85rem; font-weight: 600; color: var(--rmu-primary);">View All (<?= $totalStudents ?>) &rarr;</a>
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
          <tbody>
            <?php foreach ($recentStudents as $st): ?>
              <tr>
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
                  <span style="padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.72rem; font-weight: 700; background: #dcfce7; color: #15803d;">
                    <?= e($st['status']) ?>
                  </span>
                </td>
                <td>
                  <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= $st['id'] ?>" style="color: #0284c7; font-weight: 600; font-size: 0.82rem;">
                    Manage &rarr;
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- Right Column: Quick Links & Recent Audit Logs -->
  <div style="display: flex; flex-direction: column; gap: 2rem;">
    
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

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
