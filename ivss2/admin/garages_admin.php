<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdminLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=admin');

$db = getDB();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $gid    = intval($_POST['garage_id'] ?? 0);

    if ($action === 'toggle_active' && $gid) {
        $cur = $db->prepare("SELECT is_active FROM garages WHERE garage_id=?");
        $cur->execute([$gid]);
        $row = $cur->fetch();
        if ($row) {
            $newVal = $row['is_active'] ? 0 : 1;
            $db->prepare("UPDATE garages SET is_active=? WHERE garage_id=?")->execute([$newVal, $gid]);
            $msg = $newVal ? 'Garage activated.' : 'Garage deactivated.';
        }
    }

    if ($action === 'delete' && $gid) {
        $db->prepare("DELETE FROM garages WHERE garage_id=?")->execute([$gid]);
        $msg = 'Garage deleted successfully.';
    }

    if ($action === 'reset_password' && $gid) {
        $newPass = password_hash('password123', PASSWORD_BCRYPT);
        $db->prepare("UPDATE garages SET password=? WHERE garage_id=?")->execute([$newPass, $gid]);
        $msg = 'Password reset to: password123';
    }

    if ($action === 'update_rating' && $gid) {
        $rating = min(5, max(0, floatval($_POST['rating'] ?? 0)));
        $db->prepare("UPDATE garages SET rating=? WHERE garage_id=?")->execute([$rating, $gid]);
        $msg = 'Rating updated.';
    }
}

// Filters
$search  = sanitize($_GET['search']  ?? '');
$service = sanitize($_GET['service'] ?? '');
$filter  = sanitize($_GET['filter']  ?? '');

$sql    = "SELECT g.*,
                  COUNT(DISTINCT r.request_id) AS total_requests,
                  SUM(r.status='completed')     AS completed,
                  COALESCE(SUM(p.amount),0)     AS revenue,
                  (SELECT COUNT(*) FROM feedback f WHERE f.garage_id = g.garage_id) AS review_count
           FROM garages g
           LEFT JOIN service_requests r ON r.garage_id = g.garage_id
           LEFT JOIN payments p         ON p.request_id = r.request_id AND p.status='paid'";
$params = [];
$where  = [];

if ($search) {
    $where[]  = "(g.garage_name LIKE ? OR g.location LIKE ? OR g.email LIKE ? OR g.owner_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($service) {
    $where[]  = "FIND_IN_SET(?, g.services)";
    $params[] = $service;
}
if ($filter === 'active')   $where[] = "g.is_active=1";
if ($filter === 'inactive') $where[] = "g.is_active=0";

if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " GROUP BY g.garage_id ORDER BY g.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$garages = $stmt->fetchAll();

$totalGarages  = $db->query("SELECT COUNT(*) FROM garages")->fetchColumn();
$activeGarages = $db->query("SELECT COUNT(*) FROM garages WHERE is_active=1")->fetchColumn();
$totalRevenue  = $db->query("SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.status='paid'")->fetchColumn();

$allServices = ['towing'=>'🚛 Towing','battery'=>'🔋 Battery','tire'=>'🛞 Tire','fuel'=>'⛽ Fuel','lockout'=>'🔑 Lockout','repair'=>'🔧 Repair'];

