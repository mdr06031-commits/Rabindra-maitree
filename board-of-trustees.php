<?php
/**
 * Board of Trustees at Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Board of Trustees';
$breadcrumbTitle = 'Board of Trustees & University Governance';
$breadcrumbs = [['title' => 'About Us', 'url' => APP_URL . '/about.php'], ['title' => 'Board of Trustees']];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 950px;">
    <div class="section-header">
      <span class="section-subtitle">Governance Structure</span>
      <h2 class="section-title">Board of Trustees</h2>
      <div class="section-line"></div>
    </div>

    <p style="color: var(--rmu-muted); line-height: 1.8; margin-bottom: 2rem; text-align: center;">
      The Board of Trustees (BOT) of Rabindra Maitree University constitutes the apex policy-making body of the university as chartered under the Private University Act 2010 of Bangladesh. The Board is responsible for institutional strategy, physical expansion, financial sustainability, and upholding educational integrity.
    </p>

    <div class="table-responsive">
      <table class="rmu-table">
        <thead>
          <tr>
            <th>Designation in BOT</th>
            <th>Name / Representative</th>
            <th>Affiliation / Role</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Chairman</strong></td>
            <td>Eminent Educationist &amp; Philanthropist</td>
            <td>Chair, RMU Board of Trustees</td>
          </tr>
          <tr>
            <td><strong>Member</strong></td>
            <td>Vice-Chancellor, RMU</td>
            <td>Ex-Officio Member</td>
          </tr>
          <tr>
            <td><strong>Member</strong></td>
            <td>Nominee, Ministry of Education</td>
            <td>Government Representative</td>
          </tr>
          <tr>
            <td><strong>Member</strong></td>
            <td>Nominee, University Grants Commission (UGC)</td>
            <td>Academic Quality Oversight</td>
          </tr>
          <tr>
            <td><strong>Member</strong></td>
            <td>Representative of Civil Society, Kushtia</td>
            <td>Community Welfare</td>
          </tr>
          <tr>
            <td><strong>Member Secretary</strong></td>
            <td>Registrar, RMU</td>
            <td>Ex-Officio Member Secretary</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div style="background: var(--rmu-bg-light); border-left: 4px solid var(--rmu-primary); padding: 1.25rem; margin-top: 2rem; border-radius: var(--rmu-radius-sm);">
      <p style="margin: 0; font-size: 0.9rem; color: var(--rmu-muted);">
        <em>Note: Detailed individual profiles of all trustee members are maintained in accordance with UGC guidelines. For official gazettes and statutory documentation, please contact the Office of the Registrar.</em>
      </p>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

