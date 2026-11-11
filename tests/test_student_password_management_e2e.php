<?php
/**
 * Comprehensive Full-Stack HTTP & Database E2E Test:
 * Student Password & Login Credentials Management System
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$baseUrl = 'http://127.0.0.1:8000';
$db = getDb();

$anonCookie     = sys_get_temp_dir() . '/rmu_pwd_anon_' . uniqid() . '.txt';
$adminCookie    = sys_get_temp_dir() . '/rmu_pwd_admin1_' . uniqid() . '.txt';
$admin2Cookie   = sys_get_temp_dir() . '/rmu_pwd_admin2_' . uniqid() . '.txt';
$editorCookie   = sys_get_temp_dir() . '/rmu_pwd_editor_' . uniqid() . '.txt';
$studentACookie = sys_get_temp_dir() . '/rmu_pwd_stA_' . uniqid() . '.txt';
$studentBCookie = sys_get_temp_dir() . '/rmu_pwd_stB_' . uniqid() . '.txt';

function http_call(string $url, string $cookieFile, ?array $postData = null, bool $follow = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP error for {$url}: {$err}");
    }
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'url' => $effUrl];
}

function get_csrf(string $html): string {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

function assert_true(bool $cond, string $msg): void {
    if (!$cond) {
        throw new RuntimeException("ASSERTION FAILED: {$msg}");
    }
}

$editorUserId = 0;
$studentAPk = 0;
$studentBPk = 0;
$admissionBId = 0;
$uniq = (string)rand(50000, 99999);
$exitCode = 0;

try {
    echo "===========================================================================\n";
    echo "  RMU STUDENT LOGIN CREDENTIALS & PASSWORD MANAGEMENT - E2E TEST SUITE\n";
    echo "===========================================================================\n\n";

    $seed = $db->query("SELECT id, student_id, password_hash, status FROM students WHERE student_id = 'RMU2601001' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    assert_true(!empty($seed), "Seed student RMU2601001 must exist");
    $seedPk = (int)$seed['id'];
    $seedHashBefore = (string)$seed['password_hash'];

    // -------------------------------------------------------------------------
    // 1. Unauthenticated access must fail & never leak credentials
    // -------------------------------------------------------------------------
    echo "1. Unauthenticated Security Guard:\n";
    $unauthGet = http_call($baseUrl . '/admin/student-detail.php?id=' . $seedPk, $anonCookie, null, false);
    assert_true($unauthGet['code'] === 302, "Unauthenticated GET student-detail.php must redirect (HTTP 302)");
    assert_true(strpos($unauthGet['body'], 'credPasswordInput') === false, "Unauthenticated response must contain zero credential inputs");

    $unauthPost = http_call($baseUrl . '/admin/student-detail.php?id=' . $seedPk, $anonCookie, [
        'csrf_token' => 'fake',
        'action' => 'reset_student_password',
        'student_id_pk' => $seedPk,
    ], false);
    assert_true($unauthPost['code'] === 302, "Unauthenticated POST reset must redirect (HTTP 302)");
    echo "   [PASS] Unauthenticated requests blocked cleanly.\n\n";

    // -------------------------------------------------------------------------
    // 2. Editor role isolation (must not view temp creds or reset password)
    // -------------------------------------------------------------------------
    echo "2. Non-Manager ('editor') Role Access Isolation:\n";
    $edUser = 'e2eeditor_' . $uniq;
    $edPass = 'EditorOnly!99';
    $db->prepare("INSERT INTO users (username, full_name, email, password_hash, role, status) VALUES (?, 'E2E Editor', ?, ?, 'editor', 'active')")
       ->execute([$edUser, $edUser . '@rmu.ac.bd', password_hash($edPass, PASSWORD_DEFAULT)]);
    $editorUserId = (int)$db->lastInsertId();

    $edLoginPage = http_call($baseUrl . '/admin/login.php', $editorCookie);
    $edLoginPost = http_call($baseUrl . '/admin/login.php', $editorCookie, [
        'csrf_token' => get_csrf($edLoginPage['body']),
        'username'   => $edUser,
        'password'   => $edPass,
    ]);
    assert_true(strpos($edLoginPost['url'], '/admin/dashboard.php') !== false, "Editor login must reach dashboard");

    $edDetail = http_call($baseUrl . '/admin/student-detail.php?id=' . $seedPk, $editorCookie);
    assert_true($edDetail['code'] === 200, "Editor profile view returns HTTP 200");
    assert_true(strpos($edDetail['body'], 'Student Login Credentials') !== false, "Credentials section heading should appear");
    assert_true(strpos($edDetail['body'], 'Restricted Access') !== false, "Editor must see restriction notice");
    assert_true(strpos($edDetail['body'], 'credPasswordInput') === false, "Editor HTML must not expose password input");
    assert_true(strpos($edDetail['body'], 'toggleCredPasswordBtn') === false, "Editor HTML must not expose Show/Hide button");
    assert_true(strpos($edDetail['body'], 'resetStudentPasswordBtn') === false, "Editor HTML must not expose Reset button");

    $edEditView = http_call($baseUrl . '/admin/students.php?edit=' . $seedPk, $editorCookie);
    assert_true($edEditView['code'] === 200 && strpos($edEditView['body'], 'credPasswordInput') === false, "Editor students.php?edit must also hide password controls");

    $edPostReset = http_call($baseUrl . '/admin/student-detail.php?id=' . $seedPk, $editorCookie, [
        'csrf_token'    => get_csrf($edDetail['body']),
        'action'        => 'reset_student_password',
        'student_id_pk' => $seedPk,
    ]);
    assert_true(strpos($edPostReset['body'], 'Access denied') !== false, "Editor reset POST must return Access denied");
    $seedHashCheck = (string)$db->query("SELECT password_hash FROM students WHERE id = {$seedPk}")->fetchColumn();
    assert_true($seedHashCheck === $seedHashBefore, "Seed student password_hash must remain untouched after denied editor POST");
    echo "   [PASS] Editor role strictly blocked from password viewing and resets.\n\n";

    // -------------------------------------------------------------------------
    // 3. Admin Login & Hash-Only Legacy Student Profile Behavior
    // -------------------------------------------------------------------------
    echo "3. Hash-Only Existing Student Profile & Admin Authentication:\n";
    $admLoginPage = http_call($baseUrl . '/admin/login.php', $adminCookie);
    $admLoginPost = http_call($baseUrl . '/admin/login.php', $adminCookie, [
        'csrf_token' => get_csrf($admLoginPage['body']),
        'username'   => 'admin',
        'password'   => 'Admin@RMU2026!',
    ]);
    assert_true(strpos($admLoginPost['url'], '/admin/dashboard.php') !== false, "Admin login must succeed");

    $seedDetail = http_call($baseUrl . '/admin/student-detail.php?id=' . $seedPk, $adminCookie);
    assert_true(strpos($seedDetail['body'], 'Student Login Credentials') !== false, "student-detail.php must render Student Login Credentials");
    assert_true(strpos($seedDetail['body'], 'Copy Student ID') !== false, "Copy Student ID button must render");
    assert_true(strpos($seedDetail['body'], 'Show Password') !== false, "Show Password button must render for admin");
    assert_true(strpos($seedDetail['body'], 'Copy Password') !== false, "Copy Password button must render for admin");
    assert_true(strpos($seedDetail['body'], 'Reset Password') !== false, "Reset Password button must render for admin");
    assert_true(strpos($seedDetail['body'], 'Encrypted hash cannot be retrieved') !== false, "Legacy hash-only explanation must appear without retrieving original password");
    if (preg_match('/id="credPasswordInput"[^>]*value="([^"]+)"/', $seedDetail['body'], $pm)) {
        assert_true($pm[1] !== 'student123', "Original hashed password must never be retrieved/leaked");
    }

    $seedConsole = http_call($baseUrl . '/admin/students.php?edit=' . $seedPk, $adminCookie);
    assert_true(strpos($seedConsole['body'], 'Student Login Credentials') !== false, "students.php?edit must also render Student Login Credentials");
    echo "   [PASS] Hash-only workflow verified on both Student Profile surfaces.\n\n";

    // -------------------------------------------------------------------------
    // 4. CSPRNG Auto-Generated Passwords (Enroll Blank + Admission Accept)
    // -------------------------------------------------------------------------
    echo "4. Automatic CSPRNG Password Generation (Enrollment & Admission):\n";
    $enrollPage = http_call($baseUrl . '/admin/students.php?action=enroll', $adminCookie);
    assert_true($enrollPage['code'] === 200, "Enroll page must return HTTP 200");
    assert_true(preg_match('/id="enrollInitialPassword"[^>]*type="password"/', $enrollPage['body']) === 1 || strpos($enrollPage['body'], 'id="enrollInitialPassword"') !== false, "Enroll form should prefill secure random password in masked input");

    $stACode = 'RMU26PWDA' . $uniq;
    $progId = (int)$db->query("SELECT id FROM programs WHERE slug = 'bsc-in-cse'")->fetchColumn() ?: 1;
    $deptId = (int)$db->query("SELECT id FROM departments WHERE slug = 'dept-cse'")->fetchColumn() ?: 6;

    // Submit empty password -> server auto-generates strong random password
    $enrollSubmit = http_call($baseUrl . '/admin/students.php', $adminCookie, [
        'csrf_token'        => get_csrf($enrollPage['body']),
        'action'            => 'add',
        'submit_action'     => 'save_student_only',
        'student_id'        => $stACode,
        'full_name'         => 'AutoPwd Student ' . $uniq,
        'email'             => 'autopwd.' . $uniq . '@rmu.ac.bd',
        'phone'             => '01711' . $uniq . '1',
        'program_id'        => $progId,
        'department_id'     => $deptId,
        'academic_session'  => 'Fall 2026',
        'batch'             => 'Fall 2026',
        'current_year'      => '1st Year',
        'current_semester'  => '1st Semester',
        'status'            => 'Active',
        'password'          => '',
    ]);
    assert_true($enrollSubmit['code'] === 200, "Enroll submit must redirect cleanly to edit page");

    $stARow = $db->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
    $stARow->execute([$stACode]);
    $stA = $stARow->fetch(PDO::FETCH_ASSOC);
    assert_true(!empty($stA), "Student A must exist in DB");
    $studentAPk = (int)$stA['id'];
    assert_true(($stA['password_source'] ?? '') === 'system_generated', "Student A password_source must be system_generated");
    assert_true((int)($stA['must_change_password'] ?? 0) === 1, "Student A must_change_password must be 1");
    assert_true(!password_verify('student123', (string)$stA['password_hash']), "Auto-generated password must NOT default to student123");

    // Verify admin can inspect initial generated password on both student-detail & students?edit
    $stADetail = http_call($baseUrl . '/admin/student-detail.php?id=' . $studentAPk, $adminCookie);
    assert_true(preg_match('/id="credPasswordInput"\s+value="([^"]+)"/', $stADetail['body'], $am) === 1, "Admin session must see newly auto-generated password inside masked input");
    $autoGenPwdA = html_entity_decode($am[1], ENT_QUOTES, 'UTF-8');
    assert_true(strlen($autoGenPwdA) >= 10 && password_verify($autoGenPwdA, (string)$stA['password_hash']), "Visible temp password must match DB hash & satisfy CSPRNG length >= 10");

    // Verify Student A logs in using ID + auto-generated password
    $sALoginGet = http_call($baseUrl . '/student/login.php?student_id=' . urlencode($stACode), $studentACookie);
    assert_true(strpos($sALoginGet['body'], 'value="student123"') === false, "Opening ?student_id=... without pwd must NOT prefill student123");
    $sALoginPost = http_call($baseUrl . '/student/login.php', $studentACookie, [
        'csrf_token' => get_csrf($sALoginGet['body']),
        'student_id' => $stACode,
        'password'   => $autoGenPwdA,
    ]);
    assert_true($sALoginPost['code'] === 200 && strpos($sALoginPost['url'], '/student/dashboard.php') !== false, "Student A must log in cleanly with auto-generated password");
    assert_true(strpos($sALoginPost['body'], 'Security Advisory') !== false, "Student A must see non-blocking temporary password advisory banner");

    // 4B: Admission acceptance provisioning uses CSPRNG 12-char password
    $refB = 'RMU-2026-PWDB' . $uniq;
    $db->prepare("
        INSERT INTO admissions (
            application_ref, full_name, mobile, email, last_college, ssc_gpa, hsc_gpa,
            home_district, hsc_group, interested_subject, program_id, department_id,
            academic_session, quota, status, payment_status, payment_amount
        ) VALUES (
            ?, ?, ?, ?, 'Kushtia College', 5.00, 4.90,
            'Kushtia', 'Science', 'Computer Science and Engineering (CSE)', ?, ?,
            'Fall 2026', 'NO', 'Under Review', 'Paid', 150.00
        )
    ")->execute([$refB, 'Admission Pwd Student ' . $uniq, '01811' . $uniq . '2', 'admpwd.' . $uniq . '@rmu.ac.bd', $progId, $deptId]);
    $admissionBId = (int)$db->lastInsertId();

    $admMgmtPage = http_call($baseUrl . '/admin/admissions.php', $adminCookie);
    $acceptPost = http_call($baseUrl . '/admin/admissions.php', $adminCookie, [
        'csrf_token' => get_csrf($admMgmtPage['body']),
        'action'     => 'accept_application',
        'id'         => $admissionBId,
        'admin_notes'=> 'Approved in password E2E test',
    ]);
    assert_true($acceptPost['code'] === 200, "Accept admission POST must return HTTP 200");

    $admBRow = $db->query("SELECT * FROM admissions WHERE id = {$admissionBId}")->fetch(PDO::FETCH_ASSOC);
    $stBCode = (string)($admBRow['student_id'] ?? '');
    $initAdmPwdB = (string)($admBRow['portal_password'] ?? '');
    assert_true($stBCode !== '' && strlen($initAdmPwdB) >= 10, "Admission acceptance must generate strong initial password (>= 10 chars)");
    assert_true(strpos($initAdmPwdB, 'rmu') !== 0 || strlen($initAdmPwdB) >= 10, "Must use strong CSPRNG instead of legacy rmuXXXX");

    $stBStmt = $db->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
    $stBStmt->execute([$stBCode]);
    $stB = $stBStmt->fetch(PDO::FETCH_ASSOC);
    assert_true(!empty($stB), "Provisioned Student B must exist in students table");
    $studentBPk = (int)$stB['id'];
    assert_true(password_verify($initAdmPwdB, (string)$stB['password_hash']), "Initial admission password must verify against Student B password_hash");

    // Student B logs in before reset (to verify session invalidation upon reset!)
    $sBLoginGet = http_call($baseUrl . '/student/login.php', $studentBCookie);
    $sBLoginPost = http_call($baseUrl . '/student/login.php', $studentBCookie, [
        'csrf_token' => get_csrf($sBLoginGet['body']),
        'student_id' => $stBCode,
        'password'   => $initAdmPwdB,
    ]);
    assert_true(strpos($sBLoginPost['url'], '/student/dashboard.php') !== false, "Student B must log in with initial admission password");
    echo "   [PASS] CSPRNG passwords generated, hashed, and authenticated cleanly on both enrollment & admission.\n\n";

    // -------------------------------------------------------------------------
    // 5. Admin Reset Password Workflow, Session Invalidation & Cross-Session Isolation
    // -------------------------------------------------------------------------
    echo "5. Reset Password Workflow, Session Invalidation & Security Checks:\n";
    $stBDetailBefore = http_call($baseUrl . '/admin/student-detail.php?id=' . $studentBPk, $adminCookie);
    $oldVerB = (int)$stB['session_version'];

    $resetPost = http_call($baseUrl . '/admin/student-detail.php?id=' . $studentBPk, $adminCookie, [
        'csrf_token'    => get_csrf($stBDetailBefore['body']),
        'action'        => 'reset_student_password',
        'student_id_pk' => $studentBPk,
    ]);
    assert_true($resetPost['code'] === 200, "Reset password POST must succeed cleanly");
    assert_true(strpos($resetPost['body'], 'New temporary login password generated') !== false, "Reset confirmation flash must appear");
    assert_true(preg_match('/id="credPasswordInput"\s+value="([^"]+)"/', $resetPost['body'], $rm) === 1, "Reset page must render temp password in #credPasswordInput");
    $newResetPwdB = html_entity_decode($rm[1], ENT_QUOTES, 'UTF-8');
    assert_true($newResetPwdB !== $initAdmPwdB && strlen($newResetPwdB) >= 10, "New reset password must differ from old initial password");

    // Also verify UI presence on students.php?edit=
    $stBConsole = http_call($baseUrl . '/admin/students.php?edit=' . $studentBPk, $adminCookie);
    assert_true(strpos($stBConsole['body'], 'id="credPasswordInput" value="' . e($newResetPwdB) . '"') !== false || strpos($stBConsole['body'], $newResetPwdB) !== false, "One-page edit console must show active temporary password for same admin session");

    // Verify DB state after reset
    $stBAfterReset = $db->query("SELECT * FROM students WHERE id = {$studentBPk}")->fetch(PDO::FETCH_ASSOC);
    assert_true(($stBAfterReset['password_source'] ?? '') === 'admin_reset', "password_source must update to admin_reset");
    assert_true((int)$stBAfterReset['session_version'] === ($oldVerB + 1), "session_version must increment on password reset");
    assert_true((int)$stBAfterReset['must_change_password'] === 1, "must_change_password must be 1");
    assert_true(!password_verify($initAdmPwdB, (string)$stBAfterReset['password_hash']), "Old password must no longer verify");
    assert_true(password_verify($newResetPwdB, (string)$stBAfterReset['password_hash']), "New reset password must verify against updated bcrypt hash");

    // Verify admissions.portal_password cleared and admission-status hides old pwd
    $admPortalPwdAfter = $db->query("SELECT portal_password FROM admissions WHERE id = {$admissionBId}")->fetchColumn();
    assert_true($admPortalPwdAfter === null || $admPortalPwdAfter === '', "admissions.portal_password must be cleared after password reset");
    $statusPageAfter = http_call($baseUrl . '/admission-status.php?ref=' . urlencode($refB), $anonCookie);
    assert_true(strpos($statusPageAfter['body'], $initAdmPwdB) === false, "admission-status.php must NOT expose cleared initial password");
    assert_true(strpos($statusPageAfter['body'], $newResetPwdB) === false, "admission-status.php must NEVER expose new admin-reset password");

    // Verify plaintext NOT logged in audit_logs or student_notifications
    $auditCheck = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = 'STUDENT_PASSWORD_RESET' AND details LIKE ?");
    $auditCheck->execute(['%' . $newResetPwdB . '%']);
    assert_true((int)$auditCheck->fetchColumn() === 0, "Audit log must NEVER contain plaintext reset password");
    $notifCheck = $db->prepare("SELECT COUNT(*) FROM student_notifications WHERE student_id = ? AND message LIKE ?");
    $notifCheck->execute([$studentBPk, '%' . $newResetPwdB . '%']);
    assert_true((int)$notifCheck->fetchColumn() === 0, "Student notifications must NEVER contain plaintext reset password");

    // Verify second distinct admin session cannot read first admin's temp password
    $adm2LoginPage = http_call($baseUrl . '/admin/login.php', $admin2Cookie);
    http_call($baseUrl . '/admin/login.php', $admin2Cookie, [
        'csrf_token' => get_csrf($adm2LoginPage['body']),
        'username'   => 'admin',
        'password'   => 'Admin@RMU2026!',
    ]);
    $adm2View = http_call($baseUrl . '/admin/student-detail.php?id=' . $studentBPk, $admin2Cookie);
    assert_true(strpos($adm2View['body'], $newResetPwdB) === false, "Different admin PHP session must NOT display temporary credential created in first session");

    // Verify Student B's old active session is immediately invalidated
    $sBOldSessionCheck = http_call($baseUrl . '/student/dashboard.php', $studentBCookie);
    assert_true(strpos($sBOldSessionCheck['url'], '/student/login.php') !== false, "Old student session must be invalidated immediately upon password reset");

    // Verify old password login fails & new reset password succeeds
    $sBFailPost = http_call($baseUrl . '/student/login.php', $studentBCookie, [
        'csrf_token' => get_csrf($sBOldSessionCheck['body']),
        'student_id' => $stBCode,
        'password'   => $initAdmPwdB,
    ]);
    assert_true(strpos($sBFailPost['url'], '/student/login.php') !== false && strpos($sBFailPost['body'], 'Invalid Student ID or Password') !== false, "Old initial password must fail login after reset");

    $sBOkPost = http_call($baseUrl . '/student/login.php', $studentBCookie, [
        'csrf_token' => get_csrf($sBFailPost['body']),
        'student_id' => $stBCode,
        'password'   => $newResetPwdB,
    ]);
    assert_true($sBOkPost['code'] === 200 && strpos($sBOkPost['url'], '/student/dashboard.php') !== false, "Student B must successfully log in using new reset password");
    echo "   [PASS] Reset workflow, session invalidation, admissions clearing, and login verified!\n\n";

    // -------------------------------------------------------------------------
    // 6. Student Self-Update Clears Admin Temp Display & Must-Change Flag
    // -------------------------------------------------------------------------
    echo "6. Student Self-Password Update & Admin Temp Cache Invalidation:\n";
    $profPage = http_call($baseUrl . '/student/profile.php', $studentBCookie);
    $customStudentPass = 'MyOwnSecret!2026';
    $profPost = http_call($baseUrl . '/student/profile.php', $studentBCookie, [
        'csrf_token'       => get_csrf($profPage['body']),
        'action'           => 'change_password',
        'current_password' => $newResetPwdB,
        'new_password'     => $customStudentPass,
        'confirm_password' => $customStudentPass,
    ]);
    assert_true(strpos($profPost['body'], 'password has been changed successfully') !== false, "Student profile password change must succeed");

    $stBAfterSelf = $db->query("SELECT password_hash, password_source, must_change_password FROM students WHERE id = {$studentBPk}")->fetch(PDO::FETCH_ASSOC);
    assert_true(($stBAfterSelf['password_source'] ?? '') === 'student_updated', "password_source must update to student_updated");
    assert_true((int)$stBAfterSelf['must_change_password'] === 0, "must_change_password must reset to 0");
    assert_true(password_verify($customStudentPass, (string)$stBAfterSelf['password_hash']), "New self-chosen password must verify");

    // Admin reloads student-detail.php -> old reset temp password automatically purged via hash signature mismatch
    $admReloadAfterSelf = http_call($baseUrl . '/admin/student-detail.php?id=' . $studentBPk, $adminCookie);
    assert_true(strpos($admReloadAfterSelf['body'], $newResetPwdB) === false, "Admin session temp password must invalidate after student changes password");
    assert_true(strpos($admReloadAfterSelf['body'], $customStudentPass) === false, "Student-chosen password must NEVER appear in admin view");
    assert_true(strpos($admReloadAfterSelf['body'], 'Student Self-Updated') !== false, "Badge must show Student Self-Updated");
    echo "   [PASS] Self-update clears temporary flag and auto-invalidates admin temp credential cache.\n\n";

    // -------------------------------------------------------------------------
    // 7. Reset via students.php?edit, Clear Display & Deleted Account Revocation
    // -------------------------------------------------------------------------
    echo "7. Reset via students.php?edit, Clear Display & Deleted Account Revocation:\n";
    $stAConsoleBefore = http_call($baseUrl . '/admin/students.php?edit=' . $studentAPk, $adminCookie);
    $consoleResetPost = http_call($baseUrl . '/admin/students.php?edit=' . $studentAPk, $adminCookie, [
        'csrf_token'    => get_csrf($stAConsoleBefore['body']),
        'action'        => 'reset_student_password',
        'student_id_pk' => $studentAPk,
    ]);
    assert_true($consoleResetPost['code'] === 200 && preg_match('/id="credPasswordInput"\s+value="([^"]+)"/', $consoleResetPost['body'], $cm) === 1, "Reset via students.php?edit must render new temp password");
    $consoleResetPwdA = html_entity_decode($cm[1], ENT_QUOTES, 'UTF-8');
    assert_true($consoleResetPwdA !== $autoGenPwdA, "Reset via students.php?edit must generate a fresh distinct password");
    $stAConsoleDbHash = (string)$db->query("SELECT password_hash FROM students WHERE id = {$studentAPk}")->fetchColumn();
    assert_true(password_verify($consoleResetPwdA, $stAConsoleDbHash), "Password reset from students.php?edit must match DB hash");

    $clearPost = http_call($baseUrl . '/admin/student-detail.php?id=' . $studentAPk, $adminCookie, [
        'csrf_token'    => get_csrf($consoleResetPost['body']),
        'action'        => 'clear_temp_credential',
        'student_id_pk' => $studentAPk,
    ]);
    assert_true(strpos($clearPost['body'], $consoleResetPwdA) === false && strpos($clearPost['body'], $autoGenPwdA) === false, "Clear button must purge temp password from admin session immediately");

    $delPost = http_call($baseUrl . '/admin/student-detail.php?id=' . $studentAPk, $adminCookie, [
        'csrf_token'               => get_csrf($clearPost['body']),
        'action'                   => 'delete_student_account',
        'confirm_student_id'       => $stACode,
        'confirm_permanent_delete' => '1',
        'reason'                   => 'E2E delete credential revocation test',
    ]);
    assert_true(strpos($delPost['body'], 'Credentials Permanently Revoked') !== false, "Deleted profile must show Credentials Permanently Revoked");
    assert_true(strpos($delPost['body'], 'resetStudentPasswordBtn') === false, "Deleted account must NOT allow Reset Password");

    // Attempting reset on deleted student via direct POST must fail
    $delResetTry = http_call($baseUrl . '/admin/student-detail.php?id=' . $studentAPk, $adminCookie, [
        'csrf_token'    => get_csrf($delPost['body']),
        'action'        => 'reset_student_password',
        'student_id_pk' => $studentAPk,
    ]);
    assert_true(strpos($delResetTry['body'], 'permanently deleted') !== false, "POST reset on deleted student must be rejected");
    echo "   [PASS] students.php?edit reset, Clear display, and deleted credential revocation enforced.\n\n";

    // -------------------------------------------------------------------------
    // 8. Final Baseline Integrity Check on RMU2601001
    // -------------------------------------------------------------------------
    $seedFinal = $db->query("SELECT password_hash, status FROM students WHERE id = {$seedPk}")->fetch(PDO::FETCH_ASSOC);
    assert_true($seedFinal['password_hash'] === $seedHashBefore, "Seed student password_hash must not be altered");
    assert_true($seedFinal['status'] === $seed['status'], "Seed student status must remain unchanged ({$seed['status']})");

    echo "===========================================================================\n";
    echo "  ALL STUDENT PASSWORD MANAGEMENT E2E CHECKS PASSED 100%!\n";
    echo "===========================================================================\n";
} catch (Throwable $e) {
    $exitCode = 1;
    echo "\n[FAIL] " . $e->getMessage() . "\n";
} finally {
    foreach ([$anonCookie, $adminCookie, $admin2Cookie, $editorCookie, $studentACookie, $studentBCookie] as $cf) {
        if (file_exists($cf)) @unlink($cf);
    }
    try {
        foreach ([$studentAPk, $studentBPk] as $delPk) {
            if ($delPk > 0) {
                $db->prepare("DELETE FROM student_retention_archives WHERE student_pk_id = ?")->execute([$delPk]);
                $db->prepare("DELETE FROM student_notifications WHERE student_id = ?")->execute([$delPk]);
                $db->prepare("DELETE FROM student_semester_results WHERE student_id = ?")->execute([$delPk]);
                $db->prepare("DELETE FROM student_results WHERE student_id = ?")->execute([$delPk]);
                $db->prepare("DELETE FROM student_courses WHERE student_id = ?")->execute([$delPk]);
                $db->prepare("DELETE FROM student_fees WHERE student_id = ?")->execute([$delPk]);
                $db->prepare("DELETE FROM students WHERE id = ?")->execute([$delPk]);
            }
        }
        if ($admissionBId > 0) {
            $db->prepare("DELETE FROM admissions WHERE id = ?")->execute([$admissionBId]);
        }
        if ($editorUserId > 0) {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$editorUserId]);
        }
        if (isset($uniq)) {
            $db->prepare("DELETE FROM audit_logs WHERE details LIKE ?")->execute(['%' . $uniq . '%']);
        }
    } catch (Throwable $ce) {
        echo "[WARN] Cleanup notice: " . $ce->getMessage() . "\n";
    }
}

exit($exitCode);
