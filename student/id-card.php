<?php
/**
 * Student Portal - Digital Student ID Card & Exam Admit Card
 */
$portalTitle = 'Digital ID & Admit Card';
require_once __DIR__ . '/includes/student-header.php';
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
    <div style="width:340px; height:530px; background:linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border:2px solid #cbd5e1; border-radius:16px; box-shadow:0 12px 30px rgba(0,0,0,0.12); position:relative; overflow:hidden; display:flex; flex-direction:column; text-align:center;">
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
      <div style="padding:0.5rem 1.5rem; font-size:0.8rem; text-align:left; color:#334155; line-height:1.5; flex:1;">
        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:3px 0;">
          <span style="color:#64748b;">Program:</span>
          <strong><?= e($student['program_name'] ?? 'BSc in CSE') ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:3px 0;">
          <span style="color:#64748b;">Department:</span>
          <strong><?= e($student['department_name'] ?? 'CSE') ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:3px 0;">
          <span style="color:#64748b;">Batch:</span>
          <strong><?= e($student['batch']) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding:3px 0;">
          <span style="color:#64748b;">Blood Group:</span>
          <strong style="color:#dc2626;"><?= e($student['blood_group'] ?? 'B+') ?></strong>
        </div>
      </div>

      <!-- Card Bottom Bar -->
      <div style="background:#0f172a; color:#ffffff; padding:0.5rem; font-size:0.72rem; font-weight:600; letter-spacing:0.5px;">
        STUDENT IDENTITY CARD • VALID TILL 2028
      </div>
    </div>

    <!-- ID Card Back -->
    <div style="width:340px; height:530px; background:#ffffff; border:2px solid #cbd5e1; border-radius:16px; box-shadow:0 12px 30px rgba(0,0,0,0.12); position:relative; overflow:hidden; display:flex; flex-direction:column; padding:1.5rem; font-size:0.8rem; color:#334155; line-height:1.5;">
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
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:2rem; box-shadow:0 4px 15px rgba(0,0,0,0.04); margin-bottom:3rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #0f172a; padding-bottom:1rem; margin-bottom:1.5rem;">
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
        <div style="font-size:0.78rem; color:#64748b; margin-top:3px;">Semester Final: <strong>Fall 2026</strong></div>
      </div>
    </div>

    <!-- Student details grid for Admit Card -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1.2rem; margin-bottom:1.5rem; font-size:0.88rem;">
      <div>
        <div><span style="color:#64748b;">Name:</span> <strong><?= e($student['full_name']) ?></strong></div>
        <div style="margin-top:3px;"><span style="color:#64748b;">Student ID:</span> <strong style="font-family:monospace; color:var(--sp-primary);"><?= e($student['student_id']) ?></strong></div>
      </div>
      <div>
        <div><span style="color:#64748b;">Program:</span> <strong><?= e($student['program_name'] ?? 'BSc in CSE') ?></strong></div>
        <div style="margin-top:3px;"><span style="color:#64748b;">Department:</span> <strong><?= e($student['department_name'] ?? 'CSE') ?></strong></div>
      </div>
      <div>
        <div><span style="color:#64748b;">Exam Roll:</span> <strong style="font-family:monospace;">2601-<?= substr($student['student_id'], -3) ?></strong></div>
        <div style="margin-top:3px;"><span style="color:#64748b;">Fee Clearance:</span> <span style="color:#16a34a; font-weight:700;"><i class="fas fa-check-circle"></i> VERIFIED &amp; APPROVED</span></div>
      </div>
    </div>

    <!-- Eligible Courses for Exam -->
    <h4 style="margin:0 0 0.8rem; font-size:0.95rem; font-weight:700; color:#0f172a;">Permitted Examination Subjects:</h4>
    <table class="sp-table" style="border:1px solid #cbd5e1; margin-bottom:2rem;">
      <thead>
        <tr>
          <th>Course Code</th>
          <th>Course Title</th>
          <th>Credit</th>
          <th>Invigilator Signature</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong style="font-family:monospace;">CSE 201</strong></td>
          <td>Data Structures &amp; Algorithms</td>
          <td>3.0</td>
          <td>___________________</td>
        </tr>
        <tr>
          <td><strong style="font-family:monospace;">CSE 202</strong></td>
          <td>Data Structures &amp; Algorithms Lab</td>
          <td>1.5</td>
          <td>___________________</td>
        </tr>
        <tr>
          <td><strong style="font-family:monospace;">CSE 203</strong></td>
          <td>Object-Oriented Programming (Java)</td>
          <td>3.0</td>
          <td>___________________</td>
        </tr>
        <tr>
          <td><strong style="font-family:monospace;">CSE 204</strong></td>
          <td>Digital Logic Design &amp; Microprocessors</td>
          <td>3.0</td>
          <td>___________________</td>
        </tr>
        <tr>
          <td><strong style="font-family:monospace;">MAT 201</strong></td>
          <td>Discrete Mathematics &amp; Graph Theory</td>
          <td>3.0</td>
          <td>___________________</td>
        </tr>
      </tbody>
    </table>

    <div style="display:flex; justify-content:space-between; align-items:flex-end; padding-top:1.5rem;">
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

