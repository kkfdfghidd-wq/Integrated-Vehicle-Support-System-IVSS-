<?php
require_once __DIR__ . '/../includes/config.php';
if (!isGarageLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=garage');

$db       = getDB();
$garageId = (int)$_SESSION['garage_id'];

// ── Subscription check (safe — won't crash if tables missing) ──
$hasSubscription = isSubscriptionActive($garageId);
$subStatus       = getSubscriptionStatus($garageId);

// ── Handle status update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!$hasSubscription) {
        // Redirect to subscribe instead of silently blocking
        redirect(SITE_URL . '/pages/garage_subscriptions.php');
    }
    $reqId     = (int)$_POST['request_id'];
    $newStatus = sanitize($_POST['new_status']);
    $allowed   = ['accepted','in_progress','completed','cancelled'];
    if (in_array($newStatus, $allowed)) {
        $db->prepare("UPDATE service_requests SET status=? WHERE request_id=? AND garage_id=?")
           ->execute([$newStatus, $reqId, $garageId]);
        if ($newStatus === 'completed') {
            $ex = $db->prepare("SELECT payment_id FROM payments WHERE request_id=?");
            $ex->execute([$reqId]);
            if (!$ex->fetch()) {
                $rr = $db->prepare("SELECT user_id FROM service_requests WHERE request_id=?");
                $rr->execute([$reqId]);
                $row = $rr->fetch();
                if ($row) {
                    $db->prepare("INSERT INTO payments (request_id,user_id,amount,status,invoice_number) VALUES (?,?,?,?,?)")
                       ->execute([$reqId, $row['user_id'], 15.000, 'pending', generateInvoiceNumber()]);
                }
            }
        }
    }
    redirect(SITE_URL . '/pages/garage_dashboard.php');
}

// ── Stats ──
$pending   = $db->prepare("SELECT COUNT(*) FROM service_requests WHERE garage_id=? AND status='pending'");   $pending->execute([$garageId]);
$active    = $db->prepare("SELECT COUNT(*) FROM service_requests WHERE garage_id=? AND status IN('accepted','in_progress')"); $active->execute([$garageId]);
$completed = $db->prepare("SELECT COUNT(*) FROM service_requests WHERE garage_id=? AND status='completed'"); $completed->execute([$garageId]);
$revenue   = $db->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN service_requests r ON p.request_id=r.request_id WHERE r.garage_id=? AND p.status='paid'"); $revenue->execute([$garageId]);

