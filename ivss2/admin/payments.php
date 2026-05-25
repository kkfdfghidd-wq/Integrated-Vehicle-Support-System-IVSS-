<?php
require_once __DIR__ . '/../includes/config.php';
if (!isAdminLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=admin');

$db = getDB();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid    = intval($_POST['payment_id'] ?? 0);

    if ($action === 'update_status' && $pid) {
        $newStatus = sanitize($_POST['new_status'] ?? '');
        if (in_array($newStatus, ['pending','paid','failed'])) {
            $paidAt = ($newStatus === 'paid') ? ", paid_at=NOW()" : "";
            $db->prepare("UPDATE payments SET status=?{$paidAt} WHERE payment_id=?")->execute([$newStatus, $pid]);
            $msg = 'Payment status updated.';
        }
    }

    if ($action === 'update_amount' && $pid) {
        $amount = max(0, floatval($_POST['amount'] ?? 0));
        $db->prepare("UPDATE payments SET amount=? WHERE payment_id=?")->execute([$amount, $pid]);
        $msg = 'Amount updated.';
    }

    if ($action === 'delete' && $pid) {
        $db->prepare("DELETE FROM payments WHERE payment_id=?")->execute([$pid]);
        $msg = 'Payment record deleted.';
    }
}

// Filters
$filterStatus = sanitize($_GET['pay_status'] ?? '');
$filterMethod = sanitize($_GET['method']     ?? '');
$search       = sanitize($_GET['search']     ?? '');
$dateFrom     = sanitize($_GET['date_from']  ?? '');
$dateTo       = sanitize($_GET['date_to']    ?? '');

$sql    = "SELECT p.*,
                  u.full_name AS user_name, u.phone AS user_phone,
                  g.garage_name,
                  r.service_type, r.vehicle_type, r.location_desc
           FROM payments p
           JOIN service_requests r ON p.request_id = r.request_id
           JOIN users u             ON p.user_id    = u.user_id
           LEFT JOIN garages g      ON g.garage_id  = r.garage_id";
$params = [];
$where  = [];

if ($filterStatus && in_array($filterStatus,['pending','paid','failed']))
    { $where[] = "p.status=?"; $params[] = $filterStatus; }
if ($filterMethod && in_array($filterMethod,['card','cash','online']))
    { $where[] = "p.method=?"; $params[] = $filterMethod; }
