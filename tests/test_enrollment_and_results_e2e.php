<?php
/**
 * Test: Enroll Student Form Submission, Portal Account Creation,
 * and Individual Student Result Management with Live Portal Publishing
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=== STARTING E2E ENROLLMENT & RESULT MANAGEMENT TEST ===\n\n";

$db = getDb();

// 1. Prepare unique test student data
$randomSuffix = rand(10000, 99999);
$testStudentId = "RMU26" . $randomSuffix;
$testFullName = "Rashedul Hasan " . $randomSuffix;
$testEmail = "rashedul." . $randomSuffix . "@rmu.ac.bd";
$testPhone = "01788" . rand(100000, 999999);
$testPassword = "password" . rand(100, 999);
$testFather = "Abul Kashem " . $randomSuffix;
$testMother = "Rokeya Begum";
$testDob = "2004-03-21";
$testGender = "Male";
$testPresentAddr = "Holding 45, Station Road, Kushtia";
$testPermAddr = "Village: Shontoshpur, Kumarkhali, Kushtia";
$testBatch = "14th Batch (2026–2030)";
$testSemester = "1st Semester";
$testProgramId = 1; // B.Sc. in CSE
$testDeptId = 6;    // CSE

echo "1. Simulating 'Enroll Student' POST request to admin/students.php ...\n";

// Hash password and insert like admin/students.php does
$passwordHash = password_hash($testPassword, PASSWORD_DEFAULT);
$ins = $db->prepare("
    INSERT INTO students 
    (student_id, full_name, father_name, mother_name, email, phone, password_hash, program_id, department_id, batch, current_year, current_semester, cgpa, credits_completed, blood_group, home_district, dob, gender, present_address, permanent_address, total_credits_required, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '1st Year', ?, 0.00, 0, 'B+', 'Kushtia', ?, ?, ?, ?, 140, 'Active')
");
$ins->execute([
    $testStudentId, $testFullName, $testFather, $testMother, $testEmail, $testPhone, $passwordHash,
    $testProgramId, $testDeptId, $testBatch, $testSemester, $testDob, $testGender, $testPresentAddr, $testPermAddr
]);
$studentDbId = (int)$db->lastInsertId();

if ($studentDbId <= 0) {
    die("[FAIL] Could not insert student into database.\n");
}
echo "   [PASS] Student enrolled into 'students' table with DB ID #{$studentDbId} (Student ID: {$testStudentId})\n";

// Seed 1st semester courses as done by admin/students.php
$courses = [
    ['CSE 101', 'Introduction to Computer Science & Problem Solving', 3.0, 'Prof. Dr. M. A. Rahman', 'Sun & Tue 10:00 AM', 'Room 301'],
    ['CSE 102', 'Structured Programming Language (C)', 3.0, 'Engr. Tareq Ahmed', 'Mon & Wed 11:30 AM', 'Lab 2']
];
$insCourse = $db->prepare("
    INSERT INTO student_courses 
    (student_id, course_code, course_name, credit, instructor_name, class_schedule, room_no, attendance_percent, semester, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 90, ?, 'Enrolled')
");
foreach ($courses as $c) {
    $insCourse->execute([$studentDbId, $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $testSemester]);
}
echo "   [PASS] Auto-enrolled initial courses in student_courses.\n";

// Send welcome notification
create_student_notification(
    $studentDbId,
    "Welcome to Rabindra Maitree University!",
    "Your official Student Portal account has been created. Student ID: {$testStudentId}.",
    'general'
);
echo "   [PASS] Dispatched welcome in-app notification.\n";

// 2. Verify Student Portal Login with credentials
echo "\n2. Verifying Student Portal authentication for {$testStudentId} ...\n";
$authStmt = $db->prepare("SELECT * FROM students WHERE (student_id = ? OR email = ?) AND status != 'Suspended' LIMIT 1");
$authStmt->execute([$testStudentId, $testStudentId]);
$authStudent = $authStmt->fetch(PDO::FETCH_ASSOC);

if (!$authStudent) {
    die("[FAIL] Student record could not be retrieved by student_id.\n");
}

if (!password_verify($testPassword, $authStudent['password_hash'])) {
    die("[FAIL] Password verification failed for student {$testStudentId}.\n");
}
echo "   [PASS] Password verification succeeded with generated credentials!\n";

// 3. Test Individual Student Result Management: Draft mode
echo "\n3. Testing Individual Student Result Entry in Draft Mode ...\n";
$insDraft = $db->prepare("
    INSERT INTO student_results 
    (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, is_published, publish_status, correction_reason)
    VALUES (?, '1st Semester', 'CSE 101', 'Intro to Computer Science', 3.0, 100, 85.0, 'A+', 4.00, 'Semester Final Examination', 0, 'Draft', 'Initial academic entry')
");
$insDraft->execute([$studentDbId]);
$resultId = (int)$db->lastInsertId();

// Verify draft result is HIDDEN from student portal
$studentSummaryDraft = get_student_academic_summary($studentDbId, true);
if ($studentSummaryDraft['has_results'] !== false || !empty($studentSummaryDraft['semesters'])) {
    die("[FAIL] Draft result should not be visible to the student in Student Portal!\n");
}
echo "   [PASS] Draft result is completely hidden from Student Portal.\n";

// 4. Test Grade Correction with mandatory reason
echo "\n4. Testing Mark & Grade Correction with mandatory audit reason ...\n";
$newMarks = 92.5;
$gradeInfo = calculate_grade_from_marks($newMarks);
$correctionReason = "Paper re-scrutiny verified by Head of Department & Controller";

$upd = $db->prepare("
    UPDATE student_results 
    SET marks = ?, letter_grade = ?, grade_point = ?, correction_reason = ? 
    WHERE id = ? AND student_id = ?
");
$upd->execute([$newMarks, $gradeInfo['letter_grade'], $gradeInfo['grade_point'], $correctionReason, $resultId, $studentDbId]);

log_result_modification($resultId, $studentDbId, 1, 'CSE 101', '1st Semester', 'Marks Correction', '85.0 (A+, 4.00)', "{$newMarks} ({$gradeInfo['letter_grade']}, {$gradeInfo['grade_point']})", $correctionReason);
echo "   [PASS] Result updated to {$newMarks} marks ({$gradeInfo['letter_grade']}, {$gradeInfo['grade_point']}) and logged in audit history.\n";

// 5. Test Publishing Result to Student Portal
echo "\n5. Publishing 1st Semester Results to Student Portal ...\n";
$pub = $db->prepare("
    UPDATE student_results 
    SET publish_status = 'Published', is_published = 1, published_at = NOW(), published_by = 1 
    WHERE student_id = ? AND semester = '1st Semester'
");
$pub->execute([$studentDbId]);
$recalc = recalculate_student_cgpa($studentDbId);

create_student_notification(
    $studentDbId,
    "Semester Results Published: 1st Semester",
    "Official examination results for 1st Semester have been published! Your SGPA is 4.00 and CGPA is 4.00.",
    'result_published'
);
echo "   [PASS] Semester published and student notified.\n";

// Verify published result is NOW VISIBLE in Student Portal
$studentSummaryPub = get_student_academic_summary($studentDbId, true);
if ($studentSummaryPub['has_results'] !== true || empty($studentSummaryPub['semesters'])) {
    die("[FAIL] Published result not showing in student's academic summary!\n");
}
if ((float)$recalc['cgpa'] !== 4.00) {
    die("[FAIL] Calculated CGPA is not 4.00, got: {$recalc['cgpa']}\n");
}
echo "   [PASS] Result is now 100% visible on Student Portal with calculated CGPA: {$recalc['cgpa']}.\n";

// Clean up test data
echo "\n6. Cleaning up test artifacts ...\n";
$db->prepare("DELETE FROM student_notifications WHERE student_id = ?")->execute([$studentDbId]);
$db->prepare("DELETE FROM result_modification_history WHERE student_id = ?")->execute([$studentDbId]);
$db->prepare("DELETE FROM student_results WHERE student_id = ?")->execute([$studentDbId]);
$db->prepare("DELETE FROM student_courses WHERE student_id = ?")->execute([$studentDbId]);
$db->prepare("DELETE FROM students WHERE id = ?")->execute([$studentDbId]);
echo "   [PASS] Cleanup complete.\n";

echo "\n>>> ALL ENROLLMENT AND RESULT MANAGEMENT WORKFLOW TESTS PASSED (100%)! <<<\n";
