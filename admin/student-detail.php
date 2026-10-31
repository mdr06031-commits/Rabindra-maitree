<?php
/**
 * Admin Panel: Student Full Profile, Courses, Grades & Fee Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Student Profile & Academic Control';
$db = getDb();

$studentId = (int)($_GET['id'] ?? 0);
if ($studentId <= 0) {
    flash_set('error', 'Invalid student identifier.');
    header("Location: " . APP_URL . "/admin/students.php");
    exit;
}

// Fetch student details
$stmt = $db->prepare("
    SELECT s.*, p.title_en AS program_title, d.name_en AS dept_name
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    flash_set('error', 'Student not found.');
    header("Location: " . APP_URL . "/admin/students.php");
    exit;
}

// Handle POST actions (Add Course, Delete Course, Add Result, Delete Result, Add Fee, Mark Fee Paid, Delete Fee)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Course Enrollment Actions
        if ($action === 'add_course') {
            $courseCode = trim($_POST['course_code'] ?? '');
            $courseName = trim($_POST['course_name'] ?? '');
            $credit = (float)($_POST['credit'] ?? 3.0);
            $instructor = trim($_POST['instructor_name'] ?? '');
            $schedule = trim($_POST['class_schedule'] ?? '');
            $roomNo = trim($_POST['room_no'] ?? '');
            $semester = trim($_POST['semester'] ?? $student['current_semester']);
            $attendance = (int)($_POST['attendance_percent'] ?? 85);
            $status = in_array($_POST['status'] ?? '', ['Enrolled', 'Completed', 'Dropped']) ? $_POST['status'] : 'Enrolled';

            if (!empty($courseCode) && !empty($courseName)) {
                $cStmt = $db->prepare("
                    INSERT INTO student_courses 
                    (student_id, course_code, course_name, credit, instructor_name, class_schedule, room_no, attendance_percent, semester, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $cStmt->execute([$studentId, $courseCode, $courseName, $credit, $instructor, $schedule, $roomNo, $attendance, $semester, $status]);
                log_audit('STUDENT_COURSE_ENROLLED', "Enrolled {$student['student_id']} in {$courseCode} - {$courseName}");
                flash_set('success', "Course '{$courseCode}' added successfully.");
            } else {
                flash_set('error', 'Course code and name are required.');
            }
        } elseif ($action === 'delete_course') {
            $cid = (int)($_POST['course_id'] ?? 0);
            if ($cid > 0) {
                $del = $db->prepare("DELETE FROM student_courses WHERE id = ? AND student_id = ?");
                $del->execute([$cid, $studentId]);
                log_audit('STUDENT_COURSE_REMOVED', "Removed course ID #{$cid} from student {$student['student_id']}");
                flash_set('success', "Enrolled course record removed.");
            }
        }

        // 2. Academic Result Actions
        elseif ($action === 'add_result') {
            $semester = trim($_POST['semester'] ?? '');
            $courseCode = trim($_POST['course_code'] ?? '');
            $courseName = trim($_POST['course_name'] ?? '');
            $credit = (float)($_POST['credit'] ?? 3.0);
            $grade = trim($_POST['letter_grade'] ?? 'A+');
            $point = (float)($_POST['grade_point'] ?? 4.00);
            $term = trim($_POST['exam_term'] ?? 'Semester Final Exam');

            if (!empty($semester) && !empty($courseCode)) {
                $rStmt = $db->prepare("
                    INSERT INTO student_results 
                    (student_id, semester, course_code, course_name, credit, letter_grade, grade_point, exam_term)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $rStmt->execute([$studentId, $semester, $courseCode, $courseName, $credit, $grade, $point, $term]);

                // Auto-recalculate CGPA & total credits completed
                $calc = $db->prepare("SELECT SUM(credit * grade_point) / SUM(credit) AS new_cgpa, SUM(credit) AS total_cr FROM student_results WHERE student_id = ?");
                $calc->execute([$studentId]);
                $cData = $calc->fetch();
                if ($cData && $cData['total_cr'] > 0) {
                    $newCgpa = round($cData['new_cgpa'], 2);
                    $newCr = (int)$cData['total_cr'];
                    $upd = $db->prepare("UPDATE students SET cgpa = ?, credits_completed = ? WHERE id = ?");
                    $upd->execute([$newCgpa, $newCr, $studentId]);
                }

                log_audit('STUDENT_RESULT_ADDED', "Added result for {$student['student_id']}: {$courseCode} ({$grade})");
                flash_set('success', "Result for '{$courseCode}' recorded and CGPA updated.");
            } else {
                flash_set('error', 'Semester and Course code are required.');
            }
        } elseif ($action === 'delete_result') {
            $rid = (int)($_POST['result_id'] ?? 0);
            if ($rid > 0) {
                $del = $db->prepare("DELETE FROM student_results WHERE id = ? AND student_id = ?");
                $del->execute([$rid, $studentId]);
                log_audit('STUDENT_RESULT_DELETED', "Deleted result ID #{$rid} from student {$student['student_id']}");
                flash_set('success', "Academic result record deleted.");
            }
        }

        // 3. Fee Actions
        elseif ($action === 'add_fee') {
            $title = trim($_POST['fee_title'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $dueDate = trim($_POST['due_date'] ?? date('Y-m-d'));
            $status = in_array($_POST['status'] ?? '', ['Paid', 'Due', 'Pending']) ? $_POST['status'] : 'Due';
            $trx = trim($_POST['transaction_id'] ?? '');

            if (!empty($title) && $amount > 0) {
                $fStmt = $db->prepare("
                    INSERT INTO student_fees 
                    (student_id, fee_title, amount, due_date, status, transaction_id, paid_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $paidAt = ($status === 'Paid') ? date('Y-m-d H:i:s') : null;
                $fStmt->execute([$studentId, $title, $amount, $dueDate, $status, $trx, $paidAt]);
                log_audit('STUDENT_FEE_ADDED', "Invoiced fee '{$title}' (৳{$amount}) for {$student['student_id']}");
                flash_set('success', "Fee invoice for ৳" . number_format($amount) . " created.");
            } else {
                flash_set('error', 'Fee title and valid amount are required.');
            }
        } elseif ($action === 'mark_paid') {
            $fid = (int)($_POST['fee_id'] ?? 0);
            $trx = trim($_POST['transaction_id'] ?? 'RMU-PAY-' . date('Y') . '-' . rand(10000, 99999));
            if ($fid > 0) {
                $pStmt = $db->prepare("UPDATE student_fees SET status = 'Paid', transaction_id = ?, paid_at = NOW() WHERE id = ? AND student_id = ?");
                $pStmt->execute([$trx, $fid, $studentId]);
                log_audit('STUDENT_FEE_PAID', "Marked fee ID #{$fid} as paid (Trx: {$trx}) for {$student['student_id']}");
                flash_set('success', "Fee marked as Paid successfully.");
            }
        } elseif ($action === 'delete_fee') {
            $fid = (int)($_POST['fee_id'] ?? 0);
            if ($fid > 0) {
                $del = $db->prepare("DELETE FROM student_fees WHERE id = ? AND student_id = ?");
                $del->execute([$fid, $studentId]);
                log_audit('STUDENT_FEE_DELETED', "Deleted fee ID #{$fid} from student {$student['student_id']}");
                flash_set('success', "Fee invoice deleted.");
            }
        }
    }
    header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId);
    exit;
}

// Fetch all courses for this student
$cStmt = $db->prepare("SELECT * FROM student_courses WHERE student_id = ? ORDER BY id DESC");
$cStmt->execute([$studentId]);
$courses = $cStmt->fetchAll();

// Fetch all academic results for this student
$rStmt = $db->prepare("SELECT * FROM student_results WHERE student_id = ? ORDER BY semester, course_code");
$rStmt->execute([$studentId]);
$results = $rStmt->fetchAll();

// Fetch all fee records for this student
$fStmt = $db->prepare("SELECT * FROM student_fees WHERE student_id = ? ORDER BY id DESC");
$fStmt->execute([$studentId]);
$fees = $fStmt->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<!-- Header Breadcrumb & Actions -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <a href="<?= APP_URL ?>/admin/students.php" style="color: var(--rmu-primary); font-size: 0.88rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem; margin-bottom: 0.5rem;">
      <i class="fas fa-arrow-left"></i> Back to Student Directory
    </a>
    <h2 style="font-size: 1.6rem; font-weight: 700; color: #0f172a; margin: 0;">
      Student: <?= e($student['full_name']) ?> <span style="font-family: monospace; color: var(--rmu-primary); font-size: 1.2rem;">(<?= e($student['student_id']) ?>)</span>
    </h2>
  </div>
  <div style="display: flex; gap: 0.75rem;">
    <a href="<?= APP_URL ?>/admin/students.php?edit=<?= $student['id'] ?>" class="btn-primary" style="padding: 0.6rem 1.2rem; font-size: 0.88rem;">
      <i class="fas fa-edit"></i> Edit Profile
    </a>
    <a href="<?= APP_URL ?>/student/login.php?student_id=<?= urlencode($student['student_id']) ?>" target="_blank" class="btn-primary" style="padding: 0.6rem 1.2rem; font-size: 0.88rem; background: #0284c7;">
      <i class="fas fa-external-link-alt"></i> View Portal Login
    </a>
  </div>
</div>

<!-- Student Profile Overview Card -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: grid; grid-template-columns: auto 1fr auto; gap: 2rem; align-items: center;">
  <div style="width: 85px; height: 85px; border-radius: 50%; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 700;">
    <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
  </div>

  <div>
    <h3 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0 0 0.4rem;"><?= e($student['full_name']) ?></h3>
    <div style="color: #475569; font-size: 0.9rem; margin-bottom: 0.5rem;">
      <strong>Program:</strong> <?= e($student['program_title'] ?? 'N/A') ?> &bull; 
      <strong>Dept:</strong> <?= e($student['dept_name'] ?? 'N/A') ?> &bull; 
      <strong>Batch:</strong> <?= e($student['batch']) ?>
    </div>
    <div style="color: #64748b; font-size: 0.85rem; display: flex; gap: 1.5rem; flex-wrap: wrap;">
      <span><i class="fas fa-envelope"></i> <?= e($student['email']) ?></span>
      <span><i class="fas fa-phone-alt"></i> <?= e($student['phone']) ?></span>
      <span><i class="fas fa-tint" style="color: #ef4444;"></i> <?= e($student['blood_group'] ?: 'N/A') ?></span>
      <span><i class="fas fa-map-marker-alt"></i> <?= e($student['home_district'] ?: 'N/A') ?></span>
    </div>
  </div>

  <div style="text-align: right; border-left: 1px solid #e2e8f0; padding-left: 2rem;">
    <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Cumulative CGPA</div>
    <div style="font-size: 2rem; font-weight: 800; color: #047857; line-height: 1.1;"><?= number_format($student['cgpa'], 2) ?></div>
    <div style="font-size: 0.82rem; color: #64748b; margin-top: 2px;">
      <?= (int)$student['credits_completed'] ?> / <?= (int)$student['total_credits_required'] ?> Credits Completed
    </div>
    <div style="margin-top: 0.5rem;">
      <span style="padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; background: #dcfce7; color: #15803d;">
        <?= e($student['status']) ?>
      </span>
    </div>
  </div>
</div>

<!-- Tabs Navigation -->
<div style="display: flex; gap: 0.5rem; border-bottom: 2px solid #e2e8f0; margin-bottom: 2rem;">
  <button onclick="openTab('coursesTab')" class="tab-btn active" id="btn-coursesTab" style="padding: 0.75rem 1.5rem; border: none; background: none; font-weight: 700; font-size: 0.95rem; color: var(--rmu-primary); border-bottom: 3px solid var(--rmu-primary); cursor: pointer;">
    <i class="fas fa-book-reader"></i> Course Enrollments (<?= count($courses) ?>)
  </button>
  <button onclick="openTab('gradesTab')" class="tab-btn" id="btn-gradesTab" style="padding: 0.75rem 1.5rem; border: none; background: none; font-weight: 600; font-size: 0.95rem; color: #64748b; cursor: pointer;">
    <i class="fas fa-chart-line"></i> Academic Results (<?= count($results) ?>)
  </button>
  <button onclick="openTab('feesTab')" class="tab-btn" id="btn-feesTab" style="padding: 0.75rem 1.5rem; border: none; background: none; font-weight: 600; font-size: 0.95rem; color: #64748b; cursor: pointer;">
    <i class="fas fa-file-invoice-dollar"></i> Tuition Fees &amp; Billing (<?= count($fees) ?>)
  </button>
</div>

<!-- Tab 1: Course Enrollments -->
<div id="coursesTab" class="tab-content" style="display: block;">
  <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start;">
    <!-- Course List -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;">
        <h4 style="margin: 0; font-size: 1.1rem; color: #0f172a;">Registered Courses</h4>
      </div>
      <div class="table-responsive">
        <table class="rmu-table" style="box-shadow: none; margin: 0;">
          <thead>
            <tr>
              <th>Course Code &bull; Name</th>
              <th>Credit</th>
              <th>Instructor &bull; Schedule</th>
              <th>Attendance</th>
              <th>Status</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($courses)): ?>
              <?php foreach ($courses as $co): ?>
                <tr>
                  <td>
                    <strong style="color: var(--rmu-primary); font-family: monospace;"><?= e($co['course_code']) ?></strong>
                    <div style="font-weight: 600; color: #0f172a; font-size: 0.9rem;"><?= e($co['course_name']) ?></div>
                    <small style="color: #64748b;"><?= e($co['semester']) ?></small>
                  </td>
                  <td><?= number_format($co['credit'], 1) ?> Cr</td>
                  <td style="font-size: 0.82rem; color: #475569;">
                    <div><?= e($co['instructor_name']) ?></div>
                    <small style="color: #64748b;"><?= e($co['class_schedule']) ?> (<?= e($co['room_no']) ?>)</small>
                  </td>
                  <td>
                    <strong style="color: <?= ($co['attendance_percent'] >= 75) ? '#15803d' : '#b91c1c' ?>;">
                      <?= (int)$co['attendance_percent'] ?>%
                    </strong>
                  </td>
                  <td>
                    <span style="padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; background: #e0f2fe; color: #0369a1;">
                      <?= e($co['status']) ?>
                    </span>
                  </td>
                  <td style="text-align: right;">
                    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display: inline;" onsubmit="return confirm('Remove course <?= e($co['course_code']) ?> from this student?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_course">
                      <input type="hidden" name="course_id" value="<?= $co['id'] ?>">
                      <button type="submit" style="border: none; background: none; color: #ef4444; cursor: pointer; font-size: 0.85rem;" title="Drop / Delete">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">No courses registered yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Enroll In New Course Form -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <h4 style="margin: 0 0 1.25rem; font-size: 1.1rem; color: #0f172a;"><i class="fas fa-plus-circle" style="color: var(--rmu-primary);"></i> Enroll New Course</h4>
      <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_course">

        <div class="form-group">
          <label class="form-label">Course Code *</label>
          <input type="text" name="course_code" placeholder="e.g. CSE 201" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Course Title *</label>
          <input type="text" name="course_name" placeholder="e.g. Data Structures & Algorithms" class="form-control" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
          <div class="form-group">
            <label class="form-label">Credit Hours</label>
            <input type="number" step="0.5" min="0.5" max="6.0" name="credit" value="3.0" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Attendance (%)</label>
            <input type="number" min="0" max="100" name="attendance_percent" value="85" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Instructor Name</label>
          <input type="text" name="instructor_name" placeholder="e.g. Dr. Md. Nazmul Islam" class="form-control">
        </div>

        <div class="form-group">
          <label class="form-label">Class Schedule</label>
          <input type="text" name="class_schedule" placeholder="e.g. Sun & Tue: 10:00 AM – 11:30 AM" class="form-control">
        </div>

        <div class="form-group">
          <label class="form-label">Room / Laboratory</label>
          <input type="text" name="room_no" placeholder="e.g. Lab 203 (Engineering Bldg)" class="form-control">
        </div>

        <div class="form-group">
          <label class="form-label">Semester Term</label>
          <input type="text" name="semester" value="<?= e($student['current_semester']) ?>" class="form-control">
        </div>

        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 1rem;">
          <i class="fas fa-check"></i> Register Course
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Tab 2: Academic Results -->
<div id="gradesTab" class="tab-content" style="display: none;">
  <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start;">
    <!-- Results Table -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;">
        <h4 style="margin: 0; font-size: 1.1rem; color: #0f172a;">Official Semester Grades &amp; Transcripts</h4>
      </div>
      <div class="table-responsive">
        <table class="rmu-table" style="box-shadow: none; margin: 0;">
          <thead>
            <tr>
              <th>Semester &bull; Term</th>
              <th>Course Code &bull; Title</th>
              <th>Credit</th>
              <th>Letter Grade</th>
              <th>Grade Point</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($results)): ?>
              <?php foreach ($results as $res): ?>
                <tr>
                  <td>
                    <strong style="color: #0f172a; font-size: 0.88rem; display: block;"><?= e($res['semester']) ?></strong>
                    <small style="color: #64748b;"><?= e($res['exam_term']) ?></small>
                  </td>
                  <td>
                    <strong style="color: var(--rmu-primary); font-family: monospace;"><?= e($res['course_code']) ?></strong>
                    <div style="font-size: 0.88rem; color: #334155;"><?= e($res['course_name']) ?></div>
                  </td>
                  <td><?= number_format($res['credit'], 1) ?></td>
                  <td>
                    <span style="font-weight: 700; color: #1e40af; font-size: 0.95rem;"><?= e($res['letter_grade']) ?></span>
                  </td>
                  <td>
                    <strong style="color: #047857;"><?= number_format($res['grade_point'], 2) ?></strong>
                  </td>
                  <td style="text-align: right;">
                    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display: inline;" onsubmit="return confirm('Delete result for <?= e($res['course_code']) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_result">
                      <input type="hidden" name="result_id" value="<?= $res['id'] ?>">
                      <button type="submit" style="border: none; background: none; color: #ef4444; cursor: pointer; font-size: 0.85rem;" title="Delete">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">No academic results published yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Record New Result Form -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <h4 style="margin: 0 0 1.25rem; font-size: 1.1rem; color: #0f172a;"><i class="fas fa-award" style="color: var(--rmu-primary);"></i> Publish Grade Result</h4>
      <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_result">

        <div class="form-group">
          <label class="form-label">Semester Session *</label>
          <input type="text" name="semester" placeholder="e.g. Fall 2026 (1st Semester)" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Course Code *</label>
          <input type="text" name="course_code" placeholder="e.g. CSE 101" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Course Title</label>
          <input type="text" name="course_name" placeholder="e.g. Structured Programming in C" class="form-control">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
          <div class="form-group">
            <label class="form-label">Credit</label>
            <input type="number" step="0.5" name="credit" value="3.0" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Grade</label>
            <select name="letter_grade" class="form-select" onchange="autoGradePoint(this.value)">
              <option value="A+">A+</option>
              <option value="A">A</option>
              <option value="A-">A-</option>
              <option value="B+">B+</option>
              <option value="B">B</option>
              <option value="B-">B-</option>
              <option value="C+">C+</option>
              <option value="C">C</option>
              <option value="D">D</option>
              <option value="F">F</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Point</label>
            <input type="number" step="0.01" min="0" max="4.00" name="grade_point" id="gradePointInput" value="4.00" class="form-control" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Exam Examination Term</label>
          <input type="text" name="exam_term" value="Semester Final Exam" class="form-control">
        </div>

        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 1rem;">
          <i class="fas fa-check-circle"></i> Save Result &amp; Recalculate CGPA
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Tab 3: Fees & Invoices -->
<div id="feesTab" class="tab-content" style="display: none;">
  <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start;">
    <!-- Fees Table -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;">
        <h4 style="margin: 0; font-size: 1.1rem; color: #0f172a;">Tuition Fees, Dues &amp; Payment History</h4>
      </div>
      <div class="table-responsive">
        <table class="rmu-table" style="box-shadow: none; margin: 0;">
          <thead>
            <tr>
              <th>Fee Title &bull; Due Date</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Transaction ID &bull; Paid Date</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($fees)): ?>
              <?php foreach ($fees as $fee): ?>
                <tr>
                  <td>
                    <strong style="color: #0f172a; display: block; font-size: 0.9rem;"><?= e($fee['fee_title']) ?></strong>
                    <small style="color: #64748b;">Due: <?= format_date($fee['due_date']) ?></small>
                  </td>
                  <td>
                    <strong style="color: #0f172a; font-size: 0.95rem;">৳<?= number_format($fee['amount'], 2) ?></strong>
                  </td>
                  <td>
                    <?php if ($fee['status'] === 'Paid'): ?>
                      <span style="background: #dcfce7; color: #15803d; font-weight: 700; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px;">
                        ✓ Paid
                      </span>
                    <?php else: ?>
                      <span style="background: #fee2e2; color: #b91c1c; font-weight: 700; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px;">
                        Due / Unpaid
                      </span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size: 0.82rem; color: #475569;">
                    <?php if (!empty($fee['transaction_id'])): ?>
                      <div style="font-family: monospace; color: #0369a1; font-weight: 600;"><?= e($fee['transaction_id']) ?></div>
                      <small style="color: #64748b;"><?= format_date($fee['paid_at'] ?? $fee['created_at']) ?></small>
                    <?php else: ?>
                      <span style="color: #94a3b8;">Pending payment</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align: right; white-space: nowrap;">
                    <?php if ($fee['status'] !== 'Paid'): ?>
                      <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display: inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="mark_paid">
                        <input type="hidden" name="fee_id" value="<?= $fee['id'] ?>">
                        <button type="submit" class="btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #16a34a; margin-right: 4px;">
                          Mark Paid
                        </button>
                      </form>
                    <?php endif; ?>
                    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display: inline;" onsubmit="return confirm('Delete fee invoice #<?= $fee['id'] ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_fee">
                      <input type="hidden" name="fee_id" value="<?= $fee['id'] ?>">
                      <button type="submit" style="border: none; background: none; color: #ef4444; cursor: pointer; font-size: 0.85rem;" title="Delete">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5" style="text-align: center; padding: 2rem; color: #64748b;">No fees or billing invoices recorded.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create New Fee Invoice Form -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <h4 style="margin: 0 0 1.25rem; font-size: 1.1rem; color: #0f172a;"><i class="fas fa-file-invoice" style="color: var(--rmu-primary);"></i> Issue New Fee Invoice</h4>
      <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_fee">

        <div class="form-group">
          <label class="form-label">Fee Title *</label>
          <input type="text" name="fee_title" placeholder="e.g. Fall 2026 Semester Tuition Fee" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Amount (BDT ৳) *</label>
          <input type="number" step="100" min="0" name="amount" placeholder="e.g. 32000" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Payment Due Date</label>
          <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Payment Status</label>
          <select name="status" class="form-select">
            <option value="Due">Due / Unpaid</option>
            <option value="Paid">Already Paid</option>
            <option value="Pending">Pending Verification</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Transaction ID (if paid)</label>
          <input type="text" name="transaction_id" placeholder="e.g. RMU-PAY-2026-XXXXX" class="form-control">
        </div>

        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 1rem;">
          <i class="fas fa-save"></i> Issue Fee Invoice
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function openTab(tabId) {
  document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.style.color = '#64748b';
    btn.style.borderBottom = 'none';
    btn.style.fontWeight = '600';
  });
  document.getElementById(tabId).style.display = 'block';
  var activeBtn = document.getElementById('btn-' + tabId);
  activeBtn.style.color = 'var(--rmu-primary)';
  activeBtn.style.borderBottom = '3px solid var(--rmu-primary)';
  activeBtn.style.fontWeight = '700';
}

function autoGradePoint(grade) {
  const map = {
    'A+': 4.00, 'A': 3.75, 'A-': 3.50,
    'B+': 3.25, 'B': 3.00, 'B-': 2.75,
    'C+': 2.50, 'C': 2.25, 'D': 2.00, 'F': 0.00
  };
  if (map[grade] !== undefined) {
    document.getElementById('gradePointInput').value = map[grade].toFixed(2);
  }
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

