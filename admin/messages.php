<?php
/**
 * Admin Panel: Contact Inquiries Management
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
$adminPageTitle = 'Inquiries & Messages';
$db = getDb();

// Handle Mark Read / Replied Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($action === 'toggle_read') {
            $db->prepare("UPDATE contact_messages SET is_read = 1 - is_read WHERE id = ?")->execute([$id]);
            log_audit('MESSAGE_TOGGLE_READ', "Toggled read status for message ID {$id}");
            flash_set('success', "Message status updated.");
        } elseif ($action === 'toggle_replied') {
            $db->prepare("UPDATE contact_messages SET replied = 1 - replied WHERE id = ?")->execute([$id]);
            log_audit('MESSAGE_TOGGLE_REPLIED', "Toggled replied status for message ID {$id}");
            flash_set('success', "Message replied flag updated.");
        }
    }
    header("Location: " . APP_URL . "/admin/messages.php");
    exit;
}

$messages = $db->query("SELECT * FROM contact_messages ORDER BY id DESC")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">Visitor &amp; Student Inquiries</h2>
    <span style="color: #64748b; font-size: 0.88rem;">Review questions submitted via the public contact form.</span>
  </div>
</div>

<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
  <div class="table-responsive">
    <table class="rmu-table" style="box-shadow: none;">
      <thead>
        <tr>
          <th>Date &bull; Sender</th>
          <th>Subject</th>
          <th>Message</th>
          <th>Status</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($messages)): ?>
          <?php foreach ($messages as $msg): ?>
            <tr style="<?= empty($msg['is_read']) ? 'background: #f8fafc; font-weight: 500;' : '' ?>">
              <td>
                <small style="color: #64748b; font-weight: 600; display: block;"><?= format_date($msg['created_at'], 'M d, Y H:i') ?></small>
                <strong style="color: #0f172a; font-size: 0.95rem;"><?= e($msg['full_name']) ?></strong>
                <div style="font-size: 0.82rem; color: #475569;"><?= e($msg['email']) ?></div>
                <?php if (!empty($msg['phone'])): ?>
                  <div style="font-size: 0.78rem; color: #64748b;"><?= e($msg['phone']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <strong style="color: var(--rmu-primary); font-size: 0.92rem;"><?= e($msg['subject']) ?></strong>
              </td>
              <td style="font-size: 0.88rem; color: #334155; max-width: 380px;">
                <?= nl2br(e($msg['message'])) ?>
              </td>
              <td>
                <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                  <span style="font-size: 0.75rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 4px; <?= $msg['is_read'] ? 'background:#e2e8f0; color:#475569;' : 'background:#fee2e2; color:#b91c1c;' ?>">
                    <?= $msg['is_read'] ? 'Read' : 'New / Unread' ?>
                  </span>
                  <?php if ($msg['replied']): ?>
                    <span style="font-size: 0.75rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 4px; background:#d1fae5; color:#065f46;">
                      Replied
                    </span>
                  <?php endif; ?>
                </div>
              </td>
              <td style="text-align: right;">
                <form action="<?= APP_URL ?>/admin/messages.php" method="POST" style="display: inline-block; margin-right: 0.4rem;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$msg['id'] ?>">
                  <input type="hidden" name="action" value="toggle_read">
                  <button type="submit" class="btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.78rem; background: #64748b;">
                    <?= $msg['is_read'] ? 'Mark Unread' : 'Mark Read' ?>
                  </button>
                </form>

                <form action="<?= APP_URL ?>/admin/messages.php" method="POST" style="display: inline-block;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$msg['id'] ?>">
                  <input type="hidden" name="action" value="toggle_replied">
                  <button type="submit" class="btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.78rem; background: #10b981;">
                    <?= $msg['replied'] ? 'Unmark Replied' : 'Mark Replied' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5" style="text-align: center; padding: 2rem; color: #64748b;">No contact inquiries recorded yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

