<?php
/**
 * Admin Panel Header Layout
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
$user = current_user();
$adminTitle = isset($adminPageTitle) ? $adminPageTitle . ' - RMU Admin' : 'RMU Administration Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <?php include BASE_PATH . '/includes/pwa-head.php'; ?>
  <title><?= e($adminTitle) ?></title>
  <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/assets/images/logo.jpg">
  
  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

  <!-- Core Admin Styles -->
  <link rel="stylesheet" href="<?= e(rmu_asset_url('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(rmu_asset_url('assets/css/responsive.css')) ?>">
  <style>
    :root {
      --admin-sidebar-width: 260px;
    }
    body {
      background-color: #f1f5f9;
      display: flex;
      flex-direction: row;
      min-height: 100vh;
      margin: 0;
    }
    .admin-sidebar {
      width: var(--admin-sidebar-width);
      background-color: #0f172a;
      color: #94a3b8;
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      height: 100dvh;
      overflow-y: auto;
      z-index: 100;
      border-right: 1px solid #1e293b;
      padding-bottom: env(safe-area-inset-bottom, 0px);
    }
    .admin-brand-row {
      border-bottom: 1px solid #1e293b;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-right: 0.75rem;
    }
    .admin-brand {
      padding: 1.25rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.8rem;
      color: #ffffff;
      text-decoration: none;
      flex: 1;
      min-width: 0;
    }
    .admin-sidebar-close {
      display: none;
      background: rgba(255,255,255,0.08);
      border: 1px solid #334155;
      color: #f8fafc;
      width: 44px;
      height: 44px;
      border-radius: 8px;
      font-size: 1.4rem;
      line-height: 1;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      flex-shrink: 0;
    }
    .admin-brand img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
    }
    .admin-nav {
      list-style: none;
      padding: 1rem 0;
      margin: 0;
      flex: 1;
    }
    .admin-nav-item {
      margin-bottom: 2px;
    }
    .admin-nav-link {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1.5rem;
      color: #94a3b8;
      font-size: 0.9rem;
      font-weight: 500;
      transition: all 0.2s;
      text-decoration: none;
    }
    .admin-nav-link:hover, .admin-nav-item.active .admin-nav-link {
      background-color: #1e293b;
      color: #38bdf8;
    }
    .admin-nav-link i {
      width: 20px;
      font-size: 1rem;
    }
    .admin-main {
      margin-left: var(--admin-sidebar-width);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
    }
    .admin-topbar {
      background: #ffffff;
      height: 65px;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 2rem;
      position: sticky;
      top: 0;
      z-index: 90;
    }
    .admin-content {
      padding: 2rem;
      flex: 1;
      min-width: 0;
      overflow-x: clip;
    }
    .stat-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 1.5rem;
      display: flex;
      align-items: center;
      gap: 1.25rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .stat-icon {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }
    .admin-mobile-toggle {
      display: none;
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      color: #0f172a;
      width: 38px;
      height: 38px;
      border-radius: 6px;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 1.05rem;
    }
    .admin-sidebar-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.55);
      z-index: 95;
    }
    .admin-sidebar-backdrop.show {
      display: block;
    }
    @media (max-width: 991px) {
      .admin-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s;
      }
      .admin-sidebar.open {
        transform: translateX(0);
      }
      .admin-sidebar-close {
        display: inline-flex;
      }
      .admin-main {
        margin-left: 0;
      }
      .admin-mobile-toggle {
        display: inline-flex;
        width: 44px;
        height: 44px;
      }
      .admin-topbar {
        padding: 0 1rem;
      }
      .admin-topbar-title {
        display: none;
      }
      .admin-content {
        padding: 1.15rem;
      }
    }
    @media screen and (max-width: 767px) {
      .admin-topbar {
        height: auto;
        min-height: 58px;
        padding: 0.5rem 0.75rem;
        flex-wrap: wrap;
        gap: 0.5rem;
      }
      .admin-topbar-actions {
        gap: 0.65rem !important;
        flex-wrap: wrap;
        justify-content: flex-end;
      }
      .admin-topbar-public-link span {
        display: none;
      }
      .admin-nav-link {
        min-height: 44px;
      }
      .admin-content input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="hidden"]),
      .admin-content select,
      .admin-content textarea {
        font-size: 16px !important;
        max-width: 100%;
      }
      .admin-content button,
      .admin-content input[type="submit"],
      .admin-content .btn-primary,
      .admin-content .btn-secondary {
        min-height: 44px;
      }
      .admin-content [id$="Modal"] {
        padding: 0.75rem !important;
      }
      .admin-content [id$="Modal"] > div {
        max-height: 92dvh !important;
        overflow-y: auto !important;
        padding: 1.15rem !important;
      }
    }
    @media (max-width: 480px) {
      .admin-topbar {
        padding: 0.5rem 0.75rem;
        height: auto;
        min-height: 58px;
      }
      .admin-content {
        padding: 0.75rem;
      }
      .stat-card {
        padding: 1rem;
        gap: 0.85rem;
      }
    }
  </style>
</head>
<body>

  <div class="admin-sidebar-backdrop" id="adminSidebarBackdrop" onclick="toggleAdminSidebar(false)"></div>

  <!-- Admin Sidebar -->
  <?php include __DIR__ . '/admin-sidebar.php'; ?>

  <!-- Admin Main Content Area -->
  <div class="admin-main">
    <header class="admin-topbar">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <button type="button" class="admin-mobile-toggle" onclick="toggleAdminSidebar()" aria-label="Toggle Admin Menu">
          <i class="fas fa-bars"></i>
        </button>
        <span class="admin-topbar-title" style="color: #64748b; font-size: 0.9rem;">Rabindra Maitree University Administration</span>
      </div>
      <div class="admin-topbar-actions" style="display: flex; align-items: center; gap: 1.5rem;">
        <a href="<?= APP_URL ?>/index.php" target="_blank" class="admin-topbar-public-link" style="font-size: 0.88rem; color: #475569; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; min-width: 44px; min-height: 44px;" title="View Public Site" aria-label="View Public Site">
          <i class="fas fa-external-link-alt"></i> <span>View Public Site</span>
        </a>
        <div style="display: flex; align-items: center; gap: 0.6rem;">
          <div style="width: 36px; height: 36px; border-radius: 50%; background: #38bdf8; color: #0f172a; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">
            <?= strtoupper(substr($user['username'] ?? 'A', 0, 1)) ?>
          </div>
          <div style="font-size: 0.85rem;">
            <strong style="display: block; color: #0f172a;"><?= e($user['full_name'] ?? 'Admin') ?></strong>
            <span style="color: #64748b;"><?= ucfirst($user['role'] ?? 'Staff') ?></span>
          </div>
        </div>
        <a href="<?= APP_URL ?>/admin/logout.php" style="color: #ef4444; font-size: 0.9rem; display: inline-flex; align-items: center; justify-content: center; min-width: 44px; min-height: 44px;" title="Logout" aria-label="Logout">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </header>

    <main class="admin-content">
      <!-- Flash notifications inside admin -->
      <?php if ($success = flash_get('success')): ?>
        <div class="alert alert-success" style="margin-bottom: 1.5rem;">
          <i class="fas fa-check-circle"></i> <div><?= e($success) ?></div>
        </div>
      <?php endif; ?>
      <?php if ($error = flash_get('error')): ?>
        <div class="alert alert-error" style="margin-bottom: 1.5rem;">
          <i class="fas fa-exclamation-circle"></i> <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>
      <?php if ($info = flash_get('info')): ?>
        <div class="alert alert-info" style="margin-bottom: 1.5rem;">
          <i class="fas fa-info-circle"></i> <div><?= e($info) ?></div>
        </div>
      <?php endif; ?>
      <?php if ($warning = flash_get('warning')): ?>
        <div class="alert" style="margin-bottom: 1.5rem; background-color: #fffbeb; border: 1px solid #fde68a; color: #b45309;">
          <i class="fas fa-exclamation-triangle"></i> <div><?= e($warning) ?></div>
        </div>
      <?php endif; ?>

