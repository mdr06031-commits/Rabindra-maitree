<?php
/**
 * Student Portal - Secure Login
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect directly to dashboard
if (is_student_logged_in()) {
    header("Location: " . APP_URL . "/student/dashboard.php");
    exit;
}

$error = '';
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $studentId = trim($_POST['student_id'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (empty($studentId) || empty($password)) {
            $error = 'Both Student ID and Password are required.';
        } else {
            $stmt = $db->prepare("
                SELECT * FROM students 
                WHERE (student_id = ? OR email = ?) AND status = 'Active' 
                LIMIT 1
            ");
            $stmt->execute([$studentId, $studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student && password_verify($password, $student['password_hash'])) {
                // Successful student login
                session_regenerate_id(true);

                $_SESSION['student_pk_id'] = $student['id'];
                $_SESSION['student_id'] = $student['student_id'];
                $_SESSION['student_name'] = $student['full_name'];
                $_SESSION['student_email'] = $student['email'];

                flash_set('success', "Welcome back, {$student['full_name']}!");
                header("Location: " . APP_URL . "/student/dashboard.php");
                exit;
            } else {
                $error = 'Invalid Student ID or Password. Please try again.';
            }
        }
    }
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Portal Login | <?= APP_NAME ?></title>
  <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/assets/images/logo.jpg">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
      margin: 0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 1.5rem;
    }
    .login-container {
      width: 100%;
      max-width: 480px;
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      border: 1px solid #cbd5e1;
      overflow: hidden;
    }
    .login-header {
      background: linear-gradient(135deg, #1e73be 0%, #16568c 100%);
      color: #ffffff;
      padding: 2rem 1.75rem 1.75rem;
      text-align: center;
    }
    .login-logo {
      width: 68px;
      height: 68px;
      border-radius: 10px;
      margin-bottom: 0.75rem;
      border: 3px solid rgba(255,255,255,0.3);
    }
    .login-header h1 {
      margin: 0 0 0.25rem;
      font-size: 1.35rem;
      font-weight: 700;
    }
    .login-header p {
      margin: 0;
      font-size: 0.85rem;
      opacity: 0.9;
    }
    .login-body {
      padding: 2rem 1.75rem;
    }
    .form-group {
      margin-bottom: 1.25rem;
    }
    .form-label {
      display: block;
      margin-bottom: 0.4rem;
      font-weight: 600;
      font-size: 0.85rem;
      color: #1e293b;
    }
    .form-control {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 1.5px solid #cbd5e1;
      border-radius: 6px;
      font-size: 0.92rem;
      font-family: inherit;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-control:focus {
      outline: none;
      border-color: #1e73be;
      box-shadow: 0 0 0 3px rgba(30, 115, 190, 0.15);
    }
    .btn-login {
      width: 100%;
      background: #1e73be;
      color: #ffffff;
      border: none;
      padding: 0.85rem;
      border-radius: 6px;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      transition: background 0.2s ease;
    }
    .btn-login:hover {
      background: #16568c;
    }
    .alert-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #dc2626;
      padding: 0.75rem 1rem;
      border-radius: 6px;
      font-size: 0.85rem;
      margin-bottom: 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    /* Demo Credentials Box */
    .demo-box {
      margin-top: 1.5rem;
      padding: 1rem;
      background: #f8fafc;
      border: 1px dashed #cbd5e1;
      border-radius: 8px;
      font-size: 0.82rem;
    }
    .demo-title {
      font-weight: 700;
      color: #1e73be;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.35rem;
    }
    .demo-account-btn {
      display: block;
      width: 100%;
      text-align: left;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      padding: 0.5rem 0.75rem;
      border-radius: 4px;
      margin-bottom: 0.4rem;
      cursor: pointer;
      transition: all 0.15s ease;
      font-family: inherit;
    }
    .demo-account-btn:hover {
      background: #eff6ff;
      border-color: #93c5fd;
    }
  </style>
</head>
<body>

  <div class="login-container">
    <div class="login-header">
      <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="RMU Monogram" class="login-logo" onerror="this.src='<?= APP_URL ?>/assets/images/logo.svg'">
      <h1>Rabindra Maitree University</h1>
      <p><i class="fas fa-graduation-cap"></i> Student Information &amp; Academic Portal</p>
    </div>

    <div class="login-body">
      <?php if (!empty($error)): ?>
        <div class="alert-error">
          <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
        </div>
      <?php endif; ?>

      <?php if ($msg = flash_get('success')): ?>
        <div style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:0.75rem 1rem; border-radius:6px; font-size:0.85rem; margin-bottom:1.25rem;">
          <i class="fas fa-check-circle"></i> <?= e($msg) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($_GET['student_id'])): ?>
        <div style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; padding:0.75rem 1rem; border-radius:6px; font-size:0.85rem; margin-bottom:1.25rem;">
          <i class="fas fa-check-circle" style="color:#2563eb;"></i> <strong>Admit Card Credentials Auto-Filled:</strong> Your newly issued Student ID and Password have been filled in below. Click Sign In to enter your portal!
        </div>
      <?php endif; ?>

      <form action="<?= APP_URL ?>/student/login.php" method="POST" id="studentLoginForm">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <div class="form-group">
          <label for="student_id" class="form-label"><i class="fas fa-id-badge"></i> Student ID or Email</label>
          <input type="text" name="student_id" id="student_id" class="form-control" placeholder="e.g. RMU2601001" required autofocus value="<?= e($_POST['student_id'] ?? ($_GET['student_id'] ?? 'RMU2601001')) ?>">
        </div>

        <div class="form-group">
          <label for="password" class="form-label"><i class="fas fa-lock"></i> Password</label>
          <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required value="<?= e($_POST['password'] ?? ($_GET['pwd'] ?? 'student123')) ?>">
        </div>

        <button type="submit" class="btn-login">
          <i class="fas fa-sign-in-alt"></i> Sign In to Student Portal
        </button>
      </form>

      <!-- Quick Fill Demo Accounts -->
      <div class="demo-box">
        <div class="demo-title"><i class="fas fa-key"></i> Quick Demo Accounts (Click to Auto-Fill):</div>
        
        <button type="button" class="demo-account-btn" onclick="fillDemo('RMU2601001', 'student123')">
          <strong>RMU2601001</strong> — Tanvir Hasan <em>(BSc in CSE • 2nd Year)</em>
        </button>
        
        <button type="button" class="demo-account-btn" onclick="fillDemo('RMU2601002', 'student123')">
          <strong>RMU2601002</strong> — Nusrat Jahan <em>(BA in Bangla • 1st Year)</em>
        </button>

        <button type="button" class="demo-account-btn" onclick="fillDemo('RMU2601003', 'student123')">
          <strong>RMU2601003</strong> — Ahsan Habib <em>(BBA • 3rd Year)</em>
        </button>
      </div>

      <div style="text-align: center; margin-top: 1.5rem; font-size: 0.85rem;">
        <a href="<?= APP_URL ?>/index.php" style="color: #64748b; text-decoration: none; font-weight: 500;">
          <i class="fas fa-arrow-left"></i> Return to Main Website
        </a>
      </div>
    </div>
  </div>

  <script>
    function fillDemo(id, pass) {
      document.getElementById('student_id').value = id;
      document.getElementById('password').value = pass;
    }
  </script>
</body>
</html>
