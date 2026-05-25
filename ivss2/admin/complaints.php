<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdminLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=admin');

$db = getDB();
$msg = '';
$msgType = 'success';

/* ══════════════════════════════════════
   POST ACTIONS
══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action']      ?? '';
    $complaintId = intval($_POST['complaint_id'] ?? 0);

    if ($action === 'update_status' && $complaintId) {
        $newStatus = sanitize($_POST['new_status'] ?? '');
        if (in_array($newStatus, ['open','reviewed','resolved','dismissed'])) {
            $resolvedAt = in_array($newStatus,['resolved','dismissed']) ? ", resolved_at=NOW()" : "";
            $db->prepare("UPDATE complaints SET status=?{$resolvedAt} WHERE complaint_id=?")
               ->execute([$newStatus, $complaintId]);
            $msg = 'Complaint status updated.';
        }
    }

    if ($action === 'add_note' && $complaintId) {
        $note = sanitize($_POST['admin_note'] ?? '');
        if (strlen(trim($note)) >= 3) {
            $db->prepare("UPDATE complaints SET admin_note=? WHERE complaint_id=?")
               ->execute([$note, $complaintId]);
            $msg = '✅ Note added successfully.';
        }
    }

    if ($action === 'delete' && $complaintId) {
        $db->prepare("DELETE FROM complaints WHERE complaint_id=?")->execute([$complaintId]);
        $msg = '🗑️ Complaint deleted.';
    }
}

/* ══════════════════════════════════════
   FETCH + FILTERS
══════════════════════════════════════ */
$filterType   = sanitize($_GET['type']   ?? '');
$filterStatus = sanitize($_GET['status'] ?? '');
$search       = sanitize($_GET['search'] ?? '');

$sql = "SELECT c.*,
               u.full_name   AS user_name, u.phone AS user_phone,
               g.garage_name,
               r.service_type, r.vehicle_type, r.location_desc,
               r.price, r.status AS req_status
        FROM complaints c
        JOIN users u   ON c.user_id   = u.user_id
        JOIN garages g ON c.garage_id = g.garage_id
        JOIN service_requests r ON c.request_id = r.request_id
        WHERE 1";
$params = [];

if (in_array($filterType, ['price','service']))
    { $sql .= " AND c.type=?"; $params[] = $filterType; }
if (in_array($filterStatus, ['open','reviewed','resolved','dismissed']))
    { $sql .= " AND c.status=?"; $params[] = $filterStatus; }
