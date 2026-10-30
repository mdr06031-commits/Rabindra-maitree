<?php
/**
 * Single Notice Detail View
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$db = getDb();

$stmt = $db->prepare("SELECT * FROM notices WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$notice = $stmt->fetch();

if (!$notice) {
    header("Location: " . APP_URL . "/404.php");
    exit;
}

// Increment view count
$db->prepare("UPDATE notices SET view_count = view_count + 1 WHERE id = ?")->execute([$notice['id']]);

// Fetch other recent notices
$recentStmt = $db->prepare("SELECT id, slug, title_en, published_date FROM notices WHERE id != ? AND is_active = 1 ORDER BY published_date DESC LIMIT 5");
$recentStmt->execute([$notice['id']]);
$otherNotices = $recentStmt->fetchAll();

$pageTitle = $notice['title_en'];
$breadcrumbTitle = 'Notice Board';
$breadcrumbs = [
    ['title' => 'Notices', 'url' => APP_URL . '/notices.php'],
    ['title' => $notice['category'], 'url' => APP_URL . '/notices.php?cat=' . urlencode($notice['category'])],
    ['title' => truncate_words($notice['title_en'], 5)]
];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container">
    <div class="grid-2" style="grid-template-columns: 2fr 1fr; gap: 3rem;">
      <!-- Main Content -->
      <div>
        <div style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.8rem;">
          <span class="notice-category-badge <?= ($notice['category'] === 'Admission') ? 'badge-admission' : (($notice['category'] === 'Scholarship') ? 'badge-scholarship' : 'badge-academic') ?>" style="font-size: 0.85rem; padding: 0.2rem 0.6rem;">
            <?= e($notice['category']) ?>
          </span>
          <span style="font-size: 0.85rem; color: var(--rmu-muted);">
            <i class="fas fa-calendar-alt"></i> <?= format_date($notice['published_date'], 'F d, Y') ?>
          </span>
          <span style="font-size: 0.85rem; color: var(--rmu-muted);">
            <i class="fas fa-eye"></i> <?= (int)$notice['view_count'] + 1 ?> Views
          </span>
        </div>

        <h2 style="font-size: 1.8rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 1.5rem; line-height: 1.35;">
          <?= e($notice['title_en']) ?>
        </h2>

        <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-bottom: 2rem;"></div>

        <!-- Rich Notice Body -->
        <div style="color: var(--rmu-dark); line-height: 1.8; font-size: 1.05rem; margin-bottom: 2.5rem;">
          <?= $notice['content'] ?>
        </div>

        <?php if (!empty($notice['file_path'])): ?>
          <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 2.5rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
              <i class="fas fa-file-pdf" style="font-size: 2.2rem; color: var(--rmu-accent);"></i>
              <div>
                <strong style="color: var(--rmu-dark); display: block;">Official Attached Notice File</strong>
                <small style="color: var(--rmu-muted);">Click to download official signed memorandum</small>
              </div>
            </div>
            <a href="<?= APP_URL ?>/<?= e($notice['file_path']) ?>" download class="btn-primary" style="background: var(--rmu-accent);">
              <i class="fas fa-download"></i> Download Notice PDF
            </a>
          </div>
        <?php endif; ?>

        <div style="border-top: 1px solid var(--rmu-border); padding-top: 1.5rem;">
          <a href="<?= APP_URL ?>/notices.php" class="btn-primary" style="background: var(--rmu-topbar-bg);">
            &larr; Back to Notice Board
          </a>
        </div>
      </div>

      <!-- Sidebar -->
      <div>
        <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.75rem; margin-bottom: 2rem;">
          <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 1.25rem; border-bottom: 2px solid var(--rmu-border); padding-bottom: 0.5rem;">
            Other Recent Notices
          </h4>
          <ul style="list-style: none; padding: 0;">
            <?php foreach ($otherNotices as $on): ?>
              <li style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--rmu-border-light);">
                <small style="color: var(--rmu-primary); font-weight: 600;"><?= format_date($on['published_date']) ?></small>
                <h5 style="margin-top: 0.2rem; font-size: 0.95rem; font-weight: 600; line-height: 1.4;">
                  <a href="<?= APP_URL ?>/notice-detail.php?slug=<?= e($on['slug']) ?>" style="color: var(--rmu-dark);">
                    <?= e($on['title_en']) ?>
                  </a>
                </h5>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

