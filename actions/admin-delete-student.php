<?php
/**
 * Admin API Endpoint: Permanently Delete Student Record
 * Strictly restricted to authenticated Admin / Superadmin users with valid CSRF token.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. POST is required.',
    ]);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please log in as an administrator.',
    ]);
    exit;
}

if (!is_admin_manager_role()) {
    log_audit('STUDENT_DELETE_DENIED', "Unauthorized role '" . current_admin_role() . "' attempted permanent student deletion via API");
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied: Only authorized Admin or Superadmin users can delete student records.',
    ]);
    exit;
}

// Parse form-encoded POST or JSON payload
$payload = $_POST;
if (empty($payload)) {
    $rawBody = file_get_contents('php://input');
    if (is_string($rawBody) && trim($rawBody) !== '') {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
}

$csrfToken = (string)($payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'CSRF security token verification failed. Please refresh the page and try again.',
    ]);
    exit;
}

$rawId = $payload['id'] ?? ($payload['student_id_pk'] ?? '');
$studentPk = (is_scalar($rawId) && preg_match('/^\d+$/', trim((string)$rawId))) ? (int)trim((string)$rawId) : 0;
if ($studentPk <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid student database ID.',
    ]);
    exit;
}

$adminId = (int)($_SESSION['user_id'] ?? 1);
$reason = trim((string)($payload['reason'] ?? ''));

$res = permanently_delete_student_record($studentPk, $adminId, $reason);
if (empty($res['success'])) {
    http_response_code((int)($res['code'] ?? 400));
    echo json_encode([
        'success' => false,
        'message' => $res['error'] ?? 'Failed to delete student record.',
    ]);
    exit;
}

$db = getDb();
$totalStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status != 'Deleted'")->fetchColumn();
$activeStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
$deactivatedStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status IN ('Deactivated', 'Suspended')")->fetchColumn();
$deletedStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Deleted'")->fetchColumn();
$hiddenResultStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status != 'Deleted' AND COALESCE(results_visible, 1) = 0")->fetchColumn();

$recentStmt = $db->query("
    SELECT s.id, s.student_id, s.full_name, s.batch, s.cgpa, s.status, s.results_visible, s.result_access_status,
           p.title_en AS program_title
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    WHERE s.status != 'Deleted'
    ORDER BY s.id DESC
    LIMIT 5
");
$recentStudents = [];
foreach ($recentStmt->fetchAll(PDO::FETCH_ASSOC) as $st) {
    $stB = get_student_status_badges($st);
    $recentStudents[] = [
        'id'             => (int)$st['id'],
        'student_id'     => (string)$st['student_id'],
        'full_name'      => (string)$st['full_name'],
        'program_title'  => (string)($st['program_title'] ?? 'N/A'),
        'batch'          => (string)($st['batch'] ?? ''),
        'cgpa_formatted' => number_format((float)($st['cgpa'] ?? 0), 2),
        'badge_label'    => (string)$stB['account']['label'],
        'badge_bg'       => (string)$stB['account']['bg'],
        'badge_color'    => (string)$stB['account']['color'],
        'badge_border'   => (string)$stB['account']['border'],
        'detail_url'     => APP_URL . '/admin/student-detail.php?id=' . (int)$st['id'],
    ];
}

http_response_code(200);
echo json_encode([
    'success'              => true,
    'message'              => 'Student deleted successfully.',
    'deleted_id'           => (int)$res['deleted_id'],
    'deleted_student_code' => (string)$res['student_id'],
    'deleted_full_name'    => (string)$res['full_name'],
    'stats' => [
        'total_students'         => $totalStudents,
        'active_students'        => $activeStudents,
        'deactivated_students'   => $deactivatedStudents,
        'deleted_students'       => $deletedStudents,
        'hidden_result_students' => $hiddenResultStudents,
    ],
    'recent_students'      => $recentStudents,
]);
