<?php
/**
 * Admin Panel: Faculty Members Management
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
$adminPageTitle = 'Faculty Members';
$db = getDb();

// Handle Add/Edit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $deptId = (int)($_POST['department_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if (empty($fullName) || empty($designation) || $deptId <= 0) {
            flash_set('error', 'Department, full name, and designation are required.');
        } else {
            if ($action === 'add') {
                $stmt = $db->prepare("
                    INSERT INTO faculty_members (department_id, full_name, designation, email, phone, qualification, bio, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$deptId, $fullName, $designation, $email, $phone, $qualification, $bio, $sortOrder]);
                log_audit('FACULTY_MEMBER_ADDED', "Added teacher: {$fullName}");
                flash_set('success', "Teacher profile for '{$fullName}' created.");
            } elseif ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare("
                    UPDATE faculty_members 
                    SET department_id = ?, full_name = ?, designation = ?, email = ?, phone = ?, qualification = ?, bio = ?, sort_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([$deptId, $fullName, $designation, $email, $phone, $qualification, $bio, $sortOrder, $id]);
                log_audit('FACULTY_MEMBER_EDITED', "Updated teacher ID {$id}: {$fullName}");
                flash_set('success', "Teacher profile for '{$fullName}' updated.");
            }
        }
    }
    header("Location: " . APP_URL . "/admin/faculty-members.php");
    exit;
}

// Fetch edit target
$editTeacher = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM faculty_members WHERE id = ?");
    $stmt->execute([$editId]);
    $editTeacher = $stmt->fetch();
}

$departments = $db->query("SELECT id, name_en, code FROM departments WHERE is_active = 1 ORDER BY name_en ASC")->fetchAll();

$teachers = $db->query("
    SELECT fm.*, d.name_en AS dept_name, d.code AS dept_code 
    FROM faculty_members fm 
    JOIN departments d ON fm.department_id = d.id 
    ORDER BY fm.department_id ASC, fm.sort_order ASC
")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">Teachers &amp; Academic Staff</h2>
    <span style="color: #64748b; font-size: 0.88rem;">Manage faculty profiles, academic designations, and research backgrounds.</span>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1.2fr 2fr; gap: 2rem; align-items: start;">
  <!-- Form -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem;">
    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem;">
      <?= $editTeacher ? 'Edit Faculty Profile' : 'Add Teacher / Academic Staff' ?>
    </h3>

    <form action="<?= APP_URL ?>/admin/faculty-members.php" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editTeacher ? 'edit' : 'add' ?>">
      <?php if ($editTeacher): ?>
        <input type="hidden" name="id" value="<?= (int)$editTeacher['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Department <span class="required">*</span></label>
        <select name="department_id" class="form-select" required>
          <option value="">-- Select Department --</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= (($editTeacher['department_id'] ?? 0) == $d['id']) ? 'selected' : '' ?>>
              <?= e($d['name_en']) ?> (<?= e($d['code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Full Name &amp; Title <span class="required">*</span></label>
        <input type="text" name="full_name" value="<?= e($editTeacher['full_name'] ?? '') ?>" class="form-control" placeholder="e.g. Dr. Tariqul Islam" required>
      </div>

      <div class="form-group">
        <label class="form-label">Designation <span class="required">*</span></label>
        <input type="text" name="designation" value="<?= e($editTeacher['designation'] ?? '') ?>" class="form-control" placeholder="e.g. Associate Professor & Head" required>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" value="<?= e($editTeacher['email'] ?? '') ?>" class="form-control" placeholder="tariqul@rmu.ac.bd">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" value="<?= e($editTeacher['phone'] ?? '') ?>" class="form-control" placeholder="+8801302991188">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Academic Qualifications</label>
        <input type="text" name="qualification" value="<?= e($editTeacher['qualification'] ?? '') ?>" class="form-control" placeholder="e.g. PhD in Computer Science (BUET), MSc (DU)">
      </div>

      <div class="form-group">
        <label class="form-label">Research Bio / Interests</label>
        <textarea name="bio" rows="3" class="form-control" placeholder="Brief biographical statement..."><?= e($editTeacher['bio'] ?? '') ?></textarea>
      </div>

      <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
        <button type="submit" class="btn-primary" style="flex: 1; justify-content: center;">
          <i class="fas fa-save"></i> <?= $editTeacher ? 'Save Changes' : 'Save Teacher' ?>
        </button>
        <?php if ($editTeacher): ?>
          <a href="<?= APP_URL ?>/admin/faculty-members.php" class="btn-primary" style="background: #94a3b8; justify-content: center;">Cancel</a>
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
            <th>Name &amp; Designation</th>
            <th>Department</th>
            <th>Contact</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($teachers as $t): ?>
            <tr>
              <td>
                <strong style="color: #0f172a; display: block; font-size: 0.92rem;"><?= e($t['full_name']) ?></strong>
                <span style="font-size: 0.8rem; color: var(--rmu-primary); font-weight: 600;"><?= e($t['designation']) ?></span>
                <div style="font-size: 0.75rem; color: #64748b;"><?= e($t['qualification'] ?? '') ?></div>
              </td>
              <td style="font-size: 0.82rem; color: #475569;"><?= e($t['dept_code']) ?></td>
              <td style="font-size: 0.82rem; color: #334155;">
                <div><?= e($t['email'] ?? 'info@rmu.ac.bd') ?></div>
                <small style="color: #64748b;"><?= e($t['phone'] ?? '') ?></small>
              </td>
              <td style="text-align: right;">
                <a href="<?= APP_URL ?>/admin/faculty-members.php?edit=<?= (int)$t['id'] ?>" style="color: var(--rmu-primary); font-weight: 600; font-size: 0.85rem;">
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

