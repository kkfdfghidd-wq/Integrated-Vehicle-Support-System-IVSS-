<?php
require_once __DIR__ . '/../includes/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/pages/login.php');

$db     = getDB();
$id     = intval($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

$msg     = '';
$msgType = 'success';

/* ══════════════════════════════════════
   SUBMIT COMPLAINT
══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $type    = sanitize($_POST['complaint_type'] ?? '');
    $message = sanitize($_POST['complaint_message'] ?? '');

    if (!in_array($type, ['price','service'])) {
        $msg = 'Please select a complaint type.';
        $msgType = 'danger';
    } elseif (strlen(trim($message)) < 10) {
        $msg = 'Please write complaint details (at least 10 characters).';
        $msgType = 'danger';
    } else {
        // Get garage_id for this request
        $grq = $db->prepare("SELECT garage_id FROM service_requests WHERE request_id=? AND user_id=?");
        $grq->execute([$id, $userId]);
        $grqRow = $grq->fetch();

        if (!$grqRow || !$grqRow['garage_id']) {
            $msg = 'You cannot submit a complaint for a request that has not been assigned yet.';
            $msgType = 'danger';
        } else {
            // Check: no duplicate complaint of same type for same request
            $dup = $db->prepare("SELECT complaint_id FROM complaints WHERE request_id=? AND user_id=? AND type=?");
            $dup->execute([$id, $userId, $type]);
            if ($dup->fetch()) {
                $msg = 'You have already submitted a complaint of this type for this request.';
                $msgType = 'danger';
            } else {
                $db->prepare("INSERT INTO complaints (request_id, user_id, garage_id, type, message) VALUES (?,?,?,?,?)")
                   ->execute([$id, $userId, $grqRow['garage_id'], $type, $message]);
                $msg = '✅ Your complaint has been submitted successfully. It will be reviewed by the garage and admin.';
            }
        }
    }
}

// Fetch request
$stmt = $db->prepare("
    SELECT r.*,
           u.full_name AS user_name, u.phone AS user_phone,
           g.garage_name, g.phone AS garage_phone, g.location AS garage_location,
           p.status AS payment_status, p.amount, p.invoice_number, p.payment_id
    FROM   service_requests r
    JOIN   users u   ON u.user_id   = r.user_id
    LEFT   JOIN garages g ON g.garage_id = r.garage_id
    LEFT   JOIN payments p ON p.request_id = r.request_id
    WHERE  r.request_id = ? AND r.user_id = ?
");
$stmt->execute([$id, $userId]);
$req = $stmt->fetch();

if (!$req) redirect(SITE_URL . '/pages/dashboard.php');

// Fetch user's complaints for this request
$myComplaints = $db->prepare("
    SELECT c.*, g.garage_name
    FROM complaints c
    JOIN garages g ON c.garage_id = g.garage_id
    WHERE c.request_id = ? AND c.user_id = ?
    ORDER BY c.created_at DESC
");
$myComplaints->execute([$id, $userId]);
$myComplaints = $myComplaints->fetchAll();

$alreadyComplainedTypes = array_column($myComplaints, 'type');

$stepMap     = ['pending'=>0,'accepted'=>1,'in_progress'=>2,'completed'=>3];
$currentStep = $stepMap[$req['status']] ?? 0;

$pageTitle = 'Track Request #' . $id;
include __DIR__ . '/../includes/header.php';
?>

<style>
/* Complaint form */
.complaint-section {
  border:1.5px solid rgba(226,75,74,0.2);
  border-radius:12px;background:rgba(226,75,74,0.03);
  padding:20px 22px;
}
.complaint-type-grid { display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:12px 0; }
.ctype-card {
  border:1.5px solid var(--border);border-radius:10px;padding:14px;
  cursor:pointer;transition:all 0.18s;background:var(--white);
  display:flex;gap:10px;align-items:flex-start;
}
.ctype-card:hover { border-color:var(--danger);background:rgba(226,75,74,0.04); }
.ctype-card.selected { border-color:var(--danger);background:rgba(226,75,74,0.07); }
.ctype-card input { display:none; }
.ctype-icon { font-size:22px;flex-shrink:0;margin-top:2px; }
.ctype-title { font-size:13px;font-weight:700;margin-bottom:3px; }
.ctype-desc  { font-size:12px;color:var(--muted);line-height:1.4; }
/* Status bar for complaints */
.c-status-badge {
  padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;display:inline-block;
}
.c-status-open      { background:rgba(212,168,67,0.12);color:#a07820; }
.c-status-reviewed  { background:rgba(26,158,138,0.1);color:var(--teal); }
.c-status-resolved  { background:rgba(6,163,90,0.1);color:#06a35a; }
.c-status-dismissed { background:rgba(0,0,0,0.06);color:var(--muted); }
/* Price display */
.price-display-card {
  background:var(--navy);color:var(--white);border-radius:12px;padding:16px 20px;
  display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;
}
</style>

<section style="padding:60px 2rem;">
  <div class="container" style="max-width:720px;">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;flex-wrap:wrap;gap:12px;">
      <div>
        <div class="section-tag">Live Tracking</div>
        <div class="section-title" style="margin-bottom:0;">
          Request #IVSS-<?= str_pad($id,4,'0',STR_PAD_LEFT) ?>
        </div>
      </div>
      <span class="badge badge-<?= $req['status'] ?>" style="font-size:14px;padding:8px 18px;">
        <?= ucfirst(str_replace('_',' ',$req['status'])) ?>
      </span>
    </div>

    <!-- Alert -->
    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom:20px;"><?= $msg ?></div>
    <?php endif; ?>

    <!-- Request Details -->
    <div class="card mb-24">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div>
          <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Service</div>
          <div style="font-size:16px;font-weight:600;"><?= ucfirst($req['service_type']) ?></div>
        </div>
        <div>
          <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Vehicle</div>
          <div style="font-size:16px;font-weight:600;"><?= ucfirst(str_replace('_',' ',$req['vehicle_type'])) ?></div>
        </div>
        <div>
          <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Your Location</div>
          <div style="font-size:15px;"><?= htmlspecialchars($req['location_desc']) ?></div>
        </div>
        <div>
          <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Submitted</div>
          <div style="font-size:15px;"><?= date('d M Y, H:i', strtotime($req['created_at'])) ?></div>
        </div>
      </div>
      <?php if ($req['notes']): ?>
      <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
        <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px;">Notes</div>
        <p style="font-size:14px;color:var(--muted);"><?= htmlspecialchars($req['notes']) ?></p>
      </div>
      <?php endif; ?>
    </div>

    <!-- Garage Card -->
    <?php if ($req['garage_name']): ?>
    <div class="card mb-24" style="background:var(--navy);color:var(--white);">
      <div style="display:flex;align-items:center;gap:16px;">
        <div style="font-size:36px;">🔧</div>
        <div>
          <div style="font-size:18px;font-weight:700;"><?= htmlspecialchars($req['garage_name']) ?></div>
          <div style="font-size:14px;color:rgba(255,255,255,0.6);">📍 <?= htmlspecialchars($req['garage_location']) ?></div>
          <div style="font-size:14px;color:var(--gold);margin-top:4px;">📞 <?= htmlspecialchars($req['garage_phone']) ?></div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="card mb-24" style="background:rgba(212,168,67,0.08);border:1px solid rgba(212,168,67,0.3);">
      <div style="display:flex;align-items:center;gap:16px;">
        <div style="font-size:36px;">📢</div>
        <div>
          <div style="font-size:16px;font-weight:700;color:var(--text);">Waiting for a Garage to Accept</div>
          <div style="font-size:14px;color:var(--muted);margin-top:4px;">Your request has been broadcast to all active garages.</div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ══ PRICE DISPLAY ══ -->
    <?php if ($req['garage_name'] && in_array($req['status'],['accepted','in_progress','completed'])): ?>
    <div class="mb-24">
      <?php if ($req['price'] > 0): ?>
      <div class="price-display-card">
        <div>
          <div style="font-size:12px;color:rgba(255,255,255,0.5);margin-bottom:4px;">Service price set by the garage</div>
          <div style="font-size:28px;font-weight:700;color:var(--gold);">
            <?= number_format($req['price'],3) ?> <span style="font-size:16px;">OMR</span>
          </div>
          <?php if ($req['price_set_at']): ?>
          <div style="font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;">
            Set on <?= date('d M Y, H:i', strtotime($req['price_set_at'])) ?>
          </div>
          <?php endif; ?>
        </div>
        <div>
          <?php if ($req['status'] === 'completed' && $req['payment_status'] === 'pending'): ?>
          <a href="payment.php?request_id=<?= $id ?>" class="btn btn-primary">💳 Pay Now</a>
          <?php elseif ($req['payment_status'] === 'paid'): ?>
          <span style="background:rgba(26,158,138,0.2);color:var(--teal);padding:8px 16px;border-radius:8px;font-weight:700;">✅ Paid</span>
          <?php else: ?>
          <span style="font-size:13px;color:rgba(255,255,255,0.5);">Payment will be processed after the service is completed</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Price too high? show complaint option -->
      <?php if (!in_array('price', $alreadyComplainedTypes)): ?>
      <div style="background:rgba(212,168,67,0.06);border:1px solid rgba(212,168,67,0.2);
                  border-radius:8px;padding:10px 14px;margin-top:10px;
                  display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
        <span style="font-size:13px;color:var(--muted);">⚠️ Do you think the price is too high?</span>
        <button onclick="openComplaint('price')" class="btn btn-sm"
                style="background:transparent;border:1px solid var(--danger);color:var(--danger);padding:5px 14px;font-size:12px;">
          Submit a Price Complaint
        </button>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div style="background:rgba(212,168,67,0.06);border:1px dashed rgba(212,168,67,0.3);
                  border-radius:10px;padding:14px 18px;text-align:center;">
        <div style="font-size:13px;color:var(--muted);">
          ⏳ The garage has not set the price yet — it will appear here soon.
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Progress Tracker -->
    <div class="card mb-24">
      <h3 style="margin-bottom:24px;font-size:16px;font-weight:700;">Service Progress</h3>
      <div class="track-steps">
        <?php
        $steps = [
          ['Request Submitted', 'Your request has been sent to available garages.'],
          ['Garage Confirmed',  'A garage has accepted and is preparing to help.'],
          ['On the Way',        'A technician has been dispatched to your location.'],
          ['Service Complete',  'The service has been completed successfully.'],
        ];
        foreach ($steps as $i => [$title, $desc]):
          $dotClass = $i < $currentStep ? 'dot-done' : ($i === $currentStep ? 'dot-active' : 'dot-wait');
          $symbol   = $i < $currentStep ? '✓' : ($i === $currentStep ? '→' : ($i+1));
        ?>
        <div class="track-step">
          <div class="track-dot <?= $dotClass ?>"><?= $symbol ?></div>
          <h5 style="<?= $i > $currentStep ? 'color:var(--muted);' : '' ?>"><?= $title ?></h5>
          <p style="<?= $i > $currentStep ? 'opacity:0.5;' : '' ?>"><?= $desc ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ══ COMPLAINT SECTION (after service started) ══ -->
    <?php if ($req['garage_name'] && in_array($req['status'],['in_progress','completed'])): ?>
    <div class="card mb-24">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">⚠️ Submit a Complaint</h3>
      <p style="font-size:13px;color:var(--muted);margin-bottom:20px;">
        If you have an issue with the price or service quality, you can submit a complaint that will be sent directly to the garage and admin.
      </p>

      <?php if (!empty($myComplaints)): ?>
      <!-- Existing complaints -->
      <div style="margin-bottom:20px;">
        <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">Your Previous Complaints</div>
        <?php foreach ($myComplaints as $c): ?>
        <div style="background:var(--bg);border-radius:8px;padding:12px 14px;margin-bottom:8px;border:1px solid var(--border);">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:6px;">
            <span style="font-size:13px;font-weight:700;">
              <?= $c['type']==='price'?'💰 Price Complaint':'🔧 Service Quality Complaint' ?>
            </span>
            <div style="display:flex;gap:6px;align-items:center;">
              <span class="c-status-badge c-status-<?= $c['status'] ?>">
                <?= ['open'=>'🟡 Open','reviewed'=>'🔵 Under Review','resolved'=>'✅ Resolved','dismissed'=>'⛔ Dismissed'][$c['status']] ?? $c['status'] ?>
              </span>
              <span style="font-size:11px;color:var(--muted);"><?= date('d M Y', strtotime($c['created_at'])) ?></span>
            </div>
          </div>
          <p style="font-size:13px;color:var(--muted);margin:0 0 6px;">"<?= htmlspecialchars($c['message']) ?>"</p>
          <?php if ($c['admin_note']): ?>
          <div style="background:rgba(26,158,138,0.08);border-radius:6px;padding:7px 10px;font-size:12px;color:var(--teal);">
            💬 <strong>Admin Response:</strong> <?= htmlspecialchars($c['admin_note']) ?>
          </div>
          <?php endif; ?>
          <?php if ($c['garage_note']): ?>
          <div style="background:rgba(10,37,64,0.06);border-radius:6px;padding:7px 10px;font-size:12px;color:var(--navy);margin-top:5px;">
            🔧 <strong>Garage Response:</strong> <?= htmlspecialchars($c['garage_note']) ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- New complaint form -->
      <?php
      $canComplainPrice   = !in_array('price',   $alreadyComplainedTypes);
      $canComplainService = !in_array('service', $alreadyComplainedTypes);
      ?>
      <?php if ($canComplainPrice || $canComplainService): ?>
      <div class="complaint-section" id="complaintForm">
        <form method="POST">
          <input type="hidden" name="submit_complaint" value="1">

          <label style="font-size:13px;font-weight:700;display:block;margin-bottom:10px;">Complaint Type</label>
          <div class="complaint-type-grid">
            <?php if ($canComplainPrice): ?>
            <label class="ctype-card" id="ctype-price" onclick="selectType('price')">
              <input type="radio" name="complaint_type" value="price" required>
              <span class="ctype-icon">💰</span>
              <div>
                <div class="ctype-title">Price Too High</div>
                <div class="ctype-desc">You believe the service price set is unfair or excessive.</div>
              </div>
            </label>
            <?php endif; ?>
            <?php if ($canComplainService): ?>
            <label class="ctype-card" id="ctype-service" onclick="selectType('service')">
              <input type="radio" name="complaint_type" value="service" required>
              <span class="ctype-icon">🔧</span>
              <div>
                <div class="ctype-title">Service Quality</div>
                <div class="ctype-desc">The service provided was unsatisfactory or incomplete.</div>
              </div>
            </label>
            <?php endif; ?>
          </div>

          <div class="form-group" style="margin-bottom:16px;">
            <label style="font-size:13px;font-weight:600;margin-bottom:6px;display:block;">Complaint Details</label>
            <textarea name="complaint_message" class="form-control" rows="4"
                      placeholder="Describe the issue in detail... (at least 10 characters)" required
                      style="font-size:14px;resize:vertical;"></textarea>
          </div>

          <div style="background:rgba(212,168,67,0.06);border:1px solid rgba(212,168,67,0.2);
                      border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:var(--muted);">
            📨 Your complaint will be sent immediately to the <strong>garage</strong> and <strong>IVSS admin</strong> for review.
          </div>

          <button type="submit" class="btn btn-sm"
                  style="background:var(--danger);color:#fff;padding:9px 24px;font-size:14px;border:none;border-radius:8px;font-weight:600;">
            📤 Submit Complaint
          </button>
        </form>
      </div>
      <?php else: ?>
      <div style="text-align:center;padding:20px;color:var(--muted);font-size:13px;">
        ✅ You have submitted complaints for all available types for this request.
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Payment -->
    <?php if ($req['status'] === 'completed'): ?>
    <div class="card mb-24">
      <h3 style="margin-bottom:16px;font-size:16px;font-weight:700;">Payment</h3>
      <?php if ($req['payment_status'] === 'paid'): ?>
      <div class="alert alert-success">
        ✅ Payment received — Invoice: <strong><?= $req['invoice_number'] ?></strong>
        — Amount: <strong><?= number_format($req['amount'],3) ?> OMR</strong>
      </div>
      <?php elseif ($req['payment_status'] === 'pending'): ?>
      <p style="margin-bottom:16px;color:var(--muted);">Service complete. Please pay to receive your invoice.</p>
      <a href="payment.php?request_id=<?= $id ?>" class="btn btn-primary">💳 Pay Now — <?= number_format($req['amount'],3) ?> OMR</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <a href="dashboard.php" class="btn btn-dark btn-sm">← Back to Dashboard</a>
  </div>
</section>

<script>
function selectType(type) {
  document.querySelectorAll('.ctype-card').forEach(el => el.classList.remove('selected'));
  const card = document.getElementById('ctype-' + type);
  if (card) {
    card.classList.add('selected');
    card.querySelector('input[type=radio]').checked = true;
  }
}

function openComplaint(type) {
  const form = document.getElementById('complaintForm');
  if (form) {
    form.scrollIntoView({behavior:'smooth', block:'start'});
    setTimeout(() => selectType(type), 400);
  }
}

// Auto-refresh every 20 seconds if request is active
<?php if (in_array($req['status'],['pending','accepted','in_progress'])): ?>
setTimeout(() => location.reload(), 20000);
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
