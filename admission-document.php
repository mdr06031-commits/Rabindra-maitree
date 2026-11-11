<?php
/**
 * Secure Admission Document Controller
 * Handles Application Card View, Printable Card, Auto Bilingual PDF View/Download,
 * and Separate Official Payment Receipt View & PDF Download.
 * Strict Authorization: Admin, Owning Logged-in Student, or Authorized Applicant Session Only.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$ref = trim((string)($_GET['ref'] ?? ''));
$doc = strtolower(trim((string)($_GET['doc'] ?? 'application')));
$action = strtolower(trim((string)($_GET['action'] ?? 'view')));

if (!in_array($doc, ['application', 'receipt'], true)) {
    $doc = 'application';
}
if (!in_array($action, ['view', 'pdf', 'download', 'print'], true)) {
    $action = 'view';
}

$db = getDb();
$adm = fetch_admission_full_record($id, $ref, $db, false);

if (!$adm) {
    http_response_code(404);
    $pageTitle = 'Application Not Found';
    include __DIR__ . '/includes/header.php';
    echo '<section class="section section-bg-white"><div class="container" style="max-width:680px;"><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><div>Admission application record was not found.</div></div></div></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

if (!can_access_admission_document($adm)) {
    http_response_code(403);
    $pageTitle = 'Access Denied';
    include __DIR__ . '/includes/header.php';
    echo '<section class="section section-bg-white"><div class="container" style="max-width:680px;"><div class="alert alert-error"><i class="fas fa-lock"></i><div><strong>403 Access Denied:</strong> You are not authorized to access this admission document. Please track your own application or log in to your Student / Admin account.</div></div><p><a href="' . APP_URL . '/admission-status.php" class="btn-primary" style="text-decoration:none;">Go to Application Tracker</a></p></div></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$isPaid = (($adm['payment_status'] ?? '') === 'Paid');

if (!$isPaid) {
    http_response_code(403);
    $payNowUrl = APP_URL . '/admission-payment.php?ref=' . urlencode((string)$adm['application_ref']);
    if ($action === 'pdf' || $action === 'download') {
        header('Content-Type: text/plain; charset=UTF-8');
        exit($doc === 'receipt'
            ? 'Payment Receipt Unavailable: Application fee payment has not been verified as Paid.'
            : 'Application Card Locked: Official Application Card cannot be generated until ৳150 payment is genuinely verified.');
    }
    $pageTitle = ($doc === 'receipt') ? 'Payment Receipt Unavailable' : 'Application Card Locked';
    include __DIR__ . '/includes/header.php';
    if ($doc === 'receipt') {
        echo '<section class="section section-bg-white"><div class="container" style="max-width:680px;"><div class="alert alert-error"><i class="fas fa-receipt"></i><div><strong>Payment Receipt Unavailable:</strong> Payment has not been verified as <strong>Paid</strong> for this application.</div></div><p><a href="' . e($payNowUrl) . '" class="btn-primary" style="background:#e11d48; text-decoration:none;"><i class="fas fa-credit-card"></i> Pay ৳150 &amp; Verify Payment</a></p></div></section>';
    } else {
        echo '<section class="section section-bg-white"><div class="container" style="max-width:680px;"><div class="alert alert-error"><i class="fas fa-lock"></i><div><strong>Application Card Locked:</strong> An Official Paid Application Card cannot be generated until the mandatory <strong>৳150 application fee</strong> is genuinely verified via the payment gateway.</div></div><p><a href="' . e($payNowUrl) . '" class="btn-primary" style="background:#e11d48; text-decoration:none;"><i class="fas fa-credit-card"></i> Pay ৳150 &amp; Generate Application Card</a></p></div></section>';
    }
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Binary PDF streaming endpoints (View PDF / Download PDF)
if ($action === 'pdf' || $action === 'download') {
    $isDownload = ($action === 'download');
    if ($doc === 'receipt') {
        output_admission_payment_receipt_pdf($adm, $isDownload, $db);
    } else {
        output_admission_application_pdf($adm, $isDownload, $db);
    }
    exit;
}

$appIdDisplay = !empty($adm['application_id']) ? (string)$adm['application_id'] : (string)$adm['application_ref'];
$pageTitle = ($doc === 'receipt')
    ? ('Payment Receipt - ' . $appIdDisplay)
    : ('Admission Application Card - ' . $appIdDisplay);
$breadcrumbTitle = ($doc === 'receipt') ? 'Official Payment Receipt' : 'Official Admission Application Card';
$breadcrumbs = [
    ['title' => 'Admission', 'url' => APP_URL . '/admissions.php'],
    ['title' => $breadcrumbTitle],
];

$docStyles = get_admission_document_styles();
$docMarkup = ($doc === 'receipt')
    ? render_admission_payment_receipt_html($adm, ['db' => $db])
    : render_premium_application_card_html($adm, ['db' => $db]);

$baseDocQuery = 'id=' . (int)$adm['id'] . '&doc=' . urlencode($doc);
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<style>
<?= $docStyles ?>
.rmu-doc-toolbar {
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 18px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}
.rmu-doc-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.rmu-act-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  border: 1px solid transparent;
}
@media print {
  .rmu-topbar, .rmu-navbar, .rmu-footer, .breadcrumb-section, .rmu-doc-toolbar, .no-print {
    display: none !important;
  }
  body, html, .section {
    background: #ffffff !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .container {
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .rmu-doc-card {
    box-shadow: none !important;
  }
}
</style>

<section class="section section-bg-white">
  <div class="container" style="max-width: 920px;">
    <div class="rmu-doc-toolbar no-print">
      <div style="font-size:0.9rem; font-weight:700; color:#0f2c59;">
        <i class="fas fa-file-alt" style="color:#c9a227;"></i>
        <?= $doc === 'receipt' ? 'Official Payment Receipt' : 'Admission Application Card' ?>
        &mdash; <span style="font-family:monospace;"><?= e($appIdDisplay) ?></span>
      </div>

      <div class="rmu-doc-actions">
        <a href="<?= APP_URL ?>/admission-document.php?<?= $baseDocQuery ?>&amp;action=pdf" target="_blank" class="rmu-act-btn" style="background:#0f2c59; color:#ffffff;">
          <i class="fas fa-file-pdf"></i> View PDF
        </a>
        <a href="<?= APP_URL ?>/admission-document.php?<?= $baseDocQuery ?>&amp;action=download" class="rmu-act-btn" style="background:#b91c1c; color:#ffffff;">
          <i class="fas fa-download"></i> Download PDF
        </a>
        <button type="button" onclick="window.print()" class="rmu-act-btn" style="background:#1e40af; color:#ffffff;">
          <i class="fas fa-print"></i> Print <?= $doc === 'receipt' ? 'Receipt' : 'Application' ?>
        </button>
        <?php if ($doc === 'application' && $isPaid): ?>
          <a href="<?= APP_URL ?>/admission-document.php?id=<?= (int)$adm['id'] ?>&amp;doc=receipt&amp;action=view" class="rmu-act-btn" style="background:#15803d; color:#ffffff;">
            <i class="fas fa-receipt"></i> Payment Receipt
          </a>
        <?php elseif ($doc === 'receipt'): ?>
          <a href="<?= APP_URL ?>/admission-document.php?id=<?= (int)$adm['id'] ?>&amp;doc=application&amp;action=view" class="rmu-act-btn" style="background:#ffffff; color:#0f2c59; border-color:#cbd5e1;">
            <i class="fas fa-id-card"></i> Application Card
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?= $docMarkup ?>
  </div>
</section>

<?php if ($action === 'print'): ?>
<script>
window.addEventListener('load', function() {
  setTimeout(function() { window.print(); }, 250);
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
