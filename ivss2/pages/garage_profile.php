<?php
require_once __DIR__ . '/../includes/config.php';
if (!isGarageLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=garage');

$db       = getDB();
$garageId = $_SESSION['garage_id'];

$garage = $db->prepare("SELECT * FROM garages WHERE garage_id = ?");
$garage->execute([$garageId]);
$garage = $garage->fetch();

// ── Subscription check ──
$hasSubscription = isSubscriptionActive($garageId);
$subStatus       = getSubscriptionStatus($garageId);

$errors  = [];
$success = '';

// ── Google Maps URL resolver (AJAX) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve_gmaps') {
    header('Content-Type: application/json');

    $rawUrl = trim($_POST['gmaps_url'] ?? '');
    if (empty($rawUrl)) {
        echo json_encode(['error' => 'No link was sent.']);
        exit;
    }

    // Basic sanity check — must look like a URL
    if (!filter_var($rawUrl, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => 'The link is invalid. Make sure it is copied completely.']);
        exit;
    }

    // Follow redirects with cURL to get the final expanded Google Maps URL
    $ch = curl_init($rawUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; IVSS/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_NOBODY         => false,   // we need headers for CURLINFO_EFFECTIVE_URL
    ]);
    curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || empty($finalUrl)) {
        echo json_encode(['error' => 'Unable to reach the link. Check your internet connection.']);
        exit;
    }

    $lat = null;
    $lng = null;

    // Pattern 1: @LAT,LNG,zoom  →  most common in standard Google Maps URLs
    if (preg_match('/@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/', $finalUrl, $m)) {
        $lat = $m[1];
        $lng = $m[2];
    }
    // Pattern 2: !3dLAT!4dLNG  →  used in place/business links
    elseif (preg_match('/!3d(-?\d{1,3}\.\d+)!4d(-?\d{1,3}\.\d+)/', $finalUrl, $m)) {
        $lat = $m[1];
        $lng = $m[2];
    }
    // Pattern 3: q=LAT,LNG  →  "Search" links
    elseif (preg_match('/[?&]q=(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/', $finalUrl, $m)) {
        $lat = $m[1];
        $lng = $m[2];
    }
    // Pattern 4: ll=LAT,LNG  →  older style
    elseif (preg_match('/[?&]ll=(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/', $finalUrl, $m)) {
        $lat = $m[1];
        $lng = $m[2];
    }

    if ($lat !== null && $lng !== null) {
        // Validate coordinate ranges
        $latF = (float)$lat;
        $lngF = (float)$lng;
        if ($latF >= -90 && $latF <= 90 && $lngF >= -180 && $lngF <= 180) {
            echo json_encode(['lat' => $latF, 'lng' => $lngF]);
        } else {
            echo json_encode(['error' => 'The extracted coordinates are out of the valid range.']);
        }
    } else {
        echo json_encode(['error' => 'Failed to extract coordinates. Try copying the link from the "Share" button in Google Maps.']);
    }
    exit;
}
// ────────────────────────────────────────────────────────────────────────────

$allServices = [
  'towing'  => ['🚛', 'Towing'],
  'battery' => ['🔋', 'Battery'],
  'tire'    => ['🛞', 'Tire Change'],
  'fuel'    => ['⛽', 'Fuel Delivery'],
  'lockout' => ['🔑', 'Lockout'],
  'repair'  => ['🔧', 'Repair'],
];

