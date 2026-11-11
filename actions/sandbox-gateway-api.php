<?php
/**
 * Sandbox Payment Gateway Read-Only Status API
 * Allows querying gateway session telemetry cleanly without mutating single-use TrxID validation.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');

$db = getDb();
ensure_admission_payment_gateway_schema($db);

$action = strtolower(trim((string)($_REQUEST['action'] ?? 'status')));
$sessionId = trim((string)($_REQUEST['session'] ?? ($_REQUEST['gateway_session_id'] ?? '')));

if ($action !== 'status' && $action !== 'query') {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => 'Unsupported gateway API action. Use action=status with a valid session identifier.',
    ]);
    exit;
}

if ($sessionId === '') {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => 'Missing required parameter: session.',
    ]);
    exit;
}

$statusData = rmu_sandbox_query_transaction_status($db, $sessionId);
if (empty($statusData['found'])) {
    http_response_code(404);
    echo json_encode([
        'ok'    => false,
        'error' => 'Sandbox payment session not found.',
    ]);
    exit;
}

echo json_encode([
    'ok'   => true,
    'data' => $statusData,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
