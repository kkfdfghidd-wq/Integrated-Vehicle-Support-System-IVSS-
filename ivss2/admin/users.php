<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdminLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=admin');

$db = getDB();

// Handle actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = intval($_POST['user_id'] ?? 0);

    if ($action === 'toggle_active' && $uid) {
        $cur = $db->prepare("SELECT is_active FROM users WHERE user_id=?");
        $cur->execute([$uid]);
        $row = $cur->fetch();
        if ($row) {
            $newVal = $row['is_active'] ? 0 : 1;
            $db->prepare("UPDATE users SET is_active=? WHERE user_id=?")->execute([$newVal, $uid]);
            $msg = $newVal ? 'User activated.' : 'User deactivated.';
        }
    }

    if ($action === 'delete' && $uid) {
        $db->prepare("DELETE FROM users WHERE user_id=?")->execute([$uid]);
        $msg = 'User deleted successfully.';
    }

    if ($action === 'reset_password' && $uid) {
        $newPass = password_hash('password123', PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET password=? WHERE user_id=?")->execute([$newPass, $uid]);
        $msg = 'Password reset to: password123';
    }
}

// Search & filter
$search = sanitize($_GET['search'] ?? '');
$filter = sanitize($_GET['filter'] ?? '');

$sql    = "SELECT u.*,
                  COUNT(r.request_id)                           AS total_requests,
                  SUM(r.status = 'completed')                   AS completed_req,
                  COALESCE(SUM(p.amount),0)                     AS total_spent
           FROM users u
           LEFT JOIN service_requests r ON r.user_id = u.user_id
           LEFT JOIN payments p         ON p.user_id = u.user_id AND p.status = 'paid'";
$params = [];
$where  = [];

if ($search) {
    $where[]  = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter === 'active')   $where[] = "u.is_active = 1";
if ($filter === 'inactive') $where[] = "u.is_active = 0";

if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " GROUP BY u.user_id ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Totals
$totalUsers   = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers  = $db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$newThisWeek  = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();

$pageTitle = 'Manage Users';
include __DIR__ . '/admin_header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>

  <div class="dash-content">
    <div class="dash-header">
      <div>
        <div class="dash-title">Manage Users 👥</div>
        <div class="dash-sub">View, manage and moderate registered drivers.</div>
      </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Metrics -->
    <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
      <div class="metric-card"><div class="metric-icon">👥</div><div class="metric-label">Total Users</div><div class="metric-value"><?= $totalUsers ?></div></div>
      <div class="metric-card"><div class="metric-icon">✅</div><div class="metric-label">Active</div><div class="metric-value" style="color:var(--teal);"><?= $activeUsers ?></div></div>
      <div class="metric-card"><div class="metric-icon">🆕</div><div class="metric-label">New This Week</div><div class="metric-value" style="color:var(--gold);"><?= $newThisWeek ?></div></div>
    </div>

    <!-- Search & Filter -->
    <div class="card" style="margin-bottom:20px;">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:200px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">Search</label>
          <input type="text" name="search" class="form-control" placeholder="Name, email, or phone..."
                 value="<?= htmlspecialchars($search) ?>" style="margin-top:4px;">
        </div>
        <div class="form-group" style="min-width:160px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">Status</label>
          <select name="filter" class="form-control" style="margin-top:4px;">
            <option value="">All Users</option>
            <option value="active"   <?= $filter==='active'   ?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $filter==='inactive' ?'selected':'' ?>>Inactive</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if ($search || $filter): ?>
        <a href="users.php" class="btn btn-dark btn-sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Table -->
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="font-size:16px;font-weight:700;">
          <?= count($users) ?> user<?= count($users)!==1?'s':'' ?> found
        </h3>
      </div>

      <?php if (empty($users)): ?>
      <div style="text-align:center;padding:56px;color:var(--muted);">
        <div style="font-size:48px;margin-bottom:12px;">👤</div>
        <p>No users found matching your criteria.</p>
      </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>User</th>
              <th>Phone</th>
              <th>Requests</th>
              <th>Spent (OMR)</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td style="color:var(--muted);font-weight:600;">#<?= $u['user_id'] ?></td>
              <td>
                <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($u['full_name']) ?></div>
                <div style="font-size:12px;color:var(--muted);">✉️ <?= htmlspecialchars($u['email']) ?></div>
              </td>
              <td style="font-size:13px;">📞 <?= htmlspecialchars($u['phone']) ?></td>
              <td>
                <span style="font-weight:700;color:var(--navy);"><?= $u['total_requests'] ?></span>
                <span style="font-size:11px;color:var(--muted);"> / <?= $u['completed_req'] ?> done</span>
              </td>
              <td style="font-weight:600;color:var(--teal);"><?= number_format($u['total_spent'],3) ?></td>
              <td>
                <span class="badge <?= $u['is_active'] ? 'badge-accepted' : 'badge-cancelled' ?>">
                  <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td style="font-size:13px;color:var(--muted);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                  <!-- Toggle Active -->
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action"  value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                    <button type="submit" class="btn btn-sm"
                            style="background:<?= $u['is_active']?'rgba(226,75,74,0.1)':'rgba(26,158,138,0.1)' ?>;
                                   color:<?= $u['is_active']?'var(--danger)':'var(--teal)' ?>;border:none;padding:5px 10px;">
                      <?= $u['is_active'] ? '🚫 Deactivate' : '✅ Activate' ?>
                    </button>
                  </form>
                  <!-- Reset Password -->
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action"  value="reset_password">
                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                    <button type="submit" class="btn btn-dark btn-sm"
                            onclick="return confirm('Reset password for <?= htmlspecialchars(addslashes($u['full_name'])) ?>?')">
                      🔑 Reset
                    </button>
                  </form>
                  <!-- Delete -->
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action"  value="delete">
                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                    <button type="submit" class="btn btn-sm"
                            style="background:rgba(226,75,74,0.1);color:var(--danger);border:none;padding:5px 10px;"
                            onclick="return confirm('Delete this user permanently? This cannot be undone.')">
                      🗑️ Delete
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>
