<?php
require_once __DIR__ . '/../includes/config.php';
if (!isGarageLoggedIn()) redirect(SITE_URL . '/pages/login.php?role=garage');

$db       = getDB();
$garageId = (int)$_SESSION['garage_id'];
$subId    = (int)($_GET['sub_id'] ?? 0);

if (!$subId) redirect(SITE_URL . '/pages/garage_subscriptions.php');

$sub = getSubscriptionById($subId);

if (!$sub || (int)$sub['garage_id'] !== $garageId || $sub['status'] !== 'pending_payment') {
  redirect(SITE_URL . '/pages/garage_subscriptions.php');
}

$feats    = json_decode($sub['features'] ?? '{}', true);
$planIcon = [1 => '🕐', 2 => '📅', 3 => '📆'][$sub['plan_id']] ?? '📦';
$errors   = [];

// ── Handle payment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
  $method  = sanitize($_POST['method'] ?? '');

  if ($method !== 'card') {
    $errors[] = 'Please select a payment method.';
  } else {
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
    if (activateSubscription($subId, $method)) {
      redirect(SITE_URL . '/pages/garage_subscriptions.php?paid=1');
    } else {
      $errors[] = 'Payment processing failed. Please try again.';
    }
  }
}

$pageTitle = 'Subscription Payment';
include __DIR__ . '/../includes/header.php';
?>

<style>
  .pay-wrap {
    max-width: 580px;
    margin: 0 auto;
    padding: 48px 20px;
  }

  .sub-summary {
    background: linear-gradient(135deg, var(--navy) 0%, #1a3a5c 100%);
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 24px;
    color: #fff;
  }

  .sub-summary .plan-row {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, .1);
  }

  .sub-summary .plan-icon-big {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    background: rgba(255, 255, 255, .1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    flex-shrink: 0;
  }

  .sub-summary .plan-info h2 {
    font-size: 20px;
    font-weight: 800;
    margin: 0 0 4px;
  }

  .sub-summary .plan-info p {
    font-size: 13px;
    color: rgba(255, 255, 255, .55);
    margin: 0;
  }

  .sub-summary .detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: rgba(255, 255, 255, .6);
    margin-bottom: 10px;
  }

  .sub-summary .detail-row span:last-child {
    color: #fff;
    font-weight: 600;
  }

  .sub-summary .total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, .1);
    margin-top: 6px;
  }

  .sub-summary .total-row .lbl {
    font-size: 14px;
    color: rgba(255, 255, 255, .6);
  }

  .sub-summary .total-row .amt {
    font-size: 32px;
    font-weight: 800;
    color: var(--gold);
  }

  .steps-bar {
    display: flex;
    align-items: center;
    margin-bottom: 32px;
  }

  .step {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--muted);
  }

  .step .num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
  }

  .step.done .num {
    background: var(--teal);
    border-color: var(--teal);
    color: #fff;
  }

  .step.active .num {
    background: var(--navy);
    border-color: var(--navy);
    color: #fff;
  }

  .step.active {
    color: var(--navy);
  }

  .step-line {
    flex: 1;
    height: 2px;
    background: var(--border);
    margin: 0 8px;
  }

  .step-line.done {
    background: var(--teal);
  }

  #cardFields {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-top: 8px;
  }

  .sec-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
    font-size: 12px;
    color: var(--muted);
    margin-top: 12px;
  }
</style>

