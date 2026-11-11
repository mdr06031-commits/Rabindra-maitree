<?php
/**
 * Student Portal - My Profile, Photo Upload & Security Settings
 */
$portalTitle = 'My Profile & Photo';
require_once __DIR__ . '/includes/student-header.php';

$db = getDb();
$studentId = $student['id'];
$profileMsg = '';
$profileErr = '';

// 1. Handle Profile Picture Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $profileErr = 'Security session expired. Please try again.';
    } elseif (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $profileErr = 'Please choose a valid image file to upload.';
    } else {
        $file = $_FILES['profile_photo'];
        $maxBytes = 2 * 1024 * 1024; // 2MB

        if ($file['size'] > $maxBytes) {
            $profileErr = 'The image file size exceeds 2MB. Please choose a smaller photo.';
        } else {
            // Validate MIME type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg'
            ];

            if (!array_key_exists($mime, $allowedMimes)) {
                $profileErr = 'Invalid file format. Only JPG, PNG, and WEBP image files are permitted.';
            } else {
                $ext = $allowedMimes[$mime];
                $uploadDir = BASE_PATH . '/storage/uploads/students/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $filename = 'student_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $student['student_id']) . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Update database record
                    $updateStmt = $db->prepare("UPDATE students SET avatar = ? WHERE id = ?");
                    $updateStmt->execute([$filename, $studentId]);

                    // Update session/cache
                    $student['avatar'] = $filename;
                    $profileMsg = 'Your profile photo has been successfully uploaded and updated across your portal!';
                } else {
                    $profileErr = 'Failed to save the uploaded image. Please check file permissions.';
                }
            }
        }
    }
}

// 2. Handle Photo Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_photo') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $profileErr = 'Security session expired. Please try again.';
    } else {
        $updateStmt = $db->prepare("UPDATE students SET avatar = NULL WHERE id = ?");
        $updateStmt->execute([$studentId]);
        $student['avatar'] = null;
        $profileMsg = 'Your profile picture has been reset to default.';
    }
}

// 3. Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $profileErr = 'Security session expired. Please try again.';
    } else {
        $currentPass = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');

        if (empty($currentPass) || empty($newPass)) {
            $profileErr = 'Please fill in all password fields.';
        } elseif ($newPass !== $confirmPass) {
            $profileErr = 'New password and confirmation do not match.';
        } elseif (strlen($newPass) < 6) {
            $profileErr = 'New password must be at least 6 characters long.';
        } else {
            if (password_verify($currentPass, $student['password_hash'])) {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $updateStmt = $db->prepare("UPDATE students SET password_hash = ? WHERE id = ?");
                $updateStmt->execute([$newHash, $studentId]);
                $profileMsg = 'Your password has been changed successfully!';
            } else {
                $profileErr = 'Current password is incorrect.';
            }
        }
    }
}

$csrfToken = csrf_token();
$currentAvatarUrl = get_student_avatar_url($student);
?>

