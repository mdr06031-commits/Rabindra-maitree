<?php
/**
 * News Article Detail View
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$db = getDb();

$stmt = $db->prepare("SELECT * FROM news WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$news = $stmt->fetch();

if (!$news) {
    header("Location: " . APP_URL . "/404.php");
    exit;
}

$pageTitle = $news['title_en'];
$breadcrumbTitle = 'Campus News';
$breadcrumbs = [
    ['title' => 'News', 'url' => APP_URL . '/news.php'],
    ['title' => truncate_words($news['title_en'], 5)]
];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 900px;">
    <div>
      <small style="color: var(--rmu-primary); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
        Published: <?= format_date($news['published_date'], 'F d, Y') ?>
      </small>
      <h2 style="font-size: 2rem; font-weight: 700; color: var(--rmu-dark); margin: 0.4rem 0 1.5rem; line-height: 1.35;">
        <?= e($news['title_en']) ?>
      </h2>
      <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-bottom: 2rem;"></div>

      <div style="background: var(--rmu-bg-light); border-left: 4px solid var(--rmu-primary); padding: 1.25rem 1.5rem; font-size: 1.1rem; color: var(--rmu-dark); margin-bottom: 2rem; font-weight: 500; line-height: 1.6;">
        <?= e($news['summary']) ?>
      </div>

      <div style="color: var(--rmu-dark); line-height: 1.85; font-size: 1.05rem; margin-bottom: 2.5rem;">
        <?= $news['content'] ?>
      </div>

      <div style="border-top: 1px solid var(--rmu-border); padding-top: 1.5rem; display: flex; justify-content: space-between;">
        <a href="<?= APP_URL ?>/news.php" class="btn-primary" style="background: var(--rmu-topbar-bg);">
          &larr; Back to News
        </a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

