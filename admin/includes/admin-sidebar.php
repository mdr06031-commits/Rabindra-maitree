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
    <li class="admin-nav-item <?= ($adminCurrentPage === 'dashboard.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="admin-nav-link">
        <i class="fas fa-tachometer-alt"></i> Dashboard
      </a>
    </li>

    <li style="padding: 0.8rem 1.5rem 0.3rem; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #475569; letter-spacing: 0.5px;">
      Admissions &amp; Students
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'admissions.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/admissions.php" class="admin-nav-link">
        <i class="fas fa-user-graduate"></i> Admission Intake
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'students.php' || $adminCurrentPage === 'student-detail.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/students.php" class="admin-nav-link">
        <i class="fas fa-users-cog"></i> Enrolled Students
      </a>
    </li>

    <?php $pendingRequestsBadge = function_exists('get_pending_result_requests_count') ? get_pending_result_requests_count() : 0; ?>
    <li class="admin-nav-item <?= ($adminCurrentPage === 'results-manager.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/results-manager.php" class="admin-nav-link" style="display:flex; justify-content:space-between; align-items:center;">
        <span><i class="fas fa-award"></i> Student Result Management</span>
        <?php if ($pendingRequestsBadge > 0): ?>
          <span style="background:#ef4444; color:#fff; font-size:0.7rem; font-weight:800; padding:1px 6px; border-radius:10px; margin-left:6px;"><?= $pendingRequestsBadge ?></span>
        <?php endif; ?>
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'portal-notices.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/portal-notices.php" class="admin-nav-link">
        <i class="fas fa-broadcast-tower"></i> Portal Notices
      </a>
    </li>

    <li style="padding: 0.8rem 1.5rem 0.3rem; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #475569; letter-spacing: 0.5px;">
      Academics &amp; Faculty
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

    <li style="padding: 0.8rem 1.5rem 0.3rem; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #475569; letter-spacing: 0.5px;">
      Campus Content
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

    <li style="padding: 0.8rem 1.5rem 0.3rem; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #475569; letter-spacing: 0.5px;">
      Administration &amp; System
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'messages.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/messages.php" class="admin-nav-link">
        <i class="fas fa-envelope-open-text"></i> Inquiries
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'users.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/users.php" class="admin-nav-link">
        <i class="fas fa-user-shield"></i> Admin Users
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'settings.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/settings.php" class="admin-nav-link">
        <i class="fas fa-sliders-h"></i> Site Settings
      </a>
    </li>

    <li class="admin-nav-item <?= ($adminCurrentPage === 'audit-logs.php') ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/admin/audit-logs.php" class="admin-nav-link">
        <i class="fas fa-history"></i> Audit Logs
      </a>
    </li>

    <li class="admin-nav-item" style="margin-top: 1rem; border-top: 1px solid #1e293b;">
      <a href="<?= APP_URL ?>/admin/logout.php" class="admin-nav-link" style="color: #ef4444;">
        <i class="fas fa-power-off"></i> Logout
      </a>
    </li>
  </ul>
</aside>
