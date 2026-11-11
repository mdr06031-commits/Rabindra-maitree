<?php
/**
 * Admin Panel: Security Audit Logs
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['superadmin']);
$adminPageTitle = 'Security Audit Logs';
$db = getDb();

// Pagination setup
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = (int)$db->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();

$stmt = $db->prepare("
    SELECT a.*, u.username, u.full_name 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.id DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$totalPages = ceil($total / $perPage);

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">Security &amp; Administrative Audit Logs</h2>
    <span style="color: #64748b; font-size: 0.88rem;">Immutable chronological record of administrative actions, logins, and status transitions.</span>
  </div>
</div>

<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
  <div class="table-responsive">
    <table class="rmu-table" style="box-shadow: none;">
      <thead>
        <tr>
          <th>Timestamp</th>
          <th>Action Type</th>
          <th>User</th>
          <th>Details</th>
          <th>IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($logs)): ?>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td style="font-size: 0.85rem; color: #64748b; white-space: nowrap;">
                <?= format_date($log['created_at'], 'M d, Y H:i:s') ?>
              </td>
              <td>
                <span style="font-weight: 700; font-size: 0.78rem; background: #f1f5f9; color: #0f172a; padding: 0.2rem 0.5rem; border-radius: 4px;">
                  <?= e($log['action']) ?>
                </span>
              </td>
              <td style="font-size: 0.85rem;">
                <strong><?= e($log['username'] ?? 'System / Anonymous') ?></strong>
              </td>
              <td style="font-size: 0.85rem; color: #334155;">
                <?= e($log['details']) ?>
              </td>
              <td style="font-size: 0.8rem; color: #64748b; font-family: monospace;">
                <?= e($log['ip_address']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5" style="text-align: center; padding: 2rem; color: #64748b;">No audit records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div style="padding: 1rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: center;">
      <ul class="pagination" style="margin: 0;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
            <a href="<?= APP_URL ?>/admin/audit-logs.php?page=<?= $i ?>" class="page-link"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

