<?php
require_once __DIR__ . '/../includes/config.php';
if (isGarageLoggedIn()) redirect(SITE_URL . '/pages/garage_dashboard.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ownerName  = sanitize($_POST['owner_name']  ?? '');
  $garageName = sanitize($_POST['garage_name'] ?? '');
  $email      = sanitize($_POST['email']       ?? '');
  $phone      = $_POST['phone']       ?? '';
  $location   = sanitize($_POST['location']    ?? '');
  $password   = $_POST['password']             ?? '';
  $confirm    = $_POST['confirm_password']     ?? '';
  $services   = $_POST['services']             ?? [];

  if (empty($ownerName))                              $errors[] = 'Owner name is required.';
  if (empty($garageName))                             $errors[] = 'Garage name is required.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors[] = 'A valid email address is required.';
  if (empty($phone))                 $errors[] = 'Phone number is required.';
  elseif (strlen($phone) !== 8)      $errors[] = 'Phone number must be exactly 8 digits.';
  elseif (!preg_match('/^[7|9]/', $phone)) $errors[] = 'Phone number must start with 7 or 9.';
  if (empty($location))                               $errors[] = 'Location / address is required.';
  if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
  } else {
    if (!preg_match('/[a-z|A-Z]/', $password)) $errors[] = 'Password must contain at least one letter.';
    if (!preg_match('/[0-9]/',    $password)) $errors[] = 'Password must contain at least one number.';
    if (!preg_match('/[\W_]/',    $password)) $errors[] = 'Password must contain at least one symbol (e.g. @, #, !).';
  }
  if ($password !== $confirm)                         $errors[] = 'Passwords do not match.';
  if (empty($services))                               $errors[] = 'Please select at least one service.';

  if (empty($errors)) {
    $db  = getDB();
    $chk = $db->prepare("SELECT garage_id FROM garages WHERE email = ?");
    $chk->execute([$email]);
    if ($chk->fetch()) {
      $errors[] = 'This email address is already registered.';
    } else {
      $hash   = password_hash($password, PASSWORD_BCRYPT);
      $svcStr = implode(',', array_map('sanitize', $services));
      $db->prepare("INSERT INTO garages (owner_name, garage_name, email, phone, password, location, services) VALUES (?,?,?,?,?,?,?)")
        ->execute([$ownerName, $garageName, $email, (int)$phone, $hash, $location, $svcStr]);
      redirect(SITE_URL . '/pages/login.php?role=garage&registered=1');
    }
  }
}

$allServices = [
  'towing'  => ['🚛', 'Towing',        'Vehicle towing & recovery'],
  'battery' => ['🔋', 'Battery',       'Jump-start & replacement'],
  'tire'    => ['🛞', 'Tire Change',   'Flat tire & puncture repair'],
  'fuel'    => ['⛽', 'Fuel Delivery', 'Emergency fuel top-up'],
  'lockout' => ['🔑', 'Lockout',       'Car lockout assistance'],
  'repair'  => ['🔧', 'General Repair', 'On-site mechanical repair'],
];

