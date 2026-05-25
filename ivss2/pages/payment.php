<?php
require_once __DIR__ . '/../includes/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/pages/login.php');

$db        = getDB();
$requestId = intval($_GET['request_id'] ?? 0);
$userId    = $_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT p.*, r.service_type, r.location_desc, g.garage_name
    FROM payments p
    JOIN service_requests r ON p.request_id = r.request_id
    JOIN garages g ON r.garage_id = g.garage_id
    WHERE p.request_id = ? AND r.user_id = ? AND p.status = 'pending'
");
$stmt->execute([$requestId, $userId]);
$payment = $stmt->fetch();

if (!$payment) redirect(SITE_URL . '/pages/dashboard.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = sanitize($_POST['method'] ?? '');
    $valid  = ['card', 'cash'];

    if (!in_array($method, $valid)) {
        $errors[] = 'Please select a payment method.';
    }

    if ($method === 'card') {
        $cardNum = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $expiry  = sanitize($_POST['expiry'] ?? '');
        $cvv     = sanitize($_POST['cvv']    ?? '');

        if (strlen($cardNum) < 16) $errors[] = 'Enter a valid 16-digit card number.';
        if (empty($cvv))           $errors[] = 'CVV is required.';

        if (empty($expiry)) {
            $errors[] = 'Card expiry is required.';
        } elseif (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $expiry, $em)) {
            $errors[] = 'Expiry must be in MM/YY format (e.g. 09/26).';
        } else {
            $expMonth = (int)$em[1];
            $expYear  = (int)('20' . $em[2]);
            if ($expYear < (int)date('Y') || ($expYear === (int)date('Y') && $expMonth < (int)date('m'))) {
                $errors[] = 'Your card has expired. Please use a valid card.';
            }
        }
    }

    if (empty($errors)) {
        $db->prepare("UPDATE payments SET status='paid', method=?, paid_at=NOW() WHERE payment_id=?")
           ->execute([$method, $payment['payment_id']]);
        redirect(SITE_URL . '/pages/invoice.php?payment_id=' . $payment['payment_id']);
    }
}

$pageTitle = 'Payment';
include __DIR__ . '/../includes/header.php';
?>

<section style="padding:60px 2rem;">
  <div class="container" style="max-width:560px;">
    <div class="section-tag">Secure Payment</div>
    <div class="section-title">Complete Your Payment</div>

    <!-- Summary -->
    <div class="card mb-24" style="background:var(--navy);color:var(--white);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <span style="color:rgba(255,255,255,0.6);font-size:14px;">Service</span>
        <span style="font-weight:600;"><?= ucfirst($payment['service_type']) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <span style="color:rgba(255,255,255,0.6);font-size:14px;">Garage</span>
        <span style="font-weight:600;"><?= htmlspecialchars($payment['garage_name']) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <span style="color:rgba(255,255,255,0.6);font-size:14px;">Invoice</span>
        <span style="color:var(--gold);font-weight:600;"><?= $payment['invoice_number'] ?></span>
      </div>
      <div style="border-top:1px solid rgba(255,255,255,0.1);padding-top:16px;margin-top:4px;
                  display:flex;justify-content:space-between;align-items:center;">
        <span style="color:rgba(255,255,255,0.6);">Total Amount</span>
        <span style="font-size:28px;font-weight:700;color:var(--gold);"><?= number_format($payment['amount'],3) ?> OMR</span>
      </div>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= $e ?></div>
    <?php endforeach; ?>

    <div class="card">
      <form method="POST" id="payForm">

        <!-- Payment Method -->
        <div class="form-group">
          <label>Payment Method</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px;">

            <!-- Card -->
            <label style="border:1.5px solid var(--border);border-radius:10px;padding:18px 14px;
                          text-align:center;cursor:pointer;transition:all 0.2s;" id="lbl_card">
              <input type="radio" name="method" value="card"
                     style="display:none;" onchange="selectMethod('card')">
              <div style="font-size:26px;margin-bottom:8px;">💳</div>
              <div style="font-size:13px;font-weight:700;color:var(--navy);">Credit Card</div>
              <div style="font-size:11px;color:var(--muted);margin-top:3px;">Pay now securely</div>
            </label>

            <!-- Cash -->
            <label style="border:1.5px solid var(--border);border-radius:10px;padding:18px 14px;
                          text-align:center;cursor:pointer;transition:all 0.2s;" id="lbl_cash">
              <input type="radio" name="method" value="cash"
                     style="display:none;" onchange="selectMethod('cash')">
              <div style="font-size:26px;margin-bottom:8px;">💵</div>
              <div style="font-size:13px;font-weight:700;color:var(--navy);">Cash on Site</div>
              <div style="font-size:11px;color:var(--muted);margin-top:3px;">Pay the garage directly</div>
            </label>

          </div>
        </div>

        <!-- Card Fields -->
        <div id="cardFields" style="display:none;">
          <div class="form-group">
            <label>Card Number</label>
            <input type="text" name="card_number" class="form-control"
                   placeholder="1234 5678 9012 3456" maxlength="19"
                   oninput="formatCard(this)">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Expiry Date</label>
              <input type="text" name="expiry" id="expiry" class="form-control"
                     placeholder="MM/YY" maxlength="5"
                     oninput="formatExpiry(this)"
                     onblur="validateExpiry(this)">
              <div id="expiryError" style="font-size:12px;color:var(--danger);margin-top:4px;display:none;"></div>
            </div>
            <div class="form-group">
              <label>CVV</label>
              <input type="text" name="cvv" class="form-control"
                     placeholder="123" maxlength="3"
                     oninput="this.value=this.value.replace(/\D/g,'')">
            </div>
          </div>
          <div class="form-group">
            <label>Cardholder Name</label>
            <input type="text" name="card_name" class="form-control"
                   placeholder="Name on card">
          </div>
        </div>

        <!-- Cash Info -->
        <div id="cashInfo" style="display:none;background:rgba(26,158,138,0.06);
             border:1px solid rgba(26,158,138,0.2);border-radius:10px;
             padding:16px;margin-bottom:4px;font-size:13px;line-height:1.7;">
          💵 <strong>Cash Payment:</strong> Pay
          <strong><?= number_format($payment['amount'],3) ?> OMR</strong>
          directly to the garage technician when they arrive on site.
        </div>

        <button type="submit" class="btn btn-primary btn-full"
                style="font-size:16px;padding:14px;margin-top:16px;">
          🔒 Pay <?= number_format($payment['amount'],3) ?> OMR
        </button>
        <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:12px;">
          🔐 Payments are secured and encrypted via HTTPS
        </p>
      </form>
    </div>
  </div>
