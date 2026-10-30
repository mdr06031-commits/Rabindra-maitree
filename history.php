<?php
/**
 * History of Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Brief History of University';
$breadcrumbTitle = 'History of Rabindra Maitree University';
$breadcrumbs = [['title' => 'About Us', 'url' => APP_URL . '/about.php'], ['title' => 'History']];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 900px;">
    <span class="section-subtitle">Since 2015</span>
    <h2 class="section-title" style="text-align: left; margin-bottom: 1.5rem;">Brief History of Rabindra Maitree University</h2>
    <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-bottom: 2rem;"></div>

    <div style="color: var(--rmu-dark); line-height: 1.8; font-size: 1.02rem;">
      <p style="margin-bottom: 1.4rem;">
        Rabindra Maitree University was established in 2015 in Kushtia, Bangladesh, by a dedicated consortium of educators, cultural activists, and philanthropists. Kushtia holds historic national significance as the cultural epicenter of South-Western Bangladesh, enriched by the eternal presence of Nobel laureate Rabindranath Tagore and the mystic bard Fakir Lalon Shah.
      </p>

      <h3 style="color: var(--rmu-primary); margin: 2rem 0 1rem; font-size: 1.4rem;">The Genesis &amp; Foundation</h3>
      <p style="margin-bottom: 1.4rem;">
        Prior to 2015, high school and college graduates from Kushtia, Meherpur, Chuadanga, and Jhenaidah faced significant geographical hurdles accessing specialized higher education in engineering, business, and applied sciences. To bridge this divide, Rabindra Maitree University was chartered under the Private University Act 2010.
      </p>

      <h3 style="color: var(--rmu-primary); margin: 2rem 0 1rem; font-size: 1.4rem;">Growth &amp; Expansion</h3>
      <p style="margin-bottom: 1.4rem;">
        Beginning with fundamental undergraduate disciplines in English, Business Administration, and Computer Science, the university steadily expanded its academic footprint. Subsequent additions included Electrical &amp; Electronic Engineering (EEE), Information &amp; Communication Technology (ICT), Microbiology, Agriculture, and Physical Education (MPED).
      </p>

      <h3 style="color: var(--rmu-primary); margin: 2rem 0 1rem; font-size: 1.4rem;">Commitment to Accessible Excellence</h3>
      <p style="margin-bottom: 1.8rem;">
        Today, Rabindra Maitree University stands as a vital pillar of progressive knowledge dissemination in Kushtia, continually introducing scholarship trust funds (such as the Mansur Ali and Atharunnesa Trust Scholarship and Mohammad Fazli Hossain Memorial Scholarship) to ensure that financial hardship never prevents deserving youth from attaining an academic degree.
      </p>

      <div style="margin-top: 2.5rem; padding: 1.5rem; background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); border-left: 4px solid var(--rmu-primary);">
        <h4 style="margin-bottom: 0.5rem; color: var(--rmu-dark);">Explore Related Sections</h4>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.8rem;">
          <a href="<?= APP_URL ?>/mission-vision.php" class="card-link"><i class="fas fa-eye"></i> Vision &amp; Mission</a>
          <a href="<?= APP_URL ?>/faculties.php" class="card-link"><i class="fas fa-university"></i> Academic Faculties</a>
          <a href="<?= APP_URL ?>/admissions.php" class="card-link"><i class="fas fa-graduation-cap"></i> Admission Guidelines</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