// ── Recent requests (always shown regardless of subscription) ──
$reqStmt = $db->prepare("
    SELECT r.*, u.full_name AS user_name, u.phone AS user_phone
    FROM   service_requests r
    JOIN   users u ON r.user_id = u.user_id
    WHERE  r.garage_id = ?
    ORDER  BY FIELD(r.status,'pending','accepted','in_progress','completed','cancelled'),
              r.created_at DESC
    LIMIT  20
");
$reqStmt->execute([$garageId]);
$requests = $reqStmt->fetchAll();

// ── Complaints badge ──
$openComplaints = 0;
try {
    $oc = $db->prepare("SELECT COUNT(*) FROM complaints WHERE garage_id=? AND status='open'");
    $oc->execute([$garageId]);
    $openComplaints = (int)$oc->fetchColumn();
} catch (Exception $e) {}

$pageTitle = 'Garage Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Subscription status bar ── */
.sub-bar{
  display:flex;align-items:center;gap:12px;padding:12px 20px;
  border-radius:12px;margin-bottom:24px;border:1px solid transparent;flex-wrap:wrap;
}
.sub-bar.active  {background:rgba(26,158,138,.07); border-color:rgba(26,158,138,.22);}
.sub-bar.expiring{background:rgba(212,168,67,.08); border-color:rgba(212,168,67,.30);}
.sub-bar.expired {background:rgba(226,75,74,.07);  border-color:rgba(226,75,74,.25);}
.sub-bar.none    {background:rgba(226,75,74,.07);  border-color:rgba(226,75,74,.25);}
.sub-bar.cancelled{background:rgba(150,150,150,.06);border-color:rgba(150,150,150,.2);}

.sub-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.sub-dot.active  {background:var(--teal);  box-shadow:0 0 0 3px rgba(26,158,138,.2);}
.sub-dot.expiring{background:#d4a843;      box-shadow:0 0 0 3px rgba(212,168,67,.2);}
.sub-dot.expired {background:var(--danger);}
.sub-dot.none    {background:var(--danger);}
.sub-dot.cancelled{background:var(--muted);}
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
      <a href="garage_dashboard.php" class="active"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="garage_requests.php"><span class="nav-icon">📋</span> All Requests</a>
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
        <div class="dash-title">Garage Operations 🔧</div>
        <div class="dash-sub">Manage incoming service requests and track your performance.</div>
      </div>
    </div>

    <!-- ══ Subscription Status Bar ══ -->
    <div class="sub-bar <?= $subStatus['status'] ?>">
      <div class="sub-dot <?= $subStatus['status'] ?>"></div>
      <div style="flex:1;">
        <div style="font-size:11px;color:var(--muted);font-weight:600;margin-bottom:2px;">SUBSCRIPTION</div>
        <div style="font-size:13px;font-weight:700;color:<?= $subStatus['color'] ?>;">
          <?php if ($subStatus['plan']): ?>
            <?= htmlspecialchars($subStatus['plan']) ?> —
          <?php endif; ?>
          <?= htmlspecialchars($subStatus['message']) ?>
          <?php if ($subStatus['days'] > 0): ?>
            <span style="font-size:12px;color:var(--muted);font-weight:500;">
              (<?= $subStatus['days'] ?> days left)
            </span>
          <?php endif; ?>
        </div>
      </div>
      <a href="garage_subscriptions.php"
         style="font-size:13px;font-weight:700;color:var(--teal);text-decoration:none;
                padding:7px 16px;border:1px solid rgba(26,158,138,.35);border-radius:8px;
                white-space:nowrap;flex-shrink:0;">
        <?= in_array($subStatus['status'],['none','expired']) ? '⭐ Subscribe' : '⚙️ Manage' ?>
      </a>
    </div>

    <!-- ── Stats ── -->
    <div class="metric-grid">
      <div class="metric-card">
        <div class="metric-icon">🔔</div>
        <div class="metric-label">Pending Requests</div>
        <div class="metric-value" style="color:var(--warning);"><?= $pending->fetchColumn() ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">🔄</div>
        <div class="metric-label">Active Jobs</div>
        <div class="metric-value" style="color:var(--teal);"><?= $active->fetchColumn() ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">✅</div>
        <div class="metric-label">Completed</div>
        <div class="metric-value"><?= $completed->fetchColumn() ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">💰</div>
        <div class="metric-label">Revenue (OMR)</div>
        <div class="metric-value" style="color:var(--teal);"><?= number_format($revenue->fetchColumn(), 3) ?></div>
      </div>
    </div>

    <!-- ── Requests table (always visible) ── -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <h3 style="font-size:17px;font-weight:700;margin:0;">Service Requests</h3>
        <?php if (!$hasSubscription): ?>
        <a href="garage_subscriptions.php"
           style="font-size:12px;font-weight:700;color:#fff;background:var(--danger);
                  padding:6px 14px;border-radius:8px;text-decoration:none;">
          🔒 Subscribe to Accept Requests
        </a>
        <?php endif; ?>
      </div>

      <?php if (empty($requests)): ?>
      <div style="text-align:center;padding:48px;color:var(--muted);">
        <div style="font-size:48px;margin-bottom:16px;">📭</div>
        <p>No service requests yet. Once drivers request your garage, they'll appear here.</p>
      </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th><th>Driver</th><th>Service</th><th>Location</th>
              <th>Status</th><th>Date</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $r): ?>
            <tr>
              <td style="font-weight:600;color:var(--muted);">
                IVSS-<?= str_pad($r['request_id'],4,'0',STR_PAD_LEFT) ?>
              </td>
              <td>
                <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($r['user_name']) ?></div>
                <div style="font-size:12px;color:var(--muted);">📞 <?= htmlspecialchars($r['user_phone']) ?></div>
              </td>
              <td><?= ucfirst($r['service_type']) ?> — <?= ucfirst($r['vehicle_type']) ?></td>
              <td style="font-size:13px;color:var(--muted);">
                <?= htmlspecialchars(substr($r['location_desc'],0,25)) ?>…
              </td>
              <td>
                <span class="badge badge-<?= $r['status'] ?>">
                  <?= ucfirst(str_replace('_',' ',$r['status'])) ?>
                </span>
              </td>
              <td style="font-size:13px;color:var(--muted);">
                <?= date('d M, H:i', strtotime($r['created_at'])) ?>
              </td>
              <td>
                <?php if (in_array($r['status'],['completed','cancelled'])): ?>
                  <span style="font-size:13px;color:var(--muted);">—</span>

                <?php elseif (!$hasSubscription): ?>
                  <!-- Locked — show link, not a dead button -->
                  <a href="garage_subscriptions.php"
                     style="font-size:12px;color:var(--danger);font-weight:700;text-decoration:none;">
                    🔒 Subscribe
                  </a>

                <?php else: ?>
                  <form method="POST" style="display:flex;gap:6px;">
                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                    <select name="new_status" class="form-control"
                            style="padding:6px 8px;font-size:12px;min-width:110px;">
                      <?php
                      $next = [
                        'pending'     => ['accepted','cancelled'],
                        'accepted'    => ['in_progress','cancelled'],
                        'in_progress' => ['completed','cancelled'],
                      ];
                      foreach ($next[$r['status']] ?? [] as $s): ?>
                      <option value="<?= $s ?>"><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" name="update_status" class="btn btn-teal btn-sm">
                      Update
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