if ($search)
    { $sql .= " AND (u.full_name LIKE ? OR g.garage_name LIKE ? OR c.message LIKE ?)";
      $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$sql .= " ORDER BY FIELD(c.status,'open','reviewed','resolved','dismissed'), c.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Summary counts
$counts = [];
foreach ($db->query("SELECT status, COUNT(*) AS n FROM complaints GROUP BY status") as $r)
    $counts[$r['status']] = $r['n'];
$countPrice   = $db->query("SELECT COUNT(*) FROM complaints WHERE type='price'")->fetchColumn();
$countService = $db->query("SELECT COUNT(*) FROM complaints WHERE type='service'")->fetchColumn();
$countOpen    = $counts['open'] ?? 0;

$pageTitle = 'Complaints Management';
include __DIR__ . '/admin_header.php';
?>

<style>
.c-type-badge { padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px; }
.c-type-price   { background:rgba(212,168,67,0.12);color:#a07820; }
.c-type-service { background:rgba(10,37,64,0.08);color:var(--navy); }
.c-status-badge { padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700; }
.c-status-open      { background:rgba(212,168,67,0.12);color:#a07820; }
.c-status-reviewed  { background:rgba(26,158,138,0.1);color:var(--teal); }
.c-status-resolved  { background:rgba(6,163,90,0.1);color:#06a35a; }
.c-status-dismissed { background:rgba(0,0,0,0.06);color:var(--muted); }
.cexpand { display:none;border-top:1px solid var(--border);padding:16px 20px;background:var(--bg); }
</style>

<div class="dash-layout">

  <?php include __DIR__ . '/admin_sidebar.php'; ?>


  <div class="dash-content">
    <div class="dash-header">
      <div>
        <div class="dash-title">Complaints Management ⚠️</div>
        <div class="dash-sub">Review all customer complaints and take the appropriate action.</div>
      </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom:20px;"><?= $msg ?></div>
    <?php endif; ?>

    <!-- Metrics -->
    <div class="metric-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-bottom:24px;">
      <?php
      $allCounts = array_sum($counts);
      foreach ([
        ['⚠️', 'Total',      $allCounts,            'var(--text)'],
        ['🟡', 'Open',        $counts['open']    ?? 0,'var(--warning)'],
        ['🔵', 'Under Review',  $counts['reviewed'] ?? 0,'var(--teal)'],
        ['✅', 'Resolved',         $counts['resolved'] ?? 0,'#06a35a'],
        ['💰', 'Price Complaints',   $countPrice,            '#a07820'],
        ['🔧', 'Service Complaints',  $countService,          'var(--navy)'],
      ] as [$icon,$label,$val,$color]): ?>
      <div class="metric-card">
        <div class="metric-icon"><?= $icon ?></div>
        <div class="metric-label"><?= $label ?></div>
        <div class="metric-value" style="color:<?= $color ?>;font-size:24px;"><?= $val ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px;">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:180px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:5px;">🔍 Search</label>
          <input type="text" name="search" class="form-control"
                 placeholder="Customer name, garage name, complaint text..."
                 value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="form-group" style="min-width:140px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:5px;">Type</label>
          <select name="type" class="form-control">
            <option value="">All</option>
            <option value="price"   <?= $filterType==='price'  ?'selected':'' ?>>💰 Price Complaints</option>
            <option value="service" <?= $filterType==='service'?'selected':'' ?>>🔧 Service Complaints</option>
          </select>
        </div>
        <div class="form-group" style="min-width:160px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:5px;">Status</label>
          <select name="status" class="form-control">
            <option value="">All</option>
            <option value="open"      <?= $filterStatus==='open'      ?'selected':'' ?>>🟡 Open</option>
            <option value="reviewed"  <?= $filterStatus==='reviewed'  ?'selected':'' ?>>🔵 Under Review</option>
            <option value="resolved"  <?= $filterStatus==='resolved'  ?'selected':'' ?>>✅ Resolved</option>
            <option value="dismissed" <?= $filterStatus==='dismissed' ?'selected':'' ?>>⛔ Dismissed</option>
          </select>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;">
          <button type="submit" class="btn btn-primary btn-sm">Apply</button>
          <?php if ($search || $filterType || $filterStatus): ?>
          <a href="complaints.php" class="btn btn-dark btn-sm">Clear</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Count -->
    <div style="font-size:13px;color:var(--muted);margin-bottom:16px;">
      Showing <strong style="color:var(--text);"><?= count($complaints) ?></strong> complaint(s)
    </div>

    <!-- Complaints -->
    <?php if (empty($complaints)): ?>
    <div class="card" style="text-align:center;padding:64px;color:var(--muted);">
      <div style="font-size:56px;margin-bottom:16px;">✅</div>
      <div style="font-size:18px;font-weight:700;margin-bottom:8px;">No Complaints Found</div>
      <p>No Complaints Found match your search criteria.</p>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
      <?php foreach ($complaints as $c): ?>
      <div style="background:var(--white);border:1.5px solid <?= $c['status']==='open'?'rgba(226,75,74,0.25)':'var(--border)' ?>;border-radius:12px;overflow:hidden;">

        <!-- Header row -->
        <div style="padding:14px 18px;display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;cursor:pointer;"
             onclick="toggleAdminCard(<?= $c['complaint_id'] ?>)">

          <div style="font-size:26px;flex-shrink:0;"><?= $c['type']==='price'?'💰':'🔧' ?></div>

          <div style="flex:1;min-width:200px;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:5px;">
              <span class="c-type-badge c-type-<?= $c['type'] ?>">
                <?= $c['type']==='price'?'💰 Price Complaint':'🔧 Service Complaint' ?>
              </span>
              <span class="c-status-badge c-status-<?= $c['status'] ?>">
                <?= ['open'=>'🟡 Open','reviewed'=>'🔵 Under Review','resolved'=>'✅ Resolved','dismissed'=>'⛔ Dismissed'][$c['status']] ?>
              </span>
              <span style="font-size:12px;color:var(--muted);">
                #<?= $c['complaint_id'] ?> · IVSS-<?= str_pad($c['request_id'],4,'0',STR_PAD_LEFT) ?>
              </span>
            </div>
            <div style="font-size:13px;display:flex;gap:12px;flex-wrap:wrap;">
              <span>👤 <strong><?= htmlspecialchars($c['user_name']) ?></strong> · 📞 <?= htmlspecialchars($c['user_phone']) ?></span>
              <span>🔧 <?= htmlspecialchars($c['garage_name']) ?></span>
              <?php if ($c['price']): ?>
              <span>💰 <strong><?= number_format($c['price'],3) ?> OMR</strong></span>
              <?php endif; ?>
            </div>
            <div style="font-size:12px;color:var(--muted);margin-top:3px;">
              <?= ucfirst($c['service_type']) ?> · <?= date('d M Y, H:i', strtotime($c['created_at'])) ?>
              <?php if ($c['resolved_at']): ?>
              · Resolved <?= date('d M Y', strtotime($c['resolved_at'])) ?>
              <?php endif; ?>
            </div>
          </div>

          <div style="flex-shrink:0;font-size:13px;color:var(--muted);padding-top:2px;" id="admarrow-<?= $c['complaint_id'] ?>">▼</div>
        </div>

        <!-- Expandable body -->
        <div class="cexpand" id="admbody-<?= $c['complaint_id'] ?>">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;flex-wrap:wrap;">

            <!-- Left: Messages -->
            <div>
              <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Customer Message</div>
              <div style="background:var(--white);border:1px solid var(--border);border-radius:8px;padding:12px;font-size:13px;line-height:1.6;margin-bottom:12px;">
                "<?= htmlspecialchars($c['message']) ?>"
              </div>

              <?php if ($c['garage_note']): ?>
              <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Garage Response</div>
              <div style="background:rgba(10,37,64,0.05);border:1px solid rgba(10,37,64,0.1);border-radius:8px;padding:12px;font-size:13px;margin-bottom:12px;">
                🔧 <?= htmlspecialchars($c['garage_note']) ?>
              </div>
              <?php else: ?>
              <div style="font-size:12px;color:var(--muted);font-style:italic;margin-bottom:12px;">⏳ Garage has not responded yet.</div>
              <?php endif; ?>

              <!-- Admin note form -->
              <div>
                <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
                  <?= $c['admin_note'] ? '✏️ Edit Admin Note' : '💬 Add Note' ?>
                </div>
                <?php if ($c['admin_note']): ?>
                <div style="background:rgba(26,158,138,0.08);border:1px solid rgba(26,158,138,0.2);border-radius:8px;padding:10px;font-size:13px;margin-bottom:8px;">
                  💬 <?= htmlspecialchars($c['admin_note']) ?>
                </div>
                <?php endif; ?>
                <form method="POST" style="display:flex;flex-direction:column;gap:6px;">
                  <input type="hidden" name="action"       value="add_note">
                  <input type="hidden" name="complaint_id" value="<?= $c['complaint_id'] ?>">
                  <textarea name="admin_note" class="form-control" rows="2"
                            placeholder="Add a note visible to the customer and garage..."
                            style="font-size:13px;resize:vertical;"><?= htmlspecialchars($c['admin_note'] ?? '') ?></textarea>
                  <button type="submit" class="btn btn-teal btn-sm" style="align-self:flex-start;">💾 Save Note</button>
                </form>
              </div>
            </div>

            <!-- Right: Status + Actions -->
            <div>
              <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Update Status</div>

              <form method="POST" style="display:flex;gap:8px;margin-bottom:16px;">
                <input type="hidden" name="action"       value="update_status">
                <input type="hidden" name="complaint_id" value="<?= $c['complaint_id'] ?>">
                <select name="new_status" class="form-control" style="font-size:13px;padding:7px 10px;">
                  <option value="open"      <?= $c['status']==='open'      ?'selected':'' ?>>🟡 Open</option>
                  <option value="reviewed"  <?= $c['status']==='reviewed'  ?'selected':'' ?>>🔵 Under Review</option>
                  <option value="resolved"  <?= $c['status']==='resolved'  ?'selected':'' ?>>✅ Resolved</option>
                  <option value="dismissed" <?= $c['status']==='dismissed' ?'selected':'' ?>>⛔ Dismissed</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Update</button>
              </form>

              <!-- Quick status buttons -->
              <div style="display:flex;flex-direction:column;gap:7px;margin-bottom:16px;">
                <?php foreach ([
                  ['reviewed', '🔵 Mark Under Review', 'rgba(26,158,138,0.08)', 'var(--teal)', 'var(--teal)'],
                  ['resolved', '✅ Resolve Complaint',         'rgba(6,163,90,0.08)',   '#06a35a',    '#06a35a'],
                  ['dismissed','⛔ Dismiss Complaint',        'rgba(0,0,0,0.04)',      'var(--muted)','var(--border)'],
                ] as [$sv,$bl,$bg,$col,$bord]): if ($c['status']===$sv) continue; ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action"       value="update_status">
                  <input type="hidden" name="complaint_id" value="<?= $c['complaint_id'] ?>">
                  <input type="hidden" name="new_status"   value="<?= $sv ?>">
                  <button type="submit" class="btn btn-sm"
                          style="width:100%;background:<?= $bg ?>;color:<?= $col ?>;border:1px solid <?= $bord ?>;">
                    <?= $bl ?>
                  </button>
                </form>
                <?php endforeach; ?>
              </div>

              <!-- Request info -->
              <div style="background:var(--white);border-radius:8px;padding:12px;border:1px solid var(--border);font-size:12px;">
                <div style="font-weight:700;margin-bottom:6px;font-size:13px;">Request Details</div>
                <div style="color:var(--muted);line-height:1.8;">
                  📋 IVSS-<?= str_pad($c['request_id'],4,'0',STR_PAD_LEFT) ?><br>
                  🔧 <?= ucfirst($c['service_type']) ?> · <?= ucfirst($c['vehicle_type']) ?><br>
                  📌 <?= htmlspecialchars(substr($c['location_desc'],0,45)) ?><br>
                  🔖 Status: <?= ucfirst(str_replace('_',' ',$c['req_status'])) ?>
                  <?php if ($c['price']): ?>
                  <br>💰 Set Price: <strong><?= number_format($c['price'],3) ?> OMR</strong>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Links -->
              <div style="display:flex;gap:8px;margin-top:12px;">
                <a href="requests.php?id=<?= $c['request_id'] ?>"
                   class="btn btn-dark btn-sm" style="flex:1;text-align:center;">📋 View Request</a>
                <form method="POST" style="flex:1;">
                  <input type="hidden" name="action"       value="delete">
                  <input type="hidden" name="complaint_id" value="<?= $c['complaint_id'] ?>">
                  <button type="submit" class="btn btn-sm" style="width:100%;
                          background:rgba(226,75,74,0.08);color:var(--danger);border:1px solid rgba(226,75,74,0.3);"
                          onclick="return confirm('Permanently delete this complaint?')">🗑️ Delete</button>
                </form>
              </div>
            </div>

          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
function toggleAdminCard(id) {
  const body  = document.getElementById('admbody-' + id);
  const arrow = document.getElementById('admarrow-' + id);
  const isOpen = body.style.display === 'block';
  body.style.display = isOpen ? 'none' : 'block';
  arrow.textContent  = isOpen ? '▼' : '▲';
}
// Auto-open open complaints
document.querySelectorAll('[id^="admbody-"]').forEach(body => {
  const id   = body.id.replace('admbody-','');
  const card = body.closest('[style*="border"]');
  if (card && card.style.borderColor.includes('226,75,74')) {
    body.style.display = 'block';
    const arrow = document.getElementById('admarrow-' + id);
    if (arrow) arrow.textContent = '▲';
  }
});
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>
