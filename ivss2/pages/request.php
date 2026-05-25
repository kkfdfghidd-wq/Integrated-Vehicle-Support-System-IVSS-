<?php
require_once __DIR__ . '/../includes/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/pages/login.php');

$db     = getDB();
$errors = [];

$preGarage = intval($_GET['garage_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $garageId     = intval($_POST['garage_id'] ?? 0) ?: null;
    $serviceType  = sanitize($_POST['service_type']  ?? '');
    $vehicleType  = sanitize($_POST['vehicle_type']  ?? '');
    $locationDesc = sanitize($_POST['location_desc'] ?? '');
    $notes        = sanitize($_POST['notes']         ?? '');
    $userId       = $_SESSION['user_id'];

    $validServices = ['towing','battery','tire','fuel','lockout','repair','other'];
    $validVehicles = ['sedan','suv','pickup','van','motorcycle','other'];

    if (!in_array($serviceType, $validServices)) $errors[] = 'Please select a service type.';
    if (!in_array($vehicleType, $validVehicles)) $errors[] = 'Please select your vehicle type.';
    if (empty($locationDesc))                    $errors[] = 'Location / landmark is required.';

    if (empty($errors)) {
        $db->prepare("INSERT INTO service_requests (user_id, garage_id, service_type, vehicle_type, location_desc, notes) VALUES (?,?,?,?,?,?)")
           ->execute([$userId, $garageId, $serviceType, $vehicleType, $locationDesc, $notes]);
        redirect(SITE_URL . '/pages/track.php?id=' . $db->lastInsertId());
    }
}

$garages = $db->query("SELECT garage_id, garage_name, location, rating, services FROM garages WHERE is_active=1 ORDER BY rating DESC")->fetchAll();

$serviceIcons = [
    'towing'  => ['🚛','Towing'],
    'battery' => ['🔋','Battery'],
    'tire'    => ['🛞','Tire Change'],
    'fuel'    => ['⛽','Fuel Delivery'],
    'lockout' => ['🔑','Lockout'],
    'repair'  => ['🔧','Repair'],
];

$pageTitle = 'Request Service';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-layout">

  <!-- ── Driver Sidebar ── -->
  <div class="dash-sidebar">
    <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:8px;">
      <div style="font-size:13px;color:rgba(255,255,255,0.4);">Logged in as</div>
      <div style="font-size:15px;font-weight:600;color:var(--white);margin-top:4px;">
        <?= htmlspecialchars($_SESSION['user_name']) ?>
      </div>
    </div>
    <div class="sidebar-nav">
      <div class="sidebar-section">Driver Panel</div>
      <a href="dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="request.php" class="active"><span class="nav-icon">🚨</span> New Request</a>
      <a href="my_requests.php"><span class="nav-icon">📋</span> My Requests</a>
      <a href="garages.php"><span class="nav-icon">🔧</span> Find Garages</a>
      <div class="sidebar-section">Account</div>
      <a href="profile.php"><span class="nav-icon">👤</span> Profile</a>
      <a href="logout.php"><span class="nav-icon">🚪</span> Logout</a>
    </div>
  </div>

  <!-- ── Main Content ── -->
  <div class="dash-content" style="display:flex;justify-content:center;">

    <div style="width:100%;max-width:700px;">

    <div class="dash-header" style="padding-left:0;padding-right:0;">
      <div>
        <div class="dash-title">Request Roadside Service 🚨</div>
        <div class="dash-sub">
          Your request will be visible to <strong>all available garages</strong>.
          Optionally choose a specific garage.
        </div>
      </div>
      <div style="display:flex;gap:8px;">
      <a href="map.php" class="btn btn-dark btn-sm">🗺️ Map View</a>
      <a href="garages.php" class="btn btn-dark btn-sm">🔧 Browse Garages</a>
      </div>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger">⚠️ <?= $e ?></div>
    <?php endforeach; ?>

    <div class="card" style="max-width:680px;margin:0 auto;">
      <form method="POST">

        <!-- Service Type -->
        <div class="form-group">
          <label>What do you need? <span style="color:var(--danger);">*</span></label>
          <div class="service-grid">
            <?php foreach ($serviceIcons as $key => [$icon,$label]): ?>
            <div class="service-btn <?= (($_POST['service_type']??'')===$key)?'active':'' ?>"
                 onclick="selectSvc(this,'<?= $key ?>')">
              <span class="svc-icon"><?= $icon ?></span>
              <span><?= $label ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="service_type" id="service_type"
                 value="<?= htmlspecialchars($_POST['service_type']??'') ?>">
        </div>

        <!-- Garage (optional) -->
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;">
            Preferred Garage
            <span style="background:rgba(26,158,138,0.1);color:var(--teal);
                         padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600;">Optional</span>
          </label>
          <select name="garage_id" class="form-control" id="garage_select">
            <option value="">🌐 Broadcast to ALL garages (recommended)</option>
            <?php foreach ($garages as $g): ?>
            <option value="<?= $g['garage_id'] ?>"
                    data-services="<?= htmlspecialchars($g['services'] ?? '') ?>"
              <?= ($preGarage==$g['garage_id'] || ($_POST['garage_id']??0)==$g['garage_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($g['garage_name']) ?> — <?= htmlspecialchars($g['location']) ?> ★<?= number_format($g['rating'],1) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <!-- No match notice -->
          <div id="no_garage_match" style="display:none;margin-top:8px;padding:10px 14px;
               background:rgba(212,168,67,0.08);border:1px solid rgba(212,168,67,0.3);
               border-radius:8px;font-size:13px;color:#a07820;">
            ⚠️ No garage in the network offers this service yet — your request will be broadcast to all garages.
          </div>
          <div class="form-hint">💡 "Broadcast to ALL" lets every active garage see and accept your request faster.</div>
        </div>

        <!-- Vehicle + Location -->
        <div class="form-row">
          <div class="form-group">
            <label>Vehicle Type <span style="color:var(--danger);">*</span></label>
            <select name="vehicle_type" class="form-control" required>
              <option value="">— Select —</option>
              <?php foreach (['sedan'=>'Sedan','suv'=>'SUV / 4x4','pickup'=>'Pickup Truck','van'=>'Van','motorcycle'=>'Motorcycle','other'=>'Other'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= (($_POST['vehicle_type']??'')===$v)?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Location / Landmark <span style="color:var(--danger);">*</span></label>
            <input type="text" name="location_desc" class="form-control"
                   placeholder="e.g. Near Muscat City Centre"
                   value="<?= htmlspecialchars($_POST['location_desc']??'') ?>" required>
          </div>
        </div>

        <!-- Notes -->
        <div class="form-group">
          <label>Additional Notes
            <span style="color:var(--muted);font-weight:400;">(optional)</span>
          </label>
          <textarea name="notes" class="form-control" rows="3"
                    placeholder="Describe the problem in detail..."><?= htmlspecialchars($_POST['notes']??'') ?></textarea>
        </div>

        <!-- Info banner -->
        <div style="background:rgba(26,158,138,0.07);border:1px solid rgba(26,158,138,0.2);
                    border-radius:8px;padding:14px 16px;margin-bottom:20px;
                    display:flex;gap:12px;align-items:flex-start;">
          <span style="font-size:20px;flex-shrink:0;">📢</span>
          <div style="font-size:13px;color:var(--text);line-height:1.6;">
            <strong>How it works:</strong> Your request is broadcast to all active garages.
            The first to accept gets assigned. You'll see real-time status updates on the tracking page.
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="font-size:16px;padding:14px;">
          🚀 Submit Service Request
        </button>
      </form>
    </div>

    </div><!-- /centered-wrapper -->
  </div>
</div>

<script>
function selectSvc(el, val) {
  document.querySelectorAll('.service-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('service_type').value = val;
  filterGarages(val);
}

function filterGarages(service) {
  var select  = document.getElementById('garage_select');
  var options = select.querySelectorAll('option');
  var matched = 0;

  options.forEach(function(opt) {
    if (!opt.value) {
      // "Broadcast to ALL" — always visible
      opt.style.display = '';
      return;
    }
    var services = (opt.getAttribute('data-services') || '').split(',').map(s => s.trim());
    if (!service || services.includes(service)) {
      opt.style.display = '';
      matched++;
    } else {
      opt.style.display = 'none';
      // If this hidden option was selected, reset to broadcast
      if (opt.selected) {
        select.value = '';
      }
    }
  });

  // Show/hide "no match" notice
  document.getElementById('no_garage_match').style.display = (matched === 0 && service) ? 'block' : 'none';

  // Update hint text
  var hint = select.nextElementSibling.nextElementSibling;
  if (hint && hint.classList.contains('form-hint')) {
    if (service && matched > 0) {
      hint.innerHTML = '✅ Showing <strong>' + matched + '</strong> garage(s) that offer this service. "Broadcast" is still the fastest option.';
    } else {
      hint.innerHTML = '💡 "Broadcast to ALL" lets every active garage see and accept your request faster.';
    }
  }
}

// On page load — if a service was pre-selected (POST back), filter immediately
document.addEventListener('DOMContentLoaded', function() {
  var preSelected = document.getElementById('service_type').value;
  if (preSelected) filterGarages(preSelected);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
