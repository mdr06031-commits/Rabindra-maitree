<?php
/**
 * Admin Panel: Advanced Student Result Management System
 * Rabindra Maitree University (RMU)
 * Complete 8-Semester Management, Auto UGC Grading, Result Requests & Modification Audit Trail
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Student Result Management';
$db = getDb();
$currentAdmin = current_user();
$adminId = (int)($currentAdmin['id'] ?? ($_SESSION['user_id'] ?? 1));

// Active tab selection (editor, requests, history)
$activeTab = trim($_GET['tab'] ?? 'editor');
if (!in_array($activeTab, ['editor', 'requests', 'history'])) {
    $activeTab = 'editor';
}

// -------------------------------------------------------------------------
// POST Action Handlers
// -------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        $targetStudentId = (int)($_POST['student_id'] ?? 0);

        // 1. Add Subject Result
        if ($action === 'add_result') {
            $semester = trim($_POST['semester'] ?? '');
            $code = strtoupper(trim($_POST['course_code'] ?? ''));
            $title = trim($_POST['course_name'] ?? '');
            $credit = (float)($_POST['credit'] ?? 3.0);
            $fullMarks = (int)($_POST['full_marks'] ?? 100);
            $marks = isset($_POST['marks']) && $_POST['marks'] !== '' ? (float)$_POST['marks'] : null;
            $term = trim($_POST['exam_term'] ?? 'Semester Final Examination');
            $isPublished = !empty($_POST['is_published']) ? 1 : 0;
            $reason = trim($_POST['reason'] ?? 'Initial academic record entry');

            if ($targetStudentId > 0 && !empty($semester) && !empty($code)) {
                // Auto calculate grade and grade point from marks
                if ($marks !== null) {
                    $gradeInfo = calculate_grade_from_marks($marks);
                    $letterGrade = $gradeInfo['letter_grade'];
                    $gradePoint = $gradeInfo['grade_point'];
                } else {
                    $letterGrade = trim($_POST['letter_grade'] ?? 'A+');
                    $gradePoint = (float)($_POST['grade_point'] ?? 4.00);
                }

                $publishedAt = $isPublished ? date('Y-m-d H:i:s') : null;
                $publishedBy = $isPublished ? $adminId : null;

                $ins = $db->prepare("
                    INSERT INTO student_results 
                    (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, is_published, published_at, published_by, correction_reason)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$targetStudentId, $semester, $code, $title, $credit, $fullMarks, $marks, $letterGrade, $gradePoint, $term, $isPublished, $publishedAt, $publishedBy, $reason]);
                $newResId = (int)$db->lastInsertId();

                // Log modification history
                log_result_modification($newResId, $targetStudentId, $adminId, $code, $semester, 'Initial Entry', null, "{$marks} Marks ({$letterGrade}, GP {$gradePoint})", $reason);

                // If published, notify student
                if ($isPublished) {
                    create_student_notification(
                        $targetStudentId,
                        "New Course Result: {$code}",
                        "Your result for {$code} - {$title} ({$semester}) has been published. Grade: {$letterGrade} ({$gradePoint}).",
                        'result_published',
                        $newResId
                    );
                }

                $resMetrics = recalculate_student_cgpa($targetStudentId);
                log_audit('RESULT_ADDED', "Added {$code} ({$letterGrade}, {$marks} marks) for Student #{$targetStudentId}");
                flash_set('success', "Result for '{$code}' saved successfully! Updated CGPA: {$resMetrics['cgpa']}.");
            } else {
                flash_set('error', 'Student, Semester, and Course Code are required.');
            }
        }

        // 2. Edit Subject Result (Requires Reason)
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
            $reason = trim($_POST['correction_reason'] ?? '');

            if (empty($reason)) {
                $reason = 'Administrative grade verification & update';
            }

            if ($resultId > 0 && $targetStudentId > 0) {
                // Fetch previous record for history logging
                $oldStmt = $db->prepare("SELECT * FROM student_results WHERE id = ? AND student_id = ?");
                $oldStmt->execute([$resultId, $targetStudentId]);
                $oldRes = $oldStmt->fetch(PDO::FETCH_ASSOC);

                if ($marks !== null) {
                    $gradeInfo = calculate_grade_from_marks($marks);
                    $letterGrade = $gradeInfo['letter_grade'];
                    $gradePoint = $gradeInfo['grade_point'];
                } else {
                    $letterGrade = trim($_POST['letter_grade'] ?? 'A+');
                    $gradePoint = (float)($_POST['grade_point'] ?? 4.00);
                }

                $publishedAt = $isPublished ? ($oldRes['published_at'] ?? date('Y-m-d H:i:s')) : null;
                $publishedBy = $isPublished ? ($oldRes['published_by'] ?? $adminId) : null;

                $upd = $db->prepare("
                    UPDATE student_results 
                    SET semester = ?, course_code = ?, course_name = ?, credit = ?, full_marks = ?, marks = ?, letter_grade = ?, grade_point = ?, exam_term = ?, is_published = ?, published_at = ?, published_by = ?, correction_reason = ?
                    WHERE id = ? AND student_id = ?
                ");
                $upd->execute([$semester, $code, $title, $credit, $fullMarks, $marks, $letterGrade, $gradePoint, $term, $isPublished, $publishedAt, $publishedBy, $reason, $resultId, $targetStudentId]);

                // Log change history if marks, grade, or credit changed
                if ($oldRes) {
                    $oldSummary = "Marks: " . ($oldRes['marks'] ?? 'N/A') . ", Grade: {$oldRes['letter_grade']}, GP: {$oldRes['grade_point']}, Cr: {$oldRes['credit']}";
                    $newSummary = "Marks: {$marks}, Grade: {$letterGrade}, GP: {$gradePoint}, Cr: {$credit}";
                    log_result_modification($resultId, $targetStudentId, $adminId, $code, $semester, 'Grade Correction', $oldSummary, $newSummary, $reason);
                }

                // Notify student of update
                create_student_notification(
                    $targetStudentId,
                    "Academic Result Update: {$code}",
                    "Your academic result for {$code} ({$semester}) has been updated. New Grade: {$letterGrade} ({$gradePoint}). Reason: {$reason}.",
                    'grade_correction',
                    $resultId
                );

                $resMetrics = recalculate_student_cgpa($targetStudentId);
                log_audit('RESULT_EDITED', "Updated result #{$resultId} ({$code}) for Student #{$targetStudentId}. Reason: {$reason}");
                flash_set('success', "Result for '{$code}' updated successfully! Updated CGPA: {$resMetrics['cgpa']}.");
            }
        }

        // 3. Delete Subject Result
        elseif ($action === 'delete_result') {
            $resultId = (int)($_POST['result_id'] ?? 0);
            if ($resultId > 0 && $targetStudentId > 0) {
                $oldStmt = $db->prepare("SELECT * FROM student_results WHERE id = ? AND student_id = ?");
                $oldStmt->execute([$resultId, $targetStudentId]);
                $oldRes = $oldStmt->fetch(PDO::FETCH_ASSOC);

                if ($oldRes) {
                    log_result_modification($resultId, $targetStudentId, $adminId, $oldRes['course_code'], $oldRes['semester'], 'Subject Deleted', "{$oldRes['letter_grade']} ({$oldRes['marks']} marks)", 'DELETED', 'Administrative record deletion');
                }

                $del = $db->prepare("DELETE FROM student_results WHERE id = ? AND student_id = ?");
                $del->execute([$resultId, $targetStudentId]);

                $resMetrics = recalculate_student_cgpa($targetStudentId);
                log_audit('RESULT_DELETED', "Deleted result #{$resultId} for Student #{$targetStudentId}");
                flash_set('success', "Subject result record removed. Recalculated CGPA: {$resMetrics['cgpa']}.");
            }
        }

        // 4. Publish Entire Semester Results (Prominent Action)
        elseif ($action === 'publish_semester') {
            $semesterName = trim($_POST['semester_name'] ?? '');
            if ($targetStudentId > 0 && !empty($semesterName)) {
                $pubStmt = $db->prepare("
                    UPDATE student_results 
                    SET is_published = 1, published_at = NOW(), published_by = ? 
                    WHERE student_id = ? AND semester = ?
                ");
                $pubStmt->execute([$adminId, $targetStudentId, $semesterName]);
                $count = $pubStmt->rowCount();

                $resMetrics = recalculate_student_cgpa($targetStudentId);

                // Notify student
                create_student_notification(
                    $targetStudentId,
                    "Semester Results Published: {$semesterName}",
                    "Official examination results for {$semesterName} have been published! Your current cumulative CGPA is {$resMetrics['cgpa']}.",
                    'result_published'
                );

                log_audit('SEMESTER_RESULTS_PUBLISHED', "Published {$count} course results for {$semesterName} to Student #{$targetStudentId}");
                flash_set('success', "Success! All {$count} course results for '{$semesterName}' are now officially published to the Student Portal. Updated CGPA: {$resMetrics['cgpa']}.");
            }
        }

        // 5. Unpublish / Revert Semester Results to Draft
        elseif ($action === 'unpublish_semester') {
            $semesterName = trim($_POST['semester_name'] ?? '');
            if ($targetStudentId > 0 && !empty($semesterName)) {
                $unpubStmt = $db->prepare("
                    UPDATE student_results 
                    SET is_published = 0 
                    WHERE student_id = ? AND semester = ?
                ");
                $unpubStmt->execute([$targetStudentId, $semesterName]);
                $resMetrics = recalculate_student_cgpa($targetStudentId);

                log_audit('SEMESTER_RESULTS_UNPUBLISHED', "Reverted {$semesterName} results to draft for Student #{$targetStudentId}");
                flash_set('info', "Results for '{$semesterName}' have been reverted to Draft (hidden from Student Portal).");
            }
        }

        // 6. Process Student Result Request (Approve or Reject)
        elseif ($action === 'process_request') {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $newStatus = in_array($_POST['status'] ?? '', ['Approved', 'Rejected', 'In Review']) ? $_POST['status'] : 'In Review';
            $adminNotes = trim($_POST['admin_notes'] ?? '');

            if ($requestId > 0) {
                $reqStmt = $db->prepare("SELECT * FROM result_requests WHERE id = ?");
                $reqStmt->execute([$requestId]);
                $reqData = $reqStmt->fetch(PDO::FETCH_ASSOC);

                if ($reqData) {
                    $updReq = $db->prepare("
                        UPDATE result_requests 
                        SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW() 
                        WHERE id = ?
                    ");
                    $updReq->execute([$newStatus, $adminNotes, $adminId, $requestId]);

                    // Send student notification
                    $msg = "Your result inquiry #{$requestId} regarding '{$reqData['subject']}' has been marked as '{$newStatus}'.";
                    if (!empty($adminNotes)) {
                        $msg .= " Controller Office Note: {$adminNotes}";
                    }
                    create_student_notification(
                        (int)$reqData['student_id'],
                        "Result Request Status: {$newStatus}",
                        $msg,
                        'request_update',
                        $requestId
                    );

                    log_audit('RESULT_REQUEST_PROCESSED', "Processed result request #{$requestId} (Status: {$newStatus}) for Student #{$reqData['student_id']}");
                    flash_set('success', "Result request #{$requestId} updated to '{$newStatus}' and student notified.");
                }
            }
            header("Location: " . APP_URL . "/admin/results-manager.php?tab=requests");
            exit;
        }

        // 7. Populate Standard RMU 8-Semester Curriculum
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
                    (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, is_published, published_at, published_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Semester Final Examination', 1, NOW(), ?)
                ");

                foreach ($curriculum as $c) {
                    $grade = calculate_grade_from_marks($c[5]);
                    $insStmt->execute([
                        $targetStudentId, $c[0], $c[1], $c[2], $c[3], $c[4], $c[5],
                        $grade['letter_grade'], $grade['grade_point'], $adminId
                    ]);
                }

                $resMetrics = recalculate_student_cgpa($targetStudentId);
                $db->prepare("UPDATE students SET current_year = '4th Year', current_semester = '8th Semester (Completed)', status = 'Graduated' WHERE id = ?")->execute([$targetStudentId]);

                log_audit('ALL_8_SEMESTERS_POPULATED', "Populated complete 8 semesters for Student #{$targetStudentId}");
                flash_set('success', "All 8 Semesters (32 subjects) populated successfully with marks and credit-weighted CGPA {$resMetrics['cgpa']}!");
            }
        }

        // Redirect preserving student and tab
        header("Location: " . APP_URL . "/admin/results-manager.php?tab={$activeTab}&student_id=" . $targetStudentId);
        exit;
    }
}

// -------------------------------------------------------------------------
// Query Student Directory with Filters (ID, Name, Department, Session)
// -------------------------------------------------------------------------
$searchQuery = trim($_GET['search'] ?? '');
$deptFilter = trim($_GET['department'] ?? '');
$sessionFilter = trim($_GET['session'] ?? '');

$studentSql = "
    SELECT s.id, s.student_id, s.full_name, s.batch, s.cgpa, s.current_semester, p.title_en AS program_title, d.name_en AS dept_name
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE 1=1
";
$studentParams = [];

if (!empty($searchQuery)) {
    $studentSql .= " AND (s.student_id LIKE ? OR s.full_name LIKE ? OR s.email LIKE ?)";
    $studentParams[] = "%{$searchQuery}%";
    $studentParams[] = "%{$searchQuery}%";
    $studentParams[] = "%{$searchQuery}%";
}
if (!empty($deptFilter)) {
    $studentSql .= " AND d.name_en = ?";
    $studentParams[] = $deptFilter;
}
if (!empty($sessionFilter)) {
    $studentSql .= " AND s.batch = ?";
    $studentParams[] = $sessionFilter;
}

$studentSql .= " ORDER BY s.student_id ASC";
$studentStmt = $db->prepare($studentSql);
$studentStmt->execute($studentParams);
$allStudents = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct departments & sessions for filter dropdowns
$availableDepts = $db->query("SELECT DISTINCT name_en FROM departments WHERE name_en IS NOT NULL ORDER BY name_en ASC")->fetchAll(PDO::FETCH_COLUMN);
$availableSessions = $db->query("SELECT DISTINCT batch FROM students WHERE batch IS NOT NULL ORDER BY batch ASC")->fetchAll(PDO::FETCH_COLUMN);

// Determine selected student
$selectedStudentId = (int)($_GET['student_id'] ?? 0);
if ($selectedStudentId <= 0 && !empty($allStudents)) {
    // Default to first student in list
    $selectedStudentId = (int)$allStudents[0]['id'];
}

// Fetch academic summary for selected student
$summary = null;
if ($selectedStudentId > 0) {
    // Admin sees all results (including drafts)
    $summary = get_student_academic_summary($selectedStudentId, false);
}

// Result Requests query
$pendingRequestsCount = (int)$db->query("SELECT COUNT(*) FROM result_requests WHERE status = 'Pending'")->fetchColumn();
$requestsStmt = $db->query("
    SELECT r.*, s.student_id AS st_code, s.full_name AS st_name, p.title_en AS program_name, d.name_en AS dept_name
    FROM result_requests r
    JOIN students s ON r.student_id = s.id
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    ORDER BY r.id DESC
");
$allRequests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

// Result Modification History query
$historyStmt = $db->query("
    SELECT h.*, u.full_name AS admin_name, u.username AS admin_user, s.student_id AS st_code, s.full_name AS st_name
    FROM result_modification_history h
    JOIN users u ON h.admin_id = u.id
    JOIN students s ON h.student_id = s.id
    ORDER BY h.id DESC LIMIT 100
");
$allHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Standard 8 semesters array
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

<!-- Top Header with Actions -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem;">
      <i class="fas fa-award" style="color:var(--rmu-primary);"></i> 8-Semester Results &amp; CGPA Management
    </h2>
    <p style="color:#64748b; font-size:0.88rem; margin:0;">
      Official Rabindra Maitree University 8-Semester Examination Evaluation, Grade Publishing, and Student Inquiry Management
    </p>
  </div>

  <div style="display:flex; gap:0.6rem; flex-wrap:wrap; align-items:center;">
    <?php if ($selectedStudentId > 0 && !empty($summary['student'])): ?>
      <form action="" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Re-populate or reset standard RMU 8-semester curriculum for this student? Existing records will be updated.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="populate_all_8_semesters">
        <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
        <button type="submit" class="btn-primary" style="background:#0f766e; padding:0.55rem 1rem; font-size:0.86rem; text-decoration:none; border:none; cursor:pointer;">
          <i class="fas fa-magic"></i> Populate Standard RMU 8-Semester Curriculum
        </button>
      </form>
      <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $selectedStudentId ?>&semester=all" target="_blank" class="btn-primary" style="background:#15803d; padding:0.55rem 1.1rem; font-size:0.86rem; text-decoration:none;">
        <i class="fas fa-file-pdf"></i> Official Transcript PDF
      </a>
      <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= $selectedStudentId ?>" class="btn-primary" style="background:#0284c7; padding:0.55rem 1.1rem; font-size:0.86rem; text-decoration:none;">
        <i class="fas fa-user-graduate"></i> Complete Profile
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Primary Tabs Navigation -->
<div style="display:flex; gap:0.5rem; border-bottom:2px solid #e2e8f0; margin-bottom:1.75rem; overflow-x:auto;">
  <a href="?tab=editor<?= $selectedStudentId ? '&student_id='.$selectedStudentId : '' ?>" style="padding:0.75rem 1.4rem; text-decoration:none; font-weight:700; font-size:0.92rem; color:<?= ($activeTab === 'editor') ? 'var(--rmu-primary)' : '#64748b' ?>; border-bottom:3px solid <?= ($activeTab === 'editor') ? 'var(--rmu-primary)' : 'transparent' ?>; display:inline-flex; align-items:center; gap:8px;">
    <i class="fas fa-edit"></i> 8-Semester Result Editor
  </a>
  <a href="?tab=requests<?= $selectedStudentId ? '&student_id='.$selectedStudentId : '' ?>" style="padding:0.75rem 1.4rem; text-decoration:none; font-weight:700; font-size:0.92rem; color:<?= ($activeTab === 'requests') ? 'var(--rmu-primary)' : '#64748b' ?>; border-bottom:3px solid <?= ($activeTab === 'requests') ? 'var(--rmu-primary)' : 'transparent' ?>; display:inline-flex; align-items:center; gap:8px;">
    <i class="fas fa-inbox"></i> Student Result Requests
    <?php if ($pendingRequestsCount > 0): ?>
      <span style="background:#ef4444; color:#fff; font-size:0.72rem; padding:2px 7px; border-radius:12px; font-weight:800;"><?= $pendingRequestsCount ?> Pending</span>
    <?php endif; ?>
  </a>
  <a href="?tab=history<?= $selectedStudentId ? '&student_id='.$selectedStudentId : '' ?>" style="padding:0.75rem 1.4rem; text-decoration:none; font-weight:700; font-size:0.92rem; color:<?= ($activeTab === 'history') ? 'var(--rmu-primary)' : '#64748b' ?>; border-bottom:3px solid <?= ($activeTab === 'history') ? 'var(--rmu-primary)' : 'transparent' ?>; display:inline-flex; align-items:center; gap:8px;">
    <i class="fas fa-history"></i> Modification Audit Trail
  </a>
</div>

<!-- ========================================================================= -->
<!-- TAB 1: 8-SEMESTER STUDENT RESULT EDITOR                                    -->
<!-- ========================================================================= -->
<?php if ($activeTab === 'editor'): ?>

  <!-- Search & Filter Bar -->
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem 1.5rem; margin-bottom:2rem; box-shadow:0 1px 4px rgba(0,0,0,0.04);">
    <form method="GET" action="" style="display:grid; grid-template-columns:1.5fr 1fr 1fr auto; gap:1rem; align-items:flex-end;">
      <input type="hidden" name="tab" value="editor">

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" style="font-weight:700; font-size:0.82rem; color:#0f2c59;">
          <i class="fas fa-search"></i> Search by ID / Name:
        </label>
        <input type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="e.g. RMU2601001 or Tanvir" class="form-control" style="font-size:0.88rem;">
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" style="font-weight:700; font-size:0.82rem; color:#0f2c59;">
          <i class="fas fa-layer-group"></i> Department:
        </label>
        <select name="department" class="form-select" style="font-size:0.88rem;">
          <option value="">All Departments</option>
          <?php foreach ($availableDepts as $dName): ?>
            <option value="<?= e($dName) ?>" <?= ($deptFilter === $dName) ? 'selected' : '' ?>><?= e($dName) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" style="font-weight:700; font-size:0.82rem; color:#0f2c59;">
          <i class="fas fa-calendar-alt"></i> Academic Session:
        </label>
        <select name="session" class="form-select" style="font-size:0.88rem;">
          <option value="">All Sessions</option>
          <?php foreach ($availableSessions as $sName): ?>
            <option value="<?= e($sName) ?>" <?= ($sessionFilter === $sName) ? 'selected' : '' ?>><?= e($sName) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn-primary" style="padding:0.65rem 1.25rem; font-size:0.88rem; white-space:nowrap;">
        <i class="fas fa-filter"></i> Filter Students
      </button>
    </form>

    <!-- Student Quick Select Dropdown -->
    <div style="margin-top:1rem; padding-top:1rem; border-top:1px dashed #e2e8f0; display:flex; align-items:center; gap:1rem;">
      <label style="font-size:0.84rem; font-weight:700; color:#334155; white-space:nowrap;">Select / Search Student (<?= count($allStudents) ?> found):</label>
      <form method="GET" action="" style="flex:1; margin:0;">
        <input type="hidden" name="tab" value="editor">
        <select name="student_id" class="form-select" onchange="this.form.submit()" style="font-size:0.9rem; font-weight:600; width:100%;">
          <?php foreach ($allStudents as $st): ?>
            <option value="<?= $st['id'] ?>" <?= ($st['id'] == $selectedStudentId) ? 'selected' : '' ?>>
              <?= e($st['student_id']) ?> — <?= e($st['full_name']) ?> (<?= e($st['dept_name'] ?? 'N/A') ?> &bull; Session: <?= e($st['batch']) ?> &bull; CGPA: <?= number_format((float)$st['cgpa'], 2) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if ($summary && !empty($summary['student'])): ?>
    <?php $stObj = $summary['student']; ?>

    <!-- Student Academic Profile Banner (Section 2) -->
    <div style="background:linear-gradient(135deg, #0f2c59 0%, #1e3a8a 100%); color:#ffffff; border-radius:12px; padding:1.75rem 2rem; margin-bottom:2rem; box-shadow:0 4px 12px rgba(15, 44, 89, 0.15); display:grid; grid-template-columns:auto 1fr auto; gap:2rem; align-items:center;">
      <div style="width:78px; height:78px; border-radius:50%; background:#e0f2fe; color:#0f2c59; display:flex; align-items:center; justify-content:center; font-size:2rem; font-weight:800; border:3px solid #60a5fa;">
        <?= strtoupper(substr($stObj['full_name'], 0, 1)) ?>
      </div>

      <div>
        <h3 style="margin:0 0 0.25rem; font-size:1.45rem; font-weight:800; color:#ffffff;">
          <?= e($stObj['full_name']) ?> <span style="font-family:monospace; color:#93c5fd; font-size:1.15rem;">(<?= e($stObj['student_id']) ?>)</span>
        </h3>
        <div style="font-size:0.88rem; color:#cbd5e1; margin-bottom:0.4rem;">
          <strong>Department:</strong> <?= e($stObj['department_name'] ?? 'N/A') ?> &bull; 
          <strong>Program:</strong> <?= e($stObj['program_name'] ?? 'N/A') ?> &bull; 
          <strong>Faculty:</strong> <?= e($stObj['faculty_name'] ?? 'N/A') ?>
        </div>
        <div style="font-size:0.82rem; color:#94a3b8; display:flex; gap:1.2rem; flex-wrap:wrap;">
          <span><i class="fas fa-layer-group"></i> Academic Session: <strong><?= e($stObj['batch']) ?></strong></span>
          <span><i class="fas fa-calendar-check"></i> Current: <strong><?= e($stObj['current_semester']) ?></strong></span>
          <span><i class="fas fa-user-tag"></i> Status: <strong style="color:<?= ($stObj['status'] === 'Graduated') ? '#86efac' : '#38bdf8' ?>;"><?= e($stObj['status']) ?></strong></span>
        </div>
      </div>

      <div style="text-align:right; border-left:1px solid rgba(255,255,255,0.2); padding-left:2rem;">
        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:#93c5fd; font-weight:700;">Cumulative CGPA</div>
        <div style="font-size:2.4rem; font-weight:900; color:#4ade80; line-height:1.1;">
          <?= number_format((float)$summary['final_cgpa'], 2) ?>
        </div>
        <div style="font-size:0.82rem; color:#cbd5e1; margin-top:3px;">
          <?= (int)$summary['total_earned_credits'] ?> / <?= (int)$stObj['total_credits_required'] ?> Credits Completed
        </div>
      </div>
    </div>

    <!-- 8-Semester SGPA Progression Overview Strip (Section 3 & 4) -->
    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; margin-bottom:2rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.85rem; flex-wrap:wrap; gap:0.5rem;">
        <h4 style="margin:0; font-size:0.95rem; font-weight:800; color:#0f2c59; text-transform:uppercase; letter-spacing:0.5px;">
          <i class="fas fa-chart-line" style="color:var(--rmu-primary);"></i> 8-Semester SGPA Progression Overview &amp; Completion Status
        </h4>
        <span style="font-size:0.82rem; color:#15803d; font-weight:700;">
          Credit-Weighted Cumulative CGPA: <?= number_format((float)$summary['final_cgpa'], 2) ?> / 4.00
        </span>
      </div>
      <div style="display:grid; grid-template-columns:repeat(8, 1fr); gap:0.6rem; text-align:center;">
        <?php for ($sem = 1; $sem <= 8; $sem++): ?>
          <?php 
            $hasSem = isset($summary['semester_gpas'][$sem]); 
            $sgpaVal = $hasSem ? $summary['semester_gpas'][$sem] : null;
          ?>
          <div style="background:<?= $hasSem ? '#f0fdf4' : '#f8fafc' ?>; border:1.5px solid <?= $hasSem ? '#86efac' : '#e2e8f0' ?>; border-radius:8px; padding:0.6rem 0.3rem;">
            <div style="font-size:0.72rem; font-weight:700; color:<?= $hasSem ? '#166534' : '#94a3b8' ?>;">Sem <?= $sem ?></div>
            <div style="font-size:1.15rem; font-weight:800; color:<?= $hasSem ? '#15803d' : '#cbd5e1' ?>; margin:2px 0;">
              <?= $hasSem ? number_format((float)$sgpaVal, 2) : '—' ?>
            </div>
            <div style="font-size:0.65rem; font-weight:700; color:<?= $hasSem ? '#15803d' : '#64748b' ?>;">
              <?= $hasSem ? '✓ Completed' : 'Pending' ?>
            </div>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- 2-Column Workspace: 8 Semesters Result Blocks (Left) + Add Result Form (Right) -->
    <div style="display:grid; grid-template-columns:2.1fr 1fr; gap:2rem; align-items:flex-start;">
      
      <!-- Left Column: Individual 8-Semester Managers -->
      <div>
        <?php if (!empty($summary['semesters'])): ?>
          <?php foreach ($summary['semesters'] as $semName => $sData): ?>
            <?php 
              $allCoursesPublished = true;
              foreach ($sData['courses'] as $c) {
                  if (!$c['is_published']) { $allCoursesPublished = false; break; }
              }
              $pubDate = !empty($sData['courses'][0]['published_at']) ? format_date($sData['courses'][0]['published_at']) : null;
            ?>
            <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; margin-bottom:2rem; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05);">
              
              <!-- Semester Header with Metrics & Actions -->
              <div style="background:linear-gradient(135deg, #0f2c59 0%, #1e3a8a 100%); color:#ffffff; padding:0.9rem 1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
                <div style="display:flex; align-items:center; gap:0.6rem;">
                  <span style="background:#f59e0b; color:#0f172a; padding:2px 9px; border-radius:4px; font-weight:900; font-size:0.85rem;">
                    <?= $sData['semester_number'] ?>
                  </span>
                  <strong style="font-size:1rem;"><?= e($semName) ?></strong>
                </div>

                <div style="display:flex; align-items:center; gap:0.6rem; font-size:0.82rem; flex-wrap:wrap;">
                  <span style="background:rgba(255,255,255,0.18); padding:3px 8px; border-radius:4px;">
                    Reg: <strong><?= number_format($sData['registered_credits'], 1) ?> Cr</strong>
                  </span>
                  <span style="background:rgba(255,255,255,0.22); padding:3px 8px; border-radius:4px;">
                    SGPA: <strong style="font-size:0.95rem; color:#86efac;"><?= number_format($sData['sgpa'], 2) ?></strong>
                  </span>
                  <span style="background:#15803d; color:#ffffff; padding:3px 9px; border-radius:4px; font-weight:700;">
                    CGPA: <?= number_format($sData['cumulative_cgpa'], 2) ?>
                  </span>

                  <!-- Prominent Publish Result Button (Section 6) -->
                  <?php if (!$allCoursesPublished): ?>
                    <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Publish all results for <?= e($semName) ?> to the Student Portal? Students will immediately see their grades, marks, and SGPA.');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="publish_semester">
                      <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                      <input type="hidden" name="semester_name" value="<?= e($semName) ?>">
                      <button type="submit" style="background:#22c55e; color:#ffffff; border:none; padding:4px 12px; border-radius:4px; font-size:0.78rem; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 6px rgba(34,197,94,0.35);">
                        <i class="fas fa-check-circle"></i> Publish Result
                      </button>
                    </form>
                  <?php else: ?>
                    <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Revert <?= e($semName) ?> results to Draft? Results will be hidden from the Student Portal.');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="unpublish_semester">
                      <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                      <input type="hidden" name="semester_name" value="<?= e($semName) ?>">
                      <button type="submit" style="background:rgba(255,255,255,0.2); color:#ffffff; border:1px solid rgba(255,255,255,0.4); padding:3px 9px; border-radius:4px; font-size:0.75rem; font-weight:600; cursor:pointer;" title="Click to unpublish">
                        <i class="fas fa-eye-slash"></i> Published <?= $pubDate ? "({$pubDate})" : '' ?>
                      </button>
                    </form>
                  <?php endif; ?>

                  <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $selectedStudentId ?>&semester=<?= urlencode($semName) ?>" target="_blank" style="background:#ffffff; color:#0f2c59; border-radius:4px; padding:3px 9px; font-size:0.76rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                    <i class="fas fa-file-pdf" style="color:#dc2626;"></i> PDF
                  </a>
                </div>
              </div>

              <!-- Subject-wise Table with All Required Fields (Section 2) -->
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
                      <th style="text-align:right; width:110px;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($sData['courses'] as $co): ?>
                      <tr>
                        <td>
                          <strong style="color:var(--rmu-primary); font-family:monospace;"><?= e($co['course_code']) ?></strong>
                          <div style="font-weight:600; color:#1e293b;"><?= e($co['course_name']) ?></div>
                          <small style="color:#64748b;"><?= e($co['exam_term']) ?></small>
                          <?php if (!empty($co['correction_reason'])): ?>
                            <div style="font-size:0.72rem; color:#b45309; margin-top:2px;"><i class="fas fa-info-circle"></i> <?= e($co['correction_reason']) ?></div>
                          <?php endif; ?>
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
                        <td style="text-align:right; white-space:nowrap;">
                          <!-- Edit Result Button with Reason capture -->
                          <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode([
                            'id' => (int)$co['id'],
                            'semester' => $semName,
                            'code' => $co['course_code'],
                            'name' => $co['course_name'],
                            'credit' => (float)$co['credit'],
                            'full_marks' => (int)$co['full_marks'],
                            'marks' => $co['marks'] !== null ? (float)$co['marks'] : '',
                            'letter_grade' => $co['letter_grade'],
                            'grade_point' => (float)$co['grade_point'],
                            'exam_term' => $co['exam_term'],
                            'is_published' => (int)$co['is_published'],
                            'reason' => $co['correction_reason'] ?? ''
                          ]), ENT_QUOTES, 'UTF-8') ?>)" style="border:none; background:#eff6ff; color:#0284c7; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:0.82rem; margin-right:4px;" title="Edit Subject Result &amp; Correct Marks">
                            <i class="fas fa-edit"></i> Edit
                          </button>

                          <!-- Delete Result Form -->
                          <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Delete result for <?= e($co['course_code']) ?>?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_result">
                            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                            <input type="hidden" name="result_id" value="<?= $co['id'] ?>">
                            <button type="submit" style="border:none; background:#fee2e2; color:#ef4444; padding:4px 7px; border-radius:4px; cursor:pointer; font-size:0.82rem;" title="Delete Course Result">
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
            <h3 style="color:#0f172a; margin:0 0 0.5rem;">No academic results recorded yet.</h3>
            <p style="color:#64748b; font-size:0.9rem; margin-bottom:1.5rem;">
              You can populate the full standard RMU 8-semester curriculum or add individual subject results using the form on the right.
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
              <label class="form-label">Subject Name *</label>
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

            <!-- Obtained Marks with Live Auto-Grading (Section 4) -->
            <div style="background:#f0fdf4; border:1.5px solid #86efac; border-radius:8px; padding:1rem; margin-bottom:1rem;">
              <label class="form-label" style="color:#166534; font-weight:700;">
                Obtained Marks (out of 100) *
              </label>
              <input type="number" step="0.5" min="0" max="100" name="marks" id="marksInput" placeholder="e.g. 85" class="form-control" required style="font-size:1.1rem; font-weight:800; color:#0f2c59;" oninput="autoCalculateGrade(this.value)">
              <small style="color:#15803d; font-size:0.78rem; display:block; margin-top:3px;">
                UGC uniform scale: 80+ = A+ (4.00), 75+ = A (3.75), 70+ = A- (3.50)...
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
              <label class="form-label">Examination Term</label>
              <input type="text" name="exam_term" value="Semester Final Examination" class="form-control">
            </div>

            <div class="form-group">
              <label class="form-label">Entry / Correction Reason</label>
              <input type="text" name="reason" value="Initial semester examination marks entry" class="form-control" placeholder="Reason for this record">
            </div>

            <div style="margin-bottom:1.25rem;">
              <label style="display:inline-flex; align-items:center; gap:0.5rem; font-size:0.88rem; font-weight:600; cursor:pointer;">
                <input type="checkbox" name="is_published" value="1" checked style="width:17px; height:17px;">
                <span>Publish immediately to student portal</span>
              </label>
            </div>

            <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:0.75rem;">
              <i class="fas fa-check-circle"></i> Save Subject &amp; Recalculate CGPA
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

<!-- ========================================================================= -->
<!-- TAB 2: STUDENT RESULT INQUIRIES & UPDATE REQUESTS (Section 5)              -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'requests'): ?>

  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05); margin-bottom:2rem;">
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div>
        <h3 style="margin:0 0 0.2rem; font-size:1.15rem; color:#0f2c59; font-weight:800;">
          <i class="fas fa-inbox" style="color:var(--rmu-primary);"></i> Student Result Inquiries &amp; Correction Requests
        </h3>
        <p style="margin:0; font-size:0.84rem; color:#64748b;">
          Review and process grade re-check, missing marks, and transcript requests submitted by students.
        </p>
      </div>
      <div>
        <span style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; font-size:0.8rem; font-weight:700; padding:4px 10px; border-radius:20px;">
          Total Requests: <?= count($allRequests) ?>
        </span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.88rem;">
        <thead>
          <tr>
            <th style="width:60px;">ID</th>
            <th>Student Details</th>
            <th>Semester &bull; Course</th>
            <th>Request Type</th>
            <th>Subject &bull; Description</th>
            <th>Date</th>
            <th>Status</th>
            <th style="text-align:right; width:140px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allRequests)): ?>
            <tr>
              <td colspan="8" style="text-align:center; padding:3rem; color:#64748b;">
                <i class="fas fa-check-circle" style="font-size:2.5rem; color:#86efac; margin-bottom:0.75rem; display:block;"></i>
                No pending result requests at this time.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($allRequests as $req): ?>
              <tr>
                <td><strong style="color:#0f2c59;">#<?= $req['id'] ?></strong></td>
                <td>
                  <strong><?= e($req['st_name']) ?></strong><br>
                  <span style="font-family:monospace; color:#0284c7; font-size:0.8rem; font-weight:700;"><?= e($req['st_code']) ?></span><br>
                  <small style="color:#64748b;"><?= e($req['dept_name']) ?></small>
                </td>
                <td>
                  <strong><?= e($req['semester']) ?></strong>
                  <?php if (!empty($req['course_code'])): ?>
                    <br><span style="font-family:monospace; color:var(--rmu-primary); font-size:0.82rem; font-weight:700;"><?= e($req['course_code']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="background:#f1f5f9; color:#334155; font-size:0.75rem; font-weight:700; padding:2px 7px; border-radius:4px; border:1px solid #cbd5e1;">
                    <?= e($req['request_type']) ?>
                  </span>
                </td>
                <td>
                  <strong style="color:#0f172a; font-size:0.86rem; display:block;"><?= e($req['subject']) ?></strong>
                  <p style="margin:2px 0 0; font-size:0.8rem; color:#475569; max-width:280px; line-height:1.4;">
                    <?= e($req['description']) ?>
                  </p>
                  <?php if (!empty($req['admin_notes'])): ?>
                    <div style="font-size:0.75rem; color:#15803d; margin-top:3px; background:#f0fdf4; padding:2px 6px; border-radius:4px;">
                      <strong>Note:</strong> <?= e($req['admin_notes']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="font-size:0.8rem; color:#64748b; white-space:nowrap;">
                  <?= format_date($req['created_at']) ?>
                </td>
                <td>
                  <?php if ($req['status'] === 'Approved'): ?>
                    <span style="background:#dcfce7; color:#15803d; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:12px;">
                      <i class="fas fa-check-circle"></i> Approved
                    </span>
                  <?php elseif ($req['status'] === 'Rejected'): ?>
                    <span style="background:#fee2e2; color:#b91c1c; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:12px;">
                      <i class="fas fa-times-circle"></i> Rejected
                    </span>
                  <?php elseif ($req['status'] === 'In Review'): ?>
                    <span style="background:#e0f2fe; color:#0369a1; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:12px;">
                      <i class="fas fa-spinner"></i> In Review
                    </span>
                  <?php else: ?>
                    <span style="background:#fef3c7; color:#b45309; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:12px;">
                      <i class="fas fa-clock"></i> Pending
                    </span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <button type="button" onclick="openProcessRequestModal(<?= htmlspecialchars(json_encode([
                    'id' => (int)$req['id'],
                    'student_id' => (int)$req['student_id'],
                    'st_name' => $req['st_name'],
                    'st_code' => $req['st_code'],
                    'semester' => $req['semester'],
                    'course' => $req['course_code'],
                    'type' => $req['request_type'],
                    'subject' => $req['subject'],
                    'desc' => $req['description'],
                    'status' => $req['status'],
                    'notes' => $req['admin_notes'] ?? ''
                  ]), ENT_QUOTES, 'UTF-8') ?>)" class="btn-primary" style="padding:4px 10px; font-size:0.78rem; background:#0284c7;">
                    <i class="fas fa-cogs"></i> Process
                  </button>
                  <a href="?tab=editor&student_id=<?= (int)$req['student_id'] ?>" class="btn-primary" style="padding:4px 8px; font-size:0.78rem; background:#475569; text-decoration:none;" title="Open in Result Editor">
                    <i class="fas fa-external-link-alt"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<!-- ========================================================================= -->
<!-- TAB 3: RESULT MODIFICATION HISTORY & AUDIT TRAIL (Section 8)               -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'history'): ?>

  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05); margin-bottom:2rem;">
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div>
        <h3 style="margin:0 0 0.2rem; font-size:1.15rem; color:#0f2c59; font-weight:800;">
          <i class="fas fa-history" style="color:var(--rmu-primary);"></i> Result Modification History &amp; Audit Trail
        </h3>
        <p style="margin:0; font-size:0.84rem; color:#64748b;">
          Immutable record of every grade edit, mark change, and subject deletion authorized by university administrators.
        </p>
      </div>
      <div>
        <span style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:0.8rem; font-weight:700; padding:4px 10px; border-radius:20px;">
          <?= count($allHistory) ?> Audit Logs
        </span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.86rem;">
        <thead>
          <tr>
            <th style="width:130px;">Timestamp</th>
            <th>Authorizing Admin</th>
            <th>Student Details</th>
            <th>Course &bull; Semester</th>
            <th>Field Changed</th>
            <th>Previous Value</th>
            <th>Updated Value</th>
            <th>Reason for Correction</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allHistory)): ?>
            <tr>
              <td colspan="8" style="text-align:center; padding:3rem; color:#64748b;">
                No result modifications recorded in history yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($allHistory as $h): ?>
              <tr>
                <td style="font-size:0.8rem; color:#64748b; white-space:nowrap;">
                  <?= date('M d, Y h:i A', strtotime($h['created_at'])) ?>
                </td>
                <td>
                  <strong style="color:#0f2c59;"><?= e($h['admin_name']) ?></strong><br>
                  <small style="color:#64748b;">@<?= e($h['admin_user']) ?></small>
                </td>
                <td>
                  <strong><?= e($h['st_name']) ?></strong><br>
                  <span style="font-family:monospace; color:#0284c7; font-weight:700; font-size:0.8rem;"><?= e($h['st_code']) ?></span>
                </td>
                <td>
                  <strong style="color:var(--rmu-primary); font-family:monospace;"><?= e($h['course_code']) ?></strong><br>
                  <small style="color:#64748b;"><?= e($h['semester']) ?></small>
                </td>
                <td>
                  <span style="background:#eff6ff; color:#1e40af; font-size:0.75rem; font-weight:700; padding:2px 7px; border-radius:4px;">
                    <?= e($h['field_name']) ?>
                  </span>
                </td>
                <td style="color:#64748b; font-family:monospace; font-size:0.82rem;">
                  <?= e($h['old_value'] ?? '—') ?>
                </td>
                <td style="color:#15803d; font-family:monospace; font-weight:700; font-size:0.82rem;">
                  <?= e($h['new_value']) ?>
                </td>
                <td style="color:#334155; font-size:0.82rem; max-width:240px;">
                  <i class="fas fa-quote-left" style="color:#cbd5e1; font-size:0.7rem;"></i> <?= e($h['reason']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- MODAL: EDIT SUBJECT RESULT & CAPTURE REASON                                -->
<!-- ========================================================================= -->
<div id="editResultModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; justify-content:center; align-items:center; padding:1.5rem; overflow-y:auto;">
  <div style="background:#ffffff; border-radius:12px; max-width:560px; width:100%; padding:2rem; box-shadow:0 20px 40px rgba(0,0,0,0.2); position:relative;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.2rem; color:#0f2c59; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-edit" style="color:var(--rmu-primary);"></i> Edit Marks &amp; Result Correction
      </h3>
      <button type="button" onclick="closeEditModal()" style="border:none; background:none; font-size:1.25rem; color:#64748b; cursor:pointer;" title="Close">&times;</button>
    </div>

    <form action="" method="POST" id="editResultForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit_result">
      <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
      <input type="hidden" name="result_id" id="editResultId">

      <div class="form-group">
        <label class="form-label">Semester *</label>
        <select name="semester" id="editSemester" class="form-select" required>
          <?php foreach ($standardSemesters as $semOption): ?>
            <option value="<?= e($semOption) ?>"><?= e($semOption) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 2fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Course Code *</label>
          <input type="text" name="course_code" id="editCourseCode" class="form-control" required style="text-transform:uppercase;">
        </div>
        <div class="form-group">
          <label class="form-label">Course Title *</label>
          <input type="text" name="course_name" id="editCourseName" class="form-control" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Credit Hours *</label>
          <input type="number" step="0.5" min="0.5" max="6.0" name="credit" id="editCredit" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Full Marks</label>
          <input type="number" name="full_marks" id="editFullMarks" value="100" class="form-control" readonly style="background:#f1f5f9;">
        </div>
      </div>

      <!-- Obtained Marks with Auto-Grading in Edit Modal -->
      <div style="background:#f0fdf4; border:1.5px solid #86efac; border-radius:8px; padding:1rem; margin-bottom:1rem;">
        <label class="form-label" style="color:#166534; font-weight:700;">
          Obtained Marks (out of 100) *
        </label>
        <input type="number" step="0.5" min="0" max="100" name="marks" id="editMarksInput" class="form-control" required style="font-size:1.15rem; font-weight:800; color:#0f2c59;" oninput="autoCalculateEditGrade(this.value)">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-top:0.75rem;">
          <div>
            <label class="form-label" style="font-size:0.8rem; color:#475569;">Letter Grade</label>
            <input type="text" name="letter_grade" id="editGradeOutput" class="form-control" readonly style="font-weight:800; text-align:center; background:#ffffff; color:#1e40af;">
          </div>
          <div>
            <label class="form-label" style="font-size:0.8rem; color:#475569;">Grade Point</label>
            <input type="text" name="grade_point" id="editPointOutput" class="form-control" readonly style="font-weight:800; text-align:center; background:#ffffff; color:#15803d;">
          </div>
        </div>
      </div>

      <!-- Mandatory Reason for Correction (Section 8) -->
      <div class="form-group">
        <label class="form-label" style="color:#b45309; font-weight:700;">
          <i class="fas fa-clipboard-check"></i> Reason for Manual Grade / Mark Correction *
        </label>
        <input type="text" name="correction_reason" id="editReason" class="form-control" required placeholder="e.g. Scrutiny re-check approved by Exam Controller" style="border:1.5px solid #f59e0b;">
        <small style="color:#64748b; font-size:0.78rem;">This reason will be permanently archived in the Result Modification Audit Trail.</small>
      </div>

      <div class="form-group">
        <label class="form-label">Exam Term</label>
        <input type="text" name="exam_term" id="editExamTerm" value="Semester Final Examination" class="form-control">
      </div>

      <div class="form-group" style="margin-bottom:1.5rem;">
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600; color:#0f172a;">
          <input type="checkbox" name="is_published" id="editIsPublished" value="1" style="width:16px; height:16px;">
          <span>Published (Visible in student grade sheet &amp; transcripts)</span>
        </label>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" onclick="closeEditModal()" class="btn-primary" style="background:#e2e8f0; color:#334155; border:none; padding:0.6rem 1.25rem;">
          Cancel
        </button>
        <button type="submit" class="btn-primary" style="background:#1e73be; padding:0.6rem 1.5rem;">
          <i class="fas fa-save"></i> Save &amp; Log Modification
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: PROCESS STUDENT RESULT REQUEST (Section 5)                          -->
<!-- ========================================================================= -->
<div id="processRequestModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; justify-content:center; align-items:center; padding:1.5rem; overflow-y:auto;">
  <div style="background:#ffffff; border-radius:12px; max-width:540px; width:100%; padding:2rem; box-shadow:0 20px 40px rgba(0,0,0,0.2); position:relative;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.2rem; color:#0f2c59; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-clipboard-check" style="color:var(--rmu-primary);"></i> Process Result Request
      </h3>
      <button type="button" onclick="closeProcessRequestModal()" style="border:none; background:none; font-size:1.25rem; color:#64748b; cursor:pointer;" title="Close">&times;</button>
    </div>

    <form action="" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="process_request">
      <input type="hidden" name="request_id" id="procRequestId">

      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1rem; margin-bottom:1.25rem;">
        <div style="font-size:0.85rem; color:#475569; margin-bottom:4px;">
          Student: <strong id="procStudentName" style="color:#0f2c59;"></strong> (<span id="procStudentCode" style="font-family:monospace;"></span>)
        </div>
        <div style="font-size:0.85rem; color:#475569; margin-bottom:4px;">
          Semester / Course: <strong id="procSemester"></strong> &bull; <strong id="procCourse" style="color:var(--rmu-primary);"></strong>
        </div>
        <div style="font-size:0.85rem; color:#475569; margin-bottom:4px;">
          Request Type: <span id="procType" style="font-weight:700; color:#0284c7;"></span>
        </div>
        <div style="margin-top:6px; padding-top:6px; border-top:1px dashed #cbd5e1; font-size:0.82rem; color:#334155;">
          <strong>Student Description:</strong>
          <p id="procDesc" style="margin:2px 0 0; color:#475569; line-height:1.4;"></p>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" style="font-weight:700;">Update Status *</label>
        <select name="status" id="procStatus" class="form-select" required style="font-weight:700;">
          <option value="Approved">Approved (Grant Correction / Review)</option>
          <option value="Rejected">Rejected (Declined after Scrutiny)</option>
          <option value="In Review">In Review (Under Examination Board)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Controller Office Feedback &bull; Notes to Student</label>
        <textarea name="admin_notes" id="procNotes" rows="3" class="form-control" placeholder="Provide feedback notes to the student regarding this request..."></textarea>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" onclick="closeProcessRequestModal()" class="btn-primary" style="background:#e2e8f0; color:#334155; border:none; padding:0.6rem 1.25rem;">
          Cancel
        </button>
        <button type="submit" class="btn-primary" style="background:#16a34a; padding:0.6rem 1.5rem;">
          <i class="fas fa-check-circle"></i> Save &amp; Notify Student
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Auto-Grading Javascript Scripts -->
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

  if (m >= 80) { gradeEl.value = 'A+'; pointEl.value = '4.00'; }
  else if (m >= 75) { gradeEl.value = 'A'; pointEl.value = '3.75'; }
  else if (m >= 70) { gradeEl.value = 'A-'; pointEl.value = '3.50'; }
  else if (m >= 65) { gradeEl.value = 'B+'; pointEl.value = '3.25'; }
  else if (m >= 60) { gradeEl.value = 'B'; pointEl.value = '3.00'; }
  else if (m >= 55) { gradeEl.value = 'B-'; pointEl.value = '2.75'; }
  else if (m >= 50) { gradeEl.value = 'C+'; pointEl.value = '2.50'; }
  else if (m >= 45) { gradeEl.value = 'C'; pointEl.value = '2.25'; }
  else if (m >= 40) { gradeEl.value = 'D'; pointEl.value = '2.00'; }
  else { gradeEl.value = 'F'; pointEl.value = '0.00'; }
}

function autoCalculateEditGrade(marksVal) {
  var m = parseFloat(marksVal);
  var gradeEl = document.getElementById('editGradeOutput');
  var pointEl = document.getElementById('editPointOutput');

  if (isNaN(m) || m < 0) {
    gradeEl.value = '';
    pointEl.value = '';
    return;
  }

  if (m >= 80) { gradeEl.value = 'A+'; pointEl.value = '4.00'; }
  else if (m >= 75) { gradeEl.value = 'A'; pointEl.value = '3.75'; }
  else if (m >= 70) { gradeEl.value = 'A-'; pointEl.value = '3.50'; }
  else if (m >= 65) { gradeEl.value = 'B+'; pointEl.value = '3.25'; }
  else if (m >= 60) { gradeEl.value = 'B'; pointEl.value = '3.00'; }
  else if (m >= 55) { gradeEl.value = 'B-'; pointEl.value = '2.75'; }
  else if (m >= 50) { gradeEl.value = 'C+'; pointEl.value = '2.50'; }
  else if (m >= 45) { gradeEl.value = 'C'; pointEl.value = '2.25'; }
  else if (m >= 40) { gradeEl.value = 'D'; pointEl.value = '2.00'; }
  else { gradeEl.value = 'F'; pointEl.value = '0.00'; }
}

function openEditModal(data) {
  document.getElementById('editResultId').value = data.id;
  document.getElementById('editSemester').value = data.semester;
  document.getElementById('editCourseCode').value = data.code;
  document.getElementById('editCourseName').value = data.name;
  document.getElementById('editCredit').value = data.credit;
  document.getElementById('editFullMarks').value = data.full_marks || 100;
  document.getElementById('editMarksInput').value = data.marks !== '' ? data.marks : '';
  document.getElementById('editGradeOutput').value = data.letter_grade;
  document.getElementById('editPointOutput').value = parseFloat(data.grade_point).toFixed(2);
  document.getElementById('editReason').value = data.reason || 'Administrative grade verification & update';
  document.getElementById('editExamTerm').value = data.exam_term || 'Semester Final Examination';
  document.getElementById('editIsPublished').checked = (parseInt(data.is_published) === 1);

  var modal = document.getElementById('editResultModal');
  modal.style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('editResultModal').style.display = 'none';
}

function openProcessRequestModal(data) {
  document.getElementById('procRequestId').value = data.id;
  document.getElementById('procStudentName').textContent = data.st_name;
  document.getElementById('procStudentCode').textContent = data.st_code;
  document.getElementById('procSemester').textContent = data.semester;
  document.getElementById('procCourse').textContent = data.course || 'All Subjects';
  document.getElementById('procType').textContent = data.type;
  document.getElementById('procDesc').textContent = data.desc;
  document.getElementById('procStatus').value = data.status || 'Approved';
  document.getElementById('procNotes').value = data.notes || '';

  var modal = document.getElementById('processRequestModal');
  modal.style.display = 'flex';
}

function closeProcessRequestModal() {
  document.getElementById('processRequestModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
  var editModal = document.getElementById('editResultModal');
  if (e.target === editModal) {
    closeEditModal();
  }
  var procModal = document.getElementById('processRequestModal');
  if (e.target === procModal) {
    closeProcessRequestModal();
  }
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
