<?php
/**
 * Admin Portal Dashboard Overview
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

$adminPageTitle = 'Dashboard Overview';
$db = getDb();

// Calculate Statistics
$totalAdmissions = (int)$db->query("SELECT COUNT(*) FROM admissions")->fetchColumn();
$pendingAdmissions = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE status IN ('Submitted', 'Under Review')")->fetchColumn();
$totalPrograms = (int)$db->query("SELECT COUNT(*) FROM programs WHERE is_active = 1")->fetchColumn();
$totalNotices = (int)$db->query("SELECT COUNT(*) FROM notices WHERE is_active = 1")->fetchColumn();
$unreadMessages = (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
$totalFaculty = (int)$db->query("SELECT COUNT(*) FROM faculty_members WHERE is_active = 1")->fetchColumn();

// Recent Admissions
$recentAdmissions = $db->query("SELECT * FROM admissions ORDER BY id DESC LIMIT 5")->fetchAll();

// Recent Inquiries
$recentMessages = $db->query("SELECT * FROM contact_messages ORDER BY id DESC LIMIT 5")->fetchAll();

// Recent Audit Logs
$recentLogs = $db->query("SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.id DESC LIMIT 6")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="margin-bottom: 2rem;">
  <h2 style="font-size: 1.6rem; font-weight: 700; color: #0f172a; margin: 0 0 0.3rem;">Administration Dashboard</h2>
  <span style="color: #64748b; font-size: 0.9rem;">Overview of university operations, student applications, and portal content.</span>
</div>

<!-- Stat Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
  <!-- Card 1: Total Applications -->
  <div class="stat-card">
    <div class="stat-icon" style="background: #e0e7ff; color: #4338ca;">
      <i class="fas fa-user-graduate"></i>
    </div>
    <div>
      <div style="font-size: 1.7rem; font-weight: 700; color: #0f172a; line-height: 1.1;"><?= $totalAdmissions ?></div>
      <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Total Applications</div>
    </div>
  </div>

  <!-- Card 2: Pending Reviews -->
  <div class="stat-card">
    <div class="stat-icon" style="background: #fef3c7; color: #b45309;">
      <i class="fas fa-clock"></i>
    </div>
    <div>
      <div style="font-size: 1.7rem; font-weight: 700; color: #0f172a; line-height: 1.1;"><?= $pendingAdmissions ?></div>
      <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Pending Reviews</div>
    </div>
  </div>

  <!-- Card 3: Degree Programs -->
  <div class="stat-card">
    <div class="stat-icon" style="background: #e0f2fe; color: #0369a1;">
      <i class="fas fa-book-open"></i>
    </div>
    <div>
      <div style="font-size: 1.7rem; font-weight: 700; color: #0f172a; line-height: 1.1;"><?= $totalPrograms ?></div>
      <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Degree Programs</div>
    </div>
  </div>

  <!-- Card 4: Notices -->
  <div class="stat-card">
    <div class="stat-icon" style="background: #fee2e2; color: #b91c1c;">
      <i class="fas fa-bullhorn"></i>
    </div>
    <div>
      <div style="font-size: 1.7rem; font-weight: 700; color: #0f172a; line-height: 1.1;"><?= $totalNotices ?></div>
      <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Published Notices</div>
    </div>
  </div>

  <!-- Card 5: Unread Messages -->
  <div class="stat-card">
    <div class="stat-icon" style="background: #dcfce7; color: #15803d;">
      <i class="fas fa-envelope"></i>
    </div>
    <div>
      <div style="font-size: 1.7rem; font-weight: 700; color: #0f172a; line-height: 1.1;"><?= $unreadMessages ?></div>
      <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">New Inquiries</div>
    </div>
  </div>
</div>

<!-- Two Columns Layout -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2.5rem;">
  <!-- Left: Recent Applications Table -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">Recent Admission Applications</h3>
      <a href="<?= APP_URL ?>/admin/admissions.php" style="font-size: 0.85rem; font-weight: 600; color: var(--rmu-primary);">View All &rarr;</a>
    </div>

    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow: none; border: none;">
        <thead>
          <tr>
            <th>Ref &bull; Candidate</th>
            <th>Subject</th>
            <th>GPA</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($recentAdmissions)): ?>
            <?php foreach ($recentAdmissions as $adm): ?>
              <tr>
                <td>
                  <strong style="color: var(--rmu-primary); display: block; font-size: 0.85rem;"><?= e($adm['application_ref']) ?></strong>
                  <span style="font-weight: 600; color: #0f172a; font-size: 0.92rem;"><?= e($adm['full_name']) ?></span>
                  <div style="font-size: 0.78rem; color: #64748b;"><?= e($adm['mobile']) ?></div>
                </td>
                <td style="font-size: 0.85rem; color: #334155;">
                  <?= e($adm['interested_subject']) ?>
                </td>
                <td style="font-size: 0.82rem;">
                  SSC: <?= number_format($adm['ssc_gpa'], 2) ?><br>
                  HSC: <?= number_format($adm['hsc_gpa'], 2) ?>
                </td>
                <td>
                  <?php
                    $s = $adm['status'];
                    $color = ($s === 'Accepted') ? 'background:#d1fae5; color:#065f46;' : (($s === 'Under Review') ? 'background:#fef3c7; color:#92400e;' : 'background:#e0e7ff; color:#3730a3;');
                  ?>
                  <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; <?= $color ?>">
                    <?= e($s) ?>
                  </span>
                </td>
                <td>
                  <a href="<?= APP_URL ?>/admin/admissions.php?search=<?= urlencode($adm['application_ref']) ?>" style="font-size: 0.82rem; font-weight: 600; color: var(--rmu-primary);">
                    Review &rarr;
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

  <!-- Right: Recent Activity / Audit Logs -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">Recent Audit Activity</h3>
      <a href="<?= APP_URL ?>/admin/audit-logs.php" style="font-size: 0.85rem; font-weight: 600; color: var(--rmu-primary);">View All &rarr;</a>
    </div>

    <ul style="list-style: none; padding: 0; font-size: 0.85rem;">
      <?php foreach ($recentLogs as $log): ?>
        <li style="padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;">
          <div style="display: flex; justify-content: space-between; color: #64748b; font-size: 0.78rem; margin-bottom: 2px;">
            <span><strong><?= e($log['action']) ?></strong></span>
            <span><?= date('M d, H:i', strtotime($log['created_at'])) ?></span>
          </div>
          <div style="color: #334155; line-height: 1.4;"><?= e($log['details']) ?></div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

