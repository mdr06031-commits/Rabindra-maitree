<?php
/**
 * Online Admission Fee Payment (৳150 BDT)
 * Official Recipient: 01336367336 (Send Money)
 * Integrates bKash, Nagad, Rocket & Upay mobile payment verification with screenshot upload & TrxID
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$ref = trim($_GET['ref'] ?? '');
$db = getDb();

$stmt = $db->prepare("SELECT * FROM admissions WHERE application_ref = ? LIMIT 1");
$stmt->execute([$ref]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    flash_set('error', 'Invalid application reference. Please check your tracking number.');
    header("Location: " . APP_URL . "/admission-status.php");
    exit;
}

// If already paid, redirect straight to status / admit card page
if (($application['payment_status'] ?? '') === 'Paid') {
    flash_set('info', 'Application fee of ৳150 is already paid. You can print your Application Card below.');
    header("Location: " . APP_URL . "/admission-status.php?ref=" . urlencode($ref));
    exit;
}

$pageTitle = 'Admission Fee Payment (৳150)';
$breadcrumbTitle = 'Online Application Fee Payment';
$breadcrumbs = [
    ['title' => 'Admission', 'url' => APP_URL . '/admissions.php'],
    ['title' => 'Application Form', 'url' => APP_URL . '/admission-form.php'],
    ['title' => 'Fee Payment']
];

$officialPaymentNumber = '01336367336';

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 860px;">
    
    <div class="section-header" style="margin-bottom: 2rem;">
      <span class="section-subtitle">Intake Fee Checkout</span>
      <h2 class="section-title">Admission Application Fee Payment</h2>
      <div class="section-line"></div>
      <p style="color: var(--rmu-muted); margin-top: 0.8rem; font-size: 0.95rem;">
        Complete the mandatory <strong>৳150 Application Fee</strong> payment to instantly generate your official <strong>Application Card (with your photograph)</strong> and activate your <strong>Student Portal ID &amp; Password</strong>.
      </p>
    </div>

    <!-- Application Summary Invoice Card -->
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.06); padding:1.5rem 2rem; margin-bottom:2rem;">
      <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #f1f5f9; padding-bottom:1rem; margin-bottom:1.2rem; flex-wrap:wrap; gap:1rem;">
        <div>
          <span style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; font-weight:700;">Applicant Reference</span>
          <div style="font-size:1.35rem; font-weight:800; color:var(--rmu-primary);"><?= e($application['application_ref']) ?></div>
        </div>
        <div style="text-align:right;">
          <span style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:4px 12px; border-radius:20px; font-weight:700; font-size:0.82rem;">
            <i class="fas fa-clock"></i> PAYMENT PENDING
          </span>
          <div style="font-size:0.78rem; color:#64748b; margin-top:3px;">Date: <?= format_date($application['created_at']) ?></div>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; font-size:0.9rem; margin-bottom:1.5rem;">
        <div>
          <span style="color:#64748b; display:block; font-size:0.8rem;">Candidate Legal Name</span>
          <strong style="color:#0f172a; font-size:1rem;"><?= e($application['full_name']) ?></strong>
        </div>
        <div>
          <span style="color:#64748b; display:block; font-size:0.8rem;">Contact Mobile Number</span>
          <strong style="color:#0f172a; font-size:1rem;"><?= e($application['mobile']) ?></strong>
        </div>
        <div>
          <span style="color:#64748b; display:block; font-size:0.8rem;">Selected Academic Program</span>
          <strong style="color:var(--rmu-accent); font-size:0.95rem;"><?= e($application['interested_subject']) ?></strong>
        </div>
      </div>

      <!-- Amount Banner -->
      <div style="background:#f8fafc; border:1.5px dashed #cbd5e1; border-radius:8px; padding:1rem 1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
        <div>
          <div style="font-weight:700; color:#1e293b; font-size:0.95rem;">Admission Application Fee &amp; Exam Slip</div>
          <small style="color:#64748b;">Includes Application Card Generation + Student Portal ID Provisioning</small>
        </div>
        <div style="text-align:right;">
          <div style="font-size:1.6rem; font-weight:900; color:#15803d;">৳ 150.00 <span style="font-size:0.85rem; font-weight:600; color:#64748b;">BDT</span></div>
        </div>
      </div>
    </div>

    <!-- ============================================================== -->
    <!-- ⭐ OFFICIAL RECIPIENT NUMBER & SEND MONEY INSTRUCTIONS CARD       -->
    <!-- ============================================================== -->
    <div style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border: 2px solid #86efac; border-radius: 12px; padding: 1.8rem; margin-bottom: 2rem; box-shadow: 0 4px 10px rgba(22,163,74,0.08);">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.2rem; border-bottom:1.5px dashed #bbf7d0; padding-bottom:1rem;">
        <div>
          <span style="background:#16a34a; color:#ffffff; padding:3px 12px; border-radius:20px; font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">
            <i class="fas fa-shield-alt"></i> Official Payment Gateway
          </span>
          <h3 style="font-size:1.3rem; font-weight:800; color:#14532d; margin:0.5rem 0 0.2rem;">
            টাকা পাঠানোর অফিশিয়াল নম্বর: <strong><?= $officialPaymentNumber ?></strong>
          </h3>
          <p style="margin:0; font-size:0.9rem; color:#166534;">
            নিচের নম্বরে <strong>৳ ১৫০</strong> টাকা <strong>Send Money (সেন্ড মানি)</strong> করুন এবং ট্রানজেকশন আইডি ও স্ক্রিনশট দিয়ে পেমেন্ট নিশ্চিত করুন:
          </p>
        </div>

        <!-- Copy Box -->
        <div style="background:#ffffff; border:2px solid #22c55e; border-radius:10px; padding:0.6rem 1rem; display:flex; align-items:center; gap:0.75rem; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
          <div>
            <span style="font-size:0.72rem; color:#64748b; font-weight:700; text-transform:uppercase; display:block;">Send Money Number</span>
            <span id="targetNumber" style="font-family:monospace; font-size:1.3rem; font-weight:900; color:#15803d; letter-spacing:1px;"><?= $officialPaymentNumber ?></span>
          </div>
          <button type="button" onclick="copyNumber()" id="copyBtn" style="background:#16a34a; color:#ffffff; border:none; border-radius:6px; padding:0.45rem 0.8rem; font-size:0.82rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:4px; transition:all 0.2s ease;">
            <i class="fas fa-copy"></i> <span>কপি করুন</span>
          </button>
        </div>
      </div>

      <!-- Step-by-Step Instructions -->
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; font-size:0.85rem; color:#14532d;">
        <div style="background:#ffffff; padding:10px 12px; border-radius:8px; border:1px solid #bbf7d0;">
          <strong style="display:block; color:#15803d; margin-bottom:3px;"><i class="fas fa-mobile-alt"></i> ধাপ ১: Send Money</strong>
          bKash / Nagad / Rocket অ্যাপে গিয়ে <strong>Send Money</strong> সিলেক্ট করুন।
        </div>
        <div style="background:#ffffff; padding:10px 12px; border-radius:8px; border:1px solid #bbf7d0;">
          <strong style="display:block; color:#15803d; margin-bottom:3px;"><i class="fas fa-phone"></i> ধাপ ২: প্রাপক নম্বর</strong>
          প্রাপক নম্বর দিন: <code style="font-size:0.95rem; font-weight:bold; color:#16a34a;"><?= $officialPaymentNumber ?></code>
        </div>
        <div style="background:#ffffff; padding:10px 12px; border-radius:8px; border:1px solid #bbf7d0;">
          <strong style="display:block; color:#15803d; margin-bottom:3px;"><i class="fas fa-receipt"></i> ধাপ ৩: রেফারেন্স</strong>
          পরিমাণ <strong>৳ ১৫০</strong> এবং রেফারেন্সে <code><?= e($application['application_ref']) ?></code> দিন।
        </div>
        <div style="background:#ffffff; padding:10px 12px; border-radius:8px; border:1px solid #bbf7d0;">
          <strong style="display:block; color:#15803d; margin-bottom:3px;"><i class="fas fa-camera"></i> ধাপ ৪: স্ক্রিনশট ও TrxID</strong>
          পেমেন্ট শেষের <strong>TrxID</strong> কপি করুন ও কনফার্মেশনের <strong>স্ক্রিনশট</strong> নিন।
        </div>
      </div>
    </div>

    <!-- ============================================================== -->
    <!-- ⭐ PAYMENT SUBMISSION FORM                                      -->
    <!-- ============================================================== -->
    <div style="background:#ffffff; border:1.5px solid #dbeafe; border-radius:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.06); padding:2rem; margin-bottom:2rem;">
      <h3 style="font-size:1.15rem; font-weight:700; color:#0f172a; margin-bottom:0.4rem;">
        <i class="fas fa-wallet" style="color:var(--rmu-primary);"></i> Select Payment Method &amp; Verify
      </h3>
      <p style="color:#64748b; font-size:0.88rem; margin-bottom:1.5rem;">
        টাকা পাঠানোর পর আপনি যে মেথড ব্যবহার করেছেন তা বেছে নিন এবং নিচের তথ্যগুলো পূরণ করে পেমেন্ট যাচাই করুন:
      </p>

      <form action="<?= APP_URL ?>/actions/process-admission-payment.php" method="POST" enctype="multipart/form-data" id="admissionPayForm">
        <?= csrf_field() ?>
        <input type="hidden" name="ref" value="<?= e($application['application_ref']) ?>">

        <!-- Method Selection Tabs -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:10px; margin-bottom:1.5rem;">
          
          <!-- bKash -->
          <label style="border:2px solid #e11d48; background:#fff1f2; border-radius:8px; padding:12px; text-align:center; cursor:pointer; display:block; transition:all 0.2s ease;">
            <input type="radio" name="payment_method" value="bKash" checked style="accent-color:#e11d48; margin-right:4px;">
            <strong style="color:#be123c; font-size:0.95rem; display:block; margin-top:4px;">bKash</strong>
            <small style="font-size:0.75rem; color:#881337;">Popular MFS</small>
          </label>

          <!-- Nagad -->
          <label style="border:1.5px solid #fdba74; background:#fff7ed; border-radius:8px; padding:12px; text-align:center; cursor:pointer; display:block; transition:all 0.2s ease;">
            <input type="radio" name="payment_method" value="Nagad" style="accent-color:#ea580c; margin-right:4px;">
            <strong style="color:#c2410c; font-size:0.95rem; display:block; margin-top:4px;">Nagad</strong>
            <small style="font-size:0.75rem; color:#7c2d12;">Postal MFS</small>
          </label>

          <!-- Rocket -->
          <label style="border:1.5px solid #d8b4fe; background:#faf5ff; border-radius:8px; padding:12px; text-align:center; cursor:pointer; display:block; transition:all 0.2s ease;">
            <input type="radio" name="payment_method" value="Rocket" style="accent-color:#9333ea; margin-right:4px;">
            <strong style="color:#7e22ce; font-size:0.95rem; display:block; margin-top:4px;">Rocket</strong>
            <small style="font-size:0.75rem; color:#581c87;">DBBL MFS</small>
          </label>

          <!-- Upay -->
          <label style="border:1.5px solid #93c5fd; background:#eff6ff; border-radius:8px; padding:12px; text-align:center; cursor:pointer; display:block; transition:all 0.2s ease;">
            <input type="radio" name="payment_method" value="Upay" style="accent-color:#2563eb; margin-right:4px;">
            <strong style="color:#1d4ed8; font-size:0.95rem; display:block; margin-top:4px;">Upay</strong>
            <small style="font-size:0.75rem; color:#1e3a8a;">UCB MFS</small>
          </label>
        </div>

        <!-- 1. Sender Mobile Number Input -->
        <div class="form-group" style="margin-bottom:1.25rem;">
          <label class="form-label" for="senderNumber" style="font-weight:700; color:#1e293b;">
            যে নম্বর থেকে টাকা পাঠিয়েছেন (Sender Mobile Number) <span class="required" style="color:#e11d48;">*</span>
          </label>
          <div style="position:relative;">
            <input type="tel" name="sender_number" id="senderNumber" class="form-control" placeholder="01XXXXXXXXX" value="<?= e($application['mobile']) ?>" required maxlength="15" style="padding-left:2.5rem; font-size:1rem; font-weight:600;">
            <i class="fas fa-phone-alt" style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:#94a3b8;"></i>
          </div>
          <small style="color:#64748b; font-size:0.8rem;">টাকা পাঠানোর কাজে ব্যবহৃত আপনার ১১-ডিজিট মোবাইল অ্যাকাউন্ট নম্বরটি লিখুন।</small>
        </div>

        <!-- 2. Transaction ID Input -->
        <div class="form-group" style="margin-bottom:1.25rem;">
          <label class="form-label" for="transactionId" style="font-weight:700; color:#1e293b;">
            ট্রানজেকশন আইডি (Transaction ID / TrxID) <span class="required" style="color:#e11d48;">*</span>
          </label>
          <div style="position:relative;">
            <input type="text" name="transaction_id" id="transactionId" class="form-control" placeholder="যেমন: 9B78K3L2 অথবা BL87XQ01" required maxlength="50" style="padding-left:2.5rem; font-family:monospace; font-size:1.05rem; font-weight:700; letter-spacing:1px; text-transform:uppercase;">
            <i class="fas fa-hashtag" style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:#94a3b8;"></i>
          </div>
          <small style="color:#64748b; font-size:0.8rem;">টাকা পাঠানোর পর বিকাশ/নগদ ফিরতি SMS বা অ্যাপে প্রাপ্ত Transaction ID (TrxID) টি লিখুন।</small>
        </div>

        <!-- 3. Payment Confirmation Screenshot Upload -->
        <div class="form-group" style="margin-bottom:1.75rem;">
          <label class="form-label" style="font-weight:700; color:#1e293b;">
            পেমেন্ট কনফার্মেশন স্ক্রিনশট (Payment Screenshot) <span class="required" style="color:#e11d48;">*</span>
          </label>
          
          <div style="border:2px dashed #93c5fd; background:#f8fafc; border-radius:10px; padding:1.25rem; text-align:center; transition:all 0.2s ease;" id="dropArea">
            <input type="file" name="payment_screenshot" id="paymentScreenshot" accept="image/jpeg,image/png,image/webp,image/jpg" style="display:none;" onchange="previewScreenshot(event)">
            
            <div id="uploadPrompt">
              <i class="fas fa-cloud-upload-alt" style="font-size:2.5rem; color:#3b82f6; margin-bottom:0.5rem; display:block;"></i>
              <label for="paymentScreenshot" style="background:#2563eb; color:#ffffff; padding:0.5rem 1.4rem; border-radius:6px; font-weight:700; font-size:0.9rem; cursor:pointer; display:inline-block; margin-bottom:0.4rem;">
                <i class="fas fa-image"></i> স্ক্রিনশট নির্বাচন করুন
              </label>
              <div style="font-size:0.8rem; color:#64748b;">JPG, PNG অথবা WEBP ফরম্যাট (সর্বোচ্চ 5MB)</div>
            </div>

            <!-- Live Image Preview Container -->
            <div id="previewContainer" style="display:none; margin-top:0.75rem;">
              <div style="display:inline-block; position:relative; border:2px solid #22c55e; border-radius:8px; overflow:hidden; box-shadow:0 4px 8px rgba(0,0,0,0.1); background:#ffffff;">
                <img id="screenshotPreview" src="" alt="Payment Screenshot Preview" style="max-height:180px; max-width:100%; display:block;">
                <button type="button" onclick="clearScreenshot()" style="position:absolute; top:6px; right:6px; background:#ef4444; color:#ffffff; border:none; border-radius:50%; width:28px; height:28px; cursor:pointer; font-size:0.85rem; display:flex; align-items:center; justify-content:center;">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <div id="previewFileName" style="font-size:0.82rem; color:#15803d; font-weight:700; margin-top:6px;"></div>
            </div>
          </div>
          <small style="color:#64748b; font-size:0.8rem; display:block; margin-top:4px;">Send Money সম্পন্ন হওয়ার অ্যাপের কনফার্মেশন স্ক্রিন অথবা SMS এর পরিষ্কার স্ক্রিনশট দিন।</small>
        </div>

        <!-- Payment Actions -->
        <div style="display:flex; flex-direction:column; gap:0.75rem;">
          <button type="submit" class="btn-primary" style="background:#e11d48; width:100%; justify-content:center; padding:0.95rem; font-size:1.05rem; font-weight:800; border-radius:8px; box-shadow:0 4px 10px rgba(225,29,72,0.25);">
            <i class="fas fa-check-circle"></i> Pay ৳150 &amp; Generate Application Card
          </button>
          
          <button type="button" onclick="quickPayDemo()" class="btn-primary" style="background:#15803d; width:100%; justify-content:center; padding:0.85rem; font-size:0.95rem; border-radius:8px;">
            <i class="fas fa-bolt"></i> Instant 1-Click Demo Pay ৳150 (Auto-Fill &amp; Verify)
          </button>
        </div>
      </form>
    </div>

    <!-- Security & Instruction Note -->
    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:1.2rem; font-size:0.88rem; color:#1e40af;">
      <div style="font-weight:700; margin-bottom:4px;"><i class="fas fa-info-circle"></i> What happens after paying ৳150?</div>
      <ul style="margin:0.4rem 0 0 1.2rem; line-height:1.6;">
        <li>Your official <strong>Application Card</strong> with your color photo, TrxID, and sender mobile will be instantly generated.</li>
        <li>Your unique <strong>Student ID (e.g., RMU260100X)</strong> and <strong>Student Portal Password</strong> will be issued.</li>
        <li>You can immediately log in to the <strong>RMU Student Portal</strong> to view your enrolled courses, class routine, and profile!</li>
      </ul>
    </div>

  </div>
</section>

<script>
function copyNumber() {
  const num = '<?= $officialPaymentNumber ?>';
  navigator.clipboard.writeText(num).then(() => {
    const btn = document.getElementById('copyBtn');
    btn.innerHTML = '<i class="fas fa-check"></i> <span>কপি হয়েছে!</span>';
    btn.style.background = '#15803d';
    setTimeout(() => {
      btn.innerHTML = '<i class="fas fa-copy"></i> <span>কপি করুন</span>';
      btn.style.background = '#16a34a';
    }, 2500);
  }).catch(() => {
    alert('নম্বর: ' + num);
  });
}

function previewScreenshot(event) {
  const file = event.target.files[0];
  if (file) {
    if (file.size > 5 * 1024 * 1024) {
      alert('ফাইলের সাইজ সর্বোচ্চ ৫ মেগাবাইট হতে পারবে।');
      event.target.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('screenshotPreview').src = e.target.result;
      document.getElementById('previewContainer').style.display = 'block';
      document.getElementById('uploadPrompt').style.display = 'none';
      document.getElementById('previewFileName').innerText = '✓ ' + file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
    };
    reader.readAsDataURL(file);
  }
}

function clearScreenshot() {
  const input = document.getElementById('paymentScreenshot');
  input.value = '';
  document.getElementById('previewContainer').style.display = 'none';
  document.getElementById('uploadPrompt').style.display = 'block';
  document.getElementById('screenshotPreview').src = '';
}

function quickPayDemo() {
  const form = document.getElementById('admissionPayForm');
  // Fill sample Sender Number
  if (!document.getElementById('senderNumber').value) {
    document.getElementById('senderNumber').value = '<?= e($application['mobile']) ?>';
  }
  // Fill sample Transaction ID
  const demoTrx = 'TRX' + Math.floor(10000000 + Math.random() * 90000000);
  document.getElementById('transactionId').value = demoTrx;
  
  // Submit form
  form.submit();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
