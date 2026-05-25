<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdminLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=admin');

$db = getDB();

$msg = '';
// Admin can force-update any request status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rid    = intval($_POST['request_id'] ?? 0);

    if ($action === 'update_status' && $rid) {
        $newStatus = sanitize($_POST['new_status'] ?? '');
        $allowed   = ['pending','accepted','in_progress','completed','cancelled'];
        if (in_array($newStatus, $allowed)) {
            $db->prepare("UPDATE service_requests SET status=? WHERE request_id=?")->execute([$newStatus, $rid]);
            // Auto-create payment if completed
            if ($newStatus === 'completed') {
                $ex = $db->prepare("SELECT payment_id FROM payments WHERE request_id=?");
                $ex->execute([$rid]);
                if (!$ex->fetch()) {
                    $rr = $db->prepare("SELECT user_id FROM service_requests WHERE request_id=?");
                    $rr->execute([$rid]);
                    $rRow = $rr->fetch();
                    if ($rRow) {
                        $db->prepare("INSERT INTO payments (request_id,user_id,amount,status,invoice_number) VALUES (?,?,?,'pending',?)")
                           ->execute([$rid, $rRow['user_id'], 15.000, generateInvoiceNumber()]);
                    }
                }
            }
            $msg = 'Request status updated.';
        }
    }

    if ($action === 'delete' && $rid) {
        $db->prepare("DELETE FROM service_requests WHERE request_id=?")->execute([$rid]);
        $msg = 'Request deleted.';
    }
}

// Filters
$filterStatus  = sanitize($_GET['status']  ?? '');
$filterService = sanitize($_GET['service'] ?? '');
$search        = sanitize($_GET['search']  ?? '');

$sql    = "SELECT r.*,
                  u.full_name AS user_name, u.phone AS user_phone,
                  COALESCE(g.garage_name, '— Broadcast —') AS garage_name,
                  p.status AS pay_status, p.amount, p.invoice_number
           FROM service_requests r
           JOIN users u ON r.user_id = u.user_id
           LEFT JOIN garages  g ON g.garage_id  = r.garage_id
           LEFT JOIN payments p ON p.request_id = r.request_id";
$params = [];
$where  = [];

if ($filterStatus && in_array($filterStatus, ['pending','accepted','in_progress','completed','cancelled']))
    { $where[] = "r.status=?"; $params[] = $filterStatus; }
if ($filterService)
    { $where[] = "r.service_type=?"; $params[] = $filterService; }
if ($search)
    { $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR r.location_desc LIKE ?)";
      $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY r.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Status counts
$counts = [];
foreach ($db->query("SELECT status, COUNT(*) AS cnt FROM service_requests GROUP BY status") as $row)
    $counts[$row['status']] = $row['cnt'];
$counts[''] = array_sum($counts);

