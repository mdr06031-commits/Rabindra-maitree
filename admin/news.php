<?php
/**
 * Admin Panel: Campus News Management
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Manage News';
$db = getDb();

// Handle Add/Edit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $st = $db->prepare("SELECT title_en FROM news WHERE id = ?");
                $st->execute([$id]);
                $nData = $st->fetch();
                $del = $db->prepare("DELETE FROM news WHERE id = ?");
                $del->execute([$id]);
                log_audit('NEWS_DELETED', "Deleted news article: {$nData['title_en']}");
                flash_set('success', "News article deleted successfully.");
            }
        } else {
            $titleEn = trim($_POST['title_en'] ?? '');
            $titleBn = trim($_POST['title_bn'] ?? '');
            $pubDate = trim($_POST['published_date'] ?? date('Y-m-d'));
            $summary = trim($_POST['summary'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (empty($titleEn) || empty($summary) || empty($content)) {
                flash_set('error', 'Title, summary, and full content are required.');
            } else {
                if ($action === 'add') {
                    $slug = slugify($titleEn) . '-' . time();
                    $stmt = $db->prepare("
                        INSERT INTO news (slug, title_en, title_bn, published_date, summary, content)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$slug, $titleEn, $titleBn, $pubDate, $summary, $content]);
                    log_audit('NEWS_ADDED', "Added news: {$titleEn}");
                    flash_set('success', "News article '{$titleEn}' published successfully.");
                } elseif ($action === 'edit') {
                    $id = (int)($_POST['id'] ?? 0);
                    $stmt = $db->prepare("
                        UPDATE news 
                        SET title_en = ?, title_bn = ?, published_date = ?, summary = ?, content = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$titleEn, $titleBn, $pubDate, $summary, $content, $id]);
                    log_audit('NEWS_EDITED', "Updated news ID {$id}: {$titleEn}");
                    flash_set('success', "News article '{$titleEn}' updated successfully.");
                }
            }
        }
    }
    header("Location: " . APP_URL . "/admin/news.php");
    exit;
}

// Fetch edit target
$editNews = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->execute([$editId]);
    $editNews = $stmt->fetch();
}

$newsList = $db->query("SELECT * FROM news ORDER BY published_date DESC")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">Campus News &amp; Press Releases</h2>
    <span style="color: #64748b; font-size: 0.88rem;">Publish university achievements, accreditations, and research milestones.</span>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1.2fr 2fr; gap: 2rem; align-items: start;">
  <!-- Form -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem;">
    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem;">
      <?= $editNews ? 'Edit News Article' : 'Publish News Article' ?>
    </h3>

    <form action="<?= APP_URL ?>/admin/news.php" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editNews ? 'edit' : 'add' ?>">
      <?php if ($editNews): ?>
        <input type="hidden" name="id" value="<?= (int)$editNews['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Headline (English) <span class="required">*</span></label>
        <input type="text" name="title_en" value="<?= e($editNews['title_en'] ?? '') ?>" class="form-control" required placeholder="e.g. UGC Delegation Commends RMU Campus">
      </div>

      <div class="form-group">
        <label class="form-label">Headline Subtitle (Optional)</label>
        <input type="text" name="title_bn" value="<?= e($editNews['title_bn'] ?? '') ?>" class="form-control" placeholder="e.g. UGC Delegation Visit">
      </div>

      <div class="form-group">
        <label class="form-label">Published Date <span class="required">*</span></label>
        <input type="date" name="published_date" value="<?= e($editNews['published_date'] ?? date('Y-m-d')) ?>" class="form-control" required>
      </div>

      <div class="form-group">
        <label class="form-label">Summary / Lead Paragraph <span class="required">*</span></label>
        <textarea name="summary" rows="2" class="form-control" required placeholder="Short summary for homepage cards..."><?= e($editNews['summary'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Full Article Content <span class="required">*</span></label>
        <textarea name="content" rows="6" class="form-control" required placeholder="Complete press story (HTML paragraphs allowed)..."><?= e($editNews['content'] ?? '') ?></textarea>
      </div>

      <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
        <button type="submit" class="btn-primary" style="flex: 1; justify-content: center;">
          <i class="fas fa-save"></i> <?= $editNews ? 'Save Changes' : 'Publish Article' ?>
        </button>
        <?php if ($editNews): ?>
          <a href="<?= APP_URL ?>/admin/news.php" class="btn-primary" style="background: #94a3b8; justify-content: center;">Cancel</a>
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
            <th>Date</th>
            <th>Headline</th>
            <th>Summary Excerpt</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($newsList as $item): ?>
            <tr>
              <td style="font-size: 0.85rem; color: #64748b; white-space: nowrap;">
                <?= format_date($item['published_date']) ?>
              </td>
              <td>
                <strong style="color: #0f172a; display: block; font-size: 0.92rem;"><?= e($item['title_en']) ?></strong>
                <span style="font-size: 0.8rem; color: var(--rmu-muted); font-family: 'Hind Siliguri', sans-serif;"><?= e($item['title_bn']) ?></span>
              </td>
              <td style="font-size: 0.82rem; color: #475569;">
                <?= e(truncate_words($item['summary'], 12)) ?>
              </td>
              <td style="text-align: right; white-space: nowrap;">
                <a href="<?= APP_URL ?>/admin/news.php?edit=<?= (int)$item['id'] ?>" style="color: var(--rmu-primary); font-weight: 600; font-size: 0.85rem; margin-right: 6px;">
                  <i class="fas fa-edit"></i> Edit
                </a>
                <form action="<?= APP_URL ?>/admin/news.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete news <?= addslashes(e($item['title_en'])) ?>?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 0.85rem;" title="Delete">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

