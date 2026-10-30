<?php
/**
 * Admission Frequently Asked Questions at RMU
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Admission FAQs';
$breadcrumbTitle = 'Frequently Asked Questions (FAQs)';
$breadcrumbs = [['title' => 'Admission', 'url' => APP_URL . '/admissions.php'], ['title' => 'FAQs']];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 900px;">
    <div class="section-header">
      <span class="section-subtitle">Common Inquiries</span>
      <h2 class="section-title">Frequently Asked Questions</h2>
      <div class="section-line"></div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
      <details style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.25rem; cursor: pointer;">
        <summary style="font-weight: 700; color: var(--rmu-dark); font-size: 1.05rem; list-style: none; display: flex; justify-content: space-between; align-items: center;">
          <span>Is Rabindra Maitree University recognized by UGC?</span>
          <i class="fas fa-chevron-down" style="color: var(--rmu-primary);"></i>
        </summary>
        <p style="margin-top: 1rem; color: var(--rmu-muted); line-height: 1.7; font-size: 0.95rem;">
          Yes. Rabindra Maitree University was established in 2015 under the Private University Act 2010 with full statutory approval from the Ministry of Education and the University Grants Commission (UGC) of Bangladesh. All degrees conferred are nationally recognized and valid for government employment and foreign postgraduate admissions.
        </p>
      </details>

      <details style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.25rem; cursor: pointer;">
        <summary style="font-weight: 700; color: var(--rmu-dark); font-size: 1.05rem; list-style: none; display: flex; justify-content: space-between; align-items: center;">
          <span>How can I apply for Fall 2026 admission?</span>
          <i class="fas fa-chevron-down" style="color: var(--rmu-primary);"></i>
        </summary>
        <p style="margin-top: 1rem; color: var(--rmu-muted); line-height: 1.7; font-size: 0.95rem;">
          You can submit your application directly online through our digital portal at <a href="<?= APP_URL ?>/admission-form.php">Online Admission Form</a>. Alternatively, you can visit the Admission Office at our Kushtia campus (97/71 Ram Chandra Roy, Jahid Chowdhury Rd, Kushtia 7000) from Saturday to Thursday, 9:00 AM – 5:00 PM.
        </p>
      </details>

      <details style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.25rem; cursor: pointer;">
        <summary style="font-weight: 700; color: var(--rmu-dark); font-size: 1.05rem; list-style: none; display: flex; justify-content: space-between; align-items: center;">
          <span>What documents are required for admission confirmation?</span>
          <i class="fas fa-chevron-down" style="color: var(--rmu-primary);"></i>
        </summary>
        <p style="margin-top: 1rem; color: var(--rmu-muted); line-height: 1.7; font-size: 0.95rem;">
          Required documents include:
          <br>1. Original and photocopies of SSC and HSC mark sheets and certificates.
          <br>2. 4 copies of passport-sized color photographs.
          <br>3. Photocopy of National ID Card (NID) or Birth Registration Certificate.
          <br>4. Online application confirmation voucher.
        </p>
      </details>

      <details style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.25rem; cursor: pointer;">
        <summary style="font-weight: 700; color: var(--rmu-dark); font-size: 1.05rem; list-style: none; display: flex; justify-content: space-between; align-items: center;">
          <span>Can I pay semester tuition fees in installments?</span>
          <i class="fas fa-chevron-down" style="color: var(--rmu-primary);"></i>
        </summary>
        <p style="margin-top: 1rem; color: var(--rmu-muted); line-height: 1.7; font-size: 0.95rem;">
          Yes. Semester fees can be paid in 3 flexible installments throughout each semester: 40% at course registration, 30% prior to midterm examinations, and 30% prior to final examinations.
        </p>
      </details>

      <details style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.25rem; cursor: pointer;">
        <summary style="font-weight: 700; color: var(--rmu-dark); font-size: 1.05rem; list-style: none; display: flex; justify-content: space-between; align-items: center;">
          <span>Are credit transfers allowed for Diploma in Engineering holders?</span>
          <i class="fas fa-chevron-down" style="color: var(--rmu-primary);"></i>
        </summary>
        <p style="margin-top: 1rem; color: var(--rmu-muted); line-height: 1.7; font-size: 0.95rem;">
          Yes. Poly-technic diploma holders in Computer, Electrical, Electronics, or Civil Technology can apply for credit exemptions for up to 36-40 credits in relevant BSc engineering programs, reducing total study duration to 3 – 3.5 years.
        </p>
      </details>
    </div>

    <div style="margin-top: 3rem; text-align: center; background: var(--rmu-bg-light); padding: 2rem; border-radius: var(--rmu-radius-md); border: 1px solid var(--rmu-border);">
      <h4 style="font-size: 1.2rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.5rem;">Still have questions?</h4>
      <p style="color: var(--rmu-muted); margin-bottom: 1.2rem; font-size: 0.92rem;">Our admission counseling team is ready to assist you.</p>
      <a href="<?= APP_URL ?>/contact.php" class="btn-primary">
        <i class="fas fa-envelope"></i> Contact Admission Counseling Desk
      </a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

