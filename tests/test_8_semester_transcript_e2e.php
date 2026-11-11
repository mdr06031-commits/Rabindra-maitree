<?php
/**
 * Comprehensive End-to-End Test Suite:
 * Complete Automatic 8-Semester Academic Transcript & CGPA System
 *
 * Tests over real HTTP (http://127.0.0.1:8000) & MySQL (rmu_db):
 * 1. Partial semesters (1st & 3rd published, 2nd & 4th-8th missing):
 *    - Default /student/semester-transcript.php renders Consolidated 8-Semester mode
 *    - Shows all 8 semesters with real subjects on 1st & 3rd and clean "Not Published Yet" on 2nd & 4th-8th
 *    - Zero fake marks, zero copied GPAs
 * 2. Editable Full Marks (e.g. 50 marks lab course -> 42/50 = 84% = A+ 4.00) & Credit Hours
 * 3. Empty semester publish protection across results-manager.php & student-detail.php
 * 4. Missing/Previous semester import via CSV paste & CSV file upload (preserving existing semesters)
 * 5. Fail course registered vs earned credits & live edit -> automatic CGPA recalculation
 * 6. Genuine binary PDF download (?download=pdf) validation + decompressed stream assertions
 * 7. Cross-student IDOR prevention, Suspended/Hidden HTTP 403 block, RBAC editor denial, B.Ed 3-semester check
 * 8. Full test cleanup & seed student integrity check
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$baseUrl = 'http://127.0.0.1:8000';
$db = getDb();

$adminCookie  = sys_get_temp_dir() . '/rmu_tr8_admin_' . uniqid() . '.txt';
$editorCookie = sys_get_temp_dir() . '/rmu_tr8_editor_' . uniqid() . '.txt';
$stud1Cookie  = sys_get_temp_dir() . '/rmu_tr8_stud1_' . uniqid() . '.txt';
$stud2Cookie  = sys_get_temp_dir() . '/rmu_tr8_stud2_' . uniqid() . '.txt';

function t8_http(string $url, string $cookieFile, ?array $postData = null, bool $isMultipart = false, bool $follow = true): array {
    $ch = curl_init($url);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HEADERFUNCTION => function($curl, $headerLine) use (&$headers) {
            $len = strlen($headerLine);
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        },
    ]);
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $isMultipart ? $postData : http_build_query($postData));
    }
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP error for {$url}: {$err}");
    }
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'url' => $effectiveUrl, 'headers' => $headers];
}

function t8_csrf(string $html): string {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function t8_assert(bool $cond, string $msg): void {
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

function t8_extract_pdf_text(string $pdfBytes): string {
    $out = '';
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdfBytes, $matches)) {
        foreach ($matches[1] as $rawStream) {
            $decoded = @gzuncompress($rawStream);
            $chunk = ($decoded !== false) ? $decoded : $rawStream;
            $out .= $chunk . "\n";
        }
    }
    // Unescape PDF literal parens for easy substring search
    return str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $out);
}

$exitCode = 0;
$createdStudentPks = [];
$createdAdmissionIds = [];
$tempEditorUserId = 0;
$tempCsvPath = '';
$suffix = (string)rand(60000, 99999);
$seedBefore4 = null;
$seedBefore451 = null;
$seedResBefore4 = 0;

try {
    echo "===============================================================================\n";
    echo "  RMU 8-SEMESTER CONSOLIDATED ACADEMIC TRANSCRIPT & PDF - E2E TEST SUITE\n";
    echo "===============================================================================\n\n";

    // Snapshot baseline seed students (must remain 100% untouched)
    $seedBefore4 = $db->query("SELECT id, student_id, cgpa, credits_completed, status, results_visible, result_access_status FROM students WHERE id = 4 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $seedBefore451 = $db->query("SELECT id, student_id, cgpa, credits_completed, status, results_visible, result_access_status FROM students WHERE id = 451 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $seedResBefore4 = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = 4")->fetchColumn();

    $cseProg = $db->query("SELECT id, department_id, title_en FROM programs WHERE slug = 'bsc-in-cse' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $bedProg = $db->query("SELECT id, department_id, title_en FROM programs WHERE slug = 'b-ed' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    t8_assert(!empty($cseProg) && !empty($bedProg), "CSE and B.Ed programs must exist in DB");

    // -------------------------------------------------------------------------
    // STEP 1: Admin Login & Provision Two Active Test Students (8-Sem CSE + 3-Sem B.Ed)
    // -------------------------------------------------------------------------
    echo "1. Logging in as Admin & provisioning test students (8-Sem CSE & 3-Sem B.Ed):\n";
    $adminGet = t8_http($baseUrl . '/admin/login.php', $adminCookie);
    $adminPost = t8_http($baseUrl . '/admin/login.php', $adminCookie, [
        'csrf_token' => t8_csrf($adminGet['body']),
        'username'   => 'admin',
        'password'   => 'Admin@RMU2026!',
    ]);
    t8_assert($adminPost['code'] === 200 && strpos($adminPost['url'], '/admin/dashboard.php') !== false, "Admin login must succeed");

    // Provision B.Sc. CSE (8 Semesters) student
    $st1Code = 'RMU26TR8_' . $suffix;
    $st1Pass = 'Tr8Pass!' . $suffix;
    $st1Roll = '2026-TR' . $suffix;
    $st1App  = 'RMU-2026-TR' . $suffix;
    $db->prepare("
        INSERT INTO students
        (student_id, roll_number, application_number, username, full_name, email, phone, password_hash, program_id, degree_type, department_id, academic_session, batch, duration_years, program_duration, total_semesters, total_credits_required, status, results_visible, result_access_status, current_semester)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Bachelor / Honours', ?, 'Fall 2026', 'Fall 2026', 4.0, '4 Years (8 Semesters)', 8, 140, 'Active', 1, 'Active', '1st Semester')
    ")->execute([
        $st1Code, $st1Roll, $st1App, $st1Code,
        "Tariqul Transcript Test {$suffix}", "tariqul.{$suffix}@rmu.test", '017' . rand(10000000, 99999999),
        password_hash($st1Pass, PASSWORD_DEFAULT), (int)$cseProg['id'], (int)$cseProg['department_id']
    ]);
    $st1Pk = (int)$db->lastInsertId();
    $createdStudentPks[] = $st1Pk;

    // Provision 3-Semester B.Ed student
    $st2Code = 'RMU26TR3_' . $suffix;
    $st2Pass = 'Tr3Pass!' . $suffix;
    $db->prepare("
        INSERT INTO students
        (student_id, roll_number, application_number, username, full_name, email, phone, password_hash, program_id, degree_type, department_id, academic_session, batch, duration_years, program_duration, total_semesters, total_credits_required, status, results_visible, result_access_status, current_semester)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'B.Ed', ?, 'Fall 2026', 'Fall 2026', 1.5, '1.5 Years (3 Semesters)', 3, 45, 'Active', 1, 'Active', '1st Semester')
    ")->execute([
        $st2Code, '2026-B3' . $suffix, 'RMU-2026-B3' . $suffix, $st2Code,
        "Farhana BEd Test {$suffix}", "farhana.{$suffix}@rmu.test", '018' . rand(10000000, 99999999),
        password_hash($st2Pass, PASSWORD_DEFAULT), (int)$bedProg['id'], (int)$bedProg['department_id']
    ]);
    $st2Pk = (int)$db->lastInsertId();
    $createdStudentPks[] = $st2Pk;

    // Log both students into Student Portal
    foreach ([['code' => $st1Code, 'pass' => $st1Pass, 'cookie' => $stud1Cookie], ['code' => $st2Code, 'pass' => $st2Pass, 'cookie' => $stud2Cookie]] as $sl) {
        $sGet = t8_http($baseUrl . '/student/login.php', $sl['cookie']);
        $sPost = t8_http($baseUrl . '/student/login.php', $sl['cookie'], [
            'csrf_token' => t8_csrf($sGet['body']),
            'student_id' => $sl['code'],
            'password'   => $sl['pass'],
        ]);
        t8_assert($sPost['code'] === 200 && strpos($sPost['url'], '/student/dashboard.php') !== false, "Student {$sl['code']} login must succeed");
    }
    echo "   [PASS] Provisioned & authenticated test students {$st1Code} (8-Sem) and {$st2Code} (3-Sem).\n\n";

    // -------------------------------------------------------------------------
    // STEP 2: Add Partial Real Results (1st & 3rd Semesters, Custom Full Marks 50)
    //         and Verify Empty Semester Publish Protection
    // -------------------------------------------------------------------------
    echo "2. Testing Partial Real Result Entry (1st & 3rd Semesters), Custom Full Marks (50) & Empty Publish Guard:\n";
    $rmPage = t8_http($baseUrl . '/admin/results-manager.php?student_id=' . $st1Pk, $adminCookie);
    t8_assert($rmPage['code'] === 200, "Admin results-manager must load cleanly");
    t8_assert(strpos($rmPage['body'], 'Import Missing / Previous Semester Results') !== false, "results-manager.php must display Import Missing / Previous Semester Results card");
    $rmCsrf = t8_csrf($rmPage['body']);

    // Add 1st Semester CSE 101 (3.0 cr, Full 100, Marks 85 -> A+ 4.00) via results-manager.php
    t8_http($baseUrl . '/admin/results-manager.php?student_id=' . $st1Pk, $adminCookie, [
        'csrf_token'     => $rmCsrf,
        'action'         => 'add_result',
        'student_id'     => $st1Pk,
        'semester'       => '1st Semester',
        'course_code'    => 'CSE 101',
        'course_name'    => 'Structured Programming Language',
        'credit'         => '3.0',
        'full_marks'     => '100',
        'marks'          => '85',
        'exam_term'      => 'Semester Final Examination',
        'publish_status' => 'Published',
        'reason'         => 'E2E 1st Sem theory entry',
    ]);

    // Add 1st Semester CSE 102 Lab (1.5 cr, Full 50, Marks 42 -> 84% -> A+ 4.00) via results-manager.php
    t8_http($baseUrl . '/admin/results-manager.php?student_id=' . $st1Pk, $adminCookie, [
        'csrf_token'     => $rmCsrf,
        'action'         => 'add_result',
        'student_id'     => $st1Pk,
        'semester'       => '1st Semester',
        'course_code'    => 'CSE 102',
        'course_name'    => 'Structured Programming Lab',
        'credit'         => '1.5',
        'full_marks'     => '50',
        'marks'          => '42',
        'exam_term'      => 'Lab Practical Examination',
        'publish_status' => 'Published',
        'reason'         => 'E2E 1st Sem lab custom full marks 50 test',
    ]);

    // Verify 42/50 graded automatically as A+ (4.00) in DB
    $labRow = $db->query("SELECT full_marks, marks, letter_grade, grade_point FROM student_results WHERE student_id = {$st1Pk} AND course_code = 'CSE 102' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    t8_assert($labRow && (int)$labRow['full_marks'] === 50 && abs((float)$labRow['marks'] - 42.0) < 0.01, "Lab full_marks=50 and marks=42 must be saved cleanly");
    t8_assert($labRow['letter_grade'] === 'A+' && abs((float)$labRow['grade_point'] - 4.00) < 0.01, "42/50 (84%) must auto-grade as A+ (4.00), got {$labRow['letter_grade']} ({$labRow['grade_point']})");

    // Add 3rd Semester via admin/student-detail.php
    $sdPage = t8_http($baseUrl . '/admin/student-detail.php?id=' . $st1Pk, $adminCookie);
    $sdCsrf = t8_csrf($sdPage['body']);
    t8_http($baseUrl . '/admin/student-detail.php?id=' . $st1Pk, $adminCookie, [
        'csrf_token'     => $sdCsrf,
        'action'         => 'add_result',
        'semester'       => '3rd Semester',
        'course_code'    => 'CSE 301',
        'course_name'    => 'Database Management Systems',
        'credit'         => '3.0',
        'full_marks'     => '100',
        'marks'          => '75',
        'exam_term'      => 'Semester Final Examination',
        'publish_status' => 'Published',
        'reason'         => 'E2E 3rd Sem entry via student-detail',
    ]);
    t8_http($baseUrl . '/admin/student-detail.php?id=' . $st1Pk, $adminCookie, [
        'csrf_token'     => $sdCsrf,
        'action'         => 'add_result',
        'semester'       => '3rd Semester',
        'course_code'    => 'CSE 302',
        'course_name'    => 'Computer Architecture',
        'credit'         => '3.0',
        'full_marks'     => '100',
        'marks'          => '70',
        'exam_term'      => 'Semester Final Examination',
        'publish_status' => 'Published',
        'reason'         => 'E2E 3rd Sem entry via student-detail',
    ]);

    // Test empty-semester publish blocking (2nd Semester has no saved results)
    $pubEmptyRm = t8_http($baseUrl . '/admin/results-manager.php?student_id=' . $st1Pk, $adminCookie, [
        'csrf_token'    => $rmCsrf,
        'action'        => 'publish_semester',
        'student_id'    => $st1Pk,
        'semester_name' => '2nd Semester',
    ]);
    t8_assert(strpos($pubEmptyRm['body'], 'No saved subject results exist') !== false, "results-manager.php must refuse publishing empty 2nd Semester");
    $pubEmptySd = t8_http($baseUrl . '/admin/student-detail.php?id=' . $st1Pk, $adminCookie, [
        'csrf_token'    => $sdCsrf,
        'action'        => 'publish_semester',
        'semester_name' => '2nd Semester',
    ]);
    t8_assert(strpos($pubEmptySd['body'], 'No saved subject results exist') !== false, "student-detail.php must refuse publishing empty 2nd Semester");

    // Expected CGPA right now: (3*4.00 + 1.5*4.00 + 3*3.75 + 3*3.50) / 10.5 = 39.75 / 10.5 = 3.79
    $partialSum = get_student_academic_summary($st1Pk, true);
    t8_assert(abs((float)$partialSum['final_cgpa'] - 3.79) <= 0.01, "Partial CGPA must be 3.79, got {$partialSum['final_cgpa']}");
    t8_assert((int)$partialSum['published_semesters_count'] === 2, "Only 2 semesters (1st & 3rd) should be counted published, got {$partialSum['published_semesters_count']}");

    // Default /student/semester-transcript.php (no ?semester param -> consolidated)
    $defTr = t8_http($baseUrl . '/student/semester-transcript.php', $stud1Cookie);
    t8_assert($defTr['code'] === 200, "Default transcript page must return 200");
    t8_assert(strpos($defTr['body'], 'Consolidated Academic Transcript') !== false, "Default transcript must open Full 8-Semester Consolidated view");
    t8_assert(strpos($defTr['body'], 'Download Transcript (PDF)') !== false, "Transcript must display Download Transcript (PDF) button");
    t8_assert(strpos($defTr['body'], 'CSE 101') !== false && strpos($defTr['body'], 'CSE 102') !== false && strpos($defTr['body'], 'CSE 301') !== false, "Transcript must render real 1st and 3rd semester subjects");
    t8_assert(strpos($defTr['body'], '42.0') !== false && strpos($defTr['body'], '3.79') !== false, "Transcript must show 42.0 marks for lab and 3.79 cumulative CGPA");

    // Verify ALL 8 semesters rendered & missing ones show clean "Not Published Yet" with no fake marks
    $ordTitles = ['1st Semester', '2nd Semester', '3rd Semester', '4th Semester', '5th Semester', '6th Semester', '7th Semester', '8th Semester'];
    foreach ($ordTitles as $ot) {
        t8_assert(strpos($defTr['body'], $ot) !== false, "Consolidated transcript must display section for {$ot}");
    }
    $notPubOccurrences = substr_count($defTr['body'], 'No published subject results recorded for this semester yet.');
    t8_assert($notPubOccurrences === 6, "Exactly 6 missing semesters (2nd, 4th-8th) must display empty non-fabricated state, found {$notPubOccurrences}");

    // Also verify student/results.php and student/dashboard.php
    $stResultsPage = t8_http($baseUrl . '/student/results.php', $stud1Cookie);
    t8_assert($stResultsPage['code'] === 200 && strpos($stResultsPage['body'], 'Not Published Yet') !== false && strpos($stResultsPage['body'], 'download=pdf') !== false, "student/results.php must render 8-semester progression and PDF links");
    $stDashPage = t8_http($baseUrl . '/student/dashboard.php', $stud1Cookie);
    t8_assert($stDashPage['code'] === 200 && strpos($stDashPage['body'], 'semester-transcript.php?semester=all&amp;download=pdf') !== false, "student/dashboard.php quick link must target direct consolidated PDF");

    echo "   [PASS] Partial semesters (1st & 3rd), 42/50 grading, empty-publish block & consolidated 8-semester rendering verified!\n\n";

    // -------------------------------------------------------------------------
    // STEP 3: Missing 2nd Semester Import (Draft -> Published) + 4th-8th Progression
    //         Including Fail Course Credit Mechanics & Edit Recalculation
    // -------------------------------------------------------------------------
    echo "3. Testing Missing Semester Import (Draft -> Publish), Fail Credit Mechanics & 5th-8th Progression:\n";

    // Import 2nd Semester as Draft first so we prove Draft does NOT leak to student
    $sem2CsvText = implode("\n", [
        '2nd Semester, CSE 201, Data Structures and Algorithms, 3.0, 100, 82',
        '2nd Semester, CSE 202, Data Structures Lab, 1.5, 50, 39',
    ]);
    $impDraftPost = t8_http($baseUrl . '/admin/results-manager.php?student_id=' . $st1Pk, $adminCookie, [
        'csrf_token'      => $rmCsrf,
        'action'          => 'import_semester_results',
        'student_id'      => $st1Pk,
        'target_semester' => '2nd Semester',
        'publish_status'  => 'Draft',
        'update_existing' => '1',
        'reason'          => 'E2E missing 2nd sem import as Draft',
        'import_data'     => $sem2CsvText,
    ]);
    t8_assert($impDraftPost['code'] === 200, "Import Draft POST must return 200");

    // Verify 2nd Semester stays hidden from student while Draft
    $afterDraftTr = t8_http($baseUrl . '/student/semester-transcript.php?semester=all', $stud1Cookie);
    t8_assert(strpos($afterDraftTr['body'], 'Data Structures and Algorithms') === false, "Draft imported 2nd Semester must NOT appear in Student Transcript");

    // Publish 2nd Semester individually -> automatically appears while 1st & 3rd stay untouched
    $pub2Post = t8_http($baseUrl . '/admin/results-manager.php?student_id=' . $st1Pk, $adminCookie, [
        'csrf_token'    => $rmCsrf,
        'action'        => 'publish_semester',
        'student_id'    => $st1Pk,
        'semester_name' => '2nd Semester',
    ]);
    t8_assert($pub2Post['code'] === 200, "Publish 2nd Semester must return 200");
    $afterPub2Tr = t8_http($baseUrl . '/student/semester-transcript.php?semester=all', $stud1Cookie);
    t8_assert(strpos($afterPub2Tr['body'], 'CSE 201') !== false && strpos($afterPub2Tr['body'], 'Data Structures and Algorithms') !== false, "Published 2nd Semester must appear immediately on Student Transcript");
    t8_assert(strpos($afterPub2Tr['body'], 'CSE 101') !== false && strpos($afterPub2Tr['body'], 'CSE 301') !== false, "Existing 1st & 3rd Semester results must remain unchanged");

    // Import 4th Semester via real CSV file upload ($_FILES['csv_file'])
    $tempCsvPath = sys_get_temp_dir() . '/rmu_tr8_sem4_' . uniqid() . '.csv';
    file_put_contents($tempCsvPath, "Course Code,Course Name,Credit,Full Marks,Obtained Marks\nCSE 401,Operating Systems,3.0,100,80\nCSE 402,Operating Systems Lab,1.5,50,40\n");
    $impCsvFilePost = t8_http($baseUrl . '/admin/results-manager.php?student_id=' . $st1Pk, $adminCookie, [
        'csrf_token'      => $rmCsrf,
        'action'          => 'import_semester_results',
        'student_id'      => $st1Pk,
        'target_semester' => '4th Semester',
        'publish_status'  => 'Published',
        'update_existing' => '1',
        'reason'          => 'E2E 4th Sem CSV file upload',
        'csv_file'        => new CURLFile($tempCsvPath, 'text/csv', 'sem4.csv'),
    ], true);
    t8_assert($impCsvFilePost['code'] === 200, "CSV file upload import must return 200");

    // Import 5th to 8th Semesters (include a Fail course CSE 602 in 6th Semester: 30/100 -> F 0.00)
    $sem5to8Csv = implode("\n", [
        '5th Semester, CSE 501, Software Engineering, 3.0, 100, 76',
        '6th Semester, CSE 601, Computer Networks, 3.0, 100, 74',
        '6th Semester, CSE 602, Theory of Computation, 3.0, 100, 30',
        '7th Semester, CSE 701, Artificial Intelligence, 3.0, 100, 82',
        '8th Semester, CSE 801, Senior Capstone Thesis, 4.0, 100, 86',
    ]);
    $imp5to8Post = t8_http($baseUrl . '/admin/results-manager.php?student_id=' . $st1Pk, $adminCookie, [
        'csrf_token'      => $rmCsrf,
        'action'          => 'import_semester_results',
        'student_id'      => $st1Pk,
        'target_semester' => 'multi',
        'publish_status'  => 'Published',
        'update_existing' => '1',
        'reason'          => 'E2E 5th-8th full progression',
        'import_data'     => $sem5to8Csv,
    ]);
    t8_assert($imp5to8Post['code'] === 200, "Multi-semester import 5th-8th must return 200");

    $sumWithFail = get_student_academic_summary($st1Pk, true);
    // Registered credits = 3+1.5 (1) + 3+1.5 (2) + 3+3 (3) + 3+1.5 (4) + 3 (5) + 6 (6) + 3 (7) + 4 (8) = 35.5
    // Earned credits = 35.5 - 3.0 = 32.5
    // Weighted pts = (3*4 + 1.5*4) + (3*4 + 1.5*3.75) + (3*3.75 + 3*3.5) + (3*4 + 1.5*4) + (3*3.75) + (3*3.5 + 3*0) + (3*4) + (4*4)
    //              = 18 + 17.625 + 21.75 + 18 + 11.25 + 10.5 + 12 + 16 = 125.125
    // CGPA = 125.125 / 35.5 = 3.5246 -> 3.52
    t8_assert(abs((float)$sumWithFail['total_registered_credits'] - 35.5) < 0.01, "Total Registered Credits must be 35.5, got {$sumWithFail['total_registered_credits']}");
    t8_assert(abs((float)$sumWithFail['total_earned_credits'] - 32.5) < 0.01, "Total Earned Credits excluding Fail must be 32.5, got {$sumWithFail['total_earned_credits']}");
    t8_assert(abs((float)$sumWithFail['final_cgpa'] - 3.52) <= 0.01, "Credit-weighted CGPA with fail course must be 3.52, got {$sumWithFail['final_cgpa']}");
    t8_assert(strpos((string)$sumWithFail['overall_academic_status'], 'Carry') !== false, "Status with failed course should indicate Carry/Retake, got {$sumWithFail['overall_academic_status']}");

    // Now edit CSE 602 to passing 72/100 -> A- (3.50) via POST /admin/results-manager.php
    $cse602Id = (int)$db->query("SELECT id FROM student_results WHERE student_id = {$st1Pk} AND course_code = 'CSE 602' LIMIT 1")->fetchColumn();
    t8_assert($cse602Id > 0, "CSE 602 ID must exist");
    t8_http($baseUrl . '/admin/results-manager.php?student_id=' . $st1Pk, $adminCookie, [
        'csrf_token'        => $rmCsrf,
        'action'            => 'edit_result',
        'student_id'        => $st1Pk,
        'result_id'         => $cse602Id,
        'semester'          => '6th Semester',
        'course_code'       => 'CSE 602',
        'course_name'       => 'Theory of Computation',
        'credit'            => '3.0',
        'full_marks'        => '100',
        'marks'             => '72',
        'exam_term'         => 'Retake Clearance Exam',
        'publish_status'    => 'Published',
        'correction_reason' => 'E2E Pass update & CGPA recalculation verification',
    ]);

    $sumAllPass = get_student_academic_summary($st1Pk, true);
    // New Weighted pts = 125.125 + 3*3.5 = 135.625; 135.625 / 35.5 = 3.8204 -> 3.82
    t8_assert(abs((float)$sumAllPass['total_registered_credits'] - 35.5) < 0.01, "Total Registered Credits must stay 35.5");
    t8_assert(abs((float)$sumAllPass['total_earned_credits'] - 35.5) < 0.01, "Total Earned Credits must now be 35.5, got {$sumAllPass['total_earned_credits']}");
    t8_assert(abs((float)$sumAllPass['final_cgpa'] - 3.82) <= 0.01, "Recalculated CGPA after edit must be 3.82, got {$sumAllPass['final_cgpa']}");
    t8_assert((int)$sumAllPass['published_semesters_count'] === 8, "All 8 semesters must now be published");
    t8_assert($sumAllPass['overall_academic_status'] === 'All Semesters Completed', "Status must update to 'All Semesters Completed', got {$sumAllPass['overall_academic_status']}");

    $full8Html = t8_http($baseUrl . '/student/semester-transcript.php?semester=all', $stud1Cookie);
    t8_assert($full8Html['code'] === 200 && strpos($full8Html['body'], 'Senior Capstone Thesis') !== false && strpos($full8Html['body'], '3.82') !== false, "Student consolidated transcript must reflect all 8 semesters and 3.82 CGPA");
    echo "   [PASS] Draft-to-Publish, CSV file upload, 1st-8th progression, fail/pass credit math & live CGPA recalculation verified!\n\n";

    // -------------------------------------------------------------------------
    // STEP 4: Genuine Binary PDF Download (?download=pdf) & Stream Content Verification
    // -------------------------------------------------------------------------
    echo "4. Testing Genuine PDF Binary Generation & Multi-Page Content Verification:\n";
    $pdfResp = t8_http($baseUrl . '/student/semester-transcript.php?semester=all&download=pdf', $stud1Cookie);
    t8_assert($pdfResp['code'] === 200, "Consolidated PDF download must return HTTP 200");
    t8_assert(stripos($pdfResp['headers']['content-type'] ?? '', 'application/pdf') !== false, "Content-Type must be application/pdf, got " . ($pdfResp['headers']['content-type'] ?? 'none'));
    t8_assert(stripos($pdfResp['headers']['content-disposition'] ?? '', '.pdf') !== false, "Content-Disposition must specify .pdf filename");
    t8_assert(strpos($pdfResp['body'], '%PDF-1.4') === 0, "PDF binary must start with %PDF-1.4");
    t8_assert(strpos($pdfResp['body'], '%%EOF') !== false, "PDF binary must terminate with %%EOF");

    $pdfText = t8_extract_pdf_text($pdfResp['body']);
    $pdfChecks = [
        'RABINDRA MAITREE UNIVERSITY',
        'CONSOLIDATED ACADEMIC TRANSCRIPT',
        "Tariqul Transcript Test {$suffix}",
        $st1Code,
        'Semester 1: 1st Semester',
        'Semester 4: 4th Semester',
        'Semester 8: 8th Semester',
        'CSE 101',
        'CSE 102',
        '42.0',
        'Senior Capstone Thesis',
        '3.82 / 4.00',
        'Registered Credits: 35.5 Cr',
        'Earned Credits: 35.5 Cr',
        'Controller of Examinations',
        'Office of the Registrar',
    ];
    foreach ($pdfChecks as $chk) {
        t8_assert(strpos($pdfText, $chk) !== false, "Extracted PDF stream missing expected content: '{$chk}'");
    }

    // Single-semester PDF download test
    $pdfSem2Resp = t8_http($baseUrl . '/student/semester-transcript.php?semester=' . urlencode('2nd Semester') . '&download=pdf', $stud1Cookie);
    t8_assert($pdfSem2Resp['code'] === 200 && strpos($pdfSem2Resp['body'], '%PDF-1.4') === 0, "Single-semester PDF download must succeed cleanly");
    $pdfSem2Text = t8_extract_pdf_text($pdfSem2Resp['body']);
    t8_assert(strpos($pdfSem2Text, 'CSE 201') !== false && strpos($pdfSem2Text, 'CSE 801') === false, "Single-semester PDF must contain only requested semester subjects");
    echo "   [PASS] Consolidated & Single-Semester PDF binaries verified (%PDF-1.4, decompressed streams contain all 8 semesters, marks, credits, CGPA, signatures)!\n\n";

    // -------------------------------------------------------------------------
    // STEP 5: Cross-Student IDOR, Result Suspension 403, RBAC & B.Ed 3-Sem Check
    // -------------------------------------------------------------------------
    echo "5. Testing IDOR Prevention, Suspended PDF 403 Block, RBAC Editor Denial & 3-Sem B.Ed Structure:\n";
    // Cross-student IDOR: stud1 passes ?student_id=4 -> must STILL receive stud1's PDF, NEVER Student 4!
    $idorPdf = t8_http($baseUrl . '/student/semester-transcript.php?student_id=4&semester=all&download=pdf', $stud1Cookie);
    t8_assert($idorPdf['code'] === 200 && strpos($idorPdf['body'], '%PDF-1.4') === 0, "Student transcript request should safely scope to logged-in student");
    $idorText = t8_extract_pdf_text($idorPdf['body']);
    t8_assert(strpos($idorText, $st1Code) !== false && strpos($idorText, 'RMU2601001') === false, "IDOR guard must ignore ?student_id=4 when not logged in as admin");

    // Suspended result portal access -> PDF must return HTTP 403
    $sdPage2 = t8_http($baseUrl . '/admin/student-detail.php?id=' . $st1Pk, $adminCookie);
    t8_http($baseUrl . '/admin/student-detail.php?id=' . $st1Pk, $adminCookie, [
        'csrf_token' => t8_csrf($sdPage2['body']),
        'action'     => 'suspend_result_access',
        'reason'     => 'E2E PDF Suspension Check',
    ]);
    $susPdf = t8_http($baseUrl . '/student/semester-transcript.php?semester=all&download=pdf', $stud1Cookie);
    t8_assert($susPdf['code'] === 403 && strpos($susPdf['body'], '%PDF') === false && strpos($susPdf['body'], 'Result Access Suspended') !== false, "Suspended student PDF endpoint must return HTTP 403");
    // Restore result access
    t8_http($baseUrl . '/admin/student-detail.php?id=' . $st1Pk, $adminCookie, [
        'csrf_token' => t8_csrf($sdPage2['body']),
        'action'     => 'restore_result_access',
    ]);

    // RBAC: Editor account must be blocked from import_semester_results
    $edUser = 'e2e_tr8_ed_' . $suffix;
    $edPass = 'EdPass!' . $suffix;
    $db->prepare("INSERT INTO users (username, full_name, email, password_hash, role, status) VALUES (?, 'TR8 Temp Editor', ?, ?, 'editor', 'active')")
       ->execute([$edUser, "tr8.editor.{$suffix}@rmu.test", password_hash($edPass, PASSWORD_DEFAULT)]);
    $tempEditorUserId = (int)$db->lastInsertId();
    $edLogGet = t8_http($baseUrl . '/admin/login.php', $editorCookie);
    t8_http($baseUrl . '/admin/login.php', $editorCookie, [
        'csrf_token' => t8_csrf($edLogGet['body']),
        'username'   => $edUser,
        'password'   => $edPass,
    ]);
    $edRmGet = t8_http($baseUrl . '/admin/results-manager.php?student_id=' . $st1Pk, $editorCookie);
    $beforeEdCount = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$st1Pk}")->fetchColumn();
    $edImpPost = t8_http($baseUrl . '/admin/results-manager.php?student_id=' . $st1Pk, $editorCookie, [
        'csrf_token'      => t8_csrf($edRmGet['body']),
        'action'          => 'import_semester_results',
        'student_id'      => $st1Pk,
        'target_semester' => '1st Semester',
        'import_data'     => '1st Semester, HACK 999, Unauthorized Subject, 3.0, 100, 99',
    ]);
    $afterEdCount = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$st1Pk}")->fetchColumn();
    t8_assert($afterEdCount === $beforeEdCount, "Non-manager editor must be blocked from importing semester results");

    // B.Ed 3-Semester Student: verify 1st..3rd Semester ONLY in both HTML and PDF
    t8_http($baseUrl . '/admin/results-manager.php?student_id=' . $st2Pk, $adminCookie, [
        'csrf_token'      => $rmCsrf,
        'action'          => 'import_semester_results',
        'student_id'      => $st2Pk,
        'target_semester' => '1st Semester',
        'publish_status'  => 'Published',
        'import_data'     => '1st Semester, EDU 101, Foundations of Education, 3.0, 100, 82',
    ]);
    $bedTr = t8_http($baseUrl . '/student/semester-transcript.php?semester=all', $stud2Cookie);
    t8_assert(strpos($bedTr['body'], '3rd Semester') !== false && strpos($bedTr['body'], '4th Semester') === false && strpos($bedTr['body'], '8th Semester') === false, "B.Ed 3-semester transcript must only display Semesters 1..3");
    $bedPdf = t8_http($baseUrl . '/student/semester-transcript.php?semester=all&download=pdf', $stud2Cookie);
    $bedPdfText = t8_extract_pdf_text($bedPdf['body']);
    t8_assert(strpos($bedPdfText, 'Semester 3: 3rd Semester') !== false && strpos($bedPdfText, 'Semester 4:') === false && strpos($bedPdfText, 'Semester 8:') === false, "B.Ed 3-semester PDF must only contain Semesters 1..3");

    echo "   [PASS] IDOR protection, Suspended 403 block, RBAC editor denial, and B.Ed 3-semester boundaries verified!\n\n";

} catch (Throwable $e) {
    $exitCode = 1;
    fwrite(STDERR, "\n[FAIL] " . $e->getMessage() . "\n");
} finally {
    foreach ($createdStudentPks as $pk) {
        try {
            permanently_delete_student_record((int)$pk, 1, 'E2E 8-Semester Transcript test cleanup');
        } catch (Throwable $ignore) {}
    }
    if ($tempEditorUserId > 0) {
        try {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$tempEditorUserId]);
        } catch (Throwable $ignore) {}
    }
    try {
        $db->prepare("DELETE FROM audit_logs WHERE details LIKE ?")->execute(["%{$suffix}%"]);
    } catch (Throwable $ignore) {}
    if ($tempCsvPath !== '' && file_exists($tempCsvPath)) {
        @unlink($tempCsvPath);
    }
    foreach ([$adminCookie, $editorCookie, $stud1Cookie, $stud2Cookie] as $cf) {
        if (file_exists($cf)) {
            @unlink($cf);
        }
    }

    // Verify existing seed students intact
    $seedAfter4 = $db->query("SELECT id, student_id, cgpa, credits_completed, status, results_visible, result_access_status FROM students WHERE id = 4 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $seedAfter451 = $db->query("SELECT id, student_id, cgpa, credits_completed, status, results_visible, result_access_status FROM students WHERE id = 451 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $seedResAfter4 = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = 4")->fetchColumn();
    if ($seedBefore4 !== $seedAfter4 || $seedBefore451 !== $seedAfter451 || $seedResBefore4 !== $seedResAfter4) {
        $exitCode = 1;
        fwrite(STDERR, "[FAIL] Existing seed student records (id=4 or id=451) altered!\n");
    } elseif ($exitCode === 0) {
        echo "ALL 8-SEMESTER ACADEMIC TRANSCRIPT E2E TESTS PASSED CLEANLY! (Seed data intact & test data cleaned)\n";
    }
}

exit($exitCode);
