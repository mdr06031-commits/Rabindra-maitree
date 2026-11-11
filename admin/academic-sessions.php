<?php
/**
 * Admin Panel: Academic Sessions, Passing Years, Program Durations & Oversight Hub
 * Rabindra Maitri University (RMU)
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_manager_role();
$adminPageTitle = 'Academic Sessions & Passing Years';
$db = getDb();
$currentAdminId = (int)($_SESSION['user_id'] ?? 1);

$activeTab = trim((string)($_GET['tab'] ?? 'sessions'));
if (!in_array($activeTab, ['sessions', 'passing_years', 'programs', 'oversight', 'history'], true)) {
    $activeTab = 'sessions';
}

// =========================================================================
// POST HANDLERS (All CSRF + Manager Role Protected)
// =========================================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $postTab = trim((string)($_POST['redirect_tab'] ?? $activeTab));
    if (!in_array($postTab, ['sessions', 'passing_years', 'programs', 'oversight', 'history'], true)) {
        $postTab = 'sessions';
    }
    $redirectUrl = APP_URL . '/admin/academic-sessions.php?tab=' . urlencode($postTab);
    if (!empty($_POST['redirect_filter'])) {
        $redirectUrl .= '&filter=' . urlencode((string)$_POST['redirect_filter']);
    }
    if (!empty($_POST['redirect_search'])) {
        $redirectUrl .= '&search=' . urlencode((string)$_POST['redirect_search']);
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Security verification failed. Please reload the page and try again.');
        header('Location: ' . $redirectUrl);
        exit;
    }

    $action = trim((string)($_POST['action'] ?? ''));

    // --- 1. ACADEMIC SESSIONS MANAGEMENT ---
    if ($action === 'add_session') {
        $term = trim((string)($_POST['term_name'] ?? ''));
        $year = (int)($_POST['session_year'] ?? 0);
        $isEnabled = isset($_POST['is_enabled']) ? (int)$_POST['is_enabled'] : 1;
        $isCurrent = !empty($_POST['is_current']) ? 1 : 0;
        $notes = trim((string)($_POST['notes'] ?? ''));

        $termMap = ['Spring' => 1, 'Summer' => 2, 'Fall' => 3];
        if (!isset($termMap[$term]) || $year < 2011 || $year > 2050) {
            flash_set('error', 'Please select a valid term (Spring, Summer, Fall) and year between 2011 and 2050.');
        } else {
            $sessionName = "{$term} {$year}";
            $sortOrder = ($year * 10) + $termMap[$term];
            $dup = $db->prepare("SELECT id FROM academic_sessions WHERE session_name = ? LIMIT 1");
            $dup->execute([$sessionName]);
            if ($dup->fetchColumn()) {
                flash_set('error', "Academic Session '{$sessionName}' already exists.");
            } else {
                if ($isCurrent === 1) {
                    $db->exec("UPDATE academic_sessions SET is_current = 0");
                }
                $ins = $db->prepare("
                    INSERT INTO academic_sessions (session_name, term_name, session_year, sort_order, is_enabled, is_current, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$sessionName, $term, $year, $sortOrder, $isEnabled ? 1 : 0, $isCurrent, ($notes !== '' ? $notes : null)]);
                $newId = (int)$db->lastInsertId();
                log_academic_edit_history('session', $newId, null, $currentAdminId, 'session_created', null, $sessionName, $notes !== '' ? $notes : 'Added new academic session');
                log_audit('ACADEMIC_SESSION_ADDED', "Added Academic Session '{$sessionName}' (enabled={$isEnabled}, current={$isCurrent})", $currentAdminId);
                flash_set('success', "Academic Session '{$sessionName}' created successfully.");
            }
        }
    } elseif ($action === 'bulk_add_sessions') {
        $startYear = (int)($_POST['start_year'] ?? 2011);
        $endYear = (int)($_POST['end_year'] ?? (int)date('Y'));
        $termsSelected = isset($_POST['terms']) && is_array($_POST['terms']) ? $_POST['terms'] : ['Spring', 'Summer', 'Fall'];
        $isEnabled = isset($_POST['is_enabled']) ? (int)$_POST['is_enabled'] : 1;
        $termMap = ['Spring' => 1, 'Summer' => 2, 'Fall' => 3];

        if ($startYear < 2011 || $endYear > 2050 || $startYear > $endYear) {
            flash_set('error', 'Bulk range must stay between 2011 and 2050 with Start Year <= End Year.');
        } else {
            $validTerms = array_values(array_intersect(['Spring', 'Summer', 'Fall'], $termsSelected));
            if (empty($validTerms)) {
                $validTerms = ['Spring', 'Summer', 'Fall'];
            }
            $ins = $db->prepare("
                INSERT IGNORE INTO academic_sessions (session_name, term_name, session_year, sort_order, is_enabled, is_current)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $added = 0;
            for ($yr = $startYear; $yr <= $endYear; $yr++) {
                foreach ($validTerms as $t) {
                    $ins->execute(["{$t} {$yr}", $t, $yr, ($yr * 10) + $termMap[$t], $isEnabled ? 1 : 0]);
                    $added += $ins->rowCount();
                }
            }
            log_audit('ACADEMIC_SESSIONS_BULK_ADDED', "Bulk generated {$added} academic session(s) for {$startYear}-{$endYear}", $currentAdminId);
            flash_set('success', "Generated {$added} new academic session(s) across {$startYear}–{$endYear}. Existing records were preserved.");
        }
    } elseif ($action === 'edit_session') {
        $id = (int)($_POST['id'] ?? 0);
        $term = trim((string)($_POST['term_name'] ?? ''));
        $year = (int)($_POST['session_year'] ?? 0);
        $isEnabled = !empty($_POST['is_enabled']) ? 1 : 0;
        $notes = trim((string)($_POST['notes'] ?? ''));
        $termMap = ['Spring' => 1, 'Summer' => 2, 'Fall' => 3];

        $stmt = $db->prepare("SELECT * FROM academic_sessions WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $oldRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldRow || !isset($termMap[$term]) || $year < 2011 || $year > 2050) {
            flash_set('error', 'Invalid session edit payload.');
        } else {
            $newSessionName = "{$term} {$year}";
            $sortOrder = ($year * 10) + $termMap[$term];
            $dup = $db->prepare("SELECT id FROM academic_sessions WHERE session_name = ? AND id != ? LIMIT 1");
            $dup->execute([$newSessionName, $id]);
            if ($dup->fetchColumn()) {
                flash_set('error', "Another Academic Session named '{$newSessionName}' already exists.");
            } else {
                $db->prepare("
                    UPDATE academic_sessions
                    SET session_name = ?, term_name = ?, session_year = ?, sort_order = ?, is_enabled = ?, notes = ?
                    WHERE id = ?
                ")->execute([$newSessionName, $term, $year, $sortOrder, $isEnabled, ($notes !== '' ? $notes : null), $id]);

                $reasonNote = $notes !== '' ? $notes : 'Updated Academic Session configuration';
                if ($oldRow['session_name'] !== $newSessionName) {
                    log_academic_edit_history('session', $id, null, $currentAdminId, 'session_name', $oldRow['session_name'], $newSessionName, $reasonNote);
                }
                if ((int)$oldRow['is_enabled'] !== $isEnabled) {
                    log_academic_edit_history('session', $id, null, $currentAdminId, 'is_enabled', (string)$oldRow['is_enabled'], (string)$isEnabled, $reasonNote);
                }
                log_audit('ACADEMIC_SESSION_UPDATED', "Updated Academic Session #{$id} ({$oldRow['session_name']} -> {$newSessionName}, enabled={$isEnabled})", $currentAdminId);
                flash_set('success', "Academic Session '{$newSessionName}' updated.");
            }
        }
    } elseif ($action === 'toggle_session') {
        $id = (int)($_POST['id'] ?? 0);
        $newVal = !empty($_POST['is_enabled']) ? 1 : 0;
        $stmt = $db->prepare("SELECT * FROM academic_sessions WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $db->prepare("UPDATE academic_sessions SET is_enabled = ? WHERE id = ?")->execute([$newVal, $id]);
            log_academic_edit_history('session', $id, null, $currentAdminId, 'is_enabled', (string)$row['is_enabled'], (string)$newVal, $newVal ? 'Enabled Academic Session for admission intake' : 'Disabled Academic Session from admission intake');
            log_audit('ACADEMIC_SESSION_TOGGLED', ($newVal ? 'Enabled' : 'Disabled') . " Academic Session '{$row['session_name']}'", $currentAdminId);
            flash_set('success', "Academic Session '{$row['session_name']}' " . ($newVal ? 'enabled' : 'disabled') . '.');
        }
    } elseif ($action === 'set_current_session') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM academic_sessions WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $db->exec("UPDATE academic_sessions SET is_current = 0");
            $db->prepare("UPDATE academic_sessions SET is_current = 1, is_enabled = 1 WHERE id = ?")->execute([$id]);
            log_audit('ACADEMIC_SESSION_SET_CURRENT', "Marked '{$row['session_name']}' as active current intake session", $currentAdminId);
            flash_set('success', "'{$row['session_name']}' is now marked as Current Intake (and enabled).");
        }
    }

    // --- 2. PASSING YEARS MANAGEMENT ---
    elseif ($action === 'add_passing_year') {
        $yr = (int)($_POST['year_value'] ?? 0);
        $label = trim((string)($_POST['label'] ?? ''));
        $isEnabled = isset($_POST['is_enabled']) ? (int)$_POST['is_enabled'] : 1;
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($yr < 2011 || $yr > 2050) {
            flash_set('error', 'Passing Year must be between 2011 and 2050.');
        } else {
            if ($label === '') {
                $label = (string)$yr;
            }
            $dup = $db->prepare("SELECT id FROM passing_years WHERE year_value = ? LIMIT 1");
            $dup->execute([$yr]);
            if ($dup->fetchColumn()) {
                flash_set('error', "Passing Year {$yr} already exists.");
            } else {
                $ins = $db->prepare("INSERT INTO passing_years (year_value, label, sort_order, is_enabled, notes) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$yr, $label, $yr, $isEnabled ? 1 : 0, ($notes !== '' ? $notes : null)]);
                $newId = (int)$db->lastInsertId();
                log_academic_edit_history('passing_year', $newId, null, $currentAdminId, 'passing_year_created', null, (string)$yr, $notes !== '' ? $notes : 'Added Passing Year option');
                log_audit('PASSING_YEAR_ADDED', "Added Passing Year {$yr} (label={$label}, enabled={$isEnabled})", $currentAdminId);
                flash_set('success', "Passing Year {$yr} added successfully.");
            }
        }
    } elseif ($action === 'bulk_add_passing_years') {
        $startYear = (int)($_POST['start_year'] ?? 2011);
        $endYear = (int)($_POST['end_year'] ?? 2035);
        $isEnabled = isset($_POST['is_enabled']) ? (int)$_POST['is_enabled'] : 1;

        if ($startYear < 2011 || $endYear > 2050 || $startYear > $endYear) {
            flash_set('error', 'Bulk range must stay between 2011 and 2050 with Start Year <= End Year.');
        } else {
            $ins = $db->prepare("INSERT IGNORE INTO passing_years (year_value, label, sort_order, is_enabled) VALUES (?, ?, ?, ?)");
            $added = 0;
            for ($yr = $startYear; $yr <= $endYear; $yr++) {
                $ins->execute([$yr, (string)$yr, $yr, $isEnabled ? 1 : 0]);
                $added += $ins->rowCount();
            }
            log_audit('PASSING_YEARS_BULK_ADDED', "Bulk generated {$added} passing year(s) ({$startYear}-{$endYear})", $currentAdminId);
            flash_set('success', "Generated {$added} new passing year(s) across {$startYear}–{$endYear}.");
        }
    } elseif ($action === 'edit_passing_year') {
        $id = (int)($_POST['id'] ?? 0);
        $yr = (int)($_POST['year_value'] ?? 0);
        $label = trim((string)($_POST['label'] ?? ''));
        $isEnabled = !empty($_POST['is_enabled']) ? 1 : 0;
        $notes = trim((string)($_POST['notes'] ?? ''));

        $stmt = $db->prepare("SELECT * FROM passing_years WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $oldRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldRow || $yr < 2011 || $yr > 2050) {
            flash_set('error', 'Invalid Passing Year edit payload.');
        } else {
            if ($label === '') {
                $label = (string)$yr;
            }
            $dup = $db->prepare("SELECT id FROM passing_years WHERE year_value = ? AND id != ? LIMIT 1");
            $dup->execute([$yr, $id]);
            if ($dup->fetchColumn()) {
                flash_set('error', "Passing Year {$yr} already exists.");
            } else {
                $db->prepare("UPDATE passing_years SET year_value = ?, label = ?, sort_order = ?, is_enabled = ?, notes = ? WHERE id = ?")
                   ->execute([$yr, $label, $yr, $isEnabled, ($notes !== '' ? $notes : null), $id]);
                $reasonNote = $notes !== '' ? $notes : 'Updated Passing Year configuration';
                if ((int)$oldRow['year_value'] !== $yr) {
                    log_academic_edit_history('passing_year', $id, null, $currentAdminId, 'year_value', (string)$oldRow['year_value'], (string)$yr, $reasonNote);
                }
                if ((int)$oldRow['is_enabled'] !== $isEnabled) {
                    log_academic_edit_history('passing_year', $id, null, $currentAdminId, 'is_enabled', (string)$oldRow['is_enabled'], (string)$isEnabled, $reasonNote);
                }
                log_audit('PASSING_YEAR_UPDATED', "Updated Passing Year #{$id} ({$oldRow['year_value']} -> {$yr}, enabled={$isEnabled})", $currentAdminId);
                flash_set('success', "Passing Year {$yr} updated successfully.");
            }
        }
    } elseif ($action === 'toggle_passing_year') {
        $id = (int)($_POST['id'] ?? 0);
        $newVal = !empty($_POST['is_enabled']) ? 1 : 0;
        $stmt = $db->prepare("SELECT * FROM passing_years WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $db->prepare("UPDATE passing_years SET is_enabled = ? WHERE id = ?")->execute([$newVal, $id]);
            log_academic_edit_history('passing_year', $id, null, $currentAdminId, 'is_enabled', (string)$row['is_enabled'], (string)$newVal, $newVal ? 'Enabled Passing Year for admission form' : 'Disabled Passing Year from admission form');
            log_audit('PASSING_YEAR_TOGGLED', ($newVal ? 'Enabled' : 'Disabled') . " Passing Year {$row['year_value']}", $currentAdminId);
            flash_set('success', "Passing Year {$row['year_value']} " . ($newVal ? 'enabled' : 'disabled') . '.');
        }
    }

    // --- 3. CUSTOMIZE COURSE DURATION & SEMESTERS ---
    elseif ($action === 'update_program_duration') {
        $progId = (int)($_POST['program_id'] ?? 0);
        $degType = trim((string)($_POST['degree_type'] ?? 'Bachelor / Honours'));
        if (!in_array($degType, ['Bachelor / Honours', 'B.Ed', "Master's"], true)) {
            $degType = 'Bachelor / Honours';
        }
        $durYears = max(0.5, min(6.0, round((float)($_POST['duration_years'] ?? 4.0), 1)));
        $totalSems = max(1, min(12, (int)($_POST['total_semesters'] ?? 8)));
        $totalCredits = max(10, min(260, (int)($_POST['total_credits'] ?? ($totalSems * 15))));
        $reason = trim((string)($_POST['reason'] ?? 'Approved curriculum structure update'));

        $pStmt = $db->prepare("SELECT * FROM programs WHERE id = ? LIMIT 1");
        $pStmt->execute([$progId]);
        $oldProg = $pStmt->fetch(PDO::FETCH_ASSOC);
        if ($oldProg) {
            $degLevel = ($degType === "Master's") ? 'Postgraduate' : 'Undergraduate';
            $durLabel = format_duration_years_label($durYears, $totalSems);

            $db->prepare("
                UPDATE programs
                SET degree_type = ?, degree_level = ?, duration_years = ?, total_semesters = ?, total_credits = ?, duration = ?
                WHERE id = ?
            ")->execute([$degType, $degLevel, $durYears, $totalSems, $totalCredits, $durLabel, $progId]);

            // Sync active non-graduated students' duration/semesters & advisory expected year (NEVER modify passing_year!)
            $stStmt = $db->prepare("SELECT id, academic_session, batch FROM students WHERE program_id = ? AND status NOT IN ('Deleted', 'Graduated')");
            $stStmt->execute([$progId]);
            $updSt = $db->prepare("
                UPDATE students
                SET degree_type = ?, duration_years = ?, program_duration = ?, total_semesters = ?, total_credits_required = ?, expected_passing_year = COALESCE(?, expected_passing_year)
                WHERE id = ?
            ");
            foreach ($stStmt->fetchAll(PDO::FETCH_ASSOC) as $stRow) {
                $sessStr = !empty($stRow['academic_session']) ? $stRow['academic_session'] : (string)($stRow['batch'] ?? '');
                $expCalc = calculate_expected_passing_year($sessStr, $durYears);
                $updSt->execute([$degType, $durYears, $durLabel, $totalSems, $totalCredits, ($expCalc['expected_year'] ?? null), (int)$stRow['id']]);
                sync_student_semester_results((int)$stRow['id']);
            }

            if ((string)$oldProg['duration_years'] !== (string)$durYears) {
                log_academic_edit_history('program', $progId, null, $currentAdminId, 'duration_years', (string)$oldProg['duration_years'], (string)$durYears, $reason);
            }
            if ((int)$oldProg['total_semesters'] !== $totalSems) {
                log_academic_edit_history('program', $progId, null, $currentAdminId, 'total_semesters', (string)$oldProg['total_semesters'], (string)$totalSems, $reason);
            }
            if ((string)$oldProg['degree_type'] !== $degType) {
                log_academic_edit_history('program', $progId, null, $currentAdminId, 'degree_type', (string)$oldProg['degree_type'], $degType, $reason);
            }
            log_audit('PROGRAM_DURATION_CUSTOMIZED', "Customized {$oldProg['title_en']}: {$durLabel}, {$totalCredits} Cr. Reason: {$reason}", $currentAdminId);
            flash_set('success', "Updated '{$oldProg['title_en']}' to {$durLabel} &bull; {$totalCredits} Credits.");
        }
    }

    // --- 4. OVERSIGHT: APPROVE HISTORICAL RECORD OR OFFICIAL EDIT ---
    elseif ($action === 'approve_historical') {
        $entityType = trim((string)($_POST['entity_type'] ?? 'student'));
        $recordId = (int)($_POST['record_id'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));
        $res = approve_historical_academic_record($entityType, $recordId, $currentAdminId, $note);
        if (!empty($res['success'])) {
            flash_set('success', 'Historical record approved and logged cleanly. Selected Passing Year remains preserved.');
        } else {
            flash_set('error', $res['error'] ?? 'Failed to approve historical record.');
        }
    } elseif ($action === 'official_edit_record') {
        $entityType = trim((string)($_POST['entity_type'] ?? 'student'));
        $recordId = (int)($_POST['record_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $updates = [
            'academic_session' => trim((string)($_POST['academic_session'] ?? '')),
            'passing_year'     => trim((string)($_POST['passing_year'] ?? '')),
            'program_id'       => (int)($_POST['program_id'] ?? 0),
        ];
        if (!empty($_POST['degree_type'])) {
            $updates['degree_type'] = trim((string)$_POST['degree_type']);
        }
        if (isset($_POST['duration_years']) && is_numeric($_POST['duration_years']) && (float)$_POST['duration_years'] > 0) {
            $updates['duration_years'] = (float)$_POST['duration_years'];
        }
        if (isset($_POST['total_semesters']) && (int)$_POST['total_semesters'] > 0) {
            $updates['total_semesters'] = (int)$_POST['total_semesters'];
        }
        $res = update_academic_record_with_audit($entityType, $recordId, $updates, $currentAdminId, $reason);
        if (!empty($res['success'])) {
            flash_set('success', "Official academic update saved ({$res['changes']} field change(s) audited).");
        } else {
            flash_set('error', $res['error'] ?? 'Failed to update academic record.');
        }
    }

    header('Location: ' . $redirectUrl);
    exit;
}

// =========================================================================
// FETCH DATA FOR VIEWS
// =========================================================================
$allSessions = get_academic_sessions(false, 'DESC');
$allPassingYears = get_passing_years(false, 'DESC');
$allPrograms = $db->query("
    SELECT p.*, d.name_en AS dept_name, d.code AS dept_code
    FROM programs p
    LEFT JOIN departments d ON p.department_id = d.id
    ORDER BY FIELD(p.degree_type, 'Bachelor / Honours', 'B.Ed', \"Master's\"), p.sort_order ASC, p.title_en ASC
")->fetchAll(PDO::FETCH_ASSOC);

$sessEnabledCnt = 0;
$sessDisabledCnt = 0;
$currentSessObj = null;
foreach ($allSessions as $sr) {
    if (!empty($sr['is_enabled'])) $sessEnabledCnt++; else $sessDisabledCnt++;
    if (!empty($sr['is_current'])) $currentSessObj = $sr;
}

$pyEnabledCnt = 0;
$pyDisabledCnt = 0;
foreach ($allPassingYears as $py) {
    if (!empty($py['is_enabled'])) $pyEnabledCnt++; else $pyDisabledCnt++;
}

// Oversight filter parameters
$ovType = trim((string)($_GET['type'] ?? 'all'));
$ovFilter = trim((string)($_GET['filter'] ?? 'all'));
$ovSearch = trim((string)($_GET['search'] ?? ''));

$oversightRows = [];
$ovStats = ['total' => 0, 'discrepancy' => 0, 'needs_review' => 0, 'approved_historical' => 0, 'match' => 0];

$admOvSql = "
    SELECT 'admission' AS entity_type, a.id AS record_id, a.application_ref AS code_primary, a.student_id AS code_secondary,
           a.full_name, a.academic_session, a.passing_year, a.expected_passing_year, a.historical_record_approved,
           a.historical_approved_at, a.historical_approval_note, a.program_id, a.degree_type, a.interested_subject AS program_title,
           COALESCE(p.duration_years, CASE WHEN a.degree_type = 'B.Ed' THEN 1.0 WHEN a.degree_type = 'Master\\'s' THEN 2.0 ELSE 4.0 END) AS duration_years,
           COALESCE(p.total_semesters, CASE WHEN a.degree_type = 'B.Ed' THEN 2 WHEN a.degree_type = 'Master\\'s' THEN 4 ELSE 8 END) AS total_semesters,
           a.status AS record_status
    FROM admissions a
    LEFT JOIN programs p ON a.program_id = p.id
";
$stOvSql = "
    SELECT 'student' AS entity_type, s.id AS record_id, s.student_id AS code_primary, s.application_number AS code_secondary,
           s.full_name, COALESCE(NULLIF(TRIM(s.academic_session), ''), s.batch) AS academic_session, s.passing_year, s.expected_passing_year, s.historical_record_approved,
           s.historical_approved_at, s.historical_approval_note, s.program_id, s.degree_type, COALESCE(p.title_en, 'Program') AS program_title,
           COALESCE(s.duration_years, p.duration_years, CASE WHEN s.degree_type = 'B.Ed' THEN 1.0 WHEN s.degree_type = 'Master\\'s' THEN 2.0 ELSE 4.0 END) AS duration_years,
           COALESCE(s.total_semesters, p.total_semesters, 8) AS total_semesters,
           s.status AS record_status
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    WHERE s.status != 'Deleted'
";

$rawOversight = [];
if ($ovType === 'all' || $ovType === 'admissions') {
    foreach ($db->query($admOvSql . " ORDER BY a.id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rawOversight[] = $r;
    }
}
if ($ovType === 'all' || $ovType === 'students') {
    foreach ($db->query($stOvSql . " ORDER BY s.id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rawOversight[] = $r;
    }
}

foreach ($rawOversight as $row) {
    $eval = evaluate_passing_year_difference(
        (string)$row['academic_session'],
        $row['passing_year'],
        (float)$row['duration_years'],
        !empty($row['historical_record_approved'])
    );
    $row['eval'] = $eval;

    if ($ovSearch !== '') {
        $hay = strtolower(($row['full_name'] ?? '') . ' ' . ($row['code_primary'] ?? '') . ' ' . ($row['code_secondary'] ?? '') . ' ' . ($row['academic_session'] ?? '') . ' ' . ($row['passing_year'] ?? ''));
        if (strpos($hay, strtolower($ovSearch)) === false) {
            continue;
        }
    }

    $ovStats['total']++;
    if ($eval['is_discrepancy']) $ovStats['discrepancy']++;
    if ($eval['needs_review']) $ovStats['needs_review']++;
    if ($eval['status_key'] === 'approved_historical') $ovStats['approved_historical']++;
    if ($eval['status_key'] === 'match') $ovStats['match']++;

    if ($ovFilter === 'discrepancy' && !$eval['is_discrepancy']) continue;
    if ($ovFilter === 'needs_review' && !$eval['needs_review']) continue;
    if ($ovFilter === 'approved_historical' && $eval['status_key'] !== 'approved_historical') continue;
    if ($ovFilter === 'match' && $eval['status_key'] !== 'match') continue;

    $oversightRows[] = $row;
}

// History tab filters
$histEntityFilter = trim((string)($_GET['hist_type'] ?? ''));
$histCodeFilter = trim((string)($_GET['hist_code'] ?? ''));
$historyLogs = get_academic_edit_history($histEntityFilter !== '' ? $histEntityFilter : null, null, $histCodeFilter !== '' ? $histCodeFilter : null, 120);

include __DIR__ . '/includes/admin-header.php';
?>

<style>
  .rmu-tab-bar { display:flex; gap:8px; flex-wrap:wrap; border-bottom:2px solid #e2e8f0; margin-bottom:1.5rem; padding-bottom:0; }
  .rmu-tab-btn { padding:0.7rem 1.15rem; font-size:0.86rem; font-weight:700; color:#475569; text-decoration:none; border-radius:8px 8px 0 0; border:1px solid transparent; border-bottom:none; display:inline-flex; align-items:center; gap:7px; background:#f8fafc; }
  .rmu-tab-btn:hover { background:#e2e8f0; color:#0f172a; }
  .rmu-tab-btn.active { background:#0f172a; color:#ffffff; border-color:#0f172a; }
  .rmu-card { background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:1.35rem; box-shadow:0 1px 4px rgba(0,0,0,0.05); margin-bottom:1.5rem; }
  .rmu-pill { display:inline-flex; align-items:center; gap:4px; padding:2px 9px; border-radius:12px; font-size:0.73rem; font-weight:700; white-space:nowrap; }
  @media (max-width: 600px) { .rmu-grid-2col { grid-template-columns: 1fr !important; } }
</style>

<div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
  <div>
    <h2 style="font-size:1.55rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem;">
      <i class="fas fa-calendar-check" style="color:var(--rmu-primary);"></i> Academic Sessions &amp; Passing Year Management
    </h2>
    <p style="color:#64748b; font-size:0.88rem; margin:0;">
      Manage Academic Sessions (2011+), Passing Years (2011+), Custom Course Durations, Historical Approval &amp; Audit Oversight
    </p>
  </div>
  <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
    <a href="<?= APP_URL ?>/admission-form.php" target="_blank" class="btn-primary" style="background:#0f766e; padding:0.5rem 0.95rem; font-size:0.82rem; text-decoration:none;">
      <i class="fas fa-external-link-alt"></i> Preview Admission Form
    </a>
    <a href="<?= APP_URL ?>/admin/admissions.php" class="btn-primary" style="background:#1e40af; padding:0.5rem 0.95rem; font-size:0.82rem; text-decoration:none;">
      <i class="fas fa-user-graduate"></i> Admissions Queue
    </a>
  </div>
</div>

<!-- Navigation Tabs -->
<nav class="rmu-tab-bar" aria-label="Academic Management Sections">
  <a href="?tab=sessions" class="rmu-tab-btn <?= $activeTab === 'sessions' ? 'active' : '' ?>">
    <i class="fas fa-layer-group"></i> Academic Sessions <span class="rmu-pill" style="background:#e0f2fe; color:#0369a1;"><?= $sessEnabledCnt ?> Active</span>
  </a>
  <a href="?tab=passing_years" class="rmu-tab-btn <?= $activeTab === 'passing_years' ? 'active' : '' ?>">
    <i class="fas fa-graduation-cap"></i> Passing Years <span class="rmu-pill" style="background:#dcfce7; color:#15803d;"><?= $pyEnabledCnt ?> Active</span>
  </a>
  <a href="?tab=programs" class="rmu-tab-btn <?= $activeTab === 'programs' ? 'active' : '' ?>">
    <i class="fas fa-sliders-h"></i> Course Durations &amp; Semesters
  </a>
  <a href="?tab=oversight" class="rmu-tab-btn <?= $activeTab === 'oversight' ? 'active' : '' ?>">
    <i class="fas fa-balance-scale"></i> Expected vs. Passing Year Oversight
    <?php if ($ovStats['needs_review'] > 0): ?>
      <span class="rmu-pill" style="background:#fef3c7; color:#b45309;"><?= $ovStats['needs_review'] ?> Review</span>
    <?php endif; ?>
  </a>
  <a href="?tab=history" class="rmu-tab-btn <?= $activeTab === 'history' ? 'active' : '' ?>">
    <i class="fas fa-history"></i> Academic Edit History
  </a>
</nav>

<?php if ($activeTab === 'sessions'): ?>
  <!-- ==================== TAB 1: ACADEMIC SESSIONS ==================== -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
    <div class="stat-card" style="padding:1rem;">
      <div class="stat-icon" style="background:#dbeafe; color:#1d4ed8;"><i class="fas fa-calendar-alt"></i></div>
      <div>
        <div style="font-size:1.45rem; font-weight:800; color:#0f172a;"><?= count($allSessions) ?></div>
        <div style="font-size:0.78rem; color:#64748b;">Total Sessions Configured</div>
      </div>
    </div>
    <div class="stat-card" style="padding:1rem;">
      <div class="stat-icon" style="background:#dcfce7; color:#15803d;"><i class="fas fa-check-circle"></i></div>
      <div>
        <div style="font-size:1.45rem; font-weight:800; color:#15803d;"><?= $sessEnabledCnt ?></div>
        <div style="font-size:0.78rem; color:#64748b;">Enabled on Admission Form</div>
      </div>
    </div>
    <div class="stat-card" style="padding:1rem;">
      <div class="stat-icon" style="background:#f1f5f9; color:#475569;"><i class="fas fa-eye-slash"></i></div>
      <div>
        <div style="font-size:1.45rem; font-weight:800; color:#475569;"><?= $sessDisabledCnt ?></div>
        <div style="font-size:0.78rem; color:#64748b;">Disabled / Hidden</div>
      </div>
    </div>
    <div class="stat-card" style="padding:1rem;">
      <div class="stat-icon" style="background:#fef3c7; color:#b45309;"><i class="fas fa-star"></i></div>
      <div>
        <div style="font-size:1.1rem; font-weight:800; color:#0f172a;"><?= e($currentSessObj['session_name'] ?? 'None') ?></div>
        <div style="font-size:0.78rem; color:#64748b;">Current Default Intake</div>
      </div>
    </div>
  </div>

  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(320px, 100%), 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <!-- Add Single Academic Session -->
    <div class="rmu-card" style="margin-bottom:0;">
      <h3 style="margin:0 0 0.85rem; font-size:1.02rem; font-weight:800; color:#0f172a;"><i class="fas fa-plus-circle" style="color:var(--rmu-primary);"></i> Add Academic Session</h3>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_session">
        <input type="hidden" name="redirect_tab" value="sessions">
        <div class="rmu-grid-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
          <div class="form-group">
            <label class="form-label">Term *</label>
            <select name="term_name" class="form-select" required>
              <option value="Spring">Spring</option>
              <option value="Summer">Summer</option>
              <option value="Fall" selected>Fall</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Session Year (2011–2050) *</label>
            <input type="number" name="session_year" min="2011" max="2050" value="<?= (int)date('Y') ?>" class="form-control" required>
          </div>
        </div>
        <div class="rmu-grid-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; align-items:center;">
          <div class="form-group">
            <label class="form-label">Admission Visibility</label>
            <select name="is_enabled" class="form-select">
              <option value="1" selected>Enabled (Visible to Students)</option>
              <option value="0">Disabled (Hidden from Form)</option>
            </select>
          </div>
          <div class="form-group" style="padding-top:1.2rem;">
            <label style="display:flex; align-items:center; gap:6px; font-size:0.84rem; font-weight:600; cursor:pointer;">
              <input type="checkbox" name="is_current" value="1"> Mark as Current Intake
            </label>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Admin Note (Optional)</label>
          <input type="text" name="notes" class="form-control" placeholder="e.g. Historical archive batch or extended intake">
        </div>
        <button type="submit" class="btn-primary" style="width:100%; justify-content:center;">
          <i class="fas fa-plus"></i> Add Academic Session
        </button>
      </form>
    </div>

    <!-- Bulk Generate Session Range -->
    <div class="rmu-card" style="margin-bottom:0;">
      <h3 style="margin:0 0 0.85rem; font-size:1.02rem; font-weight:800; color:#0f172a;"><i class="fas fa-magic" style="color:#0284c7;"></i> Bulk Generate Session Range</h3>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bulk_add_sessions">
        <input type="hidden" name="redirect_tab" value="sessions">
        <div class="rmu-grid-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
          <div class="form-group">
            <label class="form-label">Start Year (min 2011) *</label>
            <input type="number" name="start_year" min="2011" max="2050" value="2011" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">End Year (max 2050) *</label>
            <input type="number" name="end_year" min="2011" max="2050" value="2035" class="form-control" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Terms to Generate</label>
          <div style="display:flex; gap:1rem; flex-wrap:wrap; font-size:0.86rem; font-weight:600;">
            <label style="display:flex; align-items:center; gap:5px;"><input type="checkbox" name="terms[]" value="Spring" checked> Spring</label>
            <label style="display:flex; align-items:center; gap:5px;"><input type="checkbox" name="terms[]" value="Summer" checked> Summer</label>
            <label style="display:flex; align-items:center; gap:5px;"><input type="checkbox" name="terms[]" value="Fall" checked> Fall</label>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Initial Status for New Entries</label>
          <select name="is_enabled" class="form-select">
            <option value="1" selected>Enabled (Visible on Online Form)</option>
            <option value="0">Disabled (Admin Only)</option>
          </select>
        </div>
        <button type="submit" class="btn-primary" style="width:100%; justify-content:center; background:#0369a1;">
          <i class="fas fa-bolt"></i> Generate Missing Sessions
        </button>
      </form>
    </div>
  </div>

  <!-- Sessions Table -->
  <div class="rmu-card" style="padding:0; overflow:hidden;">
    <div style="padding:1rem 1.25rem; background:#0f172a; color:#ffffff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
      <strong style="font-size:0.96rem;"><i class="fas fa-list"></i> Configured Academic Sessions (Spring, Summer, Fall &bull; 2011+)</strong>
      <small style="color:#94a3b8;">Only <strong>Enabled</strong> sessions appear in the public Online Admission Form.</small>
    </div>
    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow:none; margin:0;">
        <thead>
          <tr>
            <th>Academic Session</th>
            <th>Term</th>
            <th>Year</th>
            <th>Status</th>
            <th>Notes</th>
            <th style="text-align:right;">Controls</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allSessions as $sr): ?>
            <?php $isEn = ((int)$sr['is_enabled'] === 1); $isCur = ((int)$sr['is_current'] === 1); ?>
            <tr>
              <td>
                <strong style="color:#0f172a;"><?= e($sr['session_name']) ?></strong>
                <?php if ($isCur): ?>
                  <span class="rmu-pill" style="background:#dbeafe; color:#1d4ed8; margin-left:4px;"><i class="fas fa-star"></i> Current Intake</span>
                <?php endif; ?>
              </td>
              <td><?= e($sr['term_name']) ?></td>
              <td><strong><?= (int)$sr['session_year'] ?></strong></td>
              <td>
                <?php if ($isEn): ?>
                  <span class="rmu-pill" style="background:#dcfce7; color:#15803d;"><i class="fas fa-check-circle"></i> Enabled</span>
                <?php else: ?>
                  <span class="rmu-pill" style="background:#fee2e2; color:#991b1b;"><i class="fas fa-ban"></i> Disabled</span>
                <?php endif; ?>
              </td>
              <td style="font-size:0.8rem; color:#64748b;"><?= e($sr['notes'] ?? '—') ?></td>
              <td style="text-align:right; white-space:nowrap;">
                <form method="POST" action="" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_session">
                  <input type="hidden" name="redirect_tab" value="sessions">
                  <input type="hidden" name="id" value="<?= (int)$sr['id'] ?>">
                  <input type="hidden" name="is_enabled" value="<?= $isEn ? '0' : '1' ?>">
                  <button type="submit" style="padding:4px 9px; font-size:0.76rem; font-weight:700; border-radius:5px; cursor:pointer; border:1px solid <?= $isEn ? '#fca5a5; background:#fef2f2; color:#b91c1c;' : '#86efac; background:#f0fdf4; color:#15803d;' ?>">
                    <?= $isEn ? 'Disable' : 'Enable' ?>
                  </button>
                </form>

                <?php if (!$isCur): ?>
                  <form method="POST" action="" style="display:inline; margin-left:4px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set_current_session">
                    <input type="hidden" name="redirect_tab" value="sessions">
                    <input type="hidden" name="id" value="<?= (int)$sr['id'] ?>">
                    <button type="submit" style="padding:4px 9px; font-size:0.76rem; font-weight:700; border-radius:5px; cursor:pointer; border:1px solid #93c5fd; background:#eff6ff; color:#1d4ed8;">
                      Set Current
                    </button>
                  </form>
                <?php endif; ?>

                <button type="button" onclick='openEditSessionModal(<?= json_encode([
                    "id" => (int)$sr["id"],
                    "term_name" => $sr["term_name"],
                    "session_year" => (int)$sr["session_year"],
                    "is_enabled" => (int)$sr["is_enabled"],
                    "notes" => (string)($sr["notes"] ?? ""),
                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="padding:4px 9px; font-size:0.76rem; font-weight:700; border-radius:5px; cursor:pointer; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; margin-left:4px;">
                  <i class="fas fa-edit"></i> Edit
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php elseif ($activeTab === 'passing_years'): ?>
  <!-- ==================== TAB 2: PASSING YEARS ==================== -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(320px, 100%), 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <div class="rmu-card" style="margin-bottom:0;">
      <h3 style="margin:0 0 0.85rem; font-size:1.02rem; font-weight:800; color:#0f172a;"><i class="fas fa-plus-circle" style="color:var(--rmu-primary);"></i> Add Passing Year</h3>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_passing_year">
        <input type="hidden" name="redirect_tab" value="passing_years">
        <div class="rmu-grid-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
          <div class="form-group">
            <label class="form-label">Year Value (2011–2050) *</label>
            <input type="number" name="year_value" min="2011" max="2050" value="<?= (int)date('Y') ?>" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Display Label (Optional)</label>
            <input type="text" name="label" class="form-control" placeholder="Defaults to year e.g. 2031">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Form Availability</label>
          <select name="is_enabled" class="form-select">
            <option value="1" selected>Enabled (Selectable by Students)</option>
            <option value="0">Disabled (Hidden from Admission Form)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Admin Note (Optional)</label>
          <input type="text" name="notes" class="form-control" placeholder="Optional reference note">
        </div>
        <button type="submit" class="btn-primary" style="width:100%; justify-content:center;">
          <i class="fas fa-plus"></i> Add Passing Year
        </button>
      </form>
    </div>

    <div class="rmu-card" style="margin-bottom:0;">
      <h3 style="margin:0 0 0.85rem; font-size:1.02rem; font-weight:800; color:#0f172a;"><i class="fas fa-magic" style="color:#0284c7;"></i> Bulk Generate Passing Years</h3>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bulk_add_passing_years">
        <input type="hidden" name="redirect_tab" value="passing_years">
        <div class="rmu-grid-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
          <div class="form-group">
            <label class="form-label">Start Year (min 2011) *</label>
            <input type="number" name="start_year" min="2011" max="2050" value="2011" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">End Year (max 2050) *</label>
            <input type="number" name="end_year" min="2011" max="2050" value="2035" class="form-control" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Initial Status for New Years</label>
          <select name="is_enabled" class="form-select">
            <option value="1" selected>Enabled (Visible on Online Form)</option>
            <option value="0">Disabled</option>
          </select>
        </div>
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:0.7rem; font-size:0.78rem; color:#475569; margin-bottom:1rem;">
          <strong>Policy Reminder:</strong> Student-selected Passing Years are strictly preserved and never overwritten automatically.
        </div>
        <button type="submit" class="btn-primary" style="width:100%; justify-content:center; background:#0369a1;">
          <i class="fas fa-bolt"></i> Generate Missing Years
        </button>
      </form>
    </div>
  </div>

  <div class="rmu-card" style="padding:0; overflow:hidden;">
    <div style="padding:1rem 1.25rem; background:#0f172a; color:#ffffff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
      <strong style="font-size:0.96rem;"><i class="fas fa-graduation-cap"></i> Configured Passing Years (2011+)</strong>
      <small style="color:#94a3b8;">Only <strong>Enabled</strong> years appear in the Online Application Passing Year dropdown.</small>
    </div>
    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow:none; margin:0;">
        <thead>
          <tr>
            <th>Year Value</th>
            <th>Display Label</th>
            <th>Status</th>
            <th>Notes</th>
            <th style="text-align:right;">Controls</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allPassingYears as $py): ?>
            <?php $pyEn = ((int)$py['is_enabled'] === 1); ?>
            <tr>
              <td><strong style="font-size:0.96rem; color:#0f172a;"><?= (int)$py['year_value'] ?></strong></td>
              <td><?= e($py['label']) ?></td>
              <td>
                <?php if ($pyEn): ?>
                  <span class="rmu-pill" style="background:#dcfce7; color:#15803d;"><i class="fas fa-check-circle"></i> Enabled</span>
                <?php else: ?>
                  <span class="rmu-pill" style="background:#fee2e2; color:#991b1b;"><i class="fas fa-ban"></i> Disabled</span>
                <?php endif; ?>
              </td>
              <td style="font-size:0.8rem; color:#64748b;"><?= e($py['notes'] ?? '—') ?></td>
              <td style="text-align:right; white-space:nowrap;">
                <form method="POST" action="" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_passing_year">
                  <input type="hidden" name="redirect_tab" value="passing_years">
                  <input type="hidden" name="id" value="<?= (int)$py['id'] ?>">
                  <input type="hidden" name="is_enabled" value="<?= $pyEn ? '0' : '1' ?>">
                  <button type="submit" style="padding:4px 9px; font-size:0.76rem; font-weight:700; border-radius:5px; cursor:pointer; border:1px solid <?= $pyEn ? '#fca5a5; background:#fef2f2; color:#b91c1c;' : '#86efac; background:#f0fdf4; color:#15803d;' ?>">
                    <?= $pyEn ? 'Disable' : 'Enable' ?>
                  </button>
                </form>

                <button type="button" onclick='openEditPassingYearModal(<?= json_encode([
                    "id" => (int)$py["id"],
                    "year_value" => (int)$py["year_value"],
                    "label" => $py["label"],
                    "is_enabled" => (int)$py["is_enabled"],
                    "notes" => (string)($py["notes"] ?? ""),
                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="padding:4px 9px; font-size:0.76rem; font-weight:700; border-radius:5px; cursor:pointer; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; margin-left:4px;">
                  <i class="fas fa-edit"></i> Edit
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php elseif ($activeTab === 'programs'): ?>
  <!-- ==================== TAB 3: COURSE DURATION & SEMESTERS ==================== -->
  <div class="alert alert-info" style="margin-bottom:1.25rem;">
    <i class="fas fa-info-circle"></i>
    <div>
      <strong>Default Academic Structures &amp; Customizations:</strong> Bachelor / Honours (BBA, BA, BSS, BSc, BFA, CSE, EEE, ICT, Agriculture) default to <strong>4 Years / 8 Semesters</strong>; <strong>B.Ed</strong> defaults to <strong>1 Year / 2 Semesters</strong>; <strong>Master's</strong> defaults to <strong>2 Years / 4 Semesters</strong>. Administrators may customize duration (<code>0.5–6.0</code> years) and semesters (<code>1–12</code>) for any approved curriculum. Updating updates the advisory <strong>Expected Passing Year</strong> only — never existing students' selected Passing Years.
    </div>
  </div>

  <div class="rmu-card" style="padding:0; overflow:hidden;">
    <div style="padding:1rem 1.25rem; background:#0f172a; color:#ffffff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
      <strong style="font-size:0.96rem;"><i class="fas fa-sliders-h"></i> Program Course Duration &amp; Semester Rules</strong>
      <a href="<?= APP_URL ?>/admin/programs.php" style="color:#38bdf8; font-size:0.8rem; font-weight:700; text-decoration:none;">Full Program Editor &rarr;</a>
    </div>
    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow:none; margin:0;">
        <thead>
          <tr>
            <th>Program &amp; Department</th>
            <th>Degree Type</th>
            <th>Duration (Years)</th>
            <th>Semesters (1–12)</th>
            <th>Total Credits</th>
            <th>Sample Expected (from 2011)</th>
            <th>Update Reason</th>
            <th style="text-align:right;">Save</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allPrograms as $p): ?>
            <?php
              $ps = get_program_academic_structure((int)$p['id']);
              $sampleExp = calculate_expected_passing_year('Spring 2011', (float)$ps['duration_years']);
            ?>
            <tr>
              <?php $progFormId = 'sessProgForm' . (int)$p['id']; ?>
              <td>
                <strong style="color:#0f172a; display:block; font-size:0.88rem;"><?= e($p['title_en']) ?></strong>
                <small style="color:#64748b;"><?= e($p['dept_name'] ?? '') ?> (<?= e($p['dept_code'] ?? '') ?>)</small>
              </td>
              <td>
                <select name="degree_type" form="<?= $progFormId ?>" class="form-select" style="padding:4px 6px; font-size:0.8rem; min-width:140px;">
                  <option value="Bachelor / Honours" <?= ($ps['degree_type'] === 'Bachelor / Honours') ? 'selected' : '' ?>>Bachelor / Honours</option>
                  <option value="B.Ed" <?= ($ps['degree_type'] === 'B.Ed') ? 'selected' : '' ?>>B.Ed</option>
                  <option value="Master's" <?= ($ps['degree_type'] === "Master's") ? 'selected' : '' ?>>Master's</option>
                </select>
              </td>
              <td>
                <input type="number" step="0.5" min="0.5" max="6" name="duration_years" form="<?= $progFormId ?>" value="<?= number_format((float)$ps['duration_years'], 1, '.', '') ?>" class="form-control" style="padding:4px 6px; font-size:0.82rem; width:82px;">
              </td>
              <td>
                <input type="number" min="1" max="12" name="total_semesters" form="<?= $progFormId ?>" value="<?= (int)$ps['total_semesters'] ?>" class="form-control" style="padding:4px 6px; font-size:0.82rem; width:74px;">
              </td>
              <td>
                <input type="number" min="10" max="260" name="total_credits" form="<?= $progFormId ?>" value="<?= (int)$ps['total_credits'] ?>" class="form-control" style="padding:4px 6px; font-size:0.82rem; width:80px;">
              </td>
              <td style="font-size:0.8rem; color:#1e40af; font-weight:700;">
                <?= e($sampleExp['formula'] ?? 'N/A') ?>
              </td>
              <td>
                <input type="text" name="reason" form="<?= $progFormId ?>" class="form-control" placeholder="Curriculum update note" style="padding:4px 6px; font-size:0.78rem; min-width:150px;">
              </td>
              <td style="text-align:right;">
                <form id="<?= $progFormId ?>" method="POST" action="">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_program_duration">
                  <input type="hidden" name="redirect_tab" value="programs">
                  <input type="hidden" name="program_id" value="<?= (int)$p['id'] ?>">
                  <button type="submit" class="btn-primary" style="padding:5px 11px; font-size:0.78rem;">
                    <i class="fas fa-save"></i> Save
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php elseif ($activeTab === 'oversight'): ?>
  <!-- ==================== TAB 4: EXPECTED VS PASSING YEAR OVERSIGHT ==================== -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(185px, 1fr)); gap:0.9rem; margin-bottom:1.25rem;">
    <div class="stat-card" style="padding:0.9rem;">
      <div class="stat-icon" style="background:#dbeafe; color:#1d4ed8; width:42px; height:42px; font-size:1.1rem;"><i class="fas fa-clipboard-list"></i></div>
      <div><div style="font-size:1.3rem; font-weight:800;"><?= $ovStats['total'] ?></div><div style="font-size:0.75rem; color:#64748b;">Audited Records</div></div>
    </div>
    <div class="stat-card" style="padding:0.9rem;">
      <div class="stat-icon" style="background:#fef3c7; color:#b45309; width:42px; height:42px; font-size:1.1rem;"><i class="fas fa-exclamation-triangle"></i></div>
      <div><div style="font-size:1.3rem; font-weight:800; color:#b45309;"><?= $ovStats['needs_review'] ?></div><div style="font-size:0.75rem; color:#64748b;">Differs (Needs Review)</div></div>
    </div>
    <div class="stat-card" style="padding:0.9rem;">
      <div class="stat-icon" style="background:#e0f2fe; color:#0369a1; width:42px; height:42px; font-size:1.1rem;"><i class="fas fa-stamp"></i></div>
      <div><div style="font-size:1.3rem; font-weight:800; color:#0369a1;"><?= $ovStats['approved_historical'] ?></div><div style="font-size:0.75rem; color:#64748b;">Historical Approved</div></div>
    </div>
    <div class="stat-card" style="padding:0.9rem;">
      <div class="stat-icon" style="background:#dcfce7; color:#15803d; width:42px; height:42px; font-size:1.1rem;"><i class="fas fa-check-double"></i></div>
      <div><div style="font-size:1.3rem; font-weight:800; color:#15803d;"><?= $ovStats['match'] ?></div><div style="font-size:0.75rem; color:#64748b;">Matches Expected Year</div></div>
    </div>
  </div>

  <!-- Oversight Filters -->
  <div class="rmu-card" style="padding:1rem;">
    <form method="GET" action="" style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
      <input type="hidden" name="tab" value="oversight">
      <div style="flex:1; min-width:200px;">
        <label class="form-label" style="font-size:0.76rem; margin-bottom:4px;">Search Student / Applicant / Session / Year</label>
        <input type="text" name="search" value="<?= e($ovSearch) ?>" class="form-control" placeholder="Name, RMU ID, Application Ref, Session...">
      </div>
      <div>
        <label class="form-label" style="font-size:0.76rem; margin-bottom:4px;">Record Scope</label>
        <select name="type" class="form-select">
          <option value="all" <?= $ovType === 'all' ? 'selected' : '' ?>>Admissions &amp; Students</option>
          <option value="admissions" <?= $ovType === 'admissions' ? 'selected' : '' ?>>Admissions Only</option>
          <option value="students" <?= $ovType === 'students' ? 'selected' : '' ?>>Enrolled Students Only</option>
        </select>
      </div>
      <div>
        <label class="form-label" style="font-size:0.76rem; margin-bottom:4px;">Comparison Status</label>
        <select name="filter" class="form-select">
          <option value="all" <?= $ovFilter === 'all' ? 'selected' : '' ?>>All Records</option>
          <option value="discrepancy" <?= $ovFilter === 'discrepancy' ? 'selected' : '' ?>>All Discrepancies (Differs)</option>
          <option value="needs_review" <?= $ovFilter === 'needs_review' ? 'selected' : '' ?>>Needs Review (Unapproved)</option>
          <option value="approved_historical" <?= $ovFilter === 'approved_historical' ? 'selected' : '' ?>>Historical Approved</option>
          <option value="match" <?= $ovFilter === 'match' ? 'selected' : '' ?>>Exact Match</option>
        </select>
      </div>
      <button type="submit" class="btn-primary" style="padding:0.58rem 1.15rem;"><i class="fas fa-filter"></i> Filter</button>
    </form>
  </div>

  <!-- Oversight Comparison Table -->
  <div class="rmu-card" style="padding:0; overflow:hidden;">
    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow:none; margin:0;">
        <thead>
          <tr>
            <th>Record &amp; Code</th>
            <th>Student / Applicant</th>
            <th>Program &amp; Duration</th>
            <th>Academic Session</th>
            <th>Expected Year (Advisory)</th>
            <th>Selected Passing Year</th>
            <th>Oversight Status</th>
            <th style="text-align:right;">Admin Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($oversightRows)): ?>
            <tr><td colspan="8" style="text-align:center; padding:2rem; color:#64748b;">No records match the selected oversight filters.</td></tr>
          <?php else: ?>
            <?php foreach ($oversightRows as $ov): ?>
              <?php
                $ev = $ov['eval'];
                $isAdm = ($ov['entity_type'] === 'admission');
                $badgeBg = '#f1f5f9'; $badgeColor = '#475569';
                if ($ev['status_key'] === 'match') { $badgeBg = '#dcfce7'; $badgeColor = '#15803d'; }
                elseif ($ev['status_key'] === 'approved_historical') { $badgeBg = '#e0f2fe'; $badgeColor = '#0369a1'; }
                elseif ($ev['status_key'] === 'differs') { $badgeBg = '#fef3c7'; $badgeColor = '#b45309'; }
              ?>
              <tr>
                <td>
                  <span class="rmu-pill" style="background:<?= $isAdm ? '#ede9fe; color:#5b21b6;' : '#dbeafe; color:#1d4ed8;' ?>">
                    <?= $isAdm ? 'Admission' : 'Student' ?>
                  </span>
                  <div style="font-family:monospace; font-size:0.8rem; font-weight:700; margin-top:3px;">
                    <?= e($ov['code_primary']) ?>
                  </div>
                </td>
                <td>
                  <strong style="color:#0f172a; font-size:0.88rem; display:block;"><?= e($ov['full_name']) ?></strong>
                  <small style="color:#64748b;">Status: <?= e($ov['record_status']) ?></small>
                </td>
                <td style="font-size:0.8rem;">
                  <strong><?= e($ov['program_title']) ?></strong>
                  <div style="color:#64748b;"><?= e(format_duration_years_label((float)$ov['duration_years'], (int)$ov['total_semesters'])) ?></div>
                </td>
                <td><strong><?= e($ov['academic_session'] ?: 'N/A') ?></strong></td>
                <td style="font-size:0.82rem;">
                  <?php if ($ev['expected_year']): ?>
                    <strong style="color:#1e40af; font-family:monospace; font-size:0.92rem;"><?= (int)$ev['expected_year'] ?></strong>
                    <div style="font-size:0.72rem; color:#64748b;"><?= e($ev['formula'] ?? '') ?></div>
                  <?php else: ?>
                    <span style="color:#94a3b8;">N/A</span>
                  <?php endif; ?>
                </td>
                <td>
                  <strong style="font-family:monospace; font-size:0.95rem; color:#0f172a;">
                    <?= e(!empty($ov['passing_year']) ? $ov['passing_year'] : 'Not Set') ?>
                  </strong>
                </td>
                <td>
                  <span class="rmu-pill" style="background:<?= $badgeBg ?>; color:<?= $badgeColor ?>;">
                    <?= e($ev['status_label']) ?>
                  </span>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <?php if ($ev['needs_review'] || empty($ov['historical_record_approved'])): ?>
                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Approve historical Passing Year record without changing the student-selected year?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="approve_historical">
                      <input type="hidden" name="redirect_tab" value="oversight">
                      <input type="hidden" name="redirect_filter" value="<?= e($ovFilter) ?>">
                      <input type="hidden" name="redirect_search" value="<?= e($ovSearch) ?>">
                      <input type="hidden" name="entity_type" value="<?= e($ov['entity_type']) ?>">
                      <input type="hidden" name="record_id" value="<?= (int)$ov['record_id'] ?>">
                      <input type="hidden" name="note" value="Verified and approved via Admin Oversight">
                      <button type="submit" style="padding:4px 8px; font-size:0.74rem; font-weight:700; border-radius:5px; cursor:pointer; border:1px solid #86efac; background:#f0fdf4; color:#15803d;">
                        <i class="fas fa-check"></i> Approve Historical
                      </button>
                    </form>
                  <?php endif; ?>

                  <button type="button" onclick='openOfficialEditModal(<?= json_encode([
                      "entity_type" => $ov["entity_type"],
                      "record_id" => (int)$ov["record_id"],
                      "code" => $ov["code_primary"],
                      "full_name" => $ov["full_name"],
                      "academic_session" => (string)($ov["academic_session"] ?? ""),
                      "passing_year" => (string)($ov["passing_year"] ?? ""),
                      "program_id" => (int)($ov["program_id"] ?? 0),
                      "degree_type" => (string)($ov["degree_type"] ?? "Bachelor / Honours"),
                      "duration_years" => number_format((float)$ov["duration_years"], 1, ".", ""),
                      "total_semesters" => (int)$ov["total_semesters"],
                  ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="padding:4px 8px; font-size:0.74rem; font-weight:700; border-radius:5px; cursor:pointer; border:1px solid #93c5fd; background:#eff6ff; color:#1d4ed8; margin-left:4px;">
                    <i class="fas fa-pen"></i> Official Edit
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else: ?>
  <!-- ==================== TAB 5: AUDIT HISTORY ==================== -->
  <div class="rmu-card" style="padding:1rem;">
    <form method="GET" action="" style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
      <input type="hidden" name="tab" value="history">
      <div>
        <label class="form-label" style="font-size:0.76rem; margin-bottom:4px;">Entity Filter</label>
        <select name="hist_type" class="form-select">
          <option value="" <?= $histEntityFilter === '' ? 'selected' : '' ?>>All Academic Entities</option>
          <option value="student" <?= $histEntityFilter === 'student' ? 'selected' : '' ?>>Student</option>
          <option value="admission" <?= $histEntityFilter === 'admission' ? 'selected' : '' ?>>Admission</option>
          <option value="program" <?= $histEntityFilter === 'program' ? 'selected' : '' ?>>Program</option>
          <option value="session" <?= $histEntityFilter === 'session' ? 'selected' : '' ?>>Academic Session</option>
          <option value="passing_year" <?= $histEntityFilter === 'passing_year' ? 'selected' : '' ?>>Passing Year</option>
        </select>
      </div>
      <div style="flex:1; min-width:200px;">
        <label class="form-label" style="font-size:0.76rem; margin-bottom:4px;">Student ID Code (Optional)</label>
        <input type="text" name="hist_code" value="<?= e($histCodeFilter) ?>" class="form-control" placeholder="e.g. RMU2601001">
      </div>
      <button type="submit" class="btn-primary" style="padding:0.58rem 1.15rem;"><i class="fas fa-search"></i> Filter History</button>
    </form>
  </div>

  <div class="rmu-card" style="padding:0; overflow:hidden;">
    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow:none; margin:0;">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Admin</th>
            <th>Entity</th>
            <th>Field Edited</th>
            <th>Old Value &rarr; New Value</th>
            <th>Mandatory Reason</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($historyLogs)): ?>
            <tr><td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">No academic edit history entries recorded yet.</td></tr>
          <?php else: ?>
            <?php foreach ($historyLogs as $hl): ?>
              <tr>
                <td style="font-size:0.78rem; white-space:nowrap; color:#475569;"><?= e($hl['created_at']) ?></td>
                <td style="font-size:0.8rem;"><strong><?= e($hl['admin_full_name'] ?: ($hl['admin_username'] ?: ('Admin #' . ($hl['admin_id'] ?? '?')))) ?></strong></td>
                <td style="font-size:0.8rem;">
                  <span class="rmu-pill" style="background:#f1f5f9; color:#334155;"><?= e(ucfirst($hl['entity_type'])) ?> #<?= (int)$hl['entity_id'] ?></span>
                  <?php if (!empty($hl['student_id_code'])): ?>
                    <div style="font-family:monospace; font-size:0.74rem; color:#1d4ed8; margin-top:2px;"><?= e($hl['student_id_code']) ?></div>
                  <?php endif; ?>
                </td>
                <td><code style="font-size:0.78rem;"><?= e($hl['field_name']) ?></code></td>
                <td style="font-size:0.8rem;">
                  <span style="color:#991b1b;"><?= e($hl['old_value'] ?? 'NULL') ?></span>
                  &rarr;
                  <strong style="color:#15803d;"><?= e($hl['new_value'] ?? 'NULL') ?></strong>
                </td>
                <td style="font-size:0.8rem; color:#0f172a;"><?= e($hl['reason']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- Modal: Edit Academic Session -->
<div id="editSessionModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:2000; align-items:center; justify-content:center; padding:1rem;">
  <div style="background:#ffffff; border-radius:10px; max-width:440px; width:100%; padding:1.5rem; box-shadow:0 15px 30px rgba(0,0,0,0.25);">
    <h3 style="margin:0 0 1rem; font-size:1.1rem; font-weight:800; color:#0f172a;">Edit Academic Session</h3>
    <form method="POST" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit_session">
      <input type="hidden" name="redirect_tab" value="sessions">
      <input type="hidden" name="id" id="editSessId">
      <div class="rmu-grid-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Term *</label>
          <select name="term_name" id="editSessTerm" class="form-select" required>
            <option value="Spring">Spring</option>
            <option value="Summer">Summer</option>
            <option value="Fall">Fall</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Year *</label>
          <input type="number" min="2011" max="2050" name="session_year" id="editSessYear" class="form-control" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="is_enabled" id="editSessEnabled" class="form-select">
          <option value="1">Enabled (Visible on Form)</option>
          <option value="0">Disabled</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Note / Reason</label>
        <input type="text" name="notes" id="editSessNotes" class="form-control">
      </div>
      <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
        <button type="button" onclick="document.getElementById('editSessionModal').style.display='none'" style="padding:6px 14px; border:1px solid #cbd5e1; background:#f1f5f9; border-radius:6px; cursor:pointer;">Cancel</button>
        <button type="submit" class="btn-primary" style="padding:6px 16px;">Save Session</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Edit Passing Year -->
<div id="editPassingYearModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:2000; align-items:center; justify-content:center; padding:1rem;">
  <div style="background:#ffffff; border-radius:10px; max-width:440px; width:100%; padding:1.5rem; box-shadow:0 15px 30px rgba(0,0,0,0.25);">
    <h3 style="margin:0 0 1rem; font-size:1.1rem; font-weight:800; color:#0f172a;">Edit Passing Year</h3>
    <form method="POST" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit_passing_year">
      <input type="hidden" name="redirect_tab" value="passing_years">
      <input type="hidden" name="id" id="editPyId">
      <div class="rmu-grid-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Year Value *</label>
          <input type="number" min="2011" max="2050" name="year_value" id="editPyYear" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Label *</label>
          <input type="text" name="label" id="editPyLabel" class="form-control" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="is_enabled" id="editPyEnabled" class="form-select">
          <option value="1">Enabled (Visible on Form)</option>
          <option value="0">Disabled</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Note / Reason</label>
        <input type="text" name="notes" id="editPyNotes" class="form-control">
      </div>
      <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
        <button type="button" onclick="document.getElementById('editPassingYearModal').style.display='none'" style="padding:6px 14px; border:1px solid #cbd5e1; background:#f1f5f9; border-radius:6px; cursor:pointer;">Cancel</button>
        <button type="submit" class="btn-primary" style="padding:6px 16px;">Save Passing Year</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Official Edit (Oversight Audited Correction) -->
<div id="officialEditModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:2000; align-items:center; justify-content:center; padding:1rem;">
  <div style="background:#ffffff; border-radius:10px; max-width:520px; width:100%; padding:1.5rem; box-shadow:0 15px 30px rgba(0,0,0,0.25); max-height:90vh; overflow-y:auto;">
    <h3 style="margin:0 0 0.35rem; font-size:1.12rem; font-weight:800; color:#0f172a;">Official Academic Record Edit</h3>
    <p id="offEditSubtitle" style="margin:0 0 1rem; font-size:0.82rem; color:#64748b;"></p>
    <form method="POST" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="official_edit_record">
      <input type="hidden" name="redirect_tab" value="oversight">
      <input type="hidden" name="redirect_filter" value="<?= e($ovFilter) ?>">
      <input type="hidden" name="redirect_search" value="<?= e($ovSearch) ?>">
      <input type="hidden" name="entity_type" id="offEntityType">
      <input type="hidden" name="record_id" id="offRecordId">

      <div class="rmu-grid-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Academic Session *</label>
          <input type="text" name="academic_session" id="offSession" list="adminSessionDatalist" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Passing Year (Explicit Edit Only)</label>
          <input type="text" name="passing_year" id="offPassingYear" list="adminPassingYearDatalist" class="form-control" placeholder="e.g. 2015 or blank">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Academic Program</label>
        <select name="program_id" id="offProgramId" class="form-select">
          <option value="0">-- Keep Existing / Unassigned --</option>
          <?php foreach ($allPrograms as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= e($p['title_en']) ?> (<?= number_format((float)$p['duration_years'], 1, '.', '') ?> Yr / <?= (int)$p['total_semesters'] ?> Sem)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="offStudentOnlyFields" class="rmu-grid-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Course Duration (Years)</label>
          <input type="number" step="0.5" min="0.5" max="6" name="duration_years" id="offDurationYears" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Total Semesters (1–12)</label>
          <input type="number" min="1" max="12" name="total_semesters" id="offTotalSemesters" class="form-control">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Official Reason for Change <span class="required">*</span></label>
        <textarea name="reason" rows="2" class="form-control" required placeholder="Mandatory audit justification for modifying student/applicant academic history..."></textarea>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
        <button type="button" onclick="document.getElementById('officialEditModal').style.display='none'" style="padding:6px 14px; border:1px solid #cbd5e1; background:#f1f5f9; border-radius:6px; cursor:pointer;">Cancel</button>
        <button type="submit" class="btn-primary" style="padding:6px 16px;"><i class="fas fa-save"></i> Save &amp; Log Audit</button>
      </div>
    </form>
  </div>
</div>

<datalist id="adminSessionDatalist">
  <?php foreach ($allSessions as $sr): ?>
    <option value="<?= e($sr['session_name']) ?>">
  <?php endforeach; ?>
</datalist>
<datalist id="adminPassingYearDatalist">
  <?php foreach ($allPassingYears as $py): ?>
    <option value="<?= (int)$py['year_value'] ?>">
  <?php endforeach; ?>
</datalist>

<script>
function openEditSessionModal(data) {
  document.getElementById('editSessId').value = data.id;
  document.getElementById('editSessTerm').value = data.term_name;
  document.getElementById('editSessYear').value = data.session_year;
  document.getElementById('editSessEnabled').value = String(data.is_enabled);
  document.getElementById('editSessNotes').value = data.notes || '';
  document.getElementById('editSessionModal').style.display = 'flex';
}
function openEditPassingYearModal(data) {
  document.getElementById('editPyId').value = data.id;
  document.getElementById('editPyYear').value = data.year_value;
  document.getElementById('editPyLabel').value = data.label;
  document.getElementById('editPyEnabled').value = String(data.is_enabled);
  document.getElementById('editPyNotes').value = data.notes || '';
  document.getElementById('editPassingYearModal').style.display = 'flex';
}
function openOfficialEditModal(data) {
  document.getElementById('offEntityType').value = data.entity_type;
  document.getElementById('offRecordId').value = data.record_id;
  document.getElementById('offEditSubtitle').textContent = (data.entity_type === 'admission' ? 'Admission ' : 'Student ') + data.code + ' — ' + data.full_name;
  document.getElementById('offSession').value = data.academic_session || '';
  document.getElementById('offPassingYear').value = data.passing_year || '';
  document.getElementById('offProgramId').value = String(data.program_id || 0);
  document.getElementById('offDurationYears').value = data.duration_years || '4.0';
  document.getElementById('offTotalSemesters').value = data.total_semesters || '8';
  document.getElementById('officialEditModal').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
