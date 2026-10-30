<?php
/**
 * Faculties Overview at Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Academic Faculties';
$breadcrumbTitle = 'Our Academic Faculties';
$breadcrumbs = [['title' => 'Faculties']];

$db = getDb();
$faculties = $db->query("
    SELECT f.*, COUNT(d.id) AS total_depts 
    FROM faculties f 
    LEFT JOIN departments d ON f.id = d.faculty_id AND d.is_active = 1
    WHERE f.is_active = 1 
    GROUP BY f.id 
    ORDER BY f.sort_order ASC
")->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container">
    <div class="section-header">
      <span class="section-subtitle">Academic Divisions</span>
      <h2 class="section-title">Faculties at Rabindra Maitree University</h2>
      <div class="section-line"></div>
    </div>

    <div class="grid-2" style="gap: 2.5rem;">
      <?php foreach ($faculties as $fac): ?>
        <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-lg); border: 1px solid var(--rmu-border); padding: 2.2rem; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--rmu-shadow-sm); transition: var(--rmu-transition);">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem;">
              <div class="faculty-icon" style="margin: 0;">
                <i class="fas <?= e($fac['icon']) ?>"></i>
              </div>
              <span style="background: var(--rmu-white); border: 1px solid var(--rmu-border); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; color: var(--rmu-muted);">
                <?= (int)$fac['total_depts'] ?> Departments
              </span>
            </div>

            <h3 style="font-size: 1.4rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 1rem;">
              <a href="<?= APP_URL ?>/faculty-detail.php?slug=<?= e($fac['slug']) ?>" style="color: inherit;">
                <?= e($fac['name_en']) ?>
              </a>
            </h3>

            <p style="color: var(--rmu-muted); font-size: 0.95rem; line-height: 1.7; margin-bottom: 1.5rem;">
              <?= e($fac['overview']) ?>
            </p>

            <?php if (!empty($fac['dean_name'])): ?>
              <div style="font-size: 0.88rem; color: var(--rmu-dark); margin-bottom: 1.5rem; background: var(--rmu-white); padding: 0.6rem 0.9rem; border-radius: var(--rmu-radius-sm); border-left: 3px solid var(--rmu-primary);">
                <strong>Dean of Faculty:</strong> <?= e($fac['dean_name']) ?>
              </div>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 1rem; align-items: center; border-top: 1px solid var(--rmu-border); padding-top: 1.25rem;">
            <a href="<?= APP_URL ?>/faculty-detail.php?slug=<?= e($fac['slug']) ?>" class="btn-primary" style="padding: 0.6rem 1.4rem; font-size: 0.88rem;">
              View Departments &amp; Programs <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

