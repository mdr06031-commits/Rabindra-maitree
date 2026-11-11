<?php
/**
 * Student Portal - Digital Student ID Card & Exam Admit Card
 */
$portalTitle = 'Digital ID & Admit Card';
require_once __DIR__ . '/includes/student-header.php';
$idCardSession = !empty($student['academic_session']) ? $student['academic_session'] : (!empty($student['batch']) ? $student['batch'] : get_current_academic_session_name('Current Session'));
$idCardStruct = get_student_program_structure($student);
$idCardDurYears = !empty($student['duration_years']) ? (float)$student['duration_years'] : (float)$idCardStruct['duration_years'];
$idCardTotSems = !empty($student['total_semesters']) ? (int)$student['total_semesters'] : (int)$idCardStruct['total_semesters'];
$idCardDurLabel = !empty($student['program_duration']) ? $student['program_duration'] : format_duration_years_label($idCardDurYears, $idCardTotSems);
$idCardPassingYear = (isset($student['passing_year']) && trim((string)$student['passing_year']) !== '') ? trim((string)$student['passing_year']) : '';
$validTillYear = null;
if (!empty($student['expected_passing_year'])) {
    $validTillYear = (int)$student['expected_passing_year'];
} else {
    $expCardCalc = calculate_expected_passing_year($idCardSession, $idCardDurYears);
    if (!empty($expCardCalc['expected_year'])) {
        $validTillYear = (int)$expCardCalc['expected_year'];
    }
}
$validTillYear = $validTillYear ?: 2028;
$idCardAppId = trim((string)($student['application_number'] ?? ''));
$admitRollNo = trim((string)($student['roll_number'] ?? ''));
if (($idCardAppId === '' || $admitRollNo === '') && !empty($student['student_id'])) {
    $cardDb = getDb();
    $cardAdmStmt = $cardDb->prepare("SELECT application_ref, application_id, roll_number, exam_roll FROM admissions WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $cardAdmStmt->execute([$student['student_id']]);
    if ($cardAdm = $cardAdmStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($idCardAppId === '') {
            $idCardAppId = trim((string)($cardAdm['application_ref'] ?: ($cardAdm['application_id'] ?? '')));
        }
        if ($admitRollNo === '') {
            $admitRollNo = trim((string)($cardAdm['roll_number'] ?: ($cardAdm['exam_roll'] ?? '')));
        }
    }
}
if ($admitRollNo === '') {
    $admitRollNo = date('Y') . '-' . substr(preg_replace('/\D/', '', (string)$student['student_id']) ?: '0001', -4);
}
$idDb = getDb();
$feeClearStmt = $idDb->prepare("
    SELECT COUNT(*) AS total_cnt,
           SUM(CASE WHEN status = 'Paid' THEN 0 ELSE 1 END) AS due_cnt,
           COALESCE(SUM(CASE WHEN status = 'Paid' THEN 0 ELSE amount END), 0) AS due_amt
    FROM student_fees
    WHERE student_id = ?
");
$feeClearStmt->execute([(int)$student['id']]);
$feeClearRow = $feeClearStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_cnt' => 0, 'due_cnt' => 0, 'due_amt' => 0];
$totalFeeVouchers = (int)($feeClearRow['total_cnt'] ?? 0);
$dueFeeCount = (int)($feeClearRow['due_cnt'] ?? 0);
$dueFeeAmount = (float)($feeClearRow['due_amt'] ?? 0);

$idCourseStmt = $idDb->prepare("SELECT course_code, course_name, credit FROM student_courses WHERE student_id = ? ORDER BY course_code ASC");
$idCourseStmt->execute([(int)$student['id']]);
$idCardCourses = $idCourseStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="sp-main-container">

  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0;" class="no-print">
    <div>
      <h2 style="margin:0 0 0.3rem; font-size:1.45rem; font-weight:700; color:#0f172a;">
        <i class="fas fa-id-card" style="color:var(--sp-primary);"></i> Digital Student Identity Card &amp; Admit Card
      </h2>
      <p style="margin:0; font-size:0.88rem; color:#64748b;">
        Official Rabindra Maitree University Identification Document
      </p>
    </div>

    <div>
      <button type="button" onclick="window.print()" class="sp-btn-back-website" style="background:#1e73be; color:#fff; border-color:#1e73be; cursor:pointer;">
        <i class="fas fa-print"></i> Print ID Card / Admit Card
      </button>
    </div>
  </div>

  <!-- Digital ID Cards Container (Front & Back) -->
  <div style="display:flex; justify-content:center; gap:2.5rem; flex-wrap:wrap; margin-bottom:3rem;">
    
    <!-- ID Card Front -->
    <div style="width:100%; max-width:340px; min-height:560px; background:linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border:2px solid #cbd5e1; border-radius:16px; box-shadow:0 12px 30px rgba(0,0,0,0.12); position:relative; overflow:hidden; display:flex; flex-direction:column; text-align:center;">
      <!-- Card Top Header -->
      <div style="background:linear-gradient(135deg, #1e73be 0%, #16568c 100%); color:#ffffff; padding:1.25rem 1rem 1rem; border-bottom:3px solid #f59e0b;">
        <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="RMU Monogram" style="width:48px; height:48px; border-radius:50%; border:2px solid #fff; margin-bottom:0.25rem;" onerror="this.src='<?= APP_URL ?>/assets/images/logo.svg'">
        <h3 style="margin:0; font-size:0.95rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">Rabindra Maitree University</h3>
        <small style="font-size:0.7rem; opacity:0.9;">Kushtia, Bangladesh</small>
      </div>

      <!-- Student Photo -->
      <div style="padding:1.25rem 1rem 0.5rem; display:flex; flex-direction:column; align-items:center;">
        <div style="width:110px; height:120px; border-radius:8px; border:2px solid #1e73be; background:#eff6ff; overflow:hidden; margin-bottom:0.75rem; box-shadow:0 4px 8px rgba(0,0,0,0.08);">
          <img src="<?= get_student_avatar_url($student) ?>" alt="<?= e($student['full_name']) ?>" style="width:100%; height:100%; object-fit:cover;">
        </div>

        <h4 style="margin:0 0 0.15rem; font-size:1.15rem; font-weight:800; color:#0f172a;"><?= e($student['full_name']) ?></h4>
        <span style="font-size:0.82rem; font-weight:700; color:var(--sp-primary); font-family:monospace;"><?= e($student['student_id']) ?></span>
      </div>

      <!-- Student Details -->
      <div style="padding:0.5rem 1.25rem; font-size:0.78rem; text-align:left; color:#334155; line-height:1.45; flex:1; min-width:0;">
        <?php if ($idCardAppId !== ''): ?>
          <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.35rem; border-bottom:1px solid #f1f5f9; padding:3px 0;">
            <span style="color:#64748b;">Application ID:</span>
            <strong style="font-family:monospace; color:#0f172a;"><?= e($idCardAppId) ?></strong>
          </div>
        <?php endif; ?>
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.35rem; border-bottom:1px solid #f1f5f9; padding:3px 0;">
          <span style="color:#64748b;">Admission Roll:</span>
          <strong style="font-family:monospace; color:#0f172a;"><?= e($admitRollNo) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.35rem; border-bottom:1px solid #f1f5f9; padding:3px 0;">
          <span style="color:#64748b;">Program:</span>
          <strong style="overflow-wrap:anywhere;"><?= e($student['program_name'] ?? '—') ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.35rem; border-bottom:1px solid #f1f5f9; padding:3px 0;">
          <span style="color:#64748b;">Department:</span>
          <strong style="overflow-wrap:anywhere;"><?= e($student['department_name'] ?? '—') ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.35rem; border-bottom:1px solid #f1f5f9; padding:3px 0;">
          <span style="color:#64748b;">Academic Session:</span>
          <strong><?= e($idCardSession) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.35rem; border-bottom:1px solid #f1f5f9; padding:3px 0;">
          <span style="color:#64748b;">Course Duration:</span>
          <strong><?= e($idCardDurLabel) ?></strong>
        </div>
        <?php if ($idCardPassingYear !== ''): ?>
          <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.35rem; border-bottom:1px solid #f1f5f9; padding:3px 0;">
            <span style="color:#64748b;">Passing Year:</span>
            <strong><?= e($idCardPassingYear) ?></strong>
          </div>
        <?php endif; ?>
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.35rem; border-bottom:1px solid #f1f5f9; padding:3px 0;">
          <span style="color:#64748b;">Blood Group:</span>
          <strong style="color:#dc2626;"><?= e($student['blood_group'] ?: 'N/A') ?></strong>
        </div>
      </div>

      <!-- Card Bottom Bar -->
      <div style="background:#0f172a; color:#ffffff; padding:0.5rem; font-size:0.72rem; font-weight:600; letter-spacing:0.5px;">
        STUDENT IDENTITY CARD • VALID TILL <?= (int)$validTillYear ?>
      </div>
    </div>

    <!-- ID Card Back -->
    <div style="width:100%; max-width:340px; min-height:560px; background:#ffffff; border:2px solid #cbd5e1; border-radius:16px; box-shadow:0 12px 30px rgba(0,0,0,0.12); position:relative; overflow:hidden; display:flex; flex-direction:column; padding:1.5rem; font-size:0.8rem; color:#334155; line-height:1.5;">
      <h4 style="margin:0 0 0.8rem; font-size:0.9rem; font-weight:800; color:#0f172a; text-transform:uppercase; border-bottom:2px solid #1e73be; padding-bottom:0.4rem;">
        Terms &amp; Instructions
      </h4>

      <ul style="padding-left:1.2rem; margin:0 0 1rem; font-size:0.76rem; color:#475569;">
        <li>This card is non-transferable and must be presented upon campus entry, library access, and examinations.</li>
        <li>Loss of this card must be immediately reported to the Registrar Office.</li>
        <li>Cardholder must adhere to university discipline and student code of conduct.</li>
      </ul>

      <div style="margin-top:auto; text-align:center; padding-top:1rem; border-top:1px dashed #cbd5e1;">
        <!-- Barcode Simulation -->
        <div style="font-family:monospace; letter-spacing:4px; font-weight:700; font-size:1.3rem; color:#0f172a; margin-bottom:0.25rem;">
          ||||| | |||| |||| ||| |||||
        </div>
        <small style="font-family:monospace; color:#64748b;"><?= e($student['student_id']) ?></small>

        <div style="margin-top:1.25rem; display:flex; justify-content:space-between; align-items:flex-end;">
          <div>
            <small style="display:block; color:#64748b; font-size:0.68rem;">Emergency Contact</small>
            <strong style="font-size:0.75rem;">+8801302991188</strong>
          </div>
          <div>
            <div style="border-bottom:1px solid #0f172a; width:100px; margin-bottom:3px;"></div>
            <small style="color:#0f172a; font-weight:700; font-size:0.7rem;">Registrar</small>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Examination Admit Card Section -->
  <div class="sp-admit-card" style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.04); margin-bottom:3rem; max-width:100%; min-width:0; overflow-x:clip;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.85rem; border-bottom:2px solid #0f172a; padding-bottom:1rem; margin-bottom:1.5rem;">
      <div style="display:flex; align-items:center; gap:1rem;">
        <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="RMU Monogram" style="width:55px; height:55px; border-radius:6px;" onerror="this.src='<?= APP_URL ?>/assets/images/logo.svg'">
        <div>
          <h2 style="margin:0; font-size:1.3rem; font-weight:800; color:#0f172a;">Rabindra Maitree University</h2>
          <small style="color:#475569;">Controller of Examinations • Kushtia, Bangladesh</small>
        </div>
      </div>
      <div style="text-align:right;">
        <span style="background:#eff6ff; border:1px solid #bfdbfe; color:#1e73be; padding:4px 12px; border-radius:20px; font-weight:700; font-size:0.85rem; text-transform:uppercase;">
          EXAM ADMIT CARD
        </span>
        <div style="font-size:0.78rem; color:#64748b; margin-top:3px;">Semester Final: <strong><?= e($idCardSession) ?></strong></div>
      </div>
    </div>

    <!-- Student details grid for Admit Card -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 210px), 1fr)); gap:1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1.2rem; margin-bottom:1.5rem; font-size:0.88rem;">
      <div>
        <div><span style="color:#64748b;">Name:</span> <strong><?= e($student['full_name']) ?></strong></div>
        <div style="margin-top:3px;"><span style="color:#64748b;">Student ID:</span> <strong style="font-family:monospace; color:var(--sp-primary);"><?= e($student['student_id']) ?></strong></div>
        <?php if ($idCardAppId !== ''): ?>
          <div style="margin-top:3px;"><span style="color:#64748b;">Application ID:</span> <strong style="font-family:monospace;"><?= e($idCardAppId) ?></strong></div>
        <?php endif; ?>
      </div>
      <div>
        <div><span style="color:#64748b;">Program:</span> <strong><?= e($student['program_name'] ?? '—') ?></strong></div>
        <div style="margin-top:3px;"><span style="color:#64748b;">Department:</span> <strong><?= e($student['department_name'] ?? '—') ?></strong></div>
      </div>
      <div>
        <div><span style="color:#64748b;">Admission / Exam Roll:</span> <strong style="font-family:monospace; color:#0f172a;"><?= e($admitRollNo) ?></strong></div>
        <div style="margin-top:3px;"><span style="color:#64748b;">Fee Clearance:</span>
          <?php if ($dueFeeCount > 0): ?>
            <span style="color:#b45309; font-weight:700;"><i class="fas fa-exclamation-triangle"></i> DUE PENDING (৳<?= number_format($dueFeeAmount, 0) ?>)</span>
          <?php elseif ($totalFeeVouchers === 0): ?>
            <span style="color:#1e73be; font-weight:700;"><i class="fas fa-info-circle"></i> NO DUES RECORDED</span>
          <?php else: ?>
            <span style="color:#16a34a; font-weight:700;"><i class="fas fa-check-circle"></i> CLEARED &amp; VERIFIED</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Eligible Courses for Exam -->
    <h4 style="margin:0 0 0.8rem; font-size:0.95rem; font-weight:700; color:#0f172a;">Permitted Examination Subjects:</h4>
    <div style="overflow-x:auto; max-width:100%; -webkit-overflow-scrolling:touch; margin-bottom:2rem;">
    <table class="sp-table" style="border:1px solid #cbd5e1; margin-bottom:0;">
      <thead>
        <tr>
          <th>Course Code</th>
          <th>Course Title</th>
          <th>Credit</th>
          <th>Invigilator Signature</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($idCardCourses)): ?>
          <tr>
            <td colspan="4" style="text-align:center; padding:1.25rem; color:#64748b;">
              No examination subjects are registered for the active semester yet.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($idCardCourses as $idc): ?>
            <tr>
              <td><strong style="font-family:monospace;"><?= e($idc['course_code']) ?></strong></td>
              <td><?= e($idc['course_name']) ?></td>
              <td><?= number_format((float)$idc['credit'], 1) ?></td>
              <td>___________________</td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:1rem; padding-top:1.5rem;">
      <div>
        <small style="color:#64748b;">Issued on: <?= date('F d, Y') ?></small>
      </div>
      <div style="text-align:center;">
        <div style="border-bottom:1px solid #0f172a; width:160px; margin-bottom:4px;"></div>
        <strong style="font-size:0.82rem; color:#0f172a;">Controller of Examinations</strong>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/student-footer.php'; ?>

