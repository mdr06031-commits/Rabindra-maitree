<?php
/**
 * Admin Panel: System Administrators & Security Credentials Control
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
$adminPageTitle = 'Admin User Accounts';
$db = getDb();
$currentUser = current_user();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'CSRF verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Update Self Profile & Password
        if ($action === 'update_profile') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');

            if (empty($fullName) || empty($email)) {
                flash_set('error', 'Full Name and Email are required.');
            } else {
                // If attempting to change password, verify current password
                if (!empty($newPassword)) {
                    $uStmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $uStmt->execute([$currentUser['id']]);
                    $hash = $uStmt->fetchColumn();

                    if (!password_verify($currentPassword, $hash)) {
                        flash_set('error', 'Current password was incorrect. Password change failed.');
                        header("Location: " . APP_URL . "/admin/users.php");
                        exit;
                    }

                    if (strlen($newPassword) < 6) {
                        flash_set('error', 'New password must be at least 6 characters.');
                        header("Location: " . APP_URL . "/admin/users.php");
                        exit;
                    }

                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $upd = $db->prepare("UPDATE users SET full_name = ?, email = ?, password_hash = ? WHERE id = ?");
                    $upd->execute([$fullName, $email, $newHash, $currentUser['id']]);
                    log_audit('PASSWORD_CHANGED', "Administrator {$currentUser['username']} updated their password.");
                    flash_set('success', 'Profile and password updated successfully.');
                } else {
                    $upd = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                    $upd->execute([$fullName, $email, $currentUser['id']]);
                    log_audit('PROFILE_UPDATED', "Administrator {$currentUser['username']} updated profile information.");
                    flash_set('success', 'Profile information updated successfully.');
                }
            }
        }

        // 2. Add New Admin User (Superadmin only)
        elseif ($action === 'add_user') {
            if ($currentUser['role'] !== 'superadmin') {
                flash_set('error', 'Only Superadmins can create administrative accounts.');
            } else {
                $username = trim($_POST['username'] ?? '');
                $fullName = trim($_POST['full_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = (string)($_POST['password'] ?? '');
                $role = in_array($_POST['role'] ?? '', ['superadmin', 'admin', 'editor']) ? $_POST['role'] : 'admin';

                if (empty($username) || empty($fullName) || empty($email) || empty($password)) {
                    flash_set('error', 'All fields are required.');
                } elseif (strlen($password) < 6) {
                    flash_set('error', 'Password must be at least 6 characters.');
                } else {
                    $chk = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $chk->execute([$username, $email]);
                    if ($chk->fetch()) {
                        flash_set('error', 'Username or email already in use.');
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $ins = $db->prepare("INSERT INTO users (username, full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
                        $ins->execute([$username, $fullName, $email, $hash, $role]);
                        log_audit('ADMIN_USER_CREATED', "Created new {$role} user: {$username}");
                        flash_set('success', "Administrator '{$username}' created successfully.");
                    }
                }
            }
        }

        // 3. Reset Another User's Password (Superadmin only)
        elseif ($action === 'reset_user_password') {
            if ($currentUser['role'] !== 'superadmin') {
                flash_set('error', 'Only Superadmins can reset passwords.');
            } else {
                $targetId = (int)($_POST['target_id'] ?? 0);
                $newPass = (string)($_POST['reset_password'] ?? '');

                if ($targetId > 0 && strlen($newPass) >= 6) {
                    $h = password_hash($newPass, PASSWORD_DEFAULT);
                    $rst = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $rst->execute([$h, $targetId]);
                    log_audit('ADMIN_PASSWORD_RESET', "Superadmin reset password for user ID #{$targetId}");
                    flash_set('success', "Password for user #{$targetId} reset successfully.");
                } else {
                    flash_set('error', 'Password must be at least 6 characters.');
                }
            }
        }

        // 4. Delete Admin User
        elseif ($action === 'delete_user') {
            if ($currentUser['role'] !== 'superadmin') {
                flash_set('error', 'Only Superadmins can remove admin accounts.');
            } else {
                $targetId = (int)($_POST['target_id'] ?? 0);
                if ($targetId === (int)$currentUser['id']) {
                    flash_set('error', 'You cannot delete your own active administrator account.');
                } elseif ($targetId > 0) {
                    $del = $db->prepare("DELETE FROM users WHERE id = ?");
                    $del->execute([$targetId]);
                    log_audit('ADMIN_USER_DELETED', "Superadmin deleted user ID #{$targetId}");
                    flash_set('success', "Administrator account removed.");
                }
            }
        }
    }
    header("Location: " . APP_URL . "/admin/users.php");
    exit;
}

// Fetch all admin users
$users = $db->query("SELECT * FROM users ORDER BY role = 'superadmin' DESC, id ASC")->fetchAll();

include __DIR__ . '/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
  <div>
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0 0 0.2rem;">
      <i class="fas fa-user-shield" style="color: var(--rmu-primary);"></i> Admin Users &amp; Security Controls
    </h2>
    <span style="color: #64748b; font-size: 0.88rem;">Manage administrator accounts, assign permissions, and change login credentials.</span>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 2rem; align-items: start;">
  
  <!-- Left: All Admin Users Table -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">Authorized Administrators</h3>
    </div>
    <div class="table-responsive">
      <table class="rmu-table" style="box-shadow: none; margin: 0;">
        <thead>
          <tr>
            <th>User &bull; Role</th>
            <th>Email</th>
            <th>Status &bull; Last Login</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td>
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                  <div style="width: 34px; height: 34px; border-radius: 50%; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                    <?= strtoupper(substr($u['username'], 0, 1)) ?>
                  </div>
                  <div>
                    <strong style="color: #0f172a; display: block; font-size: 0.92rem;"><?= e($u['full_name']) ?></strong>
                    <span style="font-family: monospace; font-size: 0.8rem; color: #64748b;">@<?= e($u['username']) ?></span>
                    <span style="display: inline-block; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700; background: <?= ($u['role'] === 'superadmin') ? '#fef3c7' : '#e0e7ff' ?>; color: <?= ($u['role'] === 'superadmin') ? '#b45309' : '#3730a3' ?>; margin-left: 4px;">
                      <?= strtoupper(e($u['role'])) ?>
                    </span>
                  </div>
                </div>
              </td>
              <td style="font-size: 0.85rem; color: #334155;">
                <?= e($u['email']) ?>
              </td>
              <td style="font-size: 0.8rem; color: #64748b;">
                <span style="display: inline-block; padding: 0.15rem 0.45rem; border-radius: 4px; font-weight: 700; font-size: 0.72rem; background: <?= ($u['status'] === 'active') ? '#dcfce7' : '#fee2e2' ?>; color: <?= ($u['status'] === 'active') ? '#15803d' : '#b91c1c' ?>;">
                  <?= ucfirst(e($u['status'])) ?>
                </span>
                <div style="margin-top: 3px; font-size: 0.72rem;">
                  <?= !empty($u['last_login']) ? date('M d, Y H:i', strtotime($u['last_login'])) : 'Never' ?>
                </div>
              </td>
              <td style="text-align: right; white-space: nowrap;">
                <?php if ($currentUser['role'] === 'superadmin'): ?>
                  <!-- Reset Password Form Button -->
                  <button type="button" onclick="showResetModal(<?= $u['id'] ?>, '<?= e($u['username']) ?>')" style="background: none; border: none; color: #0284c7; cursor: pointer; font-size: 0.82rem; font-weight: 600; margin-right: 6px;" title="Reset Password">
                    <i class="fas fa-key"></i> Password
                  </button>
                  <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                    <form action="<?= APP_URL ?>/admin/users.php" method="POST" style="display: inline;" onsubmit="return confirm('Remove administrator account <?= e($u['username']) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                      <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 0.82rem;">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right: Add User (Superadmin) or Profile Form -->
  <div style="display: flex; flex-direction: column; gap: 2rem;">
    
    <!-- Self Profile & Password Update -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem;">
        <i class="fas fa-id-badge" style="color: var(--rmu-primary);"></i> My Admin Profile &amp; Password
      </h3>

      <form action="<?= APP_URL ?>/admin/users.php" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">

        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" name="full_name" value="<?= e($currentUser['full_name']) ?>" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" value="<?= e($currentUser['email']) ?>" class="form-control" required>
        </div>

        <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 1rem; margin-top: 1rem;">
          <label style="font-size: 0.85rem; font-weight: 700; color: #0369a1; display: block; margin-bottom: 0.5rem;">
            Change My Password (optional)
          </label>
          <div class="form-group">
            <label class="form-label" style="font-size: 0.8rem;">Current Password</label>
            <input type="password" name="current_password" class="form-control" placeholder="Required only if setting new password">
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" style="font-size: 0.8rem;">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters">
          </div>
        </div>

        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 1.25rem;">
          <i class="fas fa-save"></i> Save Profile &amp; Password
        </button>
      </form>
    </div>

    <!-- Create New Admin (Superadmin Only) -->
    <?php if ($currentUser['role'] === 'superadmin'): ?>
      <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem;">
          <i class="fas fa-user-plus" style="color: var(--rmu-primary);"></i> Add New Admin Account
        </h3>

        <form action="<?= APP_URL ?>/admin/users.php" method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_user">

          <div class="form-group">
            <label class="form-label">Username *</label>
            <input type="text" name="username" placeholder="e.g. jahan_admin" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" placeholder="e.g. Prof. Jahanara Begum" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Email *</label>
            <input type="email" name="email" placeholder="e.g. jahan@rmu.ac.bd" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Role Privilege</label>
            <select name="role" class="form-select">
              <option value="admin">Administrator (Full Content Control)</option>
              <option value="editor">Editor (Notices, News &amp; Content)</option>
              <option value="superadmin">Super Administrator (Full System + Users)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Initial Password *</label>
            <input type="text" name="password" value="Admin@RMU<?= date('Y') ?>" class="form-control" required>
          </div>

          <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 1.25rem;">
            <i class="fas fa-plus-circle"></i> Create Administrator
          </button>
        </form>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- Modal for Password Reset -->
<div id="resetModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
  <div style="background: #ffffff; padding: 2rem; border-radius: 10px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
    <h3 style="margin-top: 0; font-size: 1.2rem; color: #0f172a;">Reset Password for <span id="resetModalUsername" style="color: var(--rmu-primary);"></span></h3>
    <form action="<?= APP_URL ?>/admin/users.php" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_user_password">
      <input type="hidden" name="target_id" id="resetModalTargetId" value="">

      <div class="form-group">
        <label class="form-label">New Password *</label>
        <input type="text" name="reset_password" placeholder="Enter new strong password" class="form-control" required minlength="6">
      </div>

      <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
        <button type="submit" class="btn-primary" style="flex: 1; justify-content: center;">Save New Password</button>
        <button type="button" onclick="closeResetModal()" class="btn-primary" style="background: #94a3b8; justify-content: center;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function showResetModal(id, username) {
  document.getElementById('resetModalTargetId').value = id;
  document.getElementById('resetModalUsername').innerText = '@' + username;
  document.getElementById('resetModal').style.display = 'flex';
}
function closeResetModal() {
  document.getElementById('resetModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>

