<?php
/**
 * Privacy Policy & Data Protection Notice
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Privacy Policy';
$breadcrumbTitle = 'Privacy Policy & Student Data Protection';
$breadcrumbs = [['title' => 'Privacy Policy']];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 900px; color: var(--rmu-dark); line-height: 1.8;">
    <h2 style="color: var(--rmu-primary); margin-bottom: 1rem;">1. Development &amp; Demonstration Notice</h2>
    <p style="margin-bottom: 1.4rem;">
      This website and application software is an independent educational and development project modeled after the publicly accessible information architecture of Rabindra Maitree University (RMU), Kushtia. It is created for educational, architectural demonstration, and software engineering evaluation purposes.
    </p>

    <h2 style="color: var(--rmu-primary); margin: 2rem 0 1rem;">2. Prototype Admission Intake</h2>
    <p style="margin-bottom: 1.4rem;">
      The online admission system provided within this application functions as a local development prototype. Information submitted through the online admission form is saved solely into a local, isolated demonstration MySQL database. It is never transmitted to third parties, commercial brokers, or external servers.
    </p>

    <h2 style="color: var(--rmu-primary); margin: 2rem 0 1rem;">3. Data Security &amp; Retention</h2>
    <p style="margin-bottom: 1.4rem;">
      In accordance with best web development practices:
    </p>
    <ul style="margin-left: 1.5rem; margin-bottom: 1.4rem;">
      <li>All database communications use PDO prepared statements to safeguard against SQL injection.</li>
      <li>Sensitive administrative credentials are encrypted using industry standard Bcrypt algorithms (`password_hash`).</li>
      <li>Cross-Site Request Forgery (CSRF) tokens protect all state-changing POST endpoints.</li>
      <li>Session cookies are configured with HttpOnly and SameSite flags.</li>
    </ul>

    <h2 style="color: var(--rmu-primary); margin: 2rem 0 1rem;">4. Official University Inquiries</h2>
    <p style="margin-bottom: 1.8rem;">
      For official degree verification, authentic admissions circulars, or legal inquiries, please contact the official university campus at:
    </p>
    <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.5rem;">
      <p style="margin: 0; font-weight: 600;">Rabindra Maitree University</p>
      <p style="margin: 0.2rem 0; color: var(--rmu-muted);">97/71, Ram Chandra Roy, Jahid Chowdhury Rd, Kushtia 7000, Bangladesh</p>
      <p style="margin: 0; color: var(--rmu-muted);">Telephone: +8801302991188 | Email: info@rmu.ac.bd</p>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

