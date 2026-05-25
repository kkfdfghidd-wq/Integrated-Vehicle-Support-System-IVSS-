<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdminLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=admin');

$db      = getDB();
$adminId = $_SESSION['admin_id'];

$admin = $db->prepare("SELECT * FROM admins WHERE admin_id=?");
$admin->execute([$adminId]);
$admin = $admin->fetch();

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── Update Profile ── */
    if ($action === 'update_profile') {
        $name = sanitize($_POST['name'] ?? '');
        if (empty($name)) $errors[] = 'Name is required.';

        if (empty($errors)) {
            $db->prepare("UPDATE admins SET name=? WHERE admin_id=?")->execute([$name, $adminId]);
            $_SESSION['admin_name'] = $name;
            $success = 'Profile updated successfully.';
            $stmt = $db->prepare("SELECT * FROM admins WHERE admin_id=?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();
        }
    }

    /* ── Change Password ── */
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $admin['password'])) $errors[] = 'Current password is incorrect.';
        if (strlen($newPass) < 6)  $errors[] = 'New password must be at least 6 characters.';
        if ($newPass !== $confirm) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $db->prepare("UPDATE admins SET password=? WHERE admin_id=?")
               ->execute([password_hash($newPass, PASSWORD_BCRYPT), $adminId]);
            $success = 'Password changed successfully.';
        }
    }
}

// Platform stats for sidebar info
$totalUsers    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalGarages  = $db->query("SELECT COUNT(*) FROM garages WHERE is_active=1")->fetchColumn();
$totalRequests = $db->query("SELECT COUNT(*) FROM service_requests")->fetchColumn();
$totalRevenue  = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")->fetchColumn();

