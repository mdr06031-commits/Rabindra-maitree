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

        // 4. Semester Progress & Promotion Control
        elseif ($action === 'update_semester_progress') {
            $year = trim($_POST['current_year'] ?? '1st Year');
            $semester = trim($_POST['current_semester'] ?? '1st Semester');
            $cgpa = (float)($_POST['cgpa'] ?? 0.00);
            $credits = (int)($_POST['credits_completed'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['Active', 'Graduated', 'Suspended']) ? $_POST['status'] : 'Active';

            $upd = $db->prepare("UPDATE students SET current_year = ?, current_semester = ?, cgpa = ?, credits_completed = ?, status = ? WHERE id = ?");
            $upd->execute([$year, $semester, $cgpa, $credits, $status, $studentId]);
            log_audit('SEMESTER_PROGRESS_UPDATED', "Updated semester progress for {$student['student_id']}: Year={$year}, Sem={$semester}, CGPA={$cgpa}, Status={$status}");
            flash_set('success', "Semester progress updated! Student is now in {$year} ({$semester}) with CGPA {$cgpa}.");
        }

        // 5. One-Click Complete All 8 Semesters & Graduate
        elseif ($action === 'complete_all_semesters') {
            $finalCgpa = (float)($_POST['final_cgpa'] ?? 3.85);
            $finalCgpa = max(2.50, min(4.00, $finalCgpa));

            // 5.1 Update student record
            $upd = $db->prepare("
                UPDATE students 
                SET current_year = '4th Year', 
                    current_semester = '8th Semester (Completed)', 
                    status = 'Graduated', 
                    credits_completed = 140, 
                    cgpa = ?
                WHERE id = ?
            ");
            $upd->execute([$finalCgpa, $studentId]);

            // 5.2 Mark all current courses as Completed
            $db->prepare("UPDATE student_courses SET status = 'Completed' WHERE student_id = ?")->execute([$studentId]);

            // 5.3 Mark all fee dues as Paid
            $db->prepare("UPDATE student_fees SET status = 'Paid', transaction_id = COALESCE(NULLIF(transaction_id, ''), CONCAT('RMU-COMP-', FLOOR(RAND()*89999+10000))), paid_at = COALESCE(paid_at, NOW()) WHERE student_id = ?")->execute([$studentId]);

            // 5.4 Check existing results count. If student has fewer than 10 results, seed complete 8 semesters of transcript
            $resCount = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$studentId}")->fetchColumn();
            if ($resCount < 10) {
                $transcriptCourses = [
                    ['1st Semester (Spring)', 'CSE 101', 'Structured Programming Language', 3.0, 'A+', 4.00],
                    ['1st Semester (Spring)', 'CSE 102', 'Structured Programming Lab', 1.5, 'A+', 4.00],
                    ['1st Semester (Spring)', 'ENG 101', 'English Reading & Writing Skills', 3.0, 'A', 3.75],
                    ['1st Semester (Spring)', 'MAT 101', 'Differential and Integral Calculus', 3.0, 'A+', 4.00],
                    ['2nd Semester (Fall)', 'CSE 103', 'Discrete Mathematics', 3.0, 'A+', 4.00],
                    ['2nd Semester (Fall)', 'CSE 104', 'Object Oriented Programming (Java)', 3.0, 'A', 3.75],
                    ['2nd Semester (Fall)', 'PHY 101', 'Physics (Mechanics, Waves & Optics)', 3.0, 'A', 3.75],
                    ['2nd Semester (Fall)', 'BAN 105', 'Bangla Language & Tagore Literary Studies', 2.0, 'A+', 4.00],
                    ['3rd Semester (Spring)', 'CSE 201', 'Data Structures & Algorithms', 3.0, 'A+', 4.00],
                    ['3rd Semester (Spring)', 'CSE 202', 'Data Structures & Algorithms Lab', 1.5, 'A+', 4.00],
                    ['3rd Semester (Spring)', 'CSE 203', 'Digital Logic Design & Microprocessors', 3.0, 'A', 3.75],
                    ['3rd Semester (Spring)', 'MAT 102', 'Coordinate Geometry & Linear Algebra', 3.0, 'A', 3.75],
                    ['4th Semester (Fall)', 'CSE 205', 'Database Management Systems', 3.0, 'A+', 4.00],
                    ['4th Semester (Fall)', 'CSE 206', 'Database Management Systems Lab', 1.5, 'A+', 4.00],
                    ['4th Semester (Fall)', 'CSE 207', 'Computer Architecture & Organization', 3.0, 'A', 3.75],
                    ['4th Semester (Fall)', 'STA 201', 'Statistics & Probability for Engineers', 3.0, 'A', 3.75],
                    ['5th Semester (Spring)', 'CSE 301', 'Software Engineering & System Analysis', 3.0, 'A+', 4.00],
                    ['5th Semester (Spring)', 'CSE 302', 'Operating Systems & System Programming', 3.0, 'A', 3.75],
                    ['5th Semester (Spring)', 'CSE 303', 'Operating Systems Lab (Linux/Unix)', 1.5, 'A+', 4.00],
                    ['5th Semester (Spring)', 'CSE 304', 'Theory of Computation & Automata', 3.0, 'A', 3.75],
                    ['6th Semester (Fall)', 'CSE 305', 'Computer Networks & Internet Protocols', 3.0, 'A+', 4.00],
                    ['6th Semester (Fall)', 'CSE 306', 'Computer Networks Laboratory', 1.5, 'A+', 4.00],
                    ['6th Semester (Fall)', 'CSE 307', 'Web Engineering & Full-Stack Development', 3.0, 'A+', 4.00],
                    ['6th Semester (Fall)', 'CSE 308', 'Microcontroller & Embedded Systems Lab', 1.5, 'A', 3.75],
                    ['7th Semester (Spring)', 'CSE 401', 'Artificial Intelligence & Machine Learning', 3.0, 'A+', 4.00],
                    ['7th Semester (Spring)', 'CSE 402', 'Compiler Design & Construction', 3.0, 'A', 3.75],
                    ['7th Semester (Spring)', 'CSE 403', 'Cyber Security & Cryptography', 3.0, 'A', 3.75],
                    ['7th Semester (Spring)', 'CSE 499A', 'Capstone Project & Thesis (Part I)', 3.0, 'A+', 4.00],
                    ['8th Semester (Fall)', 'CSE 405', 'Cloud Computing & Distributed Systems', 3.0, 'A+', 4.00],
                    ['8th Semester (Fall)', 'CSE 406', 'Digital Image Processing & Vision', 3.0, 'A', 3.75],
                    ['8th Semester (Fall)', 'CSE 499B', 'Capstone Project & Defense (Part II)', 3.0, 'A+', 4.00],
                    ['8th Semester (Fall)', 'MGT 401', 'Industrial Management & Professional Ethics', 2.0, 'A+', 4.00]
                ];

                $rIns = $db->prepare("
                    INSERT INTO student_results 
                    (student_id, semester, course_code, course_name, credit, letter_grade, grade_point, exam_term)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Semester Final Examination')
                ");

                foreach ($transcriptCourses as $tc) {
                    $rIns->execute([$studentId, $tc[0], $tc[1], $tc[2], $tc[3], $tc[4], $tc[5]]);
                }
            }

            log_audit('STUDENT_GRADUATED_ALL_SEMESTERS', "Admin marked all 8 semesters completed and graduated student {$student['student_id']} (CGPA: {$finalCgpa})");
            flash_set('success', "🎉 Student {$student['student_id']} ({$student['full_name']}) has successfully completed ALL 8 Semesters (140 Credits) and is officially GRADUATED with CGPA {$finalCgpa}!");
        }

        // 6. Login Directly to Student Portal
        elseif ($action === 'login_as_student') {
            $_SESSION['student_pk_id'] = $student['id'];
            $_SESSION['student_id'] = $student['student_id'];
            $_SESSION['student_name'] = $student['full_name'];
            $_SESSION['student_email'] = $student['email'];

            flash_set('success', "Logged in as student {$student['full_name']} ({$student['student_id']})!");
            header("Location: " . APP_URL . "/student/dashboard.php");
            exit;
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
$rStmt = $db->prepare("SELECT * FROM student_results WHERE student_id = ? ORDER BY id ASC");
$rStmt->execute([$studentId]);
$results = $rStmt->fetchAll();

// Group results by semester and compute running CGPA
$groupedAdminResults = [];
foreach ($results as $res) {
    $groupedAdminResults[$res['semester']][] = $res;
}

$runningAdminCr = 0;
$runningAdminPts = 0;
$semesterAdminStats = [];
foreach ($groupedAdminResults as $sName => $cList) {
    $sCr = array_sum(array_column($cList, 'credit'));
    $sPts = 0;
    foreach ($cList as $c) {
        $sPts += ((float)$c['credit'] * (float)$c['grade_point']);
    }
    $sSgpa = ($sCr > 0) ? round($sPts / $sCr, 2) : 0.00;

    $runningAdminCr += $sCr;
    $runningAdminPts += $sPts;
    $sCgpa = ($runningAdminCr > 0) ? round($runningAdminPts / $runningAdminCr, 2) : 0.00;

    $semesterAdminStats[$sName] = [
        'sem_credits' => $sCr,
        'sem_points' => $sPts,
        'sgpa' => $sSgpa,
        'cum_credits' => $runningAdminCr,
        'cum_points' => $runningAdminPts,
        'cgpa' => $sCgpa,
    ];
}

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
  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $student['id'] ?>" method="POST" target="_blank" style="display:inline;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="login_as_student">
      <button type="submit" class="btn-primary" style="padding: 0.6rem 1.2rem; font-size: 0.88rem; background: #059669; border: none; cursor: pointer;" title="Directly log into student dashboard in new tab">
        <i class="fas fa-sign-in-alt"></i> Login As Student (সরাসরি পোর্টালে ঢুকুন)
      </button>
    </form>
    <a href="<?= APP_URL ?>/admin/students.php?edit=<?= $student['id'] ?>" class="btn-primary" style="padding: 0.6rem 1.2rem; font-size: 0.88rem;">
      <i class="fas fa-edit"></i> Edit Profile
    </a>
    <a href="<?= APP_URL ?>/student/login.php?student_id=<?= urlencode($student['student_id']) ?>" target="_blank" class="btn-primary" style="padding: 0.6rem 1.2rem; font-size: 0.88rem; background: #0284c7;">
      <i class="fas fa-external-link-alt"></i> View Portal Login
    </a>
  </div>
</div>

<!-- Semester Progress & Graduation Master Control Panel -->
<div style="background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%); border: 2px solid #bfdbfe; border-radius: 12px; padding: 1.75rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h3 style="margin: 0 0 0.3rem; font-size: 1.25rem; font-weight: 700; color: #1e3a8a;">
        <i class="fas fa-tasks" style="color: var(--rmu-primary);"></i> সেমিস্টার অগ্রগতি ও গ্র্যাজুয়েশন নিয়ন্ত্রণ (Semester Progress &amp; Graduation)
      </h3>
      <span style="font-size: 0.88rem; color: #475569;">
        এখান থেকে আপনি এই ছাত্রের সেমিস্টার প্রমোট করতে পারেন অথবা <strong>এক ক্লিকে সম্পূর্ণ ৮টি সেমিস্টার কমপ্লিট করে গ্র্যাজুয়েট</strong> করাতে পারেন।
      </span>
    </div>

    <!-- 1-Click Complete All Semesters Button -->
    <div>
      <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $student['id'] ?>" method="POST" onsubmit="return confirm('এই শিক্ষার্থীর সকল ৮টি সেমিস্টার কি সম্পন্ন (Complete) ঘোষণা করতে চান? এর ফলে ছাত্রের প্রোফাইলে সমস্ত ৮টি সেমিস্টার ও গ্রেড অটোমেটিক যুক্ত হবে এবং ছাত্রটি গ্র্যাজুয়েট হিসেবে উত্তীর্ণ হবে!');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="complete_all_semesters">
        <input type="hidden" name="final_cgpa" value="3.85">
        <button type="submit" class="btn-primary" style="padding: 0.75rem 1.5rem; font-size: 0.95rem; background: #15803d; font-weight: 700; box-shadow: 0 4px 8px rgba(21, 128, 61, 0.3); border: none; cursor: pointer;">
          <i class="fas fa-graduation-cap"></i> সব সেমিস্টার সম্পন্ন করুন (Graduate All 8 Semesters)
        </button>
      </form>
    </div>
  </div>

  <!-- Manual Custom Semester Update Form -->
  <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $student['id'] ?>" method="POST" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1.25rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)) auto; gap: 1rem; align-items: flex-end;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_semester_progress">

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">বর্তমান বর্ষ (Academic Year)</label>
      <select name="current_year" class="form-select" style="font-size: 0.88rem;">
        <option value="1st Year" <?= ($student['current_year'] === '1st Year') ? 'selected' : '' ?>>1st Year (প্রথম বর্ষ)</option>
        <option value="2nd Year" <?= ($student['current_year'] === '2nd Year') ? 'selected' : '' ?>>2nd Year (দ্বিতীয় বর্ষ)</option>
        <option value="3rd Year" <?= ($student['current_year'] === '3rd Year') ? 'selected' : '' ?>>3rd Year (তৃতীয় বর্ষ)</option>
        <option value="4th Year" <?= ($student['current_year'] === '4th Year') ? 'selected' : '' ?>>4th Year (চতুর্থ বর্ষ)</option>
      </select>
    </div>

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">বর্তমান সেমিস্টার (Semester)</label>
      <select name="current_semester" class="form-select" style="font-size: 0.88rem;">
        <option value="Fall 2026 (1st Semester)" <?= (strpos($student['current_semester'], '1st') !== false) ? 'selected' : '' ?>>1st Semester</option>
        <option value="Spring 2027 (2nd Semester)" <?= (strpos($student['current_semester'], '2nd') !== false) ? 'selected' : '' ?>>2nd Semester</option>
        <option value="Fall 2027 (3rd Semester)" <?= (strpos($student['current_semester'], '3rd') !== false) ? 'selected' : '' ?>>3rd Semester</option>
        <option value="Spring 2028 (4th Semester)" <?= (strpos($student['current_semester'], '4th') !== false) ? 'selected' : '' ?>>4th Semester</option>
        <option value="Fall 2028 (5th Semester)" <?= (strpos($student['current_semester'], '5th') !== false) ? 'selected' : '' ?>>5th Semester</option>
        <option value="Spring 2029 (6th Semester)" <?= (strpos($student['current_semester'], '6th') !== false) ? 'selected' : '' ?>>6th Semester</option>
        <option value="Fall 2029 (7th Semester)" <?= (strpos($student['current_semester'], '7th') !== false) ? 'selected' : '' ?>>7th Semester</option>
        <option value="Spring 2030 (8th Semester)" <?= (strpos($student['current_semester'], '8th') !== false) ? 'selected' : '' ?>>8th Semester (Final)</option>
        <option value="8th Semester (Completed)" <?= ($student['status'] === 'Graduated') ? 'selected' : '' ?>>Completed (All 8 Semesters)</option>
      </select>
    </div>

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">অর্জিত CGPA</label>
      <input type="number" step="0.01" min="0" max="4.00" name="cgpa" value="<?= e($student['cgpa']) ?>" class="form-control" style="font-size: 0.88rem;">
    </div>

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">অর্জিত ক্রেডিট (Credits)</label>
      <input type="number" min="0" max="160" name="credits_completed" value="<?= (int)$student['credits_completed'] ?>" class="form-control" style="font-size: 0.88rem;">
    </div>

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">ছাত্রের অবস্থা (Status)</label>
      <select name="status" class="form-select" style="font-size: 0.88rem;">
        <option value="Active" <?= ($student['status'] === 'Active') ? 'selected' : '' ?>>Active (নিয়মিত অধ্যয়নরত)</option>
        <option value="Graduated" <?= ($student['status'] === 'Graduated') ? 'selected' : '' ?>>Graduated (উত্তীর্ণ / পাসকৃত)</option>
        <option value="Suspended" <?= ($student['status'] === 'Suspended') ? 'selected' : '' ?>>Suspended (সাময়িক বরখাস্ত)</option>
      </select>
    </div>

    <button type="submit" class="btn-primary" style="padding: 0.65rem 1.25rem; font-size: 0.88rem; white-space: nowrap;">
      <i class="fas fa-save"></i> সেমিস্টার আপডেট
    </button>
  </form>
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
      <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
        <div>
          <h4 style="margin: 0 0 0.2rem; font-size: 1.1rem; color: #0f172a;">Official Semester Grades &amp; Transcripts</h4>
          <small style="color:#64748b;">Manage academic courses, grades, and generate official student transcripts</small>
        </div>
        <div style="display:flex; gap:0.5rem; align-items:center;">
          <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $studentId ?>&semester=all" target="_blank" class="btn-primary" style="padding:0.4rem 0.85rem; font-size:0.82rem; background:#16a34a; text-decoration:none;">
            <i class="fas fa-file-pdf"></i> View &amp; Print Full Transcript
          </a>
        </div>
      </div>
      <div style="padding: 1rem 1.5rem;">
        <?php if (!empty($groupedAdminResults)): ?>
          <?php foreach ($groupedAdminResults as $semTitle => $cList): ?>
            <?php $st = $semesterAdminStats[$semTitle] ?? ['sem_credits' => 0, 'sgpa' => 0, 'cum_credits' => 0, 'cgpa' => 0]; ?>
            <div style="margin-bottom: 2rem; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
              
              <!-- Semester Header with SGPA & CGPA -->
              <div style="background: linear-gradient(135deg, #1e3a8a 0%, #0f2c59 100%); color: #ffffff; padding: 0.75rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.6rem;">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                  <i class="fas fa-calendar-check" style="color: #60a5fa;"></i>
                  <strong style="font-size: 0.95rem;"><?= e($semTitle) ?></strong>
                </div>

                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; font-size: 0.82rem;">
                  <span style="background: rgba(255, 255, 255, 0.15); padding: 3px 10px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.2);">
                    Credits: <strong><?= number_format((float)$st['sem_credits'], 1) ?> Cr</strong>
                  </span>
                  <span style="background: rgba(255, 255, 255, 0.2); padding: 3px 10px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.25);">
                    Semester GPA: <strong><?= number_format((float)$st['sgpa'], 2) ?></strong>
                  </span>
                  <span style="background: #15803d; color: #ffffff; padding: 3px 10px; border-radius: 4px; font-weight: 700; box-shadow: 0 1px 2px rgba(0,0,0,0.2);">
                    Cumulative CGPA: <?= number_format((float)$st['cgpa'], 2) ?>
                  </span>
                  <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $studentId ?>&semester=<?= urlencode($semTitle) ?>" target="_blank" style="background: #ffffff; color: #0f2c59; border-radius: 4px; padding: 3px 10px; font-size: 0.78rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fas fa-file-pdf" style="color: #dc2626;"></i> Transcript PDF
                  </a>
                </div>
              </div>

              <!-- Subjects Table for this Semester -->
              <div class="table-responsive">
                <table class="rmu-table" style="box-shadow: none; margin: 0;">
                  <thead>
                    <tr>
                      <th>Course Code &bull; Title</th>
                      <th style="width: 85px; text-align: center;">Credit</th>
                      <th style="width: 100px; text-align: center;">Letter Grade</th>
                      <th style="width: 100px; text-align: center;">Grade Point</th>
                      <th style="width: 110px; text-align: center;">Points Earned</th>
                      <th style="text-align: right; width: 80px;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($cList as $res): ?>
                      <?php 
                        $cr = (float)$res['credit'];
                        $gp = (float)$res['grade_point'];
                        $pts = round($cr * $gp, 2);
                      ?>
                      <tr>
                        <td>
                          <strong style="color: var(--rmu-primary); font-family: monospace;"><?= e($res['course_code']) ?></strong>
                          <div style="font-weight: 600; color: #334155; font-size: 0.88rem;"><?= e($res['course_name']) ?></div>
                          <small style="color: #64748b;"><?= e($res['exam_term']) ?></small>
                        </td>
                        <td style="text-align: center; font-weight: 600;"><?= number_format($cr, 1) ?></td>
                        <td style="text-align: center;">
                          <span style="font-weight: 700; color: #1e40af; font-size: 0.95rem;"><?= e($res['letter_grade']) ?></span>
                        </td>
                        <td style="text-align: center;">
                          <strong style="color: #047857;"><?= number_format($gp, 2) ?></strong>
                        </td>
                        <td style="text-align: center; font-weight: 700; color: #0f172a;">
                          <?= number_format($pts, 2) ?>
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
                  </tbody>
                  <tfoot>
                    <tr style="background: #f8fafc; font-weight: 700; font-size: 0.85rem; border-top: 1.5px solid #cbd5e1;">
                      <td style="text-align: right; color: #475569;">Semester Summary (<?= e($semTitle) ?>):</td>
                      <td style="text-align: center; color: #0f172a;"><?= number_format((float)$st['sem_credits'], 1) ?> Cr</td>
                      <td colspan="2" style="text-align: right; color: #1e3a8a;">Semester GPA (SGPA): <strong><?= number_format((float)$st['sgpa'], 2) ?></strong></td>
                      <td colspan="2" style="text-align: center; color: #15803d; font-weight: 800; font-size: 0.95rem;">Cumulative CGPA: <?= number_format((float)$st['cgpa'], 2) ?></td>
                    </tr>
                  </tfoot>
                </table>
              </div>

            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="text-align: center; padding: 2.5rem; color: #64748b;">No academic results published yet.</p>
        <?php endif; ?>
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