$currentServices = array_filter(array_map('trim', explode(',', $garage['services'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  /* ── Update Profile ── */
  if ($action === 'update_profile') {
    $ownerName  = sanitize($_POST['owner_name']  ?? '');
    $garageName = sanitize($_POST['garage_name'] ?? '');
    $phone      = $_POST['phone'] ?? '';
    $location   = sanitize($_POST['location']    ?? '');
    $services   = $_POST['services'] ?? [];
    $latRaw     = trim($_POST['latitude']  ?? '');
    $lngRaw     = trim($_POST['longitude'] ?? '');
    $latitude   = $latRaw  !== '' ? floatval($latRaw)  : null;
    $longitude  = $lngRaw  !== '' ? floatval($lngRaw)  : null;

    if (empty($ownerName))  $errors[] = 'Owner name is required.';
    if (empty($garageName)) $errors[] = 'Garage name is required.';
    if (empty($phone))      $errors[] = 'Phone number is required.';
    elseif (strlen($phone) !== 8)           $errors[] = 'Phone number must be exactly 8 digits.';
    elseif (!preg_match('/^[79]/', $phone)) $errors[] = 'Phone number must start with 7 or 9.'; // ✅ Fixed regex
    if (empty($location))   $errors[] = 'Location description is required.';
    if (empty($services))   $errors[] = 'Select at least one service.';

    // Validate coordinates if provided
    if ($latitude !== null && ($latitude < -90  || $latitude > 90))   $errors[] = 'Invalid latitude value.';
    if ($longitude !== null && ($longitude < -180 || $longitude > 180)) $errors[] = 'Invalid longitude value.';

    // Phone uniqueness check
    if (empty($errors) && !empty($phone)) {
      $phoneCheck = $db->prepare(
        "SELECT garage_id FROM garages WHERE phone = ? AND garage_id != ? LIMIT 1"
      );
      $phoneCheck->execute([$phone, $garageId]);
      if ($phoneCheck->fetch()) {
        $errors[] = 'This phone number is already registered to another garage.';
      }
    }

    if (empty($errors)) {
      $svcStr = implode(',', array_map('sanitize', $services));
      // ✅ Fix #6: save phone as string (not int) to preserve leading zeros
      // ✅ New: save latitude & longitude to DB
      $db->prepare(
        "UPDATE garages SET owner_name=?, garage_name=?, phone=?, location=?, services=?,
         latitude=?, longitude=? WHERE garage_id=?"
      )->execute([$ownerName, $garageName, $phone, $location, $svcStr,
                  $latitude, $longitude, $garageId]);

      $_SESSION['garage_name'] = $garageName;
      $success = $latitude !== null
        ? 'Profile updated! Your garage will now appear on the map. ✅'
        : 'Profile updated. Set map coordinates to appear on the public map. ✅';

      // Refresh from DB
      $stmt = $db->prepare("SELECT * FROM garages WHERE garage_id=?");
      $stmt->execute([$garageId]);
      $garage = $stmt->fetch();
      $currentServices = array_filter(array_map('trim', explode(',', $garage['services'])));
    }
  }

  /* ── Change Password ── */
  if ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $garage['password'])) $errors[] = 'Current password is incorrect.';
    if (strlen($newPass) < 6)  $errors[] = 'New password must be at least 6 characters.';
    if ($newPass !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
      $db->prepare("UPDATE garages SET password=? WHERE garage_id=?")
         ->execute([password_hash($newPass, PASSWORD_BCRYPT), $garageId]);
      $success = 'Password changed successfully. ✅';
    }
  }
}

// ── Open complaints count (badge) ──
$openComplaints = $db->prepare("SELECT COUNT(*) FROM complaints WHERE garage_id=? AND status='open'");
$openComplaints->execute([$garageId]);
$openComplaints = $openComplaints->fetchColumn();

// Current coordinates for map init
$curLat     = $garage['latitude']  ?? null;
$curLng     = $garage['longitude'] ?? null;
$hasLocation = ($curLat !== null && $curLng !== null);

$pageTitle = 'Garage Settings';
include __DIR__ . '/../includes/header.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<style>
/* ── Map picker ── */
#locationPickerMap {
  height: 260px;
  border-radius: 10px;
  border: 2px solid var(--border);
  margin-top: 10px;
  z-index: 0;
  transition: border-color .25s;
}
#locationPickerMap.has-location { border-color: var(--teal); }

