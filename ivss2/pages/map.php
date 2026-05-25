<?php
require_once __DIR__ . '/../includes/config.php';

$db = getDB();

// Load all active garages
$allGarages = $db->query("
    SELECT garage_id, garage_name, location, rating, services,
           latitude, longitude
    FROM garages
    WHERE is_active = 1
    ORDER BY rating DESC
")->fetchAll();

// Separate mapped vs unmapped garages
$mappedGarages   = array_filter($allGarages, fn($g) => $g['latitude'] !== null && $g['longitude'] !== null);
$unmappedGarages = array_filter($allGarages, fn($g) => $g['latitude'] === null  || $g['longitude'] === null);

// Pre-selected garage from URL
$selectedGarageId = intval($_GET['garage_id'] ?? 0);

$pageTitle = 'Garage Map';
include __DIR__ . '/../includes/header.php';

$svcLabels = ['towing'=>'🚛 Towing','battery'=>'🔋 Battery','tire'=>'🛞 Tire','fuel'=>'⛽ Fuel','lockout'=>'🔑 Lockout','repair'=>'🔧 Repair'];
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<style>
/* ─── Layout ──────────────────────────────────────────── */
.map-page-wrap {
  display: flex;
  height: calc(100vh - 70px);
  overflow: hidden;
}

/* ─── Left Sidebar ────────────────────────────────────── */
.map-sidebar {
  width: 290px;
  flex-shrink: 0;
  background: var(--white);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.map-sidebar-header {
  padding: 18px 16px;
  border-bottom: 1px solid var(--border);
  background: var(--navy);
  color: #fff;
}
.map-sidebar-header h2 { font-size:16px; font-weight:800; margin:0 0 3px; }
.map-sidebar-header p  { font-size:12px; color:rgba(255,255,255,.45); margin:0; }

.sidebar-search {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  background: var(--bg);
}
.sidebar-search input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 13px;
  background: #fff;
  box-sizing: border-box;
}
.sidebar-search input:focus { outline:none; border-color:var(--teal); }

.garage-list { flex:1; overflow-y:auto; padding:6px; }

.garage-list-item {
  padding: 11px 13px;
  border-radius: 10px;
  cursor: pointer;
  border: 1.5px solid transparent;
  margin-bottom: 4px;
  transition: background .12s;
}
.garage-list-item:hover  { background: var(--bg); }
.garage-list-item.active { background: rgba(26,158,138,.08); border-color:var(--teal); }

.g-name  { font-weight:700; font-size:13px; margin-bottom:2px; }
.g-loc   { font-size:11px; color:var(--muted); margin-bottom:3px; }
.g-stars { font-size:12px; color:#f5a623; }
.g-dist  { font-size:12px; font-weight:700; color:var(--teal); margin-top:3px; }
.g-svcs  { display:flex; flex-wrap:wrap; gap:3px; margin-top:5px; }
.g-svcs span {
  background:rgba(26,158,138,.1); color:var(--teal);
  font-size:10px; font-weight:700; padding:1px 7px; border-radius:8px;
}
.unmapped-item {
  padding: 8px 13px;
  border-radius: 8px;
  border: 1px dashed rgba(212,168,67,.35);
  background: rgba(212,168,67,.04);
  margin-bottom: 3px;
}
.unmapped-item .g-name { color: var(--muted); }

/* ─── Map ─────────────────────────────────────────────── */
.map-canvas-wrap {
  flex: 1;
  position: relative;
  overflow: hidden;
}
#leafletMap { width:100%; height:100%; }

/* Locate button */
#locateBtn {
  position: absolute;
  top: 14px; right: 14px;
  z-index: 900;
  background: var(--teal);
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 10px 18px;
  font-size: 13px;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(0,0,0,.35);
  transition: background .15s, transform .1s;
  display: flex;
  align-items: center;
  gap: 7px;
}
#locateBtn:hover    { background: #168a78; }
#locateBtn:active   { transform: scale(.97); }
#locateBtn:disabled { background: #3a5570; cursor: not-allowed; }

/* Alert bar */
#mapAlert {
  position: absolute;
  top: 14px; left: 50%;
  transform: translateX(-50%);
  z-index: 900;
  display: none;
  padding: 9px 20px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  pointer-events: none;
}
#mapAlert.info    { background: rgba(26,158,138,.9);   color:#fff; }
#mapAlert.danger  { background: rgba(226,75,74,.92);   color:#fff; }
#mapAlert.success { background: rgba(16,180,140,.92);  color:#fff; }

