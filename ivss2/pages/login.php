<?php
require_once __DIR__ . '/../includes/config.php';
if (isLoggedIn())       redirect(SITE_URL . '/pages/dashboard.php');
if (isGarageLoggedIn()) redirect(SITE_URL . '/pages/garage_dashboard.php');

// ── Constants ──
define('MAX_ATTEMPTS',   3);
define('LOCKOUT_MINUTES', 5);

$error      = '';
$lockoutMsg = '';
$role       = $_GET['role'] ?? 'user';
$db         = getDB();

// ── Helpers ──
function getAttemptCount(PDO $db, string $identifier, string $ip): int
{
  $since = date('Y-m-d H:i:s', strtotime('-' . LOCKOUT_MINUTES . ' minutes'));
  $s = $db->prepare("SELECT COUNT(*) FROM login_attempts
                        WHERE identifier = ? AND ip_address = ? AND attempted_at >= ?");
  $s->execute([$identifier, $ip, $since]);
  return (int) $s->fetchColumn();
}

function recordAttempt(PDO $db, string $identifier, string $ip): void
{
  $db->prepare("INSERT INTO login_attempts (identifier, ip_address) VALUES (?,?)")
    ->execute([$identifier, $ip]);
  // Clean up old attempts
  $old = date('Y-m-d H:i:s', strtotime('-1 hour'));
  $db->prepare("DELETE FROM login_attempts WHERE attempted_at < ?")->execute([$old]);
}

function clearAttempts(PDO $db, string $identifier, string $ip): void
{
  $db->prepare("DELETE FROM login_attempts WHERE identifier = ? AND ip_address = ?")
    ->execute([$identifier, $ip]);
}

function getSecondsUntilUnlock(PDO $db, string $identifier, string $ip): int
{
  $since = date('Y-m-d H:i:s', strtotime('-' . LOCKOUT_MINUTES . ' minutes'));
  $s = $db->prepare("SELECT MIN(attempted_at) FROM login_attempts
                        WHERE identifier = ? AND ip_address = ? AND attempted_at >= ?");
  $s->execute([$identifier, $ip, $since]);
  $earliest = $s->fetchColumn();
  if (!$earliest) return 0;
  $unlockAt = strtotime($earliest) + (LOCKOUT_MINUTES * 60);
  return max(0, $unlockAt - time());
}

// ── Process Login ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email      = sanitize($_POST['email'] ?? '');
  $password   = $_POST['password']       ?? '';
  $role       = $_POST['role']           ?? 'user';
  $ip         = $_SERVER['REMOTE_ADDR']  ?? '0.0.0.0';
  $identifier = $email . '|' . $role;

  // Check lockout
  $attempts = getAttemptCount($db, $identifier, $ip);
  if ($attempts >= MAX_ATTEMPTS) {
    $secs       = getSecondsUntilUnlock($db, $identifier, $ip);
    $mins       = ceil($secs / 60);
    $lockoutMsg = "Too many failed attempts. Please wait <strong>{$mins} minute(s)</strong> before trying again.";
  } else {
    $loginOk = false;

    if ($role === 'user') {
      $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
      $stmt->execute([$email]);
      $user = $stmt->fetch();
      if ($user && password_verify($password, $user['password'])) {
        clearAttempts($db, $identifier, $ip);
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['user_name'] = $user['full_name'];
        redirect(SITE_URL . '/pages/dashboard.php');
      }
    } elseif ($role === 'garage') {
      $stmt = $db->prepare("SELECT * FROM garages WHERE email = ? AND is_active = 1");
      $stmt->execute([$email]);
      $garage = $stmt->fetch();
      if ($garage && password_verify($password, $garage['password'])) {
        clearAttempts($db, $identifier, $ip);
        $_SESSION['garage_id']   = $garage['garage_id'];
        $_SESSION['garage_name'] = $garage['garage_name'];
        redirect(SITE_URL . '/pages/garage_dashboard.php');
      }
    } elseif ($role === 'admin') {
      $stmt = $db->prepare("SELECT * FROM admins WHERE email = ?");
      $stmt->execute([$email]);
      $admin = $stmt->fetch();
      if ($admin && password_verify($password, $admin['password'])) {
        clearAttempts($db, $identifier, $ip);
        $_SESSION['admin_id']   = $admin['admin_id'];
        $_SESSION['admin_name'] = $admin['name'];
        redirect(SITE_URL . '/admin/dashboard.php');
      }
    }

    // If we reach here, login failed
    recordAttempt($db, $identifier, $ip);
    $remaining = MAX_ATTEMPTS - getAttemptCount($db, $identifier, $ip);
    if ($remaining <= 0) {
      $lockoutMsg = "Account locked for <strong>" . LOCKOUT_MINUTES . " minutes</strong> due to too many failed attempts.";
    } else {
      $error = "Invalid email or password. <strong>{$remaining} attempt(s)</strong> remaining before lockout.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — IVSS</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
  <style>
    .login-page {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 1fr;
    }

    .login-left {
      background: var(--navy);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 44px 48px;
      position: relative;
      overflow: hidden;
    }

    .login-left::before {
      content: '';
      position: absolute;
      width: 460px;
      height: 460px;
      border: 1px solid rgba(212, 168, 67, 0.12);
      border-radius: 50%;
      top: -120px;
      right: -120px;
      pointer-events: none;
    }

    .login-left::after {
      content: '';
      position: absolute;
      width: 320px;
      height: 320px;
      border: 1px solid rgba(212, 168, 67, 0.07);
      border-radius: 50%;
      bottom: -90px;
      left: -90px;
      pointer-events: none;
    }

    .left-content {
      position: relative;
      z-index: 1;
    }

    .left-logo {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      margin-bottom: 64px;
    }

    .left-logo .l-icon {
      width: 44px;
      height: 44px;
      background: var(--gold);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
    }

    .left-logo .l-text {
      color: var(--white);
      font-size: 22px;
      font-weight: 700;
    }

    .left-logo .l-text span {
      color: var(--gold);
    }

    .left-heading {
      color: var(--white);
      font-size: 36px;
      font-weight: 700;
      line-height: 1.2;
      margin-bottom: 16px;
    }

    .left-heading em {
      color: var(--gold);
      font-style: normal;
    }

    .left-sub {
      color: rgba(255, 255, 255, 0.55);
      font-size: 15px;
      line-height: 1.75;
      max-width: 340px;
      margin-bottom: 48px;
    }

    .left-features {
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .left-feat {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .feat-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      background: rgba(212, 168, 67, 0.1);
      border: 1px solid rgba(212, 168, 67, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }

    .feat-text {
      font-size: 14px;
      color: rgba(255, 255, 255, 0.65);
      line-height: 1.4;
    }

    .left-footer {
      position: relative;
      z-index: 1;
      color: rgba(255, 255, 255, 0.28);
      font-size: 13px;
    }

    .login-right {
      background: var(--bg);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px 40px;
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
    }

    .back-link:hover {
      color: var(--navy);
      border-color: var(--navy);
      transform: translateX(-2px);
    }

    .form-wrap {
      width: 100%;
      max-width: 400px;
    }

    .form-title {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .form-subtitle {
      font-size: 14px;
      color: var(--muted);
      margin-bottom: 32px;
    }

    .role-tabs {
      display: flex;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 28px;
      background: var(--white);
    }

    .role-tab {
      flex: 1;
      text-align: center;
      padding: 11px 6px;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      color: var(--muted);
      border-right: 1.5px solid var(--border);
      transition: all 0.2s;
    }

    .role-tab:last-child {
      border-right: none;
    }

    .role-tab.active {
      background: var(--navy);
      color: var(--white);
    }

    .role-tab:hover:not(.active) {
      background: var(--bg);
      color: var(--text);
    }

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
      padding-right: 42px;
      background: var(--white);
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

    .or-line {
      display: flex;
      align-items: center;
      gap: 12px;
      color: var(--muted);
      font-size: 13px;
      margin: 20px 0;
    }

    .or-line::before,
    .or-line::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    .demo-box {
      background: var(--white);
      border: 1.5px dashed var(--border);
      border-radius: 10px;
      padding: 14px 18px;
      margin-top: 20px;
    }

    .demo-title {
      font-size: 11px;
      font-weight: 700;
      color: var(--muted);
      letter-spacing: 1.2px;
      text-transform: uppercase;
      margin-bottom: 10px;
    }

    .demo-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 5px 0;
      border-bottom: 1px solid var(--bg);
    }

    .demo-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }

    .demo-role {
      font-size: 12px;
      color: var(--muted);
      font-weight: 500;
    }

    .demo-email {
      font-size: 12px;
      color: var(--teal);
      font-weight: 600;
      cursor: pointer;
      text-decoration: underline;
    }

    /* Lockout UI */
    .lockout-box {
      background: rgba(226, 75, 74, 0.08);
      border: 1.5px solid var(--danger);
      border-radius: 10px;
      padding: 18px 20px;
      margin-bottom: 20px;
      text-align: center;
    }

    .lockout-icon {
      font-size: 36px;
      margin-bottom: 10px;
    }

    .lockout-title {
      font-size: 16px;
      font-weight: 700;
      color: var(--danger);
      margin-bottom: 6px;
    }

    .lockout-msg {
      font-size: 13px;
      color: var(--text);
      line-height: 1.6;
    }

    .attempt-dots {
      display: flex;
      gap: 8px;
      justify-content: center;
      margin: 16px 0 8px;
    }

    .attempt-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
    }

    .dot-used {
      background: var(--danger);
    }

    .dot-left {
      background: var(--border);
    }

    @media (max-width:768px) {
      .login-page {
        grid-template-columns: 1fr;
      }

      .login-left {
        display: none;
      }

      .login-right {
        padding: 80px 24px 40px;
      }
    }
  </style>
</head>

<body>
  <div class="login-page">

    <!-- LEFT -->
    <div class="login-left">
      <div class="left-content">
        <a href="<?= SITE_URL ?>/index.php" class="left-logo">
          <div class="logo-icon">
            <img src="<?= SITE_URL ?>/images/ivss.jpg"
              alt="IVSS"
              style="width:44px; height:44px; object-fit:contain;">
          </div>
          <div class="l-text"><span>IV</span>SS</div>
        </a>
        <div class="left-heading">Welcome<br>Back to <em>IVSS</em></div>
        <p class="left-sub">Oman's trusted platform for roadside vehicle assistance. Fast, reliable, and available 24/7.</p>
        <div class="left-features">
          <div class="left-feat">
            <div class="feat-icon">📍</div>
            <div class="feat-text">Find certified garages near your location instantly</div>
          </div>
          <div class="left-feat">
            <div class="feat-icon">⚡</div>
            <div class="feat-text">Get help in under 8 minutes, any time of day</div>
          </div>
          <div class="left-feat">
            <div class="feat-icon">💳</div>
            <div class="feat-text">Secure online payments with auto-generated invoices</div>
          </div>
          <div class="left-feat">
            <div class="feat-icon">🔒</div>
            <div class="feat-text">Account protected with login attempt security</div>
          </div>
        </div>
      </div>
      <div class="left-footer">© <?= date('Y') ?> IVSS — Integrated Vehicle Support System 🇴🇲</div>
    </div>

    <!-- RIGHT -->
    <div class="login-right">
      <a href="<?= SITE_URL ?>/index.php" class="back-link">← Back to Home</a>

      <div class="form-wrap">
        <div class="form-title">Sign In</div>
        <div class="form-subtitle">Choose your account type and enter your credentials.</div>

        <?php if (isset($_GET['registered'])): ?>
          <div class="alert alert-success">✅ Account created! You can now sign in.</div>
        <?php endif; ?>

        <!-- Lockout Box -->
        <?php if ($lockoutMsg): ?>
          <div class="lockout-box">
            <div class="lockout-icon">🔒</div>
            <div class="lockout-title">Account Temporarily Locked</div>
            <div class="lockout-msg"><?= $lockoutMsg ?></div>
            <div style="font-size:12px;color:var(--muted);margin-top:10px;">
              For your security, accounts are locked for <?= LOCKOUT_MINUTES ?> minutes after <?= MAX_ATTEMPTS ?> failed attempts.
            </div>
          </div>
        <?php elseif ($error): ?>
          <?php
          $usedAttempts = 0;
          if (!empty($_POST['email']) && !empty($_POST['role'])) {
            $usedAttempts = getAttemptCount($db, $_POST['email'] . '|' . $_POST['role'], $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
          }
          ?>
          <div class="alert alert-danger">⚠️ <?= $error ?></div>
          <!-- Attempt Dots -->
          <div class="attempt-dots">
            <?php for ($i = 0; $i < MAX_ATTEMPTS; $i++): ?>
              <div class="attempt-dot <?= $i < $usedAttempts ? 'dot-used' : 'dot-left' ?>"></div>
            <?php endfor; ?>
          </div>
          <div style="text-align:center;font-size:12px;color:var(--muted);margin-bottom:16px;">
            <?= $usedAttempts ?>/<?= MAX_ATTEMPTS ?> attempts used
          </div>
        <?php endif; ?>

        <!-- Role Tabs -->
        <div class="role-tabs">
          <?php foreach (['user' => ['🚗', 'Driver'], 'garage' => ['🔧', 'Garage'], 'admin' => ['⚙️', 'Admin']] as $r => [$icon, $label]): ?>
            <a href="?role=<?= $r ?>" class="role-tab <?= $role === $r ? 'active' : '' ?>"><?= $icon ?> <?= $label ?></a>
          <?php endforeach; ?>
        </div>

        <!-- Form (disabled if locked out) -->
        <form method="POST" <?= $lockoutMsg ? 'style="opacity:0.5;pointer-events:none;"' : '' ?>>
          <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
          <div class="form-group">
            <label>Email Address</label>
            <div class="input-wrap">
              <span class="i-icon">✉️</span>
              <input type="email" name="email" class="form-control"
                placeholder="you@example.com"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                id="emailInp" required <?= $lockoutMsg ? 'disabled' : '' ?>>
            </div>
          </div>
          <div class="form-group">
            <label>Password</label>
            <div class="input-wrap">
              <span class="i-icon">🔒</span>
              <input type="password" name="password" class="form-control"
                placeholder="Your password" id="passInp"
                required <?= $lockoutMsg ? 'disabled' : '' ?>>
              <button type="button" class="pass-eye" onclick="togglePass()">👁️</button>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-full"
            style="padding:14px;font-size:16px;margin-top:6px;"
            <?= $lockoutMsg ? 'disabled' : '' ?>>
            <?= $lockoutMsg ? '🔒 Locked' : 'Sign In →' ?>
          </button>
        </form>

        <?php if ($role !== 'admin' && !$lockoutMsg): ?>
          <div class="or-line">or</div>
          <p style="text-align:center;font-size:14px;color:var(--muted);">
            Don't have an account?
            <a href="<?= $role === 'garage' ? 'garage_register.php' : 'register.php' ?>"
              style="color:var(--teal);font-weight:700;text-decoration:none;margin-left:4px;">
              Create one free →
            </a>
          </p>
        <?php endif; ?>

        <!-- Demo Credentials -->
        <div class="demo-box">
          <div class="demo-title">🧪 Demo Accounts — password: <code>password</code></div>
          <div class="demo-row">
            <span class="demo-role">🚗 Driver</span>
            <span class="demo-email" onclick="autofill('driver1@ivss.om')">driver1@ivss.om — click to fill</span>
          </div>
          <div class="demo-row">
            <span class="demo-role">🔧 Garage</span>
            <span class="demo-email" onclick="autofill('alwadi@ivss.om')">alwadi@ivss.om — click to fill</span>
          </div>
          <div class="demo-row">
            <span class="demo-role">⚙️ Admin</span>
            <span class="demo-email" onclick="autofill('admin@ivss.om')">admin@ivss.om — click to fill</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function togglePass() {
      const p = document.getElementById('passInp');
      p.type = p.type === 'password' ? 'text' : 'password';
    }

    function autofill(email) {
      document.getElementById('emailInp').value = email;
      document.getElementById('passInp').value = 'password';
    }
    <?php if ($lockoutMsg): ?>
      // Auto-countdown timer
      let secs = <?= getSecondsUntilUnlock($db, ($_POST['email'] ?? '') . '|' . ($_POST['role'] ?? $role), $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') ?>;
      const timer = setInterval(() => {
        secs--;
        if (secs <= 0) {
          clearInterval(timer);
          location.reload();
        }
      }, 1000);
    <?php endif; ?>
  </script>
</body>

</html>