$pageTitle = 'Manage Garages';
include __DIR__ . '/admin_header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>

  <div class="dash-content">
    <div class="dash-header">
      <div>
        <div class="dash-title">Manage Garages 🔧</div>
        <div class="dash-sub">View and control all registered garage partners.</div>
      </div>
      <a href="<?= SITE_URL ?>/pages/garage_register.php" class="btn btn-primary" target="_blank">+ Add Garage</a>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Metrics -->
    <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
      <div class="metric-card"><div class="metric-icon">🔧</div><div class="metric-label">Total Garages</div><div class="metric-value"><?= $totalGarages ?></div></div>
      <div class="metric-card"><div class="metric-icon">✅</div><div class="metric-label">Active</div><div class="metric-value" style="color:var(--teal);"><?= $activeGarages ?></div></div>
      <div class="metric-card"><div class="metric-icon">💰</div><div class="metric-label">Total Revenue (OMR)</div><div class="metric-value" style="color:var(--gold);"><?= number_format($totalRevenue,3) ?></div></div>
    </div>

    <!-- Search & Filter -->
    <div class="card" style="margin-bottom:20px;">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:200px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">Search</label>
          <input type="text" name="search" class="form-control" placeholder="Name, email, location, owner..."
                 value="<?= htmlspecialchars($search) ?>" style="margin-top:4px;">
        </div>
        <div class="form-group" style="min-width:170px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">Service</label>
          <select name="service" class="form-control" style="margin-top:4px;">
            <option value="">All Services</option>
            <?php foreach ($allServices as $k => $l): ?>
            <option value="<?= $k ?>" <?= $service===$k?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="min-width:140px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">Status</label>
          <select name="filter" class="form-control" style="margin-top:4px;">
            <option value="">All</option>
            <option value="active"   <?= $filter==='active'  ?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $filter==='inactive'?'selected':'' ?>>Inactive</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if ($search || $service || $filter): ?>
        <a href="garages.php" class="btn btn-dark btn-sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Garages List -->
    <?php if (empty($garages)): ?>
    <div class="card" style="text-align:center;padding:64px;">
      <div style="font-size:48px;margin-bottom:12px;">🔧</div>
      <p style="color:var(--muted);">No garages found matching your criteria.</p>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:16px;">
      <?php foreach ($garages as $g):
        $services = array_filter(array_map('trim', explode(',', $g['services'] ?? '')));
        $svcIcons = ['towing'=>'🚛','battery'=>'🔋','tire'=>'🛞','fuel'=>'⛽','lockout'=>'🔑','repair'=>'🔧'];
      ?>
      <div class="card">
        <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">

          <!-- Avatar -->
          <div style="width:56px;height:56px;border-radius:12px;background:<?= $g['is_active']?'var(--navy)':'rgba(0,0,0,0.05)' ?>;
                      display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0;">🔧</div>

          <!-- Info -->
          <div style="flex:1;min-width:220px;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
              <span style="font-size:17px;font-weight:700;"><?= htmlspecialchars($g['garage_name']) ?></span>
              <span class="badge <?= $g['is_active']?'badge-accepted':'badge-cancelled' ?>">
                <?= $g['is_active']?'Active':'Inactive' ?>
              </span>
              <span style="background:rgba(212,168,67,0.15);color:#a07820;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                <?php
                  $gr = (float)$g['rating'];
                  for($s=1;$s<=5;$s++) echo '<span style="color:'.($s<=$gr?'#f5a623':'#ddd').';">★</span>';
                ?>
                <span style="margin-left:2px;"><?= number_format($g['rating'],1) ?></span>
                <span style="color:rgba(0,0,0,0.3);font-weight:400;">(<?= $g['review_count'] ?>)</span>
              </span>
            </div>
            <div style="font-size:13px;color:var(--muted);margin-bottom:4px;">
              👤 <?= htmlspecialchars($g['owner_name']) ?>
              &nbsp;·&nbsp; ✉️ <?= htmlspecialchars($g['email']) ?>
              &nbsp;·&nbsp; 📞 <?= htmlspecialchars($g['phone']) ?>
            </div>
            <div style="font-size:13px;color:var(--muted);margin-bottom:8px;">
              📍 <?= htmlspecialchars($g['location']) ?>
              &nbsp;·&nbsp; 📋 <?= $g['total_requests'] ?> requests
              &nbsp;·&nbsp; ✅ <?= $g['completed'] ?> completed
              &nbsp;·&nbsp; 💰 <?= number_format($g['revenue'],3) ?> OMR
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <?php foreach ($services as $s): ?>
              <span style="background:rgba(26,158,138,0.08);color:var(--teal);padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600;">
                <?= ($svcIcons[$s]??'🔩').' '.ucfirst($s) ?>
              </span>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Actions -->
          <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0;min-width:160px;">
            <!-- Toggle -->
            <form method="POST">
              <input type="hidden" name="action"    value="toggle_active">
              <input type="hidden" name="garage_id" value="<?= $g['garage_id'] ?>">
              <button class="btn btn-sm" style="width:100%;background:<?= $g['is_active']?'rgba(226,75,74,0.08)':'rgba(26,158,138,0.08)' ?>;color:<?= $g['is_active']?'var(--danger)':'var(--teal)' ?>;border:1px solid <?= $g['is_active']?'var(--danger)':'var(--teal)' ?>;">
                <?= $g['is_active']?'🚫 Deactivate':'✅ Activate' ?>
              </button>
            </form>

            <!-- Update Rating -->
            <form method="POST" style="display:flex;gap:6px;">
              <input type="hidden" name="action"    value="update_rating">
              <input type="hidden" name="garage_id" value="<?= $g['garage_id'] ?>">
              <input type="number" name="rating" step="0.1" min="0" max="5"
                     value="<?= number_format($g['rating'],1) ?>"
                     class="form-control" style="padding:5px 8px;font-size:12px;width:70px;">
              <button class="btn btn-dark btn-sm" style="white-space:nowrap;">★ Set</button>
            </form>

            <!-- Reset Password -->
            <form method="POST">
              <input type="hidden" name="action"    value="reset_password">
              <input type="hidden" name="garage_id" value="<?= $g['garage_id'] ?>">
              <button class="btn btn-dark btn-sm" style="width:100%;"
                      onclick="return confirm('Reset password for this garage?')">🔑 Reset Pass</button>
            </form>

            <!-- Delete -->
            <form method="POST">
              <input type="hidden" name="action"    value="delete">
              <input type="hidden" name="garage_id" value="<?= $g['garage_id'] ?>">
              <button class="btn btn-sm" style="width:100%;background:rgba(226,75,74,0.08);color:var(--danger);border:1px solid var(--danger);"
                      onclick="return confirm('Delete this garage permanently?')">🗑️ Delete</button>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>
