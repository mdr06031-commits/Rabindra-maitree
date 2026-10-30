<?php
/**
 * Custom 404 Error Page
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

if (!headers_sent()) {
    http_response_code(404);
}
$pageTitle = 'Page Not Found';
$breadcrumbTitle = '404 - Page Not Found';
$breadcrumbs = [['title' => '404 Error']];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white" style="text-align: center; padding: 5rem 0;">
  <div class="container" style="max-width: 650px;">
    <div style="font-size: 6rem; font-weight: 800; color: var(--rmu-primary); line-height: 1; margin-bottom: 1rem;">
      404
    </div>
    <h2 style="font-size: 2rem; color: var(--rmu-dark); margin-bottom: 1rem;">Page Not Found</h2>
    <p style="color: var(--rmu-muted); font-size: 1.05rem; line-height: 1.6; margin-bottom: 2rem;">
      The requested page could not be found or may have been relocated. You can search the university portal or use the links below to return to published content.
    </p>

    <!-- Search Box -->
    <div style="max-width: 450px; margin: 0 auto 2.5rem;">
      <form action="<?= APP_URL ?>/search.php" method="GET" style="display: flex;">
        <input type="text" name="q" placeholder="Search notices, programs, departments..." style="flex: 1; padding: 0.8rem 1.2rem; border: 1.5px solid var(--rmu-border); border-radius: var(--rmu-radius-sm) 0 0 var(--rmu-radius-sm); outline: none; font-size: 0.95rem;">
        <button type="submit" class="btn-primary" style="border-radius: 0 var(--rmu-radius-sm) var(--rmu-radius-sm) 0; padding: 0.8rem 1.4rem;">
          <i class="fas fa-search"></i> Search
        </button>
      </form>
    </div>

    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
      <a href="<?= APP_URL ?>/index.php" class="btn-primary"><i class="fas fa-home"></i> Back to Homepage</a>
      <a href="<?= APP_URL ?>/notices.php" style="padding: 0.75rem 1.4rem; border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-sm); font-weight: 600; color: var(--rmu-dark); display: inline-flex; align-items: center; gap: 0.4rem; background: #fff;">
        <i class="fas fa-bullhorn"></i> View Notices
      </a>
      <a href="<?= APP_URL ?>/contact.php" style="padding: 0.75rem 1.4rem; border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-sm); font-weight: 600; color: var(--rmu-dark); display: inline-flex; align-items: center; gap: 0.4rem; background: #fff;">
        <i class="fas fa-envelope"></i> Contact Us
      </a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
