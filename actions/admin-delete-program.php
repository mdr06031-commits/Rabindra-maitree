<?php
/**
 * Admin API Endpoint: Permanently Delete Degree Program Record
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
    log_audit('PROGRAM_ACTION_DENIED', "Unauthorized role '" . current_admin_role() . "' attempted permanent program deletion via API");
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied: Only authorized Admin or Superadmin users can delete degree programs.',
    ]);
    exit;
}

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

$rawId = $payload['id'] ?? '';
$programId = (is_scalar($rawId) && preg_match('/^\d+$/', trim((string)$rawId))) ? (int)trim((string)$rawId) : 0;
if ($programId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid program database ID.',
    ]);
    exit;
}

$adminId = (int)($_SESSION['user_id'] ?? 1);
$res = permanently_delete_program_record($programId, $adminId);
if (empty($res['success'])) {
    http_response_code((int)($res['code'] ?? 400));
    echo json_encode([
        'success' => false,
        'message' => $res['error'] ?? 'Failed to delete degree program.',
    ]);
    exit;
}

$db = getDb();
$totalPrograms = (int)$db->query("SELECT COUNT(*) FROM programs WHERE is_active = 1")->fetchColumn();
$allProgramsCount = (int)$db->query("SELECT COUNT(*) FROM programs")->fetchColumn();

http_response_code(200);
echo json_encode([
    'success'            => true,
    'message'            => 'Degree program deleted successfully.',
    'deleted_id'         => (int)$res['deleted_id'],
    'deleted_title_en'   => (string)$res['title_en'],
    'total_programs'     => $totalPrograms,
    'all_programs_count' => $allProgramsCount,
]);
