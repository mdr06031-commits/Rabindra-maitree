<?php
/**
 * Department Detail Page
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$db = getDb();

$stmt = $db->prepare("
    SELECT d.*, f.name_en AS faculty_name, f.slug AS faculty_slug 
    FROM departments d 
    JOIN faculties f ON d.faculty_id = f.id 
    WHERE d.slug = ? AND d.is_active = 1
");
$stmt->execute([$slug]);
$department = $stmt->fetch();

if (!$department) {
    header("Location: " . APP_URL . "/404.php");
    exit;
}

// Fetch programs under this department
$progStmt = $db->prepare("SELECT * FROM programs WHERE department_id = ? AND is_active = 1 ORDER BY sort_order ASC");
$progStmt->execute([$department['id']]);
$programs = $progStmt->fetchAll();

// Fetch faculty members under this department
$facultyStmt = $db->prepare("SELECT * FROM faculty_members WHERE department_id = ? AND is_active = 1 ORDER BY sort_order ASC");
$facultyStmt->execute([$department['id']]);
$teachers = $facultyStmt->fetchAll();

$pageTitle = $department['name_en'];
$breadcrumbTitle = $department['name_en'];
$breadcrumbs = [
    ['title' => 'Departments', 'url' => APP_URL . '/departments.php'],
    ['title' => $department['faculty_name'], 'url' => APP_URL . '/faculty-detail.php?slug=' . e($department['faculty_slug'])],
    ['title' => $department['name_en']]
];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container">
    <div class="grid-2" style="align-items: start; gap: 3rem; margin-bottom: 3.5rem;">
      <div>
        <div style="font-size: 0.85rem; font-weight: 700; color: var(--rmu-primary); text-transform: uppercase; margin-bottom: 0.3rem;">
          Code: <?= e($department['code']) ?> &bull; <?= e($department['faculty_name']) ?>
        </div>
        <h2 class="section-title" style="text-align: left; margin-bottom: 1.25rem;"><?= e($department['name_en']) ?></h2>
        <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-bottom: 1.5rem;"></div>

        <p style="color: var(--rmu-muted); line-height: 1.8; font-size: 1.02rem; margin-bottom: 1.5rem;">
          <?= e($department['overview']) ?>
        </p>

        <?php if (!empty($department['head_name'])): ?>
          <div style="background: var(--rmu-bg-light); border-left: 4px solid var(--rmu-primary); padding: 1.25rem; border-radius: var(--rmu-radius-sm); margin-bottom: 1.5rem;">
            <strong style="color: var(--rmu-dark); display: block; font-size: 1rem;">Department Leadership</strong>
            <span style="color: var(--rmu-muted);"><?= e($department['head_name']) ?> — Head of Department</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Department Contact Box -->
      <div>
        <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-lg); padding: 2rem;">
          <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 1rem; border-bottom: 1px solid var(--rmu-border); padding-bottom: 0.6rem;">
            Department Contact &amp; Inquiries
          </h3>
          <ul style="list-style: none; padding: 0; font-size: 0.92rem;">
            <li style="padding: 0.6rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; gap: 0.8rem;">
              <i class="fas fa-envelope" style="color: var(--rmu-primary); margin-top: 3px;"></i>
              <span><strong>Official Email:</strong> <?= e($department['email'] ?? 'info@rmu.ac.bd') ?></span>
            </li>
            <li style="padding: 0.6rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; gap: 0.8rem;">
              <i class="fas fa-phone-alt" style="color: var(--rmu-primary); margin-top: 3px;"></i>
              <span><strong>Phone Hotline:</strong> <?= e($department['phone'] ?? '+8801302991188') ?></span>
            </li>
            <li style="padding: 0.6rem 0; display: flex; gap: 0.8rem;">
              <i class="fas fa-map-marker-alt" style="color: var(--rmu-primary); margin-top: 3px;"></i>
              <span><strong>Location:</strong> Academic Building, RMU Kushtia Campus</span>
            </li>
          </ul>

          <div style="margin-top: 1.5rem;">
            <a href="<?= APP_URL ?>/admission-form.php" class="btn-primary" style="width: 100%; justify-content: center; background: var(--rmu-accent);">
              <i class="fas fa-file-signature"></i> Apply for this Department
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Programs Offered in this Department -->
    <div style="margin-bottom: 3.5rem;">
      <div class="section-header" style="text-align: left; margin-bottom: 1.8rem;">
        <h3 style="font-size: 1.6rem; font-weight: 700; color: var(--rmu-dark); font-family: var(--rmu-font-serif);">
          Degree Programs Offered
        </h3>
        <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-top: 0.4rem;"></div>
      </div>

      <?php if (!empty($programs)): ?>
        <div class="grid-2">
          <?php foreach ($programs as $prog): ?>
            <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.75rem;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span class="notice-category-badge badge-academic"><?= e($prog['degree_level']) ?></span>
                <span style="font-size: 0.82rem; color: var(--rmu-muted);"><i class="fas fa-clock"></i> <?= e($prog['duration']) ?></span>
              </div>
              <h4 style="font-size: 1.2rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.3rem;">
                <?= e($prog['title_en']) ?>
              </h4>
              <div style="font-size: 0.95rem; color: var(--rmu-muted); font-family: 'Hind Siliguri', sans-serif; margin-bottom: 1rem;">
                <?= e($prog['title_bn']) ?>
              </div>
              <p style="font-size: 0.88rem; color: var(--rmu-muted); line-height: 1.6; margin-bottom: 1.25rem;">
                <?= e($prog['description']) ?>
              </p>
              <div style="background: var(--rmu-white); padding: 0.8rem 1rem; border-radius: var(--rmu-radius-sm); border: 1px solid var(--rmu-border); margin-bottom: 1.2rem; font-size: 0.85rem;">
                <strong>Eligibility:</strong> <?= e($prog['eligibility'] ?? 'HSC/Equivalent GPA 2.50 minimum') ?>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 700; color: var(--rmu-primary); font-size: 0.95rem;"><?= e($prog['tuition_fee'] ?? 'Tuition: Standard') ?></span>
                <a href="<?= APP_URL ?>/program-detail.php?slug=<?= e($prog['slug']) ?>" class="btn-primary" style="padding: 0.45rem 1rem; font-size: 0.82rem;">
                  Program Details &rarr;
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="color: var(--rmu-muted);">Currently updating program specifications for this department.</p>
      <?php endif; ?>
    </div>

    <!-- Faculty & Teaching Staff Profiles -->
    <?php if (!empty($teachers)): ?>
      <div>
        <div class="section-header" style="text-align: left; margin-bottom: 1.8rem;">
          <h3 style="font-size: 1.6rem; font-weight: 700; color: var(--rmu-dark); font-family: var(--rmu-font-serif);">
            Faculty &amp; Teaching Staff
          </h3>
          <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-top: 0.4rem;"></div>
        </div>

        <div class="grid-3">
          <?php foreach ($teachers as $t): ?>
            <div style="background: var(--rmu-white); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-md); padding: 1.5rem; text-align: center; box-shadow: var(--rmu-shadow-sm);">
              <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--rmu-primary-light); color: var(--rmu-primary); margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                <i class="fas fa-user-tie"></i>
              </div>
              <h4 style="font-size: 1.1rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.2rem;"><?= e($t['full_name']) ?></h4>
              <div style="font-size: 0.85rem; color: var(--rmu-primary); font-weight: 600; margin-bottom: 0.4rem;"><?= e($t['designation']) ?></div>
              <div style="font-size: 0.8rem; color: var(--rmu-muted); margin-bottom: 0.8rem;"><?= e($t['qualification'] ?? '') ?></div>
              <div style="font-size: 0.82rem; color: var(--rmu-dark);">
                <i class="fas fa-envelope"></i> <?= e($t['email'] ?? 'info@rmu.ac.bd') ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

