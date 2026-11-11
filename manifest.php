<?php
/**
 * Web App Manifest (PWA) for Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

if (!headers_sent()) {
    header('Content-Type: application/manifest+json; charset=UTF-8');
    header('Cache-Control: no-cache, max-age=0');
}

$parsedPath = parse_url(APP_URL, PHP_URL_PATH);
$rootPrefix = '/' . trim((string)$parsedPath, '/');
if ($rootPrefix !== '/') {
    $rootPrefix .= '/';
}

$manifest = [
    'id' => $rootPrefix . 'index.php',
    'name' => 'Rabindra Maitree University',
    'short_name' => 'RMU Portal',
    'description' => 'Official Academic, Admission, Student & Administration Portal for Rabindra Maitree University, Kushtia.',
    'start_url' => $rootPrefix . 'index.php?source=pwa',
    'scope' => $rootPrefix,
    'display' => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'orientation' => 'any',
    'background_color' => '#0f2c59',
    'theme_color' => '#0f2c59',
    'lang' => 'en',
    'icons' => [
        [
            'src' => $rootPrefix . 'assets/pwa/icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => $rootPrefix . 'assets/pwa/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => $rootPrefix . 'assets/pwa/icon-maskable-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
        [
            'src' => $rootPrefix . 'assets/pwa/icon-maskable-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
    'shortcuts' => [
        [
            'name' => 'Student Portal',
            'short_name' => 'Portal',
            'url' => $rootPrefix . 'student/login.php',
            'icons' => [
                ['src' => $rootPrefix . 'assets/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ],
        ],
        [
            'name' => 'Online Admission',
            'short_name' => 'Apply',
            'url' => $rootPrefix . 'admission-form.php',
            'icons' => [
                ['src' => $rootPrefix . 'assets/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ],
        ],
        [
            'name' => 'Official Notices',
            'short_name' => 'Notices',
            'url' => $rootPrefix . 'notices.php',
            'icons' => [
                ['src' => $rootPrefix . 'assets/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ],
        ],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
