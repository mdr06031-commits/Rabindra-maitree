<?php
/**
 * ONE-PAGE COMPLETE STUDENT & RESULT MANAGEMENT SYSTEM
 * Rabindra Maitree University (RMU)
 *
 * Unified Admin Console controlling:
 * 1. Existing Student Profile Information (all 21 fields)
 * 2. Complete Academic Result Management (8 Semesters for Bachelor's / 4 Semesters for Master's)
 * 3. Live Auto-Grading (UGC 4.00 Scale) & Credit-Weighted SGPA/CGPA Calculation
 * 4. One-Page Actions: Save Student Info, Save Draft, Preview Transcript, Publish Results
 * 5. Automatic Student Portal Synchronization & In-App Notifications
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Student Management & Result Console';
$db = getDb();
$adminId = (int)($_SESSION['user_id'] ?? 1);

// Standard 8 Semesters Titles
$standardSemesterTitles = [
    1 => '1st Semester',
    2 => '2nd Semester',
    3 => '3rd Semester',
    4 => '4th Semester',
    5 => '5th Semester',
    6 => '6th Semester',
    7 => '7th Semester',
    8 => '8th Semester'
];

// Handle Unified Form Submission (Add, Edit, Delete, Save Draft, Publish)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF security token verification failed. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        $submitAction = $_POST['submit_action'] ?? 'save_student_only';

        if ($action === 'add' || $action === 'edit') {
            $studentId = trim($_POST['student_id'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $fatherName = trim($_POST['father_name'] ?? '');
            $motherName = trim($_POST['mother_name'] ?? '');
            $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
            $gender = in_array($_POST['gender'] ?? '', ['Male', 'Female', 'Other']) ? $_POST['gender'] : 'Male';
            $presentAddress = trim($_POST['present_address'] ?? '');
            $permanentAddress = trim($_POST['permanent_address'] ?? '');
            $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
            $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $batch = trim($_POST['batch'] ?? 'Batch 26 (2026–2030)');
            $currentYear = trim($_POST['current_year'] ?? '1st Year');
            $currentSemester = trim($_POST['current_semester'] ?? '1st Semester');
            $cgpa = (float)($_POST['cgpa'] ?? 0.00);
            $creditsCompleted = (int)($_POST['credits_completed'] ?? 0);
            $bloodGroup = trim($_POST['blood_group'] ?? '');
            $homeDistrict = trim($_POST['home_district'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['Active', 'Graduated', 'Suspended']) ? $_POST['status'] : 'Active';
            $password = trim($_POST['password'] ?? 'student123');

            if ($action === 'add' && empty($studentId)) {
                $studentId = generate_next_student_id($db);
            }

            if (empty($fullName) || empty($email) || empty($phone)) {
                flash_set('error', 'Full Name, Official Email, and Mobile Phone are required.');
            } else {
                $targetDbId = 0;

                // 1. Process Student Profile
                if ($action === 'add') {
                    // Check duplicate email or student_id
                    $check = $db->prepare("SELECT id FROM students WHERE student_id = ? OR email = ?");
                    $check->execute([$studentId, $email]);
                    if ($check->fetch()) {
                        flash_set('error', 'A student with this Student ID or Email already exists.');
                    } else {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("
                            INSERT INTO students 
                            (student_id, full_name, father_name, mother_name, email, phone, password_hash, program_id, department_id, batch, current_year, current_semester, cgpa, credits_completed, blood_group, home_district, dob, gender, present_address, permanent_address, total_credits_required, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 140, ?)
                        ");
                        $stmt->execute([
                            $studentId, $fullName, $fatherName, $motherName, $email, $phone, $passwordHash,
                            $programId, $deptId, $batch, $currentYear, $currentSemester,
                            $cgpa, $creditsCompleted, $bloodGroup, $homeDistrict, $dob, $gender, $presentAddress, $permanentAddress, $status
                        ]);
                        $targetDbId = (int)$db->lastInsertId();

                        // Send Welcome In-App Notification
                        create_student_notification(
                            $targetDbId,
                            "Welcome to Rabindra Maitree University!",
                            "Your official Student Portal account has been created. Student ID: {$studentId}.",
                            'general'
                        );
                        log_audit('STUDENT_ADDED_ONE_PAGE', "Enrolled student: {$fullName} ({$studentId})");
                    }
                } elseif ($action === 'edit') {
                    $targetDbId = (int)($_POST['id'] ?? 0);
                    if ($targetDbId > 0) {
                        $stmt = $db->prepare("
                            UPDATE students 
                            SET full_name = ?, father_name = ?, mother_name = ?, email = ?, phone = ?,
                                program_id = ?, department_id = ?, batch = ?, current_year = ?, current_semester = ?,
                                cgpa = ?, credits_completed = ?, blood_group = ?, home_district = ?, dob = ?, gender = ?,
                                present_address = ?, permanent_address = ?, status = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $fullName, $fatherName, $motherName, $email, $phone,
                            $programId, $deptId, $batch, $currentYear, $currentSemester,
                            $cgpa, $creditsCompleted, $bloodGroup, $homeDistrict, $dob, $gender,
                            $presentAddress, $permanentAddress, $status, $targetDbId
                        ]);

                        // Password reset if provided
                        if (!empty($_POST['new_password'])) {
                            $newPass = trim($_POST['new_password']);
                            $passStmt = $db->prepare("UPDATE students SET password_hash = ? WHERE id = ?");
                            $passStmt->execute([password_hash($newPass, PASSWORD_DEFAULT), $targetDbId]);
                            log_audit('STUDENT_PASSWORD_RESET', "Reset password for student ID #{$targetDbId}");
                        }
                        log_audit('STUDENT_UPDATED_ONE_PAGE', "Updated student ID #{$targetDbId} ({$fullName})");
                    }
                }

                // Handle One-Click CGPA Update & Auto-Estimated Results Generation
                if ($targetDbId > 0 && in_array($submitAction, ['update_cgpa_and_generate', 'auto_generate_estimated', 'update_cgpa_only'])) {
                    $targetCgpa = (float)($_POST['target_cgpa_input'] ?? $_POST['cgpa'] ?? 3.50);
                    $targetCgpa = max(2.00, min(4.00, round($targetCgpa, 2)));

                    if ($submitAction === 'update_cgpa_only') {
                        $db->prepare("UPDATE students SET cgpa = ? WHERE id = ?")->execute([$targetCgpa, $targetDbId]);
                        log_audit('STUDENT_CGPA_UPDATED', "Admin updated CGPA to {$targetCgpa} for student #{$targetDbId}");
                        flash_set('success', "Student CGPA updated to " . number_format($targetCgpa, 2) . ".");
                    } else {
                        $genRes = generate_estimated_student_results($targetDbId, $targetCgpa, true, $adminId);
                        if (!empty($genRes['success'])) {
                            flash_set('success', "One-Click CGPA Update Complete! Updated CGPA to " . number_format($targetCgpa, 2) . " and generated estimated results for {$genRes['total_semesters']} semesters ({$genRes['inserted_courses']} subjects). All records marked as: ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT.");
                        } else {
                            flash_set('error', $genRes['error'] ?? 'Failed to generate estimated results.');
                        }
                    }
                    header("Location: " . APP_URL . "/admin/students.php?edit=" . $targetDbId);
                    exit;
                }

                // Handle Administrator Verification Workflow (Convert Estimated -> Verified Official)
                if ($targetDbId > 0 && strpos($submitAction, 'verify_semester_') === 0) {
                    $semNum = (int)str_replace('verify_semester_', '', $submitAction);
                    $semTitle = $standardSemesterTitles[$semNum] ?? "Semester {$semNum}";
                    $aff = verify_semester_results_as_official($targetDbId, $semTitle, $adminId);
                    flash_set('success', "Official Verification Complete: {$aff} course result(s) in {$semTitle} have been verified and recorded as Official University Examination Records!");
                    header("Location: " . APP_URL . "/admin/students.php?edit=" . $targetDbId);
                    exit;
                }

                if ($targetDbId > 0 && $submitAction === 'verify_all_as_official') {
                    $stmt = $db->prepare("
                        UPDATE student_results 
                        SET is_estimated = 0, 
                            result_type = 'Verified', 
                            publish_status = 'Published',
                            is_published = 1,
                            published_by = ?, 
                            published_at = NOW(),
                            correction_reason = 'Verified by administrator as official examination record'
                        WHERE student_id = ? AND is_estimated = 1
                    ");
                    $stmt->execute([$adminId, $targetDbId]);
                    $aff = $stmt->rowCount();
                    recalculate_student_cgpa($targetDbId);
                    log_audit('RESULTS_VERIFIED_OFFICIAL', "Admin verified all {$aff} estimated courses as official for student #{$targetDbId}");
                    create_student_notification(
                        $targetDbId,
                        "Official Academic Results Confirmed",
                        "All academic results across your degree curriculum have been officially verified and confirmed by the Office of the Controller of Examinations.",
                        'result'
                    );
                    flash_set('success', "All {$aff} estimated course results have been verified and confirmed as Official University Records!");
                    header("Location: " . APP_URL . "/admin/students.php?edit=" . $targetDbId);
                    exit;
                }

                // 2. Process Academic Results (if Save Draft or Publish was clicked)
                if ($targetDbId > 0 && ($submitAction === 'save_draft' || $submitAction === 'publish_results')) {
                    $isPublished = ($submitAction === 'publish_results') ? 1 : 0;
                    $publishStatus = ($submitAction === 'publish_results') ? 'Published' : 'Draft';
                    $pubAt = $isPublished ? date('Y-m-d H:i:s') : null;
                    $pubBy = $isPublished ? $adminId : null;
                    $reason = trim($_POST['correction_reason'] ?? 'Unified one-page academic result entry & update');

                    $submittedResults = $_POST['results'] ?? [];

                    // Fetch existing result IDs to detect updates vs inserts vs deletions
                    $existingResultIds = $db->query("SELECT id FROM student_results WHERE student_id = {$targetDbId}")->fetchAll(PDO::FETCH_COLUMN);
                    $keptResultIds = [];

                    $insRes = $db->prepare("
                        INSERT INTO student_results 
                        (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, is_published, publish_status, published_at, published_by, correction_reason)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Semester Final Examination', ?, ?, ?, ?, ?)
                    ");

                    $updRes = $db->prepare("
                        UPDATE student_results 
                        SET semester = ?, course_code = ?, course_name = ?, credit = ?, full_marks = ?, marks = ?, letter_grade = ?, grade_point = ?, is_published = ?, publish_status = ?, published_at = ?, published_by = ?, correction_reason = ?
                        WHERE id = ? AND student_id = ?
                    ");

                    foreach ($submittedResults as $semIndex => $courses) {
                        $semNum = (int)$semIndex;
                        $semTitle = $standardSemesterTitles[$semNum] ?? "Semester {$semNum}";

                        if (!empty($courses) && is_array($courses)) {
                            foreach ($courses as $c) {
                                $code = strtoupper(trim($c['course_code'] ?? ''));
                                $name = trim($c['course_name'] ?? '');
                                $credit = (float)($c['credit'] ?? 3.0);
                                $fullMarks = (int)($c['full_marks'] ?? 100);
                                $marks = (isset($c['marks']) && $c['marks'] !== '') ? (float)$c['marks'] : null;
                                $cid = (int)($c['id'] ?? 0);

                                if (empty($code) && empty($name)) {
                                    continue; // Skip blank template rows
                                }

                                if ($marks !== null) {
                                    $gr = calculate_grade_from_marks($marks);
                                    $grade = $gr['letter_grade'];
                                    $gp = $gr['grade_point'];
                                } else {
                                    $grade = trim($c['letter_grade'] ?? 'A+');
                                    $gp = (float)($c['grade_point'] ?? 4.00);
                                }

                                if ($cid > 0 && in_array($cid, $existingResultIds)) {
                                    $updRes->execute([
                                        $semTitle, $code, $name, $credit, $fullMarks, $marks, $grade, $gp,
                                        $isPublished, $publishStatus, $pubAt, $pubBy, $reason, $cid, $targetDbId
                                    ]);
                                    $keptResultIds[] = $cid;
                                } else {
                                    $insRes->execute([
                                        $targetDbId, $semTitle, $code, $name, $credit, $fullMarks, $marks, $grade, $gp,
                                        $isPublished, $publishStatus, $pubAt, $pubBy, $reason
                                    ]);
                                    $keptResultIds[] = (int)$db->lastInsertId();
                                }
                            }
                        }
                    }

                    // Delete rows removed by admin from the table
                    $toDelete = array_diff($existingResultIds, $keptResultIds);
                    if (!empty($toDelete)) {
                        $delRes = $db->prepare("DELETE FROM student_results WHERE id = ? AND student_id = ?");
                        foreach ($toDelete as $delId) {
                            $delRes->execute([$delId, $targetDbId]);
                        }
                    }

                    // Recalculate CGPA and credits
                    $resMetrics = recalculate_student_cgpa($targetDbId);

                    if ($submitAction === 'publish_results') {
                        // Check if all semesters are completed
                        $semCount = (int)$db->query("SELECT COUNT(DISTINCT semester) FROM student_results WHERE student_id = {$targetDbId} AND is_published = 1")->fetchColumn();
                        if ($semCount >= 8 || ($semCount >= 4 && str_contains($batch, 'Master'))) {
                            $db->prepare("UPDATE students SET status = 'Graduated', current_semester = 'Completed' WHERE id = ?")->execute([$targetDbId]);
                        }

                        create_student_notification(
                            $targetDbId,
                            "Official Academic Results Published",
                            "Your semester results and grade transcript have been officially published to your Student Portal. CGPA: {$resMetrics['cgpa']}.",
                            'result_published'
                        );
                        log_audit('RESULTS_PUBLISHED_ONE_PAGE', "Published results for Student #{$targetDbId} via One-Page Console");
                        flash_set('success', "Student record and all academic results successfully PUBLISHED to the Student Portal! Updated CGPA: {$resMetrics['cgpa']}.");
                    } else {
                        log_audit('RESULTS_DRAFT_ONE_PAGE', "Saved results as Draft for Student #{$targetDbId} via One-Page Console");
                        flash_set('info', "Student record and academic results successfully saved as DRAFT (hidden from Student Portal). Updated CGPA: {$resMetrics['cgpa']}.");
                    }
                } elseif ($targetDbId > 0) {
                    flash_set('success', "Student profile information saved successfully.");
                }

                if ($targetDbId > 0) {
                    header("Location: " . APP_URL . "/admin/students.php?edit=" . $targetDbId);
                    exit;
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $st = $db->prepare("SELECT student_id, full_name FROM students WHERE id = ?");
                $st->execute([$id]);
                $sData = $st->fetch();

                $del = $db->prepare("DELETE FROM students WHERE id = ?");
                $del->execute([$id]);
                log_audit('STUDENT_DELETED', "Deleted student: {$sData['full_name']} ({$sData['student_id']})");
                flash_set('success', "Student record deleted successfully.");
            }
            header("Location: " . APP_URL . "/admin/students.php");
            exit;
        }
    }
}

// Search & Filter for Directory
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'All';
$programFilter = (int)($_GET['program_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($statusFilter !== 'All') {
    $where[] = "s.status = ?";
    $params[] = $statusFilter;
}

if ($programFilter > 0) {
    $where[] = "s.program_id = ?";
    $params[] = $programFilter;
}

if (!empty($search)) {
    $where[] = "(s.student_id LIKE ? OR s.full_name LIKE ? OR s.email LIKE ? OR s.phone LIKE ? OR s.batch LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Summary Statistics
$totalStudents = (int)$db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$activeStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
$graduatedStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Graduated'")->fetchColumn();
$totalFeesCollected = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM student_fees WHERE status = 'Paid'")->fetchColumn();

// Total Filtered Count for Pagination
$countSql = "SELECT COUNT(*) FROM students s $whereSql";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Fetch Students for Directory
$sql = "
    SELECT s.*, p.title_en AS program_title, p.degree_level, d.name_en AS dept_name
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    $whereSql
    ORDER BY s.id DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k + 1, $v);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll();

// Programs with Degree Level & Departments for Dropdowns
$programs = $db->query("SELECT id, title_en, degree_level, duration, total_credits FROM programs ORDER BY sort_order, title_en")->fetchAll(PDO::FETCH_ASSOC);
$departments = $db->query("SELECT id, name_en FROM departments ORDER BY name_en")->fetchAll(PDO::FETCH_ASSOC);

// Check if One-Page Console is active (Edit student or Enroll New Student)
$isConsoleView = isset($_GET['edit']) || (isset($_GET['action']) && $_GET['action'] === 'enroll');
$editStudent = null;
$existingResultsBySem = [];

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $st = $db->prepare("SELECT s.*, p.title_en AS program_title, p.degree_level FROM students s LEFT JOIN programs p ON s.program_id = p.id WHERE s.id = ?");
    $st->execute([$editId]);
    $editStudent = $st->fetch(PDO::FETCH_ASSOC);

    if ($editStudent) {
        $resStmt = $db->prepare("SELECT * FROM student_results WHERE student_id = ? ORDER BY id ASC");
        $resStmt->execute([$editId]);
        $allRes = $resStmt->fetchAll(PDO::FETCH_ASSOC);

        $hasAnyEstimatedInStudent = false;
        foreach ($allRes as $r) {
            $sNum = get_semester_number($r['semester']);
            if ($sNum >= 1 && $sNum <= 8) {
                $existingResultsBySem[$sNum][] = $r;
            }
            if (!empty($r['is_estimated'])) {
                $hasAnyEstimatedInStudent = true;
            }
        }
    }
}

include __DIR__ . '/includes/admin-header.php';
?>

<!-- Top Banner & View Switcher -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem;">
      <i class="fas fa-id-card-alt" style="color: var(--rmu-primary);"></i> Student Management &bull; One-Page Result Console
    </h2>
    <span style="color: #64748b; font-size: 0.88rem;">
      Rabindra Maitree University &bull; Complete Student Management &bull; Unified Single-Page Student Enrollment, 8-Semester Results &amp; Transcript Publishing
    </span>
  </div>

  <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
    <a href="<?= APP_URL ?>/admin/students.php" class="btn-primary" style="background: <?= !$isConsoleView ? 'var(--rmu-primary)' : '#64748b' ?>; padding: 0.55rem 1.15rem; font-size: 0.88rem; text-decoration: none;">
      <i class="fas fa-list"></i> Enrolled Students Directory (<?= $totalStudents ?>)
    </a>
    <a href="<?= APP_URL ?>/admin/students.php?action=enroll" class="btn-primary" style="background: <?= ($isConsoleView && !$editStudent) ? '#059669' : '#0284c7' ?>; padding: 0.55rem 1.25rem; font-size: 0.88rem; text-decoration: none;">
      <i class="fas fa-user-plus"></i> Enroll Student &amp; Manage Results
    </a>
  </div>
</div>

<?php if (!$isConsoleView): ?>

  <!-- ========================================================================= -->
  <!-- VIEW 1: ENROLLED STUDENTS DIRECTORY (STATISTICS & TABLE)                  -->
  <!-- ========================================================================= -->

  <!-- Stat Cards Strip -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <div class="stat-card">
      <div class="stat-icon" style="background: #e0e7ff; color: #4338ca;">
        <i class="fas fa-user-graduate"></i>
      </div>
      <div>
        <div style="font-size: 1.6rem; font-weight: 800; color: #0f172a;"><?= $totalStudents ?></div>
        <div style="font-size: 0.85rem; color: #64748b;">Total Registered Students</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background: #dcfce7; color: #15803d;">
        <i class="fas fa-user-check"></i>
      </div>
      <div>
        <div style="font-size: 1.6rem; font-weight: 800; color: #0f172a;"><?= $activeStudents ?></div>
        <div style="font-size: 0.85rem; color: #64748b;">Active Enrolled</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background: #fef3c7; color: #b45309;">
        <i class="fas fa-graduation-cap"></i>
      </div>
      <div>
        <div style="font-size: 1.6rem; font-weight: 800; color: #0f172a;"><?= $graduatedStudents ?></div>
        <div style="font-size: 0.85rem; color: #64748b;">Alumni / Graduated</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background: #e0f2fe; color: #0369a1;">
        <i class="fas fa-money-check-alt"></i>
      </div>
      <div>
        <div style="font-size: 1.4rem; font-weight: 800; color: #0f172a;">৳<?= number_format($totalFeesCollected) ?></div>
        <div style="font-size: 0.85rem; color: #64748b;">Total Fees Collected</div>
      </div>
    </div>
  </div>

  <!-- Search & Filter Console -->
  <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 1.25rem 1.5rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
    <form action="<?= APP_URL ?>/admin/students.php" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
      <div style="flex: 2; min-width: 250px;">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by Student ID, Name, Phone, Email, Batch..." class="form-control" style="padding: 0.65rem 1rem;">
      </div>

      <div style="flex: 1; min-width: 180px;">
        <select name="program_id" class="form-select" style="padding: 0.65rem 1rem;">
          <option value="0">All Degree Programs</option>
          <?php foreach ($programs as $pr): ?>
            <option value="<?= $pr['id'] ?>" <?= ($programFilter === (int)$pr['id']) ? 'selected' : '' ?>>
              <?= e($pr['title_en']) ?> (<?= e($pr['degree_level']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width: 140px;">
        <select name="status" class="form-select" style="padding: 0.65rem 1rem;">
          <option value="All" <?= ($statusFilter === 'All') ? 'selected' : '' ?>>All Statuses</option>
          <option value="Active" <?= ($statusFilter === 'Active') ? 'selected' : '' ?>>Active</option>
          <option value="Graduated" <?= ($statusFilter === 'Graduated') ? 'selected' : '' ?>>Graduated</option>
          <option value="Suspended" <?= ($statusFilter === 'Suspended') ? 'selected' : '' ?>>Suspended</option>
        </select>
      </div>

      <button type="submit" class="btn-primary" style="padding: 0.65rem 1.4rem;">
        <i class="fas fa-search"></i> Search
      </button>
      <?php if (!empty($search) || $statusFilter !== 'All' || $programFilter > 0): ?>
        <a href="<?= APP_URL ?>/admin/students.php" style="color: #ef4444; font-size: 0.88rem; font-weight: 600;">Clear Filters</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Students Directory Table -->
  <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.04); margin-bottom: 2.5rem;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap:wrap; gap:0.5rem;">
      <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0;">
        <i class="fas fa-list-ul" style="color:var(--rmu-primary);"></i> Enrolled Students Directory
      </h3>
      <span style="font-size: 0.85rem; color: #64748b;">Showing <?= count($students) ?> of <?= $totalRows ?> registered students</span>
    </div>

    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow: none; margin: 0;">
        <thead>
          <tr>
            <th>Student ID &bull; Name</th>
            <th>Program &bull; Batch</th>
            <th>Year &bull; Semester</th>
            <th style="text-align:center;">CGPA &bull; Credits</th>
            <th style="text-align:center;">Status</th>
            <th style="text-align: right; width:220px;">One-Page Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($students)): ?>
            <?php foreach ($students as $stu): ?>
              <tr>
                <td>
                  <strong style="color: var(--rmu-primary); font-family: monospace; font-size: 0.95rem;">
                    <?= e($stu['student_id']) ?>
                  </strong>
                  <div style="font-weight: 700; color: #0f172a; font-size: 0.92rem;"><?= e($stu['full_name']) ?></div>
                  <div style="font-size: 0.78rem; color: #64748b;">
                    <i class="fas fa-envelope"></i> <?= e($stu['email']) ?> &bull; <i class="fas fa-phone-alt"></i> <?= e($stu['phone']) ?>
                  </div>
                </td>
                <td style="font-size: 0.85rem;">
                  <strong style="color: #334155; display: block;"><?= e($stu['program_title'] ?? 'General') ?></strong>
                  <span style="font-size: 0.78rem; color: #64748b;"><?= e($stu['batch']) ?></span>
                </td>
                <td style="font-size: 0.82rem; color: #475569;">
                  <div><?= e($stu['current_year']) ?></div>
                  <small style="color: #64748b;"><?= e($stu['current_semester']) ?></small>
                </td>
                <td style="font-size: 0.85rem; text-align:center;">
                  <strong style="color: #047857; font-size: 1rem; display: block;"><?= number_format($stu['cgpa'], 2) ?></strong>
                  <small style="color: #64748b;"><?= (int)$stu['credits_completed'] ?> Credits</small>
                </td>
                <td style="text-align:center;">
                  <?php
                    $stColor = ($stu['status'] === 'Active') ? 'background:#dcfce7; color:#15803d;' : (($stu['status'] === 'Graduated') ? 'background:#e0f2fe; color:#0369a1;' : 'background:#fee2e2; color:#b91c1c;');
                  ?>
                  <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; <?= $stColor ?>">
                    <?= e($stu['status']) ?>
                  </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <!-- Direct One-Page Management Trigger -->
                  <a href="<?= APP_URL ?>/admin/students.php?edit=<?= $stu['id'] ?>" class="btn-primary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; background: #059669; display: inline-flex; align-items:center; gap:4px; text-decoration:none;" title="Open Unified One-Page Student & Results Form">
                    <i class="fas fa-edit"></i> Edit &amp; Results
                  </a>
                  <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= $stu['id'] ?>" class="btn-primary" style="padding: 0.4rem 0.65rem; font-size: 0.8rem; background: #0284c7; display: inline-flex; align-items:center; gap:4px; text-decoration:none;" title="View Dossier">
                    <i class="fas fa-eye"></i> Details
                  </a>
                  <form action="<?= APP_URL ?>/admin/students.php" method="POST" style="display: inline-block;" onsubmit="return confirm('Delete student <?= e($stu['full_name']) ?> (<?= e($stu['student_id']) ?>)? All associated academic results will also be deleted!');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $stu['id'] ?>">
                    <button type="submit" style="background: #fee2e2; border: none; color: #ef4444; font-size: 0.82rem; cursor: pointer; padding: 0.4rem 0.6rem; border-radius:4px; font-weight: 600;" title="Delete Student">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 3rem; color: #64748b;">
                No students match your query.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div style="padding: 1rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: center;">
        <ul class="pagination" style="margin: 0;">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
              <a href="<?= APP_URL ?>/admin/students.php?search=<?= urlencode($search) ?>&program_id=<?= $programFilter ?>&status=<?= urlencode($statusFilter) ?>&page=<?= $i ?>" class="page-link"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>

<?php else: ?>

  <!-- ========================================================================= -->
  <!-- VIEW 2: ONE-PAGE COMPLETE STUDENT & ACADEMIC RESULT MANAGEMENT FORM       -->
  <!-- ========================================================================= -->

  <div style="background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 3rem;" id="onePageStudentConsole">
    
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #0f2c59; padding-bottom: 1.25rem; margin-bottom: 2rem; flex-wrap:wrap; gap:1rem;">
      <div>
        <h3 style="font-size: 1.35rem; font-weight: 800; color: #0f2c59; margin: 0 0 0.3rem; display:flex; align-items:center; gap:8px;">
          <i class="fas <?= $editStudent ? 'fa-user-edit' : 'fa-user-plus' ?>" style="color: var(--rmu-primary);"></i>
          <?= $editStudent ? 'Edit Student &amp; Academic Result Console' : 'Enroll New Student &amp; Setup Results' ?>
        </h3>
        <p style="margin: 0; font-size: 0.88rem; color: #64748b;">
          Single-Page Unified Form: Manage profile, personal credentials, and all 8 semesters (or 4 for Master's) from this one form.
        </p>
      </div>

      <div style="display:flex; gap:0.6rem; align-items:center;">
        <?php if ($editStudent): ?>
          <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= (int)$editStudent['id'] ?>&semester=all" target="_blank" class="btn-primary" style="background:#15803d; padding:0.5rem 1rem; font-size:0.84rem; text-decoration:none;">
            <i class="fas fa-file-pdf"></i> Official PDF Transcript
          </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/admin/students.php" class="btn-primary" style="background: #64748b; padding: 0.5rem 1rem; font-size: 0.84rem; text-decoration: none;">
          <i class="fas fa-times"></i> Close Console
        </a>
      </div>
    </div>

    <form action="<?= APP_URL ?>/admin/students.php" method="POST" id="unifiedStudentForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editStudent ? 'edit' : 'add' ?>">
      <?php if ($editStudent): ?>
        <input type="hidden" name="id" id="currentStudentDbId" value="<?= (int)$editStudent['id'] ?>">
      <?php endif; ?>

      <!-- ------------------------------------------------------------------- -->
      <!-- SECTION 1: ALL 21 STUDENT PROFILE & ACADEMIC INFORMATION FIELDS     -->
      <!-- ------------------------------------------------------------------- -->
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1.5rem; margin-bottom:2.5rem;">
        <h4 style="margin:0 0 1.25rem; font-size:1.05rem; font-weight:800; color:#0f2c59; border-bottom:1.5px solid #cbd5e1; padding-bottom:8px; display:flex; align-items:center; gap:8px;">
          <i class="fas fa-user-circle" style="color:var(--rmu-primary);"></i> 1. Student Personal &amp; Academic Information (All 21 Fields)
        </h4>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem;">
          
          <!-- 1. Student ID -->
          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Student ID Number</label>
            <input type="text" name="student_id" id="formStudentId" value="<?= e($editStudent['student_id'] ?? '') ?>" placeholder="e.g. RMU2601005 (or blank to auto-generate)" class="form-control" <?= $editStudent ? 'readonly style="background:#f1f5f9; font-weight:700;"' : '' ?>>
          </div>

          <!-- 2. Full Name -->
          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Full Name *</label>
            <input type="text" name="full_name" id="formFullName" value="<?= e($editStudent['full_name'] ?? '') ?>" placeholder="e.g. Mahfuzur Rahman" class="form-control" required>
          </div>

          <!-- 3. Official Email -->
          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Official Email *</label>
            <input type="email" name="email" id="formEmail" value="<?= e($editStudent['email'] ?? '') ?>" placeholder="student@rmu.ac.bd" class="form-control" required>
          </div>

          <!-- 4. Mobile Number -->
          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Mobile Phone *</label>
            <input type="text" name="phone" id="formPhone" value="<?= e($editStudent['phone'] ?? '') ?>" placeholder="017xxxxxxxx" class="form-control" required>
          </div>

          <!-- 5. Father's Name -->
          <div class="form-group">
            <label class="form-label">Father's Name</label>
            <input type="text" name="father_name" value="<?= e($editStudent['father_name'] ?? '') ?>" placeholder="Father's full name" class="form-control">
          </div>

          <!-- 6. Mother's Name -->
          <div class="form-group">
            <label class="form-label">Mother's Name</label>
            <input type="text" name="mother_name" value="<?= e($editStudent['mother_name'] ?? '') ?>" placeholder="Mother's full name" class="form-control">
          </div>

          <!-- 7. Date of Birth -->
          <div class="form-group">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="dob" value="<?= e($editStudent['dob'] ?? '') ?>" class="form-control">
          </div>

          <!-- 8. Gender -->
          <div class="form-group">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-select">
              <option value="Male" <?= (($editStudent['gender'] ?? 'Male') === 'Male') ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= (($editStudent['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= (($editStudent['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
            </select>
          </div>

          <!-- 9. Present Address -->
          <div class="form-group">
            <label class="form-label">Present Address</label>
            <input type="text" name="present_address" value="<?= e($editStudent['present_address'] ?? '') ?>" placeholder="Present address" class="form-control">
          </div>

          <!-- 10. Permanent Address -->
          <div class="form-group">
            <label class="form-label">Permanent Address</label>
            <input type="text" name="permanent_address" value="<?= e($editStudent['permanent_address'] ?? '') ?>" placeholder="Permanent address" class="form-control">
          </div>

          <!-- 11. Degree Program (Triggers 8 vs 4 Semesters) -->
          <div class="form-group">
            <label class="form-label" style="font-weight:700; color:#1e40af;">
              Degree Program (Bachelor's / Master's) *
            </label>
            <select name="program_id" id="programSelect" class="form-select" onchange="handleProgramChange(this.value)">
              <option value="">Select Degree Program</option>
              <?php foreach ($programs as $pr): ?>
                <option value="<?= $pr['id'] ?>" data-level="<?= e($pr['degree_level']) ?>" data-duration="<?= e($pr['duration']) ?>" <?= (($editStudent['program_id'] ?? 1) == $pr['id']) ? 'selected' : '' ?>>
                  <?= e($pr['title_en']) ?> — <?= e($pr['degree_level']) ?> (<?= e($pr['duration']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- 12. Academic Department -->
          <div class="form-group">
            <label class="form-label">Academic Department</label>
            <select name="department_id" class="form-select">
              <option value="">Select Department</option>
              <?php foreach ($departments as $dp): ?>
                <option value="<?= $dp['id'] ?>" <?= (($editStudent['department_id'] ?? 6) == $dp['id']) ? 'selected' : '' ?>>
                  <?= e($dp['name_en']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- 13. Academic Session / Batch -->
          <div class="form-group">
            <label class="form-label">Academic Session / Batch</label>
            <input type="text" name="batch" id="formBatch" value="<?= e($editStudent['batch'] ?? 'Batch 26 (2026–2030)') ?>" class="form-control">
          </div>

          <!-- 14. Current Year -->
          <div class="form-group">
            <label class="form-label">Current Academic Year</label>
            <select name="current_year" class="form-select">
              <option value="1st Year" <?= (($editStudent['current_year'] ?? '') === '1st Year') ? 'selected' : '' ?>>1st Year</option>
              <option value="2nd Year" <?= (($editStudent['current_year'] ?? '') === '2nd Year') ? 'selected' : '' ?>>2nd Year</option>
              <option value="3rd Year" <?= (($editStudent['current_year'] ?? '') === '3rd Year') ? 'selected' : '' ?>>3rd Year</option>
              <option value="4th Year" <?= (($editStudent['current_year'] ?? '') === '4th Year') ? 'selected' : '' ?>>4th Year</option>
            </select>
          </div>

          <!-- 15. Current Semester -->
          <div class="form-group">
            <label class="form-label">Current Semester Term</label>
            <input type="text" name="current_semester" value="<?= e($editStudent['current_semester'] ?? '1st Semester') ?>" class="form-control">
          </div>

          <!-- 16. Enrollment Status -->
          <div class="form-group">
            <label class="form-label">Enrollment Status</label>
            <select name="status" class="form-select">
              <option value="Active" <?= (($editStudent['status'] ?? '') === 'Active') ? 'selected' : '' ?>>Active</option>
              <option value="Graduated" <?= (($editStudent['status'] ?? '') === 'Graduated') ? 'selected' : '' ?>>Graduated</option>
              <option value="Suspended" <?= (($editStudent['status'] ?? '') === 'Suspended') ? 'selected' : '' ?>>Suspended</option>
            </select>
          </div>

          <!-- 17. Cumulative CGPA (One-Click Update & Generator) -->
          <div class="form-group" style="grid-column: span 2;">
            <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
              <span>Cumulative CGPA (UGC 4.00 Scale)</span>
              <span style="font-size:0.75rem; color:#b45309; font-weight:700;"><i class="fas fa-magic"></i> Auto-Generate Available</span>
            </label>
            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
              <input type="number" step="0.01" min="2.00" max="4.00" name="cgpa" id="formCgpa" value="<?= e($editStudent['cgpa'] ?? '0.00') ?>" class="form-control" style="font-weight:900; color:#0f2c59; font-size:1.1rem; width:130px; text-align:center;" placeholder="e.g. 3.85">
              
              <button type="submit" name="submit_action" value="update_cgpa_and_generate" class="btn-primary" style="background:#f59e0b; color:#0f172a; font-weight:800; font-size:0.82rem; padding:0.6rem 1rem; border:none; display:inline-flex; align-items:center; gap:6px;" title="Update CGPA and generate plausible subject marks for all semesters">
                <i class="fas fa-bolt"></i> Update CGPA &amp; Generate Results
              </button>

              <button type="submit" name="submit_action" value="update_cgpa_only" class="btn-primary" style="background:#64748b; color:#ffffff; font-weight:700; font-size:0.82rem; padding:0.6rem 1rem; border:none;" title="Update CGPA without regenerating semester results">
                <i class="fas fa-save"></i> Update CGPA Only
              </button>
            </div>
            <small style="color: #64748b;">Enter target CGPA (2.00 – 4.00). Click <strong>"Update CGPA &amp; Generate Results"</strong> to auto-create plausible semester-wise marks, grades and SGPA.</small>
          </div>

          <!-- 18. Completed Credits (Live Auto-Sync) -->
          <div class="form-group">
            <label class="form-label">Credits Completed (Live Sync)</label>
            <input type="number" min="0" name="credits_completed" id="formCreditsCompleted" value="<?= (int)($editStudent['credits_completed'] ?? 0) ?>" class="form-control" readonly style="background:#f1f5f9; font-weight:800; color:#0f2c59;">
          </div>

          <!-- 19. Blood Group -->
          <div class="form-group">
            <label class="form-label">Blood Group</label>
            <input type="text" name="blood_group" value="<?= e($editStudent['blood_group'] ?? '') ?>" placeholder="e.g. B+, O+" class="form-control">
          </div>

          <!-- 20. Home District -->
          <div class="form-group">
            <label class="form-label">Home District</label>
            <input type="text" name="home_district" value="<?= e($editStudent['home_district'] ?? '') ?>" placeholder="e.g. Kushtia" class="form-control">
          </div>

          <!-- 21. Initial Portal Password / Reset Password -->
          <div class="form-group">
            <label class="form-label"><?= $editStudent ? 'Reset Password (optional)' : 'Initial Portal Password' ?></label>
            <input type="text" name="<?= $editStudent ? 'new_password' : 'password' ?>" value="<?= $editStudent ? '' : 'student123' ?>" placeholder="<?= $editStudent ? 'Leave blank to retain password' : 'Default: student123' ?>" class="form-control">
            <small style="color: #64748b;">Student will authenticate with this password in the Student Portal.</small>
          </div>

        </div>
      </div>

      <!-- ------------------------------------------------------------------- -->
      <!-- SECTION 2: COMPLETE ACADEMIC RESULT MANAGEMENT (EXPANDABLE)        -->
      <!-- ------------------------------------------------------------------- -->
      <div style="background:#ffffff; border:1.5px solid #0f2c59; border-radius:10px; overflow:hidden; margin-bottom:2rem; box-shadow:0 3px 10px rgba(15,44,89,0.08);" id="resultManagementSection">
        
        <div style="background:linear-gradient(135deg, #0f2c59 0%, #1e3a8a 100%); color:#ffffff; padding:1.1rem 1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
          <div>
            <h3 style="margin:0 0 0.2rem; font-size:1.25rem; font-weight:800; color:#ffffff; display:flex; align-items:center; gap:8px;">
              <i class="fas fa-award" style="color:#f59e0b;"></i> 2. Complete Academic Result Management
            </h3>
            <span id="curriculumBadge" style="font-size:0.84rem; color:#cbd5e1;">
              Bachelor's Degree Curriculum &bull; 8 Semesters Available
            </span>
          </div>

          <div style="display:flex; gap:0.6rem; align-items:center;">
            <button type="button" onclick="expandAllSemesters()" style="background:rgba(255,255,255,0.18); color:#ffffff; border:1px solid rgba(255,255,255,0.3); border-radius:4px; padding:4px 10px; font-size:0.78rem; font-weight:700; cursor:pointer;">
              <i class="fas fa-expand-alt"></i> Expand All
            </button>
            <button type="button" onclick="collapseAllSemesters()" style="background:rgba(255,255,255,0.18); color:#ffffff; border:1px solid rgba(255,255,255,0.3); border-radius:4px; padding:4px 10px; font-size:0.78rem; font-weight:700; cursor:pointer;">
              <i class="fas fa-compress-alt"></i> Collapse All
            </button>
          </div>
        </div>

        <div style="padding:1.5rem;">
          
          <!-- One-Click CGPA Update & Auto-Estimated Result Generator Card -->
          <div style="background: linear-gradient(135deg, #0f2c59 0%, #1e40af 100%); border-radius: 8px; padding: 1.25rem 1.5rem; margin-bottom: 1.75rem; color: #ffffff; box-shadow: 0 4px 12px rgba(15,44,89,0.15);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem;">
              <div style="flex:1; min-width:280px;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:0.35rem;">
                  <span style="background:#f59e0b; color:#0f172a; font-size:0.75rem; font-weight:900; padding:2px 8px; border-radius:4px; text-transform:uppercase;">
                    <i class="fas fa-magic"></i> Generator
                  </span>
                  <h4 style="margin:0; font-size:1.15rem; font-weight:800; color:#ffffff;">
                    One-Click CGPA Update &amp; Automatic Estimated Results
                  </h4>
                </div>
                <p style="margin:0; font-size:0.85rem; color:#cbd5e1; line-height:1.45;">
                  Enter target CGPA below. The system automatically identifies degree level (<strong><?= ($editStudent && strtolower($editStudent['degree_level'] ?? '') === 'postgraduate') ? 'Postgraduate (4 Semesters)' : 'Undergraduate (8 Semesters)' ?></strong>), loads official curriculum subjects, and generates plausible marks &amp; grades mathematically matching your target CGPA.
                </p>
                <div style="margin-top:0.45rem; font-size:0.78rem; color:#fde68a;">
                  <i class="fas fa-exclamation-triangle"></i> Results are clearly flagged: <em>"ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT"</em>
                </div>
              </div>

              <!-- One-Click Target Input and Buttons -->
              <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
                <div style="background:#ffffff; border-radius:6px; padding:4px 10px; display:flex; align-items:center; border:2px solid #f59e0b;">
                  <span style="font-weight:800; color:#0f2c59; font-size:0.85rem; margin-right:6px;">Target CGPA:</span>
                  <input type="number" step="0.01" min="2.00" max="4.00" name="target_cgpa_input" id="targetCgpaInput" value="<?= number_format((float)($editStudent['cgpa'] ?? 3.50), 2) ?>" style="width:80px; font-weight:900; font-size:1.1rem; color:#1e40af; border:none; outline:none; text-align:center;">
                </div>

                <!-- Update CGPA & Auto-Generate Button -->
                <button type="submit" name="submit_action" value="auto_generate_estimated" class="btn-primary" style="background:#f59e0b; color:#0f172a; font-weight:800; border:none; padding:0.65rem 1.25rem; font-size:0.88rem; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                  <i class="fas fa-bolt"></i> Update CGPA &amp; Generate Results
                </button>

                <!-- Bulk Verify All Estimated Courses Button -->
                <?php if (!empty($hasAnyEstimatedInStudent)): ?>
                  <button type="submit" name="submit_action" value="verify_all_as_official" class="btn-primary" style="background:#15803d; color:#ffffff; font-weight:700; border:none; padding:0.65rem 1rem; font-size:0.85rem; cursor:pointer;" onclick="return confirm('Verify ALL estimated course results as Official University Examination Records?');">
                    <i class="fas fa-check-double"></i> Verify All as Official
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- 8 Semesters Containers (Semesters 5-8 automatically hide for Postgraduate) -->
          <div id="semestersContainer">
            <?php for ($s = 1; $s <= 8; $s++): ?>
              <?php
                $existingSemCourses = $existingResultsBySem[$s] ?? [];
                $hasCourses = !empty($existingSemCourses);
                $allPub = true;
                $hasEstimatedInSem = false;
                if ($hasCourses) {
                    foreach ($existingSemCourses as $ec) {
                        if (empty($ec['is_published'])) { $allPub = false; }
                        if (!empty($ec['is_estimated'])) { $hasEstimatedInSem = true; }
                    }
                } else {
                    $allPub = false;
                }
              ?>
              <div class="semester-wrapper" id="sem-wrapper-<?= $s ?>" data-sem="<?= $s ?>" style="border:1px solid #cbd5e1; border-radius:8px; margin-bottom:1.25rem; overflow:hidden;">
                
                <!-- Semester Header -->
                <div class="sem-header" onclick="toggleSemesterAccordion(<?= $s ?>)" style="background:#f8fafc; padding:0.85rem 1.25rem; display:flex; justify-content:space-between; align-items:center; cursor:pointer; user-select:none; border-bottom:1px solid #e2e8f0;">
                  <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                    <span style="background:#0f2c59; color:#ffffff; font-weight:900; font-size:0.85rem; padding:2px 8px; border-radius:4px;">
                      <?= $s ?>
                    </span>
                    <strong style="color:#0f172a; font-size:0.98rem;"><?= $standardSemesterTitles[$s] ?></strong>
                    
                    <span class="sem-pub-badge" id="sem-pub-badge-<?= $s ?>" style="font-size:0.72rem; font-weight:700; padding:2px 7px; border-radius:4px; <?= $hasCourses ? ($allPub ? 'background:#dcfce7; color:#15803d;' : 'background:#fef3c7; color:#b45309;') : 'background:#e2e8f0; color:#64748b;' ?>">
                      <?= $hasCourses ? ($allPub ? 'Published' : 'Draft / Unpublished') : 'Pending (0 Subjects)' ?>
                    </span>

                    <?php if ($hasEstimatedInSem): ?>
                      <span style="font-size:0.72rem; font-weight:800; background:#fef3c7; color:#92400e; border:1px solid #fde68a; padding:2px 7px; border-radius:4px; text-transform:uppercase;">
                        <i class="fas fa-robot"></i> Estimated
                      </span>
                      <button type="submit" name="submit_action" value="verify_semester_<?= $s ?>" style="background:#15803d; color:#ffffff; border:none; padding:2px 8px; border-radius:4px; font-size:0.72rem; font-weight:700; cursor:pointer;" onclick="event.stopPropagation();" title="Verify Semester <?= $s ?> as Official Examination Record">
                        <i class="fas fa-check"></i> Verify Official
                      </button>
                    <?php endif; ?>
                  </div>

                  <div style="display:flex; align-items:center; gap:0.85rem; font-size:0.82rem;">
                    <span style="color:#475569;">Reg: <strong id="sem-reg-cr-<?= $s ?>" style="color:#0f172a;">0.0</strong> Cr</span>
                    <span style="color:#475569;">Earned: <strong id="sem-earned-cr-<?= $s ?>" style="color:#15803d;">0.0</strong> Cr</span>
                    <span style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; padding:2px 8px; border-radius:4px; font-weight:800;">
                      SGPA: <span id="sem-sgpa-<?= $s ?>">0.00</span>
                    </span>
                    <i class="fas fa-chevron-down sem-icon" id="sem-icon-<?= $s ?>" style="color:#64748b; transition:transform 0.2s;"></i>
                  </div>
                </div>

                <!-- Semester Body -->
                <div class="sem-body" id="sem-body-<?= $s ?>" style="display: <?= ($s <= 2 || $hasCourses) ? 'block' : 'none' ?>; padding:1.25rem;">
                  
                  <div class="table-responsive" style="margin-bottom:0.85rem;">
                    <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.86rem; border:1px solid #e2e8f0;">
                      <thead>
                        <tr>
                          <th style="width:120px;">Course Code *</th>
                          <th>Subject Name / Title *</th>
                          <th style="width:75px; text-align:center;">Credit</th>
                          <th style="width:70px; text-align:center;">Full</th>
                          <th style="width:90px; text-align:center;">Marks</th>
                          <th style="width:75px; text-align:center;">Grade</th>
                          <th style="width:75px; text-align:center;">Point</th>
                          <th style="width:80px; text-align:center;">Status</th>
                          <th style="width:50px; text-align:center;">Action</th>
                        </tr>
                      </thead>
                      <tbody id="sem-tbody-<?= $s ?>">
                        <?php if ($hasCourses): ?>
                          <?php foreach ($existingSemCourses as $cIdx => $co): ?>
                            <tr class="subject-row" data-sem="<?= $s ?>">
                              <td>
                                <input type="hidden" name="results[<?= $s ?>][<?= $cIdx ?>][id]" value="<?= (int)$co['id'] ?>">
                                <div style="display:flex; align-items:center; gap:4px;">
                                  <input type="text" name="results[<?= $s ?>][<?= $cIdx ?>][course_code]" class="form-control sub-code" value="<?= e($co['course_code']) ?>" placeholder="e.g. CSE 101" required style="text-transform:uppercase; font-weight:700; font-family:monospace; font-size:0.85rem;">
                                  <?php if (!empty($co['is_estimated'])): ?>
                                    <span style="background:#fef3c7; color:#92400e; font-size:0.65rem; font-weight:800; padding:1px 4px; border-radius:3px; border:1px solid #fde68a;" title="Estimated / Auto-Generated">EST</span>
                                  <?php endif; ?>
                                </div>
                              </td>
                              <td>
                                <input type="text" name="results[<?= $s ?>][<?= $cIdx ?>][course_name]" class="form-control sub-name" value="<?= e($co['course_name']) ?>" placeholder="Subject Title" required style="font-size:0.85rem;">
                              </td>
                              <td>
                                <input type="number" step="0.5" min="0.5" max="6.0" name="results[<?= $s ?>][<?= $cIdx ?>][credit]" class="form-control sub-credit" value="<?= number_format($co['credit'], 1) ?>" required style="text-align:center; font-size:0.85rem;" oninput="recalculateRow(this)">
                              </td>
                              <td>
                                <input type="number" name="results[<?= $s ?>][<?= $cIdx ?>][full_marks]" class="form-control sub-full" value="<?= (int)$co['full_marks'] ?>" readonly style="text-align:center; background:#f1f5f9; font-size:0.85rem;">
                              </td>
                              <td>
                                <input type="number" step="0.5" min="0" max="100" name="results[<?= $s ?>][<?= $cIdx ?>][marks]" class="form-control sub-marks" value="<?= ($co['marks'] !== null) ? number_format($co['marks'], 1) : '' ?>" placeholder="0-100" required style="text-align:center; font-weight:700; color:#0f2c59; font-size:0.9rem;" oninput="recalculateRow(this)">
                              </td>
                              <td>
                                <input type="text" name="results[<?= $s ?>][<?= $cIdx ?>][letter_grade]" class="form-control sub-grade" value="<?= e($co['letter_grade']) ?>" readonly style="text-align:center; font-weight:800; color:#1e40af; background:#f8fafc; font-size:0.85rem;">
                              </td>
                              <td>
                                <input type="text" name="results[<?= $s ?>][<?= $cIdx ?>][grade_point]" class="form-control sub-point" value="<?= number_format($co['grade_point'], 2) ?>" readonly style="text-align:center; font-weight:700; color:#047857; background:#f8fafc; font-size:0.85rem;">
                              </td>
                              <td style="text-align:center;">
                                <span class="sub-status-badge" style="font-size:0.75rem; font-weight:800; padding:2px 6px; border-radius:4px; <?= ($co['grade_point'] >= 2.00) ? 'background:#dcfce7; color:#15803d;' : 'background:#fee2e2; color:#dc2626;' ?>">
                                  <?= ($co['grade_point'] >= 2.00) ? 'Pass' : 'Fail' ?>
                                </span>
                              </td>
                              <td style="text-align:center;">
                                <button type="button" onclick="removeSubjectRow(this)" style="background:#fee2e2; color:#ef4444; border:none; border-radius:4px; padding:4px 7px; cursor:pointer;" title="Delete Subject">
                                  <i class="fas fa-trash-alt"></i>
                                </button>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
                    <div style="display:flex; gap:0.5rem;">
                      <button type="button" onclick="addSubjectRow(<?= $s ?>)" class="btn-primary" style="background:#0284c7; padding:0.4rem 0.85rem; font-size:0.8rem; border:none; cursor:pointer;">
                        <i class="fas fa-plus-circle"></i> Add Subject to Semester <?= $s ?>
                      </button>
                      <button type="button" onclick="prefillStandardSyllabus(<?= $s ?>)" style="background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; border-radius:4px; padding:0.4rem 0.85rem; font-size:0.8rem; font-weight:600; cursor:pointer;">
                        <i class="fas fa-magic"></i> Pre-fill Standard Subjects
                      </button>
                    </div>

                    <div style="font-size:0.82rem; color:#64748b;">
                      Semester <?= $s ?> Final Summary: SGPA <strong id="sem-sgpa-text-<?= $s ?>" style="color:#0f2c59;">0.00</strong>
                    </div>
                  </div>

                </div>

              </div>
            <?php endfor; ?>
          </div>

        </div>
      </div>

      <!-- ------------------------------------------------------------------- -->
      <!-- SECTION 3: LIVE CUMULATIVE ACADEMIC SUMMARY BANNER                 -->
      <!-- ------------------------------------------------------------------- -->
      <div style="background:linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%); border:1.5px solid #86efac; border-radius:10px; padding:1.5rem; margin-bottom:2rem; box-shadow:0 2px 6px rgba(0,0,0,0.04);">
        <h4 style="margin:0 0 1rem; font-size:1.05rem; font-weight:800; color:#0f2c59; display:flex; align-items:center; gap:8px;">
          <i class="fas fa-calculator" style="color:#15803d;"></i> 3. Live Academic Progress &amp; CGPA Summary
        </h4>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; text-align:center;">
          <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:0.75rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Total Reg Credits</div>
            <div id="liveTotalRegCredits" style="font-size:1.35rem; font-weight:800; color:#0f2c59; margin-top:3px;">0.0 Cr</div>
          </div>

          <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:0.75rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Total Earned Credits</div>
            <div id="liveTotalEarnedCredits" style="font-size:1.35rem; font-weight:800; color:#15803d; margin-top:3px;">0.0 Cr</div>
          </div>

          <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:0.75rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Completed Semesters</div>
            <div id="liveCompletedSemesters" style="font-size:1.35rem; font-weight:800; color:#0284c7; margin-top:3px;">0 / 8</div>
          </div>

          <div style="background:#ffffff; border:1.5px solid #86efac; border-radius:8px; padding:0.75rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; color:#166534; font-weight:800;">Cumulative CGPA</div>
            <div id="liveCumulativeCgpa" style="font-size:1.45rem; font-weight:900; color:#15803d; margin-top:3px;">0.00</div>
          </div>

          <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:0.75rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Degree Status</div>
            <div id="liveDegreeStatus" style="font-size:0.95rem; font-weight:800; color:#0369a1; margin-top:6px;">UNDERGRADUATE</div>
          </div>
        </div>

        <div class="form-group" style="margin-top:1.25rem; margin-bottom:0;">
          <label class="form-label" style="font-weight:700; color:#0f2c59;">
            <i class="fas fa-clipboard-check"></i> Reason for Result Update / Verification Note
          </label>
          <input type="text" name="correction_reason" placeholder="e.g. Initial semester result entry or administrative grade verification" class="form-control" style="background:#ffffff; border:1px solid #cbd5e1;">
        </div>
      </div>

      <!-- ------------------------------------------------------------------- -->
      <!-- SECTION 4: ONE-PAGE ACTIONS (SAVE INFO, DRAFT, PREVIEW, PUBLISH)    -->
      <!-- ------------------------------------------------------------------- -->
      <div style="background:#0f2c59; border-radius:10px; padding:1.25rem 1.75rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div style="color:#ffffff; font-size:0.86rem;">
          <i class="fas fa-shield-alt" style="color:#f59e0b;"></i> All student records and academic grades are saved directly against this unique Student ID.
        </div>

        <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
          <!-- Action 1: Save Student Information -->
          <button type="submit" name="submit_action" value="save_student_only" class="btn-primary" style="background:#0284c7; border:none; padding:0.65rem 1.25rem; font-size:0.88rem; font-weight:700; cursor:pointer;">
            <i class="fas fa-user-check"></i> Save Student Information
          </button>

          <!-- Action 2: Save Academic Results (Draft) -->
          <button type="submit" name="submit_action" value="save_draft" class="btn-primary" style="background:#d97706; border:none; padding:0.65rem 1.25rem; font-size:0.88rem; font-weight:700; cursor:pointer;" title="Saves results as draft hidden from the Student Portal">
            <i class="fas fa-save"></i> Save Academic Results (Draft)
          </button>

          <!-- Action 3: Preview Complete Transcript -->
          <button type="button" onclick="openTranscriptPreview()" class="btn-primary" style="background:#4f46e5; border:none; padding:0.65rem 1.25rem; font-size:0.88rem; font-weight:700; cursor:pointer;">
            <i class="fas fa-eye"></i> Preview Complete Transcript
          </button>

          <!-- Action 4: Publish Academic Results -->
          <button type="submit" name="submit_action" value="publish_results" class="btn-primary" style="background:#15803d; border:none; padding:0.65rem 1.5rem; font-size:0.92rem; font-weight:800; cursor:pointer; box-shadow:0 3px 8px rgba(21,128,61,0.4);" onclick="return confirm('Publish all verified academic results to the Student Portal? The student will immediately see their subjects, marks, grades, SGPA and cumulative CGPA.');">
            <i class="fas fa-check-circle"></i> Publish Academic Results
          </button>
        </div>
      </div>

    </form>
  </div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- MODAL: PREVIEW COMPLETE TRANSCRIPT & CALCULATIONS (Section 6)             -->
<!-- ========================================================================= -->
<div id="transcriptPreviewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:9999; justify-content:center; align-items:center; padding:1.5rem; overflow-y:auto;">
  <div style="background:#ffffff; border-radius:12px; max-width:850px; width:100%; padding:2.5rem; box-shadow:0 25px 60px rgba(0,0,0,0.3); position:relative; max-height:92vh; overflow-y:auto;">
    
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; border-bottom:2px solid #0f172a; padding-bottom:1rem;">
      <div style="text-align:left;">
        <h3 style="margin:0 0 0.2rem; font-size:1.35rem; color:#0f172a; font-weight:800; text-transform:uppercase;">
          Rabindra Maitree University, Kushtia
        </h3>
        <div style="font-size:0.85rem; color:#475569; font-weight:600;">Office of the Controller of Examinations &bull; Unofficial Grade Transcript Preview</div>
      </div>
      <button type="button" onclick="closeTranscriptPreview()" style="border:none; background:none; font-size:1.5rem; color:#64748b; cursor:pointer;" title="Close">&times;</button>
    </div>

    <!-- Student Details Grid -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.5rem; display:grid; grid-template-columns:1fr 1fr; gap:0.6rem 2rem; font-size:0.86rem;">
      <div><span style="color:#64748b;">Student Name:</span> <strong id="modalStName"></strong></div>
      <div><span style="color:#64748b;">Student ID:</span> <strong id="modalStId" style="font-family:monospace; color:#0284c7;"></strong></div>
      <div><span style="color:#64748b;">Degree Program:</span> <strong id="modalStProgram"></strong></div>
      <div><span style="color:#64748b;">Batch / Session:</span> <strong id="modalStBatch"></strong></div>
    </div>

    <!-- Transcript Tables Container -->
    <div id="modalTranscriptTables" style="margin-bottom:1.5rem;">
      <!-- Populated dynamically via JS -->
    </div>

    <!-- Summary Box -->
    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div>
        <div style="font-size:0.82rem; color:#166534; font-weight:700;">CUMULATIVE METRICS PREVIEW</div>
        <div style="font-size:0.9rem; color:#0f2c59; margin-top:2px;">
          Total Registered: <strong id="modalTotalReg">0.0</strong> Cr &bull; Completed: <strong id="modalTotalEarned">0.0</strong> Cr
        </div>
      </div>
      <div style="background:#ffffff; border:1.5px solid #86efac; border-radius:6px; padding:6px 14px; text-align:center;">
        <span style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Final CGPA</span>
        <div id="modalFinalCgpa" style="font-size:1.35rem; font-weight:900; color:#15803d;">0.00</div>
      </div>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
      <button type="button" onclick="closeTranscriptPreview()" class="btn-primary" style="background:#64748b; border:none; padding:0.55rem 1.25rem; font-size:0.86rem; cursor:pointer;">
        Close Preview
      </button>
    </div>

  </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT: DYNAMIC AUTO-GRADING, SYLLABUS, AND CALCULATION ENGINE        -->
<!-- ========================================================================= -->
<script>
var programsData = <?= json_encode($programs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
var activeSemestersCount = 8;

// Standard Sylabuses for Quick Pre-fill
var standardSyllabus = {
  1: [
    { code: 'CSE 101', name: 'Structured Programming Language (C)', credit: 3.0, marks: 85.0 },
    { code: 'CSE 102', name: 'Structured Programming Laboratory', credit: 1.5, marks: 88.0 },
    { code: 'MAT 101', name: 'Differential and Integral Calculus', credit: 3.0, marks: 80.0 },
    { code: 'ENG 101', name: 'English Reading and Composition Skills', credit: 3.0, marks: 75.0 }
  ],
  2: [
    { code: 'CSE 103', name: 'Discrete Mathematics for Computing', credit: 3.0, marks: 82.0 },
    { code: 'CSE 104', name: 'Object Oriented Programming (C++)', credit: 3.0, marks: 86.0 },
    { code: 'CSE 105', name: 'Object Oriented Programming Lab', credit: 1.5, marks: 90.0 },
    { code: 'PHY 101', name: 'Physics for Engineers (Mechanics & Waves)', credit: 3.0, marks: 78.0 }
  ],
  3: [
    { code: 'CSE 201', name: 'Data Structures & Algorithms', credit: 3.0, marks: 84.0 },
    { code: 'CSE 202', name: 'Data Structures Laboratory', credit: 1.5, marks: 92.0 },
    { code: 'CSE 203', name: 'Digital Logic Design & Circuits', credit: 3.0, marks: 80.0 },
    { code: 'EEE 101', name: 'Basic Electrical Engineering & Circuit Analysis', credit: 3.0, marks: 77.0 }
  ],
  4: [
    { code: 'CSE 205', name: 'Design & Analysis of Algorithms', credit: 3.0, marks: 88.0 },
    { code: 'CSE 206', name: 'Database Management Systems (DBMS)', credit: 3.0, marks: 85.0 },
    { code: 'CSE 207', name: 'Database Management Systems Lab', credit: 1.5, marks: 90.0 },
    { code: 'MAT 201', name: 'Linear Algebra & Complex Variables', credit: 3.0, marks: 83.0 }
  ],
  5: [
    { code: 'CSE 301', name: 'Operating Systems & System Architecture', credit: 3.0, marks: 84.0 },
    { code: 'CSE 302', name: 'Operating Systems Laboratory', credit: 1.5, marks: 89.0 },
    { code: 'CSE 303', name: 'Software Engineering & System Analysis', credit: 3.0, marks: 86.0 },
    { code: 'STA 201', name: 'Statistics & Probability for Engineers', credit: 3.0, marks: 79.0 }
  ],
  6: [
    { code: 'CSE 305', name: 'Computer Networks & Internet Protocols', credit: 3.0, marks: 88.0 },
    { code: 'CSE 306', name: 'Computer Networks Laboratory', credit: 1.5, marks: 90.0 },
    { code: 'CSE 307', name: 'Web Engineering & Full-Stack Development', credit: 3.0, marks: 85.0 },
    { code: 'CSE 308', name: 'Microcontroller & Embedded Systems Lab', credit: 1.5, marks: 77.0 }
  ],
  7: [
    { code: 'CSE 401', name: 'Artificial Intelligence & Machine Learning', credit: 3.0, marks: 85.0 },
    { code: 'CSE 402', name: 'Compiler Design & Construction', credit: 3.0, marks: 77.0 },
    { code: 'CSE 403', name: 'Cyber Security & Cryptography', credit: 3.0, marks: 76.0 },
    { code: 'CSE 499A', name: 'Capstone Project & Thesis (Part I)', credit: 3.0, marks: 86.0 }
  ],
  8: [
    { code: 'CSE 405', name: 'Cloud Computing & Distributed Systems', credit: 3.0, marks: 87.0 },
    { code: 'CSE 406', name: 'Digital Image Processing & Vision', credit: 3.0, marks: 78.0 },
    { code: 'CSE 499B', name: 'Capstone Project & Defense (Part II)', credit: 3.0, marks: 90.0 },
    { code: 'MGT 401', name: 'Industrial Management & Professional Ethics', credit: 2.0, marks: 86.0 }
  ]
};

// Auto-grade calculation from marks
function calculateGrade(marks) {
  var m = parseFloat(marks);
  if (isNaN(m) || m < 0) return { grade: '', point: 0.0, status: 'Pending' };
  if (m >= 80) return { grade: 'A+', point: 4.00, status: 'Pass' };
  if (m >= 75) return { grade: 'A',  point: 3.75, status: 'Pass' };
  if (m >= 70) return { grade: 'A-', point: 3.50, status: 'Pass' };
  if (m >= 65) return { grade: 'B+', point: 3.25, status: 'Pass' };
  if (m >= 60) return { grade: 'B',  point: 3.00, status: 'Pass' };
  if (m >= 55) return { grade: 'B-', point: 2.75, status: 'Pass' };
  if (m >= 50) return { grade: 'C+', point: 2.50, status: 'Pass' };
  if (m >= 45) return { grade: 'C',  point: 2.25, status: 'Pass' };
  if (m >= 40) return { grade: 'D',  point: 2.00, status: 'Pass' };
  return { grade: 'F', point: 0.00, status: 'Fail' };
}

// Calculate individual subject row
function recalculateRow(inputEl) {
  var tr = inputEl.closest('tr');
  if (!tr) return;

  var marksVal = tr.querySelector('.sub-marks').value;
  var gr = calculateGrade(marksVal);

  tr.querySelector('.sub-grade').value = gr.grade;
  tr.querySelector('.sub-point').value = gr.point.toFixed(2);
  
  var statusBadge = tr.querySelector('.sub-status-badge');
  if (statusBadge) {
    statusBadge.textContent = gr.status;
    statusBadge.style.background = (gr.status === 'Pass') ? '#dcfce7' : ((gr.status === 'Fail') ? '#fee2e2' : '#f1f5f9');
    statusBadge.style.color = (gr.status === 'Pass') ? '#15803d' : ((gr.status === 'Fail') ? '#dc2626' : '#64748b');
  }

  var semNum = tr.getAttribute('data-sem');
  recalculateSemester(semNum);
  recalculateCumulativeCGPA();
}

// Calculate semester metrics
function recalculateSemester(semNum) {
  var tbody = document.getElementById('sem-tbody-' + semNum);
  if (!tbody) return;

  var rows = tbody.querySelectorAll('.subject-row');
  var regCredits = 0.0;
  var earnedCredits = 0.0;
  var totalPoints = 0.0;

  rows.forEach(function(r) {
    var cr = parseFloat(r.querySelector('.sub-credit').value) || 0.0;
    var gp = parseFloat(r.querySelector('.sub-point').value) || 0.0;
    var marks = r.querySelector('.sub-marks').value;

    if (marks !== '') {
      regCredits += cr;
      if (gp >= 2.00) earnedCredits += cr;
      totalPoints += (cr * gp);
    }
  });

  var sgpa = regCredits > 0 ? (totalPoints / regCredits).toFixed(2) : '0.00';

  document.getElementById('sem-reg-cr-' + semNum).textContent = regCredits.toFixed(1);
  document.getElementById('sem-earned-cr-' + semNum).textContent = earnedCredits.toFixed(1);
  document.getElementById('sem-sgpa-' + semNum).textContent = sgpa;
  document.getElementById('sem-sgpa-text-' + semNum).textContent = sgpa;

  var badge = document.getElementById('sem-pub-badge-' + semNum);
  if (badge) {
    if (rows.length === 0) {
      badge.textContent = 'Pending (0 Subjects)';
      badge.style.background = '#e2e8f0';
      badge.style.color = '#64748b';
    } else {
      badge.textContent = 'Active Entry';
      badge.style.background = '#eff6ff';
      badge.style.color = '#1e40af';
    }
  }
}

// Recalculate whole student Cumulative CGPA
function recalculateCumulativeCGPA() {
  var totalReg = 0.0;
  var totalEarned = 0.0;
  var totalPoints = 0.0;
  var completedCount = 0;

  for (var s = 1; s <= activeSemestersCount; s++) {
    var tbody = document.getElementById('sem-tbody-' + s);
    if (!tbody) continue;

    var rows = tbody.querySelectorAll('.subject-row');
    var semReg = 0.0;
    rows.forEach(function(r) {
      var cr = parseFloat(r.querySelector('.sub-credit').value) || 0.0;
      var gp = parseFloat(r.querySelector('.sub-point').value) || 0.0;
      var marks = r.querySelector('.sub-marks').value;

      if (marks !== '') {
        semReg += cr;
        totalReg += cr;
        if (gp >= 2.00) totalEarned += cr;
        totalPoints += (cr * gp);
      }
    });

    if (semReg > 0) completedCount++;
  }

  var finalCgpa = totalReg > 0 ? (totalPoints / totalReg).toFixed(2) : '0.00';

  document.getElementById('liveTotalRegCredits').textContent = totalReg.toFixed(1) + ' Cr';
  document.getElementById('liveTotalEarnedCredits').textContent = totalEarned.toFixed(1) + ' Cr';
  document.getElementById('liveCompletedSemesters').textContent = completedCount + ' / ' + activeSemestersCount;
  document.getElementById('liveCumulativeCgpa').textContent = finalCgpa;

  // Auto-sync form fields in Section 1
  var formCgpa = document.getElementById('formCgpa');
  if (formCgpa) formCgpa.value = finalCgpa;
  var formCr = document.getElementById('formCreditsCompleted');
  if (formCr) formCr.value = Math.round(totalEarned);

  // Live status
  var statusEl = document.getElementById('liveDegreeStatus');
  if (statusEl) {
    if (completedCount >= activeSemestersCount && totalEarned >= 80) {
      statusEl.textContent = 'DEGREE REQUIREMENTS MET';
      statusEl.style.color = '#15803d';
    } else {
      statusEl.textContent = (activeSemestersCount === 4) ? 'POSTGRADUATE' : 'UNDERGRADUATE';
      statusEl.style.color = '#0369a1';
    }
  }
}

// Add a new subject row to a semester
function addSubjectRow(semNum, data) {
  var tbody = document.getElementById('sem-tbody-' + semNum);
  if (!tbody) return;

  var rowIndex = tbody.querySelectorAll('tr').length;
  data = data || { code: '', name: '', credit: 3.0, marks: '' };

  var tr = document.createElement('tr');
  tr.className = 'subject-row';
  tr.setAttribute('data-sem', semNum);

  var gr = calculateGrade(data.marks);

  tr.innerHTML = 
    '<td>' +
      '<input type="hidden" name="results[' + semNum + '][' + rowIndex + '][id]" value="' + (data.id || '') + '">' +
      '<input type="text" name="results[' + semNum + '][' + rowIndex + '][course_code]" class="form-control sub-code" value="' + (data.code || '') + '" placeholder="e.g. CSE 101" required style="text-transform:uppercase; font-weight:700; font-family:monospace; font-size:0.85rem;">' +
    '</td>' +
    '<td>' +
      '<input type="text" name="results[' + semNum + '][' + rowIndex + '][course_name]" class="form-control sub-name" value="' + (data.name || '') + '" placeholder="Subject Title" required style="font-size:0.85rem;">' +
    '</td>' +
    '<td>' +
      '<input type="number" step="0.5" min="0.5" max="6.0" name="results[' + semNum + '][' + rowIndex + '][credit]" class="form-control sub-credit" value="' + parseFloat(data.credit).toFixed(1) + '" required style="text-align:center; font-size:0.85rem;" oninput="recalculateRow(this)">' +
    '</td>' +
    '<td>' +
      '<input type="number" name="results[' + semNum + '][' + rowIndex + '][full_marks]" class="form-control sub-full" value="100" readonly style="text-align:center; background:#f1f5f9; font-size:0.85rem;">' +
    '</td>' +
    '<td>' +
      '<input type="number" step="0.5" min="0" max="100" name="results[' + semNum + '][' + rowIndex + '][marks]" class="form-control sub-marks" value="' + (data.marks !== '' ? parseFloat(data.marks).toFixed(1) : '') + '" placeholder="0-100" required style="text-align:center; font-weight:700; color:#0f2c59; font-size:0.9rem;" oninput="recalculateRow(this)">' +
    '</td>' +
    '<td>' +
      '<input type="text" name="results[' + semNum + '][' + rowIndex + '][letter_grade]" class="form-control sub-grade" value="' + gr.grade + '" readonly style="text-align:center; font-weight:800; color:#1e40af; background:#f8fafc; font-size:0.85rem;">' +
    '</td>' +
    '<td>' +
      '<input type="text" name="results[' + semNum + '][' + rowIndex + '][grade_point]" class="form-control sub-point" value="' + (gr.grade ? gr.point.toFixed(2) : '') + '" readonly style="text-align:center; font-weight:700; color:#047857; background:#f8fafc; font-size:0.85rem;">' +
    '</td>' +
    '<td style="text-align:center;">' +
      '<span class="sub-status-badge" style="font-size:0.75rem; font-weight:800; padding:2px 6px; border-radius:4px; ' + (gr.status === 'Pass' ? 'background:#dcfce7; color:#15803d;' : (gr.status === 'Fail' ? 'background:#fee2e2; color:#dc2626;' : 'background:#f1f5f9; color:#64748b;')) + '">' + gr.status + '</span>' +
    '</td>' +
    '<td style="text-align:center;">' +
      '<button type="button" onclick="removeSubjectRow(this)" style="background:#fee2e2; color:#ef4444; border:none; border-radius:4px; padding:4px 7px; cursor:pointer;" title="Delete Subject">' +
        '<i class="fas fa-trash-alt"></i>' +
      '</button>' +
    '</td>';

  tbody.appendChild(tr);

  // Auto-expand semester accordion if closed
  var bodyEl = document.getElementById('sem-body-' + semNum);
  if (bodyEl && bodyEl.style.display === 'none') {
    toggleSemesterAccordion(semNum);
  }

  recalculateSemester(semNum);
  recalculateCumulativeCGPA();
}

// Remove row
function removeSubjectRow(btn) {
  var tr = btn.closest('tr');
  if (!tr) return;
  var semNum = tr.getAttribute('data-sem');
  tr.remove();
  recalculateSemester(semNum);
  recalculateCumulativeCGPA();
}

// Pre-fill standard syllabus for semester
function prefillStandardSyllabus(semNum) {
  var template = standardSyllabus[semNum] || [
    { code: 'COURSE ' + semNum + '01', name: 'Standard Academic Course I', credit: 3.0, marks: 80.0 },
    { code: 'COURSE ' + semNum + '02', name: 'Standard Academic Course II', credit: 3.0, marks: 85.0 }
  ];

  var tbody = document.getElementById('sem-tbody-' + semNum);
  if (tbody) tbody.innerHTML = '';

  template.forEach(function(item) {
    addSubjectRow(semNum, item);
  });
}

// Toggle accordion
function toggleSemesterAccordion(semNum) {
  var bodyEl = document.getElementById('sem-body-' + semNum);
  var iconEl = document.getElementById('sem-icon-' + semNum);
  if (!bodyEl) return;

  if (bodyEl.style.display === 'none') {
    bodyEl.style.display = 'block';
    if (iconEl) iconEl.style.transform = 'rotate(180deg)';
  } else {
    bodyEl.style.display = 'none';
    if (iconEl) iconEl.style.transform = 'rotate(0deg)';
  }
}

function expandAllSemesters() {
  for (var s = 1; s <= activeSemestersCount; s++) {
    var b = document.getElementById('sem-body-' + s);
    var i = document.getElementById('sem-icon-' + s);
    if (b) b.style.display = 'block';
    if (i) i.style.transform = 'rotate(180deg)';
  }
}

function collapseAllSemesters() {
  for (var s = 1; s <= 8; s++) {
    var b = document.getElementById('sem-body-' + s);
    var i = document.getElementById('sem-icon-' + s);
    if (b) b.style.display = 'none';
    if (i) i.style.transform = 'rotate(0deg)';
  }
}

// Handle dynamic switching: 8 Semesters for UG, 4 Semesters for Master's
function handleProgramChange(progId) {
  progId = parseInt(progId) || 0;
  var selProg = null;
  for (var i = 0; i < programsData.length; i++) {
    if (parseInt(programsData[i].id) === progId) {
      selProg = programsData[i];
      break;
    }
  }

  var isMaster = selProg && (selProg.degree_level === 'Postgraduate' || selProg.title_en.indexOf('Master') !== -1 || selProg.title_en.indexOf('MA in') !== -1 || selProg.title_en.indexOf('MBA') !== -1);

  if (isMaster) {
    activeSemestersCount = 4;
    document.getElementById('curriculumBadge').innerHTML = "<strong>Master's Degree Curriculum</strong> &bull; 4 Semesters Active";
    for (var s = 1; s <= 8; s++) {
      var w = document.getElementById('sem-wrapper-' + s);
      if (w) w.style.display = (s <= 4) ? 'block' : 'none';
    }
  } else {
    activeSemestersCount = 8;
    document.getElementById('curriculumBadge').innerHTML = "<strong>Bachelor's Degree Curriculum</strong> &bull; 8 Semesters Active";
    for (var s = 1; s <= 8; s++) {
      var w = document.getElementById('sem-wrapper-' + s);
      if (w) w.style.display = 'block';
    }
  }

  recalculateCumulativeCGPA();
}

// Modal Preview of the Entire Academic Transcript
function openTranscriptPreview() {
  var name = document.getElementById('formFullName') ? document.getElementById('formFullName').value : '';
  var sid = document.getElementById('formStudentId') ? document.getElementById('formStudentId').value : 'Generated on Save';
  var batch = document.getElementById('formBatch') ? document.getElementById('formBatch').value : '';
  
  var progSelect = document.getElementById('programSelect');
  var progText = progSelect && progSelect.selectedIndex >= 0 ? progSelect.options[progSelect.selectedIndex].text : '';

  document.getElementById('modalStName').textContent = name || 'Student Name';
  document.getElementById('modalStId').textContent = sid || 'Pending ID';
  document.getElementById('modalStProgram').textContent = progText || 'Degree Program';
  document.getElementById('modalStBatch').textContent = batch || 'Session';

  var container = document.getElementById('modalTranscriptTables');
  container.innerHTML = '';

  var totalReg = 0.0;
  var totalEarned = 0.0;
  var totalPoints = 0.0;
  var semHasData = false;

  for (var s = 1; s <= activeSemestersCount; s++) {
    var tbody = document.getElementById('sem-tbody-' + s);
    if (!tbody) continue;

    var rows = tbody.querySelectorAll('.subject-row');
    if (rows.length === 0) continue;

    semHasData = true;
    var semCard = document.createElement('div');
    semCard.style.marginBottom = '1.25rem';
    semCard.style.border = '1px solid #cbd5e1';
    semCard.style.borderRadius = '6px';
    semCard.style.overflow = 'hidden';

    var semHeader = document.createElement('div');
    semHeader.style.background = '#0f2c59';
    semHeader.style.color = '#ffffff';
    semHeader.style.padding = '6px 12px';
    semHeader.style.fontWeight = '700';
    semHeader.style.fontSize = '0.85rem';
    semHeader.style.display = 'flex';
    semHeader.style.justifyContent = 'space-between';

    var semTable = document.createElement('table');
    semTable.className = 'rmu-table';
    semTable.style.boxShadow = 'none';
    semTable.style.margin = '0';
    semTable.style.fontSize = '0.84rem';

    var semTbody = document.createElement('tbody');
    var sReg = 0.0, sEarn = 0.0, sPts = 0.0;

    rows.forEach(function(r) {
      var code = r.querySelector('.sub-code').value;
      var cname = r.querySelector('.sub-name').value;
      var cr = parseFloat(r.querySelector('.sub-credit').value) || 0.0;
      var marks = r.querySelector('.sub-marks').value;
      var gr = r.querySelector('.sub-grade').value;
      var gp = parseFloat(r.querySelector('.sub-point').value) || 0.0;

      if (code || cname) {
        sReg += cr;
        if (gp >= 2.00) sEarn += cr;
        sPts += (cr * gp);

        var tr = document.createElement('tr');
        tr.innerHTML = 
          '<td style="font-family:monospace; font-weight:700; color:#1e40af; width:100px;">' + code + '</td>' +
          '<td><strong>' + cname + '</strong></td>' +
          '<td style="text-align:center; width:70px;">' + cr.toFixed(1) + ' Cr</td>' +
          '<td style="text-align:center; width:80px; font-weight:700;">' + (marks !== '' ? parseFloat(marks).toFixed(1) : '—') + '</td>' +
          '<td style="text-align:center; width:70px; font-weight:800; color:#1e40af;">' + gr + '</td>' +
          '<td style="text-align:center; width:70px; font-weight:700; color:#047857;">' + gp.toFixed(2) + '</td>';
        semTbody.appendChild(tr);
      }
    });

    totalReg += sReg;
    totalEarned += sEarn;
    totalPoints += sPts;
    var sSgpa = sReg > 0 ? (sPts / sReg).toFixed(2) : '0.00';

    semHeader.innerHTML = '<span>Semester ' + s + ' (' + sReg.toFixed(1) + ' Credits)</span><span>SGPA: ' + sSgpa + '</span>';
    semTable.appendChild(semTbody);
    semCard.appendChild(semHeader);
    semCard.appendChild(semTable);
    container.appendChild(semCard);
  }

  if (!semHasData) {
    container.innerHTML = '<div style="text-align:center; padding:2rem; color:#64748b;">No subjects recorded in any semester yet. Add subjects to preview.</div>';
  }

  var cCgpa = totalReg > 0 ? (totalPoints / totalReg).toFixed(2) : '0.00';
  document.getElementById('modalTotalReg').textContent = totalReg.toFixed(1);
  document.getElementById('modalTotalEarned').textContent = totalEarned.toFixed(1);
  document.getElementById('modalFinalCgpa').textContent = cCgpa;

  document.getElementById('transcriptPreviewModal').style.display = 'flex';
}

function closeTranscriptPreview() {
  document.getElementById('transcriptPreviewModal').style.display = 'none';
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', function() {
  var progSelect = document.getElementById('programSelect');
  if (progSelect) {
    handleProgramChange(progSelect.value);
  }

  // Recalculate any existing data on load
  for (var s = 1; s <= 8; s++) {
    recalculateSemester(s);
  }
  recalculateCumulativeCGPA();

  // Close modal on outside click
  window.addEventListener('click', function(e) {
    var modal = document.getElementById('transcriptPreviewModal');
    if (e.target === modal) closeTranscriptPreview();
  });

  // Synchronize target CGPA fields
  var formCgpa = document.getElementById('formCgpa');
  var targetCgpaInput = document.getElementById('targetCgpaInput');
  if (formCgpa && targetCgpaInput) {
    formCgpa.addEventListener('input', function() {
      targetCgpaInput.value = this.value;
    });
    targetCgpaInput.addEventListener('input', function() {
      formCgpa.value = this.value;
    });
  }
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
