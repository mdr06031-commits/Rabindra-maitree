<?php
/**
 * Vision & Mission of Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Vision & Mission';
$breadcrumbTitle = 'Vision, Mission & Core Values';
$breadcrumbs = [['title' => 'About Us', 'url' => APP_URL . '/about.php'], ['title' => 'Vision & Mission']];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 1000px;">
    <div class="grid-2" style="gap: 2.5rem; margin-bottom: 3rem;">
      <!-- Vision Card -->
      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-lg); padding: 2.5rem; border-top: 5px solid var(--rmu-primary); box-shadow: var(--rmu-shadow-sm);">
        <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--rmu-primary-light); color: var(--rmu-primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1.25rem;">
          <i class="fas fa-eye"></i>
        </div>
        <h3 style="font-size: 1.5rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 1rem;">Our Vision</h3>
        <p style="color: var(--rmu-muted); line-height: 1.8;">
          To become a preeminent center of higher learning and research in Bangladesh, celebrated for cultivating academically competent, ethically upright, and culturally enlightened global citizens who contribute meaningfully to sustainable socio-economic development.
        </p>
      </div>

      <!-- Mission Card -->
      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-lg); padding: 2.5rem; border-top: 5px solid var(--rmu-secondary); box-shadow: var(--rmu-shadow-sm);">
        <div style="width: 50px; height: 50px; border-radius: 50%; background: #e0e7ff; color: var(--rmu-secondary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1.25rem;">
          <i class="fas fa-bullseye"></i>
        </div>
        <h3 style="font-size: 1.5rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 1rem;">Our Mission</h3>
        <ul style="color: var(--rmu-muted); line-height: 1.8; padding-left: 1.2rem;">
          <li style="margin-bottom: 0.5rem;">Deliver high-caliber, affordable higher education across science, technology, humanities, and business.</li>
          <li style="margin-bottom: 0.5rem;">Foster rigorous inquiry, critical thinking, and impactful applied research addressing national challenges.</li>
          <li style="margin-bottom: 0.5rem;">Instill ethical consciousness, civic responsibility, and deep respect for cultural heritage.</li>
          <li>Bridge academic curricula with industry partnerships and practical experiential learning.</li>
        </ul>
      </div>
    </div>

    <!-- Core Values -->
    <div class="section-header" style="margin-bottom: 2rem;">
      <span class="section-subtitle">Foundational Principles</span>
      <h2 class="section-title">Institutional Core Values</h2>
      <div class="section-line"></div>
    </div>

    <div class="grid-3">
      <div style="background: #ffffff; border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.75rem; text-align: center;">
        <i class="fas fa-balance-scale" style="font-size: 2rem; color: var(--rmu-primary); margin-bottom: 1rem;"></i>
        <h4 style="margin-bottom: 0.5rem; color: var(--rmu-dark);">Integrity &amp; Ethics</h4>
        <p style="font-size: 0.9rem; color: var(--rmu-muted);">Upholding uncompromising academic honesty, transparency, and ethical conduct in all endeavors.</p>
      </div>

      <div style="background: #ffffff; border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.75rem; text-align: center;">
        <i class="fas fa-lightbulb" style="font-size: 2rem; color: var(--rmu-primary); margin-bottom: 1rem;"></i>
        <h4 style="margin-bottom: 0.5rem; color: var(--rmu-dark);">Innovation &amp; Research</h4>
        <p style="font-size: 0.9rem; color: var(--rmu-muted);">Encouraging curiosity, cutting-edge experimentation, and technological discovery.</p>
      </div>

      <div style="background: #ffffff; border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.75rem; text-align: center;">
        <i class="fas fa-hands-helping" style="font-size: 2rem; color: var(--rmu-primary); margin-bottom: 1rem;"></i>
        <h4 style="margin-bottom: 0.5rem; color: var(--rmu-dark);">Inclusivity &amp; Equity</h4>
        <p style="font-size: 0.9rem; color: var(--rmu-muted);">Ensuring access to higher education through generous need- and merit-based financial aid.</p>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

