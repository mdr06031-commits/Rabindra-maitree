<?php
/**
 * Quick HTTP Status Code Verifier for All Routes
 */
$routes = [
    '/',
    '/about.php',
    '/history.php',
    '/mission-vision.php',
    '/leadership.php',
    '/administration.php',
    '/board-of-trustees.php',
    '/faculties.php',
    '/departments.php',
    '/programs.php',
    '/admissions.php',
    '/admission-form.php',
    '/admission-status.php',
    '/tuition-fees.php',
    '/scholarships.php',
    '/faqs.php',
    '/notices.php',
    '/events.php',
    '/news.php',
    '/downloads.php',
    '/campus.php',
    '/contact.php',
    '/admin/login.php',
    '/student/login.php'
];

$base = 'http://127.0.0.1:8000';
$allOk = true;

foreach ($routes as $r) {
    $url = $base . $r;
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
    $content = @file_get_contents($url, false, $ctx);
    $status = $http_response_header[0] ?? 'NO_HEADER';
    $is200 = (strpos($status, '200') !== false);
    echo ($is200 ? '[OK] ' : '[FAIL] ') . str_pad($r, 30) . ' -> ' . $status . PHP_EOL;
    if (!$is200) $allOk = false;
}

if ($allOk) {
    echo PHP_EOL . "ALL PUBLIC ENDPOINTS RETURNED 200 OK!" . PHP_EOL;
    exit(0);
} else {
    echo PHP_EOL . "SOME ENDPOINTS FAILED!" . PHP_EOL;
    exit(1);
}

