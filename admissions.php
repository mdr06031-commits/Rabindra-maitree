<?php
/**
 * Admission Overview at Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Admission Overview';
$breadcrumbTitle = 'Admissions — Fall 2026';
$breadcrumbs = [['title' => 'Admission', 'url' => APP_URL . '/admissions.php'], ['title' => 'Overview']];

$db = getDb();
$programs = $db->query("SELECT p.*, d.name_en AS dept_name FROM programs p JOIN departments d ON p.department_id = d.id WHERE p.is_active = 1 ORDER BY p.sort_order ASC")->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container">
    <div class="grid-2" style="align-items: center; gap: 3rem; margin-bottom: 3.5rem;">
      <div>
        <span class="section-subtitle">Admission Open &bull; Fall 2026</span>
        <h2 class="section-title" style="text-align: left; margin-bottom: 1.25rem;">Start Your Academic Journey at Rabindra Maitree University</h2>
        <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-bottom: 1.5rem;"></div>

        <p style="color: var(--rmu-muted); line-height: 1.8; font-size: 1.02rem; margin-bottom: 1.2rem;">
          Rabindra Maitree University invites applications from ambitious students for admission to undergraduate and postgraduate degree programs for the Fall 2026 semester.
        </p>

        <p style="color: var(--rmu-muted); line-height: 1.8; font-size: 1.02rem; margin-bottom: 1.5rem;">
          We offer UGC-accredited four-year bachelor's and one-to-two year master's degrees in engineering, agriculture, business, and humanities, accompanied by merit waivers and financial aid funds.
        </p>

        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
          <a href="<?= APP_URL ?>/admission-form.php" class="btn-primary" style="background: var(--rmu-accent); padding: 0.85rem 2rem; font-size: 1rem;">
            <i class="fas fa-file-signature"></i> Fill Online Admission Form
          </a>
          <a href="<?= APP_URL ?>/admission-status.php" style="padding: 0.85rem 1.6rem; border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-sm); font-weight: 600; color: var(--rmu-dark); display: inline-flex; align-items: center; gap: 0.4rem; background: var(--rmu-bg-light);">
            <i class="fas fa-search-location"></i> Track Existing Application
          </a>
        </div>
      </div>

      <!-- Admission Notice Box -->
      <div>
        <div style="background: var(--rmu-bg-light); border: 2px dashed var(--rmu-primary); border-radius: var(--rmu-radius-lg); padding: 2.2rem;">
          <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <span style="background: var(--rmu-accent); color: #fff; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Notice</span>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-dark); margin: 0;">Admission Fair Fall 2026</h3>
          </div>
          <p style="color: var(--rmu-muted); font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.25rem;">
            Special discount on admission fee and up to 100% tuition waiver based on SSC &amp; HSC GPA. Candidates can apply online or directly visit the Kushtia Campus Admission Office.
          </p>

          <div style="background: #ffffff; padding: 1rem; border-radius: var(--rmu-radius-sm); border: 1px solid var(--rmu-border); font-size: 0.88rem; margin-bottom: 1.2rem;">
            <div><i class="fas fa-calendar-check" style="color: var(--rmu-primary);"></i> <strong>Application Deadline:</strong> November 30, 2026</div>
            <div style="margin-top: 4px;"><i class="fas fa-phone-alt" style="color: var(--rmu-primary);"></i> <strong>Admission Hotline:</strong> +8801302991188</div>
            <div style="margin-top: 4px;"><i class="fas fa-envelope" style="color: var(--rmu-primary);"></i> <strong>Email:</strong> admission@rmu.ac.bd</div>
          </div>

          <a href="<?= APP_URL ?>/tuition-fees.php" style="font-weight: 600; font-size: 0.9rem; color: var(--rmu-primary); display: inline-flex; align-items: center; gap: 0.4rem;">
            <i class="fas fa-coins"></i> View Tuition Fees &amp; Waiver Policy &rarr;
          </a>
        </div>
      </div>
    </div>

    <!-- Eligibility Requirements -->
    <div style="margin-bottom: 3.5rem;">
      <div class="section-header" style="text-align: left; margin-bottom: 1.8rem;">
        <h3 style="font-size: 1.6rem; font-weight: 700; color: var(--rmu-dark); font-family: var(--rmu-font-serif);">
          General Eligibility Criteria
        </h3>
        <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-top: 0.4rem;"></div>
      </div>

      <div class="grid-3">
        <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.75rem;">
          <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.8rem;">
            <i class="fas fa-laptop-code" style="color: var(--rmu-primary);"></i> Engineering Programs
          </h4>
          <p style="font-size: 0.9rem; color: var(--rmu-muted); line-height: 1.6;">
            <strong>CSE, EEE, ICT:</strong> Candidates must have passed HSC from Science background with minimum GPA 2.50 in both SSC and HSC, including Mathematics and Physics. Diploma engineers are eligible for credit transfers.
          </p>
        </div>

        <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.75rem;">
          <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.8rem;">
            <i class="fas fa-briefcase" style="color: var(--rmu-primary);"></i> Business &amp; Humanities
          </h4>
          <p style="font-size: 0.9rem; color: var(--rmu-muted); line-height: 1.6;">
            <strong>BBA, English, Bangla:</strong> Open to applicants from Science, Commerce, or Humanities streams with minimum combined GPA 5.00 in SSC and HSC (minimum GPA 2.50 individually).
          </p>
        </div>

        <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.75rem;">
          <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.8rem;">
            <i class="fas fa-seedling" style="color: var(--rmu-primary);"></i> Agriculture &amp; Microbiology
          </h4>
          <p style="font-size: 0.9rem; color: var(--rmu-muted); line-height: 1.6;">
            <strong>Agriculture &amp; Microbiology:</strong> Candidates must have passed HSC with Biology and Chemistry from Science background, with minimum GPA 2.50 in both examinations.
          </p>
        </div>
      </div>
    </div>

    <!-- Application Process Steps -->
    <div style="margin-bottom: 3.5rem;">
      <div class="section-header" style="text-align: left; margin-bottom: 1.8rem;">
        <h3 style="font-size: 1.6rem; font-weight: 700; color: var(--rmu-dark); font-family: var(--rmu-font-serif);">
          Simple 4-Step Application Process
        </h3>
        <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-top: 0.4rem;"></div>
      </div>

      <div class="grid-4">
        <div style="text-align: center; padding: 1.5rem; background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); border: 1px solid var(--rmu-border);">
          <div style="width: 45px; height: 45px; border-radius: 50%; background: var(--rmu-primary); color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-weight: 700;">1</div>
          <h4 style="font-size: 1.05rem; margin-bottom: 0.4rem;">Fill Online Form</h4>
          <p style="font-size: 0.85rem; color: var(--rmu-muted);">Submit personal details, GPA, and intended subject choice.</p>
        </div>

        <div style="text-align: center; padding: 1.5rem; background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); border: 1px solid var(--rmu-border);">
          <div style="width: 45px; height: 45px; border-radius: 50%; background: var(--rmu-primary); color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-weight: 700;">2</div>
          <h4 style="font-size: 1.05rem; margin-bottom: 0.4rem;">Get Reference ID</h4>
          <p style="font-size: 0.85rem; color: var(--rmu-muted);">Receive your unique application tracking ID (e.g. RMU-2026-XXXX).</p>
        </div>

        <div style="text-align: center; padding: 1.5rem; background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); border: 1px solid var(--rmu-border);">
          <div style="width: 45px; height: 45px; border-radius: 50%; background: var(--rmu-primary); color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-weight: 700;">3</div>
          <h4 style="font-size: 1.05rem; margin-bottom: 0.4rem;">Document Review</h4>
          <p style="font-size: 0.85rem; color: var(--rmu-muted);">Admission counselors evaluate your academic transcripts and waiver eligibility.</p>
        </div>

        <div style="text-align: center; padding: 1.5rem; background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); border: 1px solid var(--rmu-border);">
          <div style="width: 45px; height: 45px; border-radius: 50%; background: var(--rmu-primary); color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-weight: 700;">4</div>
          <h4 style="font-size: 1.05rem; margin-bottom: 0.4rem;">Admit &amp; Enroll</h4>
          <p style="font-size: 0.85rem; color: var(--rmu-muted);">Receive formal admission letter and complete course registration.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

