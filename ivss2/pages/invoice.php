<?php
require_once __DIR__ . '/../includes/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/pages/login.php');

$db        = getDB();
$paymentId = intval($_GET['payment_id'] ?? 0);
$userId    = $_SESSION['user_id'];

// Fetch payment — must belong to this user
$stmt = $db->prepare("
    SELECT p.*,
           u.full_name   AS user_name,
           u.email       AS user_email,
           u.phone       AS user_phone,
           g.garage_name, g.location AS garage_location,
           g.phone       AS garage_phone, g.email AS garage_email,
           r.service_type, r.vehicle_type, r.location_desc, r.notes,
           r.created_at  AS request_date, r.price AS set_price
    FROM   payments p
    JOIN   service_requests r ON p.request_id = r.request_id
    JOIN   users u             ON p.user_id    = u.user_id
    LEFT   JOIN garages g      ON g.garage_id  = r.garage_id
    WHERE  p.payment_id = ?
      AND  p.user_id    = ?
      AND  p.status     = 'paid'
");
$stmt->execute([$paymentId, $userId]);
$inv = $stmt->fetch();

if (!$inv) redirect(SITE_URL . '/pages/my_requests.php');

$pageTitle = 'Invoice ' . $inv['invoice_number'];
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Print styles ── */
@media print {
  body * { visibility: hidden; }
  #invoice-wrap, #invoice-wrap * { visibility: visible; }
  #invoice-wrap { position: fixed; top: 0; left: 0; width: 100%; }
  .no-print { display: none !important; }
  .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}

/* ── Invoice layout ── */
#invoice-wrap {
  max-width: 820px;
  margin: 0 auto;
  padding: 40px 24px 60px;
}

.inv-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 20px;
  margin-bottom: 32px;
}

.inv-logo {
  display: flex;
  align-items: center;
  gap: 12px;
}
.inv-logo .logo-icon {
  width: 48px; height: 48px;
  background: var(--navy);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px;
}
.inv-logo .logo-text {
  font-size: 22px; font-weight: 800; color: var(--navy);
}
.inv-logo .logo-text span { color: var(--gold); }
.inv-logo .logo-sub {
  font-size: 11px; color: var(--muted); margin-top: 2px;
}

.inv-meta {
  text-align: right;
}
.inv-number {
  font-size: 20px; font-weight: 800; color: var(--navy);
}
.inv-date {
  font-size: 13px; color: var(--muted); margin-top: 4px;
}
.inv-status-paid {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(6,163,90,0.1); color: #06a35a;
  border: 1.5px solid rgba(6,163,90,0.3);
  padding: 5px 14px; border-radius: 20px;
  font-size: 13px; font-weight: 700; margin-top: 10px;
}

/* Parties grid */
.inv-parties {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 28px;
}
.inv-party {
  background: var(--bg);
  border-radius: 12px;
  padding: 18px 20px;
  border: 1px solid var(--border);
}
.inv-party-label {
  font-size: 11px; font-weight: 700; color: var(--muted);
  text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;
}
.inv-party-name {
  font-size: 15px; font-weight: 700; margin-bottom: 6px;
}
.inv-party-detail {
  font-size: 13px; color: var(--muted); line-height: 1.7;
}

/* Line items table */
.inv-table {
  width: 100%; border-collapse: collapse;
  margin-bottom: 0;
  font-size: 14px;
}
.inv-table thead tr {
  background: var(--navy); color: var(--white);
}
.inv-table thead th {
  padding: 12px 16px; font-weight: 600; font-size: 12px;
  text-transform: uppercase; letter-spacing: 0.5px;
}
.inv-table thead th:last-child { text-align: right; }
.inv-table tbody tr { border-bottom: 1px solid var(--border); }
.inv-table tbody tr:last-child { border-bottom: none; }
.inv-table tbody td { padding: 14px 16px; vertical-align: top; }
.inv-table tbody td:last-child { text-align: right; font-weight: 700; }

