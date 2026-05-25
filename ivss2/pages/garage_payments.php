<?php
require_once __DIR__ . '/../includes/config.php';
if (!isGarageLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=garage');

$db       = getDB();
$garageId = $_SESSION['garage_id'];

// ── Subscription check (safe — won't crash if tables missing) ──
$hasSubscription = isSubscriptionActive($garageId);
$subStatus       = getSubscriptionStatus($garageId);


// Mark payment as paid manually (cash)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $paymentId = intval($_POST['payment_id']);
    $db->prepare("
        UPDATE payments p
        JOIN service_requests r ON p.request_id = r.request_id
        SET p.status='paid', p.paid_at=NOW(), p.method='cash'
        WHERE p.payment_id=? AND r.garage_id=?
    ")->execute([$paymentId, $garageId]);
    redirect(SITE_URL . '/pages/garage_payments.php');
}

// Filter
$filterPay = sanitize($_GET['pay_status'] ?? '');

$sql    = "
    SELECT p.*, r.service_type, r.vehicle_type, r.location_desc,
           u.full_name AS user_name, u.phone AS user_phone
    FROM payments p
    JOIN service_requests r ON p.request_id = r.request_id
    JOIN users u ON p.user_id = u.user_id
    WHERE r.garage_id = ?
";
$params = [$garageId];
if (in_array($filterPay, ['pending','paid','failed'])) {
    $sql .= " AND p.status = ?";
    $params[] = $filterPay;
}
$sql .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Summary totals
$totalRevenue = $db->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN service_requests r ON p.request_id=r.request_id WHERE r.garage_id=? AND p.status='paid'");
$totalRevenue->execute([$garageId]);

$totalPending = $db->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN service_requests r ON p.request_id=r.request_id WHERE r.garage_id=? AND p.status='pending'");
$totalPending->execute([$garageId]);

$countPaid = $db->prepare("SELECT COUNT(*) FROM payments p JOIN service_requests r ON p.request_id=r.request_id WHERE r.garage_id=? AND p.status='paid'");
$countPaid->execute([$garageId]);

// ── Pending complaints count (for badge) ──
$openComplaints = $db->prepare("SELECT COUNT(*) FROM complaints WHERE garage_id=? AND status='open'");
$openComplaints->execute([$garageId]);
$openComplaints = $openComplaints->fetchColumn();


$pageTitle = 'Payments';
include __DIR__ . '/../includes/header.php';
?>

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
      <a href="garage_payments.php" class="active"><span class="nav-icon">💳</span> Payments</a>
      <a href="garage_complaints.php">
        <span class="nav-icon">⚠️</span> Complaints
        <?php if ($openComplaints > 0): ?>
        <span style="background:var(--danger);color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700;margin-left:4px;"><?= $openComplaints ?></span>
        <?php endif; ?>
      </a>
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
        <div class="dash-title">Payments & Invoices</div>
        <div class="dash-sub">Track all payments and revenue from your services.</div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="metric-grid" style="margin-bottom:28px;">
      <div class="metric-card">
        <div class="metric-icon">💰</div>
        <div class="metric-label">Total Revenue</div>
        <div class="metric-value" style="color:var(--teal);"><?= number_format($totalRevenue->fetchColumn(), 3) ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px;">OMR collected</div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">⏳</div>
        <div class="metric-label">Pending Amount</div>
        <div class="metric-value" style="color:var(--warning);"><?= number_format($totalPending->fetchColumn(), 3) ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px;">OMR awaiting payment</div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">✅</div>
        <div class="metric-label">Paid Invoices</div>
        <div class="metric-value"><?= $countPaid->fetchColumn() ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px;">transactions</div>
      </div>
    </div>

    <!-- Filter Tabs -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
      <?php foreach (['' => 'All', 'pending'=>'Pending','paid'=>'Paid','failed'=>'Failed'] as $s => $label):
        $active = ($filterPay === $s);
      ?>
      <a href="?pay_status=<?= $s ?>"
         style="padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;
                background:<?= $active ? 'var(--navy)' : 'var(--white)' ?>;
                color:<?= $active ? 'var(--white)' : 'var(--muted)' ?>;
                border:1px solid <?= $active ? 'var(--navy)' : 'var(--border)' ?>;">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Payments Table -->
    <?php if (empty($payments)): ?>
    <div class="card" style="text-align:center;padding:64px 32px;">
      <div style="font-size:56px;margin-bottom:16px;">💳</div>
      <div style="font-size:18px;font-weight:700;margin-bottom:8px;">No payments found</div>
      <p style="color:var(--muted);">Payments will appear here once services are completed.</p>
    </div>

    <?php else: ?>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Invoice</th>
              <th>Driver</th>
              <th>Service</th>
              <th>Amount (OMR)</th>
              <th>Method</th>
              <th>Status</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
              <td style="font-weight:600;font-size:13px;color:var(--teal);"><?= $p['invoice_number'] ?></td>
              <td>
                <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($p['user_name']) ?></div>
                <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($p['user_phone']) ?></div>
              </td>
              <td><?= ucfirst($p['service_type']) ?></td>
              <td style="font-weight:700;font-size:16px;color:var(--navy);"><?= number_format($p['amount'], 3) ?></td>
              <td style="font-size:13px;text-transform:capitalize;"><?= $p['method'] ?? '—' ?></td>
              <td>
                <span class="badge <?= $p['status'] === 'paid' ? 'badge-paid' : ($p['status'] === 'pending' ? 'badge-pending' : 'badge-cancelled') ?>">
                  <?= ucfirst($p['status']) ?>
                </span>
              </td>
              <td style="font-size:13px;color:var(--muted);"><?= $p['paid_at'] ? date('d M Y', strtotime($p['paid_at'])) : date('d M Y', strtotime($p['created_at'])) ?></td>
              <td>
                <?php if ($p['status'] === 'pending'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                  <button type="submit" name="mark_paid" class="btn btn-teal btn-sm"
                          onclick="return confirm('Mark this payment as received (cash)?')">
                    ✓ Mark Paid
                  </button>
                </form>
                <?php else: ?>
                <span style="font-size:13px;color:var(--muted);">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
