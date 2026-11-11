<?php
/**
 * PWA Offline Fallback Page (No Database Dependency)
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Offline | <?= e(APP_NAME) ?></title>
  <?php include BASE_PATH . '/includes/pwa-head.php'; ?>
  <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/pwa/icon-192.png">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: linear-gradient(135deg, #0f2c59 0%, #164e87 100%);
      color: #0f172a;
      min-height: 100vh;
      min-height: 100dvh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.25rem;
    }
    .offline-card {
      width: 100%;
      max-width: 460px;
      background: #ffffff;
      border-radius: 16px;
      padding: 2rem 1.5rem;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
    }
    .offline-logo {
      width: 84px;
      height: 84px;
      border-radius: 18px;
      margin: 0 auto 1.15rem;
      display: block;
      box-shadow: 0 4px 12px rgba(15, 44, 89, 0.2);
    }
    .offline-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fecaca;
      font-size: 0.78rem;
      font-weight: 700;
      padding: 0.28rem 0.75rem;
      border-radius: 999px;
      margin-bottom: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }
    h1 {
      font-size: 1.4rem;
      color: #0f2c59;
      margin-bottom: 0.65rem;
      line-height: 1.3;
    }
    p {
      font-size: 0.94rem;
      color: #475569;
      line-height: 1.55;
      margin-bottom: 1.5rem;
    }
    .btn-retry {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      min-height: 46px;
      background: #1e73be;
      color: #ffffff;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      margin-bottom: 0.75rem;
    }
    .btn-retry:hover {
      background: #135084;
    }
    .offline-links {
      display: flex;
      gap: 0.6rem;
      justify-content: center;
      flex-wrap: wrap;
      margin-top: 0.5rem;
    }
    .offline-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 42px;
      padding: 0.45rem 0.9rem;
      background: #f1f5f9;
      color: #1e293b;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font-size: 0.86rem;
      font-weight: 600;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <main class="offline-card">
    <img src="<?= APP_URL ?>/assets/pwa/icon-192.png" alt="Rabindra Maitree University Emblem" class="offline-logo">
    <div class="offline-badge">No Internet Connection</div>
    <h1>You Are Currently Offline</h1>
    <p>
      Rabindra Maitree University cannot reach the live academic server right now. Check your Wi-Fi or mobile data connection, then tap retry to load live Student Portal, Admission, and Registrar records.
    </p>
    <button type="button" class="btn-retry" onclick="window.location.reload()">
      Retry Connection
    </button>
    <div class="offline-links">
      <a href="<?= APP_URL ?>/index.php" class="offline-link">Return Home</a>
      <a href="<?= APP_URL ?>/student/login.php" class="offline-link">Student Portal</a>
    </div>
  </main>
</body>
</html>
