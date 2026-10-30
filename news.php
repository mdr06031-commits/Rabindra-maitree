<?php
/**
 * Campus News at Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Campus News';
$breadcrumbTitle = 'University News &amp; Press Releases';
$breadcrumbs = [['title' => 'News']];

$db = getDb();
$newsList = $db->query("SELECT * FROM news WHERE is_active = 1 ORDER BY published_date DESC")->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 1000px;">
    <div class="section-header">
      <span class="section-subtitle">Press &amp; Announcements</span>
      <h2 class="section-title">Latest Campus News</h2>
      <div class="section-line"></div>
    </div>

    <?php if (!empty($newsList)): ?>
      <div class="grid-3">
        <?php foreach ($newsList as $item): ?>
          <div class="news-card">
            <div class="card-img-wrap" style="height: 160px; background: linear-gradient(135deg, #135084 0%, #1e73be 100%); display: flex; align-items: center; justify-content: center; color: #fff;">
              <i class="fas fa-newspaper" style="font-size: 3rem; opacity: 0.85;"></i>
            </div>
            <div class="card-body">
              <small style="color: var(--rmu-primary); font-weight: 600; margin-bottom: 0.4rem; display: block;">
                <?= format_date($item['published_date'], 'M d, Y') ?>
              </small>
              <h4 style="font-size: 1.1rem; line-height: 1.4; margin-bottom: 0.8rem;">
                <a href="<?= APP_URL ?>/news-detail.php?slug=<?= e($item['slug']) ?>" style="color: inherit;">
                  <?= e($item['title_en']) ?>
                </a>
              </h4>
              <p><?= e(truncate_words($item['summary'], 18)) ?></p>
              <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--rmu-border-light);">
                <a href="<?= APP_URL ?>/news-detail.php?slug=<?= e($item['slug']) ?>" class="card-link">
                  Full Story &rarr;
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="text-align: center; color: var(--rmu-muted);">No news articles currently published.</p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

