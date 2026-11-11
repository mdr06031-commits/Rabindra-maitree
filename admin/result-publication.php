<?php
/**
 * Admin Panel: Result Publication & 3-State Workflow Hub
 * Rabindra Maitree University (RMU)
 * Draft, Under Review & Published Semester Control
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Result Publication';
$db = getDb();
$currentAdmin = current_user();
$adminId = (int)($currentAdmin['id'] ?? ($_SESSION['user_id'] ?? 1));

// Handle POST actions (Publish Batch, Unpublish/Draft Batch, Set Under Review, Show/Hide Result Visibility)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } elseif (!is_admin_manager_role()) {
        log_audit('PUBLICATION_ACTION_DENIED', "Unauthorized role '" . current_admin_role() . "' attempted action '" . ($_POST['action'] ?? '') . "' in result-publication.php");
        flash_set('error', 'Access denied: Only Administrator accounts may publish, unpublish, or modify result visibility.');
    } else {
        $action = $_POST['action'] ?? '';
        $semester = trim($_POST['semester'] ?? '');
        $studentId = (int)($_POST['student_id'] ?? 0);

        if ($action === 'publish_student_semester' && $studentId > 0 && !empty($semester)) {
            $res = set_student_results_publication($studentId, true, $semester, $adminId);
            if ($res['success']) {
                flash_set('success', "Published {$res['affected']} course result(s) for '{$semester}' to the Student Portal.");
            } else {
                flash_set('error', $res['error'] ?? 'Failed to publish results.');
            }
        } elseif ($action === 'set_under_review' && $studentId > 0 && !empty($semester)) {
            $upd = $db->prepare("
                UPDATE student_results 
                SET publish_status = 'Under Review', is_published = 0 
                WHERE student_id = ? AND semester = ?
            ");
            $upd->execute([$studentId, $semester]);
            recalculate_student_cgpa($studentId);

            log_audit('RESULT_UNDER_REVIEW', "Moved {$semester} results to Under Review for Student #{$studentId}", $adminId);
            flash_set('info', "Results for '{$semester}' marked as Under Review (hidden from student).");
        } elseif ($action === 'revert_to_draft' && $studentId > 0 && !empty($semester)) {
            $res = set_student_results_publication($studentId, false, $semester, $adminId);
            if ($res['success']) {
                flash_set('warning', "Unpublished results for '{$semester}' (reverted to Draft state).");
            } else {
                flash_set('error', $res['error'] ?? 'Failed to unpublish results.');
            }
        } elseif ($action === 'show_results' && $studentId > 0) {
            $res = set_student_result_visibility($studentId, true, $adminId);
            flash_set($res['success'] ? 'success' : 'error', $res['success'] ? "Results are now VISIBLE (Show Result) for {$res['full_name']}." : ($res['error'] ?? 'Failed.'));
        } elseif ($action === 'hide_results' && $studentId > 0) {
            $res = set_student_result_visibility($studentId, false, $adminId, trim($_POST['reason'] ?? ''));
            flash_set($res['success'] ? 'success' : 'error', $res['success'] ? "Results are now HIDDEN (Hide Result) from student view for {$res['full_name']}. Underlying academic data preserved." : ($res['error'] ?? 'Failed.'));
        }
    }
    $redirectSearch = trim($_POST['current_search'] ?? ($_GET['search'] ?? ''));
    $redirectState = trim($_POST['current_state'] ?? ($_GET['state'] ?? 'all'));
    $qs = [];
    if ($redirectSearch !== '') $qs[] = 'search=' . urlencode($redirectSearch);
    if ($redirectState !== '' && $redirectState !== 'all') $qs[] = 'state=' . urlencode($redirectState);
    header("Location: " . APP_URL . "/admin/result-publication.php" . (!empty($qs) ? '?' . implode('&', $qs) : ''));
    exit;
}

// Search & Filter
$search = trim($_GET['search'] ?? '');
$stateFilter = trim($_GET['state'] ?? 'all');

// KPI metrics across all results
$totalPublished = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE publish_status = 'Published' OR is_published = 1")->fetchColumn();
$totalUnderReview = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE publish_status = 'Under Review'")->fetchColumn();
$totalDraft = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE (publish_status = 'Draft' OR publish_status IS NULL) AND is_published = 0")->fetchColumn();
$totalHiddenStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status != 'Deleted' AND COALESCE(results_visible, 1) = 0")->fetchColumn();

// Fetch distinct student semester groups for review & publication
$whereClauses = [];
$queryParams = [];
if ($search !== '') {
    $whereClauses[] = "(s.student_id LIKE ? OR s.roll_number LIKE ? OR s.application_number LIKE ? OR s.username LIKE ? OR s.full_name LIKE ? OR sr.semester LIKE ?)";
    $like = "%{$search}%";
    for ($i = 0; $i < 6; $i++) {
        $queryParams[] = $like;
    }
}
if ($stateFilter === 'published') {
    $whereClauses[] = "(sr.is_published = 1 OR sr.publish_status = 'Published')";
} elseif ($stateFilter === 'draft') {
    $whereClauses[] = "sr.is_published = 0 AND (sr.publish_status = 'Draft' OR sr.publish_status IS NULL)";
} elseif ($stateFilter === 'review') {
    $whereClauses[] = "sr.publish_status = 'Under Review'";
} elseif ($stateFilter === 'hidden') {
    $whereClauses[] = "COALESCE(s.results_visible, 1) = 0";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$query = "
    SELECT sr.student_id, sr.semester, sr.publish_status, sr.is_published,
           MAX(sr.published_at) as pub_time, COUNT(sr.id) as course_count,
           s.student_id as st_code, s.roll_number, s.application_number, s.username,
           s.full_name as st_name, s.batch as session, s.status as account_status,
           COALESCE(s.results_visible, 1) as results_visible,
           COALESCE(s.result_access_status, 'Active') as result_access_status,
           p.title_en as program_name, d.name_en as dept_name
    FROM student_results sr
    JOIN students s ON sr.student_id = s.id
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    {$whereSql}
    GROUP BY sr.student_id, sr.semester, sr.publish_status, sr.is_published,
             s.student_id, s.roll_number, s.application_number, s.username,
             s.full_name, s.batch, s.status, s.results_visible, s.result_access_status, p.title_en, d.name_en
    ORDER BY (s.status = 'Deleted') ASC, s.student_id ASC, sr.semester ASC
";
$stmtGroups = $db->prepare($query);
$stmtGroups->execute($queryParams);
$groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem;">
      <i class="fas fa-bullhorn" style="color:var(--rmu-primary);"></i> Result Publication
    </h2>
    <p style="color:#64748b; font-size:0.88rem; margin:0;">
      Publish / Unpublish Results &amp; Show / Hide Student Result Visibility Control (Controller of Examinations)
    </p>
  </div>

  <a href="<?= APP_URL ?>/admin/results-manager.php" class="btn-primary" style="padding:0.55rem 1.1rem; font-size:0.86rem; text-decoration:none;">
    <i class="fas fa-edit"></i> Open 8-Semester Result Editor &rarr;
  </a>
</div>

<!-- 3-State + Visibility KPI Summary Cards -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1.1rem; margin-bottom:1.5rem;">
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.15rem; display:flex; align-items:center; gap:0.9rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="width:48px; height:48px; border-radius:10px; background:#f0fdf4; color:#15803d; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
      <i class="fas fa-check-circle"></i>
    </div>
    <div>
      <div style="font-size:1.7rem; font-weight:800; color:#15803d; line-height:1;"><?= $totalPublished ?></div>
      <div style="font-size:0.78rem; color:#64748b; font-weight:600; margin-top:3px;">Published Results</div>
    </div>
  </div>

  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.15rem; display:flex; align-items:center; gap:0.9rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="width:48px; height:48px; border-radius:10px; background:#eff6ff; color:#1d4ed8; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
      <i class="fas fa-search"></i>
    </div>
    <div>
      <div style="font-size:1.7rem; font-weight:800; color:#1d4ed8; line-height:1;"><?= $totalUnderReview ?></div>
      <div style="font-size:0.78rem; color:#64748b; font-weight:600; margin-top:3px;">Under Review</div>
    </div>
  </div>

  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.15rem; display:flex; align-items:center; gap:0.9rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="width:48px; height:48px; border-radius:10px; background:#fffbeb; color:#b45309; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
      <i class="fas fa-pencil-ruler"></i>
    </div>
    <div>
      <div style="font-size:1.7rem; font-weight:800; color:#b45309; line-height:1;"><?= $totalDraft ?></div>
      <div style="font-size:0.78rem; color:#64748b; font-weight:600; margin-top:3px;">Draft / Pending Results</div>
    </div>
  </div>

  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.15rem; display:flex; align-items:center; gap:0.9rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="width:48px; height:48px; border-radius:10px; background:#fef2f2; color:#b91c1c; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
      <i class="fas fa-eye-slash"></i>
    </div>
    <div>
      <div style="font-size:1.7rem; font-weight:800; color:#b91c1c; line-height:1;"><?= $totalHiddenStudents ?></div>
      <div style="font-size:0.78rem; color:#64748b; font-weight:600; margin-top:3px;">Students w/ Result Hidden</div>
    </div>
  </div>
</div>

<!-- Search & Status Filter Bar -->
<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1rem 1.25rem; margin-bottom:1.5rem; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
  <form method="GET" action="" style="display:flex; gap:0.85rem; flex-wrap:wrap; align-items:center;">
    <div style="flex:2; min-width:230px;">
      <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by Student ID, Roll No, App No, Name, Semester..." class="form-control">
    </div>
    <div style="min-width:170px;">
      <select name="state" class="form-select">
        <option value="all" <?= ($stateFilter === 'all') ? 'selected' : '' ?>>All Result States</option>
        <option value="published" <?= ($stateFilter === 'published') ? 'selected' : '' ?>>Result Published</option>
        <option value="draft" <?= ($stateFilter === 'draft') ? 'selected' : '' ?>>Draft / Pending</option>
        <option value="review" <?= ($stateFilter === 'review') ? 'selected' : '' ?>>Under Review</option>
        <option value="hidden" <?= ($stateFilter === 'hidden') ? 'selected' : '' ?>>Result Hidden (Portal Off)</option>
      </select>
    </div>
    <button type="submit" class="btn-primary" style="padding:0.6rem 1.25rem;"><i class="fas fa-search"></i> Search</button>
    <?php if ($search !== '' || $stateFilter !== 'all'): ?>
      <a href="<?= APP_URL ?>/admin/result-publication.php" style="color:#ef4444; font-size:0.85rem; font-weight:700;">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Publication Queue Table -->
<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05);">
  <div style="padding:1rem 1.25rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
    <strong style="font-size:0.95rem; color:#0f2c59;">
      <i class="fas fa-list-check" style="color:var(--rmu-primary);"></i> Semester Publication Queue &bull; Student Cohorts (<?= count($groups) ?>)
    </strong>
    <span style="font-size:0.8rem; color:#64748b;">Hiding a result keeps all database marks intact without deleting academic records</span>
  </div>

  <div class="table-responsive">
    <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.88rem;">
      <thead>
        <tr>
          <th>Student Information</th>
          <th>Semester Term</th>
          <th>Courses</th>
          <th>Semester &amp; Account Status</th>
          <th>Publication Timestamp</th>
          <th style="text-align:right;">Publication &amp; Visibility Controls</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($groups)): ?>
          <tr><td colspan="6" style="text-align:center; padding:2.5rem; color:#64748b;">No matching semester result groups found.</td></tr>
        <?php else: ?>
        <?php foreach ($groups as $g): ?>
          <?php
            $isPub = ($g['is_published'] == 1 || $g['publish_status'] === 'Published');
            $statusLabel = $isPub ? 'Published' : (($g['publish_status'] === 'Under Review') ? 'Under Review' : 'Draft');
            $statusBg = $isPub ? '#dcfce7; color:#15803d;' : (($statusLabel === 'Under Review') ? '#eff6ff; color:#1d4ed8;' : '#fef3c7; color:#b45309;');
            $isVis = ((int)$g['results_visible'] === 1);
            $isSuspendedRes = (($g['result_access_status'] ?? 'Active') === 'Suspended');
            $accStat = $g['account_status'] ?? 'Active';
          ?>
          <tr>
            <td>
              <strong style="color:#0f172a;"><?= e($g['st_name']) ?></strong>
              <div style="font-family:monospace; color:var(--rmu-primary); font-size:0.82rem; font-weight:700;">
                <?= e($g['st_code']) ?>
                <?php if (!empty($g['roll_number'])): ?>
                  &bull; <span style="color:#475569;">Roll: <?= e($g['roll_number']) ?></span>
                <?php endif; ?>
              </div>
              <small style="color:#64748b;"><?= e($g['program_name'] ?? 'Degree Program') ?></small>
            </td>
            <td>
              <strong style="color:#1e3a8a;"><?= e($g['semester']) ?></strong>
              <div style="font-size:0.75rem; color:#64748b;">Session: <?= e($g['session']) ?></div>
            </td>
            <td>
              <span style="background:#f1f5f9; padding:2px 8px; border-radius:12px; font-weight:700; font-size:0.8rem;">
                <?= (int)$g['course_count'] ?> Subjects
              </span>
            </td>
            <td>
              <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                <span style="padding:3px 10px; border-radius:12px; font-weight:800; font-size:0.74rem; background:<?= $statusBg ?>">
                  <?= $statusLabel === 'Published' ? 'Result Published' : $statusLabel ?>
                </span>
                <?php if ($isSuspendedRes): ?>
                  <span style="padding:2px 8px; border-radius:12px; font-weight:800; font-size:0.71rem; background:#ffedd5; color:#9a3412;">
                    <i class="fas fa-user-lock"></i> Result Access Suspended
                  </span>
                <?php endif; ?>
                <?php if (!$isVis): ?>
                  <span style="padding:2px 8px; border-radius:12px; font-weight:800; font-size:0.71rem; background:#fee2e2; color:#991b1b;">
                    <i class="fas fa-eye-slash"></i> Result Hidden
                  </span>
                <?php endif; ?>
                <?php if ($accStat !== 'Active'): ?>
                  <span style="padding:2px 8px; border-radius:12px; font-weight:700; font-size:0.71rem; background:#fef3c7; color:#b45309;">
                    Account: <?= e($accStat) ?>
                  </span>
                <?php endif; ?>
              </div>
            </td>
            <td style="font-size:0.8rem; color:#64748b;">
              <?= $g['pub_time'] ? format_date($g['pub_time']) : '<span style="color:#94a3b8; font-style:italic;">Not Published</span>' ?>
            </td>
            <td style="text-align:right; white-space:nowrap;">
              <!-- Preview Transcript -->
              <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= (int)$g['student_id'] ?>&semester=<?= urlencode($g['semester']) ?>" target="_blank" style="background:#eff6ff; color:#0284c7; padding:4px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; text-decoration:none; margin-right:3px;">
                <i class="fas fa-eye"></i> Preview
              </a>

              <!-- Publish / Unpublish Controls -->
              <?php if (!$isPub): ?>
                <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Publish <?= e($g['semester']) ?> results for <?= e($g['st_name']) ?> to Student Portal?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="publish_student_semester">
                  <input type="hidden" name="student_id" value="<?= (int)$g['student_id'] ?>">
                  <input type="hidden" name="semester" value="<?= e($g['semester']) ?>">
                  <input type="hidden" name="current_search" value="<?= e($search) ?>">
                  <input type="hidden" name="current_state" value="<?= e($stateFilter) ?>">
                  <button type="submit" style="background:#16a34a; color:#fff; border:none; padding:4px 9px; border-radius:4px; font-size:0.78rem; font-weight:700; cursor:pointer;" title="Publish to Student Portal">
                    <i class="fas fa-check"></i> Publish
                  </button>
                </form>
              <?php else: ?>
                <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Unpublish <?= e($g['semester']) ?> and revert to Draft?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="revert_to_draft">
                  <input type="hidden" name="student_id" value="<?= (int)$g['student_id'] ?>">
                  <input type="hidden" name="semester" value="<?= e($g['semester']) ?>">
                  <input type="hidden" name="current_search" value="<?= e($search) ?>">
                  <input type="hidden" name="current_state" value="<?= e($stateFilter) ?>">
                  <button type="submit" style="background:#f59e0b; color:#fff; border:none; padding:4px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; cursor:pointer;" title="Unpublish / Revert to Draft">
                    <i class="fas fa-undo"></i> Unpublish
                  </button>
                </form>
              <?php endif; ?>

              <!-- Separate Visibility Toggle (Show / Hide Result) -->
              <form action="" method="POST" style="display:inline;" onsubmit="return confirm('<?= $isVis ? 'Hide results from student portal view without deleting marks?' : 'Show results in student portal view?' ?>');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $isVis ? 'hide_results' : 'show_results' ?>">
                <input type="hidden" name="student_id" value="<?= (int)$g['student_id'] ?>">
                <input type="hidden" name="current_search" value="<?= e($search) ?>">
                <input type="hidden" name="current_state" value="<?= e($stateFilter) ?>">
                <button type="submit" style="background:<?= $isVis ? '#fee2e2' : '#dcfce7' ?>; color:<?= $isVis ? '#991b1b' : '#166534' ?>; border:1px solid <?= $isVis ? '#fca5a5' : '#86efac' ?>; padding:4px 8px; border-radius:4px; font-size:0.76rem; font-weight:700; cursor:pointer; margin-left:3px;" title="<?= $isVis ? 'Hide Result from Student' : 'Show Result to Student' ?>">
                  <i class="fas <?= $isVis ? 'fa-eye-slash' : 'fa-eye' ?>"></i> <?= $isVis ? 'Hide' : 'Show' ?>
                </button>
              </form>

              <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= (int)$g['student_id'] ?>" style="background:#0f2c59; color:#ffffff; padding:4px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; text-decoration:none; margin-left:3px;" title="Open Student Action Profile">
                <i class="fas fa-user-cog"></i> Profile
              </a>
              <a href="<?= APP_URL ?>/admin/results-manager.php?student_id=<?= (int)$g['student_id'] ?>" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:4px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; text-decoration:none; margin-left:3px;" title="Open in 8-Semester Result Editor">
                <i class="fas fa-pen"></i> Edit
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

