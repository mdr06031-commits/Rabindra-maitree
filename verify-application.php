<?php
/**
 * Public QR Verification Portal — Rabindra Maitree University (RMU)
 * Validates QR verification token and displays ONLY non-private official status summary.
 * Strictly hides private student PII (phone, email, addresses, parents, DOB, SSC/HSC scores, portal credentials).
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Official Application Verification';
$breadcrumbTitle = 'QR Application Verification';
$breadcrumbs = [
    ['title' => 'Admission', 'url' => APP_URL . '/admissions.php'],
    ['title' => 'Application Verification'],
];

$ref = trim((string)($_GET['ref'] ?? ''));
$suppliedToken = trim((string)($_GET['token'] ?? ''));

$db = getDb();
$branding = get_university_official_branding($db);
$adm = null;
$isVerified = false;
$failureReason = '';

if ($ref === '' || $suppliedToken === '') {
    $failureReason = 'Missing application reference or verification security token in QR payload.';
} else {
    $adm = fetch_admission_full_record(0, $ref, $db, false);
    if (!$adm) {
        $failureReason = 'No official admission record matches the supplied Application Reference.';
    } else {
        $expectedToken = trim((string)($adm['verification_token'] ?? ''));
        if ($expectedToken !== '' && strlen($suppliedToken) >= 16 && hash_equals($expectedToken, $suppliedToken)) {
            $isVerified = true;
        } else {
            $failureReason = 'Security token mismatch. This QR verification link may have been altered or forged.';
        }
    }
}

$docData = ($isVerified && $adm) ? enrich_admission_document_data($adm, $db) : null;

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<style>
.rmu-verif-wrap {
  max-width: 780px;
  margin: 0 auto;
}
.rmu-verif-card {
  background: #ffffff;
  border: 2px solid #0f2c59;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 12px 30px rgba(15, 44, 89, 0.09);
}
.rmu-verif-hd {
  background: linear-gradient(135deg, #0f2c59 0%, #1a407a 100%);
  color: #ffffff;
  padding: 18px 22px;
  border-bottom: 4px solid #c9a227;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
}
.rmu-verif-brand {
  display: flex;
  align-items: center;
  gap: 12px;
}
.rmu-verif-brand img {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  background: #ffffff;
  border: 2px solid #c9a227;
  padding: 2px;
  object-fit: contain;
}
.rmu-verif-banner-ok {
  background: #f0fdf4;
  border: 1.5px solid #86efac;
  border-left: 6px solid #15803d;
  color: #14532d;
  border-radius: 8px;
  padding: 14px 18px;
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.rmu-verif-banner-err {
  background: #fef2f2;
  border: 1.5px solid #fca5a5;
  border-left: 6px solid #b91c1c;
  color: #7f1d1d;
  border-radius: 8px;
  padding: 18px 20px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.rmu-verif-grid {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.92rem;
}
.rmu-verif-grid th, .rmu-verif-grid td {
  border: 1px solid #e2e8f0;
  padding: 10px 12px;
  text-align: left;
}
.rmu-verif-grid th {
  width: 34%;
  background: #f8fafc;
  color: #475569;
  font-weight: 700;
}
.rmu-verif-grid td {
  color: #0f172a;
  font-weight: 600;
}
@media (max-width: 640px) {
  .rmu-verif-grid, .rmu-verif-grid tbody, .rmu-verif-grid tr, .rmu-verif-grid th, .rmu-verif-grid td {
    display: block;
    width: 100% !important;
    box-sizing: border-box;
  }
  .rmu-verif-grid th { border-bottom: none; padding-bottom: 3px; font-size: 0.8rem; }
  .rmu-verif-grid td { border-top: none; padding-top: 2px; margin-bottom: 4px; }
}
</style>

<section class="section section-bg-white">
  <div class="container rmu-verif-wrap">
    <div class="rmu-verif-card">
      <div class="rmu-verif-hd">
        <div class="rmu-verif-brand">
          <img src="<?= e($branding['logo_url']) ?>" alt="<?= e($branding['name_en_upper']) ?> Logo">
          <div>
            <h2 style="margin:0; font-size:1.18rem; font-weight:800; color:#ffffff;"><?= e($branding['name_en_upper']) ?></h2>
            <div style="font-size:0.95rem; font-weight:700; color:#fde68a;"><?= e($branding['name_bn']) ?></div>
            <div style="font-size:0.76rem; color:#dbeafe;"><?= e($branding['address']) ?></div>
          </div>
        </div>
        <div>
          <span style="display:inline-block; background:rgba(201,162,39,0.2); border:1.5px solid #c9a227; color:#fef3c7; padding:5px 12px; border-radius:5px; font-size:0.78rem; font-weight:800; letter-spacing:0.6px; text-transform:uppercase;">
            QR Document Verification
          </span>
        </div>
      </div>

      <div style="padding: 22px;">
        <?php if ($isVerified && $docData): ?>
          <div class="rmu-verif-banner-ok">
            <i class="fas fa-shield-alt" style="font-size:1.75rem; color:#15803d; flex-shrink:0;"></i>
            <div>
              <strong style="font-size:1.05rem; display:block;">Authentic Application Record Verified</strong>
              <span style="font-size:0.86rem;">
                Confirmed against official university database records on <?= date('d M Y, h:i A') ?>.
              </span>
            </div>
          </div>

          <table class="rmu-verif-grid">
            <tbody>
              <tr>
                <th>Application ID</th>
                <td style="font-family:monospace; font-size:1.05rem; color:#0f2c59; font-weight:800;"><?= e($docData['application_id']) ?></td>
              </tr>
              <?php if ($docData['roll_number'] !== ''): ?>
              <tr>
                <th>Admission Roll Number</th>
                <td style="font-family:monospace; font-weight:700;"><?= e($docData['roll_number']) ?></td>
              </tr>
              <?php endif; ?>
              <tr>
                <th>Applicant Name</th>
                <td><strong><?= e($docData['raw']['full_name'] ?? '') ?></strong></td>
              </tr>
              <tr>
                <th>Selected Department</th>
                <td><?= e($docData['department_name']) ?></td>
              </tr>
              <tr>
                <th>Degree / Program</th>
                <td><?= e($docData['degree_type']) ?> &mdash; <?= e($docData['program_name']) ?></td>
              </tr>
              <tr>
                <th>Academic Session</th>
                <td><?= e($docData['academic_session']) ?></td>
              </tr>
              <tr>
                <th>Submission Date</th>
                <td><?= e($docData['submission_date']) ?></td>
              </tr>
              <tr>
                <th>Application Status</th>
                <td>
                  <strong style="color:<?= $docData['app_status_tone'] === 'approved' ? '#15803d' : ($docData['app_status_tone'] === 'rejected' ? '#b91c1c' : '#b45309') ?>;">
                    <?= e($docData['app_status_label']) ?>
                  </strong>
                  <?php if ($docData['app_status_sub'] !== ''): ?>
                    <span style="color:#64748b; font-size:0.84rem;">(<?= e($docData['app_status_sub']) ?>)</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr>
                <th>Payment Status</th>
                <td>
                  <strong style="color:<?= $docData['pay_status_tone'] === 'paid' ? '#15803d' : ($docData['pay_status_tone'] === 'verify' ? '#b45309' : '#991b1b') ?>;">
                    <?= e($docData['pay_status_label']) ?>
                  </strong>
                </td>
              </tr>
              <tr>
                <th>Student ID Status</th>
                <td>
                  <strong style="color:<?= $docData['student_id_tone'] === 'issued' ? '#15803d' : '#b45309' ?>;">
                    <?= e($docData['student_id_status']) ?>
                  </strong>
                </td>
              </tr>
            </tbody>
          </table>

          <p style="margin:14px 0 0; font-size:0.78rem; color:#64748b; text-align:center;">
            Privacy Notice: Sensitive personal, contact, financial transaction, and credential data are protected and omitted from public QR verification.
          </p>
        <?php else: ?>
          <div class="rmu-verif-banner-err">
            <i class="fas fa-times-circle" style="font-size:1.7rem; color:#b91c1c; flex-shrink:0;"></i>
            <div>
              <strong style="font-size:1.05rem; display:block; margin-bottom:4px;">Verification Failed / Unrecognized QR Token</strong>
              <p style="margin:0; font-size:0.9rem;"><?= e($failureReason) ?></p>
            </div>
          </div>
          <div style="margin-top:16px; text-align:center;">
            <a href="<?= APP_URL ?>/admission-status.php" class="btn-primary" style="text-decoration:none; display:inline-block;">
              Track Application Using Reference ID
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
