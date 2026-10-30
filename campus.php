<?php
/**
 * Campus & Facilities at Rabindra Maitree University (RMU)
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Campus & Facilities';
$breadcrumbTitle = 'Kushtia Campus & Academic Facilities';
$breadcrumbs = [['title' => 'Campus']];

$campusAddress = get_setting('campus_address', '97/71, Ram Chandra Roy, Jahid Chowdhury Rd, Kushtia 7000, Bangladesh');
$contactPhone = get_setting('contact_phone', '+8801302991188');
$contactEmail = get_setting('contact_email', 'info@rmu.ac.bd');
$officeHours = get_setting('office_hours', 'Saturday – Thursday: 9:00 AM – 5:00 PM');

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container">
    <div class="section-header" style="text-align: left; margin-bottom: 2.5rem;">
      <span class="section-subtitle">University Environment &amp; Infrastructure</span>
      <h2 class="section-title" style="text-align: left;">RMU Kushtia Campus &amp; Student Life</h2>
      <div class="section-line" style="margin: 0.8rem 0 0 0;"></div>
      <p style="color: var(--rmu-muted); margin-top: 0.8rem; font-size: 0.95rem; max-width: 820px; margin-left: 0;">
        Situated in the culturally renowned district of Kushtia, the historic home of Nobel laureate Rabindranath Tagore, Rabindra Maitree University offers a modern, safe, and academically vibrant campus designed for intellectual exploration and cultural development.
      </p>
    </div>

    <div class="grid-3" style="gap: 1.8rem; margin-bottom: 3.5rem;">
      <!-- Feature 1: Modern Classrooms -->
      <div class="feature-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        <div style="width:52px; height:52px; border-radius:10px; background:#eff6ff; color:#1e73be; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:1rem;">
          <i class="fas fa-chalkboard"></i>
        </div>
        <h3 style="font-size:1.2rem; font-weight:700; color:#0f172a; margin-bottom:0.5rem;">Multimedia Classrooms</h3>
        <p style="color:#64748b; font-size:0.9rem; line-height:1.6; margin:0;">
          Equipped with digital projectors, audio-visual presentation aids, high-speed Wi-Fi, and climate control to facilitate interactive student learning.
        </p>
      </div>

      <!-- Feature 2: High-Tech Labs -->
      <div class="feature-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        <div style="width:52px; height:52px; border-radius:10px; background:#eff6ff; color:#1e73be; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:1rem;">
          <i class="fas fa-laptop-code"></i>
        </div>
        <h3 style="font-size:1.2rem; font-weight:700; color:#0f172a; margin-bottom:0.5rem;">Engineering &amp; Science Labs</h3>
        <p style="color:#64748b; font-size:0.9rem; line-height:1.6; margin:0;">
          State-of-the-art Computer Science &amp; Engineering labs, Electrical Engineering hardware workshops, and Agriculture Science experimental research facilities.
        </p>
      </div>

      <!-- Feature 3: Central Library -->
      <div class="feature-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        <div style="width:52px; height:52px; border-radius:10px; background:#eff6ff; color:#1e73be; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:1rem;">
          <i class="fas fa-book-reader"></i>
        </div>
        <h3 style="font-size:1.2rem; font-weight:700; color:#0f172a; margin-bottom:0.5rem;">Central Library &amp; E-Resources</h3>
        <p style="color:#64748b; font-size:0.9rem; line-height:1.6; margin:0;">
          Extensive collection of academic textbooks, journals, reference materials, Tagore literary archives, and digital e-resource databases for student research.
        </p>
      </div>

      <!-- Feature 4: Cultural Center -->
      <div class="feature-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        <div style="width:52px; height:52px; border-radius:10px; background:#eff6ff; color:#1e73be; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:1rem;">
          <i class="fas fa-music"></i>
        </div>
        <h3 style="font-size:1.2rem; font-weight:700; color:#0f172a; margin-bottom:0.5rem;">Rabindra Cultural Center</h3>
        <p style="color:#64748b; font-size:0.9rem; line-height:1.6; margin:0;">
          Music, fine arts, and literary practice spaces dedicated to Rabindranath Tagore's universal philosophy, hosting poetry recitals, music workshops, and cultural galas.
        </p>
      </div>

      <!-- Feature 5: Safe Campus & Harassment-Free -->
      <div class="feature-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        <div style="width:52px; height:52px; border-radius:10px; background:#eff6ff; color:#1e73be; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:1rem;">
          <i class="fas fa-shield-alt"></i>
        </div>
        <h3 style="font-size:1.2rem; font-weight:700; color:#0f172a; margin-bottom:0.5rem;">Safe &amp; Disciplined Environment</h3>
        <p style="color:#64748b; font-size:0.9rem; line-height:1.6; margin:0;">
          24/7 CCTV surveillance, proactive security personnel, active student grievance cells, and a strictly enforced anti-harassment and anti-ragging campus policy.
        </p>
      </div>

      <!-- Feature 6: Student Services -->
      <div class="feature-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        <div style="width:52px; height:52px; border-radius:10px; background:#eff6ff; color:#1e73be; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:1rem;">
          <i class="fas fa-users"></i>
        </div>
        <h3 style="font-size:1.2rem; font-weight:700; color:#0f172a; margin-bottom:0.5rem;">Student Welfare &amp; Cafeteria</h3>
        <p style="color:#64748b; font-size:0.9rem; line-height:1.6; margin:0;">
          Subsidized campus cafeteria offering hygienic meals, indoor recreational spaces, career counseling sessions, and academic mentorship.
        </p>
      </div>
    </div>

    <!-- Campus Address Banner -->
    <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:2rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.5rem;">
      <div>
        <h3 style="font-size:1.3rem; font-weight:700; color:#0f172a; margin-bottom:0.5rem;">
          <i class="fas fa-map-marker-alt" style="color:#1e73be;"></i> Visit RMU Kushtia Campus
        </h3>
        <p style="color:#64748b; margin:0; line-height:1.6; font-size:0.95rem;">
          <strong>Address:</strong> <?= e($campusAddress) ?><br>
          <strong>Office Hours:</strong> <?= e($officeHours) ?> (Admission Desk Open)
        </p>
      </div>
      <div style="display:flex; gap:0.8rem; flex-wrap:wrap;">
        <a href="<?= APP_URL ?>/admission-form.php" class="btn-primary" style="padding:0.75rem 1.4rem;">
          <i class="fas fa-file-signature"></i> Apply Online
        </a>
        <a href="<?= APP_URL ?>/contact.php" style="background:#fff; color:#0f172a; border:1px solid #cbd5e1; padding:0.75rem 1.4rem; border-radius:6px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
          <i class="fas fa-envelope"></i> Contact Office
        </a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

