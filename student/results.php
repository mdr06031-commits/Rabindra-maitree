<?php
/**
 * Student Portal - Academic Results & Official Grade Sheet
 */
$portalTitle = 'Academic Results & Grade Sheet';
require_once __DIR__ . '/includes/student-header.php';

$db = getDb();
$studentId = $student['id'];

// Retrieve all results grouped by semester
$stmt = $db->prepare("
  SELECT * FROM student_results 
  WHERE student_id = ? 
  ORDER BY id ASC
");
$stmt->execute([$studentId]);
$allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group results by semester
$semesters = [];
foreach ($allResults as $r) {
    $semesters[$r['semester']][] = $r;
}

// Calculate running cumulative CGPA up to each semester
$runningCredits = 0;
$runningPoints = 0;
$semesterStats = [];
foreach ($semesters as $semTitle => $cList) {
    $sCredits = array_sum(array_column($cList, 'credit'));
    $sPoints = 0;
    foreach ($cList as $res) {
        $sPoints += ((float)$res['credit'] * (float)$res['grade_point']);
    }
    $sgpa = ($sCredits > 0) ? round($sPoints / $sCredits, 2) : 0.00;

    $runningCredits += $sCredits;
    $runningPoints += $sPoints;
    $runningCgpa = ($runningCredits > 0) ? round($runningPoints / $runningCredits, 2) : 0.00;

    $semesterStats[$semTitle] = [
        'sem_credits' => $sCredits,
        'sem_points' => $sPoints,
        'sgpa' => $sgpa,
        'cum_credits' => $runningCredits,
        'cum_points' => $runningPoints,
        'cgpa' => $runningCgpa,
    ];
}
?>

<div class="sp-main-container">

  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0;" class="no-print">
    <div>
      <h2 style="margin:0 0 0.3rem; font-size:1.45rem; font-weight:700; color:#0f172a;">
        <i class="fas fa-file-invoice" style="color:var(--sp-primary);"></i> Academic Results &amp; Grade Transcript
      </h2>
      <p style="margin:0; font-size:0.88rem; color:#64748b;">
        Official Semester Final Results • Cumulative GPA: <strong><?= number_format((float)$student['cgpa'], 2) ?></strong>
      </p>
    </div>

    <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
      <a href="<?= APP_URL ?>/student/semester-transcript.php?semester=all" target="_blank" class="sp-btn-back-website" style="background:#16a34a; color:#fff; border-color:#16a34a; cursor:pointer; font-weight:600; text-decoration:none;">
        <i class="fas fa-file-download"></i> Download Full Transcript (PDF)
      </a>
      <button type="button" onclick="window.print()" class="sp-btn-back-website" style="background:#1e73be; color:#fff; border-color:#1e73be; cursor:pointer;">
        <i class="fas fa-print"></i> Print Grade Sheet
      </button>
    </div>
  </div>

  <!-- Semester Transcript Download Fast-Picker Bar (no-print) -->
  <?php if (!empty($semesters)): ?>
    <div style="background:linear-gradient(135deg, #f0f7ff 0%, #e0f2fe 100%); border:1.5px solid #bfdbfe; border-radius:10px; padding:1.25rem 1.5rem; margin-bottom:2rem; box-shadow:0 2px 5px rgba(0,0,0,0.03);" class="no-print">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:0.85rem;">
        <div>
          <h3 style="margin:0 0 0.2rem; font-size:1.05rem; font-weight:700; color:#0f2c59;">
            <i class="fas fa-download" style="color:#1e73be;"></i> প্রতিটি সেমিস্টারের ট্রান্সক্রিপ্ট ডাউনলোড (Download Semester Transcripts)
          </h3>
          <span style="font-size:0.82rem; color:#475569;">
            নিচের যেকোনো সেমিস্টারে ক্লিক করে সরাসরি সেই সেমিস্টারের অফিসিয়াল গ্রেড শিট ও ট্রান্সক্রিপ্ট PDF আকারে ডাউনলোড করুন:
          </span>
        </div>
        <a href="<?= APP_URL ?>/student/semester-transcript.php?semester=all" target="_blank" style="font-size:0.82rem; font-weight:700; color:#16a34a; background:#ffffff; border:1px solid #86efac; padding:4px 12px; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:5px;">
          <i class="fas fa-graduation-cap"></i> সম্পূর্ণ ৮ সেমিস্টার ট্রান্সক্রিপ্ট &rarr;
        </a>
      </div>

      <div style="display:flex; flex-wrap:wrap; gap:0.6rem;">
        <?php foreach ($semesters as $sTitle => $cList): ?>
          <?php $st = $semesterStats[$sTitle] ?? ['sgpa' => 0, 'cgpa' => 0]; ?>
          <a href="<?= APP_URL ?>/student/semester-transcript.php?semester=<?= urlencode($sTitle) ?>" target="_blank" style="background:#ffffff; color:#0f2c59; border:1.5px solid #cbd5e1; border-radius:6px; padding:0.45rem 0.95rem; font-size:0.84rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.15s ease; box-shadow:0 1px 3px rgba(0,0,0,0.05);" onmouseover="this.style.borderColor='#1e73be'; this.style.color='#1e73be';" onmouseout="this.style.borderColor='#cbd5e1'; this.style.color='#0f2c59';">
            <i class="fas fa-file-pdf" style="color:#dc2626;"></i>
            <span><?= e($sTitle) ?></span>
            <span style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; padding:1px 6px; border-radius:4px; font-size:0.75rem;">SGPA: <?= number_format($st['sgpa'], 2) ?></span>
            <span style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; padding:1px 6px; border-radius:4px; font-size:0.75rem; font-weight:800;">CGPA: <?= number_format($st['cgpa'], 2) ?></span>
            <i class="fas fa-arrow-down" style="font-size:0.72rem; color:#64748b;"></i>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Official Transcript Document Box (Printable) -->
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:2.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.04); margin-bottom:2.5rem;" id="printableTranscript">
    
    <!-- University Seal Header -->
    <div style="text-align:center; padding-bottom:1.5rem; margin-bottom:1.5rem; border-bottom:2px solid #0f172a;">
      <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="RMU Monogram" style="width:65px; height:65px; border-radius:8px; margin-bottom:0.5rem;" onerror="this.src='<?= APP_URL ?>/assets/images/logo.svg'">
      <h1 style="margin:0 0 0.2rem; font-size:1.5rem; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:0.5px;">Rabindra Maitree University</h1>
      <div style="font-size:0.85rem; color:#475569; font-weight:500;">Kushtia, Bangladesh • Approved by UGC &amp; Govt. of Bangladesh</div>
      <div style="display:inline-block; margin-top:0.6rem; padding:4px 16px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:20px; font-weight:700; font-size:0.9rem; color:#1e73be; text-transform:uppercase;">
        OFFICIAL ACADEMIC TRANSCRIPT &amp; GRADE RECORD
      </div>
    </div>

    <!-- Student Information Block -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1.25rem; margin-bottom:2rem; font-size:0.88rem;">
      <div>
        <div><span style="color:#64748b;">Student Name:</span> <strong><?= e($student['full_name']) ?></strong></div>
        <div style="margin-top:4px;"><span style="color:#64748b;">Student ID:</span> <strong style="font-family:monospace; color:var(--sp-primary);"><?= e($student['student_id']) ?></strong></div>
        <div style="margin-top:4px;"><span style="color:#64748b;">Program:</span> <strong><?= e($student['program_name']) ?></strong></div>
      </div>
      <div>
        <div><span style="color:#64748b;">Academic Department:</span> <strong><?= e($student['department_name']) ?></strong></div>
        <div style="margin-top:4px;"><span style="color:#64748b;">Batch &amp; Session:</span> <strong><?= e($student['batch']) ?></strong></div>
        <div style="margin-top:4px;"><span style="color:#64748b;">Cumulative CGPA:</span> <strong style="color:#16a34a; font-size:1.05rem;"><?= number_format((float)$student['cgpa'], 2) ?></strong> / 4.00</div>
      </div>
    </div>

    <!-- Semester-wise Tables -->
    <?php if (empty($semesters)): ?>
      <p style="text-align:center; padding:2rem; color:#64748b;">No published semester results yet.</p>
    <?php else: ?>
      <?php foreach ($semesters as $semTitle => $results): ?>
        <?php $st = $semesterStats[$semTitle] ?? ['sem_credits' => 0, 'sgpa' => 0, 'cum_credits' => 0, 'cgpa' => 0]; ?>
        <div style="margin-bottom:2.5rem;">
          <div style="display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg, #1e73be 0%, #0f2c59 100%); color:#ffffff; padding:0.75rem 1.25rem; border-radius:6px 6px 0 0; font-size:0.92rem; font-weight:700; flex-wrap:wrap; gap:0.6rem;">
            <span style="display:flex; align-items:center; gap:0.5rem;">
              <i class="fas fa-calendar-alt"></i> <?= e($semTitle) ?>
            </span>
            <div style="display:flex; align-items:center; gap:0.75rem; font-size:0.84rem; flex-wrap:wrap;">
              <span style="background:rgba(255,255,255,0.18); padding:3px 10px; border-radius:4px; border:1px solid rgba(255,255,255,0.25);">
                Semester GPA (SGPA): <strong style="font-size:0.92rem;"><?= number_format($st['sgpa'], 2) ?></strong>
              </span>
              <span style="background:#15803d; color:#ffffff; padding:3px 10px; border-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,0.2);">
                Cumulative CGPA (সিজিপিএ): <strong style="font-size:0.95rem;"><?= number_format($st['cgpa'], 2) ?></strong> / 4.00
              </span>
              <a href="<?= APP_URL ?>/student/semester-transcript.php?semester=<?= urlencode($semTitle) ?>" target="_blank" class="no-print" style="background:#ffffff; color:#0f2c59; border-radius:4px; padding:3px 10px; font-size:0.78rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px; box-shadow:0 1px 2px rgba(0,0,0,0.15);">
                <i class="fas fa-file-pdf" style="color:#dc2626;"></i> Download Transcript
              </a>
            </div>
          </div>

          <table class="sp-table" style="border:1px solid #cbd5e1; border-top:none; border-radius:0 0 6px 6px;">
            <thead>
              <tr>
                <th style="width:110px;">Course Code</th>
                <th>Course Title</th>
                <th style="width:80px; text-align:center;">Credit</th>
                <th style="width:100px; text-align:center;">Letter Grade</th>
                <th style="width:100px; text-align:center;">Grade Point</th>
                <th style="width:90px; text-align:center;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($results as $r): ?>
                <tr>
                  <td style="font-family:monospace; font-weight:700; color:#1e293b;"><?= e($r['course_code']) ?></td>
                  <td><?= e($r['course_name']) ?></td>
                  <td style="text-align:center;"><?= number_format((float)$r['credit'], 1) ?></td>
                  <td style="text-align:center;"><strong style="color:var(--sp-primary); font-size:0.95rem;"><?= e($r['letter_grade']) ?></strong></td>
                  <td style="text-align:center;"><?= number_format((float)$r['grade_point'], 2) ?></td>
                  <td style="text-align:center;"><span style="color:#16a34a; font-weight:700; font-size:0.75rem;">PASS</span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="background:#f8fafc; font-weight:700; border-top:2px solid #cbd5e1;">
                <td colspan="2" style="text-align:right; color:#475569;">Semester Summary (এই সেমিস্টার):</td>
                <td style="text-align:center; color:#0f172a;"><?= number_format((float)$st['sem_credits'], 1) ?> Cr</td>
                <td colspan="2" style="text-align:right; color:#1e73be;">Semester GPA (SGPA):</td>
                <td style="text-align:center; color:#1e73be; font-size:1.05rem; font-weight:800;"><?= number_format($st['sgpa'], 2) ?></td>
              </tr>
              <tr style="background:#f0fdf4; font-weight:800; border-top:1px dashed #bbf7d0;">
                <td colspan="2" style="text-align:right; color:#166534;">Cumulative Summary (সর্বমোট সিজিপিএ up to <?= e($semTitle) ?>):</td>
                <td style="text-align:center; color:#166534;"><?= number_format((float)$st['cum_credits'], 1) ?> Cr</td>
                <td colspan="2" style="text-align:right; color:#15803d;">Cumulative CGPA (সিজিপিএ):</td>
                <td style="text-align:center; color:#15803d; font-size:1.15rem; font-weight:900;"><?= number_format($st['cgpa'], 2) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- Signature / Verification Footer -->
    <div style="margin-top:3.5rem; padding-top:2rem; border-top:1px dashed #cbd5e1; display:flex; justify-content:space-between; align-items:flex-end;">
      <div style="text-align:center; width:220px;">
        <div style="border-bottom:1px solid #475569; padding-bottom:5px; margin-bottom:5px; font-size:0.85rem; font-weight:600;">
          Office of Registrar
        </div>
        <small style="color:#64748b;">Prepared &amp; Verified By</small>
      </div>

      <div style="text-align:center;">
        <div style="border:2px dashed #94a3b8; border-radius:50%; width:90px; height:90px; display:inline-flex; align-items:center; justify-content:center; color:#64748b; font-size:0.7rem; font-weight:700; text-transform:uppercase;">
          OFFICIAL<br>RMU SEAL
        </div>
      </div>

      <div style="text-align:center; width:220px;">
        <div style="border-bottom:1px solid #475569; padding-bottom:5px; margin-bottom:5px; font-size:0.85rem; font-weight:700; color:#0f172a;">
          Controller of Examinations
        </div>
        <small style="color:#64748b;">Rabindra Maitree University</small>
      </div>
    </div>

  </div>

  <!-- Grading System Standard Reference Table -->
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

