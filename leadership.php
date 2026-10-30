<?php
/**
 * Leadership Messages at Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Leadership Messages';
$breadcrumbTitle = 'Messages from University Leadership';
$breadcrumbs = [['title' => 'About Us', 'url' => APP_URL . '/about.php'], ['title' => 'Leadership']];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 1050px;">
    <!-- Message from Chairman -->
    <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-lg); padding: 2.5rem; border: 1px solid var(--rmu-border); margin-bottom: 3rem; box-shadow: var(--rmu-shadow-sm);">
      <div style="display: flex; gap: 2rem; align-items: flex-start; flex-wrap: wrap;">
        <div style="width: 120px; height: 120px; border-radius: 50%; background: var(--rmu-primary-light); color: var(--rmu-primary); display: flex; align-items: center; justify-content: center; font-size: 3rem; flex-shrink: 0; border: 3px solid var(--rmu-primary);">
          <i class="fas fa-user-tie"></i>
        </div>
        <div style="flex: 1; min-width: 280px;">
          <span style="font-size: 0.85rem; font-weight: 700; color: var(--rmu-primary); text-transform: uppercase; letter-spacing: 1px;">Governance &amp; Vision</span>
          <h2 style="font-size: 1.6rem; font-weight: 700; color: var(--rmu-dark); margin: 0.3rem 0;">Message from the Chairman, Board of Trustees</h2>
          <h4 style="font-size: 1rem; color: var(--rmu-muted); font-weight: 500; margin-bottom: 1.25rem;">Rabindra Maitree University, Kushtia</h4>
          <p style="color: var(--rmu-dark); line-height: 1.8; margin-bottom: 1rem;">
            "On behalf of the Board of Trustees, I welcome prospective students, guardians, and academic researchers to Rabindra Maitree University. Established with the lofty vision of enlightening young minds in the historic soil of Kushtia, our mission has always been to democratize access to world-class education. We remain steadfast in investing in physical infrastructure, smart laboratories, and faculty development to prepare our youth for the challenges of tomorrow."
          </p>
        </div>
      </div>
    </div>

    <!-- Message from Vice-Chancellor -->
    <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-lg); padding: 2.5rem; border: 1px solid var(--rmu-border); margin-bottom: 3rem; box-shadow: var(--rmu-shadow-sm);">
      <div style="display: flex; gap: 2rem; align-items: flex-start; flex-wrap: wrap;">
        <div style="width: 120px; height: 120px; border-radius: 50%; background: #e0e7ff; color: var(--rmu-secondary); display: flex; align-items: center; justify-content: center; font-size: 3rem; flex-shrink: 0; border: 3px solid var(--rmu-secondary);">
          <i class="fas fa-user-graduate"></i>
        </div>
        <div style="flex: 1; min-width: 280px;">
          <span style="font-size: 0.85rem; font-weight: 700; color: var(--rmu-secondary); text-transform: uppercase; letter-spacing: 1px;">Academic Leadership</span>
          <h2 style="font-size: 1.6rem; font-weight: 700; color: var(--rmu-dark); margin: 0.3rem 0;">Message from the Vice-Chancellor</h2>
          <h4 style="font-size: 1rem; color: var(--rmu-muted); font-weight: 500; margin-bottom: 1.25rem;">Rabindra Maitree University, Kushtia</h4>
          <p style="color: var(--rmu-dark); line-height: 1.8; margin-bottom: 1rem;">
            "Rabindra Maitree University stands at the vanguard of higher learning in South-Western Bangladesh. We believe that true education nurtures intellect alongside empathy, curiosity alongside discipline. Our curricula are designed in compliance with UGC benchmarks and Outcome-Based Education (OBE) frameworks. Whether in Engineering, Business, or the Arts, our students are challenged to solve real-world problems and uphold ethical standards."
          </p>
        </div>
      </div>
    </div>

    <!-- Message from Treasurer -->
    <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-lg); padding: 2.5rem; border: 1px solid var(--rmu-border); box-shadow: var(--rmu-shadow-sm);">
      <div style="display: flex; gap: 2rem; align-items: flex-start; flex-wrap: wrap;">
        <div style="width: 120px; height: 120px; border-radius: 50%; background: #fef3c7; color: #92400e; display: flex; align-items: center; justify-content: center; font-size: 3rem; flex-shrink: 0; border: 3px solid #b45309;">
          <i class="fas fa-coins"></i>
        </div>
        <div style="flex: 1; min-width: 280px;">
          <span style="font-size: 0.85rem; font-weight: 700; color: #b45309; text-transform: uppercase; letter-spacing: 1px;">Financial Governance &amp; Student Welfare</span>
          <h2 style="font-size: 1.6rem; font-weight: 700; color: var(--rmu-dark); margin: 0.3rem 0;">Message from the Treasurer</h2>
          <h4 style="font-size: 1rem; color: var(--rmu-muted); font-weight: 500; margin-bottom: 1.25rem;">Rabindra Maitree University, Kushtia</h4>
          <p style="color: var(--rmu-dark); line-height: 1.8; margin-bottom: 1rem;">
            "Sound financial stewardship and student welfare are the bedrock of RMU's fiscal policies. We take great pride in allocating substantial funds toward merit waivers, freedom-fighter quotas, and endowed trust funds like the Mansur Ali &amp; Atharunnesa Trust. We ensure that every taka received from our students is directly reinvested in state-of-the-art campus learning facilities."
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

