<?php
/**
 * Rabindra Maitree University (RMU) Online Admission Form
 * Modeled after verified reference form at https://rmu.ac.bd/rmu-admission/
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Online Admission Form';
$breadcrumbTitle = 'Online Admission Form — Fall 2026';
$breadcrumbs = [['title' => 'Admission', 'url' => APP_URL . '/admissions.php'], ['title' => 'Application Form']];

$selectedSubject = $_GET['subject'] ?? '';

// Fetch all active programs grouped by degree level
$db = getDb();
$departments = $db->query("SELECT id, name_en FROM departments ORDER BY name_en ASC")->fetchAll(PDO::FETCH_ASSOC);
$ugPrograms = $db->query("SELECT p.id, p.department_id, p.title_en, p.title_bn, p.sort_order, d.name_en as dept_name FROM programs p LEFT JOIN departments d ON p.department_id = d.id WHERE p.is_active = 1 AND p.degree_level = 'Undergraduate' ORDER BY p.sort_order ASC")->fetchAll();
$pgPrograms = $db->query("SELECT p.id, p.department_id, p.title_en, p.title_bn, p.sort_order, d.name_en as dept_name FROM programs p LEFT JOIN departments d ON p.department_id = d.id WHERE p.is_active = 1 AND p.degree_level = 'Postgraduate' ORDER BY p.sort_order ASC")->fetchAll();

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
            <div style="flex:1; min-width:240px;">
              <input type="file" name="applicant_photo" id="applicantPhotoInput" class="form-control" accept="image/jpeg,image/png,image/webp" style="padding:0.5rem 0.85rem; background:#ffffff;">
              <small style="color:#64748b; font-size:0.78rem; display:block; margin-top:0.4rem;">
                Upload a recent passport-size photograph (JPG, PNG, WEBP — Max 2MB). This picture will appear on your official <strong>Application Card</strong> and Student ID.
              </small>
            </div>
          </div>
        </div>

        <!-- Full Name -->
        <div class="form-group">
          <label class="form-label">Full Name / শিক্ষার্থীর পূর্ণ নাম <span class="required">*</span></label>
          <input type="text" name="full_name" class="form-control" placeholder="Enter applicant's full legal name" required minlength="3">
          <div class="form-error-msg">Please enter applicant's full name.</div>
        </div>

        <!-- Father's Name & Mother's Name in 2 columns -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
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
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
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
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
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
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
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
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
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

        <!-- Academic Department, Program & Session Selection -->
        <div style="background:#f0f7ff; border:1.5px solid #bfdbfe; border-radius:10px; padding:1.25rem; margin-bottom:1.5rem;">
          <h4 style="margin:0 0 1rem; font-size:1rem; font-weight:700; color:#0f2c59; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-graduation-cap" style="color:var(--rmu-primary);"></i> Academic Choice &amp; Session / পছন্দের বিভাগ ও প্রোগ্রাম
          </h4>

          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:1rem; margin-bottom:1rem;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Academic Department / বিভাগ</label>
              <select name="department_id" id="departmentSelect" class="form-select" onchange="filterProgramsByDept(this.value)">
                <option value="">-- All Departments / সকল বিভাগ --</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= (int)$d['id'] ?>"><?= e($d['name_en']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Academic Session / শিক্ষাবর্ষ <span class="required">*</span></label>
              <select name="academic_session" class="form-select" required>
                <option value="Fall 2026" selected>Fall 2026 (Active Intake)</option>
                <option value="Spring 2027">Spring 2027 (Upcoming)</option>
                <option value="Summer 2026">Summer 2026</option>
              </select>
            </div>
          </div>

          <!-- Interested subject to admit in RMU -->
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Selected Program of Study / প্রোগ্রাম <span class="required">*</span></label>
            <select name="interested_subject" id="programSelect" class="form-select" required>
              <option value="">-- Select Your Preferred Program --</option>
              <optgroup label="Undergraduate Programs (Bachelor Degrees)">
                <?php foreach ($ugPrograms as $i => $prog): ?>
                  <?php 
                    $isSelected = ($selectedSubject === $prog['title_en'] || (!empty($selectedSubject) && stripos($prog['title_en'], $selectedSubject) !== false));
                  ?>
                  <option value="<?= e($prog['title_en']) ?>" data-dept="<?= (int)$prog['department_id'] ?>" data-prog-id="<?= (int)$prog['id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                    <?= ($i + 1) ?>. <?= e($prog['title_en']) ?> (<?= e($prog['dept_name'] ?? 'Faculty Program') ?>)
                  </option>
                <?php endforeach; ?>
              </optgroup>
              <optgroup label="Postgraduate Programs (Master's Degrees)">
                <?php foreach ($pgPrograms as $j => $prog): ?>
                  <?php 
                    $isSelected = ($selectedSubject === $prog['title_en'] || (!empty($selectedSubject) && stripos($prog['title_en'], $selectedSubject) !== false));
                  ?>
                  <option value="<?= e($prog['title_en']) ?>" data-dept="<?= (int)$prog['department_id'] ?>" data-prog-id="<?= (int)$prog['id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                    <?= ($j + 1) ?>. <?= e($prog['title_en']) ?> (<?= e($prog['dept_name'] ?? 'Faculty Program') ?>)
                  </option>
                <?php endforeach; ?>
              </optgroup>
            </select>
            <input type="hidden" name="program_id" id="selectedProgramId" value="">
            <div class="form-error-msg">Please select your intended academic program.</div>
          </div>
        </div>

        <!-- Academic Documents Upload (Certificates / Transcripts) -->
        <div class="form-group" style="background:#f8fafc; border:1.5px dashed #cbd5e1; border-radius:10px; padding:1.2rem; margin-bottom:1.5rem;">
          <label class="form-label" style="font-weight:700; color:#1e293b; margin-bottom:0.5rem; display:flex; align-items:center; gap:0.5rem;">
            <i class="fas fa-file-pdf" style="color:var(--rmu-primary);"></i>
            Academic Documents / শিক্ষাগত সনদ ও মার্কশীট <span style="font-size:0.8rem; font-weight:normal; color:#64748b;">(SSC &amp; HSC Transcripts/Certificates)</span>
          </label>
          <input type="file" name="academic_documents" id="academicDocsInput" class="form-control" accept=".pdf,image/jpeg,image/png,image/webp" style="padding:0.5rem 0.85rem; background:#ffffff;">
          <small style="color:#64748b; font-size:0.78rem; display:block; margin-top:0.4rem;">
            Upload your SSC/HSC transcript, certificate or testimonial (PDF, JPG, PNG, WEBP — Max 5MB).
          </small>
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

  // Program selection and Department linking
  const progSelect = document.getElementById('programSelect');
  const progIdInput = document.getElementById('selectedProgramId');
  const deptSelect = document.getElementById('departmentSelect');

  if (progSelect) {
    progSelect.addEventListener('change', function() {
      const opt = this.options[this.selectedIndex];
      if (opt && opt.dataset.progId) {
        if (progIdInput) progIdInput.value = opt.dataset.progId;
        if (deptSelect && opt.dataset.dept && !deptSelect.value) {
          deptSelect.value = opt.dataset.dept;
        }
      }
    });
  }

  window.filterProgramsByDept = function(deptId) {
    if (!progSelect) return;
    const opts = progSelect.querySelectorAll('option');
    opts.forEach(function(o) {
      if (!o.value) return; // Keep placeholder
      if (!deptId || o.dataset.dept === deptId) {
        o.style.display = '';
      } else {
        o.style.display = 'none';
      }
    });
  };

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

