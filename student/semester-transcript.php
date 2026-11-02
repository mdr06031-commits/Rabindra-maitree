<?php
/**
 * Rabindra Maitree University - Official Semester Grade Sheet & Academic Transcript
 * Downloadable & Printable by Students (for each individual semester or consolidated)
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();

// Authentication: Either logged-in Student OR logged-in Admin viewing student transcript
$student = null;
if (is_logged_in() && !empty($_GET['student_id'])) {
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
        // Also fetch faculty name if available
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

// Get all student results in chronological order
$allResStmt = $db->prepare("SELECT * FROM student_results WHERE student_id = ? ORDER BY id ASC");
$allResStmt->execute([$studentId]);
$allResults = $allResStmt->fetchAll(PDO::FETCH_ASSOC);

// Group results by semester and calculate running cumulative CGPA
$groupedSemesters = [];
foreach ($allResults as $r) {
    $groupedSemesters[$r['semester']][] = $r;
}
$availableSemesters = array_keys($groupedSemesters);

$runningCredits = 0;
$runningPoints = 0;
$semesterRunningStats = [];

foreach ($groupedSemesters as $semKey => $cList) {
    $sCr = array_sum(array_column($cList, 'credit'));
    $sPts = 0;
    foreach ($cList as $c) {
        $sPts += ((float)$c['credit'] * (float)$c['grade_point']);
    }
    $sSgpa = ($sCr > 0) ? round($sPts / $sCr, 2) : 0.00;

    $runningCredits += $sCr;
    $runningPoints += $sPts;
    $sCgpa = ($runningCredits > 0) ? round($runningPoints / $runningCredits, 2) : 0.00;

    $semesterRunningStats[$semKey] = [
        'sem_credits' => $sCr,
        'sem_points' => $sPts,
        'sgpa' => $sSgpa,
        'cum_credits' => $runningCredits,
        'cum_points' => $runningPoints,
        'cgpa' => $sCgpa,
    ];
}

// Determine requested semester
$requestedSemester = trim($_GET['semester'] ?? '');
if (empty($requestedSemester)) {
    if (!empty($availableSemesters)) {
        $requestedSemester = end($availableSemesters); // Default to latest semester
    } else {
        $requestedSemester = 'all';
    }
}

$isConsolidated = ($requestedSemester === 'all');

// Assign metrics based on mode
if ($isConsolidated) {
    $results = $allResults;
    $semCredits = $runningCredits;
    $sgpa = ($runningCredits > 0) ? round($runningPoints / $runningCredits, 2) : (float)$student['cgpa'];
    $calculatedCgpa = $sgpa;
    $totalCreditsEarned = $runningCredits;
} else {
    $results = $groupedSemesters[$requestedSemester] ?? [];
    $currStat = $semesterRunningStats[$requestedSemester] ?? ['sem_credits' => 0, 'sgpa' => 0, 'cum_credits' => 0, 'cgpa' => 0];
    $semCredits = $currStat['sem_credits'];
    $sgpa = $currStat['sgpa'];
    $calculatedCgpa = $currStat['cgpa'];
    $totalCreditsEarned = $currStat['cum_credits'];
}

// Transcript unique serial / verification code
$transcriptSerial = 'RMU-TR-' . date('Y') . '-' . strtoupper(substr(md5($student['student_id'] . $requestedSemester), 0, 8));
$issueDate = date('d F Y');
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Semester Transcript - <?= e($student['full_name']) ?> (<?= e($requestedSemester) ?>) | RMU</title>
  <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/assets/images/logo.jpg">
  
  <!-- Google Fonts: Poppins & Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- FontAwesome -->
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
      max-width: 900px;
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
      top: 52%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 420px;
      height: 420px;
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
      font-size: 1.55rem;
      font-weight: 800;
      color: var(--rmu-navy);
      letter-spacing: 0.5px;
      margin: 0 0 0.2rem;
      text-transform: uppercase;
    }

    .uni-subtitle {
      font-size: 0.85rem;
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
      font-size: 0.85rem;
      font-weight: 700;
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

    /* Student Information Matrix */
    .student-info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.5rem 2rem;
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
      width: 145px;
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
      font-size: 0.78rem;
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
        margin: 10mm 12mm 10mm 12mm;
      }

      body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 11pt;
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

      .performance-card {
        background: #f0f7ff !important;
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
        opacity: 0.05 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
    }
  </style>
