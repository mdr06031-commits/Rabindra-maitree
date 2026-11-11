<?php
/**
 * Student Portal - My Profile, Photo Upload & Security Settings
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_student_login();
$student = get_logged_in_student();
$db = getDb();
$studentId = (int)$student['id'];
$profileMsg = '';
$profileErr = '';

// Helper to clean up orphaned uploaded student/applicant avatar files when replaced or removed
$cleanupOrphanAvatar = function (string $oldFilename) use ($db): void {
    $base = basename(trim($oldFilename));
    if ($base === '' || $base === '.' || $base === '..') {
        return;
    }
    if (strpos($base, 'student_') !== 0 && strpos($base, 'applicant_') !== 0) {
        return;
    }
    $chkSt = $db->prepare("SELECT COUNT(*) FROM students WHERE avatar = ?");
    $chkSt->execute([$base]);
    $chkAdm = $db->prepare("SELECT COUNT(*) FROM admissions WHERE photo = ?");
    $chkAdm->execute([$base]);
    if ((int)$chkSt->fetchColumn() === 0 && (int)$chkAdm->fetchColumn() === 0) {
        $path = BASE_PATH . '/storage/uploads/students/' . $base;
        if (is_file($path)) {
            @unlink($path);
        }
    }
};

// 0. Guard against unauthorized student attempts to mutate official academic fields
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $protectedAcademicKeys = [
        'academic_session', 'passing_year', 'expected_passing_year',
        'program_id', 'department_id', 'degree_type', 'duration_years',
        'course_duration', 'program_duration', 'total_semesters', 'cgpa',
        'batch', 'student_id', 'roll_number', 'application_number',
        'application_year', 'original_submission_json', 'historical_record_approved'
    ];
    $attemptedProtectedEdit = false;
    foreach ($protectedAcademicKeys as $pKey) {
        if (array_key_exists($pKey, $_POST)) {
            $attemptedProtectedEdit = true;
            break;
        }
    }
    $submittedAction = trim((string)($_POST['action'] ?? ''));
    $allowedStudentActions = ['upload_photo', 'remove_photo', 'change_password'];
    if ($attemptedProtectedEdit || ($submittedAction !== '' && !in_array($submittedAction, $allowedStudentActions, true))) {
        log_audit('STUDENT_ACADEMIC_EDIT_BLOCKED', "Blocked student #{$studentId} ({$student['student_id']}) POST attempt to alter protected academic records", null);
        $profileErr = 'Academic Session, Passing Year, Program, and Course Duration are official university records managed exclusively by Administration and cannot be modified by students.';
        $_POST = [];
    }
}

// 1. Handle Profile Picture Upload BEFORE header renders so topbar avatar stays current
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
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
                'image/webp' => 'webp'
            ];

            if (!array_key_exists($mime, $allowedMimes)) {
                $profileErr = 'Invalid file format. Only JPG, PNG, and WEBP image files are permitted.';
            } else {
                $ext = $allowedMimes[$mime];
                $uploadDir = BASE_PATH . '/storage/uploads/students/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $oldAvatar = (string)($student['avatar'] ?? '');
                $filename = 'student_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $student['student_id']) . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Update database record
                    $updateStmt = $db->prepare("UPDATE students SET avatar = ? WHERE id = ?");
                    $updateStmt->execute([$filename, $studentId]);

                    if ($oldAvatar !== '' && $oldAvatar !== $filename) {
                        $cleanupOrphanAvatar($oldAvatar);
                    }

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
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_photo') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $profileErr = 'Security session expired. Please try again.';
    } else {
        $oldAvatar = (string)($student['avatar'] ?? '');
        $updateStmt = $db->prepare("UPDATE students SET avatar = NULL WHERE id = ?");
        $updateStmt->execute([$studentId]);
        if ($oldAvatar !== '') {
            $cleanupOrphanAvatar($oldAvatar);
        }
        $student['avatar'] = null;
        $profileMsg = 'Your profile picture has been reset to default.';
    }
}

// 3. Handle Password Change
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
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
                ensure_student_password_schema($db);
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $updateStmt = $db->prepare("
                    UPDATE students
                    SET password_hash = ?,
                        password_updated_at = NOW(),
                        password_updated_by = NULL,
                        password_source = 'student_updated',
                        must_change_password = 0
                    WHERE id = ?
                ");
                $updateStmt->execute([$newHash, $studentId]);
                if (!empty($student['student_id'])) {
                    $db->prepare("UPDATE admissions SET portal_password = NULL WHERE student_id = ?")->execute([$student['student_id']]);
                }
                session_regenerate_id(true);
                $student['password_hash'] = $newHash;
                $student['password_source'] = 'student_updated';
                $student['must_change_password'] = 0;
                log_audit('STUDENT_SELF_PASSWORD_CHANGED', "Student {$student['student_id']} (#{$studentId}) updated their portal password.", null);
                $profileMsg = 'Your password has been changed successfully!';
            } else {
                $profileErr = 'Current password is incorrect.';
            }
        }
    }
}

$portalTitle = 'My Profile & Photo';
require_once __DIR__ . '/includes/student-header.php';

$csrfToken = csrf_token();
$currentAvatarUrl = get_student_avatar_url($student);
$academicSummary = get_student_academic_summary((int)$studentId, true);
$progStruct = $academicSummary['program_structure'] ?? [
    'degree_type' => $student['degree_type'] ?? 'Bachelor / Honours',
    'duration_label' => $student['program_duration'] ?? '4 Years (8 Semesters)',
    'total_semesters' => (int)($student['total_semesters'] ?? 8),
];
$totalSemesters = max(1, (int)($progStruct['total_semesters'] ?? 8));
$isResultsSuspended = !empty($academicSummary['results_suspended']);
$isResultsHidden = !empty($academicSummary['results_hidden']);
$hasPublishedResults = !empty($academicSummary['has_results']);
$semSchedule = $academicSummary['semester_schedule'] ?? [];
$semesterGpas = $academicSummary['semester_gpas'] ?? [];
$hasEstimated = !empty($academicSummary['has_estimated_results']);
$pubDateDisplay = !empty($student['result_publication_date']) ? format_date($student['result_publication_date']) : 'Pending Publication';
$sessionDisplay = !empty($student['academic_session']) ? $student['academic_session'] : (!empty($student['batch']) ? $student['batch'] : 'Not Assigned');
$passingYearRaw = !empty($student['passing_year']) ? trim((string)$student['passing_year']) : '';
$passingYearDisplay = ($passingYearRaw !== '') ? $passingYearRaw : 'Not Specified';
$durationYearsFloat = (float)($student['duration_years'] ?? ($progStruct['duration_years'] ?? ($totalSemesters / 2.0)));
$expCalc = calculate_expected_passing_year($sessionDisplay, $durationYearsFloat);
$expectedPassingYear = $expCalc ? (int)$expCalc['expected_year'] : null;
$expectedFormula = $expCalc ? (string)$expCalc['formula'] : 'Session Year + Duration';
$yearOversight = evaluate_passing_year_difference($sessionDisplay, $passingYearRaw !== '' ? $passingYearRaw : null, $durationYearsFloat, !empty($student['historical_record_approved']));

// Fetch linked admission snapshot & recorded admin corrections (never lose original submission)
$linkedAdmStmt = $db->prepare("SELECT id, application_ref, application_id, roll_number, exam_roll, application_year, original_submission_json, created_at FROM admissions WHERE student_id = ? ORDER BY id DESC LIMIT 1");
$linkedAdmStmt->execute([$student['student_id']]);
$linkedAdmission = $linkedAdmStmt->fetch(PDO::FETCH_ASSOC) ?: null;
$profileAppId = trim((string)($student['application_number'] ?? '')) ?: trim((string)($linkedAdmission['application_ref'] ?? ($linkedAdmission['application_id'] ?? '')));
$profileRollNo = trim((string)($student['roll_number'] ?? '')) ?: trim((string)($linkedAdmission['roll_number'] ?? ($linkedAdmission['exam_roll'] ?? '')));
$origSubmission = null;
if (!empty($linkedAdmission['original_submission_json'])) {
    $decodedOrig = json_decode((string)$linkedAdmission['original_submission_json'], true);
    if (is_array($decodedOrig)) {
        $origSubmission = $decodedOrig;
    }
}
$academicEditHistory = get_academic_edit_history(
    $linkedAdmission ? 'admission' : 'student',
    $linkedAdmission ? (int)$linkedAdmission['id'] : (int)$studentId,
    $student['student_id'],
    25
);
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

  <div class="sp-profile-layout" style="display:grid; grid-template-columns: 1.1fr 2fr; gap:1.75rem; align-items:start;">
    
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
            <input type="file" name="profile_photo" id="profile_photo" accept="image/jpeg,image/png,image/webp" required onchange="previewImage(this);" style="width:100%; font-size:0.82rem; border:1px solid #cbd5e1; padding:0.45rem; border-radius:6px; background:#f8fafc;">
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
    <div style="min-width:0;">
      
      <!-- Academic & Personal Info Card -->
      <div class="sp-card">
        <div class="sp-card-header">
          <h3><i class="fas fa-id-card-alt" style="color:var(--sp-primary);"></i> Student Registration Details</h3>
        </div>
        <div class="sp-card-body" style="font-size:0.88rem; line-height:1.8;">
          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Full Name:</span>
            <strong><?= e($student['full_name']) ?></strong>
          </div>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Student ID:</span>
            <strong style="font-family:monospace; color:var(--sp-primary);"><?= e($student['student_id']) ?></strong>
          </div>

          <?php if ($profileRollNo !== ''): ?>
            <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
              <span style="color:#64748b;">Admission / Examination Roll Number:</span>
              <strong style="font-family:monospace; color:#0f172a;"><?= e($profileRollNo) ?></strong>
            </div>
          <?php endif; ?>

          <?php if ($profileAppId !== ''): ?>
            <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
              <span style="color:#64748b;">Application ID:</span>
              <strong style="font-family:monospace; color:#0f172a;"><?= e($profileAppId) ?></strong>
            </div>
          <?php endif; ?>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Official Student Email:</span>
            <strong style="word-break:break-word;"><?= e($student['email']) ?></strong>
          </div>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Contact Mobile:</span>
            <strong><?= e($student['phone']) ?></strong>
          </div>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Degree / Course Type:</span>
            <strong><?= e($progStruct['degree_type'] ?? 'Bachelor / Honours') ?> (<?= $totalSemesters ?> Semesters)</strong>
          </div>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Course Duration:</span>
            <strong><?= e($progStruct['duration_label'] ?? '4 Years (8 Semesters)') ?></strong>
          </div>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Total Semesters:</span>
            <strong><?= $totalSemesters ?> <?= $totalSemesters === 1 ? 'Semester' : 'Semesters' ?></strong>
          </div>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Degree Program:</span>
            <strong><?= e($student['program_name']) ?></strong>
          </div>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Department:</span>
            <strong><?= e($student['department_name']) ?></strong>
          </div>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Academic Session:</span>
            <strong><?= e($sessionDisplay) ?></strong>
          </div>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Actual Passing Year (Student Selected):</span>
            <strong><?= e($passingYearDisplay) ?></strong>
          </div>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Expected Passing Year (Advisory Only):</span>
            <strong>
              <?= $expectedPassingYear !== null ? (int)$expectedPassingYear : 'N/A' ?>
              <small style="color:#64748b; font-weight:500;">(<?= e($expectedFormula) ?> &mdash; Advisory only)</small>
            </strong>
          </div>

          <?php if (!empty($student['historical_record_approved'])): ?>
            <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
              <span style="color:#64748b;">Historical Record Status:</span>
              <span style="background:#dcfce7; color:#15803d; border:1px solid #86efac; padding:1px 8px; border-radius:99px; font-size:0.74rem; font-weight:700;">
                <i class="fas fa-check-circle"></i> Verified Historical Record
              </span>
            </div>
          <?php elseif (!empty($yearOversight['is_discrepancy'])): ?>
            <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
              <span style="color:#64748b;">Registrar Review Notice:</span>
              <span style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:1px 8px; border-radius:99px; font-size:0.74rem; font-weight:700;">
                Selected Passing Year Preserved Pending Review
              </span>
            </div>
          <?php endif; ?>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Current Year &amp; Semester:</span>
            <strong><?= e($student['current_year']) ?> (<?= e($student['current_semester']) ?>)</strong>
          </div>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">
            <span style="color:#64748b;">Blood Group:</span>
            <strong style="color:#dc2626;"><?= e($student['blood_group'] ?? 'B+') ?></strong>
          </div>

          <div class="sp-profile-row" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.25rem; padding:6px 0;">
            <span style="color:#64748b;">Home District:</span>
            <strong><?= e($student['home_district'] ?? 'Kushtia') ?></strong>
          </div>

          <div style="margin-top:0.75rem; padding:0.6rem 0.85rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; font-size:0.76rem; color:#475569; line-height:1.45;">
            <i class="fas fa-lock" style="color:var(--sp-primary);"></i> Academic Session, Passing Year, Degree Program, and Course Duration are read-only official records controlled exclusively by University Administration.
          </div>

          <?php if ($origSubmission || !empty($academicEditHistory)): ?>
            <div style="margin-top:0.85rem; padding:0.75rem 0.9rem; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; font-size:0.78rem; color:#334155;">
              <div style="font-weight:700; color:#0f2c59; margin-bottom:5px;">
                <i class="fas fa-history" style="color:var(--sp-primary);"></i> Original Submission Record &amp; Administrative Corrections
              </div>
              <?php if ($origSubmission): ?>
                <div style="padding:0.45rem 0.65rem; background:#ffffff; border:1px solid #e2e8f0; border-radius:6px; margin-bottom:6px; line-height:1.5;">
                  <strong>Original Application Baseline:</strong>
                  Program: <em><?= e((string)($origSubmission['interested_subject'] ?? $student['program_name'])) ?></em> &bull;
                  Session: <em><?= e((string)($origSubmission['academic_session'] ?? $sessionDisplay)) ?></em> &bull;
                  Duration: <em><?= e((string)($origSubmission['course_duration'] ?? $progStruct['duration_label'])) ?></em>
                  (<?= (int)($origSubmission['total_semesters'] ?? $totalSemesters) ?> Semesters) &bull;
                  Actual Passing Year: <em><?= e((string)($origSubmission['passing_year'] ?? $passingYearDisplay)) ?></em>
                  <?php if (!empty($origSubmission['expected_passing_year'])): ?>
                    &bull; Expected Year: <em><?= (int)$origSubmission['expected_passing_year'] ?></em>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($academicEditHistory)): ?>
                <div style="font-weight:700; color:#475569; margin:4px 0 3px;">Recorded Admin Corrections:</div>
                <ul style="margin:0; padding-left:1.15rem; line-height:1.5;">
                  <?php foreach (array_slice($academicEditHistory, 0, 8) as $aeh): ?>
                    <li>
                      <strong><?= e((string)$aeh['field_name']) ?></strong>:
                      <code><?= e((string)($aeh['old_value'] ?? '—')) ?></code> &rarr;
                      <code><?= e((string)($aeh['new_value'] ?? '—')) ?></code>
                      <span style="color:#64748b;">(<?= format_date($aeh['created_at']) ?>)</span>
                      &mdash; <em><?= e((string)$aeh['reason']) ?></em>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Academic Result Status & Dynamic Semester-Wise Summary Card -->
      <div class="sp-card">
        <div class="sp-card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
          <h3 style="margin:0;"><i class="fas fa-graduation-cap" style="color:var(--sp-primary);"></i> Academic Result Summary (<?= $totalSemesters ?> Semesters)</h3>
          <?php if ($isResultsSuspended): ?>
            <span style="background:#ffedd5; color:#9a3412; border:1px solid #fdba74; padding:2px 10px; border-radius:99px; font-size:0.74rem; font-weight:800;">
              <i class="fas fa-user-lock"></i> Result Access Suspended
            </span>
          <?php elseif ($isResultsHidden): ?>
            <span style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:2px 10px; border-radius:99px; font-size:0.74rem; font-weight:800;">
              <i class="fas fa-eye-slash"></i> Result Hidden
            </span>
          <?php elseif ($hasPublishedResults): ?>
            <span style="background:#dcfce7; color:#15803d; border:1px solid #86efac; padding:2px 10px; border-radius:99px; font-size:0.74rem; font-weight:800;">
              <i class="fas fa-check-circle"></i> Result Published
            </span>
          <?php else: ?>
            <span style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:2px 10px; border-radius:99px; font-size:0.74rem; font-weight:800;">
              <i class="fas fa-clock"></i> Pending
            </span>
          <?php endif; ?>
        </div>
        <div class="sp-card-body">
          <?php if ($isResultsSuspended): ?>
            <div style="background:#fff7ed; border:1.5px solid #fdba74; border-radius:8px; padding:1.25rem; text-align:center;">
              <div style="font-size:1.75rem; color:#c2410c; margin-bottom:0.4rem;"><i class="fas fa-user-lock"></i></div>
              <h4 style="margin:0 0 0.35rem; color:#9a3412; font-size:1.05rem; font-weight:800;">Result Access Suspended</h4>
              <p style="margin:0; font-size:0.85rem; color:#7c2d12; line-height:1.5;">
                Your portal account and registration profile remain active, while examination results and transcripts are temporarily suspended.
                <?php if (!empty($academicSummary['results_suspended_reason'])): ?>
                  <br><strong>Reason:</strong> <?= e($academicSummary['results_suspended_reason']) ?>
                <?php endif; ?>
              </p>
            </div>
          <?php elseif ($isResultsHidden): ?>
            <div style="background:#fef2f2; border:1.5px solid #fecaca; border-radius:8px; padding:1.25rem; text-align:center;">
              <h4 style="margin:0 0 0.3rem; color:#991b1b; font-size:1rem; font-weight:800;">Academic Results Temporarily Hidden</h4>
              <p style="margin:0; font-size:0.84rem; color:#7f1d1d;">Results are currently hidden from student view by the Office of the Controller of Examinations.</p>
            </div>
          <?php else: ?>
            <?php if ($hasPublishedResults && $hasEstimated): ?>
              <div style="background:#fffbeb; border:1.5px solid #f59e0b; border-radius:8px; padding:0.75rem 1rem; margin-bottom:1rem; font-size:0.8rem; color:#92400e;">
                <span style="background:#dc2626; color:#fff; font-weight:900; padding:1px 6px; border-radius:3px; font-size:0.7rem; margin-right:5px;">ESTIMATED / GENERATED</span>
                <strong>ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT</strong>
              </div>
            <?php endif; ?>

            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:0.85rem 1rem; margin-bottom:1rem; font-size:0.84rem;">
              <div>
                <div style="color:#64748b; font-size:0.74rem; text-transform:uppercase; font-weight:700;">Final CGPA</div>
                <div style="font-size:1.35rem; font-weight:900; color:<?= $hasPublishedResults ? '#15803d' : '#64748b' ?>;">
                  <?= $hasPublishedResults ? number_format((float)$academicSummary['final_cgpa'], 2) . ' / 4.00' : 'N/A' ?>
                </div>
              </div>
              <div>
                <div style="color:#64748b; font-size:0.74rem; text-transform:uppercase; font-weight:700;">Academic Session</div>
                <div style="font-weight:700; color:#0f172a;"><?= e($student['academic_session'] ?? $student['batch']) ?></div>
              </div>
              <div>
                <div style="color:#64748b; font-size:0.74rem; text-transform:uppercase; font-weight:700;">Result Publication Date</div>
                <div style="font-weight:700; color:#0f172a;"><?= e($pubDateDisplay) ?></div>
              </div>
              <div>
                <a href="<?= APP_URL ?>/student/results.php" style="background:var(--sp-primary); color:#fff; padding:6px 12px; border-radius:6px; font-size:0.78rem; font-weight:700; text-decoration:none;">
                  Full Transcript &rarr;
                </a>
              </div>
            </div>

            <div class="sp-sem-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(118px, 1fr)); gap:0.6rem;">
              <?php for ($sn = 1; $sn <= $totalSemesters; $sn++): ?>
                <?php
                  $sch = $semSchedule[$sn] ?? null;
                  $sgVal = $semesterGpas[$sn] ?? null;
                  $done = ($sgVal !== null);
                  $semEst = !empty($sch['is_estimated']);
                ?>
                <div style="background:<?= $done ? ($semEst ? '#fffbeb' : '#f0fdf4') : '#f8fafc' ?>; border:1px solid <?= $done ? ($semEst ? '#fcd34d' : '#86efac') : '#e2e8f0' ?>; border-radius:7px; padding:0.55rem 0.5rem; text-align:center;">
                  <div style="font-size:0.72rem; font-weight:700; color:#475569;">
                    Semester <?= $sn ?>
                    <?php if ($done && $semEst): ?><span style="color:#b45309; font-size:0.64rem;">(EST)</span><?php endif; ?>
                  </div>
                  <div style="font-size:1.1rem; font-weight:800; color:<?= $done ? '#0f2c59' : '#94a3b8' ?>; margin:2px 0;">
                    <?= $done ? number_format((float)$sgVal, 2) : '—' ?>
                  </div>
                  <div style="font-size:0.68rem; font-weight:700; color:<?= $done ? '#15803d' : '#64748b' ?>;">
                    <?= $done ? 'Completed' : 'Pending' ?>
                  </div>
                </div>
              <?php endfor; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Security & Password Change Card -->
      <div class="sp-card" id="portalPasswordCard">
        <div class="sp-card-header">
          <h3><i class="fas fa-lock" style="color:#d97706;"></i> Portal Password &amp; Credentials</h3>
        </div>
        <div class="sp-card-body">
          <?php if (!empty($student['must_change_password'])): ?>
            <div style="background:#fffbeb; border:1px solid #fcd34d; color:#92400e; padding:0.75rem 0.95rem; border-radius:8px; font-size:0.83rem; margin-bottom:1.1rem; display:flex; align-items:flex-start; gap:0.55rem;">
              <i class="fas fa-shield-alt" style="margin-top:2px; color:#d97706;"></i>
              <span><strong>Temporary / Initial Password Active:</strong> Please update your temporary password below with a strong personal password for account security.</span>
            </div>
          <?php endif; ?>
          <form action="<?= APP_URL ?>/student/profile.php" method="POST" id="changePasswordForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="change_password">

            <div style="margin-bottom:1.1rem;">
              <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.35rem; color:#1e293b;">
                Current Password
              </label>
              <input type="password" name="current_password" required placeholder="••••••••" style="width:100%; padding:0.65rem 0.9rem; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem; font-family:inherit;">
            </div>

            <div class="sp-pw-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.4rem;">
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

(function() {
  function resetProfileForms() {
    document.querySelectorAll('.sp-main-container form[data-profile-submitted="1"]').forEach(function(form) {
      delete form.dataset.profileSubmitted;
      var btn = form.querySelector('button[type="submit"]');
      if (btn && btn.dataset.origHtml !== undefined) {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.origHtml;
        delete btn.dataset.origHtml;
      }
    });
  }
  window.addEventListener('pageshow', resetProfileForms);
  document.querySelectorAll('.sp-main-container form[method="POST"]').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      if (e.defaultPrevented) return;
      if (form.dataset.profileSubmitted === '1') {
        e.preventDefault();
        return;
      }
      form.dataset.profileSubmitted = '1';
      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.dataset.origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        setTimeout(function() { btn.disabled = true; }, 0);
      }
    });
  });
})();
</script>

<?php require_once __DIR__ . '/includes/student-footer.php'; ?>
