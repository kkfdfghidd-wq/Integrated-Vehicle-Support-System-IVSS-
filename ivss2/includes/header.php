<?php
// includes/header.php
// Usage: include this at the top of every page
// $pageTitle must be set before including
if (!isset($pageTitle)) $pageTitle = 'IVSS';
$isLoggedIn       = isLoggedIn();
$isGarageLoggedIn = isGarageLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — IVSS Oman</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
</head>

<body>

  <nav class="navbar">
    <a href="<?= SITE_URL ?>/index.php" class="logo">
      <div class="logo-icon"><img src="<?= SITE_URL ?>/images/ivss.jpg" alt="IVSS" style="width:44px; height:44px; object-fit:contain;"></div>
      <div class="logo-text"><span>IV</span>SS</div>
    </a>
    <div class="nav-links">
      <a href="<?= SITE_URL ?>/index.php#features">Features</a>
      <a href="<?= SITE_URL ?>/index.php#how">How it Works</a>
      <a href="<?= SITE_URL ?>/pages/garages.php">Find Garage</a>
      <?php if ($isLoggedIn): ?>
        <a href="<?= SITE_URL ?>/pages/dashboard.php">Dashboard</a>
        <a href="<?= SITE_URL ?>/pages/logout.php" class="nav-btn-outline">Logout</a>
      <?php elseif ($isGarageLoggedIn): ?>
        <a href="<?= SITE_URL ?>/pages/garage_dashboard.php">Garage Panel</a>
        <a href="<?= SITE_URL ?>/pages/logout.php" class="nav-btn-outline">Logout</a>
      <?php else: ?>
        <a href="<?= SITE_URL ?>/pages/login.php" class="nav-btn-outline">Login</a>
        <a href="<?= SITE_URL ?>/pages/register.php" class="nav-btn">Get Started</a>
      <?php endif; ?>
    </div>
  </nav>

  <!-- ══ Google Translate Widget ══ -->
  <div id="google_translate_wrapper" style="
  position:fixed;bottom:24px;left:24px;z-index:9999;
  display:flex;align-items:center;
  background:#fff;
  border:1.5px solid #e2e8f0;
  border-radius:50px;
  box-shadow:0 4px 20px rgba(0,0,0,0.12);
  overflow:hidden;
">
    <button onclick="toggleLang()" id="langBtn"
      style="display:flex;align-items:center;gap:8px;padding:10px 18px;
           background:none;border:none;cursor:pointer;font-size:14px;
           font-weight:700;color:#0a2540;white-space:nowrap;">
      <span style="font-size:18px;">🌐</span>
      <span id="langLabel">العربية</span>
    </button>
    <div id="google_translate_element" style="display:none;"></div>
  </div>

  <style>
    .goog-te-banner-frame,
    .skiptranslate {
      display: none !important;
    }

    body {
      top: 0 !important;
    }

    .goog-logo-link,
    .goog-te-gadget span {
      display: none !important;
    }

    .goog-te-gadget {
      font-size: 0 !important;
    }

    @media(max-width:480px) {
      #google_translate_wrapper {
        bottom: 16px;
        left: 16px;
      }
    }
  </style>

  <script>
    function googleTranslateElementInit() {
      new google.translate.TranslateElement({
        pageLanguage: 'en',
        includedLanguages: 'ar,en',
        autoDisplay: false
      }, 'google_translate_element');
    }

    var currentLang = 'en';

    function toggleLang() {
      var label = document.getElementById('langLabel');
      var btn = document.getElementById('langBtn');
      if (currentLang === 'en') {
        translateTo('ar');
        currentLang = 'ar';
        label.textContent = 'English';
        btn.style.direction = 'rtl';
      } else {
        translateTo('en');
        currentLang = 'en';
        label.textContent = 'العربية';
        btn.style.direction = 'ltr';
      }
    }

    function translateTo(lang) {
      var select = document.querySelector('.goog-te-combo');
      if (select) {
        select.value = lang;
        select.dispatchEvent(new Event('change'));
      } else {
        setTimeout(function() {
          translateTo(lang);
        }, 500);
      }
    }
  </script>
  <script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>