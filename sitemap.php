<?php
/**
 * Rabindra Maitree University (RMU) Dynamic XML Sitemap Generator
 * Generates an up-to-date XML sitemap for Google Search Console and crawlers.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

// Determine canonical base URL
$baseUrl = get_setting('canonical_base_url', '');
if (empty($baseUrl)) {
    $baseUrl = defined('APP_URL') ? APP_URL : 'https://rmu.ac.bd';
}
$baseUrl = rtrim($baseUrl, '/');

$urls = [];

// Helper to add a URL entry
$addUrl = function($path, $priority, $changefreq, $lastmod = null) use (&$urls, $baseUrl) {
    $loc = $baseUrl . '/' . ltrim($path, '/');
    $urls[] = [
        'loc'        => htmlspecialchars($loc, ENT_QUOTES, 'UTF-8'),
        'lastmod'    => $lastmod ? date('Y-m-d', strtotime($lastmod)) : date('Y-m-d'),
        'changefreq' => $changefreq,
        'priority'   => number_format((float)$priority, 1, '.', '')
    ];
};

// 1. Core Static Pages
$addUrl('', 1.0, 'daily');
$addUrl('index.php', 1.0, 'daily');
$addUrl('about.php', 0.8, 'monthly');
$addUrl('academics.php', 0.9, 'weekly');
$addUrl('admissions.php', 0.9, 'weekly');
$addUrl('admission-form.php', 0.9, 'weekly');
$addUrl('admission-status.php', 0.8, 'daily');
$addUrl('faculty.php', 0.8, 'monthly');
$addUrl('notices.php', 0.9, 'daily');
$addUrl('events.php', 0.8, 'weekly');
$addUrl('news.php', 0.8, 'weekly');
$addUrl('contact.php', 0.7, 'monthly');
$addUrl('verify.php', 0.7, 'monthly');
$addUrl('verify-certificate.php', 0.7, 'monthly');
$addUrl('verify-application.php', 0.7, 'monthly');

// 2. Dynamic Content from Database
try {
    $db = getDb();

    // Faculties
    $faculties = $db->query("SELECT slug, created_at FROM faculties WHERE is_active = 1")->fetchAll();
    foreach ($faculties as $f) {
        if (!empty($f['slug'])) {
            $addUrl('faculty-detail.php?slug=' . urlencode($f['slug']), 0.8, 'monthly', $f['created_at']);
        }
    }

    // Departments
    $departments = $db->query("SELECT slug, created_at FROM departments WHERE is_active = 1")->fetchAll();
    foreach ($departments as $d) {
        if (!empty($d['slug'])) {
            $addUrl('department-detail.php?slug=' . urlencode($d['slug']), 0.8, 'monthly', $d['created_at']);
        }
    }

    // Programs
    $programs = $db->query("SELECT slug, created_at FROM programs WHERE is_active = 1")->fetchAll();
    foreach ($programs as $p) {
        if (!empty($p['slug'])) {
            $addUrl('program-detail.php?slug=' . urlencode($p['slug']), 0.85, 'weekly', $p['created_at']);
        }
    }

    // Notices
    $notices = $db->query("SELECT slug, published_date, created_at FROM notices WHERE is_active = 1 ORDER BY published_date DESC")->fetchAll();
    foreach ($notices as $n) {
        if (!empty($n['slug'])) {
            $lastmod = !empty($n['published_date']) ? $n['published_date'] : $n['created_at'];
            $addUrl('notice-detail.php?slug=' . urlencode($n['slug']), 0.8, 'daily', $lastmod);
        }
    }

    // Events
    $events = $db->query("SELECT slug, event_date, created_at FROM events WHERE is_active = 1 ORDER BY event_date DESC")->fetchAll();
    foreach ($events as $e) {
        if (!empty($e['slug'])) {
            $lastmod = !empty($e['event_date']) ? $e['event_date'] : $e['created_at'];
            $addUrl('event-detail.php?slug=' . urlencode($e['slug']), 0.8, 'weekly', $lastmod);
        }
    }

    // News
    $news = $db->query("SELECT slug, published_date, created_at FROM news WHERE is_active = 1 ORDER BY published_date DESC")->fetchAll();
    foreach ($news as $nw) {
        if (!empty($nw['slug'])) {
            $lastmod = !empty($nw['published_date']) ? $nw['published_date'] : $nw['created_at'];
            $addUrl('news-detail.php?slug=' . urlencode($nw['slug']), 0.8, 'weekly', $lastmod);
        }
    }
} catch (Exception $e) {
    // Database fallback: continue with static URLs
}

// Build XML string
$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
$xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($urls as $u) {
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . $u['loc'] . "</loc>\n";
    $xml .= "    <lastmod>" . $u['lastmod'] . "</lastmod>\n";
    $xml .= "    <changefreq>" . $u['changefreq'] . "</changefreq>\n";
    $xml .= "    <priority>" . $u['priority'] . "</priority>\n";
    $xml .= "  </url>\n";
}
$xml .= "</urlset>\n";

// Write static sitemap.xml fallback file if possible
@file_put_contents(__DIR__ . '/sitemap.xml', $xml);

// Deliver XML to client/crawler
header('Content-Type: application/xml; charset=utf-8');
echo $xml;