.coord-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 10px;
}
.coord-wrap label {
  font-size: 12px;
  color: var(--muted);
  font-weight: 600;
  display: block;
  margin-bottom: 4px;
}
.coord-wrap input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 13px;
  font-family: monospace;
  background: var(--bg);
  box-sizing: border-box;
  transition: border-color .15s;
}
.coord-wrap input:focus { outline: none; border-color: var(--teal); }

.map-hint {
  font-size: 12px;
  color: var(--muted);
  margin-top: 8px;
  padding: 9px 13px;
  background: rgba(26,158,138,.06);
  border-left: 3px solid var(--teal);
  border-radius: 6px;
  line-height: 1.6;
}
.loc-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 20px;
}
.loc-badge.set    { background: rgba(26,158,138,.12); color: var(--teal); }
.loc-badge.notset { background: rgba(226,75,74,.08);  color: var(--danger); }

.btn-clear-loc {
  background: none;
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 3px 11px;
  font-size: 12px;
  color: var(--muted);
  cursor: pointer;
  transition: all .15s;
}
.btn-clear-loc:hover { border-color: var(--danger); color: var(--danger); }

/* Google Maps import box */
.gmaps-import-box {
  margin-top: 14px;
  padding: 13px 15px;
  background: rgba(212,168,67,.05);
  border: 1px solid rgba(212,168,67,.22);
  border-radius: 10px;
}
.gmaps-import-box .gm-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--gold);
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.gmaps-import-row {
  display: flex;
  gap: 8px;
}
.gmaps-import-row input {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 13px;
  background: var(--bg);
  transition: border-color .15s;
  min-width: 0;
}
.gmaps-import-row input:focus { outline: none; border-color: var(--gold); }
.gmaps-import-row input::placeholder { color: var(--muted); font-size: 12px; }
.btn-import-gm {
  padding: 8px 16px;
  background: var(--gold);
  color: var(--navy);
  border: none;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: opacity .15s;
}
.btn-import-gm:hover   { opacity: .85; }
.btn-import-gm:disabled{ opacity: .5; cursor: not-allowed; }
#gmapsStatus { font-size: 12px; margin-top: 7px; min-height: 18px; }

