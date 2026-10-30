<?php
/**
 * Admin Panel: Events Management
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
$adminPageTitle = 'Manage Events';
$db = getDb();

// Handle Add/Edit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $titleEn = trim($_POST['title_en'] ?? '');
        $titleBn = trim($_POST['title_bn'] ?? '');
        $eventDate = trim($_POST['event_date'] ?? date('Y-m-d'));
        $eventTime = trim($_POST['event_time'] ?? '');
        $location = trim($_POST['location'] ?? 'RMU Campus, Kushtia');
        $desc = trim($_POST['description'] ?? '');

        if (empty($titleEn) || empty($desc)) {
            flash_set('error', 'Event title and description are required.');
        } else {
            if ($action === 'add') {
                $slug = slugify($titleEn) . '-' . time();
                $stmt = $db->prepare("
                    INSERT INTO events (slug, title_en, title_bn, event_date, event_time, location, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$slug, $titleEn, $titleBn, $eventDate, $eventTime, $location, $desc]);
                log_audit('EVENT_ADDED', "Added event: {$titleEn}");
                flash_set('success', "Event '{$titleEn}' added successfully.");
            } elseif ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare("
                    UPDATE events 
                    SET title_en = ?, title_bn = ?, event_date = ?, event_time = ?, location = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$titleEn, $titleBn, $eventDate, $eventTime, $location, $desc, $id]);
                log_audit('EVENT_EDITED', "Updated event ID {$id}: {$titleEn}");
                flash_set('success', "Event '{$titleEn}' updated successfully.");
            }
        }
    }
    header("Location: " . APP_URL . "/admin/events.php");
    exit;
}

// Fetch edit target
$editEvent = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$editId]);
    $editEvent = $stmt->fetch();
}

$events = $db->query("SELECT * FROM events ORDER BY event_date ASC")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">University Events Calendar</h2>
    <span style="color: #64748b; font-size: 0.88rem;">Create and manage academic orientations, symposiums, and festivals.</span>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1.2fr 2fr; gap: 2rem; align-items: start;">
  <!-- Form -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem;">
    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem;">
      <?= $editEvent ? 'Edit Event' : 'Add New Event' ?>
    </h3>

    <form action="<?= APP_URL ?>/admin/events.php" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editEvent ? 'edit' : 'add' ?>">
      <?php if ($editEvent): ?>
        <input type="hidden" name="id" value="<?= (int)$editEvent['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Event Title (English) <span class="required">*</span></label>
        <input type="text" name="title_en" value="<?= e($editEvent['title_en'] ?? '') ?>" class="form-control" required placeholder="e.g. Fall 2026 Orientation">
      </div>

      <div class="form-group">
        <label class="form-label">Event Subtitle (Optional)</label>
        <input type="text" name="title_bn" value="<?= e($editEvent['title_bn'] ?? '') ?>" class="form-control" placeholder="e.g. Orientation Gala">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label">Event Date <span class="required">*</span></label>
          <input type="date" name="event_date" value="<?= e($editEvent['event_date'] ?? date('Y-m-d')) ?>" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Time</label>
          <input type="text" name="event_time" value="<?= e($editEvent['event_time'] ?? '10:00 AM') ?>" class="form-control" placeholder="e.g. 10:00 AM - 1:00 PM">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Location / Venue</label>
        <input type="text" name="location" value="<?= e($editEvent['location'] ?? 'RMU Campus, Kushtia') ?>" class="form-control" required>
      </div>

      <div class="form-group">
        <label class="form-label">Event Description <span class="required">*</span></label>
        <textarea name="description" rows="4" class="form-control" required placeholder="Detailed agenda and description..."><?= e($editEvent['description'] ?? '') ?></textarea>
      </div>

      <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
        <button type="submit" class="btn-primary" style="flex: 1; justify-content: center;">
          <i class="fas fa-save"></i> <?= $editEvent ? 'Save Changes' : 'Create Event' ?>
        </button>
        <?php if ($editEvent): ?>
          <a href="<?= APP_URL ?>/admin/events.php" class="btn-primary" style="background: #94a3b8; justify-content: center;">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Table -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow: none;">
        <thead>
          <tr>
            <th>Date &bull; Time</th>
            <th>Title</th>
            <th>Location</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $evt): ?>
            <tr>
              <td>
                <strong style="color: var(--rmu-primary); display: block; font-size: 0.88rem;"><?= format_date($evt['event_date']) ?></strong>
                <small style="color: #64748b;"><?= e($evt['event_time'] ?? '') ?></small>
              </td>
              <td>
                <strong style="color: #0f172a; display: block; font-size: 0.92rem;"><?= e($evt['title_en']) ?></strong>
                <span style="font-size: 0.8rem; color: var(--rmu-muted); font-family: 'Hind Siliguri', sans-serif;"><?= e($evt['title_bn']) ?></span>
              </td>
              <td style="font-size: 0.82rem; color: #475569;"><?= e($evt['location']) ?></td>
              <td style="text-align: right;">
                <a href="<?= APP_URL ?>/admin/events.php?edit=<?= (int)$evt['id'] ?>" style="color: var(--rmu-primary); font-weight: 600; font-size: 0.85rem;">
                  <i class="fas fa-edit"></i> Edit
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