if ($search)
    { $where[] = "(u.full_name LIKE ? OR p.invoice_number LIKE ? OR u.phone LIKE ?)";
      $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($dateFrom)
    { $where[] = "DATE(p.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)
    { $where[] = "DATE(p.created_at) <= ?"; $params[] = $dateTo; }

if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Summary stats
$totalRevenue  = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")->fetchColumn();
$totalPending  = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='pending'")->fetchColumn();
$totalFailed   = $db->query("SELECT COUNT(*) FROM payments WHERE status='failed'")->fetchColumn();
$countPaid     = $db->query("SELECT COUNT(*) FROM payments WHERE status='paid'")->fetchColumn();
$avgAmount     = $db->query("SELECT COALESCE(AVG(amount),0) FROM payments WHERE status='paid'")->fetchColumn();

// Revenue by method
$byMethod = [];
foreach ($db->query("SELECT method, COUNT(*) AS cnt, SUM(amount) AS total FROM payments WHERE status='paid' AND method IS NOT NULL GROUP BY method") as $row)
    $byMethod[$row['method']] = $row;

$pageTitle = 'Payments';
include __DIR__ . '/admin_header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/admin_sidebar.php'; ?>

  <div class="dash-content">
    <div class="dash-header">
      <div>
        <div class="dash-title">Payments & Revenue 💰</div>
        <div class="dash-sub">Track and manage all platform transactions.</div>
      </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Summary Metrics -->
    <div class="metric-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:24px;">
      <div class="metric-card">
        <div class="metric-icon">💰</div>
        <div class="metric-label">Total Revenue</div>
        <div class="metric-value" style="color:var(--teal);"><?= number_format($totalRevenue,3) ?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:3px;">OMR collected</div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">✅</div>
        <div class="metric-label">Paid</div>
        <div class="metric-value"><?= $countPaid ?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:3px;">transactions</div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">⏳</div>
        <div class="metric-label">Pending (OMR)</div>
        <div class="metric-value" style="color:var(--warning);"><?= number_format($totalPending,3) ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">❌</div>
        <div class="metric-label">Failed</div>
        <div class="metric-value" style="color:var(--danger);"><?= $totalFailed ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-icon">📊</div>
        <div class="metric-label">Avg. Amount</div>
        <div class="metric-value" style="font-size:20px;"><?= number_format($avgAmount,3) ?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:3px;">OMR per invoice</div>
      </div>
    </div>

    <!-- Payment Method Breakdown -->
    <?php if (!empty($byMethod)): ?>
    <div class="card" style="margin-bottom:20px;">
      <div style="font-size:14px;font-weight:700;margin-bottom:12px;">Revenue by Payment Method</div>
      <div style="display:flex;gap:16px;flex-wrap:wrap;">
        <?php
        $methodIcons = ['card'=>'💳','cash'=>'💵','online'=>'🌐'];
        foreach ($byMethod as $m => $data): ?>
        <div style="background:var(--bg);border-radius:10px;padding:12px 20px;flex:1;min-width:120px;text-align:center;">
          <div style="font-size:20px;margin-bottom:4px;"><?= $methodIcons[$m]??'💳' ?></div>
          <div style="font-size:12px;font-weight:700;text-transform:capitalize;color:var(--muted);"><?= $m ?></div>
          <div style="font-size:18px;font-weight:700;color:var(--navy);margin-top:4px;"><?= number_format($data['total'],3) ?> OMR</div>
          <div style="font-size:11px;color:var(--muted);"><?= $data['cnt'] ?> transactions</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Search & Filter -->
    <div class="card" style="margin-bottom:20px;">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:180px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">Search</label>
          <input type="text" name="search" class="form-control" placeholder="Driver name, invoice, phone..."
                 value="<?= htmlspecialchars($search) ?>" style="margin-top:4px;">
        </div>
        <div class="form-group" style="min-width:130px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">Status</label>
          <select name="pay_status" class="form-control" style="margin-top:4px;">
            <option value="">All</option>
            <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>Pending</option>
            <option value="paid"    <?= $filterStatus==='paid'   ?'selected':'' ?>>Paid</option>
            <option value="failed"  <?= $filterStatus==='failed' ?'selected':'' ?>>Failed</option>
          </select>
        </div>
        <div class="form-group" style="min-width:130px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">Method</label>
          <select name="method" class="form-control" style="margin-top:4px;">
            <option value="">All</option>
            <option value="card"   <?= $filterMethod==='card'  ?'selected':'' ?>>💳 Card</option>
            <option value="cash"   <?= $filterMethod==='cash'  ?'selected':'' ?>>💵 Cash</option>
            <option value="online" <?= $filterMethod==='online'?'selected':'' ?>>🌐 Online</option>
          </select>
        </div>
        <div class="form-group" style="min-width:130px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">From</label>
          <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" style="margin-top:4px;">
        </div>
        <div class="form-group" style="min-width:130px;margin:0;">
          <label style="font-size:12px;color:var(--muted);font-weight:600;">To</label>
          <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" style="margin-top:4px;">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if ($search || $filterStatus || $filterMethod || $dateFrom || $dateTo): ?>
        <a href="payments.php" class="btn btn-dark btn-sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Table -->
    <div class="card">
      <div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
        <div style="font-size:14px;color:var(--muted);">
          Showing <strong style="color:var(--text);"><?= count($payments) ?></strong> payment<?= count($payments)!==1?'s':'' ?>
        </div>
        <?php
        $filteredTotal = array_sum(array_column(array_filter($payments, fn($p)=>$p['status']==='paid'), 'amount'));
        if (count($payments) > 0):
        ?>
        <div style="font-size:13px;font-weight:700;color:var(--teal);">
          Filtered Revenue: <?= number_format($filteredTotal,3) ?> OMR
        </div>
        <?php endif; ?>
      </div>

      <?php if (empty($payments)): ?>
      <div style="text-align:center;padding:56px;color:var(--muted);">
        <div style="font-size:48px;margin-bottom:12px;">💳</div>
        <p>No payments found matching your criteria.</p>
      </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Invoice</th>
              <th>Driver</th>
              <th>Garage</th>
              <th>Service</th>
              <th>Amount (OMR)</th>
              <th>Method</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
              <td style="font-weight:700;color:var(--teal);font-size:13px;"><?= htmlspecialchars($p['invoice_number'] ?? '—') ?></td>
              <td>
                <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($p['user_name']) ?></div>
                <div style="font-size:11px;color:var(--muted);">📞 <?= htmlspecialchars($p['user_phone']) ?></div>
              </td>
              <td style="font-size:13px;"><?= htmlspecialchars($p['garage_name'] ?? '—') ?></td>
              <td style="font-size:13px;"><?= ucfirst($p['service_type']) ?></td>
              <td style="font-weight:700;font-size:15px;color:var(--navy);"><?= number_format($p['amount'],3) ?></td>
              <td style="font-size:13px;text-transform:capitalize;"><?= $p['method'] ? (['card'=>'💳 Card','cash'=>'💵 Cash','online'=>'🌐 Online'][$p['method']] ?? $p['method']) : '—' ?></td>
              <td>
                <span class="badge <?= $p['status']==='paid'?'badge-paid':($p['status']==='pending'?'badge-pending':'badge-cancelled') ?>">
                  <?= ucfirst($p['status']) ?>
                </span>
              </td>
              <td style="font-size:12px;color:var(--muted);">
                <?= $p['paid_at'] ? date('d M Y', strtotime($p['paid_at'])) : date('d M Y', strtotime($p['created_at'])) ?>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                  <!-- Update Status -->
                  <form method="POST" style="display:flex;gap:4px;">
                    <input type="hidden" name="action"     value="update_status">
                    <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                    <select name="new_status" class="form-control"
                            style="padding:4px 6px;font-size:11px;min-width:80px;">
                      <option value="pending" <?= $p['status']==='pending'?'selected':'' ?>>Pending</option>
                      <option value="paid"    <?= $p['status']==='paid'   ?'selected':'' ?>>Paid</option>
                      <option value="failed"  <?= $p['status']==='failed' ?'selected':'' ?>>Failed</option>
                    </select>
                    <button class="btn btn-teal btn-sm" style="padding:4px 8px;font-size:11px;">Set</button>
                  </form>
                  <!-- Update Amount -->
                  <form method="POST" style="display:flex;gap:4px;">
                    <input type="hidden" name="action"     value="update_amount">
                    <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                    <input type="number" name="amount" step="0.001" min="0"
                           value="<?= number_format($p['amount'],3) ?>"
                           class="form-control" style="padding:4px 6px;font-size:11px;width:70px;">
                    <button class="btn btn-dark btn-sm" style="padding:4px 8px;font-size:11px;">💲</button>
                  </form>
                  <!-- Delete -->
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action"     value="delete">
                    <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                    <button class="btn btn-sm" style="background:rgba(226,75,74,0.08);color:var(--danger);border:none;padding:4px 8px;font-size:11px;"
                            onclick="return confirm('Delete this payment record permanently?')">🗑️</button>
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
