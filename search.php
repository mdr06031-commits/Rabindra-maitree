<?php
/**
 * Global Search Results Page
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$query = trim($_GET['q'] ?? '');
$pageTitle = !empty($query) ? 'Search: ' . $query : 'University Search';
$breadcrumbTitle = 'Search Results';
$breadcrumbs = [['title' => 'Search']];

$results = [];
$totalResults = 0;

if (!empty($query)) {
    $db = getDb();
    $like = '%' . $query . '%';

    // 1. Search Notices
    $stmt1 = $db->prepare("
        SELECT 'Notice' AS type, title_en AS title, title_bn AS subtitle, content AS snippet, CONCAT('notice-detail.php?slug=', slug) AS url, published_date AS date
        FROM notices 
        WHERE is_active = 1 AND (title_en LIKE ? OR title_bn LIKE ? OR content LIKE ?)
    ");
    $stmt1->execute([$like, $like, $like]);
    $noticeResults = $stmt1->fetchAll();

    // 2. Search Programs
    $stmt2 = $db->prepare("
        SELECT 'Program' AS type, title_en AS title, title_bn AS subtitle, description AS snippet, CONCAT('program-detail.php?slug=', slug) AS url, NULL AS date
        FROM programs 
        WHERE is_active = 1 AND (title_en LIKE ? OR title_bn LIKE ? OR description LIKE ?)
    ");
    $stmt2->execute([$like, $like, $like]);
    $programResults = $stmt2->fetchAll();

    // 3. Search Departments
    $stmt3 = $db->prepare("
        SELECT 'Department' AS type, name_en AS title, name_bn AS subtitle, overview AS snippet, CONCAT('department-detail.php?slug=', slug) AS url, NULL AS date
        FROM departments 
        WHERE is_active = 1 AND (name_en LIKE ? OR name_bn LIKE ? OR overview LIKE ?)
    ");
    $stmt3->execute([$like, $like, $like]);
    $deptResults = $stmt3->fetchAll();

    // 4. Search News
    $stmt4 = $db->prepare("
        SELECT 'News' AS type, title_en AS title, title_bn AS subtitle, summary AS snippet, CONCAT('news-detail.php?slug=', slug) AS url, published_date AS date
        FROM news 
        WHERE is_active = 1 AND (title_en LIKE ? OR title_bn LIKE ? OR summary LIKE ?)
    ");
    $stmt4->execute([$like, $like, $like]);
    $newsResults = $stmt4->fetchAll();

    $results = array_merge($noticeResults, $programResults, $deptResults, $newsResults);
    $totalResults = count($results);
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 900px;">
    <!-- Search Bar Form -->
    <div style="margin-bottom: 2.5rem;">
      <form action="<?= APP_URL ?>/search.php" method="GET" style="display: flex; gap: 0.5rem;">
        <input type="text" name="q" value="<?= e($query) ?>" placeholder="Search notices, degree programs, departments, news..." class="form-control" style="font-size: 1.05rem; padding: 0.85rem 1.25rem;" required>
        <button type="submit" class="btn-primary" style="padding: 0.85rem 2rem; font-size: 1rem;">
          <i class="fas fa-search"></i> Search
        </button>
      </form>
    </div>

    <?php if (!empty($query)): ?>
      <div style="margin-bottom: 1.5rem; font-size: 1.05rem; color: var(--rmu-muted);">
        Found <strong><?= $totalResults ?></strong> result<?= $totalResults === 1 ? '' : 's' ?> for <span style="color: var(--rmu-primary); font-weight: 700;">"<?= e($query) ?>"</span>
      </div>

      <?php if ($totalResults > 0): ?>
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
          <?php foreach ($results as $item): ?>
            <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.5rem;">
              <div style="display: flex; gap: 0.6rem; align-items: center; margin-bottom: 0.4rem;">
                <span class="notice-category-badge <?= ($item['type'] === 'Program') ? 'badge-admission' : (($item['type'] === 'Notice') ? 'badge-academic' : 'badge-scholarship') ?>">
                  <?= e($item['type']) ?>
                </span>
                <?php if (!empty($item['date'])): ?>
                  <small style="color: var(--rmu-muted);"><?= format_date($item['date']) ?></small>
                <?php endif; ?>
              </div>

              <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 0.6rem;">
                <a href="<?= APP_URL ?>/<?= e($item['url']) ?>" style="color: var(--rmu-dark);">
                  <?= e($item['title']) ?>
                </a>
              </h3>

              <p style="font-size: 0.9rem; color: var(--rmu-muted); line-height: 1.6; margin-bottom: 0.8rem;">
                <?= e(truncate_words(strip_tags($item['snippet']), 30)) ?>
              </p>

              <a href="<?= APP_URL ?>/<?= e($item['url']) ?>" class="card-link" style="font-size: 0.85rem;">
                View <?= e($item['type']) ?> Page &rarr;
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="text-align: center; padding: 3rem 0; color: var(--rmu-muted);">
          <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
          <h3>No matching results found</h3>
          <p>Try searching with different keywords such as "CSE", "Admission", "Scholarship", or "English".</p>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

