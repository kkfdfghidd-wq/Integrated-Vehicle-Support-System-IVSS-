<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdminLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=admin');

$db  = getDB();
$msg = '';
$msgType = 'success';

// ══════════════════════════════════════════════════════
//  POST ACTIONS
// ══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── Add / Edit Plan ── */
    if ($action === 'save_plan') {
        $planId       = (int)   ($_POST['plan_id']      ?? 0);
        $planName     = sanitize($_POST['plan_name']     ?? '');
        $durationDays = (int)   ($_POST['duration_days'] ?? 0);
        $price        = (float) ($_POST['price']         ?? 0);
        $dailyReq     = (int)   ($_POST['daily_requests']?? 10);
        $support      = sanitize($_POST['support']       ?? 'Email');
        $analytics    = isset($_POST['analytics'])   ? 'true' : 'false';
        $apiAccess    = isset($_POST['api_access'])  ? 'true' : 'false';
        $isActive     = isset($_POST['is_active'])   ? 1 : 0;

        if (!$planName || $durationDays < 1 || $price <= 0) {
            $msg     = '❌ Fill all required fields (Name, Duration, Price).';
            $msgType = 'danger';
        } else {
            $features = json_encode([
                'daily_requests' => $dailyReq >= 9999 ? 9999 : $dailyReq,
                'support'        => $support,
                'analytics'      => $analytics === 'true',
                'api_access'     => $apiAccess === 'true',
            ]);

            if ($planId > 0) {
                // Update existing
                $db->prepare("
                    UPDATE subscription_plans
                    SET plan_name=?, duration_days=?, price=?, features=?, is_active=?
                    WHERE plan_id=?
                ")->execute([$planName, $durationDays, $price, $features, $isActive, $planId]);
                $msg = '✅ Plan updated successfully.';
            } else {
                // Insert new
                $db->prepare("
                    INSERT INTO subscription_plans (plan_name, duration_days, price, features, is_active)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$planName, $durationDays, $price, $features, $isActive]);
                $msg = '✅ New plan created successfully.';
            }
        }
    }

    /* ── Toggle Plan Active ── */
    if ($action === 'toggle_plan') {
        $planId = (int)$_POST['plan_id'];
        $cur = $db->prepare("SELECT is_active FROM subscription_plans WHERE plan_id=?");
        $cur->execute([$planId]);
        $row = $cur->fetch();
        if ($row) {
            $db->prepare("UPDATE subscription_plans SET is_active=? WHERE plan_id=?")
               ->execute([($row['is_active'] ? 0 : 1), $planId]);
            $msg = $row['is_active'] ? '✅ Plan deactivated.' : '✅ Plan activated.';
        }
    }

    /* ── Delete Plan ── */
    if ($action === 'delete_plan') {
        $planId = (int)$_POST['plan_id'];
        // Only delete if no active subscriptions reference it
        $check = $db->prepare("SELECT COUNT(*) FROM garage_subscriptions WHERE plan_id=? AND status='active'");
        $check->execute([$planId]);
        if ($check->fetchColumn() > 0) {
            $msg = '❌ Cannot delete: garages have active subscriptions on this plan.';
            $msgType = 'danger';
        } else {
            $db->prepare("DELETE FROM subscription_plans WHERE plan_id=?")->execute([$planId]);
            $msg = '✅ Plan deleted.';
        }
    }

    /* ── Manually Subscribe a Garage ── */
    if ($action === 'manual_subscribe') {
        $garageId = (int)$_POST['garage_id'];
        $planId   = (int)$_POST['plan_id'];
        $method   = sanitize($_POST['payment_method'] ?? 'manual');

        if ($garageId > 0 && $planId > 0) {
            $subId = createPendingSubscription($garageId, $planId);
            if ($subId && activateSubscription($subId, $method)) {
                $msg = '✅ Subscription activated for garage.';
            } else {
                $msg     = '❌ Failed to create subscription.';
                $msgType = 'danger';
            }
        }
    }

    /* ── Cancel Subscription ── */
    if ($action === 'cancel_sub') {
        $subId = (int)$_POST['subscription_id'];
        $db->prepare("
            UPDATE garage_subscriptions SET status='cancelled' WHERE subscription_id=?
        ")->execute([$subId]);
        $msg = '✅ Subscription cancelled.';
    }

    /* ── Extend Subscription ── */
    if ($action === 'extend_sub') {
        $subId    = (int)$_POST['subscription_id'];
        $extDays  = max(1, (int)$_POST['extend_days']);
        $db->prepare("
            UPDATE garage_subscriptions
            SET end_date = DATE_ADD(
                IF(end_date >= CURDATE(), end_date, CURDATE()),
                INTERVAL ? DAY
            ),
            status = 'active'
            WHERE subscription_id = ?
        ")->execute([$extDays, $subId]);
        $msg = "✅ Subscription extended by {$extDays} days.";
    }
}

// ══════════════════════════════════════════════════════
//  DATA
// ══════════════════════════════════════════════════════

// Plans
$plans = $db->query("SELECT * FROM subscription_plans ORDER BY duration_days ASC")->fetchAll();

// Filter tabs
$filterTab = sanitize($_GET['tab'] ?? 'subscriptions');
$searchGarage = sanitize($_GET['search'] ?? '');
$filterStatus = sanitize($_GET['sub_status'] ?? '');

// Garages with subscription info
$gSql = "
    SELECT g.garage_id, g.garage_name, g.email, g.is_active,
           gs.subscription_id, gs.plan_id, gs.start_date, gs.end_date,
           gs.status AS sub_status, gs.payment_method,
           sp.plan_name, sp.price,
           DATEDIFF(gs.end_date, CURDATE()) AS days_left
    FROM garages g
    LEFT JOIN garage_subscriptions gs ON gs.garage_id = g.garage_id
        AND gs.status IN ('active','pending_payment','expiring')
        AND gs.end_date >= CURDATE()
    LEFT JOIN subscription_plans sp ON gs.plan_id = sp.plan_id
";
$gParams = [];
$gWhere  = [];

if ($searchGarage) {
    $gWhere[]  = "(g.garage_name LIKE ? OR g.email LIKE ?)";
    $gParams[] = "%$searchGarage%";
    $gParams[] = "%$searchGarage%";
}
if ($filterStatus === 'subscribed') {
    $gWhere[] = "gs.subscription_id IS NOT NULL";
} elseif ($filterStatus === 'unsubscribed') {
    $gWhere[] = "gs.subscription_id IS NULL";
} elseif ($filterStatus === 'expired') {
    $gWhere[] = "gs.subscription_id IS NULL";
}

if ($gWhere) $gSql .= " WHERE " . implode(" AND ", $gWhere);
$gSql .= " ORDER BY gs.subscription_id DESC, g.garage_name ASC";

$gStmt = $db->prepare($gSql);
$gStmt->execute($gParams);
$garages = $gStmt->fetchAll();

// Stats
$totalGarages    = $db->query("SELECT COUNT(*) FROM garages")->fetchColumn();
$subscribedCount = $db->query("
    SELECT COUNT(DISTINCT garage_id) FROM garage_subscriptions
    WHERE status = 'active' AND end_date >= CURDATE()
")->fetchColumn();
$unsubscribedCount = $totalGarages - $subscribedCount;

$expiringCount = $db->query("
    SELECT COUNT(*) FROM garage_subscriptions
    WHERE status = 'active'
      AND end_date >= CURDATE()
      AND DATEDIFF(end_date, CURDATE()) <= 3
")->fetchColumn();

$totalSubRevenue = $db->query("
    SELECT COALESCE(SUM(amount_paid),0) FROM garage_subscriptions
    WHERE status = 'active'
")->fetchColumn();

// All non-subscribed garages for the subscribe dropdown
$unsubscribedGarages = $db->query("
    SELECT g.garage_id, g.garage_name FROM garages g
    WHERE g.garage_id NOT IN (
        SELECT garage_id FROM garage_subscriptions
        WHERE status = 'active' AND end_date >= CURDATE()
    )
    ORDER BY g.garage_name
")->fetchAll();

// All subscriptions for management
$allSubsSql = "
    SELECT gs.*, g.garage_name, g.email, sp.plan_name, sp.price
    FROM garage_subscriptions gs
    JOIN garages g ON gs.garage_id = g.garage_id
    JOIN subscription_plans sp ON gs.plan_id = sp.plan_id
    ORDER BY gs.created_at DESC
    LIMIT 100
";
$allSubs = $db->query($allSubsSql)->fetchAll();

// Edit plan data (for modal)
$editPlan = null;
if (isset($_GET['edit_plan'])) {
    $ep = $db->prepare("SELECT * FROM subscription_plans WHERE plan_id=?");
    $ep->execute([(int)$_GET['edit_plan']]);
    $editPlan = $ep->fetch();
}

$pageTitle = 'Subscription Management';
include __DIR__ . '/admin_header.php';
?>

<style>
/* ── Plan cards ── */
.plan-admin-card {
  background: var(--white);
  border: 1.5px solid var(--border);
  border-radius: 14px;
  padding: 24px;
  position: relative;
  transition: box-shadow .2s;
}
.plan-admin-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.07); }
.plan-admin-card.inactive { opacity: .65; }

/* ── Stats ── */
.sub-stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
  gap: 16px;
  margin-bottom: 28px;
}
.sub-stat {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 18px 20px;
  text-align: center;
}
.sub-stat .val { font-size: 28px; font-weight: 800; margin-bottom: 4px; }
.sub-stat .lbl { font-size: 12px; color: var(--muted); font-weight: 600; }

/* ── Tabs ── */
.admin-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
.admin-tab  {
  padding: 9px 20px; border-radius: 8px; font-size: 13px; font-weight: 700;
  text-decoration: none; cursor: pointer;
  border: 1.5px solid var(--border); color: var(--muted); background: var(--white);
  transition: all .15s;
}
.admin-tab.active, .admin-tab:hover {
  background: var(--navy); color: #fff; border-color: var(--navy);
}

/* ── Form modal overlay ── */
.modal-wrap {
  background: rgba(0,0,0,.45);
  position: fixed; inset: 0; z-index: 1000;
  display: flex; align-items: center; justify-content: center; padding: 20px;
}
.modal-box {
  background: var(--white); border-radius: 16px;
  padding: 32px; width: 100%; max-width: 540px;
  max-height: 90vh; overflow-y: auto;
}
.modal-title { font-size: 18px; font-weight: 800; margin-bottom: 24px; }

.badge-sub-active   { background: rgba(26,158,138,.12);  color: var(--teal);    padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.badge-sub-expired  { background: rgba(226,75,74,.1);    color: var(--danger);  padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.badge-sub-none     { background: rgba(150,150,150,.12); color: var(--muted);   padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.badge-sub-expiring { background: rgba(212,168,67,.15);  color: #a07820;        padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.badge-sub-pending  { background: rgba(212,168,67,.12);  color: #a07820;        padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }

.check-toggle { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.check-toggle input { width: 18px; height: 18px; accent-color: var(--teal); cursor: pointer; }

.tag-pill {
  display: inline-block;
  padding: 2px 10px; border-radius: 10px;
  background: rgba(26,158,138,.08); color: var(--teal);
  font-size: 11px; font-weight: 700; margin-right: 4px;
}
</style>

<div class="dash-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>

  <div class="dash-content">

    <!-- Header -->
    <div class="dash-header">
      <div>
        <div class="dash-title">Subscription Management ⭐</div>
        <div class="dash-sub">Manage plans, assign subscriptions, and track garage billing.</div>
      </div>
      <button onclick="document.getElementById('newPlanModal').style.display='flex'"
              class="btn btn-primary">+ New Plan</button>
    </div>

    <!-- Alert -->
    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom:20px;"><?= $msg ?></div>
    <?php endif; ?>

    <!-- ── Stats ── -->
    <div class="sub-stat-grid">
      <div class="sub-stat">
        <div class="val" style="color:var(--teal);"><?= $subscribedCount ?></div>
        <div class="lbl">✅ Subscribed Garages</div>
      </div>
      <div class="sub-stat">
        <div class="val" style="color:var(--danger);"><?= $unsubscribedCount ?></div>
        <div class="lbl">❌ Unsubscribed</div>
      </div>
      <div class="sub-stat">
        <div class="val" style="color:var(--warning);"><?= $expiringCount ?></div>
        <div class="lbl">⚠️ Expiring ≤3 Days</div>
      </div>
      <div class="sub-stat">
        <div class="val" style="color:var(--navy);"><?= $totalGarages ?></div>
        <div class="lbl">🔧 Total Garages</div>
      </div>
      <div class="sub-stat">
        <div class="val" style="font-size:22px;color:var(--gold);"><?= number_format($totalSubRevenue,3) ?></div>
        <div class="lbl">💰 Sub Revenue (OMR)</div>
      </div>
    </div>

    <!-- ── Tabs ── -->
    <div class="admin-tabs">
      <a href="?tab=subscriptions" class="admin-tab <?= $filterTab==='subscriptions'?'active':'' ?>">📋 All Subscriptions</a>
      <a href="?tab=garages"       class="admin-tab <?= $filterTab==='garages'      ?'active':'' ?>">🔧 Garage Status</a>
      <a href="?tab=plans"         class="admin-tab <?= $filterTab==='plans'        ?'active':'' ?>">📦 Plans</a>
      <a href="?tab=assign"        class="admin-tab <?= $filterTab==='assign'       ?'active':'' ?>">➕ Assign Subscription</a>
    </div>

    <!-- ════════════════════════════════════════════
         TAB 1 — All Subscriptions
    ════════════════════════════════════════════ -->
    <?php if ($filterTab === 'subscriptions'): ?>
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;">
        <h3 style="font-size:16px;font-weight:700;">All Subscriptions</h3>
        <div style="font-size:13px;color:var(--muted);"><?= count($allSubs) ?> records</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Garage</th>
              <th>Plan</th>
              <th>Start</th>
              <th>End</th>
              <th>Days Left</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allSubs as $s):
              $daysLeft = (int) floor((strtotime($s['end_date']) - time()) / 86400);
              $statusClass = match($s['status']) {
                'active'          => 'badge-sub-active',
                'expired'         => 'badge-sub-expired',
                'cancelled'       => 'badge-sub-expired',
                'pending_payment' => 'badge-sub-pending',
                default           => 'badge-sub-none',
              };
            ?>
            <tr>
              <td style="color:var(--muted);font-size:12px;font-weight:600;">#<?= $s['subscription_id'] ?></td>
              <td>
                <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($s['garage_name']) ?></div>
                <div style="font-size:11px;color:var(--muted);">✉️ <?= htmlspecialchars($s['email']) ?></div>
              </td>
              <td>
                <strong><?= htmlspecialchars($s['plan_name']) ?></strong><br>
                <span style="font-size:11px;color:var(--muted);"><?= number_format($s['price'],3) ?> OMR</span>
              </td>
              <td style="font-size:12px;color:var(--muted);"><?= date('d M Y', strtotime($s['start_date'])) ?></td>
              <td style="font-size:12px;color:var(--muted);"><?= date('d M Y', strtotime($s['end_date'])) ?></td>
              <td>
                <?php if ($s['status'] === 'active'): ?>
                <span style="font-weight:700;color:<?= $daysLeft <= 3 ? 'var(--danger)' : 'var(--teal)' ?>;">
                  <?= max(0, $daysLeft) ?>d
                </span>
                <?php else: ?>
                <span style="color:var(--muted);">—</span>
                <?php endif; ?>
              </td>
              <td style="font-weight:700;font-size:13px;"><?= number_format($s['amount_paid'],3) ?></td>
              <td style="font-size:12px;text-transform:capitalize;color:var(--muted);"><?= $s['payment_method'] ?? '—' ?></td>
              <td><span class="<?= $statusClass ?>"><?= ucfirst(str_replace('_',' ',$s['status'])) ?></span></td>
              <td>
                <div style="display:flex;flex-direction:column;gap:6px;min-width:130px;">
                  <!-- Extend -->
                  <?php if (in_array($s['status'],['active','expired','pending_payment'])): ?>
                  <form method="POST" style="display:flex;gap:4px;">
                    <input type="hidden" name="action" value="extend_sub">
                    <input type="hidden" name="subscription_id" value="<?= $s['subscription_id'] ?>">
                    <input type="number" name="extend_days" value="30" min="1" max="365"
                           class="form-control" style="padding:4px 6px;font-size:11px;width:54px;">
                    <button class="btn btn-teal btn-sm" style="padding:4px 8px;font-size:11px;white-space:nowrap;">+Days</button>
                  </form>
                  <?php endif; ?>
                  <!-- Cancel -->
                  <?php if ($s['status'] === 'active'): ?>
                  <form method="POST" onsubmit="return confirm('Cancel this subscription?')">
                    <input type="hidden" name="action" value="cancel_sub">
                    <input type="hidden" name="subscription_id" value="<?= $s['subscription_id'] ?>">
                    <button class="btn btn-sm" style="background:rgba(226,75,74,.1);color:var(--danger);border:none;padding:4px 10px;font-size:11px;width:100%;">
                      🚫 Cancel
                    </button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ════════════════════════════════════════════
         TAB 2 — Garage Status
    ════════════════════════════════════════════ -->
    <?php if ($filterTab === 'garages'): ?>
    <div class="card" style="margin-bottom:20px;">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="tab" value="garages">
        <div style="flex:1;min-width:200px;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:4px;">Search</label>
          <input type="text" name="search" class="form-control" placeholder="Garage name or email..."
                 value="<?= htmlspecialchars($searchGarage) ?>">
        </div>
        <div style="min-width:160px;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:4px;">Filter</label>
          <select name="sub_status" class="form-control">
            <option value="">All Garages</option>
            <option value="subscribed"   <?= $filterStatus==='subscribed'   ?'selected':'' ?>>✅ Subscribed</option>
            <option value="unsubscribed" <?= $filterStatus==='unsubscribed' ?'selected':'' ?>>❌ Unsubscribed</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if ($searchGarage || $filterStatus): ?>
        <a href="?tab=garages" class="btn btn-dark btn-sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Garage</th>
              <th>Subscription</th>
              <th>Plan</th>
              <th>Expires</th>
              <th>Days Left</th>
              <th>Quick Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($garages as $g):
              $hasSub  = !empty($g['subscription_id']);
              $dLeft   = (int)$g['days_left'];
            ?>
            <tr>
              <td>
                <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($g['garage_name']) ?></div>
                <div style="font-size:11px;color:var(--muted);">✉️ <?= htmlspecialchars($g['email']) ?></div>
                <?php if (!$g['is_active']): ?>
                <span style="font-size:10px;color:var(--danger);">● Inactive</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$hasSub): ?>
                  <span class="badge-sub-none">❌ None</span>
                <?php elseif ($g['sub_status'] === 'pending_payment'): ?>
                  <span class="badge-sub-pending">⏳ Pending Payment</span>
                <?php elseif ($dLeft <= 3 && $dLeft >= 0): ?>
                  <span class="badge-sub-expiring">⚠️ Expiring</span>
                <?php else: ?>
                  <span class="badge-sub-active">✅ Active</span>
                <?php endif; ?>
              </td>
              <td style="font-size:13px;"><?= $hasSub ? htmlspecialchars($g['plan_name']) : '—' ?></td>
              <td style="font-size:12px;color:var(--muted);">
                <?= $hasSub ? date('d M Y', strtotime($g['end_date'])) : '—' ?>
              </td>
              <td>
                <?php if ($hasSub && $g['sub_status'] !== 'pending_payment'): ?>
                <span style="font-weight:700;color:<?= $dLeft <= 3 ? 'var(--danger)' : ($dLeft <= 7 ? 'var(--warning)' : 'var(--teal)') ?>;">
                  <?= max(0, $dLeft) ?>d
                </span>
                <?php else: ?>
                <span style="color:var(--muted);">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$hasSub): ?>
                <!-- Subscribe -->
                <button class="btn btn-sm" style="background:var(--teal);color:#fff;border:none;"
                        onclick="openAssignModal(<?= $g['garage_id'] ?>, '<?= htmlspecialchars(addslashes($g['garage_name']), ENT_QUOTES) ?>')">
                  ⭐ Subscribe
                </button>
                <?php elseif ($hasSub && $g['sub_status'] !== 'pending_payment'): ?>
                <!-- Extend -->
                <form method="POST" style="display:inline-flex;gap:5px;">
                  <input type="hidden" name="action" value="extend_sub">
                  <input type="hidden" name="subscription_id" value="<?= $g['subscription_id'] ?>">
                  <input type="number" name="extend_days" value="30" min="1" max="365"
                         class="form-control" style="padding:4px 6px;font-size:11px;width:54px;">
                  <button class="btn btn-dark btn-sm" style="padding:4px 10px;font-size:11px;">+Days</button>
                </form>
                <?php else: ?>
                <span style="font-size:12px;color:var(--muted);">Awaiting payment</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ════════════════════════════════════════════
         TAB 3 — Plans
    ════════════════════════════════════════════ -->
    <?php if ($filterTab === 'plans'): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
      <?php foreach ($plans as $p):
        $feats = json_decode($p['features'] ?? '{}', true);
        $subCount = $db->prepare("SELECT COUNT(*) FROM garage_subscriptions WHERE plan_id=? AND status='active'");
        $subCount->execute([$p['plan_id']]);
        $activeSubsOnPlan = (int)$subCount->fetchColumn();
      ?>
      <div class="plan-admin-card <?= !$p['is_active'] ? 'inactive' : '' ?>">
        <?php if (!$p['is_active']): ?>
        <div style="position:absolute;top:12px;right:12px;background:rgba(226,75,74,.1);color:var(--danger);
                    padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">Inactive</div>
        <?php endif; ?>

        <div style="font-size:28px;margin-bottom:8px;">
          <?= ['Weekly'=>'🕐','Monthly'=>'📅','Yearly'=>'📆'][$p['plan_name']] ?? '📦' ?>
        </div>
        <div style="font-size:18px;font-weight:800;color:var(--navy);"><?= htmlspecialchars($p['plan_name']) ?></div>
        <div style="font-size:28px;font-weight:800;color:var(--teal);margin:6px 0;">
          <?= number_format($p['price'],3) ?> <span style="font-size:14px;color:var(--muted);">OMR</span>
        </div>
        <div style="font-size:13px;color:var(--muted);margin-bottom:12px;">
          for <?= $p['duration_days'] ?> days
          &nbsp;·&nbsp; <?= number_format($p['price']/$p['duration_days'],3) ?> OMR/day
        </div>

        <!-- Features -->
        <div style="background:var(--bg);border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:12px;line-height:1.8;">
          <div>📋 Requests/day: <strong><?= $feats['daily_requests'] >= 9999 ? '∞' : ($feats['daily_requests']??'—') ?></strong></div>
          <div>🎧 Support: <strong><?= $feats['support']??'—' ?></strong></div>
          <div>📊 Analytics: <strong><?= !empty($feats['analytics'])?'✓':'✗' ?></strong></div>
          <div>🔗 API: <strong><?= !empty($feats['api_access'])?'✓':'✗' ?></strong></div>
        </div>

        <div style="font-size:12px;color:var(--muted);margin-bottom:14px;">
          🔧 <strong style="color:var(--teal);"><?= $activeSubsOnPlan ?></strong> active subscription<?= $activeSubsOnPlan != 1 ? 's' : '' ?>
        </div>

        <!-- Actions -->
        <div style="display:flex;flex-direction:column;gap:8px;">
          <a href="?tab=plans&edit_plan=<?= $p['plan_id'] ?>"
             class="btn btn-dark btn-sm" style="text-align:center;width:100%;">✏️ Edit Plan</a>

          <form method="POST">
            <input type="hidden" name="action"  value="toggle_plan">
            <input type="hidden" name="plan_id" value="<?= $p['plan_id'] ?>">
            <button class="btn btn-sm" style="width:100%;
              background:<?= $p['is_active']?'rgba(226,75,74,.08)':'rgba(26,158,138,.08)' ?>;
              color:<?= $p['is_active']?'var(--danger)':'var(--teal)' ?>;
              border:1px solid <?= $p['is_active']?'var(--danger)':'var(--teal)' ?>;">
              <?= $p['is_active']?'🚫 Deactivate':'✅ Activate' ?>
            </button>
          </form>

          <?php if ($activeSubsOnPlan === 0): ?>
          <form method="POST" onsubmit="return confirm('Delete plan \'<?= htmlspecialchars(addslashes($p['plan_name'])) ?>\'?')">
            <input type="hidden" name="action"  value="delete_plan">
            <input type="hidden" name="plan_id" value="<?= $p['plan_id'] ?>">
            <button class="btn btn-sm" style="width:100%;background:rgba(226,75,74,.08);color:var(--danger);border:1px solid var(--danger);">
              🗑️ Delete
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Add new plan card -->
      <div class="plan-admin-card" style="border-style:dashed;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:280px;cursor:pointer;"
           onclick="document.getElementById('newPlanModal').style.display='flex'">
        <div style="font-size:40px;margin-bottom:12px;">➕</div>
        <div style="font-size:15px;font-weight:700;color:var(--muted);">Add New Plan</div>
      </div>
    </div>

    <!-- Edit Plan Form (inline if edit_plan is set) -->
    <?php if ($editPlan): ?>
    <div class="card" style="margin-top:24px;border:2px solid var(--teal);">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;">✏️ Editing: <?= htmlspecialchars($editPlan['plan_name']) ?></h3>
      <?php
        $ef = json_decode($editPlan['features'] ?? '{}', true);
      ?>
      <form method="POST">
        <input type="hidden" name="action"  value="save_plan">
        <input type="hidden" name="plan_id" value="<?= $editPlan['plan_id'] ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group">
            <label>Plan Name <span style="color:var(--danger);">*</span></label>
            <input type="text" name="plan_name" class="form-control" value="<?= htmlspecialchars($editPlan['plan_name']) ?>" required>
          </div>
          <div class="form-group">
            <label>Duration (Days) <span style="color:var(--danger);">*</span></label>
            <input type="number" name="duration_days" class="form-control" value="<?= $editPlan['duration_days'] ?>" min="1" required>
          </div>
          <div class="form-group">
            <label>Price (OMR) <span style="color:var(--danger);">*</span></label>
            <input type="number" name="price" step="0.001" class="form-control" value="<?= number_format($editPlan['price'],3,'.','') ?>" min="0.001" required>
          </div>
          <div class="form-group">
            <label>Daily Requests (9999 = Unlimited)</label>
            <input type="number" name="daily_requests" class="form-control" value="<?= $ef['daily_requests'] ?? 10 ?>" min="1">
          </div>
          <div class="form-group">
            <label>Support Type</label>
            <select name="support" class="form-control">
              <?php foreach(['Email','24/7','24/7 VIP'] as $s): ?>
              <option value="<?= $s ?>" <?= ($ef['support']??'')===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="display:flex;gap:24px;margin-bottom:16px;flex-wrap:wrap;">
          <label class="check-toggle"><input type="checkbox" name="analytics"  <?= !empty($ef['analytics'])  ?'checked':'' ?>> 📊 Analytics</label>
          <label class="check-toggle"><input type="checkbox" name="api_access" <?= !empty($ef['api_access']) ?'checked':'' ?>> 🔗 API Access</label>
          <label class="check-toggle"><input type="checkbox" name="is_active"  <?= $editPlan['is_active']    ?'checked':'' ?>> ✅ Active</label>
        </div>
        <div style="display:flex;gap:10px;">
          <button type="submit" class="btn btn-primary">💾 Save Changes</button>
          <a href="?tab=plans" class="btn btn-dark">Cancel</a>
        </div>
      </form>
    </div>
    <?php endif; ?>
    <?php endif; // plans tab ?>

    <!-- ════════════════════════════════════════════
         TAB 4 — Assign Subscription
    ════════════════════════════════════════════ -->
    <?php if ($filterTab === 'assign'): ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

      <!-- Manual Subscribe Form -->
      <div class="card">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;">➕ Assign Subscription Manually</h3>
        <?php if (empty($unsubscribedGarages)): ?>
        <div style="padding:32px;text-align:center;color:var(--muted);">
          <div style="font-size:40px;margin-bottom:12px;">✅</div>
          <p>All garages are currently subscribed!</p>
        </div>
        <?php else: ?>
        <form method="POST" id="manualSubForm">
          <input type="hidden" name="action" value="manual_subscribe">
          <div class="form-group">
            <label>Select Garage <span style="color:var(--danger);">*</span></label>
            <select name="garage_id" id="garageSelect" class="form-control" required>
              <option value="">— Choose garage —</option>
              <?php foreach ($unsubscribedGarages as $ug): ?>
              <option value="<?= $ug['garage_id'] ?>"><?= htmlspecialchars($ug['garage_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Subscription Plan <span style="color:var(--danger);">*</span></label>
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:8px;">
              <?php foreach ($plans as $p):
                if (!$p['is_active']) continue;
                $pf = json_decode($p['features']??'{}',true);
              ?>
              <label style="display:flex;align-items:center;gap:12px;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;"
                     id="planLabel_<?= $p['plan_id'] ?>"
                     onclick="selectPlan(<?= $p['plan_id'] ?>)">
                <input type="radio" name="plan_id" value="<?= $p['plan_id'] ?>" required style="accent-color:var(--teal);">
                <div style="flex:1;">
                  <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($p['plan_name']) ?></div>
                  <div style="font-size:12px;color:var(--muted);"><?= $p['duration_days'] ?> days · <?= $pf['daily_requests'] >= 9999 ? '∞' : $pf['daily_requests']?>/day</div>
                </div>
                <div style="font-weight:800;font-size:16px;color:var(--teal);"><?= number_format($p['price'],3) ?> OMR</div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-group">
            <label>Payment Method</label>
            <select name="payment_method" class="form-control">
              <option value="manual">🛠️ Manual (Admin)</option>
              <option value="cash">💵 Cash</option>
              <option value="transfer">🏦 Bank Transfer</option>
              <option value="card">💳 Card</option>
            </select>
          </div>
          <div style="background:rgba(26,158,138,.06);border:1px solid rgba(26,158,138,.2);border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px;color:var(--muted);">
            ℹ️ The subscription will be activated immediately upon submission.
          </div>
          <button type="submit" class="btn btn-primary" onclick="return confirm('Activate subscription for this garage?')">
            ⭐ Activate Subscription
          </button>
        </form>
        <?php endif; ?>
      </div>

      <!-- Unsubscribed List -->
      <div class="card">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;">❌ Garages Without Subscription</h3>
        <?php if (empty($unsubscribedGarages)): ?>
        <div style="padding:24px;text-align:center;color:var(--muted);">All garages have active subscriptions!</div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;max-height:520px;overflow-y:auto;">
          <?php foreach ($unsubscribedGarages as $ug): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;
                      padding:12px 14px;background:var(--bg);border-radius:10px;gap:12px;">
            <div>
              <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($ug['garage_name']) ?></div>
            </div>
            <button class="btn btn-sm" style="background:var(--teal);color:#fff;border:none;white-space:nowrap;"
                    onclick="selectGarageInForm(<?= $ug['garage_id'] ?>)">
              + Assign
            </button>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:12px;font-size:13px;color:var(--muted);text-align:right;">
          <strong style="color:var(--danger);"><?= count($unsubscribedGarages) ?></strong> unsubscribed
        </div>
        <?php endif; ?>
      </div>

    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ══════════════════════════════════════════
     NEW PLAN MODAL
══════════════════════════════════════════ -->
<div id="newPlanModal" class="modal-wrap" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-box">
    <div class="modal-title">📦 Create New Plan</div>
    <form method="POST">
      <input type="hidden" name="action"  value="save_plan">
      <input type="hidden" name="plan_id" value="0">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group" style="grid-column:1/-1;">
          <label>Plan Name <span style="color:var(--danger);">*</span></label>
          <input type="text" name="plan_name" class="form-control" placeholder="e.g. Premium, Enterprise..." required>
        </div>
        <div class="form-group">
          <label>Duration (Days) <span style="color:var(--danger);">*</span></label>
          <input type="number" name="duration_days" class="form-control" placeholder="30" min="1" required>
        </div>
        <div class="form-group">
          <label>Price (OMR) <span style="color:var(--danger);">*</span></label>
          <input type="number" name="price" step="0.001" class="form-control" placeholder="0.000" min="0.001" required>
        </div>
        <div class="form-group">
          <label>Daily Requests (9999 = ∞)</label>
          <input type="number" name="daily_requests" class="form-control" value="20" min="1">
        </div>
        <div class="form-group">
          <label>Support Type</label>
          <select name="support" class="form-control">
            <option>Email</option>
            <option>24/7</option>
            <option>24/7 VIP</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:20px;margin:12px 0 20px;flex-wrap:wrap;">
        <label class="check-toggle"><input type="checkbox" name="analytics" checked> 📊 Analytics</label>
        <label class="check-toggle"><input type="checkbox" name="api_access"> 🔗 API Access</label>
        <label class="check-toggle"><input type="checkbox" name="is_active" checked> ✅ Active</label>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">➕ Create Plan</button>
        <button type="button" class="btn btn-dark" onclick="document.getElementById('newPlanModal').style.display='none'">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Assign Modal (for quick-subscribe from garage list) -->
<div id="assignModal" class="modal-wrap" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-box">
    <div class="modal-title">⭐ Assign Subscription</div>
    <div id="assignGarageName" style="font-size:15px;font-weight:700;color:var(--teal);margin-bottom:18px;"></div>
    <form method="POST" onsubmit="return confirm('Activate subscription?')">
      <input type="hidden" name="action" value="manual_subscribe">
      <input type="hidden" name="garage_id" id="assignGarageId" value="">
      <div class="form-group">
        <label>Plan <span style="color:var(--danger);">*</span></label>
        <select name="plan_id" class="form-control" required>
          <option value="">— Select —</option>
          <?php foreach ($plans as $p): if (!$p['is_active']) continue; ?>
          <option value="<?= $p['plan_id'] ?>"><?= htmlspecialchars($p['plan_name']) ?> — <?= number_format($p['price'],3) ?> OMR / <?= $p['duration_days'] ?>d</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Payment Method</label>
        <select name="payment_method" class="form-control">
          <option value="manual">🛠️ Manual (Admin)</option>
          <option value="cash">💵 Cash</option>
          <option value="transfer">🏦 Bank Transfer</option>
          <option value="card">💳 Card</option>
        </select>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="submit" class="btn btn-primary">⭐ Activate</button>
        <button type="button" class="btn btn-dark" onclick="document.getElementById('assignModal').style.display='none'">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
// Select plan highlights the card
function selectPlan(id) {
  document.querySelectorAll('[id^="planLabel_"]').forEach(el => {
    el.style.borderColor = 'var(--border)';
    el.style.background  = '';
  });
  const lbl = document.getElementById('planLabel_' + id);
  if (lbl) {
    lbl.style.borderColor = 'var(--teal)';
    lbl.style.background  = 'rgba(26,158,138,.05)';
    lbl.querySelector('input[type=radio]').checked = true;
  }
}

// Quick-assign from unsubscribed list → scroll to form
function selectGarageInForm(garageId) {
  const sel = document.getElementById('garageSelect');
  if (sel) {
    sel.value = garageId;
    sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
}

// Open assign modal from garage status tab
function openAssignModal(garageId, garageName) {
  document.getElementById('assignGarageId').value    = garageId;
  document.getElementById('assignGarageName').textContent = '🔧 ' + garageName;
  document.getElementById('assignModal').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>
