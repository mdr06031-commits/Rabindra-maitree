<?php
/**
 * Admin Panel: Student Portal Notices Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Student Portal Notices';
$db = getDb();

// Handle Add/Edit/Delete Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $title = trim($_POST['title'] ?? '');
            $category = in_array($_POST['category'] ?? '', ['Academic', 'Exam', 'Payment', 'General']) ? $_POST['category'] : 'Academic';
            $content = trim($_POST['content'] ?? '');
            $isPinned = isset($_POST['is_pinned']) ? 1 : 0;

            if (empty($title) || empty($content)) {
                flash_set('error', 'Notice title and message content are required.');
            } else {
                $stmt = $db->prepare("
                    INSERT INTO student_portal_notices (title, category, content, is_pinned, published_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$title, $category, $content, $isPinned]);
                log_audit('PORTAL_NOTICE_ADDED', "Published student portal notice: {$title}");
                flash_set('success', "Student portal notice '{$title}' published.");
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $category = in_array($_POST['category'] ?? '', ['Academic', 'Exam', 'Payment', 'General']) ? $_POST['category'] : 'Academic';
            $content = trim($_POST['content'] ?? '');
            $isPinned = isset($_POST['is_pinned']) ? 1 : 0;

            if ($id > 0 && !empty($title) && !empty($content)) {
                $stmt = $db->prepare("
                    UPDATE student_portal_notices 
                    SET title = ?, category = ?, content = ?, is_pinned = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $category, $content, $isPinned, $id]);
                log_audit('PORTAL_NOTICE_UPDATED', "Updated student portal notice ID #{$id}");
                flash_set('success', "Student portal notice updated.");
            } else {
                flash_set('error', 'Title and content are required.');
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $del = $db->prepare("DELETE FROM student_portal_notices WHERE id = ?");
                $del->execute([$id]);
                log_audit('PORTAL_NOTICE_DELETED', "Deleted student portal notice ID #{$id}");
                flash_set('success', "Notice deleted from student portal.");
            }
        }
    }
    header("Location: " . APP_URL . "/admin/portal-notices.php");
    exit;
}

// Fetch edit target if any
$editNotice = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $st = $db->prepare("SELECT * FROM student_portal_notices WHERE id = ?");
    $st->execute([$editId]);
    $editNotice = $st->fetch();
}

// Fetch all portal notices
$notices = $db->query("SELECT * FROM student_portal_notices ORDER BY is_pinned DESC, published_at DESC")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0 0 0.2rem;">
      <i class="fas fa-bullhorn" style="color: var(--rmu-primary);"></i> Student Portal Notices
    </h2>
    <span style="color: #64748b; font-size: 0.88rem;">Broadcast exam schedules, fee deadlines, and internal announcements inside the student dashboard.</span>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1.2fr 2fr; gap: 2rem; align-items: start;">
  <!-- Form -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem;">
      <?= $editNotice ? 'Edit Portal Notice' : 'Broadcast New Notice' ?>
    </h3>

    <form action="<?= APP_URL ?>/admin/portal-notices.php" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editNotice ? 'edit' : 'add' ?>">
      <?php if ($editNotice): ?>
        <input type="hidden" name="id" value="<?= (int)$editNotice['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Notice Headline / Title *</label>
        <input type="text" name="title" value="<?= e($editNotice['title'] ?? '') ?>" placeholder="e.g. Mid-Term Examination Routine Published" class="form-control" required>
      </div>

      <div class="form-group">
        <label class="form-label">Notice Category</label>
        <select name="category" class="form-select">
          <option value="Academic" <?= (($editNotice['category'] ?? '') === 'Academic') ? 'selected' : '' ?>>Academic</option>
          <option value="Exam" <?= (($editNotice['category'] ?? '') === 'Exam') ? 'selected' : '' ?>>Exam</option>
          <option value="Payment" <?= (($editNotice['category'] ?? '') === 'Payment') ? 'selected' : '' ?>>Payment / Fee</option>
          <option value="General" <?= (($editNotice['category'] ?? '') === 'General') ? 'selected' : '' ?>>General Notice</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Detailed Content *</label>
        <textarea name="content" rows="6" class="form-control" placeholder="Write full details..." required><?= e($editNotice['content'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.9rem; color: #334155;">
          <input type="checkbox" name="is_pinned" value="1" <?= (!empty($editNotice['is_pinned'])) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
          <strong>Pin to Top of Student Dashboard</strong>
        </label>
      </div>

      <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
        <button type="submit" class="btn-primary" style="flex: 1; justify-content: center;">
          <i class="fas fa-paper-plane"></i> <?= $editNotice ? 'Save Update' : 'Broadcast to Students' ?>
        </button>
        <?php if ($editNotice): ?>
          <a href="<?= APP_URL ?>/admin/portal-notices.php" class="btn-primary" style="background: #94a3b8; justify-content: center;">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Table -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">Active Portal Announcements</h3>
    </div>
    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow: none; margin: 0;">
        <thead>
          <tr>
            <th>Date &bull; Category</th>
            <th>Announcement Details</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($notices)): ?>
            <?php foreach ($notices as $n): ?>
              <tr>
                <td>
                  <small style="color: #64748b; font-weight: 600; display: block;"><?= format_date($n['published_at']) ?></small>
                  <span style="display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; background: #e0f2fe; color: #0369a1; margin-top: 2px;">
                    <?= e($n['category']) ?>
                  </span>
                  <?php if ($n['is_pinned']): ?>
                    <span style="font-size: 0.72rem; color: #b45309; font-weight: 700; display: block; margin-top: 2px;">
                      <i class="fas fa-thumbtack"></i> PINNED
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <strong style="color: #0f172a; font-size: 0.92rem; display: block;"><?= e($n['title']) ?></strong>
                  <div style="font-size: 0.82rem; color: #475569; margin-top: 4px; line-height: 1.4;">
                    <?= nl2br(e(substr($n['content'], 0, 140))) ?><?= strlen($n['content']) > 140 ? '...' : '' ?>
                  </div>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <a href="<?= APP_URL ?>/admin/portal-notices.php?edit=<?= (int)$n['id'] ?>" style="color: var(--rmu-primary); font-weight: 600; font-size: 0.85rem; margin-right: 8px;">
                    <i class="fas fa-edit"></i> Edit
                  </a>
                  <form action="<?= APP_URL ?>/admin/portal-notices.php" method="POST" style="display: inline;" onsubmit="return confirm('Delete this portal notice?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                    <button type="submit" style="border: none; background: none; color: #ef4444; font-size: 0.85rem; cursor: pointer;">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="3" style="text-align: center; padding: 2rem; color: #64748b;">No notices on the student portal.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