/* Totals */
.inv-totals {
  display: flex;
  justify-content: flex-end;
  margin-top: 0;
}
.inv-totals-inner {
  min-width: 280px;
  border-top: 1px solid var(--border);
}
.inv-total-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 10px 16px;
  font-size: 14px; color: var(--muted);
}
.inv-total-row.grand {
  background: var(--navy); color: var(--white);
  border-radius: 0 0 10px 10px;
  font-size: 17px; font-weight: 700;
}
.inv-total-row.grand span:last-child { color: var(--gold); }

/* Notes / footer */
.inv-notes {
  background: rgba(26,158,138,0.06);
  border: 1px solid rgba(26,158,138,0.15);
  border-radius: 10px;
  padding: 14px 18px;
  font-size: 13px; color: var(--muted);
  margin-top: 24px;
}

.inv-footer {
  text-align: center;
  margin-top: 32px;
  font-size: 12px; color: var(--muted);
  border-top: 1px solid var(--border);
  padding-top: 18px;
}
</style>

<!-- Action buttons (no print) -->
<div class="no-print" style="background:var(--navy);padding:16px 24px;">
  <div style="max-width:820px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
    <a href="my_requests.php" style="color:rgba(255,255,255,0.6);text-decoration:none;font-size:13px;font-weight:600;">
      ← Back to My Requests
    </a>
    <div style="display:flex;gap:10px;">
      <button onclick="window.print()"
              class="btn btn-sm"
              style="background:rgba(255,255,255,0.1);color:var(--white);border:1px solid rgba(255,255,255,0.2);">
        🖨️ Print Invoice
      </button>
      <button onclick="downloadPDF()"
              class="btn btn-primary btn-sm">
        ⬇️ Download PDF
      </button>
    </div>
  </div>
</div>

