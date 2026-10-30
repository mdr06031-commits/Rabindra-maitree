<?php
/**
 * Initial Administrator Account Setup Tool
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
$adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (strlen($username) < 4) {
            $error = 'Username must be at least 4 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'A valid email address is required.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters in length.';
        } else {
            // Check if username/email already taken
            $check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetchColumn() > 0) {
                $error = 'That username or email is already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("
                    INSERT INTO users (username, email, password_hash, full_name, role, status)
                    VALUES (?, ?, ?, ?, 'superadmin', 'active')
                ");
                $stmt->execute([$username, $email, $hash, $fullName]);
                log_audit('ADMIN_CREATED', "New superadmin created: {$username}");
                flash_set('success', "Administrator account '{$username}' successfully created! You can now log in.");
                header("Location: " . APP_URL . "/admin/login.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>First Administrator Setup - RMU</title>
  <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/assets/images/logo.jpg">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    body {
      background: #f1f5f9;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }
    .setup-box {
      background: #ffffff;
      max-width: 500px;
      width: 100%;
      border-radius: 12px;
      padding: 2.5rem;
      border: 1px solid var(--rmu-border);
      box-shadow: 0 10px 25px rgba(0,0,0,0.06);
    }
  </style>
</head>
<body>

  <div class="setup-box">
    <div style="text-align: center; margin-bottom: 2rem;">
      <h2 style="font-size: 1.5rem; color: var(--rmu-primary); font-weight: 700; margin-bottom: 0.3rem;">Administrator Setup</h2>
      <p style="color: var(--rmu-muted); font-size: 0.9rem; margin: 0;">Rabindra Maitree University Portal</p>
    </div>

    <?php if ($adminCount > 0): ?>
      <div class="alert alert-info" style="margin-bottom: 1.5rem;">
        <i class="fas fa-info-circle"></i>
        <div>
          An active superadministrator account already exists in the system (Default: <code>admin</code> / <code>Admin@RMU2026!</code>). You may create an additional administrator below or sign in directly.
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error" style="margin-bottom: 1.5rem;">
        <i class="fas fa-exclamation-triangle"></i>
        <div><?= e($error) ?></div>
      </div>
    <?php endif; ?>

    <form action="<?= APP_URL ?>/admin/setup-admin.php" method="POST">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label">Full Legal Name</label>
        <input type="text" name="full_name" class="form-control" placeholder="e.g. Chief Administrator" required>
      </div>

      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" placeholder="e.g. admin_rmu" required minlength="4">
      </div>

      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="admin@rmu.ac.bd" required>
      </div>

      <div class="form-group">
        <label class="form-label">Password (Minimum 8 characters)</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••••••" required minlength="8">
      </div>

      <div style="margin-top: 1.8rem;">
        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem;">
          <i class="fas fa-user-plus"></i> Create Administrator Account
        </button>
      </div>
    </form>

    <div style="margin-top: 1.5rem; text-align: center;">
      <a href="<?= APP_URL ?>/admin/login.php" style="font-size: 0.88rem; color: var(--rmu-primary); font-weight: 600;">
        &larr; Return to Admin Sign In
      </a>
    </div>
  </div>

</body>
</html>

