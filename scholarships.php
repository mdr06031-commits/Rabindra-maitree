<?php
/**
 * Scholarships & Financial Aid at Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Scholarships & Waivers';
$breadcrumbTitle = 'Scholarships, Trust Funds &amp; Financial Waivers';
$breadcrumbs = [['title' => 'Admission', 'url' => APP_URL . '/admissions.php'], ['title' => 'Scholarships']];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 1000px;">
    <div class="section-header">
      <span class="section-subtitle">Student Welfare &amp; Financial Assistance</span>
      <h2 class="section-title">Scholarships &amp; Endowed Trust Funds</h2>
      <div class="section-line"></div>
      <p style="color: var(--rmu-muted); margin-top: 0.8rem; font-size: 0.95rem;">
        Rabindra Maitree University firmly believes that economic constraints should never obstruct academic excellence. We offer a comprehensive portfolio of merit waivers and philanthropic endowed scholarships.
      </p>
    </div>

    <!-- Verified Authentic Trust Funds -->
    <div style="display: flex; flex-direction: column; gap: 1.8rem; margin-bottom: 3.5rem;">
      <!-- Fund 1: Mansur Ali and Atharunnesa -->
      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-lg); padding: 2rem; border-left: 5px solid var(--rmu-accent); box-shadow: var(--rmu-shadow-sm);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem;">
          <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--rmu-dark); margin: 0;">
            Mansur Ali &amp; Atharunnesa Trust Scholarship
          </h3>
          <span class="notice-category-badge badge-scholarship">Endowed Trust Fund</span>
        </div>
        <p style="color: var(--rmu-muted); line-height: 1.7; margin-bottom: 1rem;">
          An endowed philanthropic fund dedicated to providing financial grants and tuition assistance to meritorious but financially challenged students admitted to RMU. Beneficiaries are selected annually by a university trustee panel based on academic performance and economic need.
        </p>
        <a href="<?= APP_URL ?>/storage/downloads/rmu_scholarship_application_form.pdf" download class="btn-primary" style="padding: 0.45rem 1rem; font-size: 0.85rem;">
          <i class="fas fa-file-pdf"></i> Download Trust Application Form
        </a>
      </div>

      <!-- Fund 2: Mohammad Fazli Hossain -->
      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-lg); padding: 2rem; border-left: 5px solid var(--rmu-primary); box-shadow: var(--rmu-shadow-sm);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem;">
          <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--rmu-dark); margin: 0;">
            Mohammad Fazli Hossain Memorial Trust Scholarship
          </h3>
          <span class="notice-category-badge badge-academic">Academic Excellence</span>
        </div>
        <p style="color: var(--rmu-muted); line-height: 1.7; margin-bottom: 1rem;">
          Established in memory of eminent educationists to recognize students who achieve top GPA standings across science, engineering, and social science faculties. Awards include cash stipends and semester fee exemptions.
        </p>
      </div>

      <!-- Fund 3: Hamim Khan -->
      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-lg); padding: 2rem; border-left: 5px solid var(--rmu-secondary); box-shadow: var(--rmu-shadow-sm);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem;">
          <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--rmu-dark); margin: 0;">
            Hamim Khan Memorial Cultural Award
          </h3>
          <span class="notice-category-badge" style="background:#e9d5ff; color:#6b21a8;">Cultural &amp; Literary Award</span>
        </div>
        <p style="color: var(--rmu-muted); line-height: 1.7; margin-bottom: 0;">
          Awarded annually to students demonstrating exceptional creative mastery in literature, music, fine arts, and cultural debate reflecting the humanist philosophy of Rabindranath Tagore.
        </p>
      </div>
    </div>

    <!-- General GPA Merit Waiver Matrix -->
    <div style="margin-bottom: 3.5rem;">
      <h3 style="font-size: 1.4rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 1rem;">
        Undergraduate GPA Merit Waiver Matrix
      </h3>
      <div class="table-responsive">
        <table class="rmu-table">
          <thead>
            <tr>
              <th>Combined SSC &amp; HSC Results</th>
              <th>Tuition Fee Waiver Percentage</th>
              <th>Retention Requirement</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Golden GPA 5.00 in both SSC &amp; HSC</strong></td>
              <td><strong style="color: var(--rmu-primary);">100% Tuition Waiver</strong></td>
              <td>Maintain minimum SGPA 3.50 per semester</td>
            </tr>
            <tr>
              <td><strong>GPA 5.00 in both SSC &amp; HSC</strong></td>
              <td><strong style="color: var(--rmu-primary);">50% Tuition Waiver</strong></td>
              <td>Maintain minimum SGPA 3.25 per semester</td>
            </tr>
            <tr>
              <td><strong>GPA 4.50 – 4.99 in both examinations</strong></td>
              <td><strong style="color: var(--rmu-primary);">25% Tuition Waiver</strong></td>
              <td>Maintain minimum SGPA 3.00 per semester</td>
            </tr>
            <tr>
              <td><strong>Children of Freedom Fighters (UGC Quota)</strong></td>
              <td><strong style="color: var(--rmu-primary);">100% Full Free Tuition</strong></td>
              <td>Subject to verified Liberation War Ministry certification</td>
            </tr>
            <tr>
              <td><strong>Siblings / Female Students Special Concession</strong></td>
              <td><strong style="color: var(--rmu-primary);">10% – 15% Waiver</strong></td>
              <td>Applicable throughout duration of continuous enrollment</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