/* Leaflet popup override */
.leaflet-popup-content-wrapper {
  background: var(--navy) !important;
  color: #fff !important;
  border-radius: 10px !important;
  border: 1px solid rgba(255,255,255,.12) !important;
  box-shadow: 0 6px 24px rgba(0,0,0,.5) !important;
}
.leaflet-popup-tip { background: var(--navy) !important; }
.leaflet-popup-content { color: #fff !important; font-size: 13px !important; }
</style>

<div class="dash-layout">

  <!-- ── Sidebar ── -->
  <div class="dash-sidebar">
    <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,.06);margin-bottom:8px;">
      <div style="font-size:12px;color:rgba(255,255,255,.4);">Garage Panel</div>
      <div style="font-size:15px;font-weight:700;color:var(--gold);margin-top:4px;"><?= htmlspecialchars($garage['garage_name']) ?></div>
    </div>
    <div class="sidebar-nav">
      <div class="sidebar-section">Operations</div>
      <a href="garage_dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="garage_requests.php"><span class="nav-icon">📋</span> All Requests</a>
      <a href="garage_payments.php"><span class="nav-icon">💳</span> Payments</a>
      <a href="garage_complaints.php">
        <span class="nav-icon">⚠️</span> Complaints
        <?php if ($openComplaints > 0): ?>
          <span style="background:var(--danger);color:#fff;border-radius:10px;
                       padding:1px 7px;font-size:11px;font-weight:700;margin-left:4px;"><?= $openComplaints ?></span>
        <?php endif; ?>
      </a>
      <a href="garage_subscriptions.php">
        <span class="nav-icon">⭐</span> My Subscription
        <?php sidebarSubBadge($subStatus); ?>
      </a>
      <div class="sidebar-section">Account</div>
      <a href="garage_profile.php" class="active"><span class="nav-icon">⚙️</span> Settings</a>
      <a href="logout.php"><span class="nav-icon">🚪</span> Logout</a>
    </div>
  </div>

  <!-- ── Content ── -->
  <div class="dash-content">
    <div class="dash-header">
      <div>
        <div class="dash-title">Garage Settings</div>
        <div class="dash-sub">Update your information, map location, and account security.</div>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger">⚠️ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

      <!-- ── Info Card ── -->
      <div class="card" style="text-align:center;">
        <div style="width:80px;height:80px;border-radius:50%;background:var(--navy);color:var(--gold);
                    font-size:28px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">🔧</div>
        <div style="font-size:18px;font-weight:700;"><?= htmlspecialchars($garage['garage_name']) ?></div>
        <div style="font-size:14px;color:var(--muted);margin-top:4px;">📍 <?= htmlspecialchars($garage['location']) ?></div>
        <div style="font-size:14px;color:var(--muted);margin-top:4px;">📞 <?= htmlspecialchars($garage['phone']) ?></div>

        <div style="margin-top:12px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
          <?php if ($hasLocation): ?>
            <span class="badge badge-accepted">🗺️ Visible on Map</span>
          <?php else: ?>
            <span class="badge badge-cancelled">🗺️ Not on Map</span>
          <?php endif; ?>
          <span class="badge <?= $garage['is_active'] ? 'badge-accepted' : 'badge-cancelled' ?>">
            <?= $garage['is_active'] ? '✅ Active' : '❌ Inactive' ?>
          </span>
        </div>

        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
          <div style="font-size:24px;font-weight:700;color:var(--gold);">★ <?= number_format($garage['rating'], 1) ?></div>
          <div style="font-size:13px;color:var(--muted);">Average Rating</div>
        </div>

        <?php if ($hasLocation): ?>
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);">
          <div style="font-size:12px;font-weight:700;color:var(--teal);margin-bottom:5px;">📌 Saved Coordinates</div>
          <code style="font-size:11px;color:var(--muted);">
            <?= number_format((float)$curLat, 7) ?>,<br><?= number_format((float)$curLng, 7) ?>
          </code>
        </div>
        <?php else: ?>
        <div style="margin-top:14px;padding:10px 14px;background:rgba(212,168,67,.06);
                    border-radius:8px;border:1px dashed rgba(212,168,67,.3);">
          <div style="font-size:12px;color:#a07820;line-height:1.5;">
            ⚡ Set your map location to attract more drivers!
          </div>
        </div>
        <?php endif; ?>

        <div style="margin-top:16px;font-size:13px;color:var(--muted);">
          Registered <?= date('d M Y', strtotime($garage['created_at'])) ?>
        </div>
      </div>

      <!-- ── Right: Forms ── -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Update Profile Form -->
        <div class="card">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;">✏️ Edit Garage Info</h3>
          <form method="POST" id="profileForm">
            <input type="hidden" name="action"    value="update_profile">
            <!-- Hidden coordinate inputs (submitted with the form) -->
            <input type="hidden" name="latitude"  id="latInput" value="<?= htmlspecialchars($garage['latitude']  ?? '') ?>">
            <input type="hidden" name="longitude" id="lngInput" value="<?= htmlspecialchars($garage['longitude'] ?? '') ?>">

            <div class="form-row">
              <div class="form-group">
                <label>Owner Name</label>
                <input type="text" name="owner_name" class="form-control"
                       value="<?= htmlspecialchars($garage['owner_name']) ?>" required>
              </div>
              <div class="form-group">
                <label>Garage Name</label>
                <input type="text" name="garage_name" class="form-control"
                       value="<?= htmlspecialchars($garage['garage_name']) ?>" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control"
                  maxlength="8" inputmode="numeric"
                  onkeydown="return /[0-9]|Backspace|Delete|ArrowLeft|ArrowRight|Tab/.test(event.key)"
                  onpaste="event.preventDefault();var t=event.clipboardData.getData('text').replace(/\D/g,'').slice(0,8);this.value=t;"
                  oninput="this.value=this.value.replace(/\D/g,'').slice(0,8)"
                  value="<?= htmlspecialchars($garage['phone']) ?>" required>
              </div>
              <div class="form-group">
                <label>Location Description</label>
                <input type="text" name="location" class="form-control"
                       value="<?= htmlspecialchars($garage['location']) ?>"
                       placeholder="e.g. Al Khuwair, Muscat" required>
              </div>
            </div>

            <div class="form-group">
              <label>Email</label>
              <input type="email" class="form-control"
                     value="<?= htmlspecialchars($garage['email']) ?>"
                     disabled style="background:var(--bg);cursor:not-allowed;">
              <div class="form-hint">Email cannot be changed.</div>
            </div>

            <!-- ══════════════════════════════════════════
                 MAP LOCATION PICKER
            ══════════════════════════════════════════ -->
            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                📍 Map Location
                <span class="loc-badge <?= $hasLocation ? 'set' : 'notset' ?>" id="locBadge">
                  <?= $hasLocation ? '✅ Location Set' : '⚠️ Not set — won\'t appear on map' ?>
                </span>
                <button type="button" class="btn-clear-loc" onclick="clearLocation()">🗑 Clear</button>
              </label>

              <!-- Leaflet map container -->
              <div id="locationPickerMap" class="<?= $hasLocation ? 'has-location' : '' ?>"></div>

              <!-- Manual coordinate inputs -->
              <div class="coord-row">
                <div class="coord-wrap">
                  <label>Latitude</label>
                  <input type="text" id="latDisplay"
                         value="<?= htmlspecialchars($garage['latitude'] ?? '') ?>"
                         placeholder="e.g. 23.5880000"
                         inputmode="decimal"
                         oninput="syncManual()">
                </div>
                <div class="coord-wrap">
                  <label>Longitude</label>
                  <input type="text" id="lngDisplay"
                         value="<?= htmlspecialchars($garage['longitude'] ?? '') ?>"
                         placeholder="e.g. 58.3829000"
                         inputmode="decimal"
                         oninput="syncManual()">
                </div>
              </div>

              <!-- ── Google Maps URL Import ────────────────── -->
              <div class="gmaps-import-box">
                <div class="gm-label">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z" fill="#d4a843"/>
                  </svg>
                  Import the site from a Google Map link
                </div>
                <div class="gmaps-import-row">
                  <input type="text" id="gmapsUrl"
                         placeholder="https://maps.app.goo.gl/..."
                         autocomplete="off" spellcheck="false"
                         onkeydown="if(event.key==='Enter'){event.preventDefault();importFromGMaps();}">
                  <button type="button" class="btn-import-gm" id="btnImportGM" onclick="importFromGMaps()">
                    📍 Import
                  </button>
                </div>
                <div id="gmapsStatus" style="color:var(--muted);"></div>
              </div>
              <!-- ─────────────────────────────────────────── -->

              <div class="map-hint">
                🖱️ <strong>Click anywhere on the map</strong> to drop a pin at your garage location.<br>
                You can also type exact coordinates in the fields above, or drag the pin to adjust.<br>
                Garages without a location will not appear on the public map.
              </div>
            </div>
            <!-- ══════════════════════════════════════════ -->

            <div class="form-group">
              <label>Services Offered</label>
              <div class="service-grid" style="margin-top:8px;">
                <?php foreach ($allServices as $key => [$icon, $label]):
                  $checked = in_array($key, $currentServices);
                ?>
                  <label class="service-btn <?= $checked ? 'active' : '' ?>">
                    <input type="checkbox" name="services[]" value="<?= $key ?>"
                           style="display:none;" <?= $checked ? 'checked' : '' ?>>
                    <span class="svc-icon"><?= $icon ?></span>
                    <span><?= $label ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <button type="submit" class="btn btn-primary btn-sm">💾 Save Changes</button>
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
                     placeholder="Current password" required>
            </div>
            <div class="form-group">
              <label>New Password</label>
              <input type="password" name="new_password" class="form-control"
                     placeholder="Min. 6 characters" required>
            </div>
            <div class="form-group">
              <label>Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control"
                     placeholder="Repeat new password" required>
            </div>
            <button type="submit" class="btn btn-dark btn-sm">Change Password</button>
          </form>
        </div>

      </div><!-- /right col -->
    </div><!-- /grid -->
  </div><!-- /dash-content -->
