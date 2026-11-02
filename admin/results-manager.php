<?php
/**
 * Admin Panel: 8-Semester Academic Results, Marks & CGPA Management System
 * Full CRUD, Auto-grading, Credit-weighted SGPA & CGPA Recalculation
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Results & CGPA Management';
$db = getDb();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $targetStudentId = (int)($_POST['student_id'] ?? 0);

        // 1. Add Result
        if ($action === 'add_result') {
            $semester = trim($_POST['semester'] ?? '');
            $code = strtoupper(trim($_POST['course_code'] ?? ''));
            $title = trim($_POST['course_name'] ?? '');
            $credit = (float)($_POST['credit'] ?? 3.0);
            $fullMarks = (int)($_POST['full_marks'] ?? 100);
            $marks = isset($_POST['marks']) && $_POST['marks'] !== '' ? (float)$_POST['marks'] : null;
            $term = trim($_POST['exam_term'] ?? 'Semester Final Examination');
            $isPublished = !empty($_POST['is_published']) ? 1 : 0;

            if ($targetStudentId > 0 && !empty($semester) && !empty($code)) {
                // Auto calculate grade and grade point from marks if provided
                if ($marks !== null) {
                    $gradeInfo = calculate_grade_from_marks($marks);
                    $letterGrade = $gradeInfo['letter_grade'];
                    $gradePoint = $gradeInfo['grade_point'];
                } else {
                    $letterGrade = trim($_POST['letter_grade'] ?? 'A+');
                    $gradePoint = (float)($_POST['grade_point'] ?? 4.00);
                }

                $ins = $db->prepare("
                    INSERT INTO student_results 
                    (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, is_published)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$targetStudentId, $semester, $code, $title, $credit, $fullMarks, $marks, $letterGrade, $gradePoint, $term, $isPublished]);

                $resMetrics = recalculate_student_cgpa($targetStudentId);
                log_audit('RESULT_ADDED', "Added {$code} ({$letterGrade}, {$marks} marks) for Student #{$targetStudentId}");
                flash_set('success', "Result for '{$code}' added successfully! Updated CGPA: {$resMetrics['cgpa']}.");
            } else {
                flash_set('error', 'Student, Semester, and Course Code are required.');
            }
        }

        // 2. Edit Result
        elseif ($action === 'edit_result') {
            $resultId = (int)($_POST['result_id'] ?? 0);
            $semester = trim($_POST['semester'] ?? '');
            $code = strtoupper(trim($_POST['course_code'] ?? ''));
            $title = trim($_POST['course_name'] ?? '');
            $credit = (float)($_POST['credit'] ?? 3.0);
            $fullMarks = (int)($_POST['full_marks'] ?? 100);
            $marks = isset($_POST['marks']) && $_POST['marks'] !== '' ? (float)$_POST['marks'] : null;
            $term = trim($_POST['exam_term'] ?? 'Semester Final Examination');
            $isPublished = !empty($_POST['is_published']) ? 1 : 0;

            if ($resultId > 0 && $targetStudentId > 0) {
                if ($marks !== null) {
                    $gradeInfo = calculate_grade_from_marks($marks);
                    $letterGrade = $gradeInfo['letter_grade'];
                    $gradePoint = $gradeInfo['grade_point'];
                } else {
                    $letterGrade = trim($_POST['letter_grade'] ?? 'A+');
                    $gradePoint = (float)($_POST['grade_point'] ?? 4.00);
                }

                $upd = $db->prepare("
                    UPDATE student_results 
                    SET semester = ?, course_code = ?, course_name = ?, credit = ?, full_marks = ?, marks = ?, letter_grade = ?, grade_point = ?, exam_term = ?, is_published = ?
                    WHERE id = ? AND student_id = ?
                ");
                $upd->execute([$semester, $code, $title, $credit, $fullMarks, $marks, $letterGrade, $gradePoint, $term, $isPublished, $resultId, $targetStudentId]);

                $resMetrics = recalculate_student_cgpa($targetStudentId);
                log_audit('RESULT_EDITED', "Updated result #{$resultId} ({$code}) for Student #{$targetStudentId}");
                flash_set('success', "Result updated successfully! Current CGPA: {$resMetrics['cgpa']}.");
            }
        }

        // 3. Delete Result
        elseif ($action === 'delete_result') {
            $resultId = (int)($_POST['result_id'] ?? 0);
            if ($resultId > 0 && $targetStudentId > 0) {
                $del = $db->prepare("DELETE FROM student_results WHERE id = ? AND student_id = ?");
                $del->execute([$resultId, $targetStudentId]);

                $resMetrics = recalculate_student_cgpa($targetStudentId);
                log_audit('RESULT_DELETED', "Deleted result #{$resultId} for Student #{$targetStudentId}");
                flash_set('success', "Result removed. Recalculated CGPA: {$resMetrics['cgpa']}.");
            }
        }

        // 4. Toggle Publish Status
        elseif ($action === 'toggle_publish') {
            $resultId = (int)($_POST['result_id'] ?? 0);
            $newStatus = !empty($_POST['new_status']) ? 1 : 0;
            if ($resultId > 0 && $targetStudentId > 0) {
                $db->prepare("UPDATE student_results SET is_published = ? WHERE id = ? AND student_id = ?")->execute([$newStatus, $resultId, $targetStudentId]);
                recalculate_student_cgpa($targetStudentId);
                flash_set('success', "Publication status changed to " . ($newStatus ? 'Published' : 'Draft') . ".");
            }
        }

        // 5. Populate All 8 Semesters with RMU Standard Curriculum
        elseif ($action === 'populate_all_8_semesters') {
            if ($targetStudentId > 0) {
                $db->prepare("DELETE FROM student_results WHERE student_id = ?")->execute([$targetStudentId]);

                $curriculum = [
                    // 1st Semester
                    ['1st Semester (Spring 2023)', 'CSE 101', 'Structured Programming in C', 3.0, 100, 85.00],
                    ['1st Semester (Spring 2023)', 'CSE 102', 'Structured Programming Lab', 1.5, 100, 88.00],
                    ['1st Semester (Spring 2023)', 'ENG 101', 'English Reading & Writing Skills', 3.0, 100, 78.00],
                    ['1st Semester (Spring 2023)', 'MAT 101', 'Differential and Integral Calculus', 3.0, 100, 84.00],
                    // 2nd Semester
                    ['2nd Semester (Fall 2023)', 'CSE 103', 'Discrete Mathematics', 3.0, 100, 82.00],
                    ['2nd Semester (Fall 2023)', 'CSE 104', 'Object Oriented Programming (Java)', 3.0, 100, 77.00],
                    ['2nd Semester (Fall 2023)', 'PHY 101', 'Physics (Mechanics, Waves & Optics)', 3.0, 100, 76.00],
                    ['2nd Semester (Fall 2023)', 'BAN 105', 'Bangla Language & Tagore Literary Studies', 2.0, 100, 86.00],
                    // 3rd Semester
                    ['3rd Semester (Spring 2024)', 'CSE 201', 'Data Structures & Algorithms', 3.0, 100, 83.00],
                    ['3rd Semester (Spring 2024)', 'CSE 202', 'Data Structures & Algorithms Lab', 1.5, 100, 85.00],
                    ['3rd Semester (Spring 2024)', 'CSE 203', 'Digital Logic Design & Microprocessors', 3.0, 100, 76.00],
                    ['3rd Semester (Spring 2024)', 'MAT 102', 'Coordinate Geometry & Linear Algebra', 3.0, 100, 78.00],
                    // 4th Semester
                    ['4th Semester (Fall 2024)', 'CSE 205', 'Database Management Systems', 3.0, 100, 84.00],
                    ['4th Semester (Fall 2024)', 'CSE 206', 'Database Management Systems Lab', 1.5, 100, 87.00],
                    ['4th Semester (Fall 2024)', 'CSE 207', 'Computer Architecture & Organization', 3.0, 100, 77.00],
                    ['4th Semester (Fall 2024)', 'STA 201', 'Statistics & Probability for Engineers', 3.0, 100, 79.00],
                    // 5th Semester
                    ['5th Semester (Spring 2025)', 'CSE 301', 'Software Engineering & System Analysis', 3.0, 100, 82.00],
                    ['5th Semester (Spring 2025)', 'CSE 302', 'Operating Systems & System Programming', 3.0, 100, 77.00],
                    ['5th Semester (Spring 2025)', 'CSE 303', 'Operating Systems Lab (Linux/Unix)', 1.5, 100, 86.00],
                    ['5th Semester (Spring 2025)', 'CSE 304', 'Theory of Computation & Automata', 3.0, 100, 78.00],
                    // 6th Semester
                    ['6th Semester (Fall 2025)', 'CSE 305', 'Computer Networks & Internet Protocols', 3.0, 100, 88.00],
                    ['6th Semester (Fall 2025)', 'CSE 306', 'Computer Networks Laboratory', 1.5, 100, 90.00],
                    ['6th Semester (Fall 2025)', 'CSE 307', 'Web Engineering & Full-Stack Development', 3.0, 100, 85.00],
                    ['6th Semester (Fall 2025)', 'CSE 308', 'Microcontroller & Embedded Systems Lab', 1.5, 100, 77.00],
                    // 7th Semester
                    ['7th Semester (Spring 2026)', 'CSE 401', 'Artificial Intelligence & Machine Learning', 3.0, 100, 85.00],
                    ['7th Semester (Spring 2026)', 'CSE 402', 'Compiler Design & Construction', 3.0, 100, 77.00],
                    ['7th Semester (Spring 2026)', 'CSE 403', 'Cyber Security & Cryptography', 3.0, 100, 76.00],
                    ['7th Semester (Spring 2026)', 'CSE 499A', 'Capstone Project & Thesis (Part I)', 3.0, 100, 86.00],
                    // 8th Semester
                    ['8th Semester (Fall 2026)', 'CSE 405', 'Cloud Computing & Distributed Systems', 3.0, 100, 87.00],
                    ['8th Semester (Fall 2026)', 'CSE 406', 'Digital Image Processing & Vision', 3.0, 100, 78.00],
                    ['8th Semester (Fall 2026)', 'CSE 499B', 'Capstone Project & Defense (Part II)', 3.0, 100, 90.00],
                    ['8th Semester (Fall 2026)', 'MGT 401', 'Industrial Management & Professional Ethics', 2.0, 100, 86.00]
                ];

                $insStmt = $db->prepare("
                    INSERT INTO student_results 
                    (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, is_published)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Semester Final Examination', 1)
                ");

                foreach ($curriculum as $c) {
                    $grade = calculate_grade_from_marks($c[5]);
                    $insStmt->execute([
                        $targetStudentId, $c[0], $c[1], $c[2], $c[3], $c[4], $c[5],
                        $grade['letter_grade'], $grade['grade_point']
                    ]);
                }

                $resMetrics = recalculate_student_cgpa($targetStudentId);
                $db->prepare("UPDATE students SET current_year = '4th Year', current_semester = '8th Semester (Completed)', status = 'Graduated' WHERE id = ?")->execute([$targetStudentId]);

                log_audit('ALL_8_SEMESTERS_POPULATED', "Populated complete 8 semesters for Student #{$targetStudentId}");
                flash_set('success', "All 8 Semesters (32 subjects) populated successfully with marks and credit-weighted CGPA {$resMetrics['cgpa']}!");
            }
        }

        // Redirect back preserving selected student
        header("Location: " . APP_URL . "/admin/results-manager.php?student_id=" . $targetStudentId);
        exit;
    }
}

// Fetch all students for dropdown selector
$allStudents = $db->query("
    SELECT s.id, s.student_id, s.full_name, s.batch, s.cgpa, p.title_en AS program_title, d.name_en AS dept_name
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    ORDER BY s.student_id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Determine selected student
$selectedStudentId = (int)($_GET['student_id'] ?? 0);
if ($selectedStudentId <= 0 && !empty($allStudents)) {
    // Default to first student with results, or first student
    $firstWithRes = $db->query("SELECT student_id FROM student_results LIMIT 1")->fetchColumn();
    $selectedStudentId = $firstWithRes ? (int)$firstWithRes : (int)$allStudents[0]['id'];
}

// Fetch academic summary for selected student
$summary = null;
if ($selectedStudentId > 0) {
    // Admin sees all results (including drafts)
    $summary = get_student_academic_summary($selectedStudentId, false);
}

// List of all standard 8 semesters for quick selection
$standardSemesters = [
    '1st Semester (Spring 2023)',
    '2nd Semester (Fall 2023)',
    '3rd Semester (Spring 2024)',
    '4th Semester (Fall 2024)',
    '5th Semester (Spring 2025)',
    '6th Semester (Fall 2025)',
    '7th Semester (Spring 2026)',
    '8th Semester (Fall 2026)'
];

include __DIR__ . '/includes/admin-header.php';
?>

<!-- Header & Quick Actions -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem;">
      <i class="fas fa-award" style="color:var(--rmu-primary);"></i> 8-Semester Results &amp; CGPA Management
    </h2>
    <p style="color:#64748b; font-size:0.88rem; margin:0;">
      Manage subject-wise marks, credit hours, auto-calculated grades, SGPA, and cumulative CGPA.
    </p>
  </div>

  <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
    <?php if ($selectedStudentId > 0 && !empty($summary['student'])): ?>
      <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Populate / Refresh standard RMU 8-semester curriculum (32 courses) for this student?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="populate_all_8_semesters">
        <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
        <button type="submit" class="btn-primary" style="background:#7c3aed; padding:0.55rem 1.1rem; font-size:0.86rem; border:none; cursor:pointer;">
          <i class="fas fa-magic"></i> Populate Standard RMU 8-Semester Curriculum
        </button>
      </form>
      <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $selectedStudentId ?>&semester=all" target="_blank" class="btn-primary" style="background:#15803d; padding:0.55rem 1.1rem; font-size:0.86rem; text-decoration:none;">
        <i class="fas fa-file-pdf"></i> Official Transcript PDF
      </a>
      <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= $selectedStudentId ?>" class="btn-primary" style="background:#0284c7; padding:0.55rem 1.1rem; font-size:0.86rem; text-decoration:none;">
        <i class="fas fa-user"></i> Student Profile
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Student Selector Bar -->
<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem 1.5rem; margin-bottom:2rem; box-shadow:0 1px 4px rgba(0,0,0,0.04);">
  <form method="GET" action="" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)) auto; gap:1rem; align-items:flex-end;">
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label" style="font-weight:700; font-size:0.85rem; color:#0f2c59;">
        <i class="fas fa-search"></i> Select / Search Student:
      </label>
      <select name="student_id" class="form-select" onchange="this.form.submit()" style="font-size:0.9rem; font-weight:600;">
        <?php foreach ($allStudents as $st): ?>
          <option value="<?= $st['id'] ?>" <?= ($st['id'] == $selectedStudentId) ? 'selected' : '' ?>>
            <?= e($st['student_id']) ?> — <?= e($st['full_name']) ?> (<?= e($st['program_title'] ?? 'Program') ?> &bull; CGPA: <?= number_format((float)$st['cgpa'], 2) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <button type="submit" class="btn-primary" style="padding:0.65rem 1.25rem; font-size:0.88rem; white-space:nowrap;">
      <i class="fas fa-filter"></i> Load Academic Records
    </button>
  </form>
</div>

<?php if ($summary && !empty($summary['student'])): ?>
  <?php $stObj = $summary['student']; ?>

  <!-- Student Academic Profile Banner -->
  <div style="background:linear-gradient(135deg, #0f2c59 0%, #1e3a8a 100%); color:#ffffff; border-radius:12px; padding:1.75rem 2rem; margin-bottom:2rem; box-shadow:0 4px 12px rgba(15, 44, 89, 0.15); display:grid; grid-template-columns:auto 1fr auto; gap:2rem; align-items:center;">
    <div style="width:75px; height:75px; border-radius:50%; background:#e0f2fe; color:#0f2c59; display:flex; align-items:center; justify-content:center; font-size:2rem; font-weight:800; border:3px solid #60a5fa;">
      <?= strtoupper(substr($stObj['full_name'], 0, 1)) ?>
    </div>

    <div>
      <h3 style="margin:0 0 0.25rem; font-size:1.4rem; font-weight:800; color:#ffffff;">
        <?= e($stObj['full_name']) ?> <span style="font-family:monospace; color:#93c5fd; font-size:1.15rem;">(<?= e($stObj['student_id']) ?>)</span>
      </h3>
      <div style="font-size:0.88rem; color:#cbd5e1; margin-bottom:0.4rem;">
        <strong>Program:</strong> <?= e($stObj['program_name'] ?? 'B.Sc. in CSE') ?> &bull; 
        <strong>Dept:</strong> <?= e($stObj['department_name'] ?? 'CSE') ?> &bull; 
        <strong>Faculty:</strong> <?= e($stObj['faculty_name'] ?? 'Science & Engineering') ?>
      </div>
      <div style="font-size:0.82rem; color:#94a3b8; display:flex; gap:1.2rem; flex-wrap:wrap;">
        <span><i class="fas fa-layer-group"></i> Batch: <?= e($stObj['batch']) ?></span>
        <span><i class="fas fa-language"></i> Medium: English</span>
        <span><i class="fas fa-user-tag"></i> Status: <strong style="color:<?= ($stObj['status'] === 'Graduated') ? '#86efac' : '#38bdf8' ?>;"><?= e($stObj['status']) ?></strong></span>
      </div>
    </div>

    <div style="text-align:right; border-left:1px solid rgba(255,255,255,0.2); padding-left:2rem;">
      <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:#93c5fd; font-weight:700;">Final Cumulative CGPA</div>
      <div style="font-size:2.4rem; font-weight:900; color:#4ade80; line-height:1.1;">
        <?= number_format((float)$summary['final_cgpa'], 2) ?>
      </div>
      <div style="font-size:0.82rem; color:#cbd5e1; margin-top:3px;">
        <?= (int)$summary['total_earned_credits'] ?> / <?= (int)$stObj['total_credits_required'] ?> Credits Earned
      </div>
    </div>
  </div>

  <!-- 8-Semester Quick GPA Progress Strip -->
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; margin-bottom:2rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <h4 style="margin:0 0 0.85rem; font-size:0.95rem; font-weight:800; color:#0f2c59; text-transform:uppercase; letter-spacing:0.5px;">
      <i class="fas fa-chart-line" style="color:var(--rmu-primary);"></i> 8-Semester SGPA Progression Overview
    </h4>
    <div style="display:grid; grid-template-columns:repeat(8, 1fr); gap:0.6rem; text-align:center;">
      <?php for ($sem = 1; $sem <= 8; $sem++): ?>
        <?php $hasSem = isset($summary['semester_gpas'][$sem]); ?>
        <div style="background:<?= $hasSem ? '#f0fdf4' : '#f8fafc' ?>; border:1.5px solid <?= $hasSem ? '#86efac' : '#e2e8f0' ?>; border-radius:8px; padding:0.6rem 0.3rem;">
          <div style="font-size:0.72rem; font-weight:700; color:<?= $hasSem ? '#166534' : '#94a3b8' ?>;">Sem <?= $sem ?></div>
          <div style="font-size:1.15rem; font-weight:800; color:<?= $hasSem ? '#15803d' : '#cbd5e1' ?>; margin:2px 0;">
            <?= $hasSem ? number_format((float)$summary['semester_gpas'][$sem], 2) : '—' ?>
          </div>
          <div style="font-size:0.65rem; color:#64748b;"><?= $hasSem ? 'Published' : 'Pending' ?></div>
        </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- Main Management Area: Semester Results Table (Left) + Add Result Form (Right) -->
  <div style="display:grid; grid-template-columns:2.1fr 1fr; gap:2rem; align-items:flex-start;">
    
    <!-- Left Column: 8 Semesters Result Blocks -->
    <div>
      <?php if (!empty($summary['semesters'])): ?>
        <?php foreach ($summary['semesters'] as $semName => $sData): ?>
          <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; margin-bottom:2rem; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05);">
            
            <!-- Semester Header -->
            <div style="background:linear-gradient(135deg, #0f2c59 0%, #1e3a8a 100%); color:#ffffff; padding:0.85rem 1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.6rem;">
              <div style="display:flex; align-items:center; gap:0.6rem;">
                <span style="background:#f59e0b; color:#0f172a; padding:2px 8px; border-radius:4px; font-weight:900; font-size:0.85rem;">
                  <?= $sData['semester_number'] ?>
                </span>
                <strong style="font-size:0.98rem;"><?= e($semName) ?></strong>
              </div>

              <div style="display:flex; align-items:center; gap:0.75rem; font-size:0.82rem; flex-wrap:wrap;">
                <span style="background:rgba(255,255,255,0.15); padding:3px 8px; border-radius:4px;">
                  Reg Cr: <strong><?= number_format($sData['registered_credits'], 1) ?></strong>
                </span>
                <span style="background:rgba(255,255,255,0.2); padding:3px 8px; border-radius:4px;">
                  SGPA: <strong style="font-size:0.92rem;"><?= number_format($sData['sgpa'], 2) ?></strong>
                </span>
                <span style="background:#15803d; color:#ffffff; padding:3px 9px; border-radius:4px; font-weight:700;">
                  CGPA: <?= number_format($sData['cumulative_cgpa'], 2) ?>
                </span>
                <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $selectedStudentId ?>&semester=<?= urlencode($semName) ?>" target="_blank" style="background:#ffffff; color:#0f2c59; border-radius:4px; padding:3px 9px; font-size:0.76rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                  <i class="fas fa-file-pdf" style="color:#dc2626;"></i> PDF
                </a>
              </div>
            </div>

            <!-- Subject-wise Table with Marks (out of 100) -->
            <div class="table-responsive">
              <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.86rem;">
                <thead>
                  <tr>
                    <th>Subject Code &bull; Title</th>
                    <th style="width:65px; text-align:center;">Credit</th>
                    <th style="width:75px; text-align:center;">Full</th>
                    <th style="width:85px; text-align:center;">Marks</th>
                    <th style="width:70px; text-align:center;">Grade</th>
                    <th style="width:75px; text-align:center;">Point</th>
                    <th style="width:80px; text-align:center;">Status</th>
                    <th style="text-align:right; width:95px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($sData['courses'] as $co): ?>
                    <tr>
                      <td>
                        <strong style="color:var(--rmu-primary); font-family:monospace;"><?= e($co['course_code']) ?></strong>
                        <div style="font-weight:600; color:#1e293b;"><?= e($co['course_name']) ?></div>
                        <small style="color:#64748b;"><?= e($co['exam_term']) ?></small>
                      </td>
                      <td style="text-align:center; font-weight:600;"><?= number_format($co['credit'], 1) ?></td>
                      <td style="text-align:center; color:#64748b;"><?= (int)$co['full_marks'] ?></td>
                      <td style="text-align:center;">
                        <strong style="color:#0f2c59; font-size:0.95rem;"><?= ($co['marks'] !== null) ? number_format($co['marks'], 1) : '—' ?></strong>
                      </td>
                      <td style="text-align:center;">
                        <span style="font-weight:800; color:#1e40af; font-size:0.95rem;"><?= e($co['letter_grade']) ?></span>
                      </td>
                      <td style="text-align:center; font-weight:700; color:#047857;">
                        <?= number_format($co['grade_point'], 2) ?>
                      </td>
                      <td style="text-align:center;">
                        <span style="font-size:0.75rem; font-weight:800; color:<?= ($co['status'] === 'Pass') ? '#15803d' : '#dc2626' ?>; background:<?= ($co['status'] === 'Pass') ? '#dcfce7' : '#fee2e2' ?>; padding:2px 6px; border-radius:4px;">
                          <?= e($co['status']) ?>
                        </span>
                      </td>
                      <td style="text-align:right;">
                        <!-- Publish Toggle Form -->
                        <form action="" method="POST" style="display:inline;" title="Toggle Published/Draft">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="toggle_publish">
                          <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                          <input type="hidden" name="result_id" value="<?= $co['id'] ?>">
                          <input type="hidden" name="new_status" value="<?= $co['is_published'] ? 0 : 1 ?>">
                          <button type="submit" style="border:none; background:none; cursor:pointer; color:<?= $co['is_published'] ? '#15803d' : '#94a3b8' ?>; font-size:0.85rem;" title="<?= $co['is_published'] ? 'Published (Click to unpublish)' : 'Draft (Click to publish)' ?>">
                            <i class="fas <?= $co['is_published'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                          </button>
                        </form>

                        <!-- Delete Result Form -->
                        <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Delete result for <?= e($co['course_code']) ?>?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete_result">
                          <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                          <input type="hidden" name="result_id" value="<?= $co['id'] ?>">
                          <button type="submit" style="border:none; background:none; cursor:pointer; color:#ef4444; font-size:0.85rem; margin-left:6px;" title="Delete Course Result">
                            <i class="fas fa-trash-alt"></i>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr style="background:#f8fafc; font-weight:700; font-size:0.84rem; border-top:1.5px solid #cbd5e1;">
                    <td style="text-align:right; color:#475569;">Semester Summary (<?= e($semName) ?>):</td>
                    <td style="text-align:center; color:#0f2c59;"><?= number_format($sData['registered_credits'], 1) ?> Cr</td>
                    <td colspan="2" style="text-align:right; color:#1e3a8a;">Earned: <?= number_format($sData['earned_credits'], 1) ?> Cr</td>
                    <td colspan="2" style="text-align:right; color:#1e3a8a;">SGPA: <strong><?= number_format($sData['sgpa'], 2) ?></strong></td>
                    <td colspan="2" style="text-align:center; color:#15803d; font-weight:800;">CGPA: <?= number_format($sData['cumulative_cgpa'], 2) ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>

          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:3rem 2rem; text-align:center;">
          <i class="fas fa-folder-open" style="font-size:3rem; color:#cbd5e1; margin-bottom:1rem;"></i>
          <h3 style="color:#0f172a; margin:0 0 0.5rem;">No academic results published yet.</h3>
          <p style="color:#64748b; font-size:0.9rem; margin-bottom:1.5rem;">
            This student does not currently have any published semester results.
          </p>
          <form action="" method="POST" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="populate_all_8_semesters">
            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
            <button type="submit" class="btn-primary" style="background:#15803d; padding:0.75rem 1.5rem; font-size:0.95rem;">
              <i class="fas fa-magic"></i> Populate Standard RMU 8-Semester Curriculum (32 Subjects)
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>

    <!-- Right Column: Add Subject Result Form with Live Auto-Grading & Reference Scale -->
    <div>
      
      <!-- Add Subject Form Card -->
      <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.5rem; box-shadow:0 1px 4px rgba(0,0,0,0.05); margin-bottom:1.5rem;">
        <h4 style="margin:0 0 1.25rem; font-size:1.1rem; color:#0f2c59; display:flex; align-items:center; gap:0.5rem;">
          <i class="fas fa-plus-circle" style="color:var(--rmu-primary);"></i> Add Subject Result
        </h4>

        <form action="" method="POST" id="addResultForm">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_result">
          <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">

          <div class="form-group">
            <label class="form-label">Semester *</label>
            <select name="semester" class="form-select" required>
              <?php foreach ($standardSemesters as $semOption): ?>
                <option value="<?= e($semOption) ?>"><?= e($semOption) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Course Code *</label>
            <input type="text" name="course_code" placeholder="e.g. CSE 101" class="form-control" required style="text-transform:uppercase;">
          </div>

          <div class="form-group">
            <label class="form-label">Course Title *</label>
            <input type="text" name="course_name" placeholder="e.g. Structured Programming in C" class="form-control" required>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
            <div class="form-group">
              <label class="form-label">Credit Hours *</label>
              <input type="number" step="0.5" min="0.5" max="6.0" name="credit" value="3.0" class="form-control" required>
            </div>
            <div class="form-group">
              <label class="form-label">Full Marks</label>
              <input type="number" name="full_marks" value="100" class="form-control" readonly style="background:#f1f5f9;">
            </div>
          </div>

          <!-- Obtained Marks with Live Auto-Grading -->
          <div style="background:#f0fdf4; border:1.5px solid #86efac; border-radius:8px; padding:1rem; margin-bottom:1rem;">
            <label class="form-label" style="color:#166534; font-weight:700;">
              Obtained Marks (out of 100) *
            </label>
            <input type="number" step="0.5" min="0" max="100" name="marks" id="marksInput" placeholder="e.g. 85" class="form-control" required style="font-size:1.1rem; font-weight:800; color:#0f2c59;" oninput="autoCalculateGrade(this.value)">
            <small style="color:#15803d; font-size:0.78rem; display:block; margin-top:3px;">
              মার্কস লিখলে লেটার গ্রেড ও পয়েন্ট স্বয়ংক্রিয়ভাবে হিসাব হবে।
            </small>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-top:0.75rem;">
              <div>
                <label style="font-size:0.75rem; color:#475569; font-weight:600;">Letter Grade:</label>
                <input type="text" name="letter_grade" id="gradeOutput" value="A+" class="form-control" style="font-weight:800; color:#1e40af;" readonly>
              </div>
              <div>
                <label style="font-size:0.75rem; color:#475569; font-weight:600;">Grade Point (out of 4.00):</label>
                <input type="number" step="0.01" min="0" max="4.00" name="grade_point" id="pointOutput" value="4.00" class="form-control" style="font-weight:800; color:#047857;" readonly>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Exam Examination Term</label>
            <input type="text" name="exam_term" value="Semester Final Examination" class="form-control">
          </div>

          <div style="margin-bottom:1.25rem;">
            <label style="display:inline-flex; align-items:center; gap:0.5rem; font-size:0.88rem; font-weight:600; cursor:pointer;">
              <input type="checkbox" name="is_published" value="1" checked style="width:17px; height:17px;">
              <span>Publish result immediately to student portal</span>
            </label>
          </div>

          <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:0.75rem;">
            <i class="fas fa-check-circle"></i> Save &amp; Recalculate CGPA
          </button>
        </form>
      </div>

      <!-- UGC Grading Scale Reference -->
      <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; box-shadow:0 1px 4px rgba(0,0,0,0.04); font-size:0.78rem;">
        <h4 style="margin:0 0 0.65rem; font-size:0.88rem; font-weight:800; color:#0f2c59; text-transform:uppercase;">
          <i class="fas fa-table" style="color:var(--rmu-primary);"></i> UGC Uniform Grading Scale
        </h4>
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="background:#f1f5f9; color:#475569; border-bottom:1px solid #cbd5e1;">
              <th style="padding:4px 6px; text-align:left;">Marks Range</th>
              <th style="padding:4px 6px; text-align:center;">Grade</th>
              <th style="padding:4px 6px; text-align:right;">Point</th>
            </tr>
          </thead>
          <tbody>
            <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">80% – 100%</td><td style="text-align:center;"><strong>A+</strong></td><td style="text-align:right;">4.00</td></tr>
            <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">75% – 79%</td><td style="text-align:center;"><strong>A</strong></td><td style="text-align:right;">3.75</td></tr>
            <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">70% – 74%</td><td style="text-align:center;"><strong>A-</strong></td><td style="text-align:right;">3.50</td></tr>
            <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">65% – 69%</td><td style="text-align:center;"><strong>B+</strong></td><td style="text-align:right;">3.25</td></tr>
            <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">60% – 64%</td><td style="text-align:center;"><strong>B</strong></td><td style="text-align:right;">3.00</td></tr>
            <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">55% – 59%</td><td style="text-align:center;"><strong>B-</strong></td><td style="text-align:right;">2.75</td></tr>
            <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">50% – 54%</td><td style="text-align:center;"><strong>C+</strong></td><td style="text-align:right;">2.50</td></tr>
            <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">45% – 49%</td><td style="text-align:center;"><strong>C</strong></td><td style="text-align:right;">2.25</td></tr>
            <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">40% – 44%</td><td style="text-align:center;"><strong>D</strong></td><td style="text-align:right;">2.00</td></tr>
            <tr><td style="padding:4px 6px;">Below 40%</td><td style="text-align:center; color:#dc2626;"><strong>F</strong></td><td style="text-align:right; color:#dc2626;">0.00</td></tr>
          </tbody>
        </table>
      </div>

    </div>

  </div>

<?php endif; ?>

<!-- Auto-Grading Javascript Script -->
<script>
function autoCalculateGrade(marksVal) {
  var m = parseFloat(marksVal);
  var gradeEl = document.getElementById('gradeOutput');
  var pointEl = document.getElementById('pointOutput');

  if (isNaN(m) || m < 0) {
    gradeEl.value = '';
    pointEl.value = '';
    return;
  }

  if (m >= 80) {
    gradeEl.value = 'A+';
    pointEl.value = '4.00';
  } else if (m >= 75) {
    gradeEl.value = 'A';
    pointEl.value = '3.75';
  } else if (m >= 70) {
    gradeEl.value = 'A-';
    pointEl.value = '3.50';
  } else if (m >= 65) {
    gradeEl.value = 'B+';
    pointEl.value = '3.25';
  } else if (m >= 60) {
    gradeEl.value = 'B';
    pointEl.value = '3.00';
  } else if (m >= 55) {
    gradeEl.value = 'B-';
    pointEl.value = '2.75';
  } else if (m >= 50) {
    gradeEl.value = 'C+';
    pointEl.value = '2.50';
  } else if (m >= 45) {
    gradeEl.value = 'C';
    pointEl.value = '2.25';
  } else if (m >= 40) {
    gradeEl.value = 'D';
    pointEl.value = '2.00';
  } else {
    gradeEl.value = 'F';
    pointEl.value = '0.00';
  }
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
