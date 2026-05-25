<?php
require_once __DIR__ . '/../includes/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/pages/login.php');

$db     = getDB();
$userId = $_SESSION['user_id'];

$totalReq  = $db->prepare("SELECT COUNT(*) FROM service_requests WHERE user_id=?"); $totalReq->execute([$userId]);
$activeReq = $db->prepare("SELECT COUNT(*) FROM service_requests WHERE user_id=? AND status IN('pending','accepted','in_progress')"); $activeReq->execute([$userId]);
$completed = $db->prepare("SELECT COUNT(*) FROM service_requests WHERE user_id=? AND status='completed'"); $completed->execute([$userId]);
$spent     = $db->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN service_requests r ON p.request_id=r.request_id WHERE r.user_id=? AND p.status='paid'"); $spent->execute([$userId]);

// LEFT JOIN garages — garage_id may be NULL for broadcast requests
$requests = $db->prepare("
    SELECT r.*, g.garage_name, g.location AS garage_location
    FROM   service_requests r
    LEFT   JOIN garages g ON g.garage_id = r.garage_id
    WHERE  r.user_id = ?
    ORDER  BY r.created_at DESC
    LIMIT  10
");
$requests->execute([$userId]);
$requests = $requests->fetchAll();

$pageTitle = 'My Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar">
    <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:8px;">
      <div style="font-size:13px;color:rgba(255,255,255,0.4);">Logged in as</div>
      <div style="font-size:15px;font-weight:600;color:var(--white);margin-top:4px;"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
    </div>
    <div class="sidebar-nav">
      <div class="sidebar-section">Driver Panel</div>
      <a href="dashboard.php" class="active"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="request.php"><span class="nav-icon">🚨</span> New Request</a>
      <a href="my_requests.php"><span class="nav-icon">📋</span> My Requests</a>
      <a href="garages.php"><span class="nav-icon">🔧</span> Find Garages</a>
      <div class="sidebar-section">Account</div>
      <a href="profile.php"><span class="nav-icon">👤</span> Profile</a>
      <a href="logout.php"><span class="nav-icon">🚪</span> Logout</a>
    </div>
  </div>

  <div class="dash-content">
    <div class="dash-header">
      <div>
        <div class="dash-title">Welcome back, <?= htmlspecialchars(explode(' ',$_SESSION['user_name'])[0]) ?>! 👋</div>
        <div class="dash-sub">Overview of your roadside assistance activity.</div>
      </div>
      <a href="request.php" class="btn btn-primary">+ Request Help</a>
    </div>

    <div class="metric-grid">
      <div class="metric-card"><div class="metric-icon">📋</div><div class="metric-label">Total Requests</div><div class="metric-value"><?= $totalReq->fetchColumn() ?></div></div>
      <div class="metric-card"><div class="metric-icon">🔄</div><div class="metric-label">Active</div><div class="metric-value" style="color:var(--warning);"><?= $activeReq->fetchColumn() ?></div></div>
      <div class="metric-card"><div class="metric-icon">✅</div><div class="metric-label">Completed</div><div class="metric-value" style="color:var(--success);"><?= $completed->fetchColumn() ?></div></div>
      <div class="metric-card"><div class="metric-icon">💰</div><div class="metric-label">Total Spent</div><div class="metric-value"><?= number_format($spent->fetchColumn(),3) ?> <span style="font-size:15px;color:var(--muted);">OMR</span></div></div>
    </div>

    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <h3 style="font-size:17px;font-weight:700;">Recent Requests</h3>
        <a href="request.php" class="btn btn-teal btn-sm">+ New</a>
      </div>

      <?php if (empty($requests)): ?>
      <div style="text-align:center;padding:48px;color:var(--muted);">
        <div style="font-size:48px;margin-bottom:16px;">🚗</div>
        <p style="font-size:16px;font-weight:600;margin-bottom:8px;">No requests yet</p>
        <a href="request.php" class="btn btn-primary">Request Help Now</a>
      </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>#</th><th>Service</th><th>Garage</th><th>Location</th><th>Status</th><th>Date</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $r): ?>
            <tr>
              <td style="font-weight:600;color:var(--muted);">IVSS-<?= str_pad($r['request_id'],4,'0',STR_PAD_LEFT) ?></td>
              <td><?= ucfirst($r['service_type']) ?></td>
              <td><?= $r['garage_name'] ? htmlspecialchars($r['garage_name']) : '<span style="color:var(--muted);font-size:12px;">📢 Waiting...</span>' ?></td>
              <td style="color:var(--muted);font-size:13px;"><?= htmlspecialchars(substr($r['location_desc'],0,25)) ?>...</td>
              <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
              <td style="font-size:13px;color:var(--muted);"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
              <td><a href="track.php?id=<?= $r['request_id'] ?>" class="btn btn-dark btn-sm">Track</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;">
      <a href="garages.php" class="card card-hover" style="text-decoration:none;display:block;">
        <div style="font-size:32px;margin-bottom:12px;">🔍</div>
        <div style="font-size:15px;font-weight:700;margin-bottom:4px;">Browse Garages</div>
        <div style="font-size:13px;color:var(--muted);">Find certified garages near you</div>
      </a>
      <a href="request.php" class="card card-hover" style="text-decoration:none;display:block;border-color:var(--teal);">
        <div style="font-size:32px;margin-bottom:12px;">🚨</div>
        <div style="font-size:15px;font-weight:700;margin-bottom:4px;">Emergency Request</div>
        <div style="font-size:13px;color:var(--muted);">Get immediate roadside help now</div>
      </a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
