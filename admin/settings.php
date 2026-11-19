<?php
/**
 * Admin Panel: Site Settings Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['superadmin']);
$adminPageTitle = 'Site Settings';
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $fields = [
            'site_name_en', 'site_name_bn', 'campus_address', 'contact_phone',
            'contact_email', 'admission_active_term', 'admission_deadline',
            'office_hours', 'facebook_url', 'linkedin_url', 'whatsapp_url',
            'canonical_base_url', 'google_site_verification', 'site_meta_title',
            'site_meta_description', 'site_meta_keywords',
            'payment_bkash_no', 'payment_nagad_no', 'payment_rocket_no',
            'payment_merchant_account', 'payment_bank_info', 'payment_instructions'
        ];

        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($fields as $key) {
            if (isset($_POST[$key])) {
                $stmt->execute([$key, trim($_POST[$key])]);
            }
        }

        log_audit('SETTINGS_UPDATED', "Updated university site configurations.");
        flash_set('success', 'Site settings updated successfully.');
    }
    header("Location: " . APP_URL . "/admin/settings.php");
    exit;
}

// Fetch current settings
$settings = [];
$res = $db->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
foreach ($res as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

include __DIR__ . '/includes/admin-header.php';
?>

<div style="margin-bottom: 2rem;">
  <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">University Portal Settings</h2>
  <span style="color: #64748b; font-size: 0.88rem;">Configure global university branding, contact phone numbers, and active admission semester.</span>
</div>

<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2.2rem; max-width: 850px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
  <form action="<?= APP_URL ?>/admin/settings.php" method="POST">
    <?= csrf_field() ?>

    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-primary); margin-bottom: 1.25rem; border-bottom: 2px solid var(--rmu-border); padding-bottom: 0.5rem;">
      <i class="fas fa-university"></i> University Branding
    </h3>

    <div class="admin-form-2col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
      <div class="form-group">
        <label class="form-label">University Name (English)</label>
        <input type="text" name="site_name_en" value="<?= e($settings['site_name_en'] ?? '') ?>" class="form-control" required>
      </div>

      <div class="form-group">
        <label class="form-label">University Name (Bangla)</label>
        <input type="text" name="site_name_bn" value="<?= e($settings['site_name_bn'] ?? '') ?>" class="form-control" required>
      </div>
    </div>

    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-primary); margin: 1.8rem 0 1.25rem; border-bottom: 2px solid var(--rmu-border); padding-bottom: 0.5rem;">
      <i class="fas fa-phone-alt"></i> Campus Contact &amp; Hours
    </h3>

    <div class="form-group">
      <label class="form-label">Campus Physical Address</label>
      <input type="text" name="campus_address" value="<?= e($settings['campus_address'] ?? '') ?>" class="form-control" required>
    </div>

    <div class="admin-form-2col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
      <div class="form-group">
        <label class="form-label">Primary Hotline Telephone</label>
        <input type="text" name="contact_phone" value="<?= e($settings['contact_phone'] ?? '') ?>" class="form-control" required>
      </div>

      <div class="form-group">
        <label class="form-label">Official Inquiries Email</label>
        <input type="email" name="contact_email" value="<?= e($settings['contact_email'] ?? '') ?>" class="form-control" required>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Operating / Office Hours</label>
      <input type="text" name="office_hours" value="<?= e($settings['office_hours'] ?? '') ?>" class="form-control">
    </div>

    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-primary); margin: 1.8rem 0 1.25rem; border-bottom: 2px solid var(--rmu-border); padding-bottom: 0.5rem;">
      <i class="fas fa-graduation-cap"></i> Admission Intake Configuration
    </h3>

    <div class="admin-form-2col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
      <div class="form-group">
        <label class="form-label">Active Admission Term</label>
        <input type="text" name="admission_active_term" value="<?= e($settings['admission_active_term'] ?? 'Fall 2026') ?>" class="form-control" required>
      </div>

      <div class="form-group">
        <label class="form-label">Application Deadline</label>
        <input type="date" name="admission_deadline" value="<?= e($settings['admission_deadline'] ?? '') ?>" class="form-control">
      </div>
    </div>

    <!-- Payment & MFS Accounts Configuration -->
    <h3 id="payment-settings" style="font-size: 1.15rem; font-weight: 700; color: #15803d; margin: 1.8rem 0 0.5rem; border-bottom: 2px solid #86efac; padding-bottom: 0.5rem;">
      <i class="fas fa-money-check-alt"></i> Mobile Banking &amp; Payment Accounts (পেমেন্ট নম্বর সেটিংস)
    </h3>
    <p style="color: #64748b; font-size: 0.83rem; margin: 0 0 1.25rem;">
      এখানে দেওয়া বিকাশ, নগদ ও রকেট নম্বরসমূহ স্টুডেন্ট ফি পেমেন্ট ও এডমিশন রিসিটে স্বয়ংক্রিয়ভাবে প্রদর্শিত হবে।
    </p>

    <div class="admin-form-2col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
      <div class="form-group">
        <label class="form-label" style="font-weight: 700; color: #be123c;">
          <i class="fas fa-mobile-alt"></i> bKash Merchant / Personal Number
        </label>
        <input type="text" name="payment_bkash_no" value="<?= e($settings['payment_bkash_no'] ?? '01302991188') ?>" class="form-control" placeholder="01XXXXXXXXX" style="font-family: monospace; font-size: 1rem; font-weight: 600;">
        <small style="color: #64748b; font-size: 0.78rem;">স্টুডেন্ট পোর্টালে বিকাশ ফি পেমেন্টের জন্য প্রদর্শিত হবে।</small>
      </div>

      <div class="form-group">
        <label class="form-label" style="font-weight: 700; color: #c2410c;">
          <i class="fas fa-mobile-alt"></i> Nagad Merchant / Personal Number
        </label>
        <input type="text" name="payment_nagad_no" value="<?= e($settings['payment_nagad_no'] ?? '01302991188') ?>" class="form-control" placeholder="01XXXXXXXXX" style="font-family: monospace; font-size: 1rem; font-weight: 600;">
        <small style="color: #64748b; font-size: 0.78rem;">স্টুডেন্ট পোর্টালে নগদ ফি পেমেন্টের জন্য প্রদর্শিত হবে।</small>
      </div>
    </div>

    <div class="admin-form-2col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-top: 1rem;">
      <div class="form-group">
        <label class="form-label" style="font-weight: 700; color: #6d28d9;">
          <i class="fas fa-mobile-alt"></i> DBBL Rocket Account Number
        </label>
        <input type="text" name="payment_rocket_no" value="<?= e($settings['payment_rocket_no'] ?? '01302991188-7') ?>" class="form-control" placeholder="01XXXXXXXXX-X" style="font-family: monospace; font-size: 1rem; font-weight: 600;">
        <small style="color: #64748b; font-size: 0.78rem;">রকেট একাউন্ট নম্বর (১২ ডিজিট)।</small>
      </div>

      <div class="form-group">
        <label class="form-label" style="font-weight: 700; color: #0f2c59;">
          <i class="fas fa-university"></i> Official Gateway Merchant ID / No
        </label>
        <input type="text" name="payment_merchant_account" value="<?= e($settings['payment_merchant_account'] ?? '01336367336') ?>" class="form-control" placeholder="01XXXXXXXXX" style="font-family: monospace; font-size: 1rem; font-weight: 600;">
        <small style="color: #64748b; font-size: 0.78rem;">এডমিশন পেমেন্ট ও অফিসিয়াল গেটওয়ে রিসিটের মার্চেন্ট আইডি।</small>
      </div>
    </div>

    <div class="form-group" style="margin-top: 1rem;">
      <label class="form-label" style="font-weight: 700; color: #1e40af;">
        <i class="fas fa-university"></i> Bank Direct Transfer Account Details
      </label>
      <input type="text" name="payment_bank_info" value="<?= e($settings['payment_bank_info'] ?? 'Dutch-Bangla Bank Ltd, Kushtia Branch. A/C: 115-120-998822') ?>" class="form-control" placeholder="Bank Name, Branch, Account Number">
      <small style="color: #64748b; font-size: 0.78rem;">ব্যাংক ট্রান্সফারের মাধ্যমে টিউশন ফি দেওয়ার নির্দেশাবলি।</small>
    </div>

    <div class="form-group" style="margin-top: 1rem;">
      <label class="form-label" style="font-weight: 700; color: #334155;">
        <i class="fas fa-info-circle"></i> Payment Guidelines / Instructions
      </label>
      <textarea name="payment_instructions" class="form-control" rows="2" placeholder="পেমেন্ট করার নিয়ম বা বিশেষ নির্দেশনা"><?= e($settings['payment_instructions'] ?? 'পেমেন্ট করার সময় রেফারেন্সে অবশ্যই আপনার Student ID অথবা Admission Roll নম্বর লিখবেন।') ?></textarea>
    </div>

    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-primary); margin: 1.8rem 0 1.25rem; border-bottom: 2px solid var(--rmu-border); padding-bottom: 0.5rem;">
      <i class="fas fa-share-alt"></i> Verified Social Media Profiles
    </h3>

    <div class="admin-form-3col" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
      <div class="form-group">
        <label class="form-label">Facebook URL</label>
        <input type="url" name="facebook_url" value="<?= e($settings['facebook_url'] ?? '') ?>" class="form-control">
      </div>

      <div class="form-group">
        <label class="form-label">LinkedIn URL</label>
        <input type="url" name="linkedin_url" value="<?= e($settings['linkedin_url'] ?? '') ?>" class="form-control">
      </div>

      <div class="form-group">
        <label class="form-label">WhatsApp URL</label>
        <input type="url" name="whatsapp_url" value="<?= e($settings['whatsapp_url'] ?? '') ?>" class="form-control">
      </div>
    </div>

    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-primary); margin: 1.8rem 0 1.25rem; border-bottom: 2px solid var(--rmu-border); padding-bottom: 0.5rem;">
      <i class="fas fa-search"></i> Search Engine Optimization (SEO) &amp; Google Indexing
    </h3>

    <div class="admin-form-2col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
      <div class="form-group">
        <label class="form-label">Canonical Production Domain URL</label>
        <input type="url" name="canonical_base_url" value="<?= e($settings['canonical_base_url'] ?? 'https://rmu.ac.bd') ?>" class="form-control" placeholder="https://rmu.ac.bd">
        <small style="color: #64748b; font-size: 0.78rem;">Canonical domain submitted to Google Search Console.</small>
      </div>

      <div class="form-group">
        <label class="form-label">Google Site Verification Code / Token</label>
        <input type="text" name="google_site_verification" value="<?= e($settings['google_site_verification'] ?? '') ?>" class="form-control" placeholder="e.g. google-site-verification=abc...">
        <small style="color: #64748b; font-size: 0.78rem;">Enter the verification token provided by Google Search Console.</small>
      </div>
    </div>

    <div class="form-group" style="margin-top: 1rem;">
      <label class="form-label">Default SEO Meta Title</label>
      <input type="text" name="site_meta_title" value="<?= e($settings['site_meta_title'] ?? 'Rabindra Maitree University - Center of Academic Excellence') ?>" class="form-control">
    </div>

    <div class="form-group">
      <label class="form-label">Default Meta Description</label>
      <textarea name="site_meta_description" class="form-control" rows="2"><?= e($settings['site_meta_description'] ?? 'Rabindra Maitree University (RMU), Kushtia - A leading center for higher education, arts, engineering, and cultural enlightenment approved by UGC.') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">SEO Meta Keywords (comma separated)</label>
      <input type="text" name="site_meta_keywords" value="<?= e($settings['site_meta_keywords'] ?? 'Rabindra Maitree University, RMU, RMU Kushtia, UGC Approved University, University Admission Bangladesh, Engineering, CSE, BBA, English') ?>" class="form-control">
    </div>

    <div style="margin-top: 2rem; border-top: 1px solid var(--rmu-border); padding-top: 1.5rem;">
      <button type="submit" class="btn-primary" style="padding: 0.85rem 2rem; font-size: 1rem;">
        <i class="fas fa-save"></i> Save Global Configurations
      </button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

