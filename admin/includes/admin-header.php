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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($adminTitle) ?></title>
  <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/assets/images/logo.jpg">
  
  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

  <!-- Core Admin Styles -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
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
      overflow-y: auto;
      z-index: 100;
      border-right: 1px solid #1e293b;
    }
    .admin-brand {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid #1e293b;
      display: flex;
      align-items: center;
      gap: 0.8rem;
      color: #ffffff;
      text-decoration: none;
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
    @media (max-width: 991px) {
      .admin-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s;
      }
      .admin-sidebar.open {
        transform: translateX(0);
      }
      .admin-main {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>

  <!-- Admin Sidebar -->
  <?php include __DIR__ . '/admin-sidebar.php'; ?>

  <!-- Admin Main Content Area -->
  <div class="admin-main">
    <header class="admin-topbar">
      <div>
        <span style="color: #64748b; font-size: 0.9rem;">Rabindra Maitree University Administration</span>
      </div>
      <div style="display: flex; align-items: center; gap: 1.5rem;">
        <a href="<?= APP_URL ?>/index.php" target="_blank" style="font-size: 0.88rem; color: #475569; display: flex; align-items: center; gap: 0.4rem;">
          <i class="fas fa-external-link-alt"></i> View Public Site
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
        <a href="<?= APP_URL ?>/admin/logout.php" style="color: #ef4444; font-size: 0.9rem;" title="Logout">
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

