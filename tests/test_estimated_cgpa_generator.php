<?php
/**
 * Automated End-to-End Test for One-Click CGPA Update & Estimated Semester Result Generator
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$baseUrl = 'http://127.0.0.1:8000';
$db = getDb();

echo "======================================================================\n";
echo "  TESTING ONE-CLICK CGPA UPDATE & ESTIMATED RESULT GENERATOR\n";
echo "======================================================================\n\n";

// --- TEST 1: Bachelor's Degree (8 Semesters) CGPA Update & Auto-Generation ---
echo "1. Testing Bachelor's Degree (8 Semesters) with Target CGPA = 3.85:\n";
$cseProgId = (int)$db->query("SELECT id FROM programs WHERE slug = 'bsc-in-cse'")->fetchColumn() ?: 1;
$cseDeptId = (int)$db->query("SELECT id FROM departments WHERE slug = 'dept-cse'")->fetchColumn() ?: 1;

$bachelorStudentCode = 'RMU-TEST-BACH-' . rand(1000, 9999);
$bachelorEmail = strtolower($bachelorStudentCode) . '@rmu.ac.bd';

$db->prepare("DELETE FROM students WHERE student_id = ?")->execute([$bachelorStudentCode]);
$db->prepare("
    INSERT INTO students 
    (student_id, full_name, email, phone, password_hash, program_id, department_id, batch, current_year, current_semester, cgpa, credits_completed, total_credits_required, status)
    VALUES (?, 'Bachelor Test Student', ?, '01711223344', ?, ?, ?, '14th Batch (2026-2030)', '1st Year', '1st Semester', 0.00, 0, 140, 'Active')
")->execute([$bachelorStudentCode, $bachelorEmail, password_hash('Pass123', PASSWORD_DEFAULT), $cseProgId, $cseDeptId]);
$bachelorPk = (int)$db->lastInsertId();

$genRes = generate_estimated_student_results($bachelorPk, 3.85, true, 1);
echo "   Generator Result: " . json_encode($genRes) . "\n";
assert(!empty($genRes['success']), "Generator must succeed for Bachelor");
assert($genRes['total_semesters'] === 8, "Bachelor degree must produce 8 semesters");
assert($genRes['inserted_courses'] >= 30, "Bachelor degree must produce at least 30 subjects");

// Verify in DB
$bachelorDbStudent = $db->query("SELECT * FROM students WHERE id = {$bachelorPk}")->fetch(PDO::FETCH_ASSOC);
$bachelorDbCgpa = (float)$bachelorDbStudent['cgpa'];
echo "   Stored Student CGPA: {$bachelorDbCgpa}\n";
assert(abs($bachelorDbCgpa - 3.85) <= 0.01, "Stored CGPA must match 3.85 within 0.01");

$bachelorResults = $db->query("SELECT * FROM student_results WHERE student_id = {$bachelorPk}")->fetchAll(PDO::FETCH_ASSOC);
$estCount = 0;
foreach ($bachelorResults as $r) {
    if ((int)$r['is_estimated'] === 1 && $r['result_type'] === 'Estimated') {
        $estCount++;
    }
    assert(strpos($r['correction_reason'], 'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT') !== false, "Reason must contain mandatory label");
}
assert($estCount === count($bachelorResults), "All generated results must have is_estimated = 1");
echo "   [PASS] Bachelor's 8 Semesters generated with exact CGPA {$bachelorDbCgpa} and is_estimated = 1!\n\n";

// --- TEST 2: Master's Degree (4 Semesters) CGPA Update & Auto-Generation ---
echo "2. Testing Master's Degree (4 Semesters) with Target CGPA = 3.72:\n";
$mbaProgId = (int)$db->query("SELECT id FROM programs WHERE slug = 'mba'")->fetchColumn() ?: 14;
$mbaDeptId = (int)$db->query("SELECT id FROM departments WHERE slug = 'dept-business-administration'")->fetchColumn() ?: 10;

$masterStudentCode = 'RMU-TEST-MAST-' . rand(1000, 9999);
$masterEmail = strtolower($masterStudentCode) . '@rmu.ac.bd';

$db->prepare("DELETE FROM students WHERE student_id = ?")->execute([$masterStudentCode]);
$db->prepare("
    INSERT INTO students 
    (student_id, full_name, email, phone, password_hash, program_id, department_id, batch, current_year, current_semester, cgpa, credits_completed, total_credits_required, status)
    VALUES (?, 'Master Test Student', ?, '01711223355', ?, ?, ?, 'MBA 2026 Batch', '1st Year', '1st Semester', 0.00, 0, 60, 'Active')
")->execute([$masterStudentCode, $masterEmail, password_hash('Pass123', PASSWORD_DEFAULT), $mbaProgId, $mbaDeptId]);
$masterPk = (int)$db->lastInsertId();

$genResMaster = generate_estimated_student_results($masterPk, 3.72, true, 1);
echo "   Generator Result: " . json_encode($genResMaster) . "\n";
assert(!empty($genResMaster['success']), "Generator must succeed for Master");
assert($genResMaster['total_semesters'] === 4, "Master degree must produce exactly 4 semesters");

// Verify distinct semesters in DB
$masterSemCount = (int)$db->query("SELECT COUNT(DISTINCT semester) FROM student_results WHERE student_id = {$masterPk}")->fetchColumn();
assert($masterSemCount === 4, "DB must contain exactly 4 distinct semesters for Master");

$masterDbStudent = $db->query("SELECT * FROM students WHERE id = {$masterPk}")->fetch(PDO::FETCH_ASSOC);
$masterDbCgpa = (float)$masterDbStudent['cgpa'];
echo "   Stored Master Student CGPA: {$masterDbCgpa}\n";
assert(abs($masterDbCgpa - 3.72) <= 0.01, "Stored CGPA must match 3.72 within 0.01");
echo "   [PASS] Master's 4 Semesters generated with exact CGPA {$masterDbCgpa}!\n\n";

// --- TEST 3: Safe Re-Generation without Duplicates ---
echo "3. Testing CGPA Re-update & Safe Regeneration (Target CGPA = 3.55):\n";
$initialRowCount = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$bachelorPk}")->fetchColumn();
$genRes2 = generate_estimated_student_results($bachelorPk, 3.55, true, 1);
$newRowCount = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE student_id = {$bachelorPk}")->fetchColumn();
assert($newRowCount === $initialRowCount, "Row count must remain consistent without duplicate entries");

$updatedStudent = $db->query("SELECT * FROM students WHERE id = {$bachelorPk}")->fetch(PDO::FETCH_ASSOC);
$updatedCgpa = (float)$updatedStudent['cgpa'];
echo "   Re-generated Student CGPA: {$updatedCgpa}\n";
assert(abs($updatedCgpa - 3.55) <= 0.01, "CGPA must be updated to 3.55 within 0.01");
echo "   [PASS] CGPA safely regenerated without duplicate records!\n\n";

// --- TEST 4: Administrator Verification Workflow (Replacing Estimates with Official) ---
echo "4. Testing Admin Verification Workflow (Verify Semester 1 as Official):\n";
$verifiedCount = verify_semester_results_as_official($bachelorPk, '1st Semester', 1);
echo "   Verified Course Count in 1st Semester: {$verifiedCount}\n";
assert($verifiedCount > 0, "Must verify course results in 1st Semester");

// Verify in DB that Semester 1 has is_estimated = 0 and result_type = 'Verified'
$sem1Results = $db->query("SELECT * FROM student_results WHERE student_id = {$bachelorPk} AND semester = '1st Semester'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($sem1Results as $r) {
    assert((int)$r['is_estimated'] === 0, "Verified course must have is_estimated = 0");
    assert($r['result_type'] === 'Verified', "Verified course must have result_type = 'Verified'");
}

// Verify that other semesters remain is_estimated = 1
$sem2Results = $db->query("SELECT * FROM student_results WHERE student_id = {$bachelorPk} AND semester = '2nd Semester'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($sem2Results as $r) {
    assert((int)$r['is_estimated'] === 1, "Unverified course must remain is_estimated = 1");
    assert($r['result_type'] === 'Estimated', "Unverified course must remain result_type = 'Estimated'");
}
echo "   [PASS] Admin Verification Workflow verified 1st Semester while preserving other estimated semesters!\n\n";

// --- TEST 5: Student Portal Display & Mandatory Disclaimer Verification ---
echo "5. Testing Student Portal Display & Mandatory Disclaimers:\n";
$cookieFile = sys_get_temp_dir() . '/cookie_stud_est.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// Login as bachelor student
$ch = curl_init($baseUrl . '/student/login.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $cookieFile,
    CURLOPT_COOKIEFILE     => $cookieFile,
    CURLOPT_FOLLOWLOCATION => true
]);
$loginGet = curl_exec($ch);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginGet, $m);
$csrf = $m[1] ?? '';

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'student_id' => $bachelorStudentCode,
    'password'   => 'Pass123'
]));
$loginRes = curl_exec($ch);
curl_close($ch);

// Fetch /student/results.php
$ch = curl_init($baseUrl . '/student/results.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE     => $cookieFile
]);
$portalHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Student Portal HTTP Status: {$httpCode}\n";
assert($httpCode === 200, "Student results page must return 200");

// Check mandatory disclaimer label
$mandatoryDisclaimer = 'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT';
assert(strpos($portalHtml, $mandatoryDisclaimer) !== false, "Student portal MUST display mandatory disclaimer: '{$mandatoryDisclaimer}'");
assert(strpos($portalHtml, 'ESTIMATED') !== false, "Student portal must display ESTIMATED tag");
assert(strpos($portalHtml, 'CSE 101') !== false, "Student portal must display subject codes");
assert(strpos($portalHtml, '3.55') !== false, "Student portal must display overall CGPA");
echo "   [PASS] Student Portal displays all estimated results with mandatory disclaimer prominently shown!\n\n";

// Fetch /student/semester-transcript.php?semester=all
$ch = curl_init($baseUrl . '/student/semester-transcript.php?semester=all');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE     => $cookieFile
]);
$transcriptHtml = curl_exec($ch);
$trHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert($trHttpCode === 200, "Transcript must return 200");
assert(strpos($transcriptHtml, $mandatoryDisclaimer) !== false, "Transcript MUST display mandatory disclaimer: '{$mandatoryDisclaimer}'");
echo "   [PASS] Academic Transcript displays mandatory disclaimer banner!\n\n";

// --- Clean up test records ---
$db->prepare("DELETE FROM student_results WHERE student_id IN (?, ?)")->execute([$bachelorPk, $masterPk]);
$db->prepare("DELETE FROM student_notifications WHERE student_id IN (?, ?)")->execute([$bachelorPk, $masterPk]);
$db->prepare("DELETE FROM students WHERE id IN (?, ?)")->execute([$bachelorPk, $masterPk]);
@unlink($cookieFile);

echo "======================================================================\n";
echo ">>> ALL ESTIMATED CGPA & RESULT GENERATOR TESTS PASSED (100%)! <<<\n";
echo "======================================================================\n";
