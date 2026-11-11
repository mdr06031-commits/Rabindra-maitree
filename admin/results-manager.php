<?php
/**
 * Admin Panel: Advanced Student Result Management System
 * Rabindra Maitree University (RMU)
 * Complete 8-Semester Management, Auto UGC Grading, Result Requests & Modification Audit Trail
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Student Result Management';
$db = getDb();
$currentAdmin = current_user();
$adminId = (int)($currentAdmin['id'] ?? ($_SESSION['user_id'] ?? 1));

// Active tab selection (editor, requests, history)
$activeTab = trim($_GET['tab'] ?? 'editor');
if (!in_array($activeTab, ['editor', 'requests', 'history'])) {
    $activeTab = 'editor';
}

// -------------------------------------------------------------------------
// POST Action Handlers
// -------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed. Please try again.');
    } elseif (!is_admin_manager_role()) {
        $attemptedAction = $_POST['action'] ?? '';
        $attemptedStudent = (int)($_POST['student_id'] ?? 0);
        log_audit('RESULT_ACTION_DENIED', "Unauthorized role tried action '{$attemptedAction}' on results manager for Student #{$attemptedStudent}", $adminId);
        flash_set('error', 'Access denied: Only Administrator accounts can manage student results.');
        header("Location: " . APP_URL . "/admin/results-manager.php?tab={$activeTab}&student_id=" . $attemptedStudent);
        exit;
    } else {
        $action = $_POST['action'] ?? '';
        $targetStudentId = (int)($_POST['student_id'] ?? 0);

        $resultGatedActions = [
            'add_result', 'edit_result', 'delete_result', 'import_semester_results', 'publish_semester',
            'unpublish_semester', 'save_draft_semester', 'review_semester',
            'publish_all_results', 'unpublish_all_results', 'populate_all_8_semesters',
            'generate_cgpa_results', 'update_semester_gpa', 'verify_all_results_official',
            'delete_all_results'
        ];
        $gateBlocked = false;
        if ($targetStudentId > 0 && in_array($action, $resultGatedActions, true)) {
            $gate = can_manage_student_results($targetStudentId);
            if (empty($gate['allowed'])) {
                $gateBlocked = true;
                log_audit('RESULT_GATE_BLOCKED', "Blocked '{$action}' on Student #{$targetStudentId}: " . ($gate['reason'] ?? 'Pending application acceptance'), $adminId);
                flash_set('error', $gate['reason'] ?? 'Application must be accepted by Administration before managing results.');
            }
        }

        // Helper: resolve canonical semester name for a student's semester_number so subjects don't split across mismatched labels
        $resolveCanonicalSemester = function(int $stId, string $semLabel, int $excludeResId = 0) use ($db): string {
            $semTrim = trim($semLabel);
            $sNum = get_semester_number($semTrim);
            if ($stId <= 0 || $sNum < 1 || $sNum > 12) {
                return $semTrim;
            }
            $q = $db->prepare("SELECT id, semester FROM student_results WHERE student_id = ? ORDER BY id ASC");
            $q->execute([$stId]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ((int)$row['id'] !== $excludeResId && get_semester_number((string)$row['semester']) === $sNum) {
                    return trim((string)$row['semester']);
                }
            }
            return $semTrim;
        };

        if ($gateBlocked) {
            // Gate blocked — skip result mutation and redirect with error flash
        }
        // 1. Add Subject Result
        elseif ($action === 'add_result') {
            $semester = trim($_POST['semester'] ?? '');
            $code = strtoupper(trim($_POST['course_code'] ?? ''));
            $title = trim($_POST['course_name'] ?? '');
            $credit = round((float)($_POST['credit'] ?? 3.0), 1);
            $fullMarks = (int)($_POST['full_marks'] ?? 100);
            $marks = isset($_POST['marks']) && $_POST['marks'] !== '' ? round((float)$_POST['marks'], 2) : null;
            $term = trim($_POST['exam_term'] ?? 'Semester Final Examination');
            $isPublished = !empty($_POST['is_published']) ? 1 : 0;
            $reason = trim($_POST['reason'] ?? 'Initial academic record entry');

            if ($targetStudentId <= 0 || $semester === '' || $code === '') {
                flash_set('error', 'Student, Semester, and Course Code are required.');
            } elseif ($credit < 0.5 || $credit > 9.5) {
                flash_set('error', 'Credit hours must be between 0.5 and 9.5.');
            } elseif ($fullMarks < 1 || $fullMarks > 500) {
                flash_set('error', 'Full marks must be between 1 and 500.');
            } elseif ($marks !== null && ($marks < 0.0 || $marks > (float)$fullMarks)) {
                flash_set('error', "Obtained marks ({$marks}) must be between 0 and Full Marks ({$fullMarks}).");
            } else {
                $semester = $resolveCanonicalSemester($targetStudentId, $semester);
                // Auto calculate UGC grade and grade point from percentage of full_marks
                if ($marks !== null) {
                    $gradeInfo = calculate_grade_from_marks($marks, (float)$fullMarks);
                    $letterGrade = $gradeInfo['letter_grade'];
                    $gradePoint = $gradeInfo['grade_point'];
                } else {
                    $letterGrade = trim($_POST['letter_grade'] ?? 'A+');
                    $gradePoint = max(0.00, min(4.00, (float)($_POST['grade_point'] ?? 4.00)));
                }

                $publishStatus = in_array($_POST['publish_status'] ?? '', ['Draft', 'Under Review', 'Published'], true)
                    ? $_POST['publish_status']
                    : ($isPublished ? 'Published' : 'Draft');
                $isPublished = ($publishStatus === 'Published') ? 1 : 0;
                $publishedAt = $isPublished ? date('Y-m-d H:i:s') : null;
                $publishedBy = $isPublished ? $adminId : null;

                $sessStmt = $db->prepare("SELECT academic_session, batch FROM students WHERE id = ? LIMIT 1");
                $sessStmt->execute([$targetStudentId]);
                $stSessRow = $sessStmt->fetch(PDO::FETCH_ASSOC);
                $stSessionVal = !empty($stSessRow['academic_session']) ? $stSessRow['academic_session'] : ($stSessRow['batch'] ?? null);

                $ins = $db->prepare("
                    INSERT INTO student_results 
                    (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, academic_session, is_published, is_estimated, result_type, publish_status, published_at, published_by, correction_reason)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Verified', ?, ?, ?, ?)
                ");
                $ins->execute([
                    $targetStudentId, $semester, $code, $title !== '' ? $title : $code,
                    $credit, $fullMarks, $marks, $letterGrade, $gradePoint, $term, $stSessionVal,
                    $isPublished, $publishStatus, $publishedAt, $publishedBy, $reason
                ]);
                $newResId = (int)$db->lastInsertId();

                // Log modification history
                $marksDisp = ($marks !== null) ? "{$marks}/{$fullMarks}" : "N/A";
                log_result_modification($newResId, $targetStudentId, $adminId, $code, $semester, 'Initial Entry', null, "{$marksDisp} Marks ({$letterGrade}, GP {$gradePoint})", $reason);

                // If published, notify student
                if ($isPublished) {
                    create_student_notification(
                        $targetStudentId,
                        "New Course Result: {$code}",
                        "Your result for {$code} - {$title} ({$semester}) has been published. Grade: {$letterGrade} ({$gradePoint}).",
                        'result_published',
                        $newResId
                    );
                }

                $resMetrics = recalculate_student_cgpa($targetStudentId);
                log_audit('RESULT_ADDED', "Added {$code} ({$letterGrade}, {$marksDisp} marks) for Student #{$targetStudentId}");
                flash_set('success', "Result for '{$code}' saved successfully! Updated CGPA: " . number_format((float)$resMetrics['cgpa'], 2) . ".");
            }
        }

        // 2. Edit Subject Result (Requires Reason)
        elseif ($action === 'edit_result') {
            $resultId = (int)($_POST['result_id'] ?? 0);
            $semester = trim($_POST['semester'] ?? '');
            $code = strtoupper(trim($_POST['course_code'] ?? ''));
            $title = trim($_POST['course_name'] ?? '');
            $credit = round((float)($_POST['credit'] ?? 3.0), 1);
            $fullMarks = (int)($_POST['full_marks'] ?? 100);
            $marks = isset($_POST['marks']) && $_POST['marks'] !== '' ? round((float)$_POST['marks'], 2) : null;
            $term = trim($_POST['exam_term'] ?? 'Semester Final Examination');
            $isPublished = !empty($_POST['is_published']) ? 1 : 0;
            $reason = trim($_POST['correction_reason'] ?? '');

            if ($reason === '') {
                $reason = 'Administrative grade verification & update';
            }

            if ($resultId <= 0 || $targetStudentId <= 0 || $semester === '' || $code === '') {
                flash_set('error', 'Valid result record, semester, and course code are required.');
            } elseif ($credit < 0.5 || $credit > 9.5) {
                flash_set('error', 'Credit hours must be between 0.5 and 9.5.');
            } elseif ($fullMarks < 1 || $fullMarks > 500) {
                flash_set('error', 'Full marks must be between 1 and 500.');
            } elseif ($marks !== null && ($marks < 0.0 || $marks > (float)$fullMarks)) {
                flash_set('error', "Obtained marks ({$marks}) must be between 0 and Full Marks ({$fullMarks}).");
            } else {
                // Fetch previous record for history logging
                $oldStmt = $db->prepare("SELECT * FROM student_results WHERE id = ? AND student_id = ?");
                $oldStmt->execute([$resultId, $targetStudentId]);
                $oldRes = $oldStmt->fetch(PDO::FETCH_ASSOC);

                if (!$oldRes) {
                    flash_set('error', 'Subject result record not found.');
                } else {
                    $semester = $resolveCanonicalSemester($targetStudentId, $semester, $resultId);
                    if ($marks !== null) {
                        $gradeInfo = calculate_grade_from_marks($marks, (float)$fullMarks);
                        $letterGrade = $gradeInfo['letter_grade'];
                        $gradePoint = $gradeInfo['grade_point'];
                    } else {
                        $letterGrade = trim($_POST['letter_grade'] ?? 'A+');
                        $gradePoint = max(0.00, min(4.00, (float)($_POST['grade_point'] ?? 4.00)));
                    }

                    $publishStatus = in_array($_POST['publish_status'] ?? '', ['Draft', 'Under Review', 'Published'], true)
                        ? $_POST['publish_status']
                        : ($isPublished ? 'Published' : ($oldRes['publish_status'] ?? 'Draft'));
                    $isPublished = ($publishStatus === 'Published') ? 1 : 0;
                    $publishedAt = $isPublished ? ($oldRes['published_at'] ?? date('Y-m-d H:i:s')) : null;
                    $publishedBy = $isPublished ? ($oldRes['published_by'] ?? $adminId) : null;

                    $isEstimatedVal = !empty($_POST['keep_estimated']) ? (int)($oldRes['is_estimated'] ?? 0) : 0;
                    $resultTypeVal = ($isEstimatedVal === 1) ? 'Estimated' : 'Verified';

                    $upd = $db->prepare("
                        UPDATE student_results 
                        SET semester = ?, course_code = ?, course_name = ?, credit = ?, full_marks = ?, marks = ?, letter_grade = ?, grade_point = ?, exam_term = ?, is_published = ?, publish_status = ?, is_estimated = ?, result_type = ?, published_at = ?, published_by = ?, correction_reason = ?
                        WHERE id = ? AND student_id = ?
                    ");
                    $upd->execute([
                        $semester, $code, $title !== '' ? $title : $code, $credit, $fullMarks, $marks,
                        $letterGrade, $gradePoint, $term, $isPublished, $publishStatus, $isEstimatedVal, $resultTypeVal,
                        $publishedAt, $publishedBy, $reason, $resultId, $targetStudentId
                    ]);

                    // Log change history
                    $oldSummary = "Marks: " . ($oldRes['marks'] ?? 'N/A') . "/" . ($oldRes['full_marks'] ?? 100) . ", Grade: {$oldRes['letter_grade']}, GP: {$oldRes['grade_point']}, Cr: {$oldRes['credit']}";
                    $newSummary = "Marks: " . ($marks ?? 'N/A') . "/{$fullMarks}, Grade: {$letterGrade}, GP: {$gradePoint}, Cr: {$credit}";
                    log_result_modification($resultId, $targetStudentId, $adminId, $code, $semester, 'Grade Correction', $oldSummary, $newSummary, $reason);

                    // Notify student of update
                    create_student_notification(
                        $targetStudentId,
                        "Academic Result Update: {$code}",
                        "Your academic result for {$code} ({$semester}) has been updated. New Grade: {$letterGrade} ({$gradePoint}). Reason: {$reason}.",
                        'grade_correction',
                        $resultId
                    );

                    $resMetrics = recalculate_student_cgpa($targetStudentId);
                    log_audit('RESULT_EDITED', "Updated result #{$resultId} ({$code}) for Student #{$targetStudentId}. Reason: {$reason}");
                    flash_set('success', "Result for '{$code}' updated successfully! Updated CGPA: " . number_format((float)$resMetrics['cgpa'], 2) . ".");
                }
            }
        }

        // 2b. Import Missing / Previous Semester Results (CSV / Bulk Paste)
        elseif ($action === 'import_semester_results') {
            $rawImportText = trim($_POST['import_data'] ?? '');
            if (!empty($_FILES['csv_file']['tmp_name']) && (int)($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $fileSize = (int)($_FILES['csv_file']['size'] ?? 0);
                if ($fileSize > 0 && $fileSize <= 1048576) {
                    $fileContents = @file_get_contents($_FILES['csv_file']['tmp_name']);
                    if ($fileContents !== false && trim($fileContents) !== '') {
                        $rawImportText = trim($fileContents);
                    }
                } elseif ($fileSize > 1048576) {
                    flash_set('error', 'Uploaded CSV file exceeds the 1 MB maximum size.');
                    $rawImportText = '';
                }
            }

            if ($targetStudentId <= 0) {
                flash_set('error', 'Please select a valid student before importing results.');
            } elseif ($rawImportText === '') {
                flash_set('error', 'Please upload a CSV file or paste subject rows to import.');
            } else {
                $targetSem = trim($_POST['target_semester'] ?? '');
                $pubStatus = in_array($_POST['publish_status'] ?? '', ['Published', 'Draft', 'Under Review'], true)
                    ? $_POST['publish_status']
                    : 'Published';
                $updateExisting = !empty($_POST['update_existing']);
                $reason = trim($_POST['reason'] ?? 'Missing/Previous semester result import');

                $stFetchStmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
                $stFetchStmt->execute([$targetStudentId]);
                $stFetchRow = $stFetchStmt->fetch(PDO::FETCH_ASSOC) ?: ['program_id' => 1];
                $stProgStruct = get_student_program_structure($stFetchRow);
                $stTotalSems = max(1, (int)($stProgStruct['total_semesters'] ?? 8));

                $parsed = parse_semester_results_import_text(
                    $rawImportText,
                    $stTotalSems,
                    ($targetSem !== '' && $targetSem !== 'multi') ? $targetSem : null
                );

                if (empty($parsed['rows'])) {
                    $errSample = !empty($parsed['errors']) ? implode(' | ', array_slice($parsed['errors'], 0, 3)) : 'No valid subject rows found.';
                    flash_set('error', "Import failed: {$errSample}");
                } else {
                    $impRes = import_student_semester_results($targetStudentId, $parsed['rows'], $adminId, [
                        'publish_status'  => $pubStatus,
                        'update_existing' => $updateExisting,
                        'reason'          => $reason,
                    ]);
                    if (!empty($impRes['success'])) {
                        $warnSuffix = '';
                        if (!empty($parsed['errors'])) {
                            $warnSuffix = ' (' . count($parsed['errors']) . ' invalid row(s) skipped: ' . $parsed['errors'][0] . ')';
                        }
                        $semTxt = implode(', ', $impRes['semesters']);
                        flash_set(
                            'success',
                            "Imported {$impRes['inserted']} new, updated {$impRes['updated']}, skipped {$impRes['skipped']} subject result(s) across [{$semTxt}]. Recalculated CGPA: " . number_format((float)$impRes['cgpa'], 2) . ".{$warnSuffix}"
                        );
                    } else {
                        flash_set('error', $impRes['error'] ?? 'Could not import semester results.');
                    }
                }
            }
        }

        // 3. Delete Subject Result
        elseif ($action === 'delete_result') {
            $resultId = (int)($_POST['result_id'] ?? 0);
            if ($resultId > 0 && $targetStudentId > 0) {
                $oldStmt = $db->prepare("SELECT * FROM student_results WHERE id = ? AND student_id = ?");
                $oldStmt->execute([$resultId, $targetStudentId]);
                $oldRes = $oldStmt->fetch(PDO::FETCH_ASSOC);

                if ($oldRes) {
                    log_result_modification($resultId, $targetStudentId, $adminId, $oldRes['course_code'], $oldRes['semester'], 'Subject Deleted', "{$oldRes['letter_grade']} ({$oldRes['marks']} marks)", 'DELETED', 'Administrative record deletion');
                }

                $del = $db->prepare("DELETE FROM student_results WHERE id = ? AND student_id = ?");
                $del->execute([$resultId, $targetStudentId]);

                $resMetrics = recalculate_student_cgpa($targetStudentId);
                log_audit('RESULT_DELETED', "Deleted result #{$resultId} for Student #{$targetStudentId}");
                flash_set('success', "Subject result record removed. Recalculated CGPA: " . number_format((float)$resMetrics['cgpa'], 2) . ".");
            }
        }

        // 4. Publish Entire Semester Results (Prominent Action — blocks empty semesters)
        elseif ($action === 'publish_semester') {
            $semesterName = trim($_POST['semester_name'] ?? '');
            if ($targetStudentId > 0 && $semesterName !== '') {
                $pubRes = set_student_results_publication($targetStudentId, true, $semesterName, $adminId);
                if (!empty($pubRes['success'])) {
                    flash_set('success', "Success! All {$pubRes['affected']} course results for '{$semesterName}' are now officially published to the Student Portal. Updated CGPA: " . number_format((float)$pubRes['cgpa'], 2) . ".");
                } else {
                    flash_set('error', $pubRes['error'] ?? "Cannot publish '{$semesterName}': No saved subject results exist.");
                }
            }
        }

        // 5. Unpublish / Save Semester Results as Draft
        elseif ($action === 'unpublish_semester' || $action === 'save_draft_semester') {
            $semesterName = trim($_POST['semester_name'] ?? '');
            if ($targetStudentId > 0 && $semesterName !== '') {
                set_student_results_publication($targetStudentId, false, $semesterName, $adminId);
                flash_set('info', "Results for '{$semesterName}' have been saved as Draft (hidden from Student Portal).");
            }
        }

        // 5b. Move Semester Results to Under Review
        elseif ($action === 'review_semester') {
            $semesterName = trim($_POST['semester_name'] ?? '');
            if ($targetStudentId > 0 && $semesterName !== '') {
                $targetIds = [];
                $exactStmt = $db->prepare("SELECT id FROM student_results WHERE student_id = ? AND semester = ?");
                $exactStmt->execute([$targetStudentId, $semesterName]);
                $targetIds = array_map('intval', $exactStmt->fetchAll(PDO::FETCH_COLUMN));
                if (empty($targetIds)) {
                    $sn = get_semester_number($semesterName);
                    if ($sn >= 1 && $sn <= 12) {
                        $allSt = $db->prepare("SELECT id, semester FROM student_results WHERE student_id = ?");
                        $allSt->execute([$targetStudentId]);
                        foreach ($allSt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                            if (get_semester_number((string)$r['semester']) === $sn) {
                                $targetIds[] = (int)$r['id'];
                            }
                        }
                    }
                }
                if (!empty($targetIds)) {
                    $ph = implode(',', array_fill(0, count($targetIds), '?'));
                    $revStmt = $db->prepare("UPDATE student_results SET publish_status = 'Under Review', is_published = 0 WHERE student_id = ? AND id IN ({$ph})");
                    $revStmt->execute(array_merge([$targetStudentId], $targetIds));
                    recalculate_student_cgpa($targetStudentId);
                    log_audit('SEMESTER_RESULTS_REVIEW', "Moved {$semesterName} results to Under Review for Student #{$targetStudentId}");
                    flash_set('info', "Results for '{$semesterName}' are now marked as Under Review (hidden from Student Portal).");
                } else {
                    flash_set('error', "No saved subject results exist for '{$semesterName}' to mark as Under Review.");
                }
            }
        }

        // 5c. Result Visibility Control (Show / Hide Results without deleting data)
        elseif ($action === 'show_results' && $targetStudentId > 0) {
            $res = set_student_result_visibility($targetStudentId, true, $adminId);
            flash_set($res['success'] ? 'success' : 'error', $res['success'] ? "Published results are now VISIBLE (Show Result) in Student Portal for {$res['full_name']}." : ($res['error'] ?? 'Failed.'));
        }
        elseif ($action === 'hide_results' && $targetStudentId > 0) {
            $res = set_student_result_visibility($targetStudentId, false, $adminId, trim($_POST['reason'] ?? ''));
            flash_set($res['success'] ? 'success' : 'error', $res['success'] ? "Results are now HIDDEN (Hide Result) from student view for {$res['full_name']}. Original academic records preserved." : ($res['error'] ?? 'Failed.'));
        }
        elseif ($action === 'publish_all_results' && $targetStudentId > 0) {
            $res = set_student_results_publication($targetStudentId, true, null, $adminId);
            flash_set($res['success'] ? 'success' : 'error', $res['success'] ? "Published {$res['affected']} subject result(s) across all semesters." : ($res['error'] ?? 'Failed.'));
        }
        elseif ($action === 'unpublish_all_results' && $targetStudentId > 0) {
            $res = set_student_results_publication($targetStudentId, false, null, $adminId);
            flash_set($res['success'] ? 'success' : 'error', $res['success'] ? "Unpublished {$res['affected']} subject result(s) back to Draft." : ($res['error'] ?? 'Failed.'));
        }

        // 6. Process Student Result Request (Approve or Reject)
        elseif ($action === 'process_request') {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $newStatus = in_array($_POST['status'] ?? '', ['Approved', 'Rejected', 'In Review']) ? $_POST['status'] : 'In Review';
            $adminNotes = trim($_POST['admin_notes'] ?? '');

            if ($requestId > 0) {
                $reqStmt = $db->prepare("SELECT * FROM result_requests WHERE id = ?");
                $reqStmt->execute([$requestId]);
                $reqData = $reqStmt->fetch(PDO::FETCH_ASSOC);

                if ($reqData) {
                    $updReq = $db->prepare("
                        UPDATE result_requests 
                        SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW() 
                        WHERE id = ?
                    ");
                    $updReq->execute([$newStatus, $adminNotes, $adminId, $requestId]);

                    // Send student notification
                    $msg = "Your result inquiry #{$requestId} regarding '{$reqData['subject']}' has been marked as '{$newStatus}'.";
                    if (!empty($adminNotes)) {
                        $msg .= " Controller Office Note: {$adminNotes}";
                    }
                    create_student_notification(
                        (int)$reqData['student_id'],
                        "Result Request Status: {$newStatus}",
                        $msg,
                        'request_update',
                        $requestId
                    );

                    log_audit('RESULT_REQUEST_PROCESSED', "Processed result request #{$requestId} (Status: {$newStatus}) for Student #{$reqData['student_id']}");
                    flash_set('success', "Result request #{$requestId} updated to '{$newStatus}' and student notified.");
                }
            }
            header("Location: " . APP_URL . "/admin/results-manager.php?tab=requests");
            exit;
        }

        // 7. Populate Standard RMU Curriculum (8-Semester Bachelor or Dynamic B.Ed / Master's)
        elseif ($action === 'populate_all_8_semesters') {
            if ($targetStudentId > 0) {
                $stPopStmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
                $stPopStmt->execute([$targetStudentId]);
                $stPop = $stPopStmt->fetch(PDO::FETCH_ASSOC);
                $stStruct = $stPop ? get_student_program_structure($stPop) : ['total_semesters' => 8, 'duration_years' => 4.0];
                $popSemCount = max(1, (int)($stStruct['total_semesters'] ?? 8));
                $popProgId = (int)($stPop['program_id'] ?? 1);

                $db->prepare("DELETE FROM student_results WHERE student_id = ?")->execute([$targetStudentId]);

                if ($popSemCount === 8 && $popProgId === 1) {
                    $curriculum = [
                        // 1st Semester
                        ['1st Semester (Spring 2023)', 'CSE 101', 'Structured Programming in C', 3.0, 100, 85.00],
                        ['1st Semester (Spring 2023)', 'CSE 102', 'Structured Programming Lab', 1.5, 100, 88.00],
                        ['1st Semester (Spring 2023)', 'ENG 101', 'English Reading & Writing Skills', 3.0, 100, 78.00],
                        ['1st Semester (Spring 2023)', 'MAT 101', 'Differential and Integral Calculus', 3.0, 100, 84.00],
                        // 2nd Semester
                        ['2nd Semester (Fall 2023)', 'CSE 103', 'Discrete Mathematics', 3.0, 100, 82.00],
                        ['2nd Semester (Fall 2023)', 'CSE 104', 'Object Oriented Programming (Java)', 3.0, 100, 77.00],
                        ['2nd Semester (Fall 2023)', 'PHY 101', 'Physics (Mechanics, Waves & Optics)', 3.0, 100, 76.00],
                        ['2nd Semester (Fall 2023)', 'BAN 105', 'Bangla Language & Tagore Literary Studies', 2.0, 100, 86.00],
                        // 3rd Semester
                        ['3rd Semester (Spring 2024)', 'CSE 201', 'Data Structures & Algorithms', 3.0, 100, 83.00],
                        ['3rd Semester (Spring 2024)', 'CSE 202', 'Data Structures & Algorithms Lab', 1.5, 100, 85.00],
                        ['3rd Semester (Spring 2024)', 'CSE 203', 'Digital Logic Design & Microprocessors', 3.0, 100, 76.00],
                        ['3rd Semester (Spring 2024)', 'MAT 102', 'Coordinate Geometry & Linear Algebra', 3.0, 100, 78.00],
                        // 4th Semester
                        ['4th Semester (Fall 2024)', 'CSE 205', 'Database Management Systems', 3.0, 100, 84.00],
                        ['4th Semester (Fall 2024)', 'CSE 206', 'Database Management Systems Lab', 1.5, 100, 87.00],
                        ['4th Semester (Fall 2024)', 'CSE 207', 'Computer Architecture & Organization', 3.0, 100, 77.00],
                        ['4th Semester (Fall 2024)', 'STA 201', 'Statistics & Probability for Engineers', 3.0, 100, 79.00],
                        // 5th Semester
                        ['5th Semester (Spring 2025)', 'CSE 301', 'Software Engineering & System Analysis', 3.0, 100, 82.00],
                        ['5th Semester (Spring 2025)', 'CSE 302', 'Operating Systems & System Programming', 3.0, 100, 77.00],
                        ['5th Semester (Spring 2025)', 'CSE 303', 'Operating Systems Lab (Linux/Unix)', 1.5, 100, 86.00],
                        ['5th Semester (Spring 2025)', 'CSE 304', 'Theory of Computation & Automata', 3.0, 100, 78.00],
                        // 6th Semester
                        ['6th Semester (Fall 2025)', 'CSE 305', 'Computer Networks & Internet Protocols', 3.0, 100, 88.00],
                        ['6th Semester (Fall 2025)', 'CSE 306', 'Computer Networks Laboratory', 1.5, 100, 90.00],
                        ['6th Semester (Fall 2025)', 'CSE 307', 'Web Engineering & Full-Stack Development', 3.0, 100, 85.00],
                        ['6th Semester (Fall 2025)', 'CSE 308', 'Microcontroller & Embedded Systems Lab', 1.5, 100, 77.00],
                        // 7th Semester
                        ['7th Semester (Spring 2026)', 'CSE 401', 'Artificial Intelligence & Machine Learning', 3.0, 100, 85.00],
                        ['7th Semester (Spring 2026)', 'CSE 402', 'Compiler Design & Construction', 3.0, 100, 77.00],
                        ['7th Semester (Spring 2026)', 'CSE 403', 'Cyber Security & Cryptography', 3.0, 100, 76.00],
                        ['7th Semester (Spring 2026)', 'CSE 499A', 'Capstone Project & Thesis (Part I)', 3.0, 100, 86.00],
                        // 8th Semester
                        ['8th Semester (Fall 2026)', 'CSE 405', 'Cloud Computing & Distributed Systems', 3.0, 100, 87.00],
                        ['8th Semester (Fall 2026)', 'CSE 406', 'Digital Image Processing & Vision', 3.0, 100, 78.00],
                        ['8th Semester (Fall 2026)', 'CSE 499B', 'Capstone Project & Defense (Part II)', 3.0, 100, 90.00],
                        ['8th Semester (Fall 2026)', 'MGT 401', 'Industrial Management & Professional Ethics', 2.0, 100, 86.00]
                    ];
                } else {
                    $progCurr = get_program_semester_curriculum($popProgId, $popSemCount);
                    $curriculum = [];
                    foreach ($progCurr as $sNum => $sCourses) {
                        $sTitle = get_semester_ordinal_title((int)$sNum);
                        foreach ($sCourses as $idx => $cItem) {
                            $sampleMarks = 78.0 + (($idx + $sNum) % 10);
                            $curriculum[] = [$sTitle, $cItem['code'], $cItem['name'], (float)$cItem['credit'], 100, $sampleMarks];
                        }
                    }
                }

                $insStmt = $db->prepare("
                    INSERT INTO student_results 
                    (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, is_published, published_at, published_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Semester Final Examination', 1, NOW(), ?)
                ");

                foreach ($curriculum as $c) {
                    $grade = calculate_grade_from_marks($c[5]);
                    $insStmt->execute([
                        $targetStudentId, $c[0], $c[1], $c[2], $c[3], $c[4], $c[5],
                        $grade['letter_grade'], $grade['grade_point'], $adminId
                    ]);
                }

                $resMetrics = recalculate_student_cgpa($targetStudentId);
                $lastYear = max(1, (int)ceil(($stStruct['duration_years'] ?? ($popSemCount / 2))));
                $lastYearLabel = $lastYear . ($lastYear === 1 ? 'st' : ($lastYear === 2 ? 'nd' : ($lastYear === 3 ? 'rd' : 'th'))) . ' Year';
                $lastSemLabel = get_semester_ordinal_title((int)$popSemCount) . ' (Completed)';
                $db->prepare("UPDATE students SET current_year = ?, current_semester = ?, status = 'Graduated' WHERE id = ?")->execute([$lastYearLabel, $lastSemLabel, $targetStudentId]);

                log_audit('ALL_8_SEMESTERS_POPULATED', "Populated complete {$popSemCount} semesters for Student #{$targetStudentId}");
                flash_set('success', "All {$popSemCount} Semesters (" . count($curriculum) . " subjects) populated successfully with marks and credit-weighted CGPA {$resMetrics['cgpa']}!");
            }
        }

        // 8. Simple Automatic CGPA Result Generator (ESTIMATED / GENERATED)
        elseif ($action === 'generate_cgpa_results' && $targetStudentId > 0) {
            $finalCgpa = (float)($_POST['final_cgpa'] ?? 0);
            $academicSession = trim($_POST['academic_session'] ?? '');
            $pubDate = trim($_POST['publication_date'] ?? '');
            $semOverride = isset($_POST['total_semesters']) && $_POST['total_semesters'] !== '' ? (int)$_POST['total_semesters'] : null;
            $publishMode = (($_POST['publish_mode'] ?? 'published') !== 'draft');

            if ($finalCgpa < 2.00 || $finalCgpa > 4.00) {
                flash_set('error', 'Final CGPA must be between 2.00 and 4.00.');
            } elseif ($pubDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pubDate)) {
                flash_set('error', 'Result Publication Date must be a valid YYYY-MM-DD date.');
            } else {
                $genRes = generate_estimated_student_results($targetStudentId, $finalCgpa, $publishMode, $adminId, [
                    'academic_session' => $academicSession,
                    'publication_date' => $pubDate,
                    'total_semesters'  => $semOverride,
                ]);
                if (!empty($genRes['success'])) {
                    flash_set('success', "Generated {$genRes['total_semesters']}-semester ESTIMATED / GENERATED results ({$genRes['inserted_courses']} subjects) for Session {$genRes['academic_session']} (CGPA: " . number_format((float)$genRes['realized_cgpa'], 2) . "). Note: ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT.");
                } else {
                    flash_set('error', $genRes['error'] ?? 'Failed to generate estimated results.');
                }
            }
        }

        // 9. Update Individual Semester GPA Target
        elseif ($action === 'update_semester_gpa' && $targetStudentId > 0) {
            $semNumIn = (int)($_POST['semester_number'] ?? 0);
            $semGpaIn = (float)($_POST['semester_gpa'] ?? 0);
            $reasonIn = trim($_POST['reason'] ?? '');
            if ($semGpaIn < 2.00 || $semGpaIn > 4.00) {
                flash_set('error', 'Semester GPA must be between 2.00 and 4.00.');
            } else {
                $updSem = update_semester_cgpa_target($targetStudentId, $semNumIn, $semGpaIn, $adminId, $reasonIn);
                if (!empty($updSem['success'])) {
                    flash_set('success', "{$updSem['semester_name']} GPA updated and recalculated! Cumulative CGPA: " . number_format((float)$updSem['new_cgpa'], 2) . ".");
                } else {
                    flash_set('error', $updSem['error'] ?? 'Failed to update semester GPA.');
                }
            }
        }

        // 10. Verify All Estimated Results as Official Examination Records
        elseif ($action === 'verify_all_results_official' && $targetStudentId > 0) {
            $verRes = verify_all_student_results_official($targetStudentId, $adminId);
            if (!empty($verRes['success'])) {
                flash_set('success', "Verified {$verRes['verified_count']} subject result(s) as official examination records. CGPA: " . number_format((float)$verRes['cgpa'], 2) . ".");
            } else {
                flash_set('error', $verRes['error'] ?? 'Failed to verify results.');
            }
        }

        // 11. Suspend Result Access (Keep Account & Profile Active, Block All Results)
        elseif ($action === 'suspend_result_access' && $targetStudentId > 0) {
            $reasonIn = trim($_POST['reason'] ?? '');
            $susRes = suspend_student_result_access($targetStudentId, $adminId, $reasonIn);
            if (!empty($susRes['success'])) {
                flash_set('success', "Result access SUSPENDED for {$susRes['full_name']}. Student can still log in and view Profile, while all GPAs/transcripts are blocked.");
            } else {
                flash_set('error', $susRes['error'] ?? 'Failed to suspend result access.');
            }
        }

        // 12. Restore Result Access
        elseif ($action === 'restore_result_access' && $targetStudentId > 0) {
            $rstRes = restore_student_result_access($targetStudentId, $adminId);
            if (!empty($rstRes['success'])) {
                flash_set('success', "Result access RESTORED for {$rstRes['full_name']}.");
            } else {
                flash_set('error', $rstRes['error'] ?? 'Failed to restore result access.');
            }
        }

        // 13. Delete All Academic Results Safely (Keep Student Account & Login Intact)
        elseif ($action === 'delete_all_results' && $targetStudentId > 0) {
            $confirmDel = strtoupper(trim($_POST['confirm_delete_all_results'] ?? ''));
            if ($confirmDel !== 'DELETE') {
                flash_set('error', 'Please type DELETE in confirmation to delete all academic results safely.');
            } else {
                $reasonIn = trim($_POST['reason'] ?? '');
                $delRes = delete_student_all_results($targetStudentId, $adminId, $reasonIn);
                if (!empty($delRes['success'])) {
                    flash_set('success', "Deleted {$delRes['deleted_count']} result record(s) for {$delRes['full_name']}. Student account and login credentials remain active.");
                } else {
                    flash_set('error', $delRes['error'] ?? 'Failed to delete student results.');
                }
            }
        }

        // Redirect preserving student and tab
        header("Location: " . APP_URL . "/admin/results-manager.php?tab={$activeTab}&student_id=" . $targetStudentId);
        exit;
    }
}

// -------------------------------------------------------------------------
// Query Student Directory with Filters (ID, Roll, App No, Username, Name, Dept, Session)
// -------------------------------------------------------------------------
$searchQuery = trim($_GET['search'] ?? '');
$deptFilter = trim($_GET['department'] ?? '');
$sessionFilter = trim($_GET['session'] ?? '');

$studentSql = "
    SELECT s.id, s.student_id, s.roll_number, s.application_number, s.username,
           s.full_name, s.batch, s.cgpa, s.current_semester, s.status, s.results_visible,
           COALESCE(s.result_access_status, 'Active') AS result_access_status,
           s.degree_type, COALESCE(s.total_semesters, p.total_semesters, 8) AS total_semesters,
           p.title_en AS program_title, d.name_en AS dept_name
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE 1=1
";
$studentParams = [];

if (!empty($searchQuery)) {
    $studentSql .= " AND (s.student_id LIKE ? OR s.roll_number LIKE ? OR s.application_number LIKE ? OR s.username LIKE ? OR s.full_name LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
    $likeQ = "%{$searchQuery}%";
    for ($si = 0; $si < 7; $si++) {
        $studentParams[] = $likeQ;
    }
}
if (!empty($deptFilter)) {
    $studentSql .= " AND d.name_en = ?";
    $studentParams[] = $deptFilter;
}
if (!empty($sessionFilter)) {
    $studentSql .= " AND (s.batch = ? OR s.academic_session = ?)";
    $studentParams[] = $sessionFilter;
    $studentParams[] = $sessionFilter;
}

$studentSql .= " ORDER BY (s.status = 'Deleted') ASC, s.student_id ASC";
$studentStmt = $db->prepare($studentSql);
$studentStmt->execute($studentParams);
$allStudents = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct departments & sessions for filter dropdowns
$availableDepts = $db->query("SELECT DISTINCT name_en FROM departments WHERE name_en IS NOT NULL ORDER BY name_en ASC")->fetchAll(PDO::FETCH_COLUMN);
$availableSessions = $db->query("
    SELECT DISTINCT sess_val FROM (
        SELECT TRIM(batch) AS sess_val FROM students WHERE batch IS NOT NULL AND TRIM(batch) != ''
        UNION
        SELECT TRIM(academic_session) AS sess_val FROM students WHERE academic_session IS NOT NULL AND TRIM(academic_session) != ''
    ) AS all_sess
    ORDER BY sess_val ASC
")->fetchAll(PDO::FETCH_COLUMN);

// Determine selected student
$selectedStudentId = (int)($_GET['student_id'] ?? 0);
if ($selectedStudentId <= 0 && !empty($allStudents)) {
    // Default to first student in list
    $selectedStudentId = (int)$allStudents[0]['id'];
}

// Fetch academic summary for selected student
$summary = null;
if ($selectedStudentId > 0) {
    // Admin sees all results (including drafts)
    $summary = get_student_academic_summary($selectedStudentId, false);
}

$progStruct = $summary['program_structure'] ?? [
    'degree_type' => 'Bachelor / Honours',
    'duration_label' => '4 Years (8 Semesters)',
    'total_semesters' => 8,
    'allowed_semesters' => [8],
];
$totalSemesters = max(1, (int)($progStruct['total_semesters'] ?? 8));
$resultGate = $selectedStudentId > 0 ? can_manage_student_results($selectedStudentId) : ['allowed' => false, 'reason' => 'No student selected.'];
$isResultSuspended = !empty($summary['student']) && ($summary['student']['result_access_status'] ?? 'Active') === 'Suspended';
$currentSysSession = get_current_academic_session_name('Fall 2026');
$defaultSess = !empty($summary['student']['academic_session']) ? $summary['student']['academic_session'] : (!empty($summary['student']['batch']) ? $summary['student']['batch'] : $currentSysSession);
$defaultPubDt = !empty($summary['student']['result_publication_date']) ? $summary['student']['result_publication_date'] : date('Y-m-d');
$rmSessionOptions = get_academic_sessions(false, 'DESC');
$stExpCalc = !empty($summary['student']) ? calculate_expected_passing_year(
    $summary['student']['academic_session'] ?: ($summary['student']['batch'] ?? ''),
    (float)($progStruct['duration_years'] ?? ($totalSemesters / 2.0))
) : null;
$stExpectedPassingYear = $stExpCalc ? (int)$stExpCalc['expected_year'] : null;

// Result Requests query
$pendingRequestsCount = (int)$db->query("SELECT COUNT(*) FROM result_requests WHERE status = 'Pending'")->fetchColumn();
$requestsStmt = $db->query("
    SELECT r.*, s.student_id AS st_code, s.full_name AS st_name, p.title_en AS program_name, d.name_en AS dept_name
    FROM result_requests r
    JOIN students s ON r.student_id = s.id
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    ORDER BY r.id DESC
");
$allRequests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

// Result Modification History query
$historyStmt = $db->query("
    SELECT h.*, u.full_name AS admin_name, u.username AS admin_user, s.student_id AS st_code, s.full_name AS st_name
    FROM result_modification_history h
    JOIN users u ON h.admin_id = u.id
    JOIN students s ON h.student_id = s.id
    ORDER BY h.id DESC LIMIT 100
");
$allHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Map semesters by number for selected student
$semestersByNumber = [];
if (!empty($summary['semesters'])) {
    foreach ($summary['semesters'] as $semKey => $semVal) {
        $num = (int)$semVal['semester_number'];
        $semestersByNumber[$num] = [
            'name' => $semKey,
            'data' => $semVal
        ];
    }
}

// Dynamic semester option list (1st Semester .. Nth Semester, preserving existing DB labels)
$standardSemesters = [];
for ($sIdx = 1; $sIdx <= $totalSemesters; $sIdx++) {
    if (isset($semestersByNumber[$sIdx])) {
        $standardSemesters[] = $semestersByNumber[$sIdx]['name'];
    } else {
        $standardSemesters[] = get_semester_ordinal_title($sIdx);
    }
}
foreach ($semestersByNumber as $sn => $sInf) {
    if (!in_array($sInf['name'], $standardSemesters, true)) {
        $standardSemesters[] = $sInf['name'];
    }
}

// Prepare preview data array for instant modal previews (1..$totalSemesters)
$previewData = [];
for ($pSem = 1; $pSem <= $totalSemesters; $pSem++) {
    if (isset($semestersByNumber[$pSem])) {
        $pData = $semestersByNumber[$pSem]['data'];
        $previewData[$pSem] = [
            'name' => $semestersByNumber[$pSem]['name'],
            'number' => $pSem,
            'courses' => $pData['courses'],
            'reg_credits' => $pData['registered_credits'],
            'earned_credits' => $pData['earned_credits'],
            'sgpa' => $pData['sgpa'],
            'cgpa' => $pData['cumulative_cgpa'],
            'status' => $pData['status'],
            'is_estimated' => !empty($pData['is_estimated']),
        ];
    } else {
        $previewData[$pSem] = [
            'name' => $standardSemesters[$pSem - 1] ?? get_semester_ordinal_title($pSem),
            'number' => $pSem,
            'courses' => [],
            'reg_credits' => 0.0,
            'earned_credits' => 0.0,
            'sgpa' => 0.00,
            'cgpa' => 0.00,
            'status' => 'Pending',
            'is_estimated' => false,
        ];
    }
}

include __DIR__ . '/includes/admin-header.php';
?>

<style>
  .rm-workspace-grid > *, .rm-sgpa-strip > * { min-width: 0; }
  @media (max-width: 960px) {
    .rm-filter-form { grid-template-columns: 1fr !important; }
    .rm-profile-banner { grid-template-columns: 1fr !important; text-align: left !important; }
    .rm-profile-cgpa { border-left: none !important; padding-left: 0 !important; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 1rem; text-align: left !important; }
    .rm-sgpa-strip { grid-template-columns: repeat(4, 1fr) !important; }
    .rm-workspace-grid { grid-template-columns: 1fr !important; }
    .admin-cgpa-grid { grid-template-columns: 1fr 1fr !important; }
  }
  @media (max-width: 520px) {
    .rm-sgpa-strip { grid-template-columns: repeat(2, 1fr) !important; }
    .admin-cgpa-grid { grid-template-columns: 1fr !important; }
  }
  @media (max-width: 360px) {
    .rm-profile-banner { padding: 1rem !important; gap: 1rem !important; }
  }
</style>

<!-- Top Header with Actions -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem;">
      <i class="fas fa-award" style="color:var(--rmu-primary);"></i> <?= ($totalSemesters === 8) ? '8-Semester Results &amp; CGPA Management' : "{$totalSemesters}-Semester Results &amp; CGPA Management" ?>
    </h2>
    <p style="color:#64748b; font-size:0.88rem; margin:0;">
      Official Rabindra Maitree University <?= $totalSemesters ?>-Semester Examination Evaluation, Grade Publishing, and Student Inquiry Management
    </p>
  </div>

  <div style="display:flex; gap:0.6rem; flex-wrap:wrap; align-items:center;">
    <?php if ($selectedStudentId > 0 && !empty($summary['student'])): ?>
      <?php if (!empty($resultGate['allowed'])): ?>
        <form action="" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Re-populate or reset standard RMU curriculum (<?= $totalSemesters ?> semesters) for this student? Existing records will be updated.');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="populate_all_8_semesters">
          <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
          <button type="submit" class="btn-primary" style="background:#0f766e; padding:0.55rem 1rem; font-size:0.86rem; text-decoration:none; border:none; cursor:pointer;" title="Populate Standard RMU 8-Semester Curriculum">
            <i class="fas fa-magic"></i> <?= ($totalSemesters === 8) ? 'Populate Standard RMU 8-Semester Curriculum' : "Populate Standard RMU {$totalSemesters}-Semester Curriculum" ?>
          </button>
        </form>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $selectedStudentId ?>&semester=all&download=pdf" class="btn-primary" style="background:#15803d; padding:0.55rem 1.1rem; font-size:0.86rem; text-decoration:none;">
        <i class="fas fa-file-pdf"></i> Official Transcript PDF
      </a>
      <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $selectedStudentId ?>&semester=all" target="_blank" class="btn-primary" style="background:#475569; padding:0.55rem 1.0rem; font-size:0.86rem; text-decoration:none;">
        <i class="fas fa-external-link-alt"></i> View Transcript
      </a>
      <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= $selectedStudentId ?>" class="btn-primary" style="background:#0284c7; padding:0.55rem 1.1rem; font-size:0.86rem; text-decoration:none;">
        <i class="fas fa-user-graduate"></i> Complete Profile
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Primary Tabs Navigation -->
<div style="display:flex; gap:0.5rem; border-bottom:2px solid #e2e8f0; margin-bottom:1.75rem; overflow-x:auto;">
  <a href="?tab=editor<?= $selectedStudentId ? '&student_id='.$selectedStudentId : '' ?>" style="padding:0.75rem 1.4rem; text-decoration:none; font-weight:700; font-size:0.92rem; color:<?= ($activeTab === 'editor') ? 'var(--rmu-primary)' : '#64748b' ?>; border-bottom:3px solid <?= ($activeTab === 'editor') ? 'var(--rmu-primary)' : 'transparent' ?>; display:inline-flex; align-items:center; gap:8px;">
    <i class="fas fa-edit"></i> 8-Semester Result Editor
  </a>
  <a href="?tab=requests<?= $selectedStudentId ? '&student_id='.$selectedStudentId : '' ?>" style="padding:0.75rem 1.4rem; text-decoration:none; font-weight:700; font-size:0.92rem; color:<?= ($activeTab === 'requests') ? 'var(--rmu-primary)' : '#64748b' ?>; border-bottom:3px solid <?= ($activeTab === 'requests') ? 'var(--rmu-primary)' : 'transparent' ?>; display:inline-flex; align-items:center; gap:8px;">
    <i class="fas fa-inbox"></i> Student Result Requests
    <?php if ($pendingRequestsCount > 0): ?>
      <span style="background:#ef4444; color:#fff; font-size:0.72rem; padding:2px 7px; border-radius:12px; font-weight:800;"><?= $pendingRequestsCount ?> Pending</span>
    <?php endif; ?>
  </a>
  <a href="?tab=history<?= $selectedStudentId ? '&student_id='.$selectedStudentId : '' ?>" style="padding:0.75rem 1.4rem; text-decoration:none; font-weight:700; font-size:0.92rem; color:<?= ($activeTab === 'history') ? 'var(--rmu-primary)' : '#64748b' ?>; border-bottom:3px solid <?= ($activeTab === 'history') ? 'var(--rmu-primary)' : 'transparent' ?>; display:inline-flex; align-items:center; gap:8px;">
    <i class="fas fa-history"></i> Modification Audit Trail
  </a>
</div>

<!-- ========================================================================= -->
<!-- TAB 1: 8-SEMESTER STUDENT RESULT EDITOR                                    -->
<!-- ========================================================================= -->
<?php if ($activeTab === 'editor'): ?>

  <!-- Search & Filter Bar -->
  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem 1.5rem; margin-bottom:2rem; box-shadow:0 1px 4px rgba(0,0,0,0.04);">
    <form method="GET" action="" class="rm-filter-form" style="display:grid; grid-template-columns:1.5fr 1fr 1fr auto; gap:1rem; align-items:flex-end;">
      <input type="hidden" name="tab" value="editor">

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" style="font-weight:700; font-size:0.82rem; color:#0f2c59;">
          <i class="fas fa-search"></i> Search by ID / Roll / App No / Name:
        </label>
        <input type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="e.g. RMU2601001, 2026-0001, RMU-2026-0001, Name..." class="form-control" style="font-size:0.88rem;">
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" style="font-weight:700; font-size:0.82rem; color:#0f2c59;">
          <i class="fas fa-layer-group"></i> Department:
        </label>
        <select name="department" class="form-select" style="font-size:0.88rem;">
          <option value="">All Departments</option>
          <?php foreach ($availableDepts as $dName): ?>
            <option value="<?= e($dName) ?>" <?= ($deptFilter === $dName) ? 'selected' : '' ?>><?= e($dName) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" style="font-weight:700; font-size:0.82rem; color:#0f2c59;">
          <i class="fas fa-calendar-alt"></i> Academic Session:
        </label>
        <select name="session" class="form-select" style="font-size:0.88rem;">
          <option value="">All Sessions</option>
          <?php foreach ($availableSessions as $sName): ?>
            <option value="<?= e($sName) ?>" <?= ($sessionFilter === $sName) ? 'selected' : '' ?>><?= e($sName) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn-primary" style="padding:0.65rem 1.25rem; font-size:0.88rem; white-space:nowrap;">
        <i class="fas fa-filter"></i> Filter Students
      </button>
    </form>

    <!-- Student Quick Select Dropdown -->
    <div style="margin-top:1rem; padding-top:1rem; border-top:1px dashed #e2e8f0; display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
      <label style="font-size:0.84rem; font-weight:700; color:#334155; white-space:nowrap;">Select / Search Student (<?= count($allStudents) ?> found):</label>
      <form method="GET" action="" style="flex:1; min-width:260px; margin:0;">
        <input type="hidden" name="tab" value="editor">
        <select name="student_id" class="form-select" onchange="this.form.submit()" style="font-size:0.9rem; font-weight:600; width:100%;">
          <?php foreach ($allStudents as $st): ?>
            <option value="<?= $st['id'] ?>" <?= ($st['id'] == $selectedStudentId) ? 'selected' : '' ?>>
              <?= e($st['student_id']) ?><?= !empty($st['roll_number']) ? ' [Roll: ' . e($st['roll_number']) . ']' : '' ?> — <?= e($st['full_name']) ?> (<?= e($st['dept_name'] ?? 'N/A') ?> &bull; CGPA: <?= number_format((float)$st['cgpa'], 2) ?>)<?= ($st['status'] !== 'Active') ? ' [' . e($st['status']) . ']' : '' ?><?= (isset($st['results_visible']) && (int)$st['results_visible'] === 0) ? ' [Result Hidden]' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if ($summary && !empty($summary['student'])): ?>
    <?php
      $stObj = $summary['student'];
      $stBadges = get_student_status_badges($stObj);
      $isResVisible = !empty($stBadges['results_visible']);
      $allowedSems = $progStruct['allowed_semesters'] ?? [8];
    ?>

    <!-- Student Academic Profile Banner (Section 2) -->
    <div class="rm-profile-banner" style="background:linear-gradient(135deg, #0f2c59 0%, #1e3a8a 100%); color:#ffffff; border-radius:12px; padding:1.6rem 1.85rem; margin-bottom:1.5rem; box-shadow:0 4px 12px rgba(15, 44, 89, 0.15); display:grid; grid-template-columns:auto 1fr auto; gap:1.75rem; align-items:center;">
      <div style="width:74px; height:74px; border-radius:50%; background:#e0f2fe; color:#0f2c59; display:flex; align-items:center; justify-content:center; font-size:2rem; font-weight:800; border:3px solid #60a5fa;">
        <?= strtoupper(substr($stObj['full_name'], 0, 1)) ?>
      </div>

      <div>
        <div style="display:flex; align-items:center; gap:0.65rem; flex-wrap:wrap; margin-bottom:0.3rem;">
          <h3 style="margin:0; font-size:1.4rem; font-weight:800; color:#ffffff;">
            <?= e($stObj['full_name']) ?> <span style="font-family:monospace; color:#93c5fd; font-size:1.1rem;">(<?= e($stObj['student_id']) ?>)</span>
          </h3>
          <span style="background:<?= e($stBadges['account']['bg']) ?>; color:<?= e($stBadges['account']['color']) ?>; padding:2px 9px; border-radius:99px; font-size:0.74rem; font-weight:800;">
            <i class="fas <?= e($stBadges['account']['icon']) ?>"></i> <?= e($stBadges['account']['label']) ?>
          </span>
          <span style="background:<?= e($stBadges['result']['bg']) ?>; color:<?= e($stBadges['result']['color']) ?>; padding:2px 9px; border-radius:99px; font-size:0.74rem; font-weight:800;">
            <i class="fas <?= e($stBadges['result']['icon']) ?>"></i> <?= e($stBadges['result']['label']) ?>
          </span>
          <span style="background:#fef3c7; color:#92400e; padding:2px 9px; border-radius:99px; font-size:0.74rem; font-weight:800;">
            <i class="fas fa-graduation-cap"></i> <?= e($progStruct['degree_type'] ?? 'Bachelor / Honours') ?> &bull; <?= $totalSemesters ?> Semesters
          </span>
        </div>
        <div style="font-size:0.86rem; color:#cbd5e1; margin-bottom:0.4rem;">
          <strong>Department:</strong> <?= e($stObj['department_name'] ?? 'N/A') ?> &bull; 
          <strong>Program:</strong> <?= e($stObj['program_name'] ?? 'N/A') ?> &bull; 
          <strong>Duration:</strong> <?= e($progStruct['duration_label'] ?? '4 Years (8 Semesters)') ?>
        </div>
        <div style="font-size:0.8rem; color:#cbd5e1; display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:0.75rem;">
          <span><i class="fas fa-id-badge"></i> Roll: <strong><?= e(!empty($stObj['roll_number']) ? $stObj['roll_number'] : 'N/A') ?></strong></span>
          <span><i class="fas fa-file-alt"></i> App ID: <strong><?= e(!empty($stObj['application_number']) ? $stObj['application_number'] : 'N/A') ?></strong></span>
          <span><i class="fas fa-layer-group"></i> Session: <strong><?= e($defaultSess) ?></strong></span>
          <span><i class="fas fa-award"></i> Passing Year: <strong><?= !empty($stObj['passing_year']) ? e($stObj['passing_year']) : 'Not Set' ?></strong><?= $stExpectedPassingYear ? ' <small style="color:#93c5fd;">(Est. ' . (int)$stExpectedPassingYear . ')</small>' : '' ?></span>
          <span><i class="fas fa-calendar-day"></i> Publication Date: <strong><?= !empty($stObj['result_publication_date']) ? e($stObj['result_publication_date']) : 'Not Set' ?></strong></span>
          <span><i class="fas fa-calendar-check"></i> Current: <strong><?= e($stObj['current_semester']) ?></strong></span>
        </div>

        <!-- Quick Visibility, Result Access Suspension, Verification & Safe Result Deletion Controls -->
        <div style="display:flex; gap:0.45rem; flex-wrap:wrap;">
          <form action="" method="POST" style="margin:0;" onsubmit="return confirm('<?= $isResVisible ? 'Hide results from this student without deleting academic records?' : 'Show results in the Student Portal for this student?' ?>');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $isResVisible ? 'hide_results' : 'show_results' ?>">
            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
            <button type="submit" style="background:<?= $isResVisible ? '#f59e0b' : '#22c55e' ?>; color:<?= $isResVisible ? '#0f172a' : '#ffffff' ?>; border:none; padding:4px 11px; border-radius:5px; font-size:0.78rem; font-weight:800; cursor:pointer;">
              <i class="fas <?= $isResVisible ? 'fa-eye-slash' : 'fa-eye' ?>"></i> <?= $isResVisible ? 'Hide Result' : 'Show Result' ?>
            </button>
          </form>

          <?php if ($isResultSuspended): ?>
            <form action="" method="POST" style="margin:0;" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Restore result portal visibility for ' . $stObj['full_name'] . '?'), ENT_QUOTES, 'UTF-8') ?>);">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="restore_result_access">
              <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
              <button type="submit" style="background:#10b981; color:#ffffff; border:none; padding:4px 11px; border-radius:5px; font-size:0.78rem; font-weight:800; cursor:pointer;">
                <i class="fas fa-unlock"></i> Restore Result Access
              </button>
            </form>
          <?php else: ?>
            <button type="button" onclick="openRmSuspendModal()" style="background:#ea580c; color:#ffffff; border:none; padding:4px 11px; border-radius:5px; font-size:0.78rem; font-weight:800; cursor:pointer;">
              <i class="fas fa-user-lock"></i> Suspend Result Access
            </button>
          <?php endif; ?>

          <?php if (!empty($resultGate['allowed'])): ?>
            <form action="" method="POST" style="margin:0;" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Publish ALL semesters results for ' . $stObj['full_name'] . '?'), ENT_QUOTES, 'UTF-8') ?>);">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="publish_all_results">
              <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
              <button type="submit" style="background:#16a34a; color:#ffffff; border:none; padding:4px 11px; border-radius:5px; font-size:0.78rem; font-weight:700; cursor:pointer;">
                <i class="fas fa-globe"></i> Publish Result (All)
              </button>
            </form>

            <form action="" method="POST" style="margin:0;" onsubmit="return confirm('Unpublish all published results back to Draft? Data remains safe.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="unpublish_all_results">
              <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
              <button type="submit" style="background:rgba(255,255,255,0.18); color:#ffffff; border:1px solid rgba(255,255,255,0.35); padding:4px 10px; border-radius:5px; font-size:0.78rem; font-weight:700; cursor:pointer;">
                <i class="fas fa-undo"></i> Unpublish Result
              </button>
            </form>

            <?php if (!empty($summary['has_estimated_results'])): ?>
              <form action="" method="POST" style="margin:0;" onsubmit="return confirm('Verify and convert all estimated results to Official Verified University Results?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify_all_results_official">
                <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                <button type="submit" style="background:#059669; color:#ffffff; border:none; padding:4px 11px; border-radius:5px; font-size:0.78rem; font-weight:800; cursor:pointer;">
                  <i class="fas fa-certificate"></i> Verify Official
                </button>
              </form>
            <?php endif; ?>

            <button type="button" onclick="openRmDeleteAllModal()" style="background:#dc2626; color:#ffffff; border:none; padding:4px 10px; border-radius:5px; font-size:0.78rem; font-weight:800; cursor:pointer;">
              <i class="fas fa-trash-alt"></i> Delete All Results (Keep Account)
            </button>
          <?php endif; ?>

          <a href="<?= APP_URL ?>/admin/student-detail.php?id=<?= $selectedStudentId ?>" style="background:#38bdf8; color:#0f172a; padding:4px 10px; border-radius:5px; font-size:0.78rem; font-weight:800; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
            <i class="fas fa-user-shield"></i> Account Controls
          </a>
        </div>
      </div>

      <div class="rm-profile-cgpa" style="text-align:right; border-left:1px solid rgba(255,255,255,0.2); padding-left:2rem;">
        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:#93c5fd; font-weight:700;">Cumulative CGPA</div>
        <div style="font-size:2.4rem; font-weight:900; color:#4ade80; line-height:1.1;">
          <?= number_format((float)$summary['final_cgpa'], 2) ?>
        </div>
        <div style="font-size:0.82rem; color:#cbd5e1; margin-top:3px;">
          <?= (int)$summary['total_earned_credits'] ?> / <?= (int)$stObj['total_credits_required'] ?> Credits Completed
        </div>
      </div>
    </div>

    <?php if (empty($resultGate['allowed'])): ?>
      <!-- Admission Acceptance Gate Lock Notice -->
      <div style="background:#fef2f2; border:2px solid #f87171; border-radius:10px; padding:1.25rem 1.5rem; margin-bottom:1.75rem; color:#991b1b; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div style="display:flex; align-items:center; gap:0.85rem;">
          <i class="fas fa-lock" style="font-size:1.6rem; color:#dc2626;"></i>
          <div>
            <strong style="font-size:1rem; display:block;">Result Management Locked — Pending Application Acceptance</strong>
            <span style="font-size:0.86rem; color:#7f1d1d;"><?= e($resultGate['reason'] ?? 'Only applications accepted by Admin can proceed to Result Management.') ?></span>
          </div>
        </div>
        <a href="<?= APP_URL ?>/admin/admissions.php" class="btn-primary" style="background:#dc2626; padding:0.55rem 1.1rem; font-size:0.85rem; text-decoration:none;">
          <i class="fas fa-file-signature"></i> Open Pending Applications
        </a>
      </div>
    <?php else: ?>
      <!-- Simple Admin Automatic CGPA Result Generator (ESTIMATED / GENERATED) -->
      <div style="background:#fffbeb; border:2px solid #f59e0b; border-radius:10px; padding:1.35rem 1.5rem; margin-bottom:1.75rem; box-shadow:0 2px 8px rgba(245,158,11,0.12);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1rem;">
          <div>
            <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
              <span style="background:#d97706; color:#ffffff; font-size:0.72rem; font-weight:900; padding:2px 8px; border-radius:4px;">ESTIMATED / GENERATED</span>
              <h4 style="margin:0; font-size:1.05rem; font-weight:800; color:#92400e;">
                Automatic <?= $totalSemesters ?>-Semester CGPA Result Generator (<?= e($progStruct['degree_type'] ?? 'Program') ?>)
              </h4>
            </div>
            <p style="margin:3px 0 0; font-size:0.8rem; color:#78350f;">
              Enter final CGPA, academic session, and publication date. Semester GPAs and subject results distribute credit-weighted to match the target CGPA.
              <strong>ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT.</strong>
            </p>
          </div>
        </div>

        <form action="" method="POST" class="admin-cgpa-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:0.85rem; align-items:end;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="generate_cgpa_results">
          <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">

          <div>
            <label style="display:block; font-size:0.78rem; font-weight:700; color:#78350f; margin-bottom:3px;">Final CGPA (out of 4.00) *</label>
            <input type="number" name="final_cgpa" step="0.01" min="2.00" max="4.00" required value="<?= ((float)$summary['final_cgpa'] >= 2.00) ? number_format((float)$summary['final_cgpa'], 2, '.', '') : '3.50' ?>" class="form-control" style="font-weight:800; font-size:1rem; background:#ffffff;">
          </div>

          <div>
            <label style="display:block; font-size:0.78rem; font-weight:700; color:#78350f; margin-bottom:3px;">Academic Session *</label>
            <input type="text" name="academic_session" list="rmSessionOptions" required value="<?= e($defaultSess) ?>" placeholder="e.g. <?= e($currentSysSession) ?>" class="form-control" style="background:#ffffff;">
            <datalist id="rmSessionOptions">
              <?php foreach ($rmSessionOptions as $rms): ?>
                <option value="<?= e($rms['session_name']) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>

          <div>
            <label style="display:block; font-size:0.78rem; font-weight:700; color:#78350f; margin-bottom:3px;">Result Publication Date *</label>
            <input type="date" name="publication_date" required value="<?= e($defaultPubDt) ?>" class="form-control" style="background:#ffffff;">
          </div>

          <?php if (count($allowedSems) > 1): ?>
            <div>
              <label style="display:block; font-size:0.78rem; font-weight:700; color:#78350f; margin-bottom:3px;">Program Semesters</label>
              <select name="total_semesters" class="form-select" style="background:#ffffff; font-weight:600;">
                <?php foreach ($allowedSems as $asCnt): ?>
                  <option value="<?= (int)$asCnt ?>" <?= ((int)$asCnt === $totalSemesters) ? 'selected' : '' ?>><?= (int)$asCnt ?> Semesters</option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php else: ?>
            <input type="hidden" name="total_semesters" value="<?= $totalSemesters ?>">
          <?php endif; ?>

          <div>
            <label style="display:block; font-size:0.78rem; font-weight:700; color:#78350f; margin-bottom:3px;">Visibility Mode</label>
            <select name="publish_mode" class="form-select" style="background:#ffffff;">
              <option value="published" selected>Published (Student Portal)</option>
              <option value="draft">Draft (Admin Preview Only)</option>
            </select>
          </div>

          <div>
            <button type="submit" class="btn-primary" style="width:100%; background:#d97706; border:none; padding:0.62rem 1rem; font-weight:800; justify-content:center; cursor:pointer;">
              <i class="fas fa-bolt"></i> Generate Result
            </button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <!-- 8-Semester SGPA Progression Overview Strip (Section 3 & 4) -->
    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; margin-bottom:2rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.85rem; flex-wrap:wrap; gap:0.5rem;">
        <h4 style="margin:0; font-size:0.95rem; font-weight:800; color:#0f2c59; text-transform:uppercase; letter-spacing:0.5px;">
          <i class="fas fa-chart-line" style="color:var(--rmu-primary);"></i> <?= ($totalSemesters === 8) ? '8-Semester SGPA Progression Overview &amp; Completion Status' : "{$totalSemesters}-Semester SGPA Progression Overview &amp; Completion Status" ?>
          <span style="font-size:0.75rem; font-weight:700; color:#0284c7; text-transform:none;">(Configured: <?= $totalSemesters ?> Semesters)</span>
        </h4>
        <span style="font-size:0.82rem; color:#15803d; font-weight:700;">
          Credit-Weighted Cumulative CGPA: <?= number_format((float)$summary['final_cgpa'], 2) ?> / 4.00
        </span>
      </div>
      <div class="rm-sgpa-strip" style="display:grid; grid-template-columns:repeat(<?= min(8, $totalSemesters) ?>, 1fr); gap:0.6rem; text-align:center;">
        <?php for ($sem = 1; $sem <= $totalSemesters; $sem++): ?>
          <?php 
            $hasSem = isset($summary['semester_gpas'][$sem]); 
            $sgpaVal = $hasSem ? $summary['semester_gpas'][$sem] : null;
            $semIsEst = !empty($semestersByNumber[$sem]['data']['is_estimated']);
          ?>
          <div style="background:<?= $hasSem ? ($semIsEst ? '#fffbeb' : '#f0fdf4') : '#f8fafc' ?>; border:1.5px solid <?= $hasSem ? ($semIsEst ? '#fbbf24' : '#86efac') : '#e2e8f0' ?>; border-radius:8px; padding:0.6rem 0.35rem;">
            <div style="font-size:0.72rem; font-weight:700; color:<?= $hasSem ? '#166534' : '#94a3b8' ?>;">
              Sem <?= $sem ?>
              <?php if ($semIsEst): ?><span style="color:#b45309; font-size:0.62rem;">(EST)</span><?php endif; ?>
            </div>
            <div style="font-size:1.15rem; font-weight:800; color:<?= $hasSem ? '#15803d' : '#cbd5e1' ?>; margin:2px 0;">
              <?= $hasSem ? number_format((float)$sgpaVal, 2) : '—' ?>
            </div>
            <div style="font-size:0.65rem; font-weight:700; color:<?= $hasSem ? '#15803d' : '#64748b' ?>; margin-bottom:4px;">
              <?= $hasSem ? '✓ Completed' : 'Pending' ?>
            </div>
            <?php if (!empty($resultGate['allowed'])): ?>
              <form action="" method="POST" style="display:flex; gap:2px; justify-content:center; align-items:center; margin-top:4px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_semester_gpa">
                <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                <input type="hidden" name="semester_number" value="<?= $sem ?>">
                <input type="number" name="semester_gpa" step="0.01" min="2.00" max="4.00" value="<?= $hasSem ? number_format((float)$sgpaVal, 2, '.', '') : '3.50' ?>" aria-label="Semester <?= $sem ?> GPA" style="width:54px; padding:2px 3px; font-size:0.72rem; border:1px solid #cbd5e1; border-radius:4px; text-align:center;">
                <button type="submit" style="background:#0f2c59; color:#ffffff; border:none; border-radius:4px; padding:2px 5px; font-size:0.68rem; font-weight:700; cursor:pointer;" title="Save Semester <?= $sem ?> GPA">Save</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- 2-Column Workspace: Dynamic Semester Result Blocks (Left) + Add Result Form (Right) -->
    <div class="rm-workspace-grid" style="display:grid; grid-template-columns:minmax(0, 2.1fr) minmax(0, 1fr); gap:2rem; align-items:flex-start;">
      
      <!-- Left Column: Individual Semester Managers (1..$totalSemesters) -->
      <div>
        <!-- Complete Dynamic Semester Results Workspace (Sections 1, 2, 3, 4, 6) -->
        <?php for ($semNum = 1; $semNum <= $totalSemesters; $semNum++): ?>
          <?php
            $hasData = isset($semestersByNumber[$semNum]);
            $semName = $hasData ? $semestersByNumber[$semNum]['name'] : ($standardSemesters[$semNum - 1] ?? get_semester_ordinal_title($semNum));
            $sData = $hasData ? $semestersByNumber[$semNum]['data'] : null;
            $allCoursesPublished = false;
            $hasDraftCourses = false;
            $pubDate = null;
            $semIsEstimated = $hasData && !empty($sData['is_estimated']);
            if ($hasData && !empty($sData['courses'])) {
                $allCoursesPublished = true;
                foreach ($sData['courses'] as $c) {
                    if (!$c['is_published']) { 
                        $allCoursesPublished = false; 
                        $hasDraftCourses = true;
                    }
                }
                $pubDate = !empty($sData['courses'][0]['published_at']) ? format_date($sData['courses'][0]['published_at']) : null;
            }
          ?>
          <div style="background:#ffffff; border:1px solid <?= $hasData ? '#cbd5e1' : '#e2e8f0' ?>; border-radius:10px; margin-bottom:1.75rem; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05);" id="sem-card-<?= $semNum ?>">
            
            <!-- Semester Header with Metrics & Actions -->
            <div style="background:<?= $hasData ? 'linear-gradient(135deg, #0f2c59 0%, #1e3a8a 100%)' : '#f8fafc' ?>; color:<?= $hasData ? '#ffffff' : '#1e293b' ?>; padding:0.85rem 1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; border-bottom:1px solid <?= $hasData ? '#1e3a8a' : '#e2e8f0' ?>;">
              <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
                <span style="background:<?= $hasData ? '#f59e0b' : '#cbd5e1' ?>; color:#0f172a; padding:2px 9px; border-radius:4px; font-weight:900; font-size:0.85rem;">
                  <?= $semNum ?>
                </span>
                <strong style="font-size:1rem; color:<?= $hasData ? '#ffffff' : '#0f2c59' ?>;"><?= e($semName) ?></strong>
                <?php if ($semIsEstimated): ?>
                  <span style="background:#fef3c7; color:#92400e; font-size:0.7rem; font-weight:900; padding:2px 7px; border-radius:4px;">
                    ESTIMATED / GENERATED
                  </span>
                <?php endif; ?>
                <?php if ($hasData): ?>
                  <?php if ($allCoursesPublished): ?>
                    <span style="background:#15803d; color:#ffffff; font-size:0.72rem; font-weight:700; padding:2px 7px; border-radius:4px;">
                      <i class="fas fa-check-circle"></i> Published <?= $pubDate ? "({$pubDate})" : '' ?>
                    </span>
                  <?php else: ?>
                    <span style="background:#d97706; color:#ffffff; font-size:0.72rem; font-weight:700; padding:2px 7px; border-radius:4px;">
                      <i class="fas fa-file-alt"></i> Draft (Unpublished)
                    </span>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="background:#e2e8f0; color:#64748b; font-size:0.72rem; font-weight:700; padding:2px 7px; border-radius:4px;">
                    Pending (No Subjects)
                  </span>
                <?php endif; ?>
              </div>

              <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.82rem; flex-wrap:wrap;">
                <?php if ($hasData): ?>
                  <span style="background:rgba(255,255,255,0.18); padding:3px 8px; border-radius:4px;">
                    Reg: <strong><?= number_format($sData['registered_credits'], 1) ?> Cr</strong>
                  </span>
                  <span style="background:rgba(255,255,255,0.22); padding:3px 8px; border-radius:4px;">
                    SGPA: <strong style="font-size:0.95rem; color:#86efac;"><?= number_format($sData['sgpa'], 2) ?></strong>
                  </span>
                  <span style="background:#15803d; color:#ffffff; padding:3px 9px; border-radius:4px; font-weight:700;">
                    CGPA: <?= number_format($sData['cumulative_cgpa'], 2) ?>
                  </span>

                  <!-- 1. Add Subject Button -->
                  <button type="button" onclick="openAddModalForSemester(<?= htmlspecialchars(json_encode($semName), ENT_QUOTES, 'UTF-8') ?>)" style="background:#0284c7; color:#ffffff; border:none; padding:4px 10px; border-radius:4px; font-size:0.78rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px;" title="Add Subject to this Semester">
                    <i class="fas fa-plus-circle"></i> Add Subject
                  </button>

                  <!-- 2. Save Draft Button -->
                  <?php if ($allCoursesPublished): ?>
                    <form action="" method="POST" style="display:inline;" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Save ' . $semName . ' results as Draft? Results will be hidden from the Student Portal until published.'), ENT_QUOTES, 'UTF-8') ?>);">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="save_draft_semester">
                      <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                      <input type="hidden" name="semester_name" value="<?= e($semName) ?>">
                      <button type="submit" style="background:rgba(255,255,255,0.2); color:#ffffff; border:1px solid rgba(255,255,255,0.4); padding:3px 9px; border-radius:4px; font-size:0.75rem; font-weight:600; cursor:pointer;" title="Revert to Draft">
                        <i class="fas fa-eye-slash"></i> Save Draft
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- 3. Preview Result Button -->
                  <button type="button" onclick="openPreviewModal(<?= $semNum ?>)" style="background:#6366f1; color:#ffffff; border:none; padding:4px 10px; border-radius:4px; font-size:0.78rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px;" title="Preview Unofficial Grade Sheet">
                    <i class="fas fa-eye"></i> Preview
                  </button>

                  <!-- 4. Publish Result Button (Prominent) -->
                  <?php if (!$allCoursesPublished): ?>
                    <form action="" method="POST" style="display:inline;" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Publish all results for ' . $semName . ' to the Student Portal? Students will immediately see their subjects, marks, grades, and SGPA.'), ENT_QUOTES, 'UTF-8') ?>);">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="publish_semester">
                      <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                      <input type="hidden" name="semester_name" value="<?= e($semName) ?>">
                      <button type="submit" style="background:#22c55e; color:#ffffff; border:none; padding:4px 12px; border-radius:4px; font-size:0.78rem; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 6px rgba(34,197,94,0.35);">
                        <i class="fas fa-check-circle"></i> Publish Result
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- 5. PDF Transcript Link -->
                  <a href="<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $selectedStudentId ?>&semester=<?= urlencode($semName) ?>&download=pdf" style="background:#ffffff; color:#0f2c59; border-radius:4px; padding:3px 9px; font-size:0.76rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px;" title="Download <?= e($semName) ?> Transcript PDF">
                    <i class="fas fa-file-pdf" style="color:#dc2626;"></i> PDF
                  </a>

                <?php else: ?>
                  <!-- Pending Semester Action: Add Subject -->
                  <button type="button" onclick="openAddModalForSemester(<?= htmlspecialchars(json_encode($semName), ENT_QUOTES, 'UTF-8') ?>)" class="btn-primary" style="background:#0284c7; padding:4px 12px; font-size:0.8rem; font-weight:700; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:5px;">
                    <i class="fas fa-plus-circle"></i> Add Subject
                  </button>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($hasData && !empty($sData['courses'])): ?>
              <!-- Subject-wise Table with All 8 Required Fields (Section 2) -->
              <div class="table-responsive">
                <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.86rem;">
                  <thead>
                    <tr>
                      <th>Subject Code &bull; Title</th>
                      <th style="width:65px; text-align:center;">Credit</th>
                      <th style="width:75px; text-align:center;">Full</th>
                      <th style="width:85px; text-align:center;">Marks</th>
                      <th style="width:70px; text-align:center;">Grade</th>
                      <th style="width:75px; text-align:center;">Point</th>
                      <th style="width:80px; text-align:center;">Status</th>
                      <th style="text-align:right; width:110px;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($sData['courses'] as $co): ?>
                      <tr>
                        <td>
                          <strong style="color:var(--rmu-primary); font-family:monospace;"><?= e($co['course_code']) ?></strong>
                          <div style="font-weight:600; color:#1e293b;"><?= e($co['course_name']) ?></div>
                          <small style="color:#64748b;"><?= e($co['exam_term']) ?></small>
                          <?php if (!$co['is_published']): ?>
                            <span style="display:inline-block; font-size:0.68rem; background:#fef3c7; color:#b45309; padding:1px 5px; border-radius:3px; font-weight:700; margin-left:4px;">Draft</span>
                          <?php endif; ?>
                          <?php if (!empty($co['correction_reason'])): ?>
                            <div style="font-size:0.72rem; color:#b45309; margin-top:2px;"><i class="fas fa-info-circle"></i> <?= e($co['correction_reason']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td style="text-align:center; font-weight:600;"><?= number_format($co['credit'], 1) ?></td>
                        <td style="text-align:center; color:#64748b;"><?= (int)$co['full_marks'] ?></td>
                        <td style="text-align:center;">
                          <strong style="color:#0f2c59; font-size:0.95rem;"><?= ($co['marks'] !== null) ? number_format($co['marks'], 1) : '—' ?></strong>
                        </td>
                        <td style="text-align:center;">
                          <span style="font-weight:800; color:#1e40af; font-size:0.95rem;"><?= e($co['letter_grade']) ?></span>
                        </td>
                        <td style="text-align:center; font-weight:700; color:#047857;">
                          <?= number_format($co['grade_point'], 2) ?>
                        </td>
                        <td style="text-align:center;">
                          <span style="font-size:0.75rem; font-weight:800; color:<?= ($co['status'] === 'Pass') ? '#15803d' : '#dc2626' ?>; background:<?= ($co['status'] === 'Pass') ? '#dcfce7' : '#fee2e2' ?>; padding:2px 6px; border-radius:4px;">
                            <?= e($co['status']) ?>
                          </span>
                        </td>
                        <td style="text-align:right; white-space:nowrap;">
                          <!-- Edit Result Button with Reason capture -->
                          <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode([
                            'id' => (int)$co['id'],
                            'semester' => $semName,
                            'code' => $co['course_code'],
                            'name' => $co['course_name'],
                            'credit' => (float)$co['credit'],
                            'full_marks' => (int)$co['full_marks'],
                            'marks' => $co['marks'] !== null ? (float)$co['marks'] : '',
                            'letter_grade' => $co['letter_grade'],
                            'grade_point' => (float)$co['grade_point'],
                            'exam_term' => $co['exam_term'],
                            'is_published' => (int)$co['is_published'],
                            'publish_status' => $co['publish_status'] ?? ($co['is_published'] ? 'Published' : 'Draft'),
                            'reason' => $co['correction_reason'] ?? ''
                          ]), ENT_QUOTES, 'UTF-8') ?>)" style="border:none; background:#eff6ff; color:#0284c7; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:0.82rem; margin-right:4px;" title="Edit Subject Result &amp; Correct Marks">
                            <i class="fas fa-edit"></i> Edit
                          </button>

                          <!-- Delete Result Form -->
                          <form action="" method="POST" style="display:inline;" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Delete result for ' . $co['course_code'] . '?'), ENT_QUOTES, 'UTF-8') ?>);">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_result">
                            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                            <input type="hidden" name="result_id" value="<?= $co['id'] ?>">
                            <button type="submit" style="border:none; background:#fee2e2; color:#ef4444; padding:4px 7px; border-radius:4px; cursor:pointer; font-size:0.82rem;" title="Delete Course Result">
                              <i class="fas fa-trash-alt"></i>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                  <tfoot>
                    <tr style="background:#f8fafc; font-weight:700; font-size:0.84rem; border-top:1.5px solid #cbd5e1;">
                      <td style="text-align:right; color:#475569;">Semester Summary (<?= e($semName) ?>):</td>
                      <td style="text-align:center; color:#0f2c59;"><?= number_format($sData['registered_credits'], 1) ?> Cr</td>
                      <td colspan="2" style="text-align:right; color:#1e3a8a;">Earned: <?= number_format($sData['earned_credits'], 1) ?> Cr</td>
                      <td colspan="2" style="text-align:right; color:#1e3a8a;">SGPA: <strong><?= number_format($sData['sgpa'], 2) ?></strong></td>
                      <td colspan="2" style="text-align:center; color:#15803d; font-weight:800;">CGPA: <?= number_format($sData['cumulative_cgpa'], 2) ?></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            <?php else: ?>
              <!-- Clean Pending State for Empty Semester -->
              <div style="padding:1.75rem 1.5rem; text-align:center; background:#fafafa;">
                <p style="margin:0 0 0.75rem; color:#64748b; font-size:0.88rem;">
                  No subjects or examination marks recorded for <strong><?= e($semName) ?></strong> yet.
                </p>
                <button type="button" onclick="openAddModalForSemester(<?= htmlspecialchars(json_encode($semName), ENT_QUOTES, 'UTF-8') ?>)" class="btn-primary" style="background:#0284c7; padding:0.45rem 1.1rem; font-size:0.84rem; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:5px;">
                  <i class="fas fa-plus-circle"></i> Add Subject to <?= e($semName) ?>
                </button>
              </div>
            <?php endif; ?>

          </div>
        <?php endfor; ?>

        <?php if (!empty($resultGate['allowed'])): ?>
        <!-- Import Missing / Previous Semester Results (CSV / Bulk Paste) -->
        <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem 1.5rem; box-shadow:0 1px 4px rgba(0,0,0,0.04); margin-bottom:1.5rem;" id="importResultsCard">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:0.85rem;">
            <div>
              <strong style="color:#0f2c59; font-size:0.98rem; display:flex; align-items:center; gap:0.45rem;">
                <i class="fas fa-file-import" style="color:#0284c7;"></i> Import Missing / Previous Semester Results (CSV / Bulk Paste)
              </strong>
              <small style="color:#64748b;">
                Enter or upload historical / missing semester subjects cleanly without overwriting other semesters. Grades and CGPA recalculate automatically.
              </small>
            </div>
            <button type="button" onclick="fillSampleImportData()" style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:6px; padding:4px 10px; font-size:0.78rem; font-weight:700; cursor:pointer;">
              <i class="fas fa-clipboard-list"></i> Insert Sample Rows
            </button>
          </div>

          <form action="" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_semester_results">
            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:0.75rem; margin-bottom:0.85rem;">
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="font-size:0.8rem;">Target Semester</label>
                <select name="target_semester" id="importTargetSemester" class="form-select">
                  <option value="multi">Auto-Detect Semester Column (CSV / Multi-Semester)</option>
                  <?php foreach ($standardSemesters as $semOpt): ?>
                    <option value="<?= e($semOpt) ?>"><?= e($semOpt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="font-size:0.8rem;">Publication State</label>
                <select name="publish_status" class="form-select">
                  <option value="Published" selected>Published (Live Immediately)</option>
                  <option value="Draft">Draft (Admin Only Until Published)</option>
                  <option value="Under Review">Under Review</option>
                </select>
              </div>

              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="font-size:0.8rem;">Optional CSV File (&le; 1 MB)</label>
                <input type="file" name="csv_file" accept=".csv,.txt" class="form-control" style="padding:0.42rem 0.6rem; font-size:0.82rem;">
              </div>
            </div>

            <div class="form-group" style="margin-bottom:0.75rem;">
              <label class="form-label" style="font-size:0.8rem;">Paste Subject Rows (<code>Semester, Course Code, Subject Name, Credit, Full Marks, Obtained Marks[, Exam Term]</code>)</label>
              <textarea name="import_data" id="importDataTextarea" rows="4" class="form-control" placeholder="1st Semester, CSE 101, Structured Programming, 3.0, 100, 84&#10;2nd Semester, CSE 201, Data Structures, 3.0, 100, 78" style="font-family:monospace; font-size:0.82rem;"></textarea>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
              <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.82rem; color:#334155; font-weight:600; cursor:pointer; margin:0;">
                  <input type="checkbox" name="update_existing" value="1" checked> Update matching Course Code if already recorded
                </label>
                <input type="text" name="reason" value="Missing/Previous semester result import" class="form-control" style="width:260px; font-size:0.8rem; padding:0.35rem 0.6rem;" placeholder="Import reason for audit trail">
              </div>

              <button type="submit" class="btn-primary" style="background:#0284c7; border:none; padding:0.55rem 1.2rem; font-size:0.85rem; cursor:pointer;">
                <i class="fas fa-file-import"></i> Import Semester Results &amp; Recalculate CGPA
              </button>
            </div>
          </form>
        </div>
        <?php endif; ?>

        <!-- Quick Bulk Action Card -->
        <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; box-shadow:0 1px 4px rgba(0,0,0,0.04); margin-bottom:2rem;">
          <div>
            <strong style="color:#0f2c59; font-size:0.95rem; display:block;">Need standard full curriculum?</strong>
            <small style="color:#64748b;">Populate or refresh the standard RMU <?= $totalSemesters ?>-semester curriculum with standard credit hours.</small>
          </div>
          <form action="" method="POST" style="margin:0;" onsubmit="return confirm('Populate / reset standard RMU <?= $totalSemesters ?>-semester curriculum for this student?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="populate_all_8_semesters">
            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
            <button type="submit" class="btn-primary" style="background:#0f766e; padding:0.5rem 1.1rem; font-size:0.84rem; border:none; cursor:pointer;">
              <i class="fas fa-magic"></i> <?= ($totalSemesters === 8) ? 'Populate Standard RMU 8 Semesters' : "Populate Standard RMU {$totalSemesters} Semesters" ?>
            </button>
          </form>
        </div>
      </div>

      <!-- Right Column: Add Subject Result Form with Live Auto-Grading & Reference Scale -->
      <div>
        
        <!-- Add Subject Form Card -->
        <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.5rem; box-shadow:0 1px 4px rgba(0,0,0,0.05); margin-bottom:1.5rem;">
          <h4 style="margin:0 0 1.25rem; font-size:1.1rem; color:#0f2c59; display:flex; align-items:center; gap:0.5rem;">
            <i class="fas fa-plus-circle" style="color:var(--rmu-primary);"></i> Add Subject Result
          </h4>

          <form action="" method="POST" id="addResultForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_result">
            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">

            <div class="form-group">
              <label class="form-label">Semester *</label>
              <select name="semester" class="form-select" required>
                <?php foreach ($standardSemesters as $semOption): ?>
                  <option value="<?= e($semOption) ?>"><?= e($semOption) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Course Code *</label>
              <input type="text" name="course_code" placeholder="e.g. CSE 101" class="form-control" required style="text-transform:uppercase;">
            </div>

            <div class="form-group">
              <label class="form-label">Subject Name *</label>
              <input type="text" name="course_name" placeholder="e.g. Structured Programming in C" class="form-control" required>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
              <div class="form-group">
                <label class="form-label">Credit Hours *</label>
                <input type="number" step="0.5" min="0.5" max="9.5" name="credit" value="3.0" class="form-control" required>
              </div>
              <div class="form-group">
                <label class="form-label">Full Marks *</label>
                <input type="number" min="1" max="500" step="1" name="full_marks" id="addQuickFullMarks" value="100" class="form-control" required oninput="autoCalculateGrade()">
              </div>
            </div>

            <!-- Obtained Marks with Live Auto-Grading (Section 4) -->
            <div style="background:#f0fdf4; border:1.5px solid #86efac; border-radius:8px; padding:1rem; margin-bottom:1rem;">
              <label class="form-label" style="color:#166534; font-weight:700;">
                Obtained Marks <span id="addQuickMarksCapLabel">(out of 100)</span> *
              </label>
              <input type="number" step="0.5" min="0" max="100" name="marks" id="marksInput" placeholder="e.g. 85" class="form-control" required style="font-size:1.1rem; font-weight:800; color:#0f2c59;" oninput="autoCalculateGrade()">
              <small style="color:#15803d; font-size:0.78rem; display:block; margin-top:3px;">
                UGC uniform scale (% of Full Marks): 80%+ = A+ (4.00), 75%+ = A (3.75), 70%+ = A- (3.50)...
              </small>

              <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-top:0.75rem;">
                <div>
                  <label style="font-size:0.75rem; color:#475569; font-weight:600;">Letter Grade:</label>
                  <input type="text" name="letter_grade" id="gradeOutput" value="A+" class="form-control" style="font-weight:800; color:#1e40af;" readonly>
                </div>
                <div>
                  <label style="font-size:0.75rem; color:#475569; font-weight:600;">Grade Point (out of 4.00):</label>
                  <input type="number" step="0.01" min="0" max="4.00" name="grade_point" id="pointOutput" value="4.00" class="form-control" style="font-weight:800; color:#047857;" readonly>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Examination Term</label>
              <input type="text" name="exam_term" value="Semester Final Examination" class="form-control">
            </div>

            <div class="form-group">
              <label class="form-label">Entry / Correction Reason</label>
              <input type="text" name="reason" value="Initial semester examination marks entry" class="form-control" placeholder="Reason for this record">
            </div>

            <div class="form-group" style="margin-bottom:1.25rem;">
              <label class="form-label" style="font-weight:700;">Publication Status (3-State Workflow)</label>
              <select name="publish_status" class="form-select">
                <option value="Published" selected>Published (Live immediately in Student Portal)</option>
                <option value="Draft">Draft (Private - Only Admins can view)</option>
                <option value="Under Review">Under Review (Quality Assurance Check)</option>
              </select>
            </div>

            <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:0.75rem;">
              <i class="fas fa-check-circle"></i> Save Subject &amp; Recalculate CGPA
            </button>
          </form>
        </div>

        <!-- UGC Grading Scale Reference -->
        <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:1.25rem; box-shadow:0 1px 4px rgba(0,0,0,0.04); font-size:0.78rem;">
          <h4 style="margin:0 0 0.65rem; font-size:0.88rem; font-weight:800; color:#0f2c59; text-transform:uppercase;">
            <i class="fas fa-table" style="color:var(--rmu-primary);"></i> UGC Uniform Grading Scale
          </h4>
          <table style="width:100%; border-collapse:collapse;">
            <thead>
              <tr style="background:#f1f5f9; color:#475569; border-bottom:1px solid #cbd5e1;">
                <th style="padding:4px 6px; text-align:left;">Marks Range</th>
                <th style="padding:4px 6px; text-align:center;">Grade</th>
                <th style="padding:4px 6px; text-align:right;">Point</th>
              </tr>
            </thead>
            <tbody>
              <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">80% – 100%</td><td style="text-align:center;"><strong>A+</strong></td><td style="text-align:right;">4.00</td></tr>
              <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">75% – 79%</td><td style="text-align:center;"><strong>A</strong></td><td style="text-align:right;">3.75</td></tr>
              <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">70% – 74%</td><td style="text-align:center;"><strong>A-</strong></td><td style="text-align:right;">3.50</td></tr>
              <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">65% – 69%</td><td style="text-align:center;"><strong>B+</strong></td><td style="text-align:right;">3.25</td></tr>
              <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">60% – 64%</td><td style="text-align:center;"><strong>B</strong></td><td style="text-align:right;">3.00</td></tr>
              <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">55% – 59%</td><td style="text-align:center;"><strong>B-</strong></td><td style="text-align:right;">2.75</td></tr>
              <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">50% – 54%</td><td style="text-align:center;"><strong>C+</strong></td><td style="text-align:right;">2.50</td></tr>
              <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">45% – 49%</td><td style="text-align:center;"><strong>C</strong></td><td style="text-align:right;">2.25</td></tr>
              <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:4px 6px;">40% – 44%</td><td style="text-align:center;"><strong>D</strong></td><td style="text-align:right;">2.00</td></tr>
              <tr><td style="padding:4px 6px;">Below 40%</td><td style="text-align:center; color:#dc2626;"><strong>F</strong></td><td style="text-align:right; color:#dc2626;">0.00</td></tr>
            </tbody>
          </table>
        </div>

      </div>

    </div>

  <?php endif; ?>

<!-- ========================================================================= -->
<!-- TAB 2: STUDENT RESULT INQUIRIES & UPDATE REQUESTS (Section 5)              -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'requests'): ?>

  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05); margin-bottom:2rem;">
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div>
        <h3 style="margin:0 0 0.2rem; font-size:1.15rem; color:#0f2c59; font-weight:800;">
          <i class="fas fa-inbox" style="color:var(--rmu-primary);"></i> Student Result Inquiries &amp; Correction Requests
        </h3>
        <p style="margin:0; font-size:0.84rem; color:#64748b;">
          Review and process grade re-check, missing marks, and transcript requests submitted by students.
        </p>
      </div>
      <div>
        <span style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; font-size:0.8rem; font-weight:700; padding:4px 10px; border-radius:20px;">
          Total Requests: <?= count($allRequests) ?>
        </span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.88rem;">
        <thead>
          <tr>
            <th style="width:60px;">ID</th>
            <th>Student Details</th>
            <th>Semester &bull; Course</th>
            <th>Request Type</th>
            <th>Subject &bull; Description</th>
            <th>Date</th>
            <th>Status</th>
            <th style="text-align:right; width:140px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allRequests)): ?>
            <tr>
              <td colspan="8" style="text-align:center; padding:3rem; color:#64748b;">
                <i class="fas fa-check-circle" style="font-size:2.5rem; color:#86efac; margin-bottom:0.75rem; display:block;"></i>
                No pending result requests at this time.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($allRequests as $req): ?>
              <tr>
                <td><strong style="color:#0f2c59;">#<?= $req['id'] ?></strong></td>
                <td>
                  <strong><?= e($req['st_name']) ?></strong><br>
                  <span style="font-family:monospace; color:#0284c7; font-size:0.8rem; font-weight:700;"><?= e($req['st_code']) ?></span><br>
                  <small style="color:#64748b;"><?= e($req['dept_name']) ?></small>
                </td>
                <td>
                  <strong><?= e($req['semester']) ?></strong>
                  <?php if (!empty($req['course_code'])): ?>
                    <br><span style="font-family:monospace; color:var(--rmu-primary); font-size:0.82rem; font-weight:700;"><?= e($req['course_code']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="background:#f1f5f9; color:#334155; font-size:0.75rem; font-weight:700; padding:2px 7px; border-radius:4px; border:1px solid #cbd5e1;">
                    <?= e($req['request_type']) ?>
                  </span>
                </td>
                <td>
                  <strong style="color:#0f172a; font-size:0.86rem; display:block;"><?= e($req['subject']) ?></strong>
                  <p style="margin:2px 0 0; font-size:0.8rem; color:#475569; max-width:280px; line-height:1.4;">
                    <?= e($req['description']) ?>
                  </p>
                  <?php if (!empty($req['admin_notes'])): ?>
                    <div style="font-size:0.75rem; color:#15803d; margin-top:3px; background:#f0fdf4; padding:2px 6px; border-radius:4px;">
                      <strong>Note:</strong> <?= e($req['admin_notes']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="font-size:0.8rem; color:#64748b; white-space:nowrap;">
                  <?= format_date($req['created_at']) ?>
                </td>
                <td>
                  <?php if ($req['status'] === 'Approved'): ?>
                    <span style="background:#dcfce7; color:#15803d; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:12px;">
                      <i class="fas fa-check-circle"></i> Approved
                    </span>
                  <?php elseif ($req['status'] === 'Rejected'): ?>
                    <span style="background:#fee2e2; color:#b91c1c; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:12px;">
                      <i class="fas fa-times-circle"></i> Rejected
                    </span>
                  <?php elseif ($req['status'] === 'In Review'): ?>
                    <span style="background:#e0f2fe; color:#0369a1; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:12px;">
                      <i class="fas fa-spinner"></i> In Review
                    </span>
                  <?php else: ?>
                    <span style="background:#fef3c7; color:#b45309; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:12px;">
                      <i class="fas fa-clock"></i> Pending
                    </span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <button type="button" onclick="openProcessRequestModal(<?= htmlspecialchars(json_encode([
                    'id' => (int)$req['id'],
                    'student_id' => (int)$req['student_id'],
                    'st_name' => $req['st_name'],
                    'st_code' => $req['st_code'],
                    'semester' => $req['semester'],
                    'course' => $req['course_code'],
                    'type' => $req['request_type'],
                    'subject' => $req['subject'],
                    'desc' => $req['description'],
                    'status' => $req['status'],
                    'notes' => $req['admin_notes'] ?? ''
                  ]), ENT_QUOTES, 'UTF-8') ?>)" class="btn-primary" style="padding:4px 10px; font-size:0.78rem; background:#0284c7;">
                    <i class="fas fa-cogs"></i> Process
                  </button>
                  <a href="?tab=editor&student_id=<?= (int)$req['student_id'] ?>" class="btn-primary" style="padding:4px 8px; font-size:0.78rem; background:#475569; text-decoration:none;" title="Open in Result Editor">
                    <i class="fas fa-external-link-alt"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<!-- ========================================================================= -->
<!-- TAB 3: RESULT MODIFICATION HISTORY & AUDIT TRAIL (Section 8)               -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'history'): ?>

  <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05); margin-bottom:2rem;">
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div>
        <h3 style="margin:0 0 0.2rem; font-size:1.15rem; color:#0f2c59; font-weight:800;">
          <i class="fas fa-history" style="color:var(--rmu-primary);"></i> Result Modification History &amp; Audit Trail
        </h3>
        <p style="margin:0; font-size:0.84rem; color:#64748b;">
          Immutable record of every grade edit, mark change, and subject deletion authorized by university administrators.
        </p>
      </div>
      <div>
        <span style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:0.8rem; font-weight:700; padding:4px 10px; border-radius:20px;">
          <?= count($allHistory) ?> Audit Logs
        </span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.86rem;">
        <thead>
          <tr>
            <th style="width:130px;">Timestamp</th>
            <th>Authorizing Admin</th>
            <th>Student Details</th>
            <th>Course &bull; Semester</th>
            <th>Field Changed</th>
            <th>Previous Value</th>
            <th>Updated Value</th>
            <th>Reason for Correction</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allHistory)): ?>
            <tr>
              <td colspan="8" style="text-align:center; padding:3rem; color:#64748b;">
                No result modifications recorded in history yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($allHistory as $h): ?>
              <tr>
                <td style="font-size:0.8rem; color:#64748b; white-space:nowrap;">
                  <?= date('M d, Y h:i A', strtotime($h['created_at'])) ?>
                </td>
                <td>
                  <strong style="color:#0f2c59;"><?= e($h['admin_name']) ?></strong><br>
                  <small style="color:#64748b;">@<?= e($h['admin_user']) ?></small>
                </td>
                <td>
                  <strong><?= e($h['st_name']) ?></strong><br>
                  <span style="font-family:monospace; color:#0284c7; font-weight:700; font-size:0.8rem;"><?= e($h['st_code']) ?></span>
                </td>
                <td>
                  <strong style="color:var(--rmu-primary); font-family:monospace;"><?= e($h['course_code']) ?></strong><br>
                  <small style="color:#64748b;"><?= e($h['semester']) ?></small>
                </td>
                <td>
                  <span style="background:#eff6ff; color:#1e40af; font-size:0.75rem; font-weight:700; padding:2px 7px; border-radius:4px;">
                    <?= e($h['field_name']) ?>
                  </span>
                </td>
                <td style="color:#64748b; font-family:monospace; font-size:0.82rem;">
                  <?= e($h['old_value'] ?? '—') ?>
                </td>
                <td style="color:#15803d; font-family:monospace; font-weight:700; font-size:0.82rem;">
                  <?= e($h['new_value']) ?>
                </td>
                <td style="color:#334155; font-size:0.82rem; max-width:240px;">
                  <i class="fas fa-quote-left" style="color:#cbd5e1; font-size:0.7rem;"></i> <?= e($h['reason']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

<!-- ========================================================================= -->
<!-- MODAL: EDIT SUBJECT RESULT & CAPTURE REASON                                -->
<!-- ========================================================================= -->
<div id="editResultModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; justify-content:center; align-items:center; padding:1.5rem; overflow-y:auto;">
  <div style="background:#ffffff; border-radius:12px; max-width:560px; width:100%; padding:2rem; box-shadow:0 20px 40px rgba(0,0,0,0.2); position:relative;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.2rem; color:#0f2c59; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-edit" style="color:var(--rmu-primary);"></i> Edit Marks &amp; Result Correction
      </h3>
      <button type="button" onclick="closeEditModal()" style="border:none; background:none; font-size:1.25rem; color:#64748b; cursor:pointer;" title="Close">&times;</button>
    </div>

    <form action="" method="POST" id="editResultForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit_result">
      <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
      <input type="hidden" name="result_id" id="editResultId">

      <div class="form-group">
        <label class="form-label">Semester *</label>
        <select name="semester" id="editSemester" class="form-select" required>
          <?php foreach ($standardSemesters as $semOption): ?>
            <option value="<?= e($semOption) ?>"><?= e($semOption) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 2fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Course Code *</label>
          <input type="text" name="course_code" id="editCourseCode" class="form-control" required style="text-transform:uppercase;">
        </div>
        <div class="form-group">
          <label class="form-label">Course Title *</label>
          <input type="text" name="course_name" id="editCourseName" class="form-control" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Credit Hours *</label>
          <input type="number" step="0.5" min="0.5" max="9.5" name="credit" id="editCredit" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Full Marks *</label>
          <input type="number" min="1" max="500" step="1" name="full_marks" id="editFullMarks" value="100" class="form-control" required oninput="autoCalculateEditGrade()">
        </div>
      </div>

      <!-- Obtained Marks with Auto-Grading in Edit Modal -->
      <div style="background:#f0fdf4; border:1.5px solid #86efac; border-radius:8px; padding:1rem; margin-bottom:1rem;">
        <label class="form-label" style="color:#166534; font-weight:700;">
          Obtained Marks <span id="editMarksCapLabel">(out of 100)</span> *
        </label>
        <input type="number" step="0.5" min="0" max="100" name="marks" id="editMarksInput" class="form-control" required style="font-size:1.15rem; font-weight:800; color:#0f2c59;" oninput="autoCalculateEditGrade()">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-top:0.75rem;">
          <div>
            <label class="form-label" style="font-size:0.8rem; color:#475569;">Letter Grade</label>
            <input type="text" name="letter_grade" id="editGradeOutput" class="form-control" readonly style="font-weight:800; text-align:center; background:#ffffff; color:#1e40af;">
          </div>
          <div>
            <label class="form-label" style="font-size:0.8rem; color:#475569;">Grade Point</label>
            <input type="text" name="grade_point" id="editPointOutput" class="form-control" readonly style="font-weight:800; text-align:center; background:#ffffff; color:#15803d;">
          </div>
        </div>
      </div>

      <!-- Mandatory Reason for Correction (Section 8) -->
      <div class="form-group">
        <label class="form-label" style="color:#b45309; font-weight:700;">
          <i class="fas fa-clipboard-check"></i> Reason for Manual Grade / Mark Correction *
        </label>
        <input type="text" name="correction_reason" id="editReason" class="form-control" required placeholder="e.g. Scrutiny re-check approved by Exam Controller" style="border:1.5px solid #f59e0b;">
        <small style="color:#64748b; font-size:0.78rem;">This reason will be permanently archived in the Result Modification Audit Trail.</small>
      </div>

      <div class="form-group">
        <label class="form-label">Exam Term</label>
        <input type="text" name="exam_term" id="editExamTerm" value="Semester Final Examination" class="form-control">
      </div>

      <div class="form-group" style="margin-bottom:1.5rem;">
        <label class="form-label" style="font-weight:700;">Publication Workflow State</label>
        <select name="publish_status" id="editPublishStatus" class="form-select">
          <option value="Published">Published (Visible in student grade sheet &amp; transcripts)</option>
          <option value="Draft">Draft (Private - Only Admins can see)</option>
          <option value="Under Review">Under Review (Quality Assurance Check)</option>
        </select>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" onclick="closeEditModal()" class="btn-primary" style="background:#e2e8f0; color:#334155; border:none; padding:0.6rem 1.25rem;">
          Cancel
        </button>
        <button type="submit" class="btn-primary" style="background:#1e73be; padding:0.6rem 1.5rem;">
          <i class="fas fa-save"></i> Save &amp; Log Modification
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: ADD SUBJECT RESULT FOR SPECIFIC SEMESTER (Section 2 & 3)            -->
<!-- ========================================================================= -->
<div id="addSubjectModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:9999; justify-content:center; align-items:center; padding:1.5rem; overflow-y:auto;">
  <div style="background:#ffffff; border-radius:12px; max-width:580px; width:100%; padding:2rem; box-shadow:0 25px 50px rgba(0,0,0,0.25); position:relative;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.25rem; color:#0f2c59; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-plus-circle" style="color:var(--rmu-primary);"></i> Add Subject Result
      </h3>
      <button type="button" onclick="closeAddSubjectModal()" style="border:none; background:none; font-size:1.35rem; color:#64748b; cursor:pointer;" title="Close">&times;</button>
    </div>

    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:0.65rem 0.9rem; margin-bottom:1.25rem; font-size:0.86rem; color:#334155;">
      Student: <strong style="color:#0f2c59;"><?= e($summary['student']['full_name'] ?? '') ?></strong> &bull;
      <span style="font-family:monospace; color:#0284c7; font-weight:700;"><?= e($summary['student']['student_id'] ?? '') ?></span>
    </div>

    <form action="" method="POST" id="addSubjectModalForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_result">
      <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">

      <div class="form-group">
        <label class="form-label">Semester *</label>
        <select name="semester" id="addSubjectModalSemester" class="form-select" required>
          <?php foreach ($standardSemesters as $semOption): ?>
            <option value="<?= e($semOption) ?>"><?= e($semOption) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 2fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Course Code *</label>
          <input type="text" name="course_code" id="addModalCourseCode" placeholder="e.g. CSE 101" class="form-control" required style="text-transform:uppercase;">
        </div>
        <div class="form-group">
          <label class="form-label">Course Title *</label>
          <input type="text" name="course_name" id="addModalCourseName" placeholder="e.g. Structured Programming Language" class="form-control" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Credit Hours *</label>
          <input type="number" step="0.5" min="0.5" max="9.5" name="credit" id="addModalCredit" value="3.0" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Full Marks *</label>
          <input type="number" min="1" max="500" step="1" name="full_marks" id="addModalFullMarks" value="100" class="form-control" required oninput="autoCalculateAddGrade()">
        </div>
      </div>

      <!-- Live Auto-Grading & Marks Input -->
      <div style="background:#f0fdf4; border:1.5px solid #86efac; border-radius:8px; padding:1rem; margin-bottom:1rem;">
        <label class="form-label" style="color:#166534; font-weight:700;">
          Obtained Marks <span id="addModalMarksCapLabel">(out of 100)</span> *
        </label>
        <input type="number" step="0.5" min="0" max="100" name="marks" id="addModalMarks" placeholder="e.g. 85.00" class="form-control" required style="font-size:1.15rem; font-weight:800; color:#0f2c59;" oninput="autoCalculateAddGrade()">

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.75rem; margin-top:0.75rem;">
          <div>
            <label class="form-label" style="font-size:0.78rem; color:#475569;">Letter Grade</label>
            <input type="text" name="letter_grade" id="addModalGrade" class="form-control" readonly style="font-weight:800; text-align:center; background:#ffffff; color:#1e40af;">
          </div>
          <div>
            <label class="form-label" style="font-size:0.78rem; color:#475569;">Grade Point</label>
            <input type="text" name="grade_point" id="addModalPoint" class="form-control" readonly style="font-weight:800; text-align:center; background:#ffffff; color:#15803d;">
          </div>
          <div>
            <label class="form-label" style="font-size:0.78rem; color:#475569;">Status</label>
            <input type="text" id="addModalStatus" class="form-control" readonly style="font-weight:800; text-align:center; background:#ffffff; color:#15803d;">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Exam Term</label>
        <input type="text" name="exam_term" value="Semester Final Examination" class="form-control">
      </div>

      <div class="form-group" style="margin-bottom:1.5rem;">
        <label class="form-label" style="font-weight:700;">Publication State</label>
        <select name="publish_status" class="form-select">
          <option value="Published">Publish Immediately to Student Portal (Published)</option>
          <option value="Draft">Save as Draft (Hidden from Student Portal until published)</option>
        </select>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
        <button type="button" onclick="closeAddSubjectModal()" class="btn-primary" style="background:#e2e8f0; color:#334155; border:none; padding:0.6rem 1.25rem;">
          Cancel
        </button>
        <button type="submit" class="btn-primary" style="background:#0284c7; padding:0.6rem 1.5rem;">
          <i class="fas fa-save"></i> Save Subject Result
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: PREVIEW UNOFFICIAL GRADE SHEET & CGPA (Section 6)                   -->
<!-- ========================================================================= -->
<div id="previewResultModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:9999; justify-content:center; align-items:center; padding:1.5rem; overflow-y:auto;">
  <div style="background:#ffffff; border-radius:12px; max-width:760px; width:100%; padding:2.25rem; box-shadow:0 25px 60px rgba(0,0,0,0.3); position:relative; max-height:90vh; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.25rem; border-bottom:2px solid #0f2c59; padding-bottom:1rem;">
      <div>
        <h3 id="prevSemTitle" style="margin:0 0 0.25rem; font-size:1.3rem; color:#0f2c59; font-weight:800;">
          Semester Grade Sheet Preview
        </h3>
        <p style="margin:0; font-size:0.85rem; color:#64748b;">
          Rabindra Maitree University &bull; Office of the Controller of Examinations
        </p>
      </div>
      <button type="button" onclick="closePreviewModal()" style="border:none; background:none; font-size:1.5rem; color:#64748b; cursor:pointer;" title="Close">&times;</button>
    </div>

    <!-- Student Metadata -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.5rem; display:grid; grid-template-columns:1fr 1fr; gap:0.6rem 1.5rem; font-size:0.86rem;">
      <div><span style="color:#64748b;">Student Name:</span> <strong id="prevStName" style="color:#0f172a;"></strong></div>
      <div><span style="color:#64748b;">Student ID:</span> <strong id="prevStId" style="font-family:monospace; color:#0284c7;"></strong></div>
      <div><span style="color:#64748b;">Department:</span> <strong id="prevStDept"></strong></div>
      <div><span style="color:#64748b;">Session / Batch:</span> <strong id="prevStBatch"></strong></div>
    </div>

    <!-- Preview Course Table -->
    <div class="table-responsive" style="margin-bottom:1.5rem;">
      <table class="rmu-table" style="box-shadow:none; margin:0; font-size:0.86rem; border:1px solid #cbd5e1;">
        <thead style="background:#0f2c59; color:#ffffff;">
          <tr>
            <th style="color:#ffffff;">Code</th>
            <th style="color:#ffffff;">Subject Title</th>
            <th style="color:#ffffff; text-align:center;">Credit</th>
            <th style="color:#ffffff; text-align:center;">Marks</th>
            <th style="color:#ffffff; text-align:center;">Grade</th>
            <th style="color:#ffffff; text-align:center;">Point</th>
            <th style="color:#ffffff; text-align:center;">Status</th>
          </tr>
        </thead>
        <tbody id="prevTableBody">
          <!-- Populated by JS -->
        </tbody>
        <tfoot style="background:#f1f5f9; font-weight:700;">
          <tr>
            <td colspan="2" style="text-align:right;">Registered Credits:</td>
            <td id="prevRegCredits" style="text-align:center; color:#0f2c59;"></td>
            <td colspan="2" style="text-align:right;">Earned: <span id="prevEarnedCredits"></span></td>
            <td style="text-align:right; color:#1e40af;">SGPA: <strong id="prevSgpa" style="font-size:0.95rem;"></strong></td>
            <td style="text-align:center; color:#15803d; font-weight:800;">CGPA: <span id="prevCgpa"></span></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div style="font-size:0.8rem; color:#64748b;">
        <i class="fas fa-info-circle" style="color:#0284c7;"></i> Note: Once published, this result is instantly accessible to the student.
      </div>
      <div style="display:flex; gap:0.75rem;">
        <a id="prevPdfLink" href="#" target="_blank" class="btn-primary" style="background:#15803d; text-decoration:none; padding:0.55rem 1.1rem; font-size:0.85rem; display:inline-flex; align-items:center; gap:5px;">
          <i class="fas fa-file-pdf"></i> Official PDF Transcript
        </a>
        <button type="button" onclick="closePreviewModal()" class="btn-primary" style="background:#64748b; border:none; padding:0.55rem 1.25rem; font-size:0.85rem;">
          Close Preview
        </button>
      </div>
    </div>
  </div>
</div>
<div id="processRequestModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; justify-content:center; align-items:center; padding:1.5rem; overflow-y:auto;">
  <div style="background:#ffffff; border-radius:12px; max-width:540px; width:100%; padding:2rem; box-shadow:0 20px 40px rgba(0,0,0,0.2); position:relative;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
      <h3 style="margin:0; font-size:1.2rem; color:#0f2c59; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-clipboard-check" style="color:var(--rmu-primary);"></i> Process Result Request
      </h3>
      <button type="button" onclick="closeProcessRequestModal()" style="border:none; background:none; font-size:1.25rem; color:#64748b; cursor:pointer;" title="Close">&times;</button>
    </div>

    <form action="" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="process_request">
      <input type="hidden" name="request_id" id="procRequestId">

      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:1rem; margin-bottom:1.25rem;">
        <div style="font-size:0.85rem; color:#475569; margin-bottom:4px;">
          Student: <strong id="procStudentName" style="color:#0f2c59;"></strong> (<span id="procStudentCode" style="font-family:monospace;"></span>)
        </div>
        <div style="font-size:0.85rem; color:#475569; margin-bottom:4px;">
          Semester / Course: <strong id="procSemester"></strong> &bull; <strong id="procCourse" style="color:var(--rmu-primary);"></strong>
        </div>
        <div style="font-size:0.85rem; color:#475569; margin-bottom:4px;">
          Request Type: <span id="procType" style="font-weight:700; color:#0284c7;"></span>
        </div>
        <div style="margin-top:6px; padding-top:6px; border-top:1px dashed #cbd5e1; font-size:0.82rem; color:#334155;">
          <strong>Student Description:</strong>
          <p id="procDesc" style="margin:2px 0 0; color:#475569; line-height:1.4;"></p>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" style="font-weight:700;">Update Status *</label>
        <select name="status" id="procStatus" class="form-select" required style="font-weight:700;">
          <option value="Approved">Approved (Grant Correction / Review)</option>
          <option value="Rejected">Rejected (Declined after Scrutiny)</option>
          <option value="In Review">In Review (Under Examination Board)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Controller Office Feedback &bull; Notes to Student</label>
        <textarea name="admin_notes" id="procNotes" rows="3" class="form-control" placeholder="Provide feedback notes to the student regarding this request..."></textarea>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
        <button type="button" onclick="closeProcessRequestModal()" class="btn-primary" style="background:#e2e8f0; color:#334155; border:none; padding:0.6rem 1.25rem;">
          Cancel
        </button>
        <button type="submit" class="btn-primary" style="background:#16a34a; padding:0.6rem 1.5rem;">
          <i class="fas fa-check-circle"></i> Save &amp; Notify Student
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: SUSPEND RESULT PORTAL ACCESS (KEEP ACCOUNT & PROFILE INTACT)        -->
<!-- ========================================================================= -->
<div id="rmSuspendModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:9999; justify-content:center; align-items:center; padding:1.5rem; overflow-y:auto;">
  <div style="background:#ffffff; border-radius:12px; max-width:500px; width:100%; padding:1.75rem; box-shadow:0 20px 40px rgba(0,0,0,0.25);">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem; margin-bottom:1rem;">
      <h3 style="margin:0; font-size:1.15rem; color:#9a3412;"><i class="fas fa-user-lock"></i> Suspend Student Result Access</h3>
      <button type="button" onclick="closeRmSuspendModal()" style="border:none; background:none; font-size:1.3rem; color:#64748b; cursor:pointer;">&times;</button>
    </div>
    <p style="font-size:0.85rem; color:#475569; margin-bottom:1rem;">
      Student <strong><?= e($summary['student']['full_name'] ?? '') ?></strong> will retain portal login and profile access, while ALL CGPA, semester GPAs, transcripts, and subject grades are withheld with a <em>Result Access Suspended</em> notice.
    </p>
    <form action="" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="suspend_result_access">
      <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
      <div class="form-group">
        <label class="form-label" style="font-weight:700;">Suspension Reason *</label>
        <input type="text" name="reason" required value="Result access temporarily suspended by university administration" class="form-control">
      </div>
      <div style="display:flex; justify-content:flex-end; gap:0.6rem; margin-top:1.25rem;">
        <button type="button" onclick="closeRmSuspendModal()" class="btn-primary" style="background:#e2e8f0; color:#334155; border:none;">Cancel</button>
        <button type="submit" class="btn-primary" style="background:#ea580c; border:none;"><i class="fas fa-user-lock"></i> Suspend Result Access</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: DELETE ALL RESULTS (KEEP STUDENT ACCOUNT INTACT)                    -->
<!-- ========================================================================= -->
<div id="rmDeleteAllModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:9999; justify-content:center; align-items:center; padding:1.5rem; overflow-y:auto;">
  <div style="background:#ffffff; border-radius:12px; max-width:520px; width:100%; padding:1.75rem; box-shadow:0 20px 40px rgba(0,0,0,0.25);">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #fecaca; padding-bottom:0.75rem; margin-bottom:1rem;">
      <h3 style="margin:0; font-size:1.15rem; color:#b91c1c;"><i class="fas fa-trash-alt"></i> Delete All Results (Keep Account)</h3>
      <button type="button" onclick="closeRmDeleteAllModal()" style="border:none; background:none; font-size:1.3rem; color:#64748b; cursor:pointer;">&times;</button>
    </div>
    <p style="font-size:0.85rem; color:#7f1d1d; background:#fef2f2; border:1px solid #fecaca; padding:0.75rem; border-radius:6px; margin-bottom:1rem;">
      This deletes all subject and semester academic result rows for <strong><?= e($summary['student']['full_name'] ?? '') ?></strong> and resets published CGPA to 0.00. Their student account, login credentials, and profile remain active.
    </p>
    <form action="" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_all_results">
      <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
      <div class="form-group">
        <label class="form-label" style="font-weight:700;">Reason for Deletion *</label>
        <input type="text" name="reason" required value="Clearing result records while preserving student account" class="form-control">
      </div>
      <div class="form-group">
        <label class="form-label" style="font-weight:700; color:#b91c1c;">Type <code>DELETE</code> to confirm *</label>
        <input type="text" name="confirm_delete_all_results" required placeholder="DELETE" class="form-control" style="border:1.5px solid #ef4444; font-weight:800;">
      </div>
      <div style="display:flex; justify-content:flex-end; gap:0.6rem; margin-top:1.25rem;">
        <button type="button" onclick="closeRmDeleteAllModal()" class="btn-primary" style="background:#e2e8f0; color:#334155; border:none;">Cancel</button>
        <button type="submit" class="btn-primary" style="background:#dc2626; border:none;"><i class="fas fa-trash-alt"></i> Delete All Results</button>
      </div>
    </form>
  </div>
</div>

<!-- Auto-Grading & Modal Management Javascript Scripts -->
<script>
var configuredSemestersCount = <?= (int)$totalSemesters ?>;
var previewData = <?= json_encode($previewData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
var currentStudentInfo = {
  name: <?= json_encode($summary['student']['full_name'] ?? '') ?>,
  id: <?= json_encode($summary['student']['student_id'] ?? '') ?>,
  dept: <?= json_encode($summary['student']['department_name'] ?? '') ?>,
  batch: <?= json_encode($summary['student']['batch'] ?? '') ?>,
  program: <?= json_encode($summary['student']['program_name'] ?? '') ?>,
  final_cgpa: <?= json_encode(number_format((float)($summary['final_cgpa'] ?? 0), 2)) ?>
};

function openRmSuspendModal() {
  var el = document.getElementById('rmSuspendModal');
  if (el) el.style.display = 'flex';
}
function closeRmSuspendModal() {
  var el = document.getElementById('rmSuspendModal');
  if (el) el.style.display = 'none';
}
function openRmDeleteAllModal() {
  var el = document.getElementById('rmDeleteAllModal');
  if (el) el.style.display = 'flex';
}
function closeRmDeleteAllModal() {
  var el = document.getElementById('rmDeleteAllModal');
  if (el) el.style.display = 'none';
}

function escHtml(str) {
  return String(str == null ? '' : str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function ugcGradeFromPercentage(pct) {
  if (pct >= 80) return { grade: 'A+', point: '4.00', status: 'Pass' };
  if (pct >= 75) return { grade: 'A',  point: '3.75', status: 'Pass' };
  if (pct >= 70) return { grade: 'A-', point: '3.50', status: 'Pass' };
  if (pct >= 65) return { grade: 'B+', point: '3.25', status: 'Pass' };
  if (pct >= 60) return { grade: 'B',  point: '3.00', status: 'Pass' };
  if (pct >= 55) return { grade: 'B-', point: '2.75', status: 'Pass' };
  if (pct >= 50) return { grade: 'C+', point: '2.50', status: 'Pass' };
  if (pct >= 45) return { grade: 'C',  point: '2.25', status: 'Pass' };
  if (pct >= 40) return { grade: 'D',  point: '2.00', status: 'Pass' };
  return { grade: 'F', point: '0.00', status: 'Fail' };
}

function syncFullMarksCap(fullEl, marksEl, capLabelEl) {
  var fm = fullEl ? parseInt(fullEl.value, 10) : 100;
  if (!isFinite(fm) || fm < 1) fm = 100;
  if (fm > 500) fm = 500;
  if (marksEl) marksEl.max = fm;
  if (capLabelEl) capLabelEl.textContent = '(out of ' + fm + ')';
  return fm;
}

function autoCalculateGrade(marksVal) {
  var marksEl = document.getElementById('marksInput');
  var fullEl = document.getElementById('addQuickFullMarks');
  var capEl = document.getElementById('addQuickMarksCapLabel');
  var fm = syncFullMarksCap(fullEl, marksEl, capEl);
  var raw = (marksVal !== undefined) ? marksVal : (marksEl ? marksEl.value : '');
  var m = parseFloat(raw);
  var gradeEl = document.getElementById('gradeOutput');
  var pointEl = document.getElementById('pointOutput');

  if (raw === '' || isNaN(m) || m < 0) {
    gradeEl.value = '';
    pointEl.value = '';
    return;
  }

  var pct = Math.max(0, Math.min(100, (m / fm) * 100));
  var g = ugcGradeFromPercentage(pct);
  gradeEl.value = g.grade;
  pointEl.value = g.point;
}

function autoCalculateAddGrade(marksVal) {
  var marksEl = document.getElementById('addModalMarks');
  var fullEl = document.getElementById('addModalFullMarks');
  var capEl = document.getElementById('addModalMarksCapLabel');
  var fm = syncFullMarksCap(fullEl, marksEl, capEl);
  var raw = (marksVal !== undefined) ? marksVal : (marksEl ? marksEl.value : '');
  var m = parseFloat(raw);
  var gradeEl = document.getElementById('addModalGrade');
  var pointEl = document.getElementById('addModalPoint');
  var statusEl = document.getElementById('addModalStatus');

  if (raw === '' || isNaN(m) || m < 0) {
    gradeEl.value = '';
    pointEl.value = '';
    statusEl.value = '';
    return;
  }

  var pct = Math.max(0, Math.min(100, (m / fm) * 100));
  var g = ugcGradeFromPercentage(pct);
  gradeEl.value = g.grade;
  pointEl.value = g.point;
  statusEl.value = g.status;
}

function autoCalculateEditGrade(marksVal) {
  var marksEl = document.getElementById('editMarksInput');
  var fullEl = document.getElementById('editFullMarks');
  var capEl = document.getElementById('editMarksCapLabel');
  var fm = syncFullMarksCap(fullEl, marksEl, capEl);
  var raw = (marksVal !== undefined) ? marksVal : (marksEl ? marksEl.value : '');
  var m = parseFloat(raw);
  var gradeEl = document.getElementById('editGradeOutput');
  var pointEl = document.getElementById('editPointOutput');

  if (raw === '' || isNaN(m) || m < 0) {
    gradeEl.value = '';
    pointEl.value = '';
    return;
  }

  var pct = Math.max(0, Math.min(100, (m / fm) * 100));
  var g = ugcGradeFromPercentage(pct);
  gradeEl.value = g.grade;
  pointEl.value = g.point;
}

function fillSampleImportData() {
  var ta = document.getElementById('importDataTextarea');
  if (!ta) return;
  ta.value = [
    '2nd Semester, CSE 201, Data Structures and Algorithms, 3.0, 100, 84',
    '2nd Semester, CSE 202, Data Structures Lab, 1.5, 50, 42',
    '2nd Semester, MAT 201, Linear Algebra and Differential Equations, 3.0, 100, 76',
    '2nd Semester, EEE 201, Digital Logic Design, 3.0, 100, 72'
  ].join('\n');
  ta.focus();
}

function openAddModalForSemester(semName) {
  var sel = document.getElementById('addSubjectModalSemester');
  if (sel) {
    var hasOpt = Array.prototype.some.call(sel.options, function(o) { return o.value === semName; });
    if (!hasOpt && semName) {
      var opt = document.createElement('option');
      opt.value = semName;
      opt.textContent = semName;
      sel.appendChild(opt);
    }
    sel.value = semName;
  }
  document.getElementById('addModalCourseCode').value = '';
  document.getElementById('addModalCourseName').value = '';
  document.getElementById('addModalFullMarks').value = '100';
  syncFullMarksCap(document.getElementById('addModalFullMarks'), document.getElementById('addModalMarks'), document.getElementById('addModalMarksCapLabel'));
  document.getElementById('addModalMarks').value = '';
  document.getElementById('addModalGrade').value = '';
  document.getElementById('addModalPoint').value = '';
  document.getElementById('addModalStatus').value = '';
  document.getElementById('addSubjectModal').style.display = 'flex';
}

function closeAddSubjectModal() {
  document.getElementById('addSubjectModal').style.display = 'none';
}

function openPreviewModal(semNum) {
  var tbody = document.getElementById('prevTableBody');
  tbody.innerHTML = '';

  document.getElementById('prevStName').textContent = currentStudentInfo.name;
  document.getElementById('prevStId').textContent = currentStudentInfo.id;
  document.getElementById('prevStDept').textContent = currentStudentInfo.dept;
  document.getElementById('prevStBatch').textContent = currentStudentInfo.batch;

  if (semNum === 'all') {
    document.getElementById('prevSemTitle').textContent = 'Complete ' + configuredSemestersCount + '-Semester Academic Transcript Preview';
    var totalReg = 0, totalEarn = 0;
    for (var k = 1; k <= configuredSemestersCount; k++) {
      var d = previewData[k];
      if (d && d.courses && d.courses.length > 0) {
        totalReg += parseFloat(d.reg_credits);
        totalEarn += parseFloat(d.earned_credits);
        var semRow = document.createElement('tr');
        semRow.style.background = '#e2e8f0';
        semRow.innerHTML = '<td colspan="7" style="font-weight:800; color:#0f2c59; padding:6px 10px;">Semester ' + k + ': ' + escHtml(d.name) + ' (SGPA: ' + parseFloat(d.sgpa).toFixed(2) + ')</td>';
        tbody.appendChild(semRow);

        d.courses.forEach(function(c) {
          var tr = document.createElement('tr');
          var fullStr = c.full_marks ? (' / ' + parseInt(c.full_marks, 10)) : '';
          tr.innerHTML = '<td style="font-family:monospace; font-weight:700; color:#1e40af;">' + escHtml(c.course_code || '') + '</td>' +
            '<td><strong>' + escHtml(c.course_name || '') + '</strong></td>' +
            '<td style="text-align:center;">' + parseFloat(c.credit).toFixed(1) + '</td>' +
            '<td style="text-align:center; font-weight:700;">' + (c.marks !== null ? parseFloat(c.marks).toFixed(1) + fullStr : '—') + '</td>' +
            '<td style="text-align:center; font-weight:800; color:#1e40af;">' + escHtml(c.letter_grade || '') + '</td>' +
            '<td style="text-align:center; font-weight:700; color:#047857;">' + parseFloat(c.grade_point).toFixed(2) + '</td>' +
            '<td style="text-align:center;"><span style="font-size:0.75rem; font-weight:700; color:' + (c.status === 'Pass' ? '#15803d' : '#dc2626') + ';">' + escHtml(c.status || '') + '</span></td>';
          tbody.appendChild(tr);
        });
      }
    }
    if (tbody.children.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:1.5rem; color:#64748b;">No academic results recorded yet for any semester.</td></tr>';
    }
    document.getElementById('prevRegCredits').textContent = totalReg.toFixed(1) + ' Cr';
    document.getElementById('prevEarnedCredits').textContent = totalEarn.toFixed(1) + ' Cr';
    document.getElementById('prevSgpa').textContent = 'N/A';
    document.getElementById('prevCgpa').textContent = currentStudentInfo.final_cgpa;
    var pdfLink = document.getElementById('prevPdfLink');
    if (pdfLink) {
      pdfLink.href = '<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $selectedStudentId ?>&semester=all&download=pdf';
    }
  } else {
    var data = previewData[semNum];
    if (!data) return;
    document.getElementById('prevSemTitle').textContent = data.name + ' - Unofficial Grade Sheet Preview';

    if (!data.courses || data.courses.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:1.5rem; color:#64748b;">No subjects added to this semester yet.</td></tr>';
    } else {
      data.courses.forEach(function(c) {
        var tr = document.createElement('tr');
        var fullStr = c.full_marks ? (' / ' + parseInt(c.full_marks, 10)) : '';
        tr.innerHTML = '<td style="font-family:monospace; font-weight:700; color:#1e40af;">' + escHtml(c.course_code || '') + '</td>' +
          '<td><strong>' + escHtml(c.course_name || '') + '</strong></td>' +
          '<td style="text-align:center;">' + parseFloat(c.credit).toFixed(1) + '</td>' +
          '<td style="text-align:center; font-weight:700;">' + (c.marks !== null ? parseFloat(c.marks).toFixed(1) + fullStr : '—') + '</td>' +
          '<td style="text-align:center; font-weight:800; color:#1e40af;">' + escHtml(c.letter_grade || '') + '</td>' +
          '<td style="text-align:center; font-weight:700; color:#047857;">' + parseFloat(c.grade_point).toFixed(2) + '</td>' +
          '<td style="text-align:center;"><span style="font-size:0.75rem; font-weight:700; color:' + (c.status === 'Pass' ? '#15803d' : '#dc2626') + ';">' + escHtml(c.status || '') + '</span></td>';
        tbody.appendChild(tr);
      });
    }

    document.getElementById('prevRegCredits').textContent = parseFloat(data.reg_credits).toFixed(1) + ' Cr';
    document.getElementById('prevEarnedCredits').textContent = parseFloat(data.earned_credits).toFixed(1) + ' Cr';
    document.getElementById('prevSgpa').textContent = parseFloat(data.sgpa).toFixed(2);
    document.getElementById('prevCgpa').textContent = parseFloat(data.cgpa).toFixed(2);

    var pdfLink = document.getElementById('prevPdfLink');
    if (pdfLink) {
      pdfLink.href = '<?= APP_URL ?>/student/semester-transcript.php?student_id=<?= $selectedStudentId ?>&semester=' + encodeURIComponent(data.name) + '&download=pdf';
    }
  }

  document.getElementById('previewResultModal').style.display = 'flex';
}

function closePreviewModal() {
  document.getElementById('previewResultModal').style.display = 'none';
}

function openEditModal(data) {
  document.getElementById('editResultId').value = data.id;
  var semSelect = document.getElementById('editSemester');
  if (semSelect) {
    var hasOpt = Array.prototype.some.call(semSelect.options, function(o) { return o.value === data.semester; });
    if (!hasOpt && data.semester) {
      var opt = document.createElement('option');
      opt.value = data.semester;
      opt.textContent = data.semester;
      semSelect.appendChild(opt);
    }
    semSelect.value = data.semester;
  }
  document.getElementById('editCourseCode').value = data.code;
  document.getElementById('editCourseName').value = data.name;
  document.getElementById('editCredit').value = data.credit;
  document.getElementById('editFullMarks').value = data.full_marks || 100;
  syncFullMarksCap(document.getElementById('editFullMarks'), document.getElementById('editMarksInput'), document.getElementById('editMarksCapLabel'));
  document.getElementById('editMarksInput').value = data.marks !== '' ? data.marks : '';
  document.getElementById('editGradeOutput').value = data.letter_grade;
  document.getElementById('editPointOutput').value = parseFloat(data.grade_point).toFixed(2);
  document.getElementById('editReason').value = data.reason || 'Administrative grade verification & update';
  document.getElementById('editExamTerm').value = data.exam_term || 'Semester Final Examination';
  if (document.getElementById('editPublishStatus')) {
    document.getElementById('editPublishStatus').value = data.publish_status || (parseInt(data.is_published) === 1 ? 'Published' : 'Draft');
  }

  var modal = document.getElementById('editResultModal');
  modal.style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('editResultModal').style.display = 'none';
}

function openProcessRequestModal(data) {
  document.getElementById('procRequestId').value = data.id;
  document.getElementById('procStudentName').textContent = data.st_name;
  document.getElementById('procStudentCode').textContent = data.st_code;
  document.getElementById('procSemester').textContent = data.semester;
  document.getElementById('procCourse').textContent = data.course || 'All Subjects';
  document.getElementById('procType').textContent = data.type;
  document.getElementById('procDesc').textContent = data.desc;
  document.getElementById('procStatus').value = data.status || 'Approved';
  document.getElementById('procNotes').value = data.notes || '';

  var modal = document.getElementById('processRequestModal');
  modal.style.display = 'flex';
}

function closeProcessRequestModal() {
  document.getElementById('processRequestModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
  var addModal = document.getElementById('addSubjectModal');
  if (e.target === addModal) closeAddSubjectModal();
  var prevModal = document.getElementById('previewResultModal');
  if (e.target === prevModal) closePreviewModal();
  var editModal = document.getElementById('editResultModal');
  if (e.target === editModal) closeEditModal();
  var procModal = document.getElementById('processRequestModal');
  if (e.target === procModal) closeProcessRequestModal();
  var rmSusModal = document.getElementById('rmSuspendModal');
  if (e.target === rmSusModal) closeRmSuspendModal();
  var rmDelModal = document.getElementById('rmDeleteAllModal');
  if (e.target === rmDelModal) closeRmDeleteAllModal();
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