$selectedServices = $_POST['services'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register Garage — IVSS</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
  <style>
    /* ── Split page ── */
    .reg-page {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 1.5fr;
    }

    /* ── Left panel ── */
    .reg-left {
      background: var(--navy);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 44px 48px;
      position: relative;
      overflow: hidden;
    }

    .reg-left::before {
      content: '';
      position: absolute;
      width: 480px;
      height: 480px;
      border: 1px solid rgba(212, 168, 67, 0.1);
      border-radius: 50%;
      top: -130px;
      right: -130px;
      pointer-events: none;
    }

    .reg-left::after {
      content: '';
      position: absolute;
      width: 340px;
      height: 340px;
      border: 1px solid rgba(212, 168, 67, 0.06);
      border-radius: 50%;
      bottom: -100px;
      left: -100px;
      pointer-events: none;
    }

    .left-inner {
      position: relative;
      z-index: 1;
    }

    .reg-logo {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      margin-bottom: 56px;
    }

    .reg-logo .l-icon {
      width: 44px;
      height: 44px;
      background: var(--gold);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
    }

    .reg-logo .l-name {
      color: var(--white);
      font-size: 22px;
      font-weight: 700;
    }

    .reg-logo .l-name span {
      color: var(--gold);
    }

    .reg-heading {
      color: var(--white);
      font-size: 34px;
      font-weight: 700;
      line-height: 1.2;
      margin-bottom: 14px;
    }

    .reg-heading em {
      color: var(--gold);
      font-style: normal;
    }

    .reg-sub {
      color: rgba(255, 255, 255, 0.55);
      font-size: 15px;
      line-height: 1.75;
      margin-bottom: 44px;
      max-width: 310px;
    }

    /* Benefit cards */
    .benefit-list {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .benefit-card {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.07);
      border-radius: 12px;
      padding: 16px 18px;
      display: flex;
      align-items: flex-start;
      gap: 14px;
    }

    .benefit-icon {
      font-size: 22px;
      flex-shrink: 0;
      margin-top: 2px;
    }

    .benefit-text h4 {
      color: var(--white);
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 3px;
    }

    .benefit-text p {
      color: rgba(255, 255, 255, 0.45);
      font-size: 12px;
      line-height: 1.5;
    }

    .left-footer {
      position: relative;
      z-index: 1;
      color: rgba(255, 255, 255, 0.28);
      font-size: 13px;
    }

    /* ── Right panel ── */
    .reg-right {
      background: var(--bg);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 60px 56px;
      position: relative;
      overflow-y: auto;
    }

    .back-link {
      position: absolute;
      top: 28px;
      left: 28px;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      font-size: 13px;
      font-weight: 600;
      color: var(--muted);
      text-decoration: none;
      padding: 8px 16px;
      border-radius: 8px;
      border: 1.5px solid var(--border);
      background: var(--white);
      transition: all 0.2s;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    }

    .back-link:hover {
      color: var(--navy);
      border-color: var(--navy);
      transform: translateX(-2px);
    }

    .form-wrap {
      width: 100%;
      max-width: 520px;
      margin-top: 40px;
    }

    .form-title {
      font-size: 26px;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .form-subtitle {
      font-size: 14px;
      color: var(--muted);
      margin-bottom: 28px;
    }

    /* Progress steps */
    .progress-steps {
      display: flex;
      align-items: center;
      gap: 0;
      margin-bottom: 32px;
    }

    .p-step {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .p-step-circle {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: var(--border);
      color: var(--muted);
      font-size: 12px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: all 0.3s;
    }

    .p-step-circle.done {
      background: var(--teal);
      color: var(--white);
    }

    .p-step-circle.current {
      background: var(--navy);
      color: var(--white);
    }

    .p-step-label {
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
    }

    .p-step-label.current {
      color: var(--navy);
    }

    .p-step-label.done {
      color: var(--teal);
    }

    .p-line {
      flex: 1;
      height: 2px;
      background: var(--border);
      margin: 0 8px;
      min-width: 20px;
    }

    .p-line.done {
      background: var(--teal);
    }

    /* Section dividers inside form */
    .form-section-title {
      font-size: 13px;
      font-weight: 700;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 1.2px;
      padding-bottom: 10px;
      border-bottom: 1.5px solid var(--border);
      margin-bottom: 20px;
      margin-top: 28px;
    }

    .form-section-title:first-child {
      margin-top: 0;
    }

    /* Input with icon */
    .input-wrap {
      position: relative;
    }

    .input-wrap .i-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 15px;
      pointer-events: none;
    }

    .input-wrap .form-control {
      padding-left: 42px;
      background: var(--white);
    }

    .input-wrap .form-control.has-eye {
      padding-right: 42px;
    }

    .pass-eye {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      font-size: 16px;
      color: var(--muted);
    }

    .pass-eye:hover {
      color: var(--text);
    }

    /* Service cards */
    .services-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-top: 8px;
    }

    .svc-card {
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 14px 10px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
      background: var(--white);
      user-select: none;
    }

    .svc-card:hover {
      border-color: var(--teal);
      background: rgba(26, 158, 138, 0.03);
    }

    .svc-card.active {
      border-color: var(--teal);
      background: rgba(26, 158, 138, 0.08);
    }

    .svc-card.active .svc-name {
      color: var(--teal);
    }

    .svc-emoji {
      font-size: 24px;
      display: block;
      margin-bottom: 6px;
    }

    .svc-name {
      font-size: 12px;
      font-weight: 700;
      color: var(--text);
      display: block;
      margin-bottom: 2px;
    }

    .svc-desc {
      font-size: 11px;
      color: var(--muted);
      line-height: 1.3;
      display: block;
    }

    .svc-check {
      display: none;
    }

    /* Strength bar */
    .strength-bar {
      height: 4px;
      border-radius: 4px;
      background: var(--border);
      margin-top: 6px;
      overflow: hidden;
    }

    .strength-fill {
      height: 100%;
      border-radius: 4px;
      transition: all 0.3s;
      width: 0;
    }

    .strength-hint {
      font-size: 11px;
      margin-top: 4px;
    }

    /* Account type chooser */
    .acc-types {
      display: flex;
      gap: 10px;
      margin-bottom: 28px;
    }

    .acc-type {
      flex: 1;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 14px 10px;
      text-align: center;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
      transition: all 0.2s;
      background: var(--white);
    }

    .acc-type.active {
      border-color: var(--teal);
      background: rgba(26, 158, 138, 0.06);
    }

    .acc-type .at-icon {
      font-size: 22px;
      display: block;
      margin-bottom: 6px;
    }

    .acc-type .at-label {
      font-size: 12px;
      font-weight: 700;
      display: block;
    }

    .acc-type .at-sub {
      font-size: 11px;
      color: var(--muted);
      display: block;
      margin-top: 2px;
    }

    @media (max-width: 860px) {
      .reg-page {
        grid-template-columns: 1fr;
      }

      .reg-left {
        display: none;
      }

      .reg-right {
        padding: 80px 24px 40px;
      }

      .services-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
  </style>
</head>

<body>

  <div class="reg-page">

    <!-- ══ LEFT PANEL ══ -->
    <div class="reg-left">
      <div class="left-inner">
        <a href="<?= SITE_URL ?>/index.php" class="reg-logo">
          <div class="l-icon">🔧</div>
          <div class="l-name"><span>IV</span>SS</div>
        </a>

        <div class="reg-heading">Grow Your<br>Garage with <em>IVSS</em></div>
        <p class="reg-sub">Join Oman's leading vehicle assistance network. Connect with drivers who need your services — 24/7.</p>

        <div class="benefit-list">
          <div class="benefit-card">
            <div class="benefit-icon">📍</div>
            <div class="benefit-text">
              <h4>Be Found on the Map</h4>
              <p>Your garage appears on the IVSS live map, visible to all drivers searching nearby.</p>
            </div>
          </div>
          <div class="benefit-card">
            <div class="benefit-icon">📲</div>
            <div class="benefit-text">
              <h4>Instant Job Notifications</h4>
              <p>Receive service requests the moment a driver near you submits one.</p>
            </div>
          </div>
          <div class="benefit-card">
            <div class="benefit-icon">💳</div>
            <div class="benefit-text">
              <h4>Secure Online Payments</h4>
              <p>Get paid through the platform. Auto-invoices generated for every completed job.</p>
            </div>
          </div>
          <div class="benefit-card">
            <div class="benefit-icon">📊</div>
            <div class="benefit-text">
              <h4>Analytics Dashboard</h4>
              <p>Track your requests, revenue, and ratings all in one place.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="left-footer">© <?= date('Y') ?> IVSS — Integrated Vehicle Support System &nbsp;🇴🇲</div>
    </div>

    <!-- ══ RIGHT PANEL ══ -->
    <div class="reg-right">

      <!-- Back Button -->
      <a href="<?= SITE_URL ?>/index.php" class="back-link">
        ← Back to Home
      </a>

      <div class="form-wrap">
        <div class="form-title">Register Your Garage</div>
        <div class="form-subtitle">Fill in your garage details to join the IVSS network.</div>

        <!-- Account type switch -->
        <div class="acc-types">
          <a href="register.php" class="acc-type" style="text-decoration:none;color:inherit;">
            <span class="at-icon">🚗</span>
            <span class="at-label">I'm a Driver</span>
            <span class="at-sub">Need assistance</span>
          </a>
          <div class="acc-type active">
            <span class="at-icon">🔧</span>
            <span class="at-label">I'm a Garage</span>
            <span class="at-sub">Offer services</span>
          </div>
        </div>

        <!-- Errors -->
        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger">⚠️ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <!-- ── Form ── -->
        <form method="POST" id="garageForm">

          <!-- Section 1: Owner Info -->
          <div class="form-section-title">👤 Owner Information</div>
          <div class="form-row">
            <div class="form-group">
              <label>Owner Full Name</label>
              <div class="input-wrap">
                <span class="i-icon">👤</span>
                <input type="text" name="owner_name" class="form-control"
                  placeholder="e.g. Ahmed Al-Wadi"
                  value="<?= htmlspecialchars($_POST['owner_name'] ?? '') ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label>Garage Name</label>
              <div class="input-wrap">
                <span class="i-icon">🏪</span>
                <input type="text" name="garage_name" class="form-control"
                  placeholder="e.g. Al-Wadi Garage"
                  value="<?= htmlspecialchars($_POST['garage_name'] ?? '') ?>" required>
              </div>
            </div>
          </div>

          <!-- Section 2: Contact -->
          <div class="form-section-title">📞 Contact Details</div>
          <div class="form-row">
            <div class="form-group">
              <label>Email Address</label>
              <div class="input-wrap">
                <span class="i-icon">✉️</span>
                <input type="email" name="email" class="form-control"
                  placeholder="garage@email.com"
                  value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <div class="input-wrap">
                <span class="i-icon">📞</span>
                <input type="text" name="phone" class="form-control"
                  placeholder="e.g. 91234567"
                  maxlength="8"
                  pattern="[7|9][0-9]{7}"
                  inputmode="numeric"
                  onkeydown="return /[0-9]|Backspace|Delete|ArrowLeft|ArrowRight|Tab/.test(event.key)"
                  onpaste="event.preventDefault();var t=event.clipboardData.getData('text').replace(/\D/g,'').slice(0,8);this.value=t;"
                  oninput="this.value=this.value.replace(/\D/g,'').slice(0,8)"
                  value="<?= htmlspecialchars($phone ?? '') ?>" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Garage Location / Address</label>
            <div class="input-wrap">
              <span class="i-icon">📍</span>
              <input type="text" name="location" class="form-control"
                placeholder="e.g. Qurum, Muscat — near Al-Qurum Park"
                value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" required>
            </div>
            <div class="form-hint">Be specific — this helps drivers find you on the map.</div>
          </div>

          <!-- Section 3: Services -->
          <div class="form-section-title">🔧 Services Offered</div>
          <p style="font-size:13px;color:var(--muted);margin-bottom:12px;">Select all services your garage provides. You can update these later.</p>
          <div class="services-grid">
            <?php foreach ($allServices as $key => [$emoji, $name, $desc]): ?>
              <?php $checked = in_array($key, $selectedServices); ?>
              <div class="svc-card <?= $checked ? 'active' : '' ?>" onclick="toggleSvc(this, '<?= $key ?>')">
                <input type="checkbox" name="services[]" value="<?= $key ?>"
                  class="svc-check" id="svc_<?= $key ?>" <?= $checked ? 'checked' : '' ?>>
                <span class="svc-emoji"><?= $emoji ?></span>
                <span class="svc-name"><?= $name ?></span>
                <span class="svc-desc"><?= $desc ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="form-hint" id="svcHint" style="margin-top:8px;<?= empty($selectedServices) && !empty($errors) ? 'color:var(--danger);' : '' ?>">
            <?= (empty($selectedServices) && !empty($errors)) ? '⚠️ Select at least one service.' : 'Click to select/deselect services.' ?>
          </div>

          <!-- Section 4: Password -->
          <div class="form-section-title">🔒 Account Security</div>
          <div class="form-row">
            <div class="form-group">
              <label>Password</label>
              <div class="input-wrap">
                <span class="i-icon">🔒</span>
                <input type="password" name="password" class="form-control has-eye"
                  placeholder="Min. 8 characters,letter,symbol"
                  id="passInp" oninput="checkStrength(this.value)" required>
                <button type="button" class="pass-eye" onclick="toggleVis('passInp')">👁️</button>
              </div>
              <div class="strength-bar">
                <div class="strength-fill" id="strFill"></div>
              </div>
              <div class="strength-hint" id="strLabel"></div>
            </div>
            <div class="form-group">
              <label>Confirm Password</label>
              <div class="input-wrap">
                <span class="i-icon">🔒</span>
                <input type="password" name="confirm_password" class="form-control has-eye"
                  placeholder="Repeat password"
                  id="confirmInp" oninput="checkMatch()" required>
                <button type="button" class="pass-eye" onclick="toggleVis('confirmInp')">👁️</button>
              </div>
              <div class="form-hint" id="matchHint"></div>
            </div>
          </div>

          <!-- Agreement -->
          <div class="form-group" style="display:flex;align-items:flex-start;gap:10px;margin-top:6px;">
            <input type="checkbox" id="agree" style="margin-top:3px;width:16px;height:16px;accent-color:var(--teal);flex-shrink:0;" required>
            <label for="agree" style="font-size:13px;color:var(--muted);font-weight:400;cursor:pointer;line-height:1.5;">
              I confirm that the information above is accurate and I agree to the
              <a href="garage_terms.php" target="_blank" style="color:var(--teal);">Terms of Service</a> and
              <a href="garage_terms.php#obligations" target="_blank" style="color:var(--teal);">Garage Partner Agreement</a>.
            </label>
          </div>

          <button type="submit" class="btn btn-primary btn-full" style="padding:14px;font-size:16px;margin-top:16px;">
            Register My Garage &nbsp;→
          </button>

        </form>

        <div style="text-align:center;margin-top:20px;font-size:14px;color:var(--muted);">
          Already registered?
          <a href="login.php?role=garage" style="color:var(--teal);font-weight:700;text-decoration:none;margin-left:4px;">Garage Login →</a>
        </div>

      </div>
    </div><!-- /reg-right -->
  </div><!-- /reg-page -->

  <script>
    /* ── Service card toggle ── */
    function toggleSvc(card, key) {
      const cb = document.getElementById('svc_' + key);
      cb.checked = !cb.checked;
      card.classList.toggle('active', cb.checked);
      // update hint
      const selected = document.querySelectorAll('.svc-check:checked').length;
      const hint = document.getElementById('svcHint');
      hint.textContent = selected > 0 ?
        selected + ' service' + (selected > 1 ? 's' : '') + ' selected ✓' :
        'Click to select/deselect services.';
      hint.style.color = selected > 0 ? 'var(--teal)' : 'var(--muted)';
    }

    /* ── Show/hide password ── */
    function toggleVis(id) {
      const el = document.getElementById(id);
      el.type = el.type === 'password' ? 'text' : 'password';
    }

    /* ── Password strength ── */
    function checkStrength(val) {
      const fill = document.getElementById('strFill');
      const label = document.getElementById('strLabel');
      let score = 0;
      if (val.length >= 6) score++;
      if (val.length >= 10) score++;
      if (/[A-Z]/.test(val)) score++;
      if (/[0-9]/.test(val)) score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;

      const levels = [{
          pct: '0%',
          color: 'var(--border)',
          text: ''
        },
        {
          pct: '25%',
          color: 'var(--danger)',
          text: 'Weak'
        },
        {
          pct: '50%',
          color: 'var(--warning)',
          text: 'Fair'
        },
        {
          pct: '75%',
          color: 'var(--teal)',
          text: 'Good'
        },
        {
          pct: '90%',
          color: 'var(--teal)',
          text: 'Strong'
        },
        {
          pct: '100%',
          color: '#06a35a',
          text: 'Very Strong ✓'
        },
      ];
      const lvl = levels[Math.min(score, 5)];
      fill.style.width = lvl.pct;
      fill.style.background = lvl.color;
      label.textContent = lvl.text;
      label.style.color = lvl.color;
    }

    /* ── Password match ── */
    function checkMatch() {
      const p = document.getElementById('passInp').value;
      const c = document.getElementById('confirmInp').value;
      const h = document.getElementById('matchHint');
      if (!c) {
        h.textContent = '';
        return;
      }
      if (p === c) {
        h.textContent = '✓ Passwords match';
        h.style.color = 'var(--teal)';
      } else {
        h.textContent = '✗ Does not match';
        h.style.color = 'var(--danger)';
      }
    }

    /* ── Init: mark already-selected services on validation error ── */
    document.addEventListener('DOMContentLoaded', () => {
      const checked = document.querySelectorAll('.svc-check:checked');
      if (checked.length > 0) {
        const hint = document.getElementById('svcHint');
        hint.textContent = checked.length + ' service' + (checked.length > 1 ? 's' : '') + ' selected ✓';
        hint.style.color = 'var(--teal)';
      }
    });
  </script>
</body>

</html>