<div style="padding:40px 20px;">
  <div class="pay-wrap">

    <!-- Steps -->
    <div class="steps-bar">
      <div class="step done">
        <div class="num">✓</div><span>Choose Plan</span>
      </div>
      <div class="step-line done"></div>
      <div class="step active">
        <div class="num">2</div><span>Payment</span>
      </div>
      <div class="step-line"></div>
      <div class="step">
        <div class="num">3</div><span>Active</span>
      </div>
    </div>

    <div style="font-size:24px;font-weight:800;color:var(--navy);margin-bottom:4px;">Complete Your Payment</div>
    <div style="font-size:14px;color:var(--muted);margin-bottom:28px;">Your subscription will activate immediately after payment.</div>

    <!-- Summary -->
    <div class="sub-summary">
      <div class="plan-row">
        <div class="plan-icon-big"><?= $planIcon ?></div>
        <div class="plan-info">
          <h2><?= htmlspecialchars($sub['plan_name']) ?> Plan</h2>
          <p><?= $sub['duration_days'] ?> days access · All features included</p>
        </div>
      </div>
      <div class="detail-row"><span>Garage</span><span><?= htmlspecialchars($_SESSION['garage_name']) ?></span></div>
      <div class="detail-row"><span>Start Date</span><span><?= date('d M Y', strtotime($sub['start_date'])) ?></span></div>
      <div class="detail-row"><span>Expiry Date</span><span><?= date('d M Y', strtotime($sub['end_date'])) ?></span></div>
      <?php
      $dailyReq   = $feats['daily_requests'] ?? '—';
      $dailyLabel = ($dailyReq >= 9999) ? 'Unlimited' : $dailyReq . '/day';
      ?>
      <div class="detail-row"><span>Daily Requests</span><span><?= $dailyLabel ?></span></div>
      <div class="detail-row"><span>Support</span><span><?= htmlspecialchars($feats['support'] ?? 'Email') ?></span></div>
      <div class="total-row">
        <span class="lbl">Total Amount</span>
        <span class="amt"><?= number_format($sub['price'], 3) ?> OMR</span>
      </div>
    </div>

    <!-- Errors -->
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger" style="margin-bottom:12px;"><?= $e ?></div>
    <?php endforeach; ?>

    <!-- Form -->
    <div class="card">
      <form method="POST" id="payForm">

        <!-- Method (card only) -->
        <div class="form-group">
          <label style="font-weight:700;font-size:14px;">Payment Method</label>
          <div style="margin-top:8px;">
            <div style="border:2px solid var(--teal);border-radius:12px;padding:16px 18px;
                        background:rgba(26,158,138,0.06);display:flex;align-items:center;gap:14px;">
              <input type="radio" name="method" value="card" checked style="accent-color:var(--teal);width:16px;height:16px;">
              <div style="font-size:22px;">💳</div>
              <div>
                <div style="font-size:14px;font-weight:700;color:var(--navy);">Credit / Debit Card</div>
                <div style="font-size:12px;color:var(--muted);">Visa, Mastercard, AMEX</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Card Fields -->
        <div id="cardFields">
          <div class="form-group">
            <label>Card Number</label>
            <input type="text" name="card_number" class="form-control"
              placeholder="1234 5678 9012 3456" maxlength="19"
              oninput="formatCard(this)">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
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
            <input type="text" name="card_name" class="form-control" placeholder="Name on card">
          </div>
        </div>

        <button type="submit" name="pay"
          class="btn btn-primary btn-full"
          style="font-size:16px;padding:14px;margin-top:8px;">
          🔒 Pay <?= number_format($sub['price'], 3) ?> OMR — Activate Now
        </button>
        <div class="sec-badge">🔐 Payments are secured and encrypted via HTTPS</div>
      </form>
    </div>

    <div style="text-align:center;margin-top:16px;">
      <a href="garage_subscriptions.php?cancelled=1"
        style="font-size:13px;color:var(--muted);text-decoration:none;">← Cancel and go back</a>
    </div>
  </div>
</div>

<script>
  function formatCard(el) {
    let v = el.value.replace(/\D/g, '').substring(0, 16);
    el.value = v.replace(/(.{4})/g, '$1 ').trim();
  }

  function formatExpiry(el) {
    let v = el.value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 3) v = v.substring(0, 2) + '/' + v.substring(2);
    el.value = v;
    document.getElementById('expiryError').style.display = 'none';
    el.style.borderColor = '';
  }

  function validateExpiry(el) {
    const val = el.value.trim();
    const errEl = document.getElementById('expiryError');
    if (!val) return;

    const match = val.match(/^(0[1-9]|1[0-2])\/(\d{2})$/);
    if (!match) {
      showExpiryErr(el, errEl, 'Format must be MM/YY — e.g. 09/26');
      return;
    }

    const expM = parseInt(match[1], 10);
    const expY = parseInt('20' + match[2], 10);
    const now = new Date();
    if (expY < now.getFullYear() || (expY === now.getFullYear() && expM < now.getMonth() + 1)) {
      showExpiryErr(el, errEl, 'This card has expired.');
      return;
    }
    el.style.borderColor = 'var(--teal)';
    errEl.style.display = 'none';
  }

  function showExpiryErr(el, errEl, msg) {
    el.style.borderColor = 'var(--danger)';
    errEl.textContent = '⚠️ ' + msg;
    errEl.style.display = 'block';
  }

  document.getElementById('payForm').addEventListener('submit', function(e) {
    const el = document.getElementById('expiry');
    const errEl = document.getElementById('expiryError');
    const val = el.value.trim();
    const match = val.match(/^(0[1-9]|1[0-2])\/(\d{2})$/);
    if (!match) {
      showExpiryErr(el, errEl, 'Format must be MM/YY — e.g. 09/26');
      e.preventDefault();
      return;
    }
    const expM = parseInt(match[1], 10);
    const expY = parseInt('20' + match[2], 10);
    const now = new Date();
    if (expY < now.getFullYear() || (expY === now.getFullYear() && expM < now.getMonth() + 1)) {
      showExpiryErr(el, errEl, 'This card has expired.');
      e.preventDefault();
    }
  });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>