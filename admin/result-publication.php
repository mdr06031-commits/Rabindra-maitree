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

// Handle POST actions (Publish Batch, Unpublish/Draft Batch, Set Under Review, Preview)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $semester = trim($_POST['semester'] ?? '');
        $studentId = (int)($_POST['student_id'] ?? 0);

        if ($action === 'publish_student_semester' && $studentId > 0 && !empty($semester)) {
            $upd = $db->prepare("
                UPDATE student_results 
                SET publish_status = 'Published', is_published = 1, published_at = NOW(), published_by = ? 
                WHERE student_id = ? AND semester = ?
            ");
            $upd->execute([$adminId, $studentId, $semester]);
            $count = $upd->rowCount();

            $metrics = recalculate_student_cgpa($studentId);

            // Send notification
            create_student_notification(
                $studentId,
                "Official Results Published: {$semester}",
                "Your examination results for {$semester} are now published. Current CGPA: {$metrics['cgpa']}.",
                'result_published'
            );

            log_audit('RESULT_PUBLISHED_STUDENT', "Published {$count} course results for {$semester} to Student #{$studentId}");
            flash_set('success', "Published {$count} course results for '{$semester}' to the Student Portal.");
        } elseif ($action === 'set_under_review' && $studentId > 0 && !empty($semester)) {
            $upd = $db->prepare("
                UPDATE student_results 
                SET publish_status = 'Under Review', is_published = 0 
                WHERE student_id = ? AND semester = ?
            ");
            $upd->execute([$studentId, $semester]);
            recalculate_student_cgpa($studentId);

            log_audit('RESULT_UNDER_REVIEW', "Moved {$semester} results to Under Review for Student #{$studentId}");
            flash_set('info', "Results for '{$semester}' marked as Under Review (hidden from student).");
        } elseif ($action === 'revert_to_draft' && $studentId > 0 && !empty($semester)) {
            $upd = $db->prepare("
                UPDATE student_results 
                SET publish_status = 'Draft', is_published = 0 
                WHERE student_id = ? AND semester = ?
            ");
            $upd->execute([$studentId, $semester]);
            recalculate_student_cgpa($studentId);

            log_audit('RESULT_REVERTED_DRAFT', "Reverted {$semester} results to Draft for Student #{$studentId}");
            flash_set('warning', "Results for '{$semester}' reverted to Draft state.");
        }
    }
    header("Location: " . APP_URL . "/admin/result-publication.php");
    exit;
}

// KPI metrics across all results
$totalPublished = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE publish_status = 'Published' OR is_published = 1")->fetchColumn();
$totalUnderReview = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE publish_status = 'Under Review'")->fetchColumn();
$totalDraft = (int)$db->query("SELECT COUNT(*) FROM student_results WHERE (publish_status = 'Draft' OR publish_status IS NULL) AND is_published = 0")->fetchColumn();

// Fetch distinct student semester groups for review & publication
$query = "
    SELECT sr.student_id, sr.semester, sr.publish_status, sr.is_published,
           MAX(sr.published_at) as pub_time, COUNT(sr.id) as course_count,
           s.student_id as st_code, s.full_name as st_name, s.batch as session,
           p.title_en as program_name, d.name_en as dept_name
    FROM student_results sr
    JOIN students s ON sr.student_id = s.id
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    GROUP BY sr.student_id, sr.semester, sr.publish_status, sr.is_published, s.student_id, s.full_name, s.batch, p.title_en, d.name_en
    ORDER BY s.student_id ASC, sr.semester ASC
";
$groups = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem;">
      <i class="fas fa-bullhorn" style="color:var(--rmu-primary);"></i> Result Publication
    </h2>
    <p style="color:#64748b; font-size:0.88rem; margin:0;">
      3-State Workflow Hub: Draft &bull; Under Review &bull; Published (Controller of Examinations)
    </p>
  </div>

  <a href="<?= APP_URL ?>/admin/results-manager.php" class="btn-primary" style="padding:0.55rem 1.1rem; font-size:0.86rem; text-decoration:none;">
    <i class="fas fa-edit"></i> Open 8-Semester Result Editor &rarr;
  </a>
</div>

<!-- 3-State KPI Summary Cards -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:2rem;">
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; display:flex; align-items:center; gap:1rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="width:50px; height:50px; border-radius:10px; background:#f0fdf4; color:#15803d; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
      <i class="fas fa-check-circle"></i>
    </div>
    <div>
      <div style="font-size:1.8rem; font-weight:800; color:#15803d; line-height:1;"><?= $totalPublished ?></div>
      <div style="font-size:0.8rem; color:#64748b; font-weight:600; margin-top:3px;">Published Course Results</div>
    </div>
  </div>

  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; display:flex; align-items:center; gap:1rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="width:50px; height:50px; border-radius:10px; background:#eff6ff; color:#1d4ed8; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
      <i class="fas fa-search"></i>
    </div>
    <div>
      <div style="font-size:1.8rem; font-weight:800; color:#1d4ed8; line-height:1;"><?= $totalUnderReview ?></div>
      <div style="font-size:0.8rem; color:#64748b; font-weight:600; margin-top:3px;">Under Review / Scrutiny</div>
    </div>
  </div>

  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; display:flex; align-items:center; gap:1rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="width:50px; height:50px; border-radius:10px; background:#fffbeb; color:#b45309; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
      <i class="fas fa-pencil-ruler"></i>
    </div>
    <div>
      <div style="font-size:1.8rem; font-weight:800; color:#b45309; line-height:1;"><?= $totalDraft ?></div>
      <div style="font-size:0.8rem; color:#64748b; font-weight:600; margin-top:3px;">Draft / Unverified Results</div>
    </div>
  </div>
</div>

<!-- Publication Queue Table -->
<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05);">
  <div style="padding:1rem 1.25rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
    <strong style="font-size:0.95rem; color:#0f2c59;">
      <i class="fas fa-list-check" style="color:var(--rmu-primary);"></i> Semester Publication Queue &bull; Student Cohorts (<?= count($groups) ?>)
    </strong>
    <span style="font-size:0.8rem; color:#64748b;">Unpublished results remain strictly private from student portal</span>
  </div>

  <div class="table-responsive">
    <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.88rem;">
      <thead>
        <tr>
          <th>Student Information</th>
          <th>Semester Term</th>
          <th>Courses</th>
          <th>Current State</th>
          <th>Publication Timestamp</th>
          <th style="text-align:right;">Publication Controls</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($groups as $g): ?>
          <?php
            $isPub = ($g['is_published'] == 1 || $g['publish_status'] === 'Published');
            $statusLabel = $isPub ? 'Published' : (($g['publish_status'] === 'Under Review') ? 'Under Review' : 'Draft');
            $statusBg = $isPub ? '#dcfce7; color:#15803d;' : (($statusLabel === 'Under Review') ? '#eff6ff; color:#1d4ed8;' : '#fef3c7; color:#b45309;');
          ?>
          <tr>
            <td>
              <strong style="color:#0f172a;"><?= e($g['st_name']) ?></strong>
              <div style="font-family:monospace; color:var(--rmu-primary); font-size:0.82rem; font-weight:700;"><?= e($g['st_code']) ?></div>
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
              <span style="padding:3px 10px; border-radius:12px; font-weight:800; font-size:0.75rem; background:<?= $statusBg ?>">
                <?= $statusLabel ?>
              </span>
            </td>
            <td style="font-size:0.8rem; color:#64748b;">
              <?= $g['pub_time'] ? format_date($g['pub_time']) : '<span style="color:#94a3b8; font-style:italic;">Not Published</span>' ?>
            </td>
            <td style="text-align:right; white-space:nowrap;">
              <!-- Preview Transcript -->
              <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= (int)$g['student_id'] ?>&semester=<?= urlencode($g['semester']) ?>" target="_blank" style="background:#eff6ff; color:#0284c7; padding:4px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; text-decoration:none; margin-right:4px;">
                <i class="fas fa-eye"></i> Preview
              </a>

              <!-- 3-State Action Buttons -->
              <?php if (!$isPub): ?>
                <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Publish <?= e($g['semester']) ?> results for <?= e($g['st_name']) ?> to Student Portal?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="publish_student_semester">
                  <input type="hidden" name="student_id" value="<?= (int)$g['student_id'] ?>">
                  <input type="hidden" name="semester" value="<?= e($g['semester']) ?>">
                  <button type="submit" style="background:#16a34a; color:#fff; border:none; padding:4px 9px; border-radius:4px; font-size:0.78rem; font-weight:700; cursor:pointer;" title="Publish to Student Portal">
                    <i class="fas fa-check"></i> Publish
                  </button>
                </form>
              <?php else: ?>
                <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Revert <?= e($g['semester']) ?> to Draft (hide from student)?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="revert_to_draft">
                  <input type="hidden" name="student_id" value="<?= (int)$g['student_id'] ?>">
                  <input type="hidden" name="semester" value="<?= e($g['semester']) ?>">
                  <button type="submit" style="background:#f59e0b; color:#fff; border:none; padding:4px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; cursor:pointer;" title="Revert to Draft">
                    <i class="fas fa-undo"></i> Draft
                  </button>
                </form>
              <?php endif; ?>

              <a href="<?= APP_URL ?>/admin/results-manager.php?student_id=<?= (int)$g['student_id'] ?>" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:4px 8px; border-radius:4px; font-size:0.78rem; font-weight:700; text-decoration:none; margin-left:4px;" title="Open in 8-Semester Result Editor">
                <i class="fas fa-pen"></i> Edit
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