<!-- Invoice -->
<div id="invoice-wrap">

  <!-- ── HEADER ── -->
  <div class="inv-header">
    <div class="inv-logo">
      <div class="logo-icon">🚗</div>
      <div>
        <div class="inv-logo" style="gap:0;">
          <div class="logo-text"><span>IV</span>SS</div>
        </div>
        <div class="logo-sub">Integrated Vehicle Support System</div>
        <div class="logo-sub">Muscat, Sultanate of Oman</div>
      </div>
    </div>

    <div class="inv-meta">
      <div class="inv-number"><?= htmlspecialchars($inv['invoice_number']) ?></div>
      <div class="inv-date">
        Issued: <strong><?= date('d M Y', strtotime($inv['paid_at'])) ?></strong>
      </div>
      <div class="inv-date">
        Request: <?= date('d M Y', strtotime($inv['request_date'])) ?>
      </div>
      <div>
        <span class="inv-status-paid">✅ PAID</span>
      </div>
    </div>
  </div>

  <!-- ── PARTIES ── -->
  <div class="inv-parties">
    <!-- Bill To -->
    <div class="inv-party">
      <div class="inv-party-label">Bill To</div>
      <div class="inv-party-name"><?= htmlspecialchars($inv['user_name']) ?></div>
      <div class="inv-party-detail">
        ✉️ <?= htmlspecialchars($inv['user_email']) ?><br>
        📞 <?= htmlspecialchars($inv['user_phone']) ?>
      </div>
    </div>
    <!-- Service Provider -->
    <div class="inv-party">
      <div class="inv-party-label">Service Provider</div>
      <div class="inv-party-name"><?= htmlspecialchars($inv['garage_name'] ?? 'IVSS Network') ?></div>
      <div class="inv-party-detail">
        <?php if ($inv['garage_location']): ?>
        📍 <?= htmlspecialchars($inv['garage_location']) ?><br>
        <?php endif; ?>
        <?php if ($inv['garage_phone']): ?>
        📞 <?= htmlspecialchars($inv['garage_phone']) ?><br>
        <?php endif; ?>
        <?php if ($inv['garage_email']): ?>
        ✉️ <?= htmlspecialchars($inv['garage_email']) ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── LINE ITEMS ── -->
  <div class="card" style="padding:0;overflow:hidden;margin-bottom:0;">
    <table class="inv-table">
      <thead>
        <tr>
          <th style="width:50px;">#</th>
          <th>Description</th>
          <th>Details</th>
          <th>Amount (OMR)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="color:var(--muted);font-weight:600;">01</td>
          <td>
            <div style="font-weight:700;font-size:14px;">
              <?= ucfirst($inv['service_type']) ?> Service
            </div>
            <div style="font-size:12px;color:var(--muted);margin-top:3px;">
              Roadside assistance — <?= ucfirst(str_replace('_',' ',$inv['vehicle_type'])) ?>
            </div>
          </td>
          <td style="font-size:13px;color:var(--muted);">
            📌 <?= htmlspecialchars($inv['location_desc']) ?><br>
            🗓️ <?= date('d M Y', strtotime($inv['request_date'])) ?>
            <?php if (!empty($inv['notes'])): ?>
            <br>💬 <?= htmlspecialchars(substr($inv['notes'],0,60)) ?>
            <?php endif; ?>
          </td>
          <td><?= number_format($inv['amount'],3) ?></td>
        </tr>
      </tbody>
    </table>

    <!-- Totals -->
    <div class="inv-totals">
      <div class="inv-totals-inner">
        <div class="inv-total-row">
          <span>Subtotal</span>
          <span><?= number_format($inv['amount'],3) ?> OMR</span>
        </div>
        <div class="inv-total-row">
          <span>Tax (0%)</span>
          <span>0.000 OMR</span>
        </div>
        <div class="inv-total-row grand">
          <span>Total Paid</span>
          <span><?= number_format($inv['amount'],3) ?> OMR</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ── PAYMENT INFO ── -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;">
    <div style="background:var(--bg);border-radius:10px;padding:16px 18px;border:1px solid var(--border);">
      <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Payment Details</div>
      <div style="font-size:13px;line-height:2;color:var(--muted);">
        <span style="color:var(--text);font-weight:600;">Method:</span>
        <?= ucfirst($inv['method'] ?? 'N/A') ?><br>
        <span style="color:var(--text);font-weight:600;">Paid On:</span>
        <?= date('d M Y, H:i', strtotime($inv['paid_at'])) ?><br>
        <span style="color:var(--text);font-weight:600;">Invoice No:</span>
        <?= htmlspecialchars($inv['invoice_number']) ?>
      </div>
    </div>
    <div style="background:rgba(6,163,90,0.06);border-radius:10px;padding:16px 18px;border:1px solid rgba(6,163,90,0.2);display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;">
      <div style="font-size:36px;margin-bottom:8px;">✅</div>
      <div style="font-size:14px;font-weight:700;color:#06a35a;">Payment Confirmed</div>
      <div style="font-size:12px;color:var(--muted);margin-top:4px;">Thank you for using IVSS</div>
    </div>
  </div>

  <!-- Notes -->
  <?php if ($inv['notes']): ?>
  <div class="inv-notes">
    <strong>Service Notes:</strong> <?= htmlspecialchars($inv['notes']) ?>
  </div>
  <?php endif; ?>

  <!-- Footer -->
  <div class="inv-footer">
    <div style="margin-bottom:6px;">
      🚗 <strong>IVSS</strong> — Integrated Vehicle Support System · Muscat, Sultanate of Oman
    </div>
    <div>This is an official payment receipt generated by IVSS. For support contact: support@ivss.om</div>
    <div style="margin-top:8px;font-size:11px;">Generated on <?= date('d M Y, H:i:s') ?></div>
  </div>

</div>

<script>
function downloadPDF() {
  // Trigger browser print dialog — user can save as PDF
  window.print();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