/* Legend */
.map-legend {
  position: absolute;
  bottom: 30px; right: 14px;
  z-index: 900;
  background: rgba(10,22,40,.92);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 10px;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 7px;
  backdrop-filter: blur(6px);
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: rgba(255,255,255,.75);
}
.l-dot { width:13px; height:13px; border-radius:50%; border:2px solid rgba(255,255,255,.3); flex-shrink:0; }

/* Leaflet popup override */
.leaflet-popup-content-wrapper {
  background: #0a1628 !important;
  border: 1px solid rgba(255,255,255,.1) !important;
  border-radius: 12px !important;
  color: #fff !important;
  box-shadow: 0 8px 30px rgba(0,0,0,.5) !important;
}
.leaflet-popup-tip { background: #0a1628 !important; }
.leaflet-popup-content { margin: 14px 16px !important; min-width: 190px; }
.pp-name  { font-size:14px; font-weight:700; color:#d4a843; margin-bottom:3px; }
.pp-loc   { font-size:12px; color:rgba(255,255,255,.55); margin-bottom:4px; }
.pp-stars { font-size:13px; color:#f5a623; margin-bottom:4px; }
.pp-dist  { font-size:12px; color:#1a9e8a; font-weight:700; margin-bottom:8px; }
.pp-svcs  { display:flex; flex-wrap:wrap; gap:4px; margin-bottom:10px; }
.pp-svcs span {
  background:rgba(26,158,138,.2); color:#1a9e8a;
  font-size:10px; font-weight:700; padding:2px 8px; border-radius:8px;
}
.pp-btn {
  display: block;
  background: #d4a843;
  color: #0a1628 !important;
  text-align: center;
  padding: 7px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 800;
  text-decoration: none;
  transition: background .15s;
}
.pp-btn:hover { background: #c4983a; }

/* Bottom hint */
.map-hint-bar {
  position: absolute;
  bottom: 10px; left: 50%;
  transform: translateX(-50%);
  z-index: 900;
  font-size: 12px;
  color: rgba(255,255,255,.3);
  pointer-events: none;
  white-space: nowrap;
}

/* CTA bar */
.map-cta { padding:12px 14px; border-top:1px solid var(--border); background:var(--bg); }
</style>

<div class="map-page-wrap">

  <!-- ── Left Sidebar ── -->
  <div class="map-sidebar">
    <div class="map-sidebar-header">
      <h2>🗺️ Garage Network</h2>
      <p><?= count($mappedGarages) ?> mapped · <?= count($unmappedGarages) ?> unmapped</p>
    </div>

    <div class="sidebar-search">
      <input type="text" id="sidebarSearch" placeholder="🔍 Search garages..."
             oninput="searchList(this.value)">
    </div>

    <!-- Sort indicator — hidden until user locates -->
    <div id="sortIndicator" style="display:none;padding:7px 12px;background:rgba(26,158,138,.08);
         border-bottom:1px solid rgba(26,158,138,.18);font-size:11px;color:var(--teal);
         font-weight:700;display:none;align-items:center;gap:6px;">
      <span>📏</span>
      <span>Sorted by distance from your location</span>
    </div>

    <div class="garage-list" id="garageList">

      <!-- Mapped garages -->
      <?php foreach ($mappedGarages as $g):
        $svcs  = array_filter(array_map('trim', explode(',', $g['services'] ?? '')));
        $stars = '';
        for ($s=1;$s<=5;$s++) $stars .= $s <= round($g['rating']) ? '★' : '☆';
      ?>
      <div class="garage-list-item <?= $g['garage_id']==$selectedGarageId?'active':'' ?>"
           id="listItem_<?= $g['garage_id'] ?>"
           onclick="focusGarage(<?= $g['garage_id'] ?>)"
           data-name="<?= strtolower(htmlspecialchars($g['garage_name'])) ?>"
           data-loc="<?= strtolower(htmlspecialchars($g['location'])) ?>">
        <div class="g-name"><?= htmlspecialchars($g['garage_name']) ?></div>
        <div class="g-loc">📍 <?= htmlspecialchars($g['location']) ?></div>
        <div class="g-stars"><?= $stars ?>
          <span style="color:var(--muted);font-size:11px;"><?= number_format($g['rating'],1) ?></span>
        </div>
        <div class="g-dist" id="dist_<?= $g['garage_id'] ?>"></div>
        <div class="g-svcs">
          <?php foreach ($svcs as $sv): ?>
            <span><?= $svcLabels[$sv] ?? ('🔩 '.ucfirst($sv)) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Unmapped garages (no coordinates) -->
      <?php if ($unmappedGarages): ?>
      <div id="unmappedSection" style="font-size:11px;color:var(--muted);padding:8px 6px 4px;font-weight:700;border-top:1px solid var(--border);margin-top:4px;">
        ⚠️ NOT ON MAP (no coordinates set)
      </div>
      <?php foreach ($unmappedGarages as $g): ?>
      <div class="unmapped-item"
           data-name="<?= strtolower(htmlspecialchars($g['garage_name'])) ?>"
           data-loc="<?= strtolower(htmlspecialchars($g['location'])) ?>">
        <div class="g-name" style="font-size:12px;">🔧 <?= htmlspecialchars($g['garage_name']) ?></div>
        <div class="g-loc">📍 <?= htmlspecialchars($g['location']) ?></div>
        <div style="font-size:11px;color:#a07820;margin-top:3px;">No coordinates — ask garage to update profile</div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

    </div>

    <div class="map-cta">
      <a href="request.php" class="btn btn-primary btn-full btn-sm" style="display:block;text-align:center;">
        🚨 Request Roadside Help
      </a>
    </div>
  </div>

  <!-- ── Map Canvas ── -->
  <div class="map-canvas-wrap">
    <div id="leafletMap"></div>

    <!-- GPS Locate button -->
    <button id="locateBtn" onclick="locateUser()">📍 Locate Me</button>

    <!-- Alert bar -->
    <div id="mapAlert"></div>

    <!-- Legend -->
    <div class="map-legend">
      <div class="legend-item">
        <div class="l-dot" style="background:#d4a843;"></div>
        Certified Garage
      </div>
      <div class="legend-item">
        <div class="l-dot" style="background:#1a9e8a;"></div>
        Selected / Nearest
      </div>
      <div class="legend-item">
        <div class="l-dot" style="background:#1a9e8a;opacity:.6;"></div>
        Your Location
      </div>
    </div>

    <div class="map-hint-bar">Click a pin for details · Press "Locate Me" to sort by distance</div>
  </div>

</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ─── Garage data from PHP ──────────────────────────────────
const GARAGES = <?= json_encode(array_values(array_map(fn($g) => [
  'id'       => (int)$g['garage_id'],
  'name'     => $g['garage_name'],
  'location' => $g['location'],
  'rating'   => (float)$g['rating'],
  'services' => array_filter(array_map('trim', explode(',', $g['services'] ?? ''))),
  'lat'      => (float)$g['latitude'],
  'lng'      => (float)$g['longitude'],
], $mappedGarages))) ?>;

const SELECTED_ID = <?= $selectedGarageId ?>;

// ─── Map init ─────────────────────────────────────────────
const map = L.map('leafletMap', {
  center: GARAGES.length ? [GARAGES[0].lat, GARAGES[0].lng] : [23.5880, 58.3829],
  zoom: 13
});

L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
  attribution: '© CartoDB © OpenStreetMap',
  maxZoom: 19,
  subdomains: 'abcd'
}).addTo(map);

// ─── Icons ────────────────────────────────────────────────
function makeGarageIcon(name, rank, active) {
  const bg = active ? '#1a9e8a' : '#d4a843';
  return L.divIcon({
    html: `<div style="display:flex;flex-direction:column;align-items:center;">
      <div style="background:rgba(10,22,40,.9);color:#e8f0fe;font-size:11px;font-weight:600;
           padding:3px 9px;border-radius:5px;border:1px solid rgba(255,255,255,.15);
           margin-bottom:4px;max-width:140px;overflow:hidden;text-overflow:ellipsis;
           white-space:nowrap;">${name}</div>
      <svg width="30" height="38" viewBox="0 0 30 38" xmlns="http://www.w3.org/2000/svg">
        <path d="M15 0C6.716 0 0 6.716 0 15C0 23.284 15 38 15 38C15 38 30 23.284 30 15C30 6.716 23.284 0 15 0Z" fill="${bg}"/>
        <circle cx="15" cy="15" r="8.5" fill="rgba(0,0,0,0.28)"/>
        <text x="15" y="19.5" text-anchor="middle" font-size="10" font-weight="700" fill="#fff">${rank}</text>
      </svg>
    </div>`,
    iconSize: [160, 58],
    iconAnchor: [80, 58],
    className: ''
  });
}

const userIcon = L.divIcon({
  html: `<div style="position:relative;width:46px;height:46px;">
    <div style="position:absolute;top:50%;left:50%;
         transform:translate(-50%,-50%);
         width:16px;height:16px;background:#1a9e8a;border-radius:50%;
         border:3px solid #fff;box-shadow:0 0 0 4px rgba(26,158,138,.4);z-index:2;"></div>
    <div style="position:absolute;top:50%;left:50%;
         transform:translate(-50%,-50%);
         width:46px;height:46px;background:rgba(26,158,138,.18);border-radius:50%;
         animation:pu 1.8s ease-out infinite;"></div>
  </div>
  <style>@keyframes pu{0%{transform:translate(-50%,-50%) scale(.5);opacity:1}100%{transform:translate(-50%,-50%) scale(2.2);opacity:0}}</style>`,
  iconSize: [46, 46], iconAnchor: [23, 23], className: ''
});

// ─── State ────────────────────────────────────────────────
const mkMap = {};           // garage_id → L.marker
let userMarker = null;
let accCircle  = null;
let userLat = null, userLng = null;

// ─── Alert helper ─────────────────────────────────────────
let alertTimer = null;
function showAlert(msg, type='info') {
  const el = document.getElementById('mapAlert');
  el.textContent = msg;
  el.className = type;
  el.style.display = 'block';
  clearTimeout(alertTimer);
  if (type !== 'info') alertTimer = setTimeout(() => el.style.display='none', 4500);
}

// ─── Distance (Haversine) ─────────────────────────────────
function calcDist(la1,ln1,la2,ln2) {
  const R=6371000, dL=(la2-la1)*Math.PI/180, dN=(ln2-ln1)*Math.PI/180;
  const a=Math.sin(dL/2)**2+Math.cos(la1*Math.PI/180)*Math.cos(la2*Math.PI/180)*Math.sin(dN/2)**2;
  return R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
}
function fmtDist(d) { return d<1000 ? Math.round(d)+' m' : (d/1000).toFixed(1)+' km'; }

// ─── Stars helper ─────────────────────────────────────────
function makeStars(rating) {
  let s='';
  for(let i=1;i<=5;i++) s += i<=Math.round(rating)?'★':'☆';
  return s;
}

// ─── Popup HTML ──────────────────────────────────────────
function popupHtml(g, dist) {
  const stars = makeStars(g.rating);
  const distRow = dist!=null ? `<div class="pp-dist">📏 ${fmtDist(dist)} away</div>` : '';
  const svcsHtml = (g.services||[]).filter(Boolean)
    .map(s => `<span>${({towing:'🚛 Towing',battery:'🔋 Battery',tire:'🛞 Tire',fuel:'⛽ Fuel',lockout:'🔑 Lockout',repair:'🔧 Repair'}[s]||'🔩 '+s)}</span>`)
    .join('');
  return `
    <div class="pp-name">🔧 ${g.name}</div>
    <div class="pp-loc">📍 ${g.location}</div>
    <div class="pp-stars">${stars} <span style="color:rgba(255,255,255,.5);font-size:11px;">${g.rating.toFixed(1)}</span></div>
    ${distRow}
    <div class="pp-svcs">${svcsHtml}</div>
    <a class="pp-btn" href="request.php?garage_id=${g.id}">🚨 Request this Garage</a>`;
}

// ─── Render all pins ─────────────────────────────────────
function renderPins() {
  // Sort by distance if user located, else by rating
  const sorted = [...GARAGES].map(g => ({
    ...g,
    dist: (userLat!=null) ? calcDist(userLat, userLng, g.lat, g.lng) : null
  })).sort((a,b) => a.dist!=null ? a.dist-b.dist : b.rating-a.rating);

  // Remove old markers
  Object.values(mkMap).forEach(m => map.removeLayer(m));
  for (const k in mkMap) delete mkMap[k];

  sorted.forEach((g, i) => {
    const rank   = i+1;
    const active = g.id === SELECTED_ID;
    const mk = L.marker([g.lat, g.lng], { icon: makeGarageIcon(g.name, rank, active) })
      .addTo(map)
      .bindPopup(popupHtml(g, g.dist), { maxWidth: 240 });

    mk.on('click', () => {
      highlightListItem(g.id);
      mk.openPopup();
    });

    mkMap[g.id] = mk;

    // Update distance label in sidebar
    const distEl = document.getElementById('dist_' + g.id);
    if (distEl) distEl.textContent = g.dist!=null ? '📏 '+fmtDist(g.dist) : '';
  });
}

// ─── Highlight sidebar item ───────────────────────────────
function highlightListItem(gid) {
  document.querySelectorAll('.garage-list-item').forEach(el => el.classList.remove('active'));
  const item = document.getElementById('listItem_' + gid);
  if (item) {
    item.classList.add('active');
    item.scrollIntoView({ behavior:'smooth', block:'nearest' });
  }
}

// ─── Click sidebar item → open map popup ─────────────────
function focusGarage(gid) {
  highlightListItem(gid);
  const mk = mkMap[gid];
  if (!mk) return;
  map.setView(mk.getLatLng(), 16);
  mk.openPopup();
}

// ─── Sort sidebar cards by distance ──────────────────────
function sortSidebarByDistance() {
  const list = document.getElementById('garageList');

  // Build id → distance map
  const distMap = {};
  GARAGES.forEach(g => {
    distMap[g.id] = calcDist(userLat, userLng, g.lat, g.lng);
  });

  // Grab all mapped garage card elements
  const items = [...list.querySelectorAll('.garage-list-item')];

  // Sort by distance ascending
  items.sort((a, b) => {
    const idA = parseInt(a.id.replace('listItem_', ''));
    const idB = parseInt(b.id.replace('listItem_', ''));
    return (distMap[idA] ?? Infinity) - (distMap[idB] ?? Infinity);
  });

  // Find the anchor before unmapped section (or end of list)
  const anchor = document.getElementById('unmappedSection') || null;

  // Re-insert cards in sorted order
  items.forEach(item => list.insertBefore(item, anchor));

  // Show sort indicator
  const ind = document.getElementById('sortIndicator');
  ind.style.display = 'flex';
}

// ─── GPS Locate ───────────────────────────────────────────
function locateUser() {
  if (!navigator.geolocation) { showAlert('GPS not supported by this browser.','danger'); return; }
  showAlert('⏳ Locating you...', 'info');
  document.getElementById('locateBtn').disabled = true;

  navigator.geolocation.getCurrentPosition(onOk, onErr, {
    enableHighAccuracy: true, timeout: 15000, maximumAge: 0
  });
}

function onOk(pos) {
  userLat = pos.coords.latitude;
  userLng = pos.coords.longitude;
  const acc = pos.coords.accuracy;

  if (userMarker) map.removeLayer(userMarker);
  if (accCircle)  map.removeLayer(accCircle);

  accCircle = L.circle([userLat,userLng], {
    radius: acc, color:'#1a9e8a', fillColor:'#1a9e8a',
    fillOpacity:.07, weight:1, dashArray:'5'
  }).addTo(map);

  userMarker = L.marker([userLat,userLng], { icon:userIcon, zIndexOffset:2000 })
    .addTo(map)
    .bindPopup(`<div class="pp-name">📍 Your Location</div>
                <div class="pp-loc">Accuracy: ±${Math.round(acc)} m</div>`);

  // Re-render pins sorted by distance
  renderPins();
  sortSidebarByDistance();

  // Fit all garages + user on screen
  const allPoints = [[userLat,userLng], ...GARAGES.map(g=>[g.lat,g.lng])];
  map.fitBounds(L.latLngBounds(allPoints), { padding:[60,60] });

  document.getElementById('locateBtn').disabled = false;
  showAlert('✅ Garages sorted by distance from your location', 'success');
}

function onErr(err) {
  document.getElementById('locateBtn').disabled = false;
  const msgs = {1:'Location permission denied.', 2:'Could not determine location.', 3:'Location request timed out.'};
  showAlert('❌ ' + (msgs[err.code]||'Unknown error.'), 'danger');
}

// ─── Sidebar search ───────────────────────────────────────
function searchList(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('.garage-list-item, .unmapped-item').forEach(el => {
    const match = (el.dataset.name||'').includes(q) || (el.dataset.loc||'').includes(q);
    el.style.display = match ? '' : 'none';
  });
}

// ─── Init ─────────────────────────────────────────────────
renderPins();

// Fit bounds to all mapped garages on load
if (GARAGES.length > 1) {
  map.fitBounds(L.latLngBounds(GARAGES.map(g=>[g.lat,g.lng])), { padding:[60,80] });
} else if (GARAGES.length === 1) {
  map.setView([GARAGES[0].lat, GARAGES[0].lng], 14);
}

// Pre-select from URL param
if (SELECTED_ID) {
  setTimeout(() => focusGarage(SELECTED_ID), 500);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
