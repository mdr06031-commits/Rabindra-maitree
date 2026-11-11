<?php
/**
 * Admin Panel: Semester Management
 * Rabindra Maitree University (RMU)
 * Term activation, admission intakes & credit limits
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Semester Management';
$db = getDb();

// Handle Actions (Add Term, Edit Term, Set Active Term, Toggle Admission Open, Delete Term)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'set_current') {
            $termId = (int)($_POST['id'] ?? 0);
            if ($termId > 0) {
                $db->exec("UPDATE semester_terms SET is_current = 0");
                $upd = $db->prepare("UPDATE semester_terms SET is_current = 1 WHERE id = ?");
                $upd->execute([$termId]);
                log_audit('SEMESTER_SET_CURRENT', "Activated term ID #{$termId} as current semester");
                flash_set('success', "Active academic semester updated successfully.");
            }
        } elseif ($action === 'toggle_admission') {
            $termId = (int)($_POST['id'] ?? 0);
            $val = !empty($_POST['is_admission_open']) ? 1 : 0;
            if ($termId > 0) {
                $upd = $db->prepare("UPDATE semester_terms SET is_admission_open = ? WHERE id = ?");
                $upd->execute([$val, $termId]);
                log_audit('SEMESTER_ADMISSION_TOGGLED', "Set admission open={$val} for term ID #{$termId}");
                flash_set('success', "Admission intake status updated.");
            }
        } elseif ($action === 'add_term') {
            $termName = trim($_POST['term_name'] ?? '');
            $year = trim($_POST['academic_year'] ?? date('Y'));
            $semNum = (int)($_POST['semester_number'] ?? 1);
            $start = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

            if (!empty($termName)) {
                $ins = $db->prepare("INSERT INTO semester_terms (term_name, academic_year, semester_number, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$termName, $year, $semNum, $start, $end]);
                log_audit('SEMESTER_TERM_ADDED', "Added new academic term: {$termName}");
                flash_set('success', "Academic term '{$termName}' created successfully.");
            }
        }
    }
    header("Location: " . APP_URL . "/admin/semesters.php");
    exit;
}

$terms = $db->query("SELECT * FROM semester_terms ORDER BY semester_number ASC, academic_year ASC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem;">
      <i class="fas fa-calendar-alt" style="color:var(--rmu-primary);"></i> Semester Management
    </h2>
    <p style="color:#64748b; font-size:0.88rem; margin:0;">
      Official 8-Semester Academic Terms, Intake Activation, and Term Duration Control
    </p>
  </div>

  <button type="button" onclick="openAddTermModal()" class="btn-primary" style="padding:0.55rem 1.1rem; font-size:0.86rem;">
    <i class="fas fa-plus-circle"></i> Add Academic Term
  </button>
</div>

<!-- 8 Semesters Overview Grid -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:1.25rem; margin-bottom:2rem;">
  <?php for ($s = 1; $s <= 8; $s++): ?>
    <?php
      $termMatch = null;
      foreach ($terms as $t) {
          if ((int)$t['semester_number'] === $s) {
              $termMatch = $t;
              break;
          }
      }
      $courseCount = (int)$db->query("SELECT COUNT(*) FROM student_courses WHERE semester LIKE '{$s}%'")->fetchColumn();
    ?>
    <div style="background:#ffffff; border:1.5px solid <?= ($termMatch && $termMatch['is_current']) ? '#3b82f6' : '#e2e8f0' ?>; border-radius:10px; padding:1.25rem; box-shadow:0 1px 4px rgba(0,0,0,0.05); position:relative;">
      <?php if ($termMatch && $termMatch['is_current']): ?>
        <span style="position:absolute; top:12px; right:12px; background:#1e40af; color:#fff; font-size:0.7rem; font-weight:800; padding:2px 8px; border-radius:12px;">ACTIVE TERM</span>
      <?php endif; ?>

      <div style="font-size:0.78rem; font-weight:800; color:var(--rmu-primary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">
        Semester <?= $s ?>
      </div>
      <h4 style="margin:0 0 0.5rem; font-size:1.05rem; font-weight:700; color:#0f172a;">
        <?= e($termMatch['term_name'] ?? ($s . 'th Semester')) ?>
      </h4>
      <div style="font-size:0.82rem; color:#64748b; margin-bottom:1rem;">
        Year: <strong><?= e($termMatch['academic_year'] ?? '2026') ?></strong> &bull; <?= $courseCount ?> Enrolled Course Sections
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px dashed #e2e8f0; padding-top:0.75rem;">
        <span style="font-size:0.78rem; font-weight:600; color:<?= ($termMatch && $termMatch['is_admission_open']) ? '#15803d' : '#94a3b8' ?>;">
          <i class="fas fa-door-<?= ($termMatch && $termMatch['is_admission_open']) ? 'open' : 'closed' ?>"></i> 
          Intake <?= ($termMatch && $termMatch['is_admission_open']) ? 'Open' : 'Closed' ?>
        </span>

        <?php if ($termMatch): ?>
          <form action="" method="POST" style="margin:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_current">
            <input type="hidden" name="id" value="<?= (int)$termMatch['id'] ?>">
            <button type="submit" style="background:<?= $termMatch['is_current'] ? '#dcfce7; color:#15803d;' : '#f1f5f9; color:#475569;' ?> border:1px solid #cbd5e1; padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:700; cursor:pointer;" <?= $termMatch['is_current'] ? 'disabled' : '' ?>>
              <?= $termMatch['is_current'] ? 'Current' : 'Set Active' ?>
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endfor; ?>
</div>

<!-- Modal: Add Academic Term -->
<div id="addTermModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:2000; align-items:center; justify-content:center; padding:1rem;">
  <div style="background:#ffffff; border-radius:10px; max-width:440px; width:100%; padding:1.5rem; box-shadow:0 15px 30px rgba(0,0,0,0.25);">
    <h3 style="margin:0 0 1rem; font-size:1.15rem; color:#0f2c59; font-weight:800;">Add Academic Term</h3>
    <form action="" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_term">

      <div class="form-group">
        <label class="form-label">Term Name *</label>
        <input type="text" name="term_name" placeholder="e.g. 1st Semester (Spring 2027)" class="form-control" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Semester Number *</label>
          <select name="semester_number" class="form-select" required>
            <?php for ($num = 1; $num <= 8; $num++): ?>
              <option value="<?= $num ?>"><?= $num ?>. Semester <?= $num ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Academic Year *</label>
          <input type="text" name="academic_year" value="<?= date('Y') ?>" class="form-control" required>
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
        <button type="button" onclick="closeAddTermModal()" style="padding:6px 14px; border:1px solid #cbd5e1; background:#f1f5f9; color:#475569; border-radius:6px; cursor:pointer;">Cancel</button>
        <button type="submit" class="btn-primary" style="padding:6px 16px;">Create Term</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddTermModal() {
  document.getElementById('addTermModal').style.display = 'flex';
}
function closeAddTermModal() {
  document.getElementById('addTermModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

