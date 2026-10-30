<?php
/**
 * Faculty Detail View
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$db = getDb();

$stmt = $db->prepare("SELECT * FROM faculties WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$faculty = $stmt->fetch();

if (!$faculty) {
    header("Location: " . APP_URL . "/404.php");
    exit;
}

// Fetch departments under this faculty
$deptStmt = $db->prepare("
    SELECT d.*, COUNT(p.id) AS total_programs 
    FROM departments d 
    LEFT JOIN programs p ON d.id = p.department_id AND p.is_active = 1
    WHERE d.faculty_id = ? AND d.is_active = 1 
    GROUP BY d.id 
    ORDER BY d.sort_order ASC
");
$deptStmt->execute([$faculty['id']]);
$departments = $deptStmt->fetchAll();

$pageTitle = $faculty['name_en'];
$breadcrumbTitle = $faculty['name_en'];
$breadcrumbs = [
    ['title' => 'Faculties', 'url' => APP_URL . '/faculties.php'],
    ['title' => $faculty['name_en']]
];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container">
    <div class="grid-2" style="align-items: start; gap: 3rem; margin-bottom: 3.5rem;">
      <div>
        <span class="section-subtitle">Academic Faculty</span>
        <h2 class="section-title" style="text-align: left; margin-bottom: 1.25rem;"><?= e($faculty['name_en']) ?></h2>
        <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-bottom: 1.5rem;"></div>

        <p style="color: var(--rmu-muted); line-height: 1.8; font-size: 1.05rem; margin-bottom: 1.5rem;">
          <?= e($faculty['overview']) ?>
        </p>

        <?php if (!empty($faculty['dean_name'])): ?>
          <div style="background: var(--rmu-bg-light); border-left: 4px solid var(--rmu-primary); padding: 1.25rem; border-radius: var(--rmu-radius-sm);">
            <strong style="color: var(--rmu-dark); display: block; margin-bottom: 0.2rem;">Office of the Dean</strong>
            <span style="color: var(--rmu-muted);"><?= e($faculty['dean_name']) ?></span>
          </div>
        <?php endif; ?>
      </div>

      <div>
        <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-lg); padding: 2rem;">
          <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 1rem;">
            Faculty Quick Summary
          </h3>
          <ul style="list-style: none; padding: 0;">
            <li style="padding: 0.6rem 0; border-bottom: 1px solid var(--rmu-border); display: flex; justify-content: space-between;">
              <span>Total Departments:</span>
              <strong><?= count($departments) ?></strong>
            </li>
            <li style="padding: 0.6rem 0; border-bottom: 1px solid var(--rmu-border); display: flex; justify-content: space-between;">
              <span>Faculty Dean:</span>
              <strong><?= e($faculty['dean_name'] ?? 'TBA') ?></strong>
            </li>
            <li style="padding: 0.6rem 0; display: flex; justify-content: space-between;">
              <span>Degree Levels:</span>
              <strong>Undergraduate &amp; Graduate</strong>
            </li>
          </ul>
          <div style="margin-top: 1.5rem;">
            <a href="<?= APP_URL ?>/admission-form.php" class="btn-primary" style="width: 100%; justify-content: center;">
              Apply to this Faculty
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Departments under this Faculty -->
    <div class="section-header" style="text-align: left; margin-bottom: 2rem;">
      <h3 style="font-size: 1.6rem; font-weight: 700; color: var(--rmu-dark); font-family: var(--rmu-font-serif);">
        Departments under <?= e($faculty['name_en']) ?>
      </h3>
      <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-top: 0.5rem;"></div>
    </div>

    <div class="grid-3">
      <?php foreach ($departments as $dept): ?>
        <div style="background: var(--rmu-white); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.75rem; box-shadow: var(--rmu-shadow-sm); display: flex; flex-direction: column;">
          <div style="font-size: 0.8rem; font-weight: 700; color: var(--rmu-primary); text-transform: uppercase; margin-bottom: 0.3rem;">
            Code: <?= e($dept['code']) ?>
          </div>
          <h4 style="font-size: 1.2rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.8rem;">
            <a href="<?= APP_URL ?>/department-detail.php?slug=<?= e($dept['slug']) ?>" style="color: inherit;">
              <?= e($dept['name_en']) ?>
            </a>
          </h4>
          <p style="font-size: 0.88rem; color: var(--rmu-muted); line-height: 1.6; margin-bottom: 1.25rem; flex: 1;">
            <?= e(truncate_words($dept['overview'], 18)) ?>
          </p>
          <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.82rem; color: var(--rmu-muted);"><i class="fas fa-graduation-cap"></i> <?= (int)$dept['total_programs'] ?> Programs</span>
            <a href="<?= APP_URL ?>/department-detail.php?slug=<?= e($dept['slug']) ?>" style="font-size: 0.85rem; font-weight: 600;">
              View Department <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

