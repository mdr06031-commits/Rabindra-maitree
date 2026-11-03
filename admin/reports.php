<?php
/**
 * Admin Panel: Reports & Analytics
 * Rabindra Maitree University (RMU)
 * Academic Performance, Intake Statistics & Financial Analytics
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Reports & Analytics';
$db = getDb();

// 1. Admission Intake Funnel
$totalApps = (int)$db->query("SELECT COUNT(*) FROM admissions")->fetchColumn();
$paidApps = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE payment_status = 'Paid'")->fetchColumn();
$acceptedApps = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE status = 'Accepted'")->fetchColumn();
$rejectedApps = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE status = 'Rejected'")->fetchColumn();
$underReviewApps = (int)$db->query("SELECT COUNT(*) FROM admissions WHERE status = 'Under Review'")->fetchColumn();

// 2. Student Distribution by Department
$deptDistribution = $db->query("
    SELECT d.name_en as dept_name, COUNT(s.id) as student_count, AVG(s.cgpa) as avg_cgpa
    FROM departments d
    LEFT JOIN students s ON s.department_id = d.id
    GROUP BY d.id, d.name_en
    ORDER BY student_count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Academic CGPA Banding
$cgpaOutstanding = (int)$db->query("SELECT COUNT(*) FROM students WHERE cgpa >= 3.75")->fetchColumn();
$cgpaVeryGood = (int)$db->query("SELECT COUNT(*) FROM students WHERE cgpa >= 3.00 AND cgpa < 3.75")->fetchColumn();
$cgpaGood = (int)$db->query("SELECT COUNT(*) FROM students WHERE cgpa >= 2.50 AND cgpa < 3.00")->fetchColumn();
$cgpaConditional = (int)$db->query("SELECT COUNT(*) FROM students WHERE cgpa >= 2.00 AND cgpa < 2.50")->fetchColumn();
$cgpaProbation = (int)$db->query("SELECT COUNT(*) FROM students WHERE cgpa < 2.00 AND cgpa > 0")->fetchColumn();

// 4. Financial Revenue Analytics
$totalAdmissionRev = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM admissions WHERE payment_status = 'Paid'")->fetchColumn();
$totalTuitionRev = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM student_fees WHERE status = 'Paid'")->fetchColumn();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;" class="no-print">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem;">
      <i class="fas fa-chart-line" style="color:var(--rmu-primary);"></i> Reports &amp; Analytics
    </h2>
    <p style="color:#64748b; font-size:0.88rem; margin:0;">
      Academic Performance, Intake Conversions &amp; Institutional Metric Evaluation
    </p>
  </div>

  <button type="button" onclick="window.print()" class="btn-primary" style="background:#1e73be; padding:0.55rem 1.1rem; font-size:0.86rem;">
    <i class="fas fa-print"></i> Print Executive Summary
  </button>
</div>

<!-- Admission Conversion Funnel Cards -->
<h4 style="font-size:1.05rem; font-weight:800; color:#0f2c59; margin-bottom:0.85rem;">
  <i class="fas fa-filter" style="color:var(--rmu-primary);"></i> Admission Intake Conversion Funnel
</h4>
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:2rem;">
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:1.2rem; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="font-size:1.8rem; font-weight:800; color:#0f172a;"><?= $totalApps ?></div>
    <div style="font-size:0.8rem; color:#64748b; font-weight:700; text-transform:uppercase; margin-top:3px;">Total Applications</div>
  </div>
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:1.2rem; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="font-size:1.8rem; font-weight:800; color:#15803d;"><?= $paidApps ?></div>
    <div style="font-size:0.8rem; color:#64748b; font-weight:700; text-transform:uppercase; margin-top:3px;">৳150 Paid (Verified)</div>
  </div>
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:1.2rem; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="font-size:1.8rem; font-weight:800; color:#1e40af;"><?= $underReviewApps ?></div>
    <div style="font-size:0.8rem; color:#64748b; font-weight:700; text-transform:uppercase; margin-top:3px;">Under Academic Review</div>
  </div>
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:1.2rem; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="font-size:1.8rem; font-weight:800; color:#16a34a;"><?= $acceptedApps ?></div>
    <div style="font-size:0.8rem; color:#64748b; font-weight:700; text-transform:uppercase; margin-top:3px;">Accepted &amp; Enrolled</div>
  </div>
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:1.2rem; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="font-size:1.8rem; font-weight:800; color:#dc2626;"><?= $rejectedApps ?></div>
    <div style="font-size:0.8rem; color:#64748b; font-weight:700; text-transform:uppercase; margin-top:3px;">Rejected / Ineligible</div>
  </div>
</div>

<!-- 2-Column: Department Enrollment (Left) + CGPA Distribution (Right) -->
<div style="display:grid; grid-template-columns:1.5fr 1fr; gap:1.75rem; margin-bottom:2rem; align-items:start;">
  <!-- Department Distribution Table -->
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05);">
    <div style="padding:1rem 1.25rem; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
      <strong style="color:#0f172a; font-size:0.95rem;"><i class="fas fa-university" style="color:var(--rmu-primary);"></i> Student Enrollment by Department</strong>
    </div>
    <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.88rem;">
      <thead>
        <tr>
          <th>Department Name</th>
          <th style="text-align:center;">Enrolled Students</th>
          <th style="text-align:right;">Average CGPA</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($deptDistribution as $d): ?>
          <tr>
            <td><strong><?= e($d['dept_name']) ?></strong></td>
            <td style="text-align:center;">
              <span style="background:#eff6ff; color:#1e40af; padding:2px 10px; border-radius:12px; font-weight:700; font-size:0.82rem;">
                <?= (int)$d['student_count'] ?>
              </span>
            </td>
            <td style="text-align:right; font-weight:700; color:#15803d;">
              <?= $d['avg_cgpa'] ? number_format((float)$d['avg_cgpa'], 2) : '—' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Academic CGPA Banding -->
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; box-shadow:0 1px 4px rgba(0,0,0,0.05);">
    <div style="border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem; margin-bottom:1rem;">
      <strong style="color:#0f172a; font-size:0.95rem;"><i class="fas fa-award" style="color:#15803d;"></i> Academic CGPA Standing Distribution</strong>
    </div>
    
    <div style="display:flex; flex-direction:column; gap:0.75rem; font-size:0.88rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 10px; background:#f0fdf4; border-radius:6px;">
        <span><strong style="color:#166534;">Outstanding / First Class</strong> (&ge; 3.75)</span>
        <strong style="color:#15803d; font-size:1.1rem;"><?= $cgpaOutstanding ?></strong>
      </div>
      <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 10px; background:#eff6ff; border-radius:6px;">
        <span><strong style="color:#1e40af;">Very Good Standing</strong> (3.00 – 3.74)</span>
        <strong style="color:#1e40af; font-size:1.1rem;"><?= $cgpaVeryGood ?></strong>
      </div>
      <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 10px; background:#f8fafc; border-radius:6px;">
        <span><strong style="color:#334155;">Satisfactory / Pass</strong> (2.50 – 2.99)</span>
        <strong style="color:#334155; font-size:1.1rem;"><?= $cgpaGood ?></strong>
      </div>
      <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 10px; background:#fffbeb; border-radius:6px;">
        <span><strong style="color:#b45309;">Conditional Pass</strong> (2.00 – 2.49)</span>
        <strong style="color:#b45309; font-size:1.1rem;"><?= $cgpaConditional ?></strong>
      </div>
      <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 10px; background:#fef2f2; border-radius:6px;">
        <span><strong style="color:#dc2626;">Academic Warning</strong> (&lt; 2.00)</span>
        <strong style="color:#dc2626; font-size:1.1rem;"><?= $cgpaProbation ?></strong>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
