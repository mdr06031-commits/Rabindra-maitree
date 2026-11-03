<?php
/**
 * Student Portal - Main Dashboard Overview
 */
$portalTitle = 'Dashboard Overview';
require_once __DIR__ . '/includes/student-header.php';

$student = get_logged_in_student();

$db = getDb();
$studentId = (int)($student['id'] ?? ($_SESSION['student_pk_id'] ?? 0));

// Retrieve academic summary (only published results)
$academicSummary = get_student_academic_summary($studentId, true);
$hasPublishedResults = $academicSummary['has_results'];
$studentCgpa = $hasPublishedResults ? number_format($academicSummary['final_cgpa'], 2) : '0.00';
$studentEarnedCredits = $hasPublishedResults ? (int)$academicSummary['total_earned_credits'] : 0;

// Retrieve all distinct semesters student has enrolled courses in
$semStmt = $db->prepare("SELECT DISTINCT semester FROM student_courses WHERE student_id = ? ORDER BY semester ASC");
$semStmt->execute([$studentId]);
$availableSemesters = $semStmt->fetchAll(PDO::FETCH_COLUMN);

// Determine selected semester
$selectedSemester = trim($_GET['semester'] ?? '');
if ($selectedSemester === '') {
    if (!empty($student['current_semester']) && in_array($student['current_semester'], $availableSemesters)) {
        $selectedSemester = $student['current_semester'];
    } elseif (!empty($availableSemesters)) {
        $selectedSemester = end($availableSemesters);
    } else {
        $selectedSemester = $student['current_semester'] ?: '1st Semester';
    }
}

