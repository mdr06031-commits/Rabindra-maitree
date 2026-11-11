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

// Handle Actions (Add Term, Edit Term, Set Active Term, Toggle Admission Open, Update Program Structure)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } elseif (!is_admin_manager_role()) {
        log_audit('SEMESTER_ACTION_DENIED', "Unauthorized role '" . current_admin_role() . "' attempted action '" . ($_POST['action'] ?? '') . "' in semesters.php");
        flash_set('error', 'Access denied: Only Administrator accounts may modify semester terms or program semester structures.');
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
        } elseif ($action === 'update_program_structure') {
            $progId = (int)($_POST['program_id'] ?? 0);
            $rawSem = (int)($_POST['total_semesters'] ?? 8);
            $totalCredits = max(10, (int)($_POST['total_credits'] ?? 60));

            $pStmt = $db->prepare("SELECT * FROM programs WHERE id = ? LIMIT 1");
            $pStmt->execute([$progId]);
            $progRow = $pStmt->fetch(PDO::FETCH_ASSOC);

            if ($progRow) {
                $struct = get_program_academic_structure($progId);
                $degType = $struct['degree_type'];
                if ($rawSem >= 1 && $rawSem <= 12) {
                    $newSems = $rawSem;
                } elseif ($degType === 'B.Ed') {
                    $newSems = 2;
                } elseif ($degType === "Master's") {
                    $newSems = 4;
                } else {
                    $newSems = 8;
                }

                if (isset($_POST['duration_years']) && $_POST['duration_years'] !== '' && is_numeric($_POST['duration_years'])) {
                    $newYears = max(0.5, min(6.0, round((float)$_POST['duration_years'], 1)));
                } else {
                    if ($newSems === 8) {
                        $newYears = 4.0;
                    } elseif ($newSems === 4) {
                        $newYears = 2.0;
                    } elseif ($newSems === 3) {
                        $newYears = 1.5;
                    } elseif ($newSems === 2) {
                        $newYears = 1.0;
                    } else {
                        $newYears = max(0.5, min(6.0, round($newSems * 0.5, 1)));
                    }
                }

                $durLabel = format_duration_years_label($newYears, $newSems);

                $db->prepare("
                    UPDATE programs
                    SET duration_years = ?, total_semesters = ?, total_credits = ?, duration = ?
                    WHERE id = ?
                ")->execute([$newYears, $newSems, $totalCredits, $durLabel, $progId]);

                $adminUserId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                $editReason = trim($_POST['edit_reason'] ?? '') ?: 'Program duration/semester structure configured from Semester Management';
                $comparePairs = [
                    'duration_years'  => [isset($progRow['duration_years']) ? (string)(float)$progRow['duration_years'] : '', (string)$newYears],
                    'total_semesters' => [isset($progRow['total_semesters']) ? (string)(int)$progRow['total_semesters'] : '', (string)$newSems],
                    'total_credits'   => [isset($progRow['total_credits']) ? (string)(int)$progRow['total_credits'] : '', (string)$totalCredits],
                ];
                foreach ($comparePairs as $fKey => $pair) {
                    if ($pair[0] !== $pair[1]) {
                        log_academic_edit_history('program', $progId, null, $adminUserId, $fKey, $pair[0], $pair[1], $editReason);
                    }
                }

                // Propagate updated structure and advisory expected passing year to active students (never touch passing_year)
                $stAct = $db->prepare("
                    SELECT id, academic_session, batch, expected_passing_year
                    FROM students
                    WHERE program_id = ? AND status NOT IN ('Deleted', 'Graduated')
                ");
                $stAct->execute([$progId]);
                $updSt = $db->prepare("
                    UPDATE students
                    SET duration_years = ?, program_duration = ?, total_semesters = ?, total_credits_required = ?, expected_passing_year = ?
                    WHERE id = ?
                ");
                foreach ($stAct->fetchAll(PDO::FETCH_ASSOC) as $stRow) {
                    $stSess = !empty($stRow['academic_session']) ? (string)$stRow['academic_session'] : (string)($stRow['batch'] ?? '');
                    $expCalc = calculate_expected_passing_year($stSess, $newYears);
                    $newExpYear = $expCalc ? (int)$expCalc['expected_year'] : ($stRow['expected_passing_year'] !== null ? (int)$stRow['expected_passing_year'] : null);
                    $updSt->execute([$newYears, $durLabel, $newSems, $totalCredits, $newExpYear, (int)$stRow['id']]);
                }

                // Keep advisory expected_passing_year aligned on non-rejected admissions (never touch passing_year)
                $admAct = $db->prepare("SELECT id, academic_session FROM admissions WHERE program_id = ? AND status != 'Rejected'");
                $admAct->execute([$progId]);
                $updAdm = $db->prepare("UPDATE admissions SET expected_passing_year = ? WHERE id = ?");
                foreach ($admAct->fetchAll(PDO::FETCH_ASSOC) as $admRow) {
                    $admExp = calculate_expected_passing_year((string)($admRow['academic_session'] ?? ''), $newYears);
                    if ($admExp) {
                        $updAdm->execute([(int)$admExp['expected_year'], (int)$admRow['id']]);
                    }
                }

                $stIds = $db->prepare("SELECT id FROM students WHERE program_id = ? AND status != 'Deleted'");
                $stIds->execute([$progId]);
                foreach ($stIds->fetchAll(PDO::FETCH_COLUMN) as $sPk) {
                    sync_student_semester_results((int)$sPk);
                }

                log_audit('PROGRAM_STRUCTURE_UPDATED', "Configured {$progRow['title_en']} ({$degType}): {$durLabel}, {$totalCredits} credits.");
                flash_set('success', "Updated '{$progRow['title_en']}' structure to {$durLabel} &bull; {$totalCredits} Credits.");
            }
        }
    }
    header("Location: " . APP_URL . "/admin/semesters.php");
    exit;
}

$terms = $db->query("SELECT * FROM semester_terms ORDER BY semester_number ASC, academic_year ASC")->fetchAll(PDO::FETCH_ASSOC);
$programsList = $db->query("SELECT p.*, d.name_en AS dept_name FROM programs p LEFT JOIN departments d ON p.department_id = d.id ORDER BY FIELD(p.degree_type, 'Bachelor / Honours', 'B.Ed', \"Master's\"), p.sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem;">
      <i class="fas fa-calendar-alt" style="color:var(--rmu-primary);"></i> Semester Management
    </h2>
    <p style="color:#64748b; font-size:0.88rem; margin:0;">
      Official Academic Terms, Intake Activation, Course Duration &amp; Semester Curriculum Control
    </p>
  </div>

  <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
    <a href="<?= APP_URL ?>/admin/academic-sessions.php" class="btn-primary" style="padding:0.55rem 1rem; font-size:0.86rem; background:#1e40af;">
      <i class="fas fa-calendar-check"></i> Academic Sessions &amp; Passing Years
    </a>
    <button type="button" onclick="openAddTermModal()" class="btn-primary" style="padding:0.55rem 1.1rem; font-size:0.86rem;">
      <i class="fas fa-plus-circle"></i> Add Academic Term
    </button>
  </div>
</div>

<!-- Semesters Overview Grid -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1.1rem; margin-bottom:2rem;">
  <?php
    $maxGridSem = 8;
    foreach ($terms as $tItem) {
        if ((int)($tItem['semester_number'] ?? 0) > $maxGridSem) {
            $maxGridSem = min(12, (int)$tItem['semester_number']);
        }
    }
    $countCourseStmt = $db->prepare("SELECT COUNT(*) FROM student_courses WHERE semester LIKE ?");
  ?>
  <?php for ($s = 1; $s <= $maxGridSem; $s++): ?>
    <?php
      $termMatch = null;
      foreach ($terms as $t) {
          if ((int)$t['semester_number'] === $s) {
              $termMatch = $t;
              break;
          }
      }
      $countCourseStmt->execute([$s . '%']);
      $courseCount = (int)$countCourseStmt->fetchColumn();
      $ordTitle = get_semester_ordinal_title($s);
    ?>
    <div style="background:#ffffff; border:1.5px solid <?= ($termMatch && $termMatch['is_current']) ? '#3b82f6' : '#e2e8f0' ?>; border-radius:10px; padding:1.15rem; box-shadow:0 1px 4px rgba(0,0,0,0.05); position:relative;">
      <?php if ($termMatch && $termMatch['is_current']): ?>
        <span style="position:absolute; top:12px; right:12px; background:#1e40af; color:#fff; font-size:0.7rem; font-weight:800; padding:2px 8px; border-radius:12px;">ACTIVE TERM</span>
      <?php endif; ?>

      <div style="font-size:0.78rem; font-weight:800; color:var(--rmu-primary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">
        Semester <?= $s ?>
      </div>
      <h4 style="margin:0 0 0.5rem; font-size:1.05rem; font-weight:700; color:#0f172a;">
        <?= e($termMatch['term_name'] ?? $ordTitle) ?>
      </h4>
      <div style="font-size:0.82rem; color:#64748b; margin-bottom:1rem;">
        Year: <strong><?= e($termMatch['academic_year'] ?? date('Y')) ?></strong> &bull; <?= $courseCount ?> Enrolled Course Sections
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

<!-- Program Semester & Credit Structure Configuration -->
<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; margin-bottom:2rem; box-shadow:0 1px 4px rgba(0,0,0,0.05);">
  <div style="padding:1rem 1.5rem; background:#0f172a; color:#ffffff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
    <div>
      <h3 style="margin:0; font-size:1.05rem; font-weight:700;">
        <i class="fas fa-sitemap" style="color:#38bdf8; margin-right:6px;"></i> Automatic Program Duration, Semesters &amp; Credits Configuration
      </h3>
      <small style="color:#94a3b8;">Defaults: Bachelor / Honours (4 Yrs / 8 Sem) &bull; B.Ed (1 Yr / 2 Sem) &bull; Master's (2 Yrs / 4 Sem) — Customizable (0.5–6 Yrs, 1–12 Semesters)</small>
    </div>
    <a href="<?= APP_URL ?>/admin/programs.php" style="color:#38bdf8; font-size:0.82rem; font-weight:700; text-decoration:none;">
      <i class="fas fa-sliders-h"></i> Full Program Editor
    </a>
  </div>

  <div class="table-responsive">
    <table class="rmu-table" style="box-shadow:none; margin:0;">
      <thead>
        <tr>
          <th>Program &amp; Department</th>
          <th>Degree Type</th>
          <th>Course Duration (Years)</th>
          <th>Semesters</th>
          <th>Total Credits</th>
          <th style="text-align:right;">Save Structure</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($programsList as $pl): ?>
          <?php
            $ps = get_program_academic_structure((int)$pl['id']);
            $dType = $ps['degree_type'];
            $curSems = (int)$ps['total_semesters'];
            $curDurYears = (float)$ps['duration_years'];
            $curCredits = (int)$ps['total_credits'];
            $formId = 'progStructForm' . (int)$pl['id'];
          ?>
          <tr>
            <td>
              <strong style="color:#0f172a; display:block; font-size:0.9rem;"><?= e($pl['title_en']) ?></strong>
              <small style="color:#64748b;"><?= e($pl['dept_name'] ?? 'Academic Department') ?> &bull; <em><?= e($ps['duration_label']) ?></em></small>
            </td>
            <td>
              <span style="display:inline-block; padding:2px 8px; border-radius:4px; font-size:0.75rem; font-weight:700; background:<?= ($dType === 'B.Ed') ? '#fef3c7; color:#92400e;' : (($dType === "Master's") ? '#ede9fe; color:#5b21b6;' : '#dbeafe; color:#1d4ed8;') ?>">
                <?= e($dType) ?>
              </span>
            </td>
            <td>
              <input type="number" step="0.5" min="0.5" max="6" name="duration_years" id="durInput<?= (int)$pl['id'] ?>" form="<?= $formId ?>" value="<?= htmlspecialchars((string)$curDurYears) ?>" class="form-control" style="padding:4px 8px; font-size:0.84rem; width:84px;" required>
            </td>
            <td>
              <select name="total_semesters" form="<?= $formId ?>" class="form-select" style="padding:4px 8px; font-size:0.82rem; max-width:170px;" onchange="syncRowDurFromSems(<?= (int)$pl['id'] ?>, this.value)">
                <?php for ($sOpt = 1; $sOpt <= 12; $sOpt++): ?>
                  <option value="<?= $sOpt ?>" <?= ($curSems === $sOpt) ? 'selected' : '' ?>>
                    <?= $sOpt ?> <?= $sOpt === 1 ? 'Semester' : 'Semesters' ?>
                    <?php
                      if ($dType === 'Bachelor / Honours' && $sOpt === 8) echo ' (Default)';
                      elseif ($dType === 'B.Ed' && $sOpt === 2) echo ' (Default)';
                      elseif ($dType === "Master's" && $sOpt === 4) echo ' (Default)';
                    ?>
                  </option>
                <?php endfor; ?>
              </select>
            </td>
            <td>
              <input type="number" name="total_credits" form="<?= $formId ?>" value="<?= $curCredits ?>" min="10" max="240" class="form-control" style="padding:4px 8px; font-size:0.84rem; width:88px;">
            </td>
            <td style="text-align:right; white-space:nowrap;">
              <form id="<?= $formId ?>" action="<?= APP_URL ?>/admin/semesters.php" method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_program_structure">
                <input type="hidden" name="program_id" value="<?= (int)$pl['id'] ?>">
                <?php if (is_admin_manager_role()): ?>
                  <button type="submit" class="btn-primary" style="padding:5px 12px; font-size:0.78rem;">
                    <i class="fas fa-save"></i> Update
                  </button>
                <?php else: ?>
                  <span style="font-size:0.75rem; color:#94a3b8;">Admin only</span>
                <?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
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
            <?php for ($num = 1; $num <= 12; $num++): ?>
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
function syncRowDurFromSems(progId, semVal) {
  var inp = document.getElementById('durInput' + progId);
  if (!inp) return;
  var sCount = parseInt(semVal, 10) || 8;
  var yNum = (sCount === 8) ? 4 : (sCount === 4 ? 2 : (sCount === 3 ? 1.5 : (sCount === 2 ? 1 : (sCount * 0.5))));
  inp.value = (Math.abs(yNum - Math.round(yNum)) < 0.05) ? String(Math.round(yNum)) : yNum.toFixed(1);
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

