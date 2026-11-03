<?php
/**
 * Admin Panel: Students Management & Enrollment Directory
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Student Management';
$db = getDb();

// Handle Actions (Add, Edit, Delete, Status Toggle, Password Reset)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $studentId = trim($_POST['student_id'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = trim($_POST['password'] ?? 'student123');
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

            // Auto-generate official RMU Student ID if blank
            if (empty($studentId)) {
                $studentId = generate_next_student_id($db);
            }

            if (empty($fullName) || empty($email) || empty($phone)) {
                flash_set('error', 'Full Name, Email, and Phone number are required.');
            } else {
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
                    $newId = (int)$db->lastInsertId();

                    // Seed standard courses for 1st semester
                    $courses = [
                        ['CSE 101', 'Introduction to Computer Science & Problem Solving', 3.0, 'Prof. Dr. M. A. Rahman', 'Sun & Tue 10:00 AM', 'Room 301'],
                        ['CSE 102', 'Structured Programming Language (C)', 3.0, 'Engr. Tareq Ahmed', 'Mon & Wed 11:30 AM', 'Lab 2'],
                        ['MAT 101', 'Differential and Integral Calculus', 3.0, 'Dr. Shamsul Huda', 'Sun & Wed 02:00 PM', 'Room 205'],
                        ['ENG 101', 'English Reading and Composition Skills', 3.0, 'Ms. Sabrina Akter', 'Mon & Thu 03:30 PM', 'Room 304']
                    ];
                    $insCourse = $db->prepare("
                        INSERT INTO student_courses 
                        (student_id, course_code, course_name, credit, instructor_name, class_schedule, room_no, attendance_percent, semester, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 90, ?, 'Enrolled')
                    ");
                    foreach ($courses as $c) {
                        $insCourse->execute([$newId, $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $currentSemester]);
                    }

                    // Send Welcome In-App Notification
                    create_student_notification(
                        $newId,
                        "Welcome to Rabindra Maitree University!",
                        "Your official Student Portal account is active. Student ID: {$studentId}. You can now view your enrolled courses, tuition fee invoices, and academic results.",
                        'general'
                    );

                    log_audit('STUDENT_ADDED', "Enrolled new student: {$fullName} ({$studentId})");
                    flash_set('success', "Student '{$fullName}' ({$studentId}) enrolled successfully! Initial password: {$password}. Student Portal account is active.");
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
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
            $batch = trim($_POST['batch'] ?? '');
            $currentYear = trim($_POST['current_year'] ?? '');
            $currentSemester = trim($_POST['current_semester'] ?? '');
            $cgpa = (float)($_POST['cgpa'] ?? 0.00);
            $creditsCompleted = (int)($_POST['credits_completed'] ?? 0);
            $bloodGroup = trim($_POST['blood_group'] ?? '');
            $homeDistrict = trim($_POST['home_district'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['Active', 'Graduated', 'Suspended']) ? $_POST['status'] : 'Active';

            if ($id > 0 && !empty($fullName) && !empty($email)) {
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
                    $presentAddress, $permanentAddress, $status, $id
                ]);

                // Reset password if provided
                if (!empty($_POST['new_password'])) {
                    $newPass = trim($_POST['new_password']);
                    $passStmt = $db->prepare("UPDATE students SET password_hash = ? WHERE id = ?");
                    $passStmt->execute([password_hash($newPass, PASSWORD_DEFAULT), $id]);
                    log_audit('STUDENT_PASSWORD_RESET', "Reset password for student ID #{$id}");
                }

                log_audit('STUDENT_UPDATED', "Updated student ID #{$id} ({$fullName})");
                flash_set('success', "Student record updated successfully.");
            } else {
                flash_set('error', 'Full Name and Email are required.');
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
        }
    }
    header("Location: " . APP_URL . "/admin/students.php");
    exit;
}

// Search & Filter
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
$totalFeesCollected = (float)$db->query("SELECT SUM(amount) FROM student_fees WHERE status = 'Paid'")->fetchColumn();

// Count Total Filtered
$countStmt = $db->prepare("SELECT COUNT(*) FROM students s $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Fetch Students
$sql = "
    SELECT s.*, p.title_en AS program_title, d.name_en AS dept_name
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

// Get programs & departments for dropdowns
$programs = $db->query("SELECT id, title_en, degree_level FROM programs ORDER BY sort_order, title_en")->fetchAll();
$departments = $db->query("SELECT id, name_en FROM departments ORDER BY name_en")->fetchAll();

// Check if editing
$editStudent = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $st = $db->prepare("SELECT * FROM students WHERE id = ?");
    $st->execute([$editId]);
    $editStudent = $st->fetch();
}

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-size: 1.6rem; font-weight: 700; color: #0f172a; margin: 0 0 0.2rem;">
      <i class="fas fa-users-cog" style="color: var(--rmu-primary);"></i> Student Management Directory
    </h2>
    <span style="color: #64748b; font-size: 0.9rem;">Enroll, manage, and inspect university students, academic records, and portal accounts.</span>
  </div>
  <div>
    <button onclick="document.getElementById('studentFormModal').scrollIntoView({behavior: 'smooth'})" class="btn-primary" style="padding: 0.65rem 1.4rem;">
      <i class="fas fa-user-plus"></i> Enroll New Student
    </button>
  </div>
</div>

<!-- Stat Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
  <div class="stat-card">
    <div class="stat-icon" style="background: #e0e7ff; color: #4338ca;">
      <i class="fas fa-user-graduate"></i>
    </div>
    <div>
      <div style="font-size: 1.6rem; font-weight: 700; color: #0f172a;"><?= $totalStudents ?></div>
      <div style="font-size: 0.85rem; color: #64748b;">Total Students</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: #dcfce7; color: #15803d;">
      <i class="fas fa-user-check"></i>
    </div>
    <div>
      <div style="font-size: 1.6rem; font-weight: 700; color: #0f172a;"><?= $activeStudents ?></div>
      <div style="font-size: 0.85rem; color: #64748b;">Active Enrolled</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: #fef3c7; color: #b45309;">
      <i class="fas fa-graduation-cap"></i>
    </div>
    <div>
      <div style="font-size: 1.6rem; font-weight: 700; color: #0f172a;"><?= $graduatedStudents ?></div>
      <div style="font-size: 0.85rem; color: #64748b;">Alumni / Graduated</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: #e0f2fe; color: #0369a1;">
      <i class="fas fa-money-check-alt"></i>
    </div>
    <div>
      <div style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">৳<?= number_format($totalFeesCollected) ?></div>
      <div style="font-size: 0.85rem; color: #64748b;">Fees Collected</div>
    </div>
  </div>
</div>

<!-- Search & Filter Console -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem 1.5rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
  <form action="<?= APP_URL ?>/admin/students.php" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
    <div style="flex: 2; min-width: 250px;">
      <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by Student ID, Name, Phone, Email, Batch..." class="form-control" style="padding: 0.65rem 1rem;">
    </div>

    <div style="flex: 1; min-width: 180px;">
      <select name="program_id" class="form-select" style="padding: 0.65rem 1rem;">
        <option value="0">All Programs</option>
        <?php foreach ($programs as $pr): ?>
          <option value="<?= $pr['id'] ?>" <?= ($programFilter === (int)$pr['id']) ? 'selected' : '' ?>>
            <?= e($pr['title_en']) ?>
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

<!-- Two-Column Layout: Students Table + Add/Edit Form -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start;">
  
  <!-- Left: Students Table -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">Enrolled Students List</h3>
      <span style="font-size: 0.85rem; color: #64748b;">Showing <?= count($students) ?> of <?= $totalRows ?></span>
    </div>

    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow: none; margin: 0;">
        <thead>
          <tr>
            <th>Student ID &bull; Name</th>
            <th>Program &bull; Batch</th>
            <th>Year &bull; Semester</th>
            <th>CGPA &bull; Cr</th>
            <th>Status</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($students)): ?>
            <?php foreach ($students as $stu): ?>
              <tr>
                <td>
                  <strong style="color: var(--rmu-primary); font-size: 0.95rem; font-family: monospace; display: block;">
                    <?= e($stu['student_id']) ?>
                  </strong>
                  <span style="font-weight: 600; color: #0f172a; font-size: 0.92rem; display: block;"><?= e($stu['full_name']) ?></span>
                  <div style="font-size: 0.78rem; color: #64748b;">
                    <i class="fas fa-envelope"></i> <?= e($stu['email']) ?>
                  </div>
                  <div style="font-size: 0.78rem; color: #64748b;">
                    <i class="fas fa-phone-alt"></i> <?= e($stu['phone']) ?>
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
                <td style="font-size: 0.85rem;">
                  <strong style="color: #047857; font-size: 0.95rem; display: block;"><?= number_format($stu['cgpa'], 2) ?></strong>
                  <small style="color: #64748b;"><?= (int)$stu['credits_completed'] ?> Credits</small>
                </td>
                <td>
                  <?php
                    $stColor = ($stu['status'] === 'Active') ? 'background:#dcfce7; color:#15803d;' : (($stu['status'] === 'Graduated') ? 'background:#e0f2fe; color:#0369a1;' : 'background:#fee2e2; color:#b91c1c;');
                  ?>
                  <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; <?= $stColor ?>">
                    <?= e($stu['status']) ?>
                  </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= $stu['id'] ?>" class="btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; background: #0284c7; margin-bottom: 4px; display: inline-flex; align-items:center; gap:4px;" title="Manage Courses, Fees & Profile">
                    <i class="fas fa-eye"></i> Details
                  </a>
                  <a href="<?= APP_URL ?>/admin/results-manager.php?student_id=<?= $stu['id'] ?>" class="btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; background: #059669; margin-bottom: 4px; display: inline-flex; align-items:center; gap:4px;" title="Manage 8 Semesters & Results">
                    <i class="fas fa-chart-line"></i> Results
                  </a>
                  <a href="<?= APP_URL ?>/admin/students.php?edit=<?= $stu['id'] ?>" style="color: var(--rmu-primary); font-weight: 600; font-size: 0.82rem; margin: 0 4px; display: inline-block;">
                    <i class="fas fa-edit"></i> Edit
                  </a>
                  <form action="<?= APP_URL ?>/admin/students.php" method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete student <?= e($stu['full_name']) ?> (<?= e($stu['student_id']) ?>)? All associated courses and grades will also be removed!');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $stu['id'] ?>">
                    <button type="submit" style="background: none; border: none; color: #ef4444; font-size: 0.82rem; cursor: pointer; padding: 0 4px; font-weight: 600;">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">
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

  <!-- Right: Add / Edit Form Modal -->
  <div id="studentFormModal" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem;">
      <i class="fas <?= $editStudent ? 'fa-user-edit' : 'fa-user-plus' ?>" style="color: var(--rmu-primary);"></i>
      <?= $editStudent ? 'Edit Student Record' : 'Enroll New Student' ?>
    </h3>

    <form action="<?= APP_URL ?>/admin/students.php" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editStudent ? 'edit' : 'add' ?>">
      <?php if ($editStudent): ?>
        <input type="hidden" name="id" value="<?= (int)$editStudent['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Student ID Number</label>
        <input type="text" name="student_id" value="<?= e($editStudent['student_id'] ?? '') ?>" placeholder="e.g. RMU2601005 (or leave blank to auto-generate)" class="form-control" <?= $editStudent ? 'readonly style="background:#f8fafc;"' : '' ?>>
      </div>

      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" name="full_name" value="<?= e($editStudent['full_name'] ?? '') ?>" placeholder="e.g. Mahfuzur Rahman" class="form-control" required>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Official Email *</label>
          <input type="email" name="email" value="<?= e($editStudent['email'] ?? '') ?>" placeholder="student@rmu.ac.bd" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Mobile Number *</label>
          <input type="text" name="phone" value="<?= e($editStudent['phone'] ?? '') ?>" placeholder="017xxxxxxxx" class="form-control" required>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Father's Name</label>
          <input type="text" name="father_name" value="<?= e($editStudent['father_name'] ?? '') ?>" placeholder="Father's full name" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Mother's Name</label>
          <input type="text" name="mother_name" value="<?= e($editStudent['mother_name'] ?? '') ?>" placeholder="Mother's full name" class="form-control">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Date of Birth</label>
          <input type="date" name="dob" value="<?= e($editStudent['dob'] ?? '') ?>" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-select">
            <option value="Male" <?= (($editStudent['gender'] ?? 'Male') === 'Male') ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= (($editStudent['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
            <option value="Other" <?= (($editStudent['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Present Address</label>
          <input type="text" name="present_address" value="<?= e($editStudent['present_address'] ?? '') ?>" placeholder="Present address" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Permanent Address</label>
          <input type="text" name="permanent_address" value="<?= e($editStudent['permanent_address'] ?? '') ?>" placeholder="Permanent address" class="form-control">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Academic Degree Program</label>
        <select name="program_id" class="form-select">
          <option value="">Select Degree Program</option>
          <?php foreach ($programs as $pr): ?>
            <option value="<?= $pr['id'] ?>" <?= (($editStudent['program_id'] ?? 0) == $pr['id']) ? 'selected' : '' ?>>
              <?= e($pr['title_en']) ?> (<?= e($pr['degree_level']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Academic Department</label>
        <select name="department_id" class="form-select">
          <option value="">Select Department</option>
          <?php foreach ($departments as $dp): ?>
            <option value="<?= $dp['id'] ?>" <?= (($editStudent['department_id'] ?? 0) == $dp['id']) ? 'selected' : '' ?>>
              <?= e($dp['name_en']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Batch</label>
          <input type="text" name="batch" value="<?= e($editStudent['batch'] ?? '13th Batch (2025–2029)') ?>" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Current Year</label>
          <select name="current_year" class="form-select">
            <option value="1st Year" <?= (($editStudent['current_year'] ?? '') === '1st Year') ? 'selected' : '' ?>>1st Year</option>
            <option value="2nd Year" <?= (($editStudent['current_year'] ?? '') === '2nd Year') ? 'selected' : '' ?>>2nd Year</option>
            <option value="3rd Year" <?= (($editStudent['current_year'] ?? '') === '3rd Year') ? 'selected' : '' ?>>3rd Year</option>
            <option value="4th Year" <?= (($editStudent['current_year'] ?? '') === '4th Year') ? 'selected' : '' ?>>4th Year</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Current Semester</label>
          <input type="text" name="current_semester" value="<?= e($editStudent['current_semester'] ?? 'Fall 2026 (1st Semester)') ?>" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Enrollment Status</label>
          <select name="status" class="form-select">
            <option value="Active" <?= (($editStudent['status'] ?? '') === 'Active') ? 'selected' : '' ?>>Active</option>
            <option value="Graduated" <?= (($editStudent['status'] ?? '') === 'Graduated') ? 'selected' : '' ?>>Graduated</option>
            <option value="Suspended" <?= (($editStudent['status'] ?? '') === 'Suspended') ? 'selected' : '' ?>>Suspended</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Cumulative CGPA</label>
          <input type="number" step="0.01" min="0" max="4" name="cgpa" value="<?= e($editStudent['cgpa'] ?? '0.00') ?>" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Credits Completed</label>
          <input type="number" min="0" name="credits_completed" value="<?= (int)($editStudent['credits_completed'] ?? 0) ?>" class="form-control">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Blood Group</label>
          <input type="text" name="blood_group" value="<?= e($editStudent['blood_group'] ?? '') ?>" placeholder="e.g. B+, O+" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Home District</label>
          <input type="text" name="home_district" value="<?= e($editStudent['home_district'] ?? '') ?>" placeholder="e.g. Kushtia" class="form-control">
        </div>
      </div>

      <?php if (!$editStudent): ?>
        <div class="form-group">
          <label class="form-label">Initial Portal Password</label>
          <input type="text" name="password" value="student123" class="form-control" placeholder="Default: student123">
          <small style="color: #64748b;">The student can use this password to log into the Student Portal.</small>
        </div>
      <?php else: ?>
        <div class="form-group" style="background: #f8fafc; padding: 0.75rem; border-radius: 6px; border: 1px dashed #cbd5e1;">
          <label class="form-label" style="color: #0369a1;">Reset Portal Password (optional)</label>
          <input type="password" name="new_password" placeholder="Leave empty to keep existing password" class="form-control">
        </div>
      <?php endif; ?>

      <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
        <button type="submit" class="btn-primary" style="flex: 1; justify-content: center;">
          <i class="fas fa-save"></i> <?= $editStudent ? 'Update Student' : 'Enroll Student' ?>
        </button>
        <?php if ($editStudent): ?>
          <a href="<?= APP_URL ?>/admin/students.php" class="btn-primary" style="background: #94a3b8; justify-content: center;">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