</section>

<script>
function selectMethod(m) {
  document.querySelectorAll('[id^="lbl_"]').forEach(l => {
    l.style.borderColor = 'var(--border)';
    l.style.background  = '';
  });
  document.getElementById('lbl_' + m).style.borderColor = 'var(--teal)';
  document.getElementById('lbl_' + m).style.background  = 'rgba(26,158,138,0.06)';
  document.getElementById('cardFields').style.display = (m === 'card') ? 'block' : 'none';
  document.getElementById('cashInfo').style.display   = (m === 'cash') ? 'block' : 'none';
}

function formatCard(el) {
  let v = el.value.replace(/\D/g,'').substring(0,16);
  el.value = v.replace(/(.{4})/g,'$1 ').trim();
}

function formatExpiry(el) {
  let v = el.value.replace(/\D/g,'').substring(0,4);
  if (v.length >= 3) v = v.substring(0,2) + '/' + v.substring(2);
  el.value = v;
  document.getElementById('expiryError').style.display = 'none';
  el.style.borderColor = '';
}

function validateExpiry(el) {
  const val   = el.value.trim();
  const errEl = document.getElementById('expiryError');
  if (!val) return;

  const match = val.match(/^(0[1-9]|1[0-2])\/(\d{2})$/);
  if (!match) {
    showExpiryErr(el, errEl, 'Format must be MM/YY — e.g. 09/26');
    return;
  }
  const expM = parseInt(match[1], 10);
  const expY = parseInt('20' + match[2], 10);
  const now  = new Date();
  if (expY < now.getFullYear() || (expY === now.getFullYear() && expM < now.getMonth() + 1)) {
    showExpiryErr(el, errEl, 'This card has expired.');
    return;
  }
  el.style.borderColor  = 'var(--teal)';
  errEl.style.display   = 'none';
}

function showExpiryErr(el, errEl, msg) {
  el.style.borderColor  = 'var(--danger)';
  errEl.textContent     = '⚠️ ' + msg;
  errEl.style.display   = 'block';
}

document.getElementById('payForm').addEventListener('submit', function(e) {
  const checked = document.querySelector('input[name=method]:checked');
  if (!checked || checked.value !== 'card') return;

  const el    = document.getElementById('expiry');
  const errEl = document.getElementById('expiryError');
  const val   = el.value.trim();
  const match = val.match(/^(0[1-9]|1[0-2])\/(\d{2})$/);

  if (!match) {
    showExpiryErr(el, errEl, 'Format must be MM/YY — e.g. 09/26');
    e.preventDefault(); return;
  }
  const expM = parseInt(match[1], 10);
  const expY = parseInt('20' + match[2], 10);
  const now  = new Date();
  if (expY < now.getFullYear() || (expY === now.getFullYear() && expM < now.getMonth() + 1)) {
    showExpiryErr(el, errEl, 'This card has expired.');
    e.preventDefault();
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