</div><!-- /dash-layout -->

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── Service button toggle ──────────────────────────────────
document.querySelectorAll('.service-btn').forEach(lbl => {
  const cb = lbl.querySelector('input[type=checkbox]');
  lbl.addEventListener('click', () => {
    setTimeout(() => lbl.classList.toggle('active', cb.checked), 0);
  });
});

// ── Location Picker Map ────────────────────────────────────
const OMAN_DEFAULT = [23.5880, 58.3829]; // Muscat fallback

// Pass existing DB values from PHP
const INIT_LAT = <?= json_encode($hasLocation ? (float)$curLat : null) ?>;
const INIT_LNG = <?= json_encode($hasLocation ? (float)$curLng : null) ?>;

const pickerMap = L.map('locationPickerMap', {
  center: (INIT_LAT && INIT_LNG) ? [INIT_LAT, INIT_LNG] : OMAN_DEFAULT,
  zoom:   (INIT_LAT && INIT_LNG) ? 16 : 12
});

L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
  attribution: '© CartoDB © OpenStreetMap',
  maxZoom: 19,
  subdomains: 'abcd'
}).addTo(pickerMap);

// Gold garage pin icon
const garagePin = L.divIcon({
  html: `<div style="display:flex;flex-direction:column;align-items:center;">
    <div style="background:rgba(10,22,40,.9);color:#fff;font-size:11px;font-weight:700;
         padding:3px 8px;border-radius:5px;border:1px solid rgba(255,255,255,.15);
         margin-bottom:3px;white-space:nowrap;"><?= htmlspecialchars($garage['garage_name']) ?></div>
    <svg width="32" height="40" viewBox="0 0 30 38" xmlns="http://www.w3.org/2000/svg">
      <path d="M15 0C6.716 0 0 6.716 0 15C0 23.284 15 38 15 38C15 38 30 23.284 30 15C30 6.716 23.284 0 15 0Z" fill="#d4a843"/>
      <circle cx="15" cy="15" r="8.5" fill="rgba(0,0,0,0.25)"/>
      <text x="15" y="19.5" text-anchor="middle" font-size="11" fill="#fff">🔧</text>
    </svg>
  </div>`,
  iconSize: [120, 56],
  iconAnchor: [60, 56],
  className: ''
});

