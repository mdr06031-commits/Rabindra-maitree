<?php
/**
 * Admin Panel: In-App Notification Dispatcher & Audit Log
 * Rabindra Maitree University (RMU)
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Notifications';
$db = getDb();

// Handle Actions (Broadcast Notification, Individual Notification, Delete Notification)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'send_notification') {
            $target = $_POST['target'] ?? 'all'; // 'all' or student_id
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $type = in_array($_POST['type'] ?? '', ['result_published', 'grade_correction', 'request_update', 'general']) ? $_POST['type'] : 'general';

            if (!empty($title) && !empty($message)) {
                if ($target === 'all') {
                    $studentIds = $db->query("SELECT id FROM students WHERE status = 'Active'")->fetchAll(PDO::FETCH_COLUMN);
                    $ins = $db->prepare("INSERT INTO student_notifications (student_id, title, message, type) VALUES (?, ?, ?, ?)");
                    foreach ($studentIds as $sId) {
                        $ins->execute([$sId, $title, $message, $type]);
                    }
                    log_audit('NOTIFICATION_BROADCAST', "Broadcasted '{$title}' to " . count($studentIds) . " active students");
                    flash_set('success', "Notification broadcasted successfully to all " . count($studentIds) . " active students.");
                } else {
                    $stId = (int)$target;
                    create_student_notification($stId, $title, $message, $type);
                    log_audit('NOTIFICATION_SENT', "Sent notification '{$title}' to Student #{$stId}");
                    flash_set('success', "Notification dispatched successfully to student.");
                }
            } else {
                flash_set('error', 'Title and message are required.');
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM student_notifications WHERE id = ?")->execute([$id]);
                flash_set('success', "Notification record removed.");
            }
        }
    }
    header("Location: " . APP_URL . "/admin/notifications.php");
    exit;
}

// Fetch active students for selector
$allStudents = $db->query("SELECT id, student_id, full_name FROM students ORDER BY student_id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent notifications
$notifications = $db->query("
    SELECT sn.*, s.student_id as st_code, s.full_name as st_name 
    FROM student_notifications sn
    JOIN students s ON sn.student_id = s.id
    ORDER BY sn.id DESC LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem;">
      <i class="fas fa-bell" style="color:var(--rmu-primary);"></i> In-App Notification Center
    </h2>
    <p style="color:#64748b; font-size:0.88rem; margin:0;">
      Dispatch Announcements, Result Releases &amp; Academic Inquiries to Student Portals
    </p>
  </div>

  <button type="button" onclick="openNewNotifModal()" class="btn-primary" style="padding:0.55rem 1.1rem; font-size:0.86rem;">
    <i class="fas fa-paper-plane"></i> Dispatch New Notification
  </button>
</div>

<!-- Notifications History Table -->
<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05);">
  <div style="padding:1rem 1.25rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
    <strong style="font-size:0.95rem; color:#0f172a;">
      <i class="fas fa-history" style="color:var(--rmu-primary);"></i> Sent Student Notifications (<?= count($notifications) ?> logged)
    </strong>
  </div>

  <div class="table-responsive">
    <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.88rem;">
      <thead>
        <tr>
          <th>Student Recipient</th>
          <th>Notification Title &amp; Details</th>
          <th>Category</th>
          <th>Status</th>
          <th>Timestamp</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($notifications)): ?>
          <tr><td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">No notification history recorded yet.</td></tr>
        <?php else: ?>
          <?php foreach ($notifications as $n): ?>
            <tr>
              <td>
                <strong style="color:#0f172a;"><?= e($n['st_name']) ?></strong>
                <div style="font-family:monospace; color:var(--rmu-primary); font-size:0.82rem; font-weight:700;"><?= e($n['st_code']) ?></div>
              </td>
              <td style="max-width:380px;">
                <strong style="color:#1e3a8a; display:block;"><?= e($n['title']) ?></strong>
                <span style="color:#475569; font-size:0.82rem;"><?= e($n['message']) ?></span>
              </td>
              <td>
                <span style="background:#eff6ff; color:#1e40af; padding:2px 8px; border-radius:4px; font-size:0.75rem; font-weight:700;">
                  <?= e($n['type']) ?>
                </span>
              </td>
              <td>
                <span style="color:<?= $n['is_read'] ? '#15803d' : '#b45309' ?>; font-weight:700; font-size:0.75rem;">
                  <?= $n['is_read'] ? '✓ Read' : '● Unread' ?>
                </span>
              </td>
              <td style="font-size:0.8rem; color:#64748b;">
                <?= format_date($n['created_at']) ?>
              </td>
              <td style="text-align:right;">
                <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Delete this notification log?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                  <button type="submit" style="background:#fee2e2; color:#ef4444; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Dispatch Notification -->
<div id="newNotifModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:2000; align-items:center; justify-content:center; padding:1rem;">
  <div style="background:#ffffff; border-radius:10px; max-width:480px; width:100%; padding:1.5rem; box-shadow:0 15px 30px rgba(0,0,0,0.25);">
    <h3 style="margin:0 0 1rem; font-size:1.15rem; color:#0f2c59; font-weight:800;">
      <i class="fas fa-paper-plane" style="color:var(--rmu-primary);"></i> Dispatch In-App Notification
    </h3>
    <form action="" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send_notification">

      <div class="form-group">
        <label class="form-label">Recipient *</label>
        <select name="target" class="form-select" required>
          <option value="all">📢 Broadcast to All Active Students</option>
          <optgroup label="Individual Students">
            <?php foreach ($allStudents as $st): ?>
              <option value="<?= (int)$st['id'] ?>"><?= e($st['student_id']) ?> — <?= e($st['full_name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Category / Type *</label>
        <select name="type" class="form-select" required>
          <option value="general">General University Announcement</option>
          <option value="result_published">Examination Result Published</option>
          <option value="grade_correction">Grade Correction Notice</option>
          <option value="request_update">Result Inquiry / Review Response</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Notification Title *</label>
        <input type="text" name="title" placeholder="e.g. Semester Final Examination Results Published" class="form-control" required>
      </div>

      <div class="form-group">
        <label class="form-label">Notification Message *</label>
        <textarea name="message" rows="3" placeholder="Enter message text to display in student portal dropdown &amp; alert banners..." class="form-control" required></textarea>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1.25rem;">
        <button type="button" onclick="closeNewNotifModal()" style="padding:6px 14px; border:1px solid #cbd5e1; background:#f1f5f9; color:#475569; border-radius:6px; cursor:pointer;">Cancel</button>
        <button type="submit" class="btn-primary" style="padding:6px 18px;">Dispatch</button>
      </div>
    </form>
  </div>
</div>

<script>
function openNewNotifModal() {
  document.getElementById('newNotifModal').style.display = 'flex';
}
function closeNewNotifModal() {
  document.getElementById('newNotifModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