$pageTitle = 'Manage Requests';
include __DIR__ . '/admin_header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>

  <div class="dash-content">
    <div class="dash-header">
      <div>
        <div class="dash-title">Service Requests 📋</div>
        <div class="dash-sub">Full oversight of all service requests across the platform.</div>
      </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Metric Tabs -->
    <div class="metric-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:24px;">
      <?php
      $statColors = ['pending'=>'var(--warning)','accepted'=>'var(--teal)','in_progress'=>'var(--gold)','completed'=>'var(--teal)','cancelled'=>'var(--danger)'];
      foreach (['pending','accepted','in_progress','completed','cancelled'] as $s):
      ?>
      <div class="metric-card" style="cursor:pointer;" onclick="location.href='?status=<?= $s ?>'">
        <div class="metric-label"><?= ucfirst(str_replace('_',' ',$s)) ?></div>
        <div class="metric-value" style="font-size:26px;color:<?= $statColors[$s] ?>;"><?= $counts[$s]??0 ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Search & Filter -->
    <div class="card" style="margin-bottom:20px;">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:200px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">Search</label>
          <input type="text" name="search" class="form-control" placeholder="Driver name, email, location..."
                 value="<?= htmlspecialchars($search) ?>" style="margin-top:4px;">
        </div>
        <div class="form-group" style="min-width:150px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">Status</label>
          <select name="status" class="form-control" style="margin-top:4px;">
            <option value="">All Statuses</option>
            <?php foreach (['pending','accepted','in_progress','completed','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="min-width:150px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">Service Type</label>
          <select name="service" class="form-control" style="margin-top:4px;">
            <option value="">All Services</option>
            <?php foreach (['towing','battery','tire','fuel','lockout','repair','other'] as $s): ?>
            <option value="<?= $s ?>" <?= $filterService===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if ($search || $filterStatus || $filterService): ?>
        <a href="requests.php" class="btn btn-dark btn-sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Results -->
    <div class="card">
      <div style="margin-bottom:16px;font-size:14px;color:var(--muted);">
        Showing <strong style="color:var(--text);"><?= count($requests) ?></strong> request<?= count($requests)!==1?'s':'' ?>
      </div>

      <?php if (empty($requests)): ?>
      <div style="text-align:center;padding:56px;color:var(--muted);">
        <div style="font-size:48px;margin-bottom:12px;">📋</div>
        <p>No requests found matching your criteria.</p>
      </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Driver</th>
              <th>Service</th>
              <th>Garage</th>
              <th>Location</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $r): ?>
            <tr>
              <td style="font-weight:700;color:var(--muted);font-size:12px;">
                IVSS-<?= str_pad($r['request_id'],4,'0',STR_PAD_LEFT) ?>
              </td>
              <td>
                <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($r['user_name']) ?></div>
                <div style="font-size:11px;color:var(--muted);">📞 <?= htmlspecialchars($r['user_phone']) ?></div>
              </td>
              <td>
                <span style="font-size:13px;"><?= ucfirst($r['service_type']) ?></span><br>
                <span style="font-size:11px;color:var(--muted);"><?= ucfirst($r['vehicle_type']) ?></span>
              </td>
              <td style="font-size:13px;"><?= htmlspecialchars($r['garage_name']) ?></td>
              <td style="font-size:12px;color:var(--muted);max-width:140px;">
                <?= htmlspecialchars(substr($r['location_desc'],0,30)) ?>...
              </td>
              <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
              <td>
                <?php if ($r['pay_status']): ?>
                <span class="badge <?= $r['pay_status']==='paid'?'badge-paid':'badge-pending' ?>">
                  <?= ucfirst($r['pay_status']) ?>
                  <?php if ($r['amount']): ?> — <?= number_format($r['amount'],3) ?><?php endif; ?>
                </span>
                <?php else: ?>
                <span style="font-size:12px;color:var(--muted);">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:var(--muted);"><?= date('d M Y\nH:i', strtotime($r['created_at'])) ?></td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                  <!-- Status Update -->
                  <?php if (!in_array($r['status'],['completed','cancelled'])): ?>
                  <form method="POST" style="display:flex;gap:4px;">
                    <input type="hidden" name="action"     value="update_status">
                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                    <select name="new_status" class="form-control"
                            style="padding:4px 6px;font-size:11px;min-width:100px;">
                      <?php foreach (['pending','accepted','in_progress','completed','cancelled'] as $s): ?>
                      <option value="<?= $s ?>" <?= $s===$r['status']?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-teal btn-sm" style="padding:4px 8px;font-size:11px;">Set</button>
                  </form>
                  <?php endif; ?>
                  <!-- Delete -->
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action"     value="delete">
                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                    <button class="btn btn-sm" style="background:rgba(226,75,74,0.08);color:var(--danger);border:none;padding:4px 8px;font-size:11px;"
                            onclick="return confirm('Delete request IVSS-<?= str_pad($r['request_id'],4,'0',STR_PAD_LEFT) ?> permanently?')">
                      🗑️
                    </button>
                  </form>
                </div>
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

<?php include __DIR__ . '/admin_footer.php'; ?>
