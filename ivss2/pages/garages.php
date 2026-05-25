<?php
require_once __DIR__ . '/../includes/config.php';

$db = getDB();

// Search & filter
$search  = sanitize($_GET['search']  ?? '');
$service = sanitize($_GET['service'] ?? '');

$sql    = "SELECT g.*,
                  (SELECT COUNT(*) FROM feedback f WHERE f.garage_id = g.garage_id) AS review_count
           FROM garages g WHERE g.is_active = 1";
$params = [];

if ($search) {
    $sql .= " AND (garage_name LIKE ? OR location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($service) {
    $sql .= " AND FIND_IN_SET(?, services)";
    $params[] = $service;
}
$sql .= " ORDER BY rating DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$garages = $stmt->fetchAll();

$allServices = [
    'towing'  => '🚛 Towing',
    'battery' => '🔋 Battery',
    'tire'    => '🛞 Tire Change',
    'fuel'    => '⛽ Fuel Delivery',
    'lockout' => '🔑 Lockout',
    'repair'  => '🔧 Repair',
];

$serviceIcons = ['towing'=>'🚛','battery'=>'🔋','tire'=>'🛞','fuel'=>'⛽','lockout'=>'🔑','repair'=>'🔧'];

$pageTitle = 'Find Garages';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-layout">

  <!-- ── Driver Sidebar ── -->
  <div class="dash-sidebar">
    <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:8px;">
      <div style="font-size:13px;color:rgba(255,255,255,0.4);">Logged in as</div>
      <div style="font-size:15px;font-weight:600;color:var(--white);margin-top:4px;">
        <?= isLoggedIn() ? htmlspecialchars($_SESSION['user_name']) : 'Guest' ?>
      </div>
    </div>
    <div class="sidebar-nav">
      <div class="sidebar-section">Driver Panel</div>
      <a href="dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="request.php"><span class="nav-icon">🚨</span> New Request</a>
      <a href="my_requests.php"><span class="nav-icon">📋</span> My Requests</a>
      <a href="garages.php" class="active"><span class="nav-icon">🔧</span> Find Garages</a>
      <div class="sidebar-section">Account</div>
      <a href="profile.php"><span class="nav-icon">👤</span> Profile</a>
      <a href="logout.php"><span class="nav-icon">🚪</span> Logout</a>
    </div>
  </div>

  <!-- ── Main Content ── -->
  <div class="dash-content" style="padding:0;overflow:hidden;">

    <!-- Hero Search -->
    <div style="background:var(--navy);padding:36px 32px 48px;">
      <div class="hero-badge" style="margin-bottom:14px;">🗺️ Garage Network</div>
      <div class="section-title" style="color:var(--white);margin-bottom:6px;">Find a Certified Garage</div>
      <p style="color:rgba(255,255,255,0.6);margin-bottom:24px;">
        Browse <?= count($garages) ?>+ certified garages across Oman. Filter by service type or location.
      </p>
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control"
               placeholder="🔍 Search by name or location..."
               value="<?= htmlspecialchars($search) ?>"
               style="flex:1;min-width:200px;background:rgba(255,255,255,0.08);border-color:rgba(255,255,255,0.15);color:var(--white);">
        <select name="service" class="form-control"
                style="min-width:170px;background:rgba(255,255,255,0.08);border-color:rgba(255,255,255,0.15);color:var(--muted);">
          <option value="">All Services</option>
          <?php foreach ($allServices as $key => $label): ?>
          <option value="<?= $key ?>" <?= $service === $key ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search || $service): ?>
        <a href="garages.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Results -->
    <div style="padding:28px 32px;">

      <?php if (empty($garages)): ?>
      <div class="card" style="text-align:center;padding:64px 32px;">
        <div style="font-size:56px;margin-bottom:16px;">🔍</div>
        <div style="font-size:18px;font-weight:700;margin-bottom:8px;">No garages found</div>
        <p style="color:var(--muted);margin-bottom:24px;">Try a different search term or service filter.</p>
        <a href="garages.php" class="btn btn-teal">View All Garages</a>
      </div>

      <?php else: ?>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px;">
        <div style="font-size:14px;color:var(--muted);">
          Showing <strong style="color:var(--text);"><?= count($garages) ?></strong>
          garage<?= count($garages) !== 1 ? 's' : '' ?>
          <?= $search ? " matching \"<strong>" . htmlspecialchars($search) . "</strong>\"" : '' ?>
        </div>
        <div style="font-size:13px;color:var(--muted);">★ Sorted by rating</div>
      </div>

      <div style="display:flex;flex-direction:column;gap:14px;">
        <?php foreach ($garages as $g):
          $services = array_filter(array_map('trim', explode(',', $g['services'] ?? '')));
        ?>
        <div class="card" style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">

          <!-- Avatar -->
          <div style="width:60px;height:60px;border-radius:14px;background:var(--navy);
                      display:flex;align-items:center;justify-content:center;
                      font-size:26px;flex-shrink:0;">🔧</div>

          <!-- Info -->
          <div style="flex:1;min-width:200px;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
              <span style="font-size:17px;font-weight:700;"><?= htmlspecialchars($g['garage_name']) ?></span>
              <span class="badge badge-accepted">Active</span>
            </div>
            <div style="font-size:13px;color:var(--muted);margin-bottom:8px;">
              📍 <?= htmlspecialchars($g['location']) ?>
              &nbsp;·&nbsp;
              📞 <?= htmlspecialchars($g['phone']) ?>
            </div>
            <!-- Services -->
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
              <?php foreach ($services as $s): ?>
              <span style="background:rgba(26,158,138,0.1);color:var(--teal);
                           padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">
                <?= ($serviceIcons[$s] ?? '🔩') . ' ' . ucfirst($s) ?>
              </span>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Rating + CTA -->
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:10px;flex-shrink:0;">
            <div style="text-align:center;">
              <?php
                $avgRating = (float)$g['rating'];
                $fullStars = floor($avgRating);
                $halfStar  = ($avgRating - $fullStars) >= 0.5;
                $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
              ?>
              <div style="display:flex;gap:2px;justify-content:center;font-size:18px;">
                <?php for($s=0;$s<$fullStars;$s++): ?><span style="color:#f5a623;">★</span><?php endfor; ?>
                <?php if($halfStar): ?><span style="color:#f5a623;">½</span><?php endif; ?>
                <?php for($s=0;$s<$emptyStars;$s++): ?><span style="color:#ddd;">★</span><?php endfor; ?>
              </div>
              <div style="font-size:15px;font-weight:800;color:var(--gold);margin-top:2px;"><?= number_format($g['rating'],1) ?></div>
              <div style="font-size:11px;color:var(--muted);"><?= $g['review_count'] ?> review<?= $g['review_count']!=1?'s':'' ?></div>
            </div>
            <a href="request.php?garage_id=<?= $g['garage_id'] ?>" class="btn btn-primary btn-sm">
              Request Service
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- CTA for garages -->
      <div class="card" style="text-align:center;padding:36px;margin-top:28px;background:var(--navy);color:var(--white);">
        <div style="font-size:36px;margin-bottom:14px;">🔧</div>
        <div style="font-size:18px;font-weight:700;margin-bottom:8px;color:var(--white);">Own a Garage?</div>
        <p style="color:rgba(255,255,255,0.6);margin-bottom:20px;">
          Join the IVSS network and get discovered by thousands of drivers across Oman.
        </p>
        <a href="garage_register.php" class="btn btn-primary">Register Your Garage →</a>
      </div>

    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
