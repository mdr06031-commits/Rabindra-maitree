<?php
/**
 * Tuition & Fees Schedule at Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Tuition & Fees Structure';
$breadcrumbTitle = 'Tuition Fees &amp; Academic Expenses';
$breadcrumbs = [['title' => 'Admission', 'url' => APP_URL . '/admissions.php'], ['title' => 'Tuition Fees']];

$db = getDb();
$programs = $db->query("
    SELECT p.*, d.name_en AS dept_name, f.name_en AS faculty_name 
    FROM programs p 
    JOIN departments d ON p.department_id = d.id 
    JOIN faculties f ON d.faculty_id = f.id 
    WHERE p.is_active = 1 
    ORDER BY p.degree_level DESC, p.sort_order ASC
")->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 1050px;">
    <div class="section-header">
      <span class="section-subtitle">Academic Expenses Schedule</span>
      <h2 class="section-title">Tuition &amp; Fees Schedule (Fall 2026)</h2>
      <div class="section-line"></div>
      <p style="color: var(--rmu-muted); margin-top: 0.8rem;">
        Rabindra Maitree University adheres to a transparent and affordable fee structure in accordance with UGC guidelines. Flexible installment plans are available for all semesters.
      </p>
    </div>

    <!-- Fee Table -->
    <div class="table-responsive" style="box-shadow: var(--rmu-shadow-sm); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); margin-bottom: 3rem;">
      <table class="rmu-table">
        <thead>
          <tr>
            <th>Program Title</th>
            <th>Faculty &amp; Dept</th>
            <th>Duration</th>
            <th>Total Credits</th>
            <th>Estimated Total Fee</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($programs as $prog): ?>
            <tr>
              <td>
                <strong style="color: var(--rmu-dark); display: block;"><?= e($prog['title_en']) ?></strong>
              </td>
              <td>
                <div style="font-size: 0.85rem; color: var(--rmu-dark);"><?= e($prog['dept_name']) ?></div>
                <small style="color: var(--rmu-muted);"><?= e($prog['degree_level']) ?></small>
              </td>
              <td style="font-size: 0.88rem;"><?= e($prog['duration']) ?></td>
              <td style="font-size: 0.88rem; text-align: center;"><strong><?= (int)$prog['total_credits'] ?></strong></td>
              <td><strong style="color: var(--rmu-primary); font-size: 0.95rem;"><?= e($prog['tuition_fee'] ?? 'Standard Fee') ?></strong></td>
              <td>
                <a href="<?= APP_URL ?>/admission-form.php?subject=<?= urlencode($prog['title_en']) ?>" class="btn-primary" style="padding: 0.35rem 0.8rem; font-size: 0.8rem; background: var(--rmu-accent);">
                  Apply
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Additional Fee Policies & Waivers Note -->
    <div class="grid-2" style="gap: 2rem;">
      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); padding: 1.75rem; border: 1px solid var(--rmu-border);">
        <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.8rem;">
          <i class="fas fa-money-check-alt" style="color: var(--rmu-primary);"></i> Semester Payment Guidelines
        </h4>
        <ul style="color: var(--rmu-muted); font-size: 0.9rem; line-height: 1.7; padding-left: 1.2rem;">
          <li>Tuition is payable in 3 convenient installments per semester (at registration, mid-term, and semester final).</li>
          <li>Admission fee is a one-time non-refundable charge payable during initial registration.</li>
          <li>Semester laboratory, seminar library, and examination fees are included in the published installment schedules.</li>
        </ul>
      </div>

      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); padding: 1.75rem; border: 1px solid var(--rmu-border);">
        <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.8rem;">
          <i class="fas fa-percentage" style="color: var(--rmu-primary);"></i> Tuition Waivers Available
        </h4>
        <ul style="color: var(--rmu-muted); font-size: 0.9rem; line-height: 1.7; padding-left: 1.2rem;">
          <li><strong>GPA 5.00 in SSC &amp; HSC:</strong> Up to 100% tuition waiver for meritorious candidates.</li>
          <li><strong>Children of Freedom Fighters:</strong> 100% full-tuition scholarship (subject to quota limits).</li>
          <li><strong>Siblings / Female Students:</strong> Special 10%–20% tuition concession across all undergraduate streams.</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