<div class="sp-main-container">

  <div style="margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0;">
    <h2 style="margin:0 0 0.3rem; font-size:1.45rem; font-weight:700; color:#0f172a;">
      <i class="fas fa-user-circle" style="color:var(--sp-primary);"></i> Student Profile &amp; Photo Management
    </h2>
    <p style="margin:0; font-size:0.88rem; color:#64748b;">
      Manage your student identity photo, registration records, and account credentials.
    </p>
  </div>

  <?php if (!empty($profileMsg)): ?>
    <div style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.6rem; font-weight:600;">
      <i class="fas fa-check-circle" style="font-size:1.1rem;"></i> <?= e($profileMsg) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($profileErr)): ?>
    <div style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.6rem; font-weight:600;">
      <i class="fas fa-exclamation-circle" style="font-size:1.1rem;"></i> <?= e($profileErr) ?>
    </div>
  <?php endif; ?>

  <div style="display:grid; grid-template-columns: 1.1fr 2fr; gap:1.75rem; align-items:start;">
    
    <!-- Left Column: Student Photo Upload Card -->
    <div class="sp-card" id="uploadPhoto">
      <div class="sp-card-header">
        <h3><i class="fas fa-camera" style="color:var(--sp-primary);"></i> Profile Picture / Photo</h3>
      </div>
      <div class="sp-card-body" style="text-align:center;">
        
        <!-- Photo Preview Area -->
        <div style="position:relative; width:150px; height:160px; margin:0 auto 1.25rem; border-radius:12px; overflow:hidden; border:3px solid var(--sp-primary); box-shadow:0 6px 16px rgba(0,0,0,0.1); background:#eff6ff;">
          <img id="avatarPreview" src="<?= e($currentAvatarUrl) ?>" alt="Student Photo" style="width:100%; height:100%; object-fit:cover;">
        </div>

        <div style="margin-bottom:1rem;">
          <h4 style="margin:0 0 0.2rem; font-size:1.05rem; font-weight:700; color:#0f172a;"><?= e($student['full_name']) ?></h4>
          <span style="font-family:monospace; font-size:0.85rem; font-weight:700; color:var(--sp-primary);"><?= e($student['student_id']) ?></span>
        </div>

        <!-- Upload Form -->
        <form action="<?= APP_URL ?>/student/profile.php" method="POST" enctype="multipart/form-data" id="photoUploadForm">
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="action" value="upload_photo">

          <div style="margin-bottom:1rem; text-align:left;">
            <label for="profile_photo" style="display:block; font-size:0.82rem; font-weight:600; color:#334155; margin-bottom:0.4rem;">
              Choose New Photo (JPG, PNG, WEBP — Max 2MB):
            </label>
            <input type="file" name="profile_photo" id="profile_photo" accept="image/jpeg,image/png,image/webp,image/svg+xml" required onchange="previewImage(this);" style="width:100%; font-size:0.82rem; border:1px solid #cbd5e1; padding:0.45rem; border-radius:6px; background:#f8fafc;">
          </div>

          <button type="submit" style="width:100%; background:var(--sp-primary); color:#ffffff; border:none; padding:0.7rem; border-radius:6px; font-size:0.88rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:6px; margin-bottom:0.6rem; transition:background 0.2s ease;">
            <i class="fas fa-upload"></i> Upload &amp; Save Photo
          </button>
        </form>

        <?php if (!empty($student['avatar'])): ?>
          <form action="<?= APP_URL ?>/student/profile.php" method="POST" onsubmit="return confirm('Are you sure you want to remove your custom photo and revert to default?');">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="remove_photo">
            <button type="submit" style="width:100%; background:#f8fafc; color:#64748b; border:1px solid #cbd5e1; padding:0.5rem; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer; transition:all 0.15s ease;">
              <i class="fas fa-trash-alt"></i> Remove Custom Photo
            </button>
          </form>
        <?php endif; ?>

        <div style="margin-top:1.25rem; padding-top:0.85rem; border-top:1px dashed #e2e8f0; font-size:0.75rem; color:#64748b; line-height:1.45;">
          <i class="fas fa-info-circle" style="color:var(--sp-primary);"></i> Your photo will automatically appear on your <strong>Digital Student ID Card</strong>, <strong>Exam Admit Card</strong>, and <strong>Official Grade Records</strong>.
        </div>

      </div>
    </div>

    <!-- Right Column: Personal & Academic Details + Change Password -->
    <div>
      
      <!-- Academic & Personal Info Card -->
      <div class="sp-card">
        <div class="sp-card-header">
          <h3><i class="fas fa-id-card-alt" style="color:var(--sp-primary);"></i> Student Registration Details</h3>
        </div>
        <div class="sp-card-body" style="font-size:0.88rem; line-height:1.8;">
          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Full Name:</span>
            <strong><?= e($student['full_name']) ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Student ID:</span>
            <strong style="font-family:monospace; color:var(--sp-primary);"><?= e($student['student_id']) ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Official Student Email:</span>
            <strong><?= e($student['email']) ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Contact Mobile:</span>
            <strong><?= e($student['phone']) ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Degree Program:</span>
            <strong><?= e($student['program_name']) ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Department:</span>
            <strong><?= e($student['department_name']) ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Batch &amp; Session:</span>
            <strong><?= e($student['batch']) ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Current Year &amp; Semester:</span>
            <strong><?= e($student['current_year']) ?> (<?= e($student['current_semester']) ?>)</strong>
          </div>

          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Blood Group:</span>
            <strong style="color:#dc2626;"><?= e($student['blood_group'] ?? 'B+') ?></strong>
          </div>

          <div style="display:flex; justify-content:space-between; padding:6px 0;">
            <span style="color:#64748b;">Home District:</span>
            <strong><?= e($student['home_district'] ?? 'Kushtia') ?></strong>
          </div>
        </div>
      </div>

      <!-- Security & Password Change Card -->
      <div class="sp-card">
        <div class="sp-card-header">
          <h3><i class="fas fa-lock" style="color:#d97706;"></i> Portal Password &amp; Credentials</h3>
        </div>
        <div class="sp-card-body">
          <form action="<?= APP_URL ?>/student/profile.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="change_password">

            <div style="margin-bottom:1.1rem;">
              <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.35rem; color:#1e293b;">
                Current Password
              </label>
              <input type="password" name="current_password" required placeholder="••••••••" style="width:100%; padding:0.65rem 0.9rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem; font-family:inherit;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.4rem;">
              <div>
                <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.35rem; color:#1e293b;">
                  New Password (min 6 chars)
                </label>
                <input type="password" name="new_password" required placeholder="••••••••" style="width:100%; padding:0.65rem 0.9rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem; font-family:inherit;">
              </div>
              <div>
                <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.35rem; color:#1e293b;">
                  Confirm New Password
                </label>
                <input type="password" name="confirm_password" required placeholder="••••••••" style="width:100%; padding:0.65rem 0.9rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem; font-family:inherit;">
              </div>
            </div>

            <button type="submit" style="background:#1e73be; color:#ffffff; border:none; padding:0.75rem 1.4rem; border-radius:6px; font-size:0.9rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background 0.2s ease;">
              <i class="fas fa-save"></i> Update Password
            </button>
          </form>
        </div>
      </div>

    </div>

  </div>

</div>

<!-- Live Image Preview Script -->
<script>
function previewImage(input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    if (file.size > 2 * 1024 * 1024) {
      alert('The chosen image exceeds 2MB. Please choose a smaller photo.');
      input.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('avatarPreview').src = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}
</script>

<?php require_once __DIR__ . '/includes/student-footer.php'; ?>
