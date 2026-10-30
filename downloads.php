<?php
/**
 * Official Downloads at Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Downloadable Forms & Documents';
$breadcrumbTitle = 'Downloads &amp; Academic Resources';
$breadcrumbs = [['title' => 'Downloads']];

$db = getDb();
$category = $_GET['cat'] ?? 'All';

$validCats = ['Admission', 'Academic', 'Examination', 'Administration', 'General'];

if (in_array($category, $validCats, true)) {
    $stmt = $db->prepare("SELECT * FROM downloads WHERE is_active = 1 AND category = ? ORDER BY id ASC");
    $stmt->execute([$category]);
} else {
    $category = 'All';
    $stmt = $db->query("SELECT * FROM downloads WHERE is_active = 1 ORDER BY id ASC");
}

$downloads = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 1000px;">
    <div class="section-header">
      <span class="section-subtitle">Resource Center</span>
      <h2 class="section-title">Official Downloadable Documents</h2>
      <div class="section-line"></div>
    </div>

    <!-- Category Tabs -->
    <div style="display: flex; gap: 0.6rem; margin-bottom: 2.5rem; flex-wrap: wrap; justify-content: center;">
      <a href="<?= APP_URL ?>/downloads.php" class="<?= ($category === 'All') ? 'btn-primary' : 'btn-outline-white' ?>" style="<?= ($category !== 'All') ? 'color:var(--rmu-dark)!important; border-color:var(--rmu-border);' : '' ?> padding: 0.45rem 1.1rem; font-size: 0.88rem;">
        All Documents
      </a>
      <?php foreach ($validCats as $c): ?>
        <a href="<?= APP_URL ?>/downloads.php?cat=<?= urlencode($c) ?>" class="<?= ($category === $c) ? 'btn-primary' : 'btn-outline-white' ?>" style="<?= ($category !== $c) ? 'color:var(--rmu-dark)!important; border-color:var(--rmu-border);' : '' ?> padding: 0.45rem 1.1rem; font-size: 0.88rem;">
          <?= e($c) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($downloads)): ?>
      <div class="table-responsive">
        <table class="rmu-table">
          <thead>
            <tr>
              <th style="width: 50%;">Document Title</th>
              <th>Category</th>
              <th>File Size</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($downloads as $d): ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-file-pdf" style="color: var(--rmu-accent); font-size: 1.3rem;"></i>
                    <strong style="color: var(--rmu-dark);"><?= e($d['title']) ?></strong>
                  </div>
                </td>
                <td>
                  <span class="notice-category-badge badge-academic"><?= e($d['category']) ?></span>
                </td>
                <td style="color: var(--rmu-muted); font-size: 0.88rem;"><?= e($d['file_size']) ?></td>
                <td style="text-align: right;">
                  <a href="<?= APP_URL ?>/<?= e($d['file_path']) ?>" download class="btn-primary" style="padding: 0.4rem 1rem; font-size: 0.82rem; background: var(--rmu-accent);">
                    <i class="fas fa-download"></i> Download
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p style="text-align: center; color: var(--rmu-muted);">No documents available in this category.</p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

