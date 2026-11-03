<?php
/**
 * Rabindra Maitree University - Student Portal Header
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

require_student_login();
$student = get_logged_in_student();
$currentPortalPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
$portalTitle = $portalTitle ?? 'Student Portal Dashboard';

$studentPkId = (int)($student['id'] ?? ($_SESSION['student_pk_id'] ?? 0));
if (isset($_GET['mark_read_all']) && $studentPkId > 0) {
    mark_student_notifications_read($studentPkId);
    $cleanUrl = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    header("Location: " . ($cleanUrl ?: APP_URL . '/student/' . $currentPortalPage));
    exit;
}
$unreadNotifCount = $studentPkId > 0 ? get_student_unread_notifications_count($studentPkId) : 0;
$recentNotifications = $studentPkId > 0 ? get_student_notifications($studentPkId, false, 6) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($portalTitle) ?> | Student Portal — <?= APP_NAME ?></title>
  <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/assets/images/logo.jpg">
  
  <!-- Google Fonts: Poppins -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- FontAwesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  
  <!-- Student Portal Styles -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/responsive.css?v=<?= time() ?>">
  
  <style>
    :root {
      --sp-primary: #1e73be;
      --sp-primary-dark: #16568c;
      --sp-accent: #e02424;
      --sp-bg: #f8fafc;
      --sp-card-bg: #ffffff;
      --sp-text: #1e293b;
      --sp-muted: #64748b;
      --sp-border: #e2e8f0;
      --sp-success: #16a34a;
      --sp-warning: #d97706;
    }

    body {
      background-color: var(--sp-bg);
      font-family: 'Poppins', sans-serif;
      color: var(--sp-text);
      margin: 0;
      padding: 0;
    }

    /* Portal Top Navbar */
    .sp-navbar {
      background: #ffffff;
      border-bottom: 2px solid var(--sp-border);
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .sp-nav-container {
      max-width: 1300px;
      margin: 0 auto;
      padding: 0 1.25rem;
      height: 65px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .sp-brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
      color: var(--sp-text);
    }

    .sp-brand img {
      width: 44px;
      height: 44px;
      border-radius: 6px;
      object-fit: cover;
    }

    .sp-brand-text h1 {
      font-size: 1.05rem;
      font-weight: 700;
      margin: 0;
      color: #0f172a;
      line-height: 1.2;
    }

    .sp-brand-badge {
      font-size: 0.72rem;
      font-weight: 700;
      background: #eff6ff;
      color: var(--sp-primary);
      padding: 1px 7px;
      border-radius: 4px;
      border: 1px solid #bfdbfe;
      display: inline-block;
    }

    .sp-user-menu {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .sp-user-badge {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      padding: 0.35rem 0.85rem;
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      border-radius: 30px;
      font-size: 0.85rem;
    }

    .sp-avatar-circle {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--sp-primary);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.85rem;
      overflow: hidden;
      border: 1.5px solid #cbd5e1;
    }

    .sp-avatar-circle img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .sp-btn-back-website {
      color: #475569;
      text-decoration: none;
      font-size: 0.84rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 0.4rem 0.8rem;
      border-radius: 6px;
      background: #ffffff;
      border: 1px solid #cbd5e1;
      transition: all 0.2s ease;
    }

    .sp-btn-back-website:hover {
      background: #f8fafc;
      color: var(--sp-primary);
      border-color: var(--sp-primary);
    }

    .sp-btn-logout {
      color: #dc2626;
      background: #fef2f2;
      border: 1px solid #fecaca;
      text-decoration: none;
      font-size: 0.84rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 0.4rem 0.8rem;
      border-radius: 6px;
      transition: all 0.2s ease;
    }

    .sp-btn-logout:hover {
      background: #fee2e2;
    }

    /* Notifications Dropdown */
    .sp-notif-wrapper {
      position: relative;
    }
    .sp-notif-btn {
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      width: 38px;
      height: 38px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #334155;
      cursor: pointer;
      font-size: 1rem;
      position: relative;
      transition: all 0.2s ease;
    }
    .sp-notif-btn:hover {
      background: #e2e8f0;
      color: var(--sp-primary);
    }
    .sp-notif-badge {
      position: absolute;
      top: -3px;
      right: -3px;
      background: #ef4444;
      color: #ffffff;
      font-size: 0.65rem;
      font-weight: 800;
      min-width: 18px;
      height: 18px;
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0 4px;
      border: 2px solid #ffffff;
    }
    .sp-notif-dropdown {
      display: none;
      position: absolute;
      right: 0;
      top: 48px;
      width: 340px;
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.12);
      z-index: 1050;
      overflow: hidden;
    }
    .sp-notif-dropdown.show {
      display: block;
    }

    /* Sub-Navigation Tabs Bar */
    .sp-tabs-bar {
      background: #ffffff;
      border-bottom: 1px solid #e2e8f0;
    }

    .sp-tabs-container {
      max-width: 1300px;
      margin: 0 auto;
      padding: 0 1.25rem;
      display: flex;
      gap: 0.35rem;
      overflow-x: auto;
      scrollbar-width: none;
    }

    .sp-tabs-container::-webkit-scrollbar {
      display: none;
    }

    .sp-tab-link {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.9rem 1.1rem;
      color: #475569;
      font-size: 0.88rem;
      font-weight: 600;
      text-decoration: none;
      border-bottom: 3px solid transparent;
      white-space: nowrap;
      transition: all 0.2s ease;
    }

    .sp-tab-link:hover {
      color: var(--sp-primary);
      background: #f8fafc;
    }

    .sp-tab-link.active {
      color: var(--sp-primary);
      border-bottom-color: var(--sp-primary);
      background: #eff6ff;
    }

    /* Main Container */
    .sp-main-container {
      max-width: 1300px;
      margin: 2rem auto 4rem;
      padding: 0 1.25rem;
    }

    /* Profile Card Banner */
    .sp-profile-banner {
      background: linear-gradient(135deg, #1e73be 0%, #0f4c81 100%);
      color: #ffffff;
      border-radius: 12px;
      padding: 1.75rem 2rem;
      margin-bottom: 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1.5rem;
      box-shadow: 0 8px 20px rgba(30, 115, 190, 0.2);
    }

    .sp-profile-meta {
      display: flex;
      align-items: center;
      gap: 1.25rem;
    }

    .sp-big-avatar {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: rgba(255,255,255,0.2);
      border: 3px solid rgba(255,255,255,0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
      color: #ffffff;
      flex-shrink: 0;
    }

    .sp-profile-info h2 {
      margin: 0 0 0.25rem;
      font-size: 1.45rem;
      font-weight: 700;
    }

    .sp-profile-info p {
      margin: 0;
      font-size: 0.9rem;
      opacity: 0.92;
    }

    .sp-profile-tags {
      display: flex;
      gap: 0.5rem;
      margin-top: 0.5rem;
      flex-wrap: wrap;
    }

    .sp-tag {
      background: rgba(255,255,255,0.18);
      border: 1px solid rgba(255,255,255,0.25);
      font-size: 0.76rem;
      padding: 2px 9px;
      border-radius: 12px;
      font-weight: 500;
    }

    /* KPI Stats Grid */
    .sp-kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.25rem;
      margin-bottom: 2rem;
    }

    .sp-kpi-card {
      background: #ffffff;
      border: 1px solid var(--sp-border);
      border-radius: 10px;
      padding: 1.35rem 1.25rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.03);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .sp-kpi-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0,0,0,0.06);
    }

    .sp-kpi-icon {
      width: 52px;
      height: 52px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
      flex-shrink: 0;
    }

    .sp-kpi-val {
      font-size: 1.6rem;
      font-weight: 700;
      color: #0f172a;
      line-height: 1.1;
      margin-bottom: 2px;
    }

    .sp-kpi-label {
      font-size: 0.82rem;
      color: var(--sp-muted);
      font-weight: 500;
    }

    /* Content Cards */
    .sp-card {
      background: #ffffff;
      border: 1px solid var(--sp-border);
      border-radius: 10px;
      margin-bottom: 2rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.03);
      overflow: hidden;
    }

    .sp-card-header {
      padding: 1.1rem 1.4rem;
      border-bottom: 1px solid var(--sp-border);
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #ffffff;
    }

    .sp-card-header h3 {
      margin: 0;
      font-size: 1.05rem;
      font-weight: 700;
      color: #0f172a;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .sp-card-body {
      padding: 1.4rem;
    }

    /* Tables */
    .sp-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.88rem;
    }

    .sp-table th {
      background: #f8fafc;
      color: #475569;
      font-weight: 600;
      text-align: left;
      padding: 0.75rem 1rem;
      border-bottom: 1.5px solid var(--sp-border);
    }

    .sp-table td {
      padding: 0.85rem 1rem;
      border-bottom: 1px solid #f1f5f9;
      color: #334155;
    }

    .sp-table tr:hover td {
      background: #f8fafc;
    }

    .badge-paid {
      background: #dcfce7;
      color: #15803d;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 12px;
      font-size: 0.76rem;
    }

    .badge-enrolled {
      background: #eff6ff;
      color: #1e73be;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 12px;
      font-size: 0.76rem;
    }

    /* Print Styles */
    @media print {
      .sp-navbar, .sp-tabs-bar, .sp-btn-back-website, .sp-btn-logout, .no-print {
        display: none !important;
      }
      body {
        background: #ffffff;
      }
      .sp-main-container {
        margin: 0;
        padding: 0;
      }
    }
  </style>
