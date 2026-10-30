<?php
/**
 * Rabindra Maitree University (RMU) Footer Template
 */
$campusAddress = get_setting('campus_address', '97/71, Ram Chandra Roy, Jahid Chowdhury Rd, Kushtia 7000, Bangladesh');
$contactPhone = get_setting('contact_phone', '+8801302991188');
$contactEmail = get_setting('contact_email', 'info@rmu.ac.bd');
$officeHours = get_setting('office_hours', 'Saturday – Thursday: 9:00 AM – 5:00 PM');
$facebookUrl = get_setting('facebook_url', 'https://www.facebook.com/rmukushtia');
$linkedinUrl = get_setting('linkedin_url', 'https://www.linkedin.com/school/rmukushtia/');
$whatsappUrl = get_setting('whatsapp_url', 'https://wa.me/8801302991188');
?>
  <!-- University Footer -->
  <footer class="rmu-footer">
    <div class="container">
      <div class="footer-grid">
        <!-- Col 1: About -->
        <div class="footer-widget footer-about">
          <h4>Rabindra Maitree University</h4>
          <p>
            Established in 2015 in Kushtia, Rabindra Maitree University is an approved higher education institution dedicated to academic excellence, progressive research, and ethical leadership inspired by the universal humanism of Nobel laureate Rabindranath Tagore.
          </p>
          <div style="display:flex; gap:0.6rem;">
            <a href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener noreferrer" style="width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,0.1); display:inline-flex; align-items:center; justify-content:center; color:#fff;" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="<?= e($linkedinUrl) ?>" target="_blank" rel="noopener noreferrer" style="width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,0.1); display:inline-flex; align-items:center; justify-content:center; color:#fff;" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer" style="width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,0.1); display:inline-flex; align-items:center; justify-content:center; color:#fff;" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
          </div>
        </div>

        <!-- Col 2: Quick Links -->
        <div class="footer-widget">
          <h4>Academic Structure</h4>
          <ul class="footer-links">
            <li><a href="<?= APP_URL ?>/faculties.php"><i class="fas fa-angle-right"></i> All Faculties</a></li>
            <li><a href="<?= APP_URL ?>/departments.php"><i class="fas fa-angle-right"></i> Academic Departments</a></li>
            <li><a href="<?= APP_URL ?>/programs.php"><i class="fas fa-angle-right"></i> Degree Programs</a></li>
            <li><a href="<?= APP_URL ?>/campus.php"><i class="fas fa-angle-right"></i> Campus &amp; Facilities</a></li>
            <li><a href="<?= APP_URL ?>/notices.php"><i class="fas fa-angle-right"></i> Official Notices</a></li>
            <li><a href="<?= APP_URL ?>/events.php"><i class="fas fa-angle-right"></i> University Events</a></li>
            <li><a href="<?= APP_URL ?>/downloads.php"><i class="fas fa-angle-right"></i> Downloadable Forms</a></li>
          </ul>
        </div>

        <!-- Col 3: Admission Links -->
        <div class="footer-widget">
          <h4>Admission &amp; Student Portal</h4>
          <ul class="footer-links">
            <li><a href="<?= APP_URL ?>/student/login.php" style="color:#60a5fa; font-weight:700;"><i class="fas fa-user-graduate"></i> Student Portal Login</a></li>
            <li><a href="<?= APP_URL ?>/admissions.php"><i class="fas fa-angle-right"></i> Admission Overview</a></li>
            <li><a href="<?= APP_URL ?>/admission-form.php"><i class="fas fa-angle-right"></i> Online Admission Form</a></li>
            <li><a href="<?= APP_URL ?>/admission-status.php"><i class="fas fa-angle-right"></i> Track Application</a></li>
            <li><a href="<?= APP_URL ?>/tuition-fees.php"><i class="fas fa-angle-right"></i> Tuition Fee Structure</a></li>
            <li><a href="<?= APP_URL ?>/scholarships.php"><i class="fas fa-angle-right"></i> Scholarships &amp; Waivers</a></li>
            <li><a href="<?= APP_URL ?>/faqs.php"><i class="fas fa-angle-right"></i> Frequently Asked Questions</a></li>
          </ul>
        </div>

        <!-- Col 4: Contact Information -->
        <div class="footer-widget">
          <h4>Campus Contact</h4>
          <ul class="footer-contact-list">
            <li>
              <i class="fas fa-map-marker-alt" style="color:var(--rmu-primary); margin-top:4px;"></i>
              <span><?= e($campusAddress) ?></span>
            </li>
            <li>
              <i class="fas fa-phone-alt" style="color:var(--rmu-primary);"></i>
              <a href="tel:<?= e($contactPhone) ?>"><?= e($contactPhone) ?></a>
            </li>
            <li>
              <i class="fas fa-envelope" style="color:var(--rmu-primary);"></i>
              <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a>
            </li>
            <li>
              <i class="fas fa-clock" style="color:var(--rmu-primary);"></i>
              <span><?= e($officeHours) ?></span>
            </li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <div>
          &copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong>. All rights reserved.
        </div>
        <div style="display:flex; gap:1.25rem;">
          <a href="<?= APP_URL ?>/privacy-policy.php">Privacy Policy</a>
          <a href="<?= APP_URL ?>/contact.php">Contact Support</a>
          <a href="<?= APP_URL ?>/admin/login.php" style="color:var(--rmu-muted);"><i class="fas fa-lock"></i> Staff Portal</a>
        </div>
      </div>

      <div style="margin-top:1rem; padding-top:0.8rem; border-top:1px dashed rgba(255,255,255,0.05); font-size:0.75rem; color:#6b7280; text-align:center;">
        <em>Disclaimer: This application is an educational and authorized development reconstruction. It does not replace the official administrative registrar of Rabindra Maitree University.</em>
      </div>
    </div>
  </footer>

  <!-- Scripts (Cache-busted on every deploy) -->
  <script src="<?= APP_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>
  <script src="<?= APP_URL ?>/assets/js/validation.js?v=<?= time() ?>"></script>
</body>
</html>

