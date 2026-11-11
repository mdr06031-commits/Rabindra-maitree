<?php
/**
 * Shared PWA, Mobile Viewport & Apple Web App Meta Tags
 * Safe to include multiple times (pure markup; zero function declarations).
 */
?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0f2c59">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="RMU Portal">
  <meta name="rmu-app-url" content="<?= htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="manifest" href="<?= htmlspecialchars(APP_URL . '/manifest.php', ENT_QUOTES, 'UTF-8') ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars(rmu_asset_url('assets/pwa/apple-touch-icon.png'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="apple-touch-startup-image" media="(orientation: portrait)" href="<?= htmlspecialchars(rmu_asset_url('assets/pwa/splash-portrait.png'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="apple-touch-startup-image" media="(orientation: landscape)" href="<?= htmlspecialchars(rmu_asset_url('assets/pwa/splash-landscape.png'), ENT_QUOTES, 'UTF-8') ?>">
