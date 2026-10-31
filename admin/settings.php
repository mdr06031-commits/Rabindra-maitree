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
            'office_hours', 'facebook_url', 'linkedin_url', 'whatsapp_url'
        ];

        $stmt = $db->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($fields as $key) {
            if (isset($_POST[$key])) {
                $stmt->execute([trim($_POST[$key]), $key]);
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

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
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

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
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

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
      <div class="form-group">
        <label class="form-label">Active Admission Term</label>
        <input type="text" name="admission_active_term" value="<?= e($settings['admission_active_term'] ?? 'Fall 2026') ?>" class="form-control" required>
      </div>

      <div class="form-group">
        <label class="form-label">Application Deadline</label>
        <input type="date" name="admission_deadline" value="<?= e($settings['admission_deadline'] ?? '') ?>" class="form-control">
      </div>
    </div>

    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-primary); margin: 1.8rem 0 1.25rem; border-bottom: 2px solid var(--rmu-border); padding-bottom: 0.5rem;">
      <i class="fas fa-share-alt"></i> Verified Social Media Profiles
    </h3>

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
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

    <div style="margin-top: 2rem; border-top: 1px solid var(--rmu-border); padding-top: 1.5rem;">
      <button type="submit" class="btn-primary" style="padding: 0.85rem 2rem; font-size: 1rem;">
        <i class="fas fa-save"></i> Save Global Configurations
      </button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

