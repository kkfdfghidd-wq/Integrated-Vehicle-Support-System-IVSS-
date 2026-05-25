<?php
require_once __DIR__ . '/../includes/config.php';
if (!isGarageLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=garage');

$db       = getDB();
$garageId = (int)$_SESSION['garage_id'];

// Verify garage row exists
$gRow = $db->prepare("SELECT garage_id FROM garages WHERE garage_id=?");
$gRow->execute([$garageId]);
if (!$gRow->fetch()) { session_destroy(); redirect(SITE_URL.'/pages/login.php?role=garage'); }

// Subscription info (safe even if tables missing)
$hasSubscription = isSubscriptionActive($garageId);
$subStatus       = getSubscriptionStatus($garageId);

$msg     = '';
$msgType = 'success';

// ══ POST — set price ══
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_price'])) {
    if (!$hasSubscription) {
        $msg = '❌ Active subscription required.'; $msgType = 'danger';
    } else {
        $reqId = (int)$_POST['request_id'];
        $price = floatval($_POST['price'] ?? 0);
        if ($price <= 0) {
            $msg = 'Price must be > 0 OMR.'; $msgType = 'danger';
        } else {
            $chk = $db->prepare("SELECT request_id FROM service_requests WHERE request_id=? AND garage_id=?");
            $chk->execute([$reqId,$garageId]);
            if ($chk->fetch()) {
                $db->prepare("UPDATE service_requests SET price=?,price_set_at=NOW() WHERE request_id=?")
                   ->execute([$price,$reqId]);
                $msg = '✅ Price set: '.number_format($price,3).' OMR';
            }
        }
    }
}

// ══ POST — update status ══
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!$hasSubscription) {
        $msg = '❌ You need an active subscription to accept or update requests.';
        $msgType = 'danger';
    } else {
        $reqId     = (int)$_POST['request_id'];
        $newStatus = sanitize($_POST['new_status']);
        $allowed   = ['accepted','in_progress','completed','cancelled'];

        if (in_array($newStatus, $allowed)) {
            if ($newStatus === 'accepted') {
                $chk = $db->prepare("SELECT garage_id,status FROM service_requests WHERE request_id=?");
                $chk->execute([$reqId]);
                $row = $chk->fetch();
                if ($row && $row['status']==='pending' &&
                    ($row['garage_id']===null || $row['garage_id']==$garageId)) {
                    $db->prepare("UPDATE service_requests SET status='accepted',garage_id=? WHERE request_id=?")
                       ->execute([$garageId,$reqId]);
                }
            } else {
                $db->prepare("UPDATE service_requests SET status=? WHERE request_id=? AND garage_id=?")
                   ->execute([$newStatus,$reqId,$garageId]);
            }
            if ($newStatus === 'completed') {
                $ex = $db->prepare("SELECT payment_id FROM payments WHERE request_id=?");
                $ex->execute([$reqId]);
                if (!$ex->fetch()) {
                    $rr = $db->prepare("SELECT user_id,price FROM service_requests WHERE request_id=?");
                    $rr->execute([$reqId]);
                    $rRow = $rr->fetch();
                    if ($rRow) {
                        $amount = ($rRow['price'] > 0) ? $rRow['price'] : 15.000;
                        $db->prepare("INSERT INTO payments (request_id,user_id,amount,status,invoice_number) VALUES (?,?,?,'pending',?)")
                           ->execute([$reqId,$rRow['user_id'],$amount,generateInvoiceNumber()]);
                    }
                }
            }
        }
        redirect(SITE_URL.'/pages/garage_requests.php');
    }
}

// ── Build query (ALWAYS load requests) ──
$filterStatus  = sanitize($_GET['status'] ?? '');
$validStatuses = ['pending','accepted','in_progress','completed','cancelled'];

