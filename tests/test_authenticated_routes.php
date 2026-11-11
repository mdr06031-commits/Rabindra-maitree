<?php
/**
 * Test Authenticated User & Admin Flows via cURL with Cookie Jar
 */
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$base = 'http://127.0.0.1:8000';
$cookieFile = sys_get_temp_dir() . '/rmu_test_cookies_' . time() . '_' . rand(100, 999) . '.txt';
$db = getDb();

$tmpStudentPk = 0;
$tmpStudentCode = 'RMU-AUTH-' . time();
$tmpStudentPass = 'AuthPass!' . bin2hex(random_bytes(3));

function make_req($url, $postData = null, $followRedirect = true) {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirect);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);

    return ['code' => $httpCode, 'url' => $effUrl, 'body' => $res, 'error' => $err];
}

try {
    $progRow = $db->query("SELECT id, department_id FROM programs WHERE slug = 'bsc-in-cse' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $insTmp = $db->prepare("
        INSERT INTO students (student_id, username, full_name, email, phone, password_hash, program_id, department_id, academic_session, batch, current_year, current_semester, cgpa, credits_completed, total_credits_required, status)
        VALUES (?, ?, 'Auth Route Test Student', ?, '01711009988', ?, ?, ?, 'Fall 2026', 'Fall 2026', '1st Year', '1st Semester', 0.00, 0, 140, 'Active')
    ");
    $insTmp->execute([
        $tmpStudentCode,
        $tmpStudentCode,
        strtolower($tmpStudentCode) . '@rmu.test',
        password_hash($tmpStudentPass, PASSWORD_DEFAULT),
        (int)($progRow['id'] ?? 1),
        (int)($progRow['department_id'] ?? 6)
    ]);
    $tmpStudentPk = (int)$db->lastInsertId();

    echo "=== 1. TESTING STUDENT PORTAL AUTHENTICATION & ACCESS ===" . PHP_EOL;

    // 1.1 Load student login page to get CSRF token
    $loginPage = make_req($base . '/student/login.php');
    if ($loginPage['code'] !== 200) {
        die("Failed to reach student login page: code " . $loginPage['code'] . PHP_EOL);
    }

    preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $loginPage['body'], $m);
    $csrfToken = $m[1] ?? '';

    // 1.2 Submit student login using disposable test student credentials
    $loginPost = make_req($base . '/student/login.php', [
        'csrf_token' => $csrfToken,
        'student_id' => $tmpStudentCode,
        'password'   => $tmpStudentPass
    ]);

    echo "Student Login -> Code: {$loginPost['code']}, URL: {$loginPost['url']}" . PHP_EOL;

    $studentRoutes = [
        '/student/dashboard.php' => 'Dashboard Overview',
        '/student/results.php' => 'Academic Results &amp; Grade Transcript',
        '/student/semester-transcript.php?semester=all' => 'Rabindra Maitree University',
        '/student/courses.php' => 'Enrolled Courses &amp; Class Schedule',
        '/student/fees.php' => 'Tuition Fees &amp; Payments',
        '/student/id-card.php' => 'Digital Student Identity Card',
        '/student/profile.php' => 'My Profile &amp; Photo'
    ];

    $studentPass = true;
    foreach ($studentRoutes as $uri => $expected) {
        $res = make_req($base . $uri);
        $ok = ($res['code'] === 200 && (strpos($res['body'], $expected) !== false || strpos($res['body'], html_entity_decode($expected)) !== false));
        echo ($ok ? '[PASS] ' : '[FAIL] ') . str_pad($uri, 46) . " (HTTP {$res['code']})" . PHP_EOL;
        if (!$ok) {
            $studentPass = false;
            echo "   Error: Did not find expected text '{$expected}' on {$uri}" . PHP_EOL;
        }
    }

    // Clear cookies for admin test
    if (file_exists($cookieFile)) unlink($cookieFile);

    echo PHP_EOL . "=== 2. TESTING ADMIN PANEL AUTHENTICATION & ACCESS ===" . PHP_EOL;

    // 2.1 Load admin login page
    $adminLoginPage = make_req($base . '/admin/login.php');
    preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $adminLoginPage['body'], $am);
    $adminCsrf = $am[1] ?? '';

    // 2.2 Submit admin login
    $adminLoginPost = make_req($base . '/admin/login.php', [
        'csrf_token' => $adminCsrf,
        'username'   => 'admin',
        'password'   => 'Admin@RMU2026!'
    ]);

    echo "Admin Login -> Code: {$adminLoginPost['code']}, URL: {$adminLoginPost['url']}" . PHP_EOL;

    $adminRoutes = [
        '/admin/dashboard.php' => 'Dashboard',
        '/admin/admissions.php' => 'Admission Management',
        '/admin/payments.php' => 'Payment Management',
        '/admin/students.php' => 'Student Management',
        '/admin/student-detail.php?id=4' => 'Student Profile',
        '/admin/results-manager.php?student_id=4' => '8-Semester Results',
        '/admin/semesters.php' => 'Semester Management',
        '/admin/grading-settings.php' => 'GPA &amp; CGPA Settings',
        '/admin/result-publication.php' => 'Result Publication',
        '/admin/notifications.php' => 'Notifications',
        '/admin/reports.php' => 'Reports &amp; Analytics',
        '/admin/audit-logs.php' => 'Security Audit Logs',
        '/admin/settings.php' => 'Site Settings',
        '/admin/departments.php' => 'Departments',
        '/admin/programs.php' => 'Degree Programs',
        '/admin/faculties.php' => 'Faculties',
        '/admin/notices.php' => 'Notices',
        '/admin/events.php' => 'Events',
        '/admin/news.php' => 'News',
        '/admin/messages.php' => 'Inquiries &amp; Messages'
    ];

    $adminPass = true;
    foreach ($adminRoutes as $uri => $expected) {
        $res = make_req($base . $uri);
        $ok = ($res['code'] === 200 && (strpos($res['body'], $expected) !== false || strpos($res['body'], html_entity_decode($expected)) !== false));
        echo ($ok ? '[PASS] ' : '[FAIL] ') . str_pad($uri, 46) . " (HTTP {$res['code']})" . PHP_EOL;
        if (!$ok) {
            $adminPass = false;
            echo "   Error: Did not find expected text '{$expected}' on {$uri}" . PHP_EOL;
        }
    }

    echo PHP_EOL . "-------------------------------------------------------" . PHP_EOL;
    if ($studentPass && $adminPass) {
        echo "ALL AUTHENTICATED STUDENT & ADMIN FLOWS VERIFIED (100% WORKING)!" . PHP_EOL;
        $exitCode = 0;
    } else {
        echo "SOME AUTHENTICATED FLOWS FAILED!" . PHP_EOL;
        $exitCode = 1;
    }
} finally {
    if (file_exists($cookieFile)) @unlink($cookieFile);
    if ($tmpStudentPk > 0) {
        try {
            $db->prepare("DELETE FROM student_notifications WHERE student_id = ?")->execute([$tmpStudentPk]);
            $db->prepare("DELETE FROM students WHERE id = ?")->execute([$tmpStudentPk]);
        } catch (Throwable $ignored) {}
    }
}
exit($exitCode ?? 1);
