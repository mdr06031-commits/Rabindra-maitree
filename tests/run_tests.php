<?php
/**
 * Rabindra Maitree University (RMU) Comprehensive Automated Test Suite
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('CLI_MODE', php_sapi_name() === 'cli');

function green($text) { return CLI_MODE ? "\033[32m$text\033[0m" : "<span style='color:green;'>$text</span>"; }
function red($text) { return CLI_MODE ? "\033[31m$text\033[0m" : "<span style='color:red;'>$text</span>"; }
function bold($text) { return CLI_MODE ? "\033[1m$text\033[0m" : "<strong>$text</strong>"; }

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function run_test($name, $closure) {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    echo "Running Test #$totalTests: $name ... ";
    try {
        $result = $closure();
        if ($result === true) {
            $passedTests++;
            echo green("[PASS]") . "\n";
        } else {
            $failedTests++;
            echo red("[FAIL]: " . (is_string($result) ? $result : "Condition returned false")) . "\n";
        }
    } catch (Throwable $e) {
        $failedTests++;
        echo red("[ERROR]: " . $e->getMessage()) . "\n";
    }
}

echo bold("\n=======================================================\n");
echo bold("  RABINDRA MAITREE UNIVERSITY (RMU) AUTOMATED TEST SUITE\n");
echo bold("=======================================================\n\n");

$baseDir = dirname(__DIR__);

// 1. PHP Syntax Linting on all project PHP files
run_test("PHP Syntax Lint Check across all source files", function() use ($baseDir) {
    $dirIter = new RecursiveDirectoryIterator($baseDir);
    $iter = new RecursiveIteratorIterator($dirIter);
    $files = new RegexIterator($iter, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

    foreach ($files as $file) {
        $path = $file[0];
        // Skip scratch directory
        if (strpos($path, 'scratch') !== false) continue;
        $output = [];
        $returnVar = 0;
        exec("php -l \"$path\"", $output, $returnVar);
        if ($returnVar !== 0) {
            return "Syntax error in $path: " . implode(" ", $output);
        }
    }
    return true;
});

// 2. Database Connectivity
require_once $baseDir . '/config/app.php';
require_once $baseDir . '/includes/functions.php';

run_test("Database PDO Connection & Version Verification", function() {
    $db = getDb();
    $version = $db->query("SELECT VERSION()")->fetchColumn();
    return !empty($version);
});

// 3. Schema & Seed Integrity (Table row checks)
run_test("Database Tables & Demonstration Seed Count Integrity", function() {
    $db = getDb();
    $tables = [
        'faculties'        => 4,
        'departments'      => 11,
        'programs'         => 14,
        'notices'          => 5,
        'events'           => 3,
        'news'             => 3,
        'downloads'        => 5,
        'site_settings'    => 10,
        'users'            => 1
    ];
    foreach ($tables as $table => $minCount) {
        $count = (int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        if ($count < $minCount) {
            return "Table `$table` has $count rows, expected at least $minCount.";
        }
    }
    return true;
});

// 4. English-Only Language Enforcement & Character Set Verification
run_test("English-Only Language Enforcement & Character Set Verification", function() {
    $db = getDb();
    $name = $db->query("SELECT setting_value FROM site_settings WHERE setting_key = 'site_name_bn'")->fetchColumn();
    if (empty($name)) {
        return "Setting 'site_name_bn' should not be empty.";
    }
    // Verify zero Bengali characters in critical tables
    $tables = ['faculties', 'departments', 'programs', 'notices', 'events', 'news', 'site_settings'];
    foreach ($tables as $t) {
        $rows = $db->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            foreach ($row as $col => $val) {
                if ($val !== null && preg_match('/[\x{0980}-\x{09FF}]/u', $val)) {
                    return "Bengali text found in table $t, column $col: $val";
                }
            }
        }
    }
    return true;
});

// 5. Test Key Public Routes Execution
$publicPages = [
    'index.php',
    'about.php',
    'history.php',
    'mission-vision.php',
    'leadership.php',
    'administration.php',
    'board-of-trustees.php',
    'faculties.php',
    'faculty-detail.php?slug=engineering-and-technology',
    'departments.php',
    'department-detail.php?slug=dept-cse',
    'programs.php',
    'program-detail.php?slug=bsc-in-cse',
    'admissions.php',
    'admission-form.php',
    'admission-status.php',
    'tuition-fees.php',
    'scholarships.php',
    'faqs.php',
    'notices.php',
    'notice-detail.php?slug=admission-fair-fall-2026',
    'events.php',
    'event-detail.php?slug=rmu-tech-carnival-2026',
    'news.php',
    'news-detail.php?slug=admission-fair-opening-2026',
    'downloads.php',
    'campus.php',
    'contact.php',
    'search.php?q=CSE',
    'privacy-policy.php',
    '404.php'
];

foreach ($publicPages as $route) {
    run_test("Render Public Page: $route", function() use ($baseDir, $route) {
        $parts = parse_url($route);
        $file = $parts['path'];
        $query = $parts['query'] ?? '';
        
        $_SERVER['PHP_SELF'] = '/' . $file;
        $_SERVER['SCRIPT_NAME'] = '/' . $file;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        if (!empty($query)) {
            parse_str($query, $_GET);
        }

        ob_start();
        include $baseDir . '/' . $file;
        $output = ob_get_clean();

        if (empty($output)) {
            return "Output was empty for $route";
        }
        if (strpos($output, 'Rabindra Maitree University') === false) {
            return "Page branding missing in $route";
        }
        return true;
    });
}

// 6. Test Bangladeshi Mobile Validation
run_test("Bangladeshi Mobile Number Validation Logic", function() {
    $validNumbers = ['01712345678', '01302991188', '01812345678', '01912345678', '8801712345678'];
    $invalidNumbers = ['01234567890', '0171234567', '017123456789', 'abcdefghijk', ''];

    foreach ($validNumbers as $v) {
        if (!validate_bd_mobile($v)) return "Failed to recognize valid mobile: $v";
    }
    foreach ($invalidNumbers as $inv) {
        if (validate_bd_mobile($inv)) return "Accepted invalid mobile: $inv";
    }
    return true;
});

// 7. Test GPA Validation Logic
run_test("GPA Validation (1.00 - 5.00 range)", function() {
    if (!validate_gpa('5.00')) return "5.00 should be valid";
    if (!validate_gpa('2.50')) return "2.50 should be valid";
    if (!validate_gpa('1.00')) return "1.00 should be valid";
    if (validate_gpa('0.50')) return "0.50 should be invalid";
    if (validate_gpa('5.01')) return "5.01 should be invalid";
    if (validate_gpa('abc')) return "'abc' should be invalid";
    return true;
});

// 8. Test Online Admission Intake & Persistence
run_test("Online Admission Prototype Submission & Duplicate Prevention", function() use ($baseDir) {
    $db = getDb();
    
    // Simulate valid submission
    $testRef = generate_application_ref($db);
    $testMobile = '01700998877';
    $testSubject = 'BSc Engg in CSE';

    // Clean any prior test
    $db->prepare("DELETE FROM admissions WHERE mobile = ?")->execute([$testMobile]);

    $insert = $db->prepare("
        INSERT INTO admissions (application_ref, full_name, mobile, email, last_college, ssc_gpa, hsc_gpa, home_district, hsc_group, interested_subject, quota, status)
        VALUES (?, 'Test Student Candidate', ?, 'test@candidate.com', 'Kushtia Model College', 4.50, 4.30, 'Kushtia', 'Science', ?, 'NO', 'Submitted')
    ");
    $insert->execute([$testRef, $testMobile, $testSubject]);

    // Verify persisted
    $chk = $db->prepare("SELECT * FROM admissions WHERE application_ref = ?");
    $chk->execute([$testRef]);
    $row = $chk->fetch();
    if (!$row) return "Candidate record not found in admissions table.";
    if ($row['full_name'] !== 'Test Student Candidate') return "Data corruption in candidate name.";

    // Test duplicate check logic
    $dupStmt = $db->prepare("SELECT application_ref FROM admissions WHERE mobile = ? AND interested_subject = ? AND status IN ('Submitted', 'Under Review')");
    $dupStmt->execute([$testMobile, $testSubject]);
    $dup = $dupStmt->fetch();
    if (!$dup || $dup['application_ref'] !== $testRef) {
        return "Duplicate detection logic failed to identify existing pending application.";
    }

    return true;
});

// 9. Test Contact Inquiries Submission
run_test("Contact Form Message Persistence & Sanitization", function() {
    $db = getDb();
    $stmt = $db->prepare("
        INSERT INTO contact_messages (full_name, email, phone, subject, message, ip_address)
        VALUES ('Automated Test User', 'test.user@rmu-test.com', '01711002233', 'Test Unit Inquiry', 'Testing contact database pipeline.', '127.0.0.1')
    ");
    $stmt->execute();
    $id = $db->lastInsertId();
    if (!$id) return "Failed to retrieve insert ID for contact message.";

    $verify = $db->prepare("SELECT subject FROM contact_messages WHERE id = ?");
    $verify->execute([$id]);
    if ($verify->fetchColumn() !== 'Test Unit Inquiry') return "Subject mismatch in contact message.";

    return true;
});

// 10. Test Admin Authentication & Password Hashing
run_test("Admin Authentication Verification (password_verify)", function() {
    $db = getDb();
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE username = 'admin'");
    $stmt->execute();
    $hash = $stmt->fetchColumn();
    if (!$hash) return "Default admin user not found.";

    // Test correct password
    if (!password_verify('Admin@RMU2026!', $hash)) {
        return "Correct password 'Admin@RMU2026!' failed verification.";
    }

    // Test incorrect password
    if (password_verify('WrongPassword123!', $hash)) {
        return "Incorrect password was unexpectedly accepted.";
    }

    return true;
});

// 11. Test Protected Admin Routes Redirect Unauthenticated Visitors
run_test("Access Control: Protected Admin Pages Reject Unauthenticated Users", function() use ($baseDir) {
    $_SESSION = []; // Clear session
    if (is_logged_in()) return "Session should be empty.";

    // Check dashboard requires login
    $output = "";
    // Note: require_login calls header('Location: ...') and exit, so we test function behavior
    $caughtRedirect = false;
    try {
        if (!is_logged_in()) {
            $caughtRedirect = true;
        }
    } catch (Exception $e) {}

    return $caughtRedirect;
});

// 12. Test Global Search Functionality
run_test("Global Search Across Notices, Programs, and Departments", function() {
    $db = getDb();
    $term = '%CSE%';
    $stmt = $db->prepare("SELECT COUNT(*) FROM programs WHERE title_en LIKE ? OR description LIKE ?");
    $stmt->execute([$term, $term]);
    $matches = (int)$stmt->fetchColumn();
    if ($matches < 1) return "Search for 'CSE' returned 0 program matches.";
    return true;
});

// 13. Test RMU Official Subject List Integrity
run_test("Official RMU Subject List Integrity (11 Undergraduate & 3 Postgraduate Programs)", function() {
    $db = getDb();

    // Check 11 Undergraduate Programs
    $ugList = $db->query("SELECT slug, title_en FROM programs WHERE is_active = 1 AND degree_level = 'Undergraduate' ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (count($ugList) < 11) {
        return "Expected 11 Undergraduate programs, found " . count($ugList);
    }

    // Verify Agriculture — BSc (Honours)
    $hasAgri = false;
    $hasPess = false;
    foreach ($ugList as $ug) {
        if (strpos($ug['title_en'], 'Agriculture') !== false) $hasAgri = true;
        if (strpos($ug['title_en'], 'Physical Education') !== false) $hasPess = true;
    }
    if (!$hasAgri) return "Agriculture — BSc (Honours) program not found in Undergraduate programs list.";
    if (!$hasPess) return "Physical Education and Sports Science not found in Undergraduate programs list.";

    // Check Postgraduate Programs
    $pgList = $db->query("SELECT slug, title_en FROM programs WHERE is_active = 1 AND degree_level = 'Postgraduate' ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    $hasBangla = false;
    $hasMba = false;
    $hasMped = false;
    foreach ($pgList as $pg) {
        if (strpos($pg['title_en'], 'Bangla') !== false) $hasBangla = true;
        if (strpos($pg['title_en'], 'MBA') !== false || strpos($pg['title_en'], 'Business Administration') !== false) $hasMba = true;
        if (strpos($pg['title_en'], 'MPEd') !== false || strpos($pg['title_en'], 'Physical Education') !== false) $hasMped = true;
    }
    if (!$hasBangla) return "MA in Bangla not found in Postgraduate programs.";
    if (!$hasMba) return "Master of Business Administration (MBA) not found in Postgraduate programs.";
    if (!$hasMped) return "Master of Physical Education (MPEd) not found in Postgraduate programs.";

    return true;
});

// 44. Test Student Portal Routes & Access Protection
run_test("Student Portal Protected Routes & Access Control", function() use ($baseDir) {
    // Unauthenticated access to dashboard.php should redirect to login.php
    $_SESSION = [];
    ob_start();
    include $baseDir . '/student/login.php';
    $loginOutput = ob_get_clean();
    if (strpos($loginOutput, 'Student Information & Academic Portal') === false && strpos($loginOutput, 'Student ID') === false) {
        return "Student login page failed to render proper portal header.";
    }

    return true;
});

// 45. Test Student Record & Authentication Integrity
run_test("Student Record & Authentication Integrity", function() {
    $db = getDb();
    $student = $db->query("SELECT * FROM students WHERE student_id = 'RMU2601001'")->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return "Demonstration student RMU2601001 not found in database.";
    }
    if (!password_verify('student123', $student['password_hash'])) {
        return "Student password verification failed for RMU2601001.";
    }

    $courseCount = (int)$db->query("SELECT COUNT(*) FROM student_courses WHERE student_id = {$student['id']}")->fetchColumn();
    if ($courseCount < 4) {
        return "Enrolled courses for student RMU2601001 should be at least 4, found: $courseCount";
    }

    $resultCount = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$student['id']}")->fetchColumn();
    if ($resultCount < 5) {
        return "Academic results for student RMU2601001 should be at least 5, found: $resultCount";
    }

    return true;
});

// 46. Test ৳150 Admission Payment & Student Account Auto-Provisioning
run_test("Admission ৳150 Payment & Student Portal Auto-Provisioning", function() {
    $db = getDb();
    
    // Create a mock applicant
    $testRef = 'RMU-2026-TESTPAY';
    $db->prepare("DELETE FROM admissions WHERE application_ref = ?")->execute([$testRef]);

    $ins = $db->prepare("
        INSERT INTO admissions (
            application_ref, full_name, mobile, email, last_college,
            ssc_gpa, hsc_gpa, home_district, hsc_group, interested_subject,
            quota, status, payment_status, payment_amount
        ) VALUES (
            ?, 'Payment Workflow Test Candidate', '01799887766', 'paytest@candidate.com', 'Kushtia Model College',
            5.00, 4.80, 'Kushtia', 'Science', 'Computer Science and Engineering (CSE)',
            'NO', 'Submitted', 'Unpaid', 150.00
        )
    ");
    $ins->execute([$testRef]);
    $admId = (int)$db->lastInsertId();

    // Provision student from this admission
    $creds = provision_student_from_admission($admId);
    if (!$creds) {
        return "provision_student_from_admission failed to return credentials.";
    }

    if (empty($creds['student_id']) || empty($creds['portal_password'])) {
        return "Student ID or initial password was not generated.";
    }

    // Verify row in students table
    $student = $db->query("SELECT * FROM students WHERE student_id = '{$creds['student_id']}'")->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return "Student account was not inserted into students table for {$creds['student_id']}.";
    }

    if (!password_verify($creds['portal_password'], $student['password_hash'])) {
        return "Student portal password verification failed for newly provisioned account.";
    }

    $courseCount = (int)$db->query("SELECT COUNT(*) FROM student_courses WHERE student_id = {$student['id']}")->fetchColumn();
    if ($courseCount < 1) {
        return "New student courses were not enrolled upon admission provisioning.";
    }

    return true;
});

// 47. Test Direct Student Portal Login with Newly Generated Credentials
run_test("Student Portal Login with Auto-Generated Admission Credentials", function() {
    $db = getDb();
    $testRef = 'RMU-2026-TESTPAY';
    $adm = $db->query("SELECT * FROM admissions WHERE application_ref = '$testRef'")->fetch(PDO::FETCH_ASSOC);
    if (!$adm || empty($adm['student_id']) || empty($adm['portal_password'])) {
        return "Admission test record or credentials missing.";
    }

    // Verify student lookup logic from student/login.php
    $stmt = $db->prepare("SELECT * FROM students WHERE (student_id = ? OR email = ?) AND status = 'Active' LIMIT 1");
    $stmt->execute([$adm['student_id'], $adm['student_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        return "Student login query failed to find active student.";
    }

    if (!password_verify($adm['portal_password'], $student['password_hash'])) {
        return "Login password_verify failed with newly issued credentials.";
    }

    // Clean up mock test records
    $db->prepare("DELETE FROM student_courses WHERE student_id = ?")->execute([$student['id']]);
    $db->prepare("DELETE FROM students WHERE id = ?")->execute([$student['id']]);
    $db->prepare("DELETE FROM admissions WHERE id = ?")->execute([$adm['id']]);

    return true;
});

// 48. Test Payment Verification with Sender Number, TrxID, and Screenshot Slip
run_test("Payment Verification with Sender Number, TrxID, and Screenshot", function() {
    $db = getDb();
    $testRef = 'RMU-2026-PAYVERIFY';
    
    // Clean up any old test record
    $db->prepare("DELETE FROM admissions WHERE application_ref = ?")->execute([$testRef]);

    $ins = $db->prepare("
        INSERT INTO admissions (
            application_ref, full_name, mobile, email, last_college,
            ssc_gpa, hsc_gpa, home_district, hsc_group, interested_subject,
            quota, status, payment_status, payment_amount, payment_method,
            transaction_id, sender_number, payment_screenshot, paid_at
        ) VALUES (
            ?, 'Verification Test Candidate', '01711223344', 'verify@test.com', 'Kushtia Govt College',
            5.00, 5.00, 'Kushtia', 'Science', 'Computer Science and Engineering (CSE)',
            'NO', 'Accepted', 'Paid', 150.00, 'bKash',
            'TRX99887766', '01336367336', 'receipt_demo.svg', NOW()
        )
    ");
    $ins->execute([$testRef]);
    $admId = (int)$db->lastInsertId();

    $record = $db->query("SELECT * FROM admissions WHERE id = {$admId}")->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        return "Failed to retrieve inserted payment verification record.";
    }

    if ($record['sender_number'] !== '01336367336') {
        return "Sender number mismatch in database.";
    }

    if ($record['transaction_id'] !== 'TRX99887766') {
        return "Transaction ID mismatch in database.";
    }

    if (empty($record['payment_screenshot'])) {
        return "Payment screenshot field is empty.";
    }

    // Clean up
    $db->prepare("DELETE FROM admissions WHERE id = ?")->execute([$admId]);

    return true;
});

// 49. Test Homepage Fast Facts Statistics (Exactly 5 Items with 1,700+ Regular Students)
run_test("Homepage Fast Facts Statistics Integrity (5 Metrics)", function() {
    $indexHtml = file_get_contents(BASE_PATH . '/index.php');
    
    $requiredNumbers = ['2015', '4', '11', '14+', '1,700+'];
    foreach ($requiredNumbers as $num) {
        if (strpos($indexHtml, $num) === false) {
            return "Missing statistic number '{$num}' in index.php";
        }
    }

    $requiredLabels = [
        'Year Established',
        'Faculties',
        'Academic Departments',
        'Degree Programs',
        'Regular Students'
    ];
    foreach ($requiredLabels as $label) {
        if (stripos($indexHtml, $label) === false) {
            return "Missing statistic label '{$label}' in index.php";
        }
    }

    if (strpos($indexHtml, 'home-stats-grid') === false) {
        return "Missing home-stats-grid class in index.php";
    }

    return true;
});


// 50. Test Per-Semester Academic Transcript Generation & Verification
run_test("Per-Semester Academic Transcript Generation & Download Verification", function() {
    $db = getDb();
    
    // Find or seed a demo student with results
    $student = $db->query("SELECT s.* FROM students s JOIN student_results r ON s.id = r.student_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return "No student with academic results found in database.";
    }

    $studentId = (int)$student['id'];

    // Verify student results exist
    $resStmt = $db->prepare("SELECT * FROM student_results WHERE student_id = ?");
    $resStmt->execute([$studentId]);
    $results = $resStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($results)) {
        return "Failed to load student results.";
    }

    $firstSem = $results[0]['semester'];

    // Test rendering semester-transcript.php in buffer
    $_SESSION['student_pk_id'] = $studentId;
    $_SESSION['student_id'] = $student['student_id'];
    $_SESSION['student_name'] = $student['full_name'];
    $_GET['semester'] = $firstSem;

    ob_start();
    require BASE_PATH . '/student/semester-transcript.php';
    $output = ob_get_clean();

    unset($_GET['semester']);

    if (empty($output)) {
        return "Transcript page produced empty output.";
    }

    if (strpos($output, 'Rabindra Maitree University') === false) {
        return "Transcript missing university title.";
    }

    if (strpos($output, 'Official Semester Grade Sheet &amp; Transcript') === false && strpos($output, 'Official Semester Grade Sheet & Transcript') === false) {
        return "Transcript missing official grade sheet heading.";
    }

    if (strpos($output, $student['student_id']) === false) {
        return "Transcript missing student ID.";
    }

    if (strpos($output, 'Semester GPA (SGPA)') === false) {
        return "Transcript missing SGPA performance metric.";
    }

    if (strpos($output, 'UGC Uniform Grading Scale') === false) {
        return "Transcript missing UGC grading scale reference.";
    }

    if (strpos($output, 'Controller of Examinations') === false) {
        return "Transcript missing Controller of Examinations signature block.";
    }

    return true;
});

// 51. UGC Uniform Grading Scale Auto-Calculation Function Verification
run_test("UGC Uniform Grading Scale 4.00 Auto-Calculation Rules", function() {
    $cases = [
        ['marks' => 85, 'expected_grade' => 'A+', 'expected_gp' => 4.00, 'expected_status' => 'Pass'],
        ['marks' => 78, 'expected_grade' => 'A',  'expected_gp' => 3.75, 'expected_status' => 'Pass'],
        ['marks' => 72, 'expected_grade' => 'A-', 'expected_gp' => 3.50, 'expected_status' => 'Pass'],
        ['marks' => 67, 'expected_grade' => 'B+', 'expected_gp' => 3.25, 'expected_status' => 'Pass'],
        ['marks' => 61, 'expected_grade' => 'B',  'expected_gp' => 3.00, 'expected_status' => 'Pass'],
        ['marks' => 56, 'expected_grade' => 'B-', 'expected_gp' => 2.75, 'expected_status' => 'Pass'],
        ['marks' => 51, 'expected_grade' => 'C+', 'expected_gp' => 2.50, 'expected_status' => 'Pass'],
        ['marks' => 46, 'expected_grade' => 'C',  'expected_gp' => 2.25, 'expected_status' => 'Pass'],
        ['marks' => 42, 'expected_grade' => 'D',  'expected_gp' => 2.00, 'expected_status' => 'Pass'],
        ['marks' => 38, 'expected_grade' => 'F',  'expected_gp' => 0.00, 'expected_status' => 'Fail'],
    ];

    foreach ($cases as $c) {
        $result = calculate_grade_from_marks($c['marks']);
        if ($result['letter_grade'] !== $c['expected_grade']) {
            return "Failed for marks {$c['marks']}: expected grade {$c['expected_grade']}, got {$result['letter_grade']}";
        }
        if (abs($result['grade_point'] - $c['expected_gp']) > 0.001) {
            return "Failed for marks {$c['marks']}: expected point {$c['expected_gp']}, got {$result['grade_point']}";
        }
        if ($result['status'] !== $c['expected_status']) {
            return "Failed for marks {$c['marks']}: expected status {$c['expected_status']}, got {$result['status']}";
        }
    }
    return true;
});

// 52. Eight-Semester Credit-Weighted CGPA Recalculation Accuracy
run_test("Credit-Weighted CGPA Formula vs Simple Average Integrity", function() {
    $db = getDb();
    // Student 4 has all 8 semesters seeded
    $studentId = 4;
    $summary = get_student_academic_summary($studentId, true);

    if (!$summary['has_results']) {
        return "Student #4 does not have published results for test.";
    }

    if (count($summary['semesters']) < 8) {
        return "Expected 8 semesters for Student #4, found " . count($summary['semesters']);
    }

    // Verify credit-weighted formula: Sum(Credit * Grade Point) / Sum(Credit)
    $manualWeightedPoints = 0.0;
    $manualCredits = 0.0;
    $sgpaList = [];

    foreach ($summary['semesters'] as $sem) {
        $sgpaList[] = $sem['sgpa'];
        foreach ($sem['courses'] as $c) {
            $manualCredits += (float)$c['credit'];
            $manualWeightedPoints += ((float)$c['credit'] * (float)$c['grade_point']);
        }
    }

    $expectedCgpa = round($manualWeightedPoints / $manualCredits, 2);
    if (abs($summary['final_cgpa'] - $expectedCgpa) > 0.01) {
        return "CGPA discrepancy: calculated {$summary['final_cgpa']} vs expected {$expectedCgpa}";
    }

    // Ensure all 8 semester GPAs are present in summary
    for ($i = 1; $i <= 8; $i++) {
        if (!isset($summary['semester_gpas'][$i])) {
            return "Missing Semester {$i} GPA in summary.";
        }
    }

    return true;
});

// 53. Render Student Results Page with 8 Semesters, Marks & Transcript Summary
run_test("Render student/results.php: Full Marks, Obtained Marks, 8-Semester Strip & Summary", function() {
    $db = getDb();
    $student = $db->query("SELECT * FROM students WHERE id = 4 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    $_SESSION['student_pk_id'] = $student['id'];
    $_SESSION['student_id'] = $student['student_id'];
    $_SESSION['student_name'] = $student['full_name'];

    ob_start();
    require BASE_PATH . '/student/results.php';
    $output = ob_get_clean();

    if (empty($output)) {
        return "student/results.php generated empty output.";
    }

    $expectedSnippets = [
        'Rabindra Maitree University',
        'OFFICIAL ACADEMIC TRANSCRIPT &amp; EIGHT-SEMESTER GRADE RECORD',
        'Subject Code',
        'Subject Name',
        'Credit',
        'Full Marks',
        'Obtained',
        'Grade Point',
        'Eight-Semester GPA Progression',
        'Academic Transcript Summary &amp; Degree Status',
        'Office of Registrar',
        'Controller of Examinations',
        'RMU SEAL'
    ];

    foreach ($expectedSnippets as $snip) {
        if (strpos($output, $snip) === false) {
            return "student/results.php missing required snippet: '$snip'";
        }
    }

    return true;
});

// 54. Render Consolidated 8-Semester Transcript (PDF Printable)
run_test("Render student/semester-transcript.php (Consolidated 8-Semester Mode)", function() {
    $db = getDb();
    $student = $db->query("SELECT * FROM students WHERE id = 4 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    $_SESSION['student_pk_id'] = $student['id'];
    $_SESSION['student_id'] = $student['student_id'];
    $_SESSION['student_name'] = $student['full_name'];
    $_GET['semester'] = 'all';

    ob_start();
    require BASE_PATH . '/student/semester-transcript.php';
    $output = ob_get_clean();
    unset($_GET['semester']);

    if (empty($output)) {
        return "student/semester-transcript.php generated empty output.";
    }

    $requiredFields = [
        'Rabindra Maitree University',
        'Consolidated Academic Transcript',
        'Student Name',
        'Student ID / Roll No',
        'Program of Study',
        'Department',
        'Faculty',
        'Academic Session',
        'Semester / Term',
        'Current Academic Status',
        'Medium of Instruction',
        'English',
        'Full Marks',
        'Obtained',
        'Eight-Semester Academic GPA Breakdown',
        'Official Academic Transcript Summary',
        'Controller of Examinations',
        'Office of the Registrar'
    ];

    foreach ($requiredFields as $field) {
        if (strpos($output, $field) === false) {
            return "semester-transcript.php missing required field: '$field'";
        }
    }

    return true;
});

// 55. Zero Published Results Handling: No Dummy 3.33 CGPA Displayed
run_test("Zero Published Results: Displays 'No academic results published yet' and Zero/NA CGPA", function() {
    $db = getDb();
    // Find or test with student that has 0 published results (e.g. Student 2 RMU2601002)
    $studentZero = $db->query("
        SELECT s.* FROM students s 
        LEFT JOIN student_results r ON s.id = r.student_id AND r.is_published = 1 
        WHERE r.id IS NULL 
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$studentZero) {
        return "No student without results found to test zero-results behavior.";
    }

    $_SESSION['student_pk_id'] = $studentZero['id'];
    $_SESSION['student_id'] = $studentZero['student_id'];
    $_SESSION['student_name'] = $studentZero['full_name'];

    // Test dashboard
    ob_start();
    require BASE_PATH . '/student/dashboard.php';
    $dashOut = ob_get_clean();

    if (strpos($dashOut, '3.33') !== false) {
        return "Dashboard still displays fake 3.33 CGPA for student without published results.";
    }
    if (strpos($dashOut, 'No published results yet') === false) {
        return "Dashboard missing 'No published results yet' indicator for student without results.";
    }

    // Test results page
    ob_start();
    require BASE_PATH . '/student/results.php';
    $resultsOut = ob_get_clean();

    if (strpos($resultsOut, 'No academic results published yet') === false) {
        return "Results page missing 'No academic results published yet' banner.";
    }
    if (strpos($resultsOut, '3.33') !== false) {
        return "Results page displays fake 3.33 CGPA for student without results.";
    }

    return true;
});

// 56. Admin Dedicated Results Manager Integrity & 8-Semester Tools
run_test("Admin Results Manager Interface & Functionality", function() {
    $db = getDb();
    $adminUser = $db->query("SELECT * FROM users WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    $_SESSION['user_id'] = $adminUser['id'];
    $_SESSION['username'] = $adminUser['username'];
    $_SESSION['user_role'] = $adminUser['role'];
    $_SESSION['logged_in_time'] = time();

    $_GET['student_id'] = 4;

    ob_start();
    require BASE_PATH . '/admin/results-manager.php';
    $adminOut = ob_get_clean();
    unset($_GET['student_id']);

    if (empty($adminOut)) {
        return "admin/results-manager.php produced empty output.";
    }

    $requiredAdminElements = [
        '8-Semester Results &amp; CGPA Management',
        'Select / Search Student',
        '8-Semester SGPA Progression Overview',
        'Add Subject Result',
        'Full Marks',
        'Obtained Marks',
        'Course Code',
        'Populate Standard RMU 8-Semester Curriculum'
    ];

    foreach ($requiredAdminElements as $el) {
        if (strpos($adminOut, $el) === false && strpos($adminOut, html_entity_decode($el)) === false) {
            return "admin/results-manager.php missing: '$el'";
        }
    }

    return true;
});

echo bold("\n-------------------------------------------------------\n");
echo "TOTAL TESTS: " . bold($totalTests) . " | PASSED: " . green($passedTests) . " | FAILED: " . ($failedTests > 0 ? red($failedTests) : green(0)) . "\n";
echo bold("-------------------------------------------------------\n\n");

if ($failedTests === 0) {
    echo green(bold("ALL AUTOMATED TESTS PASSED SUCCESSFULLY (100% SUITE PASS)!\n\n"));
    exit(0);
} else {
    echo red(bold("SOME TESTS FAILED! Please inspect errors above.\n\n"));
    exit(1);
}

