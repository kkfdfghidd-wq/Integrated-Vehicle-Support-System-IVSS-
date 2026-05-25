<?php
require_once __DIR__ . '/../includes/config.php';
if (!isGarageLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=garage');

$db       = getDB();
$garageId = $_SESSION['garage_id'];

$msg     = '';
$msgType = 'success';

// ── Subscription check (safe — won't crash if tables missing) ──
$hasSubscription = isSubscriptionActive($garageId);
$subStatus       = getSubscriptionStatus($garageId);

/* ══════════════════════════════════════
   POST: Garage responds to a complaint
══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action']      ?? '';
    $complaintId = intval($_POST['complaint_id'] ?? 0);

    // Verify this complaint belongs to this garage
    $verify = $db->prepare("SELECT complaint_id FROM complaints WHERE complaint_id=? AND garage_id=?");
    $verify->execute([$complaintId, $garageId]);

    if ($verify->fetch()) {
        if ($action === 'respond') {
            $note = sanitize($_POST['garage_note'] ?? '');
            if (strlen(trim($note)) < 3) {
                $msg = 'Please write your response.';
                $msgType = 'danger';
            } else {
                $db->prepare("UPDATE complaints SET garage_note=? WHERE complaint_id=?")
                   ->execute([$note, $complaintId]);
                $msg = '✅ Your response has been sent.';
            }
        }
    }
}

/* ══════════════════════════════════════
   FETCH COMPLAINTS
══════════════════════════════════════ */
$filterType   = sanitize($_GET['type']   ?? '');
$filterStatus = sanitize($_GET['status'] ?? '');

$sql = "SELECT c.*,
               u.full_name AS user_name, u.phone AS user_phone,
               r.service_type, r.vehicle_type, r.location_desc, r.price, r.status AS req_status
        FROM complaints c
        JOIN users u ON c.user_id = u.user_id
        JOIN service_requests r ON c.request_id = r.request_id
        WHERE c.garage_id = ?";
$params = [$garageId];

if (in_array($filterType, ['price','service']))
    { $sql .= " AND c.type=?"; $params[] = $filterType; }
if (in_array($filterStatus, ['open','reviewed','resolved','dismissed']))
    { $sql .= " AND c.status=?"; $params[] = $filterStatus; }

$sql .= " ORDER BY FIELD(c.status,'open','reviewed','resolved','dismissed'), c.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Counts
$countOpen    = $db->prepare("SELECT COUNT(*) FROM complaints WHERE garage_id=? AND status='open'");
$countOpen->execute([$garageId]);
$countOpen = $countOpen->fetchColumn();

$countTotal = $db->prepare("SELECT COUNT(*) FROM complaints WHERE garage_id=?");
$countTotal->execute([$garageId]);
$countTotal = $countTotal->fetchColumn();

$countPrice = $db->prepare("SELECT COUNT(*) FROM complaints WHERE garage_id=? AND type='price'");
$countPrice->execute([$garageId]);
$countPrice = $countPrice->fetchColumn();

$countService = $db->prepare("SELECT COUNT(*) FROM complaints WHERE garage_id=? AND type='service'");
$countService->execute([$garageId]);
$countService = $countService->fetchColumn();

$pageTitle = 'Complaints';
include __DIR__ . '/../includes/header.php';
?>

<style>
.complaint-card { border-radius:12px;overflow:hidden;border:1px solid var(--border); }
.complaint-card .c-header {
  padding:14px 18px;display:flex;gap:14px;align-items:flex-start;
  background:var(--white);cursor:pointer;
}
.complaint-card .c-body {
  display:none;border-top:1px solid var(--border);
  padding:16px 18px;background:var(--bg);
}
.complaint-card.open-card { border-color:rgba(212,168,67,0.4); }
.complaint-card.open-card .c-header { background:rgba(212,168,67,0.04); }

.c-type-badge {
  padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;
}
.c-type-price   { background:rgba(212,168,67,0.12);color:#a07820; }
.c-type-service { background:rgba(10,37,64,0.08);color:var(--navy); }

.c-status-badge { padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700; }
.c-status-open      { background:rgba(212,168,67,0.12);color:#a07820; }
.c-status-reviewed  { background:rgba(26,158,138,0.1);color:var(--teal); }
.c-status-resolved  { background:rgba(6,163,90,0.1);color:#06a35a; }
.c-status-dismissed { background:rgba(0,0,0,0.06);color:var(--muted); }

.respond-area textarea {
  width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;
  font-size:13px;font-family:inherit;resize:vertical;background:var(--white);color:var(--text);
  min-height:80px;box-sizing:border-box;
}
</style>

<div class="dash-layout">
  <div class="dash-sidebar">
    <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:8px;">
      <div style="font-size:12px;color:rgba(255,255,255,0.4);">Garage Panel</div>
      <div style="font-size:15px;font-weight:700;color:var(--gold);margin-top:4px;"><?= htmlspecialchars($_SESSION['garage_name']) ?></div>
    </div>
    <div class="sidebar-nav">
      <div class="sidebar-section">Operations</div>
      <a href="garage_dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="garage_requests.php"><span class="nav-icon">📋</span> All Requests</a>
      <a href="garage_payments.php"><span class="nav-icon">💳</span> Payments</a>
      <a href="garage_complaints.php" class="active">
        <span class="nav-icon">⚠️</span> Complaints
        <?php if ($countOpen > 0): ?>
        <span style="background:var(--danger);color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700;margin-left:4px;"><?= $countOpen ?></span>
        <?php endif; ?>
      </a>
	  <!-- ★ Subscription INSIDE Operations ★ -->
      <a href="garage_subscriptions.php">
        <span class="nav-icon">⭐</span> My Subscription
        <?php sidebarSubBadge($subStatus); ?>
      </a>
      <div class="sidebar-section">Account</div>
      <a href="garage_profile.php"><span class="nav-icon">⚙️</span> Settings</a>
      <a href="logout.php"><span class="nav-icon">🚪</span> Logout</a>
    </div>
  </div>

  <div class="dash-content">
    <div class="dash-header">
      <div>
        <div class="dash-title">Customer Complaints ⚠️</div>
        <div class="dash-sub">Review and respond to complaints submitted by your customers.</div>
      </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom:20px;"><?= $msg ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="metric-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-bottom:24px;">
      <div class="metric-card">
        <div class="metric-icon">📋</div>
        <div class="metric-label">Total Complaints</div>
        <div class="metric-value"><?= $countTotal ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">🟡</div>
        <div class="metric-label">Open</div>
        <div class="metric-value" style="color:var(--warning);"><?= $countOpen ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">💰</div>
        <div class="metric-label">Price Complaints</div>
        <div class="metric-value" style="color:#a07820;"><?= $countPrice ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">🔧</div>
        <div class="metric-label">Service Complaints</div>
        <div class="metric-value" style="color:var(--navy);"><?= $countService ?></div>
      </div>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;align-items:center;">
      <span style="font-size:12px;color:var(--muted);font-weight:600;">Type:</span>
      <?php foreach ([''=> 'All','price'=>'💰 Price','service'=>'🔧 Service'] as $v=>$l): ?>
      <a href="?type=<?= $v ?>&status=<?= $filterStatus ?>"
         style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;
                background:<?= $filterType===$v?'var(--navy)':'var(--white)' ?>;
                color:<?= $filterType===$v?'var(--white)':'var(--muted)' ?>;
                border:1px solid <?= $filterType===$v?'var(--navy)':'var(--border)' ?>;">
        <?= $l ?>
      </a>
      <?php endforeach; ?>
      <span style="width:1px;height:20px;background:var(--border);margin:0 4px;"></span>
      <span style="font-size:12px;color:var(--muted);font-weight:600;">Status:</span>
      <?php foreach ([''=> 'All','open'=>'🟡 Open','reviewed'=>'🔵 Under Review','resolved'=>'✅ Resolved','dismissed'=>'⛔ Dismissed'] as $v=>$l): ?>
      <a href="?type=<?= $filterType ?>&status=<?= $v ?>"
         style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;
                background:<?= $filterStatus===$v?'var(--navy)':'var(--white)' ?>;
                color:<?= $filterStatus===$v?'var(--white)':'var(--muted)' ?>;
                border:1px solid <?= $filterStatus===$v?'var(--navy)':'var(--border)' ?>;">
        <?= $l ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Complaints List -->
    <?php if (empty($complaints)): ?>
    <div class="card" style="text-align:center;padding:64px 32px;color:var(--muted);">
      <div style="font-size:56px;margin-bottom:16px;">✅</div>
      <div style="font-size:18px;font-weight:700;margin-bottom:8px;">No Complaints Found</div>
      <p>Great! No Complaints Found match your search criteria.</p>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
      <?php foreach ($complaints as $c): ?>
      <div class="complaint-card <?= $c['status']==='open'?'open-card':'' ?>" id="ccard-<?= $c['complaint_id'] ?>">

        <!-- Clickable Header -->
        <div class="c-header" onclick="toggleCard(<?= $c['complaint_id'] ?>)">
          <div style="font-size:24px;flex-shrink:0;">
            <?= $c['type']==='price'?'💰':'🔧' ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:5px;">
              <span class="c-type-badge c-type-<?= $c['type'] ?>">
                <?= $c['type']==='price'?'💰 Price Complaint':'🔧 Service Complaint' ?>
              </span>
              <span class="c-status-badge c-status-<?= $c['status'] ?>">
                <?= ['open'=>'🟡 Open','reviewed'=>'🔵 Under Review','resolved'=>'✅ Resolved','dismissed'=>'⛔ Dismissed'][$c['status']] ?>
              </span>
              <span style="font-size:12px;color:var(--muted);">
                IVSS-<?= str_pad($c['request_id'],4,'0',STR_PAD_LEFT) ?>
              </span>
              <?php if (!$c['garage_note'] && $c['status']==='open'): ?>
              <span style="background:var(--danger);color:#fff;padding:1px 8px;border-radius:8px;font-size:10px;font-weight:700;">Needs Your Response</span>
              <?php endif; ?>
            </div>
            <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($c['user_name']) ?></div>
            <div style="font-size:12px;color:var(--muted);margin-top:2px;">
              <?= ucfirst($c['service_type']) ?> · <?= date('d M Y, H:i', strtotime($c['created_at'])) ?>
              <?php if ($c['price']): ?>
              · Price: <strong style="color:var(--navy);"><?= number_format($c['price'],3) ?> OMR</strong>
              <?php endif; ?>
            </div>
          </div>
          <div style="flex-shrink:0;font-size:14px;color:var(--muted);padding-top:2px;" id="arrow-<?= $c['complaint_id'] ?>">▼</div>
        </div>

        <!-- Expandable Body -->
        <div class="c-body" id="cbody-<?= $c['complaint_id'] ?>">

          <!-- Complaint message -->
          <div style="margin-bottom:16px;">
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Customer Message</div>
            <div style="background:var(--white);border:1px solid var(--border);border-radius:8px;padding:12px 14px;font-size:13px;line-height:1.6;">
              "<?= htmlspecialchars($c['message']) ?>"
            </div>
          </div>

          <!-- Request details -->
          <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:12px;color:var(--muted);margin-bottom:16px;">
            <span>📞 <?= htmlspecialchars($c['user_phone']) ?></span>
            <span>📌 <?= htmlspecialchars(substr($c['location_desc'],0,40)) ?></span>
            <span>🚗 <?= ucfirst($c['vehicle_type']) ?></span>
            <span>🔖 Request Status: <strong><?= ucfirst(str_replace('_',' ',$c['req_status'])) ?></strong></span>
          </div>

          <!-- Admin note if any -->
          <?php if ($c['admin_note']): ?>
          <div style="background:rgba(26,158,138,0.07);border:1px solid rgba(26,158,138,0.2);
                      border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;">
            💬 <strong>Admin Note:</strong> <?= htmlspecialchars($c['admin_note']) ?>
          </div>
          <?php endif; ?>

          <!-- Garage response area -->
          <?php if ($c['garage_note']): ?>
          <div style="background:rgba(10,37,64,0.05);border:1px solid rgba(10,37,64,0.12);
                      border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px;">
            🔧 <strong>Your Response:</strong> <?= htmlspecialchars($c['garage_note']) ?>
          </div>
          <?php endif; ?>

          <!-- Respond form (only if not resolved/dismissed) -->
          <?php if (!in_array($c['status'],['resolved','dismissed'])): ?>
          <div class="respond-area">
            <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
              <?= $c['garage_note'] ? '✏️ Edit Response' : '💬 Reply to Complaint' ?>
            </div>
            <form method="POST" style="display:flex;flex-direction:column;gap:8px;">
              <input type="hidden" name="action"       value="respond">
              <input type="hidden" name="complaint_id" value="<?= $c['complaint_id'] ?>">
              <textarea name="garage_note" placeholder="Write your response here..."><?= htmlspecialchars($c['garage_note'] ?? '') ?></textarea>
              <div>
                <button type="submit" class="btn btn-teal btn-sm">📤 Send Response</button>
                <span style="font-size:11px;color:var(--muted);margin-left:10px;">Your response will be visible to the customer and admin</span>
              </div>
            </form>
          </div>
          <?php endif; ?>

        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
function toggleCard(id) {
  const body  = document.getElementById('cbody-' + id);
  const arrow = document.getElementById('arrow-' + id);
  const isOpen = body.style.display === 'block';
  body.style.display  = isOpen ? 'none' : 'block';
  arrow.textContent   = isOpen ? '▼' : '▲';
}
// Auto-open unresponded complaints
document.querySelectorAll('.open-card').forEach(card => {
  const id = card.id.replace('ccard-','');
  const body = document.getElementById('cbody-' + id);
  if (body) body.style.display = 'block';
  const arrow = document.getElementById('arrow-' + id);
  if (arrow) arrow.textContent = '▲';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
