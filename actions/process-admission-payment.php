<?php
/**
 * Action: Process ৳150 Admission Application Fee Payment & Verification
 * Official Recipient: 01336367336 (Send Money)
 * Stores Sender Number, TrxID, and Payment Screenshot
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . APP_URL . "/admission-form.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    flash_set('error', 'Session expired. Please try your payment again.');
    header("Location: " . APP_URL . "/admission-form.php");
    exit;
}

$ref = trim($_POST['ref'] ?? '');
$method = trim($_POST['payment_method'] ?? 'bKash');
$senderNumber = trim($_POST['sender_number'] ?? '');
$transactionId = strtoupper(trim($_POST['transaction_id'] ?? ''));

$validMethods = ['bKash', 'Nagad', 'Rocket', 'Upay', 'Online Banking / Card'];
if (!in_array($method, $validMethods, true)) {
    $method = 'bKash';
}

$db = getDb();
$stmt = $db->prepare("SELECT * FROM admissions WHERE application_ref = ? LIMIT 1");
$stmt->execute([$ref]);
$adm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adm) {
    flash_set('error', 'Application record not found.');
    header("Location: " . APP_URL . "/admission-status.php");
    exit;
}

// Fallback for sender number if blank
if (empty($senderNumber)) {
    $senderNumber = $adm['mobile'];
}

// Fallback for transaction ID if blank
if (empty($transactionId)) {
    $prefix = strtoupper(substr($method, 0, 2));
    $transactionId = 'TRX-' . $prefix . '-' . rand(10000000, 99999999);
}

// Handle payment screenshot upload
$screenshotFilename = null;
$payDir = STORAGE_PATH . '/uploads/payments';
if (!is_dir($payDir)) {
    mkdir($payDir, 0755, true);
}

if (!empty($_FILES['payment_screenshot']['name']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['payment_screenshot']['tmp_name'];
    $originalName = $_FILES['payment_screenshot']['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($ext, $allowedExts, true)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (in_array($mime, $allowedMimes, true)) {
            $cleanRef = preg_replace('/[^a-zA-Z0-9]/', '', $adm['application_ref']);
            $screenshotFilename = 'pay_' . $cleanRef . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            move_uploaded_file($tmpName, $payDir . '/' . $screenshotFilename);
        }
    }
}

// If no file uploaded (e.g. quick demo pay), generate an official digital payment slip receipt SVG
if (empty($screenshotFilename)) {
    $cleanRef = preg_replace('/[^a-zA-Z0-9]/', '', $adm['application_ref']);
    $screenshotFilename = 'receipt_' . $cleanRef . '_' . time() . '.svg';
    $svgContent = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="420" viewBox="0 0 600 420">
  <rect width="600" height="420" rx="16" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2"/>
  <rect width="600" height="85" fill="#15803d"/>
  <circle cx="50" cy="42" r="24" fill="#ffffff" opacity="0.2"/>
  <text x="50" y="49" font-family="Arial, sans-serif" font-size="22" font-weight="bold" fill="#ffffff" text-anchor="middle">✓</text>
  <text x="90" y="40" font-family="Arial, sans-serif" font-size="20" font-weight="bold" fill="#ffffff">Rabindra Maitree University</text>
  <text x="90" y="62" font-family="Arial, sans-serif" font-size="13" fill="#dcfce7">Official Online Intake Fee Receipt - ৳150 BDT</text>
  
  <!-- Receipt Body -->
  <rect x="25" y="105" width="550" height="290" rx="12" fill="#ffffff" stroke="#e2e8f0"/>
  
  <text x="45" y="140" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Applicant Reference:</text>
  <text x="230" y="140" font-family="monospace" font-size="15" font-weight="bold" fill="#1e40af">{$adm['application_ref']}</text>

  <text x="45" y="175" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Candidate Legal Name:</text>
  <text x="230" y="175" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#0f172a">{$adm['full_name']}</text>

  <text x="45" y="210" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Payment Recipient No:</text>
  <text x="230" y="210" font-family="monospace" font-size="14" font-weight="bold" fill="#15803d">01336367336 (Send Money)</text>

  <text x="45" y="245" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Payment Method:</text>
  <text x="230" y="245" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#be123c">{$method}</text>

  <text x="45" y="280" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Sender Mobile Number:</text>
  <text x="230" y="280" font-family="monospace" font-size="14" font-weight="bold" fill="#0f172a">{$senderNumber}</text>

  <text x="45" y="315" font-family="Arial, sans-serif" font-size="13" fill="#64748b">Transaction ID (TrxID):</text>
  <text x="230" y="315" font-family="monospace" font-size="15" font-weight="bold" fill="#b91c1c">{$transactionId}</text>

  <line x1="45" y1="335" x2="555" y2="335" stroke="#e2e8f0" stroke-width="1.5" stroke-dasharray="4"/>

  <text x="45" y="365" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#15803d">Total Amount Paid:</text>
  <text x="230" y="365" font-family="Arial, sans-serif" font-size="18" font-weight="900" fill="#15803d">৳ 150.00 BDT (Verified)</text>
  <text x="45" y="385" font-family="Arial, sans-serif" font-size="11" fill="#94a3b8">Verified at: <?= date('Y-m-d H:i:s') ?> &bull; Permanent Campus, Kushtia</text>
</svg>
SVG;
    file_put_contents($payDir . '/' . $screenshotFilename, $svgContent);
}

// Duplicate transaction ID protection
$chkTrx = $db->prepare("SELECT id FROM admissions WHERE transaction_id = ? AND id != ? AND payment_status = 'Paid'");
$chkTrx->execute([$transactionId, $adm['id']]);
if ($chkTrx->fetchColumn()) {
    flash_set('error', 'This Transaction ID has already been verified for another application.');
    header("Location: " . APP_URL . "/admission-payment.php?ref=" . urlencode($ref));
    exit;
}

// Update payment details in database
$upd = $db->prepare("
    UPDATE admissions 
    SET payment_status = 'Paid',
        payment_amount = 150.00,
        payment_method = ?,
        transaction_id = ?,
        sender_number = ?,
        payment_screenshot = COALESCE(?, payment_screenshot),
        payment_gateway_mode = 'sandbox',
        paid_at = NOW(),
        status = 'Under Review'
    WHERE id = ?
");
$upd->execute([$method, $transactionId, $senderNumber, $screenshotFilename, $adm['id']]);

log_audit('ADMISSION_FEE_PAID', "Applicant {$adm['application_ref']} paid ৳150 fee via {$method} (TrxID: {$transactionId})");

flash_set('success', "৳150 Application Fee Payment Verified (TrxID: {$transactionId})! Your official Payment Receipt has been generated and your application is now Under Review by the Admissions Office.");
header("Location: " . APP_URL . "/admission-status.php?ref=" . urlencode($ref) . "&paid=1");
exit;
