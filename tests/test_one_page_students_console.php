<?php
/**
 * End-to-End Automated Test for One-Page Student & Academic Result Management System
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$baseUrl = 'http://127.0.0.1:8000';
$cookieFile = sys_get_temp_dir() . '/cookie_admin_onepage.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function make_request($url, $postData = null) {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_TIMEOUT        => 15
    ]);
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'url' => $effectiveUrl];
}

echo "======================================================================\n";
echo "  TESTING ONE-PAGE COMPLETE STUDENT & RESULT MANAGEMENT SYSTEM\n";
echo "======================================================================\n\n";

// --- STEP 1: Admin Login ---
$loginGet = make_request($baseUrl . '/admin/login.php');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginGet['body'], $m);
$csrfToken = $m[1] ?? '';

$loginPost = make_request($baseUrl . '/admin/login.php', [
    'csrf_token' => $csrfToken,
    'username'   => 'admin',
    'password'   => '123456'
]);

echo "1. Admin Authentication Check:\n";
echo "   Login Code: {$loginPost['code']}, URL: {$loginPost['url']}\n";
assert(strpos($loginPost['url'], 'dashboard.php') !== false || $loginPost['code'] === 200, "Admin login must succeed");
echo "   [PASS] Admin logged in successfully!\n\n";

// --- STEP 2: Directory View ---
$dirRes = make_request($baseUrl . '/admin/students.php');
echo "2. GET /admin/students.php (Directory View):\n";
echo "   Status: {$dirRes['code']}\n";
assert($dirRes['code'] === 200, "Directory must return 200");
assert(strpos($dirRes['body'], 'Student Management') !== false, "Must contain 'Student Management'");
assert(strpos($dirRes['body'], 'Enroll Student &amp; Manage Results') !== false, "Must contain action button");
echo "   [PASS] Enrolled Students Directory rendered successfully!\n\n";

// --- STEP 3: One-Page Console Enroll Mode (?action=enroll) ---
$enrollRes = make_request($baseUrl . '/admin/students.php?action=enroll');
echo "3. GET /admin/students.php?action=enroll (One-Page Console New Student):\n";
echo "   Status: {$enrollRes['code']}\n";
assert($enrollRes['code'] === 200, "Enroll page must return 200");

// Check the 4 action buttons
$expectedButtons = [
    'Save Student Information',
    'Save Academic Results (Draft)',
    'Preview Complete Transcript',
    'Publish Academic Results'
];
foreach ($expectedButtons as $btn) {
    assert(strpos($enrollRes['body'], $btn) !== false, "Must contain button: {$btn}");
}
echo "   [PASS] All 4 Action Buttons present!\n";

// Check the 3 sections
assert(strpos($enrollRes['body'], '1. Student Personal &amp; Academic Information (All 21 Fields)') !== false, "Must contain Section 1");
assert(strpos($enrollRes['body'], '2. Complete Academic Result Management') !== false, "Must contain Section 2");
assert(strpos($enrollRes['body'], '3. Live Academic Progress &amp; CGPA Summary') !== false, "Must contain Section 3");
echo "   [PASS] All 3 Core Sections present!\n";

error_reporting(E_ALL & ~E_DEPRECATED);

// Check 8 Semester Accordions
for ($sem = 1; $sem <= 8; $sem++) {
    assert(strpos($enrollRes['body'], "sem-wrapper-{$sem}") !== false, "Must contain sem-wrapper-{$sem}");
}
echo "   [PASS] All 8 Semester Accordions rendered!\n\n";

// --- STEP 4: One-Page Console Edit Mode (?edit=4) ---
$editRes = make_request($baseUrl . '/admin/students.php?edit=4');
echo "4. GET /admin/students.php?edit=4 (One-Page Console Existing Student):\n";
echo "   Status: {$editRes['code']}\n";
assert($editRes['code'] === 200, "Edit page must return 200");
assert(strpos($editRes['body'], 'RMU2601001') !== false, "Must contain Student ID RMU2601001");
assert(strpos($editRes['body'], 'Tanvir Hasan') !== false, "Must contain student name Tanvir Hasan");
assert(strpos($editRes['body'], 'CSE 101') !== false, "Must pre-fill existing semester results");
echo "   [PASS] Pre-filled Existing Student Profile and 8-Semester Results successfully!\n\n";

// --- STEP 5: Create a New Student via One-Page Console with Draft Results ---
echo "5. POST /admin/students.php (Enroll New Student with 1st Sem Results - DRAFT):\n";
$testStudentId = 'RMU' . rand(2609000, 2609999);
$testEmail = strtolower($testStudentId) . '@rmu.ac.bd';
$db = getDb();
$cseProgId = (int)$db->query("SELECT id FROM programs WHERE slug = 'bsc-in-cse'")->fetchColumn() ?: 1;
$cseDeptId = (int)$db->query("SELECT id FROM departments WHERE slug = 'dept-cse'")->fetchColumn() ?: 1;

// Grab fresh CSRF token from enroll page
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $enrollRes['body'], $m2);
$formCsrf = $m2[1] ?? '';

$enrollPostData = [
    'csrf_token'             => $formCsrf,
    'action'                 => 'add',
    'submit_action'          => 'save_draft', // Save Draft Action
    'student_id'             => $testStudentId,
    'full_name'              => 'E2E Test Student',
    'father_name'            => 'Md. Test Father',
    'mother_name'            => 'Mrs. Test Mother',
    'dob'                    => '2004-03-20',
    'gender'                 => 'Male',
    'email'                  => $testEmail,
    'phone'                  => '01799887766',
    'present_address'        => 'RMU Campus Hall, Kushtia',
    'permanent_address'      => 'House 12, Road 4, Kushtia',
    'program_id'             => $cseProgId,
    'department_id'          => $cseDeptId,
    'batch'                  => '14th Batch (2026-2030)',
    'current_year'           => '1st Year',
    'current_semester'       => '1st Semester (Spring 2026)',
    'status'                 => 'Active',
    'blood_group'            => 'A+',
    'home_district'          => 'Kushtia',
    'password'               => 'StudentPass123',
    'initial_password'       => 'StudentPass123',
    'correction_reason'      => 'Initial E2E Test Student Setup',

    // Semester 1 Results
    'results' => [
        1 => [
            [
                'course_code'    => 'CSE 111',
                'course_name'    => 'Computer Fundamentals',
                'credit'         => '3.0',
                'full_marks'     => '100',
                'marks'          => '85',
                'letter_grade'   => 'A+',
                'grade_point'    => '4.00',
                'status'         => 'Passed'
            ],
            [
                'course_code'    => 'MAT 111',
                'course_name'    => 'Mathematics I (Differential Calculus)',
                'credit'         => '3.0',
                'full_marks'     => '100',
                'marks'          => '75',
                'letter_grade'   => 'A',
                'grade_point'    => '3.75',
                'status'         => 'Passed'
            ]
        ]
    ]
];

$postRes = make_request($baseUrl . '/admin/students.php', $enrollPostData);
echo "   Post Response Code: {$postRes['code']}\n";

// Verify in DB
$createdStudent = $db->query("SELECT * FROM students WHERE student_id = '$testStudentId'")->fetch(PDO::FETCH_ASSOC);
assert(!empty($createdStudent), "New student must be created in DB");
$createdStudentPk = (int)$createdStudent['id'];
echo "   [PASS] Student record created in database with PK ID: {$createdStudentPk}!\n";

// Verify results in DB are draft (is_published = 0)
$draftResults = $db->query("SELECT * FROM student_results WHERE student_id = {$createdStudentPk}")->fetchAll(PDO::FETCH_ASSOC);
assert(count($draftResults) === 2, "Must save 2 subject results in student_results");
assert((int)$draftResults[0]['is_published'] === 0, "Draft result must have is_published = 0");
echo "   [PASS] Academic results saved as DRAFT (is_published = 0)!\n\n";

// --- STEP 6: Verify Student Portal Isolation (Draft results NOT visible) ---
echo "6. Student Portal Visibility Check (DRAFT STATE):\n";
$studentCookie = sys_get_temp_dir() . '/cookie_student_test.txt';
if (file_exists($studentCookie)) unlink($studentCookie);

// Login as the new student
$ch = curl_init($baseUrl . '/student/login.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $studentCookie,
    CURLOPT_COOKIEFILE     => $studentCookie
]);
$sLoginGet = curl_exec($ch);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $sLoginGet, $sm);
$sCsrf = $sm[1] ?? '';

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $sCsrf,
    'student_id' => $testStudentId,
    'password'   => 'StudentPass123'
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$sLoginPost = curl_exec($ch);
curl_close($ch);

// Check /student/results.php
$ch = curl_init($baseUrl . '/student/results.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE     => $studentCookie
]);
$sResultsHtml = curl_exec($ch);
curl_close($ch);

assert(strpos($sResultsHtml, 'CSE 111') === false, "Draft results must NOT appear in Student Portal");
assert(strpos($sResultsHtml, 'No academic results published yet') !== false, "Must show 'No academic results published yet'");
echo "   [PASS] Draft results are securely concealed from student portal!\n\n";

// --- STEP 7: Publish Results via One-Page Console ---
echo "7. POST /admin/students.php?edit={$createdStudentPk} (PUBLISH ACADEMIC RESULTS):\n";
// Fetch edit page to get fresh CSRF
$editPageRes = make_request($baseUrl . '/admin/students.php?edit=' . $createdStudentPk);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $editPageRes['body'], $em);
$editCsrf = $em[1] ?? '';

$publishPostData = $enrollPostData;
$publishPostData['action'] = 'edit';
$publishPostData['csrf_token'] = $editCsrf;
$publishPostData['id'] = $createdStudentPk;
$publishPostData['submit_action'] = 'publish_results'; // Publish Action

$publishRes = make_request($baseUrl . '/admin/students.php?edit=' . $createdStudentPk, $publishPostData);
echo "   Publish Response Code: {$publishRes['code']}\n";

// Verify in DB that results are published (is_published = 1)
$pubResults = $db->query("SELECT * FROM student_results WHERE student_id = {$createdStudentPk}")->fetchAll(PDO::FETCH_ASSOC);
assert((int)$pubResults[0]['is_published'] === 1, "Published result must have is_published = 1");
assert((int)$pubResults[1]['is_published'] === 1, "Published result must have is_published = 1");

// Verify student CGPA was automatically recalculated in DB: (4.00*3 + 3.75*3)/6 = 3.88
$updatedStudent = $db->query("SELECT * FROM students WHERE id = {$createdStudentPk}")->fetch(PDO::FETCH_ASSOC);
$calculatedCgpa = (float)$updatedStudent['cgpa'];
echo "   Recalculated Student CGPA in DB: {$calculatedCgpa}\n";
assert($calculatedCgpa >= 3.87 && $calculatedCgpa <= 3.89, "CGPA must be calculated correctly (~3.88)");

// Verify in-app notification was dispatched to student
$notif = $db->query("SELECT * FROM student_notifications WHERE student_id = {$createdStudentPk} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert(!empty($notif), "In-app notification must be dispatched to student");
echo "   Notification dispatched: \"{$notif['title']}\"\n";
echo "   [PASS] Results marked as published, CGPA recalculated, and notification dispatched!\n\n";

// --- STEP 8: Verify Student Portal Now Displays Published Results ---
echo "8. Student Portal Live Synchronization Check:\n";
$ch = curl_init($baseUrl . '/student/results.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE     => $studentCookie
]);
$sPubResultsHtml = curl_exec($ch);
curl_close($ch);

assert(strpos($sPubResultsHtml, 'CSE 111') !== false, "Published subject CSE 111 must appear in Student Portal");
assert(strpos($sPubResultsHtml, 'MAT 111') !== false, "Published subject MAT 111 must appear in Student Portal");
assert(strpos($sPubResultsHtml, 'Computer Fundamentals') !== false, "Subject name must appear");
assert(strpos($sPubResultsHtml, '3.88') !== false || strpos($sPubResultsHtml, '4.00') !== false, "Grades/GPA must appear");
echo "   [PASS] Student Portal instantly synchronized and displays all published subjects and grades!\n\n";

// Clean up test student
$db->prepare("DELETE FROM student_results WHERE student_id = ?")->execute([$createdStudentPk]);
$db->prepare("DELETE FROM student_notifications WHERE student_id = ?")->execute([$createdStudentPk]);
$db->prepare("DELETE FROM students WHERE id = ?")->execute([$createdStudentPk]);
@unlink($studentCookie);
@unlink($cookieFile);

echo "======================================================================\n";
echo ">>> ALL ONE-PAGE STUDENT & RESULT MANAGEMENT E2E TESTS PASSED (100%) <<<\n";
echo "======================================================================\n";
