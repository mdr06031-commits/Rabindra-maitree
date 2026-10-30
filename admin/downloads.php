<?php
/**
 * Admin Panel: Downloads Management
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
$adminPageTitle = 'Manage Downloads';
$db = getDb();

// Handle Add/Edit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $fileSize = trim($_POST['file_size'] ?? '1.0 MB');
        $filePath = null;
        $fileName = '';

        // Handle File Upload if provided
        if (!empty($_FILES['file']['name'])) {
            $allowedExts = ['pdf', 'doc', 'docx', 'zip', 'xls', 'xlsx'];
            $fileInfo = pathinfo($_FILES['file']['name']);
            $ext = strtolower($fileInfo['extension'] ?? '');

            if (!in_array($ext, $allowedExts, true)) {
                flash_set('error', 'Invalid file type. Allowed formats: PDF, DOC, DOCX, XLS, XLSX, ZIP.');
                header("Location: " . APP_URL . "/admin/downloads.php");
                exit;
            } elseif ($_FILES['file']['size'] > 15 * 1024 * 1024) {
                flash_set('error', 'File size exceeds maximum limit of 15 MB.');
                header("Location: " . APP_URL . "/admin/downloads.php");
                exit;
            } else {
                $newFilename = 'doc_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $targetDir = STORAGE_PATH . '/downloads';
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                if (move_uploaded_file($_FILES['file']['tmp_name'], $targetDir . '/' . $newFilename)) {
                    $filePath = 'storage/downloads/' . $newFilename;
                    $fileName = $_FILES['file']['name'];
                    $fileSize = round($_FILES['file']['size'] / 1024) . ' KB';
                }
            }
        }

        if (empty($title)) {
            flash_set('error', 'Document title is required.');
        } else {
            if ($action === 'add') {
                if (!$filePath) {
                    $filePath = 'storage/downloads/rmu_undergrad_admission_form.pdf';
                    $fileName = 'document.pdf';
                }
                $stmt = $db->prepare("
                    INSERT INTO downloads (title, category, file_name, file_path, file_size)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $category, $fileName, $filePath, $fileSize]);
                log_audit('DOWNLOAD_ADDED', "Added download: {$title}");
                flash_set('success', "Document '{$title}' added successfully.");
            } elseif ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                if ($filePath) {
                    $stmt = $db->prepare("
                        UPDATE downloads 
                        SET title = ?, category = ?, file_name = ?, file_path = ?, file_size = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $category, $fileName, $filePath, $fileSize, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE downloads SET title = ?, category = ? WHERE id = ?");
                    $stmt->execute([$title, $category, $id]);
                }
                log_audit('DOWNLOAD_EDITED', "Updated download ID {$id}: {$title}");
                flash_set('success', "Document '{$title}' updated successfully.");
            }
        }
    }
    header("Location: " . APP_URL . "/admin/downloads.php");
    exit;
}

// Fetch edit target
$editDoc = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM downloads WHERE id = ?");
    $stmt->execute([$editId]);
    $editDoc = $stmt->fetch();
}

$downloads = $db->query("SELECT * FROM downloads ORDER BY id DESC")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">Downloadable Forms &amp; Resources</h2>
    <span style="color: #64748b; font-size: 0.88rem;">Manage PDF application forms, academic calendars, and syllabus catalogs.</span>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1.2fr 2fr; gap: 2rem; align-items: start;">
  <!-- Form -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem;">
    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem;">
      <?= $editDoc ? 'Edit Document' : 'Upload New Document' ?>
    </h3>

    <form action="<?= APP_URL ?>/admin/downloads.php" method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editDoc ? 'edit' : 'add' ?>">
      <?php if ($editDoc): ?>
        <input type="hidden" name="id" value="<?= (int)$editDoc['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Document Title <span class="required">*</span></label>
        <input type="text" name="title" value="<?= e($editDoc['title'] ?? '') ?>" class="form-control" required placeholder="e.g. Undergraduate Admission Form 2026">
      </div>

      <div class="form-group">
        <label class="form-label">Category</label>
        <select name="category" class="form-select">
          <option value="Admission" <?= (($editDoc['category'] ?? '') === 'Admission') ? 'selected' : '' ?>>Admission</option>
          <option value="Academic" <?= (($editDoc['category'] ?? '') === 'Academic') ? 'selected' : '' ?>>Academic</option>
          <option value="Examination" <?= (($editDoc['category'] ?? '') === 'Examination') ? 'selected' : '' ?>>Examination</option>
          <option value="Administration" <?= (($editDoc['category'] ?? '') === 'Administration') ? 'selected' : '' ?>>Administration</option>
          <option value="General" <?= (($editDoc['category'] ?? '') === 'General') ? 'selected' : '' ?>>General</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Select File (PDF, DOCX, ZIP max 15MB)</label>
        <input type="file" name="file" class="form-control">
        <?php if (!empty($editDoc['file_path'])): ?>
          <small style="color: var(--rmu-muted); display: block; margin-top: 4px;">Current file: <?= e($editDoc['file_path']) ?></small>
        <?php endif; ?>
      </div>

      <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
        <button type="submit" class="btn-primary" style="flex: 1; justify-content: center;">
          <i class="fas fa-save"></i> <?= $editDoc ? 'Save Changes' : 'Upload Document' ?>
        </button>
        <?php if ($editDoc): ?>
          <a href="<?= APP_URL ?>/admin/downloads.php" class="btn-primary" style="background: #94a3b8; justify-content: center;">Cancel</a>
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
            <th>Title</th>
            <th>Category</th>
            <th>Size</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($downloads as $d): ?>
            <tr>
              <td>
                <strong style="color: #0f172a; display: block; font-size: 0.92rem;"><?= e($d['title']) ?></strong>
                <small style="color: #64748b;"><?= e($d['file_path']) ?></small>
              </td>
              <td>
                <span class="notice-category-badge badge-academic"><?= e($d['category']) ?></span>
              </td>
              <td style="font-size: 0.85rem; color: #64748b;"><?= e($d['file_size']) ?></td>
              <td style="text-align: right;">
                <a href="<?= APP_URL ?>/admin/downloads.php?edit=<?= (int)$d['id'] ?>" style="color: var(--rmu-primary); font-weight: 600; font-size: 0.85rem;">
                  <i class="fas fa-edit"></i> Edit
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

