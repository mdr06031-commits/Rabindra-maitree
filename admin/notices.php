<?php
/**
 * Admin Panel: Official Notices Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Manage Notices';
$db = getDb();

// Handle Add/Edit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $st = $db->prepare("SELECT title_en, file_path FROM notices WHERE id = ?");
                $st->execute([$id]);
                $nData = $st->fetch();
                if ($nData && !empty($nData['file_path'])) {
                    $fullPath = __DIR__ . '/../' . $nData['file_path'];
                    if (file_exists($fullPath)) {
                        @unlink($fullPath);
                    }
                }
                $del = $db->prepare("DELETE FROM notices WHERE id = ?");
                $del->execute([$id]);
                log_audit('NOTICE_DELETED', "Deleted notice: {$nData['title_en']}");
                flash_set('success', "Notice deleted successfully.");
            }
        } else {
            $titleEn = trim($_POST['title_en'] ?? '');
            $titleBn = trim($_POST['title_bn'] ?? '');
            $category = trim($_POST['category'] ?? 'General');
            $content = trim($_POST['content'] ?? '');
            $pubDate = trim($_POST['published_date'] ?? date('Y-m-d'));
            $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
            $filePath = null;

            // Handle File Upload
            if (!empty($_FILES['attachment']['name'])) {
                $allowedExts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                $fileInfo = pathinfo($_FILES['attachment']['name']);
                $ext = strtolower($fileInfo['extension'] ?? '');

                if (!in_array($ext, $allowedExts, true)) {
                    flash_set('error', 'Invalid file type. Allowed formats: PDF, DOC, DOCX, JPG, PNG.');
                    header("Location: " . APP_URL . "/admin/notices.php");
                    exit;
                } elseif ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
                    flash_set('error', 'File size exceeds maximum limit of 10 MB.');
                    header("Location: " . APP_URL . "/admin/notices.php");
                    exit;
                } else {
                    $newFilename = 'notice_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $targetDir = STORAGE_PATH . '/uploads';
                    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetDir . '/' . $newFilename)) {
                        $filePath = 'storage/uploads/' . $newFilename;
                    }
                }
            }

            if (empty($titleEn) || empty($content)) {
                flash_set('error', 'Notice title and content are required.');
            } else {
                if ($action === 'add') {
                    $slug = slugify($titleEn) . '-' . time();
                    $stmt = $db->prepare("
                        INSERT INTO notices (slug, title_en, title_bn, category, content, file_path, published_date, is_pinned)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$slug, $titleEn, $titleBn, $category, $content, $filePath, $pubDate, $isPinned]);
                    log_audit('NOTICE_ADDED', "Added notice: {$titleEn}");
                    flash_set('success', "Notice '{$titleEn}' created successfully.");
                } elseif ($action === 'edit') {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($filePath) {
                        $stmt = $db->prepare("
                            UPDATE notices 
                            SET title_en = ?, title_bn = ?, category = ?, content = ?, file_path = ?, published_date = ?, is_pinned = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$titleEn, $titleBn, $category, $content, $filePath, $pubDate, $isPinned, $id]);
                    } else {
                        $stmt = $db->prepare("
                            UPDATE notices 
                            SET title_en = ?, title_bn = ?, category = ?, content = ?, published_date = ?, is_pinned = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$titleEn, $titleBn, $category, $content, $pubDate, $isPinned, $id]);
                    }
                    log_audit('NOTICE_EDITED', "Updated notice ID {$id}: {$titleEn}");
                    flash_set('success', "Notice '{$titleEn}' updated successfully.");
                }
            }
        }
    }
    header("Location: " . APP_URL . "/admin/notices.php");
    exit;
}

// Fetch edit target
$editNotice = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM notices WHERE id = ?");
    $stmt->execute([$editId]);
    $editNotice = $stmt->fetch();
}

// Fetch all notices
$notices = $db->query("SELECT * FROM notices ORDER BY is_pinned DESC, published_date DESC")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">Official Notices Management</h2>
    <span style="color: #64748b; font-size: 0.88rem;">Publish circulars, scholarships, exam schedules, and circular documents.</span>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1.2fr 2fr; gap: 2rem; align-items: start;">
  <!-- Form -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem;">
    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem;">
      <?= $editNotice ? 'Edit Notice' : 'Publish New Notice' ?>
    </h3>

    <form action="<?= APP_URL ?>/admin/notices.php" method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editNotice ? 'edit' : 'add' ?>">
      <?php if ($editNotice): ?>
        <input type="hidden" name="id" value="<?= (int)$editNotice['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Notice Title (English) <span class="required">*</span></label>
        <input type="text" name="title_en" value="<?= e($editNotice['title_en'] ?? '') ?>" class="form-control" required placeholder="e.g. Admission Fair Fall 2026">
      </div>

      <div class="form-group">
        <label class="form-label">Notice Subtitle (Optional)</label>
        <input type="text" name="title_bn" value="<?= e($editNotice['title_bn'] ?? '') ?>" class="form-control" placeholder="e.g. Admission Fair Notice">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" class="form-select">
            <option value="General" <?= (($editNotice['category'] ?? '') === 'General') ? 'selected' : '' ?>>General</option>
            <option value="Admission" <?= (($editNotice['category'] ?? '') === 'Admission') ? 'selected' : '' ?>>Admission</option>
            <option value="Academic" <?= (($editNotice['category'] ?? '') === 'Academic') ? 'selected' : '' ?>>Academic</option>
            <option value="Scholarship" <?= (($editNotice['category'] ?? '') === 'Scholarship') ? 'selected' : '' ?>>Scholarship</option>
            <option value="Exam" <?= (($editNotice['category'] ?? '') === 'Exam') ? 'selected' : '' ?>>Exam</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Publish Date</label>
          <input type="date" name="published_date" value="<?= e($editNotice['published_date'] ?? date('Y-m-d')) ?>" class="form-control" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Notice Body Content <span class="required">*</span></label>
        <textarea name="content" rows="5" class="form-control" required placeholder="Full announcement text..."><?= e($editNotice['content'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Attach File (PDF, DOC, JPG max 10MB)</label>
        <input type="file" name="attachment" class="form-control">
        <?php if (!empty($editNotice['file_path'])): ?>
          <small style="color: var(--rmu-muted); display: block; margin-top: 4px;">Current file: <?= e($editNotice['file_path']) ?></small>
        <?php endif; ?>
      </div>

      <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
        <input type="checkbox" id="isPinned" name="is_pinned" value="1" <?= !empty($editNotice['is_pinned']) ? 'checked' : '' ?>>
        <label for="isPinned" style="margin: 0; font-size: 0.9rem; font-weight: 600; cursor: pointer;">Pin to top of Notice Board</label>
      </div>

      <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
        <button type="submit" class="btn-primary" style="flex: 1; justify-content: center;">
          <i class="fas fa-save"></i> <?= $editNotice ? 'Save Changes' : 'Publish Notice' ?>
        </button>
        <?php if ($editNotice): ?>
          <a href="<?= APP_URL ?>/admin/notices.php" class="btn-primary" style="background: #94a3b8; justify-content: center;">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Table -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow: none;">
        <thead>
          <tr>
            <th>Date &bull; Cat</th>
            <th>Notice Title</th>
            <th>Views</th>
            <th>File</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($notices as $n): ?>
            <tr>
              <td>
                <small style="color: #64748b; font-weight: 600; display: block;"><?= format_date($n['published_date']) ?></small>
                <span class="notice-category-badge <?= ($n['category'] === 'Admission') ? 'badge-admission' : (($n['category'] === 'Scholarship') ? 'badge-scholarship' : 'badge-academic') ?>" style="margin-top: 2px;">
                  <?= e($n['category']) ?>
                </span>
                <?php if ($n['is_pinned']): ?>
                  <span style="font-size: 0.7rem; color: #854d0e; font-weight: 700; display: block; margin-top: 2px;">[PINNED]</span>
                <?php endif; ?>
              </td>
              <td>
                <strong style="color: #0f172a; display: block; font-size: 0.92rem;"><?= e($n['title_en']) ?></strong>
                <span style="font-size: 0.8rem; color: var(--rmu-muted); font-family: 'Hind Siliguri', sans-serif;"><?= e($n['title_bn']) ?></span>
              </td>
              <td style="font-size: 0.85rem; color: #64748b;"><?= (int)$n['view_count'] ?></td>
              <td>
                <?php if (!empty($n['file_path'])): ?>
                  <a href="<?= APP_URL ?>/<?= e($n['file_path']) ?>" download style="color: var(--rmu-accent);"><i class="fas fa-paperclip"></i> File</a>
                <?php else: ?>
                  <span style="color: #cbd5e1;">None</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right; white-space: nowrap;">
                <a href="<?= APP_URL ?>/admin/notices.php?edit=<?= (int)$n['id'] ?>" style="color: var(--rmu-primary); font-weight: 600; font-size: 0.85rem; margin-right: 6px;">
                  <i class="fas fa-edit"></i> Edit
                </a>
                <form action="<?= APP_URL ?>/admin/notices.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete notice <?= addslashes(e($n['title_en'])) ?>?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                  <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 0.85rem;" title="Delete">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

