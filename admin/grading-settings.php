<?php
/**
 * Admin Panel: Configurable University Grading System & CGPA Rules
 * Rabindra Maitree University (RMU)
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'GPA & CGPA Settings';
$db = getDb();

// Handle POST actions (Add Rule, Edit Rule, Delete Rule, Reset to UGC Defaults, Update Academic Rules)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Add Rule
        if ($action === 'add_rule') {
            $min = (float)($_POST['min_marks'] ?? 0);
            $max = (float)($_POST['max_marks'] ?? 100);
            $grade = strtoupper(trim($_POST['letter_grade'] ?? ''));
            $point = (float)($_POST['grade_point'] ?? 0.00);
            $remarks = trim($_POST['remarks'] ?? '');
            $sort = (int)($_POST['sort_order'] ?? 10);

            if (!empty($grade) && $min >= 0 && $max <= 100 && $min <= $max) {
                $ins = $db->prepare("INSERT INTO grading_rules (min_marks, max_marks, letter_grade, grade_point, remarks, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->execute([$min, $max, $grade, $point, $remarks, $sort]);
                log_audit('GRADING_RULE_ADDED', "Added grading rule: {$grade} ({$min}-{$max}% = {$point})");
                flash_set('success', "Grading tier '{$grade}' added successfully.");
            } else {
                flash_set('error', 'Invalid marks range or letter grade.');
            }
        }

        // 2. Edit Rule
        elseif ($action === 'edit_rule') {
            $id = (int)($_POST['id'] ?? 0);
            $min = (float)($_POST['min_marks'] ?? 0);
            $max = (float)($_POST['max_marks'] ?? 100);
            $grade = strtoupper(trim($_POST['letter_grade'] ?? ''));
            $point = (float)($_POST['grade_point'] ?? 0.00);
            $remarks = trim($_POST['remarks'] ?? '');
            $sort = (int)($_POST['sort_order'] ?? 10);
            $isActive = !empty($_POST['is_active']) ? 1 : 0;

            if ($id > 0 && !empty($grade)) {
                $upd = $db->prepare("UPDATE grading_rules SET min_marks = ?, max_marks = ?, letter_grade = ?, grade_point = ?, remarks = ?, sort_order = ?, is_active = ? WHERE id = ?");
                $upd->execute([$min, $max, $grade, $point, $remarks, $sort, $isActive, $id]);
                log_audit('GRADING_RULE_EDITED', "Updated grading rule ID #{$id}: {$grade} ({$point})");
                flash_set('success', "Grading rule for '{$grade}' updated successfully.");
            }
        }

        // 3. Delete Rule
        elseif ($action === 'delete_rule') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $del = $db->prepare("DELETE FROM grading_rules WHERE id = ?");
                $del->execute([$id]);
                log_audit('GRADING_RULE_DELETED', "Deleted grading rule ID #{$id}");
                flash_set('success', "Grading tier removed successfully.");
            }
        }

        // 4. Reset to Official UGC Defaults
        elseif ($action === 'reset_ugc_defaults') {
            $db->exec("DELETE FROM grading_rules");
            $ugcRules = [
                [80.00, 100.00, 'A+', 4.00, 'Outstanding', 1],
                [75.00, 79.99,  'A',  3.75, 'Excellent',   2],
                [70.00, 74.99,  'A-', 3.50, 'Very Good',   3],
                [65.00, 69.99,  'B+', 3.25, 'Good',        4],
                [60.00, 64.99,  'B',  3.00, 'Satisfactory', 5],
                [55.00, 59.99,  'B-', 2.75, 'Above Average', 6],
                [50.00, 54.99,  'C+', 2.50, 'Average',     7],
                [45.00, 49.99,  'C',  2.25, 'Pass',        8],
                [40.00, 44.99,  'D',  2.00, 'Conditional Pass', 9],
                [0.00,  39.99,  'F',  0.00, 'Fail',        10]
            ];
            $insRule = $db->prepare("INSERT INTO grading_rules (min_marks, max_marks, letter_grade, grade_point, remarks, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($ugcRules as $r) {
                $insRule->execute($r);
            }
            log_audit('GRADING_RULES_RESET', "Reset grading scale to official UGC Bangladesh defaults");
            flash_set('success', "Grading scale has been reset to official UGC Bangladesh uniform standards.");
        }

        // 5. Academic Retake & CGPA Calculation Policy Settings
        elseif ($action === 'save_policy_settings') {
            $retakePolicy = trim($_POST['retake_policy'] ?? 'highest_grade');
            $minPassPoint = (float)($_POST['min_pass_point'] ?? 2.00);
            $fullMarksDefault = (int)($_POST['full_marks_default'] ?? 100);

            set_setting('retake_policy', $retakePolicy);
            set_setting('min_pass_point', (string)$minPassPoint);
            set_setting('full_marks_default', (string)$fullMarksDefault);

            log_audit('ACADEMIC_POLICY_UPDATED', "Updated academic retake policy to '{$retakePolicy}' and min passing point to {$minPassPoint}");
            flash_set('success', "Academic calculation policies saved successfully.");
        }
    }
    header("Location: " . APP_URL . "/admin/grading-settings.php");
    exit;
}

// Fetch all rules
$rules = $db->query("SELECT * FROM grading_rules ORDER BY sort_order ASC, min_marks DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch settings
$retakePolicy = get_setting('retake_policy', 'highest_grade');
$minPassPoint = (float)get_setting('min_pass_point', '2.00');
$fullMarksDefault = (int)get_setting('full_marks_default', '100');

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem;">
      <i class="fas fa-sliders-h" style="color:var(--rmu-primary);"></i> GPA &amp; CGPA Settings
    </h2>
    <p style="color:#64748b; font-size:0.88rem; margin:0;">
      Configurable University Grading Scale, Credit-Weighted Calculation Formulas &amp; Retake Policies
    </p>
  </div>

  <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
    <form action="" method="POST" style="margin:0;" onsubmit="return confirm('Reset all grading tiers to official UGC Bangladesh defaults?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_ugc_defaults">
      <button type="submit" class="btn-primary" style="background:#475569; padding:0.55rem 1rem; font-size:0.86rem; border:none; cursor:pointer;">
        <i class="fas fa-undo"></i> Reset to UGC Defaults
      </button>
    </form>
    <button type="button" onclick="openAddModal()" class="btn-primary" style="padding:0.55rem 1.1rem; font-size:0.86rem;">
      <i class="fas fa-plus-circle"></i> Add Grade Tier
    </button>
  </div>
</div>

<!-- 2-Column Layout: Grading Table (Left) + Calculation Policies (Right) -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:1.75rem; align-items:start;">

  <!-- Left: Grading Rules Table -->
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05);">
    <div style="padding:1rem 1.25rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
      <strong style="font-size:0.95rem; color:#0f172a;"><i class="fas fa-table" style="color:var(--rmu-primary);"></i> Configured Uniform Grading Scale (<?= count($rules) ?> Tiers)</strong>
      <span style="font-size:0.78rem; color:#15803d; font-weight:700;"><i class="fas fa-check-circle"></i> UGC Compliant (4.00 Scale)</span>
    </div>

    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.88rem;">
        <thead>
          <tr>
            <th>Numerical Range</th>
            <th>Letter Grade</th>
            <th>Grade Point</th>
            <th>Performance Remarks</th>
            <th>Status</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rules as $r): ?>
            <tr>
              <td>
                <strong><?= number_format((float)$r['min_marks'], 1) ?>%</strong> to <strong><?= number_format((float)$r['max_marks'], 1) ?>%</strong>
              </td>
              <td>
                <span style="background:<?= ($r['letter_grade'] === 'F') ? '#fee2e2; color:#b91c1c;' : '#eff6ff; color:#1d4ed8;' ?> padding:3px 10px; border-radius:4px; font-weight:800; font-size:0.95rem;">
                  <?= e($r['letter_grade']) ?>
                </span>
              </td>
              <td>
                <strong style="color:<?= ($r['grade_point'] >= 3.0) ? '#15803d' : (($r['grade_point'] > 0) ? '#b45309' : '#dc2626') ?>; font-size:1rem;">
                  <?= number_format((float)$r['grade_point'], 2) ?>
                </strong>
              </td>
              <td>
                <?= e($r['remarks']) ?>
              </td>
              <td>
                <span style="font-size:0.75rem; font-weight:700; color:<?= $r['is_active'] ? '#15803d' : '#94a3b8' ?>;">
                  <?= $r['is_active'] ? 'Active' : 'Disabled' ?>
                </span>
              </td>
              <td style="text-align:right; white-space:nowrap;">
                <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)" style="background:#eff6ff; color:#0284c7; border:none; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:0.82rem; margin-right:4px;">
                  <i class="fas fa-edit"></i> Edit
                </button>
                <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Delete tier <?= e($r['letter_grade']) ?>?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_rule">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" style="background:#fee2e2; color:#ef4444; border:none; padding:4px 7px; border-radius:4px; cursor:pointer; font-size:0.82rem;">
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

  <!-- Right: Calculation Policy Configuration -->
  <div>
    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.5rem; box-shadow:0 1px 4px rgba(0,0,0,0.05); margin-bottom:1.5rem;">
      <h4 style="margin:0 0 1rem; font-size:1.05rem; color:#0f2c59; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-calculator" style="color:var(--rmu-primary);"></i> Academic Calculation Rules
      </h4>

      <form action="" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_policy_settings">

        <div class="form-group">
          <label class="form-label" style="font-weight:700; font-size:0.84rem;">Retake / Course Repeat Policy</label>
          <select name="retake_policy" class="form-select" style="font-size:0.88rem;">
            <option value="highest_grade" <?= ($retakePolicy === 'highest_grade') ? 'selected' : '' ?>>Highest Grade Replaces Previous Grade</option>
            <option value="latest_attempt" <?= ($retakePolicy === 'latest_attempt') ? 'selected' : '' ?>>Latest Attempt Replaces Previous Grade</option>
            <option value="all_attempts" <?= ($retakePolicy === 'all_attempts') ? 'selected' : '' ?>>All Attempts Recorded in Transcript</option>
          </select>
          <small style="color:#64748b; font-size:0.76rem; display:block; margin-top:3px;">
            Determines how SGPA and CGPA treat repeated/cleared F grades.
          </small>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-weight:700; font-size:0.84rem;">Minimum Passing Grade Point</label>
          <input type="number" step="0.25" min="1.00" max="4.00" name="min_pass_point" value="<?= number_format($minPassPoint, 2) ?>" class="form-control" style="font-size:0.9rem; font-weight:700;">
          <small style="color:#64748b; font-size:0.76rem; display:block; margin-top:3px;">
            Standard is 2.00 (D Grade). Courses with points below this are treated as Failed.
          </small>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-weight:700; font-size:0.84rem;">Default Full Marks per Course</label>
          <input type="number" name="full_marks_default" value="<?= $fullMarksDefault ?>" class="form-control" style="font-size:0.9rem; font-weight:700;">
        </div>

        <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:0.65rem;">
          <i class="fas fa-save"></i> Save Academic Policies
        </button>
      </form>
    </div>

    <!-- Official UGC Math Formula Reference -->
    <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; font-size:0.82rem; color:#334155;">
      <h5 style="margin:0 0 0.5rem; font-size:0.88rem; font-weight:800; color:#0f2c59; text-transform:uppercase;">
        <i class="fas fa-square-root-alt" style="color:var(--rmu-primary);"></i> Formulas Implemented
      </h5>
      <p style="margin:0 0 0.5rem; line-height:1.4;">
        <strong>SGPA Formula:</strong><br>
        <code>SGPA = &Sigma;(Credit &times; Grade Point) / &Sigma;(Registered Credits)</code>
      </p>
      <p style="margin:0; line-height:1.4;">
        <strong>Credit-Weighted Cumulative CGPA:</strong><br>
        <code>Final CGPA = &Sigma;<sub>all semesters</sub>(Credit &times; Grade Point) / &Sigma;<sub>all semesters</sub>(Applicable Credits)</code>
      </p>
    </div>
  </div>

</div>

<!-- Modal: Add / Edit Grade Tier -->
<div id="tierModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:2000; align-items:center; justify-content:center; padding:1rem;">
  <div style="background:#ffffff; border-radius:10px; max-width:440px; width:100%; padding:1.5rem; box-shadow:0 15px 30px rgba(0,0,0,0.25);">
    <h3 id="tierModalTitle" style="margin:0 0 1rem; font-size:1.15rem; color:#0f2c59; font-weight:800;">
      Add Grade Tier
    </h3>
    <form action="" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="tierAction" value="add_rule">
      <input type="hidden" name="id" id="tierId" value="">

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Min Marks (%) *</label>
          <input type="number" step="0.5" min="0" max="100" name="min_marks" id="tierMin" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Max Marks (%) *</label>
          <input type="number" step="0.5" min="0" max="100" name="max_marks" id="tierMax" class="form-control" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Letter Grade *</label>
          <input type="text" name="letter_grade" id="tierGrade" placeholder="e.g. A+" class="form-control" required style="text-transform:uppercase; font-weight:800;">
        </div>
        <div class="form-group">
          <label class="form-label">Grade Point (out of 4.00) *</label>
          <input type="number" step="0.01" min="0" max="4.00" name="grade_point" id="tierPoint" placeholder="4.00" class="form-control" required style="font-weight:700;">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Performance Remarks</label>
        <input type="text" name="remarks" id="tierRemarks" placeholder="e.g. Outstanding" class="form-control">
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; align-items:center; margin-bottom:1.25rem;">
        <div>
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" id="tierSort" value="10" class="form-control">
        </div>
        <div style="padding-top:1.25rem;">
          <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-size:0.85rem; font-weight:600;">
            <input type="checkbox" name="is_active" id="tierActive" value="1" checked>
            <span>Active Tier</span>
          </label>
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
        <button type="button" onclick="closeTierModal()" style="padding:6px 14px; border:1px solid #cbd5e1; background:#f1f5f9; color:#475569; border-radius:6px; cursor:pointer;">Cancel</button>
        <button type="submit" class="btn-primary" style="padding:6px 16px;">Save Rule</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('tierModalTitle').innerText = 'Add Grade Tier';
  document.getElementById('tierAction').value = 'add_rule';
  document.getElementById('tierId').value = '';
  document.getElementById('tierMin').value = '';
  document.getElementById('tierMax').value = '';
  document.getElementById('tierGrade').value = '';
  document.getElementById('tierPoint').value = '';
  document.getElementById('tierRemarks').value = '';
  document.getElementById('tierSort').value = '10';
  document.getElementById('tierActive').checked = true;
  document.getElementById('tierModal').style.display = 'flex';
}

function openEditModal(rule) {
  document.getElementById('tierModalTitle').innerText = 'Edit Grade Tier: ' + rule.letter_grade;
  document.getElementById('tierAction').value = 'edit_rule';
  document.getElementById('tierId').value = rule.id;
  document.getElementById('tierMin').value = parseFloat(rule.min_marks);
  document.getElementById('tierMax').value = parseFloat(rule.max_marks);
  document.getElementById('tierGrade').value = rule.letter_grade;
  document.getElementById('tierPoint').value = parseFloat(rule.grade_point).toFixed(2);
  document.getElementById('tierRemarks').value = rule.remarks || '';
  document.getElementById('tierSort').value = rule.sort_order || 10;
  document.getElementById('tierActive').checked = (parseInt(rule.is_active) === 1);
  document.getElementById('tierModal').style.display = 'flex';
}

function closeTierModal() {
  document.getElementById('tierModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

