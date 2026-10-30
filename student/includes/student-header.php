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

