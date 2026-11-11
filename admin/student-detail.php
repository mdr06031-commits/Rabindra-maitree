<?php
/**
 * Admin Panel: Student Full Profile, Courses, Grades, Result & Account Control
 * Rabindra Maitree University (RMU)
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
}
ensure_student_password_schema();
$adminPageTitle = 'Student Profile & Academic Control';
$db = getDb();
$currentAdmin = current_user();
$adminId = (int)($currentAdmin['id'] ?? ($_SESSION['user_id'] ?? 1));

$studentId = (int)($_GET['id'] ?? 0);
if ($studentId <= 0) {
    flash_set('error', 'Invalid student identifier.');
    header("Location: " . APP_URL . "/admin/students.php");
    exit;
}

// Fetch student details
$stmt = $db->prepare("
    SELECT s.*, p.title_en AS program_title, p.degree_level,
           p.degree_type AS prog_degree_type, p.duration AS prog_duration,
           p.duration_years AS prog_duration_years, p.total_semesters AS prog_total_semesters,
           d.name_en AS dept_name, f.name_en AS faculty_name
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN faculties f ON d.faculty_id = f.id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    flash_set('error', 'Student not found.');
    header("Location: " . APP_URL . "/admin/students.php");
    exit;
}

// Fetch linked admission record if exists
$admStmt = $db->prepare("SELECT * FROM admissions WHERE student_id = ? OR (mobile = ? AND mobile != '') OR (email = ? AND email != '') ORDER BY id DESC LIMIT 1");
$admStmt->execute([$student['student_id'], $student['phone'] ?? '', $student['email'] ?? '']);
$admissionRecord = $admStmt->fetch(PDO::FETCH_ASSOC);

$progStruct = get_student_program_structure($student);
$totalSemesters = max(1, (int)($progStruct['total_semesters'] ?? 8));
$durationYears = (float)($progStruct['duration_years'] ?? 4.0);
$resultGate = can_manage_student_results($studentId);
$isManager = is_admin_manager_role();
$isResultSuspended = isset($student['result_access_status']) && $student['result_access_status'] === 'Suspended';
$defaultSessionVal = !empty($student['academic_session']) ? $student['academic_session'] : ($student['batch'] ?: get_current_academic_session_name('Fall 2026'));
$defaultPubDateVal = !empty($student['result_publication_date']) ? $student['result_publication_date'] : date('Y-m-d');
$selectedPassingYear = $student['passing_year'] ?? ($admissionRecord['passing_year'] ?? null);
$oversightEval = evaluate_passing_year_difference($defaultSessionVal, $selectedPassingYear, $durationYears, !empty($student['historical_record_approved']));
$allProgramsList = $db->query("SELECT id, title_en, degree_type, duration_years, total_semesters FROM programs ORDER BY sort_order, title_en")->fetchAll(PDO::FETCH_ASSOC);
$allSessionsDatalist = get_academic_sessions(false, 'DESC');
$allYearsDatalist = get_passing_years(false, 'DESC');

// Redirect helper preserving tab
$returnTab = trim($_POST['return_tab'] ?? ($_GET['tab'] ?? ''));
$redirectUrl = APP_URL . "/admin/student-detail.php?id=" . $studentId . ($returnTab !== '' ? "&tab=" . urlencode($returnTab) : '');

// -----------------------------------------------------------------------------
// POST Action Handlers
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
        header("Location: " . $redirectUrl);
        exit;
    } elseif (!is_admin_manager_role()) {
        log_audit('STUDENT_ACTION_DENIED', "Unauthorized role '" . current_admin_role() . "' attempted action '" . ($_POST['action'] ?? '') . "' on student #{$studentId}");
        flash_set('error', 'Access denied: Only Administrator accounts may modify student accounts or academic results.');
        header("Location: " . $redirectUrl);
        exit;
    }

    $action = $_POST['action'] ?? '';

    $resultGatedActions = [
        'generate_cgpa_results', 'update_semester_gpa', 'verify_estimated_semester',
        'verify_all_results_official', 'add_result', 'edit_result', 'delete_result',
        'publish_all_results', 'unpublish_all_results', 'publish_semester',
        'unpublish_semester', 'complete_all_semesters'
    ];
    if (in_array($action, $resultGatedActions, true) && empty($resultGate['allowed'])) {
        flash_set('error', $resultGate['reason']);
        header("Location: " . $redirectUrl);
        exit;
    }

    // -------------------------------------------------------------------------
    // A0. Academic Oversight: Historical Record Approval & Audited Official Edit
    // -------------------------------------------------------------------------
    if ($action === 'approve_historical') {
        $note = trim($_POST['approval_note'] ?? '');
        $res = approve_historical_academic_record('student', $studentId, $adminId, $note);
        if (!empty($res['success'])) {
            flash_set('success', "Historical academic record & Passing Year approved for {$student['student_id']} ({$student['full_name']}).");
        } else {
            flash_set('error', $res['error'] ?? 'Could not approve historical academic record.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId);
        exit;
    }

    elseif ($action === 'official_academic_edit') {
        $reason = trim($_POST['edit_reason'] ?? '');
        $updates = [
            'academic_session' => trim($_POST['academic_session'] ?? ''),
            'passing_year'     => trim($_POST['passing_year'] ?? ''),
            'program_id'       => (int)($_POST['program_id'] ?? 0),
            'degree_type'      => trim($_POST['degree_type'] ?? ''),
            'duration_years'   => $_POST['duration_years'] ?? '',
            'total_semesters'  => (int)($_POST['total_semesters'] ?? 0),
        ];
        $res = update_academic_record_with_audit('student', $studentId, $updates, $adminId, $reason);
        if (!empty($res['success'])) {
            $expStr = !empty($res['expected_passing_year']) ? (string)$res['expected_passing_year'] : 'N/A';
            flash_set('success', "Official academic update saved & audited ({$res['changes']} change(s) logged). Student's Passing Year remained under admin-entered value; Advisory Expected Year: {$expStr}.");
        } else {
            flash_set('error', $res['error'] ?? 'Official academic update failed.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId);
        exit;
    }

    // -------------------------------------------------------------------------
    // A. Individual Student Account Control & Result Visibility Actions
    // -------------------------------------------------------------------------
    if ($action === 'reset_student_password') {
        $res = reset_student_login_password($studentId, $adminId, 'admin_reset');
        if (!empty($res['success'])) {
            flash_set('success', "New temporary login password generated and saved securely for {$student['student_id']} ({$student['full_name']}). Previous Student Portal sessions have been invalidated. Use Show/Hide Password or Copy Password in Student Login Credentials below.");
        } else {
            flash_set('error', $res['error'] ?? 'Could not reset student password.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "#studentLoginCredentialsSection");
        exit;
    }

    elseif ($action === 'clear_temp_credential') {
        clear_admin_temp_student_password($studentId);
        flash_set('info', 'Temporary password display cleared from your current administrator session.');
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "#studentLoginCredentialsSection");
        exit;
    }

    elseif ($action === 'activate_account') {
        $reason = trim($_POST['reason'] ?? '');
        $res = activate_student_account($studentId, $adminId, $reason);
        if ($res['success']) {
            flash_set('success', "Student account {$student['student_id']} ({$student['full_name']}) is now Active and can log into the Student Portal.");
        } else {
            flash_set('error', $res['error'] ?? 'Could not activate account.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId);
        exit;
    }

    elseif ($action === 'deactivate_account') {
        $reason = trim($_POST['reason'] ?? '');
        $res = deactivate_student_account($studentId, $adminId, $reason);
        if ($res['success']) {
            flash_set('success', "Account {$student['student_id']} deactivated. Student login blocked, active sessions invalidated, and all academic records preserved.");
        } else {
            flash_set('error', $res['error'] ?? 'Could not deactivate account.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId);
        exit;
    }

    elseif ($action === 'delete_student_account') {
        $confirmInput = trim($_POST['confirm_student_id'] ?? '');
        $confirmCheck = !empty($_POST['confirm_permanent_delete']);
        $reason = trim($_POST['reason'] ?? '');

        if (!$confirmCheck || ($confirmInput !== $student['student_id'] && strtoupper($confirmInput) !== 'DELETE')) {
            flash_set('error', "Permanent deletion cancelled: you must tick the confirmation box and type '{$student['student_id']}' or 'DELETE' to confirm.");
            header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId);
            exit;
        }

        $res = delete_student_account_safely($studentId, $adminId, $reason);
        if ($res['success']) {
            flash_set('success', "Student account {$student['student_id']} permanently deleted. Login credentials revoked, active sessions cleared, and {$res['results_retained']} academic record(s) archived under Retention Policy (Archive #{$res['archive_id']}).");
        } else {
            flash_set('error', $res['error'] ?? 'Account deletion failed.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId);
        exit;
    }

    elseif ($action === 'show_results') {
        $res = set_student_result_visibility($studentId, true, $adminId);
        if ($res['success']) {
            flash_set('success', "Results are now VISIBLE (Show Result) in the Student Portal for {$student['full_name']}.");
        } else {
            flash_set('error', $res['error'] ?? 'Could not update result visibility.');
        }
        header("Location: " . $redirectUrl);
        exit;
    }

    elseif ($action === 'hide_results') {
        $reason = trim($_POST['reason'] ?? '');
        $res = set_student_result_visibility($studentId, false, $adminId, $reason);
        if ($res['success']) {
            flash_set('success', "Results are now HIDDEN (Hide Result) from student view. Underlying academic records remain safely stored in the database.");
        } else {
            flash_set('error', $res['error'] ?? 'Could not hide results.');
        }
        header("Location: " . $redirectUrl);
        exit;
    }

    elseif ($action === 'publish_all_results') {
        $res = set_student_results_publication($studentId, true, null, $adminId);
        if ($res['success']) {
            flash_set('success', "Published {$res['affected']} subject result(s) across all semesters to the Student Portal. Updated CGPA: " . number_format((float)$res['cgpa'], 2) . ".");
        } else {
            flash_set('error', $res['error'] ?? 'Failed to publish results.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
        exit;
    }

    elseif ($action === 'unpublish_all_results') {
        $res = set_student_results_publication($studentId, false, null, $adminId);
        if ($res['success']) {
            flash_set('success', "Unpublished {$res['affected']} subject result(s) back to Draft (removed from official student view while preserving data).");
        } else {
            flash_set('error', $res['error'] ?? 'Failed to unpublish results.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
        exit;
    }

    elseif ($action === 'publish_semester') {
        $semName = trim($_POST['semester_name'] ?? '');
        if ($semName !== '') {
            $res = set_student_results_publication($studentId, true, $semName, $adminId);
            if (!empty($res['success'])) {
                flash_set('success', "Published {$res['affected']} result(s) for {$semName}. Updated CGPA: " . number_format((float)$res['cgpa'], 2) . ".");
            } else {
                flash_set('error', $res['error'] ?? "Cannot publish {$semName}.");
            }
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
        exit;
    }

    elseif ($action === 'unpublish_semester') {
        $semName = trim($_POST['semester_name'] ?? '');
        if ($semName !== '') {
            $res = set_student_results_publication($studentId, false, $semName, $adminId);
            if (!empty($res['success'])) {
                flash_set('success', "Unpublished {$res['affected']} result(s) for {$semName} back to Draft.");
            } else {
                flash_set('error', $res['error'] ?? "Cannot unpublish {$semName}.");
            }
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
        exit;
    }

    elseif ($action === 'verify_estimated_semester') {
        $semName = trim($_POST['semester_name'] ?? '');
        if ($semName !== '') {
            $cnt = verify_semester_results_as_official($studentId, $semName, $adminId);
            flash_set('success', "Verified {$cnt} estimated record(s) in {$semName} as Official Examination Results.");
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
        exit;
    }

    elseif ($action === 'generate_cgpa_results') {
        $cgpaRaw = trim($_POST['final_cgpa'] ?? '');
        $finalCgpa = is_numeric($cgpaRaw) ? (float)$cgpaRaw : -1.0;
        if ($finalCgpa < 2.00 || $finalCgpa > 4.00) {
            flash_set('error', 'Please enter a valid Final CGPA between 2.00 and 4.00 (UGC 4.00 scale).');
            header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
            exit;
        }
        $academicSession = trim($_POST['academic_session'] ?? $defaultSessionVal);
        $publicationDate = trim($_POST['publication_date'] ?? $defaultPubDateVal);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $publicationDate)) {
            $publicationDate = date('Y-m-d');
        }
        $publishMode = (($_POST['publish_mode'] ?? '1') === '1');
        $overrideSems = isset($_POST['total_semesters']) && (int)$_POST['total_semesters'] > 0 ? (int)$_POST['total_semesters'] : null;

        $res = generate_estimated_student_results($studentId, $finalCgpa, $publishMode, $adminId, [
            'academic_session' => $academicSession,
            'publication_date' => $publicationDate,
            'total_semesters'  => $overrideSems,
        ]);
        if (!empty($res['success'])) {
            flash_set('success', "Generated {$res['total_semesters']}-semester ESTIMATED / GENERATED results for Session '{$res['academic_session']}' (Published Date: {$res['publication_date']}). Target CGPA: " . number_format((float)$res['target_cgpa'], 2) . ", Realized Credit-Weighted CGPA: " . number_format((float)$res['realized_cgpa'], 2) . ". Label: ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT.");
        } else {
            flash_set('error', $res['error'] ?? 'Could not generate estimated results.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
        exit;
    }

    elseif ($action === 'update_semester_gpa') {
        $semNumber = (int)($_POST['semester_number'] ?? 0);
        $gpaRaw = trim($_POST['semester_gpa'] ?? '');
        $semGpa = is_numeric($gpaRaw) ? (float)$gpaRaw : -1.0;
        $reason = trim($_POST['reason'] ?? '');
        if ($semGpa < 2.00 || $semGpa > 4.00) {
            flash_set('error', 'Semester GPA must be between 2.00 and 4.00.');
        } else {
            $res = update_semester_cgpa_target($studentId, $semNumber, $semGpa, $adminId, $reason);
            if (!empty($res['success'])) {
                flash_set('success', "Updated {$res['semester_name']} GPA to " . number_format($semGpa, 2) . " (rebalanced subjects cleanly). Cumulative CGPA: " . number_format((float)$res['new_cgpa'], 2) . ".");
            } else {
                flash_set('error', $res['error'] ?? 'Failed to update semester GPA.');
            }
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
        exit;
    }

    elseif ($action === 'verify_all_results_official') {
        $res = verify_all_student_results_official($studentId, $adminId);
        if (!empty($res['success'])) {
            flash_set('success', "Verified {$res['verified_count']} estimated course record(s) across all semesters as Official Examination Results. CGPA: " . number_format((float)$res['cgpa'], 2) . ".");
        } else {
            flash_set('error', $res['error'] ?? 'Failed to verify results.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
        exit;
    }

    elseif ($action === 'suspend_result_access') {
        $reason = trim($_POST['reason'] ?? '');
        $res = suspend_student_result_access($studentId, $adminId, $reason);
        if (!empty($res['success'])) {
            flash_set('success', "Result access SUSPENDED for {$student['full_name']}. Student account remains Active (login and profile work), while results & transcripts are blocked.");
        } else {
            flash_set('error', $res['error'] ?? 'Could not suspend result access.');
        }
        header("Location: " . $redirectUrl);
        exit;
    }

    elseif ($action === 'restore_result_access') {
        $res = restore_student_result_access($studentId, $adminId);
        if (!empty($res['success'])) {
            flash_set('success', "Result access RESTORED for {$student['full_name']}.");
        } else {
            flash_set('error', $res['error'] ?? 'Could not restore result access.');
        }
        header("Location: " . $redirectUrl);
        exit;
    }

    elseif ($action === 'delete_all_results') {
        $confirmChecked = !empty($_POST['confirm_delete_all_results']);
        $confirmTxt = strtoupper(trim($_POST['confirm_delete_results'] ?? ($_POST['confirm_text'] ?? '')));
        if (!$confirmChecked && $confirmTxt !== 'DELETE') {
            flash_set('error', 'Deletion cancelled: please confirm permanent deletion of all academic results for this student.');
            header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
            exit;
        }
        $reason = trim($_POST['reason'] ?? '');
        $res = delete_student_all_results($studentId, $adminId, $reason);
        if (!empty($res['success'])) {
            flash_set('success', "All {$res['deleted_count']} academic result record(s) permanently deleted for {$student['student_id']}. Student portal account & login remain intact.");
        } else {
            flash_set('error', $res['error'] ?? 'Failed to delete results.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
        exit;
    }

    // -------------------------------------------------------------------------
    // B. Personal Profile & Identifiers Update
    // -------------------------------------------------------------------------
    elseif ($action === 'update_personal_info') {
        $fullName = trim($_POST['full_name'] ?? $student['full_name']);
        $rollNo = trim($_POST['roll_number'] ?? ($student['roll_number'] ?? ''));
        $appNo = trim($_POST['application_number'] ?? ($student['application_number'] ?? ''));
        $username = trim($_POST['username'] ?? ($student['username'] ?? $student['student_id']));
        $fatherName = trim($_POST['father_name'] ?? '');
        $motherName = trim($_POST['mother_name'] ?? '');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $gender = in_array($_POST['gender'] ?? '', ['Male', 'Female', 'Other']) ? $_POST['gender'] : 'Male';
        $bloodGroup = trim($_POST['blood_group'] ?? '');
        $phone = trim($_POST['phone'] ?? $student['phone']);
        $email = trim($_POST['email'] ?? $student['email']);
        $presentAddr = trim($_POST['present_address'] ?? '');
        $permAddr = trim($_POST['permanent_address'] ?? '');
        $homeDistrict = trim($_POST['home_district'] ?? '');

        $dupError = validate_unique_student_identifiers($db, null, $rollNo, $appNo, $email, $studentId);
        if ($dupError !== null) {
            flash_set('error', $dupError);
            header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=admissionTab");
            exit;
        }

        try {
            $updSt = $db->prepare("
                UPDATE students SET 
                    full_name = ?, roll_number = ?, application_number = ?, username = ?,
                    father_name = ?, mother_name = ?, dob = ?, gender = ?,
                    blood_group = ?, phone = ?, email = ?, present_address = ?, permanent_address = ?, home_district = ?
                WHERE id = ?
            ");
            $updSt->execute([
                $fullName, $rollNo ?: null, $appNo ?: null, $username ?: $student['student_id'],
                $fatherName, $motherName, $dob, $gender,
                $bloodGroup, $phone, $email, $presentAddr, $permAddr, $homeDistrict, $studentId
            ]);
            log_audit('STUDENT_PROFILE_UPDATED', "Updated personal profile & identifiers for student {$student['student_id']} (#{$studentId})", $adminId);
            flash_set('success', 'Student personal profile updated successfully.');
        } catch (PDOException $e) {
            if (is_retryable_db_concurrency_exception($e)) {
                flash_set('error', 'Duplicate Roll Number, Application ID, or Email detected. Each student must have unique identifiers.');
            } else {
                flash_set('error', 'Unable to update student profile due to a database error.');
            }
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=admissionTab");
        exit;
    }

    // -------------------------------------------------------------------------
    // C. Course Enrollment Actions
    // -------------------------------------------------------------------------
    elseif ($action === 'add_course') {
        $courseCode = trim($_POST['course_code'] ?? '');
        $courseName = trim($_POST['course_name'] ?? '');
        $credit = (float)($_POST['credit'] ?? 3.0);
        $instructor = trim($_POST['instructor_name'] ?? '');
        $schedule = trim($_POST['class_schedule'] ?? '');
        $roomNo = trim($_POST['room_no'] ?? '');
        $semester = trim($_POST['semester'] ?? $student['current_semester']);
        $attendance = (int)($_POST['attendance_percent'] ?? 85);
        $status = in_array($_POST['status'] ?? '', ['Enrolled', 'Completed', 'Dropped']) ? $_POST['status'] : 'Enrolled';

        if (!empty($courseCode) && !empty($courseName)) {
            $cStmt = $db->prepare("
                INSERT INTO student_courses 
                (student_id, course_code, course_name, credit, instructor_name, class_schedule, room_no, attendance_percent, semester, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $cStmt->execute([$studentId, $courseCode, $courseName, $credit, $instructor, $schedule, $roomNo, $attendance, $semester, $status]);
            log_audit('STUDENT_COURSE_ENROLLED', "Enrolled {$student['student_id']} (#{$studentId}) in {$courseCode} - {$courseName}", $adminId);
            flash_set('success', "Course '{$courseCode}' added successfully.");
        } else {
            flash_set('error', 'Course code and name are required.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=coursesTab");
        exit;
    }

    elseif ($action === 'delete_course') {
        $cid = (int)($_POST['course_id'] ?? 0);
        if ($cid > 0) {
            $del = $db->prepare("DELETE FROM student_courses WHERE id = ? AND student_id = ?");
            $del->execute([$cid, $studentId]);
            log_audit('STUDENT_COURSE_REMOVED', "Removed course ID #{$cid} from student {$student['student_id']} (#{$studentId})", $adminId);
            flash_set('success', "Enrolled course record removed.");
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=coursesTab");
        exit;
    }

    // -------------------------------------------------------------------------
    // D. Academic Result Actions (Upload, Draft/Publish, Edit, Delete)
    // -------------------------------------------------------------------------
    elseif (in_array($action, ['add_result', 'edit_result'], true)) {
        $resolveCanonicalSemester = function(int $sid, string $rawSem, int $excludeId = 0) use ($db): string {
            $clean = trim($rawSem);
            $sNum = get_semester_number($clean);
            if ($sNum >= 1 && $sNum <= 12) {
                $stmtSem = $db->prepare("SELECT semester FROM student_results WHERE student_id = ? AND id != ? ORDER BY id ASC");
                $stmtSem->execute([$sid, $excludeId]);
                while ($existing = $stmtSem->fetchColumn()) {
                    if (get_semester_number((string)$existing) === $sNum) {
                        return trim((string)$existing);
                    }
                }
            }
            return $clean;
        };

        if ($action === 'add_result') {
            $semester = trim($_POST['semester'] ?? '');
            $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));
            $courseName = trim($_POST['course_name'] ?? '');
            $credit = round((float)($_POST['credit'] ?? 3.0), 1);
            $fullMarks = (int)($_POST['full_marks'] ?? 100);
            $marks = isset($_POST['marks']) && $_POST['marks'] !== '' ? round((float)$_POST['marks'], 2) : null;
            $term = trim($_POST['exam_term'] ?? 'Semester Final Examination');
            $reason = trim($_POST['reason'] ?? 'Individual academic result entry');

            $reqPubState = $_POST['publish_status'] ?? '';
            if (in_array($reqPubState, ['Draft', 'Under Review', 'Published'], true)) {
                $publishStatus = $reqPubState;
                $isPublished = ($publishStatus === 'Published') ? 1 : 0;
            } else {
                $isPublished = !empty($_POST['is_published']) ? 1 : 0;
                $publishStatus = $isPublished ? 'Published' : 'Draft';
            }

            if ($semester === '' || $courseCode === '') {
                flash_set('error', 'Semester and Course code are required.');
            } elseif ($credit < 0.5 || $credit > 9.5) {
                flash_set('error', 'Credit hours must be between 0.5 and 9.5.');
            } elseif ($fullMarks < 1 || $fullMarks > 500) {
                flash_set('error', 'Full marks must be between 1 and 500.');
            } elseif ($marks !== null && ($marks < 0.0 || $marks > (float)$fullMarks)) {
                flash_set('error', "Obtained marks ({$marks}) must be between 0 and Full Marks ({$fullMarks}).");
            } else {
                $semester = $resolveCanonicalSemester($studentId, $semester, 0);
                if ($marks !== null) {
                    $gradeInfo = calculate_grade_from_marks($marks, (float)$fullMarks);
                    $grade = $gradeInfo['letter_grade'];
                    $point = $gradeInfo['grade_point'];
                } else {
                    $grade = trim($_POST['letter_grade'] ?? 'A+');
                    $point = max(0.00, min(4.00, (float)($_POST['grade_point'] ?? 4.00)));
                }

                $pubAt = $isPublished ? date('Y-m-d H:i:s') : null;
                $pubBy = $isPublished ? $adminId : null;

                $rStmt = $db->prepare("
                    INSERT INTO student_results 
                    (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, academic_session, is_published, is_estimated, result_type, publish_status, published_at, published_by, correction_reason)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Verified', ?, ?, ?, ?)
                ");
                $rStmt->execute([
                    $studentId, $semester, $courseCode, $courseName !== '' ? $courseName : $courseCode,
                    $credit, $fullMarks, $marks, $grade, $point, $term, $defaultSessionVal,
                    $isPublished, $publishStatus, $pubAt, $pubBy, $reason
                ]);
                $newResId = (int)$db->lastInsertId();
                $marksDisp = ($marks !== null) ? "{$marks}/{$fullMarks}" : 'N/A';

                log_result_modification($newResId, $studentId, $adminId, $courseCode, $semester, 'Initial Entry', null, "{$marksDisp} Marks ({$grade}, GP {$point}) [{$publishStatus}]", $reason);

                if ($isPublished && $student['status'] !== 'Deleted') {
                    create_student_notification(
                        $studentId,
                        "New Course Result: {$courseCode}",
                        "Your result for {$courseCode} - {$courseName} ({$semester}) has been published. Grade: {$grade} ({$point}).",
                        'result_published',
                        $newResId
                    );
                }

                $resMetrics = recalculate_student_cgpa($studentId);
                log_audit('STUDENT_RESULT_ADDED', "Added {$publishStatus} result for {$student['student_id']} (#{$studentId}): {$courseCode} ({$grade}, {$marksDisp} marks)", $adminId);
                flash_set('success', "Result for '{$courseCode}' saved as {$publishStatus}! Updated CGPA: " . number_format((float)$resMetrics['cgpa'], 2) . ".");
            }
        } else {
            $resultId = (int)($_POST['result_id'] ?? 0);
            $semester = trim($_POST['semester'] ?? '');
            $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));
            $courseName = trim($_POST['course_name'] ?? '');
            $credit = round((float)($_POST['credit'] ?? 3.0), 1);
            $fullMarks = (int)($_POST['full_marks'] ?? 100);
            $marks = isset($_POST['marks']) && $_POST['marks'] !== '' ? round((float)$_POST['marks'], 2) : null;
            $term = trim($_POST['exam_term'] ?? 'Semester Final Examination');
            $reason = trim($_POST['correction_reason'] ?? '');
            if ($reason === '') {
                $reason = 'Administrative grade update via Student Profile';
            }

            if ($resultId <= 0 || $semester === '' || $courseCode === '') {
                flash_set('error', 'Semester, Course Code, and valid result record are required.');
            } elseif ($credit < 0.5 || $credit > 9.5) {
                flash_set('error', 'Credit hours must be between 0.5 and 9.5.');
            } elseif ($fullMarks < 1 || $fullMarks > 500) {
                flash_set('error', 'Full marks must be between 1 and 500.');
            } elseif ($marks !== null && ($marks < 0.0 || $marks > (float)$fullMarks)) {
                flash_set('error', "Obtained marks ({$marks}) must be between 0 and Full Marks ({$fullMarks}).");
            } else {
                $oldStmt = $db->prepare("SELECT * FROM student_results WHERE id = ? AND student_id = ? LIMIT 1");
                $oldStmt->execute([$resultId, $studentId]);
                $oldRes = $oldStmt->fetch(PDO::FETCH_ASSOC);

                if ($oldRes) {
                    $semester = $resolveCanonicalSemester($studentId, $semester, $resultId);
                    if ($marks !== null) {
                        $gradeInfo = calculate_grade_from_marks($marks, (float)$fullMarks);
                        $grade = $gradeInfo['letter_grade'];
                        $point = $gradeInfo['grade_point'];
                    } else {
                        $grade = trim($_POST['letter_grade'] ?? $oldRes['letter_grade']);
                        $point = max(0.00, min(4.00, (float)($_POST['grade_point'] ?? $oldRes['grade_point'])));
                    }

                    $reqPubState = $_POST['publish_status'] ?? ($oldRes['publish_status'] ?? 'Published');
                    $publishStatus = in_array($reqPubState, ['Draft', 'Under Review', 'Published'], true) ? $reqPubState : 'Draft';
                    $isPublished = ($publishStatus === 'Published') ? 1 : 0;
                    $pubAt = $isPublished ? ($oldRes['published_at'] ?? date('Y-m-d H:i:s')) : null;
                    $pubBy = $isPublished ? ($oldRes['published_by'] ?? $adminId) : null;

                    // Editing an estimated result replaces it cleanly as verified unless explicitly left estimated
                    $isEstimatedVal = !empty($_POST['keep_estimated']) ? (int)$oldRes['is_estimated'] : 0;
                    $resultTypeVal = ($isEstimatedVal === 1) ? 'Estimated' : 'Verified';

                    $upd = $db->prepare("
                        UPDATE student_results
                        SET semester = ?, course_code = ?, course_name = ?, credit = ?, full_marks = ?, marks = ?,
                            letter_grade = ?, grade_point = ?, exam_term = ?, is_published = ?, publish_status = ?,
                            is_estimated = ?, result_type = ?, published_at = ?, published_by = ?, correction_reason = ?
                        WHERE id = ? AND student_id = ?
                    ");
                    $upd->execute([
                        $semester, $courseCode, $courseName !== '' ? $courseName : $courseCode, $credit, $fullMarks, $marks,
                        $grade, $point, $term, $isPublished, $publishStatus,
                        $isEstimatedVal, $resultTypeVal, $pubAt, $pubBy, $reason,
                        $resultId, $studentId
                    ]);

                    $oldSummary = "Marks: " . ($oldRes['marks'] ?? 'N/A') . "/" . ($oldRes['full_marks'] ?? 100) . ", Grade: {$oldRes['letter_grade']}, GP: {$oldRes['grade_point']}, Status: {$oldRes['publish_status']}";
                    $newSummary = "Marks: " . ($marks ?? 'N/A') . "/{$fullMarks}, Grade: {$grade}, GP: {$point}, Status: {$publishStatus}";
                    log_result_modification($resultId, $studentId, $adminId, $courseCode, $semester, 'Grade Correction', $oldSummary, $newSummary, $reason);

                    if ($isPublished && $student['status'] !== 'Deleted') {
                        create_student_notification(
                            $studentId,
                            "Academic Result Updated: {$courseCode}",
                            "Your result for {$courseCode} ({$semester}) was updated to {$grade} ({$point}). Note: {$reason}",
                            'grade_correction',
                            $resultId
                        );
                    }

                    $resMetrics = recalculate_student_cgpa($studentId);
                    log_audit('STUDENT_RESULT_EDITED', "Edited result #{$resultId} ({$courseCode}) for {$student['student_id']} (#{$studentId}). Reason: {$reason}", $adminId);
                    flash_set('success', "Result '{$courseCode}' updated ({$grade}, {$publishStatus}). Updated CGPA: " . number_format((float)$resMetrics['cgpa'], 2) . ".");
                } else {
                    flash_set('error', 'Target result record was not found for this student.');
                }
            }
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
        exit;
    }

    elseif ($action === 'delete_result') {
        $rid = (int)($_POST['result_id'] ?? 0);
        if ($rid > 0) {
            $oldStmt = $db->prepare("SELECT * FROM student_results WHERE id = ? AND student_id = ?");
            $oldStmt->execute([$rid, $studentId]);
            $oldRes = $oldStmt->fetch(PDO::FETCH_ASSOC);
            if ($oldRes) {
                log_result_modification($rid, $studentId, $adminId, $oldRes['course_code'], $oldRes['semester'], 'Subject Deleted', "{$oldRes['letter_grade']} ({$oldRes['marks']} marks)", 'DELETED', 'Removed via Student Profile');
            }
            $del = $db->prepare("DELETE FROM student_results WHERE id = ? AND student_id = ?");
            $del->execute([$rid, $studentId]);
            $resMetrics = recalculate_student_cgpa($studentId);
            log_audit('STUDENT_RESULT_DELETED', "Deleted result ID #{$rid} from student {$student['student_id']} (#{$studentId})", $adminId);
            flash_set('success', "Academic result record deleted. Updated CGPA: " . number_format((float)$resMetrics['cgpa'], 2) . ".");
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=gradesTab");
        exit;
    }

    // -------------------------------------------------------------------------
    // E. Fee Actions
    // -------------------------------------------------------------------------
    elseif ($action === 'add_fee') {
        $title = trim($_POST['fee_title'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $dueDate = trim($_POST['due_date'] ?? date('Y-m-d'));
        $status = in_array($_POST['status'] ?? '', ['Paid', 'Pending', 'Failed', 'Due']) ? $_POST['status'] : 'Due';
        $trx = trim($_POST['transaction_id'] ?? '');

        if (!empty($title) && $amount > 0) {
            $fStmt = $db->prepare("
                INSERT INTO student_fees 
                (student_id, fee_title, amount, due_date, status, transaction_id, paid_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $paidAt = ($status === 'Paid') ? date('Y-m-d H:i:s') : null;
            if ($status === 'Paid' && empty($trx)) {
                $trx = 'RMU-PAY-' . date('Y') . '-' . rand(10000, 99999);
            }
            $fStmt->execute([$studentId, $title, $amount, $dueDate, $status, $trx ?: null, $paidAt]);
            log_audit('STUDENT_FEE_ADDED', "Invoiced fee '{$title}' (৳{$amount}, Status: {$status}) for {$student['student_id']} (#{$studentId})", $adminId);
            flash_set('success', "Fee record for '{$title}' (৳" . number_format($amount) . ") created with status: {$status}.");
        } else {
            flash_set('error', 'Fee title and valid amount are required.');
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=feesTab");
        exit;
    }

    elseif ($action === 'mark_paid') {
        $fid = (int)($_POST['fee_id'] ?? 0);
        $trx = trim($_POST['transaction_id'] ?? 'RMU-PAY-' . date('Y') . '-' . rand(10000, 99999));
        if ($fid > 0) {
            $pStmt = $db->prepare("UPDATE student_fees SET status = 'Paid', transaction_id = ?, paid_at = NOW() WHERE id = ? AND student_id = ?");
            $pStmt->execute([$trx, $fid, $studentId]);
            log_audit('STUDENT_FEE_PAID', "Marked fee ID #{$fid} as paid (Trx: {$trx}) for {$student['student_id']} (#{$studentId})", $adminId);
            flash_set('success', "Fee marked as Paid successfully.");
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=feesTab");
        exit;
    }

    elseif ($action === 'update_fee_status') {
        $fid = (int)($_POST['fee_id'] ?? 0);
        $newStatus = in_array($_POST['status'] ?? '', ['Paid', 'Pending', 'Failed', 'Due']) ? $_POST['status'] : 'Pending';
        if ($fid > 0) {
            if ($newStatus === 'Paid') {
                $trx = 'RMU-PAY-' . date('Y') . '-' . rand(10000, 99999);
                $upStmt = $db->prepare("UPDATE student_fees SET status = 'Paid', transaction_id = COALESCE(NULLIF(transaction_id, ''), ?), paid_at = NOW() WHERE id = ? AND student_id = ?");
                $upStmt->execute([$trx, $fid, $studentId]);
            } else {
                $upStmt = $db->prepare("UPDATE student_fees SET status = ?, paid_at = NULL WHERE id = ? AND student_id = ?");
                $upStmt->execute([$newStatus, $fid, $studentId]);
            }
            log_audit('STUDENT_FEE_STATUS_UPDATED', "Updated fee ID #{$fid} status to {$newStatus} for {$student['student_id']} (#{$studentId})", $adminId);
            flash_set('success', "Fee payment status updated to {$newStatus}.");
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=feesTab");
        exit;
    }

    elseif ($action === 'delete_fee') {
        $fid = (int)($_POST['fee_id'] ?? 0);
        if ($fid > 0) {
            $del = $db->prepare("DELETE FROM student_fees WHERE id = ? AND student_id = ?");
            $del->execute([$fid, $studentId]);
            log_audit('STUDENT_FEE_DELETED', "Deleted fee ID #{$fid} from student {$student['student_id']} (#{$studentId})", $adminId);
            flash_set('success', "Fee invoice deleted.");
        }
        header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId . "&tab=feesTab");
        exit;
    }

    // -------------------------------------------------------------------------
    // F. Semester Progress & Graduation Controls
    // -------------------------------------------------------------------------
    elseif ($action === 'update_semester_progress') {
        $year = trim($_POST['current_year'] ?? '1st Year');
        $semester = trim($_POST['current_semester'] ?? '1st Semester');
        $cgpa = (float)($_POST['cgpa'] ?? 0.00);
        $credits = (int)($_POST['credits_completed'] ?? 0);
        $allowedStatuses = ['Active', 'Graduated', 'Suspended', 'Deactivated'];
        $status = ($student['status'] === 'Deleted')
            ? 'Deleted'
            : (in_array($_POST['status'] ?? '', $allowedStatuses, true) ? $_POST['status'] : $student['status']);

        $upd = $db->prepare("UPDATE students SET current_year = ?, current_semester = ?, cgpa = ?, credits_completed = ?, status = ? WHERE id = ?");
        $upd->execute([$year, $semester, $cgpa, $credits, $status, $studentId]);
        if ($status === 'Deactivated' || $status === 'Suspended') {
            $db->prepare("UPDATE students SET session_version = session_version + 1 WHERE id = ?")->execute([$studentId]);
        }
        log_audit('SEMESTER_PROGRESS_UPDATED', "Updated semester progress for {$student['student_id']} (#{$studentId}): Year={$year}, Sem={$semester}, CGPA={$cgpa}, Status={$status}", $adminId);
        flash_set('success', "Semester progress updated! Student is now in {$year} ({$semester}) with CGPA {$cgpa}.");
    }

    elseif ($action === 'complete_all_semesters') {
        $finalCgpa = (float)($_POST['final_cgpa'] ?? 3.85);
        $finalCgpa = max(2.50, min(4.00, $finalCgpa));

        $yrNum = max(1, (int)ceil((float)($progStruct['duration_years'] ?? 4.0)));
        $yrOrd = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year', 6 => '6th Year'];
        $finalYearLabel = $yrOrd[$yrNum] ?? "{$yrNum}th Year";
        $finalSemLabel = get_semester_ordinal_title($totalSemesters) . ' (Completed)';
        $reqCredits = max(10, (int)($progStruct['total_credits'] ?? ($totalSemesters * 15)));

        $upd = $db->prepare("
            UPDATE students 
            SET current_year = ?, 
                current_semester = ?, 
                status = 'Graduated', 
                credits_completed = ?, 
                cgpa = ?
            WHERE id = ?
        ");
        $upd->execute([$finalYearLabel, $finalSemLabel, $reqCredits, $finalCgpa, $studentId]);

        $db->prepare("UPDATE student_courses SET status = 'Completed' WHERE student_id = ?")->execute([$studentId]);
        $db->prepare("UPDATE student_fees SET status = 'Paid', transaction_id = COALESCE(NULLIF(transaction_id, ''), CONCAT('RMU-COMP-', FLOOR(RAND()*89999+10000))), paid_at = COALESCE(paid_at, NOW()) WHERE student_id = ?")->execute([$studentId]);

        $cntStmt = $db->prepare("SELECT COUNT(*) FROM student_results WHERE student_id = ?");
        $cntStmt->execute([$studentId]);
        $resCount = (int)$cntStmt->fetchColumn();
        if ($resCount < ($totalSemesters * 3)) {
            generate_estimated_student_results($studentId, $finalCgpa, true, $adminId, [
                'academic_session' => $defaultSessionVal,
                'publication_date' => $defaultPubDateVal,
            ]);
            // Preserve graduated status and credits
            $db->prepare("UPDATE students SET current_year = ?, current_semester = ?, status = 'Graduated' WHERE id = ?")
               ->execute([$finalYearLabel, $finalSemLabel, $studentId]);
        }

        log_audit('STUDENT_GRADUATED_ALL_SEMESTERS', "Admin marked all {$totalSemesters} semesters completed and graduated student {$student['student_id']} (#{$studentId}, CGPA: {$finalCgpa})", $adminId);
        flash_set('success', "Student {$student['student_id']} ({$student['full_name']}) marked GRADUATED across all {$totalSemesters} semesters with CGPA {$finalCgpa}!");
    }

    // -------------------------------------------------------------------------
    // G. Impersonation Guarded Login
    // -------------------------------------------------------------------------
    elseif ($action === 'login_as_student') {
        if (in_array($student['status'], ['Deactivated', 'Deleted', 'Suspended'], true)) {
            flash_set('error', "Cannot log into Student Portal: account {$student['student_id']} is {$student['status']}.");
            header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId);
            exit;
        }
        $_SESSION['student_pk_id'] = (int)$student['id'];
        $_SESSION['student_id'] = $student['student_id'];
        $_SESSION['student_name'] = $student['full_name'];
        $_SESSION['student_email'] = $student['email'];
        $_SESSION['student_session_version'] = (int)($student['session_version'] ?? 1);

        log_audit('ADMIN_IMPERSONATED_STUDENT', "Admin #{$adminId} logged into Student Portal as {$student['student_id']} (#{$studentId})", $adminId);
        flash_set('success', "Logged in as student {$student['full_name']} ({$student['student_id']})!");
        header("Location: " . APP_URL . "/student/dashboard.php");
        exit;
    }

    header("Location: " . APP_URL . "/admin/student-detail.php?id=" . $studentId);
    exit;
}

// Fetch related academic & audit datasets
$cStmt = $db->prepare("SELECT * FROM student_courses WHERE student_id = ? ORDER BY id DESC");
$cStmt->execute([$studentId]);
$courses = $cStmt->fetchAll(PDO::FETCH_ASSOC);

$rStmt = $db->prepare("SELECT * FROM student_results WHERE student_id = ? ORDER BY id ASC");
$rStmt->execute([$studentId]);
$results = $rStmt->fetchAll(PDO::FETCH_ASSOC);

// Compute clear status badges for account and result visibility
$totalResCount = count($results);
$publishedResCount = 0;
foreach ($results as $rr) {
    if (!empty($rr['is_published'])) {
        $publishedResCount++;
    }
}
$badges = get_student_status_badges($student, $totalResCount, $publishedResCount);
$accBadge = $badges['account'];
$resBadge = $badges['result'];
$isDeleted = ($student['status'] === 'Deleted');
$isDeactivated = ($student['status'] === 'Deactivated');
$resultsVisible = !isset($student['results_visible']) || (int)$student['results_visible'] === 1;

// Group results by semester and compute running CGPA
$groupedAdminResults = [];
foreach ($results as $res) {
    $groupedAdminResults[$res['semester']][] = $res;
}
uksort($groupedAdminResults, function($a, $b) {
    return get_semester_number($a) <=> get_semester_number($b);
});

$runningAdminCr = 0;
$runningAdminPts = 0;
$semesterAdminStats = [];
foreach ($groupedAdminResults as $sName => $cList) {
    $sCr = array_sum(array_column($cList, 'credit'));
    $sPts = 0;
    $allPub = true;
    $anyEst = false;
    foreach ($cList as $c) {
        $sPts += ((float)$c['credit'] * (float)$c['grade_point']);
        if (empty($c['is_published'])) {
            $allPub = false;
        }
        if (!empty($c['is_estimated'])) {
            $anyEst = true;
        }
    }
    $sSgpa = ($sCr > 0) ? round($sPts / $sCr, 2) : 0.00;
    $runningAdminCr += $sCr;
    $runningAdminPts += $sPts;
    $sCgpa = ($runningAdminCr > 0) ? round($runningAdminPts / $runningAdminCr, 2) : 0.00;

    $semesterAdminStats[$sName] = [
        'sem_credits' => $sCr,
        'sem_points' => $sPts,
        'sgpa' => $sSgpa,
        'cum_credits' => $runningAdminCr,
        'cum_points' => $runningAdminPts,
        'cgpa' => $sCgpa,
        'all_published' => $allPub,
        'has_estimated' => $anyEst,
    ];
}

$fStmt = $db->prepare("SELECT * FROM student_fees WHERE student_id = ? ORDER BY id DESC");
$fStmt->execute([$studentId]);
$fees = $fStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch student-scoped Admin Activity Logs & Result Modification History
$auditStmt = $db->prepare("
    SELECT a.*, u.username AS admin_username, u.full_name AS admin_fullname
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.details LIKE ? OR a.details LIKE ? OR a.details LIKE ? OR a.details LIKE ?
    ORDER BY a.id DESC LIMIT 60
");
$auditStmt->execute([
    "%{$student['student_id']}%",
    "%#{$studentId} %",
    "%#{$studentId})%",
    "%#{$studentId}.%"
]);
$studentAuditLogs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

$modStmt = $db->prepare("
    SELECT h.*, u.username AS admin_username, u.full_name AS admin_fullname
    FROM result_modification_history h
    LEFT JOIN users u ON h.admin_id = u.id
    WHERE h.student_id = ?
    ORDER BY h.id DESC LIMIT 60
");
$modStmt->execute([$studentId]);
$modHistoryLogs = $modStmt->fetchAll(PDO::FETCH_ASSOC);

// Retention archive record if deleted
$retentionArchive = null;
if ($isDeleted) {
    $arStmt = $db->prepare("SELECT id, results_count, deleted_at, deletion_reason FROM student_retention_archives WHERE student_pk_id = ? ORDER BY id DESC LIMIT 1");
    $arStmt->execute([$studentId]);
    $retentionArchive = $arStmt->fetch(PDO::FETCH_ASSOC);
}

$academicEditLogs = get_academic_edit_history('student', $studentId, $student['student_id'], 60);

$existingBySemNum = [];
foreach (array_keys($groupedAdminResults) as $existingSem) {
    $exSn = get_semester_number($existingSem);
    if ($exSn >= 1 && $exSn <= max(12, $totalSemesters) && !isset($existingBySemNum[$exSn])) {
        $existingBySemNum[$exSn] = $existingSem;
    }
}
$standardSemesterList = [];
for ($sNum = 1; $sNum <= $totalSemesters; $sNum++) {
    $standardSemesterList[] = $existingBySemNum[$sNum] ?? get_semester_ordinal_title($sNum);
}
foreach (array_keys($groupedAdminResults) as $existingSem) {
    if (!in_array($existingSem, $standardSemesterList, true)) {
        $standardSemesterList[] = $existingSem;
    }
}
$detailAcademicSummary = get_student_academic_summary($studentId, false);
$semesterSchedule = $detailAcademicSummary['semester_schedule'] ?? [];

include __DIR__ . '/includes/admin-header.php';
?>

<style>
  .sd-header-bar { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
  .sd-action-panel { background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.75rem; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05); }
  .sd-action-groups { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 1rem; margin-top: 0.9rem; }
  .sd-action-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.9rem 1rem; min-width: 0; }
  .sd-btn-row { display: flex; flex-wrap: wrap; gap: 0.5rem; }
  .sd-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.9rem; border-radius: 6px; font-size: 0.82rem; font-weight: 700; border: none; cursor: pointer; text-decoration: none; transition: opacity 0.15s ease; color: #ffffff; }
  .sd-btn:hover { opacity: 0.92; color: #ffffff; }
  .sd-btn:disabled { opacity: 0.45; cursor: not-allowed; }
  .sd-profile-grid { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.75rem; box-shadow: 0 1px 4px rgba(0,0,0,0.05); display: grid; grid-template-columns: auto 1fr auto; gap: 1.75rem; align-items: center; }
  .sd-profile-grid > * { min-width: 0; }
  .sd-info-chips { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 165px), 1fr)); gap: 0.65rem; margin-top: 0.85rem; }
  .sd-chip { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.45rem 0.7rem; font-size: 0.8rem; min-width: 0; overflow-wrap: anywhere; }
  .sd-chip-lbl { color: #64748b; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; display: block; }
  .sd-chip-val { color: #0f172a; font-weight: 700; font-family: inherit; }
  .sd-two-col { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 1.75rem; align-items: start; }
  .sd-two-col > *, .sd-two-col-eq > * { min-width: 0; }
  .sd-two-col-eq { display: grid; grid-template-columns: 1fr 1fr; gap: 1.75rem; align-items: start; }
  .sd-inline-grid-cgpa { background: #ffffff; border: 1px solid #ddd6fe; border-radius: 8px; padding: 1rem 1.15rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 165px), 1fr)) auto; gap: 0.85rem; align-items: flex-end; }
  .sd-inline-grid-progress { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1.1rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr)) auto; gap: 0.9rem; align-items: flex-end; }
  .sd-inline-grid-cgpa > *, .sd-inline-grid-progress > * { min-width: 0; }
  .sd-tabs-bar { display: flex; gap: 0.35rem; border-bottom: 2px solid #e2e8f0; margin-bottom: 1.75rem; flex-wrap: wrap; }
  .sd-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
  .sd-form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; }
  .sd-form-grid-edit { display: grid; grid-template-columns: 1fr 2fr; gap: 0.75rem; }
  .sd-form-grid-2 > *, .sd-form-grid-3 > *, .sd-form-grid-edit > * { min-width: 0; }
  .sd-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.68); z-index: 9999; justify-content: center; align-items: center; padding: 1rem; overflow-y: auto; }
  .sd-modal-card { background: #ffffff; border-radius: 12px; max-width: 560px; width: 100%; padding: 1.75rem; box-shadow: 0 25px 50px rgba(0,0,0,0.25); position: relative; max-height: 92vh; max-height: 92dvh; overflow-y: auto; }
  @media (max-width: 991px) {
    .sd-profile-grid { grid-template-columns: 1fr; text-align: left; }
    .sd-two-col, .sd-two-col-eq { grid-template-columns: 1fr; }
    .sd-cgpa-box { border-left: none !important; border-top: 1px solid #e2e8f0; padding-left: 0 !important; padding-top: 1rem; text-align: left !important; }
  }
  @media screen and (max-width: 767px) {
    .sd-action-panel { padding: 1rem; }
    .sd-profile-grid { padding: 1rem; gap: 1rem; }
    .sd-inline-grid-cgpa,
    .sd-inline-grid-progress { grid-template-columns: 1fr; }
    .sd-inline-grid-cgpa button[type="submit"],
    .sd-inline-grid-progress button[type="submit"] { width: 100%; justify-content: center; }
    .sd-tabs-bar { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .sd-tabs-bar .tab-btn { flex: 0 0 auto; white-space: nowrap; min-height: 44px; padding: 0.6rem 0.9rem !important; font-size: 0.85rem !important; }
    .sd-modal-card { padding: 1.15rem; }
  }
  @media screen and (max-width: 540px) {
    .sd-form-grid-2,
    .sd-form-grid-3,
    .sd-form-grid-edit { grid-template-columns: 1fr; }
  }
</style>

<!-- Header Breadcrumb & Primary Navigation Actions -->
<div class="sd-header-bar">
  <div>
    <a href="<?= APP_URL ?>/admin/students.php" style="color: var(--rmu-primary); font-size: 0.88rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem; margin-bottom: 0.45rem;">
      <i class="fas fa-arrow-left"></i> Back to Student Directory
    </a>
    <h2 style="font-size: 1.55rem; font-weight: 800; color: #0f172a; margin: 0 0 0.4rem;">
      Student Profile: <?= e($student['full_name']) ?>
      <span style="font-family: monospace; color: var(--rmu-primary); font-size: 1.15rem;">(<?= e($student['student_id']) ?>)</span>
    </h2>
    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
      <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 14px; font-size: 0.78rem; font-weight: 800; background: <?= e($accBadge['bg']) ?>; color: <?= e($accBadge['color']) ?>; border: 1px solid <?= e($accBadge['border']) ?>;">
        <i class="fas <?= e($accBadge['icon']) ?>"></i> Account: <?= e($accBadge['label']) ?>
      </span>
      <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 14px; font-size: 0.78rem; font-weight: 800; background: <?= e($resBadge['bg']) ?>; color: <?= e($resBadge['color']) ?>; border: 1px solid <?= e($resBadge['border']) ?>;">
        <i class="fas <?= e($resBadge['icon']) ?>"></i> <?= e($resBadge['label']) ?>
      </span>
      <span style="font-size: 0.78rem; color: #64748b;">
        &bull; Published Subjects: <strong><?= $publishedResCount ?></strong> / <strong><?= $totalResCount ?></strong>
      </span>
      <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 14px; font-size: 0.78rem; font-weight: 800; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
        <i class="fas fa-graduation-cap"></i> <?= e($progStruct['degree_type']) ?> &bull; <?= e($progStruct['duration_label']) ?>
      </span>
    </div>
  </div>

  <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
    <a href="<?= APP_URL ?>/admin/results-manager.php?student_id=<?= $student['id'] ?>" class="btn-primary" style="padding: 0.55rem 1.05rem; font-size: 0.85rem; background: #0f766e;" title="Manage Semesters, Grades &amp; Results">
      <i class="fas fa-chart-line"></i> <?= $totalSemesters ?>-Semester Console
    </a>
    <?php if (!$isDeleted && !$isDeactivated && $student['status'] !== 'Suspended'): ?>
      <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $student['id'] ?>" method="POST" target="_blank" style="display:inline;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login_as_student">
        <button type="submit" class="btn-primary" style="padding: 0.55rem 1.05rem; font-size: 0.85rem; background: #059669; border: none; cursor: pointer;" title="Directly log into student dashboard in new tab">
          <i class="fas fa-sign-in-alt"></i> Login As Student
        </button>
      </form>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/admin/students.php?edit=<?= $student['id'] ?>" class="btn-primary" style="padding: 0.55rem 1.05rem; font-size: 0.85rem;">
      <i class="fas fa-sliders-h"></i> Edit / CGPA Generator
    </a>
    <a href="<?= APP_URL ?>/student/login.php?student_id=<?= urlencode($student['student_id']) ?>" target="_blank" class="btn-primary" style="padding: 0.55rem 1.05rem; font-size: 0.85rem; background: #0284c7;">
      <i class="fas fa-external-link-alt"></i> Portal Login
    </a>
  </div>
</div>

<?php if ($isDeleted): ?>
  <div style="background: #fef2f2; border: 2px solid #f87171; border-radius: 10px; padding: 1.15rem 1.4rem; margin-bottom: 1.5rem; color: #7f1d1d;">
    <div style="font-weight: 800; font-size: 1rem; margin-bottom: 0.3rem;">
      <i class="fas fa-user-times"></i> Account Permanently Deleted (Data Retention Mode)
    </div>
    <p style="margin: 0; font-size: 0.86rem; line-height: 1.5;">
      This student's login credentials, admission portal password, and all active sessions were permanently revoked on <strong><?= e($student['deleted_at'] ?? 'N/A') ?></strong>.
      Academic &amp; audit records remain safely preserved under university data retention policy
      <?php if ($retentionArchive): ?>
        (Archive <strong>#<?= (int)$retentionArchive['id'] ?></strong> &bull; <?= (int)$retentionArchive['results_count'] ?> academic records retained).
      <?php endif; ?>
      <?php if (!empty($student['deletion_reason'])): ?>
        <br><strong>Reason:</strong> <?= e($student['deletion_reason']) ?>
      <?php endif; ?>
    </p>
  </div>
<?php elseif ($isDeactivated): ?>
  <div style="background: #fffbeb; border: 2px solid #f59e0b; border-radius: 10px; padding: 1.15rem 1.4rem; margin-bottom: 1.5rem; color: #78350f;">
    <div style="font-weight: 800; font-size: 1rem; margin-bottom: 0.3rem;">
      <i class="fas fa-user-slash"></i> Account Deactivated — Student Login Blocked &amp; Records Preserved
    </div>
    <p style="margin: 0; font-size: 0.86rem;">
      Deactivated on <strong><?= e($student['deactivated_at'] ?? 'N/A') ?></strong>. Existing sessions were invalidated immediately.
      <?php if (!empty($student['deactivation_reason'])): ?>
        <strong>Reason:</strong> <?= e($student['deactivation_reason']) ?>
      <?php endif; ?>
    </p>
  </div>
<?php endif; ?>

<?php if (!$isDeleted && empty($resultGate['allowed'])): ?>
  <div style="background: #fff1f2; border: 2px solid #fb7185; border-radius: 10px; padding: 1.15rem 1.4rem; margin-bottom: 1.5rem; color: #881337; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
      <div style="font-weight: 800; font-size: 1rem; margin-bottom: 0.25rem;">
        <i class="fas fa-lock"></i> Result Management Locked — Pending Application Acceptance
      </div>
      <p style="margin: 0; font-size: 0.86rem; line-height: 1.45;">
        <?= e($resultGate['reason']) ?> Only applications accepted by an administrator can proceed to Result Management and automatic CGPA generation.
      </p>
    </div>
    <a href="<?= APP_URL ?>/admin/admissions.php?status=Pending" class="btn-primary" style="background: #be123c; padding: 0.5rem 1rem; font-size: 0.84rem; white-space: nowrap; text-decoration: none;">
      <i class="fas fa-user-check"></i> Open Pending Applications
    </a>
  </div>
<?php endif; ?>

<?php if (!$isDeleted && $isResultSuspended): ?>
  <div style="background: #fff7ed; border: 2px solid #fb923c; border-radius: 10px; padding: 1.05rem 1.4rem; margin-bottom: 1.5rem; color: #9a3412; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
      <div style="font-weight: 800; font-size: 0.98rem; margin-bottom: 0.2rem;">
        <i class="fas fa-user-lock"></i> Result Access Suspended — Portal Login &amp; Profile Remain Active
      </div>
      <p style="margin: 0; font-size: 0.85rem;">
        Result sheets, CGPA, and transcripts are concealed from the student portal.
        <?php if (!empty($student['result_suspended_reason'])): ?>
          <strong>Reason:</strong> <?= e($student['result_suspended_reason']) ?>
        <?php endif; ?>
      </p>
    </div>
    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="margin:0;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="restore_result_access">
      <button type="submit" class="sd-btn" style="background: #15803d;">
        <i class="fas fa-unlock"></i> Restore Result Access
      </button>
    </form>
  </div>
<?php endif; ?>

<!-- Complete Student Identification & Profile Overview Card -->
<div class="sd-profile-grid">
  <div style="width: 86px; height: 86px; border-radius: 50%; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; overflow: hidden; border: 3px solid #bfdbfe;">
    <?php if (!empty($student['avatar']) && file_exists(BASE_PATH . '/storage/uploads/students/' . $student['avatar'])): ?>
      <img src="<?= get_student_avatar_url($student) ?>" alt="<?= e($student['full_name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
    <?php else: ?>
      <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
    <?php endif; ?>
  </div>

  <div>
    <h3 style="font-size: 1.3rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem;"><?= e($student['full_name']) ?></h3>
    <div style="color: #475569; font-size: 0.88rem;">
      <strong>Degree Program:</strong> <?= e($student['program_title'] ?? 'N/A') ?>
      <?php if (!empty($student['degree_level'])): ?>
        <span style="background: #eff6ff; color: #1d4ed8; font-size: 0.72rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; margin-left: 4px;"><?= e($student['degree_level']) ?></span>
      <?php endif; ?>
      &bull; <strong>Department:</strong> <?= e($student['dept_name'] ?? 'N/A') ?>
    </div>

    <div class="sd-info-chips">
      <div class="sd-chip">
        <span class="sd-chip-lbl">Student ID</span>
        <span class="sd-chip-val" style="font-family: monospace; color: #0284c7;"><?= e($student['student_id']) ?></span>
      </div>
      <div class="sd-chip">
        <span class="sd-chip-lbl">Roll Number</span>
        <span class="sd-chip-val" style="font-family: monospace;"><?= e($student['roll_number'] ?: 'N/A') ?></span>
      </div>
      <div class="sd-chip">
        <span class="sd-chip-lbl">Application Number</span>
        <span class="sd-chip-val" style="font-family: monospace;"><?= e($student['application_number'] ?: ($admissionRecord['application_ref'] ?? 'N/A')) ?></span>
      </div>
      <div class="sd-chip">
        <span class="sd-chip-lbl">Portal Username</span>
        <span class="sd-chip-val" style="font-family: monospace;"><?= e($student['username'] ?: $student['student_id']) ?></span>
      </div>
      <div class="sd-chip">
        <span class="sd-chip-lbl">Mobile Number</span>
        <span class="sd-chip-val"><?= e($student['phone']) ?></span>
      </div>
      <div class="sd-chip">
        <span class="sd-chip-lbl">Semester Information</span>
        <span class="sd-chip-val"><?= e($student['current_year']) ?> &bull; <?= e($student['current_semester']) ?></span>
      </div>
      <div class="sd-chip">
        <span class="sd-chip-lbl">Academic Session</span>
        <span class="sd-chip-val"><?= e($defaultSessionVal) ?></span>
      </div>
      <div class="sd-chip">
        <span class="sd-chip-lbl">Course Duration &amp; Semesters</span>
        <span class="sd-chip-val"><?= e($progStruct['degree_type']) ?> &bull; <?= e($progStruct['duration_label']) ?></span>
      </div>
      <div class="sd-chip">
        <span class="sd-chip-lbl">Passing Year (Student Selection)</span>
        <span class="sd-chip-val"><?= e($selectedPassingYear ?: 'Not Provided') ?></span>
      </div>
      <div class="sd-chip">
        <span class="sd-chip-lbl">Expected Passing Year (Advisory)</span>
        <span class="sd-chip-val">
          <?php if (!empty($oversightEval['expected_year'])): ?>
            <strong style="color:#1e40af;"><?= (int)$oversightEval['expected_year'] ?></strong>
            <small style="color:#64748b; display:block; font-size:0.7rem;"><?= e($oversightEval['formula']) ?></small>
          <?php else: ?>
            N/A
          <?php endif; ?>
        </span>
      </div>
      <div class="sd-chip">
        <span class="sd-chip-lbl">Session vs Passing Oversight</span>
        <span class="sd-chip-val">
          <?php
            $ovBg = '#e2e8f0'; $ovCol = '#475569';
            if ($oversightEval['status_key'] === 'match') { $ovBg = '#dcfce7'; $ovCol = '#15803d'; }
            elseif ($oversightEval['status_key'] === 'differs') { $ovBg = '#fef3c7'; $ovCol = '#b45309'; }
            elseif ($oversightEval['status_key'] === 'approved_historical') { $ovBg = '#ede9fe'; $ovCol = '#6d28d9'; }
          ?>
          <span style="display:inline-block; background:<?= $ovBg ?>; color:<?= $ovCol ?>; padding:1px 7px; border-radius:99px; font-size:0.74rem; font-weight:800;">
            <?= e($oversightEval['status_label']) ?>
          </span>
          <?php if (!empty($student['historical_approval_note'])): ?>
            <small style="display:block; color:#6d28d9; font-size:0.7rem; margin-top:2px;"><?= e($student['historical_approval_note']) ?></small>
          <?php endif; ?>
        </span>
      </div>
      <div class="sd-chip">
        <span class="sd-chip-lbl">Result Publication Date</span>
        <span class="sd-chip-val"><?= !empty($student['result_publication_date']) ? e(format_date($student['result_publication_date'])) : 'Not Published' ?></span>
      </div>
      <div class="sd-chip">
        <span class="sd-chip-lbl">Email Address</span>
        <span class="sd-chip-val" style="word-break: break-all;"><?= e($student['email']) ?></span>
      </div>
    </div>
  </div>

  <div class="sd-cgpa-box" style="text-align: right; border-left: 1px solid #e2e8f0; padding-left: 1.75rem;">
    <div style="font-size: 0.78rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Current CGPA</div>
    <div style="font-size: 2.2rem; font-weight: 900; color: #047857; line-height: 1.1;"><?= number_format((float)$student['cgpa'], 2) ?></div>
    <div style="font-size: 0.82rem; color: #64748b; margin-top: 3px;">
      <?= (int)$student['credits_completed'] ?> / <?= (int)($student['total_credits_required'] ?: $progStruct['total_credits']) ?> Credits
    </div>
    <div style="margin-top: 0.5rem; font-size: 0.78rem; font-weight: 700; color: #0f2c59;">
      <?= count($groupedAdminResults) ?> / <?= $totalSemesters ?> Semester(s) Recorded
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- STUDENT LOGIN CREDENTIALS (ADMIN-ONLY SECURE CREDENTIAL MANAGEMENT)       -->
<!-- ========================================================================= -->
<?php render_student_login_credentials_section($student, $isManager, APP_URL . '/admin/student-detail.php?id=' . $studentId, $returnTab); ?>

<!-- ========================================================================= -->
<!-- INDIVIDUAL ACTION PANEL: RESULT CONTROL & ACCOUNT MANAGEMENT              -->
<!-- ========================================================================= -->
<div class="sd-action-panel">
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
    <h3 style="margin: 0; font-size: 1.08rem; font-weight: 800; color: #0f2c59;">
      <i class="fas fa-shield-alt" style="color: var(--rmu-primary);"></i> Individual Student Action Panel — Result &amp; Account Control
    </h3>
    <span style="font-size: 0.78rem; color: #64748b;">
      All actions require CSRF validation and are logged permanently with administrator ID &amp; timestamp.
    </span>
  </div>

  <div class="sd-action-groups">
    <!-- 1. Academic Result Operations -->
    <div class="sd-action-box">
      <div style="font-size: 0.76rem; font-weight: 800; text-transform: uppercase; color: #1e3a8a; margin-bottom: 0.55rem;">
        <i class="fas fa-edit"></i> Result Editing &amp; Upload
      </div>
      <div class="sd-btn-row">
        <button type="button" onclick="openTab('gradesTab'); document.getElementById('uploadResultFormCard').scrollIntoView({behavior:'smooth'});" class="sd-btn" style="background: #1e73be;" <?= empty($resultGate['allowed']) ? 'disabled' : '' ?>>
          <i class="fas fa-upload"></i> Upload Result
        </button>
        <button type="button" onclick="openTab('gradesTab'); document.getElementById('gradesTableCard').scrollIntoView({behavior:'smooth'});" class="sd-btn" style="background: #0284c7;" <?= empty($resultGate['allowed']) ? 'disabled' : '' ?>>
          <i class="fas fa-edit"></i> Edit Result
        </button>
        <button type="button" onclick="document.getElementById('cgpaGeneratorPanel').scrollIntoView({behavior:'smooth'});" class="sd-btn" style="background: #7c3aed;" <?= empty($resultGate['allowed']) ? 'disabled' : '' ?>>
          <i class="fas fa-magic"></i> Generate CGPA Result
        </button>
        <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $studentId ?>&semester=all" target="_blank" class="sd-btn" style="background: #475569;">
          <i class="fas fa-file-pdf"></i> Full Transcript
        </a>
        <?php if ($totalResCount > 0 && !$isDeleted): ?>
          <button type="button" onclick="openDeleteAllResultsModal()" class="sd-btn" style="background: #9f1239;">
            <i class="fas fa-eraser"></i> Delete All Results (Keep Account)
          </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- 2. Publication & Visibility Control -->
    <div class="sd-action-box">
      <div style="font-size: 0.76rem; font-weight: 800; text-transform: uppercase; color: #047857; margin-bottom: 0.55rem;">
        <i class="fas fa-eye"></i> Publication &amp; Student Visibility Control
      </div>
      <div class="sd-btn-row">
        <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="publish_all_results">
          <button type="submit" class="sd-btn" style="background: #15803d;" <?= ($totalResCount === 0 || empty($resultGate['allowed'])) ? 'disabled title="Requires results and accepted status"' : '' ?>>
            <i class="fas fa-check-circle"></i> Publish Result
          </button>
        </form>

        <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display:inline;" onsubmit="return confirm('Unpublish all results back to Draft? Student will not see unpublished draft results.');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="unpublish_all_results">
          <button type="submit" class="sd-btn" style="background: #d97706;" <?= ($publishedResCount === 0 || empty($resultGate['allowed'])) ? 'disabled' : '' ?>>
            <i class="fas fa-file-alt"></i> Unpublish Result
          </button>
        </form>

        <?php if ($resultsVisible): ?>
          <button type="button" onclick="openHideResultsModal()" class="sd-btn" style="background: #b91c1c;">
            <i class="fas fa-eye-slash"></i> Hide Result
          </button>
        <?php else: ?>
          <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="show_results">
            <button type="submit" class="sd-btn" style="background: #0d9488;">
              <i class="fas fa-eye"></i> Show Result
            </button>
          </form>
        <?php endif; ?>

        <?php if (!$isResultSuspended): ?>
          <button type="button" onclick="openSuspendResultsModal()" class="sd-btn" style="background: #c2410c;">
            <i class="fas fa-user-lock"></i> Suspend Result Access
          </button>
        <?php else: ?>
          <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="restore_result_access">
            <button type="submit" class="sd-btn" style="background: #0f766e;">
              <i class="fas fa-unlock"></i> Restore Result Access
            </button>
          </form>
        <?php endif; ?>

        <?php if (!empty($badges['estimated_results']) && $badges['estimated_results'] > 0 && !empty($resultGate['allowed'])): ?>
          <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display:inline;" onsubmit="return confirm('Verify all estimated subjects as Official University Examination Records?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify_all_results_official">
            <button type="submit" class="sd-btn" style="background: #1d4ed8;">
              <i class="fas fa-stamp"></i> Verify All Official
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- 3. Dedicated Account Management Controls -->
    <div class="sd-action-box">
      <div style="font-size: 0.76rem; font-weight: 800; text-transform: uppercase; color: #991b1b; margin-bottom: 0.55rem;">
        <i class="fas fa-user-shield"></i> Account Management Controls
      </div>
      <div class="sd-btn-row">
        <?php if (!$isDeleted): ?>
          <?php if ($isDeactivated || $student['status'] === 'Suspended'): ?>
            <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display:inline;" onsubmit="return confirm('Reactivate account for <?= e($student['full_name']) ?>? Student will regain portal access immediately.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="activate_account">
              <button type="submit" class="sd-btn" style="background: #16a34a;">
                <i class="fas fa-user-check"></i> Reactivate Account
              </button>
            </form>
          <?php else: ?>
            <button type="button" onclick="openDeactivateModal()" class="sd-btn" style="background: #d97706;">
              <i class="fas fa-user-slash"></i> Deactivate Account
            </button>
          <?php endif; ?>

          <button type="button" onclick="openDeleteAccountModal()" class="sd-btn" style="background: #dc2626;">
            <i class="fas fa-trash-alt"></i> Delete Account
          </button>
        <?php else: ?>
          <span style="font-size: 0.8rem; font-weight: 700; color: #b91c1c; padding: 0.4rem 0;">
            <i class="fas fa-lock"></i> Credentials permanently revoked &amp; archived.
          </span>
        <?php endif; ?>
        <button type="button" onclick="openOfficialAcademicEditModal()" class="sd-btn" style="background: #0f2c59;" <?= $isDeleted ? 'disabled' : '' ?>>
          <i class="fas fa-pen-fancy"></i> Official Academic Edit
        </button>
        <?php if (empty($student['historical_record_approved']) && !$isDeleted): ?>
          <button type="button" onclick="openApproveHistoricalModal()" class="sd-btn" style="background: #6d28d9;">
            <i class="fas fa-history"></i> Approve Historical
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- SIMPLE AUTOMATIC CGPA RESULT GENERATOR PANEL                              -->
<!-- ========================================================================= -->
<div id="cgpaGeneratorPanel" style="background: linear-gradient(135deg, #f5f3ff 0%, #eff6ff 100%); border: 2px solid #c4b5fd; border-radius: 12px; padding: 1.35rem 1.5rem; margin-bottom: 1.75rem; box-shadow: 0 3px 10px rgba(109,40,217,0.06);">
  <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
    <div>
      <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
        <h3 style="margin: 0; font-size: 1.12rem; font-weight: 800; color: #3b0764;">
          <i class="fas fa-magic" style="color: #7c3aed;"></i> Simple Automatic CGPA Result Generator
        </h3>
        <span style="background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; padding: 2px 9px; border-radius: 12px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase;">
          ESTIMATED / GENERATED
        </span>
      </div>
      <p style="margin: 0.25rem 0 0; font-size: 0.84rem; color: #475569;">
        Program Structure: <strong><?= e($progStruct['degree_type']) ?></strong> (<?= e($progStruct['duration_label']) ?> &bull; <strong><?= $totalSemesters ?> Semesters</strong>). Enter only the <strong>Final CGPA</strong>, <strong>Academic Session</strong>, and <strong>Publication Date</strong> to distribute credit-weighted semester GPAs &amp; subject grades automatically.
      </p>
    </div>
    <span style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 4px 10px; border-radius: 6px; font-size: 0.74rem; font-weight: 800;">
      ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT
    </span>
  </div>

  <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" class="sd-inline-grid-cgpa">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="generate_cgpa_results">

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Final CGPA (2.00 – 4.00) *</label>
      <input type="number" step="0.01" min="2.00" max="4.00" name="final_cgpa" value="<?= ((float)$student['cgpa'] >= 2.00) ? number_format((float)$student['cgpa'], 2, '.', '') : '3.50' ?>" class="form-control" required style="font-size: 0.9rem; font-weight: 700;">
    </div>

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Academic Session *</label>
      <input type="text" name="academic_session" list="sdSessionsDatalist" value="<?= e($defaultSessionVal) ?>" class="form-control" required placeholder="e.g. Fall 2026" style="font-size: 0.88rem;">
      <datalist id="sdSessionsDatalist">
        <?php foreach ($allSessionsDatalist as $asItem): ?>
          <option value="<?= e($asItem['session_name']) ?>">
        <?php endforeach; ?>
      </datalist>
      <datalist id="sdPassingYearsDatalist">
        <?php foreach ($allYearsDatalist as $pyItem): ?>
          <option value="<?= (int)$pyItem['year_value'] ?>">
        <?php endforeach; ?>
      </datalist>
    </div>

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Result Publication Date *</label>
      <input type="date" name="publication_date" value="<?= e($defaultPubDateVal) ?>" class="form-control" required style="font-size: 0.86rem;">
    </div>

    <?php if (stripos($progStruct['degree_type'], 'B.Ed') !== false || stripos($progStruct['degree_type'], 'Master') !== false || $totalSemesters !== 8): ?>
      <div class="form-group" style="margin-bottom: 0;">
        <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Configured Semesters</label>
        <select name="total_semesters" class="form-select" style="font-size: 0.86rem;">
          <?php if (stripos($progStruct['degree_type'], 'B.Ed') !== false): ?>
            <option value="2" <?= ($totalSemesters === 2) ? 'selected' : '' ?>>2 Semesters (1 Year)</option>
            <option value="3" <?= ($totalSemesters === 3) ? 'selected' : '' ?>>3 Semesters (Extended)</option>
            <?php if (!in_array($totalSemesters, [2, 3], true)): ?>
              <option value="<?= $totalSemesters ?>" selected><?= $totalSemesters ?> Semesters (Custom)</option>
            <?php endif; ?>
          <?php elseif (stripos($progStruct['degree_type'], 'Master') !== false): ?>
            <option value="2" <?= ($totalSemesters === 2) ? 'selected' : '' ?>>2 Semesters (1 Year)</option>
            <option value="3" <?= ($totalSemesters === 3) ? 'selected' : '' ?>>3 Semesters (1.5 Years)</option>
            <option value="4" <?= ($totalSemesters === 4) ? 'selected' : '' ?>>4 Semesters (2 Years)</option>
            <?php if (!in_array($totalSemesters, [2, 3, 4], true)): ?>
              <option value="<?= $totalSemesters ?>" selected><?= $totalSemesters ?> Semesters (Custom)</option>
            <?php endif; ?>
          <?php else: ?>
            <?php for ($sOpt = 1; $sOpt <= 12; $sOpt++): ?>
              <option value="<?= $sOpt ?>" <?= ($totalSemesters === $sOpt) ? 'selected' : '' ?>><?= $sOpt ?> Semesters</option>
            <?php endfor; ?>
          <?php endif; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.78rem; font-weight: 700;">Visibility Mode</label>
      <select name="publish_mode" class="form-select" style="font-size: 0.86rem;">
        <option value="1">Generate &amp; Publish to Portal</option>
        <option value="0">Generate as Draft Only</option>
      </select>
    </div>

    <button type="submit" class="sd-btn" style="background: #7c3aed; padding: 0.68rem 1.2rem; font-size: 0.88rem; white-space: nowrap;" <?= (empty($resultGate['allowed']) || $isDeleted) ? 'disabled title="Application acceptance required"' : '' ?>>
      <i class="fas fa-bolt"></i> Generate Result
    </button>
  </form>
</div>

<!-- Semester Progress & Graduation Master Control Panel -->
<?php
  $maxYearOpts = max(1, (int)ceil((float)($progStruct['duration_years'] ?? 4.0)));
  $yearLabelsMap = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year', 6 => '6th Year'];
  $gradBtnLabel = ($totalSemesters === 8) ? 'Graduate All 8 Semesters' : "Graduate All {$totalSemesters} Semesters";
  $lastSemOrd = $ordSemLabels[$totalSemesters] ?? get_semester_ordinal_title($totalSemesters);
  $completedSemVal = $lastSemOrd . ' (Completed)';
?>
<div style="background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%); border: 2px solid #bfdbfe; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.15rem; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h3 style="margin: 0 0 0.25rem; font-size: 1.15rem; font-weight: 700; color: #1e3a8a;">
        <i class="fas fa-tasks" style="color: var(--rmu-primary);"></i> Semester Progress &amp; Graduation Control
      </h3>
      <span style="font-size: 0.85rem; color: #475569;">
        Promote academic year/semester manually or complete all <?= $totalSemesters ?> semesters for graduation.
      </span>
    </div>

    <div>
      <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $student['id'] ?>" method="POST" onsubmit="return confirm('Complete all <?= $totalSemesters ?> semesters and mark student Graduated?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="complete_all_semesters">
        <input type="hidden" name="final_cgpa" value="<?= ((float)$student['cgpa'] >= 2.50) ? number_format((float)$student['cgpa'], 2, '.', '') : '3.85' ?>">
        <button type="submit" class="btn-primary" style="padding: 0.65rem 1.25rem; font-size: 0.88rem; background: #15803d; font-weight: 700; border: none; cursor: pointer;" <?= ($isDeleted || empty($resultGate['allowed'])) ? 'disabled' : '' ?>>
          <i class="fas fa-graduation-cap"></i> <?= e($gradBtnLabel) ?>
        </button>
      </form>
    </div>
  </div>

  <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $student['id'] ?>" method="POST" class="sd-inline-grid-progress">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_semester_progress">

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Academic Year</label>
      <select name="current_year" class="form-select" style="font-size: 0.86rem;">
        <?php for ($y = 1; $y <= $maxYearOpts; $y++): $yLbl = $yearLabelsMap[$y] ?? "{$y}th Year"; ?>
          <option value="<?= e($yLbl) ?>" <?= ($student['current_year'] === $yLbl) ? 'selected' : '' ?>><?= e($yLbl) ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Current Semester</label>
      <select name="current_semester" class="form-select" style="font-size: 0.86rem;">
        <?php for ($sn = 1; $sn <= $totalSemesters; $sn++): $snLbl = $ordSemLabels[$sn] ?? "Semester {$sn}"; ?>
          <option value="<?= e($snLbl) ?>" <?= ($student['status'] !== 'Graduated' && stripos($student['current_semester'], strtok($snLbl, ' ')) !== false) ? 'selected' : '' ?>>
            <?= e($snLbl) ?><?= ($sn === $totalSemesters) ? ' (Final)' : '' ?>
          </option>
        <?php endfor; ?>
        <option value="<?= e($completedSemVal) ?>" <?= ($student['status'] === 'Graduated') ? 'selected' : '' ?>>Completed (All <?= $totalSemesters ?> Semesters)</option>
      </select>
    </div>

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">CGPA</label>
      <input type="number" step="0.01" min="0" max="4.00" name="cgpa" value="<?= e($student['cgpa']) ?>" class="form-control" style="font-size: 0.86rem;">
    </div>

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Credits Completed</label>
      <input type="number" min="0" max="160" name="credits_completed" value="<?= (int)$student['credits_completed'] ?>" class="form-control" style="font-size: 0.86rem;">
    </div>

    <div class="form-group" style="margin-bottom: 0;">
      <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Account Status</label>
      <select name="status" class="form-select" style="font-size: 0.86rem;" <?= $isDeleted ? 'disabled' : '' ?>>
        <option value="Active" <?= ($student['status'] === 'Active') ? 'selected' : '' ?>>Active</option>
        <option value="Graduated" <?= ($student['status'] === 'Graduated') ? 'selected' : '' ?>>Graduated</option>
        <option value="Suspended" <?= ($student['status'] === 'Suspended') ? 'selected' : '' ?>>Suspended</option>
        <option value="Deactivated" <?= ($student['status'] === 'Deactivated') ? 'selected' : '' ?>>Deactivated</option>
      </select>
    </div>

    <button type="submit" class="btn-primary" style="padding: 0.65rem 1.15rem; font-size: 0.86rem; white-space: nowrap;" <?= $isDeleted ? 'disabled' : '' ?>>
      <i class="fas fa-save"></i> Update Progress
    </button>
  </form>
</div>

<!-- Tabs Navigation -->
<div class="sd-tabs-bar">
  <button onclick="openTab('gradesTab')" class="tab-btn active" id="btn-gradesTab" style="padding: 0.75rem 1.25rem; border: none; background: none; font-weight: 700; font-size: 0.92rem; color: var(--rmu-primary); border-bottom: 3px solid var(--rmu-primary); cursor: pointer;">
    <i class="fas fa-chart-line"></i> Academic Results (<?= count($results) ?>)
  </button>
  <button onclick="openTab('coursesTab')" class="tab-btn" id="btn-coursesTab" style="padding: 0.75rem 1.25rem; border: none; background: none; font-weight: 600; font-size: 0.92rem; color: #64748b; cursor: pointer;">
    <i class="fas fa-book-reader"></i> Course Enrollments (<?= count($courses) ?>)
  </button>
  <button onclick="openTab('feesTab')" class="tab-btn" id="btn-feesTab" style="padding: 0.75rem 1.25rem; border: none; background: none; font-weight: 600; font-size: 0.92rem; color: #64748b; cursor: pointer;">
    <i class="fas fa-file-invoice-dollar"></i> Tuition Fees (<?= count($fees) ?>)
  </button>
  <button onclick="openTab('admissionTab')" class="tab-btn" id="btn-admissionTab" style="padding: 0.75rem 1.25rem; border: none; background: none; font-weight: 600; font-size: 0.92rem; color: #64748b; cursor: pointer;">
    <i class="fas fa-id-card-alt"></i> Personal &amp; Admission Dossier
  </button>
  <button onclick="openTab('auditTab')" class="tab-btn" id="btn-auditTab" style="padding: 0.75rem 1.25rem; border: none; background: none; font-weight: 600; font-size: 0.92rem; color: #64748b; cursor: pointer;">
    <i class="fas fa-history"></i> Admin Activity Log (<?= count($studentAuditLogs) + count($modHistoryLogs) + count($academicEditLogs) ?>)
  </button>
</div>

<!-- ========================================================================= -->
<!-- TAB 1 (DEFAULT): ACADEMIC RESULTS & SEMESTER VISIBILITY                   -->
<!-- ========================================================================= -->
<div id="gradesTab" class="tab-content" style="display: block;">
  <div class="sd-two-col">
    <!-- Results Table -->
    <div id="gradesTableCard" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="padding: 1.15rem 1.4rem; border-bottom: 1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
        <div>
          <h4 style="margin: 0 0 0.2rem; font-size: 1.08rem; color: #0f172a;">Complete Semester-Wise Academic Results</h4>
          <small style="color:#64748b;">
            Student Portal View Status:
            <?php if (!$resultsVisible): ?>
              <strong style="color:#b91c1c;"><i class="fas fa-eye-slash"></i> Result Hidden (Protected from student view)</strong>
            <?php elseif ($publishedResCount > 0): ?>
              <strong style="color:#15803d;"><i class="fas fa-check-circle"></i> Result Published (<?= $publishedResCount ?> live subject records)</strong>
            <?php else: ?>
              <strong style="color:#b45309;"><i class="fas fa-clock"></i> Draft / Pending Publication</strong>
            <?php endif; ?>
          </small>
        </div>
        <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
          <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $studentId ?>&semester=all&download=pdf" class="btn-primary" style="padding:0.4rem 0.85rem; font-size:0.82rem; background:#16a34a; text-decoration:none;">
            <i class="fas fa-file-pdf"></i> Transcript PDF
          </a>
          <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $studentId ?>&semester=all" target="_blank" class="btn-primary" style="padding:0.4rem 0.85rem; font-size:0.82rem; background:#475569; text-decoration:none;">
            <i class="fas fa-external-link-alt"></i> View Transcript
          </a>
        </div>
      </div>

      <div style="padding: 1rem 1.25rem;">
        <?php if (!empty($semesterSchedule)): ?>
          <!-- Dynamic N-Semester GPA Progression & Quick Editor -->
          <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.9rem 1rem; margin-bottom: 1.4rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.7rem; flex-wrap: wrap; gap: 0.5rem;">
              <span style="font-size: 0.86rem; font-weight: 800; color: #0f2c59;">
                <i class="fas fa-layer-group" style="color: #7c3aed;"></i> Semester-Wise GPA Breakdown &amp; Quick Editor (<?= $totalSemesters ?> Semesters)
              </span>
              <span style="font-size: 0.74rem; color: #64748b;">
                Adjust any single semester's GPA without recreating other semesters
              </span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.6rem;">
              <?php foreach ($semesterSchedule as $sNum => $sch): ?>
                <div style="background: #ffffff; border: 1px solid <?= ($sch['completion_status'] === 'Completed') ? '#bfdbfe' : '#e2e8f0' ?>; border-radius: 6px; padding: 0.55rem 0.65rem;">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                    <strong style="font-size: 0.78rem; color: #0f2c59;">Sem <?= (int)$sNum ?></strong>
                    <span style="font-size: 0.66rem; font-weight: 800; padding: 1px 5px; border-radius: 4px; background: <?= ($sch['completion_status'] === 'Completed') ? '#dcfce7' : '#f1f5f9' ?>; color: <?= ($sch['completion_status'] === 'Completed') ? '#15803d' : '#64748b' ?>;">
                      <?= e($sch['completion_status']) ?>
                    </span>
                  </div>
                  <div style="font-size: 0.95rem; font-weight: 800; color: <?= ($sch['sgpa'] > 0) ? '#1d4ed8' : '#94a3b8' ?>; margin-bottom: 4px;">
                    <?= ($sch['sgpa'] > 0) ? number_format((float)$sch['sgpa'], 2) : '—' ?>
                    <?php if (!empty($sch['is_estimated'])): ?>
                      <span style="font-size: 0.62rem; background: #fef3c7; color: #92400e; padding: 1px 4px; border-radius: 3px; font-weight: 800; margin-left: 3px;">ESTIMATED / GENERATED</span>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($resultGate['allowed']) && !$isDeleted): ?>
                    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display: flex; gap: 3px; align-items: center; margin: 0;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_semester_gpa">
                      <input type="hidden" name="semester_number" value="<?= (int)$sNum ?>">
                      <input type="number" step="0.01" min="2.00" max="4.00" name="semester_gpa" value="<?= ($sch['sgpa'] >= 2.00) ? number_format((float)$sch['sgpa'], 2, '.', '') : '3.50' ?>" class="form-control" style="padding: 2px 5px; font-size: 0.76rem; height: 26px; width: 62px;">
                      <button type="submit" style="background: #1e73be; color: #fff; border: none; border-radius: 4px; padding: 3px 7px; font-size: 0.7rem; font-weight: 700; cursor: pointer; height: 26px;" title="Save Semester <?= (int)$sNum ?> GPA">
                        Set
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($groupedAdminResults)): ?>
          <?php foreach ($groupedAdminResults as $semTitle => $cList): ?>
            <?php
              $st = $semesterAdminStats[$semTitle] ?? ['sem_credits' => 0, 'sgpa' => 0, 'cum_credits' => 0, 'cgpa' => 0, 'all_published' => false, 'has_estimated' => false];
            ?>
            <div style="margin-bottom: 1.75rem; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
              <!-- Semester Header with SGPA, CGPA & Publication Controls -->
              <div style="background: linear-gradient(135deg, #1e3a8a 0%, #0f2c59 100%); color: #ffffff; padding: 0.75rem 1.15rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.6rem;">
                <div style="display: flex; align-items: center; gap: 0.55rem; flex-wrap: wrap;">
                  <span style="background: #f59e0b; color: #0f2c59; padding: 2px 8px; border-radius: 4px; font-weight: 900; font-size: 0.84rem;">
                    <?= get_semester_number($semTitle) ?>
                  </span>
                  <strong style="font-size: 0.94rem;"><?= e($semTitle) ?></strong>
                  <?php if ($st['all_published']): ?>
                    <span style="background:#15803d; color:#fff; font-size:0.7rem; font-weight:700; padding:2px 7px; border-radius:4px;">Published</span>
                  <?php else: ?>
                    <span style="background:#d97706; color:#fff; font-size:0.7rem; font-weight:700; padding:2px 7px; border-radius:4px;">Draft / Pending</span>
                  <?php endif; ?>
                  <?php if ($st['has_estimated']): ?>
                    <span style="background:#dc2626; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 7px; border-radius:4px;">ESTIMATED / NOT OFFICIAL</span>
                  <?php endif; ?>
                </div>

                <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; font-size: 0.78rem;">
                  <span style="background: rgba(255,255,255,0.18); padding: 3px 8px; border-radius: 4px;">
                    Cr: <strong><?= number_format((float)$st['sem_credits'], 1) ?></strong>
                  </span>
                  <span style="background: rgba(255,255,255,0.22); padding: 3px 8px; border-radius: 4px;">
                    GPA: <strong><?= number_format((float)$st['sgpa'], 2) ?></strong>
                  </span>
                  <span style="background: #15803d; padding: 3px 8px; border-radius: 4px; font-weight: 700;">
                    CGPA: <?= number_format((float)$st['cgpa'], 2) ?>
                  </span>

                  <?php if ($st['has_estimated']): ?>
                    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display:inline;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="verify_estimated_semester">
                      <input type="hidden" name="semester_name" value="<?= e($semTitle) ?>">
                      <button type="submit" style="background:#2563eb; color:#fff; border:none; padding:3px 8px; border-radius:4px; font-size:0.72rem; font-weight:700; cursor:pointer;">
                        <i class="fas fa-stamp"></i> Verify Official
                      </button>
                    </form>
                  <?php endif; ?>

                  <?php if (!$st['all_published']): ?>
                    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display:inline;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="publish_semester">
                      <input type="hidden" name="semester_name" value="<?= e($semTitle) ?>">
                      <button type="submit" style="background:#22c55e; color:#fff; border:none; padding:3px 8px; border-radius:4px; font-size:0.72rem; font-weight:700; cursor:pointer;">
                        <i class="fas fa-check"></i> Publish
                      </button>
                    </form>
                  <?php else: ?>
                    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display:inline;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="unpublish_semester">
                      <input type="hidden" name="semester_name" value="<?= e($semTitle) ?>">
                      <button type="submit" style="background:rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4); padding:2px 8px; border-radius:4px; font-size:0.72rem; font-weight:700; cursor:pointer;">
                        <i class="fas fa-eye-slash"></i> Draft
                      </button>
                    </form>
                  <?php endif; ?>

                  <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $studentId ?>&semester=<?= urlencode($semTitle) ?>&download=pdf" style="background:#fff; color:#0f2c59; border-radius:4px; padding:3px 8px; font-size:0.72rem; font-weight:700; text-decoration:none;" title="Download <?= e($semTitle) ?> PDF">
                    <i class="fas fa-file-pdf" style="color:#dc2626;"></i> PDF
                  </a>
                </div>
              </div>

              <!-- Subjects Table -->
              <div class="table-responsive">
                <table class="rmu-table" style="box-shadow: none; margin: 0; font-size: 0.85rem;">
                  <thead>
                    <tr>
                      <th>Subject</th>
                      <th style="width: 60px; text-align: center;">Cr</th>
                      <th style="width: 70px; text-align: center;">Marks</th>
                      <th style="width: 65px; text-align: center;">Grade</th>
                      <th style="width: 65px; text-align: center;">GP</th>
                      <th style="width: 85px; text-align: center;">State</th>
                      <th style="text-align: right; width: 105px;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($cList as $res): ?>
                      <?php
                        $cr = (float)$res['credit'];
                        $gp = (float)$res['grade_point'];
                        $obtMarks = isset($res['marks']) && $res['marks'] !== null ? number_format((float)$res['marks'], 1) : '—';
                        $pubState = $res['publish_status'] ?? (!empty($res['is_published']) ? 'Published' : 'Draft');
                      ?>
                      <tr>
                        <td>
                          <strong style="color: var(--rmu-primary); font-family: monospace;"><?= e($res['course_code']) ?></strong>
                          <div style="font-weight: 600; color: #334155; font-size: 0.86rem;"><?= e($res['course_name']) ?></div>
                          <small style="color: #64748b;"><?= e($res['exam_term']) ?></small>
                        </td>
                        <td style="text-align: center; font-weight: 600;"><?= number_format($cr, 1) ?></td>
                        <td style="text-align: center; font-weight: 700; color: #0f172a;"><?= $obtMarks ?></td>
                        <td style="text-align: center;"><span style="font-weight: 800; color: #1e40af;"><?= e($res['letter_grade']) ?></span></td>
                        <td style="text-align: center;"><strong style="color: #047857;"><?= number_format($gp, 2) ?></strong></td>
                        <td style="text-align: center;">
                          <?php if ($pubState === 'Published'): ?>
                            <span style="font-size: 0.72rem; font-weight: 700; background: #dcfce7; color: #15803d; padding: 2px 6px; border-radius: 4px;">Published</span>
                          <?php else: ?>
                            <span style="font-size: 0.72rem; font-weight: 700; background: #fef3c7; color: #b45309; padding: 2px 6px; border-radius: 4px;"><?= e($pubState) ?></span>
                          <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                          <button type="button" onclick="openEditDetailResultModal(<?= htmlspecialchars(json_encode([
                            'id' => (int)$res['id'],
                            'semester' => $res['semester'],
                            'code' => $res['course_code'],
                            'name' => $res['course_name'],
                            'credit' => (float)$res['credit'],
                            'full_marks' => (int)($res['full_marks'] ?? 100),
                            'marks' => $res['marks'] !== null ? (float)$res['marks'] : '',
                            'letter_grade' => $res['letter_grade'],
                            'grade_point' => (float)$res['grade_point'],
                            'exam_term' => $res['exam_term'],
                            'publish_status' => $pubState,
                            'reason' => $res['correction_reason'] ?? ''
                          ]), ENT_QUOTES, 'UTF-8') ?>)" style="border: none; background: #eff6ff; color: #0284c7; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.78rem; margin-right: 3px;" title="Edit Subject Result">
                            <i class="fas fa-edit"></i> Edit
                          </button>

                          <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display: inline;" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Delete result for ' . $res['course_code'] . '?'), ENT_QUOTES, 'UTF-8') ?>);">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_result">
                            <input type="hidden" name="result_id" value="<?= $res['id'] ?>">
                            <button type="submit" style="border: none; background: #fee2e2; color: #ef4444; padding: 4px 7px; border-radius: 4px; cursor: pointer; font-size: 0.78rem;" title="Delete">
                              <i class="fas fa-trash-alt"></i>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                  <tfoot>
                    <tr style="background: #f8fafc; font-weight: 700; font-size: 0.82rem; border-top: 1.5px solid #cbd5e1;">
                      <td style="text-align: right; color: #475569;">Semester Summary:</td>
                      <td style="text-align: center; color: #0f172a;"><?= number_format((float)$st['sem_credits'], 1) ?></td>
                      <td colspan="2" style="text-align: right; color: #1e3a8a;">SGPA: <strong><?= number_format((float)$st['sgpa'], 2) ?></strong></td>
                      <td colspan="3" style="text-align: center; color: #15803d; font-weight: 800;">Cumulative CGPA: <?= number_format((float)$st['cgpa'], 2) ?></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="text-align: center; padding: 2.5rem; color: #64748b;">No academic results recorded yet. Use the Upload Result panel on the right to add semester results or save as Draft.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Upload New Result Form with Draft / Publish State -->
    <div id="uploadResultFormCard" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.4rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.1rem; gap:0.5rem;">
        <h4 style="margin: 0; font-size: 1.05rem; color: #0f172a;"><i class="fas fa-upload" style="color: var(--rmu-primary);"></i> Upload / Add Result</h4>
        <a href="<?= APP_URL ?>/admin/results-manager.php?student_id=<?= $studentId ?>" style="font-size:0.78rem; font-weight:700; color:#1e73be; text-decoration:none;">
          <?= $totalSemesters ?>-Sem Editor &rarr;
        </a>
      </div>
      <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_result">

        <div class="form-group">
          <label class="form-label">Semester *</label>
          <select name="semester" class="form-select" required>
            <?php foreach ($standardSemesterList as $semOpt): ?>
              <option value="<?= e($semOpt) ?>"><?= e($semOpt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Course Code *</label>
          <input type="text" name="course_code" placeholder="e.g. CSE 101" class="form-control" required style="text-transform: uppercase;">
        </div>

        <div class="form-group">
          <label class="form-label">Subject / Course Title *</label>
          <input type="text" name="course_name" placeholder="e.g. Structured Programming in C" class="form-control" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
          <div class="form-group">
            <label class="form-label">Credit Hours *</label>
            <input type="number" step="0.5" min="0.5" max="9.5" name="credit" value="3.0" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Full Marks *</label>
            <input type="number" min="1" max="500" step="1" name="full_marks" id="uploadFullMarks" value="100" class="form-control" required oninput="autoGradeFromMarks()">
          </div>
        </div>

        <div style="background:#f0fdf4; border:1px solid #86efac; border-radius:6px; padding:0.85rem; margin-bottom:1rem;">
          <div style="font-size:0.8rem; font-weight:700; color:#166534; margin-bottom:0.45rem;">
            <i class="fas fa-magic"></i> Live Auto-Grading (UGC 4.00 Scale)
          </div>
          <div style="display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 0.6rem;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" style="font-size:0.75rem;">Marks <span id="uploadMarksCapLabel">(0–100)</span></label>
              <input type="number" step="0.5" min="0" max="100" name="marks" id="marksInput" placeholder="e.g. 85" class="form-control" oninput="autoGradeFromMarks()">
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" style="font-size:0.75rem;">Grade</label>
              <select name="letter_grade" id="letterGradeSelect" class="form-select" onchange="autoGradePoint(this.value)">
                <option value="A+">A+</option>
                <option value="A">A</option>
                <option value="A-">A-</option>
                <option value="B+">B+</option>
                <option value="B">B</option>
                <option value="B-">B-</option>
                <option value="C+">C+</option>
                <option value="C">C</option>
                <option value="D">D</option>
                <option value="F">F</option>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" style="font-size:0.75rem;">GP</label>
              <input type="number" step="0.01" min="0" max="4.00" name="grade_point" id="gradePointInput" value="4.00" class="form-control" required>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Examination Term</label>
          <input type="text" name="exam_term" value="Semester Final Examination" class="form-control">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
          <label class="form-label" style="font-weight:700;">Result Workflow Status</label>
          <select name="publish_status" class="form-select">
            <option value="Published">Publish Immediately to Student Portal</option>
            <option value="Draft">Save as Draft (Admin Only Until Published)</option>
            <option value="Under Review">Under Review (Pending Verification)</option>
          </select>
        </div>

        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 0.4rem;">
          <i class="fas fa-check-circle"></i> Save Result &amp; Recalculate CGPA
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- TAB 2: COURSE ENROLLMENTS                                                 -->
<!-- ========================================================================= -->
<div id="coursesTab" class="tab-content" style="display: none;">
  <div class="sd-two-col">
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;">
        <h4 style="margin: 0; font-size: 1.1rem; color: #0f172a;">Registered Courses</h4>
      </div>
      <div class="table-responsive">
        <table class="rmu-table" style="box-shadow: none; margin: 0;">
          <thead>
            <tr>
              <th>Course Code &bull; Name</th>
              <th>Credit</th>
              <th>Instructor &bull; Schedule</th>
              <th>Attendance</th>
              <th>Status</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($courses)): ?>
              <?php foreach ($courses as $co): ?>
                <tr>
                  <td>
                    <strong style="color: var(--rmu-primary); font-family: monospace;"><?= e($co['course_code']) ?></strong>
                    <div style="font-weight: 600; color: #0f172a; font-size: 0.9rem;"><?= e($co['course_name']) ?></div>
                    <small style="color: #64748b;"><?= e($co['semester']) ?></small>
                  </td>
                  <td><?= number_format((float)$co['credit'], 1) ?> Cr</td>
                  <td style="font-size: 0.82rem; color: #475569;">
                    <div><?= e($co['instructor_name']) ?></div>
                    <small style="color: #64748b;"><?= e($co['class_schedule']) ?> (<?= e($co['room_no']) ?>)</small>
                  </td>
                  <td>
                    <strong style="color: <?= ($co['attendance_percent'] >= 75) ? '#15803d' : '#b91c1c' ?>;">
                      <?= (int)$co['attendance_percent'] ?>%
                    </strong>
                  </td>
                  <td>
                    <span style="padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; background: #e0f2fe; color: #0369a1;">
                      <?= e($co['status']) ?>
                    </span>
                  </td>
                  <td style="text-align: right;">
                    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display: inline;" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Remove course ' . $co['course_code'] . ' from this student?'), ENT_QUOTES, 'UTF-8') ?>);">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_course">
                      <input type="hidden" name="course_id" value="<?= $co['id'] ?>">
                      <button type="submit" style="border: none; background: none; color: #ef4444; cursor: pointer; font-size: 0.85rem;" title="Drop / Delete">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">No courses registered yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <h4 style="margin: 0 0 1.25rem; font-size: 1.1rem; color: #0f172a;"><i class="fas fa-plus-circle" style="color: var(--rmu-primary);"></i> Enroll New Course</h4>
      <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_course">

        <div class="form-group">
          <label class="form-label">Course Code *</label>
          <input type="text" name="course_code" placeholder="e.g. CSE 201" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Course Title *</label>
          <input type="text" name="course_name" placeholder="e.g. Data Structures & Algorithms" class="form-control" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
          <div class="form-group">
            <label class="form-label">Credit Hours</label>
            <input type="number" step="0.5" min="0.5" max="6.0" name="credit" value="3.0" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Attendance (%)</label>
            <input type="number" min="0" max="100" name="attendance_percent" value="85" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Instructor Name</label>
          <input type="text" name="instructor_name" placeholder="e.g. Dr. Md. Nazmul Islam" class="form-control">
        </div>

        <div class="form-group">
          <label class="form-label">Class Schedule</label>
          <input type="text" name="class_schedule" placeholder="e.g. Sun & Tue: 10:00 AM" class="form-control">
        </div>

        <div class="form-group">
          <label class="form-label">Room / Laboratory</label>
          <input type="text" name="room_no" placeholder="e.g. Lab 203" class="form-control">
        </div>

        <div class="form-group">
          <label class="form-label">Semester Term</label>
          <input type="text" name="semester" value="<?= e($student['current_semester']) ?>" class="form-control" required>
        </div>

        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 0.75rem;">
          <i class="fas fa-check"></i> Register Course
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- TAB 3: TUITION FEES & BILLING                                             -->
<!-- ========================================================================= -->
<div id="feesTab" class="tab-content" style="display: none;">
  <div class="sd-two-col">
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;">
        <h4 style="margin: 0; font-size: 1.1rem; color: #0f172a;">Tuition Fees, Dues &amp; Payment History</h4>
      </div>
      <div class="table-responsive">
        <table class="rmu-table" style="box-shadow: none; margin: 0;">
          <thead>
            <tr>
              <th>Fee Title &bull; Due Date</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Transaction ID &bull; Paid Date</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($fees)): ?>
              <?php foreach ($fees as $fee): ?>
                <tr>
                  <td>
                    <strong style="color: #0f172a; display: block; font-size: 0.9rem;"><?= e($fee['fee_title']) ?></strong>
                    <small style="color: #64748b;">Due: <?= format_date($fee['due_date']) ?></small>
                  </td>
                  <td><strong style="color: #0f172a; font-size: 0.95rem;">৳<?= number_format((float)$fee['amount'], 2) ?></strong></td>
                  <td>
                    <?php if ($fee['status'] === 'Paid'): ?>
                      <span style="background: #dcfce7; color: #15803d; font-weight: 700; font-size: 0.75rem; padding: 0.25rem 0.55rem; border-radius: 4px;">Paid</span>
                    <?php elseif ($fee['status'] === 'Pending'): ?>
                      <span style="background: #fef3c7; color: #b45309; font-weight: 700; font-size: 0.75rem; padding: 0.25rem 0.55rem; border-radius: 4px;">Pending</span>
                    <?php elseif ($fee['status'] === 'Failed'): ?>
                      <span style="background: #fee2e2; color: #b91c1c; font-weight: 700; font-size: 0.75rem; padding: 0.25rem 0.55rem; border-radius: 4px;">Failed</span>
                    <?php else: ?>
                      <span style="background: #f1f5f9; color: #475569; font-weight: 700; font-size: 0.75rem; padding: 0.25rem 0.55rem; border-radius: 4px;">Due</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size: 0.82rem; color: #475569;">
                    <?php if (!empty($fee['transaction_id'])): ?>
                      <div style="font-family: monospace; color: #0369a1; font-weight: 600;"><?= e($fee['transaction_id']) ?></div>
                      <small style="color: #64748b;"><?= !empty($fee['paid_at']) ? format_date($fee['paid_at']) : format_date($fee['created_at']) ?></small>
                    <?php else: ?>
                      <span style="color: #94a3b8;">No transaction recorded</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align: right; white-space: nowrap;">
                    <div style="display: inline-flex; align-items: center; gap: 4px;">
                      <?php if ($fee['status'] !== 'Paid'): ?>
                        <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display: inline;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="mark_paid">
                          <input type="hidden" name="fee_id" value="<?= $fee['id'] ?>">
                          <button type="submit" class="btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #16a34a;">Paid</button>
                        </form>
                      <?php endif; ?>
                      <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST" style="display: inline;" onsubmit="return confirm('Delete fee invoice #<?= $fee['id'] ?>?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_fee">
                        <input type="hidden" name="fee_id" value="<?= $fee['id'] ?>">
                        <button type="submit" style="border: none; background: none; color: #ef4444; cursor: pointer; font-size: 0.85rem; padding: 0.25rem;" title="Delete">
                          <i class="fas fa-trash-alt"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5" style="text-align: center; padding: 2rem; color: #64748b;">No fees recorded.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <h4 style="margin: 0 0 1.25rem; font-size: 1.1rem; color: #0f172a;"><i class="fas fa-file-invoice" style="color: var(--rmu-primary);"></i> Issue New Fee Invoice</h4>
      <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_fee">

        <div class="form-group">
          <label class="form-label">Fee Title *</label>
          <input type="text" name="fee_title" placeholder="e.g. Fall 2026 Semester Tuition Fee" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Amount (BDT ৳) *</label>
          <input type="number" step="100" min="0" name="amount" placeholder="e.g. 32000" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Payment Due Date</label>
          <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Payment Status</label>
          <select name="status" class="form-select">
            <option value="Paid">Paid</option>
            <option value="Pending">Pending</option>
            <option value="Failed">Failed</option>
            <option value="Due">Due / Unpaid</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Transaction ID (if paid)</label>
          <input type="text" name="transaction_id" placeholder="e.g. RMU-PAY-2026-XXXXX" class="form-control">
        </div>

        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 0.75rem;">
          <i class="fas fa-save"></i> Issue Fee Invoice
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- TAB 4: PERSONAL & ADMISSION DOSSIER                                       -->
<!-- ========================================================================= -->
<div id="admissionTab" class="tab-content" style="display: none;">
  <div class="sd-two-col-eq">
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1.25rem;">
        <h4 style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 700;">
          <i class="fas fa-user-edit" style="color: var(--rmu-primary);"></i> Personal Particulars &amp; Identifiers
        </h4>
        <span style="font-size: 0.82rem; color: #64748b;">Update identifiers, parental details, and contact information.</span>
      </div>

      <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_personal_info">

        <div class="form-group">
          <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Full Student Name *</label>
          <input type="text" name="full_name" value="<?= e($student['full_name']) ?>" class="form-control" required>
        </div>

        <div class="sd-form-grid-3">
          <div class="form-group">
            <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Roll Number</label>
            <input type="text" name="roll_number" value="<?= e($student['roll_number'] ?? '') ?>" class="form-control" placeholder="e.g. 2026-0001">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Application ID</label>
            <input type="text" name="application_number" value="<?= e($student['application_number'] ?? '') ?>" class="form-control" placeholder="e.g. RMU-2026-0001">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Username</label>
            <input type="text" name="username" value="<?= e($student['username'] ?? $student['student_id']) ?>" class="form-control">
          </div>
        </div>

        <div class="sd-form-grid-2">
          <div class="form-group">
            <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Father's Name</label>
            <input type="text" name="father_name" value="<?= e($student['father_name'] ?? '') ?>" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Mother's Name</label>
            <input type="text" name="mother_name" value="<?= e($student['mother_name'] ?? '') ?>" class="form-control">
          </div>
        </div>

        <div class="sd-form-grid-3">
          <div class="form-group">
            <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Date of Birth</label>
            <input type="date" name="dob" value="<?= e($student['dob'] ?? '') ?>" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Gender</label>
            <select name="gender" class="form-select">
              <option value="Male" <?= ($student['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= ($student['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= ($student['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Blood Group</label>
            <select name="blood_group" class="form-select">
              <option value="">Select</option>
              <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                <option value="<?= $bg ?>" <?= ($student['blood_group'] === $bg) ? 'selected' : '' ?>><?= $bg ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="sd-form-grid-2">
          <div class="form-group">
            <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Contact Phone *</label>
            <input type="text" name="phone" value="<?= e($student['phone']) ?>" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Email Address *</label>
            <input type="email" name="email" value="<?= e($student['email']) ?>" class="form-control" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Present Address</label>
          <textarea name="present_address" class="form-control" rows="2"><?= e($student['present_address'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Permanent Address</label>
          <textarea name="permanent_address" class="form-control" rows="2"><?= e($student['permanent_address'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size: 0.82rem; font-weight: 600;">Home District</label>
          <input type="text" name="home_district" value="<?= e($student['home_district'] ?? '') ?>" class="form-control">
        </div>

        <button type="submit" class="btn-primary" style="padding: 0.65rem 1.5rem; font-size: 0.88rem;">
          <i class="fas fa-save"></i> Save Profile Particulars
        </button>
      </form>
    </div>

    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
      <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h4 style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 700;">
              <i class="fas fa-file-alt" style="color: var(--rmu-primary);"></i> Linked Admission Dossier
            </h4>
            <span style="font-size: 0.82rem; color: #64748b;">Original application credentials and academic verification.</span>
          </div>
          <?php if (!empty($admissionRecord)): ?>
            <a href="<?= APP_URL ?>/admission-status.php?ref=<?= urlencode($admissionRecord['application_ref']) ?>" target="_blank" class="btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.78rem; background: #0284c7;">
              <i class="fas fa-id-card"></i> Admit Card
            </a>
          <?php endif; ?>
        </div>

        <?php if (!empty($admissionRecord)): ?>
          <?php
            $admSessStr = !empty($admissionRecord['academic_session']) ? (string)$admissionRecord['academic_session'] : $defaultSessionVal;
            $admExpYr = !empty($admissionRecord['expected_passing_year'])
                ? (int)$admissionRecord['expected_passing_year']
                : ($oversightEval['expected_year'] ?? null);
          ?>
          <div class="sd-form-grid-2" style="font-size: 0.88rem; margin-bottom: 1.25rem;">
            <div><strong style="color: #64748b;">Application ID:</strong> <span style="font-family: monospace; font-weight: 700; color: var(--rmu-primary);"><?= e(!empty($admissionRecord['application_id']) ? $admissionRecord['application_id'] : $admissionRecord['application_ref']) ?></span></div>
            <div><strong style="color: #64748b;">Admission Roll No:</strong> <span style="font-family: monospace; font-weight: 700; color: #6d28d9;"><?= e(!empty($admissionRecord['roll_number']) ? $admissionRecord['roll_number'] : ($admissionRecord['exam_roll'] ?? ($student['roll_number'] ?: 'N/A'))) ?></span></div>
            <div><strong style="color: #64748b;">Applied On:</strong> <?= format_date($admissionRecord['created_at']) ?></div>
            <div><strong style="color: #64748b;">Session:</strong> <?= e($admSessStr) ?></div>
            <div><strong style="color: #64748b;">Passing Year (Applicant):</strong> <?= e($admissionRecord['passing_year'] ?: 'Not Provided') ?></div>
            <div><strong style="color: #64748b;">Expected Year (Advisory):</strong> <?= $admExpYr ? e((string)$admExpYr) : 'N/A' ?></div>
            <div><strong style="color: #64748b;">Admission Status:</strong> <span style="font-weight: 700; color: #15803d;"><?= e($admissionRecord['status']) ?></span></div>
            <div><strong style="color: #64748b;">SSC GPA:</strong> <?= number_format((float)($admissionRecord['ssc_gpa'] ?? 0), 2) ?> (<?= e($admissionRecord['ssc_board'] ?? 'N/A') ?>)</div>
            <div><strong style="color: #64748b;">HSC GPA:</strong> <?= number_format((float)($admissionRecord['hsc_gpa'] ?? 0), 2) ?> (<?= e($admissionRecord['hsc_board'] ?? 'N/A') ?>)</div>
            <div style="grid-column: 1 / -1;"><strong style="color: #64748b;">Last College:</strong> <?= e($admissionRecord['last_college'] ?? 'N/A') ?></div>
          </div>

          <?php if (!empty($admissionRecord['academic_documents'])): ?>
            <a href="<?= APP_URL . '/' . e($admissionRecord['academic_documents']) ?>" target="_blank" class="btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; background: #2563eb; display: inline-flex; align-items: center; gap: 0.5rem;">
              <i class="fas fa-download"></i> View / Download Academic Documents
            </a>
          <?php endif; ?>
        <?php else: ?>
          <div style="text-align: center; padding: 2rem; color: #64748b;">
            <i class="fas fa-info-circle" style="font-size: 2rem; color: #94a3b8; margin-bottom: 0.75rem; display: block;"></i>
            <p style="margin: 0 0 0.5rem; font-weight: 600;">No linked online admission application found.</p>
            <small>Student enrolled directly by University Administration.</small>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- TAB 5: ADMIN ACTIVITY LOG & RESULT MODIFICATION AUDIT TRAIL               -->
<!-- ========================================================================= -->
<div id="auditTab" class="tab-content" style="display: none;">
  <div class="sd-two-col-eq">
    <!-- Administrative Actions Audit Log -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="padding: 1.1rem 1.4rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
        <h4 style="margin: 0; font-size: 1.05rem; color: #0f172a; font-weight: 800;">
          <i class="fas fa-user-shield" style="color: #0284c7;"></i> Admin Activity Log (Account &amp; Result Events)
        </h4>
      </div>
      <div class="table-responsive">
        <table class="rmu-table" style="box-shadow: none; margin: 0; font-size: 0.82rem;">
          <thead>
            <tr>
              <th>Date &amp; Time</th>
              <th>Admin</th>
              <th>Action</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($studentAuditLogs)): ?>
              <tr><td colspan="4" style="text-align:center; padding:1.75rem; color:#64748b;">No administrative audit entries matched for this student yet.</td></tr>
            <?php else: ?>
              <?php foreach ($studentAuditLogs as $al): ?>
                <tr>
                  <td style="white-space: nowrap; color: #64748b;"><?= date('M d, Y H:i:s', strtotime($al['created_at'])) ?></td>
                  <td>
                    <strong>#<?= (int)($al['user_id'] ?? 0) ?></strong>
                    <?= e($al['admin_fullname'] ?? ($al['admin_username'] ?? 'System')) ?>
                  </td>
                  <td><span style="background: #f1f5f9; color: #0f2c59; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 0.74rem;"><?= e($al['action']) ?></span></td>
                  <td style="color: #334155;"><?= e($al['details']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Subject Grade Modification History -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="padding: 1.1rem 1.4rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
        <h4 style="margin: 0; font-size: 1.05rem; color: #0f172a; font-weight: 800;">
          <i class="fas fa-history" style="color: #15803d;"></i> Subject Result Modification Trail
        </h4>
      </div>
      <div class="table-responsive">
        <table class="rmu-table" style="box-shadow: none; margin: 0; font-size: 0.82rem;">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Admin ID</th>
              <th>Subject</th>
              <th>Change &amp; Reason</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($modHistoryLogs)): ?>
              <tr><td colspan="4" style="text-align:center; padding:1.75rem; color:#64748b;">No subject modification history recorded yet.</td></tr>
            <?php else: ?>
              <?php foreach ($modHistoryLogs as $mh): ?>
                <tr>
                  <td style="white-space: nowrap; color: #64748b;"><?= date('M d, Y H:i', strtotime($mh['created_at'])) ?></td>
                  <td>#<?= (int)$mh['admin_id'] ?> <?= e($mh['admin_fullname'] ?? $mh['admin_username'] ?? '') ?></td>
                  <td><strong style="font-family:monospace; color:var(--rmu-primary);"><?= e($mh['course_code']) ?></strong><br><small><?= e($mh['semester']) ?></small></td>
                  <td>
                    <div><strong><?= e($mh['field_name']) ?>:</strong> <?= e($mh['old_value'] ?? '—') ?> &rarr; <strong style="color:#15803d;"><?= e($mh['new_value']) ?></strong></div>
                    <small style="color:#64748b;"><?= e($mh['reason']) ?></small>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Official Academic Session, Passing Year & Duration Edit History -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 1.5rem;">
    <div style="padding: 1.1rem 1.4rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
      <h4 style="margin: 0; font-size: 1.05rem; color: #0f172a; font-weight: 800;">
        <i class="fas fa-calendar-check" style="color: #6d28d9;"></i> Official Academic Record &amp; Passing Year Edit History
      </h4>
      <span style="font-size:0.78rem; color:#64748b;">Mandatory-reason audit trail for Session, Passing Year, Program &amp; Historical Approvals</span>
    </div>
    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow: none; margin: 0; font-size: 0.82rem;">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Administrator</th>
            <th>Field Changed</th>
            <th>Old &rarr; New Value</th>
            <th>Official Reason</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($academicEditLogs)): ?>
            <tr><td colspan="5" style="text-align:center; padding:1.5rem; color:#64748b;">No official academic edits or historical approvals logged for this student yet.</td></tr>
          <?php else: ?>
            <?php foreach ($academicEditLogs as $ael): ?>
              <tr>
                <td style="white-space: nowrap; color: #64748b;"><?= date('M d, Y H:i', strtotime($ael['created_at'])) ?></td>
                <td>#<?= (int)($ael['admin_id'] ?? 0) ?> <?= e($ael['admin_full_name'] ?? ($ael['admin_username'] ?? 'Admin')) ?></td>
                <td><span style="background:#ede9fe; color:#5b21b6; padding:2px 6px; border-radius:4px; font-weight:700; font-size:0.74rem;"><?= e($ael['field_name']) ?></span></td>
                <td><?= e($ael['old_value'] ?? '—') ?> &rarr; <strong style="color:#15803d;"><?= e($ael['new_value'] ?? '—') ?></strong></td>
                <td style="color:#334155;"><?= e($ael['reason']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODALS: EDIT RESULT, HIDE RESULT, DEACTIVATE ACCOUNT, DELETE ACCOUNT      -->
<!-- ========================================================================= -->

<!-- 1. Edit Subject Result Modal -->
<div id="editDetailResultModal" class="sd-modal-overlay">
  <div class="sd-modal-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.15rem; color:#0f2c59;"><i class="fas fa-edit" style="color:var(--rmu-primary);"></i> Edit Subject Result</h3>
      <button type="button" onclick="closeModalById('editDetailResultModal')" style="border:none; background:none; font-size:1.3rem; color:#64748b; cursor:pointer;">&times;</button>
    </div>

    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit_result">
      <input type="hidden" name="result_id" id="edResId">

      <div class="form-group">
        <label class="form-label">Semester *</label>
        <select name="semester" id="edResSem" class="form-select" required>
          <?php foreach ($standardSemesterList as $semOpt): ?>
            <option value="<?= e($semOpt) ?>"><?= e($semOpt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="sd-form-grid-edit">
        <div class="form-group">
          <label class="form-label">Course Code *</label>
          <input type="text" name="course_code" id="edResCode" class="form-control" required style="text-transform:uppercase;">
        </div>
        <div class="form-group">
          <label class="form-label">Subject Name *</label>
          <input type="text" name="course_name" id="edResName" class="form-control" required>
        </div>
      </div>

      <div class="sd-form-grid-3">
        <div class="form-group">
          <label class="form-label">Credit Hours *</label>
          <input type="number" step="0.5" min="0.5" max="9.5" name="credit" id="edResCredit" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Obtained Marks <span id="edResMarksCapLabel">(0–100)</span> *</label>
          <input type="number" step="0.5" min="0" max="100" name="marks" id="edResMarks" class="form-control" required oninput="autoGradeEditModal()">
        </div>
        <div class="form-group">
          <label class="form-label">Full Marks *</label>
          <input type="number" min="1" max="500" step="1" name="full_marks" id="edResFullMarks" value="100" class="form-control" required oninput="autoGradeEditModal()">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; background:#f0fdf4; border:1px solid #86efac; border-radius:6px; padding:0.75rem; margin-bottom:1rem;">
        <div>
          <label class="form-label" style="font-size:0.78rem;">Letter Grade</label>
          <input type="text" name="letter_grade" id="edResGrade" class="form-control" readonly style="font-weight:800; text-align:center;">
        </div>
        <div>
          <label class="form-label" style="font-size:0.78rem;">Grade Point</label>
          <input type="number" step="0.01" name="grade_point" id="edResPoint" class="form-control" readonly style="font-weight:800; text-align:center;">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Exam Term</label>
        <input type="text" name="exam_term" id="edResTerm" class="form-control">
      </div>

      <div class="form-group">
        <label class="form-label">Publication Status</label>
        <select name="publish_status" id="edResPubStatus" class="form-select">
          <option value="Published">Published (Visible when Result Visibility is ON)</option>
          <option value="Draft">Draft (Hidden from Student Portal)</option>
          <option value="Under Review">Under Review</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Correction / Update Reason *</label>
        <input type="text" name="correction_reason" id="edResReason" class="form-control" required placeholder="Reason for mark or grade update">
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" onclick="closeModalById('editDetailResultModal')" class="sd-btn" style="background:#64748b;">Cancel</button>
        <button type="submit" class="sd-btn" style="background:#1e73be;"><i class="fas fa-save"></i> Save Updated Result</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. Hide Result Modal -->
<div id="hideResultsModal" class="sd-modal-overlay">
  <div class="sd-modal-card" style="max-width: 480px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.15rem; color:#991b1b;"><i class="fas fa-eye-slash"></i> Hide Academic Results</h3>
      <button type="button" onclick="closeModalById('hideResultsModal')" style="border:none; background:none; font-size:1.3rem; color:#64748b; cursor:pointer;">&times;</button>
    </div>
    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="hide_results">
      <p style="font-size:0.88rem; color:#334155; line-height:1.5; margin-top:0;">
        Hiding results removes result sheets, GPAs, and transcripts from <strong><?= e($student['full_name']) ?></strong>'s Student Portal view immediately. All underlying academic data remains 100% preserved and can be restored anytime using <strong>Show Result</strong>.
      </p>
      <div class="form-group">
        <label class="form-label">Reason / Student Notice (Optional)</label>
        <input type="text" name="reason" class="form-control" placeholder="e.g. Tuition clearance pending or result re-scrutiny">
      </div>
      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" onclick="closeModalById('hideResultsModal')" class="sd-btn" style="background:#64748b;">Cancel</button>
        <button type="submit" class="sd-btn" style="background:#b91c1c;"><i class="fas fa-eye-slash"></i> Confirm Hide Result</button>
      </div>
    </form>
  </div>
</div>

<!-- 3. Deactivate Account Modal (Confirmation Step) -->
<div id="deactivateAccountModal" class="sd-modal-overlay">
  <div class="sd-modal-card" style="max-width: 500px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.15rem; color:#b45309;"><i class="fas fa-user-slash"></i> Confirm Account Deactivation</h3>
      <button type="button" onclick="closeModalById('deactivateAccountModal')" style="border:none; background:none; font-size:1.3rem; color:#64748b; cursor:pointer;">&times;</button>
    </div>
    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="deactivate_account">
      <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:0.9rem; margin-bottom:1rem; font-size:0.86rem; color:#78350f;">
        <strong>What happens on Deactivation:</strong>
        <ul style="margin:0.4rem 0 0 1.2rem; padding:0;">
          <li>The student cannot log in or access the Student Portal.</li>
          <li>Any existing logged-in portal session is invalidated immediately.</li>
          <li>All academic results, CGPA, courses, and fees are strictly preserved.</li>
          <li>You can reactivate this account anytime.</li>
        </ul>
      </div>
      <div class="form-group">
        <label class="form-label">Deactivation Reason *</label>
        <input type="text" name="reason" class="form-control" required placeholder="e.g. Semester break / administrative hold">
      </div>
      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" onclick="closeModalById('deactivateAccountModal')" class="sd-btn" style="background:#64748b;">Cancel</button>
        <button type="submit" class="sd-btn" style="background:#d97706;"><i class="fas fa-user-slash"></i> Deactivate Account</button>
      </div>
    </form>
  </div>
</div>

<!-- 4. Delete Account Modal (Extra Confirmation Step) -->
<div id="deleteAccountModal" class="sd-modal-overlay">
  <div class="sd-modal-card" style="max-width: 520px; border: 2px solid #f87171;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #fecaca; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.15rem; color:#991b1b;"><i class="fas fa-exclamation-triangle"></i> Permanent Account Deletion</h3>
      <button type="button" onclick="closeModalById('deleteAccountModal')" style="border:none; background:none; font-size:1.3rem; color:#64748b; cursor:pointer;">&times;</button>
    </div>
    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_student_account">
      <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:0.9rem; margin-bottom:1rem; font-size:0.86rem; color:#7f1d1d;">
        <strong>Security &amp; Retention Notice for <?= e($student['student_id']) ?> (<?= e($student['full_name']) ?>):</strong>
        <ul style="margin:0.4rem 0 0 1.2rem; padding:0;">
          <li>Login credentials &amp; admission portal access are permanently revoked.</li>
          <li>The student can never log in with their old Student ID or password.</li>
          <li>Other students' records are never affected.</li>
          <li>Academic &amp; audit records are archived under University Data Retention Policy.</li>
        </ul>
      </div>
      <div class="form-group">
        <label class="form-label">Deletion Reason *</label>
        <input type="text" name="reason" class="form-control" required placeholder="e.g. Student permanently withdrawn / expelled / duplicate test record">
      </div>
      <div class="form-group">
        <label class="form-label">Type Student ID (<strong><?= e($student['student_id']) ?></strong>) or <strong>DELETE</strong> to confirm *</label>
        <input type="text" name="confirm_student_id" class="form-control" required placeholder="<?= e($student['student_id']) ?>">
      </div>
      <div class="form-group" style="margin-bottom: 1.25rem;">
        <label style="display:flex; align-items:flex-start; gap:8px; font-size:0.84rem; color:#7f1d1d; font-weight:700; cursor:pointer;">
          <input type="checkbox" name="confirm_permanent_delete" value="1" required style="margin-top:3px; width:16px; height:16px;">
          <span>I confirm that I want to permanently delete this student account and revoke all portal credentials.</span>
        </label>
      </div>
      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" onclick="closeModalById('deleteAccountModal')" class="sd-btn" style="background:#64748b;">Cancel</button>
        <button type="submit" class="sd-btn" style="background:#dc2626;"><i class="fas fa-trash-alt"></i> Permanently Delete Account</button>
      </div>
    </form>
  </div>
</div>

<!-- 5. Suspend Result Access Modal (Account & Login Remain Active) -->
<div id="suspendResultsModal" class="sd-modal-overlay">
  <div class="sd-modal-card" style="max-width: 500px; border: 2px solid #fb923c;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #fed7aa; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.15rem; color:#9a3412;"><i class="fas fa-user-lock"></i> Suspend Result Access</h3>
      <button type="button" onclick="closeModalById('suspendResultsModal')" style="border:none; background:none; font-size:1.3rem; color:#64748b; cursor:pointer;">&times;</button>
    </div>
    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="suspend_result_access">
      <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:0.9rem; margin-bottom:1rem; font-size:0.86rem; color:#7c2d12;">
        <strong>Result Access Suspension Behavior:</strong>
        <ul style="margin:0.4rem 0 0 1.2rem; padding:0;">
          <li>The student can still log in and access their Student Profile.</li>
          <li>Academic results, semester GPAs, CGPA, and printable transcripts are concealed with a <strong>Result Access Suspended</strong> notice.</li>
          <li>All academic records remain stored safely and access can be restored anytime.</li>
        </ul>
      </div>
      <div class="form-group">
        <label class="form-label">Suspension Reason *</label>
        <input type="text" name="reason" class="form-control" required placeholder="e.g. Tuition dues clearance or Examination Committee hold">
      </div>
      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" onclick="closeModalById('suspendResultsModal')" class="sd-btn" style="background:#64748b;">Cancel</button>
        <button type="submit" class="sd-btn" style="background:#c2410c;"><i class="fas fa-user-lock"></i> Confirm Suspend Result Access</button>
      </div>
    </form>
  </div>
</div>

<!-- 6. Delete All Results Modal (Keep Student Account Intact) -->
<div id="deleteAllResultsModal" class="sd-modal-overlay">
  <div class="sd-modal-card" style="max-width: 500px; border: 2px solid #f43f5e;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #fecdd3; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.15rem; color:#9f1239;"><i class="fas fa-eraser"></i> Delete All Academic Results</h3>
      <button type="button" onclick="closeModalById('deleteAllResultsModal')" style="border:none; background:none; font-size:1.3rem; color:#64748b; cursor:pointer;">&times;</button>
    </div>
    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_all_results">
      <div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:8px; padding:0.9rem; margin-bottom:1rem; font-size:0.86rem; color:#881337;">
        <strong>Safe Result Removal for <?= e($student['student_id']) ?>:</strong>
        <ul style="margin:0.4rem 0 0 1.2rem; padding:0;">
          <li>Removes all semester &amp; subject results and resets CGPA to 0.00.</li>
          <li><strong>Student portal account, login credentials, and profile remain 100% intact.</strong></li>
        </ul>
      </div>
      <div class="form-group">
        <label class="form-label">Deletion Reason *</label>
        <input type="text" name="reason" class="form-control" required placeholder="e.g. Resetting projected estimates prior to final publication">
      </div>
      <div class="form-group" style="margin-bottom: 1.2rem;">
        <label style="display:flex; align-items:flex-start; gap:8px; font-size:0.84rem; color:#881337; font-weight:700; cursor:pointer;">
          <input type="checkbox" name="confirm_delete_all_results" value="1" required style="margin-top:3px; width:16px; height:16px;">
          <span>I confirm deleting all academic results while keeping this student's portal account active.</span>
        </label>
      </div>
      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" onclick="closeModalById('deleteAllResultsModal')" class="sd-btn" style="background:#64748b;">Cancel</button>
        <button type="submit" class="sd-btn" style="background:#9f1239;"><i class="fas fa-eraser"></i> Delete Results (Keep Account)</button>
      </div>
    </form>
  </div>
</div>

<!-- 7. Official Academic Edit Modal (Mandatory Reason & Audit Trail) -->
<div id="officialAcademicEditModal" class="sd-modal-overlay">
  <div class="sd-modal-card" style="max-width: 560px; border: 2px solid #0f2c59;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #cbd5e1; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.12rem; color:#0f2c59;"><i class="fas fa-pen-fancy"></i> Official Academic Record Update</h3>
      <button type="button" onclick="closeModalById('officialAcademicEditModal')" style="border:none; background:none; font-size:1.3rem; color:#64748b; cursor:pointer;">&times;</button>
    </div>
    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="official_academic_edit">

      <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:0.75rem 0.9rem; margin-bottom:1rem; font-size:0.82rem; color:#1e3a8a;">
        <strong>Oversight Policy:</strong> Selected Passing Year and Expected Passing Year remain independent. Every change here requires an official reason and is permanently recorded in <code>academic_edit_history</code>.
      </div>

      <div class="sd-form-grid-2">
        <div class="form-group">
          <label class="form-label">Academic Session *</label>
          <input type="text" name="academic_session" id="sdEditSess" list="sdSessionsDatalist" value="<?= e($defaultSessionVal) ?>" class="form-control" required oninput="syncSdModalExpPreview()">
        </div>
        <div class="form-group">
          <label class="form-label">Passing Year (Admin/Student Selection)</label>
          <input type="text" name="passing_year" id="sdEditPassYr" list="sdPassingYearsDatalist" value="<?= e($selectedPassingYear ?? '') ?>" class="form-control" placeholder="e.g. 2015">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Degree Program</label>
        <select name="program_id" id="sdEditProgId" class="form-select" onchange="syncSdModalProg()">
          <?php foreach ($allProgramsList as $ap): ?>
            <option value="<?= (int)$ap['id'] ?>" data-type="<?= e($ap['degree_type'] ?? 'Bachelor / Honours') ?>" data-dur="<?= htmlspecialchars((string)($ap['duration_years'] ?? 4.0)) ?>" data-sems="<?= (int)($ap['total_semesters'] ?? 8) ?>" <?= ((int)$student['program_id'] === (int)$ap['id']) ? 'selected' : '' ?>>
              <?= e($ap['title_en']) ?> (<?= e($ap['degree_type'] ?? 'Bachelor / Honours') ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="sd-form-grid-3">
        <div class="form-group">
          <label class="form-label">Degree Type</label>
          <select name="degree_type" id="sdEditDegType" class="form-select">
            <option value="Bachelor / Honours" <?= ($progStruct['degree_type'] === 'Bachelor / Honours') ? 'selected' : '' ?>>Bachelor / Honours</option>
            <option value="B.Ed" <?= ($progStruct['degree_type'] === 'B.Ed') ? 'selected' : '' ?>>B.Ed</option>
            <option value="Master's" <?= ($progStruct['degree_type'] === "Master's") ? 'selected' : '' ?>>Master's</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Duration (Years)</label>
          <input type="number" step="0.5" min="0.5" max="6" name="duration_years" id="sdEditDurYrs" value="<?= htmlspecialchars((string)$durationYears) ?>" class="form-control" oninput="syncSdModalExpPreview()">
        </div>
        <div class="form-group">
          <label class="form-label">Semesters</label>
          <select name="total_semesters" id="sdEditSems" class="form-select">
            <?php for ($so = 1; $so <= 12; $so++): ?>
              <option value="<?= $so ?>" <?= ($totalSemesters === $so) ? 'selected' : '' ?>><?= $so ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <div id="sdModalExpBox" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:0.55rem 0.75rem; font-size:0.82rem; color:#0f2c59; margin-bottom:0.9rem;">
        Advisory Expected Passing Year: <strong><?= !empty($oversightEval['expected_year']) ? (int)$oversightEval['expected_year'] . ' (' . e($oversightEval['formula']) . ')' : 'N/A' ?></strong>
      </div>

      <div class="form-group">
        <label class="form-label" style="color:#991b1b; font-weight:700;">Mandatory Official Reason *</label>
        <input type="text" name="edit_reason" class="form-control" required placeholder="e.g. Controller of Examinations correction per verified registration card">
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" onclick="closeModalById('officialAcademicEditModal')" class="sd-btn" style="background:#64748b;">Cancel</button>
        <button type="submit" class="sd-btn" style="background:#0f2c59;"><i class="fas fa-save"></i> Save Official Update</button>
      </div>
    </form>
  </div>
</div>

<!-- 8. Approve Historical Academic Record Modal -->
<div id="approveHistoricalModal" class="sd-modal-overlay">
  <div class="sd-modal-card" style="max-width: 480px; border: 2px solid #8b5cf6;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #ddd6fe; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.12rem; color:#5b21b6;"><i class="fas fa-history"></i> Approve Historical Academic Record</h3>
      <button type="button" onclick="closeModalById('approveHistoricalModal')" style="border:none; background:none; font-size:1.3rem; color:#64748b; cursor:pointer;">&times;</button>
    </div>
    <form action="<?= APP_URL ?>/admin/student-detail.php?id=<?= $studentId ?>" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="approve_historical">
      <div style="background:#f5f3ff; border:1px solid #ddd6fe; border-radius:8px; padding:0.85rem; margin-bottom:1rem; font-size:0.84rem; color:#4c1d95;">
        Confirm that the student's selected Passing Year (<strong><?= e($selectedPassingYear ?: 'N/A') ?></strong>) and Academic Session (<strong><?= e($defaultSessionVal) ?></strong>) reflect an approved historical / transfer / extended timeline without modifying the student's selected Passing Year.
      </div>
      <div class="form-group">
        <label class="form-label">Administrative Verification Note *</label>
        <input type="text" name="approval_note" class="form-control" required value="Historical session and passing year verified by University Administration">
      </div>
      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" onclick="closeModalById('approveHistoricalModal')" class="sd-btn" style="background:#64748b;">Cancel</button>
        <button type="submit" class="sd-btn" style="background:#6d28d9;"><i class="fas fa-check"></i> Confirm Approval</button>
      </div>
    </form>
  </div>
</div>

<script>
function openTab(tabId) {
  document.querySelectorAll('.tab-content').forEach(function(el) { el.style.display = 'none'; });
  document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.style.color = '#64748b';
    btn.style.borderBottom = 'none';
    btn.style.fontWeight = '600';
  });
  var target = document.getElementById(tabId);
  if (target) target.style.display = 'block';
  var activeBtn = document.getElementById('btn-' + tabId);
  if (activeBtn) {
    activeBtn.style.color = 'var(--rmu-primary)';
    activeBtn.style.borderBottom = '3px solid var(--rmu-primary)';
    activeBtn.style.fontWeight = '700';
  }
}

window.addEventListener('DOMContentLoaded', function() {
  var params = new URLSearchParams(window.location.search);
  var reqTab = params.get('tab');
  if (reqTab && document.getElementById(reqTab)) {
    openTab(reqTab);
  }
});

function closeModalById(id) {
  var el = document.getElementById(id);
  if (el) el.style.display = 'none';
}

function openHideResultsModal() {
  document.getElementById('hideResultsModal').style.display = 'flex';
}
function openDeactivateModal() {
  document.getElementById('deactivateAccountModal').style.display = 'flex';
}
function openDeleteAccountModal() {
  document.getElementById('deleteAccountModal').style.display = 'flex';
}
function openSuspendResultsModal() {
  document.getElementById('suspendResultsModal').style.display = 'flex';
}
function openDeleteAllResultsModal() {
  document.getElementById('deleteAllResultsModal').style.display = 'flex';
}
function openOfficialAcademicEditModal() {
  syncSdModalExpPreview();
  document.getElementById('officialAcademicEditModal').style.display = 'flex';
}
function openApproveHistoricalModal() {
  document.getElementById('approveHistoricalModal').style.display = 'flex';
}
function syncSdModalProg() {
  var sel = document.getElementById('sdEditProgId');
  if (!sel || sel.selectedIndex < 0) return;
  var opt = sel.options[sel.selectedIndex];
  var dt = opt.getAttribute('data-type');
  var dur = opt.getAttribute('data-dur');
  var sems = opt.getAttribute('data-sems');
  if (dt) document.getElementById('sdEditDegType').value = dt;
  if (dur) document.getElementById('sdEditDurYrs').value = dur;
  if (sems) document.getElementById('sdEditSems').value = sems;
  syncSdModalExpPreview();
}
function syncSdModalExpPreview() {
  var sess = document.getElementById('sdEditSess') ? document.getElementById('sdEditSess').value : '';
  var dur = parseFloat(document.getElementById('sdEditDurYrs') ? document.getElementById('sdEditDurYrs').value : '0');
  var box = document.getElementById('sdModalExpBox');
  if (!box) return;
  var m = String(sess).match(/\b(19\d{2}|20\d{2})\b/);
  if (!m || !isFinite(dur) || dur <= 0) {
    box.innerHTML = 'Advisory Expected Passing Year: <strong>N/A</strong>';
    return;
  }
  var sy = parseInt(m[1], 10);
  var cd = Math.max(0.5, Math.min(6.0, Math.round(dur * 10) / 10));
  var ey = sy + Math.round(cd);
  var dt = (Math.abs(cd - Math.round(cd)) < 0.05) ? String(Math.round(cd)) : cd.toFixed(1);
  box.innerHTML = 'Advisory Expected Passing Year: <strong>' + ey + ' (' + sy + ' + ' + dt + ' = ' + ey + ')</strong> <span style="color:#64748b;">(Does not overwrite Passing Year)</span>';
}

function openEditDetailResultModal(data) {
  document.getElementById('edResId').value = data.id;
  var semSel = document.getElementById('edResSem');
  if (semSel) {
    var hasOpt = Array.prototype.some.call(semSel.options, function(o) { return o.value === data.semester; });
    if (!hasOpt && data.semester) {
      var opt = document.createElement('option');
      opt.value = data.semester;
      opt.textContent = data.semester;
      semSel.appendChild(opt);
    }
    semSel.value = data.semester;
  }
  document.getElementById('edResCode').value = data.code;
  document.getElementById('edResName').value = data.name;
  document.getElementById('edResCredit').value = data.credit;
  var fmEl = document.getElementById('edResFullMarks');
  var mEl = document.getElementById('edResMarks');
  var fmVal = parseInt(data.full_marks || 100, 10);
  if (!isFinite(fmVal) || fmVal < 1) fmVal = 100;
  if (fmEl) fmEl.value = fmVal;
  if (mEl) {
    mEl.max = fmVal;
    mEl.value = data.marks !== '' ? data.marks : '';
  }
  var capEl = document.getElementById('edResMarksCapLabel');
  if (capEl) capEl.textContent = '(0–' + fmVal + ')';
  document.getElementById('edResGrade').value = data.letter_grade;
  document.getElementById('edResPoint').value = parseFloat(data.grade_point).toFixed(2);
  document.getElementById('edResTerm').value = data.exam_term || 'Semester Final Examination';
  document.getElementById('edResPubStatus').value = data.publish_status || 'Published';
  document.getElementById('edResReason').value = data.reason || 'Administrative result update';
  document.getElementById('editDetailResultModal').style.display = 'flex';
}

function autoGradePoint(grade) {
  var map = {
    'A+': 4.00, 'A': 3.75, 'A-': 3.50,
    'B+': 3.25, 'B': 3.00, 'B-': 2.75,
    'C+': 2.50, 'C': 2.25, 'D': 2.00, 'F': 0.00
  };
  if (map[grade] !== undefined) {
    document.getElementById('gradePointInput').value = map[grade].toFixed(2);
  }
}

function autoGradeFromMarks(val) {
  var mEl = document.getElementById('marksInput');
  var fmEl = document.getElementById('uploadFullMarks');
  var capEl = document.getElementById('uploadMarksCapLabel');
  var fm = fmEl ? parseInt(fmEl.value, 10) : 100;
  if (!isFinite(fm) || fm < 1) fm = 100;
  if (fm > 500) fm = 500;
  if (mEl) mEl.max = fm;
  if (capEl) capEl.textContent = '(0–' + fm + ')';

  var raw = (val !== undefined) ? val : (mEl ? mEl.value : '');
  var m = parseFloat(raw);
  if (isNaN(m) || raw === '') return;
  var pct = Math.max(0, Math.min(100, (m / fm) * 100));
  var grade = 'F', point = 0.00;
  if (pct >= 80) { grade = 'A+'; point = 4.00; }
  else if (pct >= 75) { grade = 'A'; point = 3.75; }
  else if (pct >= 70) { grade = 'A-'; point = 3.50; }
  else if (pct >= 65) { grade = 'B+'; point = 3.25; }
  else if (pct >= 60) { grade = 'B'; point = 3.00; }
  else if (pct >= 55) { grade = 'B-'; point = 2.75; }
  else if (pct >= 50) { grade = 'C+'; point = 2.50; }
  else if (pct >= 45) { grade = 'C'; point = 2.25; }
  else if (pct >= 40) { grade = 'D'; point = 2.00; }

  var sel = document.getElementById('letterGradeSelect');
  if (sel) sel.value = grade;
  var pInput = document.getElementById('gradePointInput');
  if (pInput) pInput.value = point.toFixed(2);
}

function autoGradeEditModal(val) {
  var mEl = document.getElementById('edResMarks');
  var fmEl = document.getElementById('edResFullMarks');
  var capEl = document.getElementById('edResMarksCapLabel');
  var fm = fmEl ? parseInt(fmEl.value, 10) : 100;
  if (!isFinite(fm) || fm < 1) fm = 100;
  if (fm > 500) fm = 500;
  if (mEl) mEl.max = fm;
  if (capEl) capEl.textContent = '(0–' + fm + ')';

  var raw = (val !== undefined) ? val : (mEl ? mEl.value : '');
  var m = parseFloat(raw);
  if (isNaN(m) || raw === '') return;
  var pct = Math.max(0, Math.min(100, (m / fm) * 100));
  var grade = 'F', point = 0.00;
  if (pct >= 80) { grade = 'A+'; point = 4.00; }
  else if (pct >= 75) { grade = 'A'; point = 3.75; }
  else if (pct >= 70) { grade = 'A-'; point = 3.50; }
  else if (pct >= 65) { grade = 'B+'; point = 3.25; }
  else if (pct >= 60) { grade = 'B'; point = 3.00; }
  else if (pct >= 55) { grade = 'B-'; point = 2.75; }
  else if (pct >= 50) { grade = 'C+'; point = 2.50; }
  else if (pct >= 45) { grade = 'C'; point = 2.25; }
  else if (pct >= 40) { grade = 'D'; point = 2.00; }

  document.getElementById('edResGrade').value = grade;
  document.getElementById('edResPoint').value = point.toFixed(2);
}

window.addEventListener('click', function(e) {
  ['editDetailResultModal', 'hideResultsModal', 'deactivateAccountModal', 'deleteAccountModal', 'suspendResultsModal', 'deleteAllResultsModal', 'officialAcademicEditModal', 'approveHistoricalModal'].forEach(function(id) {
    var modal = document.getElementById(id);
    if (modal && e.target === modal) {
      modal.style.display = 'none';
    }
  });
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    ['editDetailResultModal', 'hideResultsModal', 'deactivateAccountModal', 'deleteAccountModal', 'suspendResultsModal', 'deleteAllResultsModal', 'officialAcademicEditModal', 'approveHistoricalModal'].forEach(function(id) {
      var modal = document.getElementById(id);
      if (modal && modal.style.display && modal.style.display !== 'none') {
        modal.style.display = 'none';
      }
    });
  }
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
