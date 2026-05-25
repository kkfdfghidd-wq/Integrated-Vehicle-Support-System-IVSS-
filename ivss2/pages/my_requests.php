<?php
require_once __DIR__ . '/../includes/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/pages/login.php');

$db     = getDB();
$userId = $_SESSION['user_id'];

$filterStatus  = sanitize($_GET['status'] ?? '');
$validStatuses = ['pending','accepted','in_progress','completed','cancelled'];

// LEFT JOIN garages — garage_id can be NULL for broadcast requests
$sql = "
    SELECT r.*,
           f.feedback_id, f.rating AS feedback_rating,
           g.garage_name, g.location AS garage_location, g.phone AS garage_phone,
           p.payment_id, p.status AS payment_status, p.amount, p.invoice_number
    FROM   service_requests r
    LEFT   JOIN garages  g ON g.garage_id  = r.garage_id
    LEFT   JOIN payments p ON p.request_id = r.request_id
    LEFT   JOIN feedback f ON f.request_id = r.request_id AND f.user_id = r.user_id
    WHERE  r.user_id = ?
";
$params = [$userId];
if (in_array($filterStatus, $validStatuses)) {
    $sql .= " AND r.status = ?";
    $params[] = $filterStatus;
}
$sql .= " ORDER BY r.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$pageTitle = 'My Requests';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar">
    <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:8px;">
      <div style="font-size:13px;color:rgba(255,255,255,0.4);">Logged in as</div>
      <div style="font-size:15px;font-weight:600;color:var(--white);margin-top:4px;"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
    </div>
    <div class="sidebar-nav">
      <div class="sidebar-section">Driver Panel</div>
      <a href="dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="request.php"><span class="nav-icon">🚨</span> New Request</a>
      <a href="my_requests.php" class="active"><span class="nav-icon">📋</span> My Requests</a>
      <a href="garages.php"><span class="nav-icon">🔧</span> Find Garages</a>
      <div class="sidebar-section">Account</div>
      <a href="profile.php"><span class="nav-icon">👤</span> Profile</a>
      <a href="logout.php"><span class="nav-icon">🚪</span> Logout</a>
    </div>
  </div>

  <div class="dash-content">
    <div class="dash-header">
      <div>
        <div class="dash-title">My Service Requests</div>
        <div class="dash-sub">Track and manage all your roadside assistance requests.</div>
      </div>
      <a href="request.php" class="btn btn-primary">+ New Request</a>
    </div>

    <!-- Filter Tabs -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;">
      <?php
      $tabs = ['' => 'All','pending'=>'Pending','accepted'=>'Accepted','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'];
      foreach ($tabs as $s => $label):
        $active = ($filterStatus === $s);
      ?>
      <a href="?status=<?= $s ?>"
         style="padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;
                background:<?= $active?'var(--navy)':'var(--white)' ?>;
                color:<?= $active?'var(--white)':'var(--muted)' ?>;
                border:1px solid <?= $active?'var(--navy)':'var(--border)' ?>;">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($requests)): ?>
    <div class="card" style="text-align:center;padding:64px 32px;">
      <div style="font-size:56px;margin-bottom:16px;">📋</div>
      <div style="font-size:18px;font-weight:700;margin-bottom:8px;">No requests found</div>
      <p style="color:var(--muted);margin-bottom:28px;">
        <?= $filterStatus ? 'No '.str_replace('_',' ',$filterStatus).' requests.' : "You haven't made any service requests yet." ?>
      </p>
      <a href="request.php" class="btn btn-primary">Request Help Now</a>
    </div>

    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:16px;">
      <?php foreach ($requests as $r): ?>
      <div class="card" style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">

        <div style="width:52px;height:52px;border-radius:12px;background:rgba(26,158,138,0.1);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">
          <?= ['towing'=>'🚛','battery'=>'🔋','tire'=>'🛞','fuel'=>'⛽','lockout'=>'🔑','repair'=>'🔧','other'=>'🔩'][$r['service_type']] ?? '🔧' ?>
        </div>

        <div style="flex:1;min-width:200px;">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
            <span style="font-weight:700;font-size:16px;"><?= ucfirst($r['service_type']) ?></span>
            <span class="badge badge-<?= $r['status'] ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span>
            <?php if ($r['payment_status']==='paid'): ?>
            <span class="badge badge-paid">Paid ✓</span>
            <?php elseif ($r['status']==='completed' && $r['payment_status']!=='paid'): ?>
            <span class="badge badge-unpaid">Payment Pending</span>
            <?php endif; ?>
          </div>
          <div style="font-size:13px;color:var(--muted);margin-bottom:4px;">
            <?php if ($r['garage_name']): ?>
            🔧 <?= htmlspecialchars($r['garage_name']) ?> &nbsp;·&nbsp; 📍 <?= htmlspecialchars($r['garage_location']) ?>
            <?php else: ?>
            <span style="color:var(--warning);">📢 Waiting for a garage to accept...</span>
            <?php endif; ?>
          </div>
          <div style="font-size:13px;color:var(--muted);margin-bottom:4px;">
            📌 <?= htmlspecialchars($r['location_desc']) ?> &nbsp;·&nbsp; 🚗 <?= ucfirst($r['vehicle_type']) ?>
          </div>
          <div style="font-size:12px;color:var(--muted);">
            🕐 <?= date('d M Y, H:i', strtotime($r['created_at'])) ?>
            <?php if ($r['invoice_number']): ?>
            &nbsp;·&nbsp; 🧾 <?= $r['invoice_number'] ?> &nbsp;·&nbsp; <strong><?= number_format($r['amount'],3) ?> OMR</strong>
            <?php endif; ?>
          </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;flex-shrink:0;">
          <a href="track.php?id=<?= $r['request_id'] ?>" class="btn btn-dark btn-sm">📍 Track</a>
          <?php if ($r['status']==='completed' && $r['payment_status']==='pending'): ?>
          <a href="payment.php?request_id=<?= $r['request_id'] ?>" class="btn btn-primary btn-sm">💳 Pay</a>
          <?php endif; ?>
          <?php if ($r['payment_status']==='paid' && $r['payment_id']): ?>
          <a href="invoice.php?payment_id=<?= $r['payment_id'] ?>" class="btn btn-teal btn-sm">🧾 Invoice</a>
          <?php endif; ?>
          <?php if ($r['status']==='completed' && $r['garage_name']): ?>
            <?php if ($r['feedback_rating']): ?>
            <div style="display:flex;align-items:center;gap:4px;justify-content:flex-end;">
              <?php for($s=1;$s<=5;$s++): ?>
              <span style="font-size:15px;color:<?= $s<=$r['feedback_rating']?'#f5a623':'#ddd' ?>;">★</span>
              <?php endfor; ?>
            </div>
            <div style="font-size:11px;color:var(--muted);text-align:right;">Your rating</div>
            <?php else: ?>
            <a href="feedback.php?request_id=<?= $r['request_id'] ?>"
               class="btn btn-sm"
               style="background:rgba(245,166,35,0.1);color:#a07820;border:1px solid rgba(245,166,35,0.4);">
              ⭐ Rate Service
            </a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
