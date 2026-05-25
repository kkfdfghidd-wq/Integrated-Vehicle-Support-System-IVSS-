<?php
require_once __DIR__ . '/../includes/config.php';
if (!isGarageLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=garage');

$db       = getDB();
$garageId = (int) $_SESSION['garage_id'];
$msg      = '';
$msgType  = 'success';

// ── Handle "Subscribe Now" → create pending → redirect to payment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {
    $planId = (int) $_POST['plan_id'];
    if ($planId > 0) {
        $subId = createPendingSubscription($garageId, $planId);
        if ($subId) {
            // Redirect to subscription payment page
            redirect(SITE_URL . '/pages/subscription_payment.php?sub_id=' . $subId);
        } else {
            $msg     = '❌ Failed to initiate subscription. Make sure subscriptions.sql is imported.';
            $msgType = 'danger';
        }
    }
}

// ── Data ─────────────────────────────────────────────────────
$subStatus = getSubscriptionStatus($garageId);
$curSub    = getGarageSubscription($garageId);

$plans = [];
try {
    $plans = $db->query("SELECT * FROM subscription_plans WHERE is_active=1 ORDER BY duration_days ASC")->fetchAll();
} catch (Exception $e) {}

$history = [];
try {
    $histStmt = $db->prepare("
        SELECT sh.*, sp.plan_name, sp.price AS plan_price
        FROM   subscription_history sh
        JOIN   subscription_plans   sp ON sh.plan_id = sp.plan_id
        WHERE  sh.garage_id = ?
        ORDER  BY sh.archived_at DESC
        LIMIT  10
    ");
    $histStmt->execute([$garageId]);
    $history = $histStmt->fetchAll();
} catch (Exception $e) {}

$openComplaints = 0;
try {
    $oc = $db->prepare("SELECT COUNT(*) FROM complaints WHERE garage_id=? AND status='open'");
    $oc->execute([$garageId]);
    $openComplaints = (int)$oc->fetchColumn();
} catch (Exception $e) {}

$dbMissing   = empty($plans);
$planIcons   = [1=>'🕐', 2=>'📅', 3=>'📆'];
$recommended = 2;

$pageTitle = 'My Subscription';
include __DIR__ . '/../includes/header.php';

// Check for success/cancelled message from payment redirect
$flashMsg  = '';
$flashType = 'success';
if (isset($_GET['paid']) && $_GET['paid'] == '1') {
    $flashMsg  = '✅ Payment successful! Your subscription is now active.';
    $flashType = 'success';
}
if (isset($_GET['cancelled']) && $_GET['cancelled'] == '1') {
    $flashMsg  = '⚠️ Payment cancelled. Your subscription is not active yet.';
    $flashType = 'warning';
}
?>

<style>
.sub-hero{
  background:linear-gradient(135deg,var(--navy) 0%,#1a3a5c 100%);
  border-radius:16px;padding:32px;margin-bottom:28px;
  color:#fff;display:flex;align-items:center;gap:24px;flex-wrap:wrap;
}
.sub-hero-icon{
  width:72px;height:72px;border-radius:18px;
  display:flex;align-items:center;justify-content:center;font-size:36px;flex-shrink:0;
}
.sub-hero-icon.active         {background:rgba(26,158,138,.25);}
.sub-hero-icon.expiring       {background:rgba(212,168,67,.25);}
.sub-hero-icon.expired        {background:rgba(226,75,74,.2);}
.sub-hero-icon.pending_payment{background:rgba(212,168,67,.2);}
.sub-hero-icon.none           {background:rgba(255,255,255,.08);}
.sub-days-ring{
  width:80px;height:80px;border-radius:50%;border:4px solid rgba(255,255,255,.12);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  margin-left:auto;flex-shrink:0;
}
.sub-days-ring .num{font-size:26px;font-weight:800;line-height:1;}
.sub-days-ring .lbl{font-size:10px;color:rgba(255,255,255,.5);margin-top:2px;}

.plans-grid{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
  gap:20px;margin-bottom:28px;
}
.plan-card{
  background:var(--white);border:2px solid var(--border);border-radius:16px;
  padding:28px 24px;position:relative;transition:border-color .2s,box-shadow .2s;
}
.plan-card:hover{border-color:var(--teal);box-shadow:0 6px 24px rgba(26,158,138,.12);}
.plan-card.recommended{border-color:var(--teal);box-shadow:0 6px 24px rgba(26,158,138,.15);}
.plan-badge{
  position:absolute;top:-12px;left:50%;transform:translateX(-50%);
  background:var(--teal);color:#fff;
  font-size:11px;font-weight:700;padding:3px 14px;border-radius:12px;white-space:nowrap;
}
.plan-icon {font-size:36px;margin-bottom:12px;}
.plan-name {font-size:18px;font-weight:800;color:var(--navy);margin-bottom:4px;}
.plan-price{font-size:32px;font-weight:800;color:var(--teal);margin-bottom:2px;}
.plan-price span{font-size:14px;color:var(--muted);font-weight:500;}
.plan-dur  {font-size:13px;color:var(--muted);margin-bottom:20px;}
.plan-feat {list-style:none;padding:0;margin:0 0 24px;}
.plan-feat li{
  font-size:13px;color:var(--text);padding:5px 0;
  border-bottom:1px solid var(--border);display:flex;gap:8px;align-items:center;
}
.plan-feat li:last-child{border:none;}

.history-table th{font-size:12px;color:var(--muted);font-weight:600;}
.history-table td{font-size:13px;}
.status-pill{display:inline-block;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;}
.pill-active         {background:rgba(26,158,138,.1);color:var(--teal);}
.pill-expired        {background:rgba(226,75,74,.1); color:var(--danger);}
.pill-cancelled      {background:rgba(150,150,150,.12);color:var(--muted);}
.pill-pending_payment{background:rgba(212,168,67,.12);color:#a07820;}

.db-missing{
  background:rgba(212,168,67,.08);border:1px solid rgba(212,168,67,.35);
  border-radius:12px;padding:20px 24px;margin-bottom:24px;
}
.db-missing code{
  display:block;background:var(--navy);color:var(--gold);
  padding:10px 14px;border-radius:8px;margin-top:10px;font-size:13px;
}
.pending-pay-bar{
  background:rgba(212,168,67,.08);border:1px solid rgba(212,168,67,.35);
  border-radius:12px;padding:16px 20px;margin-bottom:24px;
  display:flex;align-items:center;gap:14px;flex-wrap:wrap;
}
.pending-pay-bar a{
  margin-left:auto;background:#d4a843;color:#fff;
  padding:9px 20px;border-radius:9px;font-weight:700;font-size:13px;
  text-decoration:none;white-space:nowrap;
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
      <a href="garage_subscriptions.php" class="active">
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
        <div class="dash-title">My Subscription ⭐</div>
        <div class="dash-sub">Manage your garage subscription plan.</div>
      </div>
    </div>

    <!-- Flash messages -->
    <?php if ($flashMsg): ?>
    <div class="alert alert-<?= $flashType ?>" style="margin-bottom:20px;"><?= $flashMsg ?></div>
    <?php endif; ?>
    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom:20px;"><?= $msg ?></div>
    <?php endif; ?>

    <!-- DB missing -->
    <?php if ($dbMissing): ?>
    <div class="db-missing">
      <div style="font-size:15px;font-weight:700;color:#a07820;margin-bottom:6px;">
        ⚠️ Subscription tables not found
      </div>
      <div style="font-size:13px;color:var(--muted);">
        Import <strong>subscriptions.sql</strong> into phpMyAdmin first.
      </div>
      <code>phpMyAdmin → ivss2_db → Import → subscriptions.sql → Go</code>
    </div>
    <?php endif; ?>

    <!-- Pending payment reminder -->
    <?php if ($subStatus['status'] === 'pending_payment' && $curSub): ?>
    <div class="pending-pay-bar">
      <div style="font-size:24px;">⏳</div>
      <div>
        <div style="font-weight:700;color:#a07820;font-size:14px;">Payment Pending</div>
        <div style="font-size:12px;color:var(--muted);">
          <?= htmlspecialchars($curSub['plan_name']) ?> — <?= number_format($curSub['price'],3) ?> OMR
          — Complete payment to activate your subscription.
        </div>
      </div>
      <a href="subscription_payment.php?sub_id=<?= $curSub['subscription_id'] ?>">
        💳 Complete Payment →
      </a>
    </div>
    <?php endif; ?>

    <!-- ══ Hero ══ -->
    <div class="sub-hero">
      <div class="sub-hero-icon <?= $subStatus['status'] ?>">
        <?= match($subStatus['status']) {
            'active'          => '✅',
            'expiring'        => '⚠️',
            'expired'         => '❌',
            'pending_payment' => '⏳',
            default           => '📋',
        } ?>
      </div>
      <div>
        <div style="font-size:13px;color:rgba(255,255,255,.5);margin-bottom:4px;">Current Subscription</div>
        <div style="font-size:20px;font-weight:800;color:#fff;margin-bottom:6px;">
          <?= htmlspecialchars($subStatus['plan'] ?? 'No Active Plan') ?>
        </div>
        <div style="font-size:14px;font-weight:600;color:<?= $subStatus['color'] ?>;">
          <?= htmlspecialchars($subStatus['message']) ?>
        </div>
        <?php if ($curSub && !in_array($subStatus['status'],['none'])): ?>
        <div style="font-size:12px;color:rgba(255,255,255,.4);margin-top:6px;">
          <?= number_format($curSub['price'],3) ?> OMR ·
          Started: <?= date('d M Y', strtotime($curSub['start_date'])) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php if (in_array($subStatus['status'],['active','expiring'])): ?>
      <div class="sub-days-ring" style="border-color:<?= $subStatus['color'] ?>;">
        <div class="num" style="color:<?= $subStatus['color'] ?>;"><?= $subStatus['days'] ?></div>
        <div class="lbl">days left</div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ Plans ══ -->
    <?php if (!$dbMissing): ?>
    <h3 style="font-size:17px;font-weight:700;margin-bottom:20px;">
      <?= in_array($subStatus['status'],['none','expired']) ? 'Choose a Plan to Get Started' : 'Available Plans — Upgrade or Renew' ?>
    </h3>

    <div class="plans-grid">
      <?php foreach ($plans as $plan):
        $feats      = json_decode($plan['features'] ?? '{}', true);
        $isCurrent  = ($curSub && $curSub['plan_id'] == $plan['plan_id'] && $subStatus['status'] === 'active');
        $isPending  = ($curSub && $curSub['plan_id'] == $plan['plan_id'] && $subStatus['status'] === 'pending_payment');
        $isRec      = ($plan['plan_id'] == $recommended);
        $dailyReq   = $feats['daily_requests'] ?? '—';
        $dailyLabel = ($dailyReq >= 9999) ? 'Unlimited' : $dailyReq.'/day';
      ?>
      <div class="plan-card <?= $isRec ? 'recommended' : '' ?>">
        <?php if ($isRec && !$isCurrent && !$isPending): ?>
        <div class="plan-badge">⭐ Most Popular</div>
        <?php endif; ?>
        <?php if ($isCurrent): ?>
        <div class="plan-badge" style="background:var(--navy);">✅ Active Plan</div>
        <?php elseif ($isPending): ?>
        <div class="plan-badge" style="background:#d4a843;">⏳ Pending Payment</div>
        <?php endif; ?>

        <div class="plan-icon"><?= $planIcons[$plan['plan_id']] ?? '📦' ?></div>
        <div class="plan-name"><?= htmlspecialchars($plan['plan_name']) ?></div>
        <div class="plan-price"><?= number_format($plan['price'],3) ?> <span>OMR</span></div>
        <div class="plan-dur">for <?= $plan['duration_days'] ?> days</div>

        <ul class="plan-feat">
          <li>🔔 <strong><?= $dailyLabel ?></strong> requests/day</li>
          <li>🎧 <strong><?= htmlspecialchars($feats['support'] ?? 'Email') ?></strong> support</li>
          <li>📊 Analytics <?= !empty($feats['analytics']) ? '✓' : '✗' ?></li>
          <li>🔗 API Access <?= !empty($feats['api_access']) ? '✓' : '✗' ?></li>
        </ul>

        <?php if ($isPending): ?>
        <!-- Pending: show "Complete Payment" button -->
        <a href="subscription_payment.php?sub_id=<?= $curSub['subscription_id'] ?>"
           class="btn btn-primary" style="display:block;width:100%;padding:12px;text-align:center;text-decoration:none;">
          💳 Complete Payment →
        </a>
        <?php elseif ($isCurrent): ?>
        <button class="btn btn-teal" style="width:100%;padding:12px;" disabled>✅ Active Plan</button>
        <?php else: ?>
        <form method="POST">
          <input type="hidden" name="plan_id" value="<?= $plan['plan_id'] ?>">
          <button type="submit" name="subscribe"
                  class="btn <?= $isRec ? 'btn-teal' : 'btn-primary' ?>"
                  style="width:100%;padding:12px;">
            <?= in_array($subStatus['status'],['none','expired','cancelled'])
                ? '💳 Subscribe & Pay →'
                : '🔄 Switch & Pay →' ?>
          </button>
        </form>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ══ History ══ -->
    <?php if (!empty($history)): ?>
    <div class="card">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:18px;">📜 Subscription History</h3>
      <div class="table-wrap">
        <table class="history-table">
          <thead><tr><th>Plan</th><th>Start</th><th>End</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($history as $h): ?>
            <tr>
              <td><strong><?= htmlspecialchars($h['plan_name']) ?></strong></td>
              <td><?= date('d M Y', strtotime($h['start_date'])) ?></td>
              <td><?= date('d M Y', strtotime($h['end_date'])) ?></td>
              <td><?= number_format($h['amount_paid'],3) ?> OMR</td>
              <td>
                <span class="status-pill pill-<?= str_replace('_payment','',$h['status']) ?>">
                  <?= ucfirst(str_replace('_',' ',$h['status'])) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; // !$dbMissing ?>

  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
