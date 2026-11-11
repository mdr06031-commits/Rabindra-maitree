<?php
/**
 * ONE-PAGE COMPLETE STUDENT & RESULT MANAGEMENT SYSTEM
 * Rabindra Maitree University (RMU)
 *
 * Unified Admin Console controlling:
 * 1. Existing Student Profile Information (all 21 fields)
 * 2. Complete Academic Result Management (8 Semesters for Bachelor's / 4 Semesters for Master's)
 * 3. Live Auto-Grading (UGC 4.00 Scale) & Credit-Weighted SGPA/CGPA Calculation
 * 4. One-Page Actions: Save Student Info, Save Draft, Preview Transcript, Publish Results
 * 5. Automatic Student Portal Synchronization & In-App Notifications
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
}
ensure_student_password_schema();
$adminPageTitle = 'Student Management & Result Console';
$db = getDb();
$adminId = (int)($_SESSION['user_id'] ?? 1);

// Standard Semesters Titles (Supports 1-12 Semesters for custom approved curricula)
$standardSemesterTitles = [];
for ($__stIdx = 1; $__stIdx <= 12; $__stIdx++) {
    $standardSemesterTitles[$__stIdx] = get_semester_ordinal_title($__stIdx);
}

// Handle Unified Form Submission (Add, Edit, Account Controls, Result Visibility, Save Draft, Publish)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF security token verification failed. Please try again.');
    } elseif (!is_admin_manager_role()) {
        log_audit('STUDENT_ACTION_DENIED', "Unauthorized role '" . current_admin_role() . "' attempted action '" . ($_POST['action'] ?? '') . "' on students console");
        flash_set('error', 'Access denied: Only Administrator accounts may modify student records or academic results.');
        header("Location: " . APP_URL . "/admin/students.php");
        exit;
    } else {
        $action = $_POST['action'] ?? '';
        $submitAction = $_POST['submit_action'] ?? 'save_student_only';

        if ($action === 'reset_student_password') {
            $id = (int)($_POST['student_id_pk'] ?? ($_POST['id'] ?? 0));
            $res = reset_student_login_password($id, $adminId, 'admin_reset');
            if (!empty($res['success'])) {
                flash_set('success', "New temporary login password generated and saved securely for {$res['student_id']} ({$res['full_name']}). Active Student Portal sessions were invalidated.");
            } else {
                flash_set('error', $res['error'] ?? 'Could not reset student password.');
            }
            header("Location: " . APP_URL . "/admin/students.php" . ($id > 0 ? "?edit=" . $id . "#studentLoginCredentialsSection" : ""));
            exit;
        } elseif ($action === 'clear_temp_credential') {
            $id = (int)($_POST['student_id_pk'] ?? ($_POST['id'] ?? 0));
            clear_admin_temp_student_password($id);
            flash_set('info', 'Temporary password display cleared from your current administrator session.');
            header("Location: " . APP_URL . "/admin/students.php" . ($id > 0 ? "?edit=" . $id . "#studentLoginCredentialsSection" : ""));
            exit;
        } elseif ($action === 'activate_account') {
            $id = (int)($_POST['id'] ?? 0);
            $res = activate_student_account($id, $adminId, trim($_POST['reason'] ?? ''));
            flash_set($res['success'] ? 'success' : 'error', $res['success'] ? "Account {$res['student_id']} ({$res['full_name']}) activated successfully." : ($res['error'] ?? 'Activation failed.'));
            header("Location: " . APP_URL . "/admin/students.php" . (!empty($_POST['redirect_to_edit']) ? "?edit=" . $id : ""));
            exit;
        } elseif ($action === 'deactivate_account') {
            $id = (int)($_POST['id'] ?? 0);
            $res = deactivate_student_account($id, $adminId, trim($_POST['reason'] ?? ''));
            flash_set($res['success'] ? 'success' : 'error', $res['success'] ? "Account {$res['student_id']} ({$res['full_name']}) deactivated. Active sessions invalidated & academic records preserved." : ($res['error'] ?? 'Deactivation failed.'));
            header("Location: " . APP_URL . "/admin/students.php" . (!empty($_POST['redirect_to_edit']) ? "?edit=" . $id : ""));
            exit;
        } elseif ($action === 'show_results') {
            $id = (int)($_POST['id'] ?? 0);
            $res = set_student_result_visibility($id, true, $adminId);
            flash_set($res['success'] ? 'success' : 'error', $res['success'] ? "Results are now VISIBLE (Show Result) in Student Portal for {$res['full_name']}." : ($res['error'] ?? 'Action failed.'));
            header("Location: " . APP_URL . "/admin/students.php" . (!empty($_POST['redirect_to_edit']) ? "?edit=" . $id : ""));
            exit;
        } elseif ($action === 'hide_results') {
            $id = (int)($_POST['id'] ?? 0);
            $res = set_student_result_visibility($id, false, $adminId, trim($_POST['reason'] ?? ''));
            flash_set($res['success'] ? 'success' : 'error', $res['success'] ? "Results are now HIDDEN (Hide Result) from student view for {$res['full_name']}. Original academic data preserved." : ($res['error'] ?? 'Action failed.'));
            header("Location: " . APP_URL . "/admin/students.php" . (!empty($_POST['redirect_to_edit']) ? "?edit=" . $id : ""));
            exit;
        } elseif ($action === 'add' || $action === 'edit') {
            $studentId = trim($_POST['student_id'] ?? '');
            $rollNumber = trim($_POST['roll_number'] ?? '');
            $applicationNumber = trim($_POST['application_number'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $fatherName = trim($_POST['father_name'] ?? '');
            $motherName = trim($_POST['mother_name'] ?? '');
            $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
            $gender = in_array($_POST['gender'] ?? '', ['Male', 'Female', 'Other']) ? $_POST['gender'] : 'Male';
            $presentAddress = trim($_POST['present_address'] ?? '');
            $permanentAddress = trim($_POST['permanent_address'] ?? '');
            $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
            $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $batch = trim($_POST['batch'] ?? '');
            $progStructInput = get_program_academic_structure((int)($programId ?: 1));
            $degreeTypeInput = trim($_POST['degree_type'] ?? '');
            if (!in_array($degreeTypeInput, ['Bachelor / Honours', 'B.Ed', "Master's"], true)) {
                $degreeTypeInput = $progStructInput['degree_type'];
            }
            $configuredSemsInput = isset($_POST['configured_semesters']) && (int)$_POST['configured_semesters'] > 0
                ? max(1, min(12, (int)$_POST['configured_semesters']))
                : (int)$progStructInput['total_semesters'];

            if (isset($_POST['duration_years']) && $_POST['duration_years'] !== '' && is_numeric($_POST['duration_years'])) {
                $durationYearsInput = max(0.5, min(6.0, round((float)$_POST['duration_years'], 1)));
            } elseif ($configuredSemsInput === (int)$progStructInput['total_semesters']) {
                $durationYearsInput = (float)$progStructInput['duration_years'];
            } else {
                $durationYearsInput = ($configuredSemsInput === 8 ? 4.0 : ($configuredSemsInput === 4 ? 2.0 : ($configuredSemsInput === 3 ? 1.5 : ($configuredSemsInput === 2 ? 1.0 : max(0.5, min(6.0, round($configuredSemsInput * 0.5, 1)))))));
            }
            $progDurationLabel = format_duration_years_label($durationYearsInput, $configuredSemsInput);

            $reqCreditsInput = max(10, (int)($progStructInput['total_credits'] ?? ($configuredSemsInput * 15)));
            $academicSessionInput = trim($_POST['academic_session'] ?? '');
            if ($academicSessionInput === '') {
                $academicSessionInput = $batch !== '' ? $batch : get_current_academic_session_name('Fall 2026');
            }
            if ($batch === '') {
                $batch = $academicSessionInput;
            }
            // CRITICAL: Never automatically overwrite or derive passing_year from expected year
            $hasPassingYearField = array_key_exists('passing_year', $_POST);
            $passingYearInput = ($hasPassingYearField && trim((string)$_POST['passing_year']) !== '') ? trim((string)$_POST['passing_year']) : null;
            $expCalcInput = calculate_expected_passing_year($academicSessionInput, $durationYearsInput);
            $expectedPassingYearInput = $expCalcInput ? (int)$expCalcInput['expected_year'] : null;

            $pubDateInput = trim($_POST['result_publication_date'] ?? '');
            if ($pubDateInput !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pubDateInput)) {
                $pubDateInput = '';
            }
            $currentYear = trim($_POST['current_year'] ?? '1st Year');
            $currentSemester = trim($_POST['current_semester'] ?? '1st Semester');
            $cgpa = (float)($_POST['cgpa'] ?? 0.00);
            $creditsCompleted = (int)($_POST['credits_completed'] ?? 0);
            $bloodGroup = trim($_POST['blood_group'] ?? '');
            $homeDistrict = trim($_POST['home_district'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['Active', 'Graduated', 'Suspended', 'Deactivated']) ? $_POST['status'] : 'Active';
            $rawPassword = trim((string)($_POST['password'] ?? ''));
            $password = ($rawPassword !== '') ? $rawPassword : generate_secure_student_password(12);

            if ($action === 'add' && empty($studentId)) {
                $studentId = generate_next_student_id($db);
            }
            if ($username === '' && $studentId !== '') {
                $username = $studentId;
            }

            if (empty($fullName) || empty($email) || empty($phone)) {
                flash_set('error', 'Full Name, Official Email, and Mobile Phone are required.');
            } else {
                $targetDbId = 0;

                // 1. Process Student Profile
                if ($action === 'add') {
                    // Check duplicate identifiers (Student ID, Roll No, Application ID, Email) across students & admissions
                    $dupErr = validate_unique_student_identifiers($db, $studentId, $rollNumber, $applicationNumber, $email, 0);
                    if ($dupErr !== null) {
                        flash_set('error', $dupErr);
                        header("Location: " . APP_URL . "/admin/students.php?action=enroll");
                        exit;
                    } else {
                        ensure_student_password_schema($db);
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        try {
                            $stmt = $db->prepare("
                                INSERT INTO students 
                                (student_id, roll_number, application_number, username, full_name, father_name, mother_name, email, phone, password_hash, password_updated_at, password_updated_by, password_source, must_change_password, program_id, degree_type, department_id, batch, academic_session, passing_year, expected_passing_year, duration_years, program_duration, total_semesters, result_publication_date, current_year, current_semester, cgpa, credits_completed, blood_group, home_district, dob, gender, present_address, permanent_address, total_credits_required, status, results_visible, result_access_status, session_version)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'system_generated', 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'Active', 1)
                            ");
                            $stmt->execute([
                                $studentId, $rollNumber !== '' ? $rollNumber : null, $applicationNumber !== '' ? $applicationNumber : null, $username,
                                $fullName, $fatherName, $motherName, $email, $phone, $passwordHash,
                                $adminId,
                                $programId, $degreeTypeInput, $deptId, $batch, $academicSessionInput, $passingYearInput, $expectedPassingYearInput, $durationYearsInput, $progDurationLabel, $configuredSemsInput, $pubDateInput !== '' ? $pubDateInput : null,
                                $currentYear, $currentSemester,
                                $cgpa, $creditsCompleted, $bloodGroup, $homeDistrict, $dob, $gender, $presentAddress, $permanentAddress, $reqCreditsInput, $status
                            ]);
                            $targetDbId = (int)$db->lastInsertId();
                            store_admin_temp_student_password($targetDbId, $password, $adminId, $passwordHash);
                            sync_student_semester_results($targetDbId);

                            // Send Welcome In-App Notification
                            create_student_notification(
                                $targetDbId,
                                "Welcome to Rabindra Maitree University!",
                                "Your official Student Portal account has been created. Student ID: {$studentId}.",
                                'general'
                            );
                            log_audit('STUDENT_ADDED_ONE_PAGE', "Enrolled student: {$fullName} ({$studentId})", $adminId);
                        } catch (PDOException $e) {
                            if (is_retryable_db_concurrency_exception($e)) {
                                flash_set('error', 'Duplicate Student ID, Admission Roll Number, Application ID, or Email detected. Each student must have unique identifiers.');
                            } else {
                                flash_set('error', 'Could not create student record due to a database constraint error.');
                            }
                            header("Location: " . APP_URL . "/admin/students.php?action=enroll");
                            exit;
                        }
                    }
                } elseif ($action === 'edit') {
                    $targetDbId = (int)($_POST['id'] ?? 0);
                    if ($targetDbId > 0) {
                        $currStStmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
                        $currStStmt->execute([$targetDbId]);
                        $currSt = $currStStmt->fetch(PDO::FETCH_ASSOC);

                        $dupErr = validate_unique_student_identifiers($db, null, $rollNumber, $applicationNumber, $email, $targetDbId);
                        if ($dupErr !== null) {
                            flash_set('error', $dupErr);
                            header("Location: " . APP_URL . "/admin/students.php?edit=" . $targetDbId);
                            exit;
                        }

                        $finalStatus = ($currSt && $currSt['status'] === 'Deleted') ? 'Deleted' : $status;
                        $bumpSession = ($finalStatus !== 'Active' && $currSt && $currSt['status'] !== $finalStatus) ? ", session_version = session_version + 1" : "";
                        $pubDateVal = ($pubDateInput !== '') ? $pubDateInput : ($currSt['result_publication_date'] ?? null);

                        // Preserve existing academic structure / passing year if partial POST omitted those keys
                        if ($currSt) {
                            if (!$hasPassingYearField) {
                                $passingYearInput = $currSt['passing_year'] !== null ? (string)$currSt['passing_year'] : null;
                            }
                            if (!array_key_exists('academic_session', $_POST)) {
                                $academicSessionInput = !empty($currSt['academic_session']) ? (string)$currSt['academic_session'] : $academicSessionInput;
                            }
                            if (!isset($_POST['configured_semesters']) && (int)$programId === (int)($currSt['program_id'] ?? 0) && !empty($currSt['total_semesters'])) {
                                $configuredSemsInput = max(1, min(12, (int)$currSt['total_semesters']));
                            }
                            if (!isset($_POST['duration_years']) && (int)$programId === (int)($currSt['program_id'] ?? 0) && !empty($currSt['duration_years'])) {
                                $durationYearsInput = max(0.5, min(6.0, round((float)$currSt['duration_years'], 1)));
                            }
                            $progDurationLabel = format_duration_years_label($durationYearsInput, $configuredSemsInput);
                            $expCalcEdit = calculate_expected_passing_year($academicSessionInput, $durationYearsInput);
                            $expectedPassingYearInput = $expCalcEdit ? (int)$expCalcEdit['expected_year'] : null;
                        }

                        try {
                            $stmt = $db->prepare("
                                UPDATE students 
                                SET full_name = ?, roll_number = ?, application_number = ?, username = ?,
                                    father_name = ?, mother_name = ?, email = ?, phone = ?,
                                    program_id = ?, degree_type = ?, department_id = ?, batch = ?, academic_session = ?, passing_year = ?, expected_passing_year = ?, duration_years = ?, program_duration = ?, total_semesters = ?, result_publication_date = ?, total_credits_required = ?,
                                    current_year = ?, current_semester = ?,
                                    cgpa = ?, credits_completed = ?, blood_group = ?, home_district = ?, dob = ?, gender = ?,
                                    present_address = ?, permanent_address = ?, status = ? {$bumpSession}
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $fullName, $rollNumber !== '' ? $rollNumber : null, $applicationNumber !== '' ? $applicationNumber : null,
                                $username !== '' ? $username : ($currSt['student_id'] ?? $studentId),
                                $fatherName, $motherName, $email, $phone,
                                $programId, $degreeTypeInput, $deptId, $batch, $academicSessionInput, $passingYearInput, $expectedPassingYearInput, $durationYearsInput, $progDurationLabel, $configuredSemsInput, $pubDateVal, $reqCreditsInput,
                                $currentYear, $currentSemester,
                                $cgpa, $creditsCompleted, $bloodGroup, $homeDistrict, $dob, $gender,
                                $presentAddress, $permanentAddress, $finalStatus, $targetDbId
                            ]);
                        } catch (PDOException $e) {
                            if (is_retryable_db_concurrency_exception($e)) {
                                flash_set('error', 'Duplicate Admission Roll Number, Application ID, or Email detected. Each student must have unique identifiers.');
                            } else {
                                flash_set('error', 'Could not update student record due to a database constraint error.');
                            }
                            header("Location: " . APP_URL . "/admin/students.php?edit=" . $targetDbId);
                            exit;
                        }

                        if ($currSt) {
                            // Keep linked admission dossier aligned when edited by administrator
                            if (!empty($currSt['student_id'])) {
                                $db->prepare("
                                    UPDATE admissions
                                    SET academic_session = ?, passing_year = ?, expected_passing_year = ?, program_id = ?, department_id = ?, degree_type = ?
                                    WHERE student_id = ?
                                ")->execute([$academicSessionInput, $passingYearInput, $expectedPassingYearInput, $programId, $deptId, $degreeTypeInput, $currSt['student_id']]);
                            }

                            $acadReason = trim($_POST['academic_edit_reason'] ?? '') ?: 'Updated from Admin Student Management Console';
                            $acadDiffPairs = [
                                'academic_session'      => [$currSt['academic_session'] ?? null, $academicSessionInput],
                                'passing_year'          => [$currSt['passing_year'] ?? null, $passingYearInput],
                                'program_id'            => [(string)($currSt['program_id'] ?? ''), (string)$programId],
                                'degree_type'           => [$currSt['degree_type'] ?? null, $degreeTypeInput],
                                'duration_years'        => [isset($currSt['duration_years']) ? (string)(float)$currSt['duration_years'] : null, (string)$durationYearsInput],
                                'total_semesters'       => [(string)($currSt['total_semesters'] ?? ''), (string)$configuredSemsInput],
                                'expected_passing_year' => [$currSt['expected_passing_year'] !== null ? (string)$currSt['expected_passing_year'] : null, $expectedPassingYearInput !== null ? (string)$expectedPassingYearInput : null],
                            ];
                            foreach ($acadDiffPairs as $fKey => $pair) {
                                if ((string)($pair[0] ?? '') !== (string)($pair[1] ?? '')) {
                                    log_academic_edit_history('student', $targetDbId, $currSt['student_id'], $adminId, $fKey, $pair[0] !== null ? (string)$pair[0] : null, $pair[1] !== null ? (string)$pair[1] : null, $acadReason);
                                }
                            }
                        }

                        sync_student_semester_results($targetDbId);

                        // Password reset if provided
                        if (!empty($_POST['new_password']) && $finalStatus !== 'Deleted') {
                            ensure_student_password_schema($db);
                            $newPass = trim((string)$_POST['new_password']);
                            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                            $passStmt = $db->prepare("UPDATE students SET password_hash = ?, password_updated_at = NOW(), password_updated_by = ?, password_source = 'admin_reset', must_change_password = 1, session_version = session_version + 1 WHERE id = ?");
                            $passStmt->execute([$newHash, $adminId, $targetDbId]);
                            if (!empty($currSt['student_id'])) {
                                $db->prepare("UPDATE admissions SET portal_password = NULL WHERE student_id = ?")->execute([$currSt['student_id']]);
                            }
                            store_admin_temp_student_password($targetDbId, $newPass, $adminId, $newHash);
                            create_student_notification(
                                $targetDbId,
                                'Student Portal Password Reset',
                                'Your Student Portal login password was reset by university administration and active sessions were signed out.',
                                'account_status'
                            );
                            log_audit('STUDENT_PASSWORD_RESET', "Reset password and invalidated sessions for student ID #{$targetDbId}", $adminId);
                        }
                        log_audit('STUDENT_UPDATED_ONE_PAGE', "Updated student ID #{$targetDbId} ({$fullName})", $adminId);
                    }
                }

                // Acceptance gate check for any academic result mutation
                $isResultActionRequested = in_array($submitAction, ['update_cgpa_and_generate', 'auto_generate_estimated', 'update_cgpa_only', 'verify_all_as_official', 'save_draft', 'publish_results'], true)
                    || strpos($submitAction, 'verify_semester_') === 0;
                if ($targetDbId > 0 && $isResultActionRequested) {
                    $resGate = can_manage_student_results($targetDbId);
                    if (empty($resGate['allowed'])) {
                        flash_set('error', $resGate['reason']);
                        header("Location: " . APP_URL . "/admin/students.php?edit=" . $targetDbId);
                        exit;
                    }
                }

                // Handle One-Click CGPA Update & Auto-Estimated Results Generation
                if ($targetDbId > 0 && in_array($submitAction, ['update_cgpa_and_generate', 'auto_generate_estimated', 'update_cgpa_only'])) {
                    $targetCgpa = (float)($_POST['target_cgpa_input'] ?? $_POST['cgpa'] ?? 3.50);
                    $targetCgpa = max(2.00, min(4.00, round($targetCgpa, 2)));
                    $genSession = trim($_POST['gen_academic_session'] ?? $academicSessionInput);
                    $genPubDate = trim($_POST['gen_publication_date'] ?? $pubDateInput);
                    if ($genPubDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $genPubDate)) {
                        $genPubDate = date('Y-m-d');
                    }

                    if ($submitAction === 'update_cgpa_only') {
                        $db->prepare("UPDATE students SET cgpa = ? WHERE id = ?")->execute([$targetCgpa, $targetDbId]);
                        log_audit('STUDENT_CGPA_UPDATED', "Admin updated CGPA to {$targetCgpa} for student #{$targetDbId}");
                        flash_set('success', "Student CGPA updated to " . number_format($targetCgpa, 2) . ".");
                    } else {
                        $genRes = generate_estimated_student_results($targetDbId, $targetCgpa, true, $adminId, [
                            'academic_session' => $genSession,
                            'publication_date' => $genPubDate,
                            'total_semesters'  => $configuredSemsInput,
                        ]);
                        if (!empty($genRes['success'])) {
                            flash_set('success', "One-Click CGPA Update Complete! Updated CGPA to " . number_format($targetCgpa, 2) . " across {$genRes['total_semesters']} semesters ({$genRes['inserted_courses']} subjects, Session: {$genRes['academic_session']}, Publication Date: {$genRes['publication_date']}). Labelled: ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT.");
                        } else {
                            flash_set('error', $genRes['error'] ?? 'Failed to generate estimated results.');
                        }
                    }
                    header("Location: " . APP_URL . "/admin/students.php?edit=" . $targetDbId);
                    exit;
                }

                // Handle Administrator Verification Workflow (Convert Estimated -> Verified Official)
                if ($targetDbId > 0 && strpos($submitAction, 'verify_semester_') === 0) {
                    $semNum = (int)str_replace('verify_semester_', '', $submitAction);
                    $semTitle = $standardSemesterTitles[$semNum] ?? "Semester {$semNum}";
                    $aff = verify_semester_results_as_official($targetDbId, $semTitle, $adminId);
                    flash_set('success', "Official Verification Complete: {$aff} course result(s) in {$semTitle} have been verified and recorded as Official University Examination Records!");
                    header("Location: " . APP_URL . "/admin/students.php?edit=" . $targetDbId);
                    exit;
                }

                if ($targetDbId > 0 && $submitAction === 'verify_all_as_official') {
                    $stmt = $db->prepare("
                        UPDATE student_results 
                        SET is_estimated = 0, 
                            result_type = 'Verified', 
                            publish_status = 'Published',
                            is_published = 1,
                            published_by = ?, 
                            published_at = NOW(),
                            correction_reason = 'Verified by administrator as official examination record'
                        WHERE student_id = ? AND is_estimated = 1
                    ");
                    $stmt->execute([$adminId, $targetDbId]);
                    $aff = $stmt->rowCount();
                    recalculate_student_cgpa($targetDbId);
                    log_audit('RESULTS_VERIFIED_OFFICIAL', "Admin verified all {$aff} estimated courses as official for student #{$targetDbId}");
                    create_student_notification(
                        $targetDbId,
                        "Official Academic Results Confirmed",
                        "All academic results across your degree curriculum have been officially verified and confirmed by the Office of the Controller of Examinations.",
                        'result'
                    );
                    flash_set('success', "All {$aff} estimated course results have been verified and confirmed as Official University Records!");
                    header("Location: " . APP_URL . "/admin/students.php?edit=" . $targetDbId);
                    exit;
                }

                // 2. Process Academic Results (if Save Draft or Publish was clicked)
                if ($targetDbId > 0 && ($submitAction === 'save_draft' || $submitAction === 'publish_results')) {
                    $isPublished = ($submitAction === 'publish_results') ? 1 : 0;
                    $publishStatus = ($submitAction === 'publish_results') ? 'Published' : 'Draft';
                    $pubAt = $isPublished ? date('Y-m-d H:i:s') : null;
                    $pubBy = $isPublished ? $adminId : null;
                    $reason = trim($_POST['correction_reason'] ?? 'Unified one-page academic result entry & update');

                    $submittedResults = $_POST['results'] ?? [];

                    // Fetch existing result IDs to detect updates vs inserts vs deletions
                    $existingResStmt = $db->prepare("SELECT id FROM student_results WHERE student_id = ?");
                    $existingResStmt->execute([$targetDbId]);
                    $existingResultIds = $existingResStmt->fetchAll(PDO::FETCH_COLUMN);
                    $keptResultIds = [];

                    $insRes = $db->prepare("
                        INSERT INTO student_results 
                        (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, is_published, publish_status, published_at, published_by, correction_reason)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Semester Final Examination', ?, ?, ?, ?, ?)
                    ");

                    $updRes = $db->prepare("
                        UPDATE student_results 
                        SET semester = ?, course_code = ?, course_name = ?, credit = ?, full_marks = ?, marks = ?, letter_grade = ?, grade_point = ?, is_published = ?, publish_status = ?, published_at = ?, published_by = ?, correction_reason = ?
                        WHERE id = ? AND student_id = ?
                    ");

                    foreach ($submittedResults as $semIndex => $courses) {
                        $semNum = (int)$semIndex;
                        $semTitle = $standardSemesterTitles[$semNum] ?? "Semester {$semNum}";

                        if (!empty($courses) && is_array($courses)) {
                            foreach ($courses as $c) {
                                $code = strtoupper(trim($c['course_code'] ?? ''));
                                $name = trim($c['course_name'] ?? '');
                                $credit = (float)($c['credit'] ?? 3.0);
                                $fullMarks = (int)($c['full_marks'] ?? 100);
                                $marks = (isset($c['marks']) && $c['marks'] !== '') ? (float)$c['marks'] : null;
                                $cid = (int)($c['id'] ?? 0);

                                if (empty($code) && empty($name)) {
                                    continue; // Skip blank template rows
                                }

                                if ($marks !== null) {
                                    $gr = calculate_grade_from_marks($marks);
                                    $grade = $gr['letter_grade'];
                                    $gp = $gr['grade_point'];
                                } else {
                                    $grade = trim($c['letter_grade'] ?? 'A+');
                                    $gp = (float)($c['grade_point'] ?? 4.00);
                                }

                                if ($cid > 0 && in_array($cid, $existingResultIds)) {
                                    $updRes->execute([
                                        $semTitle, $code, $name, $credit, $fullMarks, $marks, $grade, $gp,
                                        $isPublished, $publishStatus, $pubAt, $pubBy, $reason, $cid, $targetDbId
                                    ]);
                                    $keptResultIds[] = $cid;
                                } else {
                                    $insRes->execute([
                                        $targetDbId, $semTitle, $code, $name, $credit, $fullMarks, $marks, $grade, $gp,
                                        $isPublished, $publishStatus, $pubAt, $pubBy, $reason
                                    ]);
                                    $keptResultIds[] = (int)$db->lastInsertId();
                                }
                            }
                        }
                    }

                    // Delete rows removed by admin from the table
                    $toDelete = array_diff($existingResultIds, $keptResultIds);
                    if (!empty($toDelete)) {
                        $delRes = $db->prepare("DELETE FROM student_results WHERE id = ? AND student_id = ?");
                        foreach ($toDelete as $delId) {
                            $delRes->execute([$delId, $targetDbId]);
                        }
                    }

                    // Recalculate CGPA and credits
                    $resMetrics = recalculate_student_cgpa($targetDbId);

                    if ($submitAction === 'publish_results') {
                        // Check if all configured semesters are completed
                        $semCountStmt = $db->prepare("SELECT COUNT(DISTINCT semester) FROM student_results WHERE student_id = ? AND is_published = 1");
                        $semCountStmt->execute([$targetDbId]);
                        $semCount = (int)$semCountStmt->fetchColumn();
                        $expectedSems = max(1, (int)$configuredSemsInput);
                        if ($semCount >= $expectedSems) {
                            $db->prepare("UPDATE students SET status = 'Graduated', current_semester = 'Completed' WHERE id = ?")->execute([$targetDbId]);
                        }

                        create_student_notification(
                            $targetDbId,
                            "Official Academic Results Published",
                            "Your semester results and grade transcript have been officially published to your Student Portal. CGPA: {$resMetrics['cgpa']}.",
                            'result_published'
                        );
                        log_audit('RESULTS_PUBLISHED_ONE_PAGE', "Published results for Student #{$targetDbId} via One-Page Console");
                        flash_set('success', "Student record and all academic results successfully PUBLISHED to the Student Portal! Updated CGPA: {$resMetrics['cgpa']}.");
                    } else {
                        log_audit('RESULTS_DRAFT_ONE_PAGE', "Saved results as Draft for Student #{$targetDbId} via One-Page Console");
                        flash_set('info', "Student record and academic results successfully saved as DRAFT (hidden from Student Portal). Updated CGPA: {$resMetrics['cgpa']}.");
                    }
                } elseif ($targetDbId > 0) {
                    flash_set('success', "Student profile information saved successfully.");
                }

                if ($targetDbId > 0) {
                    header("Location: " . APP_URL . "/admin/students.php?edit=" . $targetDbId);
                    exit;
                }
            }
        } elseif ($action === 'delete' || $action === 'delete_student_account') {
            $id = (int)($_POST['id'] ?? 0);
            $confirmInput = trim($_POST['confirm_student_id'] ?? '');
            $confirmCheck = !empty($_POST['confirm_permanent_delete']);
            $reason = trim($_POST['reason'] ?? '');

            if ($id > 0) {
                $st = $db->prepare("SELECT student_id, full_name FROM students WHERE id = ? LIMIT 1");
                $st->execute([$id]);
                $sData = $st->fetch(PDO::FETCH_ASSOC);

                if (!$sData) {
                    flash_set('error', 'Student account not found.');
                } elseif (!$confirmCheck || ($confirmInput !== $sData['student_id'] && strtoupper($confirmInput) !== 'DELETE')) {
                    flash_set('error', "Permanent deletion cancelled: you must tick the confirmation checkbox and type '{$sData['student_id']}' or 'DELETE' to confirm.");
                } else {
                    $delRes = delete_student_account_safely($id, $adminId, $reason);
                    if ($delRes['success']) {
                        flash_set('success', "Student account {$sData['student_id']} ({$sData['full_name']}) permanently deleted. Login credentials revoked & {$delRes['results_retained']} academic record(s) archived under Retention Policy (Archive #{$delRes['archive_id']}).");
                    } else {
                        flash_set('error', $delRes['error'] ?? 'Could not delete student account.');
                    }
                }
            }
            header("Location: " . APP_URL . "/admin/students.php");
            exit;
        }
    }
}

// Search & Filter for Directory
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'All';
$resultStatusFilter = $_GET['result_status'] ?? 'all';
$programFilter = (int)($_GET['program_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($statusFilter !== 'All') {
    $where[] = "s.status = ?";
    $params[] = $statusFilter;
}

if ($programFilter > 0) {
    $where[] = "s.program_id = ?";
    $params[] = $programFilter;
}

if ($resultStatusFilter === 'hidden') {
    $where[] = "COALESCE(s.results_visible, 1) = 0";
} elseif ($resultStatusFilter === 'published') {
    $where[] = "COALESCE(s.results_visible, 1) = 1 AND EXISTS (SELECT 1 FROM student_results srp WHERE srp.student_id = s.id AND srp.is_published = 1)";
} elseif ($resultStatusFilter === 'pending') {
    $where[] = "COALESCE(s.results_visible, 1) = 1 AND NOT EXISTS (SELECT 1 FROM student_results srp WHERE srp.student_id = s.id AND srp.is_published = 1)";
}

if (!empty($search)) {
    $where[] = "(s.student_id LIKE ? OR s.roll_number LIKE ? OR s.application_number LIKE ? OR s.username LIKE ? OR s.full_name LIKE ? OR s.email LIKE ? OR s.phone LIKE ? OR s.batch LIKE ?)";
    $like = "%$search%";
    for ($si = 0; $si < 8; $si++) {
        $params[] = $like;
    }
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Summary Statistics
$totalStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status != 'Deleted'")->fetchColumn();
$activeStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
$deactivatedStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status IN ('Deactivated', 'Suspended')")->fetchColumn();
$deletedStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Deleted'")->fetchColumn();
$hiddenResultStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status != 'Deleted' AND COALESCE(results_visible, 1) = 0")->fetchColumn();
$graduatedStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Graduated'")->fetchColumn();
$totalFeesCollected = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM student_fees WHERE status = 'Paid'")->fetchColumn();

// Total Filtered Count for Pagination
$countSql = "SELECT COUNT(*) FROM students s $whereSql";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Fetch Students for Directory along with result counts
$sql = "
    SELECT s.*, p.title_en AS program_title, p.degree_level, d.name_en AS dept_name,
           COALESCE(rs.total_res, 0) AS total_results_cnt,
           COALESCE(rs.pub_res, 0) AS published_results_cnt
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN (
        SELECT student_id,
               COUNT(*) AS total_res,
               SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) AS pub_res
        FROM student_results
        GROUP BY student_id
    ) rs ON rs.student_id = s.id
    $whereSql
    ORDER BY (s.status = 'Deleted') ASC, s.id DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k + 1, $v);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll();

// Programs with Degree Level, Degree Type & Semesters for Dropdowns
$programs = $db->query("SELECT id, title_en, degree_level, degree_type, duration, duration_years, total_semesters, total_credits FROM programs ORDER BY sort_order, title_en")->fetchAll(PDO::FETCH_ASSOC);
$departments = $db->query("SELECT id, name_en FROM departments ORDER BY name_en")->fetchAll(PDO::FETCH_ASSOC);
$allSessionsList = get_academic_sessions(false, 'DESC');
$allPassingYearsList = get_passing_years(false, 'DESC');
$defaultActiveSession = get_current_academic_session_name('Fall 2026');

// Check if One-Page Console is active (Edit student or Enroll New Student)
$isConsoleView = isset($_GET['edit']) || (isset($_GET['action']) && $_GET['action'] === 'enroll');
$editStudent = null;
$editStudentBadges = null;
$editProgStruct = null;
$editResultGate = ['allowed' => true, 'reason' => ''];
$existingResultsBySem = [];

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $st = $db->prepare("SELECT s.*, p.title_en AS program_title, p.degree_level, p.degree_type AS prog_degree_type, p.duration AS prog_duration, p.duration_years AS prog_duration_years, p.total_semesters AS prog_total_semesters FROM students s LEFT JOIN programs p ON s.program_id = p.id WHERE s.id = ?");
    $st->execute([$editId]);
    $editStudent = $st->fetch(PDO::FETCH_ASSOC);

    if ($editStudent) {
        $editStudentBadges = get_student_status_badges($editStudent);
        $editProgStruct = get_student_program_structure($editStudent);
        $editResultGate = can_manage_student_results($editId);
        $resStmt = $db->prepare("SELECT * FROM student_results WHERE student_id = ? ORDER BY id ASC");
        $resStmt->execute([$editId]);
        $allRes = $resStmt->fetchAll(PDO::FETCH_ASSOC);

        $hasAnyEstimatedInStudent = false;
        foreach ($allRes as $r) {
            $sNum = get_semester_number($r['semester']);
            if ($sNum >= 1 && $sNum <= 12) {
                $existingResultsBySem[$sNum][] = $r;
            }
            if (!empty($r['is_estimated'])) {
                $hasAnyEstimatedInStudent = true;
            }
        }
    }
}

include __DIR__ . '/includes/admin-header.php';
?>

<style>
  .stu-cgpa-span { grid-column: span 2; }
  @media (max-width: 768px) {
    .stu-cgpa-span { grid-column: span 1 !important; }
    .stu-dir-actions { white-space: normal !important; display: flex; flex-wrap: wrap; gap: 0.3rem; justify-content: flex-end; }
    #onePageStudentConsole { padding: 1.15rem !important; }
  }
</style>

<!-- Top Banner & View Switcher -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem;">
      <i class="fas fa-id-card-alt" style="color: var(--rmu-primary);"></i> Student Management &bull; One-Page Result Console
    </h2>
    <span style="color: #64748b; font-size: 0.88rem;">
      Rabindra Maitree University &bull; Complete Student Management, Account Control, Result Visibility &amp; Transcript Publishing
    </span>
  </div>

  <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
    <a href="<?= APP_URL ?>/admin/students.php" class="btn-primary" style="background: <?= !$isConsoleView ? 'var(--rmu-primary)' : '#64748b' ?>; padding: 0.55rem 1.15rem; font-size: 0.88rem; text-decoration: none;">
      <i class="fas fa-list"></i> Enrolled Students Directory (<span id="dirTopTotalBadge"><?= $totalStudents ?></span>)
    </a>
    <a href="<?= APP_URL ?>/admin/students.php?action=enroll" class="btn-primary" style="background: <?= ($isConsoleView && !$editStudent) ? '#059669' : '#0284c7' ?>; padding: 0.55rem 1.25rem; font-size: 0.88rem; text-decoration: none;">
      <i class="fas fa-user-plus"></i> Enroll Student &amp; Manage Results
    </a>
  </div>
</div>

<?php if (!$isConsoleView): ?>

  <!-- ========================================================================= -->
  <!-- VIEW 1: ENROLLED STUDENTS DIRECTORY (STATISTICS & TABLE)                  -->
  <!-- ========================================================================= -->

  <div id="studentsDirAjaxAlert" style="display: none; margin-bottom: 1.5rem;"></div>

  <!-- Stat Cards Strip -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 195px), 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="stat-card">
      <div class="stat-icon" style="background: #e0e7ff; color: #4338ca;">
        <i class="fas fa-user-graduate"></i>
      </div>
      <div>
        <div id="dirStatTotalStudents" style="font-size: 1.55rem; font-weight: 800; color: #0f172a;"><?= $totalStudents ?></div>
        <div style="font-size: 0.82rem; color: #64748b;">Registered Students</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background: #dcfce7; color: #15803d;">
        <i class="fas fa-user-check"></i>
      </div>
      <div>
        <div id="dirStatActiveStudents" style="font-size: 1.55rem; font-weight: 800; color: #0f172a;"><?= $activeStudents ?></div>
        <div style="font-size: 0.82rem; color: #64748b;">Active Enrolled</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background: #fef3c7; color: #b45309;">
        <i class="fas fa-user-slash"></i>
      </div>
      <div>
        <div id="dirStatDeactivatedStudents" style="font-size: 1.55rem; font-weight: 800; color: #0f172a;"><?= $deactivatedStudents ?></div>
        <div style="font-size: 0.82rem; color: #64748b;">Deactivated / Suspended</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background: #fee2e2; color: #b91c1c;">
        <i class="fas fa-eye-slash"></i>
      </div>
      <div>
        <div id="dirStatHiddenResultStudents" style="font-size: 1.55rem; font-weight: 800; color: #0f172a;"><?= $hiddenResultStudents ?></div>
        <div id="dirStatHiddenSubLabel" style="font-size: 0.82rem; color: #64748b;">Results Hidden <?= $deletedStudents > 0 ? "&bull; {$deletedStudents} Deleted" : '' ?></div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background: #e0f2fe; color: #0369a1;">
        <i class="fas fa-money-check-alt"></i>
      </div>
      <div>
        <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a;">৳<?= number_format($totalFeesCollected) ?></div>
        <div style="font-size: 0.82rem; color: #64748b;">Total Fees Collected</div>
      </div>
    </div>
  </div>

  <!-- Search & Filter Console -->
  <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 1.25rem 1.5rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
    <form action="<?= APP_URL ?>/admin/students.php" method="GET" style="display: flex; gap: 0.85rem; flex-wrap: wrap; align-items: center;">
      <div style="flex: 2.2; min-width: min(100%, 240px);">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by Student ID, Application ID (RMU-2026-0001), Admission Roll (2026-0001), Name, Phone..." class="form-control" style="padding: 0.65rem 1rem;">
      </div>

      <div style="flex: 1.2; min-width: min(100%, 170px);">
        <select name="program_id" class="form-select" style="padding: 0.65rem 0.9rem;">
          <option value="0">All Degree Programs</option>
          <?php foreach ($programs as $pr): ?>
            <option value="<?= $pr['id'] ?>" <?= ($programFilter === (int)$pr['id']) ? 'selected' : '' ?>>
              <?= e($pr['title_en']) ?> (<?= e($pr['degree_level']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width: min(100%, 145px);">
        <select name="status" class="form-select" style="padding: 0.65rem 0.9rem;">
          <option value="All" <?= ($statusFilter === 'All') ? 'selected' : '' ?>>Account: All</option>
          <option value="Active" <?= ($statusFilter === 'Active') ? 'selected' : '' ?>>Active</option>
          <option value="Deactivated" <?= ($statusFilter === 'Deactivated') ? 'selected' : '' ?>>Deactivated</option>
          <option value="Suspended" <?= ($statusFilter === 'Suspended') ? 'selected' : '' ?>>Suspended</option>
          <option value="Graduated" <?= ($statusFilter === 'Graduated') ? 'selected' : '' ?>>Graduated</option>
          <option value="Deleted" <?= ($statusFilter === 'Deleted') ? 'selected' : '' ?>>Deleted</option>
        </select>
      </div>

      <div style="min-width: min(100%, 155px);">
        <select name="result_status" class="form-select" style="padding: 0.65rem 0.9rem;">
          <option value="all" <?= ($resultStatusFilter === 'all') ? 'selected' : '' ?>>Results: All</option>
          <option value="published" <?= ($resultStatusFilter === 'published') ? 'selected' : '' ?>>Result Published</option>
          <option value="hidden" <?= ($resultStatusFilter === 'hidden') ? 'selected' : '' ?>>Result Hidden</option>
          <option value="pending" <?= ($resultStatusFilter === 'pending') ? 'selected' : '' ?>>Pending</option>
        </select>
      </div>

      <button type="submit" class="btn-primary" style="padding: 0.65rem 1.35rem;">
        <i class="fas fa-search"></i> Search
      </button>
      <?php if (!empty($search) || $statusFilter !== 'All' || $programFilter > 0 || $resultStatusFilter !== 'all'): ?>
        <a href="<?= APP_URL ?>/admin/students.php" style="color: #ef4444; font-size: 0.88rem; font-weight: 600;">Clear Filters</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Students Directory Table -->
  <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.04); margin-bottom: 2.5rem;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap:wrap; gap:0.5rem;">
      <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0;">
        <i class="fas fa-list-ul" style="color:var(--rmu-primary);"></i> Enrolled Students Directory
      </h3>
      <span id="dirShowingCountText" data-total-filtered="<?= (int)$totalRows ?>" style="font-size: 0.85rem; color: #64748b;">Showing <?= count($students) ?> of <?= $totalRows ?> student accounts</span>
    </div>

    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow: none; margin: 0;">
        <thead>
          <tr>
            <th>Student ID &bull; App ID &bull; Roll No &bull; Name</th>
            <th>Program &bull; Batch</th>
            <th>Year &bull; Semester</th>
            <th style="text-align:center;">CGPA &bull; Credits</th>
            <th style="text-align:center;">Account &amp; Result Status</th>
            <th style="text-align: right;">Account &amp; Result Actions</th>
          </tr>
        </thead>
        <tbody id="studentsDirTbody">
          <?php if (!empty($students)): ?>
            <?php foreach ($students as $stu): ?>
              <?php
                $badges = get_student_status_badges($stu, (int)$stu['total_results_cnt'], (int)$stu['published_results_cnt']);
                $accB = $badges['account'];
                $resB = $badges['result'];
                $isDeleted = ($stu['status'] === 'Deleted');
                $isActive = ($stu['status'] === 'Active');
                $isVisible = !empty($badges['results_visible']);
              ?>
              <tr data-student-row-id="<?= (int)$stu['id'] ?>" style="<?= $isDeleted ? 'background:#fef2f2; opacity:0.85;' : '' ?>">
                <td>
                  <strong style="color: var(--rmu-primary); font-family: monospace; font-size: 0.94rem;">
                    <?= e($stu['student_id']) ?>
                  </strong>
                  <?php if (!empty($stu['roll_number'])): ?>
                    <span style="font-family:monospace; font-size:0.74rem; background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; padding:1px 6px; border-radius:4px; margin-left:4px; font-weight:700;">Roll: <?= e($stu['roll_number']) ?></span>
                  <?php endif; ?>
                  <div style="font-weight: 700; color: #0f172a; font-size: 0.92rem;"><?= e($stu['full_name']) ?></div>
                  <div style="font-size: 0.76rem; color: #64748b;">
                    <?php if (!empty($stu['application_number'])): ?>
                      <span><i class="fas fa-hashtag"></i> App ID: <strong style="font-family:monospace; color:#334155;"><?= e($stu['application_number']) ?></strong></span> &bull;
                    <?php endif; ?>
                    <i class="fas fa-phone-alt"></i> <?= e($stu['phone']) ?>
                  </div>
                </td>
                <td style="font-size: 0.84rem;">
                  <?php
                    $rowSess = !empty($stu['academic_session']) ? (string)$stu['academic_session'] : (string)($stu['batch'] ?? '');
                    $rowDurY = !empty($stu['duration_years']) && (float)$stu['duration_years'] > 0 ? (float)$stu['duration_years'] : 4.0;
                    $rowEval = evaluate_passing_year_difference($rowSess, $stu['passing_year'] ?? null, $rowDurY, !empty($stu['historical_record_approved']));
                  ?>
                  <strong style="color: #334155; display: block;"><?= e($stu['program_title'] ?? 'General') ?></strong>
                  <span style="font-size: 0.76rem; color: #64748b;"><?= e($rowSess !== '' ? $rowSess : $stu['batch']) ?></span>
                  <?php if (!empty($stu['passing_year']) || !empty($rowEval['expected_year'])): ?>
                    <div style="font-size:0.72rem; margin-top:2px; color:#475569;">
                      <?php if (!empty($stu['passing_year'])): ?>
                        Pass: <strong><?= e($stu['passing_year']) ?></strong>
                      <?php endif; ?>
                      <?php if (!empty($rowEval['expected_year'])): ?>
                        <span style="color:#64748b;">(Exp: <?= (int)$rowEval['expected_year'] ?>)</span>
                      <?php endif; ?>
                      <?php if (!empty($rowEval['needs_review'])): ?>
                        <span style="background:#fef3c7; color:#b45309; padding:0 4px; border-radius:3px; font-weight:700;"><?= e($rowEval['status_label']) ?></span>
                      <?php elseif (!empty($rowEval['historical_approved'])): ?>
                        <span style="background:#ede9fe; color:#6d28d9; padding:0 4px; border-radius:3px; font-weight:700;">Hist. Approved</span>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="font-size: 0.82rem; color: #475569;">
                  <div><?= e($stu['current_year']) ?></div>
                  <small style="color: #64748b;"><?= e($stu['current_semester']) ?></small>
                </td>
                <td style="font-size: 0.85rem; text-align:center;">
                  <strong style="color: #047857; font-size: 1rem; display: block;"><?= number_format((float)$stu['cgpa'], 2) ?></strong>
                  <small style="color: #64748b;"><?= (int)$stu['credits_completed'] ?> Cr (<?= (int)$badges['published_results'] ?>/<?= (int)$badges['total_results'] ?> Pub)</small>
                </td>
                <td style="text-align:center;">
                  <div style="display:flex; flex-direction:column; gap:4px; align-items:center;">
                    <span style="display: inline-flex; align-items:center; gap:4px; padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.73rem; font-weight: 800; background:<?= e($accB['bg']) ?>; color:<?= e($accB['color']) ?>; border:1px solid <?= e($accB['border']) ?>;">
                      <i class="fas <?= e($accB['icon']) ?>"></i> <?= e($accB['label']) ?>
                    </span>
                    <span style="display: inline-flex; align-items:center; gap:4px; padding: 0.18rem 0.55rem; border-radius: 99px; font-size: 0.71rem; font-weight: 700; background:<?= e($resB['bg']) ?>; color:<?= e($resB['color']) ?>; border:1px solid <?= e($resB['border']) ?>;">
                      <i class="fas <?= e($resB['icon']) ?>"></i> <?= e($resB['label']) ?>
                    </span>
                  </div>
                </td>
                <td class="stu-dir-actions" style="text-align: right; white-space: nowrap;">
                  <!-- Direct One-Page Management Trigger -->
                  <?php if (!$isDeleted): ?>
                    <a href="<?= APP_URL ?>/admin/students.php?edit=<?= $stu['id'] ?>" class="btn-primary" style="padding: 0.36rem 0.65rem; font-size: 0.78rem; background: #059669; display: inline-flex; align-items:center; gap:4px; text-decoration:none;" title="Open Unified One-Page Student & Results Form">
                      <i class="fas fa-edit"></i> Edit &amp; Results
                    </a>
                  <?php endif; ?>
                  <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= $stu['id'] ?>" class="btn-primary" style="padding: 0.36rem 0.65rem; font-size: 0.78rem; background: #0284c7; display: inline-flex; align-items:center; gap:4px; text-decoration:none;" title="Open Individual Student Profile & Action Panel">
                    <i class="fas fa-eye"></i> Details
                  </a>

                  <?php if (!$isDeleted): ?>
                    <!-- Show / Hide Result Quick Toggle -->
                    <form action="<?= APP_URL ?>/admin/students.php" method="POST" style="display:inline-block; margin:0;" onsubmit="return confirm('<?= $isVisible ? 'Hide published results from student view without deleting academic records?' : 'Show results in the Student Portal again?' ?>');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="<?= $isVisible ? 'hide_results' : 'show_results' ?>">
                      <input type="hidden" name="id" value="<?= $stu['id'] ?>">
                      <button type="submit" style="background: <?= $isVisible ? '#fef3c7' : '#dcfce7' ?>; border: 1px solid <?= $isVisible ? '#fde68a' : '#86efac' ?>; color: <?= $isVisible ? '#92400e' : '#166534' ?>; font-size: 0.76rem; cursor: pointer; padding: 0.36rem 0.55rem; border-radius:4px; font-weight: 700;" title="<?= $isVisible ? 'Hide Result from Student Portal' : 'Show Result in Student Portal' ?>">
                        <i class="fas <?= $isVisible ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                      </button>
                    </form>

                    <!-- Activate / Deactivate Account -->
                    <?php if ($isActive): ?>
                      <button type="button" onclick="openDirDeactivateModal(<?= (int)$stu['id'] ?>, <?= htmlspecialchars(json_encode($stu['student_id']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($stu['full_name']), ENT_QUOTES, 'UTF-8') ?>)" style="background: #fff7ed; border: 1px solid #fed7aa; color: #c2410c; font-size: 0.76rem; cursor: pointer; padding: 0.36rem 0.55rem; border-radius:4px; font-weight: 700;" title="Deactivate Account & Invalidate Sessions">
                        <i class="fas fa-user-slash"></i>
                      </button>
                    <?php else: ?>
                      <form action="<?= APP_URL ?>/admin/students.php" method="POST" style="display:inline-block; margin:0;" onsubmit="return confirm('Reactivate account <?= e($stu['student_id']) ?> so the student can log in again?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="activate_account">
                        <input type="hidden" name="id" value="<?= $stu['id'] ?>">
                        <button type="submit" style="background: #dcfce7; border: 1px solid #86efac; color: #15803d; font-size: 0.76rem; cursor: pointer; padding: 0.36rem 0.55rem; border-radius:4px; font-weight: 700;" title="Activate / Reactivate Account">
                          <i class="fas fa-user-check"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php if (is_admin_manager_role()): ?>
                    <!-- Permanent Delete Student Record -->
                    <button type="button"
                            class="dir-delete-student-btn"
                            data-student-id="<?= (int)$stu['id'] ?>"
                            onclick="deleteDirectoryStudent(<?= (int)$stu['id'] ?>, this)"
                            style="background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; font-size: 0.76rem; cursor: pointer; padding: 0.36rem 0.55rem; border-radius:4px; font-weight: 700;"
                            title="Delete Student">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 3rem; color: #64748b;">
                No students match your query.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div style="padding: 1rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: center;">
        <ul class="pagination" style="margin: 0;">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
              <a href="<?= APP_URL ?>/admin/students.php?search=<?= urlencode($search) ?>&program_id=<?= $programFilter ?>&status=<?= urlencode($statusFilter) ?>&result_status=<?= urlencode($resultStatusFilter) ?>&page=<?= $i ?>" class="page-link"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>

  <!-- DIRECTORY MODAL: DEACTIVATE ACCOUNT -->
  <div id="dirDeactivateModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:9999; justify-content:center; align-items:center; padding:1rem; overflow-y:auto;">
    <div style="background:#ffffff; border-radius:12px; max-width:480px; width:100%; padding:1.75rem; box-shadow:0 20px 45px rgba(0,0,0,0.28);">
      <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem; margin-bottom:1rem;">
        <h3 style="margin:0; font-size:1.15rem; color:#b45309; font-weight:800;"><i class="fas fa-user-slash"></i> Confirm Account Deactivation</h3>
        <button type="button" onclick="closeDirModal('dirDeactivateModal')" style="border:none; background:none; font-size:1.35rem; color:#64748b; cursor:pointer;">&times;</button>
      </div>
      <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:0.85rem; margin-bottom:1rem; font-size:0.84rem; color:#92400e;">
        Deactivating <strong id="dirDeactLabel"></strong> will immediately block Student Portal logins and terminate active sessions. All academic records will remain preserved and can be reactivated anytime.
      </div>
      <form action="<?= APP_URL ?>/admin/students.php" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="deactivate_account">
        <input type="hidden" name="id" id="dirDeactId" value="">
        <div class="form-group">
          <label class="form-label" style="font-weight:700;">Deactivation Reason / Note</label>
          <input type="text" name="reason" class="form-control" placeholder="e.g. Temporary administrative hold" value="Administrative hold">
        </div>
        <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1.25rem;">
          <button type="button" onclick="closeDirModal('dirDeactivateModal')" style="background:#e2e8f0; color:#334155; border:none; padding:0.55rem 1.1rem; border-radius:6px; font-weight:700; cursor:pointer;">Cancel</button>
          <button type="submit" style="background:#d97706; color:#ffffff; border:none; padding:0.55rem 1.2rem; border-radius:6px; font-weight:800; cursor:pointer;">Deactivate Account</button>
        </div>
      </form>
    </div>
  </div>

  <!-- DIRECTORY MODAL: PERMANENT DELETE CONFIRMATION -->
  <div id="dirDeleteModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.72); z-index:9999; justify-content:center; align-items:center; padding:1rem; overflow-y:auto;">
    <div style="background:#ffffff; border-radius:12px; max-width:520px; width:100%; padding:1.75rem; border:2px solid #fca5a5; box-shadow:0 24px 55px rgba(0,0,0,0.35);">
      <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #fecaca; padding-bottom:0.75rem; margin-bottom:1rem;">
        <h3 style="margin:0; font-size:1.15rem; color:#b91c1c; font-weight:800;"><i class="fas fa-exclamation-triangle"></i> Permanent Account Deletion</h3>
        <button type="button" onclick="closeDirModal('dirDeleteModal')" style="border:none; background:none; font-size:1.35rem; color:#64748b; cursor:pointer;">&times;</button>
      </div>
      <div style="background:#fef2f2; border:1px solid #fca5a5; border-radius:8px; padding:0.9rem; margin-bottom:1rem; font-size:0.84rem; color:#7f1d1d; line-height:1.5;">
        <strong>Extra Confirmation Required:</strong><br>
        You are permanently deleting <strong id="dirDelLabel"></strong>. Login credentials &amp; active portal sessions will be permanently revoked. Necessary audit &amp; academic history will be safely archived under RMU data-retention policy without affecting any other student.
      </div>
      <form action="<?= APP_URL ?>/admin/students.php" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_student_account">
        <input type="hidden" name="id" id="dirDelId" value="">
        <div class="form-group">
          <label class="form-label" style="font-weight:700; color:#991b1b;">Type Student ID (<code id="dirDelCodeHint"></code>) or <code>DELETE</code> to confirm *</label>
          <input type="text" name="confirm_student_id" class="form-control" required placeholder="Type Student ID or DELETE" style="border:1.5px solid #fca5a5; font-family:monospace; font-weight:700;">
        </div>
        <div class="form-group">
          <label class="form-label" style="font-weight:700;">Deletion Reason *</label>
          <input type="text" name="reason" class="form-control" required value="Permanent account closure authorized by administration">
        </div>
        <label style="display:flex; align-items:flex-start; gap:0.5rem; font-size:0.83rem; font-weight:700; color:#7f1d1d; background:#fff1f2; padding:0.65rem; border-radius:6px; border:1px solid #fecdd3; margin-bottom:1rem; cursor:pointer;">
          <input type="checkbox" name="confirm_permanent_delete" value="1" required style="margin-top:3px;">
          <span>I confirm this account should be permanently deleted and credentials revoked.</span>
        </label>
        <div style="display:flex; justify-content:flex-end; gap:0.65rem;">
          <button type="button" onclick="closeDirModal('dirDeleteModal')" style="background:#e2e8f0; color:#334155; border:none; padding:0.55rem 1.1rem; border-radius:6px; font-weight:700; cursor:pointer;">Cancel</button>
          <button type="submit" style="background:#b91c1c; color:#ffffff; border:none; padding:0.55rem 1.25rem; border-radius:6px; font-weight:800; cursor:pointer;">Permanently Delete Account</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    <?php if (is_admin_manager_role()): ?>
    var dirCsrfToken = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var dirDeleteStudentApiUrl = <?= json_encode(APP_URL . '/actions/admin-delete-student.php', JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    function showDirAjaxAlert(type, message) {
      var box = document.getElementById('studentsDirAjaxAlert');
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

    function deleteDirectoryStudent(studentId, btnEl) {
      var targetId = parseInt(studentId, 10);
      if (!targetId || targetId <= 0) {
        showDirAjaxAlert('error', 'Invalid student database ID.');
        return;
      }
      if (!window.confirm('Are you sure you want to delete this student?')) {
        return;
      }

      var originalHtml = btnEl ? btnEl.innerHTML : '';
      if (btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
      }

      fetch(dirDeleteStudentApiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
          csrf_token: dirCsrfToken,
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
          showDirAjaxAlert('error', data.message || 'Failed to delete student record.');
          return;
        }

        var tbody = document.getElementById('studentsDirTbody');
        var rowEl = tbody ? tbody.querySelector('tr[data-student-row-id="' + targetId + '"]') : null;
        if (rowEl && rowEl.parentNode) {
          rowEl.parentNode.removeChild(rowEl);
        }

        if (tbody && tbody.querySelectorAll('tr[data-student-row-id]').length === 0) {
          var emptyTr = document.createElement('tr');
          emptyTr.innerHTML = '<td colspan="6" style="text-align: center; padding: 3rem; color: #64748b;">No students match your query.</td>';
          tbody.appendChild(emptyTr);
        }

        if (data.stats) {
          var s = data.stats;
          var eTotal = document.getElementById('dirStatTotalStudents');
          var eBadge = document.getElementById('dirTopTotalBadge');
          var eAct   = document.getElementById('dirStatActiveStudents');
          var eDeact = document.getElementById('dirStatDeactivatedStudents');
          var eHid   = document.getElementById('dirStatHiddenResultStudents');
          var eSub   = document.getElementById('dirStatHiddenSubLabel');
          if (eTotal && typeof s.total_students !== 'undefined') eTotal.textContent = String(s.total_students);
          if (eBadge && typeof s.total_students !== 'undefined') eBadge.textContent = String(s.total_students);
          if (eAct && typeof s.active_students !== 'undefined') eAct.textContent = String(s.active_students);
          if (eDeact && typeof s.deactivated_students !== 'undefined') eDeact.textContent = String(s.deactivated_students);
          if (eHid && typeof s.hidden_result_students !== 'undefined') eHid.textContent = String(s.hidden_result_students);
          if (eSub && typeof s.deleted_students !== 'undefined') {
            eSub.innerHTML = 'Results Hidden' + (s.deleted_students > 0 ? ' &bull; ' + s.deleted_students + ' Deleted' : '');
          }
        }

        var showTxt = document.getElementById('dirShowingCountText');
        if (showTxt && tbody) {
          var remRows = tbody.querySelectorAll('tr[data-student-row-id]').length;
          var prevTotal = parseInt(showTxt.getAttribute('data-total-filtered') || String(remRows), 10);
          var nextTotal = Math.max(remRows, prevTotal - 1);
          showTxt.setAttribute('data-total-filtered', String(nextTotal));
          showTxt.textContent = 'Showing ' + remRows + ' of ' + nextTotal + ' student accounts';
        }

        showDirAjaxAlert('success', data.message || 'Student deleted successfully.');
      })
      .catch(function() {
        if (btnEl) {
          btnEl.disabled = false;
          btnEl.innerHTML = originalHtml;
        }
        showDirAjaxAlert('error', 'Network error while attempting to delete student record.');
      });
    }
    <?php endif; ?>

    function openDirDeactivateModal(id, code, name) {
      document.getElementById('dirDeactId').value = id;
      document.getElementById('dirDeactLabel').textContent = name + ' (' + code + ')';
      document.getElementById('dirDeactivateModal').style.display = 'flex';
    }
    function openDirDeleteModal(id, code, name) {
      document.getElementById('dirDelId').value = id;
      document.getElementById('dirDelLabel').textContent = name + ' (' + code + ')';
      document.getElementById('dirDelCodeHint').textContent = code;
      document.getElementById('dirDeleteModal').style.display = 'flex';
    }
    function closeDirModal(id) {
      var m = document.getElementById(id);
      if (m) m.style.display = 'none';
    }
    window.addEventListener('click', function(e) {
      var d1 = document.getElementById('dirDeactivateModal');
      var d2 = document.getElementById('dirDeleteModal');
      if (e.target === d1) d1.style.display = 'none';
      if (e.target === d2) d2.style.display = 'none';
    });
  </script>

<?php else: ?>

  <!-- ========================================================================= -->
  <!-- VIEW 2: ONE-PAGE COMPLETE STUDENT & ACADEMIC RESULT MANAGEMENT FORM       -->
  <!-- ========================================================================= -->

  <div style="background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 3rem;" id="onePageStudentConsole">
    
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #0f2c59; padding-bottom: 1.25rem; margin-bottom: 2rem; flex-wrap:wrap; gap:1rem;">
      <div>
        <h3 style="font-size: 1.35rem; font-weight: 800; color: #0f2c59; margin: 0 0 0.3rem; display:flex; align-items:center; gap:8px;">
          <i class="fas <?= $editStudent ? 'fa-user-edit' : 'fa-user-plus' ?>" style="color: var(--rmu-primary);"></i>
          <?= $editStudent ? 'Edit Student &amp; Academic Result Console' : 'Enroll New Student &amp; Setup Results' ?>
        </h3>
        <p style="margin: 0; font-size: 0.88rem; color: #64748b;">
          Single-Page Unified Form: Manage profile, personal credentials, and all 8 semesters (or 4 for Master's) from this one form.
        </p>
      </div>

      <div style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap;">
        <?php if ($editStudent): ?>
          <?php if (!empty($editStudentBadges)): ?>
            <span style="background:<?= e($editStudentBadges['account']['bg']) ?>; color:<?= e($editStudentBadges['account']['color']) ?>; border:1px solid <?= e($editStudentBadges['account']['border']) ?>; padding:4px 10px; border-radius:99px; font-size:0.76rem; font-weight:800;">
              <i class="fas <?= e($editStudentBadges['account']['icon']) ?>"></i> <?= e($editStudentBadges['account']['label']) ?>
            </span>
            <span style="background:<?= e($editStudentBadges['result']['bg']) ?>; color:<?= e($editStudentBadges['result']['color']) ?>; border:1px solid <?= e($editStudentBadges['result']['border']) ?>; padding:4px 10px; border-radius:99px; font-size:0.76rem; font-weight:800;">
              <i class="fas <?= e($editStudentBadges['result']['icon']) ?>"></i> <?= e($editStudentBadges['result']['label']) ?>
            </span>
          <?php endif; ?>
          <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= (int)$editStudent['id'] ?>" class="btn-primary" style="background:#0f2c59; padding:0.5rem 1rem; font-size:0.84rem; text-decoration:none;">
            <i class="fas fa-user-shield"></i> Account &amp; Result Control Panel
          </a>
          <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= (int)$editStudent['id'] ?>&semester=all" target="_blank" class="btn-primary" style="background:#15803d; padding:0.5rem 1rem; font-size:0.84rem; text-decoration:none;">
            <i class="fas fa-file-pdf"></i> Official PDF Transcript
          </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/admin/students.php" class="btn-primary" style="background: #64748b; padding: 0.5rem 1rem; font-size: 0.84rem; text-decoration: none;">
          <i class="fas fa-times"></i> Close Console
        </a>
      </div>
    </div>

    <?php if ($editStudent && ($editStudent['status'] ?? '') === 'Deleted'): ?>
      <div style="background:#fef2f2; border:1.5px solid #fca5a5; color:#991b1b; padding:0.9rem 1.15rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.88rem;">
        <strong><i class="fas fa-trash-alt"></i> Deleted Student Account:</strong> Credentials for this account were permanently revoked. Retained academic and audit records remain preserved under University Data-Retention Policy.
      </div>
    <?php endif; ?>

    <?php if ($editStudent): ?>
      <?php render_student_login_credentials_section($editStudent, is_admin_manager_role(), APP_URL . '/admin/students.php?edit=' . (int)$editStudent['id']); ?>
    <?php endif; ?>

    <form action="<?= APP_URL ?>/admin/students.php" method="POST" id="unifiedStudentForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editStudent ? 'edit' : 'add' ?>">
      <?php if ($editStudent): ?>
        <input type="hidden" name="id" id="currentStudentDbId" value="<?= (int)$editStudent['id'] ?>">
      <?php endif; ?>

      <!-- ------------------------------------------------------------------- -->
      <!-- SECTION 1: ALL 21 STUDENT PROFILE & ACADEMIC INFORMATION FIELDS     -->
      <!-- ------------------------------------------------------------------- -->
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1.5rem; margin-bottom:2.5rem;">
        <h4 style="margin:0 0 1.25rem; font-size:1.05rem; font-weight:800; color:#0f2c59; border-bottom:1.5px solid #cbd5e1; padding-bottom:8px; display:flex; align-items:center; gap:8px;">
          <i class="fas fa-user-circle" style="color:var(--rmu-primary);"></i> 1. Student Personal &amp; Academic Information (All 21 Fields)
        </h4>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap:1.25rem;">
          
          <!-- 1. Student ID -->
          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Student ID Number</label>
            <input type="text" name="student_id" id="formStudentId" value="<?= e($editStudent['student_id'] ?? '') ?>" placeholder="e.g. RMU2601005 (or blank to auto-generate)" class="form-control" <?= $editStudent ? 'readonly style="background:#f1f5f9; font-weight:700;"' : '' ?>>
          </div>

          <!-- Roll Number -->
          <div class="form-group">
            <label class="form-label">Admission / Examination Roll Number</label>
            <input type="text" name="roll_number" value="<?= e($editStudent['roll_number'] ?? '') ?>" placeholder="e.g. 2026-0001" class="form-control">
          </div>

          <!-- Application Number -->
          <div class="form-group">
            <label class="form-label">Application ID</label>
            <input type="text" name="application_number" value="<?= e($editStudent['application_number'] ?? '') ?>" placeholder="e.g. RMU-2026-0001" class="form-control">
          </div>

          <!-- Username -->
          <div class="form-group">
            <label class="form-label">Portal Username</label>
            <input type="text" name="username" value="<?= e($editStudent['username'] ?? '') ?>" placeholder="Defaults to Student ID if blank" class="form-control">
          </div>

          <!-- 2. Full Name -->
          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Full Name *</label>
            <input type="text" name="full_name" id="formFullName" value="<?= e($editStudent['full_name'] ?? '') ?>" placeholder="e.g. Mahfuzur Rahman" class="form-control" required>
          </div>

          <!-- 3. Official Email -->
          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Official Email *</label>
            <input type="email" name="email" id="formEmail" value="<?= e($editStudent['email'] ?? '') ?>" placeholder="student@rmu.ac.bd" class="form-control" required>
          </div>

          <!-- 4. Mobile Number -->
          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Mobile Phone *</label>
            <input type="text" name="phone" id="formPhone" value="<?= e($editStudent['phone'] ?? '') ?>" placeholder="017xxxxxxxx" class="form-control" required>
          </div>

          <!-- 5. Father's Name -->
          <div class="form-group">
            <label class="form-label">Father's Name</label>
            <input type="text" name="father_name" value="<?= e($editStudent['father_name'] ?? '') ?>" placeholder="Father's full name" class="form-control">
          </div>

          <!-- 6. Mother's Name -->
          <div class="form-group">
            <label class="form-label">Mother's Name</label>
            <input type="text" name="mother_name" value="<?= e($editStudent['mother_name'] ?? '') ?>" placeholder="Mother's full name" class="form-control">
          </div>

          <!-- 7. Date of Birth -->
          <div class="form-group">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="dob" value="<?= e($editStudent['dob'] ?? '') ?>" class="form-control">
          </div>

          <!-- 8. Gender -->
          <div class="form-group">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-select">
              <option value="Male" <?= (($editStudent['gender'] ?? 'Male') === 'Male') ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= (($editStudent['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= (($editStudent['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
            </select>
          </div>

          <!-- 9. Present Address -->
          <div class="form-group">
            <label class="form-label">Present Address</label>
            <input type="text" name="present_address" value="<?= e($editStudent['present_address'] ?? '') ?>" placeholder="Present address" class="form-control">
          </div>

          <!-- 10. Permanent Address -->
          <div class="form-group">
            <label class="form-label">Permanent Address</label>
            <input type="text" name="permanent_address" value="<?= e($editStudent['permanent_address'] ?? '') ?>" placeholder="Permanent address" class="form-control">
          </div>

          <!-- 11. Degree Program (Triggers configured semesters & course duration) -->
          <div class="form-group">
            <label class="form-label" style="font-weight:700; color:#1e40af;">
              Degree Program (Bachelor's / B.Ed / Master's) *
            </label>
            <select name="program_id" id="programSelect" class="form-select" onchange="handleProgramChange(this.value)">
              <option value="">Select Degree Program</option>
              <?php foreach ($programs as $pr): ?>
                <option value="<?= $pr['id'] ?>" data-level="<?= e($pr['degree_level']) ?>" data-type="<?= e($pr['degree_type'] ?? 'Bachelor / Honours') ?>" data-semesters="<?= (int)($pr['total_semesters'] ?? 8) ?>" data-duration="<?= e($pr['duration']) ?>" data-duration-years="<?= htmlspecialchars((string)($pr['duration_years'] ?? 4.0)) ?>" <?= (($editStudent['program_id'] ?? 1) == $pr['id']) ? 'selected' : '' ?>>
                  <?= e($pr['title_en']) ?> — <?= e($pr['degree_type'] ?? $pr['degree_level']) ?> (<?= e($pr['duration']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Degree Type, Configured Semesters & Duration (Years) -->
          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Degree Type</label>
            <select name="degree_type" id="degreeTypeSelect" class="form-select" onchange="handleDegreeTypeSelect(this.value)">
              <?php $curDegType = $editStudent ? ($editProgStruct['degree_type'] ?? 'Bachelor / Honours') : 'Bachelor / Honours'; ?>
              <option value="Bachelor / Honours" <?= ($curDegType === 'Bachelor / Honours') ? 'selected' : '' ?>>Bachelor / Honours (4 Years / 8 Semesters)</option>
              <option value="B.Ed" <?= ($curDegType === 'B.Ed') ? 'selected' : '' ?>>B.Ed (1–1.5 Years / 2–3 Semesters)</option>
              <option value="Master's" <?= ($curDegType === "Master's") ? 'selected' : '' ?>>Master's (1–2 Years / 2–4 Semesters)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Configured Semesters</label>
            <?php
              $curSems = $editStudent ? (int)($editProgStruct['total_semesters'] ?? 8) : 8;
              $curDurYears = $editStudent ? (float)($editProgStruct['duration_years'] ?? 4.0) : 4.0;
            ?>
            <select name="configured_semesters" id="configuredSemestersSelect" class="form-select" onchange="handleSemesterCountChange(this.value)">
              <?php for ($sOpt = 1; $sOpt <= 12; $sOpt++): ?>
                <?php
                  $optY = $sOpt * 0.5;
                  $optYStr = (abs($optY - round($optY)) < 0.05) ? ((int)round($optY) . ((int)round($optY) === 1 ? ' Year' : ' Years')) : (number_format($optY, 1) . ' Years');
                ?>
                <option value="<?= $sOpt ?>" <?= ($curSems === $sOpt) ? 'selected' : '' ?>><?= $sOpt ?> <?= $sOpt === 1 ? 'Semester' : 'Semesters' ?> (<?= $optYStr ?>)</option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Course Duration (Years)</label>
            <input type="number" step="0.5" min="0.5" max="6" name="duration_years" id="formDurationYears" value="<?= htmlspecialchars((string)$curDurYears) ?>" class="form-control" oninput="syncExpectedPassingYearPreview()">
          </div>

          <!-- 12. Academic Department -->
          <div class="form-group">
            <label class="form-label">Academic Department</label>
            <select name="department_id" class="form-select">
              <option value="">Select Department</option>
              <?php foreach ($departments as $dp): ?>
                <option value="<?= $dp['id'] ?>" <?= (($editStudent['department_id'] ?? 6) == $dp['id']) ? 'selected' : '' ?>>
                  <?= e($dp['name_en']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- 13. Academic Session / Batch & Passing Year -->
          <?php
            $formSessVal = $editStudent ? (!empty($editStudent['academic_session']) ? (string)$editStudent['academic_session'] : (string)($editStudent['batch'] ?? $defaultActiveSession)) : $defaultActiveSession;
            $formBatchVal = $editStudent ? (string)($editStudent['batch'] ?? $defaultActiveSession) : $defaultActiveSession;
            $formExpCalc = calculate_expected_passing_year($formSessVal, $curDurYears);
          ?>
          <div class="form-group">
            <label class="form-label">Academic Session / Batch</label>
            <input type="text" name="batch" id="formBatch" value="<?= e($formBatchVal) ?>" class="form-control">
          </div>

          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Academic Session Label</label>
            <input type="text" name="academic_session" id="formAcademicSession" list="rmuSessionsDatalist" value="<?= e($formSessVal) ?>" placeholder="e.g. Spring 2011 or <?= e($defaultActiveSession) ?>" class="form-control" oninput="syncExpectedPassingYearPreview()">
            <datalist id="rmuSessionsDatalist">
              <?php foreach ($allSessionsList as $asItem): ?>
                <option value="<?= e($asItem['session_name']) ?>"><?= empty($asItem['is_enabled']) ? '(Disabled)' : (!empty($asItem['is_current']) ? '(Current)' : '') ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>

          <div class="form-group">
            <label class="form-label" style="font-weight:700;">Passing Year (Student Selection — Never Auto-Overwritten)</label>
            <input type="text" name="passing_year" id="formPassingYear" list="rmuPassingYearsDatalist" value="<?= e($editStudent['passing_year'] ?? '') ?>" placeholder="e.g. 2011 – <?= (int)date('Y') + 4 ?>" class="form-control">
            <datalist id="rmuPassingYearsDatalist">
              <?php foreach ($allPassingYearsList as $pyItem): ?>
                <option value="<?= (int)$pyItem['year_value'] ?>"><?= empty($pyItem['is_enabled']) ? '(Disabled)' : '' ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>

          <div class="form-group">
            <label class="form-label" style="font-weight:700; color:#1e40af;">Expected Passing Year (Advisory Only)</label>
            <div id="advisoryExpectedYearBox" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:0.55rem 0.75rem; font-size:0.84rem; color:#1e3a8a; font-weight:700;">
              <?php if ($formExpCalc): ?>
                <?= (int)$formExpCalc['expected_year'] ?> <span style="font-weight:500; font-size:0.78rem; color:#475569;">(<?= e($formExpCalc['formula']) ?>)</span>
              <?php else: ?>
                Select session year to preview (Advisory Only)
              <?php endif; ?>
            </div>
            <small style="color:#64748b; font-size:0.73rem;">Formula: Session Year + Course Duration. Never alters Passing Year.</small>
          </div>

          <?php if ($editStudent): ?>
          <div class="form-group">
            <label class="form-label">Academic Change Reason (Audit Trail)</label>
            <input type="text" name="academic_edit_reason" placeholder="Optional reason when updating session / passing year / structure" class="form-control">
          </div>
          <?php endif; ?>

          <div class="form-group">
            <label class="form-label">Result Publication Date</label>
            <input type="date" name="result_publication_date" id="formPubDate" value="<?= e($editStudent['result_publication_date'] ?? date('Y-m-d')) ?>" class="form-control">
          </div>

          <!-- 14. Current Year -->
          <div class="form-group">
            <label class="form-label">Current Academic Year</label>
            <select name="current_year" class="form-select">
              <option value="1st Year" <?= (($editStudent['current_year'] ?? '') === '1st Year') ? 'selected' : '' ?>>1st Year</option>
              <option value="2nd Year" <?= (($editStudent['current_year'] ?? '') === '2nd Year') ? 'selected' : '' ?>>2nd Year</option>
              <option value="3rd Year" <?= (($editStudent['current_year'] ?? '') === '3rd Year') ? 'selected' : '' ?>>3rd Year</option>
              <option value="4th Year" <?= (($editStudent['current_year'] ?? '') === '4th Year') ? 'selected' : '' ?>>4th Year</option>
            </select>
          </div>

          <!-- 15. Current Semester -->
          <div class="form-group">
            <label class="form-label">Current Semester Term</label>
            <input type="text" name="current_semester" value="<?= e($editStudent['current_semester'] ?? '1st Semester') ?>" class="form-control">
          </div>

          <!-- 16. Enrollment Status -->
          <div class="form-group">
            <label class="form-label">Enrollment Status</label>
            <select name="status" class="form-select">
              <option value="Active" <?= (($editStudent['status'] ?? '') === 'Active') ? 'selected' : '' ?>>Active</option>
              <option value="Deactivated" <?= (($editStudent['status'] ?? '') === 'Deactivated') ? 'selected' : '' ?>>Deactivated</option>
              <option value="Graduated" <?= (($editStudent['status'] ?? '') === 'Graduated') ? 'selected' : '' ?>>Graduated</option>
              <option value="Suspended" <?= (($editStudent['status'] ?? '') === 'Suspended') ? 'selected' : '' ?>>Suspended</option>
              <?php if (($editStudent['status'] ?? '') === 'Deleted'): ?>
                <option value="Deleted" selected>Deleted</option>
              <?php endif; ?>
            </select>
          </div>

          <!-- 17. Cumulative CGPA (One-Click Update & Generator) -->
          <div class="form-group stu-cgpa-span">
            <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
              <span>Cumulative CGPA (UGC 4.00 Scale)</span>
              <span style="font-size:0.75rem; color:#b45309; font-weight:700;"><i class="fas fa-magic"></i> Auto-Generate Available</span>
            </label>
            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
              <input type="number" step="0.01" min="2.00" max="4.00" name="cgpa" id="formCgpa" value="<?= e($editStudent['cgpa'] ?? '0.00') ?>" class="form-control" style="font-weight:900; color:#0f2c59; font-size:1.1rem; width:130px; text-align:center;" placeholder="e.g. 3.85">
              
              <button type="submit" name="submit_action" value="update_cgpa_and_generate" class="btn-primary" style="background:#f59e0b; color:#0f172a; font-weight:800; font-size:0.82rem; padding:0.6rem 1rem; border:none; display:inline-flex; align-items:center; gap:6px;" title="Update CGPA and generate plausible subject marks for all semesters">
                <i class="fas fa-bolt"></i> Update CGPA &amp; Generate Results
              </button>

              <button type="submit" name="submit_action" value="update_cgpa_only" class="btn-primary" style="background:#64748b; color:#ffffff; font-weight:700; font-size:0.82rem; padding:0.6rem 1rem; border:none;" title="Update CGPA without regenerating semester results">
                <i class="fas fa-save"></i> Update CGPA Only
              </button>
            </div>
            <small style="color: #64748b;">Enter target CGPA (2.00 – 4.00). Click <strong>"Update CGPA &amp; Generate Results"</strong> to auto-create plausible semester-wise marks, grades and SGPA.</small>
          </div>

          <!-- 18. Completed Credits (Live Auto-Sync) -->
          <div class="form-group">
            <label class="form-label">Credits Completed (Live Sync)</label>
            <input type="number" min="0" name="credits_completed" id="formCreditsCompleted" value="<?= (int)($editStudent['credits_completed'] ?? 0) ?>" class="form-control" readonly style="background:#f1f5f9; font-weight:800; color:#0f2c59;">
          </div>

          <!-- 19. Blood Group -->
          <div class="form-group">
            <label class="form-label">Blood Group</label>
            <input type="text" name="blood_group" value="<?= e($editStudent['blood_group'] ?? '') ?>" placeholder="e.g. B+, O+" class="form-control">
          </div>

          <!-- 20. Home District -->
          <div class="form-group">
            <label class="form-label">Home District</label>
            <input type="text" name="home_district" value="<?= e($editStudent['home_district'] ?? '') ?>" placeholder="e.g. Kushtia" class="form-control">
          </div>

          <!-- 21. Initial Portal Password / Reset Password -->
          <div class="form-group">
            <label class="form-label"><?= $editStudent ? 'Set Manual Password Override (optional)' : 'Initial Portal Password (Auto-Generated)' ?></label>
            <?php if ($editStudent): ?>
              <input type="password" name="new_password" value="" autocomplete="new-password" placeholder="Leave blank to keep existing password (or use Reset Password above)" class="form-control">
              <small style="color: #64748b;">Optional override; stored securely using one-way hashing.</small>
            <?php else: ?>
              <?php $autoEnrollPass = generate_secure_student_password(12); ?>
              <div style="display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap;">
                <input type="password" name="password" id="enrollInitialPassword" value="<?= e($autoEnrollPass) ?>" autocomplete="new-password" placeholder="Auto-generated if left blank" class="form-control" style="flex:1; min-width:150px; font-family:monospace; font-weight:700;">
                <button type="button" id="toggleEnrollPwdBtn" onclick="rmuToggleEnrollPassword()" style="background:#0284c7; color:#ffffff; border:none; border-radius:6px; padding:0.48rem 0.72rem; font-size:0.78rem; font-weight:700; cursor:pointer; white-space:nowrap;">
                  <i class="fas fa-eye"></i> Show
                </button>
                <button type="button" onclick="rmuRegenEnrollPassword()" style="background:#0f2c59; color:#ffffff; border:none; border-radius:6px; padding:0.48rem 0.72rem; font-size:0.78rem; font-weight:700; cursor:pointer; white-space:nowrap;" title="Generate new random password">
                  <i class="fas fa-dice"></i> Regenerate
                </button>
              </div>
              <small style="color: #64748b;">Automatically generated secure random password; hashed with <code style="background:#f1f5f9; padding:0 4px;">password_hash()</code> on enrollment.</small>
            <?php endif; ?>
          </div>

        </div>
      </div>

      <!-- ------------------------------------------------------------------- -->
      <!-- SECTION 2: COMPLETE ACADEMIC RESULT MANAGEMENT (EXPANDABLE)        -->
      <!-- ------------------------------------------------------------------- -->
      <div style="background:#ffffff; border:1.5px solid #0f2c59; border-radius:10px; overflow:hidden; margin-bottom:2rem; box-shadow:0 3px 10px rgba(15,44,89,0.08);" id="resultManagementSection">
        
        <div style="background:linear-gradient(135deg, #0f2c59 0%, #1e3a8a 100%); color:#ffffff; padding:1.1rem 1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
          <div>
            <h3 style="margin:0 0 0.2rem; font-size:1.25rem; font-weight:800; color:#ffffff; display:flex; align-items:center; gap:8px;">
              <i class="fas fa-award" style="color:#f59e0b;"></i> 2. Complete Academic Result Management
            </h3>
            <span id="curriculumBadge" style="font-size:0.84rem; color:#cbd5e1;">
              Bachelor's Degree Curriculum &bull; 8 Semesters Available
            </span>
          </div>

          <div style="display:flex; gap:0.6rem; align-items:center;">
            <button type="button" onclick="expandAllSemesters()" style="background:rgba(255,255,255,0.18); color:#ffffff; border:1px solid rgba(255,255,255,0.3); border-radius:4px; padding:4px 10px; font-size:0.78rem; font-weight:700; cursor:pointer;">
              <i class="fas fa-expand-alt"></i> Expand All
            </button>
            <button type="button" onclick="collapseAllSemesters()" style="background:rgba(255,255,255,0.18); color:#ffffff; border:1px solid rgba(255,255,255,0.3); border-radius:4px; padding:4px 10px; font-size:0.78rem; font-weight:700; cursor:pointer;">
              <i class="fas fa-compress-alt"></i> Collapse All
            </button>
          </div>
        </div>

        <div style="padding:1.5rem;">
          <?php if ($editStudent && empty($editResultGate['allowed'])): ?>
            <div style="background:#fff1f2; border:2px solid #fb7185; border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.5rem; color:#881337; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
              <div>
                <div style="font-weight:800; font-size:0.95rem;"><i class="fas fa-lock"></i> Result Management Locked — Pending Application Acceptance</div>
                <div style="font-size:0.84rem; margin-top:2px;"><?= e($editResultGate['reason']) ?> Only applications accepted by Admin can proceed to Result Management.</div>
              </div>
              <a href="<?= APP_URL ?>/admin/admissions.php?status=Pending" class="btn-primary" style="background:#be123c; padding:0.45rem 0.9rem; font-size:0.82rem; text-decoration:none;">
                Review Pending Applications
              </a>
            </div>
          <?php endif; ?>
          
          <!-- One-Click CGPA Update & Auto-Estimated Result Generator Card -->
          <div style="background: linear-gradient(135deg, #0f2c59 0%, #1e40af 100%); border-radius: 8px; padding: 1.25rem 1.5rem; margin-bottom: 1.75rem; color: #ffffff; box-shadow: 0 4px 12px rgba(15,44,89,0.15);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem;">
              <div style="flex:1; min-width:min(100%, 280px);">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:0.35rem; flex-wrap:wrap;">
                  <span style="background:#f59e0b; color:#0f172a; font-size:0.75rem; font-weight:900; padding:2px 8px; border-radius:4px; text-transform:uppercase;">
                    <i class="fas fa-magic"></i> ESTIMATED / GENERATED
                  </span>
                  <h4 style="margin:0; font-size:1.15rem; font-weight:800; color:#ffffff;">
                    One-Click CGPA Update &amp; Automatic Estimated Results
                  </h4>
                </div>
                <p style="margin:0; font-size:0.85rem; color:#cbd5e1; line-height:1.45;">
                  Enter Final CGPA, Academic Session, and Result Publication Date below. The system identifies the configured structure (<strong><?= e($editStudent ? ($editProgStruct['degree_type'] . ' — ' . ($editProgStruct['total_semesters'] ?? 8) . ' Semesters') : 'Bachelor / Honours — 8 Semesters') ?></strong>), loads curriculum subjects, and distributes semester GPAs matching your target CGPA.
                </p>
                <div style="margin-top:0.45rem; font-size:0.78rem; color:#fde68a;">
                  <i class="fas fa-exclamation-triangle"></i> Results are clearly flagged: <em>"ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT"</em>
                </div>
              </div>

              <!-- One-Click Target Input, Session, Date and Buttons -->
              <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
                <div style="background:#ffffff; border-radius:6px; padding:4px 10px; display:flex; align-items:center; border:2px solid #f59e0b;">
                  <span style="font-weight:800; color:#0f2c59; font-size:0.85rem; margin-right:6px;">Target CGPA:</span>
                  <input type="number" step="0.01" min="2.00" max="4.00" name="target_cgpa_input" id="targetCgpaInput" value="<?= number_format((float)($editStudent['cgpa'] ?? 3.50), 2) ?>" style="width:80px; font-weight:900; font-size:1.1rem; color:#1e40af; border:none; outline:none; text-align:center;">
                </div>

                <div style="background:#ffffff; border-radius:6px; padding:4px 8px; display:flex; align-items:center; border:1px solid #cbd5e1;">
                  <span style="font-weight:700; color:#0f2c59; font-size:0.78rem; margin-right:5px;">Session:</span>
                  <input type="text" name="gen_academic_session" id="genAcademicSession" list="rmuSessionsDatalist" value="<?= e($editStudent['academic_session'] ?? ($editStudent['batch'] ?? $defaultActiveSession)) ?>" style="width:125px; font-weight:700; font-size:0.82rem; color:#0f172a; border:none; outline:none;">
                </div>

                <div style="background:#ffffff; border-radius:6px; padding:4px 8px; display:flex; align-items:center; border:1px solid #cbd5e1;">
                  <span style="font-weight:700; color:#0f2c59; font-size:0.78rem; margin-right:5px;">Pub Date:</span>
                  <input type="date" name="gen_publication_date" id="genPubDate" value="<?= e($editStudent['result_publication_date'] ?? date('Y-m-d')) ?>" style="font-weight:700; font-size:0.8rem; color:#0f172a; border:none; outline:none;">
                </div>

                <!-- Update CGPA & Auto-Generate Button -->
                <button type="submit" name="submit_action" value="auto_generate_estimated" class="btn-primary" style="background:#f59e0b; color:#0f172a; font-weight:800; border:none; padding:0.65rem 1.25rem; font-size:0.88rem; cursor:pointer; display:inline-flex; align-items:center; gap:6px;" <?= ($editStudent && empty($editResultGate['allowed'])) ? 'disabled' : '' ?>>
                  <i class="fas fa-bolt"></i> Update CGPA &amp; Generate Results
                </button>

                <!-- Bulk Verify All Estimated Courses Button -->
                <?php if (!empty($hasAnyEstimatedInStudent)): ?>
                  <button type="submit" name="submit_action" value="verify_all_as_official" class="btn-primary" style="background:#15803d; color:#ffffff; font-weight:700; border:none; padding:0.65rem 1rem; font-size:0.85rem; cursor:pointer;" onclick="return confirm('Verify ALL estimated course results as Official University Examination Records?');">
                    <i class="fas fa-check-double"></i> Verify All as Official
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Semesters Containers (1-12; Semesters beyond active program total hide automatically) -->
          <div id="semestersContainer">
            <?php for ($s = 1; $s <= 12; $s++): ?>
              <?php
                $existingSemCourses = $existingResultsBySem[$s] ?? [];
                $hasCourses = !empty($existingSemCourses);
                $allPub = true;
                $hasEstimatedInSem = false;
                if ($hasCourses) {
                    foreach ($existingSemCourses as $ec) {
                        if (empty($ec['is_published'])) { $allPub = false; }
                        if (!empty($ec['is_estimated'])) { $hasEstimatedInSem = true; }
                    }
                } else {
                    $allPub = false;
                }
              ?>
              <div class="semester-wrapper" id="sem-wrapper-<?= $s ?>" data-sem="<?= $s ?>" style="border:1px solid #cbd5e1; border-radius:8px; margin-bottom:1.25rem; overflow:hidden;">
                
                <!-- Semester Header -->
                <div class="sem-header" onclick="toggleSemesterAccordion(<?= $s ?>)" style="background:#f8fafc; padding:0.85rem 1.25rem; display:flex; justify-content:space-between; align-items:center; cursor:pointer; user-select:none; border-bottom:1px solid #e2e8f0;">
                  <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                    <span style="background:#0f2c59; color:#ffffff; font-weight:900; font-size:0.85rem; padding:2px 8px; border-radius:4px;">
                      <?= $s ?>
                    </span>
                    <strong style="color:#0f172a; font-size:0.98rem;"><?= $standardSemesterTitles[$s] ?></strong>
                    
                    <span class="sem-pub-badge" id="sem-pub-badge-<?= $s ?>" style="font-size:0.72rem; font-weight:700; padding:2px 7px; border-radius:4px; <?= $hasCourses ? ($allPub ? 'background:#dcfce7; color:#15803d;' : 'background:#fef3c7; color:#b45309;') : 'background:#e2e8f0; color:#64748b;' ?>">
                      <?= $hasCourses ? ($allPub ? 'Published' : 'Draft / Unpublished') : 'Pending (0 Subjects)' ?>
                    </span>

                    <?php if ($hasEstimatedInSem): ?>
                      <span style="font-size:0.72rem; font-weight:800; background:#fef3c7; color:#92400e; border:1px solid #fde68a; padding:2px 7px; border-radius:4px; text-transform:uppercase;">
                        <i class="fas fa-robot"></i> Estimated
                      </span>
                      <button type="submit" name="submit_action" value="verify_semester_<?= $s ?>" style="background:#15803d; color:#ffffff; border:none; padding:2px 8px; border-radius:4px; font-size:0.72rem; font-weight:700; cursor:pointer;" onclick="event.stopPropagation();" title="Verify Semester <?= $s ?> as Official Examination Record">
                        <i class="fas fa-check"></i> Verify Official
                      </button>
                    <?php endif; ?>
                  </div>

                  <div style="display:flex; align-items:center; gap:0.85rem; font-size:0.82rem;">
                    <span style="color:#475569;">Reg: <strong id="sem-reg-cr-<?= $s ?>" style="color:#0f172a;">0.0</strong> Cr</span>
                    <span style="color:#475569;">Earned: <strong id="sem-earned-cr-<?= $s ?>" style="color:#15803d;">0.0</strong> Cr</span>
                    <span style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; padding:2px 8px; border-radius:4px; font-weight:800;">
                      SGPA: <span id="sem-sgpa-<?= $s ?>">0.00</span>
                    </span>
                    <i class="fas fa-chevron-down sem-icon" id="sem-icon-<?= $s ?>" style="color:#64748b; transition:transform 0.2s;"></i>
                  </div>
                </div>

                <!-- Semester Body -->
                <div class="sem-body" id="sem-body-<?= $s ?>" style="display: <?= ($s <= 2 || $hasCourses) ? 'block' : 'none' ?>; padding:1.25rem;">
                  
                  <div class="table-responsive" style="margin-bottom:0.85rem;">
                    <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.86rem; border:1px solid #e2e8f0;">
                      <thead>
                        <tr>
                          <th style="width:120px;">Course Code *</th>
                          <th>Subject Name / Title *</th>
                          <th style="width:75px; text-align:center;">Credit</th>
                          <th style="width:70px; text-align:center;">Full</th>
                          <th style="width:90px; text-align:center;">Marks</th>
                          <th style="width:75px; text-align:center;">Grade</th>
                          <th style="width:75px; text-align:center;">Point</th>
                          <th style="width:80px; text-align:center;">Status</th>
                          <th style="width:50px; text-align:center;">Action</th>
                        </tr>
                      </thead>
                      <tbody id="sem-tbody-<?= $s ?>">
                        <?php if ($hasCourses): ?>
                          <?php foreach ($existingSemCourses as $cIdx => $co): ?>
                            <tr class="subject-row" data-sem="<?= $s ?>">
                              <td>
                                <input type="hidden" name="results[<?= $s ?>][<?= $cIdx ?>][id]" value="<?= (int)$co['id'] ?>">
                                <div style="display:flex; align-items:center; gap:4px;">
                                  <input type="text" name="results[<?= $s ?>][<?= $cIdx ?>][course_code]" class="form-control sub-code" value="<?= e($co['course_code']) ?>" placeholder="e.g. CSE 101" required style="text-transform:uppercase; font-weight:700; font-family:monospace; font-size:0.85rem;">
                                  <?php if (!empty($co['is_estimated'])): ?>
                                    <span style="background:#fef3c7; color:#92400e; font-size:0.65rem; font-weight:800; padding:1px 4px; border-radius:3px; border:1px solid #fde68a;" title="Estimated / Auto-Generated">EST</span>
                                  <?php endif; ?>
                                </div>
                              </td>
                              <td>
                                <input type="text" name="results[<?= $s ?>][<?= $cIdx ?>][course_name]" class="form-control sub-name" value="<?= e($co['course_name']) ?>" placeholder="Subject Title" required style="font-size:0.85rem;">
                              </td>
                              <td>
                                <input type="number" step="0.5" min="0.5" max="6.0" name="results[<?= $s ?>][<?= $cIdx ?>][credit]" class="form-control sub-credit" value="<?= number_format($co['credit'], 1) ?>" required style="text-align:center; font-size:0.85rem;" oninput="recalculateRow(this)">
                              </td>
                              <td>
                                <input type="number" name="results[<?= $s ?>][<?= $cIdx ?>][full_marks]" class="form-control sub-full" value="<?= (int)$co['full_marks'] ?>" readonly style="text-align:center; background:#f1f5f9; font-size:0.85rem;">
                              </td>
                              <td>
                                <input type="number" step="0.5" min="0" max="100" name="results[<?= $s ?>][<?= $cIdx ?>][marks]" class="form-control sub-marks" value="<?= ($co['marks'] !== null) ? number_format($co['marks'], 1) : '' ?>" placeholder="0-100" required style="text-align:center; font-weight:700; color:#0f2c59; font-size:0.9rem;" oninput="recalculateRow(this)">
                              </td>
                              <td>
                                <input type="text" name="results[<?= $s ?>][<?= $cIdx ?>][letter_grade]" class="form-control sub-grade" value="<?= e($co['letter_grade']) ?>" readonly style="text-align:center; font-weight:800; color:#1e40af; background:#f8fafc; font-size:0.85rem;">
                              </td>
                              <td>
                                <input type="text" name="results[<?= $s ?>][<?= $cIdx ?>][grade_point]" class="form-control sub-point" value="<?= number_format($co['grade_point'], 2) ?>" readonly style="text-align:center; font-weight:700; color:#047857; background:#f8fafc; font-size:0.85rem;">
                              </td>
                              <td style="text-align:center;">
                                <span class="sub-status-badge" style="font-size:0.75rem; font-weight:800; padding:2px 6px; border-radius:4px; <?= ($co['grade_point'] >= 2.00) ? 'background:#dcfce7; color:#15803d;' : 'background:#fee2e2; color:#dc2626;' ?>">
                                  <?= ($co['grade_point'] >= 2.00) ? 'Pass' : 'Fail' ?>
                                </span>
                              </td>
                              <td style="text-align:center;">
                                <button type="button" onclick="removeSubjectRow(this)" style="background:#fee2e2; color:#ef4444; border:none; border-radius:4px; padding:4px 7px; cursor:pointer;" title="Delete Subject">
                                  <i class="fas fa-trash-alt"></i>
                                </button>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
                    <div style="display:flex; gap:0.5rem;">
                      <button type="button" onclick="addSubjectRow(<?= $s ?>)" class="btn-primary" style="background:#0284c7; padding:0.4rem 0.85rem; font-size:0.8rem; border:none; cursor:pointer;">
                        <i class="fas fa-plus-circle"></i> Add Subject to Semester <?= $s ?>
                      </button>
                      <button type="button" onclick="prefillStandardSyllabus(<?= $s ?>)" style="background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; border-radius:4px; padding:0.4rem 0.85rem; font-size:0.8rem; font-weight:600; cursor:pointer;">
                        <i class="fas fa-magic"></i> Pre-fill Standard Subjects
                      </button>
                    </div>

                    <div style="font-size:0.82rem; color:#64748b;">
                      Semester <?= $s ?> Final Summary: SGPA <strong id="sem-sgpa-text-<?= $s ?>" style="color:#0f2c59;">0.00</strong>
                    </div>
                  </div>

                </div>

              </div>
            <?php endfor; ?>
          </div>

        </div>
      </div>

      <!-- ------------------------------------------------------------------- -->
      <!-- SECTION 3: LIVE CUMULATIVE ACADEMIC SUMMARY BANNER                 -->
      <!-- ------------------------------------------------------------------- -->
      <div style="background:linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%); border:1.5px solid #86efac; border-radius:10px; padding:1.5rem; margin-bottom:2rem; box-shadow:0 2px 6px rgba(0,0,0,0.04);">
        <h4 style="margin:0 0 1rem; font-size:1.05rem; font-weight:800; color:#0f2c59; display:flex; align-items:center; gap:8px;">
          <i class="fas fa-calculator" style="color:#15803d;"></i> 3. Live Academic Progress &amp; CGPA Summary
        </h4>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; text-align:center;">
          <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:0.75rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Total Reg Credits</div>
            <div id="liveTotalRegCredits" style="font-size:1.35rem; font-weight:800; color:#0f2c59; margin-top:3px;">0.0 Cr</div>
          </div>

          <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:0.75rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Total Earned Credits</div>
            <div id="liveTotalEarnedCredits" style="font-size:1.35rem; font-weight:800; color:#15803d; margin-top:3px;">0.0 Cr</div>
          </div>

          <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:0.75rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Completed Semesters</div>
            <div id="liveCompletedSemesters" style="font-size:1.35rem; font-weight:800; color:#0284c7; margin-top:3px;">0 / 8</div>
          </div>

          <div style="background:#ffffff; border:1.5px solid #86efac; border-radius:8px; padding:0.75rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; color:#166534; font-weight:800;">Cumulative CGPA</div>
            <div id="liveCumulativeCgpa" style="font-size:1.45rem; font-weight:900; color:#15803d; margin-top:3px;">0.00</div>
          </div>

          <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:0.75rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Degree Status</div>
            <div id="liveDegreeStatus" style="font-size:0.95rem; font-weight:800; color:#0369a1; margin-top:6px;">UNDERGRADUATE</div>
          </div>
        </div>

        <div class="form-group" style="margin-top:1.25rem; margin-bottom:0;">
          <label class="form-label" style="font-weight:700; color:#0f2c59;">
            <i class="fas fa-clipboard-check"></i> Reason for Result Update / Verification Note
          </label>
          <input type="text" name="correction_reason" placeholder="e.g. Initial semester result entry or administrative grade verification" class="form-control" style="background:#ffffff; border:1px solid #cbd5e1;">
        </div>
      </div>

      <!-- ------------------------------------------------------------------- -->
      <!-- SECTION 4: ONE-PAGE ACTIONS (SAVE INFO, DRAFT, PREVIEW, PUBLISH)    -->
      <!-- ------------------------------------------------------------------- -->
      <div style="background:#0f2c59; border-radius:10px; padding:1.25rem 1.75rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div style="color:#ffffff; font-size:0.86rem;">
          <i class="fas fa-shield-alt" style="color:#f59e0b;"></i> All student records and academic grades are saved directly against this unique Student ID.
        </div>

        <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
          <!-- Action 1: Save Student Information -->
          <button type="submit" name="submit_action" value="save_student_only" class="btn-primary" style="background:#0284c7; border:none; padding:0.65rem 1.25rem; font-size:0.88rem; font-weight:700; cursor:pointer;">
            <i class="fas fa-user-check"></i> Save Student Information
          </button>

          <!-- Action 2: Save Academic Results (Draft) -->
          <button type="submit" name="submit_action" value="save_draft" class="btn-primary" style="background:#d97706; border:none; padding:0.65rem 1.25rem; font-size:0.88rem; font-weight:700; cursor:pointer;" title="Saves results as draft hidden from the Student Portal">
            <i class="fas fa-save"></i> Save Academic Results (Draft)
          </button>

          <!-- Action 3: Preview Complete Transcript -->
          <button type="button" onclick="openTranscriptPreview()" class="btn-primary" style="background:#4f46e5; border:none; padding:0.65rem 1.25rem; font-size:0.88rem; font-weight:700; cursor:pointer;">
            <i class="fas fa-eye"></i> Preview Complete Transcript
          </button>

          <!-- Action 4: Publish Academic Results -->
          <button type="submit" name="submit_action" value="publish_results" class="btn-primary" style="background:#15803d; border:none; padding:0.65rem 1.5rem; font-size:0.92rem; font-weight:800; cursor:pointer; box-shadow:0 3px 8px rgba(21,128,61,0.4);" onclick="return confirm('Publish all verified academic results to the Student Portal? The student will immediately see their subjects, marks, grades, SGPA and cumulative CGPA.');">
            <i class="fas fa-check-circle"></i> Publish Academic Results
          </button>
        </div>
      </div>

    </form>
  </div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- MODAL: PREVIEW COMPLETE TRANSCRIPT & CALCULATIONS (Section 6)             -->
<!-- ========================================================================= -->
<div id="transcriptPreviewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:9999; justify-content:center; align-items:center; padding:1.5rem; overflow-y:auto;">
  <div style="background:#ffffff; border-radius:12px; max-width:850px; width:100%; padding:2.5rem; box-shadow:0 25px 60px rgba(0,0,0,0.3); position:relative; max-height:92vh; overflow-y:auto;">
    
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; border-bottom:2px solid #0f172a; padding-bottom:1rem;">
      <div style="text-align:left;">
        <h3 style="margin:0 0 0.2rem; font-size:1.35rem; color:#0f172a; font-weight:800; text-transform:uppercase;">
          Rabindra Maitree University, Kushtia
        </h3>
        <div style="font-size:0.85rem; color:#475569; font-weight:600;">Office of the Controller of Examinations &bull; Unofficial Grade Transcript Preview</div>
      </div>
      <button type="button" onclick="closeTranscriptPreview()" style="border:none; background:none; font-size:1.5rem; color:#64748b; cursor:pointer;" title="Close">&times;</button>
    </div>

    <!-- Student Details Grid -->
    <div class="admin-form-2col" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.5rem; display:grid; grid-template-columns:1fr 1fr; gap:0.6rem 2rem; font-size:0.86rem;">
      <div><span style="color:#64748b;">Student Name:</span> <strong id="modalStName"></strong></div>
      <div><span style="color:#64748b;">Student ID:</span> <strong id="modalStId" style="font-family:monospace; color:#0284c7;"></strong></div>
      <div><span style="color:#64748b;">Degree Program:</span> <strong id="modalStProgram"></strong></div>
      <div><span style="color:#64748b;">Batch / Session:</span> <strong id="modalStBatch"></strong></div>
    </div>

    <!-- Transcript Tables Container -->
    <div id="modalTranscriptTables" style="margin-bottom:1.5rem;">
      <!-- Populated dynamically via JS -->
    </div>

    <!-- Summary Box -->
    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div>
        <div style="font-size:0.82rem; color:#166534; font-weight:700;">CUMULATIVE METRICS PREVIEW</div>
        <div style="font-size:0.9rem; color:#0f2c59; margin-top:2px;">
          Total Registered: <strong id="modalTotalReg">0.0</strong> Cr &bull; Completed: <strong id="modalTotalEarned">0.0</strong> Cr
        </div>
      </div>
      <div style="background:#ffffff; border:1.5px solid #86efac; border-radius:6px; padding:6px 14px; text-align:center;">
        <span style="font-size:0.75rem; text-transform:uppercase; color:#64748b; font-weight:700;">Final CGPA</span>
        <div id="modalFinalCgpa" style="font-size:1.35rem; font-weight:900; color:#15803d;">0.00</div>
      </div>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
      <button type="button" onclick="closeTranscriptPreview()" class="btn-primary" style="background:#64748b; border:none; padding:0.55rem 1.25rem; font-size:0.86rem; cursor:pointer;">
        Close Preview
      </button>
    </div>

  </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT: DYNAMIC AUTO-GRADING, SYLLABUS, AND CALCULATION ENGINE        -->
<!-- ========================================================================= -->
<script>
var programsData = <?= json_encode($programs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
var activeSemestersCount = <?= $editStudent ? (int)($editProgStruct['total_semesters'] ?? 8) : 8 ?>;
var isEditStudentMode = <?= $editStudent ? 'true' : 'false' ?>;

// Standard Sylabuses for Quick Pre-fill
var standardSyllabus = {
  1: [
    { code: 'CSE 101', name: 'Structured Programming Language (C)', credit: 3.0, marks: 85.0 },
    { code: 'CSE 102', name: 'Structured Programming Laboratory', credit: 1.5, marks: 88.0 },
    { code: 'MAT 101', name: 'Differential and Integral Calculus', credit: 3.0, marks: 80.0 },
    { code: 'ENG 101', name: 'English Reading and Composition Skills', credit: 3.0, marks: 75.0 }
  ],
  2: [
    { code: 'CSE 103', name: 'Discrete Mathematics for Computing', credit: 3.0, marks: 82.0 },
    { code: 'CSE 104', name: 'Object Oriented Programming (C++)', credit: 3.0, marks: 86.0 },
    { code: 'CSE 105', name: 'Object Oriented Programming Lab', credit: 1.5, marks: 90.0 },
    { code: 'PHY 101', name: 'Physics for Engineers (Mechanics & Waves)', credit: 3.0, marks: 78.0 }
  ],
  3: [
    { code: 'CSE 201', name: 'Data Structures & Algorithms', credit: 3.0, marks: 84.0 },
    { code: 'CSE 202', name: 'Data Structures Laboratory', credit: 1.5, marks: 92.0 },
    { code: 'CSE 203', name: 'Digital Logic Design & Circuits', credit: 3.0, marks: 80.0 },
    { code: 'EEE 101', name: 'Basic Electrical Engineering & Circuit Analysis', credit: 3.0, marks: 77.0 }
  ],
  4: [
    { code: 'CSE 205', name: 'Design & Analysis of Algorithms', credit: 3.0, marks: 88.0 },
    { code: 'CSE 206', name: 'Database Management Systems (DBMS)', credit: 3.0, marks: 85.0 },
    { code: 'CSE 207', name: 'Database Management Systems Lab', credit: 1.5, marks: 90.0 },
    { code: 'MAT 201', name: 'Linear Algebra & Complex Variables', credit: 3.0, marks: 83.0 }
  ],
  5: [
    { code: 'CSE 301', name: 'Operating Systems & System Architecture', credit: 3.0, marks: 84.0 },
    { code: 'CSE 302', name: 'Operating Systems Laboratory', credit: 1.5, marks: 89.0 },
    { code: 'CSE 303', name: 'Software Engineering & System Analysis', credit: 3.0, marks: 86.0 },
    { code: 'STA 201', name: 'Statistics & Probability for Engineers', credit: 3.0, marks: 79.0 }
  ],
  6: [
    { code: 'CSE 305', name: 'Computer Networks & Internet Protocols', credit: 3.0, marks: 88.0 },
    { code: 'CSE 306', name: 'Computer Networks Laboratory', credit: 1.5, marks: 90.0 },
    { code: 'CSE 307', name: 'Web Engineering & Full-Stack Development', credit: 3.0, marks: 85.0 },
    { code: 'CSE 308', name: 'Microcontroller & Embedded Systems Lab', credit: 1.5, marks: 77.0 }
  ],
  7: [
    { code: 'CSE 401', name: 'Artificial Intelligence & Machine Learning', credit: 3.0, marks: 85.0 },
    { code: 'CSE 402', name: 'Compiler Design & Construction', credit: 3.0, marks: 77.0 },
    { code: 'CSE 403', name: 'Cyber Security & Cryptography', credit: 3.0, marks: 76.0 },
    { code: 'CSE 499A', name: 'Capstone Project & Thesis (Part I)', credit: 3.0, marks: 86.0 }
  ],
  8: [
    { code: 'CSE 405', name: 'Cloud Computing & Distributed Systems', credit: 3.0, marks: 87.0 },
    { code: 'CSE 406', name: 'Digital Image Processing & Vision', credit: 3.0, marks: 78.0 },
    { code: 'CSE 499B', name: 'Capstone Project & Defense (Part II)', credit: 3.0, marks: 90.0 },
    { code: 'MGT 401', name: 'Industrial Management & Professional Ethics', credit: 2.0, marks: 86.0 }
  ]
};

// Auto-grade calculation from marks
function calculateGrade(marks) {
  var m = parseFloat(marks);
  if (isNaN(m) || m < 0) return { grade: '', point: 0.0, status: 'Pending' };
  if (m >= 80) return { grade: 'A+', point: 4.00, status: 'Pass' };
  if (m >= 75) return { grade: 'A',  point: 3.75, status: 'Pass' };
  if (m >= 70) return { grade: 'A-', point: 3.50, status: 'Pass' };
  if (m >= 65) return { grade: 'B+', point: 3.25, status: 'Pass' };
  if (m >= 60) return { grade: 'B',  point: 3.00, status: 'Pass' };
  if (m >= 55) return { grade: 'B-', point: 2.75, status: 'Pass' };
  if (m >= 50) return { grade: 'C+', point: 2.50, status: 'Pass' };
  if (m >= 45) return { grade: 'C',  point: 2.25, status: 'Pass' };
  if (m >= 40) return { grade: 'D',  point: 2.00, status: 'Pass' };
  return { grade: 'F', point: 0.00, status: 'Fail' };
}

// Calculate individual subject row
function recalculateRow(inputEl) {
  var tr = inputEl.closest('tr');
  if (!tr) return;

  var marksVal = tr.querySelector('.sub-marks').value;
  var gr = calculateGrade(marksVal);

  tr.querySelector('.sub-grade').value = gr.grade;
  tr.querySelector('.sub-point').value = gr.point.toFixed(2);
  
  var statusBadge = tr.querySelector('.sub-status-badge');
  if (statusBadge) {
    statusBadge.textContent = gr.status;
    statusBadge.style.background = (gr.status === 'Pass') ? '#dcfce7' : ((gr.status === 'Fail') ? '#fee2e2' : '#f1f5f9');
    statusBadge.style.color = (gr.status === 'Pass') ? '#15803d' : ((gr.status === 'Fail') ? '#dc2626' : '#64748b');
  }

  var semNum = tr.getAttribute('data-sem');
  recalculateSemester(semNum);
  recalculateCumulativeCGPA();
}

// Calculate semester metrics
function recalculateSemester(semNum) {
  var tbody = document.getElementById('sem-tbody-' + semNum);
  if (!tbody) return;

  var rows = tbody.querySelectorAll('.subject-row');
  var regCredits = 0.0;
  var earnedCredits = 0.0;
  var totalPoints = 0.0;

  rows.forEach(function(r) {
    var cr = parseFloat(r.querySelector('.sub-credit').value) || 0.0;
    var gp = parseFloat(r.querySelector('.sub-point').value) || 0.0;
    var marks = r.querySelector('.sub-marks').value;

    if (marks !== '') {
      regCredits += cr;
      if (gp >= 2.00) earnedCredits += cr;
      totalPoints += (cr * gp);
    }
  });

  var sgpa = regCredits > 0 ? (totalPoints / regCredits).toFixed(2) : '0.00';

  var semRegCrEl = document.getElementById('sem-reg-cr-' + semNum);
  if (semRegCrEl) semRegCrEl.textContent = regCredits.toFixed(1);
  var semEarnCrEl = document.getElementById('sem-earned-cr-' + semNum);
  if (semEarnCrEl) semEarnCrEl.textContent = earnedCredits.toFixed(1);
  var semSgpaEl = document.getElementById('sem-sgpa-' + semNum);
  if (semSgpaEl) semSgpaEl.textContent = sgpa;
  var semSgpaTxtEl = document.getElementById('sem-sgpa-text-' + semNum);
  if (semSgpaTxtEl) semSgpaTxtEl.textContent = sgpa;

  var badge = document.getElementById('sem-pub-badge-' + semNum);
  if (badge) {
    if (rows.length === 0) {
      badge.textContent = 'Pending (0 Subjects)';
      badge.style.background = '#e2e8f0';
      badge.style.color = '#64748b';
    } else {
      badge.textContent = 'Active Entry';
      badge.style.background = '#eff6ff';
      badge.style.color = '#1e40af';
    }
  }
}

// Recalculate whole student Cumulative CGPA
function recalculateCumulativeCGPA() {
  var totalReg = 0.0;
  var totalEarned = 0.0;
  var totalPoints = 0.0;
  var completedCount = 0;

  for (var s = 1; s <= activeSemestersCount; s++) {
    var tbody = document.getElementById('sem-tbody-' + s);
    if (!tbody) continue;

    var rows = tbody.querySelectorAll('.subject-row');
    var semReg = 0.0;
    rows.forEach(function(r) {
      var cr = parseFloat(r.querySelector('.sub-credit').value) || 0.0;
      var gp = parseFloat(r.querySelector('.sub-point').value) || 0.0;
      var marks = r.querySelector('.sub-marks').value;

      if (marks !== '') {
        semReg += cr;
        totalReg += cr;
        if (gp >= 2.00) totalEarned += cr;
        totalPoints += (cr * gp);
      }
    });

    if (semReg > 0) completedCount++;
  }

  var finalCgpa = totalReg > 0 ? (totalPoints / totalReg).toFixed(2) : '0.00';

  var liveTotalRegEl = document.getElementById('liveTotalRegCredits');
  if (liveTotalRegEl) liveTotalRegEl.textContent = totalReg.toFixed(1) + ' Cr';
  var liveTotalEarnEl = document.getElementById('liveTotalEarnedCredits');
  if (liveTotalEarnEl) liveTotalEarnEl.textContent = totalEarned.toFixed(1) + ' Cr';
  var liveCompSemsEl = document.getElementById('liveCompletedSemesters');
  if (liveCompSemsEl) liveCompSemsEl.textContent = completedCount + ' / ' + activeSemestersCount;
  var liveCgpaEl = document.getElementById('liveCumulativeCgpa');
  if (liveCgpaEl) liveCgpaEl.textContent = finalCgpa;

  // Auto-sync form fields in Section 1
  var formCgpa = document.getElementById('formCgpa');
  if (formCgpa) formCgpa.value = finalCgpa;
  var formCr = document.getElementById('formCreditsCompleted');
  if (formCr) formCr.value = Math.round(totalEarned);

  // Live status
  var statusEl = document.getElementById('liveDegreeStatus');
  if (statusEl) {
    if (completedCount >= activeSemestersCount && totalEarned >= 80) {
      statusEl.textContent = 'DEGREE REQUIREMENTS MET';
      statusEl.style.color = '#15803d';
    } else {
      statusEl.textContent = (activeSemestersCount === 4) ? 'POSTGRADUATE' : 'UNDERGRADUATE';
      statusEl.style.color = '#0369a1';
    }
  }
}

function escHtml(str) {
  return String(str == null ? '' : str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// Add a new subject row to a semester
function addSubjectRow(semNum, data) {
  var tbody = document.getElementById('sem-tbody-' + semNum);
  if (!tbody) return;

  var rowIndex = tbody.querySelectorAll('tr').length;
  data = data || { code: '', name: '', credit: 3.0, marks: '' };

  var tr = document.createElement('tr');
  tr.className = 'subject-row';
  tr.setAttribute('data-sem', semNum);

  var gr = calculateGrade(data.marks);

  tr.innerHTML = 
    '<td>' +
      '<input type="hidden" name="results[' + semNum + '][' + rowIndex + '][id]" value="' + escHtml(data.id || '') + '">' +
      '<input type="text" name="results[' + semNum + '][' + rowIndex + '][course_code]" class="form-control sub-code" value="' + escHtml(data.code || '') + '" placeholder="e.g. CSE 101" required style="text-transform:uppercase; font-weight:700; font-family:monospace; font-size:0.85rem;">' +
    '</td>' +
    '<td>' +
      '<input type="text" name="results[' + semNum + '][' + rowIndex + '][course_name]" class="form-control sub-name" value="' + escHtml(data.name || '') + '" placeholder="Subject Title" required style="font-size:0.85rem;">' +
    '</td>' +
    '<td>' +
      '<input type="number" step="0.5" min="0.5" max="6.0" name="results[' + semNum + '][' + rowIndex + '][credit]" class="form-control sub-credit" value="' + parseFloat(data.credit).toFixed(1) + '" required style="text-align:center; font-size:0.85rem;" oninput="recalculateRow(this)">' +
    '</td>' +
    '<td>' +
      '<input type="number" name="results[' + semNum + '][' + rowIndex + '][full_marks]" class="form-control sub-full" value="100" readonly style="text-align:center; background:#f1f5f9; font-size:0.85rem;">' +
    '</td>' +
    '<td>' +
      '<input type="number" step="0.5" min="0" max="100" name="results[' + semNum + '][' + rowIndex + '][marks]" class="form-control sub-marks" value="' + (data.marks !== '' ? parseFloat(data.marks).toFixed(1) : '') + '" placeholder="0-100" required style="text-align:center; font-weight:700; color:#0f2c59; font-size:0.9rem;" oninput="recalculateRow(this)">' +
    '</td>' +
    '<td>' +
      '<input type="text" name="results[' + semNum + '][' + rowIndex + '][letter_grade]" class="form-control sub-grade" value="' + escHtml(gr.grade) + '" readonly style="text-align:center; font-weight:800; color:#1e40af; background:#f8fafc; font-size:0.85rem;">' +
    '</td>' +
    '<td>' +
      '<input type="text" name="results[' + semNum + '][' + rowIndex + '][grade_point]" class="form-control sub-point" value="' + (gr.grade ? gr.point.toFixed(2) : '') + '" readonly style="text-align:center; font-weight:700; color:#047857; background:#f8fafc; font-size:0.85rem;">' +
    '</td>' +
    '<td style="text-align:center;">' +
      '<span class="sub-status-badge" style="font-size:0.75rem; font-weight:800; padding:2px 6px; border-radius:4px; ' + (gr.status === 'Pass' ? 'background:#dcfce7; color:#15803d;' : (gr.status === 'Fail' ? 'background:#fee2e2; color:#dc2626;' : 'background:#f1f5f9; color:#64748b;')) + '">' + escHtml(gr.status) + '</span>' +
    '</td>' +
    '<td style="text-align:center;">' +
      '<button type="button" onclick="removeSubjectRow(this)" style="background:#fee2e2; color:#ef4444; border:none; border-radius:4px; padding:4px 7px; cursor:pointer;" title="Delete Subject">' +
        '<i class="fas fa-trash-alt"></i>' +
      '</button>' +
    '</td>';

  tbody.appendChild(tr);

  // Auto-expand semester accordion if closed
  var bodyEl = document.getElementById('sem-body-' + semNum);
  if (bodyEl && bodyEl.style.display === 'none') {
    toggleSemesterAccordion(semNum);
  }

  recalculateSemester(semNum);
  recalculateCumulativeCGPA();
}

// Remove row
function removeSubjectRow(btn) {
  var tr = btn.closest('tr');
  if (!tr) return;
  var semNum = tr.getAttribute('data-sem');
  tr.remove();
  recalculateSemester(semNum);
  recalculateCumulativeCGPA();
}

// Pre-fill standard syllabus for semester
function prefillStandardSyllabus(semNum) {
  var template = standardSyllabus[semNum] || [
    { code: 'COURSE ' + semNum + '01', name: 'Standard Academic Course I', credit: 3.0, marks: 80.0 },
    { code: 'COURSE ' + semNum + '02', name: 'Standard Academic Course II', credit: 3.0, marks: 85.0 }
  ];

  var tbody = document.getElementById('sem-tbody-' + semNum);
  if (tbody) tbody.innerHTML = '';

  template.forEach(function(item) {
    addSubjectRow(semNum, item);
  });
}

// Toggle accordion
function toggleSemesterAccordion(semNum) {
  var bodyEl = document.getElementById('sem-body-' + semNum);
  var iconEl = document.getElementById('sem-icon-' + semNum);
  if (!bodyEl) return;

  if (bodyEl.style.display === 'none') {
    bodyEl.style.display = 'block';
    if (iconEl) iconEl.style.transform = 'rotate(180deg)';
  } else {
    bodyEl.style.display = 'none';
    if (iconEl) iconEl.style.transform = 'rotate(0deg)';
  }
}

function expandAllSemesters() {
  for (var s = 1; s <= activeSemestersCount; s++) {
    var b = document.getElementById('sem-body-' + s);
    var i = document.getElementById('sem-icon-' + s);
    if (b) b.style.display = 'block';
    if (i) i.style.transform = 'rotate(180deg)';
  }
}

function collapseAllSemesters() {
  for (var s = 1; s <= 12; s++) {
    var b = document.getElementById('sem-body-' + s);
    var i = document.getElementById('sem-icon-' + s);
    if (b) b.style.display = 'none';
    if (i) i.style.transform = 'rotate(0deg)';
  }
}

// Apply visible semester wrapper state and disable hidden required inputs safely
function applySemesterVisibility(semCount, labelTitle, durationYearsOverride) {
  semCount = parseInt(semCount, 10) || 8;
  activeSemestersCount = Math.max(1, Math.min(12, semCount));

  var badge = document.getElementById('curriculumBadge');
  if (badge) {
    badge.innerHTML = '<strong>' + escHtml(labelTitle || 'Academic Program Curriculum') + '</strong> &bull; ' + activeSemestersCount + ' Semesters Active';
  }
  var semSel = document.getElementById('configuredSemestersSelect');
  if (semSel) {
    semSel.value = String(activeSemestersCount);
  }
  var durInp = document.getElementById('formDurationYears');
  if (durInp && durationYearsOverride !== undefined && durationYearsOverride !== null && !isNaN(parseFloat(durationYearsOverride))) {
    durInp.value = String(durationYearsOverride);
  }

  for (var s = 1; s <= 12; s++) {
    var w = document.getElementById('sem-wrapper-' + s);
    if (!w) continue;
    var isVisible = (s <= activeSemestersCount);
    w.style.display = isVisible ? 'block' : 'none';
    w.querySelectorAll('input, select, textarea').forEach(function(inp) {
      inp.disabled = !isVisible;
    });
  }
  recalculateCumulativeCGPA();
  syncExpectedPassingYearPreview();
}

function syncExpectedPassingYearPreview() {
  var box = document.getElementById('advisoryExpectedYearBox');
  var sessInp = document.getElementById('formAcademicSession');
  var durInp = document.getElementById('formDurationYears');
  if (!box || !sessInp || !durInp) return;

  var sessStr = String(sessInp.value || '');
  var m = sessStr.match(/\b(19\d{2}|20\d{2})\b/);
  var dur = parseFloat(durInp.value);
  if (!m || !isFinite(dur) || dur <= 0) {
    box.innerHTML = 'Select session year to preview (Advisory Only)';
    return;
  }
  var sessYr = parseInt(m[1], 10);
  var cleanDur = Math.max(0.5, Math.min(6.0, Math.round(dur * 10) / 10));
  var expYr = sessYr + Math.round(cleanDur);
  var durTxt = (Math.abs(cleanDur - Math.round(cleanDur)) < 0.05) ? String(Math.round(cleanDur)) : cleanDur.toFixed(1);
  box.innerHTML = expYr + ' <span style="font-weight:500; font-size:0.78rem; color:#475569;">(' + sessYr + ' + ' + durTxt + ' = ' + expYr + ')</span>';
}

function handleSemesterCountChange(semCount) {
  var degSel = document.getElementById('degreeTypeSelect');
  var degLabel = degSel ? degSel.value : "Academic Program";
  var sNum = parseInt(semCount, 10) || 8;
  var estDur = (sNum === 8) ? 4 : (sNum === 4 ? 2 : (sNum === 3 ? 1.5 : (sNum === 2 ? 1 : (Math.round(sNum * 5) / 10))));
  applySemesterVisibility(sNum, degLabel + ' Curriculum', estDur);
}

function handleDegreeTypeSelect(degType) {
  var count = 8;
  var dur = 4;
  if (degType === 'B.Ed') { count = 2; dur = 1; }
  else if (degType === "Master's") { count = 4; dur = 2; }
  applySemesterVisibility(count, degType + ' Curriculum', dur);
}

// Handle dynamic switching across Bachelor / Honours (8), B.Ed (2/3), Master's (2/3/4) and custom programs (1-12)
function handleProgramChange(progId, preserveStudentSemCount) {
  progId = parseInt(progId, 10) || 0;
  var selProg = null;
  for (var i = 0; i < programsData.length; i++) {
    if (parseInt(programsData[i].id, 10) === progId) {
      selProg = programsData[i];
      break;
    }
  }

  var semCount = 8;
  var durYears = 4;
  var degType = 'Bachelor / Honours';
  var titleLabel = "Bachelor's Degree Curriculum";

  if (selProg) {
    degType = selProg.degree_type || ((selProg.degree_level === 'Postgraduate') ? "Master's" : 'Bachelor / Honours');
    semCount = parseInt(selProg.total_semesters, 10) || (degType === 'B.Ed' ? 2 : (degType === "Master's" ? 4 : 8));
    durYears = parseFloat(selProg.duration_years) || (semCount === 8 ? 4 : (semCount === 4 ? 2 : (semCount === 3 ? 1.5 : 1)));
    if (selProg.title_en && selProg.title_en.indexOf('B.Ed') !== -1) {
      degType = 'B.Ed';
      if (!selProg.total_semesters) { semCount = 2; durYears = 1; }
    } else if (selProg.degree_level === 'Postgraduate' && !selProg.total_semesters) {
      degType = "Master's";
      semCount = 4;
      durYears = 2;
    }
    titleLabel = degType + ' Curriculum';
  }

  var degSel = document.getElementById('degreeTypeSelect');
  if (degSel && !preserveStudentSemCount) {
    degSel.value = degType;
  }

  var durOverride = durYears;
  if (preserveStudentSemCount && activeSemestersCount > 0) {
    semCount = activeSemestersCount;
    titleLabel = (degSel ? degSel.value : degType) + ' Curriculum';
    durOverride = null; // Preserve existing duration_years loaded from DB for this student
  }

  applySemesterVisibility(semCount, titleLabel, durOverride);
}

// Modal Preview of the Entire Academic Transcript
function openTranscriptPreview() {
  var name = document.getElementById('formFullName') ? document.getElementById('formFullName').value : '';
  var sid = document.getElementById('formStudentId') ? document.getElementById('formStudentId').value : 'Generated on Save';
  var batch = document.getElementById('formBatch') ? document.getElementById('formBatch').value : '';
  
  var progSelect = document.getElementById('programSelect');
  var progText = progSelect && progSelect.selectedIndex >= 0 ? progSelect.options[progSelect.selectedIndex].text : '';

  document.getElementById('modalStName').textContent = name || 'Student Name';
  document.getElementById('modalStId').textContent = sid || 'Pending ID';
  document.getElementById('modalStProgram').textContent = progText || 'Degree Program';
  document.getElementById('modalStBatch').textContent = batch || 'Session';

  var container = document.getElementById('modalTranscriptTables');
  container.innerHTML = '';

  var totalReg = 0.0;
  var totalEarned = 0.0;
  var totalPoints = 0.0;
  var semHasData = false;

  for (var s = 1; s <= activeSemestersCount; s++) {
    var tbody = document.getElementById('sem-tbody-' + s);
    if (!tbody) continue;

    var rows = tbody.querySelectorAll('.subject-row');
    if (rows.length === 0) continue;

    semHasData = true;
    var semCard = document.createElement('div');
    semCard.style.marginBottom = '1.25rem';
    semCard.style.border = '1px solid #cbd5e1';
    semCard.style.borderRadius = '6px';
    semCard.style.overflow = 'hidden';

    var semHeader = document.createElement('div');
    semHeader.style.background = '#0f2c59';
    semHeader.style.color = '#ffffff';
    semHeader.style.padding = '6px 12px';
    semHeader.style.fontWeight = '700';
    semHeader.style.fontSize = '0.85rem';
    semHeader.style.display = 'flex';
    semHeader.style.justifyContent = 'space-between';

    var semTable = document.createElement('table');
    semTable.className = 'rmu-table';
    semTable.style.boxShadow = 'none';
    semTable.style.margin = '0';
    semTable.style.fontSize = '0.84rem';

    var semTbody = document.createElement('tbody');
    var sReg = 0.0, sEarn = 0.0, sPts = 0.0;

    rows.forEach(function(r) {
      var code = r.querySelector('.sub-code').value;
      var cname = r.querySelector('.sub-name').value;
      var cr = parseFloat(r.querySelector('.sub-credit').value) || 0.0;
      var marks = r.querySelector('.sub-marks').value;
      var gr = r.querySelector('.sub-grade').value;
      var gp = parseFloat(r.querySelector('.sub-point').value) || 0.0;

      if (code || cname) {
        sReg += cr;
        if (gp >= 2.00) sEarn += cr;
        sPts += (cr * gp);

        var tr = document.createElement('tr');
        tr.innerHTML = 
          '<td style="font-family:monospace; font-weight:700; color:#1e40af; width:100px;">' + escHtml(code) + '</td>' +
          '<td><strong>' + escHtml(cname) + '</strong></td>' +
          '<td style="text-align:center; width:70px;">' + cr.toFixed(1) + ' Cr</td>' +
          '<td style="text-align:center; width:80px; font-weight:700;">' + (marks !== '' ? parseFloat(marks).toFixed(1) : '—') + '</td>' +
          '<td style="text-align:center; width:70px; font-weight:800; color:#1e40af;">' + escHtml(gr) + '</td>' +
          '<td style="text-align:center; width:70px; font-weight:700; color:#047857;">' + gp.toFixed(2) + '</td>';
        semTbody.appendChild(tr);
      }
    });

    totalReg += sReg;
    totalEarned += sEarn;
    totalPoints += sPts;
    var sSgpa = sReg > 0 ? (sPts / sReg).toFixed(2) : '0.00';

    semHeader.innerHTML = '<span>Semester ' + s + ' (' + sReg.toFixed(1) + ' Credits)</span><span>SGPA: ' + sSgpa + '</span>';
    semTable.appendChild(semTbody);
    semCard.appendChild(semHeader);
    semCard.appendChild(semTable);
    container.appendChild(semCard);
  }

  if (!semHasData) {
    container.innerHTML = '<div style="text-align:center; padding:2rem; color:#64748b;">No subjects recorded in any semester yet. Add subjects to preview.</div>';
  }

  var cCgpa = totalReg > 0 ? (totalPoints / totalReg).toFixed(2) : '0.00';
  document.getElementById('modalTotalReg').textContent = totalReg.toFixed(1);
  document.getElementById('modalTotalEarned').textContent = totalEarned.toFixed(1);
  document.getElementById('modalFinalCgpa').textContent = cCgpa;

  document.getElementById('transcriptPreviewModal').style.display = 'flex';
}

function closeTranscriptPreview() {
  document.getElementById('transcriptPreviewModal').style.display = 'none';
}

function rmuToggleEnrollPassword() {
  var inp = document.getElementById('enrollInitialPassword');
  var btn = document.getElementById('toggleEnrollPwdBtn');
  if (!inp || !btn) return;
  var showing = inp.type === 'text';
  inp.type = showing ? 'password' : 'text';
  btn.innerHTML = showing ? '<i class="fas fa-eye"></i> Show' : '<i class="fas fa-eye-slash"></i> Hide';
}

function rmuRegenEnrollPassword() {
  var inp = document.getElementById('enrollInitialPassword');
  if (!inp) return;
  var upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
  var lower = 'abcdefghjkmnpqrstuvwxyz';
  var digits = '23456789';
  var special = '@#!*-';
  var all = upper + lower + digits + special;
  var pick = function(s) {
    if (window.crypto && window.crypto.getRandomValues) {
      var arr = new Uint32Array(1);
      window.crypto.getRandomValues(arr);
      return s.charAt(arr[0] % s.length);
    }
    return s.charAt(Math.floor(Math.random() * s.length));
  };
  var out = [pick(upper), pick(upper), pick(lower), pick(lower), pick(digits), pick(digits), pick(special)];
  while (out.length < 12) out.push(pick(all));
  for (var i = out.length - 1; i > 0; i--) {
    var j = Math.floor(Math.random() * (i + 1));
    var t = out[i]; out[i] = out[j]; out[j] = t;
  }
  inp.value = out.join('');
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', function() {
  var progSelect = document.getElementById('programSelect');
  if (progSelect) {
    handleProgramChange(progSelect.value, isEditStudentMode);
  }

  // Sync Session & PubDate between Section 1 & Section 2
  var fSess = document.getElementById('formAcademicSession');
  var gSess = document.getElementById('genAcademicSession');
  if (fSess && gSess) {
    fSess.addEventListener('input', function() { gSess.value = this.value; syncExpectedPassingYearPreview(); });
    gSess.addEventListener('input', function() { fSess.value = this.value; syncExpectedPassingYearPreview(); });
  }
  var fDate = document.getElementById('formPubDate');
  var gDate = document.getElementById('genPubDate');
  if (fDate && gDate) {
    fDate.addEventListener('input', function() { gDate.value = this.value; });
    gDate.addEventListener('input', function() { fDate.value = this.value; });
  }

  // Recalculate any existing data on load (only when editor form is rendered)
  if (document.getElementById('unifiedStudentForm')) {
    for (var s = 1; s <= 12; s++) {
      recalculateSemester(s);
    }
    recalculateCumulativeCGPA();
    syncExpectedPassingYearPreview();
  }

  // Close modal on outside click
  window.addEventListener('click', function(e) {
    var modal = document.getElementById('transcriptPreviewModal');
    if (e.target === modal) closeTranscriptPreview();
  });

  // Synchronize target CGPA fields
  var formCgpa = document.getElementById('formCgpa');
  var targetCgpaInput = document.getElementById('targetCgpaInput');
  if (formCgpa && targetCgpaInput) {
    formCgpa.addEventListener('input', function() {
      targetCgpaInput.value = this.value;
    });
    targetCgpaInput.addEventListener('input', function() {
      formCgpa.value = this.value;
    });
  }
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
