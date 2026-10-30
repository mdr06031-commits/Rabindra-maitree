<?php
/**
 * Admin Panel: Departments Management
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
$adminPageTitle = 'Manage Departments';
$db = getDb();

// Handle Add/Edit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $facultyId = (int)($_POST['faculty_id'] ?? 0);
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameBn = trim($_POST['name_bn'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $headName = trim($_POST['head_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $overview = trim($_POST['overview'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if (empty($nameEn) || empty($code) || $facultyId <= 0) {
            flash_set('error', 'Faculty, department name, and code are required.');
        } else {
            if ($action === 'add') {
                $slug = slugify($nameEn);
                $stmt = $db->prepare("
                    INSERT INTO departments (faculty_id, slug, name_en, name_bn, code, head_name, email, phone, overview, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$facultyId, $slug, $nameEn, $nameBn, $code, $headName, $email, $phone, $overview, $sortOrder]);
                log_audit('DEPARTMENT_ADDED', "Added department: {$nameEn} ({$code})");
                flash_set('success', "Department '{$nameEn}' added successfully.");
            } elseif ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare("
                    UPDATE departments 
                    SET faculty_id = ?, name_en = ?, name_bn = ?, code = ?, head_name = ?, email = ?, phone = ?, overview = ?, sort_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([$facultyId, $nameEn, $nameBn, $code, $headName, $email, $phone, $overview, $sortOrder, $id]);
                log_audit('DEPARTMENT_EDITED', "Updated department ID {$id}: {$nameEn}");
                flash_set('success', "Department '{$nameEn}' updated successfully.");
            }
        }
    }
    header("Location: " . APP_URL . "/admin/departments.php");
    exit;
}

// Fetch edit target
$editDept = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$editId]);
    $editDept = $stmt->fetch();
}

// Fetch faculties list for select dropdown
$faculties = $db->query("SELECT id, name_en FROM faculties WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();

// Fetch all departments
$departments = $db->query("
    SELECT d.*, f.name_en AS faculty_name, COUNT(p.id) AS program_count 
    FROM departments d 
    JOIN faculties f ON d.faculty_id = f.id 
    LEFT JOIN programs p ON d.id = p.department_id 
    GROUP BY d.id 
    ORDER BY d.faculty_id ASC, d.sort_order ASC
")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">Academic Departments</h2>
    <span style="color: #64748b; font-size: 0.88rem;">Manage academic departments, department chairs, and codes.</span>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; align-items: start;">
  <!-- Form -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem;">
    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem;">
      <?= $editDept ? 'Edit Department' : 'Add New Department' ?>
    </h3>

    <form action="<?= APP_URL ?>/admin/departments.php" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editDept ? 'edit' : 'add' ?>">
      <?php if ($editDept): ?>
        <input type="hidden" name="id" value="<?= (int)$editDept['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Associated Faculty <span class="required">*</span></label>
        <select name="faculty_id" class="form-select" required>
          <option value="">-- Select Faculty --</option>
          <?php foreach ($faculties as $f): ?>
            <option value="<?= (int)$f['id'] ?>" <?= (($editDept['faculty_id'] ?? 0) == $f['id']) ? 'selected' : '' ?>>
              <?= e($f['name_en']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Department Name (English) <span class="required">*</span></label>
        <input type="text" name="name_en" value="<?= e($editDept['name_en'] ?? '') ?>" class="form-control" placeholder="e.g. Department of English" required>
      </div>

      <div class="form-group">
        <label class="form-label">Department Subtitle / Short Name (Optional)</label>
        <input type="text" name="name_bn" value="<?= e($editDept['name_bn'] ?? '') ?>" class="form-control" placeholder="e.g. Dept of English">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Dept Code <span class="required">*</span></label>
          <input type="text" name="code" value="<?= e($editDept['code'] ?? '') ?>" class="form-control" placeholder="e.g. ENG" required>
        </div>
        <div class="form-group">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" value="<?= (int)($editDept['sort_order'] ?? 0) ?>" class="form-control">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Head of Department</label>
        <input type="text" name="head_name" value="<?= e($editDept['head_name'] ?? '') ?>" class="form-control" placeholder="e.g. Dr. Shamsul Alam">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Dept Email</label>
          <input type="email" name="email" value="<?= e($editDept['email'] ?? '') ?>" class="form-control" placeholder="dept@rmu.ac.bd">
        </div>
        <div class="form-group">
          <label class="form-label">Dept Phone</label>
          <input type="text" name="phone" value="<?= e($editDept['phone'] ?? '') ?>" class="form-control" placeholder="+8801302991188">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Department Overview</label>
        <textarea name="overview" rows="4" class="form-control" placeholder="Overview of curricula, lab resources, and research..."><?= e($editDept['overview'] ?? '') ?></textarea>
      </div>

      <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
        <button type="submit" class="btn-primary" style="flex: 1; justify-content: center;">
          <i class="fas fa-save"></i> <?= $editDept ? 'Save Changes' : 'Create Department' ?>
        </button>
        <?php if ($editDept): ?>
          <a href="<?= APP_URL ?>/admin/departments.php" class="btn-primary" style="background: #94a3b8; justify-content: center;">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Departments Table -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow: none;">
        <thead>
          <tr>
            <th>Code</th>
            <th>Department</th>
            <th>Faculty</th>
            <th>Head of Dept</th>
            <th>Programs</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($departments as $d): ?>
            <tr>
              <td>
                <span style="font-weight: 700; color: var(--rmu-primary); background: var(--rmu-primary-light); padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                  <?= e($d['code']) ?>
                </span>
              </td>
              <td>
                <strong style="color: #0f172a; display: block; font-size: 0.95rem;"><?= e($d['name_en']) ?></strong>
                <span style="font-size: 0.85rem; color: var(--rmu-muted); font-family: 'Hind Siliguri', sans-serif;"><?= e($d['name_bn']) ?></span>
              </td>
              <td style="font-size: 0.82rem; color: #475569;"><?= e($d['faculty_name']) ?></td>
              <td style="font-size: 0.85rem; color: #334155;"><?= e($d['head_name'] ?? 'TBA') ?></td>
              <td style="font-size: 0.85rem;">
                <span class="notice-category-badge badge-admission"><?= (int)$d['program_count'] ?> Programs</span>
              </td>
              <td style="text-align: right;">
                <a href="<?= APP_URL ?>/admin/departments.php?edit=<?= (int)$d['id'] ?>" style="color: var(--rmu-primary); font-weight: 600; font-size: 0.85rem;">
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