// Retrieve enrolled courses for selected semester
if ($selectedSemester === 'all') {
    $enrolledCourses = $db->prepare("
      SELECT * FROM student_courses 
      WHERE student_id = ?
      ORDER BY semester ASC, course_code ASC
    ");
    $enrolledCourses->execute([$studentId]);
} else {
    $enrolledCourses = $db->prepare("
      SELECT * FROM student_courses 
      WHERE student_id = ? AND (semester = ? OR semester LIKE ?)
      ORDER BY course_code ASC
    ");
    $enrolledCourses->execute([$studentId, $selectedSemester, "%{$selectedSemester}%"]);
}
$courses = $enrolledCourses->fetchAll(PDO::FETCH_ASSOC);

// Calculate average attendance
$avgAttendance = 0;
if (!empty($courses)) {
    $totalAtt = array_sum(array_column($courses, 'attendance_percent'));
    $avgAttendance = round($totalAtt / count($courses));
} else {
    $allAttStmt = $db->prepare("SELECT attendance_percent FROM student_courses WHERE student_id = ?");
    $allAttStmt->execute([$studentId]);
    $allAtts = $allAttStmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($allAtts)) {
        $avgAttendance = round(array_sum($allAtts) / count($allAtts));
    }
}

// Retrieve recent published results
$recentResultsStmt = $db->prepare("
  SELECT * FROM student_results 
  WHERE student_id = ? AND is_published = 1
  ORDER BY id DESC LIMIT 5
");
$recentResultsStmt->execute([$studentId]);
$recentResults = $recentResultsStmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve fee and payment history
$feeStmt = $db->prepare("
  SELECT * FROM student_fees 
  WHERE student_id = ? 
  ORDER BY id DESC LIMIT 5
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
        <?php if ($hasPublishedResults): ?>
          <div class="sp-kpi-val" style="color:#15803d;"><?= $studentCgpa ?></div>
          <div class="sp-kpi-label">Cumulative GPA (out of 4.00)</div>
        <?php else: ?>
          <div class="sp-kpi-val" style="color:#64748b; font-size:1.35rem;">N/A</div>
          <div class="sp-kpi-label">No published results yet</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Credits Completed Card -->
    <div class="sp-kpi-card">
      <div class="sp-kpi-icon" style="background:#f0fdf4; color:#16a34a;">
        <i class="fas fa-check-double"></i>
      </div>
      <div>
        <div class="sp-kpi-val"><?= $studentEarnedCredits ?> <small style="font-size:0.9rem; color:#64748b; font-weight:500;">/ <?= (int)$student['total_credits_required'] ?></small></div>
        <div class="sp-kpi-label"><?= $hasPublishedResults ? 'Earned Credits' : 'Credits Completed' ?></div>
      </div>
    </div>

    <!-- Enrolled Courses Card -->
    <div class="sp-kpi-card">
      <div class="sp-kpi-icon" style="background:#fef3c7; color:#d97706;">
        <i class="fas fa-book-open"></i>
      </div>
      <div>
        <div class="sp-kpi-val"><?= count($courses) ?> Course<?= count($courses) === 1 ? '' : 's' ?></div>
        <div class="sp-kpi-label">Enrolled (<?= e($selectedSemester === 'all' ? 'All' : $selectedSemester) ?>)</div>
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
      <!-- 1. Course Enrollment Section -->
      <div class="sp-card">
        <div class="sp-card-header" style="flex-wrap: wrap; gap: 0.75rem;">
          <div>
            <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
              <i class="fas fa-chalkboard-teacher" style="color:var(--sp-primary);"></i> Course Enrollment
            </h3>
            <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;">
              Showing courses for: <strong style="color: #0f172a;"><?= e($selectedSemester === 'all' ? 'All Enrolled Semesters' : $selectedSemester) ?></strong>
            </div>
          </div>

          <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <form method="GET" action="<?= APP_URL ?>/student/dashboard.php" style="margin: 0; display: inline-flex; align-items: center; gap: 6px;">
              <label for="semSelectFilter" style="font-size: 0.8rem; font-weight: 600; color: #475569; margin: 0; white-space: nowrap;">
                <i class="fas fa-layer-group" style="color: var(--sp-primary);"></i> Semester:
              </label>
              <select id="semSelectFilter" name="semester" onchange="this.form.submit()" style="padding: 4px 8px; font-size: 0.82rem; font-weight: 600; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; cursor: pointer;">
                <option value="all" <?= ($selectedSemester === 'all') ? 'selected' : '' ?>>All Semesters</option>
                <?php
                $standardSems = ['1st Semester', '2nd Semester', '3rd Semester', '4th Semester', '5th Semester', '6th Semester', '7th Semester', '8th Semester'];
                $dropdownSems = array_unique(array_merge($standardSems, $availableSemesters));
                foreach ($dropdownSems as $sem):
                ?>
                  <option value="<?= e($sem) ?>" <?= ($selectedSemester === $sem) ? 'selected' : '' ?>><?= e($sem) ?></option>
                <?php endforeach; ?>
              </select>
            </form>

            <a href="<?= APP_URL ?>/student/courses.php" style="font-size: 0.82rem; font-weight: 700; color: var(--sp-primary); text-decoration: none; padding: 4px 8px; border-radius: 4px; background: #eff6ff; border: 1px solid #bfdbfe; white-space: nowrap;">
              Full Routine &rarr;
            </a>
          </div>
        </div>

        <div class="sp-card-body" style="padding: 0;">
          <?php if (empty($courses)): ?>
            <!-- Clean Empty State for Courses -->
            <div style="text-align: center; padding: 3rem 1.5rem; background: #ffffff;">
              <div style="width: 62px; height: 62px; margin: 0 auto 1rem; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 1.6rem;">
                <i class="fas fa-book-reader"></i>
              </div>
              <h4 style="margin: 0 0 0.4rem; font-size: 1.05rem; font-weight: 700; color: #1e293b;">No Courses Currently Assigned</h4>
              <p style="margin: 0 0 1.15rem; font-size: 0.85rem; color: #64748b; max-width: 440px; margin-left: auto; margin-right: auto; line-height: 1.5;">
                No academic courses have been assigned for <strong><?= e($selectedSemester === 'all' ? 'any semester' : $selectedSemester) ?></strong>. Once administrators assign courses from the Admin Panel, they will appear here automatically.
              </p>
              <div style="display: inline-flex; align-items: center; gap: 6px; padding: 0.35rem 0.85rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 20px; font-size: 0.78rem; font-weight: 600; color: #1e73be;">
                <i class="fas fa-info-circle"></i> Need course registration? Please contact your Department Coordinator or Academic Advisor.
              </div>
            </div>
          <?php else: ?>
            <div style="overflow-x: auto;">
              <table class="sp-table">
                <thead>
                  <tr>
                    <th style="min-width: 105px;">Course Code</th>
                    <th style="min-width: 175px;">Course Name</th>
                    <th style="min-width: 70px;">Credits</th>
                    <th style="min-width: 135px;">Instructor Name</th>
                    <th style="min-width: 150px;">Class Schedule</th>
                    <th style="min-width: 100px;">Room Number</th>
                    <th style="min-width: 105px;">Attendance</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($courses as $c): ?>
                    <tr>
                      <td>
                        <span style="font-family: monospace; font-weight: 800; font-size: 0.86rem; color: var(--sp-primary); background: #eff6ff; padding: 2px 7px; border-radius: 4px; border: 1px solid #bfdbfe; display: inline-block;">
                          <?= e($c['course_code']) ?>
                        </span>
                        <?php if ($selectedSemester === 'all'): ?>
                          <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;"><?= e($c['semester']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <strong style="color: #0f172a; font-size: 0.88rem; line-height: 1.35; display: block;"><?= e($c['course_name']) ?></strong>
                      </td>
                      <td>
                        <span class="badge-enrolled" style="white-space: nowrap;"><?= number_format((float)$c['credit'], 1) ?> Cr</span>
                      </td>
                      <td style="font-size: 0.83rem; color: #334155;">
                        <div style="font-weight: 600;"><i class="fas fa-user-tie" style="color: var(--sp-primary); width: 14px;"></i> <?= e($c['instructor_name'] ?: 'Department Faculty') ?></div>
                      </td>
                      <td style="font-size: 0.82rem; color: #475569;">
                        <i class="fas fa-clock" style="color: #64748b; width: 14px;"></i> <?= e($c['class_schedule'] ?: 'Schedule TBA') ?>
                      </td>
                      <td style="font-size: 0.82rem; color: #475569;">
                        <span style="font-weight: 600; color: #334155;"><i class="fas fa-door-open" style="color: #64748b; width: 14px;"></i> <?= e($c['room_no'] ?: 'TBA') ?></span>
                      </td>
                      <td>
                        <div style="display: flex; align-items: center; gap: 6px;">
                          <strong style="color: <?= ($c['attendance_percent'] >= 75) ? '#16a34a' : '#dc2626' ?>; font-size: 0.92rem;">
                            <?= (int)$c['attendance_percent'] ?>%
                          </strong>
                          <span style="font-size: 0.7rem; font-weight: 700; padding: 1px 5px; border-radius: 4px; background: <?= ($c['attendance_percent'] >= 75) ? '#dcfce7; color:#15803d;' : '#fee2e2; color:#b91c1c;' ?>;">
                            <?= ($c['attendance_percent'] >= 75) ? 'Good' : 'Low' ?>
                          </span>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 2. Tuition & Semester Fee History Section -->
      <div class="sp-card">
        <div class="sp-card-header" style="flex-wrap: wrap; gap: 0.75rem;">
          <div>
            <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
              <i class="fas fa-receipt" style="color: #16a34a;"></i> Tuition &amp; Semester Fee History
            </h3>
            <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;">
              Payment transaction records and semester invoices
            </div>
          </div>

          <div>
            <a href="<?= APP_URL ?>/student/fees.php" style="background: #eff6ff; color: var(--sp-primary); text-decoration: none; padding: 5px 12px; border-radius: 6px; border: 1px solid #bfdbfe; font-weight: 700; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s ease;">
              <i class="fas fa-history"></i> All Payments &rarr;
            </a>
          </div>
        </div>

        <div class="sp-card-body" style="padding: 0;">
          <?php if (empty($fees)): ?>
            <!-- Clean Empty State for Fees -->
            <div style="text-align: center; padding: 2.75rem 1.5rem; background: #ffffff;">
              <div style="width: 58px; height: 58px; margin: 0 auto 0.75rem; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 1.5rem;">
                <i class="fas fa-receipt"></i>
              </div>
              <h4 style="margin: 0 0 0.35rem; font-size: 1rem; font-weight: 700; color: #1e293b;">No Fee Records Found</h4>
              <p style="margin: 0 0 1rem; font-size: 0.83rem; color: #64748b; line-height: 1.5;">
                No tuition or semester fees have been recorded for your student account yet.
              </p>
              <a href="<?= APP_URL ?>/student/fees.php" style="font-size: 0.82rem; font-weight: 700; color: var(--sp-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                <i class="fas fa-plus-circle"></i> Pay Semester Fees Online &rarr;
              </a>
            </div>
          <?php else: ?>
            <div style="overflow-x: auto;">
              <table class="sp-table">
                <thead>
                  <tr>
                    <th style="min-width: 160px;">Fee Description</th>
                    <th style="min-width: 95px;">Amount</th>
                    <th style="min-width: 95px;">Payment Status</th>
                    <th style="min-width: 130px;">Transaction ID</th>
                    <th style="min-width: 95px;">Paid Date</th>
                    <th style="min-width: 80px; text-align: center;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($fees as $f): ?>
                    <tr>
                      <td>
                        <strong style="color: #0f172a; font-size: 0.88rem; display: block;"><?= e($f['fee_title']) ?></strong>
                        <small style="color: #64748b; font-size: 0.76rem;">Voucher #<?= str_pad((string)$f['id'], 6, '0', STR_PAD_LEFT) ?> • Due: <?= date('M d, Y', strtotime($f['due_date'])) ?></small>
                      </td>
                      <td>
                        <strong style="color: #0f172a; font-size: 0.92rem;">৳<?= number_format((float)$f['amount'], 2) ?></strong>
                      </td>
                      <td>
                        <?php if ($f['status'] === 'Paid'): ?>
                          <span style="background: #dcfce7; color: #15803d; font-weight: 700; padding: 3px 9px; border-radius: 12px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fas fa-check-circle"></i> Paid
                          </span>
                        <?php elseif ($f['status'] === 'Pending'): ?>
                          <span style="background: #fef3c7; color: #b45309; font-weight: 700; padding: 3px 9px; border-radius: 12px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fas fa-clock"></i> Pending
                          </span>
                        <?php elseif ($f['status'] === 'Failed'): ?>
                          <span style="background: #fee2e2; color: #b91c1c; font-weight: 700; padding: 3px 9px; border-radius: 12px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fas fa-times-circle"></i> Failed
                          </span>
                        <?php else: ?>
                          <span style="background: #f1f5f9; color: #475569; font-weight: 700; padding: 3px 9px; border-radius: 12px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fas fa-hourglass-start"></i> Due
                          </span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if (!empty($f['transaction_id'])): ?>
                          <span style="font-family: monospace; font-size: 0.82rem; color: #0369a1; font-weight: 600;"><?= e($f['transaction_id']) ?></span>
                        <?php else: ?>
                          <span style="color: #94a3b8; font-size: 0.82rem;">—</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($f['status'] === 'Paid' && !empty($f['paid_at'])): ?>
                          <span style="font-size: 0.82rem; color: #475569; font-weight: 500;"><?= date('M d, Y', strtotime($f['paid_at'])) ?></span>
                        <?php else: ?>
                          <span style="color: #94a3b8; font-size: 0.82rem;">—</span>
                        <?php endif; ?>
                      </td>
                      <td style="text-align: center;">
                        <?php if ($f['status'] === 'Paid'): ?>
                          <a href="<?= APP_URL ?>/student/fees.php" style="background: #eff6ff; color: #1e73be; border: 1px solid #bfdbfe; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 3px;">
                            <i class="fas fa-receipt"></i> Voucher
                          </a>
                        <?php else: ?>
                          <a href="<?= APP_URL ?>/student/fees.php?pay_id=<?= (int)$f['id'] ?>" style="background: #16a34a; color: #ffffff; padding: 3px 9px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 3px;">
                            <i class="fas fa-wallet"></i> <?= ($f['status'] === 'Failed') ? 'Retry' : 'Pay' ?>
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
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
            <span>Download Semester Transcripts (সেমিস্টার ট্রান্সক্রিপ্ট)</span>
          </a>

          <a href="<?= APP_URL ?>/student/semester-transcript.php?semester=all" target="_blank" style="display:flex; align-items:center; gap:0.65rem; padding:0.65rem 0.9rem; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; color:#166534; text-decoration:none; font-size:0.85rem; font-weight:700; transition:all 0.15s ease;">
            <i class="fas fa-file-pdf" style="color:#dc2626; font-size:1.1rem;"></i>
            <span>Full Academic Transcript (৮টি সেমিস্টার PDF)</span>
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

