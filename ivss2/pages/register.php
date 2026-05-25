<?php
require_once __DIR__ . '/../includes/config.php';
if (isLoggedIn()) redirect(SITE_URL . '/pages/dashboard.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name    = sanitize($_POST['full_name'] ?? '');
  $email   = sanitize($_POST['email']     ?? '');
  $phone   = preg_replace('/\D/', '', $_POST['phone'] ?? ''); // digits only
  $pass    = $_POST['password']           ?? '';
  $confirm = $_POST['confirm_password']   ?? '';

  if (empty($name))                                   $errors[] = 'Full name is required.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors[] = 'A valid email address is required.';
  if (empty($phone))                 $errors[] = 'Phone number is required.';
  elseif (strlen($phone) !== 8)      $errors[] = 'Phone number must be exactly 8 digits.';
  elseif (!preg_match('/^[7|9]/', $phone)) $errors[] = 'Phone number must start with 7 or 9.';
  if (strlen($pass) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
  } else {
    if (!preg_match('/[a-z|A-Z]/', $pass)) $errors[] = 'Password must contain at least one letter.';
    if (!preg_match('/[0-9]/',    $pass)) $errors[] = 'Password must contain at least one number.';
    if (!preg_match('/[\W_]/',    $pass)) $errors[] = 'Password must contain at least one symbol (e.g. @, #, !).';
  }
  if ($pass !== $confirm)                             $errors[] = 'Passwords do not match.';

  if (empty($errors)) {
    $db = getDB();
    $chk = $db->prepare("SELECT user_id FROM users WHERE email = ?");
    $chk->execute([$email]);
    if ($chk->fetch()) {
      $errors[] = 'This email address is already registered.';
    } else {
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $db->prepare("INSERT INTO users (full_name, email, phone, password) VALUES (?,?,?,?)")
        ->execute([$name, $email, (int)$phone, $hash]);
      redirect(SITE_URL . '/pages/login.php?registered=1');
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — IVSS</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
  <style>
    .reg-page {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 1.4fr;
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
      width: 500px;
      height: 500px;
      border: 1px solid rgba(212, 168, 67, 0.1);
      border-radius: 50%;
      top: -140px;
      right: -140px;
      pointer-events: none;
    }

    .reg-left::after {
      content: '';
      position: absolute;
      width: 350px;
      height: 350px;
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
      margin-bottom: 64px;
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
      margin-bottom: 16px;
    }

    .reg-heading em {
      color: var(--gold);
      font-style: normal;
    }

    .reg-sub {
      color: rgba(255, 255, 255, 0.55);
      font-size: 15px;
      line-height: 1.75;
      margin-bottom: 48px;
      max-width: 320px;
    }

    .steps-list {
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    .reg-step {
      display: flex;
      gap: 16px;
      align-items: flex-start;
      padding-bottom: 28px;
      position: relative;
    }

    .reg-step::before {
      content: '';
      position: absolute;
      left: 16px;
      top: 36px;
      bottom: 0;
      width: 1px;
      background: rgba(212, 168, 67, 0.2);
    }

    .reg-step:last-child::before {
      display: none;
    }

    .reg-step:last-child {
      padding-bottom: 0;
    }

    .step-num {
      width: 33px;
      height: 33px;
      border-radius: 50%;
      background: rgba(212, 168, 67, 0.12);
      border: 1.5px solid rgba(212, 168, 67, 0.35);
      color: var(--gold);
      font-size: 14px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      margin-top: 1px;
    }

    .step-info h4 {
      color: var(--white);
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .step-info p {
      color: rgba(255, 255, 255, 0.45);
      font-size: 13px;
      line-height: 1.5;
    }

    .reg-footer {
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
      justify-content: center;
      padding: 60px 56px;
      position: relative;
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
      max-width: 480px;
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

    /* Strength bar */
    .strength-bar {
      height: 4px;
      border-radius: 4px;
      margin-top: 6px;
      background: var(--border);
      overflow: hidden;
    }

    .strength-fill {
      height: 100%;
      border-radius: 4px;
      transition: all 0.3s;
      width: 0%;
    }

    .strength-label {
      font-size: 11px;
      color: var(--muted);
      margin-top: 4px;
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

    .account-types {
      display: flex;
      gap: 10px;
      margin-bottom: 24px;
    }

    .acc-type {
      flex: 1;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 14px 10px;
      text-align: center;
      cursor: pointer;
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
      color: var(--text);
    }

    .acc-type .at-sub {
      font-size: 11px;
      color: var(--muted);
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
    }
  </style>
</head>

<body>

  <div class="reg-page">

    <!-- ══ LEFT PANEL ══ -->
    <div class="reg-left">
      <div class="left-inner">
        <a href="<?= SITE_URL ?>/index.php" class="reg-logo">
          <div class="l-icon">🚗</div>
          <div class="l-name"><span>IV</span>SS</div>
        </a>

        <div class="reg-heading">Join <em>IVSS</em> Today</div>
        <p class="reg-sub">Get access to 150+ certified garages across Oman. Sign up free in under 2 minutes.</p>

        <div class="steps-list">
          <div class="reg-step">
            <div class="step-num">1</div>
            <div class="step-info">
              <h4>Create Your Account</h4>
              <p>Fill in your basic details to register as a driver on IVSS.</p>
            </div>
          </div>
          <div class="reg-step">
            <div class="step-num">2</div>
            <div class="step-info">
              <h4>Find a Nearby Garage</h4>
              <p>Browse certified garages by location, service, and rating.</p>
            </div>
          </div>
          <div class="reg-step">
            <div class="step-num">3</div>
            <div class="step-info">
              <h4>Request Roadside Help</h4>
              <p>Submit a service request and get a response in minutes.</p>
            </div>
          </div>
          <div class="reg-step">
            <div class="step-num">4</div>
            <div class="step-info">
              <h4>Pay Securely Online</h4>
              <p>Complete payment on the platform and receive your invoice.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="reg-footer">© <?= date('Y') ?> IVSS — Integrated Vehicle Support System &nbsp;🇴🇲</div>
    </div>

    <!-- ══ RIGHT PANEL ══ -->
    <div class="reg-right">

      <!-- Back Button -->
      <a href="<?= SITE_URL ?>/index.php" class="back-link">
        ← Back to Home
      </a>

      <div class="form-wrap">
        <div class="form-title">Create Account</div>
        <div class="form-subtitle">Register as a driver to access roadside assistance across Oman.</div>

        <!-- Account type chooser -->
        <div class="account-types">
          <div class="acc-type active" id="type-driver" style="display:flex;flex-direction:column;align-items:center;">
            <span class="at-icon">🚗</span>
            <div class="at-label">I'm a Driver</div>
            <div class="at-sub">Need assistance</div>
          </div>
          <a href="garage_register.php" class="acc-type" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;">
            <span class="at-icon">🔧</span>
            <div class="at-label">I'm a Garage</div>
            <div class="at-sub">Offer services</div>
          </a>
        </div>

        <!-- Errors -->
        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger">⚠️ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <!-- Form -->
        <form method="POST" id="regForm">

          <div class="form-group">
            <label>Full Name</label>
            <div class="input-wrap">
              <span class="i-icon">👤</span>
              <input type="text" name="full_name" class="form-control"
                placeholder="e.g. Ahmed Al-Balushi"
                value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Email Address</label>
              <div class="input-wrap">
                <span class="i-icon">✉️</span>
                <input type="email" name="email" class="form-control"
                  placeholder="you@example.com"
                  value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <div class="input-wrap">
                <span class="i-icon">📞</span>
                <input type="number" name="phone" class="form-control"
                  placeholder="e.g. 91234567"
                  maxlength="8"
                  pattern="[7|9][0-9]{7}"
                  inputmode="numeric"
                  onkeydown="return /[0-9]|Backspace|Delete|ArrowLeft|ArrowRight|Tab/.test(event.key)"
                  onpaste="event.preventDefault();var t=event.clipboardData.getData('text').replace(/\D/g,'').slice(0,8);this.value=t;"
                  oninput="this.value=this.value.replace(/\D/g,'').slice(0,8)"
                  value="<?= htmlspecialchars($phone ?? '') ?>" required>
              </div>
              <small style="font-size:11px;color:var(--muted);margin-top:5px;display:block;padding-left:2px;">
                8 digits · Must start with 7 or 9
              </small>
            </div>
          </div>

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
                <div class="strength-fill" id="strengthFill"></div>
              </div>
              <div class="strength-label" id="strengthLabel"></div>
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

          <div class="form-group" style="display:flex;align-items:flex-start;gap:10px;margin-top:4px;">
            <input type="checkbox" id="agree" style="margin-top:3px;width:16px;height:16px;accent-color:var(--teal);flex-shrink:0;" required>
            <label for="agree" style="font-size:13px;color:var(--muted);font-weight:400;cursor:pointer;">
              I agree to the <a href="terms.php" target="_blank" style="color:var(--teal);">Terms of Service</a> and <a href="terms.php#privacy" target="_blank" style="color:var(--teal);">Privacy Policy</a>.
            </label>
          </div>

          <button type="submit" class="btn btn-primary btn-full" style="padding:14px;font-size:16px;margin-top:12px;">
            Create My Account &nbsp;→
          </button>
        </form>

        <div style="text-align:center;margin-top:20px;font-size:14px;color:var(--muted);">
          Already have an account?
          <a href="login.php" style="color:var(--teal);font-weight:700;text-decoration:none;margin-left:4px;">Sign in →</a>
        </div>

      </div>
    </div>

  </div>

  <script>
    function toggleVis(id) {
      const el = document.getElementById(id);
      el.type = el.type === 'password' ? 'text' : 'password';
    }

    function checkStrength(val) {
      const fill = document.getElementById('strengthFill');
      const label = document.getElementById('strengthLabel');
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
        h.textContent = '✗ Passwords do not match';
        h.style.color = 'var(--danger)';
      }
    }
  </script>
</body>

</html>