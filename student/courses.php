<?php
/**
 * Student Portal - Enrolled Courses & Class Routine
 */
$portalTitle = 'Enrolled Courses & Class Routine';
require_once __DIR__ . '/includes/student-header.php';

$db = getDb();
$studentId = $student['id'];

// Retrieve all courses
$stmt = $db->prepare("
  SELECT * FROM student_courses 
  WHERE student_id = ? 
  ORDER BY course_code ASC
");
$stmt->execute([$studentId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalCredits = array_sum(array_column($courses, 'credit'));
$courseSessionLabel = !empty($student['academic_session'])
    ? $student['academic_session']
    : (!empty($student['batch']) ? $student['batch'] : get_current_academic_session_name('Current Session'));
?>

<div class="sp-main-container">
  
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0;">
    <div>
      <h2 style="margin:0 0 0.3rem; font-size:1.45rem; font-weight:700; color:#0f172a;">
        <i class="fas fa-book-reader" style="color:var(--sp-primary);"></i> Enrolled Courses &amp; Class Schedule
      </h2>
      <p style="margin:0; font-size:0.88rem; color:#64748b;">
        Academic Session: <strong><?= e($courseSessionLabel) ?></strong> • Current Semester: <strong><?= e($student['current_semester'] ?? '—') ?></strong> • Total Enrolled Credits: <strong><?= number_format((float)$totalCredits, 1) ?> Credits</strong>
      </p>
    </div>

    <div>
      <button type="button" onclick="window.print()" class="sp-btn-back-website" style="cursor:pointer;">
        <i class="fas fa-print"></i> Print Class Routine
      </button>
    </div>
  </div>

  <!-- Courses Cards Grid -->
  <?php if (empty($courses)): ?>
    <div class="sp-card" style="margin-bottom:2rem;">
      <div class="sp-card-body" style="text-align:center; padding:2.5rem 1.25rem; color:#64748b;">
        <i class="fas fa-book-open" style="font-size:2rem; color:#94a3b8; margin-bottom:0.65rem; display:block;"></i>
        <strong style="display:block; color:#0f172a; margin-bottom:0.25rem;">No Enrolled Courses Found</strong>
        Your course registration for <?= e($student['current_semester'] ?? 'the active semester') ?> will appear here once assigned by Administration.
      </div>
    </div>
  <?php else: ?>
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 350px), 1fr)); gap:1.25rem; margin-bottom:2.5rem;">
    <?php foreach ($courses as $c): ?>
      <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.4rem; box-shadow:0 2px 6px rgba(0,0,0,0.03); display:flex; flex-direction:column; position:relative; min-width:0; overflow-wrap:anywhere;">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem; gap:0.5rem; flex-wrap:wrap;">
          <span style="font-family:monospace; font-size:0.95rem; font-weight:800; color:var(--sp-primary); background:#eff6ff; padding:2px 8px; border-radius:4px; border:1px solid #bfdbfe;">
            <?= e($c['course_code']) ?>
          </span>
          <div style="display:flex; align-items:center; gap:0.4rem;">
            <?php if (!empty($c['semester'])): ?>
              <span style="font-size:0.74rem; font-weight:700; color:#475569; background:#f1f5f9; padding:2px 8px; border-radius:999px; border:1px solid #cbd5e1;"><?= e($c['semester']) ?></span>
            <?php endif; ?>
            <span class="badge-enrolled"><?= number_format((float)$c['credit'], 1) ?> Credits</span>
          </div>
        </div>

        <h3 style="margin:0 0 0.8rem; font-size:1.05rem; font-weight:700; color:#0f172a; line-height:1.35;">
          <?= e($c['course_name']) ?>
        </h3>

        <div style="font-size:0.84rem; color:#475569; line-height:1.6; margin-bottom:1rem; flex:1;">
          <div><i class="fas fa-user-tie" style="color:var(--sp-primary); width:18px;"></i> <strong>Instructor:</strong> <?= e($c['instructor_name'] ?: 'TBA') ?></div>
          <div><i class="fas fa-clock" style="color:var(--sp-primary); width:18px;"></i> <strong>Time:</strong> <?= e($c['class_schedule'] ?: 'TBA') ?></div>
          <div><i class="fas fa-map-marker-alt" style="color:var(--sp-primary); width:18px;"></i> <strong>Location:</strong> <?= e($c['room_no'] ?: 'TBA') ?></div>
        </div>

        <div style="padding-top:0.8rem; border-top:1px dashed #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
          <span style="font-size:0.82rem; color:#64748b;">
            Attendance: <strong style="color:<?= ($c['attendance_percent'] >= 75) ? '#16a34a' : '#dc2626' ?>;"><?= (int)$c['attendance_percent'] ?>%</strong>
          </span>
          <?php $syllabusUrl = !empty($student['program_slug']) ? (APP_URL . '/program-detail.php?slug=' . urlencode($student['program_slug'])) : (APP_URL . '/programs.php'); ?>
          <a href="<?= e($syllabusUrl) ?>" target="_blank" style="font-size:0.78rem; font-weight:700; color:var(--sp-primary); text-decoration:none;">
            Course Syllabus &rarr;
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Dynamic Class Routine / Schedule Table -->
  <div class="sp-card">
    <div class="sp-card-header">
      <h3><i class="fas fa-calendar-alt" style="color:var(--sp-primary);"></i> Registered Course Class Schedule (<?= e($courseSessionLabel) ?>)</h3>
    </div>
    <div class="sp-card-body" style="padding:0;">
      <div style="overflow-x:auto; max-width:100%; -webkit-overflow-scrolling:touch;">
        <table class="sp-table">
          <thead>
            <tr>
              <th>Course Code</th>
              <th>Course Title</th>
              <th>Credits</th>
              <th>Instructor</th>
              <th>Scheduled Days &amp; Time</th>
              <th>Room / Lab</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($courses)): ?>
              <tr>
                <td colspan="6" style="text-align:center; padding:1.75rem; color:#64748b;">
                  No scheduled classes recorded for your enrolled semester yet.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($courses as $c): ?>
                <tr>
                  <td><strong style="font-family:monospace; color:var(--sp-primary);"><?= e($c['course_code']) ?></strong></td>
                  <td><?= e($c['course_name']) ?></td>
                  <td><?= number_format((float)$c['credit'], 1) ?></td>
                  <td><?= e($c['instructor_name'] ?: 'TBA') ?></td>
                  <td><?= e($c['class_schedule'] ?: 'TBA') ?></td>
                  <td><?= e($c['room_no'] ?: 'TBA') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/student-footer.php'; ?>

