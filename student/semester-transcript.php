<?php
/**
 * Rabindra Maitree University - Official Academic Transcript & Semester Grade Sheet
 * Complete 8-Semester Academic Transcript & CGPA Management System
 * Matches RMU Official Transcript Reference Design, UGC Uniform Grading Scale
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
$isAdminViewing = false;

// Authentication: Either logged-in Student OR logged-in Admin viewing student transcript
$student = null;
$hasAnyEstimated = false;
if (is_logged_in() && !empty($_GET['student_id'])) {
    $isAdminViewing = true;
    $targetStudentId = (int)$_GET['student_id'];
    $stmt = $db->prepare("
        SELECT s.*, p.title_en AS program_name, p.slug AS program_slug, d.name_en AS department_name, f.name_en AS faculty_name
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN faculties f ON d.faculty_id = f.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$targetStudentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$student) {
    require_student_login();
    $student = get_logged_in_student();
    if ($student) {
        $facStmt = $db->prepare("
            SELECT f.name_en AS faculty_name
            FROM departments d
            LEFT JOIN faculties f ON d.faculty_id = f.id
            WHERE d.id = ?
        ");
        $facStmt->execute([(int)($student['department_id'] ?? 0)]);
        $student['faculty_name'] = $facStmt->fetchColumn() ?: 'Faculty of Science & Engineering';
    }
}

if (!$student) {
    flash_set('error', 'Student record not found.');
    header('Location: ' . APP_URL . '/student/login.php');
    exit;
}

$studentId = (int)$student['id'];

// Retrieve structured academic summary (published & visible for students, all for admin)
$academicSummary = get_student_academic_summary($studentId, !$isAdminViewing);
$hasResults = $academicSummary['has_results'];
$isResultsHidden = !empty($academicSummary['results_hidden']);
$isResultsSuspended = !empty($academicSummary['results_suspended']);
$progStruct = $academicSummary['program_structure'] ?? [
    'degree_type' => $student['degree_type'] ?? 'Bachelor / Honours',
    'duration_label' => $student['program_duration'] ?? '4 Years (8 Semesters)',
    'total_semesters' => (int)($student['total_semesters'] ?? 8),
];
$totalSemesters = max(1, (int)($progStruct['total_semesters'] ?? 8));
$semSchedule = $academicSummary['semester_schedule'] ?? [];
$pubDateDisplay = !empty($student['result_publication_date']) ? format_date($student['result_publication_date']) : '';
$hasAnyEstimated = !empty($academicSummary['has_estimated_results']);
$groupedSemesters = $academicSummary['semesters'] ?? [];
$allSemesters = $academicSummary['all_semesters'] ?? [];
$availableSemesters = array_keys($groupedSemesters);
$semesterGpas = $academicSummary['semester_gpas'] ?? [];
$finalCgpa = $academicSummary['final_cgpa'] ?? 0.00;
$totalRegCredits = $academicSummary['total_registered_credits'] ?? 0.0;
$totalEarnedCredits = $academicSummary['total_earned_credits'] ?? 0.0;
$totalReqCredits = (int)($academicSummary['total_required_credits'] ?? ($student['total_credits_required'] ?? 140));
$publishedSemCount = (int)($academicSummary['published_semesters_count'] ?? count($groupedSemesters));
$overallStatus = $academicSummary['overall_academic_status'] ?? ($student['status'] ?? 'Active');

if ($isResultsSuspended && !$isAdminViewing) {
    $hasResults = false;
    $hasAnyEstimated = false;
    $groupedSemesters = [];
    $allSemesters = [];
    $availableSemesters = [];
    $semesterGpas = [];
    $finalCgpa = 0.00;
    $totalRegCredits = 0.0;
    $totalEarnedCredits = 0.0;
    $publishedSemCount = 0;
    $overallStatus = 'Result Access Suspended';
}

// Determine requested semester (always default to consolidated full transcript)
$requestedSemester = trim($_GET['semester'] ?? '');
if ($requestedSemester === '') {
    $requestedSemester = 'all';
}

$isConsolidated = ($requestedSemester === 'all');
$singleSemData = null;
if (!$isConsolidated) {
    if (isset($groupedSemesters[$requestedSemester])) {
        $singleSemData = $groupedSemesters[$requestedSemester];
    } else {
        $reqSemNum = get_semester_number($requestedSemester);
        if ($reqSemNum >= 1 && $reqSemNum <= $totalSemesters && isset($allSemesters[$reqSemNum])) {
            $singleSemData = $allSemesters[$reqSemNum];
        }
    }
}

// Mode-specific metrics
if ($isConsolidated) {
    $displaySemCredits = $totalRegCredits;
    $displayEarnedCredits = $totalEarnedCredits;
    $displaySgpa = $finalCgpa;
    $displayCgpa = $finalCgpa;
} else {
    $currStat = $singleSemData;
    $displaySemCredits = $currStat ? (float)($currStat['registered_credits'] ?? 0.0) : 0.0;
    $displayEarnedCredits = $currStat ? (float)($currStat['earned_credits'] ?? 0.0) : 0.0;
    $displaySgpa = ($currStat && $currStat['sgpa'] !== null) ? (float)$currStat['sgpa'] : 0.00;
    $displayCgpa = ($currStat && $currStat['cumulative_cgpa'] !== null) ? (float)$currStat['cumulative_cgpa'] : 0.00;
}

// Transcript unique serial & verification codes
$transcriptSerial = 'RMU-TR-' . date('Y') . '-' . strtoupper(substr(md5($student['student_id'] . $requestedSemester), 0, 8));
$issueDate = date('d F Y');

// Genuine binary PDF transcript download endpoint (?download=pdf)
if (($_GET['download'] ?? '') === 'pdf') {
    if (!$isAdminViewing && ($isResultsSuspended || $isResultsHidden)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        $blockTitle = $isResultsSuspended ? 'Result Access Suspended' : 'Academic Results Temporarily Hidden';
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($blockTitle) . '</title></head><body style="font-family:Arial,sans-serif;padding:2rem;text-align:center;"><h2 style="color:#9a3412;">' . htmlspecialchars($blockTitle) . '</h2><p>PDF transcript download is unavailable while academic results are restricted.</p></body></html>';
        exit;
    }
    require_once __DIR__ . '/../includes/pdf_transcript.php';
    output_student_transcript_pdf($student, $academicSummary, $requestedSemester, $transcriptSerial, $issueDate, $isAdminViewing);
}

$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <?php include BASE_PATH . '/includes/pwa-head.php'; ?>
  <title>Official Academic Transcript - <?= e($student['full_name']) ?> | Rabindra Maitree University</title>
  <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/assets/images/logo.jpg">
  
  <!-- Google Fonts: Cinzel & Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700;800;900&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <!-- FontAwesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    :root {
      --rmu-navy: #0f2c59;
      --rmu-blue: #1e73be;
      --rmu-gold: #c59b27;
      --rmu-dark: #1e293b;
      --rmu-muted: #475569;
      --rmu-border: #cbd5e1;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      background: #f1f5f9;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      color: var(--rmu-dark);
      -webkit-font-smoothing: antialiased;
    }

    /* Top Action Bar (Screen Only) */
    .action-bar {
      position: sticky;
      top: 0;
      z-index: 100;
      background: #0f2c59;
      color: #ffffff;
      padding: 0.85rem 1.5rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .action-bar-left {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .action-bar-title {
      font-size: 1rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .semester-picker-form {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      min-width: 0;
      max-width: 100%;
      flex-wrap: wrap;
    }

    .semester-picker-select {
      background: #1e3a8a;
      color: #ffffff;
      border: 1px solid #3b82f6;
      border-radius: 6px;
      padding: 0.45rem 0.85rem;
      font-size: 0.88rem;
      font-weight: 600;
      outline: none;
      cursor: pointer;
      max-width: 100%;
      min-width: 0;
    }

    .semester-picker-select option {
      background: #ffffff;
      color: #0f172a;
    }

    .action-bar-right {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .btn-download-pdf {
      background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
      color: #ffffff;
      border: none;
      padding: 0.55rem 1.25rem;
      border-radius: 6px;
      font-size: 0.9rem;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 2px 6px rgba(22, 163, 74, 0.3);
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .btn-download-pdf:hover {
      background: #15803d;
      transform: translateY(-1px);
    }

    .btn-portal-back {
      background: rgba(255, 255, 255, 0.15);
      color: #ffffff;
      border: 1px solid rgba(255, 255, 255, 0.3);
      padding: 0.55rem 1rem;
      border-radius: 6px;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      text-decoration: none;
      transition: background 0.2s;
    }

    .btn-portal-back:hover {
      background: rgba(255, 255, 255, 0.25);
    }

    /* Page Container */
    .sheet-wrapper {
      max-width: 960px;
      margin: 2rem auto;
      padding: 0 1rem;
    }

    /* The Printable Document Sheet */
    .transcript-sheet {
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 2.8rem 3.2rem;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
      position: relative;
      overflow: hidden;
    }

    /* Watermark Seal in the background */
    .transcript-watermark {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: min(440px, 75vw);
      height: min(440px, 75vw);
      opacity: 0.04;
      pointer-events: none;
      z-index: 1;
    }

    .sheet-content {
      position: relative;
      z-index: 2;
    }

    /* Official University Header */
    .uni-header {
      display: grid;
      grid-template-columns: 85px 1fr 85px;
      align-items: center;
      gap: 1.5rem;
      border-bottom: 2.5px solid var(--rmu-navy);
      padding-bottom: 1.25rem;
      margin-bottom: 1.5rem;
    }

    .uni-logo {
      width: 82px;
      height: 82px;
      object-fit: contain;
    }

    .uni-header-text {
      text-align: center;
    }

    .uni-title-en {
      font-family: 'Cinzel', serif;
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--rmu-navy);
      letter-spacing: 0.5px;
      margin: 0 0 0.2rem;
      text-transform: uppercase;
    }

    .uni-subtitle {
      font-size: 0.88rem;
      font-weight: 600;
      color: var(--rmu-muted);
      margin-bottom: 0.2rem;
    }

    .uni-ugc {
      font-size: 0.78rem;
      color: #64748b;
      font-weight: 500;
    }

    .uni-office {
      font-size: 0.88rem;
      font-weight: 800;
      color: var(--rmu-navy);
      margin-top: 0.35rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Transcript Title Ribbon */
    .transcript-badge-wrapper {
      text-align: center;
      margin-bottom: 1.75rem;
    }

    .transcript-title-badge {
      display: inline-block;
      background: var(--rmu-navy);
      color: #ffffff;
      padding: 0.45rem 1.75rem;
      border-radius: 4px;
      font-size: 1.05rem;
      font-weight: 800;
      letter-spacing: 1.2px;
      text-transform: uppercase;
      border: 1.5px solid #1e3a8a;
    }

    .transcript-meta-strip {
      display: flex;
      justify-content: space-between;
      font-size: 0.82rem;
      color: var(--rmu-muted);
      font-weight: 600;
      margin-top: 0.6rem;
      border-bottom: 1px dashed var(--rmu-border);
      padding-bottom: 0.5rem;
    }

    /* Student Information Matrix (9 Fields) */
    .student-info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.4rem 2rem;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      padding: 1rem 1.25rem;
      margin-bottom: 1.75rem;
      font-size: 0.88rem;
    }

    .info-row {
      display: flex;
      padding: 3px 0;
    }

    .info-label {
      width: 155px;
      color: #64748b;
      font-weight: 500;
      font-size: 0.84rem;
      flex-shrink: 0;
    }

    .info-colon {
      width: 15px;
      color: #64748b;
      flex-shrink: 0;
    }

    .info-value {
      font-weight: 700;
      color: #0f172a;
      flex: 1;
      min-width: 0;
      overflow-wrap: anywhere;
    }

    .info-value.highlight {
      color: var(--rmu-blue);
      font-family: monospace;
      font-size: 0.95rem;
    }

    /* Course Grade Table */
    .transcript-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 1.5rem;
      font-size: 0.86rem;
    }

    .transcript-table th {
      background: #f1f5f9;
      color: #0f2c59;
      font-weight: 700;
      text-transform: uppercase;
      font-size: 0.76rem;
      letter-spacing: 0.5px;
      padding: 0.75rem 0.65rem;
      border: 1px solid #cbd5e1;
      text-align: left;
    }

    .transcript-table td {
      padding: 0.65rem;
      border: 1px solid #cbd5e1;
      color: #1e293b;
    }

    .transcript-table tr:nth-child(even) td {
      background: #fafbfc;
    }

    .col-center {
      text-align: center !important;
    }

    .col-right {
      text-align: right !important;
    }

    .grade-badge {
      display: inline-block;
      font-weight: 800;
      color: #0f2c59;
      font-size: 0.92rem;
    }

    /* 8-Semester GPA Breakdown Strip */
    .gpa-progression-box {
      border: 1.5px solid #0f2c59;
      background: #f8fafc;
      border-radius: 6px;
      padding: 1rem 1.25rem;
      margin-bottom: 1.75rem;
    }

    .gpa-progression-title {
      font-size: 0.86rem;
      font-weight: 800;
      color: #0f2c59;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.75rem;
      border-bottom: 1px solid #cbd5e1;
      padding-bottom: 4px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .gpa-grid {
      display: grid;
      grid-template-columns: repeat(8, 1fr) 1.2fr;
      gap: 6px;
      text-align: center;
    }

    .gpa-cell {
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      padding: 6px 3px;
    }

    .gpa-cell.final {
      background: #f0fdf4;
      border: 1.5px solid #16a34a;
    }

    .gpa-cell-lbl {
      font-size: 0.7rem;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      margin-bottom: 2px;
    }

    .gpa-cell-val {
      font-size: 0.95rem;
      font-weight: 800;
      color: #0f2c59;
    }

    .gpa-cell.final .gpa-cell-val {
      color: #15803d;
      font-size: 1.05rem;
    }

    /* Semester Summary & Statistics Box */
    .summary-section {
      display: grid;
      grid-template-columns: 2fr 1.3fr;
      gap: 1.5rem;
      margin-bottom: 2rem;
      align-items: stretch;
    }

    .performance-card {
      border: 1.5px solid #bfdbfe;
      background: #f0f7ff;
      border-radius: 6px;
      padding: 1rem 1.25rem;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.85rem;
    }

    .stat-item {
      display: flex;
      flex-direction: column;
    }

    .stat-label {
      font-size: 0.76rem;
      font-weight: 600;
      color: #475569;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      margin-bottom: 2px;
    }

    .stat-value {
      font-size: 1.25rem;
      font-weight: 800;
      color: #0f2c59;
    }

    .stat-value.primary {
      color: #16a34a;
      font-size: 1.45rem;
    }

    /* UGC Grading Scale Mini Reference */
    .scale-card {
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      background: #ffffff;
      padding: 0.75rem 1rem;
      font-size: 0.74rem;
    }

    .scale-card-title {
      font-weight: 700;
      color: #0f2c59;
      margin-bottom: 0.4rem;
      text-transform: uppercase;
      font-size: 0.72rem;
      border-bottom: 1px solid #e2e8f0;
      padding-bottom: 3px;
    }

    .scale-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 3px 6px;
      color: #334155;
    }

    /* 7. Transcript Summary Block */
    .transcript-summary-box {
      background: #f8fafc;
      border: 1.5px solid #cbd5e1;
      border-radius: 6px;
      padding: 1rem 1.25rem;
      margin-top: 1.5rem;
      margin-bottom: 2.5rem;
    }

    .summary-grid-bottom {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 0.85rem;
      font-size: 0.86rem;
    }

    .cumulative-kpi-strip {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 0.5rem;
      margin-top: 0.75rem;
      padding-top: 0.75rem;
      border-top: 1px dashed #cbd5e1;
    }

    .cumulative-kpi-item {
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      padding: 0.45rem 0.6rem;
      text-align: center;
    }

    .cumulative-kpi-lbl {
      font-size: 0.68rem;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .cumulative-kpi-val {
      font-size: 0.92rem;
      font-weight: 800;
      color: #0f2c59;
      margin-top: 2px;
    }

    /* Official Signatures Section */
    .signature-area {
      margin-top: 3.5rem;
      padding-top: 1rem;
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: flex-end;
      gap: 1.5rem;
    }

    .sig-box {
      text-align: center;
    }

    .sig-line {
      border-bottom: 1.5px solid #475569;
      margin-bottom: 0.45rem;
      height: 38px;
      display: flex;
      align-items: flex-end;
      justify-content: center;
    }

    .sig-title {
      font-size: 0.84rem;
      font-weight: 700;
      color: #0f172a;
    }

    .sig-sub {
      font-size: 0.75rem;
      color: #64748b;
    }

    .seal-box {
      text-align: center;
      padding: 0 1rem;
    }

    .seal-stamp {
      width: 105px;
      height: 105px;
      border: 2px dashed #0f2c59;
      border-radius: 50%;
      display: inline-flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: #0f2c59;
      font-size: 0.68rem;
      font-weight: 800;
      text-transform: uppercase;
      line-height: 1.25;
      padding: 6px;
      box-shadow: inset 0 0 8px rgba(15, 44, 89, 0.08);
      background: rgba(239, 246, 255, 0.4);
    }

    .security-footer {
      margin-top: 2.2rem;
      padding-top: 0.75rem;
      border-top: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.72rem;
      color: #64748b;
    }

    .barcode-sim {
      font-family: monospace;
      letter-spacing: 3px;
      font-weight: 700;
      font-size: 1rem;
      color: #0f172a;
    }

    /* Print Styles: Clean A4 Format */
    @media print {
      @page {
        size: A4 portrait;
        margin: 8mm 10mm 8mm 10mm;
      }

      body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 10.5pt;
      }

      .no-print, .action-bar {
        display: none !important;
      }

      .sheet-wrapper {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      .transcript-sheet {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        border-radius: 0 !important;
      }

      .transcript-title-badge {
        background: #0f2c59 !important;
        color: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      .performance-card, .gpa-progression-box, .transcript-summary-box {
        background: #f8fafc !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      .student-info-grid {
        background: #f8fafc !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      .transcript-table th {
        background: #f1f5f9 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      .transcript-watermark {
        width: 440px !important;
        height: 440px !important;
        opacity: 0.05 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
    }

    @media screen and (max-width: 767px) {
      .action-bar { padding: 0.75rem 1rem; gap: 0.65rem; }
      .action-bar-left, .action-bar-right { flex-wrap: wrap; width: 100%; min-width: 0; }
      .semester-picker-form { width: 100%; }
      .semester-picker-select { width: 100%; max-width: 100%; font-size: 16px; min-height: 44px; text-overflow: ellipsis; overflow: hidden; }
      .btn-download-pdf, .btn-portal-back { min-height: 44px; flex: 1 1 auto; justify-content: center; text-align: center; }
      .transcript-sheet { padding: 1.25rem 1rem; max-width: 100%; }
      .transcript-meta-strip { flex-wrap: wrap; gap: 0.4rem; }
      .uni-header { grid-template-columns: 1fr; text-align: center; gap: 0.75rem; }
      .uni-logo { margin: 0 auto; }
      .student-info-grid, .summary-section, .performance-card, .signature-area { grid-template-columns: 1fr; }
      .student-info-grid > *, .summary-section > *, .performance-card > *, .gpa-progression-box, .transcript-summary-box { min-width: 0; }
      .gpa-grid { grid-template-columns: repeat(2, 1fr) !important; }
      .summary-grid-bottom, .cumulative-kpi-strip, .scale-grid { grid-template-columns: repeat(2, 1fr); }
      .transcript-table { display: block; width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    }
    @media (max-width: 480px) {
      .sheet-wrapper { margin: 0.75rem auto; padding: 0 0.4rem; }
      .transcript-sheet { padding: 0.9rem 0.65rem; }
      .info-row { flex-wrap: wrap; gap: 2px; }
      .info-label { width: 120px; font-size: 0.78rem; }
      .summary-grid-bottom, .cumulative-kpi-strip, .scale-grid { grid-template-columns: 1fr; }
      .security-footer { flex-direction: column; text-align: center; gap: 0.5rem; }
    }
  </style>
</head>
<body>

  <!-- Top Action Bar (Screen Only) -->
  <div class="action-bar no-print">
    <div class="action-bar-left">
      <div class="action-bar-title">
        <i class="fas fa-file-alt"></i>
        <span>Official Academic Transcript</span>
      </div>

      <!-- Semester Selector Form (1st to 8th + Full Consolidated) -->
      <form method="GET" action="" class="semester-picker-form">
        <?php if (!empty($_GET['student_id'])): ?>
          <input type="hidden" name="student_id" value="<?= (int)$_GET['student_id'] ?>">
        <?php endif; ?>
        <label for="semSelect" style="font-size:0.84rem; font-weight:600; color:#cbd5e1;">Select Semester:</label>
        <select name="semester" id="semSelect" class="semester-picker-select" onchange="this.form.submit()">
          <option value="all" <?= ($requestedSemester === 'all') ? 'selected' : '' ?>>
            Full <?= $totalSemesters ?> Semesters (Consolidated Academic Transcript)
          </option>
          <?php for ($sn = 1; $sn <= $totalSemesters; $sn++): ?>
            <?php
              $semSlot = $allSemesters[$sn] ?? null;
              $semOptName = $semSlot['semester_name'] ?? get_semester_ordinal_title($sn);
              $semOptHas = !empty($semSlot['has_courses']);
              $isOptSel = ($semOptName === $requestedSemester) || (!$isConsolidated && get_semester_number($requestedSemester) === $sn);
            ?>
            <option value="<?= e($semOptName) ?>" <?= $isOptSel ? 'selected' : '' ?>>
              Semester <?= $sn ?>: <?= e($semOptName) ?><?= $semOptHas ? '' : ' (Not Published Yet)' ?>
            </option>
          <?php endfor; ?>
          <?php foreach ($groupedSemesters as $extraName => $extraData): ?>
            <?php $exNum = (int)($extraData['semester_number'] ?? 99); ?>
            <?php if ($exNum < 1 || $exNum > $totalSemesters): ?>
              <option value="<?= e($extraName) ?>" <?= ($extraName === $requestedSemester) ? 'selected' : '' ?>>
                Semester <?= $exNum ?>: <?= e($extraName) ?>
              </option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <div class="action-bar-right">
      <a href="?<?= $isAdminViewing ? 'student_id=' . (int)$studentId . '&amp;' : '' ?>semester=<?= urlencode($requestedSemester) ?>&amp;download=pdf" class="btn-download-pdf">
        <i class="fas fa-file-pdf"></i>
        <span>Download Transcript (PDF)</span>
      </a>
      <button type="button" onclick="window.print()" class="btn-portal-back">
        <i class="fas fa-print"></i>
        <span>Print View</span>
      </button>

      <?php if ($isAdminViewing): ?>
        <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= (int)$_GET['student_id'] ?>" class="btn-portal-back">
          <i class="fas fa-arrow-left"></i> Back to Student Profile
        </a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/student/results.php" class="btn-portal-back">
          <i class="fas fa-arrow-left"></i> Back to Grade Sheet
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Document Container -->
  <div class="sheet-wrapper">
    <div class="transcript-sheet" id="printContent">
      
      <!-- Watermark Image in Background -->
      <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="Watermark" class="transcript-watermark" onerror="this.src='<?= APP_URL ?>/assets/images/logo.svg'">

      <div class="sheet-content">

        <!-- University Letterhead Header -->
        <div class="uni-header">
          <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="RMU Monogram" class="uni-logo" onerror="this.src='<?= APP_URL ?>/assets/images/logo.svg'">

          <div class="uni-header-text">
            <h1 class="uni-title-en">Rabindra Maitree University</h1>
            <div class="uni-subtitle">Kushtia, Bangladesh</div>
            <div class="uni-ugc">Approved by the Govt. of Bangladesh &amp; University Grants Commission (UGC)</div>
            <div class="uni-office">Office of the Controller of Examinations</div>
          </div>

          <div style="text-align:right;">
            <div style="width:75px; height:75px; border:1px solid #cbd5e1; border-radius:4px; display:inline-flex; flex-direction:column; align-items:center; justify-content:center; background:#f8fafc; font-size:0.65rem; color:#64748b; font-family:monospace; padding:4px;">
              <i class="fas fa-qrcode" style="font-size:2.2rem; color:#0f2c59; margin-bottom:2px;"></i>
              <span>VERIFIED</span>
            </div>
          </div>
        </div>

        <!-- Document Title Banner -->
        <div class="transcript-badge-wrapper">
          <?php if ($isAdminViewing && $isResultsSuspended): ?>
            <div class="no-print" style="background:#c2410c; color:#ffffff; font-weight:700; font-size:0.82rem; padding:6px 14px; border-radius:4px; margin-bottom:8px; text-align:center;">
              <i class="fas fa-user-lock"></i> ADMIN PREVIEW — Result Access is currently SUSPENDED for this student.
            </div>
          <?php endif; ?>
          <?php if ($isAdminViewing && $isResultsHidden): ?>
            <div class="no-print" style="background:#991b1b; color:#ffffff; font-weight:700; font-size:0.82rem; padding:6px 14px; border-radius:4px; margin-bottom:8px; text-align:center;">
              <i class="fas fa-eye-slash"></i> ADMIN PREVIEW — These results are currently HIDDEN from Student Portal view.
            </div>
          <?php endif; ?>
          <?php if (!empty($hasAnyEstimated)): ?>
            <div style="background:#dc2626; color:#ffffff; font-weight:900; font-size:0.85rem; padding:6px 16px; border-radius:4px; margin-bottom:8px; letter-spacing:0.5px; text-transform:uppercase; text-align:center;">
              <i class="fas fa-exclamation-triangle"></i> ESTIMATED / GENERATED &mdash; ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT
            </div>
            <div class="transcript-title-badge" style="background:#b45309;">
              <?= $isConsolidated ? 'Consolidated Academic Transcript (Unofficial Estimate)' : 'Semester Grade Sheet (Unofficial Estimate)' ?>
            </div>
          <?php else: ?>
            <div class="transcript-title-badge">
              <?= $isConsolidated ? 'Consolidated Academic Transcript' : 'Official Semester Grade Sheet &amp; Transcript' ?>
            </div>
          <?php endif; ?>
          
          <div class="transcript-meta-strip">
            <span><strong>Transcript Serial:</strong> <span style="font-family:monospace;"><?= e($transcriptSerial) ?></span></span>
            <span><strong>Academic Session:</strong> <?= e($student['academic_session'] ?? $student['batch']) ?></span>
            <span><strong>Date of Issue:</strong> <?= e($issueDate) ?></span>
          </div>
        </div>

        <!-- 1. Student Identification Matrix (All 9 Mandatory Requirements + Dynamic Structure) -->
        <?php
          $lastSemOrd = preg_replace('/\s+Semester$/', '', get_semester_ordinal_title($totalSemesters));
          $stSessVal = !empty($student['academic_session']) ? $student['academic_session'] : ($student['batch'] ?? '');
          $stDurY = (float)($student['duration_years'] ?? ($progStruct['duration_years'] ?? ($totalSemesters / 2.0)));
          $stExpCalc = calculate_expected_passing_year($stSessVal, $stDurY);
          $stExpPassYear = $stExpCalc ? (int)$stExpCalc['expected_year'] : null;
        ?>
        <div class="student-info-grid">
          <div>
            <div class="info-row">
              <span class="info-label">Student Name</span>
              <span class="info-colon">:</span>
              <span class="info-value"><?= e($student['full_name']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Student ID / Roll No</span>
              <span class="info-colon">:</span>
              <span class="info-value highlight"><?= e($student['student_id']) ?><?= !empty($student['roll_number']) ? ' / ' . e($student['roll_number']) : '' ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Degree / Course Type</span>
              <span class="info-colon">:</span>
              <span class="info-value"><?= e($progStruct['degree_type'] ?? 'Bachelor / Honours') ?> (<?= e($progStruct['duration_label'] ?? "{$totalSemesters} Semesters") ?>)</span>
            </div>
            <div class="info-row">
              <span class="info-label">Program of Study</span>
              <span class="info-colon">:</span>
              <span class="info-value"><?= e($student['program_name'] ?? 'B.Sc. in Computer Science & Engineering') ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Department</span>
              <span class="info-colon">:</span>
              <span class="info-value"><?= e($student['department_name'] ?? 'Computer Science & Engineering') ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Faculty</span>
              <span class="info-colon">:</span>
              <span class="info-value"><?= e($student['faculty_name'] ?? 'Faculty of Science & Engineering') ?></span>
            </div>
          </div>

          <div>
            <div class="info-row">
              <span class="info-label">Academic Session</span>
              <span class="info-colon">:</span>
              <span class="info-value"><?= e($stSessVal) ?></span>
            </div>
            <?php if (!empty($student['passing_year'])): ?>
              <div class="info-row">
                <span class="info-label">Passing Year</span>
                <span class="info-colon">:</span>
                <span class="info-value"><?= e($student['passing_year']) ?><?php if ($stExpPassYear !== null): ?> <small style="color:#64748b; font-weight:500;">(Est. <?= (int)$stExpPassYear ?>)</small><?php endif; ?></span>
              </div>
            <?php elseif ($stExpPassYear !== null): ?>
              <div class="info-row">
                <span class="info-label">Expected Passing</span>
                <span class="info-colon">:</span>
                <span class="info-value"><?= (int)$stExpPassYear ?> <small style="color:#64748b; font-weight:500;">(Advisory)</small></span>
              </div>
            <?php endif; ?>
            <div class="info-row">
              <span class="info-label">Semester / Term</span>
              <span class="info-colon">:</span>
              <span class="info-value" style="color:var(--rmu-navy); font-weight:800;">
                <?= $isConsolidated ? "All Semesters (1st to {$lastSemOrd} Semester)" : e($requestedSemester) ?>
              </span>
            </div>
            <?php if ($pubDateDisplay !== '' && !($isResultsSuspended && !$isAdminViewing)): ?>
              <div class="info-row">
                <span class="info-label">Publication Date</span>
                <span class="info-colon">:</span>
                <span class="info-value"><?= e($pubDateDisplay) ?></span>
              </div>
            <?php endif; ?>
            <div class="info-row">
              <span class="info-label">Current Academic Status</span>
              <span class="info-colon">:</span>
              <span class="info-value" style="color:<?= ($student['status'] === 'Graduated') ? '#15803d' : '#0369a1' ?>;">
                <?= e($student['status']) ?>
              </span>
            </div>
            <div class="info-row">
              <span class="info-label">Medium of Instruction</span>
              <span class="info-colon">:</span>
              <span class="info-value">English</span>
            </div>
            <div class="info-row">
              <span class="info-label">Cumulative CGPA</span>
              <span class="info-colon">:</span>
              <span class="info-value" style="color:#15803d; font-size:1rem;">
                <?php if ($isResultsSuspended && !$isAdminViewing): ?>
                  <span style="color:#c2410c; font-size:0.88rem; font-weight:800;">Result Access Suspended</span>
                <?php elseif ($hasResults): ?>
                  <?= number_format($finalCgpa, 2) ?> / 4.00
                <?php else: ?>
                  <span style="color:#94a3b8; font-size:0.85rem; font-weight:normal;">No published results yet</span>
                <?php endif; ?>
              </span>
            </div>
          </div>
        </div>

        <?php if (!$hasResults): ?>
          <?php if ($isResultsSuspended && !$isAdminViewing): ?>
            <div style="border:2px solid #fb923c; border-radius:8px; padding:3.5rem 1.5rem; text-align:center; margin:2rem 0; background:#fff7ed;">
              <div style="font-size:2.5rem; color:#c2410c; margin-bottom:1rem;"><i class="fas fa-user-lock"></i></div>
              <h3 style="margin:0 0 0.5rem; font-size:1.25rem; font-weight:800; color:#9a3412;">Result Access Suspended</h3>
              <p style="margin:0 auto; max-width:480px; font-size:0.88rem; color:#7c2d12; line-height:1.6;">
                Your access to view transcripts and semester grade sheets has been temporarily suspended by university administration.
                <?php if (!empty($academicSummary['results_suspended_reason'])): ?>
                  <br><strong>Reason:</strong> <?= e($academicSummary['results_suspended_reason']) ?>
                <?php endif; ?>
              </p>
            </div>
          <?php elseif ($isResultsHidden && !$isAdminViewing): ?>
            <div style="border:1.5px solid #fdba74; border-radius:8px; padding:3.5rem 1.5rem; text-align:center; margin:2rem 0; background:#fff7ed;">
              <div style="font-size:2.5rem; color:#c2410c; margin-bottom:1rem;"><i class="fas fa-eye-slash"></i></div>
              <h3 style="margin:0 0 0.5rem; font-size:1.25rem; font-weight:800; color:#9a3412;">Academic Results Temporarily Hidden</h3>
              <p style="margin:0 auto; max-width:480px; font-size:0.88rem; color:#7c2d12; line-height:1.6;">
                Your transcript and semester grade details are temporarily hidden by the Office of the Controller of Examinations.
                <?php if (!empty($academicSummary['results_hidden_reason'])): ?>
                  <br><strong>Reason:</strong> <?= e($academicSummary['results_hidden_reason']) ?>
                <?php endif; ?>
              </p>
            </div>
          <?php else: ?>
            <!-- Clean empty message if no published results (Requirement 10) -->
            <div style="border:1.5px dashed #cbd5e1; border-radius:8px; padding:3.5rem 1.5rem; text-align:center; margin:2rem 0; background:#f8fafc;">
              <div style="font-size:2.5rem; color:#94a3b8; margin-bottom:1rem;"><i class="fas fa-graduation-cap"></i></div>
              <h3 style="margin:0 0 0.5rem; font-size:1.25rem; font-weight:800; color:#0f2c59;">No academic results published yet</h3>
              <p style="margin:0 auto; max-width:480px; font-size:0.88rem; color:#64748b; line-height:1.6;">
                No course results have been officially published for this student record. Grade sheets and official transcripts will become available once published by the Controller of Examinations.
              </p>
            </div>
          <?php endif; ?>
        <?php else: ?>

          <!-- 6. Overall Cumulative CGPA & Dynamic N-Semester GPA Progression Summary -->
          <div class="gpa-progression-box">
            <div class="gpa-progression-title">
              <span><i class="fas fa-chart-line" style="color:var(--rmu-blue);"></i> <?= ($totalSemesters === 8) ? 'Eight-Semester' : "{$totalSemesters}-Semester" ?> Academic GPA Breakdown</span>
              <span style="font-size:0.8rem; font-weight:700; color:#15803d;">
                Credit-Weighted Formula Applied
              </span>
            </div>
            <div class="gpa-grid" style="grid-template-columns: repeat(<?= min(8, $totalSemesters) ?>, 1fr) 1.2fr;">
              <?php for ($sn = 1; $sn <= $totalSemesters; $sn++): ?>
                <?php 
                  $sg = $semesterGpas[$sn] ?? null;
                  $hasSem = ($sg !== null);
                  $semEst = !empty($semSchedule[$sn]['is_estimated']);
                ?>
                <div class="gpa-cell">
                  <div class="gpa-cell-lbl"><?= e(preg_replace('/\s+Semester$/', '', get_semester_ordinal_title($sn))) ?> Sem<?= ($hasSem && $semEst) ? ' (EST)' : '' ?></div>
                  <div class="gpa-cell-val" style="color:<?= $hasSem ? '#0f2c59' : '#94a3b8' ?>;">
                    <?= $hasSem ? number_format($sg, 2) : '—' ?>
                  </div>
                </div>
              <?php endfor; ?>
              
              <div class="gpa-cell final">
                <div class="gpa-cell-lbl" style="color:#15803d; font-weight:800;">Final CGPA</div>
                <div class="gpa-cell-val">
                  <?= number_format($finalCgpa, 2) ?>
                </div>
              </div>
            </div>

            <div class="cumulative-kpi-strip">
              <div class="cumulative-kpi-item">
                <div class="cumulative-kpi-lbl">Total Registered Credits</div>
                <div class="cumulative-kpi-val"><?= number_format((float)$totalRegCredits, 1) ?> Cr</div>
              </div>
              <div class="cumulative-kpi-item">
                <div class="cumulative-kpi-lbl">Total Earned Credits</div>
                <div class="cumulative-kpi-val" style="color:#15803d;"><?= number_format((float)$totalEarnedCredits, 1) ?> Cr</div>
              </div>
              <div class="cumulative-kpi-item">
                <div class="cumulative-kpi-lbl">Overall Completed Credits</div>
                <div class="cumulative-kpi-val"><?= (int)$totalEarnedCredits ?> / <?= $totalReqCredits ?> Cr</div>
              </div>
              <div class="cumulative-kpi-item">
                <div class="cumulative-kpi-lbl">Published Semesters</div>
                <div class="cumulative-kpi-val"><?= $publishedSemCount ?> / <?= $totalSemesters ?></div>
              </div>
              <div class="cumulative-kpi-item">
                <div class="cumulative-kpi-lbl">Final Academic Status</div>
                <div class="cumulative-kpi-val" style="color:#0369a1; font-size:0.82rem;"><?= e($overallStatus) ?></div>
              </div>
            </div>
          </div>

          <!-- 2 & 3. Course Marks, Grades & SGPA Display -->
          <?php if ($isConsolidated): ?>
            <!-- All Configured Semesters Tables Loop (1st to <?= $totalSemesters ?>th + any extra overflow semesters) -->
            <?php
              $consolidatedRenderList = [];
              for ($sn = 1; $sn <= $totalSemesters; $sn++) {
                  $slot = $allSemesters[$sn] ?? null;
                  $consolidatedRenderList[] = [
                      'num' => $sn,
                      'title' => $slot['semester_name'] ?? get_semester_ordinal_title($sn),
                      'data' => $slot,
                  ];
              }
              foreach ($groupedSemesters as $extraName => $extraData) {
                  $exNum = (int)($extraData['semester_number'] ?? 99);
                  if ($exNum < 1 || $exNum > $totalSemesters) {
                      $consolidatedRenderList[] = [
                          'num' => $exNum,
                          'title' => $extraName,
                          'data' => $extraData,
                      ];
                  }
              }
            ?>
            <?php foreach ($consolidatedRenderList as $semEntry): ?>
              <?php
                $sNum = (int)$semEntry['num'];
                $semKey = (string)$semEntry['title'];
                $semData = $semEntry['data'];
                $hasCourses = ($semData !== null && !empty($semData['courses']));
              ?>
              <div style="margin-bottom: 2rem;" id="sem-section-<?= $sNum ?>">
                <?php if ($hasCourses): ?>
                  <div style="background:#0f2c59; color:#ffffff; padding:0.55rem 1rem; border-radius:4px 4px 0 0; display:flex; justify-content:space-between; align-items:center; font-size:0.86rem; font-weight:700; flex-wrap:wrap; gap:0.5rem;">
                    <span>
                      <i class="fas fa-bookmark" style="color:#f59e0b; margin-right:6px;"></i> Semester <?= $sNum ?>: <?= e($semKey) ?>
                      <?php if (!empty($semData['is_estimated'])): ?>
                        <span style="background:#fef3c7; color:#92400e; font-size:0.68rem; font-weight:800; padding:1px 6px; border-radius:3px; margin-left:6px;">ESTIMATED / GENERATED</span>
                      <?php elseif (isset($semData['all_published']) && !$semData['all_published']): ?>
                        <span style="background:#f59e0b; color:#0f172a; font-size:0.68rem; font-weight:800; padding:1px 6px; border-radius:3px; margin-left:6px;">DRAFT / UNPUBLISHED</span>
                      <?php endif; ?>
                    </span>
                    <span style="font-size:0.82rem; letter-spacing:0.3px;">
                      Reg: <strong><?= number_format((float)$semData['registered_credits'], 1) ?> Cr</strong>
                      &nbsp;&bull;&nbsp;
                      Earned: <strong><?= number_format((float)$semData['earned_credits'], 1) ?> Cr</strong>
                      &nbsp;&bull;&nbsp;
                      Semester SGPA: <strong style="color:#ffffff; font-size:0.92rem;"><?= number_format((float)$semData['sgpa'], 2) ?></strong>
                      &nbsp;&bull;&nbsp;
                      Cumulative CGPA: <strong style="color:#86efac; font-size:0.95rem;"><?= number_format((float)$semData['cumulative_cgpa'], 2) ?></strong>
                    </span>
                  </div>

                  <table class="transcript-table" style="margin-bottom: 0; border-top: none;">
                    <thead>
                      <tr>
                        <th style="width: 40px;" class="col-center">SL</th>
                        <th style="width: 105px;">Subject Code</th>
                        <th>Subject Name</th>
                        <th style="width: 60px;" class="col-center">Credit</th>
                        <th style="width: 75px;" class="col-center">Full Marks</th>
                        <th style="width: 75px;" class="col-center">Obtained</th>
                        <th style="width: 65px;" class="col-center">Grade</th>
                        <th style="width: 80px;" class="col-center">Grade Point</th>
                        <th style="width: 75px;" class="col-center">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php $sl = 1; ?>
                      <?php foreach ($semData['courses'] as $c): ?>
                        <tr>
                          <td class="col-center"><?= $sl++ ?></td>
                          <td style="font-family: monospace; font-weight: 700; color:#0f2c59;"><?= e($c['course_code']) ?></td>
                          <td style="font-weight: 600; color:#1e293b;"><?= e($c['course_name']) ?></td>
                          <td class="col-center" style="font-weight: 600;"><?= number_format($c['credit'], 1) ?></td>
                          <td class="col-center" style="color:#64748b;"><?= (int)$c['full_marks'] ?></td>
                          <td class="col-center" style="font-weight: 700; color:#0f2c59;"><?= ($c['marks'] !== null) ? number_format($c['marks'], 1) : '—' ?></td>
                          <td class="col-center">
                            <span class="grade-badge"><?= e($c['letter_grade']) ?></span>
                          </td>
                          <td class="col-center" style="font-weight: 600;"><?= number_format($c['grade_point'], 2) ?></td>
                          <td class="col-center">
                            <span style="font-size:0.75rem; font-weight:800; color:<?= ($c['status'] === 'Pass') ? '#15803d' : '#dc2626' ?>;">
                              <?= strtoupper($c['status']) ?>
                            </span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                      <tr style="background:#f8fafc; font-weight:700; font-size:0.82rem;">
                        <td colspan="3" style="text-align:right; color:#475569;">Semester Summary (Semester <?= $sNum ?>):</td>
                        <td class="col-center"><?= number_format((float)$semData['registered_credits'], 1) ?> Cr</td>
                        <td colspan="2" class="col-center" style="color:#0f2c59;">Earned: <?= number_format((float)$semData['earned_credits'], 1) ?> Cr</td>
                        <td style="text-align:right; color:#0f2c59;">SGPA: <strong><?= number_format((float)$semData['sgpa'], 2) ?></strong></td>
                        <td colspan="2" class="col-center" style="color:#15803d; font-weight:800;">CGPA: <?= number_format((float)$semData['cumulative_cgpa'], 2) ?></td>
                      </tr>
                    </tfoot>
                  </table>
                <?php else: ?>
                  <div style="background:#e2e8f0; color:#0f2c59; padding:0.5rem 1rem; border-radius:4px 4px 0 0; display:flex; justify-content:space-between; align-items:center; font-size:0.84rem; font-weight:700; flex-wrap:wrap; gap:0.5rem; border:1px solid #cbd5e1; border-bottom:none;">
                    <span><i class="fas fa-clock" style="color:#64748b; margin-right:6px;"></i> Semester <?= $sNum ?>: <?= e($semKey) ?></span>
                    <span style="font-size:0.76rem; color:#475569; background:#ffffff; padding:2px 8px; border-radius:4px; border:1px solid #cbd5e1;">
                      Not Published Yet
                    </span>
                  </div>
                  <table class="transcript-table" style="margin-bottom: 0;">
                    <tbody>
                      <tr>
                        <td colspan="9" class="col-center" style="padding:1rem; color:#64748b; font-style:italic; background:#ffffff;">
                          No published subject results recorded for this semester yet.
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr style="background:#f8fafc; font-weight:700; font-size:0.8rem; color:#64748b;">
                        <td colspan="3" style="text-align:right;">Semester Summary (Semester <?= $sNum ?>):</td>
                        <td class="col-center">0.0 Cr</td>
                        <td colspan="2" class="col-center">Earned: 0.0 Cr</td>
                        <td style="text-align:right;">SGPA: <strong>—</strong></td>
                        <td colspan="2" class="col-center">Status: Pending</td>
                      </tr>
                    </tfoot>
                  </table>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <!-- Single Semester Table -->
            <?php
              $semData = $singleSemData;
              $cList = $semData ? ($semData['courses'] ?? []) : [];
              $sNum = $semData ? (int)$semData['semester_number'] : max(1, get_semester_number($requestedSemester));
            ?>
            <table class="transcript-table">
              <thead>
                <tr>
                  <th style="width: 40px;" class="col-center">SL</th>
                  <th style="width: 105px;">Subject Code</th>
                  <th>Subject Name</th>
                  <th style="width: 60px;" class="col-center">Credit</th>
                  <th style="width: 75px;" class="col-center">Full Marks</th>
                  <th style="width: 75px;" class="col-center">Obtained</th>
                  <th style="width: 65px;" class="col-center">Grade</th>
                  <th style="width: 80px;" class="col-center">Grade Point</th>
                  <th style="width: 75px;" class="col-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($cList)): ?>
                  <tr>
                    <td colspan="9" class="col-center" style="padding: 2.5rem; color:#64748b;">
                      No published subject results recorded for <strong><?= e($requestedSemester) ?></strong> yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php $sl = 1; ?>
                  <?php foreach ($cList as $c): ?>
                    <tr>
                      <td class="col-center"><?= $sl++ ?></td>
                      <td style="font-family: monospace; font-weight: 700; color:#0f2c59;"><?= e($c['course_code']) ?></td>
                      <td style="font-weight: 600; color:#1e293b;"><?= e($c['course_name']) ?></td>
                      <td class="col-center" style="font-weight: 600;"><?= number_format($c['credit'], 1) ?></td>
                      <td class="col-center" style="color:#64748b;"><?= (int)$c['full_marks'] ?></td>
                      <td class="col-center" style="font-weight: 700; color:#0f2c59;"><?= ($c['marks'] !== null) ? number_format($c['marks'], 1) : '—' ?></td>
                      <td class="col-center">
                        <span class="grade-badge"><?= e($c['letter_grade']) ?></span>
                      </td>
                      <td class="col-center" style="font-weight: 600;"><?= number_format($c['grade_point'], 2) ?></td>
                      <td class="col-center">
                        <span style="font-size:0.75rem; font-weight:800; color:<?= ($c['status'] === 'Pass') ? '#15803d' : '#dc2626' ?>;">
                          <?= strtoupper($c['status']) ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
              <tfoot>
                <tr style="background:#f8fafc; font-weight:700;">
                  <td colspan="3" style="text-align:right; color:#475569;">Semester Summary (<?= e($requestedSemester) ?>):</td>
                  <td class="col-center"><?= number_format((float)$displaySemCredits, 1) ?> Cr</td>
                  <td colspan="2" class="col-center" style="color:#0f2c59;">Earned: <?= number_format((float)$displayEarnedCredits, 1) ?> Cr</td>
                  <td style="text-align:right; color:#0f2c59;">SGPA: <strong><?= !empty($cList) ? number_format($displaySgpa, 2) : '—' ?></strong></td>
                  <td colspan="2" class="col-center" style="color:#15803d; font-weight:800; font-size:0.95rem;">CGPA: <?= number_format($displayCgpa, 2) ?></td>
                </tr>
              </tfoot>
            </table>
          <?php endif; ?>

          <!-- 5. Semester Performance Metrics + UGC Scale Reference -->
          <div class="summary-section">
            <!-- Performance Metrics Card -->
            <div class="performance-card">
              <div class="stat-item">
                <span class="stat-label">Registered Credits</span>
                <span class="stat-value"><?= number_format($displaySemCredits, 1) ?></span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Earned Credits</span>
                <span class="stat-value"><?= number_format($displayEarnedCredits, 1) ?></span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Semester GPA (SGPA)</span>
                <span class="stat-value primary"><?= number_format($displaySgpa, 2) ?></span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Cumulative CGPA</span>
                <span class="stat-value primary"><?= number_format($finalCgpa, 2) ?></span>
              </div>
              <div class="stat-item" style="grid-column: span 2; border-top: 1px dashed #bfdbfe; padding-top: 0.5rem; margin-top: 2px;">
                <span class="stat-label">Total Completed Credits (Overall)</span>
                <span style="font-size:0.92rem; font-weight:700; color:#0f2c59;">
                  <?= (int)$totalEarnedCredits ?> Credits / <?= $totalReqCredits ?> Required Credits
                  <span style="color:#16a34a; margin-left: 8px;">(<?= round(($totalEarnedCredits / max(1, $totalReqCredits)) * 100) ?>% Completed)</span>
                </span>
              </div>
            </div>

            <!-- 4. UGC Grading Scale Reference -->
            <div class="scale-card">
              <div class="scale-card-title">UGC Uniform Grading Scale</div>
              <div class="scale-grid">
                <div>80%+ : <strong>A+</strong> (4.00)</div>
                <div>75-79% : <strong>A</strong> (3.75)</div>
                <div>70-74% : <strong>A-</strong> (3.50)</div>
                <div>65-69% : <strong>B+</strong> (3.25)</div>
                <div>60-64% : <strong>B</strong> (3.00)</div>
                <div>55-59% : <strong>B-</strong> (2.75)</div>
                <div>50-54% : <strong>C+</strong> (2.50)</div>
                <div>45-49% : <strong>C</strong> (2.25)</div>
                <div>40-44% : <strong>D</strong> (2.00)</div>
                <div>&lt;40% : <strong style="color:#dc2626;">F</strong> (0.00)</div>
              </div>
              <div style="font-size:0.68rem; color:#64748b; margin-top:6px; border-top:1px dashed #e2e8f0; padding-top:4px;">
                CGPA = Sum of (Subject Credit × Subject Grade Point) ÷ Total Registered Credits
              </div>
            </div>
          </div>

          <!-- 7. Transcript Summary (Bottom) -->
          <div class="transcript-summary-box">
            <div style="font-size:0.86rem; font-weight:800; color:#0f2c59; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.65rem; border-bottom:1px solid #cbd5e1; padding-bottom:4px;">
              <i class="fas fa-file-signature" style="color:var(--rmu-blue);"></i> Official Academic Transcript Summary
            </div>
            <div class="summary-grid-bottom">
              <div>
                <div style="font-size:0.75rem; color:#64748b; text-transform:uppercase;">Total Registered Credits</div>
                <div style="font-size:1.15rem; font-weight:800; color:#0f2c59; margin-top:2px;">
                  <?= number_format((float)$totalRegCredits, 1) ?> Credits
                </div>
              </div>
              <div>
                <div style="font-size:0.75rem; color:#64748b; text-transform:uppercase;">Total Earned Credits</div>
                <div style="font-size:1.15rem; font-weight:800; color:#0f2c59; margin-top:2px;">
                  <?= number_format((float)$totalEarnedCredits, 1) ?> Credits
                </div>
              </div>
              <div>
                <div style="font-size:0.75rem; color:#64748b; text-transform:uppercase;">Completed / Required</div>
                <div style="font-size:1.15rem; font-weight:800; color:#0f2c59; margin-top:2px;">
                  <?= (int)$totalEarnedCredits ?> / <?= $totalReqCredits ?> Cr
                </div>
              </div>
              <div>
                <div style="font-size:0.75rem; color:#64748b; text-transform:uppercase;">Overall Cumulative CGPA</div>
                <div style="font-size:1.25rem; font-weight:900; color:#15803d; margin-top:2px;">
                  <?= number_format($finalCgpa, 2) ?> / 4.00
                </div>
              </div>
              <div>
                <div style="font-size:0.75rem; color:#64748b; text-transform:uppercase;">Final Academic Status</div>
                <div style="font-size:0.88rem; font-weight:800; color:<?= ($student['status'] === 'Graduated') ? '#15803d' : '#0284c7' ?>; margin-top:2px;">
                  <?= e($overallStatus) ?>
                </div>
                <div style="font-size:0.75rem; color:#64748b; margin-top:2px;"><?= e($issueDate) ?></div>
              </div>
            </div>
          </div>

        <?php endif; ?>

        <!-- Official Signatures & University Seal (Reference Design) -->
        <div class="signature-area">
          <div class="sig-box">
            <div class="sig-line">
              <span style="font-family:'Brush Script MT', cursive, sans-serif; font-size:1.35rem; color:#0f2c59; transform:rotate(-3deg);">A. H. Tanvir</span>
            </div>
            <div class="sig-title">Prepared &amp; Tabulated by</div>
            <div class="sig-sub">Office of the Registrar</div>
          </div>

          <div class="seal-box">
            <div class="seal-stamp">
              <i class="fas fa-university" style="font-size:1.1rem; margin-bottom:2px;"></i>
              <span>RABINDRA MAITREE</span>
              <span>UNIVERSITY</span>
              <small style="font-size:0.55rem; letter-spacing:0.5px;">KUSHTIA • SEAL</small>
            </div>
          </div>

          <div class="sig-box">
            <div class="sig-line">
              <span style="font-family:'Brush Script MT', cursive, sans-serif; font-size:1.45rem; color:#0f2c59; transform:rotate(-4deg);">Prof. M. Rahman</span>
            </div>
            <div class="sig-title">Controller of Examinations</div>
            <div class="sig-sub">Rabindra Maitree University</div>
          </div>
        </div>

        <!-- Security Barcode and Notice -->
        <div class="security-footer">
          <div>
            <span>Official Computer-Generated Academic Record • Rabindra Maitree University • Valid without manual stamp if verified online</span>
          </div>
          <div style="text-align:right;">
            <span class="barcode-sim">|||| ||| ||||| |||| || |||||</span>
            <div style="font-size:0.68rem; font-family:monospace;"><?= e($transcriptSerial) ?></div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <?php if ($autoprint): ?>
    <script>
      window.addEventListener('load', function() {
        setTimeout(function() {
          window.print();
        }, 500);
      });
    </script>
  <?php endif; ?>

  <script src="<?= e(rmu_asset_url('assets/js/pwa.js')) ?>"></script>
</body>
</html>
