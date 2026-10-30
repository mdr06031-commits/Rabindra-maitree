<?php
/**
 * Official Notice Board at Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Official Notice Board';
$breadcrumbTitle = 'University Notice Board';
$breadcrumbs = [['title' => 'Notices']];

$db = getDb();
$category = $_GET['cat'] ?? 'All';

// Pagination setup
$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$validCats = ['General', 'Admission', 'Academic', 'Scholarship', 'Exam'];

if (in_array($category, $validCats, true)) {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM notices WHERE is_active = 1 AND category = ?");
    $countStmt->execute([$category]);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT * FROM notices 
        WHERE is_active = 1 AND category = ? 
        ORDER BY is_pinned DESC, published_date DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $category, PDO::PARAM_STR);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
} else {
    $category = 'All';
    $total = (int)$db->query("SELECT COUNT(*) FROM notices WHERE is_active = 1")->fetchColumn();
    $stmt = $db->prepare("
        SELECT * FROM notices 
        WHERE is_active = 1 
        ORDER BY is_pinned DESC, published_date DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
}

$notices = $stmt->fetchAll();
$totalPages = ceil($total / $perPage);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 1000px;">
    <!-- Category Tabs -->
    <div style="display: flex; gap: 0.6rem; margin-bottom: 2.5rem; flex-wrap: wrap; justify-content: center;">
      <a href="<?= APP_URL ?>/notices.php" class="<?= ($category === 'All') ? 'btn-primary' : 'btn-outline-white' ?>" style="<?= ($category !== 'All') ? 'color:var(--rmu-dark)!important; border-color:var(--rmu-border);' : '' ?> padding: 0.45rem 1.1rem; font-size: 0.88rem;">
        All Notices (<?= $total ?>)
      </a>
      <?php foreach ($validCats as $c): ?>
        <a href="<?= APP_URL ?>/notices.php?cat=<?= urlencode($c) ?>" class="<?= ($category === $c) ? 'btn-primary' : 'btn-outline-white' ?>" style="<?= ($category !== $c) ? 'color:var(--rmu-dark)!important; border-color:var(--rmu-border);' : '' ?> padding: 0.45rem 1.1rem; font-size: 0.88rem;">
          <?= e($c) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($notices)): ?>
      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <?php foreach ($notices as $n): ?>
          <div class="notice-card">
            <div class="notice-date-box">
              <div class="day"><?= date('d', strtotime($n['published_date'])) ?></div>
              <div class="month"><?= date('M', strtotime($n['published_date'])) ?></div>
            </div>

            <div class="notice-info">
              <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.4rem;">
                <span class="notice-category-badge <?= ($n['category'] === 'Admission') ? 'badge-admission' : (($n['category'] === 'Scholarship') ? 'badge-scholarship' : 'badge-academic') ?>">
                  <?= e($n['category']) ?>
                </span>
                <?php if ($n['is_pinned']): ?>
                  <span style="background: #fef08a; color: #854d0e; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                    <i class="fas fa-thumbtack"></i> Pinned
                  </span>
                <?php endif; ?>
              </div>

              <h3 class="notice-title" style="margin-bottom: 0.6rem;">
                <a href="<?= APP_URL ?>/notice-detail.php?slug=<?= e($n['slug']) ?>">
                  <?= e($n['title_en']) ?>
                </a>
              </h3>

              <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.8rem; margin-top: 0.5rem;">
                <a href="<?= APP_URL ?>/notice-detail.php?slug=<?= e($n['slug']) ?>" style="font-weight: 600; font-size: 0.85rem;">
                  Read Full Notice &rarr;
                </a>
                <?php if (!empty($n['file_path'])): ?>
                  <a href="<?= APP_URL ?>/<?= e($n['file_path']) ?>" download style="font-size: 0.82rem; color: var(--rmu-accent); font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem;">
                    <i class="fas fa-paperclip"></i> Download Attachment
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <ul class="pagination">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
              <a href="<?= APP_URL ?>/notices.php?cat=<?= urlencode($category) ?>&page=<?= $i ?>" class="page-link"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      <?php endif; ?>

    <?php else: ?>
      <div style="text-align: center; padding: 3rem 0; color: var(--rmu-muted);">
        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.4;"></i>
        <p>No notices found in the selected category.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

