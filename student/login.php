<?php
/**
 * Student Portal - Secure Login
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in with an active, valid session, redirect directly to dashboard
if (get_logged_in_student()) {
    header("Location: " . APP_URL . "/student/dashboard.php");
    exit;
}

$error = '';
$db = getDb();

$now = time();
$loginThrottle = $_SESSION['rmu_student_login_fails'] ?? ['count' => 0, 'first_at' => $now, 'locked_until' => 0];
if (($now - (int)($loginThrottle['first_at'] ?? $now)) > 900 && (int)($loginThrottle['locked_until'] ?? 0) <= $now) {
    $loginThrottle = ['count' => 0, 'first_at' => $now, 'locked_until' => 0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } elseif ((int)($loginThrottle['locked_until'] ?? 0) > $now) {
        $waitMins = max(1, (int)ceil(((int)$loginThrottle['locked_until'] - $now) / 60));
        $error = "Too many failed login attempts. Please wait {$waitMins} minute(s) before trying again.";
    } else {
        $studentId = trim($_POST['student_id'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (empty($studentId) || empty($password)) {
            $error = 'Both Student ID and Password are required.';
        } else {
            $stmt = $db->prepare("
                SELECT * FROM students 
                WHERE (student_id = ? OR email = ? OR username = ?)
                LIMIT 1
            ");
            $stmt->execute([$studentId, $studentId, $studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student && ($student['status'] ?? '') === 'Deleted') {
                $error = 'This student account has been permanently deleted and login access has been revoked.';
            } elseif ($student && ($student['status'] ?? '') === 'Deactivated') {
                $error = 'Your student portal account has been deactivated by university administration. Please contact the Registrar Office.';
            } elseif ($student && ($student['status'] ?? '') === 'Suspended') {
                $error = 'Your student portal account is currently suspended. Please contact administration.';
            } elseif ($student && password_verify($password, $student['password_hash'])) {
                // Successful student login
                unset($_SESSION['rmu_student_login_fails']);
                session_regenerate_id(true);

                $_SESSION['student_pk_id'] = (int)$student['id'];
                $_SESSION['student_id'] = $student['student_id'];
                $_SESSION['student_name'] = $student['full_name'];
                $_SESSION['student_email'] = $student['email'];
                $_SESSION['student_session_version'] = (int)($student['session_version'] ?? 1);

                flash_set('success', "Welcome back, {$student['full_name']}!");
                header("Location: " . APP_URL . "/student/dashboard.php");
                exit;
            } else {
                $loginThrottle['count'] = (int)($loginThrottle['count'] ?? 0) + 1;
                if ($loginThrottle['count'] === 1) {
                    $loginThrottle['first_at'] = $now;
                }
                if ($loginThrottle['count'] >= 6) {
                    $loginThrottle['locked_until'] = $now + 900;
                    $error = 'Too many failed login attempts. Please wait 15 minute(s) before trying again.';
                } else {
                    $error = 'Invalid Student ID or Password. Please try again.';
                }
                $_SESSION['rmu_student_login_fails'] = $loginThrottle;
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
  <?php include BASE_PATH . '/includes/pwa-head.php'; ?>
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
    @media screen and (max-width: 767px) {
      body {
        padding: 0.85rem;
      }
      .login-header {
        padding: 1.65rem 1.25rem 1.35rem;
      }
      .login-body {
        padding: 1.5rem 1.15rem;
      }
      .form-control {
        font-size: 16px;
        min-height: 46px;
      }
      .btn-login,
      button[aria-label="Toggle password visibility"] {
        min-height: 44px;
        min-width: 44px;
      }
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

      <?php if ($flashErr = flash_get('error')): ?>
        <div class="alert-error">
          <i class="fas fa-ban"></i> <?= e($flashErr) ?>
        </div>
      <?php endif; ?>

      <?php if ($msg = flash_get('success')): ?>
        <div style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:0.75rem 1rem; border-radius:6px; font-size:0.85rem; margin-bottom:1.25rem;">
          <i class="fas fa-check-circle"></i> <?= e($msg) ?>
        </div>
      <?php endif; ?>

      <?php
        $hasPreStudentId = !empty($_GET['student_id']);
      ?>
      <?php if ($hasPreStudentId): ?>
        <div style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; padding:0.75rem 1rem; border-radius:6px; font-size:0.85rem; margin-bottom:1.25rem;">
          <i class="fas fa-id-badge" style="color:#2563eb;"></i> <strong>Student ID Pre-Filled:</strong> Enter your Student Portal password below to sign in.
        </div>
      <?php endif; ?>

      <form action="<?= APP_URL ?>/student/login.php" method="POST" id="studentLoginForm">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <div class="form-group">
          <label for="student_id" class="form-label"><i class="fas fa-id-badge"></i> Student ID or Email</label>
          <input type="text" name="student_id" id="student_id" class="form-control" placeholder="e.g. RMU2601001" required autofocus value="<?= e($_POST['student_id'] ?? ($_GET['student_id'] ?? '')) ?>">
        </div>

        <div class="form-group">
          <label for="password" class="form-label"><i class="fas fa-lock"></i> Password</label>
          <div style="position:relative;">
            <input type="password" name="password" id="password" class="form-control" style="padding-right:2.75rem;" placeholder="Enter your portal password" required value="">
            <button type="button" onclick="var p=document.getElementById('password'),i=this.querySelector('i'); if(p.type==='password'){p.type='text';i.className='fas fa-eye-slash';}else{p.type='password';i.className='fas fa-eye';}" aria-label="Toggle password visibility" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:#64748b; cursor:pointer; padding:4px 6px;">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login">
          <i class="fas fa-sign-in-alt"></i> Sign In to Student Portal
        </button>
      </form>

      <div class="demo-box" style="color:#475569; line-height:1.55;">
        <div class="demo-title"><i class="fas fa-shield-alt"></i> Student Access Notice</div>
        Use your official <strong>Student ID</strong> and issued portal password to sign in. For credential recovery or enrollment assistance, please contact the Office of the Registrar.
      </div>

      <div style="text-align: center; margin-top: 1.5rem; font-size: 0.85rem;">
        <a href="<?= APP_URL ?>/index.php" style="color: #64748b; text-decoration: none; font-weight: 500;">
          <i class="fas fa-arrow-left"></i> Return to Main Website
        </a>
      </div>
    </div>
  </div>
  <script src="<?= e(rmu_asset_url('assets/js/pwa.js')) ?>"></script>
</body>
</html>
