<?php
/**
 * Admin Panel Sidebar
 */
$adminCurrentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
?>
<aside class="admin-sidebar">
  <a href="<?= APP_URL ?>/admin/dashboard.php" class="admin-brand">
    <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="RMU Logo" onerror="this.src='<?= APP_URL ?>/assets/images/logo.svg'">
    <div>
      <strong style="display: block; font-size: 0.95rem; line-height: 1.2;">RMU Admin</strong>
      <small style="color: #64748b; font-size: 0.75rem;">Master Control Panel</small>
    </div>
  </a>

  <ul class="admin-nav">
    <!-- 1. Dashboard -->
    <li class="admin-nav-item <?= ($adminCurrentPage === 'dashboard.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="admin-nav-link">
        <i class="fas fa-tachometer-alt"></i> Dashboard
      </a>
    </li>

    <li style="padding: 0.8rem 1.5rem 0.3rem; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #475569; letter-spacing: 0.5px;">
      Core Admissions &amp; Finance
    </li>

    <!-- 2. Admission Management -->
    <li class="admin-nav-item <?= ($adminCurrentPage === 'admissions.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/admissions.php" class="admin-nav-link">
        <i class="fas fa-user-graduate"></i> Admission Management
      </a>
    </li>

    <!-- 3. Payment Management -->
    <li class="admin-nav-item <?= ($adminCurrentPage === 'payments.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/payments.php" class="admin-nav-link">
        <i class="fas fa-receipt"></i> Payment Management
      </a>
    </li>

    <li style="padding: 0.8rem 1.5rem 0.3rem; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #475569; letter-spacing: 0.5px;">
      Students &amp; Examination Control
    </li>

    <!-- 4. Student Management -->
    <li class="admin-nav-item <?= ($adminCurrentPage === 'students.php' || $adminCurrentPage === 'student-detail.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/students.php" class="admin-nav-link">
        <i class="fas fa-users-cog"></i> Student Management
      </a>
    </li>

    <!-- 5. Student Result Management -->
    <?php $pendingRequestsBadge = function_exists('get_pending_result_requests_count') ? get_pending_result_requests_count() : 0; ?>
    <li class="admin-nav-item <?= ($adminCurrentPage === 'results-manager.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/results-manager.php" class="admin-nav-link" style="display:flex; justify-content:space-between; align-items:center;">
        <span><i class="fas fa-award"></i> Student Result Management</span>
        <?php if ($pendingRequestsBadge > 0): ?>
          <span style="background:#ef4444; color:#fff; font-size:0.7rem; font-weight:800; padding:1px 6px; border-radius:10px; margin-left:6px;"><?= $pendingRequestsBadge ?></span>
        <?php endif; ?>
      </a>
    </li>

    <!-- 6. Semester Management -->
    <li class="admin-nav-item <?= ($adminCurrentPage === 'semesters.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/semesters.php" class="admin-nav-link">
        <i class="fas fa-calendar-alt"></i> Semester Management
      </a>
    </li>

    <!-- 7. GPA & CGPA Settings -->
    <li class="admin-nav-item <?= ($adminCurrentPage === 'grading-settings.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/grading-settings.php" class="admin-nav-link">
        <i class="fas fa-sliders-h"></i> GPA &amp; CGPA Settings
      </a>
    </li>

    <!-- 8. Result Publication -->
    <li class="admin-nav-item <?= ($adminCurrentPage === 'result-publication.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/result-publication.php" class="admin-nav-link">
        <i class="fas fa-bullhorn"></i> Result Publication
      </a>
    </li>

    <!-- 9. Notifications -->
    <li class="admin-nav-item <?= ($adminCurrentPage === 'notifications.php' || $adminCurrentPage === 'portal-notices.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/notifications.php" class="admin-nav-link">
        <i class="fas fa-bell"></i> Notifications
      </a>
    </li>

    <li style="padding: 0.8rem 1.5rem 0.3rem; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #475569; letter-spacing: 0.5px;">
      Institutional Intelligence &amp; System
    </li>

    <!-- 10. Reports & Analytics -->
    <li class="admin-nav-item <?= ($adminCurrentPage === 'reports.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/reports.php" class="admin-nav-link">
        <i class="fas fa-chart-line"></i> Reports &amp; Analytics
      </a>
    </li>

    <!-- 11. Activity Logs -->
    <li class="admin-nav-item <?= ($adminCurrentPage === 'audit-logs.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/audit-logs.php" class="admin-nav-link">
        <i class="fas fa-history"></i> Activity Logs
      </a>
    </li>

    <!-- 12. System Settings -->
    <li class="admin-nav-item <?= ($adminCurrentPage === 'settings.php' || $adminCurrentPage === 'users.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/settings.php" class="admin-nav-link">
        <i class="fas fa-cog"></i> System Settings
      </a>
    </li>

    <li style="padding: 0.8rem 1.5rem 0.3rem; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #475569; letter-spacing: 0.5px;">
      Campus Content &amp; Staff
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'faculties.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/faculties.php" class="admin-nav-link">
        <i class="fas fa-university"></i> Faculties
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'departments.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/departments.php" class="admin-nav-link">
        <i class="fas fa-layer-group"></i> Departments
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'programs.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/programs.php" class="admin-nav-link">
        <i class="fas fa-book"></i> Degree Programs
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'faculty-members.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/faculty-members.php" class="admin-nav-link">
        <i class="fas fa-chalkboard-teacher"></i> Teachers &amp; Staff
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'notices.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/notices.php" class="admin-nav-link">
        <i class="fas fa-bullhorn"></i> Public Notices
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'events.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/events.php" class="admin-nav-link">
        <i class="fas fa-calendar-alt"></i> Events
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'news.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/news.php" class="admin-nav-link">
        <i class="fas fa-newspaper"></i> News
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'downloads.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/downloads.php" class="admin-nav-link">
        <i class="fas fa-file-download"></i> Downloads
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'messages.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/messages.php" class="admin-nav-link">
        <i class="fas fa-envelope-open-text"></i> Inquiries
      </a>
    </li>

    <li class="admin-nav-item" style="margin-top: 1rem; border-top: 1px solid #1e293b;">
      <a href="<?= APP_URL ?>/admin/logout.php" class="admin-nav-link" style="color: #ef4444;">
        <i class="fas fa-power-off"></i> Logout
      </a>
    </li>
  </ul>
</aside>
