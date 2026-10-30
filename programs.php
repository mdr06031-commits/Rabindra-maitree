<?php
/**
 * All Academic Programs at Rabindra Maitree University
 * Official Subject List: 11 Undergraduate & 3 Postgraduate Programs
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Academic Degree Programs';
$breadcrumbTitle = 'Undergraduate &amp; Postgraduate Degree Programs';
$breadcrumbs = [['title' => 'Programs']];

$level = $_GET['level'] ?? 'All';
$db = getDb();

if (in_array($level, ['Undergraduate', 'Postgraduate'], true)) {
    $stmt = $db->prepare("
        SELECT p.*, d.name_en AS dept_name, d.slug AS dept_slug, f.name_en AS faculty_name 
        FROM programs p 
        JOIN departments d ON p.department_id = d.id 
        JOIN faculties f ON d.faculty_id = f.id 
        WHERE p.is_active = 1 AND p.degree_level = ?
        ORDER BY p.sort_order ASC
    ");
    $stmt->execute([$level]);
} else {
    $level = 'All';
    $stmt = $db->query("
        SELECT p.*, d.name_en AS dept_name, d.slug AS dept_slug, f.name_en AS faculty_name 
        FROM programs p 
        JOIN departments d ON p.department_id = d.id 
        JOIN faculties f ON d.faculty_id = f.id 
        WHERE p.is_active = 1 
        ORDER BY FIELD(p.degree_level, 'Undergraduate', 'Postgraduate'), p.sort_order ASC
    ");
}
$programs = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container">
    <!-- Top Action Bar: Back to Main Website & Accordion Controls -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e2e8f0;">
      <a href="<?= APP_URL ?>/index.php" class="btn-back-home" style="display: inline-flex; align-items: center; gap: 8px; background: #ffffff; color: #1e73be; border: 1.5px solid #1e73be; padding: 0.55rem 1.15rem; border-radius: 6px; font-weight: 700; font-size: 0.92rem; text-decoration: none; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(30,115,190,0.1);">
        <i class="fas fa-arrow-left"></i> Back to Main Website
      </a>
      
      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <button type="button" id="btnExpandAllCurricula" onclick="document.querySelectorAll('details.program-curriculum-accordion').forEach(el=>el.open=true);" style="background: #1e73be; color: #ffffff; border: none; padding: 0.55rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.84rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background 0.15s ease;">
          <i class="fas fa-plus-circle"></i> Expand All Programs
        </button>
        <button type="button" id="btnCollapseAllCurricula" onclick="document.querySelectorAll('details.program-curriculum-accordion').forEach(el=>el.open=false);" style="background: #e2e8f0; color: #334155; border: none; padding: 0.55rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.84rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background 0.15s ease;">
          <i class="fas fa-minus-circle"></i> Collapse All
        </button>
      </div>
    </div>

    <div class="section-header" style="text-align: left; margin-bottom: 2rem;">
      <span class="section-subtitle">Academic Curricula &amp; Degrees</span>
      <h2 class="section-title" style="text-align: left;">Academic Degree Programs &amp; 4-Year Subject Curriculums</h2>
      <div class="section-line" style="margin: 0.8rem 0 0 0;"></div>
      <p style="color: var(--rmu-muted); margin-top: 0.8rem; font-size: 0.95rem; max-width: 850px; margin-left: 0; margin-right: auto;">
        Rabindra Maitree University offers comprehensive UGC-approved undergraduate and postgraduate programs across four academic faculties: Arts &amp; Social Science, Engineering &amp; Technology, Business Studies, and Agriculture. Explore each program's complete 4-year subject curriculum organized by 1st Year, 2nd Year, 3rd Year, and 4th Year.
      </p>
    </div>

    <!-- 3 Underline Buttons / Filter Tabs -->
    <div class="program-tabs-wrapper">
      <div class="program-tabs-nav" role="tablist" aria-label="Academic Degree Categories">
        <a href="<?= APP_URL ?>/programs.php" class="program-tab-btn <?= ($level === 'All') ? 'active' : '' ?>" role="tab" aria-selected="<?= ($level === 'All') ? 'true' : 'false' ?>">
          <i class="fas fa-th-large"></i> All Degree Programs <span class="tab-count"><?= ($level === 'All') ? count($programs) : '14' ?></span>
        </a>
        <a href="<?= APP_URL ?>/programs.php?level=Undergraduate" class="program-tab-btn <?= ($level === 'Undergraduate') ? 'active' : '' ?>" role="tab" aria-selected="<?= ($level === 'Undergraduate') ? 'true' : 'false' ?>">
          <i class="fas fa-user-graduate"></i> Undergraduate <span class="tab-count">11</span>
        </a>
        <a href="<?= APP_URL ?>/programs.php?level=Postgraduate" class="program-tab-btn <?= ($level === 'Postgraduate') ? 'active' : '' ?>" role="tab" aria-selected="<?= ($level === 'Postgraduate') ? 'true' : 'false' ?>">
          <i class="fas fa-graduation-cap"></i> Postgraduate <span class="tab-count">3</span>
        </a>
      </div>
    </div>

    <!-- Program Cards Grid -->
    <div class="grid-3">
      <?php foreach ($programs as $prog): ?>
        <?php 
          $isAgri = ($prog['slug'] === 'bsc-in-agriculture');
          $cardBg = $isAgri ? 'background: #ffffff; border: 2px solid #22c55e;' : 'background: var(--rmu-bg-light); border: 1px solid var(--rmu-border);';
          $curriculum = get_program_curriculum_by_slug($prog['slug']);
          $displayTitle = (!empty($curriculum['title'])) ? $curriculum['title'] : $prog['title_en'];
        ?>
        <div style="<?= $cardBg ?> border-radius: var(--rmu-radius-md); padding: 1.75rem; display: flex; flex-direction: column; position: relative;">
          
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <span class="notice-category-badge <?= ($prog['degree_level'] === 'Undergraduate') ? 'badge-academic' : 'badge-scholarship' ?>">
              <?= e($prog['degree_level']) ?>
            </span>
            <span style="font-size: 0.8rem; color: var(--rmu-muted);"><i class="fas fa-layer-group"></i> <?= (int)$prog['total_credits'] ?> Credits</span>
          </div>

          <?php if ($isAgri): ?>
            <div style="margin-bottom: 0.4rem;">
              <span style="background: #22c55e; color: #fff; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                <i class="fas fa-seedling"></i> Featured Agricultural Hub
              </span>
            </div>
          <?php endif; ?>

          <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.8rem;">
            <a href="<?= APP_URL ?>/program-detail.php?slug=<?= e($prog['slug']) ?>" style="color: inherit;">
              <?= e($displayTitle) ?>
            </a>
          </h3>

          <div style="font-size: 0.82rem; color: var(--rmu-primary); font-weight: 600; margin-bottom: 1rem;">
            <i class="fas fa-university"></i> <?= e($prog['dept_name']) ?>
          </div>

          <p style="font-size: 0.88rem; color: var(--rmu-muted); line-height: 1.6; margin-bottom: 1.25rem; flex: 1;">
            <?= e(truncate_words($prog['description'], 18)) ?>
          </p>

          <div style="background: var(--rmu-white); padding: 0.6rem 0.9rem; border-radius: var(--rmu-radius-sm); border: 1px solid var(--rmu-border-light); margin-bottom: 1.2rem; font-size: 0.82rem;">
            <div><i class="fas fa-clock"></i> <strong>Duration:</strong> <?= e($prog['duration']) ?></div>
            <div style="margin-top: 3px;"><i class="fas fa-tag"></i> <strong>Fee:</strong> <?= e($prog['tuition_fee'] ?? 'Standard Fee') ?></div>
          </div>

          <?php $curriculum = get_program_curriculum_by_slug($prog['slug']); ?>
          <?php if ($curriculum): ?>
            <details class="program-curriculum-accordion" style="margin-bottom: 1.25rem; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.65rem 0.85rem;" open>
              <summary style="font-size: 0.84rem; font-weight: 700; color: #1e73be; cursor: pointer; user-select: none; display: flex; align-items: center; justify-content: space-between;">
                <span><i class="fas fa-book-reader" style="margin-right: 5px;"></i> 4-Year Academic Subjects (Curriculum)</span>
                <span style="font-size: 0.72rem; color: #64748b; font-weight: 600;"><i class="fas fa-chevron-down"></i></span>
              </summary>
              <div style="margin-top: 0.75rem; border-top: 1px solid #e2e8f0; padding-top: 0.6rem;">
                <?php foreach ($curriculum['years'] as $yrName => $subjs): ?>
                  <div style="margin-bottom: 0.65rem;">
                    <div style="font-size: 0.74rem; font-weight: 700; color: #1e73be; text-transform: uppercase; margin-bottom: 0.3rem; display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 2px;">
                      <span><i class="fas fa-calendar-alt"></i> <?= e($yrName) ?></span>
                      <span style="font-size: 0.68rem; font-weight: 600; background: #f1f5f9; padding: 1px 6px; border-radius: 8px; color: #475569;"><?= count($subjs) ?> Subjects</span>
                    </div>
                    <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.77rem; line-height: 1.45;">
                      <?php foreach ($subjs as $s): ?>
                        <li style="padding: 2.5px 0; display: flex; gap: 5px; border-bottom: 1px dashed #f1f5f9;">
                          <strong style="color: #475569; font-family: monospace; font-size: 0.72rem;"><?= e($s['code']) ?>:</strong>
                          <span style="color: #334155;"><?= e($s['name']) ?></span>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endforeach; ?>
              </div>
            </details>
          <?php endif; ?>

          <div style="margin-top: auto; display: flex; gap: 0.5rem;">
            <a href="<?= APP_URL ?>/program-detail.php?slug=<?= e($prog['slug']) ?>" class="btn-primary" style="flex: 1; justify-content: center; font-size: 0.85rem; padding: 0.55rem 0.8rem;">
              View Program
            </a>
            <a href="<?= APP_URL ?>/admission-form.php?subject=<?= urlencode($prog['title_en']) ?>" style="background: var(--rmu-accent); color: #fff; padding: 0.55rem 0.9rem; border-radius: var(--rmu-radius-sm); font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center;" title="Apply Now">
              Apply
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Bottom Return to Main Website Banner -->
    <div style="text-align: center; margin-top: 3.5rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
      <a href="<?= APP_URL ?>/index.php" style="display: inline-flex; align-items: center; gap: 8px; background: #1e73be; color: #ffffff; padding: 0.8rem 2.2rem; border-radius: 6px; font-weight: 700; font-size: 1rem; text-decoration: none; box-shadow: 0 4px 12px rgba(30,115,190,0.25); transition: all 0.2s ease;">
        <i class="fas fa-arrow-left"></i> Return to Main Website (Homepage)
      </a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
