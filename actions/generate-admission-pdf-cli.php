<?php
/**
 * CLI Worker: Non-blocking Background PDF Compiler for Admission Applications
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$admissionId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($admissionId <= 0) {
    exit(0);
}

$argAppUrl = isset($argv[2]) ? trim((string)$argv[2]) : '';
if ($argAppUrl !== '' && preg_match('#^https?://#i', $argAppUrl)) {
    putenv('RMU_APP_URL=' . rtrim($argAppUrl, '/'));
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDb();
    generate_admission_application_pdf_file($admissionId, true, $db);
} catch (Throwable $e) {
    error_log('Admission background PDF worker failed for #' . $admissionId . ': ' . $e->getMessage());
    exit(1);
}
exit(0);
