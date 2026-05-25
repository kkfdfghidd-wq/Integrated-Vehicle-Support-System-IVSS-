<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdminLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=admin');

$db = getDB();

$totalUsers    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalGarages  = $db->query("SELECT COUNT(*) FROM garages WHERE is_active=1")->fetchColumn();
$totalRequests = $db->query("SELECT COUNT(*) FROM service_requests")->fetchColumn();
$totalRevenue  = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")->fetchColumn();
$pendingReq    = $db->query("SELECT COUNT(*) FROM service_requests WHERE status='pending'")->fetchColumn();
$newUsers7days = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();

// Subscription stats
$subStats = ['subscribed' => 0, 'unsubscribed' => 0, 'expiring' => 0, 'sub_revenue' => 0];
try {
    $subStats['subscribed'] = (int) $db->query("
        SELECT COUNT(DISTINCT garage_id) FROM garage_subscriptions
        WHERE status='active' AND end_date >= CURDATE()
    ")->fetchColumn();
    $subStats['unsubscribed'] = max(0, (int)$db->query("SELECT COUNT(*) FROM garages")->fetchColumn() - $subStats['subscribed']);
    $subStats['expiring'] = (int) $db->query("
        SELECT COUNT(*) FROM garage_subscriptions
        WHERE status='active' AND end_date >= CURDATE() AND DATEDIFF(end_date, CURDATE()) <= 3
    ")->fetchColumn();
    $subStats['sub_revenue'] = (float) $db->query("
        SELECT COALESCE(SUM(amount_paid),0) FROM garage_subscriptions WHERE status='active'
    ")->fetchColumn();
} catch (Exception $e) {}

// Recent requests
$recentReq = $db->query("
    SELECT r.*, u.full_name AS user_name,
           COALESCE(g.garage_name,'— Open —') AS garage_name
    FROM   service_requests r
    JOIN   users u ON r.user_id=u.user_id
    LEFT   JOIN garages g ON r.garage_id=g.garage_id
    ORDER  BY r.created_at DESC LIMIT 8
")->fetchAll();

// Recent subscriptions
$recentSubs = [];
try {
    $recentSubs = $db->query("
        SELECT gs.*, g.garage_name, sp.plan_name, sp.price
        FROM garage_subscriptions gs
        JOIN garages g ON gs.garage_id = g.garage_id
        JOIN subscription_plans sp ON gs.plan_id = sp.plan_id
        ORDER BY gs.created_at DESC LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/admin_header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>
  <div class="dash-content">
    <div class="dash-header">
      <div>
        <div class="dash-title">Admin Dashboard ⚙️</div>
        <div class="dash-sub">System overview — <?= date('d M Y, H:i') ?></div>
      </div>
    </div>

    <!-- Platform Metrics -->
    <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">
      Platform Overview
    </div>
    <div class="metric-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:28px;">
      <div class="metric-card">
        <div class="metric-icon">👤</div>
        <div class="metric-label">Total Users</div>
        <div class="metric-value"><?= $totalUsers ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">🔧</div>
        <div class="metric-label">Active Garages</div>
        <div class="metric-value"><?= $totalGarages ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">📋</div>
        <div class="metric-label">Total Requests</div>
        <div class="metric-value"><?= $totalRequests ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">⏳</div>
        <div class="metric-label">Pending</div>
        <div class="metric-value" style="color:var(--warning);"><?= $pendingReq ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">💰</div>
        <div class="metric-label">Revenue (OMR)</div>
        <div class="metric-value" style="color:var(--teal);"><?= number_format($totalRevenue,3) ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">🆕</div>
        <div class="metric-label">New Users 7d</div>
        <div class="metric-value" style="color:var(--gold);"><?= $newUsers7days ?></div>
      </div>
    </div>

    <!-- Subscription Metrics -->
    <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">
      Subscriptions
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:28px;">
      <!-- Subscribed -->
      <div style="background:rgba(26,158,138,0.07);border:1px solid rgba(26,158,138,0.2);border-radius:12px;padding:18px 20px;text-align:center;">
        <div style="font-size:28px;font-weight:800;color:var(--teal);"><?= $subStats['subscribed'] ?></div>
        <div style="font-size:12px;color:var(--muted);font-weight:600;margin-top:4px;">✅ Subscribed Garages</div>
      </div>
      <!-- Unsubscribed -->
      <div style="background:rgba(226,75,74,0.07);border:1px solid rgba(226,75,74,0.2);border-radius:12px;padding:18px 20px;text-align:center;cursor:pointer;"
           onclick="location.href='admin_subscriptions.php?tab=assign'">
        <div style="font-size:28px;font-weight:800;color:var(--danger);"><?= $subStats['unsubscribed'] ?></div>
        <div style="font-size:12px;color:var(--muted);font-weight:600;margin-top:4px;">❌ Unsubscribed</div>
        <?php if ($subStats['unsubscribed'] > 0): ?>
        <div style="font-size:11px;color:var(--danger);margin-top:4px;">Click to assign →</div>
        <?php endif; ?>
      </div>
      <!-- Expiring -->
      <div style="background:rgba(212,168,67,0.07);border:1px solid rgba(212,168,67,0.2);border-radius:12px;padding:18px 20px;text-align:center;cursor:pointer;"
           onclick="location.href='admin_subscriptions.php'">
        <div style="font-size:28px;font-weight:800;color:#a07820;"><?= $subStats['expiring'] ?></div>
        <div style="font-size:12px;color:var(--muted);font-weight:600;margin-top:4px;">⚠️ Expiring ≤3 Days</div>
      </div>
      <!-- Sub Revenue -->
      <div style="background:rgba(26,158,138,0.05);border:1px solid rgba(26,158,138,0.15);border-radius:12px;padding:18px 20px;text-align:center;">
        <div style="font-size:22px;font-weight:800;color:var(--teal);"><?= number_format($subStats['sub_revenue'],3) ?></div>
        <div style="font-size:12px;color:var(--muted);font-weight:600;margin-top:4px;">💰 Sub Revenue (OMR)</div>
      </div>
      <!-- Quick Link -->
      <div style="background:var(--white);border:1.5px dashed var(--border);border-radius:12px;padding:18px 20px;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;gap:8px;"
           onclick="location.href='admin_subscriptions.php'">
        <div style="font-size:24px;">⭐</div>
        <div style="font-size:12px;font-weight:700;color:var(--muted);">Manage Subscriptions</div>
      </div>
    </div>

    <!-- Two-column bottom -->
    <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:20px;align-items:start;">

      <!-- Recent Requests -->
      <div class="card">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;">
          Recent Service Requests
          <a href="requests.php" style="font-size:12px;color:var(--teal);font-weight:600;margin-left:10px;text-decoration:none;">View all →</a>
        </h3>
        <div class="table-wrap">
          <table>
            <thead><tr><th>#</th><th>Driver</th><th>Service</th><th>Garage</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($recentReq as $r): ?>
              <tr>
                <td style="font-weight:600;color:var(--muted);font-size:12px;">IVSS-<?= str_pad($r['request_id'],4,'0',STR_PAD_LEFT) ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['user_name']) ?></td>
                <td style="font-size:13px;"><?= ucfirst($r['service_type']) ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['garage_name']) ?></td>
                <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
                <td style="font-size:12px;color:var(--muted);"><?= date('d M',strtotime($r['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Subscriptions -->
      <div class="card">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;">
          Recent Subscriptions
          <a href="admin_subscriptions.php" style="font-size:12px;color:var(--teal);font-weight:600;margin-left:10px;text-decoration:none;">Manage →</a>
        </h3>
        <?php if (empty($recentSubs)): ?>
        <div style="text-align:center;padding:32px;color:var(--muted);">
          <div style="font-size:36px;margin-bottom:8px;">⭐</div>
          <p style="font-size:13px;">No subscriptions yet.</p>
          <a href="admin_subscriptions.php?tab=assign" class="btn btn-primary btn-sm" style="margin-top:8px;">Assign Now</a>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php foreach ($recentSubs as $s):
            $daysLeft = (int)floor((strtotime($s['end_date']) - time()) / 86400);
          ?>
          <div style="padding:12px 14px;background:var(--bg);border-radius:10px;display:flex;align-items:center;gap:12px;">
            <div style="font-size:22px;">
              <?= $s['status']==='active' ? '✅' : ($s['status']==='pending_payment' ? '⏳' : '❌') ?>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= htmlspecialchars($s['garage_name']) ?>
              </div>
              <div style="font-size:11px;color:var(--muted);">
                <?= htmlspecialchars($s['plan_name']) ?> · <?= number_format($s['price'],3) ?> OMR
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
              <div style="font-size:13px;font-weight:700;color:<?= $daysLeft<=3?'var(--danger)':'var(--teal)' ?>;">
                <?= $s['status']==='active' ? $daysLeft.'d' : ucfirst($s['status']) ?>
              </div>
              <div style="font-size:11px;color:var(--muted);"><?= date('d M',strtotime($s['created_at'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>
