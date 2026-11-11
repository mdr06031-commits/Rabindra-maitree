<?php
/**
 * Action: Process Online Admission Application Submission
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . APP_URL . "/admission-form.php");
    exit;
}

// 1. Verify CSRF Token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    flash_set('error', 'Security session expired. Please refresh the page and submit again.');
    header("Location: " . APP_URL . "/admission-form.php");
    exit;
}

// 2. Extract & Sanitize Form Inputs (Application ID & Roll Number are strictly server-assigned)
$draftToken           = trim((string)($_POST['draft_token'] ?? ($_SESSION['rmu_admission_draft']['draft_token'] ?? '')));
$applicationYearInput = trim($_POST['application_year'] ?? '');
$fullName    = trim($_POST['full_name'] ?? '');
$fatherName  = trim($_POST['father_name'] ?? '');
$motherName  = trim($_POST['mother_name'] ?? '');
$dob         = trim($_POST['date_of_birth'] ?? '');
$gender      = in_array($_POST['gender'] ?? '', ['Male', 'Female', 'Other']) ? $_POST['gender'] : 'Male';
$rawMobile   = trim($_POST['mobile'] ?? '');
$email       = trim($_POST['email'] ?? '');
$presentAddr = trim($_POST['present_address'] ?? '');
$permAddr    = trim($_POST['permanent_address'] ?? '');
$session     = trim($_POST['academic_session'] ?? '');
$deptId      = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
$progId      = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
$lastCollege = trim($_POST['last_college'] ?? '');
$sscGpa      = trim($_POST['ssc_gpa'] ?? '');
$hscGpa      = trim($_POST['hsc_gpa'] ?? '');
$district    = trim($_POST['home_district'] ?? '');
$hscGroup    = trim($_POST['hsc_group'] ?? '');
$subject     = trim($_POST['interested_subject'] ?? '');
$degreeTypeInput = trim($_POST['degree_type'] ?? '');
$passingYearInput = trim($_POST['passing_year'] ?? '');
$quota       = trim($_POST['quota'] ?? 'NO');

// Extended Educational Background
$sscExam       = trim($_POST['ssc_exam'] ?? 'S.S.C');
$sscRoll       = trim($_POST['ssc_roll'] ?? '');
$sscRegNo      = trim($_POST['ssc_reg_no'] ?? '');
$sscBoard      = trim($_POST['ssc_board'] ?? 'Dinajpur');
$sscResultType = trim($_POST['ssc_result_type'] ?? 'GPA(out of 5)');
$sscGroup      = trim($_POST['ssc_group'] ?? 'Science');
$sscYear       = trim($_POST['ssc_year'] ?? '2011');

$hscExam       = trim($_POST['hsc_exam'] ?? 'H.S.C');
$hscRoll       = trim($_POST['hsc_roll'] ?? '');
$hscRegNo      = trim($_POST['hsc_reg_no'] ?? '');
$hscBoard      = trim($_POST['hsc_board'] ?? 'Dinajpur');
$hscResultType = trim($_POST['hsc_result_type'] ?? 'GPA(out of 5)');
$hscYear       = trim($_POST['hsc_year'] ?? '2014');

$gradApplicable = isset($_POST['grad_applicable']) ? 1 : 0;
$gradExam       = $gradApplicable ? trim($_POST['grad_exam'] ?? '') : null;
$gradUniversity = $gradApplicable ? trim($_POST['grad_university'] ?? '') : null;
$gradSubject    = $gradApplicable ? trim($_POST['grad_subject'] ?? '') : null;
$gradResultType = $gradApplicable ? trim($_POST['grad_result_type'] ?? '') : null;
$gradResult     = $gradApplicable ? trim($_POST['grad_result'] ?? '') : null;
$gradYear       = $gradApplicable ? trim($_POST['grad_year'] ?? '') : null;
$gradDuration   = $gradApplicable ? trim($_POST['grad_duration'] ?? '') : null;

// Handle Applicant Photograph Upload
$uploadedPhoto = null;
if (isset($_FILES['applicant_photo']) && $_FILES['applicant_photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['applicant_photo'];
    $maxBytes = 2 * 1024 * 1024; // 2MB
    if ($file['size'] <= $maxBytes) {
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (isset($allowedTypes[$mime])) {
            $ext = $allowedTypes[$mime];
            $uploadDir = BASE_PATH . '/storage/uploads/students/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $cleanMobile = preg_replace('/[^0-9]/', '', $rawMobile);
            $filename = 'applicant_' . $cleanMobile . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $uploadedPhoto = $filename;
            }
        }
    }
}

$errors = [];

// 3. Server-side Validation
if (empty($fullName) || mb_strlen($fullName) < 3) {
    $errors[] = 'Full name must be at least 3 characters in length.';
}

if (!validate_bd_mobile($rawMobile)) {
    $errors[] = 'Please provide a valid 11-digit Bangladeshi mobile number (e.g. 017XXXXXXXX).';
}
$mobile = normalize_bd_mobile($rawMobile);

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please provide a valid email address or leave the field blank.';
}

if (empty($lastCollege)) {
    $errors[] = 'Previous college or institution name is required.';
}

if (!validate_gpa($sscGpa)) {
    $errors[] = 'S.S.C GPA must be a valid number between 1.00 and 5.00.';
}

if (!validate_gpa($hscGpa)) {
    $errors[] = 'H.S.C GPA must be a valid number between 1.00 and 5.00.';
}

if (empty($district)) {
    $errors[] = 'Home district is required.';
}

$validGroups = ['Science', 'Humanities', 'Commerce', 'Business Studies', 'Diploma'];
if (!in_array($hscGroup, $validGroups, true)) {
    $errors[] = 'Please select a valid HSC group stream.';
}

$db = getDb();
$progRows = $db->query("SELECT id, department_id, title_en, degree_type, duration, duration_years, total_semesters FROM programs WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
$dbSubjects = array_column($progRows, 'title_en');
$legacySubjects = [
    'BSc Engg in CSE', 'BSc Engg in EEE', 'BSc Engg in ICT', 'BSc Engg in Microbiology',
    'BSc Engg in Agriculture', 'BA in English', 'BA in Bangla', 'BA in Fine Arts',
    'BA in Music', 'MA in English', 'MA in Bangla', 'MPED', 'BBA', 'MBA', 'Bachelor of Education (B.Ed)'
];
$allValidSubjects = array_unique(array_merge($dbSubjects, $legacySubjects));

// Resolve matched program by program_id or subject name
$matchedProg = null;
foreach ($progRows as $pr) {
    if ($progId && (int)$pr['id'] === $progId) {
        $matchedProg = $pr;
        break;
    }
    if ($subject !== '' && strcasecmp($pr['title_en'], $subject) === 0) {
        $matchedProg = $pr;
        break;
    }
}
if ($matchedProg) {
    if (!$progId) $progId = (int)$matchedProg['id'];
    if (!$deptId) $deptId = (int)$matchedProg['department_id'];
    if ($subject === '') $subject = $matchedProg['title_en'];
}

if ($deptId) {
    $dChk = $db->prepare("SELECT id FROM departments WHERE id = ? LIMIT 1");
    $dChk->execute([$deptId]);
    if (!$dChk->fetchColumn()) {
        $deptId = $matchedProg ? (int)$matchedProg['department_id'] : null;
    }
}

if (!in_array($subject, $allValidSubjects, true)) {
    $errors[] = 'Please select a valid academic program from the list.';
}

$validDegreeTypes = ['Bachelor / Honours', 'B.Ed', "Master's"];
if ($degreeTypeInput !== '' && !in_array($degreeTypeInput, $validDegreeTypes, true)) {
    $errors[] = 'Please select a valid degree / course type.';
}
$degreeType = $degreeTypeInput !== '' ? $degreeTypeInput : ($matchedProg['degree_type'] ?? 'Bachelor / Honours');

// Enforce Admin-enabled Academic Session & Passing Year (NEVER automatically overwrite passing year!)
if ($session === '' || !is_academic_session_enabled($session)) {
    $errors[] = 'Please select a currently enabled Academic Session.';
}
if ($passingYearInput === '' || !is_passing_year_enabled($passingYearInput)) {
    $errors[] = 'Please select an enabled Passing Year.';
}

$finalPassingYear = $passingYearInput;

// Online application record year starts from 2015 (independent of 2011+ Academic Session & Passing Year)
if ($applicationYearInput !== '') {
    if (!ctype_digit($applicationYearInput) || (int)$applicationYearInput < 2015 || (int)$applicationYearInput > ((int)date('Y') + 5)) {
        $errors[] = 'Online Application Record Year must start from 2015 or later.';
    }
    $finalApplicationYear = (int)$applicationYearInput;
} else {
    $finalApplicationYear = max(2015, (int)date('Y'));
}

$progDurYears = isset($matchedProg['duration_years']) && (float)$matchedProg['duration_years'] > 0
    ? (float)$matchedProg['duration_years']
    : (($degreeType === 'B.Ed') ? 1.0 : (($degreeType === "Master's") ? 2.0 : 4.0));
$progTotalSemesters = isset($matchedProg['total_semesters']) && (int)$matchedProg['total_semesters'] > 0
    ? (int)$matchedProg['total_semesters']
    : (($degreeType === 'B.Ed') ? 2 : (($degreeType === "Master's") ? 4 : 8));
$courseDurationLabel = format_duration_years_label($progDurYears, $progTotalSemesters);

$expCalc = calculate_expected_passing_year($session, $progDurYears);
$expectedPassingYear = $expCalc ? (int)$expCalc['expected_year'] : null;

$originalSnapshotData = [
    'application_year'      => $finalApplicationYear,
    'program_id'            => $progId,
    'interested_subject'    => $subject,
    'degree_type'           => $degreeType,
    'academic_session'      => $session,
    'duration_years'        => $progDurYears,
    'course_duration'       => $courseDurationLabel,
    'total_semesters'       => $progTotalSemesters,
    'expected_passing_year' => $expectedPassingYear,
    'passing_year'          => $finalPassingYear,
    'submitted_at'          => date('Y-m-d H:i:s'),
];

$quotaVal = ($quota === 'YES') ? 'YES' : 'NO';

// If validation errors exist, redirect back with error message (preserving draft reservation)
if (!empty($errors)) {
    flash_set('error', implode(' ', $errors));
    header("Location: " . APP_URL . "/admission-form.php");
    exit;
}

try {
    $db = getDb();
    ensure_admission_id_generation_schema($db);
    ensure_admission_application_system_schema($db);
    cleanup_expired_admission_reservations($db);

    // 4. Check for duplicate active submission by mobile + subject
    $dupStmt = $db->prepare("
        SELECT application_ref, status 
        FROM admissions 
        WHERE mobile = ? AND interested_subject = ? AND status IN ('Submitted', 'Pending', 'Under Review')
    ");
    $dupStmt->execute([$mobile, $subject]);
    $existing = $dupStmt->fetch();

    if ($existing) {
        flash_set('info', "An active application ({$existing['application_ref']}) has already been submitted for mobile number {$mobile}. You can track its status below.");
        header("Location: " . APP_URL . "/admission-status.php?ref=" . urlencode($existing['application_ref']));
        exit;
    }

    // 5. Transactional Server-Side ID & Roll Assignment + DB Insert (Concurrency-safe retry loop)
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $seqYear = (int)date('Y');
    $refNumber = null;
    $finalApplicationId = null;
    $finalRollNumber = null;
    $verificationToken = bin2hex(random_bytes(20));

    $insertSql = "
        INSERT INTO admissions (
            application_ref, application_id, application_year, full_name, father_name, mother_name, date_of_birth, gender,
            mobile, email, present_address, permanent_address, home_district,
            academic_session, department_id, program_id, last_college,
            ssc_gpa, hsc_gpa, hsc_group, interested_subject, degree_type, duration_years, course_duration, total_semesters,
            roll_number, exam_roll, passing_year, expected_passing_year,
            quota, status, payment_status, payment_amount, ip_address,
            ssc_exam, ssc_roll, ssc_reg_no, ssc_board, ssc_result_type, ssc_group, ssc_year,
            hsc_exam, hsc_roll, hsc_reg_no, hsc_board, hsc_result_type, hsc_year,
            grad_applicable, grad_exam, grad_university, grad_subject, grad_result_type, grad_result, grad_year, grad_duration,
            photo, original_submission_json, verification_token
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, 'Pending', 'Pending', 150.00, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?
        )
    ";

    $committed = false;
    $newAdmId = 0;
    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            $db->beginTransaction();

            $assigned = finalize_admission_identifiers_in_tx($db, $draftToken, $seqYear);
            $refNumber = $assigned['application_id'];
            $finalApplicationId = $assigned['application_id'];
            $finalRollNumber = $assigned['roll_number'];

            $originalSubmissionJson = build_admission_original_snapshot($originalSnapshotData + [
                'application_id' => $finalApplicationId,
                'roll_number'    => $finalRollNumber,
            ]);

            $insertStmt = $db->prepare($insertSql);
            $insertStmt->execute([
                $refNumber, $finalApplicationId, $finalApplicationYear, $fullName, $fatherName, $motherName, (!empty($dob) ? $dob : null), $gender,
                $mobile, $email, $presentAddr, $permAddr, $district,
                $session, $deptId, $progId, $lastCollege,
                (float)$sscGpa, (float)$hscGpa, $hscGroup, $subject, $degreeType, $progDurYears, $courseDurationLabel, $progTotalSemesters,
                $finalRollNumber, $finalRollNumber, $finalPassingYear, $expectedPassingYear,
                $quotaVal, $ipAddress,
                $sscExam, $sscRoll, ($sscRegNo !== '' ? $sscRegNo : null), $sscBoard, $sscResultType, $sscGroup, $sscYear,
                $hscExam, $hscRoll, ($hscRegNo !== '' ? $hscRegNo : null), $hscBoard, $hscResultType, $hscYear,
                $gradApplicable, $gradExam, $gradUniversity, $gradSubject, $gradResultType, $gradResult, $gradYear, $gradDuration,
                $uploadedPhoto, $originalSubmissionJson, $verificationToken
            ]);

            $newAdmId = (int)$db->lastInsertId();
            mark_admission_reservation_committed($db, $assigned['reservation_id'] ?? null, $newAdmId);

            $db->commit();
            $committed = true;
            break;
        } catch (PDOException $txEx) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if (!is_retryable_db_concurrency_exception($txEx) || $attempt === 4) {
                throw $txEx;
            }
            $draftToken = ''; // Reallocate clean sequential pair on retry
            usleep(25000);
        }
    }

    if (!$committed || $newAdmId <= 0) {
        throw new RuntimeException('Unable to finalize unique Application ID and Roll Number.');
    }

    unset($_SESSION['rmu_admission_draft']);
    authorize_applicant_session_ref($refNumber, $finalApplicationId, $finalRollNumber);
    log_audit('ADMISSION_SUBMITTED', "Submitted online admission {$refNumber} (Roll: {$finalRollNumber}) for {$fullName}");

    flash_set('success', "Application submitted successfully! Your Application ID is {$refNumber} and Admission Roll Number is {$finalRollNumber}. Please complete the ৳150 application fee payment to generate your official Application Card.");
    header("Location: " . APP_URL . "/admission-payment.php?ref=" . urlencode($refNumber));
    exit;

} catch (Exception $e) {
    error_log("Admission submission error: " . $e->getMessage());
    flash_set('error', 'A system error occurred while recording your application. Please try again.');
    header("Location: " . APP_URL . "/admission-form.php");
    exit;
}