$pageTitle = 'Admin Profile';
include __DIR__ . '/admin_header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>

  <div class="dash-content">
    <div class="dash-header">
      <div>
        <div class="dash-title">Admin Profile ⚙️</div>
        <div class="dash-sub">Manage your administrator account and security settings.</div>
      </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger">⚠️ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:24px;align-items:start;">

      <!-- Left: Info Card -->
      <div style="display:flex;flex-direction:column;gap:20px;">
        <div class="card" style="text-align:center;">
          <!-- Avatar -->
          <div style="width:84px;height:84px;border-radius:50%;background:var(--navy);
                      color:var(--gold);font-size:30px;font-weight:700;
                      display:flex;align-items:center;justify-content:center;
                      margin:0 auto 16px;border:3px solid rgba(212,168,67,0.3);">
            <?= strtoupper(substr($admin['name'],0,1)) ?>
          </div>
          <div style="font-size:20px;font-weight:700;"><?= htmlspecialchars($admin['name']) ?></div>
          <div style="font-size:13px;color:var(--muted);margin-top:4px;">✉️ <?= htmlspecialchars($admin['email']) ?></div>
          <div style="margin-top:12px;">
            <span class="badge badge-accepted" style="background:rgba(212,168,67,0.15);color:#a07820;border:none;">
              ⚙️ System Administrator
            </span>
          </div>
          <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);
                      font-size:13px;color:var(--muted);">
            Account since <?= date('d M Y', strtotime($admin['created_at'])) ?>
          </div>
        </div>

        <!-- Platform Stats -->
        <div class="card">
          <div style="font-size:13px;font-weight:700;color:var(--muted);margin-bottom:16px;
                      text-transform:uppercase;letter-spacing:1px;">Platform Overview</div>
          <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ([
              ['👥','Total Users',    $totalUsers,   'var(--teal)'],
              ['🔧','Active Garages', $totalGarages, 'var(--gold)'],
              ['📋','All Requests',   $totalRequests,'var(--navy)'],
              ['💰','Revenue (OMR)',  number_format($totalRevenue,3),'var(--teal)'],
            ] as [$icon,$label,$value,$color]): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:10px 14px;background:var(--bg);border-radius:8px;">
              <div style="font-size:13px;color:var(--muted);"><?= $icon ?> <?= $label ?></div>
              <div style="font-size:16px;font-weight:700;color:<?= $color ?>;"><?= $value ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Quick Links -->
        <div class="card">
          <div style="font-size:13px;font-weight:700;color:var(--muted);margin-bottom:14px;
                      text-transform:uppercase;letter-spacing:1px;">Quick Links</div>
          <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ([
              ['dashboard.php','📊','Dashboard'],
              ['users.php',    '👥','Manage Users'],
              ['garages_admin.php',  '🔧','Manage Garages'],
              ['requests.php', '📋','Manage Requests'],
              ['payments.php', '💰','Payments'],
            ] as [$href,$icon,$label]): ?>
            <a href="<?= $href ?>"
               style="display:flex;align-items:center;gap:10px;padding:9px 14px;
                      border-radius:8px;text-decoration:none;color:var(--text);
                      background:var(--bg);font-size:13px;font-weight:600;
                      transition:background 0.15s;"
               onmouseover="this.style.background='rgba(26,158,138,0.08)'"
               onmouseout="this.style.background='var(--bg)'">
              <span><?= $icon ?></span><?= $label ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Right: Edit Forms -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Update Profile -->
        <div class="card">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;">✏️ Edit Profile</h3>
          <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
              <label>Full Name</label>
              <input type="text" name="name" class="form-control"
                     value="<?= htmlspecialchars($admin['name']) ?>" required>
            </div>
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" class="form-control"
                     value="<?= htmlspecialchars($admin['email']) ?>" disabled
                     style="background:var(--bg);cursor:not-allowed;">
              <div class="form-hint">Email cannot be changed from this panel.</div>
            </div>
            <div class="form-group">
              <label>Role</label>
              <input type="text" class="form-control" value="System Administrator" disabled
                     style="background:var(--bg);cursor:not-allowed;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
          </form>
        </div>

        <!-- Change Password -->
        <div class="card">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;">🔒 Change Password</h3>
          <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
              <label>Current Password</label>
              <input type="password" name="current_password" class="form-control"
                     placeholder="Enter current password" required>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" id="newPass" class="form-control"
                       placeholder="Min. 6 characters"
                       oninput="adminStrength(this.value)" required>
                <div style="height:4px;border-radius:4px;background:var(--border);overflow:hidden;margin-top:6px;">
                  <div id="adminStrFill" style="height:100%;border-radius:4px;transition:all 0.3s;width:0%;"></div>
                </div>
              </div>
              <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirmPass" class="form-control"
                       placeholder="Repeat new password"
                       oninput="adminMatch()" required>
                <div class="form-hint" id="adminMatchHint"></div>
              </div>
            </div>
            <div style="background:rgba(212,168,67,0.08);border:1px solid rgba(212,168,67,0.2);
                        border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:var(--muted);">
              ⚠️ As an administrator, use a strong, unique password. Avoid reusing passwords from other accounts.
            </div>
            <button type="submit" class="btn btn-dark btn-sm">Change Password</button>
          </form>
        </div>

        <!-- Session Info -->
        <div class="card">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;">🛡️ Session Info</h3>
          <div style="display:flex;flex-direction:column;gap:10px;font-size:13px;">
            <div style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--bg);border-radius:8px;">
              <span style="color:var(--muted);">Current Date & Time</span>
              <span style="font-weight:600;"><?= date('d M Y, H:i') ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--bg);border-radius:8px;">
              <span style="color:var(--muted);">IP Address</span>
              <span style="font-weight:600;"><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '—') ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--bg);border-radius:8px;">
              <span style="color:var(--muted);">Session Status</span>
              <span style="font-weight:600;color:var(--teal);">✅ Active</span>
            </div>
          </div>
          <a href="<?= SITE_URL ?>/pages/logout.php"
             style="display:inline-flex;align-items:center;gap:8px;margin-top:16px;
                    padding:9px 20px;border-radius:8px;text-decoration:none;
                    background:rgba(226,75,74,0.08);color:var(--danger);
                    font-size:13px;font-weight:600;border:1px solid rgba(226,75,74,0.2);"
             onclick="return confirm('Log out of admin panel?')">
            🚪 Log Out
          </a>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
function adminStrength(val) {
  const fill = document.getElementById('adminStrFill');
  let score = 0;
  if (val.length >= 6)           score++;
  if (val.length >= 10)          score++;
  if (/[A-Z]/.test(val))         score++;
  if (/[0-9]/.test(val))         score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const colors = ['','var(--danger)','var(--warning)','var(--teal)','var(--teal)','#06a35a'];
  const widths  = ['0%','25%','50%','70%','85%','100%'];
  fill.style.width      = widths[Math.min(score,5)];
  fill.style.background = colors[Math.min(score,5)];
}
function adminMatch() {
  const p = document.getElementById('newPass').value;
  const c = document.getElementById('confirmPass').value;
  const h = document.getElementById('adminMatchHint');
  if (!c) { h.textContent=''; return; }
  if (p===c) { h.textContent='✓ Match'; h.style.color='var(--teal)'; }
  else       { h.textContent='✗ No match'; h.style.color='var(--danger)'; }
}
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>
