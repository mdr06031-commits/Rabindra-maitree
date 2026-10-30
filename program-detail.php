<?php
/**
 * Academic Program Detail Page
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$db = getDb();

$stmt = $db->prepare("
    SELECT p.*, d.name_en AS dept_name, d.slug AS dept_slug, f.name_en AS faculty_name, f.slug AS faculty_slug 
    FROM programs p 
    JOIN departments d ON p.department_id = d.id 
    JOIN faculties f ON d.faculty_id = f.id 
    WHERE p.slug = ? AND p.is_active = 1
");
$stmt->execute([$slug]);
$program = $stmt->fetch();

if (!$program) {
    header("Location: " . APP_URL . "/404.php");
    exit;
}

$pageTitle = $program['title_en'];
$breadcrumbTitle = $program['title_en'];
$breadcrumbs = [
    ['title' => 'Programs', 'url' => APP_URL . '/programs.php'],
    ['title' => $program['dept_name'], 'url' => APP_URL . '/department-detail.php?slug=' . e($program['dept_slug'])],
    ['title' => $program['title_en']]
];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container">
    <div class="grid-2" style="align-items: start; gap: 3.5rem;">
      <!-- Left Column: Details -->
      <div>
        <div style="font-size: 0.85rem; font-weight: 700; color: var(--rmu-primary); text-transform: uppercase; margin-bottom: 0.3rem;">
          <a href="<?= APP_URL ?>/faculty-detail.php?slug=<?= e($program['faculty_slug']) ?>" style="color:inherit;"><?= e($program['faculty_name']) ?></a> &bull; 
          <a href="<?= APP_URL ?>/department-detail.php?slug=<?= e($program['dept_slug']) ?>" style="color:inherit;"><?= e($program['dept_name']) ?></a>
        </div>
        <h2 class="section-title" style="text-align: left; margin-bottom: 1.25rem;"><?= e($program['title_en']) ?></h2>
        <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-bottom: 1.8rem;"></div>

        <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.8rem;">Program Overview</h3>
        <p style="color: var(--rmu-muted); line-height: 1.8; font-size: 1.02rem; margin-bottom: 2rem;">
          <?= e($program['description']) ?>
        </p>

        <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.8rem;">Admission &amp; Eligibility Criteria</h3>
        <div style="background: var(--rmu-bg-light); border-left: 4px solid var(--rmu-primary); padding: 1.25rem 1.5rem; border-radius: var(--rmu-radius-sm); margin-bottom: 2rem;">
          <p style="color: var(--rmu-dark); margin: 0; line-height: 1.7;">
            <?= e($program['eligibility'] ?? 'Candidates must have passed SSC and HSC or equivalent examinations with minimum GPA 2.50 in both.') ?>
          </p>
        </div>

        <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.8rem;">Tuition Fees &amp; Scholarship Waivers</h3>
        <p style="color: var(--rmu-muted); line-height: 1.8; margin-bottom: 1.5rem;">
          Estimated total tuition cost for this degree: <strong><?= e($program['tuition_fee'] ?? 'Standard Tuition Schedule') ?></strong>.
          RMU offers generous merit-based tuition fee waivers up to 100% based on combined GPA in SSC and HSC examinations, as well as need-based support via the Mansur Ali &amp; Atharunnesa Trust Scholarship.
        </p>

        <!-- 4-Year Academic Subject Curriculum Breakdown -->
        <?php $curriculum = get_program_curriculum_by_slug($program['slug']); ?>
        <?php if ($curriculum): ?>
          <div style="margin: 2.2rem 0; padding: 1.5rem; background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md);">
            <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.4rem; display: flex; align-items: center; gap: 8px;">
              <i class="fas fa-graduation-cap" style="color:var(--rmu-primary);"></i> 4-Year Academic Subjects &amp; Curriculum
            </h3>
            <p style="color: var(--rmu-muted); font-size: 0.92rem; margin-bottom: 1.2rem;">
              Comprehensive course breakdown organized by academic year for <?= e($curriculum['title']) ?>:
            </p>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
              <?php foreach ($curriculum['years'] as $yrName => $subjs): ?>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 1rem 1.15rem;">
                  <div style="font-size: 0.9rem; font-weight: 700; color: var(--rmu-primary); margin-bottom: 0.6rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.4rem;">
                    <span><i class="fas fa-calendar-alt"></i> <?= e($yrName) ?></span>
                    <span style="font-size: 0.75rem; font-weight: 600; color: #475569; background: #f1f5f9; padding: 2px 8px; border-radius: 12px;"><?= count($subjs) ?> Courses</span>
                  </div>
                  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 0.5rem;">
                    <?php foreach ($subjs as $s): ?>
                      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 0.45rem 0.65rem; display: flex; align-items: baseline; gap: 6px; font-size: 0.85rem;">
                        <span style="font-family: monospace; font-size: 0.75rem; font-weight: 700; color: #1e73be; background: #e0f2fe; padding: 1px 5px; border-radius: 3px; white-space: nowrap;"><?= e($s['code']) ?></span>
                        <span style="color: #1e293b; font-weight: 500; line-height: 1.35;"><?= e($s['name']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
          <a href="<?= APP_URL ?>/admission-form.php?subject=<?= urlencode($program['title_en']) ?>" class="btn-primary" style="background: var(--rmu-accent); padding: 0.85rem 2rem; font-size: 1rem;">
            <i class="fas fa-file-signature"></i> Apply for this Program
          </a>
          <a href="<?= APP_URL ?>/tuition-fees.php" class="btn-primary" style="padding: 0.85rem 1.8rem; font-size: 1rem; background: var(--rmu-secondary);">
            <i class="fas fa-calculator"></i> Full Fee Table
          </a>
        </div>
      </div>

      <!-- Right Column: Program Fast Facts -->
      <div>
        <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-lg); padding: 2.2rem; box-shadow: var(--rmu-shadow-sm);">
          <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 1.25rem; border-bottom: 2px solid var(--rmu-border); padding-bottom: 0.6rem;">
            <i class="fas fa-graduation-cap" style="color: var(--rmu-primary);"></i> Program Summary
          </h3>

          <ul style="list-style: none; padding: 0; font-size: 0.95rem;">
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between;">
              <span style="color: var(--rmu-muted);">Degree Level:</span>
              <strong style="color: var(--rmu-dark);"><?= e($program['degree_level']) ?></strong>
            </li>
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between;">
              <span style="color: var(--rmu-muted);">Duration:</span>
              <strong style="color: var(--rmu-dark);"><?= e($program['duration']) ?></strong>
            </li>
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between;">
              <span style="color: var(--rmu-muted);">Total Credits:</span>
              <strong style="color: var(--rmu-dark);"><?= (int)$program['total_credits'] ?> Credits</strong>
            </li>
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; justify-content: space-between;">
              <span style="color: var(--rmu-muted);">Department:</span>
              <strong style="color: var(--rmu-dark);"><?= e($program['dept_name']) ?></strong>
            </li>
            <li style="padding: 0.75rem 0; display: flex; justify-content: space-between;">
              <span style="color: var(--rmu-muted);">Application Status:</span>
              <span class="topbar-badge" style="animation: none;">Open for Fall 2026</span>
            </li>
          </ul>

          <div style="margin-top: 1.8rem; padding: 1rem; background: var(--rmu-white); border-radius: var(--rmu-radius-sm); border: 1px solid var(--rmu-border); text-align: center;">
            <small style="color: var(--rmu-muted); display: block; margin-bottom: 0.5rem;">Have questions regarding this syllabus?</small>
            <a href="<?= APP_URL ?>/contact.php" style="font-weight: 600; color: var(--rmu-primary); font-size: 0.9rem;">
              <i class="fas fa-headset"></i> Contact Academic Counseling
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

