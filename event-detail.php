<?php
/**
 * Event Detail View
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$db = getDb();

$stmt = $db->prepare("SELECT * FROM events WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$event = $stmt->fetch();

if (!$event) {
    header("Location: " . APP_URL . "/404.php");
    exit;
}

$pageTitle = $event['title_en'];
$breadcrumbTitle = 'University Event Details';
$breadcrumbs = [
    ['title' => 'Events', 'url' => APP_URL . '/events.php'],
    ['title' => truncate_words($event['title_en'], 5)]
];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 900px;">
    <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-lg); padding: 2.5rem; border: 1px solid var(--rmu-border); margin-bottom: 2rem;">
      <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.8rem;">
        <span class="notice-category-badge badge-admission" style="font-size: 0.85rem; padding: 0.2rem 0.6rem;">
          Event Date: <?= format_date($event['event_date'], 'F d, Y') ?>
        </span>
        <?php if (!empty($event['event_time'])): ?>
          <span style="font-size: 0.85rem; color: var(--rmu-muted);"><i class="fas fa-clock"></i> <?= e($event['event_time']) ?></span>
        <?php endif; ?>
      </div>

      <h2 style="font-size: 1.8rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.4rem;">
        <?= e($event['title_en']) ?>
      </h2>
      <div style="font-size: 1.3rem; font-weight: 600; color: var(--rmu-primary); font-family: 'Hind Siliguri', sans-serif; margin-bottom: 1.5rem;">
        <?= e($event['title_bn']) ?>
      </div>

      <div style="background: var(--rmu-white); padding: 1rem 1.25rem; border-radius: var(--rmu-radius-sm); border: 1px solid var(--rmu-border); margin-bottom: 1.8rem; display: flex; align-items: center; gap: 0.75rem;">
        <i class="fas fa-map-marker-alt" style="color: var(--rmu-accent); font-size: 1.3rem;"></i>
        <div>
          <strong style="color: var(--rmu-dark); display: block;">Venue Location:</strong>
          <span style="color: var(--rmu-muted);"><?= e($event['location']) ?></span>
        </div>
      </div>

      <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.8rem;">About the Event</h3>
      <div style="color: var(--rmu-dark); line-height: 1.8; font-size: 1.05rem; margin-bottom: 2rem;">
        <?= nl2br(e($event['description'])) ?>
      </div>

      <div style="border-top: 1px solid var(--rmu-border); padding-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <a href="<?= APP_URL ?>/events.php" class="btn-primary" style="background: var(--rmu-topbar-bg);">
          &larr; All Events
        </a>
        <a href="<?= APP_URL ?>/contact.php" class="btn-primary">
          <i class="fas fa-question-circle"></i> Inquire About Attendance
        </a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

