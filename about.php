<?php
/**
 * About Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'About the University';
$breadcrumbTitle = 'About Rabindra Maitree University';
$breadcrumbs = [['title' => 'About Us']];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container">
    <div class="grid-2" style="align-items: start; gap: 3rem;">
      <div>
        <span class="section-subtitle">Since 2015 • Kushtia, Bangladesh</span>
        <h2 class="section-title" style="text-align: left; margin-bottom: 1.25rem;">A Dream of Higher Education &amp; Cultural Renaissance</h2>
        <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-bottom: 1.5rem;"></div>

        <p style="color: var(--rmu-muted); margin-bottom: 1.2rem; line-height: 1.8;">
          <strong>Rabindra Maitree University</strong> was established in 2015 under the Private University Act 2010 of Bangladesh, with prior approval from the Ministry of Education and the University Grants Commission (UGC).
        </p>

        <p style="color: var(--rmu-muted); margin-bottom: 1.2rem; line-height: 1.8;">
          Named in honor of Nobel laureate Rabindranath Tagore, who spent significant years of his creative life in Shilaidaha, Kushtia, the university represents a harmonious fusion of timeless humanism, cultural heritage, and modern scientific inquiry.
        </p>

        <p style="color: var(--rmu-muted); margin-bottom: 1.5rem; line-height: 1.8;">
          Our main campus in Kushtia is equipped with modern multimedia lecture halls, computer science laboratories, electrical engineering workshops, biology and agricultural science laboratories, and an extensive central library.
        </p>

        <div style="background: var(--rmu-bg-light); border-left: 4px solid var(--rmu-primary); padding: 1.25rem 1.5rem; margin-bottom: 1.8rem; border-radius: 0 var(--rmu-radius-sm) var(--rmu-radius-sm) 0;">
          <h4 style="color: var(--rmu-primary); margin-bottom: 0.4rem; font-size: 1.1rem;">University Motto</h4>
          <p style="color: var(--rmu-dark); font-style: italic; margin: 0; font-size: 1.05rem;">
            "Center of Higher Education and Humane Awakening — This University is our dream."
          </p>
        </div>

        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
          <a href="<?= APP_URL ?>/mission-vision.php" class="btn-primary"><i class="fas fa-compass"></i> Vision &amp; Mission</a>
          <a href="<?= APP_URL ?>/leadership.php" style="padding: 0.75rem 1.4rem; border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-sm); font-weight: 600; color: var(--rmu-dark); display: inline-flex; align-items: center; gap: 0.4rem;">
            <i class="fas fa-users"></i> University Leadership
          </a>
        </div>
      </div>

      <!-- Quick Facts Card -->
      <div>
        <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-lg); padding: 2.2rem; box-shadow: var(--rmu-shadow-sm);">
          <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 1.25rem; border-bottom: 2px solid var(--rmu-border); padding-bottom: 0.8rem;">
            <i class="fas fa-info-circle" style="color: var(--rmu-primary);"></i> Institutional Overview
          </h3>

          <ul style="list-style: none; padding: 0;">
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between;">
              <strong style="color: var(--rmu-dark);">Institution Name:</strong>
              <span style="color: var(--rmu-muted); text-align: right;">Rabindra Maitree University</span>
            </li>
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between;">
              <strong style="color: var(--rmu-dark);">Campus Location:</strong>
              <span style="color: var(--rmu-muted);">Kushtia, Bangladesh</span>
            </li>
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between;">
              <strong style="color: var(--rmu-dark);">Year Established:</strong>
              <span style="color: var(--rmu-muted);">2015</span>
            </li>
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between;">
              <strong style="color: var(--rmu-dark);">Regulatory Authority:</strong>
              <span style="color: var(--rmu-muted);">UGC &amp; Ministry of Education</span>
            </li>
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between;">
              <strong style="color: var(--rmu-dark);">Location:</strong>
              <span style="color: var(--rmu-muted);">Kushtia, Bangladesh</span>
            </li>
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between;">
              <strong style="color: var(--rmu-dark);">Faculties:</strong>
              <span style="color: var(--rmu-muted);">4 Academic Faculties</span>
            </li>
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between;">
              <strong style="color: var(--rmu-dark);">Academic Departments:</strong>
              <span style="color: var(--rmu-muted);">11 Departments</span>
            </li>
            <li style="padding: 0.75rem 0; display: flex; justify-content: space-between;">
              <strong style="color: var(--rmu-dark);">Degrees Offered:</strong>
              <span style="color: var(--rmu-muted);">Undergraduate &amp; Graduate</span>
            </li>
          </ul>

          <div style="margin-top: 1.5rem; text-align: center;">
            <a href="<?= APP_URL ?>/admission-form.php" class="btn-primary" style="width: 100%; justify-content: center; background: var(--rmu-accent);">
              <i class="fas fa-file-signature"></i> Apply for Admission Online
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

