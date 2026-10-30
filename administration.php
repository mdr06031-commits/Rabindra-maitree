<?php
/**
 * University Administration at RMU
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'University Administration';
$breadcrumbTitle = 'University Administration & Key Offices';
$breadcrumbs = [['title' => 'About Us', 'url' => APP_URL . '/about.php'], ['title' => 'Administration']];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 1000px;">
    <div class="section-header">
      <span class="section-subtitle">Administrative Structure</span>
      <h2 class="section-title">Key Administrative Offices</h2>
      <div class="section-line"></div>
    </div>

    <div class="grid-2" style="gap: 2rem;">
      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); padding: 1.75rem; border: 1px solid var(--rmu-border);">
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-primary); margin-bottom: 0.5rem;"><i class="fas fa-building"></i> Office of the Vice-Chancellor</h3>
        <p style="font-size: 0.92rem; color: var(--rmu-muted); margin-bottom: 0.8rem;">Chief Executive and Academic Officer directing university policy, accreditation, faculty appointments, and academic leadership.</p>
        <div style="font-size: 0.85rem; color: var(--rmu-dark);"><strong>Email:</strong> vc@rmu.ac.bd | <strong>Phone:</strong> +8801302991188</div>
      </div>

      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); padding: 1.75rem; border: 1px solid var(--rmu-border);">
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-primary); margin-bottom: 0.5rem;"><i class="fas fa-clipboard-list"></i> Office of the Registrar</h3>
        <p style="font-size: 0.92rem; color: var(--rmu-muted); margin-bottom: 0.8rem;">Custodian of student academic records, university statutory bodies, statutory compliance, and administrative admissions.</p>
        <div style="font-size: 0.85rem; color: var(--rmu-dark);"><strong>Email:</strong> registrar@rmu.ac.bd | <strong>Phone:</strong> +8801302991188</div>
      </div>

      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); padding: 1.75rem; border: 1px solid var(--rmu-border);">
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-primary); margin-bottom: 0.5rem;"><i class="fas fa-file-invoice-dollar"></i> Office of the Treasurer</h3>
        <p style="font-size: 0.92rem; color: var(--rmu-muted); margin-bottom: 0.8rem;">Overseeing university budget allocation, financial audits, student billing, tuition waivers, and capital development funds.</p>
        <div style="font-size: 0.85rem; color: var(--rmu-dark);"><strong>Email:</strong> treasurer@rmu.ac.bd | <strong>Phone:</strong> +8801302991188</div>
      </div>

      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); padding: 1.75rem; border: 1px solid var(--rmu-border);">
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-primary); margin-bottom: 0.5rem;"><i class="fas fa-award"></i> Controller of Examinations</h3>
        <p style="font-size: 0.92rem; color: var(--rmu-muted); margin-bottom: 0.8rem;">Conducting semester final examinations, grading management, degree conferral, and issuing official transcripts and certificates.</p>
        <div style="font-size: 0.85rem; color: var(--rmu-dark);"><strong>Email:</strong> controller@rmu.ac.bd | <strong>Phone:</strong> +8801302991188</div>
      </div>

      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); padding: 1.75rem; border: 1px solid var(--rmu-border);">
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-primary); margin-bottom: 0.5rem;"><i class="fas fa-shield-alt"></i> Office of the Proctor</h3>
        <p style="font-size: 0.92rem; color: var(--rmu-muted); margin-bottom: 0.8rem;">Maintaining campus security, student discipline, harassment-free environment, and student welfare policies.</p>
        <div style="font-size: 0.85rem; color: var(--rmu-dark);"><strong>Email:</strong> proctor@rmu.ac.bd | <strong>Phone:</strong> +8801302991188</div>
      </div>

      <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); padding: 1.75rem; border: 1px solid var(--rmu-border);">
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-primary); margin-bottom: 0.5rem;"><i class="fas fa-user-friends"></i> Office of Student Affairs &amp; Admissions</h3>
        <p style="font-size: 0.92rem; color: var(--rmu-muted); margin-bottom: 0.8rem;">Assisting prospective applicants, coordinating freshman orientation, managing student clubs, cultural festivals, and career services.</p>
        <div style="font-size: 0.85rem; color: var(--rmu-dark);"><strong>Email:</strong> admission@rmu.ac.bd | <strong>Phone:</strong> +8801302991188</div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

