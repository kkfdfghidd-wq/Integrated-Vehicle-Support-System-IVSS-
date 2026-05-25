<?php
// Counts for badges — safe if table doesn't exist yet
$_openComplaints = 0;
$_unsubCount     = 0;

try {
    $_sidebarDB      = getDB();
    $_openComplaints = (int) $_sidebarDB->query("SELECT COUNT(*) FROM complaints WHERE status='open'")->fetchColumn();
} catch (Exception $e) { $_openComplaints = 0; }

try {
    $_totalGarages = (int) getDB()->query("SELECT COUNT(*) FROM garages")->fetchColumn();
    $_subCount     = (int) getDB()->query("
        SELECT COUNT(DISTINCT garage_id) FROM garage_subscriptions
        WHERE status='active' AND end_date >= CURDATE()
    ")->fetchColumn();
    $_unsubCount = max(0, $_totalGarages - $_subCount);
} catch (Exception $e) { $_unsubCount = 0; }

$_currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="dash-sidebar">
  <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:8px;">
    <div style="font-size:12px;color:rgba(255,255,255,0.4);">Admin Panel</div>
    <div style="font-size:15px;font-weight:700;color:var(--gold);margin-top:4px;">
      <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>
    </div>
  </div>

  <div class="sidebar-nav">
    <div class="sidebar-section">Management</div>

    <a href="dashboard.php" <?= $_currentPage==='dashboard.php' ? 'class="active"' : '' ?>>
      <span class="nav-icon">📊</span> Dashboard
    </a>

    <a href="users.php" <?= $_currentPage==='users.php' ? 'class="active"' : '' ?>>
      <span class="nav-icon">👥</span> Users
    </a>

    <a href="garages_admin.php" <?= $_currentPage==='garages_admin.php' ? 'class="active"' : '' ?>>
      <span class="nav-icon">🔧</span> Garages
    </a>

    <a href="requests.php" <?= $_currentPage==='requests.php' ? 'class="active"' : '' ?>>
      <span class="nav-icon">📋</span> Requests
    </a>

    <a href="payments.php" <?= $_currentPage==='payments.php' ? 'class="active"' : '' ?>>
      <span class="nav-icon">💰</span> Payments
    </a>

    <a href="complaints.php" <?= $_currentPage==='complaints.php' ? 'class="active"' : '' ?>
       style="display:flex;align-items:center;justify-content:space-between;">
      <span><span class="nav-icon">⚠️</span> Complaints</span>
      <?php if ($_openComplaints > 0): ?>
      <span style="background:var(--danger);color:#fff;border-radius:10px;
                   padding:1px 8px;font-size:11px;font-weight:700;flex-shrink:0;">
        <?= $_openComplaints ?>
      </span>
      <?php endif; ?>
    </a>

    <a href="admin_subscriptions.php" <?= $_currentPage==='admin_subscriptions.php' ? 'class="active"' : '' ?>
       style="display:flex;align-items:center;justify-content:space-between;">
      <span><span class="nav-icon">⭐</span> Subscriptions</span>
      <?php if ($_unsubCount > 0): ?>
      <span style="background:rgba(212,168,67,0.85);color:#fff;border-radius:10px;
                   padding:1px 8px;font-size:11px;font-weight:700;flex-shrink:0;"
            title="<?= $_unsubCount ?> garages without subscription">
        <?= $_unsubCount ?>
      </span>
      <?php endif; ?>
    </a>

    <div class="sidebar-section">Account</div>

    <a href="profile_admin.php" <?= $_currentPage==='profile_admin.php' ? 'class="active"' : '' ?>>
      <span class="nav-icon">⚙️</span> Profile
    </a>

    <a href="<?= SITE_URL ?>/pages/logout.php">
      <span class="nav-icon">🚪</span> Logout
    </a>
  </div>
</div>
