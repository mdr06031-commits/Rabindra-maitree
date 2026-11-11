<?php
/**
 * Admin Panel: Degree Programs Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Manage Degree Programs';
$db = getDb();

// Handle Add/Edit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } elseif (!is_admin_manager_role()) {
        log_audit('PROGRAM_ACTION_DENIED', "Unauthorized role '" . current_admin_role() . "' attempted action '" . ($_POST['action'] ?? '') . "' in programs.php");
        flash_set('error', 'Access denied: Only Administrator accounts may add, modify, or delete degree programs.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $adminUserId = (int)($_SESSION['user_id'] ?? 1);
            $res = permanently_delete_program_record($id, $adminUserId);
            if (!empty($res['success'])) {
                flash_set('success', "Degree program deleted successfully.");
            } else {
                flash_set('error', $res['error'] ?? 'Failed to delete degree program.');
            }
        } else {
            $deptId = (int)($_POST['department_id'] ?? 0);
            $titleEn = trim($_POST['title_en'] ?? '');
            $titleBn = trim($_POST['title_bn'] ?? '');
            $degreeType = trim($_POST['degree_type'] ?? 'Bachelor / Honours');
            if (!in_array($degreeType, ['Bachelor / Honours', 'B.Ed', "Master's"], true)) {
                $degreeType = 'Bachelor / Honours';
            }
            $level = trim($_POST['degree_level'] ?? ($degreeType === "Master's" ? 'Postgraduate' : 'Undergraduate'));

            // Allow configurable semester counts (1-12) and duration years (0.5-6.0) per approved curriculum
            $rawSems = (int)($_POST['total_semesters'] ?? 0);
            if ($degreeType === 'Bachelor / Honours') {
                $totalSemesters = ($rawSems >= 1 && $rawSems <= 12) ? $rawSems : 8;
                $level = trim($_POST['degree_level'] ?? 'Undergraduate') ?: 'Undergraduate';
            } elseif ($degreeType === 'B.Ed') {
                $totalSemesters = ($rawSems >= 1 && $rawSems <= 12) ? $rawSems : 2;
                $level = trim($_POST['degree_level'] ?? 'Undergraduate') ?: 'Undergraduate';
            } else {
                $totalSemesters = ($rawSems >= 1 && $rawSems <= 12) ? $rawSems : 4;
                $level = 'Postgraduate';
            }

            if (isset($_POST['duration_years']) && $_POST['duration_years'] !== '' && is_numeric($_POST['duration_years'])) {
                $durationYears = max(0.5, min(6.0, round((float)$_POST['duration_years'], 1)));
            } else {
                if ($totalSemesters === 8) {
                    $durationYears = 4.0;
                } elseif ($totalSemesters === 4) {
                    $durationYears = 2.0;
                } elseif ($totalSemesters === 3) {
                    $durationYears = 1.5;
                } elseif ($totalSemesters === 2) {
                    $durationYears = 1.0;
                } else {
                    $durationYears = max(0.5, min(6.0, round($totalSemesters * 0.5, 1)));
                }
            }

            $duration = trim($_POST['duration'] ?? '');
            if ($duration === '') {
                $duration = format_duration_years_label($durationYears, $totalSemesters);
            }
            $credits = max(10, (int)($_POST['total_credits'] ?? ($totalSemesters * 15)));

            $semCreditsMap = [];
            if (!empty($_POST['sem_credits']) && is_array($_POST['sem_credits'])) {
                for ($si = 1; $si <= $totalSemesters; $si++) {
                    if (isset($_POST['sem_credits'][$si]) && is_numeric($_POST['sem_credits'][$si]) && (float)$_POST['sem_credits'][$si] > 0) {
                        $semCreditsMap[(string)$si] = round((float)$_POST['sem_credits'][$si], 1);
                    }
                }
            }
            $semCreditsJson = !empty($semCreditsMap) ? json_encode($semCreditsMap) : null;

            $eligibility = trim($_POST['eligibility'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $tuitionFee = trim($_POST['tuition_fee'] ?? '');
            $sortOrder = (int)($_POST['sort_order'] ?? 0);

            if (empty($titleEn) || $deptId <= 0) {
                flash_set('error', 'Department and program title are required.');
            } else {
                try {
                    if ($action === 'add') {
                        $slug = generate_unique_slug($db, 'programs', $titleEn);
                        $stmt = $db->prepare("
                            INSERT INTO programs (department_id, slug, title_en, title_bn, degree_level, degree_type, duration, duration_years, total_semesters, total_credits, semester_credits_json, eligibility, description, tuition_fee, sort_order)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$deptId, $slug, $titleEn, $titleBn, $level, $degreeType, $duration, $durationYears, $totalSemesters, $credits, $semCreditsJson, $eligibility, $description, $tuitionFee, $sortOrder]);
                        log_audit('PROGRAM_ADDED', "Added program: {$titleEn} ({$degreeType}, {$totalSemesters} Semesters)");
                        flash_set('success', "Program '{$titleEn}' added successfully.");
                    } elseif ($action === 'edit') {
                        $id = (int)($_POST['id'] ?? 0);
                        $oldStmt = $db->prepare("SELECT * FROM programs WHERE id = ? LIMIT 1");
                        $oldStmt->execute([$id]);
                        $oldProg = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                        $stmt = $db->prepare("
                            UPDATE programs 
                            SET department_id = ?, title_en = ?, title_bn = ?, degree_level = ?, degree_type = ?, duration = ?, duration_years = ?, total_semesters = ?, total_credits = ?, semester_credits_json = ?, eligibility = ?, description = ?, tuition_fee = ?, sort_order = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$deptId, $titleEn, $titleBn, $level, $degreeType, $duration, $durationYears, $totalSemesters, $credits, $semCreditsJson, $eligibility, $description, $tuitionFee, $sortOrder, $id]);

                        $adminUserId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                        $editReason = trim($_POST['structure_edit_reason'] ?? '');
                        if ($editReason === '') {
                            $editReason = 'Program duration/semester structure updated from Academic Degree Programs panel';
                        }
                        $structCompare = [
                            'degree_type'     => [(string)($oldProg['degree_type'] ?? ''), $degreeType],
                            'duration_years'  => [isset($oldProg['duration_years']) ? (string)(float)$oldProg['duration_years'] : '', (string)$durationYears],
                            'total_semesters' => [isset($oldProg['total_semesters']) ? (string)(int)$oldProg['total_semesters'] : '', (string)$totalSemesters],
                            'total_credits'   => [isset($oldProg['total_credits']) ? (string)(int)$oldProg['total_credits'] : '', (string)$credits],
                        ];
                        foreach ($structCompare as $fKey => $pair) {
                            if ($pair[0] !== $pair[1]) {
                                log_academic_edit_history('program', $id, null, $adminUserId, $fKey, $pair[0], $pair[1], $editReason);
                            }
                        }

                        // Propagate updated degree/semester structure & advisory expected completion year to active students
                        // CRITICAL: Never alter students.passing_year or admissions.passing_year!
                        $activeStStmt = $db->prepare("
                            SELECT id, academic_session, batch, expected_passing_year
                            FROM students
                            WHERE program_id = ? AND status NOT IN ('Deleted', 'Graduated')
                        ");
                        $activeStStmt->execute([$id]);
                        $updStStruct = $db->prepare("
                            UPDATE students
                            SET degree_type = ?, duration_years = ?, program_duration = ?, total_semesters = ?, total_credits_required = ?, expected_passing_year = ?
                            WHERE id = ?
                        ");
                        foreach ($activeStStmt->fetchAll(PDO::FETCH_ASSOC) as $stItem) {
                            $stSess = !empty($stItem['academic_session']) ? (string)$stItem['academic_session'] : (string)($stItem['batch'] ?? '');
                            $expCalc = calculate_expected_passing_year($stSess, $durationYears);
                            $newExpYear = $expCalc ? (int)$expCalc['expected_year'] : ($stItem['expected_passing_year'] !== null ? (int)$stItem['expected_passing_year'] : null);
                            $updStStruct->execute([$degreeType, $durationYears, $duration, $totalSemesters, $credits, $newExpYear, (int)$stItem['id']]);
                        }

                        // Sync advisory expected_passing_year for active admissions tied to this program (never alter passing_year)
                        $admSyncStmt = $db->prepare("
                            SELECT id, academic_session, expected_passing_year
                            FROM admissions
                            WHERE program_id = ? AND status != 'Rejected'
                        ");
                        $admSyncStmt->execute([$id]);
                        $updAdmExp = $db->prepare("UPDATE admissions SET expected_passing_year = ? WHERE id = ?");
                        foreach ($admSyncStmt->fetchAll(PDO::FETCH_ASSOC) as $admItem) {
                            $admExp = calculate_expected_passing_year((string)($admItem['academic_session'] ?? ''), $durationYears);
                            if ($admExp) {
                                $updAdmExp->execute([(int)$admExp['expected_year'], (int)$admItem['id']]);
                            }
                        }

                        $stSync = $db->prepare("SELECT id FROM students WHERE program_id = ? AND status != 'Deleted'");
                        $stSync->execute([$id]);
                        foreach ($stSync->fetchAll(PDO::FETCH_COLUMN) as $sPk) {
                            sync_student_semester_results((int)$sPk);
                        }

                        log_audit('PROGRAM_EDITED', "Updated program ID {$id}: {$titleEn} ({$degreeType}, {$durationYears} Years / {$totalSemesters} Semesters, {$credits} Cr)");
                        flash_set('success', "Program '{$titleEn}' updated ({$duration}) and synchronized with enrolled students without altering any student's Passing Year.");
                    }
                } catch (PDOException $e) {
                    flash_set('error', 'Could not save program record (duplicate identifier or database constraint). Please check your inputs and try again.');
                }
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

<div id="programsAjaxAlert" style="display: none; margin-bottom: 1.5rem;"></div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 0.75rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">Academic Degree Programs</h2>
    <span style="color: #64748b; font-size: 0.88rem;">Manage undergraduate and graduate degree curricula, custom course durations, semester counts, and tuition costs.</span>
  </div>
  <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
    <a href="<?= APP_URL ?>/admin/semesters.php" class="btn-primary" style="padding: 0.45rem 0.9rem; font-size: 0.82rem; background: #0f2c59;">
      <i class="fas fa-sitemap"></i> Quick Duration Matrix
    </a>
    <a href="<?= APP_URL ?>/admin/academic-sessions.php" class="btn-primary" style="padding: 0.45rem 0.9rem; font-size: 0.82rem; background: #1e40af;">
      <i class="fas fa-calendar-check"></i> Sessions &amp; Passing Years
    </a>
  </div>
</div>

<div class="rmu-prog-split-grid" style="display: grid; grid-template-columns: 1.2fr 2fr; gap: 2rem; align-items: start;">
  <!-- Form -->
  <div class="rmu-prog-form-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem;">
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

      <?php
        $curDegType = $editProg['degree_type'] ?? 'Bachelor / Honours';
        $curSems = (int)($editProg['total_semesters'] ?? ($curDegType === 'B.Ed' ? 2 : ($curDegType === "Master's" ? 4 : 8)));
        $curDurYears = (float)($editProg['duration_years'] ?? ($curSems === 8 ? 4.0 : ($curSems === 4 ? 2.0 : ($curSems === 3 ? 1.5 : 1.0))));
        $curSemCredits = [];
        if (!empty($editProg['semester_credits_json'])) {
            $dec = json_decode((string)$editProg['semester_credits_json'], true);
            if (is_array($dec)) $curSemCredits = $dec;
        }
      ?>
      <div class="rmu-prog-2col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Degree Type <span class="required">*</span></label>
          <select name="degree_type" id="progDegreeType" class="form-select" onchange="syncProgramStructureForm()">
            <option value="Bachelor / Honours" <?= ($curDegType === 'Bachelor / Honours') ? 'selected' : '' ?>>Bachelor / Honours</option>
            <option value="B.Ed" <?= ($curDegType === 'B.Ed') ? 'selected' : '' ?>>B.Ed</option>
            <option value="Master's" <?= ($curDegType === "Master's") ? 'selected' : '' ?>>Master's</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Degree Level</label>
          <select name="degree_level" id="progDegreeLevel" class="form-select">
            <option value="Undergraduate" <?= (($editProg['degree_level'] ?? '') === 'Undergraduate') ? 'selected' : '' ?>>Undergraduate</option>
            <option value="Postgraduate" <?= (($editProg['degree_level'] ?? '') === 'Postgraduate') ? 'selected' : '' ?>>Postgraduate</option>
            <option value="Diploma" <?= (($editProg['degree_level'] ?? '') === 'Diploma') ? 'selected' : '' ?>>Diploma</option>
          </select>
        </div>
      </div>

      <div class="rmu-prog-3col" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
        <div class="form-group">
          <label class="form-label">Configured Semesters</label>
          <select name="total_semesters" id="progTotalSemesters" class="form-select" onchange="syncSemesterCount()">
            <?php for ($optS = 1; $optS <= 12; $optS++): ?>
              <?php
                $optYrs = ($optS * 0.5);
                $optYrsStr = (abs($optYrs - round($optYrs)) < 0.05) ? ((int)round($optYrs) . ((int)round($optYrs) === 1 ? ' Year' : ' Years')) : (number_format($optYrs, 1) . ' Years');
                $optSemWord = ($optS === 1) ? 'Semester' : 'Semesters';
              ?>
              <option value="<?= $optS ?>" <?= ($curSems === $optS) ? 'selected' : '' ?>><?= $optS ?> <?= $optSemWord ?> (<?= $optYrsStr ?>)</option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Duration (Years)</label>
          <input type="number" step="0.5" min="0.5" max="6" name="duration_years" id="progDurationYears" value="<?= htmlspecialchars((string)$curDurYears) ?>" class="form-control" oninput="syncDurationTextFromInputs()" required>
        </div>
        <div class="form-group">
          <label class="form-label">Total Credits</label>
          <input type="number" name="total_credits" id="progTotalCredits" value="<?= (int)($editProg['total_credits'] ?? 140) ?>" class="form-control" required>
        </div>
      </div>

      <div class="rmu-prog-2col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Duration Display Label</label>
          <input type="text" name="duration" id="progDurationText" value="<?= e($editProg['duration'] ?? '4 Years (8 Semesters)') ?>" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Tuition Fee Info</label>
          <input type="text" name="tuition_fee" value="<?= e($editProg['tuition_fee'] ?? 'BDT 3,50,000') ?>" class="form-control" placeholder="e.g. BDT 3,80,000">
        </div>
      </div>

      <div class="form-group" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:0.75rem;">
        <label class="form-label" style="margin-bottom:0.4rem; font-size:0.8rem;">Optional Per-Semester Credit Distribution (Up to 12 Semesters)</label>
        <div class="rmu-prog-4col" style="display:grid; grid-template-columns:repeat(4, 1fr); gap:0.4rem;">
          <?php for ($sc = 1; $sc <= 12; $sc++): ?>
            <div class="prog-sem-cr-cell" data-sem="<?= $sc ?>" style="<?= ($sc > $curSems) ? 'display:none;' : '' ?>">
              <small style="display:block; color:#64748b; font-size:0.7rem;">Sem <?= $sc ?> Cr</small>
              <input type="number" step="0.5" min="0" max="30" name="sem_credits[<?= $sc ?>]" value="<?= isset($curSemCredits[(string)$sc]) ? htmlspecialchars((string)$curSemCredits[(string)$sc]) : '' ?>" class="form-control" style="padding:4px 6px; font-size:0.8rem;" placeholder="Auto">
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <?php if ($editProg): ?>
        <div class="form-group">
          <label class="form-label" style="color:#0f2c59;">Curriculum Change Reason (Audit Log)</label>
          <input type="text" name="structure_edit_reason" class="form-control" placeholder="e.g. Approved Academic Council curriculum update (optional)">
        </div>
      <?php endif; ?>

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
        <tbody id="programsTableBody">
          <?php if (!empty($programs)): ?>
            <?php foreach ($programs as $p): ?>
              <?php $pDurYr = isset($p['duration_years']) && (float)$p['duration_years'] > 0 ? (float)$p['duration_years'] : 4.0; ?>
              <tr data-program-row-id="<?= (int)$p['id'] ?>">
                <td>
                  <strong style="color: #0f172a; display: block; font-size: 0.92rem;"><?= e($p['title_en']) ?></strong>
                  <span style="font-size: 0.8rem; color: var(--rmu-muted); font-family: 'Hind Siliguri', sans-serif;"><?= e($p['title_bn']) ?></span>
                </td>
                <td style="font-size: 0.82rem; color: #475569;"><?= e($p['dept_code']) ?></td>
                <td>
                  <span class="notice-category-badge <?= ($p['degree_level'] === 'Undergraduate') ? 'badge-academic' : 'badge-scholarship' ?>">
                    <?= e($p['degree_type'] ?: $p['degree_level']) ?>
                  </span>
                  <div style="font-size:0.72rem; color:#64748b; margin-top:2px;"><?= e($p['degree_level']) ?></div>
                </td>
                <td style="font-size: 0.82rem; color: #334155;">
                  <strong><?= (int)($p['total_semesters'] ?? 8) ?> Semesters</strong> &bull; <?= rtrim(rtrim(number_format($pDurYr, 1), '0'), '.') ?> Yr &bull; <?= (int)$p['total_credits'] ?> Cr<br>
                  <small style="color:#64748b;"><?= e($p['duration']) ?></small>
                </td>
                <td style="font-size: 0.82rem; font-weight: 600; color: var(--rmu-primary);">
                  <?= e($p['tuition_fee'] ?? 'Standard') ?>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <a href="<?= APP_URL ?>/admin/programs.php?edit=<?= (int)$p['id'] ?>" style="color: var(--rmu-primary); font-weight: 600; font-size: 0.85rem; margin-right: 6px;">
                    <i class="fas fa-edit"></i> Edit
                  </a>
                  <?php if (is_admin_manager_role()): ?>
                    <form action="<?= APP_URL ?>/admin/programs.php" method="POST" style="display: inline;" onsubmit="return deleteProgramRecord(event, <?= (int)$p['id'] ?>, this);">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                      <button type="submit"
                              data-program-id="<?= (int)$p['id'] ?>"
                              style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 0.85rem;"
                              title="Delete Program">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr id="programsEmptyRow"><td colspan="6" style="text-align: center; color: #64748b;">No degree programs found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function syncProgramStructureForm() {
  var deg = document.getElementById('progDegreeType').value;
  var lvl = document.getElementById('progDegreeLevel');
  var sems = document.getElementById('progTotalSemesters');
  if (deg === 'Bachelor / Honours') {
    lvl.value = 'Undergraduate';
    sems.value = '8';
  } else if (deg === 'B.Ed') {
    lvl.value = 'Undergraduate';
    sems.value = '2';
  } else {
    lvl.value = 'Postgraduate';
    sems.value = '4';
  }
  syncSemesterCount();
}
function syncSemesterCount() {
  var sCount = parseInt(document.getElementById('progTotalSemesters').value, 10) || 8;
  var yNum = (sCount === 8) ? 4 : (sCount === 4 ? 2 : (sCount === 3 ? 1.5 : (sCount === 2 ? 1 : (sCount * 0.5))));
  var yrs = (Math.abs(yNum - Math.round(yNum)) < 0.05) ? String(Math.round(yNum)) : yNum.toFixed(1);
  document.getElementById('progDurationYears').value = yrs;
  syncDurationTextFromInputs();
  var cells = document.querySelectorAll('.prog-sem-cr-cell');
  cells.forEach(function(cell) {
    var sn = parseInt(cell.getAttribute('data-sem'), 10);
    cell.style.display = (sn <= sCount) ? 'block' : 'none';
  });
}
function syncDurationTextFromInputs() {
  var sCount = parseInt(document.getElementById('progTotalSemesters').value, 10) || 8;
  var rawYr = parseFloat(document.getElementById('progDurationYears').value);
  if (!isFinite(rawYr) || rawYr <= 0) rawYr = sCount * 0.5;
  var yrs = (Math.abs(rawYr - Math.round(rawYr)) < 0.05) ? String(Math.round(rawYr)) : rawYr.toFixed(1);
  var yrWord = (parseFloat(yrs) === 1) ? 'Year' : 'Years';
  var semWord = (sCount === 1) ? 'Semester' : 'Semesters';
  document.getElementById('progDurationText').value = yrs + ' ' + yrWord + ' (' + sCount + ' ' + semWord + ')';
}
<?php if (is_admin_manager_role()): ?>
var progCsrfToken = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var deleteProgramApiUrl = <?= json_encode(APP_URL . '/actions/admin-delete-program.php', JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function showProgramsAlert(type, message) {
  var box = document.getElementById('programsAjaxAlert');
  if (!box) return;
  box.innerHTML = '';
  var alertDiv = document.createElement('div');
  alertDiv.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
  var icon = document.createElement('i');
  icon.className = 'fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle');
  var msgDiv = document.createElement('div');
  msgDiv.textContent = message;
  alertDiv.appendChild(icon);
  alertDiv.appendChild(msgDiv);
  box.appendChild(alertDiv);
  box.style.display = 'block';
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function deleteProgramRecord(event, programId, formEl) {
  if (event && event.preventDefault) event.preventDefault();
  var targetId = parseInt(programId, 10);
  if (!targetId || targetId <= 0) {
    showProgramsAlert('error', 'Invalid program database ID.');
    return false;
  }
  if (!window.confirm('Are you sure you want to delete this program?')) {
    return false;
  }

  var btnEl = formEl ? formEl.querySelector('button[type="submit"]') : null;
  var originalHtml = btnEl ? btnEl.innerHTML : '';
  if (btnEl) {
    btnEl.disabled = true;
    btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  }

  fetch(deleteProgramApiUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: new URLSearchParams({
      csrf_token: progCsrfToken,
      id: String(targetId)
    }).toString()
  })
  .then(function(res) {
    return res.json().catch(function() {
      return { success: false, message: 'Unexpected server response (HTTP ' + res.status + ').' };
    }).then(function(data) {
      return { ok: res.ok, status: res.status, data: data };
    });
  })
  .then(function(result) {
    var data = result.data || {};
    if (!result.ok || !data.success) {
      if (btnEl) {
        btnEl.disabled = false;
        btnEl.innerHTML = originalHtml;
      }
      showProgramsAlert('error', data.message || 'Failed to delete degree program.');
      return false;
    }

    var tbody = document.getElementById('programsTableBody');
    var rowEl = tbody ? tbody.querySelector('tr[data-program-row-id="' + targetId + '"]') : null;
    if (rowEl && rowEl.parentNode) {
      rowEl.parentNode.removeChild(rowEl);
    }
    if (tbody && tbody.querySelectorAll('tr[data-program-row-id]').length === 0) {
      var emptyTr = document.createElement('tr');
      emptyTr.id = 'programsEmptyRow';
      emptyTr.innerHTML = '<td colspan="6" style="text-align: center; color: #64748b;">No degree programs found.</td>';
      tbody.appendChild(emptyTr);
    }
    showProgramsAlert('success', data.message || 'Degree program deleted successfully.');
    return false;
  })
  .catch(function() {
    if (btnEl) {
      btnEl.disabled = false;
      btnEl.innerHTML = originalHtml;
    }
    showProgramsAlert('error', 'Network error while attempting to delete degree program.');
  });

  return false;
}
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

