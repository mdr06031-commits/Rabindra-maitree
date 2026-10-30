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
?>

<div class="sp-main-container">
  
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0;">
    <div>
      <h2 style="margin:0 0 0.3rem; font-size:1.45rem; font-weight:700; color:#0f172a;">
        <i class="fas fa-book-reader" style="color:var(--sp-primary);"></i> Enrolled Courses &amp; Class Schedule
      </h2>
      <p style="margin:0; font-size:0.88rem; color:#64748b;">
        Academic Session: <strong><?= e($student['current_semester']) ?></strong> • Total Enrolled Credits: <strong><?= number_format((float)$totalCredits, 1) ?> Credits</strong>
      </p>
    </div>

    <div>
      <button type="button" onclick="window.print()" class="sp-btn-back-website" style="cursor:pointer;">
        <i class="fas fa-print"></i> Print Class Routine
      </button>
    </div>
  </div>

  <!-- Courses Cards Grid -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(350px, 1fr)); gap:1.25rem; margin-bottom:2.5rem;">
    <?php foreach ($courses as $c): ?>
      <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.4rem; box-shadow:0 2px 6px rgba(0,0,0,0.03); display:flex; flex-direction:column; position:relative;">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem;">
          <span style="font-family:monospace; font-size:0.95rem; font-weight:800; color:var(--sp-primary); background:#eff6ff; padding:2px 8px; border-radius:4px; border:1px solid #bfdbfe;">
            <?= e($c['course_code']) ?>
          </span>
          <span class="badge-enrolled"><?= number_format((float)$c['credit'], 1) ?> Credits</span>
        </div>

        <h3 style="margin:0 0 0.8rem; font-size:1.05rem; font-weight:700; color:#0f172a; line-height:1.35;">
          <?= e($c['course_name']) ?>
        </h3>

        <div style="font-size:0.84rem; color:#475569; line-height:1.6; margin-bottom:1rem; flex:1;">
          <div><i class="fas fa-user-tie" style="color:var(--sp-primary); width:18px;"></i> <strong>Instructor:</strong> <?= e($c['instructor_name']) ?></div>
          <div><i class="fas fa-clock" style="color:var(--sp-primary); width:18px;"></i> <strong>Time:</strong> <?= e($c['class_schedule']) ?></div>
          <div><i class="fas fa-map-marker-alt" style="color:var(--sp-primary); width:18px;"></i> <strong>Location:</strong> <?= e($c['room_no']) ?></div>
        </div>

        <div style="padding-top:0.8rem; border-top:1px dashed #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
          <span style="font-size:0.82rem; color:#64748b;">
            Attendance: <strong style="color:<?= ($c['attendance_percent'] >= 75) ? '#16a34a' : '#dc2626' ?>;"><?= (int)$c['attendance_percent'] ?>%</strong>
          </span>
          <a href="<?= APP_URL ?>/program-detail.php?slug=<?= e($student['program_slug'] ?? 'bsc-in-cse') ?>" target="_blank" style="font-size:0.78rem; font-weight:700; color:var(--sp-primary); text-decoration:none;">
            Course Syllabus &rarr;
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Weekly Timetable / Routine Table -->
  <div class="sp-card">
    <div class="sp-card-header">
      <h3><i class="fas fa-calendar-alt" style="color:var(--sp-primary);"></i> Weekly Class Timetable (Fall 2026)</h3>
    </div>
    <div class="sp-card-body" style="padding:0;">
      <div style="overflow-x:auto;">
        <table class="sp-table">
          <thead>
            <tr>
              <th style="width:140px;">Day</th>
              <th>Period 1 (09:30 AM - 11:00 AM)</th>
              <th>Period 2 (11:15 AM - 12:45 PM)</th>
              <th>Period 3 (02:00 PM - 03:30 PM)</th>
              <th>Period 4 (03:45 PM - 05:00 PM)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Sunday</strong></td>
              <td><span style="font-weight:700; color:var(--sp-primary);">CSE 201</span><br><small>Lab 203</small></td>
              <td>—</td>
              <td colspan="2" style="background:#eff6ff;"><span style="font-weight:700; color:var(--sp-primary);">CSE 202 (Lab Session)</span><br><small>Computer Lab 1 (02:00 PM - 05:00 PM)</small></td>
            </tr>
            <tr>
              <td><strong>Monday</strong></td>
              <td><span style="font-weight:700; color:var(--sp-primary);">CSE 204</span><br><small>Hardware Lab 102</small></td>
              <td><span style="font-weight:700; color:var(--sp-primary);">CSE 203</span><br><small>Room 304</small></td>
              <td>—</td>
              <td>—</td>
            </tr>
            <tr>
              <td><strong>Tuesday</strong></td>
              <td><span style="font-weight:700; color:var(--sp-primary);">CSE 201</span><br><small>Lab 203</small></td>
              <td>—</td>
              <td>Library &amp; Study Group</td>
              <td>—</td>
            </tr>
            <tr>
              <td><strong>Wednesday</strong></td>
              <td><span style="font-weight:700; color:var(--sp-primary);">CSE 204</span><br><small>Hardware Lab 102</small></td>
              <td><span style="font-weight:700; color:var(--sp-primary);">CSE 203</span><br><small>Room 304</small></td>
              <td>—</td>
              <td>—</td>
            </tr>
            <tr>
              <td><strong>Thursday</strong></td>
              <td>—</td>
              <td><span style="font-weight:700; color:var(--sp-primary);">MAT 201</span><br><small>Room 205 (10:00 AM - 01:00 PM)</small></td>
              <td>—</td>
              <td>Faculty Counseling Hour</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/student-footer.php'; ?>

