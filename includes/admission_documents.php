<?php
/**
 * Rabindra Maitree University (RMU)
 * Premium Admission Application Card, Separate Payment Receipt,
 * Authorization Guards, QR Verification Helpers & Bilingual A4 PDF Engine
 */

require_once __DIR__ . '/qr_generator.php';

/**
 * Ensure additive schema columns & verification tokens exist on `admissions`.
 */
function ensure_admission_application_system_schema(PDO $db): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $cols = $db->query("SHOW COLUMNS FROM admissions")->fetchAll(PDO::FETCH_COLUMN);
        if (!$cols) {
            return;
        }

        if (!in_array('ssc_reg_no', $cols, true)) {
            $db->exec("ALTER TABLE admissions ADD COLUMN ssc_reg_no VARCHAR(60) NULL AFTER ssc_roll");
        }
        if (!in_array('hsc_reg_no', $cols, true)) {
            $db->exec("ALTER TABLE admissions ADD COLUMN hsc_reg_no VARCHAR(60) NULL AFTER hsc_roll");
        }
        if (!in_array('verification_token', $cols, true)) {
            $db->exec("ALTER TABLE admissions ADD COLUMN verification_token VARCHAR(64) NULL");
        }
        if (!in_array('pdf_path', $cols, true)) {
            $db->exec("ALTER TABLE admissions ADD COLUMN pdf_path VARCHAR(255) NULL");
        }
        if (!in_array('pdf_generated_at', $cols, true)) {
            $db->exec("ALTER TABLE admissions ADD COLUMN pdf_generated_at DATETIME NULL");
        }

        // Ensure 'Under Verification' is valid in payment_status ENUM (Requirement 6)
        $payColStmt = $db->query("SHOW COLUMNS FROM admissions LIKE 'payment_status'");
        $payColInfo = $payColStmt ? $payColStmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($payColInfo && stripos((string)($payColInfo['Type'] ?? ''), 'Under Verification') === false) {
            $db->exec("ALTER TABLE admissions MODIFY COLUMN payment_status ENUM('Pending','Paid','Under Verification','Failed','Refunded') NOT NULL DEFAULT 'Pending'");
        }

        // Backfill missing verification tokens on existing records with unpredictable random tokens
        $missingStmt = $db->query("SELECT id FROM admissions WHERE verification_token IS NULL OR verification_token = ''");
        $missingIds = $missingStmt ? $missingStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        if (!empty($missingIds)) {
            $updTok = $db->prepare("UPDATE admissions SET verification_token = ? WHERE id = ?");
            foreach ($missingIds as $mid) {
                $updTok->execute([bin2hex(random_bytes(20)), (int)$mid]);
            }
        }
    } catch (Throwable $e) {
        error_log('Admission schema upgrade check failed: ' . $e->getMessage());
    }
}

/**
 * Fetch official university branding solely from site_settings / verified project assets.
 */
function get_university_official_branding(?PDO $db = null): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $settings = [];
    try {
        $pdo = $db ?: getDb();
        $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = trim((string)($r['setting_value'] ?? ''));
        }
    } catch (Throwable $e) {
        error_log('Failed to load site_settings for branding: ' . $e->getMessage());
    }

    $nameEn = !empty($settings['site_name_en']) ? $settings['site_name_en'] : 'Rabindra Maitree University';
    $rawBn  = !empty($settings['site_name_bn']) ? $settings['site_name_bn'] : '';
    // Ensure authentic Bengali institutional script if site_settings still stores ASCII fallback
    $nameBn = ($rawBn !== '' && preg_match('/[\x{0980}-\x{09FF}]/u', $rawBn))
        ? $rawBn
        : 'রবীন্দ্র মৈত্রী বিশ্ববিদ্যালয়';

    $address = !empty($settings['campus_address'])
        ? $settings['campus_address']
        : '97/71, Ram Chandra Roy, Jahid Chowdhury Rd, Kushtia 7000, Bangladesh';
    $phone = !empty($settings['admission_phone'])
        ? $settings['admission_phone']
        : (!empty($settings['contact_phone']) ? $settings['contact_phone'] : '+8801302991188');
    $email = !empty($settings['contact_email']) ? $settings['contact_email'] : 'info@rmu.ac.bd';
    $estYear = !empty($settings['established_year']) ? $settings['established_year'] : '2015';

    $logoJpg = BASE_PATH . '/assets/images/logo.jpg';
    $logoSvg = BASE_PATH . '/assets/images/logo.svg';
    $logoFile = file_exists($logoJpg) ? $logoJpg : (file_exists($logoSvg) ? $logoSvg : null);
    $logoUrl = file_exists($logoJpg) ? (APP_URL . '/assets/images/logo.jpg') : (APP_URL . '/assets/images/logo.svg');
    $logoDataUri = '';
    if ($logoFile && is_readable($logoFile)) {
        $ext = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
        $mime = ($ext === 'svg') ? 'image/svg+xml' : 'image/jpeg';
        $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($logoFile));
    }

    $cached = [
        'name_en'          => $nameEn,
        'name_en_upper'    => mb_strtoupper($nameEn, 'UTF-8'),
        'name_bn'          => $nameBn,
        'address'          => $address,
        'phone'            => $phone,
        'email'            => $email,
        'established_year' => $estYear,
        'logo_url'         => $logoUrl,
        'logo_data_uri'    => $logoDataUri,
    ];
    return $cached;
}

/**
 * Retrieve or create and persist an unpredictable verification token for an admission record.
 */
function get_or_create_admission_verification_token(array &$adm, ?PDO $db = null): string {
    $pdo = $db ?: getDb();
    ensure_admission_application_system_schema($pdo);

    $token = trim((string)($adm['verification_token'] ?? ''));
    if ($token !== '' && strlen($token) >= 16) {
        return $token;
    }

    $token = bin2hex(random_bytes(20));
    if (!empty($adm['id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE admissions SET verification_token = ? WHERE id = ?");
            $stmt->execute([$token, (int)$adm['id']]);
        } catch (Throwable $e) {
            error_log('Token persistence warning: ' . $e->getMessage());
        }
    }
    $adm['verification_token'] = $token;
    return $token;
}

/**
 * Build canonical verification URL encoded inside QR Codes.
 */
function get_admission_verification_url(array &$adm, ?PDO $db = null): string {
    $token = get_or_create_admission_verification_token($adm, $db);
    $ref = !empty($adm['application_id']) ? $adm['application_id'] : ($adm['application_ref'] ?? '');
    return APP_URL . '/verify-application.php?ref=' . urlencode((string)$ref) . '&token=' . urlencode($token);
}

/**
 * Grant current session access to the applicant's own submitted/tracked admission records.
 */
function authorize_applicant_session_ref(string $ref, ?string $appId = null, ?string $roll = null): void {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        if (function_exists('start_secure_session')) {
            start_secure_session();
        } else {
            session_start();
        }
    }
    if (!isset($_SESSION['rmu_applicant_refs']) || !is_array($_SESSION['rmu_applicant_refs'])) {
        $_SESSION['rmu_applicant_refs'] = [];
    }
    foreach ([$ref, $appId, $roll] as $val) {
        $v = trim((string)$val);
        if ($v !== '' && !in_array($v, $_SESSION['rmu_applicant_refs'], true)) {
            $_SESSION['rmu_applicant_refs'][] = $v;
        }
    }
    // Keep session slice bounded
    if (count($_SESSION['rmu_applicant_refs']) > 40) {
        $_SESSION['rmu_applicant_refs'] = array_slice($_SESSION['rmu_applicant_refs'], -40);
    }
}

/**
 * Strict access check for viewing/downloading an Application Card PDF or Payment Receipt.
 * Allows:
 *  1. Authenticated Admin user (`is_logged_in()`)
 *  2. Authenticated Student owning the admission record (matching student_id / application_number / roll_number)
 *  3. Applicant session that legitimately submitted, paid, or tracked the record (`rmu_applicant_refs`)
 */
