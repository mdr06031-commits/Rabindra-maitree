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

// 2. Extract & Sanitize Form Inputs
$fullName    = trim($_POST['full_name'] ?? '');
$fatherName  = trim($_POST['father_name'] ?? '');
$motherName  = trim($_POST['mother_name'] ?? '');
$dob         = trim($_POST['date_of_birth'] ?? '');
$gender      = in_array($_POST['gender'] ?? '', ['Male', 'Female', 'Other']) ? $_POST['gender'] : 'Male';
$rawMobile   = trim($_POST['mobile'] ?? '');
$email       = trim($_POST['email'] ?? '');
$presentAddr = trim($_POST['present_address'] ?? '');
$permAddr    = trim($_POST['permanent_address'] ?? '');
$session     = trim($_POST['academic_session'] ?? 'Fall 2026');
$deptId      = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
$progId      = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
$lastCollege = trim($_POST['last_college'] ?? '');
$sscGpa      = trim($_POST['ssc_gpa'] ?? '');
$hscGpa      = trim($_POST['hsc_gpa'] ?? '');
$district    = trim($_POST['home_district'] ?? '');
$hscGroup    = trim($_POST['hsc_group'] ?? '');
$subject     = trim($_POST['interested_subject'] ?? '');
$quota       = trim($_POST['quota'] ?? 'NO');

// Extended Educational Background
$sscExam       = trim($_POST['ssc_exam'] ?? 'S.S.C');
$sscRoll       = trim($_POST['ssc_roll'] ?? '');
$sscBoard      = trim($_POST['ssc_board'] ?? 'Dinajpur');
$sscResultType = trim($_POST['ssc_result_type'] ?? 'GPA(out of 5)');
$sscGroup      = trim($_POST['ssc_group'] ?? 'Science');
$sscYear       = trim($_POST['ssc_year'] ?? '2011');

$hscExam       = trim($_POST['hsc_exam'] ?? 'H.S.C');
$hscRoll       = trim($_POST['hsc_roll'] ?? '');
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

// Handle Academic Documents Upload (Certificates / Transcripts)
$uploadedDocs = null;
if (isset($_FILES['academic_documents']) && $_FILES['academic_documents']['error'] === UPLOAD_ERR_OK) {
    $docFile = $_FILES['academic_documents'];
    $maxDocBytes = 5 * 1024 * 1024; // 5MB
    if ($docFile['size'] <= $maxDocBytes) {
        $allowedDocTypes = [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp'
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $docMime = $finfo->file($docFile['tmp_name']);
        if (isset($allowedDocTypes[$docMime])) {
            $docExt = $allowedDocTypes[$docMime];
            $docDir = BASE_PATH . '/storage/uploads/documents/';
            if (!is_dir($docDir)) {
                mkdir($docDir, 0755, true);
            }
            $cleanMobile = preg_replace('/[^0-9]/', '', $rawMobile);
            $docFilename = 'doc_' . $cleanMobile . '_' . time() . '.' . $docExt;
            if (move_uploaded_file($docFile['tmp_name'], $docDir . $docFilename)) {
                $uploadedDocs = $docFilename;
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
$dbSubjects = $db->query("SELECT title_en FROM programs WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
$legacySubjects = [
    'BSc Engg in CSE', 'BSc Engg in EEE', 'BSc Engg in ICT', 'BSc Engg in Microbiology',
    'BSc Engg in Agriculture', 'BA in English', 'BA in Bangla', 'BA in Fine Arts',
    'BA in Music', 'MA in English', 'MA in Bangla', 'MPED', 'BBA', 'MBA'
];
$allValidSubjects = array_unique(array_merge($dbSubjects, $legacySubjects));
if (!in_array($subject, $allValidSubjects, true)) {
    $errors[] = 'Please select a valid academic program from the list.';
}


$quotaVal = ($quota === 'YES') ? 'YES' : 'NO';

// If validation errors exist, redirect back with error message
if (!empty($errors)) {
    flash_set('error', implode(' ', $errors));
    header("Location: " . APP_URL . "/admission-form.php");
    exit;
}

try {
    $db = getDb();

    // 4. Check for duplicate pending submission
    $dupStmt = $db->prepare("
        SELECT application_ref, status 
        FROM admissions 
        WHERE mobile = ? AND interested_subject = ? AND status IN ('Submitted', 'Under Review')
    ");
    $dupStmt->execute([$mobile, $subject]);
    $existing = $dupStmt->fetch();

    if ($existing) {
        flash_set('info', "An active application ({$existing['application_ref']}) has already been submitted for mobile number {$mobile}. You can track its status below.");
        header("Location: " . APP_URL . "/admission-status.php?ref=" . urlencode($existing['application_ref']));
        exit;
    }

    // 5. Generate unique reference ID
    $refNumber = generate_application_ref($db);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // 6. Persist to MySQL
    $insertStmt = $db->prepare("
        INSERT INTO admissions (
            application_ref, full_name, father_name, mother_name, date_of_birth, gender,
            mobile, email, present_address, permanent_address, home_district,
            academic_session, department_id, program_id, last_college,
            ssc_gpa, hsc_gpa, hsc_group, interested_subject,
            quota, status, payment_status, payment_amount, ip_address,
            ssc_exam, ssc_roll, ssc_board, ssc_result_type, ssc_group, ssc_year,
            hsc_exam, hsc_roll, hsc_board, hsc_result_type, hsc_year,
            grad_applicable, grad_exam, grad_university, grad_subject, grad_result_type, grad_result, grad_year, grad_duration,
            photo, academic_documents
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, 'Submitted', 'Pending', 150.00, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?
        )
    ");

    $insertStmt->execute([
        $refNumber, $fullName, $fatherName, $motherName, (!empty($dob) ? $dob : null), $gender,
        $mobile, $email, $presentAddr, $permAddr, $district,
        $session, $deptId, $progId, $lastCollege,
        (float)$sscGpa, (float)$hscGpa, $hscGroup, $subject,
        $quotaVal, $ipAddress,
        $sscExam, $sscRoll, $sscBoard, $sscResultType, $sscGroup, $sscYear,
        $hscExam, $hscRoll, $hscBoard, $hscResultType, $hscYear,
        $gradApplicable, $gradExam, $gradUniversity, $gradSubject, $gradResultType, $gradResult, $gradYear, $gradDuration,
        $uploadedPhoto, $uploadedDocs
    ]);

    flash_set('success', "Application submitted successfully! Your tracking reference number is {$refNumber}. Please complete the ৳150 application fee payment to generate your official Application Card and receive your Student Portal ID & Password.");
    header("Location: " . APP_URL . "/admission-payment.php?ref=" . urlencode($refNumber));
    exit;

} catch (Exception $e) {
    error_log("Admission submission error: " . $e->getMessage());
    flash_set('error', 'A system error occurred while recording your application. Please try again.');
    header("Location: " . APP_URL . "/admission-form.php");
    exit;
}

