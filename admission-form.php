<?php
/**
 * Rabindra Maitree University (RMU) Online Admission Form
 * Modeled after verified reference form at https://rmu.ac.bd/rmu-admission/
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$selectedSubject = $_GET['subject'] ?? '';

// Fetch enabled Academic Sessions & Passing Years configured by Admin
$db = getDb();
$currentSessionRow = get_current_academic_session();
$currentSessionName = $currentSessionRow['session_name'] ?? 'Online Intake';
$enabledSessions = get_academic_sessions(true, 'DESC');
$enabledPassingYears = get_passing_years(true, 'DESC');
$applicationRecordYears = get_application_record_years(2015, (int)date('Y') + 2, 'DESC');
$defaultApplicationYear = max(2015, (int)date('Y'));

// Automatically generate & reserve sequential Application ID (RMU-YYYY-0001) & Roll Number (YYYY-0001)
$reservedIdentifiers = get_or_reserve_admission_identifiers($db);
$generatedAppId = $reservedIdentifiers['application_id'];
$generatedRollNumber = $reservedIdentifiers['roll_number'];
$draftToken = $reservedIdentifiers['draft_token'];

$pageTitle = 'Online Admission Form';
$breadcrumbTitle = 'Online Admission Form — ' . $currentSessionName;
$breadcrumbs = [['title' => 'Admission', 'url' => APP_URL . '/admissions.php'], ['title' => 'Application Form']];

// Fetch all active programs grouped by degree level
$departments = $db->query("SELECT id, name_en FROM departments ORDER BY name_en ASC")->fetchAll(PDO::FETCH_ASSOC);
$ugPrograms = $db->query("SELECT p.id, p.department_id, p.title_en, p.title_bn, p.degree_type, p.duration, p.duration_years, p.total_semesters, p.sort_order, d.name_en as dept_name FROM programs p LEFT JOIN departments d ON p.department_id = d.id WHERE p.is_active = 1 AND p.degree_level = 'Undergraduate' ORDER BY p.sort_order ASC")->fetchAll();
$pgPrograms = $db->query("SELECT p.id, p.department_id, p.title_en, p.title_bn, p.degree_type, p.duration, p.duration_years, p.total_semesters, p.sort_order, d.name_en as dept_name FROM programs p LEFT JOIN departments d ON p.department_id = d.id WHERE p.is_active = 1 AND p.degree_level = 'Postgraduate' ORDER BY p.sort_order ASC")->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';

?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 850px;">
    <div class="section-header" style="margin-bottom: 2rem;">
      <span class="section-subtitle">Online Intake System</span>
      <h2 class="section-title">RMU Admission Form Online</h2>
      <div class="section-line"></div>
      <p style="color: var(--rmu-muted); margin-top: 0.8rem; font-size: 0.95rem;">
        Please complete all mandatory fields below accurately. Upon submission, an application reference number will be generated immediately for your tracking.
      </p>
    </div>

    <!-- Development Prototype Notice -->
    <div class="alert alert-info" style="margin-bottom: 2rem;">
      <i class="fas fa-info-circle" style="font-size: 1.3rem;"></i>
      <div>
        <strong>Educational Prototype Application:</strong> This intake form functions as an authorized development system. Applications submitted are stored locally in the development database for testing and verification.
      </div>
    </div>

    <div class="form-container">
      <form action="<?= APP_URL ?>/actions/submit-admission.php" method="POST" enctype="multipart/form-data" id="rmuAdmissionForm" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="draft_token" id="draftTokenInput" value="<?= e($draftToken) ?>">

        <!-- Applicant Photograph (Passport Size) -->
        <div class="form-group" style="background:#f8fafc; border:1.5px dashed #cbd5e1; border-radius:10px; padding:1.2rem; margin-bottom:1.5rem;">
          <label class="form-label" style="font-weight:700; color:#1e293b; margin-bottom:0.5rem; display:flex; align-items:center; gap:0.5rem;">
            <i class="fas fa-camera" style="color:var(--rmu-primary);"></i>
            Applicant Photograph / প্রার্থীর ছবি <span style="font-size:0.8rem; font-weight:normal; color:#64748b;">(For Application Card &amp; Student ID)</span>
          </label>
          <div style="display:flex; gap:1.25rem; align-items:center; flex-wrap:wrap;">
            <!-- Live Preview -->
            <div style="width:105px; height:125px; border:2px solid #94a3b8; border-radius:6px; background:#ffffff; display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; box-shadow:0 2px 4px rgba(0,0,0,0.06);">
              <img id="applicantPhotoPreview" src="<?= APP_URL ?>/assets/images/default-student.svg" alt="Preview" style="width:100%; height:100%; object-fit:cover;">
            </div>
            <div style="flex:1; min-width:min(100%, 220px);">
              <input type="file" name="applicant_photo" id="applicantPhotoInput" class="form-control" accept="image/jpeg,image/png,image/webp" style="padding:0.5rem 0.85rem; background:#ffffff;">
              <small style="color:#64748b; font-size:0.78rem; display:block; margin-top:0.4rem;">
                Upload a recent passport-size photograph (JPG, PNG, WEBP — Max 2MB). This picture will appear on your official <strong>Application Card</strong> and Student ID.
              </small>
            </div>
          </div>
        </div>

        <!-- Full Name, Application ID & Roll Number -->
        <div class="form-group">
          <label class="form-label">Full Name / শিক্ষার্থীর পূর্ণ নাম <span class="required">*</span></label>
          <input type="text" name="full_name" class="form-control" placeholder="Enter applicant's full legal name" required minlength="3">
          <div class="form-error-msg">Please enter applicant's full name.</div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 210px), 1fr)); gap: 1.25rem;">
          <div class="form-group">
            <label class="form-label">Application ID <span style="font-size:0.74rem; font-weight:700; color:#0284c7; background:#e0f2fe; padding:1px 6px; border-radius:4px; margin-left:4px;"><i class="fas fa-lock"></i> Auto-Generated</span></label>
            <input type="text" name="application_id" id="applicationIdInput" class="form-control" value="<?= e($generatedAppId) ?>" readonly aria-readonly="true" style="background:#f1f5f9; color:#0f2c59; font-weight:800; font-family:monospace; letter-spacing:0.4px; cursor:not-allowed; border-color:#cbd5e1;">
            <small style="color: var(--rmu-muted); font-size: 0.76rem;"><i class="fas fa-shield-alt" style="color:#0284c7;"></i> Unique sequential ID assigned automatically (read-only).</small>
          </div>
          <div class="form-group">
            <label class="form-label">Admission / Examination Roll Number <span style="font-size:0.74rem; font-weight:700; color:#6d28d9; background:#ede9fe; padding:1px 6px; border-radius:4px; margin-left:4px;"><i class="fas fa-lock"></i> Auto-Generated</span></label>
            <input type="text" name="roll_number" id="applicantRollInput" class="form-control" value="<?= e($generatedRollNumber) ?>" readonly aria-readonly="true" style="background:#f1f5f9; color:#4c1d95; font-weight:800; font-family:monospace; letter-spacing:0.4px; cursor:not-allowed; border-color:#cbd5e1;">
            <small style="color: var(--rmu-muted); font-size: 0.76rem;"><i class="fas fa-shield-alt" style="color:#6d28d9;"></i> System-generated examination roll number (read-only).</small>
          </div>
          <div class="form-group">
            <label class="form-label">Application Record Year (2015+) <span class="required">*</span></label>
            <select name="application_year" id="applicationYearSelect" class="form-select" required>
              <?php foreach ($applicationRecordYears as $appYr): ?>
                <option value="<?= (int)$appYr ?>" <?= ((int)$appYr === $defaultApplicationYear) ? 'selected' : '' ?>><?= (int)$appYr ?></option>
              <?php endforeach; ?>
            </select>
            <small style="color: var(--rmu-muted); font-size: 0.76rem;">Intake records begin 2015+ (independent of session &amp; passing year).</small>
          </div>
        </div>

        <!-- Father's Name & Mother's Name in 2 columns -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1.25rem;">
          <div class="form-group">
            <label class="form-label">Father's Name / পিতার নাম <span class="required">*</span></label>
            <input type="text" name="father_name" class="form-control" placeholder="Enter father's legal name" required minlength="3">
            <div class="form-error-msg">Please enter father's name.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Mother's Name / মাতার নাম <span class="required">*</span></label>
            <input type="text" name="mother_name" class="form-control" placeholder="Enter mother's legal name" required minlength="3">
            <div class="form-error-msg">Please enter mother's name.</div>
          </div>
        </div>

        <!-- Date of Birth & Gender in 2 columns -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1.25rem;">
          <div class="form-group">
            <label class="form-label">Date of Birth / জন্ম তারিখ <span class="required">*</span></label>
            <input type="date" name="date_of_birth" class="form-control" required max="<?= date('Y-m-d', strtotime('-15 years')) ?>">
            <div class="form-error-msg">Please provide a valid date of birth.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Gender / লিঙ্গ <span class="required">*</span></label>
            <select name="gender" class="form-control" required style="padding: 0.65rem 0.85rem;">
              <option value="Male" selected>Male / পুরুষ</option>
              <option value="Female">Female / নারী</option>
              <option value="Other">Other / অন্যান্য</option>
            </select>
          </div>
        </div>

        <!-- Mobile & Email in 2 columns -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1.25rem;">
          <div class="form-group">
            <label class="form-label">Mobile Number <span class="required">*</span></label>
            <input type="tel" name="mobile" class="form-control" placeholder="01XXXXXXXXX (11 digits)" required maxlength="15">
            <small style="color: var(--rmu-muted); font-size: 0.78rem;">Must be an active Bangladeshi mobile number.</small>
            <div class="form-error-msg">Enter a valid 11-digit Bangladeshi mobile number.</div>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address (Optional)</label>
            <input type="email" name="email" class="form-control" placeholder="example@email.com">
          </div>
        </div>

        <!-- Present Address & Permanent Address in 2 columns -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1.25rem;">
          <div class="form-group">
            <label class="form-label">Present Address / বর্তমান ঠিকানা <span class="required">*</span></label>
            <textarea name="present_address" class="form-control" rows="2" placeholder="House/Village, Road, Post Office, Thana, District" required style="resize:vertical;"></textarea>
            <div class="form-error-msg">Present address is required.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Permanent Address / স্থায়ী ঠিকানা <span class="required">*</span></label>
            <textarea name="permanent_address" class="form-control" rows="2" placeholder="Village, Post Office, Thana, District" required style="resize:vertical;"></textarea>
            <div class="form-error-msg">Permanent address is required.</div>
          </div>
        </div>

        <!-- Last College Name & Home District in 2 columns -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Last College Name <span class="required">*</span></label>
            <input type="text" name="last_college" class="form-control" placeholder="Name of previous college/institution" required>
            <div class="form-error-msg">Previous college is required.</div>
          </div>

          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Home District <span class="required">*</span></label>
            <input type="text" name="home_district" class="form-control" placeholder="e.g. Kushtia, Meherpur, Dhaka" required>
            <div class="form-error-msg">Home district is required.</div>
          </div>
        </div>

        <!-- ================================================================ -->
        <!-- EDUCATION INFORMATION FORM (SSC, HSC)                             -->
        <!-- ================================================================ -->
        <div style="margin-top: 1.75rem; margin-bottom: 1.75rem;">

          <!-- 1. SSC / Equivalent Level -->
          <div class="edu-form-container">
            <div class="edu-section-header">SSC/Equivalent Level</div>
            <div class="edu-grid-2col">
              <!-- Left Column -->
              <div class="edu-col">
                <div class="edu-field-row">
                  <label for="ssc_exam">Examination</label>
                  <select id="ssc_exam" name="ssc_exam" class="edu-control" required>
                    <option value="S.S.C" selected>S.S.C</option>
                    <option value="Dakhil">Dakhil</option>
                    <option value="Equivalent">Equivalent</option>
                  </select>
                </div>
                <div class="edu-field-row">
                  <label for="ssc_roll">Roll No</label>
                  <input type="text" id="ssc_roll" name="ssc_roll" class="edu-control required-empty" placeholder="" required>
                </div>
                <div class="edu-field-row">
                  <label for="ssc_reg_no">Registration No</label>
                  <input type="text" id="ssc_reg_no" name="ssc_reg_no" class="edu-control" placeholder="Optional">
                </div>
                <div class="edu-field-row">
                  <label for="ssc_group">Group/Subject</label>
                  <select id="ssc_group" name="ssc_group" class="edu-control" required>
                    <option value="Science" selected>Science</option>
                    <option value="Humanities">Humanities</option>
                    <option value="Business Studies">Business Studies</option>
                  </select>
                </div>
              </div>

              <!-- Right Column -->
              <div class="edu-col">
                <div class="edu-field-row">
                  <label for="ssc_board">Board</label>
                  <select id="ssc_board" name="ssc_board" class="edu-control" required>
                    <option value="Dinajpur" selected>Dinajpur</option>
                    <option value="Dhaka">Dhaka</option>
                    <option value="Rajshahi">Rajshahi</option>
                    <option value="Cumilla">Cumilla</option>
                    <option value="Chattogram">Chattogram</option>
                    <option value="Jashore">Jashore</option>
                    <option value="Barishal">Barishal</option>
                    <option value="Sylhet">Sylhet</option>
                    <option value="Mymensingh">Mymensingh</option>
                    <option value="Madrasah">Madrasah</option>
                    <option value="Technical">Technical</option>
                  </select>
                </div>
                <div class="edu-field-row">
                  <label for="ssc_gpa">Result</label>
                  <div class="edu-result-group">
                    <select id="ssc_result_type" name="ssc_result_type" class="edu-control" style="flex: 1.5;">
                      <option value="GPA(out of 5)" selected>GPA(out of 5)</option>
                      <option value="Passed">Passed (Without GPA)</option>
                      <option value="Division">Division</option>
                    </select>
                    <input type="number" step="0.01" min="1.00" max="5.00" id="ssc_gpa" name="ssc_gpa" class="edu-control required-empty" placeholder="" required style="flex: 1;">
                  </div>
                </div>
                <div class="edu-field-row">
                  <label for="ssc_year">Passing Year</label>
                  <select id="ssc_year" name="ssc_year" class="edu-control" required>
                    <?php for ($y = 2026; $y >= 1995; $y--): ?>
                      <option value="<?= $y ?>" <?= ($y == 2011) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- 2. HSC / Equivalent Level / Diploma-in-Engineering -->
          <div class="edu-form-container">
            <div class="edu-section-header">HSC/Equivalent Level/Diploma-in-Engineering</div>
            <div class="edu-grid-2col">
              <!-- Left Column -->
              <div class="edu-col">
                <div class="edu-field-row">
                  <label for="hsc_exam">Examination</label>
                  <select id="hsc_exam" name="hsc_exam" class="edu-control" required>
                    <option value="H.S.C" selected>H.S.C</option>
                    <option value="Alim">Alim</option>
                    <option value="Diploma-in-Engineering">Diploma-in-Engineering</option>
                    <option value="Equivalent">Equivalent</option>
                  </select>
                </div>
                <div class="edu-field-row">
                  <label for="hsc_roll">Roll No</label>
                  <input type="text" id="hsc_roll" name="hsc_roll" class="edu-control required-empty" placeholder="" required>
                </div>
                <div class="edu-field-row">
                  <label for="hsc_reg_no">Registration No</label>
                  <input type="text" id="hsc_reg_no" name="hsc_reg_no" class="edu-control" placeholder="Optional">
                </div>
                <div class="edu-field-row">
                  <label for="hsc_group">Group/Subject</label>
                  <select id="hsc_group" name="hsc_group" class="edu-control" required>
                    <option value="Science" selected>Science</option>
                    <option value="Humanities">Humanities</option>
                    <option value="Business Studies">Business Studies</option>
                    <option value="Diploma">Diploma</option>
                  </select>
                </div>
              </div>

              <!-- Right Column -->
              <div class="edu-col">
                <div class="edu-field-row">
                  <label for="hsc_board">Board</label>
                  <select id="hsc_board" name="hsc_board" class="edu-control" required>
                    <option value="Dinajpur" selected>Dinajpur</option>
                    <option value="Dhaka">Dhaka</option>
                    <option value="Rajshahi">Rajshahi</option>
                    <option value="Cumilla">Cumilla</option>
                    <option value="Chattogram">Chattogram</option>
                    <option value="Jashore">Jashore</option>
                    <option value="Barishal">Barishal</option>
                    <option value="Sylhet">Sylhet</option>
                    <option value="Mymensingh">Mymensingh</option>
                    <option value="Madrasah">Madrasah</option>
                    <option value="Technical">Technical</option>
                  </select>
                </div>
                <div class="edu-field-row">
                  <label for="hsc_gpa">Result</label>
                  <div class="edu-result-group">
                    <select id="hsc_result_type" name="hsc_result_type" class="edu-control" style="flex: 1.5;">
                      <option value="GPA(out of 5)" selected>GPA(out of 5)</option>
                      <option value="Passed">Passed (Without GPA)</option>
                      <option value="Division">Division</option>
                    </select>
                    <input type="number" step="0.01" min="1.00" max="5.00" id="hsc_gpa" name="hsc_gpa" class="edu-control required-empty" placeholder="" required style="flex: 1;">
                  </div>
                </div>
                <div class="edu-field-row">
                  <label for="hsc_year">Passing Year</label>
                  <select id="hsc_year" name="hsc_year" class="edu-control" required>
                    <?php for ($y = 2026; $y >= 1995; $y--): ?>
                      <option value="<?= $y ?>" <?= ($y == 2014) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Academic Degree Type, Department, Program, Passing Year & Session Selection -->
        <div style="background:#f0f7ff; border:1.5px solid #bfdbfe; border-radius:10px; padding:1.25rem; margin-bottom:1.5rem;">
          <h4 style="margin:0 0 1rem; font-size:1rem; font-weight:700; color:#0f2c59; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-graduation-cap" style="color:var(--rmu-primary);"></i> Academic Choice &amp; Degree Structure / পছন্দের বিভাগ, ডিগ্রি ও প্রোগ্রাম
          </h4>

          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap:1rem; margin-bottom:1rem;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Degree / Course Type <span class="required">*</span></label>
              <select name="degree_type" id="degreeTypeSelect" class="form-select" required onchange="filterProgramsBySelection()">
                <option value="Bachelor / Honours">Bachelor / Honours (4 Years &bull; 8 Semesters)</option>
                <option value="B.Ed">B.Ed (1 Year &bull; 2–3 Semesters)</option>
                <option value="Master's">Master's (1–2 Years &bull; 2–4 Semesters)</option>
              </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Academic Department / বিভাগ</label>
              <select name="department_id" id="departmentSelect" class="form-select" onchange="filterProgramsBySelection()">
                <option value="">-- All Departments / সকল বিভাগ --</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= (int)$d['id'] ?>"><?= e($d['name_en']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap:1rem; margin-bottom:1rem;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Academic Session / শিক্ষাবর্ষ <span class="required">*</span></label>
              <select name="academic_session" id="academicSessionSelect" class="form-select" required>
                <?php if (empty($enabledSessions)): ?>
                  <option value="" disabled selected>-- No Enabled Academic Sessions Available --</option>
                <?php else: ?>
                  <?php
                    $hasMatchedCurrent = false;
                    foreach ($enabledSessions as $esCheck) {
                        if ((int)$esCheck['is_current'] === 1) {
                            $hasMatchedCurrent = true;
                            break;
                        }
                    }
                  ?>
                  <?php foreach ($enabledSessions as $idx => $sRow): ?>
                    <?php $isDefSess = $hasMatchedCurrent ? ((int)$sRow['is_current'] === 1) : ($idx === 0); ?>
                    <option value="<?= e($sRow['session_name']) ?>" data-session-year="<?= (int)$sRow['session_year'] ?>" <?= $isDefSess ? 'selected' : '' ?>>
                      <?= e($sRow['session_name']) ?><?= (int)$sRow['is_current'] === 1 ? ' (Current Intake)' : '' ?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
              <small style="color:#64748b; font-size:0.76rem; display:block; margin-top:4px;">Only administrator-enabled sessions (2011 onward) are shown.</small>
            </div>

            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Actual Passing Year (Student Selection) <span class="required">*</span></label>
              <select name="passing_year" id="passingYearSelect" class="form-select" required>
                <?php if (empty($enabledPassingYears)): ?>
                  <option value="" disabled selected>-- No Enabled Passing Years Available --</option>
                <?php else: ?>
                  <?php
                    $has2025 = false;
                    foreach ($enabledPassingYears as $pyCheck) {
                        if ((int)$pyCheck['year_value'] === 2025) {
                            $has2025 = true;
                            break;
                        }
                    }
                  ?>
                  <?php foreach ($enabledPassingYears as $idx => $pyRow): ?>
                    <?php $yrVal = (int)$pyRow['year_value']; $isDefYr = $has2025 ? ($yrVal === 2025) : ($idx === 0); ?>
                    <option value="<?= $yrVal ?>" <?= $isDefYr ? 'selected' : '' ?>><?= e($pyRow['label']) ?></option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
              <small style="color:#64748b; font-size:0.76rem; display:block; margin-top:4px;">Select your exact Actual Passing Year (2011+). Never overwritten automatically.</small>
            </div>
          </div>

          <!-- Interested subject to admit in RMU -->
          <div class="form-group" style="margin-bottom:1rem;">
            <label class="form-label">Degree / Selected Academic Program <span class="required">*</span></label>
            <select name="interested_subject" id="programSelect" class="form-select" required>
              <option value="">-- Select Your Preferred Program --</option>
              <optgroup label="Undergraduate Programs (Bachelor / Honours &amp; B.Ed)">
                <?php foreach ($ugPrograms as $i => $prog): ?>
                  <?php 
                    $isSelected = ($selectedSubject === $prog['title_en'] || (!empty($selectedSubject) && stripos($prog['title_en'], $selectedSubject) !== false));
                    $pDegType = $prog['degree_type'] ?? (($prog['title_en'] === 'Bachelor of Education (B.Ed)') ? 'B.Ed' : 'Bachelor / Honours');
                    $pDurYrs = isset($prog['duration_years']) && (float)$prog['duration_years'] > 0 ? (float)$prog['duration_years'] : ($pDegType === 'B.Ed' ? 1.0 : 4.0);
                  ?>
                  <option value="<?= e($prog['title_en']) ?>" data-dept="<?= (int)$prog['department_id'] ?>" data-prog-id="<?= (int)$prog['id'] ?>" data-degree-type="<?= e($pDegType) ?>" data-duration="<?= e($prog['duration'] ?? '4 Years (8 Semesters)') ?>" data-duration-years="<?= number_format($pDurYrs, 1, '.', '') ?>" data-semesters="<?= (int)($prog['total_semesters'] ?? 8) ?>" <?= $isSelected ? 'selected' : '' ?>>
                    <?= ($i + 1) ?>. <?= e($prog['title_en']) ?> (<?= e($prog['dept_name'] ?? 'Faculty Program') ?> &mdash; <?= (int)($prog['total_semesters'] ?? 8) ?> Semesters)
                  </option>
                <?php endforeach; ?>
              </optgroup>
              <optgroup label="Postgraduate Programs (Master's Degrees)">
                <?php foreach ($pgPrograms as $j => $prog): ?>
                  <?php 
                    $isSelected = ($selectedSubject === $prog['title_en'] || (!empty($selectedSubject) && stripos($prog['title_en'], $selectedSubject) !== false));
                    $pDegType = $prog['degree_type'] ?? "Master's";
                    $pDurYrs = isset($prog['duration_years']) && (float)$prog['duration_years'] > 0 ? (float)$prog['duration_years'] : 2.0;
                  ?>
                  <option value="<?= e($prog['title_en']) ?>" data-dept="<?= (int)$prog['department_id'] ?>" data-prog-id="<?= (int)$prog['id'] ?>" data-degree-type="<?= e($pDegType) ?>" data-duration="<?= e($prog['duration'] ?? '2 Years (4 Semesters)') ?>" data-duration-years="<?= number_format($pDurYrs, 1, '.', '') ?>" data-semesters="<?= (int)($prog['total_semesters'] ?? 4) ?>" <?= $isSelected ? 'selected' : '' ?>>
                    <?= ($j + 1) ?>. <?= e($prog['title_en']) ?> (<?= e($prog['dept_name'] ?? 'Faculty Program') ?> &mdash; <?= (int)($prog['total_semesters'] ?? 4) ?> Semesters)
                  </option>
                <?php endforeach; ?>
              </optgroup>
            </select>
            <input type="hidden" name="program_id" id="selectedProgramId" value="">
            <div class="form-error-msg">Please select your intended academic program.</div>
          </div>

          <!-- Integrated Read-Only Academic Structure & Expected Completion Fields -->
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap:0.9rem;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" for="courseDurationDisplay" style="font-size:0.84rem; color:#0f2c59;">Course Duration</label>
              <input type="text" id="courseDurationDisplay" class="form-control" readonly placeholder="Select a program" style="background:#ffffff; font-weight:700; color:#0f172a; border-color:#93c5fd;">
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" for="totalSemestersDisplay" style="font-size:0.84rem; color:#0f2c59;">Total Semesters</label>
              <input type="text" id="totalSemestersDisplay" class="form-control" readonly placeholder="Select a program" style="background:#ffffff; font-weight:700; color:#0f172a; border-color:#93c5fd;">
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" for="expectedPassingYearDisplay" style="font-size:0.84rem; color:#0f2c59;">Expected Passing Year</label>
              <input type="text" id="expectedPassingYearDisplay" class="form-control" readonly placeholder="Session + Duration" style="background:#ffffff; font-family:monospace; font-weight:800; color:#1e40af; border-color:#93c5fd;">
            </div>
          </div>

          <div id="programStructureHint" style="font-size:0.8rem; color:#1e40af; margin-top:8px; font-weight:600;"></div>
          <div id="expectedPassingPreview" style="margin-top:8px; padding:10px 12px; background:#ffffff; border:1px solid #bfdbfe; border-radius:8px; font-size:0.8rem; color:#1e3a8a; display:none;"></div>
          <div id="passingMismatchNotice" style="margin-top:8px; padding:10px 12px; background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; font-size:0.8rem; color:#92400e; display:none;"></div>
        </div>

        <!-- Quota Declaration -->
        <div class="form-group">
          <label class="form-label">Have You Any Quota? (Freedom Fighter / Tribal / Sports / Physically Challenged) <span class="required">*</span></label>
          <div class="radio-group">
            <label class="radio-option">
              <input type="radio" name="quota" value="NO" checked>
              <span>NO</span>
            </label>
            <label class="radio-option">
              <input type="radio" name="quota" value="YES">
              <span>YES</span>
            </label>
          </div>
        </div>

        <!-- Submit Button -->
        <div style="margin-top: 2rem; border-top: 1px solid var(--rmu-border); padding-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
          <a href="<?= APP_URL ?>/admission-status.php" style="font-size: 0.9rem; font-weight: 600; color: var(--rmu-muted);">
            <i class="fas fa-search-location"></i> Already applied? Check status
          </a>
          <button type="submit" class="btn-primary" style="background: var(--rmu-accent); padding: 0.85rem 2.2rem; font-size: 1.05rem;">
            <i class="fas fa-paper-plane"></i> Submit Application
          </button>
        </div>
      </form>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('rmuAdmissionForm');

  function validateControl(el) {
    if (!el || el.disabled) return true;
    const id = el.id;
    const val = el.value.trim();

    if (id === 'ssc_roll' || id === 'hsc_roll') {
      if (val.length >= 1) {
        el.classList.remove('is-invalid', 'required-empty');
        el.classList.add('is-valid');
        return true;
      } else {
        el.classList.remove('is-valid');
        el.classList.add('is-invalid', 'required-empty');
        return false;
      }
    }

    if (id === 'ssc_gpa' || id === 'hsc_gpa') {
      const gpa = parseFloat(val);
      if (val.length > 0 && !isNaN(gpa) && gpa >= 1.00 && gpa <= 5.00) {
        el.classList.remove('is-invalid', 'required-empty');
        el.classList.add('is-valid');
        return true;
      } else {
        el.classList.remove('is-valid');
        el.classList.add('is-invalid', 'required-empty');
        return false;
      }
    }

    return true;
  }

  // Attach live event listeners to all education controls
  const allEduControls = document.querySelectorAll('.edu-control');
  allEduControls.forEach(function(ctrl) {
    ctrl.addEventListener('input', function() {
      validateControl(this);
    });
    ctrl.addEventListener('change', function() {
      validateControl(this);
    });
    ctrl.addEventListener('blur', function() {
      validateControl(this);
    });
  });

  // Handle Photo Preview
  const photoInput = document.getElementById('applicantPhotoInput');
  const photoPreview = document.getElementById('applicantPhotoPreview');
  if (photoInput && photoPreview) {
    photoInput.addEventListener('change', function() {
      if (this.files && this.files[0]) {
        const file = this.files[0];
        if (file.size > 2 * 1024 * 1024) {
          alert('Photo size exceeds 2MB limit. Please choose a photo under 2MB.');
          this.value = '';
          photoPreview.src = '<?= APP_URL ?>/assets/images/default-student.svg';
          return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
          photoPreview.src = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Program selection, Degree Type, Department & Advisory Expected Passing Year
  const progSelect = document.getElementById('programSelect');
  const progIdInput = document.getElementById('selectedProgramId');
  const deptSelect = document.getElementById('departmentSelect');
  const degSelect = document.getElementById('degreeTypeSelect');
  const sessionSelect = document.getElementById('academicSessionSelect');
  const passingYearSelect = document.getElementById('passingYearSelect');
  const durDisplay = document.getElementById('courseDurationDisplay');
  const semDisplay = document.getElementById('totalSemestersDisplay');
  const expDisplay = document.getElementById('expectedPassingYearDisplay');
  const structHint = document.getElementById('programStructureHint');
  const expPreview = document.getElementById('expectedPassingPreview');
  const mismatchNotice = document.getElementById('passingMismatchNotice');

  function updateExpectedPassingPreview() {
    if (!sessionSelect) return;
    const sessOpt = sessionSelect.options[sessionSelect.selectedIndex];
    const sessVal = sessOpt ? sessOpt.value : '';
    const yrMatch = sessVal.match(/\b(19\d{2}|20\d{2})\b/);
    const sessYr = yrMatch ? parseInt(yrMatch[1], 10) : null;

    let durYrs = null;
    let totalSems = null;
    if (progSelect) {
      const pOpt = progSelect.options[progSelect.selectedIndex];
      if (pOpt && pOpt.value && pOpt.dataset.durationYears) {
        durYrs = parseFloat(pOpt.dataset.durationYears);
        totalSems = pOpt.dataset.semesters ? parseInt(pOpt.dataset.semesters, 10) : null;
      }
    }
    if (!durYrs && degSelect) {
      const dv = degSelect.value;
      durYrs = (dv === 'B.Ed') ? 1 : (dv === "Master's" ? 2 : 4);
      totalSems = (dv === 'B.Ed') ? 2 : (dv === "Master's" ? 4 : 8);
    }

    if (durDisplay && durYrs) {
      const durText = (Math.abs(durYrs - Math.round(durYrs)) < 0.05) ? Math.round(durYrs) : durYrs.toFixed(1);
      durDisplay.value = durText + (parseFloat(durText) === 1 ? ' Year' : ' Years');
    } else if (durDisplay) {
      durDisplay.value = '';
    }
    if (semDisplay && totalSems) {
      semDisplay.value = totalSems + (totalSems === 1 ? ' Semester' : ' Semesters');
    } else if (semDisplay) {
      semDisplay.value = '';
    }

    if (sessYr && durYrs) {
      const expYr = sessYr + Math.round(durYrs);
      const durText = (Math.abs(durYrs - Math.round(durYrs)) < 0.05) ? Math.round(durYrs) : durYrs.toFixed(1);
      if (expDisplay) {
        expDisplay.value = String(expYr);
      }
      if (expPreview) {
        expPreview.style.display = 'block';
        expPreview.innerHTML =
          '<div><i class="fas fa-calendar-check" style="color:#0284c7;"></i> ' +
          '<strong>Expected Passing Year (Advisory):</strong> ' +
          '<span style="font-family:monospace; font-weight:800; color:#0f172a;">' + expYr + '</span> ' +
          '<span style="color:#475569;">(' + sessYr + ' + ' + durText + ' = ' + expYr + ')</span></div>' +
          '<div style="font-size:0.74rem; color:#64748b; margin-top:2px;">Reference calculation only — never automatically changes your selected Actual Passing Year.</div>';
      }
      if (mismatchNotice && passingYearSelect) {
        const selPassing = parseInt(passingYearSelect.value, 10);
        if (selPassing && selPassing !== expYr) {
          const diff = selPassing - expYr;
          mismatchNotice.style.display = 'block';
          mismatchNotice.innerHTML =
            '<i class="fas fa-exclamation-triangle" style="color:#d97706;"></i> ' +
            '<strong>Advisory Notice:</strong> Your selected Actual Passing Year (<strong>' + selPassing + '</strong>) differs from the Expected Passing Year (<strong>' + expYr + '</strong>, variance ' + (diff > 0 ? '+' : '') + diff + ' yr). Your application can still be submitted normally and will be flagged for Admin verification without changing your selection.';
        } else {
          mismatchNotice.style.display = 'none';
        }
      }
    } else {
      if (expDisplay) expDisplay.value = '';
      if (expPreview) expPreview.style.display = 'none';
      if (mismatchNotice) mismatchNotice.style.display = 'none';
    }
  }

  function updateProgramHintAndInputs() {
    if (!progSelect) return;
    const opt = progSelect.options[progSelect.selectedIndex];
    if (opt && opt.value && opt.dataset.progId) {
      if (progIdInput) progIdInput.value = opt.dataset.progId;
      if (deptSelect && opt.dataset.dept && !deptSelect.value) {
        deptSelect.value = opt.dataset.dept;
      }
      if (degSelect && opt.dataset.degreeType) {
        degSelect.value = opt.dataset.degreeType;
      }
      if (structHint) {
        structHint.innerHTML = '<i class="fas fa-layer-group"></i> Configured Structure: <strong>' + (opt.dataset.degreeType || '') + '</strong> &bull; ' + (opt.dataset.duration || '') + ' (' + (opt.dataset.semesters || '') + ' Semesters)';
      }
    } else if (structHint) {
      structHint.textContent = '';
    }
    updateExpectedPassingPreview();
  }

  if (sessionSelect) {
    sessionSelect.addEventListener('change', updateExpectedPassingPreview);
  }
  if (passingYearSelect) {
    passingYearSelect.addEventListener('change', updateExpectedPassingPreview);
  }
  if (degSelect) {
    degSelect.addEventListener('change', updateExpectedPassingPreview);
  }
  if (progSelect) {
    progSelect.addEventListener('change', updateProgramHintAndInputs);
    updateProgramHintAndInputs();
  } else {
    updateExpectedPassingPreview();
  }

  window.filterProgramsBySelection = function() {
    if (!progSelect) return;
    const deptId = deptSelect ? deptSelect.value : '';
    const degType = degSelect ? degSelect.value : '';
    const opts = progSelect.querySelectorAll('option');
    let firstVisible = null;
    let currentHidden = false;
    opts.forEach(function(o) {
      if (!o.value) return;
      const matchDept = !deptId || o.dataset.dept === deptId;
      const matchDeg = !degType || o.dataset.degreeType === degType;
      if (matchDept && matchDeg) {
        o.style.display = '';
        o.disabled = false;
        if (!firstVisible) firstVisible = o;
      } else {
        o.style.display = 'none';
        o.disabled = true;
        if (o.selected) currentHidden = true;
      }
    });
    if (currentHidden) {
      progSelect.value = '';
      if (progIdInput) progIdInput.value = '';
      if (structHint) structHint.textContent = '';
    }
    if (!progSelect.value && firstVisible && (degType === 'B.Ed' || deptId)) {
      firstVisible.selected = true;
      updateProgramHintAndInputs();
    } else {
      updateExpectedPassingPreview();
    }
  };
  window.filterProgramsByDept = window.filterProgramsBySelection;

  // Form submit interception & client validation
  if (form) {
    form.addEventListener('submit', function(e) {
      let isAllValid = true;
      let firstInvalidEl = null;

      // Validate core inputs
      const coreInputs = form.querySelectorAll('input[required], select[required]');
      coreInputs.forEach(function(input) {
        if (input.disabled) return;
        if (!input.checkValidity() || input.value.trim() === '') {
          isAllValid = false;
          if (!firstInvalidEl) firstInvalidEl = input;
          input.classList.add('is-invalid');
        }
      });

      // Validate education controls
      allEduControls.forEach(function(ctrl) {
        if (!ctrl.disabled) {
          const ok = validateControl(ctrl);
          if (!ok) {
            isAllValid = false;
            if (!firstInvalidEl) firstInvalidEl = ctrl;
          }
        }
      });

      if (!isAllValid) {
        e.preventDefault();
        if (firstInvalidEl) {
          firstInvalidEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
          firstInvalidEl.focus();
        }
      }
    });
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

