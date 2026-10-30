<?php
/**
 * Contact Us at Rabindra Maitree University
 * Verified reference contact details from https://rmu.ac.bd/contact/
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Contact Us';
$breadcrumbTitle = 'Contact Rabindra Maitree University';
$breadcrumbs = [['title' => 'Contact']];

$campusAddress = get_setting('campus_address', '97/71, Ram Chandra Roy, Jahid Chowdhury Rd, Kushtia 7000, Bangladesh');
$contactPhone = get_setting('contact_phone', '+8801302991188');
$contactEmail = get_setting('contact_email', 'info@rmu.ac.bd');
$officeHours = get_setting('office_hours', 'Saturday – Thursday: 9:00 AM – 5:00 PM');

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container">
    <div class="grid-2" style="gap: 3.5rem; align-items: start;">
      <!-- Contact Information & Map -->
      <div>
        <span class="section-subtitle">Get in Touch</span>
        <h2 class="section-title" style="text-align: left; margin-bottom: 1.25rem;">We Are Here to Assist You</h2>
        <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-bottom: 2rem;"></div>

        <div style="display: flex; flex-direction: column; gap: 1.5rem; margin-bottom: 2.5rem;">
          <div style="display: flex; gap: 1.25rem;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: var(--rmu-primary-light); color: var(--rmu-primary); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">
              <i class="fas fa-map-marker-alt"></i>
            </div>
            <div>
              <h4 style="font-size: 1.1rem; color: var(--rmu-dark); margin-bottom: 0.2rem;">Campus Address</h4>
              <p style="color: var(--rmu-muted); margin: 0; line-height: 1.6;"><?= e($campusAddress) ?></p>
            </div>
          </div>

          <div style="display: flex; gap: 1.25rem;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: var(--rmu-primary-light); color: var(--rmu-primary); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">
              <i class="fas fa-phone-alt"></i>
            </div>
            <div>
              <h4 style="font-size: 1.1rem; color: var(--rmu-dark); margin-bottom: 0.2rem;">Telephone &amp; Hotline</h4>
              <p style="color: var(--rmu-muted); margin: 0;"><a href="tel:<?= e($contactPhone) ?>"><?= e($contactPhone) ?></a> (Admission Desk)</p>
            </div>
          </div>

          <div style="display: flex; gap: 1.25rem;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: var(--rmu-primary-light); color: var(--rmu-primary); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">
              <i class="fas fa-envelope"></i>
            </div>
            <div>
              <h4 style="font-size: 1.1rem; color: var(--rmu-dark); margin-bottom: 0.2rem;">Email Inquiries</h4>
              <p style="color: var(--rmu-muted); margin: 0;"><a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></p>
            </div>
          </div>

          <div style="display: flex; gap: 1.25rem;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: var(--rmu-primary-light); color: var(--rmu-primary); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">
              <i class="fas fa-clock"></i>
            </div>
            <div>
              <h4 style="font-size: 1.1rem; color: var(--rmu-dark); margin-bottom: 0.2rem;">Office Hours</h4>
              <p style="color: var(--rmu-muted); margin: 0;"><?= e($officeHours) ?></p>
            </div>
          </div>
        </div>

        <!-- Campus Location Interactive Map Card -->
        <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.5rem; text-align: center;">
          <i class="fas fa-compass" style="font-size: 2.2rem; color: var(--rmu-primary); margin-bottom: 0.5rem;"></i>
          <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.4rem;">Location in Kushtia, Bangladesh</h4>
          <p style="color: var(--rmu-muted); font-size: 0.88rem; line-height: 1.5; margin-bottom: 1rem;">
            Centrally situated on Jahid Chowdhury Road, easily accessible from Kushtia Sadar, Majampur gate, and Shilaidaha Tagore Lodge.
          </p>
          <a href="https://maps.google.com/?q=Rabindra+Maitree+University+Kushtia" target="_blank" rel="noopener noreferrer" class="btn-primary" style="padding: 0.5rem 1.25rem; font-size: 0.85rem;">
            <i class="fas fa-external-link-alt"></i> Open in Google Maps
          </a>
        </div>
      </div>

      <!-- Contact Message Form -->
      <div>
        <div class="form-container">
          <h3 style="font-size: 1.4rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.4rem;">Send Us a Message</h3>
          <p style="color: var(--rmu-muted); font-size: 0.92rem; margin-bottom: 1.5rem;">Have questions regarding degrees, syllabus, or admission counseling?</p>

          <form action="<?= APP_URL ?>/actions/submit-contact.php" method="POST" id="rmuContactForm" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
              <label class="form-label">Full Name <span class="required">*</span></label>
              <input type="text" name="full_name" class="form-control" placeholder="Your full name" required minlength="3">
              <div class="form-error-msg">Please provide your full name.</div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label">Email Address <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
                <div class="form-error-msg">A valid email address is required.</div>
              </div>

              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-control" placeholder="01XXXXXXXXX">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Subject <span class="required">*</span></label>
              <input type="text" name="subject" class="form-control" placeholder="e.g. Admission Inquiry for Fall 2026" required>
              <div class="form-error-msg">Please enter a subject.</div>
            </div>

            <div class="form-group">
              <label class="form-label">Your Message <span class="required">*</span></label>
              <textarea name="message" rows="5" class="form-control" placeholder="Write your questions or inquiries here..." required minlength="10"></textarea>
              <div class="form-error-msg">Message must be at least 10 characters long.</div>
            </div>

            <div style="margin-top: 1.5rem;">
              <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem; font-size: 1rem;">
                <i class="fas fa-paper-plane"></i> Send Inquiry
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