let pickerMarker = null;

// Place existing marker if coordinates are saved
if (INIT_LAT && INIT_LNG) {
  pickerMarker = L.marker([INIT_LAT, INIT_LNG], { icon: garagePin, draggable: true })
    .addTo(pickerMap)
    .bindPopup('<strong style="color:#d4a843;">📍 Your garage location</strong><br><small>Drag to adjust</small>');

  // Allow dragging the marker to update coords
  pickerMarker.on('dragend', function() {
    const pos = pickerMarker.getLatLng();
    updateCoords(pos.lat, pos.lng);
  });
}

// Click on map → place / move marker
pickerMap.on('click', function(e) {
  placeMarker(e.latlng.lat, e.latlng.lng);
});

function placeMarker(lat, lng) {
  if (pickerMarker) {
    pickerMarker.setLatLng([lat, lng]);
  } else {
    pickerMarker = L.marker([lat, lng], { icon: garagePin, draggable: true })
      .addTo(pickerMap)
      .bindPopup('<strong style="color:#d4a843;">📍 Your garage location</strong><br><small>Drag to adjust</small>');
    pickerMarker.on('dragend', function() {
      const pos = pickerMarker.getLatLng();
      updateCoords(pos.lat, pos.lng);
    });
  }
  updateCoords(lat, lng);
}

