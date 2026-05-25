<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Home';
$db = getDB();

// Stats for hero section
$totalGarages  = $db->query("SELECT COUNT(*) FROM garages WHERE is_active=1")->fetchColumn();
$totalRequests = $db->query("SELECT COUNT(*) FROM service_requests")->fetchColumn();
$totalUsers    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$avgRating     = $db->query("SELECT AVG(rating) FROM garages WHERE rating > 0")->fetchColumn();

include 'includes/header.php';
?>

<!-- ── Hero ── -->
<div class="hero">
  <div class="hero-badge">🇴🇲 Built for Oman &nbsp;·&nbsp; Oman Vision 2040</div>
  <h1>Roadside Help,<br><span>Anytime. Anywhere.</span></h1>
  <p>Connect with certified garages across Oman in minutes. Real-time tracking, secure payments, and 24/7 emergency support — all in one platform.</p>
  <div class="hero-btns">
    <a href="pages/request.php" class="btn btn-primary">🔧 Request Help Now</a>
    <a href="#how" class="btn btn-secondary">See How it Works</a>
  </div>
</div>

<div class="stats-bar">
  <div class="stat-item">
    <div class="stat-num"><?= $totalGarages ?>+</div>
    <div class="stat-label">Certified Garages</div>
  </div>
  <div class="stat-item">
    <div class="stat-num">&lt; 8 min</div>
    <div class="stat-label">Avg. Response Time</div>
  </div>
  <div class="stat-item">
    <div class="stat-num">24/7</div>
    <div class="stat-label">Emergency Support</div>
  </div>
  <div class="stat-item">
    <div class="stat-num"><?= $avgRating ? number_format($avgRating, 1) . '★' : '4.7★' ?></div>
    <div class="stat-label">User Rating</div>
  </div>
</div>

<!-- ── Features ── -->
<section id="features">
  <div class="container">
    <div class="section-tag">Platform Features</div>
    <div class="section-title">Everything you need, in one place</div>
    <div class="section-sub">From GPS-based garage discovery to secure online payments — IVSS covers every step of your roadside emergency.</div>
    <div class="features-grid" style="margin-top:48px;">
      <?php
      $features = [
        ['📍','gold', 'GPS Garage Finder',       'Locate the nearest certified garage using real-time location services. View distance, rating, and availability instantly.'],
        ['🔧','teal', 'Service Request System',  'Request towing, tire change, battery assistance, or repairs — directly from the platform. Garages respond instantly.'],
        ['💳','gold', 'Secure Online Payment',   'Pay safely through the platform with auto-generated invoices. Multiple payment methods supported.'],
        ['📊','teal', 'Live Status Tracking',    'Track the status of your service request in real time. Know when help is confirmed, on the way, and arrived.'],
        ['🌐','navy', 'Arabic & English',        'Full bilingual support designed for drivers and garage owners across Oman. Switch languages anytime.'],
        ['⭐','gold', 'Ratings & Feedback',      'Rate your experience after each service. Help build a trusted network of quality garages across Oman.'],
      ];
      foreach ($features as [$icon,$color,$title,$desc]): ?>
      <div class="card card-hover feature-card">
        <div class="feature-icon icon-<?= $color ?>"><?= $icon ?></div>
        <h3><?= $title ?></h3>
        <p><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── How It Works ── -->
<section id="how" class="section-dark">
  <div class="container">
    <div class="section-tag">Simple Process</div>
    <div class="section-title">Help in 4 easy steps</div>
    <div class="steps-row">
      <?php
      $steps = [
        ['1','Sign Up',         'Create your account as a driver or garage owner in under 2 minutes'],
        ['2','Find a Garage',   'Browse nearby certified garages with ratings and available services'],
        ['3','Request Service', 'Select your issue, confirm location, and submit — garage is notified instantly'],
        ['4','Pay & Review',    'Pay securely online and rate your experience after the service'],
      ];
      foreach ($steps as [$n,$t,$d]): ?>
      <div class="step-box">
        <div class="step-circle"><?= $n ?></div>
        <h4><?= $t ?></h4>
        <p><?= $d ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center" style="margin-top:48px;">
      <a href="pages/register.php" class="btn btn-primary">Get Started Free</a>
    </div>
  </div>
</section>

<!-- ── Garages Listing ── -->
<section id="garages">
  <div class="container">
    <div class="section-tag">Network</div>
    <div class="section-title">Featured Garages</div>
    <div class="section-sub">Browse some of our certified service partners across Muscat.</div>

    <?php
    $garages = $db->query("SELECT * FROM garages WHERE is_active = 1 ORDER BY rating DESC LIMIT 4")->fetchAll();
    ?>
    <div class="features-grid" style="margin-top:40px;">
      <?php foreach ($garages as $g):
        $services = explode(',', $g['services'] ?? '');
      ?>
      <div class="card card-hover">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
          <div style="font-size:28px;">🔧</div>
          <span class="badge badge-accepted">Active</span>
        </div>
        <h3 style="font-size:17px;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($g['garage_name']) ?></h3>
        <p style="font-size:13px;color:var(--muted);margin-bottom:12px;">📍 <?= htmlspecialchars($g['location']) ?></p>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
          <span style="color:var(--gold);font-weight:700;">★ <?= number_format($g['rating'], 1) ?></span>
          <span style="font-size:12px;color:var(--muted);">📞 <?= htmlspecialchars($g['phone']) ?></span>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
          <?php foreach ($services as $s): ?>
          <span style="background:rgba(26,158,138,0.1);color:var(--teal);padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;"><?= ucfirst(trim($s)) ?></span>
          <?php endforeach; ?>
        </div>
        <a href="pages/request.php?garage_id=<?= $g['garage_id'] ?>" class="btn btn-dark btn-sm btn-full">Request Service</a>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-24">
      <a href="pages/garages.php" class="btn btn-teal">View All Garages →</a>
    </div>
  </div>
</section>

<!-- ── CTA ── -->
<section class="section-dark">
  <div class="container text-center">
    <div class="section-title">Ready to get started?</div>
    <p style="color:rgba(255,255,255,0.6);font-size:17px;max-width:500px;margin:16px auto 36px;">Join thousands of drivers across Oman who use IVSS for fast, reliable roadside assistance.</p>
    <div class="hero-btns">
      <a href="pages/register.php" class="btn btn-primary">Create Free Account</a>
      <a href="pages/garage_register.php" class="btn btn-secondary">Register Your Garage</a>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