$sql    = "SELECT r.*,
                  u.full_name AS user_name, u.phone AS user_phone, u.email AS user_email,
                  p.status    AS pay_status, p.amount AS pay_amount,
                  (SELECT COUNT(*) FROM complaints c WHERE c.request_id=r.request_id) AS complaint_count
           FROM   service_requests r
           INNER  JOIN users    u ON u.user_id    = r.user_id
           LEFT   JOIN payments p ON p.request_id = r.request_id
           WHERE  (r.garage_id IS NULL AND r.status='pending')
              OR  (r.garage_id = ?)";
$params = [$garageId];

if (in_array($filterStatus, $validStatuses)) {
    $sql    .= " AND r.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY
            CASE r.status
              WHEN 'pending'     THEN 1 WHEN 'accepted'    THEN 2
              WHEN 'in_progress' THEN 3 WHEN 'completed'   THEN 4
              WHEN 'cancelled'   THEN 5 ELSE 6
            END, r.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Tab counts
$cntStmt = $db->prepare(
    "SELECT r.status, COUNT(*) AS cnt FROM service_requests r
     WHERE (r.garage_id IS NULL AND r.status='pending') OR r.garage_id=?
     GROUP BY r.status"
);
$cntStmt->execute([$garageId]);
$statusCounts = [];
foreach ($cntStmt->fetchAll() as $row) $statusCounts[$row['status']] = (int)$row['cnt'];
$statusCounts[''] = array_sum($statusCounts);

// Complaints badge
$openComplaints = 0;
try {
    $oc = $db->prepare("SELECT COUNT(*) FROM complaints WHERE garage_id=? AND status='open'");
    $oc->execute([$garageId]);
    $openComplaints = (int)$oc->fetchColumn();
} catch (Exception $e) {}

$pageTitle = 'All Requests';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* price */
.price-badge{
  display:inline-flex;align-items:center;gap:5px;
  background:rgba(26,158,138,.1);color:var(--teal);
  border:1px solid rgba(26,158,138,.3);
  padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;
}
.price-wrap{
  background:rgba(10,37,64,.04);border:1px dashed var(--border);
  border-radius:8px;padding:12px 14px;margin-top:10px;
}
.price-wrap label{font-size:12px;font-weight:700;color:var(--muted);display:block;margin-bottom:6px;}
.price-row{display:flex;gap:8px;align-items:center;}
.price-row input[type=number]{
  padding:7px 10px;border:1px solid var(--border);border-radius:7px;
  font-size:14px;font-weight:700;background:var(--white);color:var(--navy);width:130px;
}
/* complaint */
.cpill{
  display:inline-flex;align-items:center;gap:4px;
  background:rgba(226,75,74,.1);color:var(--danger);
  border:1px solid rgba(226,75,74,.25);
  padding:2px 9px;border-radius:12px;font-size:11px;font-weight:700;
}
/* sub notice */
.sub-notice{
  display:flex;align-items:center;gap:16px;flex-wrap:wrap;
  padding:14px 20px;border-radius:12px;margin-bottom:20px;border:1px solid;
}
.sub-notice.warn-none,.sub-notice.warn-expired{
  background:rgba(226,75,74,.06);border-color:rgba(226,75,74,.3);
}
.sub-notice.warn-expiring{
  background:rgba(212,168,67,.07);border-color:rgba(212,168,67,.35);
}
.sub-notice .icon{font-size:26px;flex-shrink:0;}
.sub-notice .t{font-size:14px;font-weight:700;color:var(--navy);}
.sub-notice .s{font-size:12px;color:var(--muted);margin-top:2px;}
.sub-notice a.sbtn{
  margin-left:auto;background:var(--teal);color:#fff;
  padding:9px 20px;border-radius:9px;font-weight:700;font-size:13px;
  text-decoration:none;white-space:nowrap;flex-shrink:0;
}
/* locked action */
.locked{
  background:rgba(226,75,74,.06);border:1px dashed rgba(226,75,74,.3);
  border-radius:9px;padding:10px 12px;text-align:center;
  font-size:12px;font-weight:700;color:var(--danger);min-width:130px;
}
</style>

<div class="dash-layout">

  <!-- ══ Sidebar ══ -->
  <div class="dash-sidebar">
    <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:8px;">
      <div style="font-size:12px;color:rgba(255,255,255,0.4);">Garage Panel</div>
      <div style="font-size:15px;font-weight:700;color:var(--gold);margin-top:4px;">
        <?= htmlspecialchars($_SESSION['garage_name']) ?>
      </div>
    </div>
    <div class="sidebar-nav">
      <div class="sidebar-section">Operations</div>
      <a href="garage_dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="garage_requests.php" class="active"><span class="nav-icon">📋</span> All Requests</a>
      <a href="garage_payments.php"><span class="nav-icon">💳</span> Payments</a>
      <a href="garage_complaints.php">
        <span class="nav-icon">⚠️</span> Complaints
        <?php if ($openComplaints > 0): ?>
        <span style="background:var(--danger);color:#fff;border-radius:10px;
                     padding:1px 7px;font-size:11px;font-weight:700;margin-left:4px;">
          <?= $openComplaints ?>
        </span>
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

  <!-- ══ Content ══ -->
  <div class="dash-content">

    <div class="dash-header">
      <div>
        <div class="dash-title">All Requests 📋</div>
        <div class="dash-sub">View all requests — subscribe to start accepting them.</div>
      </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom:18px;"><?= $msg ?></div>
    <?php endif; ?>

    <!-- Subscription notice -->
    <?php if (!$hasSubscription): ?>
    <div class="sub-notice warn-<?= $subStatus['status'] === 'expired' ? 'expired' : 'none' ?>">
      <div class="icon">🔒</div>
      <div>
        <div class="t"><?= $subStatus['status']==='expired' ? 'Subscription Expired' : 'No Active Subscription' ?></div>
        <div class="s">Requests are visible below — subscribe to accept them.</div>
      </div>
      <a href="garage_subscriptions.php" class="sbtn">⭐ Subscribe Now →</a>
    </div>
    <?php elseif ($subStatus['status'] === 'expiring'): ?>
    <div class="sub-notice warn-expiring">
      <div class="icon">⚠️</div>
      <div>
        <div class="t">Subscription Expiring Soon</div>
        <div class="s"><?= htmlspecialchars($subStatus['message']) ?></div>
      </div>
      <a href="garage_subscriptions.php" class="sbtn">🔄 Renew →</a>
    </div>
    <?php endif; ?>

    <!-- Filter tabs -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
      <?php
      $tabs = [''=>'All','pending'=>'Pending','accepted'=>'Accepted',
               'in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'];
      foreach ($tabs as $s => $label):
        $active = ($filterStatus === $s);
        $cnt    = $statusCounts[$s] ?? 0;
      ?>
      <a href="?status=<?= $s ?>"
         style="padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;
                text-decoration:none;display:inline-flex;align-items:center;gap:6px;
                background:<?= $active?'var(--navy)':'var(--white)' ?>;
                color:<?= $active?'var(--white)':'var(--muted)' ?>;
                border:1px solid <?= $active?'var(--navy)':'var(--border)' ?>;">
        <?= $label ?>
        <?php if ($cnt > 0): ?>
        <span style="background:<?= $active?'rgba(255,255,255,.2)':'var(--border)' ?>;
                     color:<?= $active?'#fff':'var(--text)' ?>;
                     padding:1px 7px;border-radius:10px;font-size:11px;"><?= $cnt ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- ══ Request cards ══ -->
    <?php if (empty($requests)): ?>
    <div class="card" style="text-align:center;padding:64px 32px;">
      <div style="font-size:56px;margin-bottom:16px;">📭</div>
      <div style="font-size:18px;font-weight:700;margin-bottom:8px;">No requests found</div>
      <p style="color:var(--muted);">
        <?= $filterStatus
            ? 'No <strong>'.str_replace('_',' ',$filterStatus).'</strong> requests.
               <a href="?" style="color:var(--teal);">View all →</a>'
            : 'No service requests yet.' ?>
      </p>
    </div>

    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:16px;">
      <?php foreach ($requests as $r):
        $isBroadcast = ($r['garage_id'] === null);
        $priceSet    = ($r['price'] > 0);
        $serviceIcons = ['towing'=>'🚛','battery'=>'🔋','tire'=>'🛞','fuel'=>'⛽',
                         'lockout'=>'🔑','repair'=>'🔧','other'=>'🔩'];
      ?>
      <div class="card" style="<?= $isBroadcast?'border-left:3px solid var(--gold);':'' ?>">
        <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">

          <!-- Icon -->
          <div style="width:52px;height:52px;border-radius:12px;flex-shrink:0;
                      background:<?= $isBroadcast?'rgba(212,168,67,.12)':'rgba(26,158,138,.1)' ?>;
                      display:flex;align-items:center;justify-content:center;font-size:24px;">
            <?= $serviceIcons[$r['service_type']] ?? '🔧' ?>
          </div>

          <!-- Details -->
          <div style="flex:1;min-width:220px;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
              <span style="font-weight:700;font-size:15px;">
                IVSS-<?= str_pad($r['request_id'],4,'0',STR_PAD_LEFT) ?>
                · <?= ucfirst($r['service_type']) ?>
              </span>
              <span class="badge badge-<?= $r['status'] ?>">
                <?= ucfirst(str_replace('_',' ',$r['status'])) ?>
              </span>
              <?php if ($isBroadcast): ?>
              <span style="background:rgba(212,168,67,.15);color:#a07820;
                           padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;">
                📢 Open to All
              </span>
              <?php endif; ?>
              <?php if ($r['complaint_count'] > 0): ?>
              <span class="cpill">⚠️ <?= $r['complaint_count'] ?> complaint(s)</span>
              <?php endif; ?>
            </div>

            <div style="font-size:13px;color:var(--muted);display:flex;flex-wrap:wrap;gap:8px;margin-bottom:4px;">
              <span>👤 <strong style="color:var(--text);"><?= htmlspecialchars($r['user_name']) ?></strong></span>
              <span>📞 <?= htmlspecialchars($r['user_phone']) ?></span>
              <span>✉️ <?= htmlspecialchars($r['user_email']) ?></span>
            </div>
            <div style="font-size:13px;color:var(--muted);margin-bottom:6px;">
              📌 <?= htmlspecialchars($r['location_desc']) ?>
              · 🚗 <?= ucfirst(str_replace('_',' ',$r['vehicle_type'])) ?>
            </div>
            <?php if (!empty($r['notes'])): ?>
            <div style="font-size:13px;color:var(--muted);font-style:italic;
                        background:var(--bg);border-radius:6px;padding:6px 10px;margin-bottom:6px;">
              💬 "<?= htmlspecialchars(substr($r['notes'],0,120)) ?><?= strlen($r['notes'])>120?'…':'' ?>"
            </div>
            <?php endif; ?>
            <div style="font-size:12px;color:var(--muted);">
              🕐 <?= date('d M Y, H:i', strtotime($r['created_at'])) ?>
            </div>

            <!-- Price section (only when subscribed & request is mine) -->
            <?php if ($hasSubscription && !$isBroadcast && in_array($r['status'],['accepted','in_progress','completed'])): ?>
            <div class="price-wrap">
              <?php if ($priceSet): ?>
              <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <div>
                  <span class="price-badge">💰 <?= number_format($r['price'],3) ?> OMR</span>
                  <?php if ($r['price_set_at']): ?>
                  <span style="font-size:11px;color:var(--muted);margin-left:8px;">
                    Set <?= date('d M, H:i', strtotime($r['price_set_at'])) ?>
                  </span>
                  <?php endif; ?>
                  <?php if ($r['pay_status']==='paid'): ?>
                  <span class="badge badge-paid" style="margin-left:6px;font-size:11px;">✓ Paid</span>
                  <?php endif; ?>
                </div>
                <?php if ($r['status'] !== 'completed'): ?>
                <form method="POST" style="display:flex;gap:7px;align-items:center;">
                  <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                  <div class="price-row">
                    <input type="number" name="price" step="0.001" min="0.001" max="9999"
                           value="<?= number_format($r['price'],3,'.','') ?>" required>
                    <span style="font-size:13px;color:var(--muted);font-weight:600;">OMR</span>
                  </div>
                  <button type="submit" name="set_price" class="btn btn-teal btn-sm">Edit</button>
                </form>
                <?php endif; ?>
              </div>
              <?php else: ?>
              <label>💰 Set Service Price <span style="color:var(--danger);">*</span></label>
              <form method="POST">
                <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                <div class="price-row">
                  <input type="number" name="price" step="0.001" min="0.001" max="9999"
                         placeholder="0.000" required>
                  <span style="font-size:13px;color:var(--muted);font-weight:600;">OMR</span>
                  <button type="submit" name="set_price" class="btn btn-primary btn-sm">💾 Set</button>
                </div>
                <div style="font-size:11px;color:var(--muted);margin-top:5px;">
                  ⚠️ Default: 15.000 OMR if not set before completing
                </div>
              </form>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- ══ Action buttons ══ -->
          <div style="flex-shrink:0;min-width:140px;">
            <?php
            $next = [
              'pending'     => ['accepted'=>'✅ Accept',   'cancelled'=>'❌ Decline'],
              'accepted'    => ['in_progress'=>'🔧 Start', 'cancelled'=>'❌ Cancel'],
              'in_progress' => ['completed'=>'✔️ Complete','cancelled'=>'❌ Cancel'],
            ];
            $opts = $next[$r['status']] ?? [];
            if ($r['status']==='pending' && !$isBroadcast && $r['garage_id'] != $garageId) $opts = [];
            ?>

            <?php if (in_array($r['status'],['completed','cancelled'])): ?>
            <div style="font-size:13px;font-weight:600;text-align:center;padding:8px;border-radius:8px;
                        background:<?= $r['status']==='completed'?'rgba(26,158,138,.1)':'rgba(226,75,74,.08)' ?>;
                        color:<?= $r['status']==='completed'?'var(--teal)':'var(--danger)' ?>;">
              <?= $r['status']==='completed' ? '✅ Done' : '❌ Cancelled' ?>
            </div>

            <?php elseif (!empty($opts) && !$hasSubscription && $r['status']==='pending'): ?>
            <!-- No subscription — show locked -->
            <div class="locked">
              🔒 Subscribe to<br>accept requests
              <div style="margin-top:6px;">
                <a href="garage_subscriptions.php"
                   style="font-size:11px;color:var(--teal);font-weight:700;text-decoration:none;">
                  Subscribe →
                </a>
              </div>
            </div>

            <?php elseif (!empty($opts)): ?>
            <form method="POST" style="display:flex;flex-direction:column;gap:8px;">
              <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
              <input type="hidden" name="new_status"  value="">
              <?php foreach ($opts as $sv => $bl): ?>
              <button type="submit" name="update_status" value="1"
                      onclick="this.form.querySelector('[name=new_status]').value='<?= $sv ?>'"
                      class="btn btn-sm"
                      style="<?= $sv==='cancelled'
                          ? 'background:transparent;border:1px solid var(--danger);color:var(--danger);'
                          : 'background:var(--teal);color:#fff;'
                      ?>width:100%;">
                <?= $bl ?>
              </button>
              <?php endforeach; ?>
            </form>
            <?php endif; ?>
          </div>

        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
