<?php
/**
 * Breadcrumb Component
 * Usage: $breadcrumbTitle = 'History'; $breadcrumbs = [['title' => 'About', 'url' => 'about.php'], ['title' => 'History']];
 */
$bTitle = $breadcrumbTitle ?? ($pageTitle ?? 'Page');
$bList = $breadcrumbs ?? [];
?>
<section class="breadcrumb-section" aria-label="Breadcrumb">
  <div class="container">
    <h1 class="breadcrumb-title"><?= e($bTitle) ?></h1>
    <nav class="breadcrumb-nav">
      <a href="<?= APP_URL ?>/index.php"><i class="fas fa-home"></i> Home</a>
      <?php foreach ($bList as $crumb): ?>
        <span class="separator"><i class="fas fa-chevron-right" style="font-size: 0.75rem;"></i></span>
        <?php if (!empty($crumb['url'])): ?>
          <a href="<?= e($crumb['url']) ?>"><?= e($crumb['title']) ?></a>
        <?php else: ?>
          <span class="current" aria-current="page"><?= e($crumb['title']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </div>
</section>

