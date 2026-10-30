<?php
/**
 * Student Portal - Main Dashboard Overview
 */
$portalTitle = 'Dashboard Overview';
require_once __DIR__ . '/includes/student-header.php';

$db = getDb();
$studentId = $student['id'];

// Retrieve enrolled courses for current semester
$enrolledCourses = $db->prepare("
  SELECT * FROM student_courses 
  WHERE student_id = ? AND status = 'Enrolled' 
  ORDER BY course_code ASC
");
$enrolledCourses->execute([$studentId]);
$courses = $enrolledCourses->fetchAll(PDO::FETCH_ASSOC);

// Calculate average attendance
$avgAttendance = 0;
if (!empty($courses)) {
    $totalAtt = array_sum(array_column($courses, 'attendance_percent'));
    $avgAttendance = round($totalAtt / count($courses));
}

// Retrieve recent results
$recentResultsStmt = $db->prepare("
  SELECT * FROM student_results 
  WHERE student_id = ? 
  ORDER BY id DESC LIMIT 5
");
$recentResultsStmt->execute([$studentId]);
$recentResults = $recentResultsStmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve fee status
$feeStmt = $db->prepare("
  SELECT * FROM student_fees 
  WHERE student_id = ? 
  ORDER BY id DESC LIMIT 3
");
$feeStmt->execute([$studentId]);
$fees = $feeStmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve pinned portal notices
$notices = $db->query("
  SELECT * FROM student_portal_notices 
  ORDER BY is_pinned DESC, published_at DESC LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="sp-main-container">

  <!-- Profile Banner -->
  <div class="sp-profile-banner">
    <div class="sp-profile-meta">
      <div class="sp-big-avatar" style="overflow:hidden; border:3px solid rgba(255,255,255,0.8); position:relative;">
        <img src="<?= get_student_avatar_url($student) ?>" alt="<?= e($student['full_name']) ?>" style="width:100%; height:100%; object-fit:cover;">
      </div>
      <div class="sp-profile-info">
        <h2><?= e($student['full_name']) ?></h2>
        <p><i class="fas fa-university"></i> <?= e($student['program_name'] ?? 'Degree Program') ?> (<?= e($student['department_name'] ?? '') ?>)</p>
        <div class="sp-profile-tags">
          <span class="sp-tag"><i class="fas fa-id-badge"></i> Student ID: <strong><?= e($student['student_id']) ?></strong></span>
          <span class="sp-tag"><i class="fas fa-layer-group"></i> Batch: <?= e($student['batch']) ?></span>
          <span class="sp-tag"><i class="fas fa-calendar-alt"></i> Current: <?= e($student['current_year']) ?> (<?= e($student['current_semester']) ?>)</span>
          <span class="sp-tag" style="background:#22c55e;"><i class="fas fa-check-circle"></i> Status: <?= e($student['status']) ?></span>
          <a href="<?= APP_URL ?>/student/profile.php#uploadPhoto" class="sp-tag" style="background:#f59e0b; color:#fff; text-decoration:none;"><i class="fas fa-camera"></i> Change Photo</a>
        </div>
      </div>
    </div>

    <div>
      <a href="<?= APP_URL ?>/student/id-card.php" style="background:#ffffff; color:var(--sp-primary); text-decoration:none; padding:0.6rem 1.1rem; border-radius:6px; font-weight:700; font-size:0.88rem; display:inline-flex; align-items:center; gap:6px; box-shadow:0 4px 10px rgba(0,0,0,0.15);">
        <i class="fas fa-id-card"></i> View Digital ID Card
      </a>
    </div>
  </div>

  <!-- KPI Metrics Grid -->
  <div class="sp-kpi-grid">
    <!-- CGPA Card -->
    <div class="sp-kpi-card">
      <div class="sp-kpi-icon" style="background:#eff6ff; color:#1e73be;">
        <i class="fas fa-award"></i>
      </div>
      <div>
        <div class="sp-kpi-val"><?= number_format((float)$student['cgpa'], 2) ?></div>
        <div class="sp-kpi-label">Cumulative GPA (out of 4.00)</div>
      </div>
    </div>

    <!-- Credits Completed Card -->
    <div class="sp-kpi-card">
      <div class="sp-kpi-icon" style="background:#f0fdf4; color:#16a34a;">
        <i class="fas fa-check-double"></i>
      </div>
      <div>
        <div class="sp-kpi-val"><?= (int)$student['credits_completed'] ?> <small style="font-size:0.9rem; color:#64748b; font-weight:500;">/ <?= (int)$student['total_credits_required'] ?></small></div>
        <div class="sp-kpi-label">Credits Completed</div>
      </div>
    </div>

    <!-- Enrolled Courses Card -->
    <div class="sp-kpi-card">
      <div class="sp-kpi-icon" style="background:#fef3c7; color:#d97706;">
        <i class="fas fa-book-open"></i>
      </div>
      <div>
        <div class="sp-kpi-val"><?= count($courses) ?> Courses</div>
        <div class="sp-kpi-label">Enrolled This Semester</div>
      </div>
    </div>

    <!-- Attendance Card -->
    <div class="sp-kpi-card">
      <div class="sp-kpi-icon" style="background:#fdf2f8; color:#db2777;">
        <i class="fas fa-user-check"></i>
      </div>
      <div>
        <div class="sp-kpi-val"><?= $avgAttendance ?>%</div>
        <div class="sp-kpi-label">Average Class Attendance</div>
      </div>
    </div>
  </div>

  <!-- 2-Column Content Grid: Enrolled Courses & Routine + Recent Results & Notices -->
  <div style="display:grid; grid-template-columns: 2fr 1.2fr; gap:1.75rem; align-items:start;">
    
    <!-- Left Column: Current Courses & Class Routine -->
    <div>
      <div class="sp-card">
        <div class="sp-card-header">
          <h3><i class="fas fa-chalkboard-teacher" style="color:var(--sp-primary);"></i> Current Semester Enrolled Courses (<?= e($student['current_semester']) ?>)</h3>
          <a href="<?= APP_URL ?>/student/courses.php" style="font-size:0.82rem; font-weight:700; color:var(--sp-primary); text-decoration:none;">View Full Routine &rarr;</a>
        </div>
        <div class="sp-card-body" style="padding:0;">
          <div style="overflow-x:auto;">
            <table class="sp-table">
              <thead>
                <tr>
                  <th>Course</th>
                  <th>Credits</th>
                  <th>Instructor</th>
                  <th>Schedule &amp; Room</th>
                  <th>Attendance</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($courses)): ?>
                  <tr><td colspan="5" style="text-align:center; padding:1.5rem; color:#64748b;">No enrolled courses found for this semester.</td></tr>
                <?php else: ?>
                  <?php foreach ($courses as $c): ?>
                    <tr>
                      <td>
                        <strong style="color:#0f172a; font-family:monospace;"><?= e($c['course_code']) ?></strong><br>
                        <span style="font-size:0.82rem; color:#475569;"><?= e($c['course_name']) ?></span>
                      </td>
                      <td><span class="badge-enrolled"><?= number_format((float)$c['credit'], 1) ?> Cr</span></td>
                      <td style="font-size:0.82rem; color:#334155;"><?= e($c['instructor_name']) ?></td>
                      <td style="font-size:0.8rem; color:#64748b;">
                        <i class="fas fa-clock"></i> <?= e($c['class_schedule']) ?><br>
                        <i class="fas fa-map-marker-alt"></i> <?= e($c['room_no']) ?>
                      </td>
                      <td>
                        <strong style="color:<?= ($c['attendance_percent'] >= 75) ? '#16a34a' : '#dc2626' ?>;"><?= (int)$c['attendance_percent'] ?>%</strong>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Financial Status Summary -->
      <div class="sp-card">
        <div class="sp-card-header">
          <h3><i class="fas fa-receipt" style="color:#16a34a;"></i> Tuition &amp; Semester Fee History</h3>
          <a href="<?= APP_URL ?>/student/fees.php" style="font-size:0.82rem; font-weight:700; color:var(--sp-primary); text-decoration:none;">All Payments &rarr;</a>
        </div>
        <div class="sp-card-body" style="padding:0;">
          <div style="overflow-x:auto;">
            <table class="sp-table">
              <thead>
                <tr>
                  <th>Fee Description</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Transaction ID</th>
                  <th>Paid Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($fees as $f): ?>
                  <tr>
                    <td><strong><?= e($f['fee_title']) ?></strong></td>
                    <td>৳<?= number_format((float)$f['amount'], 2) ?></td>
                    <td><span class="badge-paid"><i class="fas fa-check-circle"></i> <?= e($f['status']) ?></span></td>
                    <td style="font-family:monospace; font-size:0.8rem; color:#64748b;"><?= e($f['transaction_id'] ?? 'N/A') ?></td>
                    <td style="font-size:0.82rem; color:#64748b;"><?= date('M d, Y', strtotime($f['paid_at'] ?? $f['created_at'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: Recent Grades + Academic Notices -->
    <div>
      <!-- Academic Notices -->
      <div class="sp-card">
        <div class="sp-card-header">
          <h3><i class="fas fa-bullhorn" style="color:#d97706;"></i> Portal Notices</h3>
        </div>
        <div class="sp-card-body" style="padding: 1rem 1.25rem;">
          <?php foreach ($notices as $n): ?>
            <div style="padding-bottom: 0.85rem; margin-bottom: 0.85rem; border-bottom: 1px dashed #e2e8f0;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                <span style="font-size:0.72rem; font-weight:700; padding:1px 6px; border-radius:4px; background:#eff6ff; color:#1e73be;"><?= e($n['category']) ?></span>
                <span style="font-size:0.75rem; color:#94a3b8;"><i class="fas fa-clock"></i> <?= date('M d', strtotime($n['published_at'])) ?></span>
              </div>
              <h4 style="margin:0 0 0.3rem; font-size:0.88rem; font-weight:700; color:#0f172a; line-height:1.35;"><?= e($n['title']) ?></h4>
              <p style="margin:0; font-size:0.78rem; color:#64748b; line-height:1.45;"><?= e(truncate_words($n['content'], 18)) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Quick Academic Links -->
      <div class="sp-card">
        <div class="sp-card-header">
          <h3><i class="fas fa-link" style="color:var(--sp-primary);"></i> Quick Downloads &amp; Services</h3>
        </div>
        <div class="sp-card-body" style="display:flex; flex-direction:column; gap:0.6rem;">
          <a href="<?= APP_URL ?>/student/id-card.php" style="display:flex; align-items:center; gap:0.65rem; padding:0.65rem 0.9rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; color:#1e293b; text-decoration:none; font-size:0.85rem; font-weight:600; transition:all 0.15s ease;">
            <i class="fas fa-id-badge" style="color:var(--sp-primary); font-size:1.1rem;"></i>
            <span>Download Digital Student ID Card</span>
          </a>

          <a href="<?= APP_URL ?>/student/results.php" style="display:flex; align-items:center; gap:0.65rem; padding:0.65rem 0.9rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; color:#1e293b; text-decoration:none; font-size:0.85rem; font-weight:600; transition:all 0.15s ease;">
            <i class="fas fa-file-invoice" style="color:#16a34a; font-size:1.1rem;"></i>
            <span>Print Official Academic Transcript</span>
          </a>

          <a href="<?= APP_URL ?>/student/fees.php" style="display:flex; align-items:center; gap:0.65rem; padding:0.65rem 0.9rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; color:#1e293b; text-decoration:none; font-size:0.85rem; font-weight:600; transition:all 0.15s ease;">
            <i class="fas fa-receipt" style="color:#d97706; font-size:1.1rem;"></i>
            <span>Download Semester Fee Receipts</span>
          </a>

          <a href="<?= APP_URL ?>/programs.php" target="_blank" style="display:flex; align-items:center; gap:0.65rem; padding:0.65rem 0.9rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; color:#1e293b; text-decoration:none; font-size:0.85rem; font-weight:600; transition:all 0.15s ease;">
            <i class="fas fa-book" style="color:#6366f1; font-size:1.1rem;"></i>
            <span>Full 4-Year University Curriculum &rarr;</span>
          </a>
        </div>
      </div>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/includes/student-footer.php'; ?>

