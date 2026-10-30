<?php
/**
 * University Events Calendar at Rabindra Maitree University
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'University Events';
$breadcrumbTitle = 'Events &amp; Academic Conferences';
$breadcrumbs = [['title' => 'Events']];

$db = getDb();
$events = $db->query("SELECT * FROM events WHERE is_active = 1 ORDER BY event_date ASC")->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 1000px;">
    <div class="section-header">
      <span class="section-subtitle">Campus Calendar</span>
      <h2 class="section-title">Events &amp; Academic Seminars</h2>
      <div class="section-line"></div>
    </div>

    <?php if (!empty($events)): ?>
      <div class="grid-2" style="gap: 2rem;">
        <?php foreach ($events as $evt): ?>
          <div class="event-card">
            <div class="card-img-wrap" style="height: 180px; background: linear-gradient(135deg, #0b3c66 0%, #1e73be 100%); display: flex; align-items: center; justify-content: center; color: #fff;">
              <i class="fas fa-calendar-day" style="font-size: 3.5rem; opacity: 0.8;"></i>
            </div>
            <div class="card-body">
              <div class="card-meta">
                <span><i class="fas fa-calendar-alt" style="color: var(--rmu-primary);"></i> <?= format_date($evt['event_date'], 'F d, Y') ?></span>
                <?php if (!empty($evt['event_time'])): ?>
                  <span><i class="fas fa-clock" style="color: var(--rmu-primary);"></i> <?= e($evt['event_time']) ?></span>
                <?php endif; ?>
              </div>

              <h4>
                <a href="<?= APP_URL ?>/event-detail.php?slug=<?= e($evt['slug']) ?>" style="color: inherit;">
                  <?= e($evt['title_en']) ?>
                </a>
              </h4>
              <div style="font-size: 0.95rem; color: var(--rmu-muted); font-family: 'Hind Siliguri', sans-serif; margin-bottom: 0.8rem;">
                <?= e($evt['title_bn']) ?>
              </div>

              <div style="font-size: 0.85rem; color: var(--rmu-muted); margin-bottom: 1rem;">
                <i class="fas fa-map-marker-alt" style="color: var(--rmu-accent);"></i> <?= e($evt['location']) ?>
              </div>

              <p><?= e(truncate_words($evt['description'], 20)) ?></p>

              <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--rmu-border-light);">
                <a href="<?= APP_URL ?>/event-detail.php?slug=<?= e($evt['slug']) ?>" class="card-link">
                  Event Details &rarr;
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="text-align: center; color: var(--rmu-muted);">No upcoming events currently scheduled.</p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

