<?php
require_once __DIR__ . '/../includes/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/pages/login.php');

$db     = getDB();
$userId = $_SESSION['user_id'];

// Fetch current user
$user = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$user->execute([$userId]);
$user = $user->fetch();

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  /* ── Update Profile ── */
  if ($action === 'update_profile') {
    $name  = sanitize($_POST['full_name'] ?? '');
    $phone = $_POST['phone']     ?? '';

    if (empty($name))  $errors[] = 'Full name is required.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    elseif (strlen($phone) !== 8)      $errors[] = 'Phone number must be exactly 8 digits.';
    elseif (!preg_match('/^[7|9]/', $phone)) $errors[] = 'Phone number must start with 7 or 9.';

    //  Check if phone already exists for another user 
    if (empty($errors)) {
      $phoneCheck = $db->prepare(
        "SELECT user_id FROM users WHERE phone = ? AND user_id != ? LIMIT 1"
      );
      $phoneCheck->execute([$phone, $userId]);
      if ($phoneCheck->fetch()) {
        $errors[] = 'This phone number is already registered to another account.';
      }
    }

    if (empty($errors)) {
      $db->prepare("UPDATE users SET full_name=?, phone=? WHERE user_id=?")
        ->execute([$name, $phone, $userId]);
      $_SESSION['user_name'] = $name;
      $success = 'Profile updated successfully.';
      // Refresh user data
      $stmt = $db->prepare("SELECT * FROM users WHERE user_id=?");
      $stmt->execute([$userId]);
      $user = $stmt->fetch();
    }
  }

  /* ── Change Password ── */
  if ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) $errors[] = 'Current password is incorrect.';
    if (strlen($newPass) < 6)  $errors[] = 'New password must be at least 6 characters.';
    if ($newPass !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
      $hash = password_hash($newPass, PASSWORD_BCRYPT);
      $db->prepare("UPDATE users SET password=? WHERE user_id=?")->execute([$hash, $userId]);
      $success = 'Password changed successfully.';
    }
  }
}

// Request summary for sidebar stats
$totalReq = $db->prepare("SELECT COUNT(*) FROM service_requests WHERE user_id=?");
$totalReq->execute([$userId]);
$totalReq = $totalReq->fetchColumn();

$pageTitle = 'My Profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-layout">
  <!-- Sidebar -->
  <div class="dash-sidebar">
    <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:8px;">
      <div style="font-size:13px;color:rgba(255,255,255,0.4);">Logged in as</div>
      <div style="font-size:15px;font-weight:600;color:var(--white);margin-top:4px;"><?= htmlspecialchars($user['full_name']) ?></div>
    </div>
    <div class="sidebar-nav">
      <div class="sidebar-section">Driver Panel</div>
      <a href="dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="request.php"><span class="nav-icon">🚨</span> New Request</a>
      <a href="my_requests.php"><span class="nav-icon">📋</span> My Requests</a>
      <a href="garages.php"><span class="nav-icon">🔧</span> Find Garages</a>
      <div class="sidebar-section">Account</div>
      <a href="profile.php" class="active"><span class="nav-icon">👤</span> Profile</a>
      <a href="logout.php"><span class="nav-icon">🚪</span> Logout</a>
    </div>
  </div>

  <!-- Content -->
  <div class="dash-content">
    <div class="dash-header">
      <div>
        <div class="dash-title">My Profile</div>
        <div class="dash-sub">Manage your account information and security settings.</div>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= $success ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?= $e ?></div>
    <?php endforeach; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

      <!-- ── Profile Info Card ── -->
      <div class="card">
        <!-- Avatar -->
        <div style="text-align:center;margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid var(--border);">
          <div style="width:80px;height:80px;border-radius:50%;background:var(--navy);color:var(--gold);font-size:32px;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
          </div>
          <div style="font-size:18px;font-weight:700;"><?= htmlspecialchars($user['full_name']) ?></div>
          <div style="font-size:13px;color:var(--muted);margin-top:4px;"><?= htmlspecialchars($user['email']) ?></div>
          <div style="margin-top:10px;">
            <span class="badge badge-accepted">Driver Account</span>
          </div>
        </div>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
          <div style="background:var(--bg);border-radius:8px;padding:14px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:var(--navy);"><?= $totalReq ?></div>
            <div style="font-size:12px;color:var(--muted);margin-top:2px;">Total Requests</div>
          </div>
          <div style="background:var(--bg);border-radius:8px;padding:14px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:var(--teal);">
              <?php
              $done = $db->prepare("SELECT COUNT(*) FROM service_requests WHERE user_id=? AND status='completed'");
              $done->execute([$userId]);
              echo $done->fetchColumn();
              ?>
            </div>
            <div style="font-size:12px;color:var(--muted);margin-top:2px;">Completed</div>
          </div>
        </div>

        <div style="font-size:13px;color:var(--muted);text-align:center;">
          Member since <?= date('d M Y', strtotime($user['created_at'])) ?>
        </div>
      </div>

      <!-- ── Edit Forms ── -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Update Profile -->
        <div class="card">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;">✏️ Edit Profile</h3>
          <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
              <label>Full Name</label>
              <input type="text" name="full_name" class="form-control"
                value="<?= htmlspecialchars($user['full_name']) ?>" required>
            </div>
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" class="form-control"
                value="<?= htmlspecialchars($user['email']) ?>" disabled
                style="background:var(--bg);cursor:not-allowed;">
              <div class="form-hint">Email cannot be changed.</div>
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="text" name="phone" class="form-control"
                maxlength="8"
                pattern="[7|9][0-9]{7}"
                inputmode="numeric"
                onkeydown="return /[0-9]|Backspace|Delete|ArrowLeft|ArrowRight|Tab/.test(event.key)"
                onpaste="event.preventDefault();var t=event.clipboardData.getData('text').replace(/\D/g,'').slice(0,8);this.value=t;"
                oninput="this.value=this.value.replace(/\D/g,'').slice(0,8)"
                value="<?= htmlspecialchars($user['phone']) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
          </form>
        </div>

        <!-- Change Password -->
        <div class="card">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;">🔒 Change Password</h3>
          <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
              <label>Current Password</label>
              <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
            </div>
            <div class="form-group">
              <label>New Password</label>
              <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters" required>
            </div>
            <div class="form-group">
              <label>Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
            </div>
            <button type="submit" class="btn btn-dark btn-sm">Change Password</button>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>