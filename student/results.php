<?php
/**
 * Student Portal - Academic Results & Official Grade Sheet
 * Complete 8-Semester Academic Transcript & CGPA Management System
 * Rabindra Maitree University (RMU)
 */
$portalTitle = 'Academic Results & Grade Sheet';
require_once __DIR__ . '/includes/student-header.php';

$student = get_logged_in_student();
$studentId = (int)($student['id'] ?? ($_SESSION['student_pk_id'] ?? 0));

// Retrieve complete academic summary (only published results for students)
$academicSummary = get_student_academic_summary($studentId, true);
$hasResults = $academicSummary['has_results'];
$semesters = $academicSummary['semesters'] ?? [];
$semesterGpas = $academicSummary['semester_gpas'] ?? [];
$finalCgpa = $academicSummary['final_cgpa'] ?? 0.00;
$totalRegCredits = $academicSummary['total_registered_credits'] ?? 0.0;
$totalEarnedCredits = $academicSummary['total_earned_credits'] ?? 0.0;

$transcriptSerial = 'RMU-TR-' . date('Y') . '-' . strtoupper(substr(md5($student['student_id'] . 'all'), 0, 8));
$issueDate = date('d F Y');
?>

<div class="sp-main-container">

  <!-- Header Action Bar (no-print) -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0;" class="no-print">
    <div>
      <h2 style="margin:0 0 0.3rem; font-size:1.45rem; font-weight:700; color:#0f172a;">
        <i class="fas fa-file-invoice" style="color:var(--sp-primary);"></i> Academic Results &amp; Grade Transcript
      </h2>
      <p style="margin:0; font-size:0.88rem; color:#64748b;">
        Official Semester Final Results • Overall Cumulative CGPA: 
        <?php if ($hasResults): ?>
          <strong style="color:#15803d; font-size:0.95rem;"><?= number_format($finalCgpa, 2) ?></strong> / 4.00
        <?php else: ?>
          <span style="color:#94a3b8; font-style:italic;">No published results yet</span>
        <?php endif; ?>
      </p>
    </div>

    <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
      <?php if ($hasResults): ?>
        <a href="<?= APP_URL ?>/student/semester-transcript.php?semester=all" target="_blank" class="sp-btn-back-website" style="background:#16a34a; color:#fff; border-color:#16a34a; cursor:pointer; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
          <i class="fas fa-file-download"></i> Download Full Transcript (PDF)
        </a>
        <button type="button" onclick="window.print()" class="sp-btn-back-website" style="background:#1e73be; color:#fff; border-color:#1e73be; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
          <i class="fas fa-print"></i> Print Grade Sheet
        </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$hasResults): ?>
    <!-- Empty State: No published academic results -->
    <div style="background:#ffffff; border:1.5px dashed #cbd5e1; border-radius:12px; padding:3.5rem 2rem; text-align:center; margin-bottom:2.5rem; box-shadow:0 2px 6px rgba(0,0,0,0.03);">
      <div style="width:72px; height:72px; border-radius:50%; background:#f1f5f9; color:#94a3b8; display:inline-flex; align-items:center; justify-content:center; font-size:2rem; margin-bottom:1.25rem;">
        <i class="fas fa-graduation-cap"></i>
      </div>
      <h3 style="margin:0 0 0.5rem; font-size:1.35rem; font-weight:700; color:#0f172a;">No academic results published yet</h3>
      <p style="margin:0 auto 1.5rem; max-width:540px; font-size:0.92rem; color:#64748b; line-height:1.6;">
        Your semester final examination results have not been published by the Office of the Controller of Examinations. Once grades are finalized and published, your full semester grade sheets and academic transcripts will appear here.
      </p>
      <div style="display:inline-flex; align-items:center; gap:8px; background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; padding:8px 16px; border-radius:6px; font-size:0.85rem; font-weight:600;">
        <i class="fas fa-info-circle"></i> For result publication queries, please contact the Registrar Office or Exam Controller.
      </div>
    </div>
  <?php else: ?>

    <!-- 8-Semester GPA Progression Summary Grid (no-print) -->
    <div style="background:linear-gradient(135deg, #0f2c59 0%, #1e3a8a 100%); color:#ffffff; border-radius:12px; padding:1.5rem; margin-bottom:2rem; box-shadow:0 4px 15px rgba(15,44,89,0.15);" class="no-print">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1.25rem;">
        <div>
          <h3 style="margin:0 0 0.2rem; font-size:1.15rem; font-weight:800; color:#ffffff;">
            <i class="fas fa-chart-line" style="color:#f59e0b;"></i> Eight-Semester GPA Progression &amp; Cumulative CGPA
          </h3>
          <span style="font-size:0.84rem; color:#cbd5e1;">
            Credit-weighted Cumulative CGPA calculated strictly across all 8 academic semesters:
          </span>
        </div>
        <div style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25); padding:6px 14px; border-radius:6px; font-size:0.9rem; font-weight:700;">
          Final CGPA: <span style="color:#86efac; font-size:1.15rem; font-weight:900;"><?= number_format($finalCgpa, 2) ?></span> / 4.00
        </div>
      </div>

      <!-- 8 Semester Cards Strip -->
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(115px, 1fr)); gap:0.6rem;">
        <?php for ($semNum = 1; $semNum <= 8; $semNum++): ?>
          <?php 
            $sgpaVal = $semesterGpas[$semNum] ?? null;
            $hasSem = ($sgpaVal !== null);
          ?>
          <div style="background:<?= $hasSem ? 'rgba(255,255,255,0.12)' : 'rgba(255,255,255,0.05)' ?>; border:1px solid <?= $hasSem ? 'rgba(255,255,255,0.25)' : 'rgba(255,255,255,0.1)' ?>; border-radius:8px; padding:0.65rem 0.5rem; text-align:center;">
            <div style="font-size:0.75rem; text-transform:uppercase; color:<?= $hasSem ? '#f59e0b' : '#94a3b8' ?>; font-weight:700; margin-bottom:4px;">
              Sem <?= $semNum ?>
            </div>
            <div style="font-size:1.1rem; font-weight:800; color:<?= $hasSem ? '#ffffff' : '#64748b' ?>;">
              <?= $hasSem ? number_format($sgpaVal, 2) : '—' ?>
            </div>
            <div style="font-size:0.7rem; color:<?= $hasSem ? '#86efac' : '#64748b' ?>; font-weight:600; margin-top:2px;">
              <?= $hasSem ? 'PASSED' : 'Pending' ?>
            </div>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- 8-Semester Interactive Navigation Matrix (no-print) -->
    <div style="background:linear-gradient(135deg, #f0f7ff 0%, #e0f2fe 100%); border:1.5px solid #bfdbfe; border-radius:12px; padding:1.5rem; margin-bottom:2.5rem; box-shadow:0 3px 8px rgba(0,0,0,0.04);" class="no-print">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1.25rem;">
        <div>
          <h3 style="margin:0 0 0.2rem; font-size:1.15rem; font-weight:800; color:#0f2c59;">
            <i class="fas fa-th-large" style="color:#1e73be;"></i> সেমিস্টার অগ্রগতি ও ট্রান্সক্রিপ্ট গ্রিড (1st to 8th Semester)
          </h3>
          <span style="font-size:0.84rem; color:#475569;">
            প্রতিটি সেমিস্টারের বিস্তারিত বিষয়ভিত্তিক প্রাপ্ত নম্বর, লেটার গ্রেড, SGPA ও PDF ডাউনলোড লিঙ্ক:
          </span>
        </div>
        <a href="<?= APP_URL ?>/student/semester-transcript.php?semester=all" target="_blank" style="font-size:0.85rem; font-weight:700; color:#16a34a; background:#ffffff; border:1.5px solid #86efac; padding:6px 14px; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
          <i class="fas fa-graduation-cap"></i> সম্পূর্ণ ৮ সেমিস্টার ট্রান্সক্রিপ্ট (PDF) &rarr;
        </a>
      </div>

      <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:0.85rem;">
        <?php foreach ($semesters as $sTitle => $semData): ?>
          <?php 
            $sNum = $semData['semester_number'];
            $cCount = count($semData['courses']);
          ?>
          <div style="background:#ffffff; border:1.5px solid #cbd5e1; border-radius:8px; padding:0.85rem 1rem; box-shadow:0 1px 4px rgba(0,0,0,0.04); display:flex; flex-direction:column; justify-content:space-between; gap:0.5rem; transition:transform 0.15s, border-color 0.15s;" onmouseover="this.style.borderColor='#1e73be'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='#cbd5e1'; this.style.transform='translateY(0)';" id="nav-sem-<?= $sNum ?>">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span style="background:#0f2c59; color:#ffffff; font-weight:800; font-size:0.8rem; padding:2px 8px; border-radius:4px; display:inline-flex; align-items:center; gap:4px;">
                <i class="fas fa-bookmark" style="color:#f59e0b; font-size:0.7rem;"></i> <?= $sNum ?>. Semester <?= $sNum ?>
              </span>
              <a href="<?= APP_URL ?>/student/semester-transcript.php?semester=<?= urlencode($sTitle) ?>" target="_blank" style="color:#dc2626; font-size:0.8rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:3px; background:#fef2f2; border:1px solid #fecaca; padding:2px 7px; border-radius:4px;" title="Download PDF Transcript">
                <i class="fas fa-file-pdf"></i> PDF
              </a>
            </div>
            
            <div>
              <a href="#sem-block-<?= $sNum ?>" style="font-size:0.9rem; font-weight:700; color:#0f172a; text-decoration:none; display:block; margin-bottom:2px;">
                <?= e($sTitle) ?>
              </a>
              <small style="color:#64748b; font-size:0.75rem;"><?= $cCount ?> Subjects &bull; <?= number_format($semData['registered_credits'], 1) ?> Credits</small>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px dashed #e2e8f0; padding-top:6px; font-size:0.8rem;">
              <span style="color:#475569;">SGPA: <strong style="color:#1e3a8a;"><?= number_format($semData['sgpa'], 2) ?></strong></span>
              <span style="color:#15803d; font-weight:800; background:#f0fdf4; border:1px solid #bbf7d0; padding:1px 6px; border-radius:4px;">CGPA: <?= number_format($semData['cumulative_cgpa'], 2) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Official Printable Transcript Box -->
    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:2.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.04); margin-bottom:2.5rem;" id="printableTranscript">
      
      <!-- University Seal Header -->
      <div style="text-align:center; padding-bottom:1.5rem; margin-bottom:1.5rem; border-bottom:2px solid #0f172a;">
        <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="RMU Monogram" style="width:68px; height:68px; border-radius:8px; margin-bottom:0.5rem;" onerror="this.src='<?= APP_URL ?>/assets/images/logo.svg'">
        <h1 style="margin:0 0 0.2rem; font-size:1.6rem; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:0.5px;">Rabindra Maitree University</h1>
        <div style="font-size:0.88rem; color:#475569; font-weight:600;">Kushtia, Bangladesh • Approved by Govt. of Bangladesh &amp; UGC</div>
        <div style="font-size:0.84rem; color:#1e293b; font-weight:700; margin-top:2px; text-transform:uppercase; letter-spacing:0.5px;">Office of the Controller of Examinations</div>
        <div style="display:inline-block; margin-top:0.75rem; padding:5px 20px; background:#eff6ff; border:1.5px solid #bfdbfe; border-radius:20px; font-weight:800; font-size:0.92rem; color:#1e73be; text-transform:uppercase; letter-spacing:0.5px;">
          OFFICIAL ACADEMIC TRANSCRIPT &amp; EIGHT-SEMESTER GRADE RECORD
        </div>
      </div>

      <!-- Student Information Block (All 9 Mandatory Fields) -->
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:0.75rem 2rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1.25rem 1.5rem; margin-bottom:2rem; font-size:0.88rem;">
        <div>
          <div style="padding:2px 0;"><span style="color:#64748b; width:150px; display:inline-block;">Student Name:</span> <strong><?= e($student['full_name']) ?></strong></div>
          <div style="padding:2px 0;"><span style="color:#64748b; width:150px; display:inline-block;">Student ID / Roll:</span> <strong style="font-family:monospace; color:var(--sp-primary); font-size:0.95rem;"><?= e($student['student_id']) ?></strong></div>
          <div style="padding:2px 0;"><span style="color:#64748b; width:150px; display:inline-block;">Program of Study:</span> <strong><?= e($student['program_name'] ?? 'B.Sc. in Computer Science & Engineering') ?></strong></div>
          <div style="padding:2px 0;"><span style="color:#64748b; width:150px; display:inline-block;">Academic Department:</span> <strong><?= e($student['department_name'] ?? 'Computer Science & Engineering') ?></strong></div>
          <div style="padding:2px 0;"><span style="color:#64748b; width:150px; display:inline-block;">Faculty:</span> <strong><?= e($academicSummary['student']['faculty_name'] ?? 'Faculty of Science & Engineering') ?></strong></div>
        </div>
        <div>
          <div style="padding:2px 0;"><span style="color:#64748b; width:160px; display:inline-block;">Academic Session:</span> <strong><?= e($student['batch']) ?></strong></div>
          <div style="padding:2px 0;"><span style="color:#64748b; width:160px; display:inline-block;">Semester / Term:</span> <strong style="color:#0f2c59;">All Semesters (1st to 8th)</strong></div>
          <div style="padding:2px 0;"><span style="color:#64748b; width:160px; display:inline-block;">Current Academic Status:</span> <strong style="color:<?= ($student['status'] === 'Graduated') ? '#15803d' : '#0284c7' ?>;"><?= e($student['status']) ?> (<?= ($student['status'] === 'Graduated') ? 'Degree Awarded' : 'Undergraduate' ?>)</strong></div>
          <div style="padding:2px 0;"><span style="color:#64748b; width:160px; display:inline-block;">Medium of Instruction:</span> <strong>English</strong></div>
          <div style="padding:2px 0; margin-top:2px;"><span style="color:#64748b; width:160px; display:inline-block;">Cumulative CGPA:</span> <strong style="color:#15803d; font-size:1.05rem;"><?= number_format($finalCgpa, 2) ?></strong> / 4.00</div>
        </div>
      </div>

      <!-- Semester-wise Tables with Subject Marks, Credits, Grades, SGPA -->
      <?php foreach ($semesters as $semTitle => $semData): ?>
        <?php 
          $sNum = $semData['semester_number'];
        ?>
        <div style="margin-bottom:2.5rem;" id="sem-block-<?= $sNum ?>">
          <!-- Semester Header Strip -->
          <div style="display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg, #1e73be 0%, #0f2c59 100%); color:#ffffff; padding:0.75rem 1.25rem; border-radius:6px 6px 0 0; font-size:0.92rem; font-weight:700; flex-wrap:wrap; gap:0.6rem;">
            <span style="display:flex; align-items:center; gap:0.6rem;">
              <span style="background:#f59e0b; color:#0f172a; padding:2px 8px; border-radius:4px; font-weight:900; font-size:0.85rem;">
                <?= $sNum ?>
              </span>
              <span>Semester <?= $sNum ?>: <?= e($semTitle) ?></span>
            </span>
            <div style="display:flex; align-items:center; gap:0.75rem; font-size:0.84rem; flex-wrap:wrap;">
              <span style="background:rgba(255,255,255,0.18); padding:3px 10px; border-radius:4px; border:1px solid rgba(255,255,255,0.25);">
                Semester GPA (SGPA): <strong style="font-size:0.92rem;"><?= number_format($semData['sgpa'], 2) ?></strong>
              </span>
              <span style="background:#15803d; color:#ffffff; padding:3px 10px; border-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,0.2);">
                Cumulative CGPA: <strong style="font-size:0.95rem;"><?= number_format($semData['cumulative_cgpa'], 2) ?></strong> / 4.00
              </span>
              <a href="<?= APP_URL ?>/student/semester-transcript.php?semester=<?= urlencode($semTitle) ?>" target="_blank" class="no-print" style="background:#ffffff; color:#0f2c59; border-radius:4px; padding:3px 10px; font-size:0.78rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px; box-shadow:0 1px 2px rgba(0,0,0,0.15);">
                <i class="fas fa-file-pdf" style="color:#dc2626;"></i> PDF Transcript
              </a>
            </div>
          </div>

          <!-- Subjects Table with Full Marks & Obtained Marks -->
          <table class="sp-table" style="border:1px solid #cbd5e1; border-top:none; border-radius:0 0 6px 6px;">
            <thead>
              <tr>
                <th style="width:110px;">Subject Code</th>
                <th>Subject Name</th>
                <th style="width:75px; text-align:center;">Credit</th>
                <th style="width:85px; text-align:center;">Full Marks</th>
                <th style="width:90px; text-align:center;">Obtained</th>
                <th style="width:80px; text-align:center;">Grade</th>
                <th style="width:90px; text-align:center;">Grade Point</th>
                <th style="width:85px; text-align:center;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($semData['courses'] as $c): ?>
                <tr>
                  <td style="font-family:monospace; font-weight:700; color:#1e293b;"><?= e($c['course_code']) ?></td>
                  <td style="font-weight:600; color:#0f172a;"><?= e($c['course_name']) ?></td>
                  <td style="text-align:center; font-weight:600;"><?= number_format($c['credit'], 1) ?></td>
                  <td style="text-align:center; color:#64748b;"><?= (int)$c['full_marks'] ?></td>
                  <td style="text-align:center; font-weight:700; color:#0f2c59;"><?= ($c['marks'] !== null) ? number_format($c['marks'], 1) : '—' ?></td>
                  <td style="text-align:center;"><strong style="color:var(--sp-primary); font-size:0.95rem;"><?= e($c['letter_grade']) ?></strong></td>
                  <td style="text-align:center; font-weight:600;"><?= number_format($c['grade_point'], 2) ?></td>
                  <td style="text-align:center;">
                    <span style="color:<?= ($c['status'] === 'Pass') ? '#16a34a' : '#dc2626' ?>; font-weight:700; font-size:0.75rem;">
                      <?= strtoupper($c['status']) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <!-- Semester Footer: Total Registered, Total Earned, SGPA, Status -->
              <tr style="background:#f8fafc; font-weight:700; border-top:2px solid #cbd5e1; font-size:0.86rem;">
                <td colspan="2" style="text-align:right; color:#475569;">Semester Summary (<?= e($semTitle) ?>):</td>
                <td style="text-align:center; color:#0f172a;"><?= number_format($semData['registered_credits'], 1) ?> Cr</td>
                <td colspan="2" style="text-align:center; color:#1e293b;">Earned: <strong><?= number_format($semData['earned_credits'], 1) ?> Cr</strong></td>
                <td style="text-align:right; color:#1e73be;">SGPA:</td>
                <td style="text-align:center; color:#1e73be; font-size:1.05rem; font-weight:800;"><?= number_format($semData['sgpa'], 2) ?></td>
                <td style="text-align:center;"><span style="color:#15803d; font-weight:800; font-size:0.78rem;"><?= e($semData['status']) ?></span></td>
              </tr>
              <!-- Cumulative Footer -->
              <tr style="background:#f0fdf4; font-weight:800; border-top:1px dashed #bbf7d0; font-size:0.86rem;">
                <td colspan="2" style="text-align:right; color:#166534;">Cumulative Progress (up to <?= e($semTitle) ?>):</td>
                <td style="text-align:center; color:#166534;"><?= number_format($semData['cumulative_reg_credits'], 1) ?> Cr</td>
                <td colspan="2" style="text-align:center; color:#166534;">Earned: <?= number_format($semData['cumulative_earned_credits'], 1) ?> Cr</td>
                <td style="text-align:right; color:#15803d;">CGPA:</td>
                <td colspan="2" style="text-align:center; color:#15803d; font-size:1.1rem; font-weight:900;"><?= number_format($semData['cumulative_cgpa'], 2) ?> / 4.00</td>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endforeach; ?>

      <!-- 7. Official Transcript Summary Section -->
      <div style="background:#f8fafc; border:1.5px solid #cbd5e1; border-radius:8px; padding:1.25rem 1.5rem; margin-top:2rem; margin-bottom:2.5rem;">
        <h4 style="margin:0 0 0.75rem; font-size:1rem; font-weight:800; color:#0f2c59; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid #e2e8f0; padding-bottom:6px;">
          <i class="fas fa-clipboard-check" style="color:#16a34a;"></i> Academic Transcript Summary &amp; Degree Status
        </h4>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; font-size:0.88rem;">
          <div>
            <div style="color:#64748b; font-size:0.78rem; text-transform:uppercase;">Total Completed Credits</div>
            <div style="font-size:1.2rem; font-weight:800; color:#0f172a; margin-top:2px;">
              <?= (int)$totalEarnedCredits ?> <span style="font-size:0.82rem; color:#64748b; font-weight:500;">/ <?= (int)$student['total_credits_required'] ?> Credits Required</span>
            </div>
          </div>
          <div>
            <div style="color:#64748b; font-size:0.78rem; text-transform:uppercase;">Overall Cumulative CGPA</div>
            <div style="font-size:1.35rem; font-weight:900; color:#15803d; margin-top:2px;">
              <?= number_format($finalCgpa, 2) ?> <span style="font-size:0.82rem; color:#64748b; font-weight:500;">/ 4.00</span>
            </div>
          </div>
          <div>
            <div style="color:#64748b; font-size:0.78rem; text-transform:uppercase;">Overall Academic Status</div>
            <div style="font-size:1.05rem; font-weight:800; color:<?= ($student['status'] === 'Graduated') ? '#15803d' : '#0369a1' ?>; margin-top:2px;">
              <?= ($student['status'] === 'Graduated') ? 'DEGREE COMPLETED / GRADUATED' : 'ACTIVE / IN GOOD STANDING' ?>
            </div>
          </div>
          <div>
            <div style="color:#64748b; font-size:0.78rem; text-transform:uppercase;">Date of Issue &bull; Serial No.</div>
            <div style="font-size:0.9rem; font-weight:700; color:#0f172a; margin-top:2px;">
              <?= e($issueDate) ?><br>
              <span style="font-family:monospace; color:#475569; font-size:0.82rem;"><?= e($transcriptSerial) ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Signature & Seal Section -->
      <div style="margin-top:3.5rem; padding-top:1.5rem; border-top:1px dashed #cbd5e1; display:flex; justify-content:space-between; align-items:flex-end;">
        <div style="text-align:center; width:220px;">
          <div style="font-family:'Brush Script MT', cursive, sans-serif; font-size:1.3rem; color:#0f2c59; transform:rotate(-3deg); height:28px;">A. H. Tanvir</div>
          <div style="border-bottom:1px solid #475569; padding-bottom:5px; margin-bottom:5px; font-size:0.85rem; font-weight:600;">
            Office of Registrar
          </div>
          <small style="color:#64748b;">Prepared &amp; Verified By</small>
        </div>

        <div style="text-align:center;">
          <div style="border:2px dashed #0f2c59; border-radius:50%; width:95px; height:95px; display:inline-flex; flex-direction:column; align-items:center; justify-content:center; color:#0f2c59; font-size:0.68rem; font-weight:800; text-transform:uppercase; line-height:1.25; background:rgba(239,246,255,0.5);">
            <i class="fas fa-university" style="font-size:1rem; margin-bottom:2px;"></i>
            <span>RMU SEAL</span>
            <small style="font-size:0.55rem;">KUSHTIA</small>
          </div>
        </div>

        <div style="text-align:center; width:220px;">
          <div style="font-family:'Brush Script MT', cursive, sans-serif; font-size:1.4rem; color:#0f2c59; transform:rotate(-4deg); height:28px;">Prof. M. Rahman</div>
          <div style="border-bottom:1px solid #475569; padding-bottom:5px; margin-bottom:5px; font-size:0.85rem; font-weight:700; color:#0f172a;">
            Controller of Examinations
          </div>
          <small style="color:#64748b;">Rabindra Maitree University</small>
        </div>
      </div>

    </div>

  <?php endif; ?>

  <!-- UGC Uniform Grading Scale Reference Table (no-print) -->
  <div class="sp-card no-print">
    <div class="sp-card-header">
      <h3><i class="fas fa-chart-bar" style="color:var(--sp-primary);"></i> UGC Uniform Grading Scale Reference</h3>
    </div>
    <div class="sp-card-body" style="padding:0;">
      <table class="sp-table">
        <thead>
          <tr>
            <th>Numerical Marks</th>
            <th>Letter Grade</th>
            <th>Grade Point</th>
            <th>Performance Description</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>80% and above</td><td><strong>A+</strong></td><td>4.00</td><td>Outstanding</td></tr>
          <tr><td>75% to less than 80%</td><td><strong>A</strong></td><td>3.75</td><td>Excellent</td></tr>
          <tr><td>70% to less than 75%</td><td><strong>A-</strong></td><td>3.50</td><td>Very Good</td></tr>
          <tr><td>65% to less than 70%</td><td><strong>B+</strong></td><td>3.25</td><td>Good</td></tr>
          <tr><td>60% to less than 65%</td><td><strong>B</strong></td><td>3.00</td><td>Satisfactory</td></tr>
          <tr><td>55% to less than 60%</td><td><strong>B-</strong></td><td>2.75</td><td>Above Average</td></tr>
          <tr><td>50% to less than 55%</td><td><strong>C+</strong></td><td>2.50</td><td>Average</td></tr>
          <tr><td>45% to less than 50%</td><td><strong>C</strong></td><td>2.25</td><td>Pass</td></tr>
          <tr><td>40% to less than 45%</td><td><strong>D</strong></td><td>2.00</td><td>Conditional Pass</td></tr>
          <tr><td>Less than 40%</td><td><strong style="color:#dc2626;">F</strong></td><td>0.00</td><td>Fail</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/student-footer.php'; ?>