</head>
<body>

  <!-- Top Action Bar (Screen Only) -->
  <div class="action-bar no-print">
    <div class="action-bar-left">
      <div class="action-bar-title">
        <i class="fas fa-file-alt"></i>
        <span>Official Semester Transcript</span>
      </div>

      <!-- Semester Selector Form -->
      <form method="GET" action="" class="semester-picker-form">
        <?php if (!empty($_GET['student_id'])): ?>
          <input type="hidden" name="student_id" value="<?= (int)$_GET['student_id'] ?>">
        <?php endif; ?>
        <label for="semSelect" style="font-size:0.84rem; font-weight:600; color:#cbd5e1;">সেমিস্টার বাছাই করুন:</label>
        <select name="semester" id="semSelect" class="semester-picker-select" onchange="this.form.submit()">
          <?php foreach ($availableSemesters as $semName): ?>
            <option value="<?= e($semName) ?>" <?= ($semName === $requestedSemester) ? 'selected' : '' ?>>
              <?= e($semName) ?>
            </option>
          <?php endforeach; ?>
          <option value="all" <?= ($requestedSemester === 'all') ? 'selected' : '' ?>>
            ★ Full 8 Semesters (Consolidated Transcript)
          </option>
        </select>
      </form>
    </div>

    <div class="action-bar-right">
      <button type="button" onclick="window.print()" class="btn-download-pdf">
        <i class="fas fa-download"></i>
        <span>Download / Save as PDF</span>
      </button>

      <?php if (is_logged_in() && !empty($_GET['student_id'])): ?>
        <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= (int)$_GET['student_id'] ?>" class="btn-portal-back">
          <i class="fas fa-arrow-left"></i> Back to Admin Panel
        </a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/student/results.php" class="btn-portal-back">
          <i class="fas fa-arrow-left"></i> Back to Results
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
          <div class="transcript-title-badge">
            <?= $isConsolidated ? 'Consolidated Academic Transcript' : 'Official Semester Grade Sheet &amp; Transcript' ?>
          </div>
          
          <div class="transcript-meta-strip">
            <span><strong>Transcript Serial:</strong> <span style="font-family:monospace;"><?= e($transcriptSerial) ?></span></span>
            <span><strong>Academic Session:</strong> <?= e($student['batch']) ?></span>
            <span><strong>Date of Issue:</strong> <?= e($issueDate) ?></span>
          </div>
        </div>

        <!-- Student Identification Matrix -->
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
              <span class="info-value highlight"><?= e($student['student_id']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Program of Study</span>
              <span class="info-colon">:</span>
              <span class="info-value"><?= e($student['program_name'] ?? 'B.Sc. in Computer Science & Engineering') ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Faculty</span>
              <span class="info-colon">:</span>
              <span class="info-value"><?= e($student['faculty_name'] ?? 'Faculty of Science & Engineering') ?></span>
            </div>
          </div>

          <div>
            <div class="info-row">
              <span class="info-label">Department</span>
              <span class="info-colon">:</span>
              <span class="info-value"><?= e($student['department_name'] ?? 'Computer Science & Engineering') ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Semester / Term</span>
              <span class="info-colon">:</span>
              <span class="info-value" style="color:var(--rmu-navy); font-weight:800;">
                <?= $isConsolidated ? 'All Semesters (1st to 8th Semester)' : e($requestedSemester) ?>
              </span>
            </div>
            <div class="info-row">
              <span class="info-label">Current Academic Status</span>
              <span class="info-colon">:</span>
              <span class="info-value" style="color:<?= ($student['status'] === 'Graduated') ? '#15803d' : '#0369a1' ?>;">
                <?= e($student['status']) ?> (<?= ($student['status'] === 'Graduated') ? 'Degree Awarded' : 'Undergraduate' ?>)
              </span>
            </div>
            <div class="info-row">
              <span class="info-label">Medium of Instruction</span>
              <span class="info-colon">:</span>
              <span class="info-value">English</span>
            </div>
          </div>
        </div>

        <!-- Courses & Grades Tables -->
        <?php if ($isConsolidated): ?>
          <?php if (empty($groupedSemesters)): ?>
            <p style="text-align:center; padding:2rem; color:#64748b;">No academic results published yet.</p>
          <?php else: ?>
            <?php foreach ($groupedSemesters as $semKey => $cList): ?>
              <?php $st = $semesterRunningStats[$semKey] ?? ['sem_credits' => 0, 'sgpa' => 0, 'cum_credits' => 0, 'cgpa' => 0]; ?>
              <div style="margin-bottom: 1.75rem;">
                <div style="background:#0f2c59; color:#ffffff; padding:0.5rem 1rem; border-radius:4px 4px 0 0; display:flex; justify-content:space-between; align-items:center; font-size:0.86rem; font-weight:700;">
                  <span><i class="fas fa-bookmark" style="color:#f59e0b; margin-right:5px;"></i> <?= e($semKey) ?></span>
                  <span style="font-size:0.8rem; letter-spacing:0.3px;">
                    Semester SGPA: <strong style="color:#ffffff; font-size:0.92rem;"><?= number_format($st['sgpa'], 2) ?></strong>
                    &nbsp;&bull;&nbsp;
                    Cumulative CGPA: <strong style="color:#86efac; font-size:0.95rem;"><?= number_format($st['cgpa'], 2) ?></strong>
                  </span>
                </div>

                <table class="transcript-table" style="margin-bottom: 0; border-top: none;">
                  <thead>
                    <tr>
                      <th style="width: 45px;" class="col-center">SL</th>
                      <th style="width: 105px;">Course Code</th>
                      <th>Course Title</th>
                      <th style="width: 65px;" class="col-center">Credit</th>
                      <th style="width: 75px;" class="col-center">Grade</th>
                      <th style="width: 85px;" class="col-center">Grade Point</th>
                      <th style="width: 95px;" class="col-center">Points Secured</th>
                      <th style="width: 80px;" class="col-center">Remarks</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $sl = 1; ?>
                    <?php foreach ($cList as $res): ?>
                      <?php 
                        $cr = (float)$res['credit'];
                        $gp = (float)$res['grade_point'];
                        $pts = round($cr * $gp, 2);
                        $status = ($gp >= 2.00) ? 'PASSED' : 'FAILED';
                      ?>
                      <tr>
                        <td class="col-center"><?= $sl++ ?></td>
                        <td style="font-family: monospace; font-weight: 700; color:#0f2c59;"><?= e($res['course_code']) ?></td>
                        <td style="font-weight: 600; color:#1e293b;"><?= e($res['course_name']) ?></td>
                        <td class="col-center" style="font-weight: 600;"><?= number_format($cr, 1) ?></td>
                        <td class="col-center">
                          <span class="grade-badge"><?= e($res['letter_grade']) ?></span>
                        </td>
                        <td class="col-center" style="font-weight: 600;"><?= number_format($gp, 2) ?></td>
                        <td class="col-center" style="font-weight: 700; color:#0f2c59;"><?= number_format($pts, 2) ?></td>
                        <td class="col-center">
                          <span style="font-size:0.75rem; font-weight:800; color:<?= ($status === 'PASSED') ? '#15803d' : '#dc2626' ?>;">
                            <?= $status ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                  <tfoot>
                    <tr style="background:#f8fafc; font-weight:700; font-size:0.82rem;">
                      <td colspan="3" style="text-align:right; color:#475569;">Semester Summary:</td>
                      <td class="col-center"><?= number_format((float)$st['sem_credits'], 1) ?></td>
                      <td colspan="2" style="text-align:right; color:#0f2c59;">Semester SGPA: <strong><?= number_format($st['sgpa'], 2) ?></strong></td>
                      <td colspan="2" class="col-center" style="color:#15803d; font-weight:800;">Cumulative CGPA: <?= number_format($st['cgpa'], 2) ?></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php else: ?>
          <!-- Single Semester Table -->
          <table class="transcript-table">
            <thead>
              <tr>
                <th style="width: 45px;" class="col-center">SL</th>
                <th style="width: 105px;">Course Code</th>
                <th>Course Title</th>
                <th style="width: 65px;" class="col-center">Credit</th>
                <th style="width: 75px;" class="col-center">Grade</th>
                <th style="width: 85px;" class="col-center">Grade Point</th>
                <th style="width: 95px;" class="col-center">Points Secured</th>
                <th style="width: 80px;" class="col-center">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($results)): ?>
                <tr>
                  <td colspan="8" class="col-center" style="padding: 2.5rem; color:#64748b;">
                    No course results found for <strong><?= e($requestedSemester) ?></strong>.
                  </td>
                </tr>
              <?php else: ?>
                <?php $sl = 1; ?>
                <?php foreach ($results as $res): ?>
                  <?php 
                    $cr = (float)$res['credit'];
                    $gp = (float)$res['grade_point'];
                    $pts = round($cr * $gp, 2);
                    $status = ($gp >= 2.00) ? 'PASSED' : 'FAILED';
                  ?>
                  <tr>
                    <td class="col-center"><?= $sl++ ?></td>
                    <td style="font-family: monospace; font-weight: 700; color:#0f2c59;"><?= e($res['course_code']) ?></td>
                    <td style="font-weight: 600; color:#1e293b;"><?= e($res['course_name']) ?></td>
                    <td class="col-center" style="font-weight: 600;"><?= number_format($cr, 1) ?></td>
                    <td class="col-center">
                      <span class="grade-badge"><?= e($res['letter_grade']) ?></span>
                    </td>
                    <td class="col-center" style="font-weight: 600;"><?= number_format($gp, 2) ?></td>
                    <td class="col-center" style="font-weight: 700; color:#0f2c59;"><?= number_format($pts, 2) ?></td>
                    <td class="col-center">
                      <span style="font-size:0.75rem; font-weight:800; color:<?= ($status === 'PASSED') ? '#15803d' : '#dc2626' ?>;">
                        <?= $status ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr style="background:#f8fafc; font-weight:700;">
                <td colspan="3" style="text-align:right; color:#475569;">Semester Summary (<?= e($requestedSemester) ?>):</td>
                <td class="col-center"><?= number_format((float)$semCredits, 1) ?></td>
                <td colspan="2" style="text-align:right; color:#0f2c59;">Semester SGPA: <strong><?= number_format($sgpa, 2) ?></strong></td>
                <td colspan="2" class="col-center" style="color:#15803d; font-weight:800; font-size:0.95rem;">CGPA: <?= number_format($calculatedCgpa, 2) ?></td>
              </tr>
            </tfoot>
          </table>
        <?php endif; ?>

        <!-- Semester Performance Metrics + UGC Scale Reference -->
        <div class="summary-section">
          <!-- Performance Metrics Card -->
          <div class="performance-card">
            <div class="stat-item">
              <span class="stat-label">Credits Registered</span>
              <span class="stat-value"><?= number_format($semCredits, 1) ?></span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Credits Earned</span>
              <span class="stat-value"><?= number_format($semCredits, 1) ?></span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Semester GPA (SGPA)</span>
              <span class="stat-value primary"><?= number_format($sgpa, 2) ?></span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Cumulative CGPA</span>
              <span class="stat-value primary"><?= number_format($calculatedCgpa, 2) ?></span>
            </div>
            <div class="stat-item" style="grid-column: span 2; border-top: 1px dashed #bfdbfe; padding-top: 0.5rem; margin-top: 2px;">
              <span class="stat-label">Total Completed Credits (Overall)</span>
              <span style="font-size:0.92rem; font-weight:700; color:#0f2c59;">
                <?= (int)$totalCreditsEarned ?> Credits / <?= (int)$student['total_credits_required'] ?> Required Credits
                <span style="color:#16a34a; margin-left: 8px;">(<?= round(($totalCreditsEarned / max(1, (int)$student['total_credits_required'])) * 100) ?>% Completed)</span>
              </span>
            </div>
          </div>

          <!-- UGC Grading Scale Reference -->
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
              SGPA = ∑(Course Credit × Grade Point) ÷ ∑(Total Credits)
            </div>
          </div>
        </div>

        <!-- Official Signatures & Seal -->
        <div class="signature-area">
          <div class="sig-box">
            <div class="sig-line">
              <span style="font-family:'Brush Script MT', cursive, sans-serif; font-size:1.3rem; color:#0f2c59; transform:rotate(-3deg);">A. H. Tanvir</span>
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
              <span style="font-family:'Brush Script MT', cursive, sans-serif; font-size:1.4rem; color:#0f2c59; transform:rotate(-4deg);">Prof. M. Rahman</span>
            </div>
            <div class="sig-title">Controller of Examinations</div>
            <div class="sig-sub">Rabindra Maitree University</div>
          </div>
        </div>

        <!-- Security Barcode and Notice -->
        <div class="security-footer">
          <div>
            <span>Official Computer-Generated Academic Record • Valid without manual stamp if verified online</span>
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

</body>
</html>

