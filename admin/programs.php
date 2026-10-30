<?php
/**
 * Admin Panel: Degree Programs Management
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
$adminPageTitle = 'Manage Degree Programs';
$db = getDb();

// Handle Add/Edit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $deptId = (int)($_POST['department_id'] ?? 0);
        $titleEn = trim($_POST['title_en'] ?? '');
        $titleBn = trim($_POST['title_bn'] ?? '');
        $level = trim($_POST['degree_level'] ?? 'Undergraduate');
        $duration = trim($_POST['duration'] ?? '4 Years (8 Semesters)');
        $credits = (int)($_POST['total_credits'] ?? 140);
        $eligibility = trim($_POST['eligibility'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tuitionFee = trim($_POST['tuition_fee'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if (empty($titleEn) || $deptId <= 0) {
            flash_set('error', 'Department and program title are required.');
        } else {
            if ($action === 'add') {
                $slug = slugify($titleEn);
                $stmt = $db->prepare("
                    INSERT INTO programs (department_id, slug, title_en, title_bn, degree_level, duration, total_credits, eligibility, description, tuition_fee, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$deptId, $slug, $titleEn, $titleBn, $level, $duration, $credits, $eligibility, $description, $tuitionFee, $sortOrder]);
                log_audit('PROGRAM_ADDED', "Added program: {$titleEn}");
                flash_set('success', "Program '{$titleEn}' added successfully.");
            } elseif ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare("
                    UPDATE programs 
                    SET department_id = ?, title_en = ?, title_bn = ?, degree_level = ?, duration = ?, total_credits = ?, eligibility = ?, description = ?, tuition_fee = ?, sort_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([$deptId, $titleEn, $titleBn, $level, $duration, $credits, $eligibility, $description, $tuitionFee, $sortOrder, $id]);
                log_audit('PROGRAM_EDITED', "Updated program ID {$id}: {$titleEn}");
                flash_set('success', "Program '{$titleEn}' updated successfully.");
            }
        }
    }
    header("Location: " . APP_URL . "/admin/programs.php");
    exit;
}

// Fetch edit target
$editProg = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM programs WHERE id = ?");
    $stmt->execute([$editId]);
    $editProg = $stmt->fetch();
}

// Fetch departments for select
$departments = $db->query("SELECT id, name_en, code FROM departments WHERE is_active = 1 ORDER BY name_en ASC")->fetchAll();

// Fetch programs
$programs = $db->query("
    SELECT p.*, d.name_en AS dept_name, d.code AS dept_code 
    FROM programs p 
    JOIN departments d ON p.department_id = d.id 
    ORDER BY p.sort_order ASC
")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">Academic Degree Programs</h2>
    <span style="color: #64748b; font-size: 0.88rem;">Manage undergraduate and graduate degree curricula and tuition costs.</span>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1.2fr 2fr; gap: 2rem; align-items: start;">
  <!-- Form -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem;">
    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem;">
      <?= $editProg ? 'Edit Program' : 'Add New Degree Program' ?>
    </h3>

    <form action="<?= APP_URL ?>/admin/programs.php" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editProg ? 'edit' : 'add' ?>">
      <?php if ($editProg): ?>
        <input type="hidden" name="id" value="<?= (int)$editProg['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Department <span class="required">*</span></label>
        <select name="department_id" class="form-select" required>
          <option value="">-- Select Department --</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= (($editProg['department_id'] ?? 0) == $d['id']) ? 'selected' : '' ?>>
              <?= e($d['name_en']) ?> (<?= e($d['code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Program Title (English) <span class="required">*</span></label>
        <input type="text" name="title_en" value="<?= e($editProg['title_en'] ?? '') ?>" class="form-control" placeholder="e.g. BSc Engg in CSE" required>
      </div>

      <div class="form-group">
        <label class="form-label">Program Short Code / Subtitle (Optional)</label>
        <input type="text" name="title_bn" value="<?= e($editProg['title_bn'] ?? '') ?>" class="form-control" placeholder="e.g. CSE">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Degree Level</label>
          <select name="degree_level" class="form-select">
            <option value="Undergraduate" <?= (($editProg['degree_level'] ?? '') === 'Undergraduate') ? 'selected' : '' ?>>Undergraduate</option>
            <option value="Postgraduate" <?= (($editProg['degree_level'] ?? '') === 'Postgraduate') ? 'selected' : '' ?>>Postgraduate</option>
            <option value="Diploma" <?= (($editProg['degree_level'] ?? '') === 'Diploma') ? 'selected' : '' ?>>Diploma</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Total Credits</label>
          <input type="number" name="total_credits" value="<?= (int)($editProg['total_credits'] ?? 140) ?>" class="form-control" required>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Duration</label>
          <input type="text" name="duration" value="<?= e($editProg['duration'] ?? '4 Years (8 Semesters)') ?>" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Tuition Fee Info</label>
          <input type="text" name="tuition_fee" value="<?= e($editProg['tuition_fee'] ?? 'BDT 3,50,000') ?>" class="form-control" placeholder="e.g. BDT 3,80,000">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Admission Eligibility Criteria</label>
        <textarea name="eligibility" rows="2" class="form-control" placeholder="Minimum GPA requirements and prerequisite subjects..."><?= e($editProg['eligibility'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Program Description</label>
        <textarea name="description" rows="3" class="form-control" placeholder="Curriculum summary..."><?= e($editProg['description'] ?? '') ?></textarea>
      </div>

      <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
        <button type="submit" class="btn-primary" style="flex: 1; justify-content: center;">
          <i class="fas fa-save"></i> <?= $editProg ? 'Save Changes' : 'Create Program' ?>
        </button>
        <?php if ($editProg): ?>
          <a href="<?= APP_URL ?>/admin/programs.php" class="btn-primary" style="background: #94a3b8; justify-content: center;">Cancel</a>
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
            <th>Program Title</th>
            <th>Department</th>
            <th>Level</th>
            <th>Credits &amp; Duration</th>
            <th>Fee</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($programs as $p): ?>
            <tr>
              <td>
                <strong style="color: #0f172a; display: block; font-size: 0.92rem;"><?= e($p['title_en']) ?></strong>
                <span style="font-size: 0.8rem; color: var(--rmu-muted); font-family: 'Hind Siliguri', sans-serif;"><?= e($p['title_bn']) ?></span>
              </td>
              <td style="font-size: 0.82rem; color: #475569;"><?= e($p['dept_code']) ?></td>
              <td>
                <span class="notice-category-badge <?= ($p['degree_level'] === 'Undergraduate') ? 'badge-academic' : 'badge-scholarship' ?>">
                  <?= e($p['degree_level']) ?>
                </span>
              </td>
              <td style="font-size: 0.82rem; color: #334155;">
                <?= (int)$p['total_credits'] ?> Cr &bull; <?= e($p['duration']) ?>
              </td>
              <td style="font-size: 0.82rem; font-weight: 600; color: var(--rmu-primary);">
                <?= e($p['tuition_fee'] ?? 'Standard') ?>
              </td>
              <td style="text-align: right;">
                <a href="<?= APP_URL ?>/admin/programs.php?edit=<?= (int)$p['id'] ?>" style="color: var(--rmu-primary); font-weight: 600; font-size: 0.85rem;">
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

