<?php
/**
 * Rabindra Maitree University (RMU) Header Template
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME . ' - RMU | ' . APP_NAME_BN;
$contactPhone = get_setting('contact_phone', '+8801302991188');
$contactEmail = get_setting('contact_email', 'info@rmu.ac.bd');
$facebookUrl = get_setting('facebook_url', 'https://www.facebook.com/rmukushtia');
$linkedinUrl = get_setting('linkedin_url', 'https://www.linkedin.com/school/rmukushtia/');
$whatsappUrl = get_setting('whatsapp_url', 'https://wa.me/8801302991188');
$currentTerm = get_setting('admission_active_term', 'Fall 2026');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="Rabindra Maitree University (RMU), Kushtia - A center of excellence in higher education, arts, engineering, and cultural enlightenment.">
  <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/assets/images/logo.jpg">
  
  <!-- Google Fonts: Poppins, Roboto Slab -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto+Slab:wght@400;600;700&display=swap" rel="stylesheet">
  
  <!-- FontAwesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- Core Stylesheets (Cache-busted on every deploy) -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/responsive.css?v=<?= time() ?>">
</head>
<body>

  <!-- Top Announcement Bar -->
  <div class="rmu-topbar">
    <div class="container">
      <div class="topbar-social">
        <span>Follow us:</span>
        <a href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="<?= e($linkedinUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
        <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
      </div>
      <div class="topbar-info">
        <a href="tel:<?= e($contactPhone) ?>"><i class="fas fa-phone-alt"></i> Call: <?= e($contactPhone) ?></a>
        <a href="mailto:<?= e($contactEmail) ?>"><i class="fas fa-envelope"></i> <?= e($contactEmail) ?></a>
        <a href="<?= APP_URL ?>/student/login.php" class="topbar-badge" style="background:#1e73be; color:#ffffff; font-weight:700;"><i class="fas fa-user-graduate"></i> Student Portal</a>
        <a href="<?= APP_URL ?>/admission-form.php" class="topbar-badge">Admission Open <?= e($currentTerm) ?></a>
      </div>
    </div>
  </div>

  <!-- University Header Branding -->
  <header class="rmu-header">
    <div class="container">
      <a href="<?= APP_URL ?>/index.php" class="brand-wrapper">
        <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="RMU Monogram" class="brand-logo" onerror="this.src='<?= APP_URL ?>/assets/images/logo.svg'">
        <div class="brand-text">
          <h1><?= APP_NAME ?></h1>
          <div class="brand-bn">Kushtia, Bangladesh</div>
          <div class="brand-tagline">Approved by UGC &amp; Govt. of Bangladesh • Established in 2015</div>
        </div>
      </a>
      <div class="header-actions">
        <a href="<?= APP_URL ?>/student/login.php" class="btn-header-student" style="font-size:0.88rem; font-weight:700; color:#0f172a; background:#f1f5f9; display:inline-flex; align-items:center; gap:0.4rem; padding:0.45rem 0.85rem; border:1px solid #cbd5e1; border-radius:6px; text-decoration:none; transition:all 0.2s ease;">
          <i class="fas fa-user-graduate" style="color:var(--rmu-primary);"></i> Student Portal
        </a>
        <a href="<?= APP_URL ?>/admission-status.php" style="font-size:0.88rem; font-weight:600; color:var(--rmu-muted); display:inline-flex; align-items:center; gap:0.3rem;">
          <i class="fas fa-search-location"></i> Check Status
        </a>
        <a href="<?= APP_URL ?>/admission-form.php" class="btn-header-apply">
          <i class="fas fa-edit"></i> Apply Online
        </a>
      </div>
    </div>
  </header>

  <!-- Navigation Bar -->
  <?php include __DIR__ . '/navigation.php'; ?>

  <!-- Flash Messages Alert Banner -->
  <div class="container" style="margin-top: 1rem;">
    <?php if ($successMsg = flash_get('success')): ?>
      <div class="alert alert-success" role="alert">
        <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
        <div><?= e($successMsg) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($errorMsg = flash_get('error')): ?>
      <div class="alert alert-error" role="alert">
        <i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i>
        <div><?= e($errorMsg) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($infoMsg = flash_get('info')): ?>
      <div class="alert alert-info" role="alert">
        <i class="fas fa-info-circle" style="font-size: 1.2rem;"></i>
        <div><?= e($infoMsg) ?></div>
      </div>
    <?php endif; ?>
  </div>

