<?php
/**
 * Admin Panel: Faculties Management
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
$adminPageTitle = 'Manage Faculties';
$db = getDb();

// Handle Add/Edit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameBn = trim($_POST['name_bn'] ?? '');
        $deanName = trim($_POST['dean_name'] ?? '');
        $overview = trim($_POST['overview'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-university');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if (empty($nameEn)) {
            flash_set('error', 'Faculty name in English is required.');
        } else {
            if ($action === 'add') {
                $slug = slugify($nameEn);
                $stmt = $db->prepare("INSERT INTO faculties (slug, name_en, name_bn, dean_name, overview, icon, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$slug, $nameEn, $nameBn, $deanName, $overview, $icon, $sortOrder]);
                log_audit('FACULTY_ADDED', "Added new faculty: {$nameEn}");
                flash_set('success', "Faculty '{$nameEn}' added successfully.");
            } elseif ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare("UPDATE faculties SET name_en = ?, name_bn = ?, dean_name = ?, overview = ?, icon = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$nameEn, $nameBn, $deanName, $overview, $icon, $sortOrder, $id]);
                log_audit('FACULTY_EDITED', "Updated faculty ID {$id}: {$nameEn}");
                flash_set('success', "Faculty '{$nameEn}' updated successfully.");
            }
        }
    }
    header("Location: " . APP_URL . "/admin/faculties.php");
    exit;
}

// Fetch edit target if any
$editFaculty = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM faculties WHERE id = ?");
    $stmt->execute([$editId]);
    $editFaculty = $stmt->fetch();
}

// Fetch all faculties
$faculties = $db->query("
    SELECT f.*, COUNT(d.id) AS dept_count 
    FROM faculties f 
    LEFT JOIN departments d ON f.id = d.faculty_id 
    GROUP BY f.id 
    ORDER BY f.sort_order ASC
")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">Academic Faculties</h2>
    <span style="color: #64748b; font-size: 0.88rem;">Manage major academic faculty divisions and deans.</span>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; align-items: start;">
  <!-- Add / Edit Form -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem;">
    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem;">
      <?= $editFaculty ? 'Edit Faculty' : 'Add New Faculty' ?>
    </h3>

    <form action="<?= APP_URL ?>/admin/faculties.php" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editFaculty ? 'edit' : 'add' ?>">
      <?php if ($editFaculty): ?>
        <input type="hidden" name="id" value="<?= (int)$editFaculty['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Faculty Name (English) <span class="required">*</span></label>
        <input type="text" name="name_en" value="<?= e($editFaculty['name_en'] ?? '') ?>" class="form-control" required placeholder="e.g. Faculty of Science">
      </div>

      <div class="form-group">
        <label class="form-label">Faculty Subtitle (Optional)</label>
        <input type="text" name="name_bn" value="<?= e($editFaculty['name_bn'] ?? '') ?>" class="form-control" placeholder="e.g. Faculty of Arts">
      </div>

      <div class="form-group">
        <label class="form-label">Dean Name</label>
        <input type="text" name="dean_name" value="<?= e($editFaculty['dean_name'] ?? '') ?>" class="form-control" placeholder="e.g. Prof. Dr. M. A. Karim">
      </div>

      <div class="form-group">
        <label class="form-label">FontAwesome Icon Class</label>
        <input type="text" name="icon" value="<?= e($editFaculty['icon'] ?? 'fa-university') ?>" class="form-control" placeholder="e.g. fa-laptop-code">
      </div>

      <div class="form-group">
        <label class="form-label">Sort Order</label>
        <input type="number" name="sort_order" value="<?= (int)($editFaculty['sort_order'] ?? 0) ?>" class="form-control">
      </div>

      <div class="form-group">
        <label class="form-label">Overview Description</label>
        <textarea name="overview" rows="4" class="form-control" placeholder="Summary of faculty aims and programs..."><?= e($editFaculty['overview'] ?? '') ?></textarea>
      </div>

      <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
        <button type="submit" class="btn-primary" style="flex: 1; justify-content: center;">
          <i class="fas fa-save"></i> <?= $editFaculty ? 'Save Changes' : 'Create Faculty' ?>
        </button>
        <?php if ($editFaculty): ?>
          <a href="<?= APP_URL ?>/admin/faculties.php" class="btn-primary" style="background: #94a3b8; justify-content: center;">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Faculties List Table -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow: none;">
        <thead>
          <tr>
            <th>Order</th>
            <th>Faculty Title</th>
            <th>Dean</th>
            <th>Departments</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($faculties as $f): ?>
            <tr>
              <td style="font-weight: 700; color: #64748b;"><?= (int)$f['sort_order'] ?></td>
              <td>
                <strong style="color: #0f172a; display: block; font-size: 0.95rem;"><?= e($f['name_en']) ?></strong>
                <span style="font-size: 0.85rem; color: var(--rmu-muted); font-family: 'Hind Siliguri', sans-serif;"><?= e($f['name_bn']) ?></span>
              </td>
              <td style="font-size: 0.88rem; color: #334155;"><?= e($f['dean_name'] ?? 'TBA') ?></td>
              <td style="font-size: 0.88rem;">
                <span class="notice-category-badge badge-academic"><?= (int)$f['dept_count'] ?> Depts</span>
              </td>
              <td style="text-align: right;">
                <a href="<?= APP_URL ?>/admin/faculties.php?edit=<?= (int)$f['id'] ?>" style="color: var(--rmu-primary); font-weight: 600; font-size: 0.85rem; margin-right: 0.6rem;">
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

