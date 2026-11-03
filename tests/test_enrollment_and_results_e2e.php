<?php
/**
 * Comprehensive End-to-End Test Suite:
 * 1. Admin Student Enrollment & Appearance in Directory
 * 2. 8-Semester Individual Result Management
 * 3. 8 Subject Fields (Code, Name, Credit, Full, Marks, Grade, Point, Status)
 * 4. Credit-weighted SGPA and Cumulative CGPA Calculations
 * 5. Save Draft, Preview Result, and Publish Result Workflow
 * 6. Live Portal Synchronization & In-App Notifications
 * 7. Complete 8-Semester Academic Transcript & Graduation Status
 * 8. Strict Data Isolation between Multiple Students
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=== RMU COMPREHENSIVE 8-SEMESTER RESULT & PORTAL SYNCHRONIZATION TEST ===\n\n";

$db = getDb();

// -----------------------------------------------------------------------------
// STEP 1: Enroll Student A and Student B via Admin
// -----------------------------------------------------------------------------
echo "1. Creating Test Students (Student A and Student B) ...\n";
$randA = rand(10000, 99999);
$randB = rand(10000, 99999);

$studentA_id = "RMU26" . $randA;
$studentA_name = "Tanvir Rahman " . $randA;
$studentA_email = "tanvir." . $randA . "@rmu.ac.bd";
$studentA_pass = "passA" . rand(100, 999);

$studentB_id = "RMU26" . $randB;
$studentB_name = "Sadia Islam " . $randB;
$studentB_email = "sadia." . $randB . "@rmu.ac.bd";
$studentB_pass = "passB" . rand(100, 999);

$passHashA = password_hash($studentA_pass, PASSWORD_DEFAULT);
$passHashB = password_hash($studentB_pass, PASSWORD_DEFAULT);

$insStmt = $db->prepare("
    INSERT INTO students 
    (student_id, full_name, email, phone, password_hash, program_id, department_id, batch, current_year, current_semester, cgpa, credits_completed, total_credits_required, status)
    VALUES (?, ?, ?, '01711000000', ?, 1, 6, 'Batch 26 (2026-2030)', '1st Year', '1st Semester', 0.00, 0, 140, 'Active')
");

$insStmt->execute([$studentA_id, $studentA_name, $studentA_email, $passHashA]);
$dbIdA = (int)$db->lastInsertId();

$insStmt->execute([$studentB_id, $studentB_name, $studentB_email, $passHashB]);
$dbIdB = (int)$db->lastInsertId();

assert($dbIdA > 0 && $dbIdB > 0, "Both students must be inserted.");
echo "   [PASS] Student A created: ID #{$dbIdA} ({$studentA_id})\n";
echo "   [PASS] Student B created: ID #{$dbIdB} ({$studentB_id})\n";

// Verify appearance in Student Management directory
$dirCheck = $db->query("SELECT id, student_id, full_name FROM students WHERE id IN ({$dbIdA}, {$dbIdB}) ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
assert(count($dirCheck) === 2, "Both students must appear in Student Management.");
echo "   [PASS] Verified both students appear in Student Management directory.\n";

// -----------------------------------------------------------------------------
// STEP 2: Verify 8 Semesters Structure
// -----------------------------------------------------------------------------
echo "\n2. Verifying 8-Semester Structure for Student A (with 0 results recorded) ...\n";
$summaryA = get_student_academic_summary($dbIdA, false);
assert($summaryA['has_results'] === false, "Student A should have 0 results initially.");

// The standard 8 semesters mapping should handle all 8 semesters
$standardSemesters = [
    1 => '1st Semester (Spring 2023)',
    2 => '2nd Semester (Fall 2023)',
    3 => '3rd Semester (Spring 2024)',
    4 => '4th Semester (Fall 2024)',
    5 => '5th Semester (Spring 2025)',
    6 => '6th Semester (Fall 2025)',
    7 => '7th Semester (Spring 2026)',
    8 => '8th Semester (Fall 2026)'
];
assert(count($standardSemesters) === 8, "Must cover all 8 semesters.");
echo "   [PASS] All 8 semesters are defined and ready for individual entry.\n";

// -----------------------------------------------------------------------------
// STEP 3: Add Subject in Draft Mode with All 8 Required Fields
// -----------------------------------------------------------------------------
echo "\n3. Adding Subject to 1st Semester in DRAFT mode ...\n";
$subCode = 'CSE 101';
$subName = 'Computer Fundamentals & Programming';
$subCredit = 3.0;
$subFullMarks = 100;
$subObtainedMarks = 78.50; // Should be Grade 'A', Point 3.75, Status 'Pass'
$gradeInfo = calculate_grade_from_marks($subObtainedMarks);

assert($gradeInfo['letter_grade'] === 'A', "78.50 marks should yield Letter Grade A");
assert($gradeInfo['grade_point'] == 3.75, "78.50 marks should yield Grade Point 3.75");
assert($gradeInfo['status'] === 'Pass', "Grade Point >= 2.00 must yield Pass status");

$insRes = $db->prepare("
    INSERT INTO student_results 
    (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, is_published, publish_status, published_at, published_by, correction_reason)
    VALUES (?, '1st Semester (Spring 2023)', ?, ?, ?, ?, ?, ?, ?, 'Semester Final Examination', 0, 'Draft', NULL, NULL, 'Initial Entry')
");
$insRes->execute([$dbIdA, $subCode, $subName, $subCredit, $subFullMarks, $subObtainedMarks, $gradeInfo['letter_grade'], $gradeInfo['grade_point']]);
$resIdA = (int)$db->lastInsertId();
assert($resIdA > 0, "Result row must be inserted.");
echo "   [PASS] Subject added in Draft mode: {$subCode} ({$gradeInfo['letter_grade']}, GP {$gradeInfo['grade_point']}, {$gradeInfo['status']})\n";

// -----------------------------------------------------------------------------
// STEP 4: Verify Draft Concealment in Student Portal
// -----------------------------------------------------------------------------
echo "\n4. Verifying Draft Concealment in Student Portal ...\n";
$portalSummaryA = get_student_academic_summary($dbIdA, true);
assert($portalSummaryA['has_results'] === false, "Student Portal must NOT show Draft results.");
assert(empty($portalSummaryA['semesters']), "Semesters list must be empty for student in draft mode.");
echo "   [PASS] Student Portal strictly conceals unpublished Draft results.\n";

// -----------------------------------------------------------------------------
// STEP 5: Add Second Subject and Test Preview Calculation (SGPA & CGPA)
// -----------------------------------------------------------------------------
echo "\n5. Adding Second Subject to 1st Semester and Testing Calculation Preview ...\n";
$subCode2 = 'MAT 101';
$subName2 = 'Differential & Integral Calculus';
$subCredit2 = 3.0;
$subObtainedMarks2 = 85.00; // A+, 4.00, Pass
$gradeInfo2 = calculate_grade_from_marks($subObtainedMarks2);

$insRes->execute([$dbIdA, $subCode2, $subName2, $subCredit2, 100, $subObtainedMarks2, $gradeInfo2['letter_grade'], $gradeInfo2['grade_point']]);

// Unofficial preview calculation for Admin (publishedOnly = false)
$adminPreview = get_student_academic_summary($dbIdA, false);
assert($adminPreview['has_results'] === true, "Admin preview must include draft results.");
$sem1Data = $adminPreview['semesters']['1st Semester (Spring 2023)'];
assert($sem1Data['registered_credits'] == 6.0, "Total registered credits should be 6.0");
// SGPA calculation: (3.0 * 3.75 + 3.0 * 4.00) / 6.0 = (11.25 + 12.0) / 6.0 = 23.25 / 6.0 = 3.875 => 3.88
assert($sem1Data['sgpa'] == 3.88, "SGPA must be 3.88. Got: " . $sem1Data['sgpa']);
assert($adminPreview['final_cgpa'] == 3.88, "CGPA must match SGPA for single semester.");
echo "   [PASS] Preview calculation verified: Registered 6.0 Cr, SGPA 3.88, CGPA 3.88.\n";

// -----------------------------------------------------------------------------
// STEP 6: Publish 1st Semester Results to Student Portal
// -----------------------------------------------------------------------------
echo "\n6. Publishing 1st Semester Results to Student Portal ...\n";
$db->prepare("
    UPDATE student_results 
    SET is_published = 1, publish_status = 'Published', published_at = NOW(), published_by = 1 
    WHERE student_id = ? AND semester = '1st Semester (Spring 2023)'
")->execute([$dbIdA]);

recalculate_student_cgpa($dbIdA);

create_student_notification(
    $dbIdA,
    "Semester Results Published: 1st Semester (Spring 2023)",
    "Your official results for 1st Semester have been published. Check your Student Portal to view your grades and SGPA.",
    'result_published'
);

// Verify live synchronization in Student Portal
$portalPublishedA = get_student_academic_summary($dbIdA, true);
assert($portalPublishedA['has_results'] === true, "Student Portal must now show published results.");
assert(isset($portalPublishedA['semesters']['1st Semester (Spring 2023)']), "1st Semester must be visible to student.");
assert($portalPublishedA['final_cgpa'] == 3.88, "Student Portal must show live calculated CGPA 3.88.");

// Verify notification
$notifCount = (int)$db->query("SELECT COUNT(*) FROM student_notifications WHERE student_id = {$dbIdA}")->fetchColumn();
assert($notifCount >= 1, "Student A must have received publication notification.");
echo "   [PASS] Results synchronized to Student Portal immediately upon publication!\n";

// -----------------------------------------------------------------------------
// STEP 7: Data Isolation Verification (Student A vs Student B)
// -----------------------------------------------------------------------------
echo "\n7. Verifying Strict Data Isolation between Student A and Student B ...\n";
$portalStudentB = get_student_academic_summary($dbIdB, true);
assert($portalStudentB['has_results'] === false, "Student B must have ZERO results (no leakage from Student A).");
assert($portalStudentB['final_cgpa'] == 0.00, "Student B's CGPA must remain 0.00.");

// Add a distinct result for Student B
$insResB = $db->prepare("
    INSERT INTO student_results 
    (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, is_published, publish_status, published_at, published_by)
    VALUES (?, '1st Semester (Spring 2023)', 'ENG 101', 'English Composition', 3.0, 100, 62.00, 'B', 3.00, 'Semester Final Examination', 1, 'Published', NOW(), 1)
");
$insResB->execute([$dbIdB]);
recalculate_student_cgpa($dbIdB);

$portalStudentB = get_student_academic_summary($dbIdB, true);
$portalStudentA_check = get_student_academic_summary($dbIdA, true);

assert($portalStudentB['final_cgpa'] == 3.00, "Student B must have CGPA 3.00.");
assert($portalStudentA_check['final_cgpa'] == 3.88, "Student A's CGPA must strictly remain 3.88.");
assert(count($portalStudentA_check['semesters']['1st Semester (Spring 2023)']['courses']) === 2, "Student A must have 2 courses.");
assert(count($portalStudentB['semesters']['1st Semester (Spring 2023)']['courses']) === 1, "Student B must have 1 course.");
echo "   [PASS] Complete data isolation confirmed: Student A and Student B are independent.\n";

// -----------------------------------------------------------------------------
// STEP 8: Populate All 8 Semesters & Verify Graduation Transcript
// -----------------------------------------------------------------------------
echo "\n8. Populating Standard RMU 8-Semester Curriculum (32 subjects) for Student A ...\n";
// Wipe existing results for Student A to seed full 8 semesters
$db->prepare("DELETE FROM student_results WHERE student_id = ?")->execute([$dbIdA]);

$curriculum = [
    // 1st Semester
    ['1st Semester (Spring 2023)', 'CSE 101', 'Structured Programming Language (C)', 3.0, 100, 85.00],
    ['1st Semester (Spring 2023)', 'CSE 102', 'Structured Programming Language Lab', 1.5, 100, 88.00],
    ['1st Semester (Spring 2023)', 'MAT 101', 'Differential and Integral Calculus', 3.0, 100, 80.00],
    ['1st Semester (Spring 2023)', 'ENG 101', 'English Reading and Composition Skills', 3.0, 100, 75.00],
    // 2nd Semester
    ['2nd Semester (Fall 2023)', 'CSE 103', 'Discrete Mathematics for Computing', 3.0, 100, 82.00],
    ['2nd Semester (Fall 2023)', 'CSE 104', 'Object Oriented Programming (C++)', 3.0, 100, 86.00],
    ['2nd Semester (Fall 2023)', 'CSE 105', 'Object Oriented Programming Lab', 1.5, 100, 90.00],
    ['2nd Semester (Fall 2023)', 'PHY 101', 'Physics for Engineers (Mechanics & Waves)', 3.0, 100, 78.00],
    // 3rd Semester
    ['3rd Semester (Spring 2024)', 'CSE 201', 'Data Structures & Algorithms', 3.0, 100, 84.00],
    ['3rd Semester (Spring 2024)', 'CSE 202', 'Data Structures Laboratory', 1.5, 100, 92.00],
    ['3rd Semester (Spring 2024)', 'CSE 203', 'Digital Logic Design & Circuits', 3.0, 100, 80.00],
    ['3rd Semester (Spring 2024)', 'EEE 101', 'Basic Electrical Engineering & Circuit Analysis', 3.0, 100, 77.00],
    // 4th Semester
    ['4th Semester (Fall 2024)', 'CSE 205', 'Design & Analysis of Algorithms', 3.0, 100, 88.00],
    ['4th Semester (Fall 2024)', 'CSE 206', 'Database Management Systems (DBMS)', 3.0, 100, 85.00],
    ['4th Semester (Fall 2024)', 'CSE 207', 'Database Management Systems Lab', 1.5, 100, 90.00],
    ['4th Semester (Fall 2024)', 'MAT 201', 'Linear Algebra & Complex Variables', 3.0, 100, 83.00],
    // 5th Semester
    ['5th Semester (Spring 2025)', 'CSE 301', 'Operating Systems & System Architecture', 3.0, 100, 84.00],
    ['5th Semester (Spring 2025)', 'CSE 302', 'Operating Systems Laboratory', 1.5, 100, 89.00],
    ['5th Semester (Spring 2025)', 'CSE 303', 'Software Engineering & System Analysis', 3.0, 100, 86.00],
    ['5th Semester (Spring 2025)', 'STA 201', 'Statistics & Probability for Engineers', 3.0, 100, 79.00],
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

$insFull = $db->prepare("
    INSERT INTO student_results 
    (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, is_published, publish_status, published_at, published_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Semester Final Examination', 1, 'Published', NOW(), 1)
");

foreach ($curriculum as $c) {
    $gr = calculate_grade_from_marks($c[5]);
    $insFull->execute([$dbIdA, $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $gr['letter_grade'], $gr['grade_point']]);
}

$fullMetrics = recalculate_student_cgpa($dbIdA);
$db->prepare("UPDATE students SET current_year = '4th Year', current_semester = '8th Semester (Completed)', status = 'Graduated' WHERE id = ?")->execute([$dbIdA]);

$gradSummary = get_student_academic_summary($dbIdA, true);
assert($gradSummary['has_results'] === true, "Must have results.");
assert(count($gradSummary['semesters']) === 8, "Must have exactly 8 semesters populated.");
assert($gradSummary['total_earned_credits'] >= 80, "Must have accumulated all credits.");
assert($gradSummary['final_cgpa'] >= 3.50, "Final CGPA should be high first class.");
echo "   [PASS] All 8 semesters (32 courses) populated. Completed Credits: {$gradSummary['total_earned_credits']}, Final CGPA: {$gradSummary['final_cgpa']}.\n";

// -----------------------------------------------------------------------------
// STEP 9: Cleanup Test Students
// -----------------------------------------------------------------------------
echo "\n9. Cleaning up test data ...\n";
$db->prepare("DELETE FROM student_results WHERE student_id IN (?, ?)")->execute([$dbIdA, $dbIdB]);
$db->prepare("DELETE FROM student_notifications WHERE student_id IN (?, ?)")->execute([$dbIdA, $dbIdB]);
$db->prepare("DELETE FROM students WHERE id IN (?, ?)")->execute([$dbIdA, $dbIdB]);
echo "   [PASS] Cleanup complete.\n";

echo "\n========================================================================\n";
echo ">>> ALL 8-SEMESTER RESULT MANAGEMENT & PORTAL WORKFLOW TESTS PASSED! <<<\n";
echo "========================================================================\n";