function can_access_admission_document(array $adm): bool {
    if (empty($adm['id'])) {
        return false;
    }

    if (function_exists('is_logged_in') && is_logged_in()) {
        return true;
    }

    if (!empty($_SESSION['student_pk_id']) || !empty($_SESSION['student_id'])) {
        $st = function_exists('get_logged_in_student') ? get_logged_in_student() : null;
        if (is_array($st) && !empty($st['id'])) {
            $stCode = trim((string)($st['student_id'] ?? ''));
            $stAppNo = trim((string)($st['application_number'] ?? ''));
            $stRollNo = trim((string)($st['roll_number'] ?? ''));

            if ($stCode !== '' && trim((string)($adm['student_id'] ?? '')) === $stCode) {
                return true;
            }
            if ($stAppNo !== '' && (
                trim((string)($adm['application_ref'] ?? '')) === $stAppNo
                || trim((string)($adm['application_id'] ?? '')) === $stAppNo
            )) {
                return true;
            }
            if ($stRollNo !== '' && (
                trim((string)($adm['roll_number'] ?? '')) === $stRollNo
                || trim((string)($adm['exam_roll'] ?? '')) === $stRollNo
            )) {
                return true;
            }
        }
    }

    $sessRefs = $_SESSION['rmu_applicant_refs'] ?? [];
    if (is_array($sessRefs) && !empty($sessRefs)) {
        $candidates = array_filter([
            trim((string)($adm['application_ref'] ?? '')),
            trim((string)($adm['application_id'] ?? '')),
            trim((string)($adm['roll_number'] ?? '')),
            trim((string)($adm['exam_roll'] ?? '')),
        ], static fn($v) => $v !== '');
        foreach ($candidates as $c) {
            if (in_array($c, $sessRefs, true)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Fetch an admission record enriched with Department, Program, Student Photo, Status Mapping & QR.
 */
function fetch_admission_full_record(int $id = 0, string $ref = '', ?PDO $db = null, bool $allowMobile = false): ?array {
    $pdo = $db ?: getDb();
    ensure_admission_application_system_schema($pdo);

    $sql = "
        SELECT a.*,
               d.name_en AS joined_dept_name,
               p.title_en AS joined_program_title,
               p.degree_type AS joined_degree_type,
               p.duration_years AS joined_duration_years,
               p.total_semesters AS joined_total_semesters,
               pd.name_en AS prog_dept_name,
               s.avatar AS student_avatar,
               s.id AS student_table_id
        FROM admissions a
        LEFT JOIN departments d ON a.department_id = d.id
        LEFT JOIN programs p ON a.program_id = p.id
        LEFT JOIN departments pd ON p.department_id = pd.id
        LEFT JOIN students s ON a.student_id = s.student_id
    ";

    if ($id > 0) {
        $stmt = $pdo->prepare($sql . " WHERE a.id = ? LIMIT 1");
        $stmt->execute([$id]);
    } elseif ($ref !== '') {
        if ($allowMobile) {
            $stmt = $pdo->prepare($sql . " WHERE a.application_ref = ? OR a.application_id = ? OR a.roll_number = ? OR a.exam_roll = ? OR a.mobile = ? ORDER BY a.id DESC LIMIT 1");
            $stmt->execute([$ref, $ref, $ref, $ref, $ref]);
        } else {
            $stmt = $pdo->prepare($sql . " WHERE a.application_ref = ? OR a.application_id = ? OR a.roll_number = ? OR a.exam_roll = ? ORDER BY a.id DESC LIMIT 1");
            $stmt->execute([$ref, $ref, $ref, $ref]);
        }
    } else {
        return null;
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Fetch all admissions belonging exclusively to the currently logged-in student.
 */
function fetch_authorized_student_applications(array $student, PDO $db): array {
    ensure_admission_application_system_schema($db);
    $conds = [];
    $params = [];

    $stCode = trim((string)($student['student_id'] ?? ''));
    $stAppNo = trim((string)($student['application_number'] ?? ''));
    $stRollNo = trim((string)($student['roll_number'] ?? ''));

    if ($stCode !== '') {
        $conds[] = "a.student_id = ?";
        $params[] = $stCode;
    }
    if ($stAppNo !== '') {
        $conds[] = "a.application_ref = ? OR a.application_id = ?";
        $params[] = $stAppNo;
        $params[] = $stAppNo;
    }
    if ($stRollNo !== '') {
        $conds[] = "a.roll_number = ? OR a.exam_roll = ?";
        $params[] = $stRollNo;
        $params[] = $stRollNo;
    }

    if (empty($conds)) {
        return [];
    }

    $sql = "
        SELECT a.*,
               d.name_en AS joined_dept_name,
               p.title_en AS joined_program_title,
               pd.name_en AS prog_dept_name
        FROM admissions a
        LEFT JOIN departments d ON a.department_id = d.id
        LEFT JOIN programs p ON a.program_id = p.id
        LEFT JOIN departments pd ON p.department_id = pd.id
        WHERE (" . implode(' OR ', $conds) . ")
        ORDER BY a.id DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Resolve applicant photo absolute file path + Data URI + Web URL cleanly without false "verified" badges.
 */
function resolve_admission_photo_assets(array $adm, ?PDO $db = null): array {
    $pdo = $db ?: getDb();
    $avatarFile = trim((string)($adm['student_avatar'] ?? ''));

    if ($avatarFile === '' && !empty($adm['student_id'])) {
        $stStmt = $pdo->prepare("SELECT avatar FROM students WHERE student_id = ? LIMIT 1");
        $stStmt->execute([$adm['student_id']]);
        $avatarFile = trim((string)$stStmt->fetchColumn());
    }

    $photoName = $avatarFile !== '' ? $avatarFile : trim((string)($adm['photo'] ?? ''));
    $studentsDir = BASE_PATH . '/storage/uploads/students/';
    $filePath = ($photoName !== '' && file_exists($studentsDir . $photoName))
        ? ($studentsDir . $photoName)
        : null;

    $webUrl = $filePath
        ? (APP_URL . '/storage/uploads/students/' . rawurlencode($photoName))
        : (APP_URL . '/assets/images/default-student.svg');

    $fallbackFile = BASE_PATH . '/assets/images/default-student.svg';
    $dataTarget = $filePath ?: (file_exists($fallbackFile) ? $fallbackFile : null);
    $dataUri = '';

    if ($dataTarget && is_readable($dataTarget)) {
        $ext = strtolower(pathinfo($dataTarget, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            default       => 'image/jpeg',
        };
        $dataUri = 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($dataTarget));
    }

    return [
        'has_photo' => ($filePath !== null),
        'web_url'   => $webUrl,
        'data_uri'  => $dataUri,
        'file_path' => $filePath,
    ];
}

/**
 * Compute authoritative UI/PDF/Verification metadata from real database fields.
 */
function enrich_admission_document_data(array $adm, ?PDO $db = null): array {
    $pdo = $db ?: getDb();
    $branding = get_university_official_branding($pdo);
    $token = get_or_create_admission_verification_token($adm, $pdo);
    $verifyUrl = APP_URL . '/verify-application.php?ref=' . urlencode((string)(!empty($adm['application_id']) ? $adm['application_id'] : ($adm['application_ref'] ?? ''))) . '&token=' . urlencode($token);

    $appId = !empty($adm['application_id']) ? (string)$adm['application_id'] : (string)($adm['application_ref'] ?? '');
    $rollNo = !empty($adm['roll_number']) ? (string)$adm['roll_number'] : (string)($adm['exam_roll'] ?? '');

    $deptName = trim((string)($adm['joined_dept_name'] ?? ''));
    if ($deptName === '') {
        $deptName = trim((string)($adm['prog_dept_name'] ?? ''));
    }
    if ($deptName === '' && !empty($adm['department_id'])) {
        $dStmt = $pdo->prepare("SELECT name_en FROM departments WHERE id = ? LIMIT 1");
        $dStmt->execute([(int)$adm['department_id']]);
        $deptName = trim((string)$dStmt->fetchColumn());
    }

    $progName = trim((string)($adm['joined_program_title'] ?? ''));
    if ($progName === '') {
        $progName = trim((string)($adm['interested_subject'] ?? ''));
    }

    if ($deptName === '' && !empty($progName)) {
        $pStmt = $pdo->prepare("SELECT d.name_en FROM programs p JOIN departments d ON p.department_id = d.id WHERE p.title_en = ? LIMIT 1");
        $pStmt->execute([$progName]);
        $deptName = trim((string)$pStmt->fetchColumn());
    }

    $degType = trim((string)($adm['degree_type'] ?? ($adm['joined_degree_type'] ?? 'Bachelor / Honours')));
    $durYears = !empty($adm['duration_years'])
        ? (float)$adm['duration_years']
        : (!empty($adm['joined_duration_years'])
            ? (float)$adm['joined_duration_years']
            : (($degType === 'B.Ed') ? 1.0 : (($degType === "Master's") ? 2.0 : 4.0)));
    $totSems = !empty($adm['total_semesters'])
        ? (int)$adm['total_semesters']
        : (!empty($adm['joined_total_semesters'])
            ? (int)$adm['joined_total_semesters']
            : (($degType === 'B.Ed') ? 2 : (($degType === "Master's") ? 4 : 8)));
    $durLabel = !empty($adm['course_duration'])
        ? (string)$adm['course_duration']
        : format_duration_years_label($durYears, $totSems);

    $sessionName = trim((string)($adm['academic_session'] ?? ''));
    if ($sessionName === '') {
        $sessionName = get_current_academic_session_name('N/A');
    }

    $rawAppStatus = trim((string)($adm['status'] ?? 'Pending'));
    if ($rawAppStatus === 'Accepted') {
        $appStatusLabel = 'Approved';
        $appStatusSub = 'Accepted';
        $appStatusTone = 'approved';
    } elseif ($rawAppStatus === 'Rejected') {
        $appStatusLabel = 'Rejected';
        $appStatusSub = '';
        $appStatusTone = 'rejected';
    } else {
        $appStatusLabel = 'Pending';
        $appStatusSub = in_array($rawAppStatus, ['Under Review', 'Additional Information Required'], true) ? $rawAppStatus : '';
        $appStatusTone = 'pending';
    }

    $rawPayStatus = trim((string)($adm['payment_status'] ?? 'Pending'));
    if ($rawPayStatus === 'Paid') {
        $payStatusLabel = 'Paid';
        $payStatusTone = 'paid';
    } elseif ($rawPayStatus === 'Under Verification') {
        $payStatusLabel = 'Under Verification';
        $payStatusTone = 'verify';
    } else {
        $payStatusLabel = 'Unpaid';
        $payStatusTone = 'unpaid';
    }

    $issuedStId = trim((string)($adm['student_id'] ?? ''));
    if ($issuedStId !== '') {
        $stIdStatusLabel = 'Issued';
        $stIdDetail = $issuedStId;
        $stIdTone = 'issued';
    } else {
        $stIdStatusLabel = 'Pending';
        $stIdDetail = 'Pending Admin Acceptance';
        $stIdTone = 'pending';
    }

    $photo = resolve_admission_photo_assets($adm, $pdo);
    $qrSvg = rmu_generate_qr_svg($verifyUrl, 108, '#0f2c59', '#ffffff');
    $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

    $submittedAt = !empty($adm['created_at']) ? date('d M Y, h:i A', strtotime((string)$adm['created_at'])) : 'N/A';

    return [
        'raw'                  => $adm,
        'branding'             => $branding,
        'application_id'       => $appId,
        'application_ref'      => (string)($adm['application_ref'] ?? $appId),
        'roll_number'          => $rollNo,
        'department_name'      => $deptName !== '' ? $deptName : 'Academic Department',
        'program_name'         => $progName !== '' ? $progName : 'Selected Program',
        'degree_type'          => $degType,
        'duration_label'       => $durLabel,
        'total_semesters'      => $totSems,
        'academic_session'     => $sessionName,
        'actual_passing_year'  => trim((string)($adm['passing_year'] ?? '')),
        'submission_date'      => $submittedAt,
        'app_status_label'     => $appStatusLabel,
        'app_status_sub'       => $appStatusSub,
        'app_status_tone'      => $appStatusTone,
        'pay_status_label'     => $payStatusLabel,
        'pay_status_raw'       => $rawPayStatus,
        'pay_status_tone'      => $payStatusTone,
        'student_id_status'    => $stIdStatusLabel,
        'student_id_detail'    => $stIdDetail,
        'student_id_tone'      => $stIdTone,
        'photo'                => $photo,
        'verification_token'   => $token,
        'verification_url'     => $verifyUrl,
        'qr_svg'               => $qrSvg,
        'qr_data_uri'          => $qrDataUri,
    ];
}

/**
 * Shared CSS stylesheet for the Premium Navy Blue / White / Gold Admission Application Card
 * and Separate Payment Receipt (used across web, print, and headless Chromium A4 Portrait PDF).
 */
function get_admission_document_styles(): string {
    return <<<'CSS'
.rmu-doc-card {
  background: #ffffff;
  border: 2px solid #0f2c59;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 10px 28px rgba(15, 44, 89, 0.08);
  color: #0f172a;
  font-family: 'Nirmala UI', 'Noto Sans Bengali', 'Poppins', 'Segoe UI', Arial, sans-serif;
  line-height: 1.42;
}
.rmu-doc-header {
  background: linear-gradient(135deg, #0f2c59 0%, #1a407a 100%);
  color: #ffffff;
  padding: 18px 22px 16px;
  border-bottom: 4px solid #c9a227;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}
.rmu-doc-brand {
  display: flex;
  align-items: center;
  gap: 14px;
  flex: 1 1 320px;
}
.rmu-doc-logo {
  width: 64px;
  height: 64px;
  border-radius: 8px;
  background: #ffffff;
  border: 2px solid #c9a227;
  padding: 3px;
  object-fit: contain;
  flex-shrink: 0;
}
.rmu-doc-brand-text h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: 0.6px;
  color: #ffffff;
  line-height: 1.2;
}
.rmu-doc-brand-text .rmu-doc-bn {
  font-size: 14.5px;
  font-weight: 700;
  color: #fde68a;
  margin-top: 2px;
}
.rmu-doc-brand-text .rmu-doc-contact {
  font-size: 10.5px;
  color: #dbeafe;
  margin-top: 4px;
  line-height: 1.35;
}
.rmu-doc-title-box {
  text-align: right;
  flex: 0 0 auto;
}
.rmu-doc-title-pill {
  display: inline-block;
  background: rgba(201, 162, 39, 0.18);
  border: 1.5px solid #c9a227;
  color: #fef3c7;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.8px;
  padding: 6px 14px;
  border-radius: 5px;
  text-transform: uppercase;
}
.rmu-doc-session-tag {
  margin-top: 6px;
  font-size: 11.5px;
  color: #e2e8f0;
  font-weight: 600;
}
.rmu-doc-session-tag strong {
  color: #ffffff;
}
.rmu-doc-body {
  padding: 18px 22px 20px;
}
.rmu-app-hero {
  display: grid;
  grid-template-columns: 1fr 142px;
  gap: 16px;
  margin-bottom: 14px;
  align-items: stretch;
  break-inside: avoid;
  page-break-inside: avoid;
}
.rmu-app-id-banner {
  background: #f8fafc;
  border: 1.5px solid #cbd5e1;
  border-left: 5px solid #0f2c59;
  border-radius: 6px;
  padding: 12px 15px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 8px;
}
.rmu-app-id-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 8px;
}
.rmu-app-id-label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  color: #64748b;
  font-weight: 700;
  display: block;
}
.rmu-app-id-value {
  font-family: Consolas, 'Courier New', monospace;
  font-size: 20px;
  font-weight: 800;
  color: #0f2c59;
  letter-spacing: 0.6px;
}
.rmu-app-meta-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px 14px;
  font-size: 12px;
}
.rmu-app-meta-item .lbl {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #64748b;
  font-weight: 700;
  display: block;
}
.rmu-app-meta-item .val {
  color: #0f172a;
  font-weight: 700;
  font-size: 12px;
  word-break: break-word;
}
.rmu-photo-frame {
  border: 1.5px solid #cbd5e1;
  border-top: 4px solid #0f2c59;
  border-radius: 6px;
  background: #f8fafc;
  padding: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.rmu-photo-box {
  width: 112px;
  height: 134px;
  border: 1.5px solid #94a3b8;
  background: #ffffff;
  border-radius: 4px;
  overflow: hidden;
}
.rmu-photo-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.rmu-photo-caption {
  margin-top: 6px;
  font-family: Consolas, 'Courier New', monospace;
  font-size: 10.5px;
  font-weight: 700;
  color: #334155;
  letter-spacing: 0.4px;
}
.rmu-status-strip {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 16px;
  break-inside: avoid;
  page-break-inside: avoid;
}
.rmu-status-tile {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 9px 12px;
  background: #ffffff;
}
.rmu-status-tile .st-lbl {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #64748b;
  font-weight: 700;
  display: block;
  margin-bottom: 4px;
}
.rmu-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 4px;
  font-size: 11.5px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.rmu-badge-approved, .rmu-badge-paid, .rmu-badge-issued {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #86efac;
}
.rmu-badge-pending, .rmu-badge-verify {
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fcd34d;
}
.rmu-badge-rejected, .rmu-badge-unpaid {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fca5a5;
}
.rmu-status-sub {
  display: block;
  margin-top: 3px;
  font-size: 10.5px;
  color: #475569;
  font-weight: 600;
}
.rmu-doc-section {
  margin-bottom: 14px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  overflow: hidden;
  break-inside: avoid;
  page-break-inside: avoid;
}
.rmu-doc-section-hd {
  background: #0f2c59;
  color: #ffffff;
  padding: 6px 12px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  border-bottom: 2px solid #c9a227;
}
.rmu-info-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11.8px;
}
.rmu-info-table th,
.rmu-info-table td {
  border: 1px solid #e2e8f0;
  padding: 6px 10px;
  vertical-align: top;
  text-align: left;
  word-break: break-word;
}
.rmu-info-table th {
  background: #f8fafc;
  color: #475569;
  font-weight: 700;
  width: 22%;
}
.rmu-info-table td {
  color: #0f172a;
  font-weight: 500;
  width: 28%;
}
.rmu-acad-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11.4px;
}
.rmu-acad-table th {
  background: #f1f5f9;
  color: #0f2c59;
  font-weight: 800;
  border: 1px solid #cbd5e1;
  padding: 6px 8px;
  text-align: left;
  font-size: 10.6px;
  text-transform: uppercase;
}
.rmu-acad-table td {
  border: 1px solid #e2e8f0;
  padding: 6px 8px;
  color: #0f172a;
  vertical-align: top;
}
.rmu-doc-footer {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 2px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 16px;
  flex-wrap: wrap;
  break-inside: avoid;
  page-break-inside: avoid;
}
.rmu-qr-block {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 8px 12px;
}
.rmu-qr-box {
  width: 96px;
  height: 96px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  padding: 4px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.rmu-qr-box svg, .rmu-qr-box img {
  width: 100%;
  height: 100%;
  display: block;
}
.rmu-qr-text {
  font-size: 10.5px;
  color: #475569;
  max-width: 245px;
}
.rmu-qr-text strong {
  color: #0f2c59;
  display: block;
  font-size: 11px;
  margin-bottom: 2px;
}
.rmu-auth-sign {
  text-align: center;
  min-width: 215px;
  padding-top: 24px;
}
.rmu-auth-line {
  border-top: 1.5px solid #0f2c59;
  padding-top: 5px;
  font-size: 11.5px;
  font-weight: 800;
  color: #0f2c59;
}
.rmu-auth-sub {
  font-size: 10px;
  color: #64748b;
  font-weight: 600;
}

@media (max-width: 767px) {
  .rmu-doc-header { padding: 14px 15px; }
  .rmu-doc-title-box { text-align: left; width: 100%; }
  .rmu-doc-body { padding: 14px 15px; }
  .rmu-app-hero { grid-template-columns: 1fr; }
  .rmu-app-meta-grid { grid-template-columns: 1fr; }
  .rmu-status-strip { grid-template-columns: 1fr; }
  .rmu-info-table, .rmu-info-table tbody, .rmu-info-table tr, .rmu-info-table th, .rmu-info-table td {
    display: block;
    width: 100% !important;
    box-sizing: border-box;
  }
  .rmu-info-table th { background: #f1f5f9; border-bottom: none; padding-bottom: 2px; font-size: 10.5px; }
  .rmu-info-table td { border-top: none; padding-top: 2px; margin-bottom: 4px; }
  .rmu-acad-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .rmu-auth-sign { width: 100%; text-align: center; }
}
@media print {
  .rmu-doc-card, .rmu-doc-section {
    overflow: visible !important;
    box-shadow: none !important;
  }
}
CSS;
}

/**
 * Render the clean Executive Official Admission Application Card HTML.
 * Zero unnecessary items (no Expected Completion, no Application Record Year,
 * no payment gateway/sender/trx breakdown, no Change Photo / Photo Verified / fake signature).
 */
function render_premium_application_card_html(array $adm, array $options = []): string {
    if (($adm['payment_status'] ?? '') !== 'Paid' && empty($options['allow_unpaid'])) {
        throw new RuntimeException('Official Paid Application Card cannot be generated until ৳150 fee payment is genuinely verified.');
    }
    $pdo = $options['db'] ?? getDb();
    $isPdf = !empty($options['for_pdf']);
    $wrapperId = $options['wrapper_id'] ?? 'admitCardPrint';
    $data = enrich_admission_document_data($adm, $pdo);
    $raw = $data['raw'];
    $b = $data['branding'];

    $logoSrc = ($isPdf && $b['logo_data_uri'] !== '') ? $b['logo_data_uri'] : $b['logo_url'];
    $photoSrc = ($isPdf || !$data['photo']['has_photo']) && $data['photo']['data_uri'] !== ''
        ? $data['photo']['data_uri']
        : $data['photo']['web_url'];
    $qrHtml = $isPdf
        ? '<img src="' . e($data['qr_data_uri']) . '" alt="QR Verification">'
        : $data['qr_svg'];

    $appToneClass = 'rmu-badge-' . $data['app_status_tone'];
    $payToneClass = 'rmu-badge-' . $data['pay_status_tone'];
    $stIdToneClass = 'rmu-badge-' . $data['student_id_tone'];

    $appBadgeHtml = '<span class="rmu-badge ' . e($appToneClass) . '">' . e($data['app_status_label']) . '</span>';
    if ($data['app_status_sub'] !== '') {
        $appBadgeHtml .= '<span class="rmu-status-sub">Stage: ' . e($data['app_status_sub']) . '</span>';
    }

    $payBadgeHtml = '<span class="rmu-badge ' . e($payToneClass) . '">' . e($data['pay_status_label']) . '</span>';

    $stIdBadgeHtml = '<span class="rmu-badge ' . e($stIdToneClass) . '">' . e($data['student_id_status']) . '</span>';
    if ($data['student_id_detail'] !== '') {
        $stIdBadgeHtml .= '<span class="rmu-status-sub" style="font-family:monospace;">' . e($data['student_id_detail']) . '</span>';
    }

    $dobDisplay = !empty($raw['date_of_birth']) ? date('d M Y', strtotime((string)$raw['date_of_birth'])) : '—';
    $emailDisplay = !empty($raw['email']) ? (string)$raw['email'] : '—';
    $fatherDisplay = !empty($raw['father_name']) ? (string)$raw['father_name'] : '—';
    $motherDisplay = !empty($raw['mother_name']) ? (string)$raw['mother_name'] : '—';
    $presentDisplay = !empty($raw['present_address']) ? (string)$raw['present_address'] : '—';
    $permDisplay = !empty($raw['permanent_address']) ? (string)$raw['permanent_address'] : '—';
    $districtDisplay = !empty($raw['home_district']) ? (string)$raw['home_district'] : '—';
    $genderDisplay = !empty($raw['gender']) ? (string)$raw['gender'] : '—';

    $sscResultDisplay = trim((string)($raw['ssc_result_type'] ?? 'GPA(out of 5)')) . ': ' . number_format((float)($raw['ssc_gpa'] ?? 0), 2);
    $hscResultDisplay = trim((string)($raw['hsc_result_type'] ?? 'GPA(out of 5)')) . ': ' . number_format((float)($raw['hsc_gpa'] ?? 0), 2);
    $sscRegDisplay = trim((string)($raw['ssc_reg_no'] ?? ''));
    $hscRegDisplay = trim((string)($raw['hsc_reg_no'] ?? ''));
    $hasRegCol = ($sscRegDisplay !== '' || $hscRegDisplay !== '');

    ob_start();
    ?>
    <div id="<?= e($wrapperId) ?>" class="rmu-doc-card">
      <!-- 1. UNIVERSITY BRANDING -->
      <div class="rmu-doc-header">
        <div class="rmu-doc-brand">
          <img src="<?= e($logoSrc) ?>" alt="<?= e($b['name_en_upper']) ?> Logo" class="rmu-doc-logo">
          <div class="rmu-doc-brand-text">
            <h2><?= e($b['name_en_upper']) ?></h2>
            <div class="rmu-doc-bn"><?= e($b['name_bn']) ?></div>
            <div class="rmu-doc-contact">
              <?= e($b['address']) ?><br>
              Phone: <?= e($b['phone']) ?> &bull; Email: <?= e($b['email']) ?>
            </div>
          </div>
        </div>
        <div class="rmu-doc-title-box">
          <div class="rmu-doc-title-pill">ADMISSION APPLICATION CARD</div>
          <div class="rmu-doc-session-tag">Academic Session: <strong><?= e($data['academic_session']) ?></strong></div>
        </div>
      </div>

      <div class="rmu-doc-body">
        <!-- 2. APPLICATION INFORMATION + STUDENT PHOTO -->
        <div class="rmu-app-hero">
          <div class="rmu-app-id-banner">
            <div class="rmu-app-id-top">
              <div>
                <span class="rmu-app-id-label">Unique Application ID</span>
                <span class="rmu-app-id-value"><?= e($data['application_id']) ?></span>
              </div>
              <?php if ($data['roll_number'] !== ''): ?>
                <div style="text-align:right;">
                  <span class="rmu-app-id-label">Admission Roll Number</span>
                  <span class="rmu-app-id-value" style="font-size:16.5px; color:#1e40af;"><?= e($data['roll_number']) ?></span>
                </div>
              <?php endif; ?>
            </div>
            <div class="rmu-app-meta-grid">
              <div class="rmu-app-meta-item">
                <span class="lbl">Selected Department</span>
                <span class="val"><?= e($data['department_name']) ?></span>
              </div>
              <div class="rmu-app-meta-item">
                <span class="lbl">Selected Subject / Program</span>
                <span class="val"><?= e($data['program_name']) ?></span>
              </div>
              <div class="rmu-app-meta-item">
                <span class="lbl">Degree / Course Type</span>
                <span class="val"><?= e($data['degree_type']) ?></span>
              </div>
              <div class="rmu-app-meta-item">
                <span class="lbl">Academic Session</span>
                <span class="val"><?= e($data['academic_session']) ?></span>
              </div>
              <div class="rmu-app-meta-item">
                <span class="lbl">Submission Date</span>
                <span class="val"><?= e($data['submission_date']) ?></span>
              </div>
              <div class="rmu-app-meta-item">
                <span class="lbl">Application Status</span>
                <span class="val" style="color:#0f2c59;"><?= e($data['app_status_label']) ?></span>
              </div>
            </div>
          </div>

          <div class="rmu-photo-frame">
            <div class="rmu-photo-box">
              <img src="<?= e($photoSrc) ?>" alt="<?= e($raw['full_name'] ?? 'Applicant Photograph') ?>">
            </div>
            <div class="rmu-photo-caption"><?= e($data['application_id']) ?></div>
          </div>
        </div>

        <!-- 6. APPLICATION / PAYMENT / STUDENT ID STATUS BLOCK -->
        <div class="rmu-status-strip">
          <div class="rmu-status-tile">
            <span class="st-lbl">Application Status</span>
            <?= $appBadgeHtml ?>
          </div>
          <div class="rmu-status-tile">
            <span class="st-lbl">Payment Status</span>
            <?= $payBadgeHtml ?>
          </div>
          <div class="rmu-status-tile">
            <span class="st-lbl">Student ID Status</span>
            <?= $stIdBadgeHtml ?>
          </div>
        </div>

        <!-- 3. STUDENT PERSONAL INFORMATION -->
        <div class="rmu-doc-section">
          <div class="rmu-doc-section-hd">Student Personal Information</div>
          <table class="rmu-info-table">
            <tbody>
              <tr>
                <th>Student Full Name</th>
                <td><strong><?= e($raw['full_name'] ?? '') ?></strong></td>
                <th>Mobile Number</th>
                <td><strong><?= e($raw['mobile'] ?? '') ?></strong></td>
              </tr>
              <tr>
                <th>Father's Name</th>
                <td><?= e($fatherDisplay) ?></td>
                <th>Email Address</th>
                <td><?= e($emailDisplay) ?></td>
              </tr>
              <tr>
                <th>Mother's Name</th>
                <td><?= e($motherDisplay) ?></td>
                <th>Home District</th>
                <td><?= e($districtDisplay) ?></td>
              </tr>
              <tr>
                <th>Date of Birth</th>
                <td><?= e($dobDisplay) ?></td>
                <th>Gender</th>
                <td><?= e($genderDisplay) ?></td>
              </tr>
              <tr>
                <th>Present Address</th>
                <td colspan="3"><?= e($presentDisplay) ?></td>
              </tr>
              <tr>
                <th>Permanent Address</th>
                <td colspan="3"><?= e($permDisplay) ?></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 4. ACADEMIC INFORMATION (ONLY RELEVANT FIELDS) -->
        <div class="rmu-doc-section">
          <div class="rmu-doc-section-hd">Academic &amp; Program Information</div>
          <table class="rmu-info-table" style="border-bottom:1px solid #cbd5e1;">
            <tbody>
              <?php if (!empty($raw['last_college'])): ?>
              <tr>
                <th>Previous Institution</th>
                <td colspan="3"><strong><?= e($raw['last_college']) ?></strong></td>
              </tr>
              <?php endif; ?>
              <tr>
                <th>Selected Department</th>
                <td><?= e($data['department_name']) ?></td>
                <th>Degree / Program</th>
                <td><strong><?= e($data['degree_type']) ?> &mdash; <?= e($data['program_name']) ?></strong></td>
              </tr>
              <tr>
                <th>Course Duration</th>
                <td><?= e($data['duration_label']) ?></td>
                <th>Total Semesters</th>
                <td><strong><?= (int)$data['total_semesters'] ?> Semesters</strong></td>
              </tr>
              <tr>
                <th>Academic Session</th>
                <td><strong><?= e($data['academic_session']) ?></strong></td>
                <?php if ($data['actual_passing_year'] !== ''): ?>
                  <th>Actual Passing Year</th>
                  <td><strong><?= e($data['actual_passing_year']) ?></strong></td>
                <?php else: ?>
                  <th>Admission Quota</th>
                  <td><?= ($raw['quota'] ?? 'NO') === 'YES' ? 'Quota Claimed (YES)' : 'General' ?></td>
                <?php endif; ?>
              </tr>
              <?php if ($data['actual_passing_year'] !== '' && ($raw['quota'] ?? 'NO') === 'YES'): ?>
              <tr>
                <th>Special Quota</th>
                <td colspan="3">Quota Claimed (YES)</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>

          <div class="rmu-acad-wrap">
            <table class="rmu-acad-table">
              <thead>
                <tr>
                  <th>Examination</th>
                  <th>Board</th>
                  <th>Group / Subject</th>
                  <th>Roll Number</th>
                  <?php if ($hasRegCol): ?>
                    <th>Registration No.</th>
                  <?php endif; ?>
                  <th>GPA / Result</th>
                  <th>Passing Year</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong><?= e($raw['ssc_exam'] ?? 'S.S.C') ?></strong></td>
                  <td><?= e($raw['ssc_board'] ?? '—') ?></td>
                  <td><?= e($raw['ssc_group'] ?? '—') ?></td>
                  <td style="font-family:monospace; font-weight:700;"><?= e($raw['ssc_roll'] ?? '—') ?></td>
                  <?php if ($hasRegCol): ?>
                    <td style="font-family:monospace;"><?= e($sscRegDisplay !== '' ? $sscRegDisplay : '—') ?></td>
                  <?php endif; ?>
                  <td><strong><?= e($sscResultDisplay) ?></strong></td>
                  <td><?= e($raw['ssc_year'] ?? '—') ?></td>
                </tr>
                <tr>
                  <td><strong><?= e($raw['hsc_exam'] ?? 'H.S.C') ?></strong></td>
                  <td><?= e($raw['hsc_board'] ?? '—') ?></td>
                  <td><?= e($raw['hsc_group'] ?? '—') ?></td>
                  <td style="font-family:monospace; font-weight:700;"><?= e($raw['hsc_roll'] ?? '—') ?></td>
                  <?php if ($hasRegCol): ?>
                    <td style="font-family:monospace;"><?= e($hscRegDisplay !== '' ? $hscRegDisplay : '—') ?></td>
                  <?php endif; ?>
                  <td><strong><?= e($hscResultDisplay) ?></strong></td>
                  <td><?= e($raw['hsc_year'] ?? '—') ?></td>
                </tr>
                <?php if (!empty($raw['grad_applicable'])): ?>
                <tr>
                  <td><strong><?= e($raw['grad_exam'] ?: 'Graduation') ?></strong></td>
                  <td><?= e($raw['grad_university'] ?: '—') ?></td>
                  <td><?= e($raw['grad_subject'] ?: '—') ?></td>
                  <td><?= e($raw['grad_duration'] ? ('Duration: ' . $raw['grad_duration']) : '—') ?></td>
                  <?php if ($hasRegCol): ?>
                    <td>—</td>
                  <?php endif; ?>
                  <td><strong><?= e(trim((string)($raw['grad_result_type'] ?? 'CGPA') . ' ' . (string)($raw['grad_result'] ?? ''))) ?></strong></td>
                  <td><?= e($raw['grad_year'] ?: '—') ?></td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- 7. QR CODE VERIFICATION & AUTHORIZED OFFICER SIGNATURE -->
        <div class="rmu-doc-footer">
          <div class="rmu-qr-block">
            <div class="rmu-qr-box"><?= $qrHtml ?></div>
            <div class="rmu-qr-text">
              <strong>QR Document Verification</strong>
              Scan this QR code to verify application authenticity and live admission status on the official RMU verification portal.<br>
              <span style="font-family:monospace; font-size:10px; color:#334155;">ID: <?= e($data['application_id']) ?></span>
            </div>
          </div>

          <div class="rmu-auth-sign">
            <div class="rmu-auth-line">Authorized Admission Officer</div>
            <div class="rmu-auth-sub">Office of Admissions &bull; <?= e($b['name_en']) ?></div>
          </div>
        </div>
      </div>
    </div>
    <?php
    return (string)ob_get_clean();
}

/**
 * Render the SEPARATE Official Payment Receipt HTML.
 * Strictly requires `payment_status === 'Paid'`. Never fabricates a Paid receipt when unpaid.
 */
function render_admission_payment_receipt_html(array $adm, array $options = []): string {
    if (($adm['payment_status'] ?? '') !== 'Paid') {
        throw new RuntimeException('Payment receipt cannot be generated because admission fee payment is not verified as Paid.');
    }

    $pdo = $options['db'] ?? getDb();
    $isPdf = !empty($options['for_pdf']);
    $data = enrich_admission_document_data($adm, $pdo);
    $raw = $data['raw'];
    $b = $data['branding'];

    $logoSrc = ($isPdf && $b['logo_data_uri'] !== '') ? $b['logo_data_uri'] : $b['logo_url'];
    $qrHtml = $isPdf
        ? '<img src="' . e($data['qr_data_uri']) . '" alt="QR Verification">'
        : $data['qr_svg'];

    $amountFmt = '৳' . number_format((float)($raw['payment_amount'] ?? 150.00), 2) . ' BDT';
    $trxId = trim((string)($raw['transaction_id'] ?? 'N/A'));
    $method = trim((string)($raw['payment_method'] ?? 'Online Payment'));
    $sender = trim((string)($raw['sender_number'] ?: ($raw['mobile'] ?? 'N/A')));
    $paidDate = !empty($raw['paid_at']) ? date('d M Y, h:i A', strtotime((string)$raw['paid_at'])) : 'Verified';

    ob_start();
    ?>
    <div id="paymentReceiptPrint" class="rmu-doc-card">
      <div class="rmu-doc-header">
        <div class="rmu-doc-brand">
          <img src="<?= e($logoSrc) ?>" alt="<?= e($b['name_en_upper']) ?> Logo" class="rmu-doc-logo">
          <div class="rmu-doc-brand-text">
            <h2><?= e($b['name_en_upper']) ?></h2>
            <div class="rmu-doc-bn"><?= e($b['name_bn']) ?></div>
            <div class="rmu-doc-contact">
              <?= e($b['address']) ?><br>
              Phone: <?= e($b['phone']) ?> &bull; Email: <?= e($b['email']) ?>
            </div>
          </div>
        </div>
        <div class="rmu-doc-title-box">
          <div class="rmu-doc-title-pill">OFFICIAL PAYMENT RECEIPT</div>
          <div class="rmu-doc-session-tag">Verification Status: <strong style="color:#86efac;">Paid / Verified</strong></div>
        </div>
      </div>

      <div class="rmu-doc-body">
        <div style="background:#f0fdf4; border:1.5px solid #86efac; border-left:5px solid #15803d; border-radius:6px; padding:12px 16px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
          <div>
            <span style="font-size:10.5px; text-transform:uppercase; font-weight:800; color:#166534; letter-spacing:0.6px; display:block;">Admission Application Fee Receipt</span>
            <strong style="font-size:17px; color:#0f2c59; font-family:Consolas, monospace;"><?= e($data['application_id']) ?></strong>
          </div>
          <div style="text-align:right;">
            <span style="font-size:10.5px; text-transform:uppercase; font-weight:700; color:#166534; display:block;">Amount Paid</span>
            <strong style="font-size:20px; font-weight:900; color:#15803d;"><?= e($amountFmt) ?></strong>
          </div>
        </div>

        <div class="rmu-doc-section">
          <div class="rmu-doc-section-hd">Payment &amp; Applicant Particulars</div>
          <table class="rmu-info-table">
            <tbody>
              <tr>
                <th>Institution Name</th>
                <td colspan="3"><strong><?= e($b['name_en']) ?></strong> (<?= e($b['name_bn']) ?>)</td>
              </tr>
              <tr>
                <th>Student Full Name</th>
                <td><strong><?= e($raw['full_name'] ?? '') ?></strong></td>
                <th>Application ID</th>
                <td><strong style="font-family:monospace; color:#0f2c59;"><?= e($data['application_id']) ?></strong></td>
              </tr>
              <tr>
                <th>Program &amp; Session</th>
                <td><?= e($data['program_name']) ?> (<?= e($data['academic_session']) ?>)</td>
                <th>Admission Roll No</th>
                <td style="font-family:monospace;"><?= e($data['roll_number'] !== '' ? $data['roll_number'] : '—') ?></td>
              </tr>
              <tr>
                <th>Payment Amount</th>
                <td><strong style="color:#15803d; font-size:13px;"><?= e($amountFmt) ?></strong></td>
                <th>Transaction ID</th>
                <td><strong style="font-family:monospace; color:#0f2c59;"><?= e($trxId) ?></strong></td>
              </tr>
              <tr>
                <th>Payment Method</th>
                <td><strong><?= e($method) ?></strong></td>
                <th>Sender Number</th>
                <td style="font-family:monospace;"><strong><?= e($sender) ?></strong></td>
              </tr>
              <tr>
                <th>Payment Date</th>
                <td><?= e($paidDate) ?></td>
                <th>Payment Verification Status</th>
                <td><span class="rmu-badge rmu-badge-paid">Paid / Verified</span></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="rmu-doc-footer">
          <div class="rmu-qr-block">
            <div class="rmu-qr-box"><?= $qrHtml ?></div>
            <div class="rmu-qr-text">
              <strong>Verify Payment &amp; Application</strong>
              Scan to verify this payment status and official admission record at RMU.<br>
              <span style="font-family:monospace; font-size:10px; color:#334155;">TrxID: <?= e($trxId) ?></span>
            </div>
          </div>

          <div class="rmu-auth-sign">
            <div class="rmu-auth-line">Authorized Accounts / Admission Officer</div>
            <div class="rmu-auth-sub"><?= e($b['name_en']) ?> &bull; Kushtia</div>
          </div>
        </div>
      </div>
    </div>
    <?php
    return (string)ob_get_clean();
}

/**
 * Wrap card or receipt HTML in a complete standalone UTF-8 HTML document formatted for A4 Portrait PDF.
 */
function build_admission_pdf_html_wrapper(string $bodyFragment, string $docTitle, string $appRef): string {
    $styles = get_admission_document_styles();
    $safeTitle = htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8');
    $safeRef = htmlspecialchars($appRef, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{$safeTitle}</title>
  <style>{$styles}</style>
  <style>
    @page {
      size: A4 portrait;
      margin: 7.5mm 8mm 11mm 8mm;
      @bottom-left {
        content: "RABINDRA MAITREE UNIVERSITY • Official Admission Record ({$safeRef})";
        font-family: 'Nirmala UI', 'Segoe UI', Arial, sans-serif;
        font-size: 7.5pt;
        color: #475569;
      }
      @bottom-right {
        content: "Page " counter(page) " of " counter(pages);
        font-family: 'Nirmala UI', 'Segoe UI', Arial, sans-serif;
        font-size: 8pt;
        font-weight: 700;
        color: #0f2c59;
      }
    }
    * {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    html, body {
      margin: 0;
      padding: 0;
      background: #ffffff;
      color: #0f172a;
      font-family: 'Nirmala UI', 'Noto Sans Bengali', 'Poppins', 'Segoe UI', Arial, sans-serif;
    }
    .rmu-pdf-sheet {
      width: 100%;
    }
    .rmu-pdf-sheet .rmu-doc-card {
      box-shadow: none !important;
      border-radius: 4px;
      overflow: visible !important;
    }
    .rmu-pdf-sheet .rmu-doc-header {
      padding: 8px 13px;
      gap: 9px;
    }
    .rmu-pdf-sheet .rmu-doc-logo {
      width: 44px;
      height: 44px;
    }
    .rmu-pdf-sheet .rmu-doc-brand-text h2 {
      font-size: 15px;
    }
    .rmu-pdf-sheet .rmu-doc-bn {
      font-size: 12px;
    }
    .rmu-pdf-sheet .rmu-doc-contact {
      font-size: 9px;
      margin-top: 2px;
      line-height: 1.25;
    }
    .rmu-pdf-sheet .rmu-doc-title-pill {
      font-size: 9.8px;
      padding: 3px 10px;
    }
    .rmu-pdf-sheet .rmu-doc-session-tag {
      font-size: 9.2px;
      margin-top: 3px;
    }
    .rmu-pdf-sheet .rmu-doc-body {
      padding: 8px 12px 9px;
    }
    .rmu-pdf-sheet .rmu-app-hero {
      grid-template-columns: 1fr 110px;
      gap: 9px;
      margin-bottom: 7px;
    }
    .rmu-pdf-sheet .rmu-app-id-banner {
      padding: 6px 10px;
      gap: 4px;
    }
    .rmu-pdf-sheet .rmu-app-id-top {
      padding-bottom: 4px;
    }
    .rmu-pdf-sheet .rmu-app-id-value {
      font-size: 15px;
    }
    .rmu-pdf-sheet .rmu-app-meta-grid {
      gap: 4px 8px;
      font-size: 9.8px;
    }
    .rmu-pdf-sheet .rmu-app-meta-item .lbl {
      font-size: 8.2px;
    }
    .rmu-pdf-sheet .rmu-app-meta-item .val {
      font-size: 9.9px;
    }
    .rmu-pdf-sheet .rmu-photo-frame {
      padding: 4px;
    }
    .rmu-pdf-sheet .rmu-photo-box {
      width: 84px;
      height: 100px;
    }
    .rmu-pdf-sheet .rmu-photo-caption {
      margin-top: 3px;
      font-size: 8.5px;
    }
    .rmu-pdf-sheet .rmu-status-strip {
      gap: 7px;
      margin-bottom: 7px;
    }
    .rmu-pdf-sheet .rmu-status-tile {
      padding: 4.5px 8px;
    }
    .rmu-pdf-sheet .rmu-status-tile .st-lbl {
      font-size: 8.2px;
      margin-bottom: 1.5px;
    }
    .rmu-pdf-sheet .rmu-badge {
      padding: 1.5px 7px;
      font-size: 9.2px;
    }
    .rmu-pdf-sheet .rmu-doc-section {
      margin-bottom: 6.5px;
      overflow: visible !important;
      break-inside: avoid;
      page-break-inside: avoid;
    }
    .rmu-pdf-sheet .rmu-doc-section-hd {
      padding: 3.5px 9px;
      font-size: 9.2px;
    }
    .rmu-pdf-sheet .rmu-info-table {
      font-size: 9.5px;
    }
    .rmu-pdf-sheet .rmu-info-table th,
    .rmu-pdf-sheet .rmu-info-table td {
      padding: 3.2px 6.5px;
      line-height: 1.22;
    }
    .rmu-pdf-sheet .rmu-acad-table {
      font-size: 9.1px;
    }
    .rmu-pdf-sheet .rmu-acad-table th {
      font-size: 8.3px;
      padding: 3.2px 5px;
    }
    .rmu-pdf-sheet .rmu-acad-table td {
      padding: 3.2px 5px;
    }
    .rmu-pdf-sheet .rmu-doc-footer {
      margin-top: 7px;
      padding-top: 7px;
      gap: 10px;
    }
    .rmu-pdf-sheet .rmu-qr-block {
      padding: 5px 8px;
      gap: 8px;
    }
    .rmu-pdf-sheet .rmu-qr-box {
      width: 68px;
      height: 68px;
    }
    .rmu-pdf-sheet .rmu-qr-text {
      font-size: 8.8px;
      line-height: 1.24;
    }
    .rmu-pdf-sheet .rmu-auth-sign {
      padding-top: 12px;
      min-width: 175px;
    }
    .rmu-pdf-sheet .rmu-auth-line {
      font-size: 9.4px;
    }
  </style>
</head>
<body>
  <div class="rmu-pdf-sheet">
    {$bodyFragment}
  </div>
</body>
</html>
HTML;
}

/**
 * Locate Headless Chromium / Microsoft Edge executable for genuine Unicode Bengali + English A4 PDF rendering.
 */
function find_chromium_pdf_executable(): ?string {
    static $resolved = false;
    static $cachedPath = null;
    if ($resolved) {
        return $cachedPath;
    }
    $resolved = true;

    $candidates = [
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/usr/bin/microsoft-edge',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            $cachedPath = $path;
            return $cachedPath;
        }
    }
    return null;
}

/**
 * Recursively remove a temporary directory (best-effort cleanup).
 */
function rmu_rrmdir_temp(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $items = @scandir($dir);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $p = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($p)) {
            rmu_rrmdir_temp($p);
        } else {
            @unlink($p);
        }
    }
    @rmdir($dir);
}

/**
 * Compile standalone HTML into real A4 Portrait PDF binary bytes with selectable Unicode English & Bengali text.
 */
function compile_html_to_pdf_bytes(string $fullHtml): string {
    $browser = find_chromium_pdf_executable();
    if ($browser === null) {
        throw new RuntimeException('Headless PDF engine binary (Microsoft Edge / Chrome) not available.');
    }

    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rmu_pdf_' . bin2hex(random_bytes(8));
    $profileDir = $tmpDir . DIRECTORY_SEPARATOR . 'profile';
    $htmlFile = $tmpDir . DIRECTORY_SEPARATOR . 'document.html';
    $pdfFile = $tmpDir . DIRECTORY_SEPARATOR . 'document.pdf';

    @mkdir($profileDir, 0755, true);
    file_put_contents($htmlFile, $fullHtml);

    try {
        $fileUri = 'file:///' . ltrim(str_replace('\\', '/', $htmlFile), '/');
        $cmd = escapeshellarg($browser)
            . ' --headless=new'
            . ' --disable-gpu'
            . ' --no-sandbox'
            . ' --disable-dev-shm-usage'
            . ' --no-first-run'
            . ' --disable-extensions'
            . ' --allow-file-access-from-files'
            . ' --no-pdf-header-footer'
            . ' --user-data-dir=' . escapeshellarg($profileDir)
            . ' --print-to-pdf=' . escapeshellarg($pdfFile)
            . ' ' . escapeshellarg($fileUri)
            . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if (!is_file($pdfFile) || filesize($pdfFile) < 100) {
            // Retry briefly with --headless (older builds) if --headless=new produced no output
            $cmdFallback = str_replace('--headless=new', '--headless', $cmd);
            exec($cmdFallback, $output, $exitCode);
        }

        if (!is_file($pdfFile) || filesize($pdfFile) < 100) {
            throw new RuntimeException('PDF compilation failed (exit ' . $exitCode . '): ' . implode(' ', $output));
        }

        $bytes = file_get_contents($pdfFile);
        if ($bytes === false || strpos($bytes, '%PDF-') !== 0) {
            throw new RuntimeException('Generated output is not a valid PDF document.');
        }
        return $bytes;
    } finally {
        rmu_rrmdir_temp($tmpDir);
    }
}

/**
 * Auto-generate and atomically persist an individual Application PDF into storage/admissions/pdf/.
 */
function generate_admission_application_pdf_file(int $admissionId, bool $forceRegenerate = true, ?PDO $db = null): array {
    $pdo = $db ?: getDb();
    ensure_admission_application_system_schema($pdo);

    $adm = fetch_admission_full_record($admissionId, '', $pdo);
    if (!$adm) {
        return ['success' => false, 'error' => 'Admission record not found.'];
    }
    if (($adm['payment_status'] ?? '') !== 'Paid') {
        invalidate_admission_application_pdf_cache($admissionId, $pdo);
        return ['success' => false, 'error' => 'Application Card PDF cannot be generated unless ৳150 fee payment is verified as Paid.'];
    }

    $pdfDir = BASE_PATH . '/storage/admissions/pdf';
    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0755, true);
    }

    $appId = !empty($adm['application_id']) ? $adm['application_id'] : ($adm['application_ref'] ?? ('ADM-' . $admissionId));
    $safeRef = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$appId);
    $filename = 'application_' . $safeRef . '_' . (int)$adm['id'] . '.pdf';
    $relPath = 'storage/admissions/pdf/' . $filename;
    $absPath = BASE_PATH . '/' . $relPath;

    if (!$forceRegenerate && is_file($absPath) && filesize($absPath) > 500) {
        return [
            'success'  => true,
            'rel_path' => $relPath,
            'abs_path' => $absPath,
            'filename' => $filename,
        ];
    }

    $cardHtml = render_premium_application_card_html($adm, ['db' => $pdo, 'for_pdf' => true]);
    $fullHtml = build_admission_pdf_html_wrapper($cardHtml, 'Admission Application Card - ' . $appId, (string)$appId);
    $pdfBytes = compile_html_to_pdf_bytes($fullHtml);

    $tmpTarget = $pdfDir . '/.tmp_' . bin2hex(random_bytes(6)) . '.pdf';
    file_put_contents($tmpTarget, $pdfBytes);
    @chmod($tmpTarget, 0644);
    if (!@rename($tmpTarget, $absPath)) {
        file_put_contents($absPath, $pdfBytes);
        @unlink($tmpTarget);
    }

    $pdo->prepare("UPDATE admissions SET pdf_path = ?, pdf_generated_at = NOW() WHERE id = ?")
        ->execute([$relPath, (int)$adm['id']]);

    return [
        'success'   => true,
        'rel_path'  => $relPath,
        'abs_path'  => $absPath,
        'filename'  => $filename,
        'pdf_bytes' => $pdfBytes,
    ];
}

/**
 * Synchronously invalidate cached individual PDF so next view/download or background worker refreshes it.
 */
function invalidate_admission_application_pdf_cache(int $admissionId, ?PDO $db = null): void {
    if ($admissionId <= 0) {
        return;
    }
    try {
        $pdo = $db ?: getDb();
        $stmt = $pdo->prepare("SELECT pdf_path FROM admissions WHERE id = ? LIMIT 1");
        $stmt->execute([$admissionId]);
        $oldRel = trim((string)$stmt->fetchColumn());
        if ($oldRel !== '' && strpos($oldRel, 'storage/admissions/pdf/') === 0) {
            $abs = BASE_PATH . '/' . $oldRel;
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        $pdo->prepare("UPDATE admissions SET pdf_path = NULL, pdf_generated_at = NULL WHERE id = ?")
            ->execute([$admissionId]);
    } catch (Throwable $e) {
        error_log('Admission PDF cache invalidation warning for #' . $admissionId . ': ' . $e->getMessage());
    }
}

/**
 * Trigger non-blocking background PDF generation for an admission record so HTTP form/admin actions stay instantaneous.
 */
function refresh_admission_application_pdf_async(int $admissionId, ?PDO $db = null): void {
    if ($admissionId <= 0) {
        return;
    }
    invalidate_admission_application_pdf_cache($admissionId, $db);

    try {
        $pdo = $db ?: getDb();
        $payCheck = $pdo->prepare("SELECT payment_status FROM admissions WHERE id = ? LIMIT 1");
        $payCheck->execute([$admissionId]);
        if ((string)$payCheck->fetchColumn() !== 'Paid') {
            return;
        }
    } catch (Throwable $e) {
        return;
    }

    $worker = BASE_PATH . DIRECTORY_SEPARATOR . 'actions' . DIRECTORY_SEPARATOR . 'generate-admission-pdf-cli.php';
    if (!is_file($worker)) {
        return;
    }

    $phpBin = (defined('PHP_BINARY') && PHP_BINARY !== '') ? PHP_BINARY : 'php';
    $argId = (string)(int)$admissionId;
    $argAppUrl = defined('APP_URL') ? escapeshellarg(APP_URL) : escapeshellarg('http://127.0.0.1:8000');

    if (DIRECTORY_SEPARATOR === '\\') {
        $cmd = 'start "" /B ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($worker) . ' ' . $argId . ' ' . $argAppUrl . ' > NUL 2>&1';
        $proc = @popen($cmd, 'r');
        if (is_resource($proc)) {
            @pclose($proc);
            return;
        }
    } else {
        $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($worker) . ' ' . $argId . ' ' . $argAppUrl . ' > /dev/null 2>&1 &';
        @exec($cmd);
        return;
    }

    // Fallback if process spawn is disabled
    refresh_admission_application_pdf_safe($admissionId, $db);
}

/**
 * Non-throwing synchronous wrapper to refresh an admission's cached individual PDF.
 */
function refresh_admission_application_pdf_safe(int $admissionId, ?PDO $db = null): void {
    if ($admissionId <= 0) {
        return;
    }
    try {
        generate_admission_application_pdf_file($admissionId, true, $db);
    } catch (Throwable $e) {
        error_log('Admission PDF refresh warning for #' . $admissionId . ': ' . $e->getMessage());
    }
}

/**
 * Generate Separate Official Payment Receipt PDF bytes (Paid records only).
 */
function generate_payment_receipt_pdf_bytes(array $adm, ?PDO $db = null): string {
    $pdo = $db ?: getDb();
    $receiptHtml = render_admission_payment_receipt_html($adm, ['db' => $pdo, 'for_pdf' => true]);
    $appId = !empty($adm['application_id']) ? $adm['application_id'] : ($adm['application_ref'] ?? '');
    $fullHtml = build_admission_pdf_html_wrapper($receiptHtml, 'Official Payment Receipt - ' . $appId, (string)$appId);
    return compile_html_to_pdf_bytes($fullHtml);
}

/**
 * Stream or download an Application PDF cleanly over HTTP.
 * Serves fresh pre-generated cached PDF when up to date, otherwise generates synchronously on demand.
 */
function output_admission_application_pdf(array $adm, bool $download = false, ?PDO $db = null): void {
    if (($adm['payment_status'] ?? '') !== 'Paid') {
        invalidate_admission_application_pdf_cache((int)($adm['id'] ?? 0), $db);
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Application Card PDF is locked until ৳150 payment verification is completed.');
    }

    $cachedRel = trim((string)($adm['pdf_path'] ?? ''));
    $cachedGenAt = trim((string)($adm['pdf_generated_at'] ?? ''));
    $updatedAt = trim((string)($adm['updated_at'] ?? ''));
    $cachedAbs = ($cachedRel !== '') ? (BASE_PATH . '/' . $cachedRel) : '';

    $useCached = (
        $cachedRel !== ''
        && $cachedGenAt !== ''
        && $cachedAbs !== ''
        && is_file($cachedAbs)
        && filesize($cachedAbs) > 500
        && ($updatedAt === '' || strtotime($cachedGenAt) >= strtotime($updatedAt))
        && (time() - strtotime($cachedGenAt)) < 300
    );

    if ($useCached) {
        $pdfBinary = file_get_contents($cachedAbs);
    } else {
        $res = generate_admission_application_pdf_file((int)$adm['id'], true, $db);
        if (empty($res['success']) || empty($res['abs_path']) || !is_file($res['abs_path'])) {
            http_response_code(500);
            exit('Unable to generate Application PDF.');
        }
        $pdfBinary = $res['pdf_bytes'] ?? file_get_contents($res['abs_path']);
    }
    $appId = !empty($adm['application_id']) ? $adm['application_id'] : ($adm['application_ref'] ?? ('ADM-' . $adm['id']));
    $safeName = 'RMU_Application_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$appId) . '.pdf';
    $disposition = $download ? 'attachment' : 'inline';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
    header('Content-Length: ' . strlen($pdfBinary));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $pdfBinary;
    exit;
}

/**
 * Stream or download a Separate Payment Receipt PDF cleanly over HTTP (Paid records only).
 */
function output_admission_payment_receipt_pdf(array $adm, bool $download = false, ?PDO $db = null): void {
    $pdfBinary = generate_payment_receipt_pdf_bytes($adm, $db);
    $appId = !empty($adm['application_id']) ? $adm['application_id'] : ($adm['application_ref'] ?? ('ADM-' . $adm['id']));
    $safeName = 'RMU_Payment_Receipt_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$appId) . '.pdf';
    $disposition = $download ? 'attachment' : 'inline';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
    header('Content-Length: ' . strlen($pdfBinary));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $pdfBinary;
    exit;
}