function updateCoords(lat, lng) {
  const latStr = lat.toFixed(7);
  const lngStr = lng.toFixed(7);

  // Update hidden form inputs (submitted to server)
  document.getElementById('latInput').value = latStr;
  document.getElementById('lngInput').value = lngStr;

  // Update visible display inputs
  document.getElementById('latDisplay').value = latStr;
  document.getElementById('lngDisplay').value = lngStr;

  // Map border → teal
  document.getElementById('locationPickerMap').classList.add('has-location');

  // Status badge
  const badge = document.getElementById('locBadge');
  badge.className = 'loc-badge set';
  badge.textContent = '✅ Location Set';
}

// Manual coordinate inputs → move map + marker
function syncManual() {
  const lat = parseFloat(document.getElementById('latDisplay').value);
  const lng = parseFloat(document.getElementById('lngDisplay').value);
  if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
    pickerMap.setView([lat, lng], 16);
    placeMarker(lat, lng);
  }
}

// Clear location
function clearLocation() {
  document.getElementById('latInput').value   = '';
  document.getElementById('lngInput').value   = '';
  document.getElementById('latDisplay').value = '';
  document.getElementById('lngDisplay').value = '';

  if (pickerMarker) { pickerMap.removeLayer(pickerMarker); pickerMarker = null; }

  document.getElementById('locationPickerMap').classList.remove('has-location');

  const badge = document.getElementById('locBadge');
  badge.className = 'loc-badge notset';
  badge.textContent = "⚠️ Not set — won't appear on map";
}

// Fix tile rendering if map was in hidden container
setTimeout(() => pickerMap.invalidateSize(), 250);

// ── Import location from Google Maps URL ──────────────────────────────────
async function importFromGMaps() {
  const urlInput  = document.getElementById('gmapsUrl');
  const btn       = document.getElementById('btnImportGM');
  const statusEl  = document.getElementById('gmapsStatus');
  const url       = urlInput.value.trim();

  // Reset status
  statusEl.style.color = 'var(--muted)';
  statusEl.textContent  = '';

  if (!url) {
    statusEl.style.color = 'var(--danger)';
    statusEl.textContent = '⚠️ Please paste the Google Maps link first.';
    urlInput.focus();
    return;
  }

  // Basic URL shape check client-side
  if (!url.startsWith('http')) {
    statusEl.style.color = 'var(--danger)';
    statusEl.textContent = '⚠️ The link is invalid. Make sure it is copied completely.';
    return;
  }

  // Loading state
  btn.disabled      = true;
  btn.textContent   = '⏳ ...';
  statusEl.style.color = 'var(--muted)';
  statusEl.textContent = 'Extracting coordinates...';

  try {
    const fd = new FormData();
    fd.append('action',    'resolve_gmaps');
    fd.append('gmaps_url', url);

    const res  = await fetch('garage_profile.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.error) {
      statusEl.style.color = 'var(--danger)';
      statusEl.textContent = '❌ ' + data.error;
    } else {
      // Place marker and zoom
      pickerMap.setView([data.lat, data.lng], 17);
      placeMarker(data.lat, data.lng);

      statusEl.style.color = 'var(--teal)';
      statusEl.textContent =
        `✅ Imported successfully — ${data.lat.toFixed(5)}, ${data.lng.toFixed(5)}`;

      // Clear the URL field
      urlInput.value = '';
    }
  } catch (e) {
    statusEl.style.color = 'var(--danger)';
    statusEl.textContent = '❌ Network error. Please try again.';
  } finally {
    btn.disabled    = false;
    btn.textContent = '📍 Import';
  }
}

</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
