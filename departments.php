<?php
/**
 * All Academic Departments at RMU
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Academic Departments';
$breadcrumbTitle = 'All Academic Departments';
$breadcrumbs = [['title' => 'Departments']];

$db = getDb();
$faculties = $db->query("SELECT * FROM faculties WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();

$deptStmt = $db->prepare("
    SELECT d.*, COUNT(p.id) AS total_programs 
    FROM departments d 
    LEFT JOIN programs p ON d.id = p.department_id AND p.is_active = 1
    WHERE d.faculty_id = ? AND d.is_active = 1 
    GROUP BY d.id 
    ORDER BY d.sort_order ASC
");

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container">
    <div class="section-header">
      <span class="section-subtitle">Academic Departments Directory</span>
      <h2 class="section-title">Departments by Faculty</h2>
      <div class="section-line"></div>
    </div>

    <?php foreach ($faculties as $fac): 
        $deptStmt->execute([$fac['id']]);
        $departments = $deptStmt->fetchAll();
    ?>
      <div style="margin-bottom: 3.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 2px solid var(--rmu-border); padding-bottom: 0.6rem; margin-bottom: 1.5rem;">
          <div>
            <h3 style="font-size: 1.45rem; font-weight: 700; color: var(--rmu-primary); display: inline-flex; align-items: center; gap: 0.5rem;">
              <i class="fas <?= e($fac['icon']) ?>"></i> <?= e($fac['name_en']) ?>
            </h3>
          </div>
          <a href="<?= APP_URL ?>/faculty-detail.php?slug=<?= e($fac['slug']) ?>" style="font-size: 0.88rem; font-weight: 600;">Faculty Overview &rarr;</a>
        </div>

        <div class="grid-3">
          <?php foreach ($departments as $dept): ?>
            <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.5rem; display: flex; flex-direction: column;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                <span style="font-size: 0.75rem; font-weight: 700; background: var(--rmu-primary-light); color: var(--rmu-primary); padding: 0.15rem 0.5rem; border-radius: 4px;">
                  <?= e($dept['code']) ?>
                </span>
                <span style="font-size: 0.8rem; color: var(--rmu-muted);"><?= (int)$dept['total_programs'] ?> Degree Programs</span>
              </div>
              <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.8rem;">
                <a href="<?= APP_URL ?>/department-detail.php?slug=<?= e($dept['slug']) ?>" style="color: inherit;">
                  <?= e($dept['name_en']) ?>
                </a>
              </h4>
              <p style="font-size: 0.85rem; color: var(--rmu-muted); line-height: 1.6; margin-bottom: 1.2rem; flex: 1;">
                <?= e(truncate_words($dept['overview'], 16)) ?>
              </p>
              <div style="margin-top: auto; padding-top: 0.8rem; border-top: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.8rem; color: var(--rmu-muted);"><i class="fas fa-user-tie"></i> <?= e($dept['head_name'] ?? 'Dept Head') ?></span>
                <a href="<?= APP_URL ?>/department-detail.php?slug=<?= e($dept['slug']) ?>" style="font-size: 0.85rem; font-weight: 600;">
                  Explore &rarr;
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