</head>
<body>

  <!-- Top Navbar -->
  <header class="sp-navbar no-print">
    <div class="sp-nav-container">
      <a href="<?= APP_URL ?>/student/dashboard.php" class="sp-brand">
        <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="RMU Logo" onerror="this.src='<?= APP_URL ?>/assets/images/logo.svg'">
        <div class="sp-brand-text">
          <h1>Rabindra Maitree University</h1>
          <span class="sp-brand-badge"><i class="fas fa-graduation-cap"></i> Student Portal</span>
        </div>
      </a>

      <div class="sp-user-menu">
        <a href="<?= APP_URL ?>/index.php" class="sp-btn-back-website">
          <i class="fas fa-globe"></i> Main Website
        </a>

        <!-- Notifications Bell & Dropdown -->
        <div class="sp-notif-wrapper">
          <button type="button" class="sp-notif-btn" id="notifBellBtn" onclick="toggleNotificationMenu(event)" title="Notifications" aria-label="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($unreadNotifCount > 0): ?>
              <span class="sp-notif-badge"><?= $unreadNotifCount ?></span>
            <?php endif; ?>
          </button>

          <div class="sp-notif-dropdown" id="notifDropdown">
            <div style="padding:0.75rem 1rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
              <strong style="font-size:0.88rem; color:#0f172a;"><i class="fas fa-bell" style="color:var(--sp-primary);"></i> Notifications</strong>
              <?php if ($unreadNotifCount > 0): ?>
                <a href="?mark_read_all=1" style="font-size:0.75rem; color:#1e73be; text-decoration:none; font-weight:600;">
                  <i class="fas fa-check-double"></i> Mark all read
                </a>
              <?php endif; ?>
            </div>
            <div style="max-height:280px; overflow-y:auto;">
              <?php if (empty($recentNotifications)): ?>
                <div style="padding:1.5rem; text-align:center; color:#94a3b8; font-size:0.84rem;">
                  <i class="fas fa-bell-slash" style="font-size:1.5rem; display:block; margin-bottom:0.5rem; opacity:0.5;"></i>
                  No notifications yet
                </div>
              <?php else: ?>
                <?php foreach ($recentNotifications as $notif): ?>
                  <div style="padding:0.75rem 1rem; border-bottom:1px solid #f1f5f9; background:<?= empty($notif['is_read']) ? '#eff6ff' : '#ffffff' ?>; font-size:0.82rem;">
                    <div style="font-weight:700; color:#0f172a; margin-bottom:2px; display:flex; align-items:center; gap:6px;">
                      <?php if ($notif['type'] === 'result_published'): ?>
                        <i class="fas fa-award" style="color:#16a34a; font-size:0.85rem;"></i>
                      <?php elseif ($notif['type'] === 'grade_correction'): ?>
                        <i class="fas fa-pen-alt" style="color:#0284c7; font-size:0.85rem;"></i>
                      <?php elseif ($notif['type'] === 'request_update'): ?>
                        <i class="fas fa-clipboard-check" style="color:#8b5cf6; font-size:0.85rem;"></i>
                      <?php else: ?>
                        <i class="fas fa-info-circle" style="color:#f59e0b; font-size:0.85rem;"></i>
                      <?php endif; ?>
                      <span><?= e($notif['title']) ?></span>
                    </div>
                    <div style="color:#475569; line-height:1.35;"><?= e($notif['message']) ?></div>
                    <div style="color:#94a3b8; font-size:0.72rem; margin-top:3px;">
                      <?= format_date($notif['created_at']) ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div style="padding:0.5rem; text-align:center; background:#f8fafc; border-top:1px solid #e2e8f0;">
              <a href="<?= APP_URL ?>/student/results.php" style="font-size:0.78rem; font-weight:700; color:var(--sp-primary); text-decoration:none;">
                View All Results &amp; Requests &rarr;
              </a>
            </div>
          </div>
        </div>

        <a href="<?= APP_URL ?>/student/profile.php" class="sp-user-badge" style="text-decoration:none; color:inherit;">
          <div class="sp-avatar-circle">
            <img src="<?= get_student_avatar_url($student) ?>" alt="<?= e($student['full_name']) ?>">
          </div>
          <div>
            <strong><?= e($student['full_name']) ?></strong>
            <small style="display:block; color:var(--sp-muted); font-size:0.75rem;"><?= e($student['student_id']) ?></small>
          </div>
        </a>

        <a href="<?= APP_URL ?>/student/logout.php" class="sp-btn-logout" title="Sign out">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </div>
    </div>
  </header>

  <!-- Sub-Navigation Tabs Bar -->
  <nav class="sp-tabs-bar no-print" aria-label="Student Portal Navigation">
    <div class="sp-tabs-container">
      <a href="<?= APP_URL ?>/student/dashboard.php" class="sp-tab-link <?= ($currentPortalPage === 'dashboard.php') ? 'active' : '' ?>">
        <i class="fas fa-th-large"></i> Dashboard
      </a>
      <a href="<?= APP_URL ?>/student/courses.php" class="sp-tab-link <?= ($currentPortalPage === 'courses.php') ? 'active' : '' ?>">
        <i class="fas fa-book-reader"></i> Enrolled Courses &amp; Routine
      </a>
      <a href="<?= APP_URL ?>/student/results.php" class="sp-tab-link <?= ($currentPortalPage === 'results.php') ? 'active' : '' ?>">
        <i class="fas fa-file-invoice"></i> Results &amp; Grade Sheet
      </a>
      <a href="<?= APP_URL ?>/student/fees.php" class="sp-tab-link <?= ($currentPortalPage === 'fees.php') ? 'active' : '' ?>">
        <i class="fas fa-receipt"></i> Tuition &amp; Payments
      </a>
      <a href="<?= APP_URL ?>/student/id-card.php" class="sp-tab-link <?= ($currentPortalPage === 'id-card.php') ? 'active' : '' ?>">
        <i class="fas fa-id-card"></i> Digital ID &amp; Admit Card
      </a>
      <a href="<?= APP_URL ?>/student/profile.php" class="sp-tab-link <?= ($currentPortalPage === 'profile.php') ? 'active' : '' ?>">
        <i class="fas fa-user-circle"></i> My Profile
      </a>
    </div>
  </nav>

  <!-- Flash Alerts -->
  <div class="sp-main-container" style="margin-top:1.5rem; margin-bottom:0;">
    <?php if ($success = flash_get('success')): ?>
      <div style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.6rem; font-weight:600; font-size:0.9rem;">
        <i class="fas fa-check-circle" style="font-size:1.1rem;"></i> <?= e($success) ?>
      </div>
    <?php endif; ?>
    <?php if ($error = flash_get('error')): ?>
      <div style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.6rem; font-weight:600; font-size:0.9rem;">
        <i class="fas fa-exclamation-circle" style="font-size:1.1rem;"></i> <?= e($error) ?>
      </div>
    <?php endif; ?>
  </div>

  <script>
  function toggleNotificationMenu(e) {
    if (e) e.stopPropagation();
    var menu = document.getElementById('notifDropdown');
    if (menu) menu.classList.toggle('show');
  }
  document.addEventListener('click', function(e) {
    var menu = document.getElementById('notifDropdown');
    var btn = document.getElementById('notifBellBtn');
    if (menu && menu.classList.contains('show')) {
      if (!menu.contains(e.target) && (!btn || !btn.contains(e.target))) {
        menu.classList.remove('show');
      }
    }
  });
  </script>

